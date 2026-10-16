import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, CalendarDays, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import LoadingState from '@/components/booking/shared/LoadingState';
import { previewBooking, createBooking } from '@/services/bookingService';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDate } from '@/utils/formatDate';
import { getApiErrorMessage, REASON_TRANSLATIONS } from '@/utils/getApiErrorMessage';

export default function BookingCheckoutPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const roomId = searchParams.get('room_id');
  const checkInDate = searchParams.get('check_in_date');
  const checkOutDate = searchParams.get('check_out_date');
  const guestCount = Number(searchParams.get('guest_count') || 1);
  const [note, setNote] = useState('');
  const [quote, setQuote] = useState(null);
  const [loading, setLoading] = useState(true);
  const [previewError, setPreviewError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);
  const [fieldErrors, setFieldErrors] = useState({});

  const loadQuote = useCallback(async () => {
    if (!roomId || !checkInDate || !checkOutDate) {
      setPreviewError('Thiếu thông tin phòng hoặc ngày lưu trú. Vui lòng tìm phòng lại.');
      setLoading(false);
      return;
    }
    setLoading(true);
    setPreviewError('');
    try {
      const response = await previewBooking({ room_id: Number(roomId), check_in_date: checkInDate, check_out_date: checkOutDate, guest_count: guestCount });
      setQuote(response.data);
    } catch (error) {
      setPreviewError(getApiErrorMessage(error).message);
    } finally {
      setLoading(false);
    }
  }, [roomId, checkInDate, checkOutDate, guestCount]);

  useEffect(() => {
    let active = true;
    Promise.resolve().then(() => active && loadQuote());
    return () => { active = false; };
  }, [loadQuote]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);
    setSubmitError(null);
    setFieldErrors({});
    try {
      const response = await createBooking({ room_id: Number(roomId), check_in_date: checkInDate, check_out_date: checkOutDate, guest_count: guestCount, note: note.trim() || undefined });
      navigate(`/customer/bookings/${response.data.id}`);
    } catch (error) {
      const parsed = getApiErrorMessage(error);
      setSubmitError({ status: error.response?.status, message: parsed.message });
      setFieldErrors(parsed.fieldErrors || {});
    } finally {
      setSubmitting(false);
    }
  };

  const availability = quote?.availability?.available;
  const noteError = fieldErrors.note?.[0];
  const availabilityReasons = (quote?.availability?.reasons || []).map((reason) => REASON_TRANSLATIONS[reason] || reason);

  if (loading) return <main className="mx-auto max-w-6xl px-4 py-12 text-left sm:px-6"><LoadingState message="Đang lấy báo giá từ hệ thống..." /></main>;
  if (previewError) return <main className="mx-auto max-w-3xl space-y-5 px-4 py-12 text-left sm:px-6">
    <div role="alert" aria-live="polite" className="flex gap-3 rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-destructive"><AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" /><div><p className="font-semibold">Không thể lập báo giá</p><p className="mt-1 text-sm">{previewError}</p></div></div>
    <Button asChild variant="outline"><Link to="/search"><ArrowLeft className="size-4" aria-hidden="true" />Quay lại tìm phòng</Link></Button>
  </main>;

  return <main className="mx-auto max-w-6xl px-4 py-8 text-left sm:px-6 lg:px-8">
    <header className="mb-8 space-y-2">
      <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Đặt phòng · Bước cuối</p>
      <h1 className="text-3xl font-medium sm:text-4xl">Xác nhận đặt phòng</h1>
      <p className="text-sm text-muted-foreground">Kiểm tra thông tin lưu trú và giá do StayHub báo.</p>
    </header>

    {submitError && <div role="alert" aria-live="assertive" className={`mb-6 flex gap-3 rounded-2xl border p-4 ${submitError.status === 409 ? 'border-destructive/30 bg-destructive/5 text-destructive' : 'border-accent/40 bg-accent-pale/50 text-foreground'}`}>
      <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <div className="space-y-2 text-sm">
        <p className="font-semibold">{submitError.status === 409 ? 'Phòng không còn khả dụng' : submitError.status === 401 ? 'Cần đăng nhập' : 'Không thể xác nhận đặt phòng'}</p>
        <p>{submitError.status === 401 ? 'Bạn cần đăng nhập bằng tài khoản Khách hàng để tiếp tục.' : submitError.message}</p>
        {submitError.status === 401 ? <Link className="font-medium underline underline-offset-4" to="/login-test">Đăng nhập</Link> : null}
        {submitError.status === 409 ? <Link className="font-medium underline underline-offset-4" to="/search">Chọn ngày hoặc phòng khác</Link> : null}
        {submitError.status === 422 && Object.entries(fieldErrors).map(([field, messages]) => <p key={field} className="text-sm">{field}: {Array.isArray(messages) ? messages.join(' ') : messages}</p>)}
      </div>
    </div>}

    <form onSubmit={handleSubmit} aria-busy={submitting}>
      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(300px,.8fr)]">
        <div className="space-y-5">
          <Card className="rounded-3xl border-border/60">
            <CardHeader><div className="flex items-center justify-between gap-3"><CardTitle className="text-xl">Thông tin lưu trú</CardTitle><Button asChild size="sm" variant="outline"><Link to="/search">Đổi ngày</Link></Button></div></CardHeader>
            <CardContent className="space-y-4">
              <dl className="grid gap-4 sm:grid-cols-2">
                <div><dt className="text-xs text-muted-foreground">Phòng</dt><dd className="mt-1 font-medium">#{quote?.room_id || roomId}</dd></div>
                <div><dt className="text-xs text-muted-foreground">Số khách</dt><dd className="mt-1 flex items-center gap-2 font-medium"><Users className="size-4 text-primary" aria-hidden="true" />{quote?.guest_count ?? guestCount} khách</dd></div>
                <div><dt className="text-xs text-muted-foreground">Ngày nhận phòng</dt><dd className="mt-1 flex items-center gap-2 font-medium"><CalendarDays className="size-4 text-primary" aria-hidden="true" />{formatDate(quote?.check_in_date || checkInDate)}</dd></div>
                <div><dt className="text-xs text-muted-foreground">Ngày trả phòng</dt><dd className="mt-1 flex items-center gap-2 font-medium"><CalendarDays className="size-4 text-primary" aria-hidden="true" />{formatDate(quote?.check_out_date || checkOutDate)}</dd></div>
              </dl>
              <p className={`rounded-xl px-4 py-3 text-sm font-medium ${availability ? 'bg-primary/10 text-primary' : 'bg-destructive/10 text-destructive'}`} role="status">
                {availability ? 'Phòng còn trống theo kiểm tra hiện tại.' : 'Phòng không còn trống trong khoảng ngày này.'}
                {availabilityReasons.length > 0 ? ` ${availabilityReasons.join(', ')}` : ''}
              </p>
            </CardContent>
          </Card>
          <Card className="rounded-3xl border-border/60">
            <CardHeader><CardTitle className="text-xl">Ghi chú cho cơ sở lưu trú</CardTitle></CardHeader>
            <CardContent className="space-y-2">
              <Label htmlFor="booking-note">Yêu cầu đặc biệt (không bắt buộc)</Label>
              <Textarea id="booking-note" rows={4} value={note} onChange={(event) => setNote(event.target.value)} disabled={submitting} aria-invalid={Boolean(noteError)} aria-describedby={noteError ? 'booking-note-error' : undefined} placeholder="Ghi chú thêm cho cơ sở lưu trú" />
              {noteError && <p id="booking-note-error" className="text-sm text-destructive">{noteError}</p>}
            </CardContent>
          </Card>
        </div>

        <Card className="rounded-3xl border-border/60 shadow-[0_15px_50px_-35px_rgba(17,26,19,0.5)] lg:sticky lg:top-6">
          <CardHeader><CardTitle className="text-xl">Tóm tắt giá</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="flex justify-between gap-4 text-sm"><span className="text-muted-foreground">Số đêm</span><span>{quote?.number_of_nights} đêm</span></div>
            <div className="flex justify-between gap-4 text-sm"><span className="text-muted-foreground">Giá mỗi đêm</span><span>{formatCurrency(quote?.price_per_night)}</span></div>
            <div className="border-t border-border pt-4"><div className="flex justify-between gap-4 font-semibold"><span>Tiền phòng</span><span className="text-lg text-primary">{formatCurrency(quote?.room_total)}</span></div><p className="mt-2 text-xs text-muted-foreground">Giá cuối cùng được backend xác nhận khi tạo đặt phòng.</p></div>
            <Button type="submit" className="h-12 w-full rounded-full" disabled={submitting || availability === false}>
              {submitting ? 'Đang gửi yêu cầu…' : 'Xác nhận đặt phòng'}
            </Button>
            <p className="text-center text-xs text-muted-foreground">Yêu cầu sẽ được gửi đến cơ sở lưu trú để xác nhận.</p>
          </CardContent>
        </Card>
      </div>
    </form>
  </main>;
}
