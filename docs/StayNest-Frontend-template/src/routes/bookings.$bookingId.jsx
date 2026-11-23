import { useNavigate, useParams, Link } from "react-router-dom";
import { useState, useEffect, useCallback } from "react";
import { motion } from "framer-motion";
import { toast } from "sonner";
import {
  ArrowLeft,
  BedDouble,
  Calendar,
  CheckCircle2,
  DoorOpen,
  Loader2,
  Mail,
  MapPin,
  Phone,
  Users,
  XCircle,
} from "lucide-react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { cancelBooking, getBooking } from "@/lib/api/bookings";
import { useHotelInfo } from "@/hooks/queries/useHotelDetails";
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

function fmtLong(iso) {
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    });
  } catch {
    return iso;
  }
}

function nightsBetween(a, b) {
  return Math.max(1, Math.round((new Date(b).getTime() - new Date(a).getTime()) / 86_400_000));
}

export default function BookingDetailPage() {
  const { bookingId } = useParams();
  const id = Number(bookingId);
  const navigate = useNavigate();

  const [confirmOpen, setConfirmOpen] = useState(false);
  const [b, setB] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [cancelling, setCancelling] = useState(false);

  const fetchBooking = useCallback(async () => {
    if (!Number.isFinite(id)) return;
    setIsLoading(true);
    try {
      const data = await getBooking(id);
      setB(data);
    } catch {
      setB(null);
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    fetchBooking();
  }, [fetchBooking]);

  const hotelInfoQ = useHotelInfo(b?.hotel.id ?? 0, {
    startDate: b?.checkInDate ?? "",
    endDate: b?.checkOutDate ?? "",
    roomsCount: b?.roomsCount ?? 1,
  });

  const handleCancel = async (e) => {
    e.preventDefault();
    setCancelling(true);
    try {
      await cancelBooking(id);
      toast.success("Booking cancelled");
      fetchBooking();
      setConfirmOpen(false);
    } catch (err) {
      const status = err?.status;
      if (status === 401 || status === 403) return;
      if (status === 409) {
        toast.error("This booking cannot be cancelled at this stage.");
      } else {
        toast.error(err?.message ?? "Failed to cancel booking. Please try again.");
      }
    } finally {
      setCancelling(false);
    }
  };

  return (
    <div className="min-h-screen bg-background">
      <Nav />
      <main className="mx-auto max-w-[1180px] px-4 pb-24 pt-24 sm:px-6 lg:px-10">
        <button
          type="button"
          onClick={() => navigate("/bookings")}
          className="inline-flex items-center gap-1.5 text-[12.5px] text-muted-foreground transition-colors hover:text-primary"
        >
          <ArrowLeft className="h-4 w-4" /> Back to bookings
        </button>

        {isLoading && (
          <div className="mt-16 grid place-items-center py-24 text-muted-foreground">
            <Loader2 className="h-6 w-6 animate-spin" />
          </div>
        )}

        {b && <BookingBody b={b} hotelInfo={hotelInfoQ.data} onCancelClick={() => setConfirmOpen(true)} />}
      </main>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel this booking?</AlertDialogTitle>
            <AlertDialogDescription>
              Cancelling will release your rooms and initiate any eligible refund per the property's policy.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancelling}>Keep booking</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleCancel}
              disabled={cancelling}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {cancelling ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Yes, cancel
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <Footer />
    </div>
  );
}

function BookingBody({ b, hotelInfo, onCancelClick }) {
  const d = new Date();
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const todayStr = `${year}-${month}-${day}`;
  const displayStatus =
    b.bookingStatus === "CONFIRMED" && b.checkOutDate < todayStr
      ? "COMPLETED"
      : b.bookingStatus;
  const status = STATUS_STYLES[displayStatus] || {
    dot: "bg-gray-400",
    text: "text-gray-700",
    bg: "bg-gray-100",
    label: displayStatus,
  };
  const hasStarted = b.checkInDate <= todayStr;
  const canCancel = CANCELLABLE.includes(b.bookingStatus) && !hasStarted && displayStatus !== "COMPLETED";
  const nights = nightsBetween(b.checkInDate, b.checkOutDate);
  const contact = hotelInfo?.hotel.contactInfo;
  const amenities = hotelInfo?.hotel.amenities ?? [];

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
      className="mt-6"
    >
      <div className="overflow-hidden rounded-3xl border border-border/60 bg-white shadow-[0_1px_2px_rgba(17,26,19,0.04)]">
        <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
          <div className="relative aspect-[16/10] md:aspect-auto md:min-h-[340px]">
            <img src={b.hotel.photos[0]} alt={b.hotel.name} className="absolute inset-0 h-full w-full object-cover" />
          </div>
          <div className="flex flex-col p-6 md:p-8">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
                  Booking #BK{b.id}
                </p>
                <h1 className="mt-2 font-display text-3xl leading-tight text-primary md:text-4xl">
                  {b.hotel.name}
                </h1>
                <p className="mt-2 inline-flex items-center gap-1.5 text-[13px] text-muted-foreground">
                  <MapPin className="h-3.5 w-3.5" /> {b.hotel.city}
                  {b.hotel.country ? `, ${b.hotel.country}` : ""}
                </p>
              </div>
              <span
                className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-semibold tracking-wide ${status.bg} ${status.text}`}
              >
                <span className={`h-1.5 w-1.5 rounded-full ${status.dot}`} />
                {status.label}
              </span>
            </div>

            <div className="mt-6 grid grid-cols-2 gap-4">
              <Field icon={Calendar} label="Check-in" value={fmtLong(b.checkInDate)} />
              <Field icon={Calendar} label="Check-out" value={fmtLong(b.checkOutDate)} />
              <Field icon={BedDouble} label="Room" value={b.room.type} />
              <Field
                icon={DoorOpen}
                label="Rooms"
                value={`${b.roomsCount} · ${nights} night${nights === 1 ? "" : "s"}`}
              />
            </div>

            <div className="mt-auto flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-6">
              <div>
                <div className="text-[11px] uppercase tracking-wide text-muted-foreground">Total amount</div>
                <div className="font-display text-3xl leading-none text-primary">
                  ₹{b.amount.toLocaleString(undefined, { maximumFractionDigits: 2 })}
                </div>
                {typeof b.refundAmount === "number" && b.refundAmount > 0 && (
                  <div className="mt-1 text-[11.5px] text-muted-foreground">
                    Refund issued: <span className="text-ink">₹{b.refundAmount.toLocaleString()}</span>
                  </div>
                )}
              </div>
              <div className="flex gap-2">
                <Link
                  to={`/hotels/${b.hotel.id}?checkIn=${b.checkInDate}&checkOut=${b.checkOutDate}&rooms=${b.roomsCount}`}
                  className="inline-flex items-center rounded-xl border border-border/60 bg-white px-4 py-2.5 text-[12.5px] font-medium text-ink hover:border-primary/40"
                >
                  View hotel
                </Link>
                {canCancel && (
                  <button
                    type="button"
                    onClick={onCancelClick}
                    className="inline-flex items-center gap-1.5 rounded-xl bg-destructive px-4 py-2.5 text-[12.5px] font-medium text-destructive-foreground shadow-sm transition-all hover:bg-destructive/90"
                  >
                    <XCircle className="h-4 w-4" /> Cancel booking
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div className="space-y-6">
          <section className="rounded-2xl border border-border/60 bg-white p-6">
            <h2 className="flex items-center gap-2 font-display text-xl text-primary">
              <Users className="h-4 w-4" /> Guests
            </h2>
            {b.guests.length === 0 ? (
              <p className="mt-3 text-[13px] text-muted-foreground">No guests added.</p>
            ) : (
              <ul className="mt-4 divide-y divide-border/60">
                {b.guests.map((g) => (
                  <li key={g.id ?? g.name} className="flex items-center justify-between py-3">
                    <div className="flex items-center gap-3">
                      <span className="grid h-9 w-9 place-items-center rounded-full bg-primary/10 text-[13px] font-semibold text-primary">
                        {g.name.charAt(0)}
                      </span>
                      <div>
                        <div className="text-[13.5px] font-medium text-ink">{g.name}</div>
                        <div className="text-[11.5px] text-muted-foreground">
                          {g.gender ?? ""}{g.age ? ` · ${g.age} yrs` : ""}
                        </div>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>

          {amenities.length > 0 && (
            <section className="rounded-2xl border border-border/60 bg-white p-6">
              <h2 className="font-display text-xl text-primary">Hotel amenities</h2>
              <div className="mt-4 flex flex-wrap gap-2">
                {amenities.map((a) => (
                  <span
                    key={a}
                    className="inline-flex items-center rounded-full border border-border/60 bg-background px-3 py-1 text-[12px] text-ink"
                  >
                    {a}
                  </span>
                ))}
              </div>
            </section>
          )}
        </div>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-border/60 bg-white p-6">
            <h2 className="font-display text-xl text-primary">Booking timeline</h2>
            <ul className="mt-4 space-y-3 text-[12.5px]">
              <li className="flex items-start gap-2">
                <CheckCircle2 className="mt-0.5 h-4 w-4 text-primary" />
                <div>
                  <div className="font-medium text-ink">Booked</div>
                  <div className="text-muted-foreground">{fmtLong(b.createdAt)}</div>
                </div>
              </li>
              <li className="flex items-start gap-2">
                <CheckCircle2
                  className={`mt-0.5 h-4 w-4 ${b.bookingStatus === "CANCELLED" ? "text-destructive" : "text-primary"}`}
                />
                <div>
                  <div className="font-medium text-ink">
                    {b.bookingStatus === "CANCELLED" ? "Cancelled" : "Last updated"}
                  </div>
                  <div className="text-muted-foreground">{fmtLong(b.updatedAt)}</div>
                </div>
              </li>
            </ul>
          </section>

          {contact && (
            <section className="rounded-2xl border border-border/60 bg-white p-6">
              <h2 className="font-display text-xl text-primary">Property contact</h2>
              <ul className="mt-4 space-y-3 text-[12.5px] text-ink">
                {contact.address && (
                  <li className="flex items-start gap-2">
                    <MapPin className="mt-0.5 h-4 w-4 text-primary" />
                    {contact.address}
                  </li>
                )}
                {contact.phoneNumber && (
                  <li className="flex items-start gap-2">
                    <Phone className="mt-0.5 h-4 w-4 text-primary" />
                    {contact.phoneNumber}
                  </li>
                )}
                {contact.email && (
                  <li className="flex items-start gap-2">
                    <Mail className="mt-0.5 h-4 w-4 text-primary" />
                    {contact.email}
                  </li>
                )}
              </ul>
            </section>
          )}
        </aside>
      </div>
    </motion.div>
  );
}

function Field({ icon: Icon, label, value }) {
  return (
    <div>
      <div className="flex items-center gap-1.5 text-[11px] uppercase tracking-wider text-muted-foreground">
        <Icon className="h-3.5 w-3.5" /> {label}
      </div>
      <div className="mt-1 text-[13.5px] font-semibold text-ink">{value}</div>
    </div>
  );
}
