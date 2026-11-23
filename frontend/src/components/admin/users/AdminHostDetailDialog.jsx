import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { useAuth } from '../../../hooks/useAuth';
import { getAdminHost, parseAdminHostError } from '../../../services/adminHostService';
import { hostStatuses } from '../../../mappers/adminHostMapper';

const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const genders = { MALE: 'Nam', FEMALE: 'Nữ', OTHER: 'Khác' };
const buttonClass = 'cursor-pointer rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary';
const display = (value) => value === null || value === '' ? 'Chưa cập nhật' : value;
const displayDate = (value) => value === null ? 'Chưa cập nhật' : dateFormat.format(new Date(value));
const displayBirthday = (value) => value === null ? 'Chưa cập nhật' : value.split('-').reverse().join('/');

export default function AdminHostDetailDialog({ hostId, trigger, onClose, onReloadList }) {
  const { user, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const closeRef = useRef(null);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  useEffect(() => {
    const auth = captureProfileAuth();
    const controller = new AbortController();
    let disposed = false;
    if (isProfileAuthCurrent(auth)) {
      getAdminHost(hostId, { signal: controller.signal }).then((host) => {
        if (!disposed && isProfileAuthCurrent(auth)) setView({ host, error: null, hostId, revision, auth });
      }, (failure) => {
        const error = parseAdminHostError(failure);
        if (!disposed && isProfileAuthCurrent(auth) && !error.isCancelled) setView({ host: null, error, hostId, revision, auth });
      });
    }
    return () => { disposed = true; controller.abort(); };
  }, [hostId, revision, user, captureProfileAuth, isProfileAuthCurrent]);

  const visible = view?.hostId === hostId && view?.revision === revision && isProfileAuthCurrent(view.auth) ? view : null;
  const loading = visible === null;
  const host = visible?.host;
  const error = visible?.error;
  const fields = host ? [
    ['Mã Host', `#${host.id}`], ['Họ tên', host.name], ['Email', host.email],
    ['Điện thoại', display(host.phone)], ['Giới tính', host.gender === null ? 'Chưa cập nhật' : genders[host.gender]],
    ['Ngày sinh', displayBirthday(host.date_of_birth)], ['Trạng thái', hostStatuses[host.status]],
    ['Ngày tạo', displayDate(host.created_at)], ['Ngày cập nhật', displayDate(host.updated_at)],
    ['Số cơ sở', new Intl.NumberFormat('vi-VN').format(host.properties_count)],
    ['Địa chỉ', display(host.address)],
  ] : [];

  return <Dialog.Root open onOpenChange={(open) => { if (!open) onClose(); }}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm" />
      <Dialog.Content className="fixed left-1/2 top-1/2 z-50 flex max-h-[90dvh] w-[92vw] max-w-2xl -translate-x-1/2 -translate-y-1/2 flex-col rounded-2xl border border-border bg-background shadow-xl"
        onOpenAutoFocus={(event) => { event.preventDefault(); closeRef.current?.focus(); }}
        onCloseAutoFocus={(event) => {
          event.preventDefault();
          (trigger?.isConnected ? trigger : document.getElementById('admin-hosts-title'))?.focus();
        }}>
        <header className="shrink-0 border-b border-border p-5 sm:p-6">
          <Dialog.Title className="text-xl font-bold">Chi tiết Host</Dialog.Title>
          <Dialog.Description className="mt-2 text-sm text-muted-foreground">Thông tin tài khoản Host #{hostId}.</Dialog.Description>
        </header>
        <div className="min-h-24 overflow-y-auto p-5 sm:p-6" aria-busy={loading} aria-live="polite">
          {loading && <p role="status" className="py-4 text-center text-muted-foreground">Đang tải thông tin Host…</p>}
          {error && <div role="alert" className="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
            <p>{error.message}</p>
            {Object.keys(error.fields ?? {}).length > 0 && <ul className="mt-2 list-inside list-disc">
              {Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}
            </ul>}
            {![401, 403, 404].includes(error.status) && <button type="button" className={`${buttonClass} mt-3`} onClick={() => setRevision((previous) => previous + 1)}>Thử lại</button>}
            {error.isNotFound && <button type="button" className={`${buttonClass} mt-3`} onClick={() => { onClose(); onReloadList(); }}>Đóng và tải lại danh sách</button>}
          </div>}
          {host && <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
            {fields.map(([label, value]) => <div key={label} className={label === 'Địa chỉ' ? 'sm:col-span-2' : ''}>
              <dt className="text-sm text-muted-foreground">{label}</dt>
              <dd className="mt-1 whitespace-pre-wrap break-words font-medium [overflow-wrap:anywhere]">{value}</dd>
            </div>)}
          </dl>}
        </div>
        <footer className="flex shrink-0 justify-end border-t border-border px-5 py-4 sm:px-6">
          <Dialog.Close asChild><button ref={closeRef} type="button" className={buttonClass}>Đóng</button></Dialog.Close>
        </footer>
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>;
}
