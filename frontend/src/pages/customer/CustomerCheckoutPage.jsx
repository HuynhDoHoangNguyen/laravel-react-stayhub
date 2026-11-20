import { useState, useEffect, useMemo, useRef } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, AlertCircle, Compass, BedDouble, CheckCircle2 } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { propertyService } from '@/services/propertyService';
import { bookingService, nightsBetween } from '@/services/bookingService';
import { CheckoutStepper } from '@/components/checkout/CheckoutStepper';
import { CheckoutGuestsStep } from '@/components/checkout/CheckoutGuestsStep';
import { CheckoutReviewStep } from '@/components/checkout/CheckoutReviewStep';
import { CheckoutPriceSummary } from '@/components/checkout/CheckoutPriceSummary';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

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
  const [preview, setPreview] = useState(null);
  const [previewSnapshot, setPreviewSnapshot] = useState(null);
  const [previewError, setPreviewError] = useState('');
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [createError, setCreateError] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [createdBooking, setCreatedBooking] = useState(null);
  const createLock = useRef(false);
  const previewRequestIdRef = useRef(0);
  const isMountedRef = useRef(true);

  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  // 3. Tải thông tin cơ sở lưu trú và hạng phòng thật
  const [property, setProperty] = useState(null);
  const [propertySource, setPropertySource] = useState(null);
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
        const res = await propertyService.getBookableProperty(propertyId, {
          startDate,
          endDate,
          adults: urlAdults,
          children: urlChildren,
        });
        if (!ignore) {
          setPropertySource(res?.source || null);
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
  }, [propertyId, startDate, endDate, urlAdults, urlChildren]);

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

  // Đường dẫn quay lại chi tiết chỗ nghỉ
  const backToPropertyUrl = propertyId
    ? `/properties/${propertyId}?checkIn=${startDate || ''}&checkOut=${endDate || ''}&adults=${adults}`
    : '/search';

  const bookingPayload = useMemo(() => ({
    room_id: Number(roomId),
    check_in_date: startDate,
    check_out_date: endDate,
    guest_count: (Number(adults) || 1) + (Number(children) || 0),
    note: note.trim() || null,
  }), [roomId, startDate, endDate, adults, children, note]);

  // R03: Pure derived active preview; invalidates automatically whenever booking parameters change
  const isPreviewCurrent = useMemo(() => {
    if (!preview || !previewSnapshot) return false;
    return (
      previewSnapshot.room_id === bookingPayload.room_id &&
      previewSnapshot.check_in_date === bookingPayload.check_in_date &&
      previewSnapshot.check_out_date === bookingPayload.check_out_date &&
      previewSnapshot.guest_count === bookingPayload.guest_count
    );
  }, [preview, previewSnapshot, bookingPayload]);

  const activePreview = isPreviewCurrent ? preview : null;

  const handlePreviewBooking = async () => {
    // R01: Block mutation if source is not verified real room-search
    if (propertySource !== 'room-search') {
      setPreview(null);
      setPreviewSnapshot(null);
      setPreviewError(
        propertySource === 'mock'
          ? 'Chỗ nghỉ này đang ở chế độ xem thử (mock), không thể tính giá hoặc đặt phòng từ hệ thống thực tế.'
          : 'Nguồn dữ liệu chỗ nghỉ không hợp lệ để tính giá.'
      );
      return;
    }

    const currentReqId = ++previewRequestIdRef.current;
    const requestedPayload = {
      room_id: bookingPayload.room_id,
      check_in_date: bookingPayload.check_in_date,
      check_out_date: bookingPayload.check_out_date,
      guest_count: bookingPayload.guest_count,
    };

    setPreview(null);
    setPreviewSnapshot(null);
    setPreviewError('');
    setCreateError('');
    setIsPreviewing(true);
    try {
      const result = await bookingService.previewBooking(requestedPayload);
      if (!isMountedRef.current) return;
      // R03: Only set preview if this request is still current
      if (currentReqId === previewRequestIdRef.current) {
        setPreview(result);
        setPreviewSnapshot(requestedPayload);
      }
    } catch (error) {
      if (!isMountedRef.current) return;
      if (currentReqId === previewRequestIdRef.current) {
        setPreviewError(error.message);
      }
    } finally {
      if (isMountedRef.current && currentReqId === previewRequestIdRef.current) {
        setIsPreviewing(false);
      }
    }
  };

  const updateGuestCount = (setter, value) => {
    setter(value);
    setPreview(null);
    setPreviewSnapshot(null);
    setPreviewError('');
    setCreateError('');
    setIsPreviewing(false);
    previewRequestIdRef.current += 1;
  };

  const handleCreateBooking = async () => {
    if (createLock.current) return;

    // R01: Block mutation if data source is mock
    if (propertySource !== 'room-search') {
      setCreateError(
        propertySource === 'mock'
          ? 'Không thể tạo đặt phòng thật từ chỗ nghỉ xem thử (mock).'
          : 'Nguồn dữ liệu chỗ nghỉ không hợp lệ để đặt phòng.'
      );
      return;
    }

    // R03: Validate that preview exists and matches the exact current payload
    const isPreviewCurrent =
      preview &&
      previewSnapshot &&
      previewSnapshot.room_id === bookingPayload.room_id &&
      previewSnapshot.check_in_date === bookingPayload.check_in_date &&
      previewSnapshot.check_out_date === bookingPayload.check_out_date &&
      previewSnapshot.guest_count === bookingPayload.guest_count &&
      preview.availability?.available;

    if (!isPreviewCurrent) {
      setCreateError('Thông tin đặt phòng đã thay đổi hoặc chưa có báo giá hợp lệ. Quý khách vui lòng kiểm tra lại giá trước khi xác nhận.');
      return;
    }

    createLock.current = true;
    setCreateError('');
    setIsCreating(true);
    try {
      const result = await bookingService.createBooking(bookingPayload);
      if (!isMountedRef.current) return;
      setCreatedBooking(result);
    } catch (error) {
      if (!isMountedRef.current) return;
      setCreateError(error.message);
      if (error.status === 409 || error.status === 422) {
        setPreview(null);
        setPreviewSnapshot(null);
      }
    } finally {
      if (isMountedRef.current) {
        createLock.current = false;
        setIsCreating(false);
      }
    }
  };

  // State: Loading Skeleton
  if (isLoading) {
    return (
      <div className="mx-auto max-w-[1240px] px-4 pt-2 pb-20 text-left sm:px-6">
        <Skeleton aria-hidden="true" className="h-4 w-36 bg-surface" />
        <div className="mt-6 flex justify-between">
          <Skeleton aria-hidden="true" className="h-10 w-64 rounded-xl bg-surface" />
          <Skeleton aria-hidden="true" className="h-8 w-48 rounded-xl bg-surface" />
        </div>
        <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[1fr_380px]">
          <Skeleton aria-hidden="true" className="h-[460px] rounded-3xl bg-white/70" />
          <Skeleton aria-hidden="true" className="h-[360px] rounded-3xl bg-white/70" />
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
        <Card className="rounded-3xl border-border/70 bg-white p-8 shadow-xs sm:p-12">
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
            <Button asChild className="h-11 rounded-xl px-5 text-xs font-semibold">
              <Link to={backToPropertyUrl}>
                <BedDouble className="h-4 w-4" />
                <span>Quay lại chọn phòng</span>
              </Link>
            </Button>
            <Button asChild variant="outline" className="h-11 rounded-xl px-5 text-xs font-semibold">
              <Link to="/search">
                <Compass className="h-4 w-4" />
                <span>Khám phá chỗ nghỉ khác</span>
              </Link>
            </Button>
          </div>
        </Card>
      </div>
    );
  }

  if (createdBooking) {
    return (
      <div className="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <Card className="rounded-3xl border-emerald-200 bg-white p-8 shadow-xs sm:p-12">
          <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
            <CheckCircle2 className="h-7 w-7" />
          </div>
          <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-700">Đặt phòng thành công</p>
          <h1 className="mt-2 font-display text-2xl font-bold text-primary sm:text-3xl">StayHub đã nhận booking của bạn</h1>
          <p className="mt-3 text-sm text-muted-foreground">Mã booking</p>
          <p className="mt-1 font-mono text-xl font-bold text-ink">{createdBooking.booking_code}</p>
          <p className="mt-4 text-sm text-muted-foreground">Đơn đang chờ cơ sở lưu trú xác nhận.</p>
          <Button asChild className="mt-8 h-12 rounded-xl px-6 text-sm font-semibold">
            <Link to="/customer/bookings">Xem đơn đặt phòng</Link>
          </Button>
        </Card>
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

      {/* R01: Mock Data Warning Banner */}
      {propertySource === 'mock' && (
        <div role="status" className="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 shadow-xs">
          <div className="flex items-center gap-2 font-semibold">
            <AlertCircle className="h-4 w-4 text-amber-700" />
            <span>Chế độ xem thử (Mock data)</span>
          </div>
          <p className="mt-1 text-amber-800">
            Chỗ nghỉ này đang được hiển thị từ dữ liệu mẫu phát triển. Chức năng tính giá thực tế và tạo đặt phòng trên hệ thống bị khóa để tránh ảnh hưởng đến cơ sở dữ liệu.
          </p>
        </div>
      )}

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
              onAdultsChange={(value) => updateGuestCount(setAdults, value)}
              onChildrenChange={(value) => updateGuestCount(setChildren, value)}
              onNoteChange={setNote}
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
              user={user}
              onEditGuests={() => setStep('guests')}
              onBackToProperty={() => navigate(backToPropertyUrl)}
              preview={activePreview}
              previewError={previewError}
              createError={createError}
              isPreviewing={isPreviewing}
              isCreating={isCreating}
              isMock={propertySource === 'mock'}
              onRequestPreview={handlePreviewBooking}
              onCreateBooking={handleCreateBooking}
            />
          )}
        </div>

        {/* Right Column: Sticky Price Summary */}
        <div className="lg:sticky lg:top-24 lg:h-fit">
          <CheckoutPriceSummary
            room={selectedRoom}
            nights={nights}
            quote={activePreview}
          />
        </div>
      </div>
    </div>
  );
}
