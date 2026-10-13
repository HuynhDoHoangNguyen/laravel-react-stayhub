import { useMemo, useState } from "react";
import { CalendarClock, ChevronLeft, ChevronRight, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, } from "@/components/ui/select";
import { useManagerBookings } from "@/hooks/queries/manager";
const STATUS_STYLES = {
    CONFIRMED: "bg-primary/10 text-primary",
    COMPLETED: "bg-surface text-ink",
    CANCELLED: "bg-destructive/10 text-destructive",
    RESERVED: "bg-accent/15 text-accent-foreground",
    GUESTS_ADDED: "bg-accent/15 text-accent-foreground",
    PAYMENTS_PENDING: "bg-accent/15 text-accent-foreground",
    PENDING: "bg-accent/15 text-accent-foreground",
    FAILED: "bg-destructive/10 text-destructive",
};
export function BookingsTab({ hotelId }) {
    const [status, setStatus] = useState("ALL");
    const [page, setPage] = useState(0);
    const size = 20;
    const { data, isLoading, isError, refetch } = useManagerBookings(hotelId, { page, size });
    const rows = useMemo(() => {
        const list = data?.content ?? [];
        return status === "ALL" ? list : list.filter((b) => b.bookingStatus === status);
    }, [data, status]);
    return (<div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-xl text-primary">Bookings</h2>
          <p className="text-[13px] text-muted-foreground">
            {data?.totalElements ?? 0} total reservations across all statuses.
          </p>
        </div>
        <div className="w-52">
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
      </div>

      {isLoading && (<div className="grid place-items-center py-16 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin"/>
        </div>)}
      {isError && (<div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-6 text-center">
          <p>Unable to load bookings.</p>
          <Button className="mt-3 rounded-xl" onClick={() => refetch()}>
            Retry
          </Button>
        </div>)}

      {!isLoading && !isError && rows.length === 0 && (<div className="rounded-3xl border border-dashed border-border bg-surface/40 p-12 text-center">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary/[0.06]">
            <CalendarClock className="h-5 w-5 text-primary/70"/>
          </div>
          <h3 className="mt-4 font-display text-xl text-primary">No bookings</h3>
          <p className="mx-auto mt-1 max-w-md text-[13px] text-muted-foreground">
            When guests book your property, their reservations will appear here.
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
              {rows.map((b) => (<BookingRow key={b.id} booking={b}/>))}
            </tbody>
          </table>
        </div>)}

      {data && data.totalPages > 1 && (<div className="flex items-center justify-center gap-3">
          <Button variant="outline" className="rounded-xl" disabled={page === 0} onClick={() => setPage((p) => Math.max(0, p - 1))}>
            <ChevronLeft className="h-4 w-4"/> Prev
          </Button>
          <span className="text-[13px] text-muted-foreground">
            Page {page + 1} of {data.totalPages}
          </span>
          <Button variant="outline" className="rounded-xl" disabled={page + 1 >= data.totalPages} onClick={() => setPage((p) => p + 1)}>
            Next <ChevronRight className="h-4 w-4"/>
          </Button>
        </div>)}
    </div>);
}
function BookingRow({ booking: b }) {
    const statusClass = STATUS_STYLES[b.bookingStatus] ?? "bg-surface text-ink";
    return (<tr className="border-t border-border/60">
      <td className="px-4 py-3">
        <div className="font-medium text-ink">#{b.id}</div>
        <div className="text-[12px] text-muted-foreground">
          {b.room?.type ?? "—"}
        </div>
      </td>
      <td className="px-4 py-3">
        <div>{b.checkInDate}</div>
        <div className="text-[12px] text-muted-foreground">→ {b.checkOutDate}</div>
      </td>
      <td className="px-4 py-3">
        {b.guests?.length ? (<div className="flex flex-wrap gap-1">
            {b.guests.slice(0, 3).map((g, i) => (<span key={i} className="rounded-full bg-surface px-2 py-0.5 text-[11.5px] text-ink">
                {g.name}
              </span>))}
            {b.guests.length > 3 && (<span className="text-[11.5px] text-muted-foreground">
                +{b.guests.length - 3}
              </span>)}
          </div>) : (<span className="text-muted-foreground">—</span>)}
      </td>
      <td className="px-4 py-3 font-medium">₹{b.amount.toFixed(2)}</td>
      <td className="px-4 py-3">
        <span className={`rounded-full px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.1em] ${statusClass}`}>
          {b.bookingStatus}
        </span>
      </td>
    </tr>);
}
