import { Star, Award, TrendingUp } from 'lucide-react';

function getRatingSentiment(score) {
  if (!score || score <= 0) return 'Chưa có xếp hạng';
  if (score >= 4.8) return 'Xuất sắc';
  if (score >= 4.5) return 'Tuyệt hảo';
  if (score >= 4.0) return 'Rất tốt';
  if (score >= 3.5) return 'Tốt';
  if (score >= 3.0) return 'Khá';
  return 'Cần cải thiện';
}

/**
 * HostReviewSummary Component — StayNest Quiet Luxury
 *
 * Hiển thị điểm trung bình toàn cục của Host và biểu đồ phân bổ 5 mức sao.
 * Dữ liệu lấy trực tiếp từ `summary` của Backend Host Review API.
 *
 * @param {Object} props
 * @param {Object} [props.summary] Đối tượng { total_reviews, average_rating, rating_breakdown }
 * @param {number|null} [props.selectedRating] Mức sao đang lọc (1..5 hoặc null)
 * @param {Function} [props.onSelectRating] Callback khi Host click chọn lọc theo sao
 */
export function HostReviewSummary({ summary, selectedRating, onSelectRating }) {
  const total = Number(summary?.total_reviews) || 0;
  const avg = summary?.average_rating != null ? Number(summary.average_rating) : null;
  const breakdown = summary?.rating_breakdown || { '5': 0, '4': 0, '3': 0, '2': 0, '1': 0 };

  const starLevels = [5, 4, 3, 2, 1];

  return (
    <section
      aria-label="Thống kê đánh giá của cơ sở lưu trú"
      className="grid grid-cols-1 gap-5 lg:grid-cols-12"
    >
      {/* Cột 1: Điểm trung bình & Tổng lượt đánh giá (5/12 cột desktop) */}
      <div className="flex flex-col justify-between rounded-3xl border border-border/70 bg-card p-6 shadow-xs sm:p-7 lg:col-span-5">
        <div>
          <div className="flex items-center justify-between">
            <span className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">
              <Award className="h-3.5 w-3.5" />
              <span>Chỉ số dịch vụ Host</span>
            </span>
            <span className="inline-flex items-center gap-1 rounded-full border border-primary/15 bg-primary/5 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
              <TrendingUp className="h-3 w-3" />
              <span>Toàn hệ thống</span>
            </span>
          </div>

          <div className="mt-5 flex items-baseline gap-3">
            <span className="font-display text-5xl font-bold tracking-tight text-primary sm:text-6xl">
              {avg !== null ? avg.toFixed(1) : '—'}
            </span>
            <div className="flex flex-col">
              <span className="text-xs font-semibold text-muted-foreground">trên thang 5.0</span>
              <span className="text-sm font-bold text-ink">
                {getRatingSentiment(avg)}
              </span>
            </div>
          </div>

          {/* 5 sao trực quan */}
          <div className="mt-3 flex items-center gap-1">
            {Array.from({ length: 5 }).map((_, i) => {
              const starVal = i + 1;
              const isFilled = avg !== null && starVal <= Math.round(avg);
              return (
                <Star
                  key={i}
                  className={`h-4 w-4 ${
                    isFilled ? 'fill-accent text-accent' : 'text-muted-foreground/25'
                  }`}
                />
              );
            })}
          </div>
        </div>

        <div className="mt-6 border-t border-border/60 pt-4 text-xs text-muted-foreground">
          <p>
            Dựa trên{' '}
            <strong className="font-bold text-ink">
              {total.toLocaleString('vi-VN')}
            </strong>{' '}
            lượt đánh giá thực tế từ khách hàng đã hoàn tất kỳ nghỉ tại các cơ sở lưu trú do bạn sở hữu.
          </p>
        </div>
      </div>

      {/* Cột 2: Phân bố 5 mức sao (7/12 cột desktop) */}
      <div className="flex flex-col justify-between rounded-3xl border border-border/70 bg-card p-6 shadow-xs sm:p-7 lg:col-span-7">
        <div>
          <div className="flex items-center justify-between">
            <h2 className="font-display text-base font-bold text-primary sm:text-lg">
              Phân bổ mức độ hài lòng
            </h2>
            <span className="text-xs text-muted-foreground">
              {total > 0 ? `${total} lượt đánh giá` : 'Chưa có dữ liệu'}
            </span>
          </div>

          <div className="mt-4 space-y-2.5">
            {starLevels.map((star) => {
              const count = Number(breakdown[String(star)]) || 0;
              const percent = total > 0 ? Math.round((count / total) * 100) : 0;
              const isFilterActive = Number(selectedRating) === star;

              return (
                <button
                  key={star}
                  type="button"
                  onClick={() => onSelectRating && onSelectRating(isFilterActive ? null : star)}
                  className={`group flex w-full cursor-pointer items-center gap-3 rounded-xl p-1.5 text-xs transition-colors ${
                    isFilterActive
                      ? 'bg-primary/10 ring-1 ring-primary/30'
                      : 'hover:bg-surface/70'
                  }`}
                  title={`Lọc đánh giá ${star} sao (${count} lượt)`}
                  aria-label={`Lọc theo đánh giá ${star} sao: ${count} lượt`}
                >
                  {/* Nhãn mức sao */}
                  <div className="flex w-16 shrink-0 items-center justify-between gap-1 font-semibold text-ink">
                    <span>{star} sao</span>
                    <Star className="h-3 w-3 fill-accent text-accent" />
                  </div>

                  {/* Thanh Progress bar */}
                  <div className="relative h-2.5 flex-1 overflow-hidden rounded-full bg-surface">
                    <div
                      className={`h-full rounded-full transition-all duration-500 ${
                        isFilterActive
                          ? 'bg-primary'
                          : 'bg-primary/80 group-hover:bg-primary'
                      }`}
                      style={{ width: `${percent}%` }}
                    />
                  </div>

                  {/* Số lượng và % */}
                  <div className="flex w-20 shrink-0 justify-end gap-1.5 text-[11px] text-muted-foreground">
                    <span className="font-semibold text-ink">{count}</span>
                    <span className="text-muted-foreground/80">({percent}%)</span>
                  </div>
                </button>
              );
            })}
          </div>
        </div>

        <div className="mt-4 flex items-center justify-between border-t border-border/60 pt-3 text-[11.5px] text-muted-foreground">
          <span>* Nhấp vào mức sao để lọc nhanh danh sách đánh giá</span>
          {selectedRating && (
            <button
              type="button"
              onClick={() => onSelectRating && onSelectRating(null)}
              className="font-medium text-primary hover:underline cursor-pointer"
            >
              Bỏ lọc sao
            </button>
          )}
        </div>
      </div>
    </section>
  );
}

export default HostReviewSummary;
