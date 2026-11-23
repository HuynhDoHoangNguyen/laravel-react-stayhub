import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { useAuth } from '../../../hooks/useAuth';
import { getAdminProperty, parseAdminPropertyError } from '../../../services/adminPropertyService';
import { propertyStatuses, propertyTypes } from '../../../mappers/adminPropertyMapper';

const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const numberFormat = new Intl.NumberFormat('vi-VN');
const buttonClass = 'cursor-pointer rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary';
const display = (value) => value === null || value === '' ? 'Chưa cập nhật' : value;
const displayDate = (value) => value === null ? 'Chưa cập nhật' : dateFormat.format(new Date(value));

export default function AdminPropertyDetailDialog({ propertyId, trigger, onClose, onReloadList }) {
  const { user, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const closeRef = useRef(null);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  useEffect(() => {
    const auth = captureProfileAuth();
    const controller = new AbortController();
    let disposed = false;
    if (isProfileAuthCurrent(auth)) {
      getAdminProperty(propertyId, { signal: controller.signal }).then((property) => {
        if (!disposed && isProfileAuthCurrent(auth)) setView({ property, error: null, propertyId, revision, auth });
      }, (failure) => {
        const error = parseAdminPropertyError(failure);
        if (!disposed && isProfileAuthCurrent(auth) && !error.isCancelled) setView({ property: null, error, propertyId, revision, auth });
      });
    }
    return () => { disposed = true; controller.abort(); };
  }, [propertyId, revision, user, captureProfileAuth, isProfileAuthCurrent]);

  const visible = view?.propertyId === propertyId && view?.revision === revision && isProfileAuthCurrent(view.auth) ? view : null;
  const loading = visible === null;
  const property = visible?.property;
  const error = visible?.error;
  const fields = property ? [
    ['Mã cơ sở', `#${property.id}`], ['Tên cơ sở', property.name],
    ['Loại cơ sở', propertyTypes[property.type]], ['Trạng thái', propertyStatuses[property.status]],
    ['Mã Host', `#${property.host.id}`], ['Tên Host', property.host.name], ['Email Host', property.host.email],
    ['Điện thoại cơ sở', display(property.phone)], ['Giờ nhận phòng', property.check_in_time], ['Giờ trả phòng', property.check_out_time],
    ['Số phòng', numberFormat.format(property.rooms_count)], ['Số loại phòng', numberFormat.format(property.room_types_count)],
    ['Ngày tạo', displayDate(property.created_at)], ['Ngày cập nhật', displayDate(property.updated_at)],
    ['Địa chỉ', property.address], ['Mô tả', display(property.description)],
  ] : [];

  return <Dialog.Root open onOpenChange={(open) => { if (!open) onClose(); }}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm" />
      <Dialog.Content className="fixed left-1/2 top-1/2 z-50 flex max-h-[90dvh] w-[92vw] max-w-2xl -translate-x-1/2 -translate-y-1/2 flex-col rounded-2xl border border-border bg-background shadow-xl"
        onOpenAutoFocus={(event) => { event.preventDefault(); closeRef.current?.focus(); }}
        onCloseAutoFocus={(event) => {
          event.preventDefault();
          (trigger?.isConnected ? trigger : document.getElementById('admin-properties-title'))?.focus();
        }}>
        <header className="shrink-0 border-b border-border p-5 sm:p-6">
          <Dialog.Title className="text-xl font-bold">Chi tiết cơ sở lưu trú</Dialog.Title>
          <Dialog.Description className="mt-2 text-sm text-muted-foreground">Thông tin cơ sở lưu trú #{propertyId}.</Dialog.Description>
        </header>
        <div className="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6" aria-busy={loading} aria-live="polite">
          {loading && <p role="status" className="py-4 text-center text-muted-foreground">Đang tải thông tin cơ sở lưu trú…</p>}
          {error && <div role="alert" className="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
            <p>{error.message}</p>
            {Object.keys(error.fields ?? {}).length > 0 && <ul className="mt-2 list-inside list-disc">
              {Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}
            </ul>}
            {![401, 403, 404].includes(error.status) && <button type="button" className={`${buttonClass} mt-3`} onClick={() => setRevision((previous) => previous + 1)}>Thử lại</button>}
            {error.isNotFound && <button type="button" className={`${buttonClass} mt-3`} onClick={() => { onClose(); onReloadList(); }}>Đóng và tải lại danh sách</button>}
          </div>}
          {property && <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
            {fields.map(([label, value]) => <div key={label} className={['Địa chỉ', 'Mô tả'].includes(label) ? 'sm:col-span-2' : ''}>
              <dt className="text-sm text-muted-foreground">{label}</dt>
              <dd className="mt-1 whitespace-pre-wrap break-words font-medium [overflow-wrap:anywhere]">{value}</dd>
            </div>)}
          </dl>}
        </div>
        <footer className="flex shrink-0 flex-wrap justify-end gap-2 border-t border-border px-5 py-4 sm:px-6">
          <button type="button" className={`${buttonClass} aria-disabled:cursor-default aria-disabled:opacity-50`}
            aria-disabled={loading || [401, 403, 404].includes(error?.status)} onClick={() => {
              if (!loading && ![401, 403, 404].includes(error?.status)) setRevision((previous) => previous + 1);
            }}>Tải lại</button>
          <Dialog.Close asChild><button ref={closeRef} type="button" className={buttonClass}>Đóng</button></Dialog.Close>
        </footer>
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>;
}
