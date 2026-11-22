import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { useAuth } from '../../../hooks/useAuth';
import { parseAccountStatusError, updateAccountStatus } from '../../../services/accountStatusService';

const buttonClass = 'cursor-pointer rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-default disabled:opacity-50';
const statuses = { ACTIVE: 'Đang hoạt động', BLOCKED: 'Đã khóa', INACTIVE: 'Chưa hoạt động' };

export default function AccountStatusDialog({ accountId, desiredStatus, loadAccount, trigger, onClose, onSuccess,
  accountLabel = 'khách hàng', fallbackFocusId = 'admin-customers-title' }) {
  const { user, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const closeRef = useRef(null);
  const aliveRef = useRef(false);
  const pendingRef = useRef(false);
  const mutationController = useRef(null);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  const [pending, setPending] = useState(false);
  const [mutationError, setMutationError] = useState(null);
  const [recovery, setRecovery] = useState(false);
  const verb = desiredStatus === 'BLOCKED' ? 'Khóa' : 'Mở khóa';

  useEffect(() => {
    aliveRef.current = true;
    return () => { aliveRef.current = false; mutationController.current?.abort(); };
  }, []);

  useEffect(() => {
    const auth = captureProfileAuth();
    const controller = new AbortController();
    let disposed = false;
    if (isProfileAuthCurrent(auth)) {
      loadAccount(accountId, { signal: controller.signal }).then((account) => {
        if (!disposed && isProfileAuthCurrent(auth)) setView({ account, error: null, revision, auth });
      }, (failure) => {
        const error = parseAccountStatusError(failure);
        if (!disposed && isProfileAuthCurrent(auth) && !error.isCancelled) setView({ account: null, error, revision, auth });
      });
    }
    return () => { disposed = true; controller.abort(); };
  }, [accountId, revision, user, loadAccount, captureProfileAuth, isProfileAuthCurrent]);

  const visible = view?.revision === revision && isProfileAuthCurrent(view.auth) ? view : null;
  const loading = visible === null;
  const account = visible?.account;
  const error = visible ? mutationError ?? visible.error : null;
  const alreadyDesired = account?.status === desiredStatus;
  const eligible = account && ['ACTIVE', 'BLOCKED'].includes(account.status) && !alreadyDesired;

  function close() { if (!pendingRef.current) onClose(); }
  function readAgain() {
    if (pendingRef.current) return;
    setMutationError(null);
    setRevision((previous) => previous + 1);
  }
  async function confirm() {
    if (pendingRef.current || loading || error || !eligible) return;
    const auth = captureProfileAuth();
    if (!isProfileAuthCurrent(auth)) return;
    pendingRef.current = true;
    setPending(true);
    const controller = new AbortController();
    mutationController.current = controller;
    try {
      const result = await updateAccountStatus(accountId,
        { status: desiredStatus, expected_status: account.status }, { signal: controller.signal });
      if (aliveRef.current && isProfileAuthCurrent(auth)) {
        pendingRef.current = false;
        onSuccess(result);
      }
    } catch (failure) {
      const parsed = parseAccountStatusError(failure);
      if (aliveRef.current && isProfileAuthCurrent(auth) && !parsed.isCancelled) {
        setMutationError(parsed);
        setRecovery(true);
      }
    } finally {
      pendingRef.current = false;
      if (aliveRef.current) setPending(false);
    }
  }

  return <Dialog.Root open onOpenChange={(open) => { if (!open) close(); }}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm" />
      <Dialog.Content className="fixed left-1/2 top-1/2 z-50 flex max-h-[90dvh] w-[92vw] max-w-lg -translate-x-1/2 -translate-y-1/2 flex-col rounded-2xl border border-border bg-background shadow-xl"
        onEscapeKeyDown={(event) => { if (pendingRef.current) event.preventDefault(); }}
        onInteractOutside={(event) => { if (pendingRef.current) event.preventDefault(); }}
        onOpenAutoFocus={(event) => { event.preventDefault(); closeRef.current?.focus(); }}
        onCloseAutoFocus={(event) => {
          event.preventDefault();
          (trigger?.isConnected ? trigger : document.getElementById(fallbackFocusId))?.focus();
        }}>
        <header className="shrink-0 border-b border-border p-5 sm:p-6">
          <Dialog.Title className="text-xl font-bold">{verb} tài khoản</Dialog.Title>
          <Dialog.Description className="mt-2 text-sm text-muted-foreground">Xác nhận thay đổi tài khoản {accountLabel} #{accountId}.</Dialog.Description>
        </header>
        <div className="overflow-y-auto p-5 sm:p-6" aria-busy={loading || pending} aria-live="polite">
          {loading && <p role="status">Đang kiểm tra thông tin tài khoản…</p>}
          {account && <>
            <dl className="grid gap-3 break-words [overflow-wrap:anywhere]">
              <div><dt className="text-sm text-muted-foreground">Họ tên</dt><dd className="font-medium">{account.name}</dd></div>
              <div><dt className="text-sm text-muted-foreground">Trạng thái hiện tại</dt><dd className="font-medium">{statuses[account.status]}</dd></div>
            </dl>
            {!error && alreadyDesired && <p role="status" className="mt-4">Trạng thái hiện tại đã là “{statuses[desiredStatus]}”. Hãy đóng để cập nhật danh sách.</p>}
            {!error && account.status === 'INACTIVE' && <p role="status" className="mt-4">Tài khoản chưa hoạt động. Không thể khóa hoặc mở khóa tài khoản này.</p>}
            {!error && eligible && <p className="mt-4">{desiredStatus === 'BLOCKED'
              ? 'Sau khi khóa, tài khoản này không thể đăng nhập và sẽ bị từ chối tại yêu cầu được bảo vệ kế tiếp khi đang khóa.'
              : 'Sau khi mở khóa, tài khoản này có thể đăng nhập lại. Phiên đã kết thúc sẽ không được khôi phục.'}</p>}
            {!error && recovery && eligible && <p role="status" className="mt-3 text-sm text-muted-foreground">Đã đọc lại thông tin hiện tại. Hãy kiểm tra và xác nhận lại nếu bạn vẫn muốn tiếp tục.</p>}
          </>}
          {error && <div role="alert" className="mt-4 rounded-xl border border-destructive/30 bg-destructive/5 p-4">
            <p>{error.outcomeUnknown ? 'Chưa xác định được kết quả thao tác. Hãy đọc lại thông tin trước khi xác nhận tiếp.' : error.message}</p>
            {Object.keys(error.fields).length > 0 && <ul id="account-status-errors" className="mt-2 list-inside list-disc">
              {Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}
            </ul>}
            {![401, 403, 404].includes(error.status) && <button type="button" className={`${buttonClass} mt-3`} onClick={readAgain}>Đọc lại thông tin</button>}
          </div>}
          {pending && <p role="status" className="mt-4">Đang {verb.toLowerCase()} tài khoản…</p>}
        </div>
        <footer className="flex shrink-0 flex-wrap justify-end gap-3 border-t border-border px-5 py-4 sm:px-6">
          <button ref={closeRef} type="button" className={buttonClass} disabled={pending} onClick={close}>Đóng và tải lại danh sách</button>
          {!alreadyDesired && account?.status !== 'INACTIVE' && <button type="button"
            className={`${buttonClass} ${desiredStatus === 'BLOCKED' ? 'bg-destructive text-white hover:bg-destructive/90' : 'bg-primary text-primary-foreground hover:bg-primary/90'}`}
            disabled={loading || pending || Boolean(error) || !eligible} onClick={confirm}>Xác nhận {verb.toLowerCase()}</button>}
        </footer>
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>;
}
