import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { useAuth } from '../../../hooks/useAuth';
import { adminAmenityService, parseAdminAmenityError } from '../../../services/adminAmenityService';
import AdminAmenityStatusDialog from './AdminAmenityStatusDialog';

const empty = { name: '', icon: '', description: '', status: true };
const fields = [
  { key: 'name', label: 'Tên tiện nghi' },
  { key: 'icon', label: 'Mã icon' },
  { key: 'description', label: 'Mô tả' },
  { key: 'status', label: 'Trạng thái' },
];
const button = 'min-h-10 rounded-lg border border-border px-4 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-50';
const input = 'w-full min-w-0 rounded-lg border border-border bg-background px-3 py-2 focus-visible:outline-2 focus-visible:outline-primary';
const statusLabel = (status) => status ? 'Đang hoạt động' : 'Ngưng sử dụng';

function editValues(row) {
  return { name: row.name, icon: row.icon ?? '', description: row.description ?? '', status: row.status };
}

export default function AdminAmenityFormDialog({ itemId, desiredStatus, trigger, onClose, onSaved }) {
  const { user, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const [readVersion, setReadVersion] = useState(0);
  const [read, setRead] = useState(null);
  const [draft, setDraft] = useState(null);
  const [problem, setProblem] = useState(null);
  const [saving, setSaving] = useState(false);
  const [checking, setChecking] = useState(false);
  const [creationCheck, setCreationCheck] = useState('');
  const closeButton = useRef(null);
  const writeRequest = useRef(null);
  const checkRequest = useRef(null);
  const creating = itemId === undefined;
  const changingStatus = typeof desiredStatus === 'boolean';

  useEffect(() => () => {
    writeRequest.current?.abort();
    checkRequest.current?.abort();
  }, []);

  useEffect(() => {
    if (itemId === undefined) return;
    const snapshot = captureProfileAuth();
    if (!isProfileAuthCurrent(snapshot)) return;
    const controller = new AbortController();
    adminAmenityService.get(itemId, { signal: controller.signal }).then((row) => {
      if (!controller.signal.aborted && isProfileAuthCurrent(snapshot)) {
        setRead({ row, snapshot, readVersion });
      }
    }, (failure) => {
      const error = parseAdminAmenityError(failure);
      if (!controller.signal.aborted && !error.isCancelled && isProfileAuthCurrent(snapshot)) {
        setRead({ error, snapshot, readVersion });
      }
    });
    return () => controller.abort();
  }, [itemId, readVersion, user, captureProfileAuth, isProfileAuthCurrent]);

  const current = read?.readVersion === readVersion && isProfileAuthCurrent(read.snapshot) ? read : null;
  const row = current?.row;
  const values = draft ?? (row ? editValues(row) : empty);
  const loading = !creating && !current;
  const waitingForReview = problem?.outcomeUnknown || problem?.code === 'ADMIN_AMENITY_STALE';
  const reviewed = waitingForReview && row && readVersion > problem.atRead;
  const alreadySet = changingStatus && row?.status === desiredStatus;
  const canSubmit = (creating || row) && !saving && !alreadySet && (!waitingForReview || reviewed);

  async function save(event) {
    event.preventDefault();
    if (!canSubmit || writeRequest.current) return;
    const snapshot = captureProfileAuth();
    if (!isProfileAuthCurrent(snapshot)) return;

    const controller = new AbortController();
    writeRequest.current = controller;
    const payload = creating ? values : {
      ...(changingStatus ? editValues(row) : values),
      status: changingStatus ? desiredStatus : row.status,
    };
    setSaving(true);
    setProblem(null);
    try {
      const result = creating
        ? await adminAmenityService.create(payload, { signal: controller.signal })
        : await adminAmenityService.update(itemId, { ...payload, version: row.version }, { signal: controller.signal });
      if (!controller.signal.aborted && isProfileAuthCurrent(snapshot)) onSaved(result);
    } catch (failure) {
      if (!controller.signal.aborted && isProfileAuthCurrent(snapshot)) {
        setProblem({ ...parseAdminAmenityError(failure), atRead: readVersion });
      }
    } finally {
      writeRequest.current = null;
      if (!controller.signal.aborted) setSaving(false);
    }
  }

  async function checkCreation() {
    if (checkRequest.current) return;
    const snapshot = captureProfileAuth();
    if (!isProfileAuthCurrent(snapshot)) return;
    const controller = new AbortController();
    checkRequest.current = controller;
    const name = values.name.trim();
    setChecking(true);
    setCreationCheck('');
    try {
      const page = await adminAmenityService.list({ q: [...name].slice(0, 100).join(''), per_page: 50 }, { signal: controller.signal });
      if (controller.signal.aborted || !isProfileAuthCurrent(snapshot)) return;
      const matches = page.data.filter((item) => item.name.trim() === name);
      setCreationCheck(matches.length
        ? 'Đã thấy tiện nghi cùng tên trong danh sách (' + matches.map(({ id }) => '#' + id).join(', ') + '). Hãy mở danh sách để đối chiếu thông tin.'
        : 'Chưa thấy tên này trong trang kết quả đã tải. Hãy đóng hộp thoại và kiểm tra danh sách trước khi tạo lại.');
    } catch (failure) {
      const error = parseAdminAmenityError(failure);
      if (!controller.signal.aborted && !error.isCancelled && isProfileAuthCurrent(snapshot)) setCreationCheck(error.message);
    } finally {
      checkRequest.current = null;
      if (!controller.signal.aborted) setChecking(false);
    }
  }

  function update(field, value) {
    setDraft({ ...values, [field]: value });
    if (!waitingForReview) setProblem(null);
  }

  function fieldProps(key) {
    return { id: 'amenity-' + key, className: input,
      'aria-invalid': Boolean(problem?.fields?.[key]),
      'aria-describedby': problem?.fields?.[key] ? 'amenity-error-' + key : undefined };
  }

  return <Dialog.Root open onOpenChange={(open) => { if (!open && !writeRequest.current) onClose(); }}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm" />
      <Dialog.Content className="fixed left-1/2 top-1/2 z-50 w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-border bg-background shadow-xl"
        onOpenAutoFocus={(event) => { event.preventDefault(); closeButton.current?.focus(); }}
        onCloseAutoFocus={(event) => {
          event.preventDefault();
          (trigger?.isConnected ? trigger : document.getElementById('admin-amenities-title'))?.focus();
        }}>
        <form noValidate onSubmit={save} className="flex max-h-[90dvh] flex-col">
          <div className="shrink-0 border-b border-border p-4 sm:p-6">
            {changingStatus ? <AdminAmenityStatusDialog amenity={row} desiredStatus={desiredStatus} /> : <>
              <Dialog.Title className="text-lg font-semibold">{creating ? 'Thêm tiện nghi' : 'Sửa tiện nghi'}</Dialog.Title>
              <Dialog.Description className="mt-2 text-sm text-muted-foreground">
                Tiện nghi này dùng chung cho các cơ sở lưu trú.
              </Dialog.Description>
            </>}
          </div>
          <div className="min-h-0 overflow-y-auto p-4 sm:p-6" aria-busy={loading || saving}>
            {loading && <p role="status">Đang tải dữ liệu tiện nghi…</p>}
            {current?.error && <div role="alert" className="space-y-2">
              <p>{current.error.message}</p>
              {![401, 403, 404].includes(current.error.status) &&
                <button className={button} type="button" onClick={() => setReadVersion((n) => n + 1)}>Thử lại</button>}
            </div>}
            {problem && !reviewed && <div role="alert" className="mb-4 rounded-lg border border-destructive/30 p-3">
              <p>{problem.message}</p>
              {waitingForReview && creating &&
                <button type="button" className={button + ' mt-2'} onClick={checkCreation} disabled={checking}>
                  {checking ? 'Đang kiểm tra…' : 'Kiểm tra danh sách'}
                </button>}
              {waitingForReview && !creating && !loading &&
                <button type="button" className={button + ' mt-2'} onClick={() => setReadVersion((n) => n + 1)}>Tải dữ liệu mới</button>}
            </div>}
            {creationCheck && <p role="status" className="mb-4 text-sm">{creationCheck}</p>}
            {reviewed && <div role="status" className="mb-4 space-y-2 rounded-lg border border-border bg-muted p-3 text-sm">
              <p className="font-medium">Dữ liệu mới nhất trên máy chủ</p>
              {fields.map(({ key, label }) => <p key={key} className="whitespace-pre-wrap [overflow-wrap:anywhere]">
                {label}{' — '}{key === 'status' ? statusLabel(row.status) : row[key] || 'Chưa cập nhật'}
              </p>)}
              {!changingStatus && <p>Hãy đối chiếu với bản nháp bên dưới trước khi lưu.</p>}
            </div>}
            {alreadySet && <p role="status">Tiện nghi đã ở trạng thái {statusLabel(desiredStatus).toLowerCase()}.</p>}
            {!changingStatus && (creating || row) && <fieldset disabled={saving || Boolean(creating && waitingForReview)} className="grid gap-4">
              {fields.filter(({ key }) => creating || key !== 'status').map(({ key, label }) => <label key={key} htmlFor={'amenity-' + key} className="grid gap-1.5 text-sm font-medium">
                {label}
                {key === 'status' ? <select {...fieldProps(key)} value={String(values.status)} onChange={(e) => update(key, e.target.value === 'true')}>
                  <option value="true">Đang hoạt động</option>
                  <option value="false">Ngưng sử dụng</option>
                </select> : key === 'description' ? <textarea {...fieldProps(key)} rows={3} value={values[key]} onChange={(e) => update(key, e.target.value)} />
                  : <input {...fieldProps(key)} value={values[key]} required={key === 'name'} onChange={(e) => update(key, e.target.value)} />}
                {problem?.fields?.[key] && <span id={'amenity-error-' + key} className="text-destructive">
                  {problem.fields[key].join(' ')}
                </span>}
              </label>)}
            </fieldset>}
          </div>
          <footer className="flex shrink-0 flex-wrap justify-end gap-2 border-t border-border p-4">
            <Dialog.Close asChild><button ref={closeButton} type="button" className={button} disabled={saving}>Đóng</button></Dialog.Close>
            {alreadySet ? <button type="button" className={button} onClick={() => onSaved(row, false)}>Về danh sách</button>
              : <button type="submit" className={button + ' bg-primary text-primary-foreground'} disabled={!canSubmit}>
                {saving ? 'Đang lưu…' : changingStatus ? 'Xác nhận' : 'Lưu tiện nghi'}
              </button>}
          </footer>
        </form>
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>;
}
