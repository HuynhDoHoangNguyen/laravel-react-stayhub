import { useState, useRef } from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Star, X, AlertCircle, Info, Loader2, Sparkles } from 'lucide-react';
import { reviewService, parseReviewError } from '@/services/reviewService';

const RATING_LABELS = {
  1: 'Chưa hài lòng',
  2: 'Cần cải thiện',
  3: 'Bình thường',
  4: 'Hài lòng',
  5: 'Rất tuyệt vời',
};

/**
 * ReviewCreateModal Component — StayNest Quiet Luxury
 *
 * Cho phép khách hàng gửi đánh giá cho kỳ nghỉ đã hoàn tất (COMPLETED).
 * Tuân thủ quy định Mock Isolation: Nếu là Dev Mock, khóa gửi API để bảo vệ dữ liệu.
 */
export function ReviewCreateModal({
  isOpen,
  onClose,
  booking,
  isMock = false,
  onSuccess,
  onConflict,
}) {
  const [rating, setRating] = useState(5);
  const [hoverRating, setHoverRating] = useState(0);
  const [comment, setComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState(null);

  // Ref guard chống double-click và race condition
  const isSubmittingRef = useRef(false);

  const activeRating = hoverRating || rating;
  const trimmedComment = comment.trim();
  const charCount = trimmedComment.length;
  const isCommentValid = charCount >= 5 && charCount <= 1000;
  const isRatingValid = Number.isInteger(rating) && rating >= 1 && rating <= 5;

  const handleKeyDownStar = (e) => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
      e.preventDefault();
      setRating((prev) => Math.min(5, prev + 1));
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
      e.preventDefault();
      setRating((prev) => Math.max(1, prev - 1));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    // 1. Chống double-submit qua ref guard
    if (isSubmittingRef.current || isSubmitting) {
      return;
    }

    // 2. Mock Safety Guard: Không cho phép gửi API thật khi Booking là Dev Mock
    if (isMock) {
      setErrorMessage(
        'Đơn đặt phòng này đang ở chế độ xem trước (Dev Mock). Chức năng gửi đánh giá thật đã bị vô hiệu hóa để bảo vệ dữ liệu máy chủ.'
      );
      return;
    }

    // 3. Client Validation
    if (!isRatingValid) {
      setErrorMessage('Vui lòng chọn số sao đánh giá hợp lệ (từ 1 đến 5 sao).');
      return;
    }

    if (charCount < 5) {
      setErrorMessage('Nội dung đánh giá phải có tối thiểu 5 ký tự.');
      return;
    }

    if (charCount > 1000) {
      setErrorMessage('Nội dung đánh giá không được vượt quá 1000 ký tự.');
      return;
    }

    isSubmittingRef.current = true;
    setIsSubmitting(true);
    setErrorMessage(null);

    try {
      const response = await reviewService.createBookingReview(booking.id, {
        rating,
        comment: trimmedComment,
      });

      const createdReview = response?.data || response;
      if (onSuccess) {
        onSuccess(createdReview);
      }
      onClose();
    } catch (err) {
      const parsed = parseReviewError(err);
      if (parsed.isConflict) {
        setErrorMessage(
          'Đơn đặt phòng này đã có đánh giá trong hệ thống. Đang đồng bộ lại trạng thái...'
        );
        if (onConflict) {
          onConflict();
        }
      } else {
        setErrorMessage(parsed.message);
      }
    } finally {
      isSubmittingRef.current = false;
      setIsSubmitting(false);
    }
  };

  const propertyName =
    booking?.room?.property?.name || booking?.property_name || 'Cơ sở lưu trú';
  const roomName = booking?.room?.name || booking?.room_name || 'Phòng nghỉ';

  return (
    <DialogPrimitive.Root open={isOpen} onOpenChange={(open) => !open && !isSubmitting && onClose()}>
      <DialogPrimitive.Portal>
        {/* Backdrop Overlay */}
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs transition-opacity animate-in fade-in-0" />

        {/* Modal Dialog Content */}
        <DialogPrimitive.Content
          aria-describedby="review-modal-description"
          className="fixed left-1/2 top-1/2 z-50 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-3xl border border-border/70 bg-card p-6 shadow-2xl focus:outline-none animate-in fade-in-0 zoom-in-95 sm:p-8"
        >
          {/* Header */}
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
                <Sparkles className="h-3.5 w-3.5" />
                <span>Đánh giá kỳ nghỉ</span>
              </p>
              <DialogPrimitive.Title className="mt-1 font-display text-2xl font-bold text-primary sm:text-3xl">
                Cảm nhận của bạn
              </DialogPrimitive.Title>
              <DialogPrimitive.Description
                id="review-modal-description"
                className="mt-1 truncate text-xs text-muted-foreground"
              >
                {propertyName} · {roomName}
              </DialogPrimitive.Description>
            </div>

            <DialogPrimitive.Close
              disabled={isSubmitting}
              className="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition hover:bg-surface hover:text-ink disabled:opacity-40"
              aria-label="Đóng"
            >
              <X className="h-4 w-4" />
            </DialogPrimitive.Close>
          </div>

          {/* Dev Mock Warning Banner */}
          {isMock && (
            <div className="mt-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs text-amber-900">
              <Info className="mt-0.5 h-4 w-4 shrink-0 text-amber-700" />
              <div className="space-y-0.5">
                <div className="font-semibold">Chế độ xem trước (Dev Mock)</div>
                <div className="text-[11.5px] leading-relaxed text-amber-800">
                  Đơn đặt phòng này là dữ liệu giả lập. Bạn có thể tương tác với giao diện form, nhưng chức năng gửi API thật đã được khóa để bảo vệ dữ liệu hệ thống.
                </div>
              </div>
            </div>
          )}

          {/* Form */}
          <form onSubmit={handleSubmit} className="mt-6 space-y-5">
            {/* 1. Star Rating Selector */}
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Mức độ hài lòng của bạn <span className="text-destructive">*</span>
              </label>

              <div
                className="mt-2.5 flex items-center gap-2"
                tabIndex={0}
                role="radiogroup"
                aria-label="Đánh giá số sao từ 1 đến 5"
                onKeyDown={handleKeyDownStar}
              >
                {[1, 2, 3, 4, 5].map((star) => {
                  const isFilled = star <= activeRating;
                  return (
                    <button
                      key={star}
                      type="button"
                      disabled={isSubmitting}
                      onClick={() => setRating(star)}
                      onMouseEnter={() => setHoverRating(star)}
                      onMouseLeave={() => setHoverRating(0)}
                      role="radio"
                      aria-checked={rating === star}
                      aria-label={`${star} sao`}
                      className="group grid h-10 w-10 place-items-center rounded-xl transition hover:bg-surface focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                      <Star
                        className={`h-7 w-7 transition-all duration-200 ${
                          isFilled
                            ? 'fill-accent text-accent scale-110 drop-shadow-xs'
                            : 'text-muted-foreground/30 group-hover:text-muted-foreground/50'
                        }`}
                      />
                    </button>
                  );
                })}

                <span className="ml-2 font-display text-base font-semibold text-ink">
                  {RATING_LABELS[activeRating] || `${activeRating} sao`}
                </span>
              </div>
            </div>

            {/* 2. Comment Textarea */}
            <div>
              <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <label htmlFor="review-comment">
                  Nội dung nhận xét <span className="text-destructive">*</span>
                </label>
                <span
                  className={`font-mono text-[11px] ${
                    charCount > 1000
                      ? 'text-destructive font-bold'
                      : charCount >= 5
                      ? 'text-emerald-700'
                      : 'text-muted-foreground'
                  }`}
                >
                  {charCount} / 1000 ký tự
                </span>
              </div>

              <textarea
                id="review-comment"
                rows={4}
                disabled={isSubmitting}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Chia sẻ trải nghiệm của bạn về không gian phòng nghỉ, tiện nghi, dịch vụ và vị trí chỗ nghỉ (tối thiểu 5 ký tự)..."
                className="mt-2 w-full resize-none rounded-2xl border border-border/70 bg-surface/30 p-3.5 text-xs text-ink placeholder:text-muted-foreground/60 focus:border-primary focus:bg-card focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all leading-relaxed"
              />

              <div className="mt-1 flex items-center justify-between text-[11px] text-muted-foreground">
                <span>
                  {charCount < 5 ? (
                    <span className="text-amber-700">
                      Tối thiểu 5 ký tự (còn thiếu {5 - charCount} ký tự)
                    </span>
                  ) : charCount > 1000 ? (
                    <span className="text-destructive">
                      Vượt quá {charCount - 1000} ký tự cho phép
                    </span>
                  ) : (
                    <span className="text-emerald-700">✓ Độ dài hợp lệ</span>
                  )}
                </span>
                <span className="text-muted-foreground/70">Quy định V1</span>
              </div>
            </div>

            {/* Error Message Alert */}
            {errorMessage && (
              <div className="flex items-start gap-2.5 rounded-2xl border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive">
                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                <span className="leading-relaxed">{errorMessage}</span>
              </div>
            )}

            {/* Actions */}
            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                type="button"
                disabled={isSubmitting}
                onClick={onClose}
                className="rounded-full border border-border px-5 py-2.5 text-xs font-semibold text-ink transition hover:border-primary/40 hover:text-primary disabled:opacity-40"
              >
                Hủy bỏ
              </button>

              <button
                type="submit"
                disabled={isSubmitting || isMock || !isCommentValid || !isRatingValid}
                className="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-2.5 text-xs font-semibold text-primary-foreground shadow-sm transition hover:bg-primary-mid active:scale-95 disabled:pointer-events-none disabled:opacity-40"
                title={isMock ? 'Khóa gửi API cho đơn xem trước' : undefined}
              >
                {isSubmitting && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                <span>{isSubmitting ? 'Đang gửi...' : 'Gửi đánh giá'}</span>
              </button>
            </div>
          </form>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  );
}

export default ReviewCreateModal;
