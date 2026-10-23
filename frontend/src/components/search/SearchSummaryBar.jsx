import { Search, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { DestinationField } from "@/components/landing/search/DestinationField";
import { DateField } from "@/components/landing/search/DateField";
import { GuestsField } from "@/components/landing/search/GuestsField";

function toIsoDate(d) {
  if (!d || !(d instanceof Date) || isNaN(d)) return "";
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function fromIso(iso) {
  if (!iso) return undefined;
  const parts = iso.split("-").map(Number);
  if (parts.length !== 3) return undefined;
  const dt = new Date(parts[0], parts[1] - 1, parts[2]);
  return isNaN(dt.getTime()) ? undefined : dt;
}

export function SearchSummaryBar({
  city = "",
  startDate = "",
  endDate = "",
  roomsCount = 1,
  adults = 2,
  children = 0,
}) {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const [destination, setDestination] = useState(city);
  const [checkIn, setCheckIn] = useState(fromIso(startDate));
  const [checkOut, setCheckOut] = useState(fromIso(endDate));
  const [guests, setGuests] = useState({
    adults: Number(adults) || 2,
    children: Number(children) || 0,
    rooms: Number(roomsCount) || 1,
  });

  const [prevSync, setPrevSync] = useState({
    city,
    startDate,
    endDate,
    roomsCount,
    adults,
    children,
  });

  // Sync state during render when search params change
  if (
    city !== prevSync.city ||
    startDate !== prevSync.startDate ||
    endDate !== prevSync.endDate ||
    roomsCount !== prevSync.roomsCount ||
    adults !== prevSync.adults ||
    children !== prevSync.children
  ) {
    setPrevSync({ city, startDate, endDate, roomsCount, adults, children });
    setDestination(city || "");
    setCheckIn(fromIso(startDate));
    setCheckOut(fromIso(endDate));
    setGuests({
      adults: Number(adults) || 2,
      children: Number(children) || 0,
      rooms: Number(roomsCount) || 1,
    });
  }

  const handleCheckIn = (d) => {
    setCheckIn(d);
    if (d && checkOut && checkOut <= d) {
      setCheckOut(undefined);
    }
  };

  const minCheckOut = checkIn
    ? new Date(checkIn.getFullYear(), checkIn.getMonth(), checkIn.getDate() + 1)
    : undefined;

  const handleSubmit = (e) => {
    e.preventDefault();
    const params = new URLSearchParams(searchParams);
    if (destination.trim()) {
      params.set("city", destination.trim());
    } else {
      params.delete("city");
    }

    if (checkIn) {
      params.set("startDate", toIsoDate(checkIn));
    } else {
      params.delete("startDate");
    }

    if (checkOut) {
      params.set("endDate", toIsoDate(checkOut));
    } else {
      params.delete("endDate");
    }

    params.set("roomsCount", String(guests.rooms));
    params.set("adults", String(guests.adults));
    if (guests.children > 0) {
      params.set("children", String(guests.children));
    } else {
      params.delete("children");
    }

    navigate(`/search?${params.toString()}`, { replace: true });
  };

  return (
    <div className="relative border-b border-border/40 bg-background/95 backdrop-blur-md">
      <div className="mx-auto max-w-[1440px] px-4 pb-4 pt-4 md:px-10">
        <form
          role="search"
          onSubmit={handleSubmit}
          className="w-full rounded-3xl bg-card shadow-[0_10px_40px_-25px_rgba(17,26,19,0.2)] ring-1 ring-black/[0.04]"
        >
          <div className="flex flex-col divide-y divide-border md:flex-row md:items-stretch md:divide-x md:divide-y-0">
            <DestinationField value={destination} onChange={setDestination} />
            <DateField label="Nhận phòng" value={checkIn} onChange={handleCheckIn} />
            <DateField
              label="Trả phòng"
              value={checkOut}
              onChange={setCheckOut}
              minDate={minCheckOut}
            />
            <GuestsField value={guests} onChange={setGuests} />

            <div className="flex items-center p-2">
              <button
                type="submit"
                className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors duration-200 hover:bg-primary-mid md:h-14 md:w-auto"
              >
                <Search className="h-4 w-4" aria-hidden />
                Tìm kiếm
              </button>
            </div>
          </div>
        </form>

        <div className="mt-3 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-center text-[12.5px] text-muted-foreground">
          <span className="inline-flex items-center gap-1.5">
            <ShieldCheck className="h-4 w-4 text-primary" aria-hidden />
            <span className="font-medium text-ink">Thông tin minh bạch</span>
          </span>
          <span>— Không gian nghỉ dưỡng chọn lọc, đặt phòng nhanh chóng.</span>
        </div>
      </div>
    </div>
  );
}
