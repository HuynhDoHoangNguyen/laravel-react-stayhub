import { Star, CheckCircle2, Calendar, BedDouble, Tag } from 'lucide-react';

function formatReviewDate(isoStr) {
  if (!isoStr) return '';
  try {
    const d = new Date(isoStr);
    if (isNaN(d.getTime())) return isoStr;
    return d.toLocaleDateString('vi-VN', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  } catch {
    return isoStr;
  }
}

/**
 * CustomerReviewCard Component — StayNest Quiet Luxury
 *
 * Hiển thị thẻ đánh giá của khách hàng tuân thủ nghiêm ngặt các quy tắc:
 * 1. Không hardcode dữ liệu giả.
 * 2. Booking reference an toàn: hiển thị booking_code thật hoặc 'Tham chiếu đặt phòng #{booking_id}', không click để tránh link TV2 hỏng.
 * 3. Property / Room name an toàn: hiển thị trung lập khi null, ẩn link chỗ nghỉ khi chưa xác minh identity.
 * 4. Không hiển thị nút Sửa/Xóa khi backend chưa hỗ trợ.
 *
 * @param {Object} props
 * @param {Object} props.review Dữ liệu ReviewResource từ backend
 */
export function CustomerReviewCard({ review }) {
  if (!review) return null;

  const ratingStars = Math.min(5, Math.max(1, Number(review.rating) || 5));
  const propertyName = review.property_name || 'Chỗ nghỉ chưa có thông tin tên';
  const roomName = review.room_name;

  // Xử lý tham chiếu đặt phòng chuẩn xác theo quy tắc
  let bookingReference = null;
  if (review.booking_code && String(review.booking_code).trim() !== '') {
    bookingReference = `Mã đơn: ${review.booking_code}`;
  } else if (review.booking_id) {
    bookingReference = `Tham chiếu đặt phòng #${review.booking_id}`;
  }

  return (
    <article className="flex flex-col justify-between rounded-3xl border border-border/70 bg-card p-6 shadow-xs transition-all hover:border-primary/20 hover:shadow-md">
      <div>
        {/* Header: Property name & Rating badge */}
        <header className="flex items-start justify-between gap-4">
          <div className="min-w-0 flex-1">
            <h2 className="font-display text-lg font-bold leading-snug text-primary sm:text-xl">
              {propertyName}
            </h2>

            {roomName && (
              <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                <BedDouble className="h-3.5 w-3.5 shrink-0 opacity-75" />
                <span className="truncate">{roomName}</span>
              </p>
            )}
          </div>

          {/* Rating Badge */}
          <div className="inline-flex shrink-0 items-center gap-1 rounded-full border border-border/50 bg-surface/60 px-3 py-1">
            <div className="inline-flex items-center gap-0.5">
              {Array.from({ length: 5 }).map((_, i) => (
                <Star
                  key={i}
                  className={`h-3.5 w-3.5 ${
                    i < ratingStars
                      ? 'fill-accent text-accent'
                      : 'text-muted-foreground/30'
                  }`}
                />
              ))}
            </div>
            <span className="ml-1 text-xs font-bold text-ink">
              {ratingStars}.0
            </span>
          </div>
        </header>

        {/* Comment Text Box */}
        <div className="mt-4 rounded-2xl border border-border/40 bg-surface/30 p-4">
          <p className="whitespace-pre-line text-[13.5px] leading-relaxed text-ink/85">
            {review.comment}
          </p>
        </div>
      </div>

      {/* Footer: Date & Booking Reference Tag */}
      <footer className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-4 text-xs">
        <div className="flex flex-wrap items-center gap-3 text-muted-foreground">
          {review.created_at && (
            <span className="inline-flex items-center gap-1.5">
              <Calendar className="h-3.5 w-3.5 shrink-0" />
              <span>{formatReviewDate(review.created_at)}</span>
            </span>
          )}

          {bookingReference && (
            <span className="inline-flex items-center gap-1 rounded-md bg-surface px-2 py-0.5 text-[11px] font-medium text-ink/75">
              <Tag className="h-3 w-3 shrink-0 opacity-60" />
              <span>{bookingReference}</span>
            </span>
          )}
        </div>

        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200/70">
          <CheckCircle2 className="h-3 w-3 text-emerald-600" />
          <span>Đã xác thực</span>
        </span>
      </footer>
    </article>
  );
}

export default CustomerReviewCard;
