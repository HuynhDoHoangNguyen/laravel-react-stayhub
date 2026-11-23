import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { CalendarRange, Loader2, Search } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, } from "@/components/ui/select";
import { useManagerBookings, useManagerHotels, } from "@/hooks/queries/manager";
const STATUS_STYLES = {
    CONFIRMED: "bg-primary/10 text-primary",
    COMPLETED: "bg-surface text-ink",
    CANCELLED: "bg-destructive/10 text-destructive",
    PENDING: "bg-accent/15 text-accent-foreground",
    FAILED: "bg-destructive/10 text-destructive",
};
export default function BookingsPage() {
    const { data: hotelsPage, isLoading: hotelsLoading } = useManagerHotels();
    const hotels = hotelsPage?.content ?? [];
    const [hotelId, setHotelId] = useState("");
    const [status, setStatus] = useState("ALL");
    const [q, setQ] = useState("");
    const selected = Number(hotelId || hotels[0]?.id || 0);
    const { data, isLoading } = useManagerBookings(selected, { page: 0, size: 50 });
    const rows = useMemo(() => {
        const list = data?.content ?? [];
        return list
            .filter((b) => (status === "ALL" ? true : b.bookingStatus === status))
            .filter((b) => q ? String(b.id).includes(q) || (b.room?.type ?? "").toLowerCase().includes(q.toLowerCase()) : true);
    }, [data, status, q]);
    return (<div className="mx-auto max-w-[1200px] space-y-6">
      <header>
        <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
          Manager · Reservations
        </p>
        <h1 className="mt-1 font-display text-3xl text-primary">All bookings</h1>
        <p className="mt-1 text-[14px] text-muted-foreground">
          Reservations across your portfolio. Filter by property, status or booking ID.
        </p>
      </header>

      <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-border/60 bg-white p-3">
        <div className="w-64">
          <Select value={hotelId || String(hotels[0]?.id ?? "")} onValueChange={setHotelId}>
            <SelectTrigger className="h-10 rounded-xl">
              <SelectValue placeholder="Choose hotel"/>
            </SelectTrigger>
            <SelectContent>
              {hotels.map((h) => (<SelectItem key={h.id} value={String(h.id)}>
                  {h.name}
                </SelectItem>))}
            </SelectContent>
          </Select>
        </div>
        <div className="w-44">
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger className="h-10 rounded-xl">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="ALL">All statuses</SelectItem>
              <SelectItem value="CONFIRMED">Confirmed</SelectItem>
              <SelectItem value="COMPLETED">Completed</SelectItem>
              <SelectItem value="CANCELLED">Cancelled</SelectItem>
              <SelectItem value="PENDING">Pending</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="relative min-w-[220px] flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"/>
          <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search by ID or room type" className="h-10 rounded-xl pl-9"/>
        </div>
        {selected ? (<Link to={`/manage/hotels/${selected}`} className="text-[13px] font-medium text-primary hover:underline">
            Open workspace →
          </Link>) : null}
      </div>

      {(isLoading || hotelsLoading) && (<div className="grid place-items-center py-16 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin"/>
        </div>)}

      {!isLoading && rows.length === 0 && (<div className="rounded-3xl border border-dashed border-border bg-surface/40 p-12 text-center">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary/[0.06]">
            <CalendarRange className="h-5 w-5 text-primary/70"/>
          </div>
          <h3 className="mt-4 font-display text-xl text-primary">No bookings</h3>
          <p className="mx-auto mt-1 max-w-md text-[13px] text-muted-foreground">
            Nothing matches your current filters.
          </p>
        </div>)}

      {rows.length > 0 && (<div className="overflow-hidden rounded-2xl border border-border/60 bg-white">
          <table className="w-full text-left text-[13.5px]">
            <thead className="bg-surface/60 text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
              <tr>
                <th className="px-4 py-3">Booking</th>
                <th className="px-4 py-3">Dates</th>
                <th className="px-4 py-3">Guests</th>
                <th className="px-4 py-3">Amount</th>
                <th className="px-4 py-3">Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((b) => (<Row key={b.id} b={b}/>))}
            </tbody>
          </table>
        </div>)}
    </div>);
}
function Row({ b }) {
    const cls = STATUS_STYLES[b.bookingStatus] ?? "bg-surface text-ink";
    return (<tr className="border-t border-border/60">
      <td className="px-4 py-3">
        <div className="font-medium text-ink">#{b.id}</div>
        <div className="text-[12px] text-muted-foreground">{b.room?.type ?? "—"}</div>
      </td>
      <td className="px-4 py-3">
        <div>{b.checkInDate}</div>
        <div className="text-[12px] text-muted-foreground">→ {b.checkOutDate}</div>
      </td>
      <td className="px-4 py-3">
        {b.guests?.length ? `${b.guests.length} guest${b.guests.length > 1 ? "s" : ""}` : "—"}
      </td>
      <td className="px-4 py-3 font-medium">₹{b.amount.toFixed(2)}</td>
      <td className="px-4 py-3">
        <span className={`rounded-full px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.1em] ${cls}`}>
          {b.bookingStatus}
        </span>
      </td>
    </tr>);
}
