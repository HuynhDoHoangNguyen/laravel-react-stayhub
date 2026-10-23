import { useParams, useSearchParams, Link } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Loader2, AlertCircle } from "lucide-react";
import { propertyService } from "@/services/propertyService";
import { PropertyHeader } from "@/components/hotel/PropertyHeader";
import { PropertyGallery } from "@/components/hotel/PropertyGallery";
import { PropertyAmenities } from "@/components/hotel/PropertyAmenities";
import { RoomsList } from "@/components/hotel/RoomsList";
import { BookingPreviewCard } from "@/components/hotel/BookingPreviewCard";
import { ReviewsSection } from "@/components/hotel/ReviewsSection";

function todayPlus(days) {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

export default function PropertyDetailPage() {
  const { propertyId } = useParams();
  const [searchParams, setSearchParams] = useSearchParams();

  // Search Context parsed from URL parameters
  const rawStart = searchParams.get("startDate") || todayPlus(1);
  const rawEnd = searchParams.get("endDate") || todayPlus(3);
  const roomsCount = Math.max(1, Number(searchParams.get("roomsCount")) || 1);
  const adults = Math.max(1, Number(searchParams.get("adults")) || 2);
  const children = Math.max(0, Number(searchParams.get("children")) || 0);

  const startDate = rawStart;
  const endDate = useMemo(() => {
    const s = new Date(rawStart);
    const e = new Date(rawEnd);
    if (isNaN(e.getTime()) || e <= s) {
      const fallback = new Date(s);
      fallback.setDate(fallback.getDate() + 2);
      return fallback.toISOString().slice(0, 10);
    }
    return rawEnd;
  }, [rawStart, rawEnd]);

  const nights = useMemo(() => {
    const n = Math.round(
      (new Date(endDate).getTime() - new Date(startDate).getTime()) / 86400000
    );
    return Number.isFinite(n) && n > 0 ? n : 1;
  }, [startDate, endDate]);

  const handleDatesChange = (newStart, newEnd) => {
    const params = new URLSearchParams(searchParams);
    params.set("startDate", newStart);
    params.set("endDate", newEnd);
    setSearchParams(params, { replace: true });
  };

  // Back to Search preservation
  const backToSearchUrl = useMemo(() => {
    const params = new URLSearchParams(searchParams);
    const qs = params.toString();
    return qs ? `/search?${qs}` : "/search";
  }, [searchParams]);

  // Data fetching
  const [property, setProperty] = useState(null);
  const [propertySource, setPropertySource] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);
  const [selectedRoomId, setSelectedRoomId] = useState(null);

  const [prevPropertyId, setPrevPropertyId] = useState(propertyId);
  if (propertyId !== prevPropertyId) {
    setPrevPropertyId(propertyId);
    setIsLoading(true);
    setIsError(false);
  }

  useEffect(() => {
    let isMounted = true;

    propertyService
      .getPropertyById(propertyId)
      .then((res) => {
        if (isMounted) {
          const data = res?.data || null;
          setProperty(data);
          setPropertySource(res?.source || null);
          if (data?.rooms?.length > 0) {
            setSelectedRoomId((prev) => prev ?? data.rooms[0].id);
          }
          setIsLoading(false);
        }
      })
      .catch(() => {
        if (isMounted) {
          setProperty(null);
          setPropertySource(null);
          setIsError(true);
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [propertyId]);

  // Active selected room
  const selectedRoom = useMemo(() => {
    if (!property?.rooms || property.rooms.length === 0) return null;
    return (
      property.rooms.find((r) => r.id === selectedRoomId) ?? property.rooms[0]
    );
  }, [property, selectedRoomId]);

  // Loading State
  if (isLoading) {
    return (
      <div className="mx-auto max-w-[1440px] px-6 py-20 text-center md:px-10">
        <div className="flex flex-col items-center justify-center gap-4 py-24 text-muted-foreground">
          <Loader2 className="h-8 w-8 animate-spin text-primary" />
          <p className="text-sm font-medium">Đang tải thông tin chỗ nghỉ...</p>
        </div>
      </div>
    );
  }

  // Error State
  if (isError) {
    return (
      <div className="mx-auto max-w-lg px-6 py-24 text-center">
        <div className="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full bg-destructive/10 text-destructive">
          <AlertCircle className="h-6 w-6" />
        </div>
        <h2 className="font-display text-2xl text-ink">
          Không thể tải thông tin chỗ nghỉ
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          Đã có lỗi xảy ra trong quá trình kết nối. Vui lòng kiểm tra lại đường truyền hoặc thử lại sau.
        </p>
        <Link
          to={backToSearchUrl}
          className="mt-6 inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-medium text-primary-foreground transition hover:bg-primary-mid"
        >
          <ArrowLeft className="h-4 w-4" />
          Quay lại danh sách tìm kiếm
        </Link>
      </div>
    );
  }

  // Not Found State (404)
  if (!property) {
    return (
      <div className="mx-auto max-w-lg px-6 py-24 text-center">
        <h2 className="font-display text-2xl text-ink">
          Không tìm thấy chỗ nghỉ
        </h2>
        <p className="mt-3 text-sm text-muted-foreground">
          Chỗ nghỉ bạn tìm kiếm không tồn tại hoặc đã ngừng cung cấp dịch vụ trên StayHub.
        </p>
        <Link
          to={backToSearchUrl}
          className="mt-6 inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-medium text-primary-foreground transition hover:bg-primary-mid"
        >
          <ArrowLeft className="h-4 w-4" />
          Quay lại danh sách tìm kiếm
        </Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background pb-20">
      {/* Top back navigation bar */}
      <div className="border-b border-border/60 bg-surface/30">
        <div className="mx-auto flex max-w-[1440px] items-center justify-between px-6 py-4 md:px-10">
          <Link
            to={backToSearchUrl}
            className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-primary"
          >
            <ArrowLeft className="h-4 w-4" />
            Quay lại kết quả tìm kiếm
          </Link>
        </div>
      </div>

      <main className="mx-auto max-w-[1440px] px-6 pt-8 md:px-10">
        {/* 1. Property Header */}
        <PropertyHeader property={property} />

        {/* 2. Photo Gallery with Lightbox */}
        <div className="mt-6">
          <PropertyGallery photos={property.photos ?? []} name={property.name} />
        </div>

        {/* 3. Main 2-Column Content Grid: Left details & Right sticky booking preview */}
        <div className="mt-10 grid grid-cols-1 items-start gap-10 lg:grid-cols-[1fr_380px]">
          {/* Left Column: Description, Amenities, Rooms, Reviews */}
          <div className="space-y-10 min-w-0">
            {/* Property Overview */}
            {property.description && (
              <section className="space-y-3">
                <h2 className="font-display text-xl text-primary md:text-2xl">
                  Giới thiệu không gian nghỉ dưỡng
                </h2>
                <p className="text-[15px] leading-relaxed text-ink/80">
                  {property.description}
                </p>
              </section>
            )}

            {/* Signature Amenities */}
            <PropertyAmenities amenities={property.amenities ?? []} />

            {/* Rooms List */}
            <RoomsList
              rooms={property.rooms ?? []}
              nights={nights}
              selectedRoomId={selectedRoom?.id}
              onSelect={(r) => setSelectedRoomId(r.id)}
            />

            {/* Reviews Section */}
            <ReviewsSection
              propertyId={
                import.meta.env.DEV && searchParams.has("backendPropertyId")
                  ? searchParams.get("backendPropertyId")
                  : property.id
              }
              propertySource={
                import.meta.env.DEV && searchParams.has("backendPropertyId")
                  ? "api"
                  : propertySource
              }
            />
          </div>

          {/* Right Column: Sticky Booking Preview Card */}
          <div className="lg:min-h-[500px]">
            <BookingPreviewCard
              propertyId={property.id}
              room={selectedRoom}
              startDate={startDate}
              endDate={endDate}
              roomsCount={roomsCount}
              adults={adults}
              children={children}
              nights={nights}
              onDatesChange={handleDatesChange}
            />
          </div>
        </div>
      </main>
    </div>
  );
}
