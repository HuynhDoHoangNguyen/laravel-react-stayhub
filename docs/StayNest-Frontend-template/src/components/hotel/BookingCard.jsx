import { CalendarDays, Users, BedDouble, ShieldCheck } from "lucide-react";
const currency = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 });
function fmt(d) {
    try {
        return new Date(d).toLocaleDateString(undefined, { month: "short", day: "numeric" });
    }
    catch {
        return d;
    }
}
export function BookingCard({ room, startDate, endDate, roomsCount, nights, onReserve }) {
    const n = nights > 0 ? nights : 1;
    const rc = roomsCount > 0 ? roomsCount : 1;
    const nightlyPrice = room ? Math.round(room.price) : 0;
    const total = nightlyPrice * n * rc;
    const taxes = Math.round(total * 0.12);
    const grand = total + taxes;
    return (<aside className="sticky top-24 space-y-4 rounded-2xl border border-border/70 bg-background/95 p-6 shadow-[0_20px_50px_-24px_rgba(26,46,32,0.25)] backdrop-blur">
      <div>
        <div className="flex items-baseline gap-2">
          <span className="text-[26px] font-semibold text-ink">
            {room ? currency.format(nightlyPrice) : "—"}
          </span>
          <span className="text-[12.5px] text-muted-foreground">/ night</span>
        </div>
        {room ? (<p className="mt-1 text-[12.5px] text-muted-foreground">{room.type}</p>) : (<p className="mt-1 text-[12.5px] text-muted-foreground">Select a room to see the total</p>)}
      </div>

      <div className="grid grid-cols-2 gap-2 rounded-xl border border-border/70 p-1">
        <div className="rounded-lg px-3 py-2">
          <div className="text-[10.5px] font-medium uppercase tracking-wider text-muted-foreground">Check-in</div>
          <div className="mt-0.5 inline-flex items-center gap-1.5 text-[13px] text-ink">
            <CalendarDays className="h-3.5 w-3.5 text-primary"/> {fmt(startDate)}
          </div>
        </div>
        <div className="rounded-lg px-3 py-2">
          <div className="text-[10.5px] font-medium uppercase tracking-wider text-muted-foreground">Check-out</div>
          <div className="mt-0.5 inline-flex items-center gap-1.5 text-[13px] text-ink">
            <CalendarDays className="h-3.5 w-3.5 text-primary"/> {fmt(endDate)}
          </div>
        </div>
      </div>

      <div className="flex items-center justify-between rounded-xl border border-border/70 px-3 py-2 text-[13px]">
        <span className="inline-flex items-center gap-1.5 text-ink">
          <Users className="h-3.5 w-3.5 text-primary"/> Rooms
        </span>
        <span className="font-medium text-ink">{roomsCount}</span>
      </div>

      {room && (<div className="space-y-1.5 border-t border-border/60 pt-3 text-[13px]">
          <div className="flex justify-between text-muted-foreground">
            <span>{currency.format(nightlyPrice)} × {n} night{n === 1 ? "" : "s"}</span>
            <span className="text-ink">{currency.format(total)}</span>
          </div>
          <div className="flex justify-between text-muted-foreground">
            <span>Taxes & fees</span>
            <span className="text-ink">{currency.format(taxes)}</span>
          </div>
          <div className="mt-2 flex justify-between border-t border-border/60 pt-3 text-[15px] font-semibold text-ink">
            <span>Total</span>
            <span>{currency.format(grand)}</span>
          </div>
        </div>)}

      <button type="button" onClick={onReserve} disabled={!room} className="w-full rounded-full bg-primary py-3 text-[13.5px] font-medium text-primary-foreground shadow-sm transition hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-50">
        <span className="inline-flex items-center justify-center gap-2">
          <BedDouble className="h-4 w-4"/>
          {room ? "Reserve now" : "Select a room"}
        </span>
      </button>

      <p className="inline-flex items-center gap-1.5 text-[11.5px] text-muted-foreground">
        <ShieldCheck className="h-3.5 w-3.5 text-primary/70"/>
        Free cancellation up to 48h before check-in
      </p>
    </aside>);
}
