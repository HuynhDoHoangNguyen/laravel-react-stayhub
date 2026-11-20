import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  MapPin,
  Calendar,
  BedDouble,
  DoorOpen,
  Phone,
  Clock,
  Edit2,
  Info,
  ExternalLink,
  X,
  Loader2,
} from 'lucide-react';
import { formatLongDateVi } from '@/services/bookingService';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export function CheckoutReviewStep({
  property,
  room,
  startDate,
  endDate,
  nights,
  adults,
  children,
  note,
  user,
  onEditGuests,
  onBackToProperty,
  preview,
  previewError,
  createError,
  isPreviewing,
  isCreating,
  isMock = false,
  onRequestPreview,
  onCreateBooking,
}) {
  const [previewModalOpen, setPreviewModalOpen] = useState(false);

  const totalGuests = (Number(adults) || 1) + (Number(children) || 0);

  const photo =
    room?.primary_image ||
    room?.photos?.[0] ||
    property?.primary_image ||
    property?.photos?.[0] ||
    'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80&fit=crop';

  const checkInTime = property?.check_in_time ? property.check_in_time.slice(0, 5) : '14:00';
  const checkOutTime = property?.check_out_time ? property.check_out_time.slice(0, 5) : '12:00';

  const handleOpenPreview = () => {
    setPreviewModalOpen(true);
    onRequestPreview();
  };

  return (
    <div className="space-y-6">
      {/* 1. Stay Details Card */}
      <div className="rounded-3xl border border-border/70 bg-white p-6 sm:p-8 shadow-xs">
        <div className="flex items-start justify-between border-b border-border/60 pb-4">
          <div>
            <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
              ✦ Kiểm tra kỳ nghỉ
            </span>
            <h2 className="mt-0.5 font-display text-xl font-bold text-primary sm:text-2xl">
              Chi tiết chỗ nghỉ & thời gian
            </h2>
          </div>
          <Button
            type="button"
            variant="ghost"
            className="h-9 px-2 text-xs font-medium"
            onClick={onBackToProperty}
          >
            <span>Đổi chỗ nghỉ</span>
            <ExternalLink className="h-3 w-3" />
          </Button>
        </div>

        {/* Property & Room Media Row */}
        <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center">
          <img
            src={photo}
            alt={property?.name || 'Cơ sở lưu trú'}
            className="h-28 w-full rounded-2xl object-cover sm:h-24 sm:w-32 shrink-0 border border-border/60"
          />
          <div className="min-w-0 flex-1">
            <h3 className="truncate font-display text-lg font-bold text-primary">
              {property?.name || 'Cơ sở lưu trú StayHub'}
            </h3>
            <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground truncate">
              <MapPin className="h-3.5 w-3.5 shrink-0" />
              <span className="truncate">{property?.address || 'Việt Nam'}</span>
            </p>
            <Badge variant="secondary" className="mt-2 inline-flex items-center gap-2 rounded-xl px-2.5 py-1 text-xs font-semibold text-ink">
              <BedDouble className="h-3.5 w-3.5 text-primary" />
              <span>{room?.name || 'Hạng phòng nghỉ'}</span>
              <span className="text-muted-foreground font-normal">({room?.room_type_name || 'Tiêu chuẩn'})</span>
            </Badge>
          </div>
        </div>

        {/* Dates & Duration Grid */}
        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="rounded-2xl border border-border/70 bg-surface/30 p-3.5">
            <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
              Nhận phòng (Check-in)
            </div>
            <div className="mt-1 flex items-center gap-2 text-xs font-bold text-ink">
              <Calendar className="h-3.5 w-3.5 text-primary" />
              <span>{formatLongDateVi(startDate)}</span>
            </div>
            <div className="mt-0.5 text-[11px] text-muted-foreground">
              Từ {checkInTime} trở đi
            </div>
          </div>

          <div className="rounded-2xl border border-border/70 bg-surface/30 p-3.5">
            <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
              Trả phòng (Check-out)
            </div>
            <div className="mt-1 flex items-center gap-2 text-xs font-bold text-ink">
              <Calendar className="h-3.5 w-3.5 text-primary" />
              <span>{formatLongDateVi(endDate)}</span>
            </div>
            <div className="mt-0.5 text-[11px] text-muted-foreground">
              Trước {checkOutTime} trưa ({nights} đêm lưu trú)
            </div>
          </div>
        </div>
      </div>

      {/* 2. Customer & Guest Review Card */}
      <div className="rounded-3xl border border-border/70 bg-white p-6 sm:p-8 shadow-xs">
        <div className="flex items-center justify-between border-b border-border/60 pb-4">
          <div>
            <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
              ✦ Khách lưu trú
            </span>
            <h2 className="mt-0.5 font-display text-xl font-bold text-primary sm:text-2xl">
              Thông tin đón tiếp
            </h2>
          </div>
          <Button
            type="button"
            variant="outline"
            className="h-8 rounded-xl px-3 py-1.5 text-xs font-semibold"
            onClick={onEditGuests}
          >
            <Edit2 className="h-3.5 w-3.5" />
            <span>Chỉnh sửa</span>
          </Button>
        </div>

        <div className="mt-6 divide-y divide-border/60 text-xs">
          <div className="flex items-center justify-between py-2.5">
            <span className="text-muted-foreground">Người đặt phòng</span>
            <span className="font-semibold text-ink">{user?.name || 'Khách hàng StayHub'}</span>
          </div>
          <div className="flex items-center justify-between py-2.5">
            <div>
              <span className="text-muted-foreground">Số điện thoại trong hồ sơ</span>
              <span className="block text-[10px] text-muted-foreground/70">Thông tin tham khảo</span>
            </div>
            <span className="font-semibold text-ink">{user?.phone || '—'}</span>
          </div>
          <div className="flex items-center justify-between py-2.5">
            <span className="text-muted-foreground">Hòm thư điện tử</span>
            <span className="font-semibold text-ink">{user?.email || '—'}</span>
          </div>
          <div className="flex items-center justify-between py-2.5">
            <span className="text-muted-foreground">Quy mô đoàn</span>
            <span className="font-semibold text-ink">
              {totalGuests} khách (1 phòng)
            </span>
          </div>
          {note && (
            <div className="py-2.5">
              <span className="text-muted-foreground block mb-1">Ghi chú cho cơ sở</span>
              <p className="rounded-xl bg-surface/70 p-3 font-medium text-ink/80 italic">
                "{note}"
              </p>
            </div>
          )}
        </div>
      </div>

      {/* 3. House Rules & Payment Notice */}
      <div className="rounded-3xl border border-border/70 bg-white p-6 sm:p-8 shadow-xs">
        <h2 className="font-display text-lg font-bold text-primary">
          Quy định & Hỗ trợ từ cơ sở lưu trú
        </h2>
        <div className="mt-4 space-y-3 text-xs text-ink">
          {property?.phone && (
            <div className="flex items-start gap-2.5">
              <Phone className="mt-0.5 h-4 w-4 text-primary shrink-0" />
              <div>
                <span className="font-semibold">Hotline hỗ trợ lễ tân:</span>{' '}
                <a href={`tel:${property.phone}`} className="text-primary font-bold hover:underline">
                  {property.phone}
                </a>
              </div>
            </div>
          )}
          <div className="flex items-start gap-2.5">
            <Clock className="mt-0.5 h-4 w-4 text-primary shrink-0" />
            <div>
              <span className="font-semibold">Giờ giấc quy định:</span> Nhận phòng từ {checkInTime} và trả phòng trước {checkOutTime}.
            </div>
          </div>
          <div className="flex items-start gap-2.5">
            <Info className="mt-0.5 h-4 w-4 text-primary shrink-0" />
            <div>
              <span className="font-semibold">Thông tin lưu trú:</span> Thời gian nhận phòng và các quy định đón tiếp tuân theo chính sách của cơ sở.
            </div>
          </div>
        </div>
      </div>

      {/* 4. Truthful Preview Workflow Banner & Action */}
      <div className="rounded-3xl border border-primary/20 bg-gradient-to-br from-surface/80 via-white to-surface/40 p-6 sm:p-8 shadow-xs text-left">
        <div className="flex items-start gap-3">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground">
            <DoorOpen className="h-5 w-5" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="font-display text-lg font-bold text-primary">
              Xem lại thông tin đặt phòng
            </div>
            <p className="mt-1 text-xs text-muted-foreground leading-relaxed">
              Kiểm tra tình trạng phòng và báo giá mới nhất từ hệ thống trước khi xác nhận đặt phòng.
            </p>
          </div>
        </div>

        <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-6">
          <Button
            type="button"
            variant="ghost"
            className="h-auto px-1 py-1 text-xs font-semibold"
            onClick={onEditGuests}
          >
            ← Quay lại bước thông tin khách
          </Button>

          <Button
            type="button"
            disabled={isMock || isPreviewing || isCreating}
            onClick={handleOpenPreview}
            className="h-11 rounded-xl px-7 text-xs font-semibold"
          >
            <Info className="h-4 w-4" />
            <span>{isMock ? 'Chế độ xem thử (Khóa đặt phòng)' : 'Kiểm tra và đặt phòng'}</span>
          </Button>
        </div>
      </div>

      {/* Modal thông tin Preview Workflow theo Hard Rule 1 */}
      <AnimatePresence>
        {previewModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setPreviewModalOpen(false)}
              className="absolute inset-0 bg-black/40 backdrop-blur-xs"
            />

            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 10 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              role="dialog"
              aria-modal="true"
              aria-labelledby="checkout-preview-title"
              className="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-border/70 bg-white p-6 sm:p-8 shadow-2xl"
            >
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Đóng cửa sổ xem trước"
                onClick={() => setPreviewModalOpen(false)}
                className="absolute right-4 top-4 h-8 w-8 rounded-full"
              >
                <X className="h-4 w-4" />
              </Button>

              <div className="grid h-12 w-12 place-items-center rounded-2xl bg-primary/[0.08] text-primary mb-3">
                <Info className="h-6 w-6" />
              </div>

              <h4 id="checkout-preview-title" className="font-display text-2xl font-bold text-primary">
                Bản xem trước thông tin lưu trú
              </h4>
              <p className="text-xs text-muted-foreground leading-relaxed pt-2">
                Báo giá và tình trạng phòng được lấy trực tiếp từ StayHub. Giá cuối cùng do hệ thống xác nhận khi tạo booking.
              </p>

              {isPreviewing ? (
                <div className="my-5 flex items-center gap-2 rounded-xl bg-surface p-4 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" /> Đang kiểm tra giá và tình trạng phòng…
                </div>
              ) : previewError ? (
                <div role="alert" className="my-5 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                  {previewError}
                </div>
              ) : preview ? (
                <div className="my-4 space-y-3 rounded-2xl border border-border/60 bg-surface/80 p-4 text-xs">
                  <div className="flex justify-between gap-3">
                    <span className="text-muted-foreground">Chỗ nghỉ</span>
                    <span className="text-right font-bold text-ink">{property?.name}</span>
                  </div>
                  <div className="flex justify-between gap-3">
                    <span className="text-muted-foreground">Phòng</span>
                    <span className="text-right font-semibold text-ink">{room?.name}</span>
                  </div>
                  <div className="flex justify-between gap-3">
                    <span className="text-muted-foreground">Thời gian</span>
                    <span className="text-right font-semibold text-ink">{preview.number_of_nights} đêm ({startDate} → {endDate})</span>
                  </div>
                  <div className="flex justify-between gap-3">
                    <span className="text-muted-foreground">Giá mỗi đêm</span>
                    <span className="font-semibold text-ink">{new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(preview.price_per_night))}</span>
                  </div>
                  <div className="flex justify-between gap-3 border-t border-border/60 pt-3">
                    <span className="font-semibold text-ink">Tổng tiền phòng</span>
                    <span className="font-bold text-primary">{new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(preview.room_total))}</span>
                  </div>
                  <p className={preview.availability?.available ? 'text-emerald-700' : 'text-destructive'}>
                    {preview.availability?.available ? 'Phòng còn trống trong khoảng ngày đã chọn.' : 'Phòng hiện không còn trống trong khoảng ngày đã chọn.'}
                  </p>
                </div>
              ) : null}

              {createError && (
                <div role="alert" className="mb-3 rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive">
                  {createError}
                </div>
              )}

              <div className="flex flex-col-reverse gap-2.5 pt-2 sm:flex-row">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setPreviewModalOpen(false)}
                  className="h-10 w-full rounded-xl px-4 text-xs font-semibold sm:w-auto"
                >
                  Đóng
                </Button>
                {preview?.availability?.available && (
                  <Button
                    type="button"
                    onClick={onCreateBooking}
                    disabled={isCreating || isPreviewing}
                    className="h-10 w-full rounded-xl px-5 text-xs font-semibold sm:w-auto"
                  >
                    {isCreating && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                    <span>{isCreating ? 'Đang tạo booking…' : 'Xác nhận đặt phòng'}</span>
                  </Button>
                )}
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </div>
  );
}

export default CheckoutReviewStep;
