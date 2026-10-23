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
} from 'lucide-react';
import { formatLongDateVi } from '@/services/bookingService';

export function CheckoutReviewStep({
  property,
  room,
  startDate,
  endDate,
  nights,
  adults,
  children,
  note,
  contactPhone,
  user,
  onEditGuests,
  onBackToProperty,
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
          <button
            type="button"
            onClick={onBackToProperty}
            className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
          >
            <span>Đổi chỗ nghỉ</span>
            <ExternalLink className="h-3 w-3" />
          </button>
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
            <div className="mt-2 inline-flex items-center gap-2 rounded-xl bg-surface px-2.5 py-1 text-xs font-semibold text-ink">
              <BedDouble className="h-3.5 w-3.5 text-primary" />
              <span>{room?.name || 'Hạng phòng nghỉ'}</span>
              <span className="text-muted-foreground font-normal">({room?.room_type_name || 'Tiêu chuẩn'})</span>
            </div>
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
          <button
            type="button"
            onClick={onEditGuests}
            className="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border/80 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-surface"
          >
            <Edit2 className="h-3.5 w-3.5" />
            <span>Chỉnh sửa</span>
          </button>
        </div>

        <div className="mt-6 divide-y divide-border/60 text-xs">
          <div className="flex items-center justify-between py-2.5">
            <span className="text-muted-foreground">Người đặt phòng</span>
            <span className="font-semibold text-ink">{user?.name || 'Khách hàng StayHub'}</span>
          </div>
          <div className="flex items-center justify-between py-2.5">
            <div>
              <span className="text-muted-foreground">Số điện thoại liên hệ</span>
              <span className="block text-[10px] text-muted-foreground/70">Thông tin tham khảo</span>
            </div>
            <span className="font-semibold text-ink">{contactPhone || '—'}</span>
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
              Quý khách vui lòng rà soát lại thông tin lưu trú và dự toán chi phí. Hệ thống hiện cung cấp bản xem trước chi tiết để tham khảo trước khi liên hệ hoặc hoàn tất thủ tục lưu trú.
            </p>
          </div>
        </div>

        <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-6">
          <button
            type="button"
            onClick={onEditGuests}
            className="cursor-pointer text-xs font-semibold text-muted-foreground hover:text-ink transition-colors"
          >
            ← Quay lại bước thông tin khách
          </button>

          <button
            type="button"
            onClick={handleOpenPreview}
            className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary px-7 py-3 text-xs font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary-mid active:scale-95"
          >
            <Info className="h-4 w-4" />
            <span>Xem lại thông tin dự kiến</span>
          </button>
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
              className="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-border/70 bg-white p-6 sm:p-8 shadow-2xl"
            >
              <button
                type="button"
                onClick={() => setPreviewModalOpen(false)}
                className="absolute right-4 top-4 grid h-8 w-8 cursor-pointer place-items-center rounded-full text-muted-foreground hover:bg-surface hover:text-ink transition-colors"
              >
                <X className="h-4 w-4" />
              </button>

              <div className="grid h-12 w-12 place-items-center rounded-2xl bg-primary/[0.08] text-primary mb-3">
                <Info className="h-6 w-6" />
              </div>

              <h4 className="font-display text-2xl font-bold text-primary">
                Bản xem trước thông tin lưu trú
              </h4>
              <p className="text-xs text-muted-foreground leading-relaxed pt-2">
                Hệ thống tiếp nhận đơn trực tuyến đang trong giai đoạn hoàn tất kết nối. Quý khách có thể sử dụng thông tin tóm tắt bên dưới để tham khảo và chủ động liên hệ lễ tân cơ sở lưu trú nếu cần tìm hiểu thêm:
              </p>

              <div className="my-4 rounded-2xl bg-surface/80 p-4 border border-border/60 text-xs space-y-2">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Chỗ nghỉ:</span>
                  <span className="font-bold text-ink">{property?.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Hạng phòng:</span>
                  <span className="font-semibold text-ink">{room?.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Thời gian:</span>
                  <span className="font-semibold text-ink">{nights} đêm ({startDate} → {endDate})</span>
                </div>
                {property?.phone && (
                  <div className="flex justify-between border-t border-border/60 pt-2 text-primary">
                    <span className="font-semibold">Hotline hỗ trợ:</span>
                    <span className="font-bold font-mono text-sm">{property.phone}</span>
                  </div>
                )}
              </div>

              <div className="flex flex-col-reverse sm:flex-row gap-2.5 pt-2">
                <button
                  type="button"
                  onClick={() => setPreviewModalOpen(false)}
                  className="w-full sm:w-auto cursor-pointer rounded-xl border border-border/80 bg-white px-4 py-2.5 text-xs font-semibold text-ink hover:bg-surface transition-colors"
                >
                  Đóng xem lại
                </button>
                {property?.phone && (
                  <a
                    href={`tel:${property.phone}`}
                    className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground hover:bg-primary-mid transition-all"
                  >
                    <Phone className="h-3.5 w-3.5" />
                    <span>Hotline lễ tân</span>
                  </a>
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
