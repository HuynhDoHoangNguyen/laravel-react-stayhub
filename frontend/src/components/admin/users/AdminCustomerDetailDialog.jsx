import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { useAuth } from '../../../hooks/useAuth';
import { getAdminCustomer, parseAdminUserError } from '../../../services/adminUserService';
import { customerStatuses } from '../../../mappers/adminUserMapper';

const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const genders = { MALE: 'Nam', FEMALE: 'Nữ', OTHER: 'Khác' };
const buttonClass = 'cursor-pointer rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary';
const display = (value) => value === null || value === '' ? 'Chưa cập nhật' : value;
const displayDate = (value) => value === null ? 'Chưa cập nhật' : dateFormat.format(new Date(value));
const displayBirthday = (value) => value === null ? 'Chưa cập nhật' : value.split('-').reverse().join('/');

export default function AdminCustomerDetailDialog({ customerId, trigger, onClose, onReloadList }) {
  const { user, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const closeRef = useRef(null);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  useEffect(() => {
    const auth = captureProfileAuth();
    const controller = new AbortController();
    let disposed = false;
    if (isProfileAuthCurrent(auth)) {
      getAdminCustomer(customerId, { signal: controller.signal }).then((customer) => {
        if (!disposed && isProfileAuthCurrent(auth)) setView({ customer, error: null, customerId, revision, auth });
      }, (failure) => {
        const error = parseAdminUserError(failure);
        if (!disposed && isProfileAuthCurrent(auth) && !error.isCancelled) setView({ customer: null, error, customerId, revision, auth });
      });
    }
    return () => { disposed = true; controller.abort(); };
  }, [customerId, revision, user, captureProfileAuth, isProfileAuthCurrent]);

  const visible = view?.customerId === customerId && view?.revision === revision && isProfileAuthCurrent(view.auth) ? view : null;
  const loading = visible === null;
  const customer = visible?.customer;
  const error = visible?.error;
  const fields = customer ? [
    ['Mã khách hàng', `#${customer.id}`], ['Họ tên', customer.name], ['Email', customer.email],
    ['Điện thoại', display(customer.phone)], ['Giới tính', customer.gender === null ? 'Chưa cập nhật' : genders[customer.gender]],
    ['Ngày sinh', displayBirthday(customer.date_of_birth)], ['Trạng thái', customerStatuses[customer.status]],
    ['Ngày tạo', displayDate(customer.created_at)], ['Ngày cập nhật', displayDate(customer.updated_at)],
    ['Địa chỉ', display(customer.address)],
  ] : [];

  return <Dialog.Root open onOpenChange={(open) => { if (!open) onClose(); }}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm" />
      <Dialog.Content className="fixed left-1/2 top-1/2 z-50 flex max-h-[90dvh] w-[92vw] max-w-2xl -translate-x-1/2 -translate-y-1/2 flex-col rounded-2xl border border-border bg-background shadow-xl"
        onOpenAutoFocus={(event) => { event.preventDefault(); closeRef.current?.focus(); }}
        onCloseAutoFocus={(event) => {
          event.preventDefault();
          (trigger?.isConnected ? trigger : document.getElementById('admin-customers-title'))?.focus();
        }}>
        <header className="shrink-0 border-b border-border p-5 sm:p-6">
          <Dialog.Title className="text-xl font-bold">Chi tiết khách hàng</Dialog.Title>
          <Dialog.Description className="mt-2 text-sm text-muted-foreground">Thông tin tài khoản khách hàng #{customerId}.</Dialog.Description>
        </header>
        <div className="min-h-24 overflow-y-auto p-5 sm:p-6" aria-busy={loading} aria-live="polite">
          {loading && <p role="status" className="py-4 text-center text-muted-foreground">Đang tải thông tin khách hàng…</p>}
          {error && <div role="alert" className="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
            <p>{error.message}</p>
            {Object.keys(error.fields ?? {}).length > 0 && <ul className="mt-2 list-inside list-disc">
              {Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}
            </ul>}
            {![401, 403, 404].includes(error.status) && <button type="button" className={`${buttonClass} mt-3`} onClick={() => setRevision((previous) => previous + 1)}>Thử lại</button>}
            {error.isNotFound && <button type="button" className={`${buttonClass} mt-3`} onClick={() => { onClose(); onReloadList(); }}>Đóng và tải lại danh sách</button>}
          </div>}
          {customer && <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
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
