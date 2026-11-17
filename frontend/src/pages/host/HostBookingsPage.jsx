import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, ChevronLeft, ChevronRight, Search, RefreshCw, MapPin, Users } from 'lucide-react';
import { hostBookingService, HOST_BOOKING_STATUSES, formatHostDate, formatHostVnd } from '@/services/hostBookingService';
import { bookingDisplayError } from '@/utils/bookingDisplayError';
import { BOOKING_STATUS_CONFIG } from '@/services/bookingService';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';

const PAGE_SIZE = 10;

export default function HostBookingsPage() {
  const [bookings, setBookings] = useState([]);
  const [filters, setFilters] = useState({ status: '', keyword: '', startDate: '', endDate: '' });
  const [appliedFilters, setAppliedFilters] = useState(filters);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({ total: 0, last_page: 1, current_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reloadKey, setReloadKey] = useState(0);

  const loadBookings = useCallback(async (signal) => {
    setLoading(true);
    setError('');
    try {
      const result = await hostBookingService.list({ page, perPage: PAGE_SIZE, ...appliedFilters }, { signal });
      setBookings(result?.data || []);
      setMeta(result?.meta || { total: 0, last_page: 1, current_page: page });
    } catch (loadError) {
      if (loadError.name !== 'CanceledError' && loadError.name !== 'AbortError') {
        setBookings([]);
        setError(bookingDisplayError(loadError, 'Không thể tải danh sách booking.'));
      }
    } finally {
      if (!signal.aborted) setLoading(false);
    }
  }, [page, appliedFilters]);

  useEffect(() => {
    const controller = new AbortController();
    const start = window.setTimeout(() => loadBookings(controller.signal), 0);
    return () => {
      window.clearTimeout(start);
      controller.abort();
    };
  }, [loadBookings, reloadKey]);

  const applyFilters = (event) => {
    event.preventDefault();
    if (filters.startDate && filters.endDate && filters.startDate > filters.endDate) {
      setError('Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.');
      return;
    }
    setError('');
    setLoading(true);
    setPage(1);
    setAppliedFilters(filters);
  };

  const retryLoad = () => {
    if (error.startsWith('Ngày kết thúc')) {
      setError('');
      return;
    }
    setLoading(true);
    setReloadKey((key) => key + 1);
  };

  const lastPage = Math.max(1, Number(meta.last_page) || 1);
  const currentPage = Number(meta.current_page) || page;
  const total = Number(meta.total) || 0;

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 text-left">
      <header className="rounded-3xl border border-border/60 bg-gradient-to-br from-white to-surface/60 p-5 shadow-xs sm:p-7">
        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-accent">Vận hành lưu trú</p>
        <h1 className="mt-1 font-display text-3xl font-bold text-primary sm:text-4xl">Đơn đặt phòng</h1>
        <p className="mt-2 text-sm text-muted-foreground">Theo dõi yêu cầu mới và cập nhật tiến trình lưu trú của khách.</p>
      </header>

      <form onSubmit={applyFilters} className="grid min-w-0 gap-3 rounded-2xl border border-border/70 bg-white p-4 shadow-xs md:grid-cols-2 xl:grid-cols-[minmax(0,1.3fr)_minmax(150px,1fr)_minmax(150px,1fr)_minmax(150px,1fr)_auto]">
        <label className="relative">
          <span className="sr-only">Tìm mã booking hoặc khách</span>
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={filters.keyword} onChange={(event) => setFilters({ ...filters, keyword: event.target.value })} placeholder="Mã booking, tên hoặc email khách" className="h-10 rounded-xl pl-9" />
        </label>
        <label>
          <span className="sr-only">Lọc trạng thái</span>
          <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })} className="h-10 w-full rounded-xl border border-border bg-white px-3 text-sm outline-none focus:border-primary">
            <option value="">Tất cả trạng thái</option>
            {Object.entries(HOST_BOOKING_STATUSES).map(([key, value]) => <option key={key} value={key}>{value.label}</option>)}
          </select>
        </label>
        <label className="relative">
          <span className="sr-only">Ngày nhận phòng từ</span>
          <CalendarDays className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input type="date" value={filters.startDate} onChange={(event) => setFilters({ ...filters, startDate: event.target.value })} aria-label="Ngày nhận phòng từ" className="h-10 rounded-xl pl-9 pr-2" />
        </label>
        <label className="relative">
          <span className="sr-only">Ngày trả phòng đến</span>
          <CalendarDays className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input type="date" value={filters.endDate} onChange={(event) => setFilters({ ...filters, endDate: event.target.value })} aria-label="Ngày trả phòng đến" className="h-10 rounded-xl pl-9 pr-2" />
        </label>
        <Button type="submit" className="h-10 rounded-xl">
          <Search className="h-4 w-4" /> Lọc booking
        </Button>
      </form>

      {error && (
        <Card role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-none">
          <span>{error}</span>
          <Button variant="outline" type="button" onClick={retryLoad} className="h-9 border-rose-200 bg-white text-rose-800 hover:bg-rose-100">
            <RefreshCw className="h-3.5 w-3.5" /> Thử lại
          </Button>
        </Card>
      )}

      <div className="flex items-center justify-between text-sm text-muted-foreground">
        <span>{loading ? 'Đang tải danh sách…' : `${total} booking`}</span>
        {!loading && total > 0 && <span>Trang {currentPage}/{lastPage}</span>}
      </div>

      {loading ? (
        <div className="space-y-3" aria-label="Đang tải booking" aria-busy="true">
          {[1, 2, 3].map((item) => <Skeleton key={item} className="h-36 rounded-2xl" />)}
        </div>
      ) : !error && bookings.length === 0 ? (
        <Card className="rounded-2xl border-dashed p-8 text-center sm:p-12">
          <CalendarDays className="mx-auto h-8 w-8 text-muted-foreground" />
          <h2 className="mt-3 font-display text-xl font-bold text-primary">Chưa có booking phù hợp</h2>
          <p className="mt-1 text-sm text-muted-foreground">Thử thay đổi bộ lọc hoặc quay lại sau khi có yêu cầu mới.</p>
        </Card>
      ) : !error && (
        <div className="space-y-3 md:hidden">
          {bookings.map((booking) => {
            const status = HOST_BOOKING_STATUSES[booking.status] || { label: booking.status, style: 'border-border bg-surface text-ink' };
            const color = BOOKING_STATUS_CONFIG[booking.status] || { bg: 'bg-stone-100 border-stone-200', text: 'text-stone-700', dot: 'bg-stone-500' };
            return (
              <Card key={booking.id} className="overflow-hidden rounded-2xl border-border/70 p-4 shadow-xs sm:p-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-display text-lg font-bold text-primary">{booking.booking_code || `Booking #${booking.id}`}</h2>
                      <Badge variant="outline" className={`gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${color.bg} ${color.text}`}><span className={`h-1.5 w-1.5 rounded-full ${color.dot}`} aria-hidden="true" />{status.label}</Badge>
                    </div>
                    <p className="mt-1 truncate text-sm font-semibold text-ink">{booking.customer?.name || 'Khách hàng'}</p>
                    <p className="text-xs text-muted-foreground">{booking.customer?.email || 'Chưa có email'}</p>
                  </div>
                <div className="grid min-w-0 grid-cols-2 gap-x-3 gap-y-2 text-xs sm:gap-x-5 sm:text-right">
                    <span className="text-muted-foreground">Nhận phòng</span><strong className="text-ink">{formatHostDate(booking.check_in_date)}</strong>
                    <span className="text-muted-foreground">Trả phòng</span><strong className="text-ink">{formatHostDate(booking.check_out_date)}</strong>
                    <span className="text-muted-foreground">Tổng tiền phòng</span><strong className="text-primary">{formatHostVnd(booking.room_total)}</strong>
                  </div>
                </div>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-3">
                  <div className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5" />{booking.room?.property?.name || 'Cơ sở lưu trú'}</span>
                    <span className="inline-flex items-center gap-1.5"><Users className="h-3.5 w-3.5" />{booking.guest_count} khách</span>
                  </div>
                  <Link to={`/host/bookings/${booking.id}`} className="rounded-lg bg-primary px-3.5 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary-mid">Xem chi tiết</Link>
                </div>
              </Card>
            );
          })}
        </div>
      )}

      {!loading && !error && bookings.length > 0 && (
        <Card className="hidden overflow-x-auto rounded-2xl border-border/70 p-0 shadow-xs md:block">
          <table className="w-full min-w-[820px] text-left text-sm">
            <thead className="bg-surface/70 text-xs uppercase tracking-wide text-muted-foreground">
              <tr><th className="px-4 py-3">Booking / khách</th><th className="px-4 py-3">Cơ sở / phòng</th><th className="px-4 py-3">Ngày lưu trú</th><th className="px-4 py-3">Khách</th><th className="px-4 py-3">Tiền phòng</th><th className="px-4 py-3">Trạng thái</th><th className="px-4 py-3"><span className="sr-only">Thao tác</span></th></tr>
            </thead>
            <tbody className="divide-y divide-border/60">
              {bookings.map((booking) => {
                const status = HOST_BOOKING_STATUSES[booking.status] || { label: booking.status };
                const color = BOOKING_STATUS_CONFIG[booking.status] || { bg: 'bg-stone-100 border-stone-200', text: 'text-stone-700', dot: 'bg-stone-500' };
                return (
                  <tr key={booking.id} className="align-top hover:bg-surface/30">
                    <td className="px-4 py-4"><div className="font-semibold text-primary">{booking.booking_code || `Booking #${booking.id}`}</div><div className="mt-1 text-xs text-ink">{booking.customer?.name || 'Khách hàng'}</div><div className="text-xs text-muted-foreground">{booking.customer?.email || 'Chưa có email'}</div></td>
                    <td className="px-4 py-4"><div className="font-medium text-ink">{booking.room?.property?.name || 'Cơ sở lưu trú'}</div><div className="mt-1 text-xs text-muted-foreground">{booking.room?.name || 'Phòng lưu trú'}</div></td>
                    <td className="whitespace-nowrap px-4 py-4"><div>{formatHostDate(booking.check_in_date)}</div><div className="text-xs text-muted-foreground">đến {formatHostDate(booking.check_out_date)}</div></td>
                    <td className="px-4 py-4">{booking.guest_count} khách</td>
                    <td className="whitespace-nowrap px-4 py-4 font-semibold text-primary">{formatHostVnd(booking.room_total)}</td>
                    <td className="px-4 py-4"><Badge variant="outline" className={`gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${color.bg} ${color.text}`}><span className={`h-1.5 w-1.5 rounded-full ${color.dot}`} aria-hidden="true" />{status.label}</Badge></td>
                    <td className="px-4 py-4"><Link to={`/host/bookings/${booking.id}`} className="whitespace-nowrap font-semibold text-primary hover:underline">Xem chi tiết</Link></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </Card>
      )}

      {!loading && !error && lastPage > 1 && (
        <nav aria-label="Phân trang booking" className="flex items-center justify-center gap-4 py-2">
          <Button variant="outline" type="button" disabled={currentPage <= 1} onClick={() => { setLoading(true); setPage((current) => Math.max(1, current - 1)); }}><ChevronLeft className="h-4 w-4" /> Trước</Button>
          <span className="text-sm text-muted-foreground">{currentPage} / {lastPage}</span>
          <Button variant="outline" type="button" disabled={currentPage >= lastPage} onClick={() => { setLoading(true); setPage((current) => Math.min(lastPage, current + 1)); }}>Sau <ChevronRight className="h-4 w-4" /></Button>
        </nav>
      )}
    </div>
  );
}
