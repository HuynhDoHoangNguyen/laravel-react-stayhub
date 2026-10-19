import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  MapPin,
  Calendar,
  Users,
  DoorOpen,
  BedDouble,
  MoreVertical,
  Eye,
  FileText,
} from 'lucide-react';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  BOOKING_STATUS_CONFIG,
  formatVnd,
  formatDateVi,
  nightsBetween,
} from '@/services/bookingService';

function InfoCell({ icon: Icon, label, value, sub }) {
  return (
    <div className="min-w-0">
      <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
        <Icon className="h-3.5 w-3.5" aria-hidden="true" />
        <span>{label}</span>
      </div>
      <div className="mt-1 truncate text-[13px] font-semibold text-ink">{value}</div>
      {sub && <div className="text-[11px] text-muted-foreground">{sub}</div>}
    </div>
  );
}

export function BookingCard({ booking, index = 0 }) {
  // Hiển thị trạng thái chuẩn xác từ Backend: COMPLETED chỉ khi backend trả về COMPLETED
  const effectiveStatus = booking.status;

  const statusConfig = BOOKING_STATUS_CONFIG[effectiveStatus] || {
    label: effectiveStatus,
    dot: 'bg-stone-400',
    text: 'text-stone-700',
    bg: 'bg-stone-100 border border-stone-200',
  };

  const nights = nightsBetween(booking.check_in_date, booking.check_out_date);
  const photo =
    booking.room?.property?.primary_image ||
    booking.room?.property?.photos?.[0] ||
    'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80&fit=crop';

  const propertyName = booking.room?.property?.name || 'Cơ sở lưu trú StayHub';
  const propertyAddress = booking.room?.property?.address || 'Việt Nam';
  const roomName = booking.room?.name || 'Phòng nghỉ cao cấp';
  const roomTypeName = booking.room?.room_type_name || 'Tiêu chuẩn';
  const guestCount = booking.guest_count || 1;

  // Kiểm tra liên kết hóa đơn hợp lệ (khóa chính Invoice ID độc lập)
  const validInvoiceId = booking.invoice_id || booking.invoice?.id;

  return (
    <motion.article
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, scale: 0.98 }}
      transition={{ duration: 0.35, delay: index * 0.04, ease: [0.16, 1, 0.3, 1] }}
      className="group overflow-hidden rounded-2xl border border-border/60 bg-white shadow-[0_1px_2px_rgba(17,26,19,0.04)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_18px_40px_-20px_rgba(17,26,19,0.22)]"
    >
      <div className="flex flex-col md:flex-row">
        {/* Thumbnail Image */}
        <Link
          to={`/customer/bookings/${booking.id}`}
          className="relative block h-52 w-full shrink-0 overflow-hidden md:h-auto md:w-[240px]"
        >
          <img
            src={photo}
            alt={propertyName}
            loading="lazy"
            className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
          />
        </Link>

        {/* Card Content */}
        <div className="flex flex-1 flex-col p-5">
          <header className="flex items-start justify-between gap-4">
            <Link to={`/customer/bookings/${booking.id}`} className="min-w-0 flex-1">
              <h3 className="truncate font-display text-xl leading-tight text-primary hover:underline">
                {propertyName}
              </h3>
              <p className="mt-1 flex items-center gap-1.5 text-[12.5px] text-muted-foreground truncate">
                <MapPin className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                <span className="truncate">{propertyAddress}</span>
              </p>
            </Link>

            <div className="flex shrink-0 items-center gap-2">
              <span
                className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide ${statusConfig.bg} ${statusConfig.text}`}
              >
                <span className={`h-1.5 w-1.5 rounded-full ${statusConfig.dot}`} aria-hidden="true" />
                {statusConfig.label}
              </span>

              {/* Action Popover Menu */}
              <Popover>
                <PopoverTrigger asChild>
                  <button
                    type="button"
                    aria-label="Thao tác khác"
                    className="grid h-8 w-8 cursor-pointer place-items-center rounded-full text-muted-foreground transition-colors hover:bg-primary/[0.06] hover:text-primary"
                  >
                    <MoreVertical className="h-4 w-4" aria-hidden="true" />
                  </button>
                </PopoverTrigger>
                <PopoverContent align="end" className="w-52 p-1.5 shadow-lg">
                  <div className="space-y-1">
                    <Link
                      to={`/customer/bookings/${booking.id}`}
                      className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-surface hover:text-primary"
                    >
                      <Eye className="h-3.5 w-3.5" />
                      <span>Xem chi tiết đơn</span>
                    </Link>
                    {validInvoiceId && (
                      <>
                        <div className="my-1 h-px bg-border/60" />
                        <Link
                          to={`/customer/invoices/${validInvoiceId}`}
                          className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-surface hover:text-primary"
                        >
                          <FileText className="h-3.5 w-3.5" />
                          <span>Xem hóa đơn đã xuất</span>
                        </Link>
                      </>
                    )}
                  </div>
                </PopoverContent>
              </Popover>
            </div>
          </header>

          {/* Quick Info Grid */}
          <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-5">
            <InfoCell
              icon={Calendar}
              label="Ngày nhận phòng"
              value={formatDateVi(booking.check_in_date)}
            />
            <InfoCell
              icon={Calendar}
              label="Ngày trả phòng"
              value={formatDateVi(booking.check_out_date)}
              sub={`${nights} đêm lưu trú`}
            />
            <InfoCell
              icon={Users}
              label="Số lượng khách"
              value={`${guestCount} khách`}
            />
            <InfoCell
              icon={DoorOpen}
              label="Phòng nghỉ"
              value={roomName}
            />
            <InfoCell
              icon={BedDouble}
              label="Loại phòng"
              value={roomTypeName}
            />
          </div>

          {/* Card Footer Bar */}
          <div className="mt-5 border-t border-border/60 pt-4">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div className="flex flex-wrap gap-x-6 gap-y-2 text-[11.5px]">
                <div>
                  <div className="text-muted-foreground">Mã đơn đặt</div>
                  <div className="mt-0.5 font-mono text-[12px] font-semibold text-ink">
                    #{booking.booking_code || `BK${booking.id}`}
                  </div>
                </div>
                <div>
                  <div className="text-muted-foreground">Thời gian đặt</div>
                  <div className="mt-0.5 text-[12px] text-ink">
                    {formatDateVi(booking.created_at)}
                  </div>
                </div>
                {validInvoiceId && (
                  <div>
                    <div className="text-muted-foreground">Hóa đơn</div>
                    <Link
                      to={`/customer/invoices/${validInvoiceId}`}
                      className="mt-0.5 inline-flex items-center gap-1 font-medium text-primary hover:underline"
                    >
                      <FileText className="h-3 w-3" />
                      <span>Chi tiết hóa đơn</span>
                    </Link>
                  </div>
                )}
              </div>

              <div className="flex items-end gap-3.5">
                <div className="text-right">
                  <div className="text-[10.5px] uppercase tracking-wide text-muted-foreground">
                    Tiền phòng
                  </div>
                  <div className="font-display text-2xl font-bold leading-none text-primary">
                    {formatVnd(booking.room_total)}
                  </div>
                </div>
                <Link
                  to={`/customer/bookings/${booking.id}`}
                  className="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition-all duration-200 hover:bg-primary-mid"
                >
                  Xem chi tiết
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </motion.article>
  );
}

export default BookingCard;
