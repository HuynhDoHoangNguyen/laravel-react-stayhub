import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import BookingCard from '@/components/booking/BookingCard';
import EmptyState from '@/components/booking/shared/EmptyState';
import ErrorState from '@/components/booking/shared/ErrorState';
import LoadingState from '@/components/booking/shared/LoadingState';
import Pagination from '@/components/booking/shared/Pagination';
import { getCustomerBookings } from '@/services/bookingService';
import { getApiErrorMessage } from '@/utils/getApiErrorMessage';

const TABS = [
  { key: '', label: 'Tất cả' },
  { key: 'PENDING', label: 'Chờ xác nhận' },
  { key: 'CONFIRMED', label: 'Đã xác nhận' },
  { key: 'CHECKED_IN', label: 'Đang lưu trú' },
  { key: 'COMPLETED', label: 'Đã hoàn thành' },
  { key: 'CANCELLED', label: 'Đã hủy' },
];

export default function MyBookingsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const status = searchParams.get('status') || '';
  const page = Number(searchParams.get('page') || 1);
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadBookings = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = { page, per_page: 15 };
      if (status) params.status = status;
      setResult(await getCustomerBookings(params));
    } catch (requestError) {
      setError(getApiErrorMessage(requestError).message);
    } finally {
      setLoading(false);
    }
  }, [page, status]);

  useEffect(() => {
    let active = true;
    Promise.resolve().then(() => active && loadBookings());
    return () => { active = false; };
  }, [loadBookings]);

  const selectStatus = (nextStatus) => {
    const next = new URLSearchParams();
    if (nextStatus) next.set('status', nextStatus);
    next.set('page', '1');
    setSearchParams(next);
  };

  const selectPage = (nextPage) => {
    const next = new URLSearchParams(searchParams);
    next.set('page', String(nextPage));
    setSearchParams(next);
  };

  return <main className="mx-auto min-h-[70vh] max-w-7xl space-y-7 px-4 py-8 text-left sm:px-6 lg:px-8">
    <header className="flex flex-wrap items-end justify-between gap-4">
      <div className="space-y-2"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Khu vực khách hàng</p><h1 className="text-3xl font-medium sm:text-4xl">Đặt phòng của tôi</h1><p className="text-sm text-muted-foreground">Theo dõi các kỳ lưu trú và trạng thái xác nhận.</p></div>
      <Button asChild variant="outline"><Link to="/search">Tìm phòng</Link></Button>
    </header>

    <div role="group" aria-label="Lọc theo trạng thái" className="flex snap-x gap-2 overflow-x-auto border-b border-border/70 pb-2">
      {TABS.map((tab) => <button key={tab.key || 'all'} type="button" aria-pressed={status === tab.key} onClick={() => selectStatus(tab.key)} className={`shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${status === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}>{tab.label}</button>)}
    </div>

    {loading && <LoadingState message="Đang tải lịch sử đặt phòng..." />}
    {!loading && error && <ErrorState title="Không thể tải lịch sử đặt phòng" message={error} onRetry={loadBookings} />}
    {!loading && !error && result?.data?.length === 0 && <EmptyState title="Chưa có đặt phòng" description={status ? 'Chưa có booking ở trạng thái này.' : 'Các đặt phòng của bạn sẽ xuất hiện tại đây.'} action={<Button asChild><Link to="/search">Tìm phòng lưu trú</Link></Button>} />}
    {!loading && !error && (result?.data?.length || 0) > 0 && <>
      <section aria-label="Danh sách đặt phòng" className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">{result.data.map((booking) => <BookingCard key={booking.id} booking={booking} />)}</section>
      {result.meta && <Pagination currentPage={result.meta.current_page} lastPage={result.meta.last_page} total={result.meta.total} perPage={result.meta.per_page} onPageChange={selectPage} />}
    </>}
  </main>;
}
