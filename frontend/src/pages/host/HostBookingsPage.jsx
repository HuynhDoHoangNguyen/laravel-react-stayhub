import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, ChevronLeft, ChevronRight, Search, RefreshCw, MapPin, Users } from 'lucide-react';
import { hostBookingService, HOST_BOOKING_STATUSES, formatHostDate, formatHostVnd } from '@/services/hostBookingService';

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
        setError(loadError.message || 'Không thể tải danh sách booking.');
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
      <header>
        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-accent">Vận hành lưu trú</p>
        <h1 className="mt-1 font-display text-3xl font-bold text-primary sm:text-4xl">Đơn đặt phòng</h1>
        <p className="mt-2 text-sm text-muted-foreground">Theo dõi yêu cầu mới và cập nhật tiến trình lưu trú của khách.</p>
      </header>

      <form onSubmit={applyFilters} className="grid gap-3 rounded-2xl border border-border/70 bg-white p-4 shadow-xs md:grid-cols-2 xl:grid-cols-[1.3fr_1fr_1fr_1fr_auto]">
        <label className="relative">
          <span className="sr-only">Tìm mã booking hoặc khách</span>
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input value={filters.keyword} onChange={(event) => setFilters({ ...filters, keyword: event.target.value })} placeholder="Mã booking, tên hoặc email khách" className="h-10 w-full rounded-xl border border-border bg-white pl-9 pr-3 text-sm outline-none focus:border-primary" />
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
          <input type="date" value={filters.startDate} onChange={(event) => setFilters({ ...filters, startDate: event.target.value })} aria-label="Ngày nhận phòng từ" className="h-10 w-full rounded-xl border border-border bg-white pl-9 pr-2 text-sm outline-none focus:border-primary" />
        </label>
        <label className="relative">
          <span className="sr-only">Ngày trả phòng đến</span>
          <CalendarDays className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input type="date" value={filters.endDate} onChange={(event) => setFilters({ ...filters, endDate: event.target.value })} aria-label="Ngày trả phòng đến" className="h-10 w-full rounded-xl border border-border bg-white pl-9 pr-2 text-sm outline-none focus:border-primary" />
        </label>
        <button type="submit" className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary-mid">
          <Search className="h-4 w-4" /> Lọc booking
        </button>
      </form>

      {error && (
        <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
          <span>{error}</span>
          <button type="button" onClick={retryLoad} className="inline-flex items-center gap-1.5 font-semibold hover:underline">
            <RefreshCw className="h-3.5 w-3.5" /> Thử lại
          </button>
        </div>
      )}

      <div className="flex items-center justify-between text-sm text-muted-foreground">
        <span>{loading ? 'Đang tải danh sách…' : `${total} booking`}</span>
        {!loading && total > 0 && <span>Trang {currentPage}/{lastPage}</span>}
      </div>

      {loading ? (
        <div className="space-y-3" aria-label="Đang tải booking">
          {[1, 2, 3].map((item) => <div key={item} className="h-36 animate-pulse rounded-2xl bg-white/80" />)}
        </div>
      ) : !error && bookings.length === 0 ? (
        <section className="rounded-2xl border border-dashed border-border bg-white p-12 text-center">
          <CalendarDays className="mx-auto h-8 w-8 text-muted-foreground" />
          <h2 className="mt-3 font-display text-xl font-bold text-primary">Chưa có booking phù hợp</h2>
          <p className="mt-1 text-sm text-muted-foreground">Thử thay đổi bộ lọc hoặc quay lại sau khi có yêu cầu mới.</p>
        </section>
      ) : !error && (
        <div className="space-y-3">
          {bookings.map((booking) => {
            const status = HOST_BOOKING_STATUSES[booking.status] || { label: booking.status, style: 'border-border bg-surface text-ink' };
            return (
              <article key={booking.id} className="rounded-2xl border border-border/70 bg-white p-4 shadow-xs sm:p-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-display text-lg font-bold text-primary">{booking.booking_code || `Booking #${booking.id}`}</h2>
                      <span className={`rounded-full border px-2.5 py-1 text-xs font-semibold ${status.style}`}>{status.label}</span>
                    </div>
                    <p className="mt-1 truncate text-sm font-semibold text-ink">{booking.customer?.name || 'Khách hàng'}</p>
                    <p className="text-xs text-muted-foreground">{booking.customer?.email || 'Chưa có email'}</p>
                  </div>
                  <div className="grid grid-cols-2 gap-x-5 gap-y-2 text-xs sm:text-right">
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
              </article>
            );
          })}
        </div>
      )}

      {!loading && !error && lastPage > 1 && (
        <nav aria-label="Phân trang booking" className="flex items-center justify-center gap-4 py-2">
          <button type="button" disabled={currentPage <= 1} onClick={() => { setLoading(true); setPage((current) => Math.max(1, current - 1)); }} className="inline-flex items-center gap-1 rounded-lg border border-border bg-white px-3 py-2 text-sm disabled:opacity-40"><ChevronLeft className="h-4 w-4" /> Trước</button>
          <span className="text-sm text-muted-foreground">{currentPage} / {lastPage}</span>
          <button type="button" disabled={currentPage >= lastPage} onClick={() => { setLoading(true); setPage((current) => Math.min(lastPage, current + 1)); }} className="inline-flex items-center gap-1 rounded-lg border border-border bg-white px-3 py-2 text-sm disabled:opacity-40">Sau <ChevronRight className="h-4 w-4" /></button>
        </nav>
      )}
    </div>
  );
}
