import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, LoaderCircle, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { hostBookingService, HOST_BOOKING_STATUSES, formatHostDate, formatHostVnd } from '@/services/hostBookingService';

export default function HostBookingServicesPage() {
  const { bookingId } = useParams();
  const [booking, setBooking] = useState(null);
  const [services, setServices] = useState([]);
  const [catalog, setCatalog] = useState([]);
  const [selectedServiceId, setSelectedServiceId] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [quantityDrafts, setQuantityDrafts] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState('');
  const actionLock = useRef(false);

  const loadPage = useCallback(async (signal) => {
    setLoading(true);
    setError('');
    try {
      const bookingData = await hostBookingService.detail(bookingId, { signal });
      setBooking(bookingData);
      const serviceRequests = [hostBookingService.listBookingServices(bookingId, { signal })];
      if (bookingData.status === 'CHECKED_IN' && bookingData.room?.property?.id) {
        serviceRequests.push(hostBookingService.listPropertyServices(bookingData.room.property.id, { signal }));
      }
      const [bookingServices, availableServices = []] = await Promise.all(serviceRequests);
      if (signal.aborted) return;
      setServices(bookingServices);
      setCatalog(availableServices);
      setQuantityDrafts(Object.fromEntries(bookingServices.map((item) => [item.id, String(item.quantity)])));
      setSelectedServiceId((current) => current || String(availableServices[0]?.id || ''));
    } catch (loadError) {
      if (loadError.name !== 'CanceledError' && loadError.name !== 'AbortError') {
        setError(loadError.message || 'Không thể tải dịch vụ booking.');
      }
    } finally {
      if (!signal.aborted) setLoading(false);
    }
  }, [bookingId]);

  useEffect(() => {
    const controller = new AbortController();
    const start = window.setTimeout(() => loadPage(controller.signal), 0);
    return () => {
      window.clearTimeout(start);
      controller.abort();
    };
  }, [loadPage]);

  const reloadServices = async () => {
    const [bookingData, bookingServices] = await Promise.all([
      hostBookingService.detail(bookingId),
      hostBookingService.listBookingServices(bookingId),
    ]);
    setBooking(bookingData);
    setServices(bookingServices);
    setQuantityDrafts(Object.fromEntries(bookingServices.map((item) => [item.id, String(item.quantity)])));
  };

  const performAction = async (key, action) => {
    if (actionLock.current) return;
    actionLock.current = true;
    setSaving(key);
    setError('');
    try {
      await action();
      await reloadServices();
      toast.success('Đã cập nhật dịch vụ booking.');
    } catch (actionError) {
      setError(actionError.message || 'Không thể cập nhật dịch vụ booking.');
      toast.error(actionError.message || 'Không thể cập nhật dịch vụ booking.');
    } finally {
      actionLock.current = false;
      setSaving('');
    }
  };

  const selectedService = catalog.find((item) => String(item.id) === selectedServiceId);
  const status = booking ? (HOST_BOOKING_STATUSES[booking.status] || { label: booking.status, style: 'border-border bg-surface text-ink' }) : null;

  if (loading) {
    return <div className="mx-auto max-w-5xl space-y-5"><div className="h-5 w-40 animate-pulse rounded bg-white" /><div className="h-48 animate-pulse rounded-2xl bg-white" /><div className="h-64 animate-pulse rounded-2xl bg-white" /></div>;
  }

  if (error && !booking) {
    return (
      <section className="mx-auto max-w-2xl rounded-2xl border border-border/70 bg-white p-8 text-center shadow-xs">
        <h1 className="font-display text-2xl font-bold text-primary">Không thể mở dịch vụ booking</h1>
        <p role="alert" className="mt-2 text-sm text-muted-foreground">{error}</p>
        <Link to={`/host/bookings/${bookingId}`} className="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground"><ArrowLeft className="h-4 w-4" /> Về chi tiết booking</Link>
      </section>
    );
  }

  const canManage = booking?.status === 'CHECKED_IN';

  return (
    <div className="mx-auto max-w-5xl space-y-6 text-left">
      <Link to={`/host/bookings/${bookingId}`} className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"><ArrowLeft className="h-4 w-4" /> Chi tiết booking</Link>

      <header className="flex flex-col justify-between gap-4 rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:flex-row sm:items-start sm:p-6">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Dịch vụ sử dụng</p>
          <h1 className="mt-1 font-display text-2xl font-bold text-primary sm:text-3xl">{booking.booking_code || `Booking #${booking.id}`}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{booking.customer?.name || 'Khách hàng'} · {booking.room?.name || 'Phòng lưu trú'} · {formatHostDate(booking.check_in_date)} – {formatHostDate(booking.check_out_date)}</p>
        </div>
        <span className={`w-fit rounded-full border px-3 py-1.5 text-xs font-bold ${status.style}`}>{status.label}</span>
      </header>

      {error && <div role="alert" className="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><span>{error}</span><button type="button" onClick={() => { setLoading(true); const controller = new AbortController(); loadPage(controller.signal); }} className="inline-flex shrink-0 items-center gap-1 font-semibold"><RefreshCw className="h-3.5 w-3.5" /> Tải lại</button></div>}

      {!canManage && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          Chỉ có thể thêm, sửa hoặc xóa dịch vụ khi booking ở trạng thái <strong>Đang lưu trú</strong>. Danh sách dưới đây ở chế độ xem.
        </div>
      )}

      {canManage && (
        <section className="rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:p-6">
          <h2 className="font-display text-xl font-bold text-primary">Thêm dịch vụ</h2>
          {catalog.length > 0 ? (
            <form onSubmit={(event) => {
              event.preventDefault();
              if (!selectedServiceId || !selectedService) return;
              performAction('add', async () => {
                await hostBookingService.addBookingService(bookingId, { service_id: Number(selectedServiceId), quantity: Number(quantity) });
                setQuantity('1');
              });
            }} className="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_auto] md:items-end">
              <label className="block text-xs font-semibold text-muted-foreground">
                Dịch vụ
                <select required value={selectedServiceId} onChange={(event) => setSelectedServiceId(event.target.value)} disabled={saving !== ''} className="mt-1.5 h-11 w-full rounded-xl border border-border bg-white px-3 text-sm font-medium text-ink outline-none focus:border-primary">
                  {catalog.map((service) => <option key={service.id} value={service.id}>{service.name} · {formatHostVnd(service.price)}/{service.unit}</option>)}
                </select>
                {selectedService?.description && <span className="mt-1 block font-normal">{selectedService.description}</span>}
              </label>
              <label className="block text-xs font-semibold text-muted-foreground">
                Số lượng ({selectedService?.unit || 'đơn vị'})
                <input type="number" min="0.01" max="9999.99" step="0.01" required value={quantity} onChange={(event) => setQuantity(event.target.value)} disabled={saving !== ''} className="mt-1.5 h-11 w-full rounded-xl border border-border px-3 text-sm font-medium text-ink outline-none focus:border-primary" />
              </label>
              <button type="submit" disabled={!selectedServiceId || saving !== ''} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-50">
                {saving === 'add' ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                Thêm dịch vụ
              </button>
            </form>
          ) : (
            <p className="mt-3 rounded-xl bg-surface px-4 py-5 text-sm text-muted-foreground">Cơ sở này chưa có dịch vụ nào đang bật để thêm vào booking.</p>
          )}
          <p className="mt-3 text-xs text-muted-foreground">Giá và thành tiền được máy chủ xác nhận theo snapshot dịch vụ. Thêm lại dịch vụ đã có sẽ cộng dồn số lượng.</p>
        </section>
      )}

      <section className="rounded-2xl border border-border/70 bg-white p-5 shadow-xs sm:p-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div><h2 className="font-display text-xl font-bold text-primary">Dịch vụ đã ghi nhận</h2><p className="mt-1 text-sm text-muted-foreground">Số tiền hiển thị lấy từ snapshot backend.</p></div>
          <div className="text-right"><p className="text-xs text-muted-foreground">Tổng dịch vụ</p><p className="font-display text-xl font-bold text-primary">{formatHostVnd(booking.services_total)}</p></div>
        </div>

        {services.length > 0 ? (
          <div className="mt-4 divide-y divide-border/60">
            {services.map((service) => {
              const draft = quantityDrafts[service.id] ?? String(service.quantity);
              const changed = draft !== String(service.quantity);
              return (
                <div key={service.id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-ink">{service.service_name}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">{service.quantity} {service.unit} × {formatHostVnd(service.unit_price)}</p>
                  </div>
                  <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                    {canManage && <>
                      <label className="sr-only" htmlFor={`quantity-${service.id}`}>Số lượng {service.service_name}</label>
                      <input id={`quantity-${service.id}`} type="number" min="0.01" max="9999.99" step="0.01" value={draft} onChange={(event) => setQuantityDrafts((current) => ({ ...current, [service.id]: event.target.value }))} disabled={saving !== ''} className="h-9 w-28 rounded-lg border border-border px-2 text-sm outline-none focus:border-primary" />
                      <button type="button" disabled={!changed || saving !== ''} onClick={() => performAction(`update-${service.id}`, () => hostBookingService.updateBookingService(bookingId, service.id, Number(draft)))} className="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-ink hover:border-primary/40 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40">Lưu số lượng</button>
                      <button type="button" aria-label={`Xóa ${service.service_name}`} disabled={saving !== ''} onClick={() => {
                        if (!window.confirm(`Xóa ${service.service_name} khỏi booking?`)) return;
                        performAction(`delete-${service.id}`, () => hostBookingService.removeBookingService(bookingId, service.id));
                      }} className="grid h-9 w-9 place-items-center rounded-lg text-rose-700 hover:bg-rose-50 disabled:opacity-40"><Trash2 className="h-4 w-4" /></button>
                    </>}
                    <strong className="min-w-24 text-right text-sm text-primary">{formatHostVnd(service.amount)}</strong>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <p className="mt-4 rounded-xl bg-surface px-4 py-6 text-center text-sm text-muted-foreground">Booking chưa có dịch vụ nào được ghi nhận.</p>
        )}
      </section>
    </div>
  );
}
