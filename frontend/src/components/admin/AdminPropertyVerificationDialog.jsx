import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { Download, FileCheck2, MapPin } from 'lucide-react';
import { normalizeApiError } from '../../api/apiError.js';
import { useAuth } from '../../hooks/useAuth';
import { downloadAdminPropertyVerification, getAdminPropertyVerification, reviewAdminPropertyVerification } from '../../services/adminPropertyService.js';
import { Button } from '../ui/button.jsx';

const statusDisplay = {
  SUBMITTED: ['Chờ xem xét', 'border-amber-200 bg-amber-50 text-amber-900'],
  APPROVED: ['Đã duyệt', 'border-emerald-200 bg-emerald-50 text-emerald-900'],
  REJECTED: ['Đã từ chối', 'border-rose-200 bg-rose-50 text-rose-900'],
  INVALIDATED: ['Cần xác minh lại', 'border-amber-200 bg-amber-50 text-amber-900'],
};
const formatDate = new Intl.DateTimeFormat('vi-VN', {
  dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh',
});

function saveDownload({ blob, filename }) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.append(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function VerificationDetails({ propertyId }) {
  const { captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const [attempt, setAttempt] = useState(0);
  const [view, setView] = useState(null);
  const [decision, setDecision] = useState(null);
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(null);
  const operation = useRef(null);
  const confirmationFocus = useRef(null);
  const actionFocus = useRef(null);
  const approveButton = useRef(null);
  const rejectButton = useRef(null);
  const resultFocus = useRef(null);

  useEffect(() => {
    const controller = new AbortController();
    const auth = captureProfileAuth();
    const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
    if (current()) {
      getAdminPropertyVerification(propertyId, { signal: controller.signal }).then(
        (entry) => { if (current()) setView({ attempt, auth, entry }); },
        (failure) => {
          const error = normalizeApiError(failure);
          if (current() && !error.isCancelled) setView({ attempt, auth, error });
        },
      );
    }
    return () => controller.abort();
  }, [propertyId, attempt, captureProfileAuth, isProfileAuthCurrent]);

  useEffect(() => () => operation.current?.abort(), []);
  useEffect(() => {
    if (decision) confirmationFocus.current?.focus();
    else if (actionFocus.current) {
      (actionFocus.current.current ?? resultFocus.current)?.focus();
      actionFocus.current = null;
    }
  }, [decision]);

  const visible = view?.attempt === attempt && isProfileAuthCurrent(view.auth) ? view : null;
  const entry = visible?.entry;
  const addressChanged = entry && entry.address_current !== entry.address_snapshot;

  async function run(kind) {
    if (!entry || operation.current) return;
    const auth = captureProfileAuth();
    if (!isProfileAuthCurrent(auth)) return;
    const controller = new AbortController();
    const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
    operation.current = controller;
    setBusy(kind);
    setMessage('');
    try {
      if (kind === 'download') {
        const document = await downloadAdminPropertyVerification(propertyId, entry.id, { signal: controller.signal });
        if (current()) saveDownload(document);
      } else {
        const saved = await reviewAdminPropertyVerification(propertyId, decision, reason, { signal: controller.signal });
        if (current()) {
          setView({ attempt, auth, entry: saved });
          setDecision(null);
        }
      }
    } catch (failure) {
      const error = normalizeApiError(failure);
      if (!current() || error.isCancelled) return;
      if (kind === 'download' && error.status === 404) {
        setMessage('Không tìm thấy tệp hồ sơ xác minh.');
      } else if (kind === 'review' && (error.status === 409 || error.status === 0 || error.status >= 500)) {
        setMessage(error.status === 409 ? error.message : 'Đang đối chiếu kết quả xử lý hồ sơ. Hãy kiểm tra trạng thái trước khi gửi lại.');
        setDecision(null);
        setAttempt((value) => value + 1);
      } else if (kind === 'review' && error.status === 422 && error.fields.reason?.length) {
        setReasonError(error.fields.reason.join(' '));
        confirmationFocus.current?.focus();
      } else {
        setMessage(error.message);
      }
    } finally {
      if (operation.current === controller) operation.current = null;
      if (current()) setBusy(null);
    }
  }

  function choose(next, button) {
    actionFocus.current = button;
    setReason('');
    setReasonError('');
    setMessage('');
    setDecision(next);
  }

  function confirm(event) {
    event.preventDefault();
    if (decision === 'REJECT' && !reason.trim()) {
      setReasonError('Nhập lý do từ chối hồ sơ.');
      confirmationFocus.current?.focus();
      return;
    }
    void run('review');
  }

  return <div className="min-h-0 space-y-4 overflow-y-auto overscroll-contain p-4 sm:p-6" aria-busy={!visible || Boolean(busy)}>
    {!visible && <p role="status">Đang tải hồ sơ xác minh…</p>}
    {visible?.error && <div className="space-y-2">
      <p role="alert">{visible.error.message}</p>
      {![401, 403, 404].includes(visible.error.status) &&
        <Button variant="outline" type="button" onClick={() => setAttempt((value) => value + 1)}>Thử lại</Button>}
    </div>}
    {message && <p role="alert" className="break-words text-sm text-destructive">{message}</p>}
    {entry === null && <p className="rounded-xl border border-dashed border-border p-5 text-sm text-muted-foreground">Chưa có hồ sơ xác minh.</p>}
    {entry && <>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p role="status" tabIndex={-1} ref={resultFocus} className={`rounded-full border px-3 py-1 text-xs font-semibold ${statusDisplay[entry.status][1]}`}>
          {statusDisplay[entry.status][0]}
        </p>
        <Button type="button" variant="outline" disabled={Boolean(busy)} onClick={() => run('download')}>
          <Download size={16} aria-hidden="true" />
          {busy === 'download' ? 'Đang tải tệp…' : 'Tải tệp hồ sơ'}
        </Button>
      </div>
      <div className="rounded-2xl border border-border">
        <h3 className="flex items-center gap-2 border-b border-border px-4 py-3 text-sm font-semibold">
          <MapPin size={16} className="text-primary" aria-hidden="true" />
          Đối chiếu địa chỉ
        </h3>
        <dl className="grid gap-3 p-4 text-sm sm:grid-cols-2">
          {[
            ['Địa chỉ lúc gửi', entry.address_snapshot],
            ['Địa chỉ hiện tại', entry.address_current],
            ['Thời điểm gửi', formatDate.format(new Date(entry.submitted_at))],
            ...(entry.reviewed_at ? [['Thời điểm xử lý', formatDate.format(new Date(entry.reviewed_at))]] : []),
          ].map(([label, value]) => <div key={label} className="min-w-0 rounded-xl bg-muted/45 px-3 py-2.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 whitespace-pre-wrap font-medium [overflow-wrap:anywhere]">{value}</dd>
          </div>)}
        </dl>
      </div>
      {entry.status === 'INVALIDATED' && <p className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Địa chỉ cơ sở đã thay đổi sau khi hồ sơ được duyệt.</p>}
      {entry.rejection_reason && <dl className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">
        <dt className="font-semibold">Lý do từ chối</dt>
        <dd className="mt-1 whitespace-pre-wrap [overflow-wrap:anywhere]">{entry.rejection_reason}</dd>
      </dl>}
      {entry.status === 'SUBMITTED' && <>
        {addressChanged && <p className="text-sm text-destructive">Địa chỉ cơ sở đã thay đổi kể từ khi gửi hồ sơ.</p>}
        {decision ? <form onSubmit={confirm} className="space-y-3 rounded-2xl border border-border bg-muted/40 p-4">
          <p className="text-sm font-medium">{decision === 'APPROVE' ? 'Xác nhận duyệt hồ sơ này?' : 'Xác nhận từ chối hồ sơ này?'}</p>
          {decision === 'REJECT' && <div className="grid gap-1.5">
            <label htmlFor="verification-rejection-reason" className="text-sm">Lý do từ chối</label>
            <textarea id="verification-rejection-reason" ref={confirmationFocus} maxLength={500} rows={3} value={reason}
              aria-invalid={Boolean(reasonError)} aria-required="true" disabled={Boolean(busy)}
              aria-describedby={reasonError ? 'verification-rejection-error' : undefined}
              onChange={(event) => { setReason(event.target.value); setReasonError(''); }}
              className="w-full rounded-lg border border-input bg-background p-2 text-sm focus-visible:ring-2 focus-visible:ring-ring" />
            {reasonError && <p role="alert" id="verification-rejection-error" className="text-sm text-destructive">{reasonError}</p>}
          </div>}
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" ref={decision === 'APPROVE' ? confirmationFocus : undefined}
              disabled={Boolean(busy)} onClick={() => setDecision(null)}>Hủy</Button>
            <Button type="submit" variant={decision === 'REJECT' ? 'destructive' : 'default'} disabled={Boolean(busy)}>
              {busy === 'review' ? 'Đang xử lý…' : decision === 'APPROVE' ? 'Xác nhận duyệt' : 'Xác nhận từ chối'}
            </Button>
          </div>
        </form> : <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
          <Button ref={approveButton} type="button" disabled={Boolean(busy) || addressChanged} onClick={() => choose('APPROVE', approveButton)}>Duyệt</Button>
          <Button ref={rejectButton} type="button" variant="outline" disabled={Boolean(busy)} onClick={() => choose('REJECT', rejectButton)}>Từ chối</Button>
        </div>}
      </>}
    </>}
  </div>;
}

export default function AdminPropertyVerificationDialog({ property }) {
  const [open, setOpen] = useState(false);
  const closeRef = useRef(null);

  return <Dialog.Root open={open} onOpenChange={setOpen}>
    <Dialog.Trigger asChild><Button type="button" variant="outline" size="sm">Hồ sơ xác minh</Button></Dialog.Trigger>
    {open && <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm" />
      <Dialog.Content className="fixed left-1/2 top-1/2 z-50 flex max-h-[90dvh] w-[calc(100%-2rem)] max-w-2xl -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl"
        onOpenAutoFocus={(event) => { event.preventDefault(); closeRef.current?.focus(); }}>
        <header className="flex shrink-0 items-start gap-3 border-b border-border bg-muted/40 p-4 sm:p-6">
          <span className="grid size-10 shrink-0 place-items-center rounded-xl border border-primary/15 bg-primary/10 text-primary">
            <FileCheck2 size={20} aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <Dialog.Title className="text-lg font-semibold">Hồ sơ xác minh</Dialog.Title>
            <Dialog.Description className="mt-0.5 text-sm text-muted-foreground [overflow-wrap:anywhere]">{property.name}</Dialog.Description>
          </div>
        </header>
        <VerificationDetails propertyId={property.id} />
        <footer className="flex shrink-0 justify-end border-t border-border p-4">
          <Dialog.Close asChild><Button ref={closeRef} type="button" variant="outline">Đóng</Button></Dialog.Close>
        </footer>
      </Dialog.Content>
    </Dialog.Portal>}
  </Dialog.Root>;
}
