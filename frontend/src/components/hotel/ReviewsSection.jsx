import { useState } from "react";
import { Star, ChevronDown } from "lucide-react";

function formatReviewDate(isoStr) {
  if (!isoStr) return "";
  try {
    const d = new Date(isoStr);
    if (isNaN(d.getTime())) return isoStr;
    return d.toLocaleDateString("vi-VN", {
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

export function ReviewsSection({
  reviews = [],
  averageRating,
  reviewCount,
}) {
  const [displayCount, setDisplayCount] = useState(4);

  const rating = Number(averageRating);
  const totalReviews = Number(reviewCount) || reviews.length;
  const hasRating = !isNaN(rating) && rating > 0;

  if (!reviews || reviews.length === 0) {
    return (
      <section id="reviews" className="rounded-3xl border border-border/70 bg-card p-6 md:p-8">
        <h2 className="font-display text-xl text-primary md:text-2xl">
          Đánh giá từ khách lưu trú
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          Chưa có đánh giá nào cho địa điểm lưu trú này. Những đánh giá sau kỳ nghỉ thực tế sẽ được hiển thị tại đây.
        </p>
      </section>
    );
  }

  const visibleReviews = reviews.slice(0, displayCount);
  const hasMore = reviews.length > displayCount;

  return (
    <section id="reviews" className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="font-display text-xl text-primary md:text-2xl">
            Đánh giá từ khách lưu trú
          </h2>
          <p className="mt-1 inline-flex items-center gap-2 text-[13.5px] text-muted-foreground">
            {hasRating && (
              <span className="inline-flex items-center gap-1 text-ink">
                <Star className="h-4 w-4 fill-accent text-accent" />
                <span className="font-semibold">{rating.toFixed(1)}</span>
              </span>
            )}
            <span>· Dựa trên {totalReviews.toLocaleString("vi-VN")} kỳ nghỉ xác thực</span>
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {visibleReviews.map((r) => {
          const author = r.author || r.guestName || "Khách lưu trú";
          const ratingStars = Math.min(5, Math.max(1, Number(r.rating) || 5));

          return (
            <article
              key={r.id}
              className="flex flex-col rounded-3xl border border-border/70 bg-card/70 p-5 shadow-sm md:p-6"
            >
              <header className="flex items-center gap-3">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/10 text-[13px] font-semibold text-primary">
                  {getInitials(author)}
                </span>
                <div className="min-w-0 flex-1">
                  <div className="truncate text-[14px] font-medium text-ink">
                    {author}
                  </div>
                  <div className="text-[11.5px] text-muted-foreground">
                    {formatReviewDate(r.createdAt)}
                  </div>
                </div>
                <div className="inline-flex items-center gap-0.5">
                  {Array.from({ length: 5 }).map((_, i) => (
                    <Star
                      key={i}
                      className={`h-3.5 w-3.5 ${
                        i < ratingStars
                          ? "fill-accent text-accent"
                          : "text-muted-foreground/30"
                      }`}
                    />
                  ))}
                </div>
              </header>

              <p className="mt-3 text-[13.5px] leading-relaxed text-ink/85">
                {r.comment}
              </p>
            </article>
          );
        })}
      </div>

      {hasMore && (
        <div className="flex justify-center pt-2">
          <button
            type="button"
            onClick={() => setDisplayCount((c) => c + 4)}
            className="inline-flex items-center gap-2 rounded-full border border-border bg-card px-6 py-2.5 text-[13px] font-medium text-ink transition hover:border-primary/40 hover:text-primary active:scale-95"
          >
            Xem thêm đánh giá ({reviews.length - displayCount})
            <ChevronDown className="h-4 w-4" />
          </button>
        </div>
      )}
    </section>
  );
}
