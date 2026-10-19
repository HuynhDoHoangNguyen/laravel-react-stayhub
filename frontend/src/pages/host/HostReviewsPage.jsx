import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  Star,
  Search,
  X,
  RotateCcw,
  RefreshCw,
  AlertCircle,
  WifiOff,
  ChevronLeft,
  ChevronRight,
  Loader2,
  SlidersHorizontal,
  Hotel,
} from 'lucide-react';
import { reviewService, parseReviewError } from '@/services/reviewService';
import { HostReviewSummary } from '@/components/reviews/HostReviewSummary';
import { HostReviewCard } from '@/components/reviews/HostReviewCard';

const RATING_FILTER_OPTIONS = [
  { value: null, label: 'Tất cả số sao' },
  { value: 5, label: '5 sao ★' },
  { value: 4, label: '4 sao ★' },
  { value: 3, label: '3 sao ★' },
  { value: 2, label: '2 sao ★' },
  { value: 1, label: '1 sao ★' },
];

/**
 * Trang Quản lý Đánh giá của Host (StayNest Quiet Luxury Pattern)
 * Route: /host/reviews
 *
 * Tiêu thụ API Backend:
 * GET /api/host/reviews?page={page}&per_page=10&rating={rating}&search={search}
 */
export default function HostReviewsPage() {
  const [reviews, setReviews] = useState([]);
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isPaging, setIsPaging] = useState(false);
  const [error, setError] = useState(null);

  // Bộ lọc và tìm kiếm
  const [rating, setRating] = useState(null);
  const [searchInput, setSearchInput] = useState('');
  const [activeSearch, setActiveSearch] = useState('');
  const [page, setPage] = useState(1);
  const [reloadTrigger, setReloadTrigger] = useState(0);

  const initialLoadedRef = useRef(false);

  const [paginationMeta, setPaginationMeta] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 10,
  });

  useEffect(() => {
    let isMounted = true;
    const controller = new AbortController();

    const fetchHostReviews = async () => {
      if (!initialLoadedRef.current) {
        setLoading(true);
      } else {
        setIsPaging(true);
      }
      setError(null);

      try {
        const res = await reviewService.getHostReviews(
          {
            page,
            perPage: 10,
            rating,
            search: activeSearch,
          },
          { signal: controller.signal }
        );

        if (!isMounted) return;

        const list = res?.data || (Array.isArray(res) ? res : []);
        const meta = res?.meta || {};
        const sum = res?.summary || null;

        const total = Number(meta.total) || 0;
        const lastPage = Number(meta.last_page) || 1;
        const currentPage = Number(meta.current_page) || page;

        // Xử lý an toàn: nếu do thay đổi dữ liệu mà page hiện tại vượt lastPage
        if (currentPage > lastPage && lastPage >= 1) {
          setPage(lastPage);
          return;
        }

        setReviews(list);
        if (sum) {
          setSummary(sum);
        }
        setPaginationMeta({
          currentPage,
          lastPage,
          total,
          perPage: Number(meta.per_page) || 10,
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

    fetchHostReviews();

    return () => {
      isMounted = false;
      controller.abort();
    };
  }, [page, rating, activeSearch, reloadTrigger]);

  const handleRatingChange = (newRating) => {
    setRating(newRating);
    setPage(1);
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
    setActiveSearch(searchInput.trim());
  };

  const handleClearSearch = () => {
    setSearchInput('');
    setActiveSearch('');
    setPage(1);
  };

  const handleResetAllFilters = () => {
    setRating(null);
    setSearchInput('');
    setActiveSearch('');
    setPage(1);
  };

  const handleRefresh = () => {
    setReloadTrigger((prev) => prev + 1);
  };

  const handlePageChange = (newPage) => {
    if (newPage === page || newPage < 1 || newPage > paginationMeta.lastPage) return;
    setPage(newPage);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const hasActiveFilters = rating !== null || activeSearch !== '';

  return (
    <div className="mx-auto max-w-[1360px] space-y-6 pb-20 text-left">
      {/* 1. Header Trang Quản Trị (StayNest Host Header) */}
      <div className="flex flex-wrap items-end justify-between gap-4 border-b border-border/60 pb-5">
        <div>
          <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
            <Hotel className="h-3.5 w-3.5" />
            <span>Kênh Quản lý Host · Đánh giá & Nhận xét</span>
          </p>
          <h1 className="mt-1 font-display text-2xl font-bold leading-tight text-primary sm:text-3xl md:text-4xl">
            Đánh giá của khách hàng
          </h1>
          <p className="mt-1.5 text-xs text-muted-foreground sm:text-sm">
            Theo dõi mức độ hài lòng, lắng nghe trải nghiệm và phân tích chất lượng dịch vụ trên toàn bộ cơ sở lưu trú của bạn.
          </p>
        </div>

        <button
          type="button"
          disabled={loading || isPaging}
          onClick={handleRefresh}
          className="inline-flex cursor-pointer items-center gap-2 rounded-full border border-border/70 bg-card px-4 py-2 text-xs font-medium text-ink transition hover:border-primary/40 hover:text-primary disabled:opacity-40"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading || isPaging ? 'animate-spin' : ''}`} />
          <span>Làm mới dữ liệu</span>
        </button>
      </div>

      {/* 2. Error Alert (nếu có lỗi hệ thống hoặc phân quyền) */}
      {error && (
        <div className="rounded-3xl border border-destructive/20 bg-destructive/5 p-6 text-center sm:p-8">
          <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-destructive/10 text-destructive">
            {error.isNetworkError ? <WifiOff className="h-6 w-6" /> : <AlertCircle className="h-6 w-6" />}
          </div>
          <h2 className="font-display text-lg font-bold text-ink sm:text-xl">
            Không thể tải dữ liệu đánh giá
          </h2>
          <p className="mx-auto mt-1.5 max-w-md text-xs leading-relaxed text-muted-foreground sm:text-sm">
            {error.message}
          </p>
          <div className="mt-5 flex justify-center gap-3">
            {error.status === 401 ? (
              <Link
                to="/login?role=host"
                className="inline-flex items-center rounded-full bg-primary px-5 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
              >
                Đăng nhập lại
              </Link>
            ) : (
              <button
                type="button"
                onClick={handleRefresh}
                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-primary px-5 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
              >
                <RotateCcw className="h-3.5 w-3.5" />
                <span>Thử lại</span>
              </button>
            )}
          </div>
        </div>
      )}

      {/* 3. Skeleton Loading Lần Đầu */}
      {loading && !summary && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
            <div className="h-56 animate-pulse rounded-3xl bg-muted/50 lg:col-span-5" />
            <div className="h-56 animate-pulse rounded-3xl bg-muted/50 lg:col-span-7" />
          </div>
          <div className="h-14 animate-pulse rounded-2xl bg-muted/40" />
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-44 animate-pulse rounded-3xl bg-muted/40" />
            ))}
          </div>
        </div>
      )}

      {/* 4. KPI & Rating Summary Section */}
      {summary && (
        <HostReviewSummary
          summary={summary}
          selectedRating={rating}
          onSelectRating={handleRatingChange}
        />
      )}

      {/* 5. Toolbar Lọc & Tìm Kiếm */}
      <div className="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
        {/* Rating Filter Pills */}
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="flex items-center gap-1 text-xs font-semibold text-muted-foreground mr-1">
            <SlidersHorizontal className="h-3.5 w-3.5" />
            <span>Lọc:</span>
          </span>
          {RATING_FILTER_OPTIONS.map((opt) => {
            const isActive = rating === opt.value;
            return (
              <button
                key={String(opt.value)}
                type="button"
                onClick={() => handleRatingChange(opt.value)}
                className={`cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition-all ${
                  isActive
                    ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                    : 'bg-surface text-ink/80 hover:bg-primary/10 hover:text-primary'
                }`}
              >
                {opt.label}
              </button>
            );
          })}
        </div>

        {/* Search Input Form */}
        <form onSubmit={handleSearchSubmit} className="relative flex min-w-[260px] sm:max-w-xs items-center">
          <Search className="pointer-events-none absolute left-3.5 h-3.5 w-3.5 text-muted-foreground" />
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Tìm theo nhận xét hoặc tên khách..."
            className="h-9 w-full rounded-full border border-border/70 bg-surface/50 pl-9 pr-9 text-xs text-ink transition focus:border-primary/50 focus:bg-background focus:outline-none"
          />
          {searchInput && (
            <button
              type="button"
              onClick={handleClearSearch}
              className="absolute right-3 grid h-4 w-4 place-items-center text-muted-foreground hover:text-ink cursor-pointer"
              title="Xóa tìm kiếm"
            >
              <X className="h-3 w-3" />
            </button>
          )}
        </form>
      </div>

      {/* 6. Active Filter Indicators */}
      {hasActiveFilters && (
        <div className="flex flex-wrap items-center justify-between gap-2 px-1 text-xs text-muted-foreground">
          <div className="flex flex-wrap items-center gap-2">
            <span>Đang lọc theo:</span>
            {rating !== null && (
              <span className="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 font-medium text-primary">
                <span>{rating} sao</span>
                <button
                  type="button"
                  onClick={() => handleRatingChange(null)}
                  className="cursor-pointer hover:opacity-75"
                >
                  <X className="h-3 w-3" />
                </button>
              </span>
            )}
            {activeSearch && (
              <span className="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 font-medium text-primary">
                <span>Từ khóa: "{activeSearch}"</span>
                <button
                  type="button"
                  onClick={handleClearSearch}
                  className="cursor-pointer hover:opacity-75"
                >
                  <X className="h-3 w-3" />
                </button>
              </span>
            )}
            <span className="text-ink font-medium">({paginationMeta.total} kết quả phù hợp)</span>
          </div>

          <button
            type="button"
            onClick={handleResetAllFilters}
            className="cursor-pointer text-xs font-semibold text-primary hover:underline"
          >
            Xóa toàn bộ bộ lọc
          </button>
        </div>
      )}

      {/* 7. Danh Sách Đánh Giá (Cards Grid) */}
      <div className="relative min-h-[200px]">
        {/* Loading Overlay khi chuyển trang */}
        {isPaging && (
          <div className="absolute inset-0 z-10 flex items-center justify-center rounded-3xl bg-background/50 backdrop-blur-[1px]">
            <Loader2 className="h-6 w-6 animate-spin text-primary" />
          </div>
        )}

        {/* TRẠNG THÁI 1: Empty toàn cục (Host chưa từng nhận được đánh giá nào) */}
        {!loading && !error && summary?.total_reviews === 0 && (
          <div className="rounded-3xl border border-border/70 bg-card p-8 text-center sm:p-14">
            <div className="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-2xl border border-accent/30 bg-accent/10 text-accent">
              <Star className="h-8 w-8 fill-accent text-accent" />
            </div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">
              ✦ Trải nghiệm khách hàng
            </p>
            <h2 className="mt-1 font-display text-2xl font-bold text-primary sm:text-3xl">
              Chưa có đánh giá nào từ khách lưu trú
            </h2>
            <p className="mx-auto mt-3 max-w-md text-xs leading-relaxed text-muted-foreground sm:text-sm">
              Các đánh giá thực tế của khách hàng sau khi hoàn tất kỳ nghỉ tại các cơ sở của bạn sẽ tự động hiển thị và thống kê tại đây.
            </p>
          </div>
        )}

        {/* TRẠNG THÁI 2: Empty do bộ lọc (Không có kết quả nào khớp với search hoặc rating) */}
        {!loading && !error && summary?.total_reviews > 0 && reviews.length === 0 && (
          <div className="rounded-3xl border border-border/70 bg-card p-8 text-center sm:p-12">
            <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-muted text-muted-foreground">
              <Search className="h-6 w-6" />
            </div>
            <h2 className="font-display text-lg font-bold text-ink sm:text-xl">
              Không tìm thấy đánh giá phù hợp
            </h2>
            <p className="mx-auto mt-1 max-w-md text-xs text-muted-foreground sm:text-sm">
              Không có đánh giá nào khớp với tiêu chí lọc hoặc từ khóa tìm kiếm hiện tại của bạn.
            </p>
            <div className="mt-5">
              <button
                type="button"
                onClick={handleResetAllFilters}
                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
              >
                <RotateCcw className="h-3.5 w-3.5" />
                <span>Bỏ lọc để xem tất cả</span>
              </button>
            </div>
          </div>
        )}

        {/* TRẠNG THÁI 3: Hiển thị danh sách Cards */}
        {!loading && !error && reviews.length > 0 && (
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
            {reviews.map((item) => (
              <HostReviewCard key={item.id} review={item} />
            ))}
          </div>
        )}
      </div>

      {/* 8. Server-side Pagination Bar */}
      {!loading && !error && paginationMeta.lastPage > 1 && (
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border/60 bg-card px-5 py-3.5">
          <div className="text-xs text-muted-foreground">
            Hiển thị trang <span className="font-semibold text-ink">{paginationMeta.currentPage}</span> /{' '}
            <span className="font-semibold text-ink">{paginationMeta.lastPage}</span> (
            {paginationMeta.total} kết quả)
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
