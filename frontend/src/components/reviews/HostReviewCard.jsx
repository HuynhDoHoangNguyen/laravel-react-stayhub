import { Star, Calendar, BedDouble, Building2, Tag, CheckCircle2 } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';

function initialsOf(name) {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return 'KH';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

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
 * HostReviewCard Component — StayNest Quiet Luxury
 *
 * Hiển thị đánh giá của khách hàng trong giao diện quản trị Host:
 * - Bảo vệ quyền riêng tư: Tuyệt đối không hiển thị email hoặc số điện thoại.
 * - Tham chiếu đặt phòng chuẩn xác: Hiển thị booking_code thật hoặc 'Tham chiếu đặt phòng #{booking_id}'.
 * - Không có nút Reply/Edit/Delete/Moderate khi backend chưa hỗ trợ.
 *
 * @param {Object} props
 * @param {Object} props.review Dữ liệu ReviewResource từ Host Review API
 */
export function HostReviewCard({ review }) {
  if (!review) return null;

  const ratingStars = Math.min(5, Math.max(1, Number(review.rating) || 5));
  const customerName = review.customer_name || 'Khách lưu trú';
  const propertyName = review.property_name || 'Chỗ nghỉ chưa có thông tin tên';
  const roomName = review.room_name;

  // Xử lý booking code an toàn
  let bookingRef = null;
  if (review.booking_code && String(review.booking_code).trim() !== '') {
    bookingRef = `Mã đơn: ${review.booking_code}`;
  } else if (review.booking_id) {
    bookingRef = `Tham chiếu đặt phòng #${review.booking_id}`;
  }

  return (
    <article className="flex flex-col justify-between rounded-3xl border border-border/70 bg-card p-6 shadow-xs transition-all hover:border-primary/20 hover:shadow-md">
      <div>
        {/* Header: Customer identity & Rating badge */}
        <header className="flex items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <Avatar className="h-10 w-10 border border-primary/20">
              <AvatarFallback className="bg-primary/10 font-display font-semibold text-primary">
                {initialsOf(customerName)}
              </AvatarFallback>
            </Avatar>

            <div className="min-w-0">
              <h3 className="font-display text-base font-bold text-ink sm:text-lg">
                {customerName}
              </h3>
              {review.created_at && (
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Calendar className="h-3 w-3 shrink-0" />
                  <span>{formatReviewDate(review.created_at)}</span>
                </p>
              )}
            </div>
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

        {/* Property & Room Meta */}
        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl bg-surface/40 px-3.5 py-2 text-xs text-muted-foreground">
          <div className="flex items-center gap-1.5 font-medium text-ink">
            <Building2 className="h-3.5 w-3.5 text-primary shrink-0" />
            <span className="truncate">{propertyName}</span>
          </div>

          {roomName && (
            <div className="flex items-center gap-1.5">
              <span className="text-muted-foreground/40">•</span>
              <BedDouble className="h-3.5 w-3.5 shrink-0 opacity-70" />
              <span className="truncate">{roomName}</span>
            </div>
          )}
        </div>

        {/* Comment Content */}
        <div className="mt-4 rounded-2xl border border-border/40 bg-surface/30 p-4">
          <p className="whitespace-pre-line text-[13.5px] leading-relaxed text-ink/85">
            {review.comment}
          </p>
        </div>
      </div>

      {/* Footer: Booking Reference & Verified badge */}
      <footer className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-4 text-xs">
        <div>
          {bookingRef && (
            <span className="inline-flex items-center gap-1 rounded-md bg-surface px-2.5 py-1 text-[11px] font-medium text-ink/80">
              <Tag className="h-3 w-3 shrink-0 opacity-60" />
              <span>{bookingRef}</span>
            </span>
          )}
        </div>

        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200/70 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">
          <CheckCircle2 className="h-3 w-3 text-emerald-600" />
          <span>Kỳ nghỉ hoàn tất</span>
        </span>
      </footer>
    </article>
  );
}

export default HostReviewCard;
