import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, CalendarDays, Check, CircleAlert, DoorOpen, Mail, MapPin, Phone, UserRound, Users } from 'lucide-react';
import { toast } from 'sonner';
import { hostBookingService, HOST_BOOKING_STATUSES, formatHostDate, formatHostVnd } from '@/services/hostBookingService';

function DetailItem({ icon: Icon, label, children }) {
  return (
    <div className="flex gap-3">
      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-accent" />
      <div className="min-w-0">
        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
        <div className="mt-1 break-words text-sm font-semibold text-ink">{children}</div>
      </div>
    </div>
  );
}

export default function HostBookingDetailPage() {
  const { bookingId } = useParams();
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [rejectFormOpen, setRejectFormOpen] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const actionLock = useRef(false);

  const loadBooking = useCallback(async (signal) => {
    setLoading(true);
    setError('');
    try {
      const data = await hostBookingService.detail(bookingId, { signal });
      setBooking(data);
    } catch (loadError) {
      if (loadError.name !== 'CanceledError' && loadError.name !== 'AbortError') {
        setError(loadError.message || 'Không thể tải chi tiết booking.');
      }
    } finally {
      if (!signal.aborted) setLoading(false);
    }
  }, [bookingId]);

  useEffect(() => {
    const controller = new AbortController();
    const start = window.setTimeout(() => loadBooking(controller.signal), 0);
    return () => {
      window.clearTimeout(start);
      controller.abort();
    };
  }, [loadBooking]);

  const runTransition = async (action, confirmation, payload = {}) => {
    if (actionLock.current) return;
    if (!window.confirm(confirmation)) return;
    actionLock.current = true;
    setActionLoading(true);
    setActionError('');
    try {
      const updated = await hostBookingService.transition(bookingId, action, payload);
      setBooking(updated);
      setRejectFormOpen(false);
      setRejectReason('');
      toast.success('Đã cập nhật trạng thái booking.');
    } catch (transitionError) {
      const message = transitionError.message || 'Không thể cập nhật booking.';
      setActionError(message);
      toast.error(message);
    } finally {
      actionLock.current = false;
      setActionLoading(false);
    }
  };

  const status = booking ? (HOST_BOOKING_STATUSES[booking.status] || { label: booking.status, style: 'border-border bg-surface text-ink' }) : null;
  const serviceItems = booking?.booking_services || [];

  if (loading) {
    return <div className="mx-auto max-w-5xl space-y-5"><div className="h-5 w-36 animate-pulse rounded bg-white" /><div className="h-64 animate-pulse rounded-2xl bg-white" /><div className="h-48 animate-pulse rounded-2xl bg-white" /></div>;
  }

  if (error || !booking) {
    return (
      <section className="mx-auto max-w-2xl rounded-2xl border border-border/70 bg-white p-8 text-center shadow-xs">
        <CircleAlert className="mx-auto h-8 w-8 text-rose-600" />
        <h1 className="mt-3 font-display text-2xl font-bold text-primary">Không thể mở booking</h1>
        <p role="alert" className="mt-2 text-sm text-muted-foreground">{error || 'Không tìm thấy booking.'}</p>
        <Link to="/host/bookings" className="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground"><ArrowLeft className="h-4 w-4" /> Về danh sách</Link>
      </section>
    );
  }

  const customer = booking.customer || {};
  const room = booking.room || {};
  const property = room.property || {};

  return (
    <div className="mx-auto max-w-5xl space-y-6 text-left">
      <Link to="/host/bookings" className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"><ArrowLeft className="h-4 w-4" /> Danh sách booking</Link>

      <header className="flex flex-col justify-between gap-4 rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:flex-row sm:items-start sm:p-6">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Chi tiết đơn đặt phòng</p>
          <h1 className="mt-1 font-display text-2xl font-bold text-primary sm:text-3xl">{booking.booking_code || `Booking #${booking.id}`}</h1>
          <p className="mt-1 text-sm text-muted-foreground">Tạo lúc {booking.created_at ? new Date(booking.created_at).toLocaleString('vi-VN') : '—'}</p>
        </div>
        <span className={`w-fit rounded-full border px-3 py-1.5 text-xs font-bold ${status.style}`}>{status.label}</span>
      </header>

      {actionError && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{actionError}</div>}

      <section className="grid gap-5 lg:grid-cols-2">
        <article className="space-y-5 rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:p-6">
          <h2 className="font-display text-xl font-bold text-primary">Thông tin lưu trú</h2>
          <div className="grid gap-5 sm:grid-cols-2">
            <DetailItem icon={MapPin} label="Cơ sở lưu trú">{property.name || '—'}{property.address ? <span className="mt-1 block text-xs font-normal text-muted-foreground">{property.address}</span> : null}</DetailItem>
            <DetailItem icon={DoorOpen} label="Phòng">{room.name || room.room_number || '—'}</DetailItem>
            <DetailItem icon={CalendarDays} label="Nhận phòng">{formatHostDate(booking.check_in_date)}</DetailItem>
            <DetailItem icon={CalendarDays} label="Trả phòng">{formatHostDate(booking.check_out_date)}</DetailItem>
            <DetailItem icon={Users} label="Số khách">{booking.guest_count} khách</DetailItem>
            <DetailItem icon={UserRound} label="Số đêm">{booking.number_of_nights} đêm</DetailItem>
          </div>
          {booking.note && <div className="rounded-xl bg-surface p-3 text-sm text-ink"><strong>Ghi chú:</strong> {booking.note}</div>}
        </article>

        <article className="space-y-5 rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:p-6">
          <h2 className="font-display text-xl font-bold text-primary">Thông tin khách hàng</h2>
          <DetailItem icon={UserRound} label="Khách đặt">{customer.name || '—'}</DetailItem>
          <DetailItem icon={Mail} label="Email">{customer.email ? <a className="text-primary hover:underline" href={`mailto:${customer.email}`}>{customer.email}</a> : '—'}</DetailItem>
          <DetailItem icon={Phone} label="Điện thoại">{customer.phone ? <a className="text-primary hover:underline" href={`tel:${customer.phone}`}>{customer.phone}</a> : '—'}</DetailItem>
          <div className="border-t border-border/60 pt-4">
            <div className="flex justify-between gap-4 text-sm"><span className="text-muted-foreground">Tiền phòng</span><strong>{formatHostVnd(booking.room_total)}</strong></div>
            <div className="mt-2 flex justify-between gap-4 text-sm"><span className="text-muted-foreground">Dịch vụ đã dùng</span><strong>{formatHostVnd(booking.services_total)}</strong></div>
          </div>
        </article>
      </section>

      <section className="rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:p-6">
        <h2 className="font-display text-xl font-bold text-primary">Dịch vụ đã ghi nhận</h2>
        {serviceItems.length ? (
          <div className="mt-3 divide-y divide-border/60">
            {serviceItems.map((service) => (
              <div key={service.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                <div><p className="font-semibold text-ink">{service.service_name}</p><p className="text-xs text-muted-foreground">{service.quantity} {service.unit} × {formatHostVnd(service.unit_price)}</p></div>
                <strong className="text-primary">{formatHostVnd(service.amount)}</strong>
              </div>
            ))}
          </div>
        ) : <p className="mt-3 rounded-xl bg-surface px-4 py-5 text-sm text-muted-foreground">Booking chưa có dịch vụ phát sinh.</p>}
      </section>

      <section className="rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:p-6">
        <h2 className="font-display text-xl font-bold text-primary">Cập nhật tiến trình</h2>
        <p className="mt-1 text-sm text-muted-foreground">Mỗi thao tác được kiểm tra lại theo trạng thái hiện tại của booking.</p>
        <div className="mt-4 flex flex-wrap gap-3">
          {booking.status === 'PENDING' && <>
            <button type="button" disabled={actionLoading} onClick={() => runTransition('confirm', 'Xác nhận tiếp nhận booking này?')} className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50"><Check className="h-4 w-4" />{actionLoading ? 'Đang cập nhật…' : 'Xác nhận booking'}</button>
            {!rejectFormOpen && <button type="button" disabled={actionLoading} onClick={() => setRejectFormOpen(true)} className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-800 disabled:opacity-50">Từ chối booking</button>}
          </>}
          {booking.status === 'CONFIRMED' && <button type="button" disabled={actionLoading} onClick={() => runTransition('check-in', 'Xác nhận khách đã nhận phòng?')} className="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50">{actionLoading ? 'Đang cập nhật…' : 'Xác nhận nhận phòng'}</button>}
          {booking.status === 'CHECKED_IN' && <button type="button" disabled={actionLoading} onClick={() => runTransition('check-out', 'Xác nhận khách đã trả phòng? Thao tác này hoàn tất booking nhưng không tạo hóa đơn.')} className="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50">{actionLoading ? 'Đang cập nhật…' : 'Xác nhận trả phòng'}</button>}
          {booking.status === 'CHECKED_IN' && <Link to={`/host/bookings/${booking.id}/services`} className="rounded-xl border border-border bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:border-primary/40 hover:text-primary">Quản lý dịch vụ đã dùng</Link>}
          {['COMPLETED', 'CANCELLED', 'REJECTED'].includes(booking.status) && <p className="rounded-xl bg-surface px-4 py-3 text-sm text-muted-foreground">Booking đã kết thúc, không còn thao tác workflow.</p>}
        </div>
        {rejectFormOpen && booking.status === 'PENDING' && (
          <form onSubmit={(event) => { event.preventDefault(); runTransition('reject', 'Từ chối booking này?', { reason: rejectReason.trim() }); }} className="mt-4 max-w-xl space-y-3 rounded-xl border border-rose-200 bg-rose-50/60 p-4">
            <label htmlFor="reject-reason" className="block text-sm font-semibold text-ink">Lý do từ chối <span className="font-normal text-muted-foreground">(không bắt buộc)</span></label>
            <textarea id="reject-reason" rows={3} maxLength={500} value={rejectReason} onChange={(event) => setRejectReason(event.target.value)} className="w-full resize-y rounded-lg border border-border bg-white p-3 text-sm outline-none focus:border-primary" placeholder="Nhập lý do để khách hàng nắm được thông tin…" />
            <div className="flex gap-2">
              <button type="submit" disabled={actionLoading} className="rounded-lg bg-rose-700 px-3.5 py-2 text-sm font-semibold text-white disabled:opacity-50">Xác nhận từ chối</button>
              <button type="button" disabled={actionLoading} onClick={() => { setRejectFormOpen(false); setRejectReason(''); }} className="rounded-lg border border-border bg-white px-3.5 py-2 text-sm font-semibold text-ink">Quay lại</button>
            </div>
          </form>
        )}
      </section>
    </div>
  );
}
