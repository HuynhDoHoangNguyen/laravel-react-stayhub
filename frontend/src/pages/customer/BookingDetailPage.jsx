import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, CalendarDays, MapPin, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ConfirmDialog from '@/components/booking/shared/ConfirmDialog';
import ErrorState from '@/components/booking/shared/ErrorState';
import LoadingState from '@/components/booking/shared/LoadingState';
import StatusBadge from '@/components/booking/shared/StatusBadge';
import { cancelBooking, getCustomerBookingDetail } from '@/services/bookingService';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDate } from '@/utils/formatDate';
import { getApiErrorMessage } from '@/utils/getApiErrorMessage';

const WORKFLOW_STEPS = [
  { key: 'PENDING', label: 'Chờ xác nhận' },
  { key: 'CONFIRMED', label: 'Đã xác nhận' },
  { key: 'CHECKED_IN', label: 'Đã nhận phòng' },
  { key: 'COMPLETED', label: 'Hoàn tất' },
];

export default function BookingDetailPage() {
  const { bookingId } = useParams();
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notFound, setNotFound] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [feedback, setFeedback] = useState('');

  const loadBooking = useCallback(async () => {
    setLoading(true);
    setError('');
    setNotFound(false);
    try {
      const response = await getCustomerBookingDetail(bookingId);
      setBooking(response.data);
    } catch (requestError) {
      if (requestError.response?.status === 404) setNotFound(true);
      else setError(getApiErrorMessage(requestError).message);
    } finally {
      setLoading(false);
    }
  }, [bookingId]);

  useEffect(() => {
    let active = true;
    Promise.resolve().then(() => active && loadBooking());
    return () => { active = false; };
  }, [loadBooking]);

  const handleCancel = async () => {
    setCancelling(true);
    setFeedback('');
    try {
      const response = await cancelBooking(bookingId);
      setBooking((current) => ({ ...current, ...response.data }));
      setFeedback('Đã gửi yêu cầu hủy đặt phòng thành công.');
      setConfirmOpen(false);
    } catch (requestError) {
      setFeedback(getApiErrorMessage(requestError).message);
      setConfirmOpen(false);
    } finally {
      setCancelling(false);
    }
  };

  if (loading) return <main className="mx-auto max-w-5xl px-4 py-12 text-left sm:px-6"><LoadingState message="Đang tải chi tiết booking..." /></main>;
  if (notFound) return <main className="mx-auto max-w-4xl px-4 py-12 text-left sm:px-6">
    <Card className="rounded-3xl"><CardContent className="flex flex-col items-center gap-4 py-14 text-center"><h1 className="text-2xl font-medium">Không tìm thấy booking</h1><p className="text-sm text-muted-foreground">Booking không tồn tại hoặc bạn không có quyền xem.</p><Button asChild variant="outline"><Link to="/customer/bookings"><ArrowLeft className="size-4" aria-hidden="true" />Về danh sách đặt phòng</Link></Button></CardContent></Card>
  </main>;
  if (error || !booking) return <main className="mx-auto max-w-4xl px-4 py-12 text-left sm:px-6"><ErrorState title="Không thể tải chi tiết booking" message={error || 'Đã xảy ra lỗi.'} onRetry={loadBooking} /></main>;

  const terminal = booking.status === 'CANCELLED' || booking.status === 'REJECTED';
  const canCancel = booking.status === 'PENDING' || booking.status === 'CONFIRMED';
  const currentStep = WORKFLOW_STEPS.findIndex((step) => step.key === booking.status);
  const services = booking.booking_services || [];

  return <main className="mx-auto max-w-5xl space-y-6 px-4 py-8 text-left sm:px-6 lg:px-8">
    <header className="flex flex-wrap items-start justify-between gap-4">
      <div className="space-y-2"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Chi tiết đặt phòng</p><h1 className="text-3xl font-medium">Booking {booking.booking_code}</h1><StatusBadge status={booking.status} /></div>
      <Button asChild variant="outline"><Link to="/customer/bookings"><ArrowLeft className="size-4" aria-hidden="true" />Danh sách booking</Link></Button>
    </header>

    {feedback && <div role="status" aria-live="polite" className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-foreground">{feedback}</div>}

    <Card className="rounded-3xl border-border/60">
      <CardHeader><CardTitle className="text-xl">Tiến trình đặt phòng</CardTitle></CardHeader>
      <CardContent>
        {terminal ? <div className="rounded-2xl border border-accent/40 bg-accent-pale/50 p-4"><p className="font-medium">Booking kết thúc với trạng thái <StatusBadge status={booking.status} /></p>{booking.note && <p className="mt-2 text-sm text-muted-foreground">Ghi chú / lý do: {booking.note}</p>}</div> : <div className="overflow-x-auto"><ol aria-label="Tiến trình booking" className="grid min-w-[520px] grid-cols-4 gap-4">
          {WORKFLOW_STEPS.map((step, index) => {
            const done = index < currentStep;
            const active = index === currentStep;
            return <li key={step.key} aria-current={active ? 'step' : undefined} className={`relative flex flex-col items-center gap-2 text-center ${index < WORKFLOW_STEPS.length - 1 ? 'after:absolute after:left-1/2 after:right-[-50%] after:top-4 after:z-0 after:h-0.5 after:bg-border after:content-[\'\']' : ''}`}>
              <span className={`relative z-10 grid size-9 place-items-center rounded-full border-2 text-sm font-semibold ${done ? 'border-primary bg-primary text-primary-foreground' : active ? 'border-accent bg-accent-pale text-foreground' : 'border-border bg-background text-muted-foreground'}`}>{done ? '✓' : index + 1}</span>
              <span className={`text-xs sm:text-sm ${active ? 'font-semibold text-foreground' : 'text-muted-foreground'}`}>{step.label}</span>
            </li>;
          })}
        </ol></div>}
      </CardContent>
    </Card>

    <div className="grid gap-6 md:grid-cols-2">
      <Card className="rounded-3xl border-border/60">
        <CardHeader><CardTitle className="text-xl">Phòng và cơ sở lưu trú</CardTitle></CardHeader>
        <CardContent className="space-y-4">
          <div><p className="font-medium">{booking.room?.name || 'Phòng lưu trú'}</p><p className="text-sm text-muted-foreground">Số phòng {booking.room?.room_number || '—'}</p></div>
          <Separator />
          <div className="flex gap-3"><MapPin className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" /><div><p className="font-medium">{booking.room?.property?.name || 'Cơ sở lưu trú'}</p><p className="text-sm text-muted-foreground">{booking.room?.property?.address || 'Chưa có địa chỉ'}</p></div></div>
        </CardContent>
      </Card>
      <Card className="rounded-3xl border-border/60">
        <CardHeader><CardTitle className="text-xl">Thông tin lưu trú</CardTitle></CardHeader>
        <CardContent className="space-y-4">
          <p className="flex items-center gap-3 text-sm"><CalendarDays className="size-4 text-primary" aria-hidden="true" />{formatDate(booking.check_in_date)} – {formatDate(booking.check_out_date)}</p>
          <p className="flex items-center gap-3 text-sm"><Users className="size-4 text-primary" aria-hidden="true" />{booking.guest_count} khách · {booking.number_of_nights} đêm</p>
          {booking.note && <p className="rounded-xl bg-muted/60 p-3 text-sm text-muted-foreground">Ghi chú: {booking.note}</p>}
        </CardContent>
      </Card>
    </div>

    <Card className="rounded-3xl border-border/60">
      <CardHeader><CardTitle className="text-xl">Chi phí</CardTitle></CardHeader>
      <CardContent className="space-y-6">
        <div className="flex flex-wrap justify-between gap-3"><div><p className="font-medium">Tiền phòng</p><p className="text-sm text-muted-foreground">{formatCurrency(booking.price_per_night)} × {booking.number_of_nights} đêm</p></div><p className="font-semibold">{formatCurrency(booking.room_total)}</p></div>
        <Separator />
        <div className="space-y-3"><h2 className="font-medium">Dịch vụ sử dụng</h2>
          {services.length === 0 ? <p className="text-sm text-muted-foreground">Chưa có dịch vụ được ghi nhận cho booking này.</p> : <div className="overflow-x-auto"><Table>
            <TableHeader><TableRow><TableHead>Dịch vụ</TableHead><TableHead>SL</TableHead><TableHead>Đơn giá</TableHead><TableHead className="text-right">Thành tiền</TableHead></TableRow></TableHeader>
            <TableBody>{services.map((service) => <TableRow key={service.id}><TableCell><span className="font-medium">{service.service_name}</span><span className="block text-xs text-muted-foreground">{service.unit}</span></TableCell><TableCell>{service.quantity}</TableCell><TableCell>{formatCurrency(service.unit_price)}</TableCell><TableCell className="text-right">{formatCurrency(service.amount)}</TableCell></TableRow>)}</TableBody>
          </Table></div>}
          <div className="flex justify-between border-t border-border pt-3 font-semibold"><span>Tổng dịch vụ</span><span>{formatCurrency(booking.services_total ?? 0)}</span></div>
        </div>
      </CardContent>
    </Card>

    <Card className="flex flex-wrap items-center justify-between gap-4 rounded-3xl border-border/60 p-5">
      <div><p className="font-medium">Thao tác booking</p><p className="text-sm text-muted-foreground">Trạng thái mới nhất từ StayHub.</p>{/* TODO Thành viên 3: thêm nút Xem hóa đơn khi module Invoice bàn giao. */}</div>
      {canCancel && <Button variant="destructive" onClick={() => setConfirmOpen(true)}>Hủy đặt phòng</Button>}
    </Card>

    <ConfirmDialog isOpen={confirmOpen} title="Xác nhận hủy booking" message={`Bạn có chắc chắn muốn hủy booking ${booking.booking_code}?`} confirmText="Xác nhận hủy" cancelText="Giữ booking" isDanger loading={cancelling} onConfirm={handleCancel} onCancel={() => setConfirmOpen(false)} />
  </main>;
}
