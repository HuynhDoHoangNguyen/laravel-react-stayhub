import { useState, useEffect, useMemo } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, AlertCircle, Compass, BedDouble } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { propertyService } from '@/services/propertyService';
import { nightsBetween } from '@/services/bookingService';
import { CheckoutStepper } from '@/components/checkout/CheckoutStepper';
import { CheckoutGuestsStep } from '@/components/checkout/CheckoutGuestsStep';
import { CheckoutReviewStep } from '@/components/checkout/CheckoutReviewStep';
import { CheckoutPriceSummary } from '@/components/checkout/CheckoutPriceSummary';

export default function CustomerCheckoutPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { user } = useAuth();

  // 1. Phân giải an toàn tham số tìm kiếm (URL Context Preservation)
  const propertyId = searchParams.get('propertyId');
  const roomId = searchParams.get('roomId');
  const startDate = searchParams.get('startDate') || searchParams.get('checkIn');
  const endDate = searchParams.get('endDate') || searchParams.get('checkOut');
  const urlAdults = parseInt(searchParams.get('adults'), 10) || 2;
  const urlChildren = parseInt(searchParams.get('children'), 10) || 0;

  // 2. Local State của Checkout Form
  const [step, setStep] = useState('guests');
  const [adults, setAdults] = useState(urlAdults);
  const [children, setChildren] = useState(urlChildren);
  const [note, setNote] = useState('');
  const [customPhone, setCustomPhone] = useState(null);
  const contactPhone = customPhone !== null ? customPhone : (user?.phone || '');

  // 3. Tải thông tin cơ sở lưu trú và hạng phòng thật
  const [property, setProperty] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState(null);

  useEffect(() => {
    let ignore = false;

    async function loadProperty() {
      if (!propertyId || isNaN(Number(propertyId))) {
        setErrorMsg('Mã cơ sở lưu trú không hợp lệ hoặc bị thiếu.');
        setIsLoading(false);
        return;
      }

      setIsLoading(true);
      try {
        const res = await propertyService.getPropertyById(propertyId);
        if (!ignore) {
          if (res?.source === 'api') {
            setProperty(null);
            setErrorMsg('Hiện chưa thể đặt phòng trực tuyến từ danh mục này. Giá phòng chỉ để tham khảo.');
            setIsLoading(false);
            return;
          }
          if (res?.data) {
            setProperty(res.data);
            setErrorMsg(null);
          } else {
            setErrorMsg('Không tìm thấy thông tin cơ sở lưu trú yêu cầu.');
          }
          setIsLoading(false);
        }
      } catch (err) {
        if (!ignore) {
          setErrorMsg(err.message || 'Không thể kết nối thông tin chỗ nghỉ.');
          setIsLoading(false);
        }
      }
    }

    loadProperty();

    return () => {
      ignore = true;
    };
  }, [propertyId]);

  // 4. Tìm hạng phòng tương ứng trong danh sách phòng của cơ sở
  const selectedRoom = useMemo(() => {
    if (!property?.rooms || !roomId) return null;
    return property.rooms.find((r) => String(r.id) === String(roomId)) || null;
  }, [property, roomId]);

  // 5. Kiểm tra tính hợp lệ của ngày tháng và số đêm
  const isValidDateRange = Boolean(
    startDate &&
    endDate &&
    new Date(endDate).getTime() > new Date(startDate).getTime()
  );

  const nights = useMemo(() => {
    if (!isValidDateRange) return 0;
    return nightsBetween(startDate, endDate);
  }, [startDate, endDate, isValidDateRange]);

  // 6. Tính toán tiền phòng chính xác (Integer Scaled Arithmetic)
  const roomTotal = useMemo(() => {
    if (!selectedRoom || nights <= 0) return 0;
    const nightlyPrice = Math.round(Number(selectedRoom.price || selectedRoom.price_per_night || 0));
    return nightlyPrice * nights; // 1 phòng theo quy định nghiệp vụ
  }, [selectedRoom, nights]);

  // Đường dẫn quay lại chi tiết chỗ nghỉ
  const backToPropertyUrl = propertyId
    ? `/properties/${propertyId}?checkIn=${startDate || ''}&checkOut=${endDate || ''}&adults=${adults}`
    : '/search';

  // State: Loading Skeleton
  if (isLoading) {
    return (
      <div className="mx-auto max-w-[1240px] px-4 pt-2 pb-20 text-left sm:px-6">
        <div className="h-4 w-36 animate-pulse rounded bg-surface" />
        <div className="mt-6 flex justify-between">
          <div className="h-10 w-64 animate-pulse rounded-xl bg-surface" />
          <div className="h-8 w-48 animate-pulse rounded-xl bg-surface" />
        </div>
        <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[1fr_380px]">
          <div className="h-[460px] animate-pulse rounded-3xl bg-white/70" />
          <div className="h-[360px] animate-pulse rounded-3xl bg-white/70" />
        </div>
      </div>
    );
  }

  // State: Thiếu hoặc Sai lệch dữ liệu cốt lõi (Missing Context / Invalid Room / Dates)
  if (errorMsg || !property || !selectedRoom || !isValidDateRange) {
    let message = errorMsg;
    if (!message) {
      if (!isValidDateRange) {
        message = 'Khoảng thời gian nhận phòng và trả phòng không hợp lệ. Quý khách vui lòng chọn lại ngày lưu trú.';
      } else if (!selectedRoom) {
        message = 'Không tìm thấy hạng phòng yêu cầu hoặc hạng phòng không còn thuộc cơ sở này.';
      }
    }

    return (
      <div className="mx-auto max-w-xl py-16 text-center">
        <div className="rounded-3xl border border-border/70 bg-white p-8 shadow-xs sm:p-12">
          <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber-50 text-amber-700">
            <AlertCircle className="h-7 w-7" />
          </div>
          <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
            ✦ Đặt phòng lưu trú
          </p>
          <h1 className="mt-2 font-display text-2xl font-bold text-primary sm:text-3xl">
            Thông tin đặt phòng chưa đầy đủ
          </h1>
          <p className="mx-auto mt-3 max-w-md text-xs leading-relaxed text-muted-foreground">
            {message}
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link
              to={backToPropertyUrl}
              className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid"
            >
              <BedDouble className="h-4 w-4" />
              <span>Quay lại chọn phòng</span>
            </Link>
            <Link
              to="/search"
              className="inline-flex items-center gap-2 rounded-xl border border-border/80 bg-white px-5 py-2.5 text-xs font-semibold text-ink hover:border-primary/40"
            >
              <Compass className="h-4 w-4" />
              <span>Khám phá chỗ nghỉ khác</span>
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-[1240px] px-4 pt-2 pb-20 text-left sm:px-6">
      {/* Breadcrumb Navigation */}
      <div className="mb-4">
        <Link
          to={backToPropertyUrl}
          className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          <span>Quay lại chỗ nghỉ {property.name}</span>
        </Link>
      </div>

      {/* Header & Stepper */}
      <div className="flex flex-col gap-4 border-b border-border/60 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent">
            ✦ Đặt phòng lưu trú
          </p>
          <h1 className="mt-1 font-display text-2xl font-bold leading-tight text-primary sm:text-3xl md:text-4xl">
            Xem lại thông tin đặt phòng
          </h1>
          <p className="mt-1.5 text-xs text-muted-foreground">
            Kiểm tra thông tin lưu trú và dự toán chi phí cho chuyến đi của bạn tại StayHub.
          </p>
        </div>

        {/* 2-Step Stepper */}
        <div className="self-start sm:self-auto">
          <CheckoutStepper currentStep={step} />
        </div>
      </div>

      {/* Main 2-Column Content Layout (Content + Sticky Price Summary) */}
      <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[1fr_380px]">
        {/* Left Column: Form Steps */}
        <div className="min-w-0">
          {step === 'guests' ? (
            <CheckoutGuestsStep
              user={user}
              room={selectedRoom}
              adults={adults}
              children={children}
              note={note}
              contactPhone={contactPhone}
              onAdultsChange={setAdults}
              onChildrenChange={setChildren}
              onNoteChange={setNote}
              onContactPhoneChange={setCustomPhone}
              onContinue={() => setStep('review')}
            />
          ) : (
            <CheckoutReviewStep
              property={property}
              room={selectedRoom}
              startDate={startDate}
              endDate={endDate}
              nights={nights}
              adults={adults}
              children={children}
              note={note}
              contactPhone={contactPhone}
              user={user}
              onEditGuests={() => setStep('guests')}
              onBackToProperty={() => navigate(backToPropertyUrl)}
            />
          )}
        </div>

        {/* Right Column: Sticky Price Summary */}
        <div className="lg:sticky lg:top-24 lg:h-fit">
          <CheckoutPriceSummary
            room={selectedRoom}
            nights={nights}
            roomTotal={roomTotal}
          />
        </div>
      </div>
    </div>
  );
}
