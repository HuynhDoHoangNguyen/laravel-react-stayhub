import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  ArrowLeft,
  Calendar,
  BedDouble,
  DoorOpen,
  Users,
  MapPin,
  Phone,
  FileText,
  Clock,
  CheckCircle2,
  AlertCircle,
  Sparkles,
  ExternalLink,
} from 'lucide-react';
import {
  bookingService,
  BOOKING_STATUS_CONFIG,
  formatVnd,
  formatDateVi,
  formatLongDateVi,
  nightsBetween,
} from '@/services/bookingService';

function FieldItem({ icon: Icon, label, value }) {
  return (
    <div>
      <div className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
        <Icon className="h-3.5 w-3.5" aria-hidden="true" />
        <span>{label}</span>
      </div>
      <div className="mt-1 text-[13.5px] font-semibold text-ink">{value}</div>
    </div>
  );
}

export default function CustomerBookingDetailPage() {
  const { bookingId } = useParams();
  const navigate = useNavigate();

  const [booking, setBooking] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState(null);
  const [isMockData, setIsMockData] = useState(false);

  useEffect(() => {
    let ignore = false;

    async function load() {
      try {
        const res = await bookingService.getBookingDetail(bookingId);
        if (!ignore) {
          setBooking(res.data);
          setIsMockData(Boolean(res.isMock));
          setErrorMessage(null);
          setIsLoading(false);
        }
      } catch (err) {
        if (!ignore) {
          setBooking(null);
          setErrorMessage(err.message || 'Không tìm thấy thông tin đơn đặt phòng.');
          setIsLoading(false);
        }
      }
    }

    if (bookingId) {
      load();
    }

    return () => {
      ignore = true;
    };
  }, [bookingId]);

  if (isLoading) {
    return (
      <div className="mx-auto max-w-[1180px] pb-24 pt-4 text-left">
        <div className="h-4 w-36 animate-pulse rounded bg-surface" />
        <div className="mt-6 h-[380px] animate-pulse rounded-3xl bg-white/70" />
        <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
          <div className="h-64 animate-pulse rounded-2xl bg-white/70" />
          <div className="h-64 animate-pulse rounded-2xl bg-white/70" />
        </div>
      </div>
    );
  }

  if (errorMessage || !booking) {
    return (
      <div className="mx-auto max-w-2xl py-16 text-center">
        <div className="rounded-3xl border border-border/70 bg-white p-8 shadow-xs sm:p-12">
          <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-50 text-rose-700">
            <AlertCircle className="h-7 w-7" />
          </div>
          <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
            ✦ Đơn đặt phòng
          </p>
          <h1 className="mt-2 font-display text-2xl font-bold text-primary sm:text-3xl">
            Không tìm thấy thông tin đơn đặt phòng
          </h1>
          <p className="mx-auto mt-3 max-w-md text-xs leading-relaxed text-muted-foreground">
            {errorMessage || 'Mã đơn đặt phòng không tồn tại hoặc bạn không có quyền truy cập.'}
          </p>
          <div className="mt-8 flex justify-center">
            <Link
              to="/customer/bookings"
              className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Quay lại danh sách đơn</span>
            </Link>
          </div>
        </div>
      </div>
    );
  }

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

  const property = booking.room?.property || {};
  const room = booking.room || {};
  const validInvoiceId = booking.invoice_id || booking.invoice?.id;

  // Tính tổng tiền dịch vụ nếu có
  const bookingServices = booking.booking_services || [];
  const servicesTotal = bookingServices.reduce(
    (acc, s) => acc + (parseFloat(s.amount) || 0),
    0
  );

  return (
    <div className="mx-auto max-w-[1180px] pb-24 pt-2 text-left">
      {/* Top Back Action */}
      <div className="mb-4 flex items-center justify-between">
        <button
          type="button"
          onClick={() => navigate('/customer/bookings')}
          className="inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          <span>Quay lại danh sách đơn đặt phòng</span>
        </button>

        {isMockData && (
          <span className="rounded-full bg-amber-100/80 px-2.5 py-0.5 text-[10.5px] font-medium text-amber-800">
            Dữ liệu minh họa (Dev Mode)
          </span>
        )}
      </div>

      {/* Hero Card (StayNest BookingBody Pattern) */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
        className="overflow-hidden rounded-3xl border border-border/60 bg-white shadow-[0_1px_2px_rgba(17,26,19,0.04)]"
      >
        <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
          {/* Cover Photo */}
          <div className="relative aspect-[16/10] md:aspect-auto md:min-h-[360px]">
            <img
              src={photo}
              alt={property.name || 'Cơ sở lưu trú'}
              className="absolute inset-0 h-full w-full object-cover"
            />
          </div>

          {/* Core Info */}
          <div className="flex flex-col p-6 sm:p-8">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent">
                  Mã đơn #{booking.booking_code || `BK${booking.id}`}
                </p>
                <h1 className="mt-1 font-display text-2xl font-bold leading-tight text-primary sm:text-3xl md:text-4xl">
                  {property.name || 'Cơ sở lưu trú StayHub'}
                </h1>
                <p className="mt-2 inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                  <MapPin className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                  <span>{property.address || 'Việt Nam'}</span>
                </p>
              </div>

              <span
                className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold tracking-wide ${statusConfig.bg} ${statusConfig.text}`}
              >
                <span className={`h-1.5 w-1.5 rounded-full ${statusConfig.dot}`} aria-hidden="true" />
                {statusConfig.label}
              </span>
            </div>

            {/* Quick Meta Grid */}
            <div className="mt-6 grid grid-cols-2 gap-4">
              <FieldItem
                icon={Calendar}
                label="Ngày nhận phòng"
                value={formatLongDateVi(booking.check_in_date)}
              />
              <FieldItem
                icon={Calendar}
                label="Ngày trả phòng"
                value={formatLongDateVi(booking.check_out_date)}
              />
              <FieldItem
                icon={BedDouble}
                label="Hạng phòng"
                value={`${room.name || 'Phòng nghỉ'} (${room.room_type_name || 'Tiêu chuẩn'})`}
              />
              <FieldItem
                icon={DoorOpen}
                label="Thời lượng lưu trú"
                value={`1 phòng · ${nights} đêm lưu trú`}
              />
            </div>

            {/* Bottom Actions & Total Amount */}
            <div className="mt-auto flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-6">
              <div>
                <div className="text-[10.5px] uppercase tracking-wide text-muted-foreground">
                  Tiền phòng lưu trú
                </div>
                <div className="font-display text-3xl font-bold leading-none text-primary">
                  {formatVnd(booking.room_total)}
                </div>
                {servicesTotal > 0 && (
                  <div className="mt-1 text-[11.5px] text-muted-foreground">
                    Dịch vụ phát sinh: <span className="font-semibold text-ink">{formatVnd(servicesTotal)}</span>
                  </div>
                )}
              </div>

              <div className="flex flex-wrap items-center gap-2.5">
                {property.id && (
                  <Link
                    to={`/properties/${property.id}`}
                    className="inline-flex items-center gap-1.5 rounded-xl border border-border/80 bg-white px-4 py-2.5 text-xs font-semibold text-ink hover:border-primary/40 hover:text-primary transition-colors"
                  >
                    <span>Xem chỗ nghỉ</span>
                    <ExternalLink className="h-3 w-3" />
                  </Link>
                )}

                {validInvoiceId && (
                  <Link
                    to={`/customer/invoices/${validInvoiceId}`}
                    className="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
                  >
                    <FileText className="h-3.5 w-3.5" />
                    <span>Xem hóa đơn</span>
                  </Link>
                )}
              </div>
            </div>
          </div>
        </div>
      </motion.div>

      {/* Lower Details Grid: 2 Columns */}
      <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        {/* Left Column: Guest info & Services */}
        <div className="space-y-6">
          {/* Guest Information Section */}
          <section className="rounded-2xl border border-border/60 bg-white p-6 shadow-xs">
            <h2 className="flex items-center gap-2 font-display text-xl font-bold text-primary">
              <Users className="h-4 w-4 text-accent" />
              <span>Thông tin khách đặt & lưu trú</span>
            </h2>

            <div className="mt-4 divide-y divide-border/60">
              <div className="flex items-center justify-between py-3">
                <span className="text-xs text-muted-foreground">Người đặt phòng</span>
                <span className="text-xs font-semibold text-ink">
                  {booking.customer_name || 'Khách hàng StayHub'}
                </span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-xs text-muted-foreground">Số lượng khách lưu trú</span>
                <span className="text-xs font-semibold text-ink">
                  {booking.guest_count} khách
                </span>
              </div>
              {booking.customer_phone && (
                <div className="flex items-center justify-between py-3">
                  <span className="text-xs text-muted-foreground">Số điện thoại liên hệ</span>
                  <span className="text-xs font-semibold text-ink">
                    {booking.customer_phone}
                  </span>
                </div>
              )}
              {booking.customer_email && (
                <div className="flex items-center justify-between py-3">
                  <span className="text-xs text-muted-foreground">Email nhận xác nhận</span>
                  <span className="text-xs font-semibold text-ink">
                    {booking.customer_email}
                  </span>
                </div>
              )}
            </div>
          </section>

          {/* Booking Services Section */}
          {bookingServices.length > 0 && (
            <section className="rounded-2xl border border-border/60 bg-white p-6 shadow-xs">
              <div className="flex items-center justify-between">
                <h2 className="flex items-center gap-2 font-display text-xl font-bold text-primary">
                  <Sparkles className="h-4 w-4 text-accent" />
                  <span>Dịch vụ sử dụng tại chỗ nghỉ</span>
                </h2>
                <span className="text-xs text-muted-foreground">
                  {bookingServices.length} dịch vụ
                </span>
              </div>

              <div className="mt-4 divide-y divide-border/60">
                {bookingServices.map((svc) => (
                  <div key={svc.id || svc.service_name} className="flex items-center justify-between py-3">
                    <div>
                      <div className="text-xs font-semibold text-ink">{svc.service_name}</div>
                      <div className="text-[11px] text-muted-foreground">
                        {svc.quantity} {svc.unit} × {formatVnd(svc.unit_price)}
                      </div>
                    </div>
                    <div className="text-xs font-bold text-primary">
                      {formatVnd(svc.amount)}
                    </div>
                  </div>
                ))}
              </div>

              <div className="mt-4 flex items-center justify-between border-t border-border/60 pt-3">
                <span className="text-xs font-medium text-muted-foreground">Tổng tiền dịch vụ</span>
                <span className="text-sm font-bold text-primary">
                  {formatVnd(servicesTotal)}
                </span>
              </div>
            </section>
          )}

          {/* Special Notes Section */}
          {booking.note && (
            <section className="rounded-2xl border border-border/60 bg-white p-6 shadow-xs">
              <h2 className="font-display text-xl font-bold text-primary">
                Ghi chú cho cơ sở lưu trú
              </h2>
              <p className="mt-3 text-xs leading-relaxed text-ink/80 bg-surface/70 p-4 rounded-xl border border-border/60">
                "{booking.note}"
              </p>
            </section>
          )}
        </div>

        {/* Right Aside: Timeline, Contact & Invoice */}
        <aside className="space-y-6">
          {/* Booking Timeline */}
          <section className="rounded-2xl border border-border/60 bg-white p-6 shadow-xs">
            <h2 className="font-display text-xl font-bold text-primary">
              Tiến trình đơn đặt phòng
            </h2>
            <ul className="mt-4 space-y-4 text-xs">
              <li className="flex items-start gap-3">
                <CheckCircle2 className="mt-0.5 h-4 w-4 text-primary shrink-0" />
                <div>
                  <div className="font-semibold text-ink">Đã tiếp nhận đơn</div>
                  <div className="text-muted-foreground mt-0.5">
                    {formatLongDateVi(booking.created_at)}
                  </div>
                </div>
              </li>
              <li className="flex items-start gap-3">
                <CheckCircle2
                  className={`mt-0.5 h-4 w-4 shrink-0 ${
                    effectiveStatus === 'CANCELLED' || effectiveStatus === 'REJECTED'
                      ? 'text-rose-600'
                      : 'text-primary'
                  }`}
                />
                <div>
                  <div className="font-semibold text-ink">
                    {effectiveStatus === 'CANCELLED'
                      ? 'Đơn đã hủy'
                      : effectiveStatus === 'REJECTED'
                      ? 'Từ chối tiếp nhận'
                      : 'Trạng thái hiện tại'}
                  </div>
                  <div className="text-muted-foreground mt-0.5">
                    {statusConfig.label} ({formatDateVi(booking.updated_at)})
                  </div>
                </div>
              </li>
            </ul>
          </section>

          {/* Property Contact & Check-in Rules */}
          {property.phone && (
            <section className="rounded-2xl border border-border/60 bg-white p-6 shadow-xs">
              <h2 className="font-display text-xl font-bold text-primary">
                Liên hệ hỗ trợ cơ sở
              </h2>
              <div className="mt-4 space-y-3 text-xs text-ink">
                <div className="flex items-start gap-2.5">
                  <Phone className="mt-0.5 h-4 w-4 text-primary shrink-0" />
                  <div>
                    <div className="font-semibold">Hotline lễ tân</div>
                    <a
                      href={`tel:${property.phone}`}
                      className="text-primary hover:underline font-medium"
                    >
                      {property.phone}
                    </a>
                  </div>
                </div>
                <div className="flex items-start gap-2.5">
                  <Clock className="mt-0.5 h-4 w-4 text-primary shrink-0" />
                  <div>
                    <div className="font-semibold">Quy định giờ giấc</div>
                    <div className="text-muted-foreground">
                      Nhận phòng: {property.check_in_time ? property.check_in_time.slice(0, 5) : '14:00'} · Trả phòng: {property.check_out_time ? property.check_out_time.slice(0, 5) : '12:00'}
                    </div>
                  </div>
                </div>
              </div>

              {/* Hướng dẫn thay đổi / hủy phòng an toàn */}
              <div className="mt-5 rounded-xl bg-amber-50/70 p-3.5 border border-amber-200/60 text-[11px] text-amber-900 leading-relaxed">
                <span className="font-semibold block mb-0.5">✦ Lưu ý thay đổi lịch trình:</span>
                Để yêu cầu thay đổi ngày lưu trú hoặc hủy đơn đặt phòng, quý khách vui lòng liên hệ trực tiếp lễ tân theo số điện thoại trên để được hỗ trợ theo quy định của cơ sở.
              </div>
            </section>
          )}

          {/* Linked Invoice Summary Card */}
          {validInvoiceId && (
            <section className="rounded-2xl border border-primary/20 bg-gradient-to-br from-surface to-white p-6 shadow-xs">
              <div className="flex items-center gap-2">
                <FileText className="h-4 w-4 text-accent" />
                <h2 className="font-display text-lg font-bold text-primary">
                  Hóa đơn đã xuất
                </h2>
              </div>
              <p className="mt-2 text-xs text-muted-foreground">
                Đơn đặt phòng này đã được lập hóa đơn quyết toán chính thức.
              </p>
              <div className="mt-4 flex items-center justify-between rounded-xl bg-white p-3 border border-border/60">
                <div>
                  <div className="text-[10px] uppercase tracking-wider text-muted-foreground">Mã hóa đơn</div>
                  <div className="font-mono text-xs font-bold text-primary">
                    {booking.invoice?.invoice_code || `INV#${validInvoiceId}`}
                  </div>
                </div>
                <span
                  className={`rounded-full px-2.5 py-0.5 text-[10.5px] font-semibold ${
                    booking.invoice?.payment_status === 'PAID'
                      ? 'bg-emerald-50 text-emerald-800'
                      : 'bg-amber-50 text-amber-800'
                  }`}
                >
                  {booking.invoice?.payment_status === 'PAID' ? 'Đã thanh toán' : 'Chưa thanh toán'}
                </span>
              </div>
              <Link
                to={`/customer/invoices/${validInvoiceId}`}
                className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
              >
                <FileText className="h-3.5 w-3.5" />
                <span>Xem chi tiết hóa đơn</span>
              </Link>
            </section>
          )}
        </aside>
      </div>
    </div>
  );
}
