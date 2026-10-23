import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  Star,
  Sparkles,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  RotateCcw,
  AlertCircle,
  WifiOff,
  CalendarCheck,
  Compass,
  Loader2,
} from 'lucide-react';
import { reviewService, parseReviewError } from '@/services/reviewService';
import { CustomerReviewCard } from '@/components/reviews/CustomerReviewCard';

/**
 * Trang "Đánh giá của tôi" dành cho Khách hàng (StayNest Quiet Luxury).
 * Tuyến đường: /customer/reviews
 *
 * Kết nối trực tiếp API Backend:
 * GET /api/customer/reviews?page={page}&per_page=6
 */
export default function CustomerReviewsPage() {
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isPaging, setIsPaging] = useState(false);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [reloadTrigger, setReloadTrigger] = useState(0);
  const initialLoadedRef = useRef(false);

  const [paginationMeta, setPaginationMeta] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 6,
  });

  useEffect(() => {
    let isMounted = true;
    const controller = new AbortController();

    const fetchReviews = async () => {
      if (!initialLoadedRef.current) {
        setLoading(true);
      } else {
        setIsPaging(true);
      }
      setError(null);

      try {
        const res = await reviewService.getCustomerReviews(
          { page, perPage: 6 },
          { signal: controller.signal }
        );
        if (!isMounted) return;

        const list = res?.data || (Array.isArray(res) ? res : []);
        const meta = res?.meta || {};
        const total = Number(meta.total) || 0;
        const lastPage = Number(meta.last_page) || 1;
        const currentPage = Number(meta.current_page) || page;

        // Xử lý an toàn: Nếu do dữ liệu thay đổi mà page hiện tại vượt lastPage
        if (currentPage > lastPage && lastPage >= 1) {
          setPage(lastPage);
          return;
        }

        setReviews(list);
        setPaginationMeta({
          currentPage,
          lastPage,
          total,
          perPage: Number(meta.per_page) || 6,
        });
      } catch (err) {
        if (!isMounted) return;
        if (
          err.name === 'CanceledError' ||
          err.name === 'AbortError' ||
          err.code === 'ERR_CANCELED'
        ) {
          return;
        }

        setError(parseReviewError(err));
      } finally {
        if (isMounted) {
          initialLoadedRef.current = true;
          setLoading(false);
          setIsPaging(false);
        }
      }
    };

    fetchReviews();

    return () => {
      isMounted = false;
      controller.abort();
    };
  }, [page, reloadTrigger]);

  const handlePageChange = (newPage) => {
    if (newPage === page || newPage < 1 || newPage > paginationMeta.lastPage) return;
    setPage(newPage);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleRefresh = () => {
    setReloadTrigger((c) => c + 1);
  };

  return (
    <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
      {/* 1. Page Header (StayNest Quiet Luxury Pattern) */}
      <div className="border-b border-border/60 pb-6">
        <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
          <Sparkles className="h-3.5 w-3.5" />
          <span>Hội viên · Đánh giá & Nhận xét</span>
        </p>
        <h1 className="mt-1 font-display text-2xl font-bold leading-tight text-primary sm:text-3xl md:text-4xl">
          Đánh giá của tôi
        </h1>
        <p className="mt-1.5 text-xs text-muted-foreground sm:text-sm">
          Xem lại lịch sử đánh giá và cảm nhận thực tế của bạn cho các kỳ nghỉ đã hoàn tất trên StayHub.
        </p>
      </div>

      {/* 2. Overview Bar: Total Count & Refresh Action */}
      <div className="mt-6 flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-2 text-xs text-muted-foreground sm:text-sm">
          <span className="font-semibold text-ink">
            {paginationMeta.total.toLocaleString('vi-VN')}
          </span>
          <span>lượt đánh giá đã đóng góp cho cộng đồng</span>
        </div>

        <button
          type="button"
          disabled={loading || isPaging}
          onClick={handleRefresh}
          className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-border/70 bg-card px-3.5 py-1.5 text-xs font-medium text-ink transition hover:border-primary/40 hover:text-primary disabled:opacity-40"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading || isPaging ? 'animate-spin' : ''}`} />
          <span>Làm mới</span>
        </button>
      </div>

      {/* 3. Main Content Area */}
      <div className="relative mt-6">
        {/* Loading Overlay khi chuyển trang */}
        {isPaging && (
          <div className="absolute inset-0 z-10 flex items-center justify-center rounded-3xl bg-background/50 backdrop-blur-[1px]">
            <Loader2 className="h-6 w-6 animate-spin text-primary" />
          </div>
        )}

        {/* TRẠNG THÁI 1: Skeleton Loading lần đầu */}
        {loading && reviews.length === 0 && (
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
            {[1, 2, 3, 4].map((i) => (
              <div
                key={i}
                className="animate-pulse rounded-3xl border border-border/60 bg-card/60 p-6 space-y-4"
              >
                <div className="flex items-start justify-between">
                  <div className="space-y-2 flex-1">
                    <div className="h-5 w-48 rounded bg-muted" />
                    <div className="h-3 w-32 rounded bg-muted/60" />
                  </div>
                  <div className="h-6 w-20 rounded-full bg-muted/60" />
                </div>
                <div className="h-16 w-full rounded-2xl bg-muted/40" />
                <div className="flex items-center justify-between pt-2">
                  <div className="h-3.5 w-24 rounded bg-muted/60" />
                  <div className="h-3.5 w-16 rounded bg-muted/60" />
                </div>
              </div>
            ))}
          </div>
        )}

        {/* TRẠNG THÁI 2: Error State */}
        {error && (
          <div className="rounded-3xl border border-border/70 bg-card p-8 text-center sm:p-12">
            <div className="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-destructive/10 text-destructive">
              {error.isNetworkError ? (
                <WifiOff className="h-7 w-7" />
              ) : (
                <AlertCircle className="h-7 w-7" />
              )}
            </div>
            <h2 className="font-display text-xl font-bold text-ink sm:text-2xl">
              Không thể tải danh sách đánh giá
            </h2>
            <p className="mx-auto mt-2 max-w-md text-xs leading-relaxed text-muted-foreground sm:text-sm">
              {error.message}
            </p>

            <div className="mt-6 flex justify-center gap-3">
              {error.status === 401 ? (
                <Link
                  to="/login?role=customer"
                  className="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
                >
                  <span>Đăng nhập lại</span>
                </Link>
              ) : (
                <button
                  type="button"
                  onClick={handleRefresh}
                  className="inline-flex cursor-pointer items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all active:scale-95"
                >
                  <RotateCcw className="h-3.5 w-3.5" />
                  <span>Thử lại</span>
                </button>
              )}
            </div>
          </div>
        )}

        {/* TRẠNG THÁI 3: Empty State (Khách hàng chưa có đánh giá nào) */}
        {!loading && !error && reviews.length === 0 && (
          <div className="rounded-3xl border border-border/70 bg-card p-8 text-center sm:p-14">
            <div className="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-2xl border border-accent/30 bg-accent/10 text-accent">
              <Star className="h-8 w-8 fill-accent text-accent" />
            </div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">
              ✦ Trải nghiệm nghỉ dưỡng
            </p>
            <h2 className="mt-1 font-display text-2xl font-bold text-primary sm:text-3xl">
              Bạn chưa có đánh giá nào
            </h2>
            <p className="mx-auto mt-3 max-w-md text-xs leading-relaxed text-muted-foreground sm:text-sm">
              Những đánh giá thực tế của bạn sau khi hoàn tất kỳ nghỉ sẽ được lưu trữ và hiển thị tại đây. Đóng góp của bạn giúp cộng đồng du lịch StayHub ngày càng minh bạch và hoàn thiện.
            </p>

            <div className="mt-8 flex flex-wrap justify-center gap-3">
              <Link
                to="/customer/bookings"
                className="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
              >
                <CalendarCheck className="h-3.5 w-3.5" />
                <span>Xem đơn đặt phòng của tôi</span>
              </Link>
              <Link
                to="/search"
                className="inline-flex items-center gap-2 rounded-full border border-border bg-white px-5 py-2.5 text-xs font-semibold text-ink hover:border-primary/40 hover:text-primary transition-all"
              >
                <Compass className="h-3.5 w-3.5" />
                <span>Khám phá chỗ nghỉ</span>
              </Link>
            </div>
          </div>
        )}

        {/* TRẠNG THÁI 4: Success — Grid danh sách đánh giá */}
        {!loading && !error && reviews.length > 0 && (
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
            {reviews.map((item) => (
              <CustomerReviewCard key={item.id} review={item} />
            ))}
          </div>
        )}
      </div>

      {/* 4. Server-side Pagination Bar */}
      {!loading && !error && paginationMeta.lastPage > 1 && (
        <div className="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border/60 bg-card px-5 py-3.5">
          <div className="text-xs text-muted-foreground">
            Hiển thị trang <span className="font-semibold text-ink">{paginationMeta.currentPage}</span> /{' '}
            <span className="font-semibold text-ink">{paginationMeta.lastPage}</span> (Tổng cộng{' '}
            {paginationMeta.total} đánh giá)
          </div>

          <div className="flex items-center gap-1.5">
            <button
              type="button"
              disabled={paginationMeta.currentPage <= 1 || isPaging}
              onClick={() => handlePageChange(paginationMeta.currentPage - 1)}
              className="inline-flex cursor-pointer items-center gap-1 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-ink transition hover:border-primary/40 hover:text-primary disabled:pointer-events-none disabled:opacity-40"
            >
              <ChevronLeft className="h-3.5 w-3.5" />
              <span>Trước</span>
            </button>

            {Array.from({ length: paginationMeta.lastPage }).map((_, idx) => {
              const p = idx + 1;
              const isActive = p === paginationMeta.currentPage;
              return (
                <button
                  key={p}
                  type="button"
                  disabled={isPaging}
                  onClick={() => handlePageChange(p)}
                  className={`h-8 w-8 cursor-pointer rounded-full text-xs font-medium transition ${
                    isActive
                      ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                      : 'border border-border bg-card text-ink hover:border-primary/40 hover:text-primary'
                  }`}
                >
                  {p}
                </button>
              );
            })}

            <button
              type="button"
              disabled={paginationMeta.currentPage >= paginationMeta.lastPage || isPaging}
              onClick={() => handlePageChange(paginationMeta.currentPage + 1)}
              className="inline-flex cursor-pointer items-center gap-1 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-ink transition hover:border-primary/40 hover:text-primary disabled:pointer-events-none disabled:opacity-40"
            >
              <span>Sau</span>
              <ChevronRight className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
