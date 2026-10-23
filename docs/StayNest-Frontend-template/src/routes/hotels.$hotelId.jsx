import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { useMemo, useState } from "react";
import { Loader2 } from "lucide-react";
import { toast } from "sonner";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { HotelGallery } from "@/components/hotel/HotelGallery";
import { HotelHeader } from "@/components/hotel/HotelHeader";
import { HotelAmenities } from "@/components/hotel/HotelAmenities";
import { RoomsList } from "@/components/hotel/RoomsList";
import { ReviewsSection } from "@/components/hotel/ReviewsSection";
import { AskAssistant } from "@/components/hotel/AskAssistant";
import { BookingCard } from "@/components/hotel/BookingCard";
import { useHotelInfo } from "@/hooks/queries/useHotelDetails";
import { initBooking } from "@/lib/api/bookings";

function todayPlus(days) {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

export default function HotelDetailsPage() {
  const { hotelId } = useParams();
  const [searchParams] = useSearchParams();

  const rawStart = searchParams.get("checkIn") || searchParams.get("startDate") || todayPlus(1);
  const rawEnd = searchParams.get("checkOut") || searchParams.get("endDate") || todayPlus(3);

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

  const roomsCount = Number(searchParams.get("rooms")) || Number(searchParams.get("roomsCount")) || 1;

  const nights = useMemo(() => {
    const n = Math.round((new Date(endDate).getTime() - new Date(startDate).getTime()) / 86400000);
    return Number.isFinite(n) && n > 0 ? n : 1;
  }, [startDate, endDate]);

  const { data, isLoading, isError, error } = useHotelInfo(hotelId, {
    startDate,
    endDate,
    roomsCount,
  });

  const [selectedRoom, setSelectedRoom] = useState();
  const [reserving, setReserving] = useState(false);
  const navigate = useNavigate();

  const activeRoom = selectedRoom ?? data?.rooms?.[0];

  const handleReserve = async () => {
    if (!activeRoom || !data) return;
    setReserving(true);
    try {
      const booking = await initBooking({
        hotelId: data.hotel.id,
        roomId: activeRoom.id,
        checkInDate: startDate,
        checkOutDate: endDate,
        roomsCount,
      });
      navigate(`/checkout/${booking.id}`);
    } catch (e) {
      const status = e?.status;
      if (status === 401 || status === 403) return;
      if (status === 409) {
        toast.error("These dates are no longer available. Please choose different dates.");
      } else if (status === 410) {
        toast.error("Your session timed out. Please try reserving again.");
      } else if (e?.message === "Validation failed" || e?.message?.includes("Validation")) {
        toast.error("Check-out date must be after check-in date.");
      } else {
        toast.error(e?.message ?? "Couldn't reserve the room. Please try again.");
      }
    } finally {
      setReserving(false);
    }
  };

  return (
    <div className="min-h-screen bg-background">
      <Nav />

      <main className="mx-auto max-w-[1300px] px-4 pt-24 pb-16 md:px-8">
        {isLoading && !data && (
          <div className="grid place-items-center py-32 text-muted-foreground">
            <Loader2 className="h-6 w-6 animate-spin" />
          </div>
        )}

        {isError && (
          <div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-8 text-center">
            <h2 className="font-display text-xl text-destructive">We couldn't load this stay</h2>
            <p className="mt-2 text-[13.5px] text-muted-foreground">
              {error?.message ?? "Please try again in a moment."}
            </p>
          </div>
        )}

        {data && (
          <>
            <HotelHeader hotel={data.hotel} />
            <div className="mt-6">
              <HotelGallery photos={data.hotel.photos ?? []} name={data.hotel.name} />
            </div>

            <div className="mt-10 grid grid-cols-1 gap-10 lg:grid-cols-[1fr_360px]">
              <div className="space-y-10">
                <HotelAmenities amenities={data.hotel.amenities ?? []} />
                <RoomsList
                  rooms={data.rooms}
                  nights={nights}
                  selectedRoomId={activeRoom?.id}
                  onSelect={setSelectedRoom}
                />
                <AskAssistant hotelId={hotelId} hotelName={data.hotel.name} />
                <ReviewsSection
                  hotelId={hotelId}
                  averageRating={data.hotel.averageRating}
                  reviewCount={data.hotel.reviewCount}
                />
              </div>

              <div className="lg:min-h-[600px]">
                <BookingCard
                  room={activeRoom}
                  startDate={startDate}
                  endDate={endDate}
                  roomsCount={roomsCount}
                  nights={nights}
                  onReserve={handleReserve}
                />
              </div>
            </div>
          </>
        )}
      </main>

      <Footer />
    </div>
  );
}
