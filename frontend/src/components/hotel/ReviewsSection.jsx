import { useState, useEffect, useId } from "react";
import {
  Star,
  ChevronLeft,
  ChevronRight,
  RotateCcw,
  AlertCircle,
  WifiOff,
  HelpCircle,
  Info,
  SlidersHorizontal,
  Loader2,
} from "lucide-react";
import { reviewService, parseReviewError } from "@/services/reviewService";

function formatReviewDate(isoStr) {
  if (!isoStr) return "";
  try {
    const d = new Date(isoStr);
    if (isNaN(d.getTime())) return isoStr;
    return d.toLocaleDateString("vi-VN", {
      day: "numeric",
      month: "long",
      year: "numeric",
    });
  } catch {
    return isoStr;
  }
}

function getInitials(name) {
  if (!name || typeof name !== "string") return "KH";
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Component hiển thị danh sách đánh giá từ Backend cho Property có danh tính xác thực.
 */
function ActiveReviewsSection({ propertyId }) {
  const sortSelectId = useId();

  // State dữ liệu từ Backend API
  const [reviews, setReviews] = useState([]);
  const [summary, setSummary] = useState(null);
  const [meta, setMeta] = useState(null);

  // State điều khiển phân trang & bộ lọc
  const [currentPage, setCurrentPage] = useState(1);
  const [filterRating, setFilterRating] = useState(null);
  const [sortBy, setSortBy] = useState("latest");

  // State trạng thái tải & lỗi
  const [isLoading, setIsLoading] = useState(true);
  const [isPaging, setIsPaging] = useState(false);
  const [error, setError] = useState(null);
  const [retryCount, setRetryCount] = useState(0);

  const isValidId =
    Boolean(propertyId) && !isNaN(Number(propertyId)) && Number(propertyId) > 0;

  useEffect(() => {
    if (!isValidId) return;

    const controller = new AbortController();
    let isSubscribed = true;

    reviewService
      .getPublicPropertyReviews(
        propertyId,
        {
          page: currentPage,
          perPage: 6,
          rating: filterRating,
          sortBy,
        },
        { signal: controller.signal }
      )
      .then((res) => {
        if (!isSubscribed) return;
        setReviews(res?.data || []);
        if (res?.summary) {
          setSummary(res.summary);
        }
        setMeta(res?.meta || null);
        setIsLoading(false);
        setIsPaging(false);
      })
      .catch((err) => {
        if (!isSubscribed) return;
        if (
          err.name === "CanceledError" ||
          err.name === "AbortError" ||
          err.code === "ERR_CANCELED"
        ) {
          return;
        }
        setError(parseReviewError(err));
        setIsLoading(false);
        setIsPaging(false);
      });

    return () => {
      isSubscribed = false;
      controller.abort();
    };
  }, [propertyId, currentPage, filterRating, sortBy, retryCount, isValidId]);

  // Handlers
  const handleFilterChange = (newRating) => {
    setIsPaging(true);
    setFilterRating(newRating);
    setCurrentPage(1);
  };

  const handleSortChange = (e) => {
    setIsPaging(true);
    setSortBy(e.target.value);
    setCurrentPage(1);
  };

  const handlePageChange = (newPage) => {
    setIsPaging(true);
    setCurrentPage(newPage);
    const section = document.getElementById("reviews");
    if (section) {
      section.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  const handleRetry = () => {
    setIsLoading(true);
    setError(null);
    setRetryCount((c) => c + 1);
  };

  // Trạng thái ID không hợp lệ
  if (!isValidId) {
    return (
      <section
        id="reviews"
        className="rounded-3xl border border-border/70 bg-card p-6 text-center md:p-8"
      >
        <div className="mx-auto mb-3.5 grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground">
          <HelpCircle className="h-6 w-6" />
        </div>
        <h2 className="font-display text-xl text-primary md:text-2xl">
          Không tìm thấy cơ sở lưu trú
        </h2>
        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
          Mã cơ sở lưu trú không hợp lệ hoặc không tồn tại trên hệ thống đánh giá.
        </p>
      </section>
    );
  }

  // TRẠNG THÁI: Loading Skeleton lần đầu
  if (isLoading && !summary) {
    return (
      <section
        id="reviews"
        className="rounded-3xl border border-border/70 bg-card p-6 md:p-8 animate-pulse space-y-6"
      >
        <div className="flex flex-col gap-2">
          <div className="h-7 w-56 rounded-md bg-muted" />
          <div className="h-4 w-72 rounded-md bg-muted/60" />
        </div>

        <div className="grid grid-cols-1 gap-6 rounded-2xl border border-border/50 bg-surface/30 p-6 md:grid-cols-2">
          <div className="flex flex-col justify-center space-y-3">
            <div className="h-10 w-24 rounded-lg bg-muted" />
            <div className="h-4 w-40 rounded-md bg-muted/60" />
          </div>
          <div className="space-y-2">
            {[5, 4, 3, 2, 1].map((s) => (
              <div key={s} className="flex items-center gap-3">
                <div className="h-3 w-10 rounded bg-muted/60" />
                <div className="h-2 flex-1 rounded-full bg-muted/40" />
                <div className="h-3 w-6 rounded bg-muted/60" />
              </div>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {[1, 2].map((i) => (
            <div
              key={i}
              className="rounded-3xl border border-border/60 bg-card/60 p-5 space-y-3"
            >
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-muted" />
                <div className="space-y-1.5 flex-1">
                  <div className="h-4 w-28 rounded bg-muted" />
                  <div className="h-3 w-20 rounded bg-muted/60" />
                </div>
              </div>
              <div className="h-3 w-full rounded bg-muted/50" />
              <div className="h-3 w-4/5 rounded bg-muted/40" />
            </div>
          ))}
        </div>
      </section>
    );
  }

  // TRẠNG THÁI: Lỗi 404 (Không tìm thấy Property trên Backend)
  if (error?.isNotFound) {
    return (
      <section
        id="reviews"
        className="rounded-3xl border border-border/70 bg-card p-6 text-center md:p-8"
      >
        <div className="mx-auto mb-3.5 grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground">
          <HelpCircle className="h-6 w-6" />
        </div>
        <h2 className="font-display text-xl text-primary md:text-2xl">
          Không tìm thấy cơ sở lưu trú
        </h2>
        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
          Cơ sở lưu trú này không tồn tại trên hệ thống đánh giá của máy chủ (Mã lỗi: 404).
        </p>
      </section>
    );
  }

  // TRẠNG THÁI: Lỗi Mạng hoặc Server 500
  if (error) {
    return (
      <section
        id="reviews"
        className="rounded-3xl border border-border/70 bg-card p-6 text-center md:p-8"
      >
        <div className="mx-auto mb-3.5 grid h-12 w-12 place-items-center rounded-full bg-destructive/10 text-destructive">
          {error.isNetworkError ? (
            <WifiOff className="h-6 w-6" />
          ) : (
            <AlertCircle className="h-6 w-6" />
          )}
        </div>
        <h2 className="font-display text-xl text-ink md:text-2xl">
          Không thể tải đánh giá
        </h2>
        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
          {error.message}
        </p>
        <button
          type="button"
          onClick={handleRetry}
          className="mt-5 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-medium text-primary-foreground transition hover:bg-primary-mid active:scale-95"
        >
          <RotateCcw className="h-3.5 w-3.5" />
          Thử lại
        </button>
      </section>
    );
  }

  // TRẠNG THÁI: Valid Empty (Backend 200 nhưng review_count = 0)
  const totalReviews = Number(summary?.review_count) || 0;
  const avgRating = summary?.average_rating ? Number(summary.average_rating) : null;

  if (totalReviews === 0) {
    return (
      <section
        id="reviews"
        className="rounded-3xl border border-border/70 bg-card p-6 md:p-8"
      >
        <h2 className="font-display text-xl text-primary md:text-2xl">
          Đánh giá từ khách lưu trú
        </h2>
        <div className="mt-4 rounded-2xl border border-border/50 bg-surface/40 p-6 text-center">
          <p className="text-sm font-medium text-ink">
            Chỗ nghỉ này chưa có đánh giá.
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            Những đánh giá sau kỳ nghỉ thực tế của khách hàng sẽ được hiển thị tại đây.
          </p>
        </div>
      </section>
    );
  }

  // TRẠNG THÁI: Success — Hiển thị đầy đủ Quiet Luxury Review UI
  const breakdown = summary?.rating_breakdown || {};

  return (
    <section id="reviews" className="space-y-6">
      {/* 1. Header & Summary Box */}
      <div className="rounded-3xl border border-border/70 bg-card p-6 shadow-sm md:p-8">
        <h2 className="font-display text-xl text-primary md:text-2xl">
          Đánh giá từ khách lưu trú
        </h2>
        <p className="mt-1 text-[13px] text-muted-foreground">
          Xác thực minh bạch từ khách hàng đã hoàn tất kỳ nghỉ thực tế.
        </p>

        {/* Aggregate Rating & Breakdown Grid */}
        <div className="mt-6 grid grid-cols-1 items-center gap-6 rounded-2xl border border-border/50 bg-surface/30 p-5 md:grid-cols-[220px_1fr] md:p-6">
          {/* Big Rating Display */}
          <div className="flex flex-col items-center justify-center border-b border-border/40 pb-5 text-center md:border-b-0 md:border-r md:pb-0 md:pr-6">
            <div className="font-display text-4xl font-bold tracking-tight text-ink md:text-5xl">
              {avgRating !== null ? avgRating.toFixed(1) : "—"}
            </div>
            <div className="mt-2 inline-flex items-center gap-1">
              {Array.from({ length: 5 }).map((_, i) => (
                <Star
                  key={i}
                  className={`h-4 w-4 ${
                    avgRating !== null && i < Math.round(avgRating)
                      ? "fill-accent text-accent"
                      : "text-muted-foreground/30"
                  }`}
                />
              ))}
            </div>
            <p className="mt-2 text-xs font-medium text-muted-foreground">
              {totalReviews.toLocaleString("vi-VN")} lượt đánh giá
            </p>
          </div>

          {/* 5-Star Breakdown Bars (Bấm vào thanh để lọc nhanh) */}
          <div className="space-y-2">
            {[5, 4, 3, 2, 1].map((star) => {
              const count = Number(breakdown[String(star)]) || 0;
              const percentage =
                totalReviews > 0 ? Math.round((count / totalReviews) * 100) : 0;
              const isSelected = filterRating === star;

              return (
                <button
                  key={star}
                  type="button"
                  onClick={() => handleFilterChange(isSelected ? null : star)}
                  className={`flex w-full items-center gap-3 rounded-lg px-2 py-1 text-left text-xs transition hover:bg-surface/80 ${
                    isSelected
                      ? "bg-surface font-semibold text-primary"
                      : "text-muted-foreground"
                  }`}
                  title={`Lọc đánh giá ${star} sao (${count} đánh giá)`}
                >
                  <span className="flex w-12 shrink-0 items-center gap-1 text-[12px] font-medium text-ink">
                    <span>{star}</span>
                    <Star className="h-3 w-3 fill-accent text-accent" />
                  </span>
                  <div className="h-2 flex-1 overflow-hidden rounded-full border border-border/40 bg-surface">
                    <div
                      className="h-full rounded-full bg-accent transition-all duration-500"
                      style={{ width: `${percentage}%` }}
                    />
                  </div>
                  <span className="w-9 shrink-0 text-right font-mono text-[11px] text-muted-foreground">
                    {count}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        {/* 2. Filter Chips & Sort Select */}
        <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-5">
          {/* Star Filter Chips */}
          <div className="flex flex-wrap items-center gap-1.5">
            <button
              type="button"
              onClick={() => handleFilterChange(null)}
              className={`rounded-full px-3.5 py-1.5 text-xs font-medium transition ${
                filterRating === null
                  ? "bg-primary text-primary-foreground shadow-sm"
                  : "border border-border bg-card text-muted-foreground hover:border-primary/40 hover:text-ink"
              }`}
            >
              Tất cả ({totalReviews})
            </button>
            {[5, 4, 3, 2, 1].map((s) => {
              const count = Number(breakdown[String(s)]) || 0;
              const active = filterRating === s;
              return (
                <button
                  key={s}
                  type="button"
                  onClick={() => handleFilterChange(active ? null : s)}
                  className={`inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-medium transition ${
                    active
                      ? "bg-primary text-primary-foreground shadow-sm"
                      : "border border-border bg-card text-muted-foreground hover:border-primary/40 hover:text-ink"
                  }`}
                >
                  <span>{s}</span>
                  <Star className="h-3 w-3 fill-accent text-accent" />
                  <span className="text-[11px] opacity-75">({count})</span>
                </button>
              );
            })}
          </div>

          {/* Sort Selector */}
          <div className="flex items-center gap-2">
            <SlidersHorizontal className="h-3.5 w-3.5 text-muted-foreground" />
            <label htmlFor={sortSelectId} className="sr-only">
              Sắp xếp đánh giá
            </label>
            <select
              id={sortSelectId}
              value={sortBy}
              onChange={handleSortChange}
              className="rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-ink transition hover:border-primary/40 focus:border-primary focus:outline-none"
            >
              <option value="latest">Mới nhất</option>
              <option value="rating_desc">Điểm cao nhất</option>
              <option value="rating_asc">Điểm thấp nhất</option>
            </select>
          </div>
        </div>
      </div>

      {/* 3. Reviews List Grid */}
      <div className="relative">
        {/* Subtle Loading Overlay khi chuyển trang / đổi filter */}
        {isPaging && (
          <div className="absolute inset-0 z-10 flex items-center justify-center rounded-3xl bg-background/50 backdrop-blur-[1px]">
            <Loader2 className="h-6 w-6 animate-spin text-primary" />
          </div>
        )}

        {reviews.length === 0 ? (
          /* Empty Filter State */
          <div className="rounded-3xl border border-border/70 bg-card p-8 text-center">
            <p className="text-sm font-medium text-ink">
              Không tìm thấy đánh giá nào {filterRating ? `${filterRating} sao` : ""}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              Chưa có đánh giá nào phù hợp với bộ lọc hiện tại.
            </p>
            {filterRating !== null && (
              <button
                type="button"
                onClick={() => handleFilterChange(null)}
                className="mt-4 inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-4 py-2 text-xs font-medium text-ink transition hover:border-primary/40 hover:text-primary active:scale-95"
              >
                Xem tất cả đánh giá
              </button>
            )}
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {reviews.map((r) => {
              const ratingStars = Math.min(5, Math.max(1, Number(r.rating) || 5));
              const author = r.author || "Khách lưu trú";

              return (
                <article
                  key={r.id}
                  className="flex flex-col justify-between rounded-3xl border border-border/70 bg-card p-5 shadow-sm transition hover:border-primary/20 hover:shadow-md md:p-6"
                >
                  <div>
                    <header className="flex items-center justify-between gap-3">
                      <div className="flex min-w-0 items-center gap-3">
                        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/10 text-[13px] font-semibold text-primary ring-1 ring-primary/20">
                          {getInitials(author)}
                        </span>
                        <div className="min-w-0">
                          <div className="truncate text-[14px] font-medium text-ink">
                            {author}
                          </div>
                          <div className="text-[11.5px] text-muted-foreground">
                            {formatReviewDate(r.created_at)}
                          </div>
                        </div>
                      </div>

                      <div className="inline-flex shrink-0 items-center gap-0.5 rounded-full border border-border/50 bg-surface/60 px-2.5 py-1">
                        {Array.from({ length: 5 }).map((_, i) => (
                          <Star
                            key={i}
                            className={`h-3 w-3 ${
                              i < ratingStars
                                ? "fill-accent text-accent"
                                : "text-muted-foreground/30"
                            }`}
                          />
                        ))}
                        <span className="ml-1 text-[11px] font-semibold text-ink">
                          {ratingStars}.0
                        </span>
                      </div>
                    </header>

                    <p className="mt-3.5 whitespace-pre-line text-[13.5px] leading-relaxed text-ink/85">
                      {r.comment}
                    </p>
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </div>

      {/* 4. Server-side Pagination Bar */}
      {meta && meta.last_page > 1 && (
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border/60 bg-card px-5 py-3.5">
          <div className="text-xs text-muted-foreground">
            Hiển thị trang <span className="font-medium text-ink">{meta.current_page}</span> /{" "}
            <span className="font-medium text-ink">{meta.last_page}</span> (Tổng số{" "}
            {meta.total} đánh giá)
          </div>

          <div className="flex items-center gap-1.5">
            <button
              type="button"
              disabled={meta.current_page <= 1 || isPaging}
              onClick={() => handlePageChange(meta.current_page - 1)}
              className="inline-flex items-center gap-1 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-ink transition hover:border-primary/40 hover:text-primary disabled:opacity-40 disabled:pointer-events-none"
            >
              <ChevronLeft className="h-3.5 w-3.5" />
              Trước
            </button>

            {Array.from({ length: meta.last_page }).map((_, idx) => {
              const p = idx + 1;
              const isActive = p === meta.current_page;
              return (
                <button
                  key={p}
                  type="button"
                  disabled={isPaging}
                  onClick={() => handlePageChange(p)}
                  className={`h-8 w-8 rounded-full text-xs font-medium transition ${
                    isActive
                      ? "bg-primary text-primary-foreground shadow-sm"
                      : "border border-border bg-card text-ink hover:border-primary/40 hover:text-primary"
                  }`}
                >
                  {p}
                </button>
              );
            })}

            <button
              type="button"
              disabled={meta.current_page >= meta.last_page || isPaging}
              onClick={() => handlePageChange(meta.current_page + 1)}
              className="inline-flex items-center gap-1 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-ink transition hover:border-primary/40 hover:text-primary disabled:opacity-40 disabled:pointer-events-none"
            >
              Sau
              <ChevronRight className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      )}
    </section>
  );
}

/**
 * ReviewsSection Component — StayNest Quiet Luxury
 *
 * Tích hợp Public Property Reviews API thật:
 * GET /api/public/properties/{propertyId}/reviews
 *
 * @param {Object} props
 * @param {number|string} props.propertyId ID của cơ sở lưu trú
 * @param {'api'|'mock'} [props.propertySource='api'] Nguồn gốc của Property ID
 */
export function ReviewsSection({
  propertyId,
  propertySource = "api",
}) {
  // TRẠNG THÁI: Mock Identity Unavailable (Dev Mock chưa có Backend ID)
  if (propertySource === "mock") {
    return (
      <section
        id="reviews"
        className="rounded-3xl border border-border/70 bg-card p-6 md:p-8"
      >
        <h2 className="font-display text-xl text-primary md:text-2xl">
          Đánh giá từ khách lưu trú
        </h2>
        <div className="mt-4 flex items-start gap-3.5 rounded-2xl border border-border/60 bg-surface/60 p-4 md:p-5">
          <Info className="mt-0.5 h-5 w-5 shrink-0 text-accent" />
          <div className="space-y-1">
            <p className="text-sm font-medium text-ink">
              Đánh giá chưa khả dụng cho cơ sở giả lập
            </p>
            <p className="text-xs leading-relaxed text-muted-foreground">
              Dữ liệu chỗ nghỉ này hiện đang ở chế độ xem trước (Dev Mock) và
              chưa được liên kết với cơ sở lưu trú thực tế trên hệ thống máy
              chủ. Đánh giá xác thực sẽ tự động kích hoạt khi cơ sở được đồng bộ
              từ Backend.
            </p>
          </div>
        </div>
      </section>
    );
  }

  // Khi có identity từ API hoặc được chỉ định, render ActiveReviewsSection với key để reset state hoàn toàn khi đổi Property
  return <ActiveReviewsSection key={String(propertyId)} propertyId={propertyId} />;
}

export default ReviewsSection;
