import { useNavigate } from "react-router-dom";
import { CalendarDays, Users, BedDouble, ShieldCheck, Info, ArrowRight } from "lucide-react";

const vndFormatter = new Intl.NumberFormat("vi-VN", {
  style: "currency",
  currency: "VND",
  maximumFractionDigits: 0,
});

function formatDisplayDate(dateStr) {
  if (!dateStr) return "Chưa chọn";
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString("vi-VN", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch {
    return dateStr;
  }
}

export function BookingPreviewCard({
  propertyId,
  room,
  startDate,
  endDate,
  roomsCount = 1,
  adults = 2,
  children = 0,
  nights = 1,
  onDatesChange,
}) {
  const navigate = useNavigate();
  const validNights = Math.max(1, Number(nights) || 1);
  const validRooms = Math.max(1, Number(roomsCount) || 1);
  const nightlyPrice = room ? Math.round(Number(room.price) || 0) : 0;

  // Strict integer financial calculation for estimate
  const roomTotal = nightlyPrice * validNights * validRooms;

  const today = new Date().toISOString().slice(0, 10);

  const handleStartDateChange = (e) => {
    const nextStart = e.target.value;
    if (onDatesChange) {
      let nextEnd = endDate;
      if (nextEnd && nextEnd <= nextStart) {
        const d = new Date(nextStart);
        d.setDate(d.getDate() + 1);
        nextEnd = d.toISOString().slice(0, 10);
      }
      onDatesChange(nextStart, nextEnd);
    }
  };

  const handleEndDateChange = (e) => {
    const nextEnd = e.target.value;
    if (onDatesChange && nextEnd > startDate) {
      onDatesChange(startDate, nextEnd);
    }
  };

  const handleScrollToRooms = () => {
    const el = document.getElementById("rooms-list");
    if (el) {
      el.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  const isValidDates = Boolean(
    startDate &&
    endDate &&
    new Date(endDate).getTime() > new Date(startDate).getTime()
  );

  const handleProceedToCheckout = () => {
    if (!room || !isValidDates) return;
    const propId = propertyId || room.property_id || room.property?.id || 1;
    navigate(
      `/customer/checkout?propertyId=${propId}&roomId=${room.id}&startDate=${startDate}&endDate=${endDate}&roomsCount=1&adults=${adults}&children=${children}`
    );
  };

  return (
    <aside className="sticky top-28 space-y-5 rounded-3xl border border-border/70 bg-card p-6 shadow-[0_20px_50px_-24px_rgba(26,46,32,0.22)] ring-1 ring-black/[0.04]">
      {/* Price per night header */}
      <div>
        <div className="flex items-baseline gap-2">
          <span className="font-display text-2xl font-semibold text-ink md:text-3xl">
            {room ? vndFormatter.format(nightlyPrice) : "—"}
          </span>
          <span className="text-[13px] text-muted-foreground">/ đêm</span>
        </div>
        {room ? (
          <p className="mt-1 text-[13px] font-medium text-primary">
            {room.name || room.roomTypeName}
          </p>
        ) : (
          <p className="mt-1 text-[12.5px] text-muted-foreground">
            Chọn một hạng phòng bên dưới để xem dự toán
          </p>
        )}
      </div>

      {/* Date Pickers */}
      <div className="grid grid-cols-2 gap-2 rounded-2xl border border-border/70 bg-background/50 p-2">
        <label className="block rounded-xl px-2.5 py-1.5 transition-colors focus-within:bg-surface">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
            Nhận phòng
          </span>
          <div className="mt-1 flex items-center gap-1.5 text-[13px] font-medium text-ink">
            <CalendarDays className="h-3.5 w-3.5 text-primary/70 shrink-0" />
            <input
              type="date"
              min={today}
              value={startDate || ""}
              onChange={handleStartDateChange}
              className="w-full bg-transparent text-[12.5px] text-ink focus:outline-none"
            />
          </div>
          <div className="text-[11px] text-muted-foreground mt-0.5">
            {formatDisplayDate(startDate)}
          </div>
        </label>

        <label className="block rounded-xl px-2.5 py-1.5 transition-colors focus-within:bg-surface">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
            Trả phòng
          </span>
          <div className="mt-1 flex items-center gap-1.5 text-[13px] font-medium text-ink">
            <CalendarDays className="h-3.5 w-3.5 text-primary/70 shrink-0" />
            <input
              type="date"
              min={startDate || today}
              value={endDate || ""}
              onChange={handleEndDateChange}
              className="w-full bg-transparent text-[12.5px] text-ink focus:outline-none"
            />
          </div>
          <div className="text-[11px] text-muted-foreground mt-0.5">
            {formatDisplayDate(endDate)}
          </div>
        </label>
      </div>

      {/* Guest & Room count information */}
      <div className="flex items-center justify-between rounded-2xl border border-border/70 bg-background/50 px-4 py-3 text-[13px]">
        <span className="inline-flex items-center gap-2 text-ink">
          <Users className="h-4 w-4 text-primary/70" />
          Quy mô lưu trú
        </span>
        <span className="font-medium text-ink">
          1 phòng · {adults} người lớn{children > 0 ? `, ${children} trẻ em` : ""}
        </span>
      </div>

      {/* Cost Breakdown Estimate */}
      {room && (
        <div className="space-y-2 border-t border-border/60 pt-4 text-[13px]">
          <div className="flex items-center justify-between text-muted-foreground">
            <span>
              {vndFormatter.format(nightlyPrice)} × {validNights} đêm
            </span>
            <span className="font-medium text-ink">
              {vndFormatter.format(roomTotal)}
            </span>
          </div>

          <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
            <Info className="h-3.5 w-3.5 shrink-0 text-primary/70" />
            <span>Chi phí ước tính dựa trên đơn giá niêm yết của hạng phòng.</span>
          </div>

          <div className="mt-3 flex items-baseline justify-between border-t border-border/60 pt-3">
            <div>
              <span className="text-[14px] font-semibold text-ink">Tổng tạm tính</span>
              <p className="text-[11px] text-muted-foreground">Ước tính theo thời gian lưu trú</p>
            </div>
            <span className="font-display text-xl font-bold text-primary">
              {vndFormatter.format(roomTotal)}
            </span>
          </div>
        </div>
      )}

      {/* Action Buttons */}
      {room ? (
        <div className="space-y-2.5">
          <button
            type="button"
            disabled={!isValidDates}
            onClick={handleProceedToCheckout}
            className="w-full cursor-pointer rounded-full bg-primary py-3.5 text-[13.5px] font-semibold text-primary-foreground shadow-sm transition hover:bg-primary-mid active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <span>Tiến hành đặt phòng</span>
            <ArrowRight className="h-4 w-4" />
          </button>

          <button
            type="button"
            onClick={handleScrollToRooms}
            className="w-full cursor-pointer rounded-full border border-border/80 bg-surface/60 py-2.5 text-xs font-medium text-ink transition hover:bg-surface active:scale-95 flex items-center justify-center gap-1.5"
          >
            <BedDouble className="h-3.5 w-3.5 text-muted-foreground" />
            <span>Đổi sang hạng phòng khác</span>
          </button>
        </div>
      ) : (
        <button
          type="button"
          onClick={handleScrollToRooms}
          className="w-full rounded-full border border-primary/40 bg-surface/80 py-3 text-[13px] font-medium text-primary transition hover:bg-surface active:scale-95"
        >
          Chọn phòng để xem tạm tính
        </button>
      )}

      {/* Policy reassurance */}
      <p className="flex items-center gap-2 text-[11.5px] text-muted-foreground">
        <ShieldCheck className="h-4 w-4 shrink-0 text-primary/70" />
        <span>Chính sách hủy và nhận phòng tùy theo quy định của cơ sở lưu trú.</span>
      </p>
    </aside>
  );
}
