import { Search } from "lucide-react";
import { useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { DestinationField } from "./search/DestinationField";
import { DateField } from "./search/DateField";
import { GuestsField } from "./search/GuestsField";

function toIsoDate(d) {
  if (!d || !(d instanceof Date) || isNaN(d)) return "";
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function parseIsoDate(str) {
  if (!str) return undefined;
  const parts = str.split("-");
  if (parts.length !== 3) return undefined;
  const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  return isNaN(d.getTime()) ? undefined : d;
}

export function SearchBar() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  // Initialize from searchParams if present, preserving state across navigation
  const [destination, setDestination] = useState(
    () => searchParams.get("city") || searchParams.get("q") || ""
  );
  const [checkIn, setCheckIn] = useState(() =>
    parseIsoDate(searchParams.get("startDate"))
  );
  const [checkOut, setCheckOut] = useState(() =>
    parseIsoDate(searchParams.get("endDate"))
  );
  const [guests, setGuests] = useState(() => ({
    adults: Math.max(1, parseInt(searchParams.get("adults") || "2", 10)),
    children: Math.max(0, parseInt(searchParams.get("children") || "0", 10)),
    rooms: Math.max(1, parseInt(searchParams.get("roomsCount") || "1", 10)),
  }));

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

    // Validation
    if (guests.adults < 1) {
      toast.error("Vui lòng chọn ít nhất 1 người lớn.");
      return;
    }

    if (guests.rooms < 1) {
      toast.error("Vui lòng chọn ít nhất 1 phòng.");
      return;
    }

    if (checkIn && checkOut && checkOut <= checkIn) {
      toast.error("Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 đêm.");
      return;
    }

    const params = new URLSearchParams();
    if (destination.trim()) params.set("city", destination.trim());
    if (checkIn) params.set("startDate", toIsoDate(checkIn));
    if (checkOut) params.set("endDate", toIsoDate(checkOut));
    params.set("roomsCount", String(guests.rooms));
    params.set("adults", String(guests.adults));
    if (guests.children > 0) params.set("children", String(guests.children));

    navigate(`/search?${params.toString()}`);
  };

  return (
    <form
      role="search"
      onSubmit={handleSubmit}
      className="w-full rounded-3xl bg-background/95 backdrop-blur-sm shadow-[0_20px_60px_-25px_rgba(17,26,19,0.35)] ring-1 ring-black/[0.04] transition-shadow duration-300"
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
            className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-primary px-7 text-sm font-medium text-primary-foreground transition-colors duration-200 hover:bg-primary-mid md:h-14 md:w-auto"
          >
            <Search className="h-4 w-4" aria-hidden />
            Tìm kiếm
          </button>
        </div>
      </div>
    </form>
  );
}
