import { MapPin, Calendar, Users, DoorOpen, BedDouble, MoreVertical, Eye, XCircle, Loader2 } from "lucide-react";
import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import { useState } from "react";
import { toast } from "sonner";
import { cancelBooking } from "@/lib/api/bookings";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

const currency = new Intl.NumberFormat("en-IN", {
  style: "currency",
  currency: "INR",
  maximumFractionDigits: 0,
});

const STATUS_STYLES = {
  CONFIRMED: { dot: "bg-[#3a5a40]", text: "text-[#2c4632]", bg: "bg-[#e8efe4]", label: "CONFIRMED" },
  COMPLETED: { dot: "bg-[#3b6a8a]", text: "text-[#2b526d]", bg: "bg-[#e2ecf3]", label: "COMPLETED" },
  CANCELLED: { dot: "bg-[#b04a3a]", text: "text-[#8a3a2d]", bg: "bg-[#f7e3df]", label: "CANCELLED" },
  PENDING: { dot: "bg-[#b98a2b]", text: "text-[#8b6a24]", bg: "bg-[#f4ead1]", label: "PENDING" },
  RESERVED: { dot: "bg-[#b98a2b]", text: "text-[#8b6a24]", bg: "bg-[#f4ead1]", label: "RESERVED" },
  GUESTS_ADDED: { dot: "bg-[#b98a2b]", text: "text-[#8b6a24]", bg: "bg-[#f4ead1]", label: "AWAITING PAYMENT" },
  PAYMENTS_PENDING: { dot: "bg-[#b98a2b]", text: "text-[#8b6a24]", bg: "bg-[#f4ead1]", label: "PROCESSING" },
  FAILED: { dot: "bg-[#b04a3a]", text: "text-[#8a3a2d]", bg: "bg-[#f7e3df]", label: "FAILED" },
  EXPIRED: { dot: "bg-gray-400", text: "text-gray-700", bg: "bg-gray-100", label: "EXPIRED" },
  PAYMENT_FAILED: { dot: "bg-[#b04a3a]", text: "text-[#8a3a2d]", bg: "bg-[#f7e3df]", label: "PAYMENT FAILED" },
  REFUND_PENDING: { dot: "bg-[#b98a2b]", text: "text-[#8b6a24]", bg: "bg-[#f4ead1]", label: "REFUND PENDING" },
};
const CANCELLABLE = ["CONFIRMED", "PENDING", "RESERVED", "GUESTS_ADDED", "PAYMENTS_PENDING"];

function fmtDate(iso) {
  const d = new Date(iso);
  return {
    day: d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }),
    weekday: d.toLocaleDateString("en-GB", { weekday: "short" }),
  };
}

function fmtBookingId(id) {
  return `#BK${id}`;
}

function guestSummary(b) {
  const adults = b.guests.filter((g) => (g.age ?? 99) >= 18).length || b.guests.length;
  const kids = b.guests.length - adults;
  const parts = [`${adults} ${adults === 1 ? "Adult" : "Adults"}`];
  if (kids > 0) parts.push(`${kids} ${kids === 1 ? "Child" : "Children"}`);
  return parts.join(", ");
}

export function BookingCard({ booking, index, onRefresh }) {
  const d = new Date();
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const todayStr = `${year}-${month}-${day}`;
  const displayStatus =
    booking.bookingStatus === "CONFIRMED" && booking.checkOutDate < todayStr
      ? "COMPLETED"
      : booking.bookingStatus;
  const status = STATUS_STYLES[displayStatus] || {
    dot: "bg-gray-400",
    text: "text-gray-700",
    bg: "bg-gray-100",
    label: displayStatus,
  };
  const checkIn = fmtDate(booking.checkInDate);
  const checkOut = fmtDate(booking.checkOutDate);
  const booked = fmtDate(booking.createdAt);
  const photo = booking.hotel.photos[0];
  const primaryCta = booking.bookingStatus === "COMPLETED" ? "View Details" : "View Booking";
  const canCancel = CANCELLABLE.includes(booking.bookingStatus);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [cancelling, setCancelling] = useState(false);

  const handleCancel = async (e) => {
    e.preventDefault();
    setCancelling(true);
    try {
      await cancelBooking(booking.id);
      toast.success("Booking cancelled");
      if (onRefresh) onRefresh();
    } catch (err) {
      toast.error(err?.message ?? "Failed to cancel booking");
    } finally {
      setCancelling(false);
      setConfirmOpen(false);
    }
  };

  return (
    <motion.article
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay: index * 0.05, ease: [0.16, 1, 0.3, 1] }}
      className="group overflow-hidden rounded-2xl border border-border/60 bg-white shadow-[0_1px_2px_rgba(17,26,19,0.04)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_18px_40px_-20px_rgba(17,26,19,0.28)]"
    >
      <div className="flex flex-col gap-0 md:flex-row">
        <Link to={`/bookings/${booking.id}`} className="relative block h-52 w-full shrink-0 overflow-hidden md:h-auto md:w-[240px]">
          <img src={photo} alt={booking.hotel.name} loading="lazy" className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.03]"/>
        </Link>

        <div className="flex flex-1 flex-col p-5">
          <header className="flex items-start justify-between gap-4">
            <Link to={`/bookings/${booking.id}`} className="min-w-0 flex-1">
              <h3 className="truncate font-display text-xl leading-tight text-primary hover:underline">
                {booking.hotel.name}
              </h3>
              <p className="mt-1 flex items-center gap-1.5 text-[12.5px] text-muted-foreground">
                <MapPin className="h-3.5 w-3.5" aria-hidden/>
                {booking.hotel.city}
                {booking.hotel.country ? `, ${booking.hotel.country}` : ""}
              </p>
            </Link>
            <div className="flex shrink-0 items-center gap-2">
              <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10.5px] font-semibold tracking-wide ${status.bg} ${status.text}`}>
                <span className={`h-1.5 w-1.5 rounded-full ${status.dot}`} aria-hidden/>
                {status.label}
              </span>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button type="button" aria-label="More actions" className="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-primary/[0.06] hover:text-primary">
                    <MoreVertical className="h-4 w-4" aria-hidden/>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                  <DropdownMenuItem asChild>
                    <Link to={`/bookings/${booking.id}`} className="cursor-pointer">
                      <Eye className="mr-2 h-4 w-4"/> View details
                    </Link>
                  </DropdownMenuItem>
                  {canCancel && (
                    <>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem className="cursor-pointer text-destructive focus:text-destructive" onSelect={(e) => {
                        e.preventDefault();
                        setConfirmOpen(true);
                      }}>
                        <XCircle className="mr-2 h-4 w-4"/> Cancel booking
                      </DropdownMenuItem>
                    </>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </header>

          <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-5">
            <InfoCell icon={Calendar} label="Check-in" value={checkIn.day} sub={checkIn.weekday}/>
            <InfoCell icon={Calendar} label="Check-out" value={checkOut.day} sub={checkOut.weekday}/>
            <InfoCell icon={Users} label="Guests" value={guestSummary(booking)}/>
            <InfoCell icon={DoorOpen} label="Rooms" value={`${booking.roomsCount} ${booking.roomsCount === 1 ? "Room" : "Rooms"}`}/>
            <InfoCell icon={BedDouble} label="Room Type" value={booking.room.type}/>
          </div>

          <div className="mt-4 border-t border-border/60 pt-4">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div className="flex flex-wrap gap-x-8 gap-y-2 text-[11.5px]">
                <div>
                  <div className="text-muted-foreground">Booking ID</div>
                  <div className="mt-0.5 font-mono text-[12px] text-ink">{fmtBookingId(booking.id)}</div>
                </div>
                <div>
                  <div className="text-muted-foreground">Booked on</div>
                  <div className="mt-0.5 text-[12px] text-ink">{booked.day}</div>
                </div>
              </div>

              <div className="flex items-end gap-4">
                <div className="text-right">
                  <div className="text-[11px] uppercase tracking-wide text-muted-foreground">Total Amount</div>
                  <div className="font-display text-2xl leading-none text-primary">
                    {currency.format(booking.amount)}
                  </div>
                </div>
                <Link to={`/bookings/${booking.id}`} className="inline-flex items-center rounded-xl bg-primary px-4 py-2.5 text-[12.5px] font-medium text-primary-foreground shadow-sm transition-all duration-200 hover:bg-primary-mid hover:shadow-[0_8px_20px_-10px_rgba(17,26,19,0.4)]">
                  {primaryCta}
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel this booking?</AlertDialogTitle>
            <AlertDialogDescription>
              You're about to cancel your stay at <span className="font-medium text-ink">{booking.hotel.name}</span>. Refund eligibility depends on the property's cancellation policy.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancelling}>Keep booking</AlertDialogCancel>
            <AlertDialogAction onClick={handleCancel} disabled={cancelling} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              {cancelling ? <Loader2 className="mr-2 h-4 w-4 animate-spin"/> : null}
              Yes, cancel
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </motion.article>
  );
}

function InfoCell({ icon: Icon, label, value, sub }) {
  return (
    <div className="min-w-0">
      <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
        <Icon className="h-3.5 w-3.5" aria-hidden/>
        {label}
      </div>
      <div className="mt-1 truncate text-[13px] font-semibold text-ink">{value}</div>
      {sub && <div className="text-[11px] text-muted-foreground">{sub}</div>}
    </div>
  );
}
