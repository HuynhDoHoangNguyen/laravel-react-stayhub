import { useNavigate, useParams, Link } from "react-router-dom";
import { useMemo, useState, useEffect } from "react";
import {
  ArrowLeft,
  BedDouble,
  CalendarDays,
  CheckCircle2,
  Loader2,
  MapPin,
  Plus,
  ShieldCheck,
  Users,
} from "lucide-react";
import { toast } from "sonner";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { GuestFormDialog } from "@/components/guests/GuestFormDialog";
import { addBookingGuests, getBooking, initiatePayment } from "@/lib/api/bookings";
import { useGuests } from "@/hooks/queries/useGuests";

const currency = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 });

function fmtDate(iso) {
  try {
    return new Date(iso).toLocaleDateString(undefined, { weekday: "short", day: "numeric", month: "short", year: "numeric" });
  } catch {
    return iso;
  }
}

export default function CheckoutPage() {
  const { bookingId } = useParams();
  const id = Number(bookingId);
  const navigate = useNavigate();

  const [step, setStep] = useState("guests");
  const [selectedIds, setSelectedIds] = useState([]);
  const [guestDialogOpen, setGuestDialogOpen] = useState(false);
  const [booking, setBooking] = useState(null);
  const [bookingLoading, setBookingLoading] = useState(true);
  const [addingGuests, setAddingGuests] = useState(false);
  const [paying, setPaying] = useState(false);

  useEffect(() => {
    if (!Number.isFinite(id)) return;
    setBookingLoading(true);
    getBooking(id)
      .then(setBooking)
      .catch(() => {})
      .finally(() => setBookingLoading(false));
  }, [id]);

  const guestsQ = useGuests({ page: 0, size: 50 });
  const guests = guestsQ.data?.content ?? [];

  const handleAddGuests = async () => {
    setAddingGuests(true);
    try {
      await addBookingGuests(id, selectedIds);
      setStep("review");
    } catch (e) {
      if (e?.status === 401 || e?.status === 403) return;
      toast.error(e?.message ?? "Couldn't save companions. Please try again.");
    } finally {
      setAddingGuests(false);
    }
  };

  const handlePay = async () => {
    setPaying(true);
    try {
      const res = await initiatePayment(id);
      window.location.href = res.sessionUrl;
    } catch (e) {
      if (e?.status === 401 || e?.status === 403) return;
      if (e?.status === 410) {
        toast.error("This booking has expired. Please start a new reservation.");
      } else {
        toast.error(e?.message ?? "Payment could not be initiated. Please try again.");
      }
    } finally {
      setPaying(false);
    }
  };

  const selectedGuests = useMemo(() => guests.filter((g) => selectedIds.includes(g.id)), [guests, selectedIds]);
  const nights = booking
    ? Math.max(1, Math.round((new Date(booking.checkOutDate).getTime() - new Date(booking.checkInDate).getTime()) / 86400000))
    : 0;

  const toggleGuest = (gid) =>
    setSelectedIds((prev) => (prev.includes(gid) ? prev.filter((x) => x !== gid) : [...prev, gid]));

  if (bookingLoading || !booking) {
    return (
      <div className="min-h-screen bg-background">
        <Nav />
        <div className="grid min-h-[60vh] place-items-center text-muted-foreground">
          <Loader2 className="h-6 w-6 animate-spin" />
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background">
      <Nav />

      <main className="mx-auto max-w-[1200px] px-4 pt-24 pb-16 md:px-8">
        <button
          type="button"
          onClick={() => {
            if (booking?.hotel?.id) {
              const checkIn = booking.checkInDate || "";
              const checkOut = booking.checkOutDate || "";
              const rooms = booking.roomsCount || 1;
              navigate(`/hotels/${booking.hotel.id}?checkIn=${checkIn}&checkOut=${checkOut}&rooms=${rooms}`);
            } else {
              navigate(-1);
            }
          }}
          className="mb-6 inline-flex items-center gap-1.5 text-[12.5px] font-medium text-muted-foreground transition-colors hover:text-primary"
        >
          <ArrowLeft className="h-3.5 w-3.5" /> Back to stay
        </button>

        <div className="flex items-baseline justify-between gap-4">
          <div>
            <h1 className="font-display text-3xl text-primary md:text-4xl">Complete your booking</h1>
            <p className="mt-1 text-[13.5px] text-muted-foreground">Booking reference #BK{booking.id}</p>
          </div>
          <StepDots step={step} />
        </div>

        <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[1fr_380px]">
          <section className="space-y-6">
            {step === "guests" ? (
              <GuestsStep
                guests={guests}
                selectedIds={selectedIds}
                onToggle={toggleGuest}
                onAdd={() => setGuestDialogOpen(true)}
                onContinue={handleAddGuests}
                canContinue={selectedIds.length > 0 && !addingGuests}
                pending={addingGuests}
              />
            ) : (
              <ReviewStep
                booking={booking}
                guests={selectedGuests}
                onEditGuests={() => setStep("guests")}
                onPay={handlePay}
                paying={paying}
              />
            )}
          </section>

          <aside className="lg:sticky lg:top-24 lg:h-fit">
            <Summary booking={booking} nights={nights} />
          </aside>
        </div>
      </main>

      <GuestFormDialog open={guestDialogOpen} onOpenChange={setGuestDialogOpen} />

      <Footer />
    </div>
  );
}

function StepDots({ step }) {
  const items = [
    { key: "guests", label: "Guests" },
    { key: "review", label: "Review & Pay" },
  ];
  const activeIdx = items.findIndex((i) => i.key === step);
  return (
    <div className="hidden items-center gap-3 md:flex">
      {items.map((it, i) => {
        const active = i === activeIdx;
        const done = i < activeIdx;
        return (
          <div key={it.key} className="flex items-center gap-3">
            <div
              className={[
                "grid h-7 w-7 place-items-center rounded-full text-[11px] font-medium transition-colors",
                done
                  ? "bg-primary text-primary-foreground"
                  : active
                  ? "border border-primary/50 text-primary"
                  : "border border-border/70 text-muted-foreground",
              ].join(" ")}
            >
              {done ? <CheckCircle2 className="h-3.5 w-3.5" /> : i + 1}
            </div>
            <span className={active ? "text-[13px] text-primary" : "text-[13px] text-muted-foreground"}>
              {it.label}
            </span>
            {i < items.length - 1 && <span className="h-px w-8 bg-border/70" />}
          </div>
        );
      })}
    </div>
  );
}

function GuestsStep({ guests, selectedIds, onToggle, onAdd, onContinue, canContinue, pending }) {
  return (
    <div className="rounded-2xl border border-border/70 bg-background p-6 md:p-8">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="font-display text-xl text-primary md:text-2xl">Who's staying?</h2>
          <p className="mt-1 text-[13px] text-muted-foreground">
            Select the companions joining you. You can add new guests to your address book.
          </p>
        </div>
        <button
          type="button"
          onClick={onAdd}
          className="inline-flex items-center gap-1.5 rounded-full border border-primary/40 px-3.5 py-1.5 text-[12.5px] font-medium text-primary transition hover:bg-primary hover:text-primary-foreground"
        >
          <Plus className="h-3.5 w-3.5" /> Add guest
        </button>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
        {guests.length === 0 && (
          <p className="col-span-full rounded-xl border border-dashed border-border/70 p-8 text-center text-[13px] text-muted-foreground">
            You haven't saved any companions yet. Add one to continue.
          </p>
        )}
        {guests.map((g) => {
          const selected = selectedIds.includes(g.id);
          return (
            <button
              type="button"
              key={g.id}
              onClick={() => onToggle(g.id)}
              className={[
                "flex items-center gap-3 rounded-xl border p-4 text-left transition-all",
                selected ? "border-primary bg-primary/5" : "border-border/70 hover:border-primary/40",
              ].join(" ")}
            >
              <span
                className={[
                  "grid h-9 w-9 place-items-center rounded-full text-[13px] font-semibold",
                  selected ? "bg-primary text-primary-foreground" : "bg-muted text-ink",
                ].join(" ")}
              >
                {g.name.charAt(0)}
              </span>
              <div className="min-w-0 flex-1">
                <div className="truncate text-[14px] font-medium text-ink">{g.name}</div>
                <div className="text-[12px] text-muted-foreground">
                  {g.gender?.toLowerCase()} · DOB {g.dateOfBirth}
                </div>
              </div>
              <div
                className={[
                  "grid h-5 w-5 place-items-center rounded-full border transition-colors",
                  selected ? "border-primary bg-primary text-primary-foreground" : "border-border/70",
                ].join(" ")}
              >
                {selected && <CheckCircle2 className="h-3.5 w-3.5" />}
              </div>
            </button>
          );
        })}
      </div>

      <div className="mt-6 flex items-center justify-between">
        <span className="text-[12.5px] text-muted-foreground">{selectedIds.length} selected</span>
        <button
          type="button"
          onClick={onContinue}
          disabled={!canContinue}
          className="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-[13.5px] font-medium text-primary-foreground transition hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-50"
        >
          {pending && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
          Continue to review
        </button>
      </div>
    </div>
  );
}

function ReviewStep({ booking, guests, onEditGuests, onPay, paying }) {
  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-border/70 bg-background p-6 md:p-8">
        <h2 className="font-display text-xl text-primary md:text-2xl">Review your stay</h2>

        <div className="mt-5 flex gap-4">
          <img
            src={booking.hotel.photos?.[0] ?? "/placeholder.svg"}
            alt={booking.hotel.name}
            className="h-24 w-24 flex-none rounded-xl object-cover md:h-28 md:w-28"
          />
          <div className="min-w-0">
            <div className="font-display text-lg text-primary">{booking.hotel.name}</div>
            <div className="mt-1 inline-flex items-center gap-1.5 text-[12.5px] text-muted-foreground">
              <MapPin className="h-3.5 w-3.5" /> {booking.hotel.city}
              {booking.hotel.country ? `, ${booking.hotel.country}` : ""}
            </div>
            <div className="mt-2 inline-flex items-center gap-1.5 text-[13px] text-ink">
              <BedDouble className="h-3.5 w-3.5 text-primary" /> {booking.room.type}
            </div>
          </div>
        </div>

        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <InfoBlock icon={<CalendarDays className="h-3.5 w-3.5 text-primary" />} label="Check-in" value={fmtDate(booking.checkInDate)} />
          <InfoBlock icon={<CalendarDays className="h-3.5 w-3.5 text-primary" />} label="Check-out" value={fmtDate(booking.checkOutDate)} />
        </div>
      </div>

      <div className="rounded-2xl border border-border/70 bg-background p-6 md:p-8">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h3 className="font-display text-lg text-primary">Guests</h3>
            <p className="mt-1 text-[12.5px] text-muted-foreground">
              {guests.length} companion{guests.length === 1 ? "" : "s"}
            </p>
          </div>
          <button type="button" onClick={onEditGuests} className="text-[12.5px] font-medium text-primary hover:underline">
            Edit
          </button>
        </div>
        <ul className="mt-4 divide-y divide-border/60">
          {guests.map((g) => (
            <li key={g.id} className="flex items-center gap-3 py-3">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-muted text-[12px] font-semibold text-ink">
                {g.name.charAt(0)}
              </span>
              <div className="min-w-0">
                <div className="truncate text-[13.5px] text-ink">{g.name}</div>
                <div className="text-[12px] text-muted-foreground">
                  {g.gender?.toLowerCase()} · DOB {g.dateOfBirth}
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>

      <button
        type="button"
        onClick={onPay}
        disabled={paying}
        className="flex w-full items-center justify-center gap-2 rounded-full bg-primary py-3.5 text-[14px] font-medium text-primary-foreground shadow-sm transition hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-60"
      >
        {paying ? <Loader2 className="h-4 w-4 animate-spin" /> : <ShieldCheck className="h-4 w-4" />}
        {paying ? "Redirecting to secure payment…" : `Pay ${currency.format(booking.amount)} with Stripe`}
      </button>
      <p className="text-center text-[11.5px] text-muted-foreground">
        You'll be taken to Stripe to complete payment securely. Free cancellation up to 48h before check-in.
      </p>
    </div>
  );
}

function InfoBlock({ icon, label, value }) {
  return (
    <div className="rounded-xl border border-border/70 px-4 py-3">
      <div className="text-[10.5px] font-medium uppercase tracking-wider text-muted-foreground">{label}</div>
      <div className="mt-1 inline-flex items-center gap-1.5 text-[13.5px] text-ink">
        {icon} {value}
      </div>
    </div>
  );
}

function Summary({ booking, nights }) {
  const nightly = Math.round(booking.amount / Math.max(nights, 1));
  const subtotal = nightly * nights;
  const taxes = booking.amount - subtotal;
  return (
    <div className="rounded-2xl border border-border/70 bg-background/95 p-6 shadow-[0_20px_50px_-24px_rgba(26,46,32,0.25)]">
      <div className="font-display text-lg text-primary">Price summary</div>

      <div className="mt-4 space-y-2 text-[13px] text-muted-foreground">
        <div className="flex justify-between">
          <span>
            {currency.format(nightly)} × {nights} night{nights === 1 ? "" : "s"}
          </span>
          <span className="text-ink">{currency.format(subtotal)}</span>
        </div>
        <div className="flex justify-between">
          <span>Rooms</span>
          <span className="text-ink">{booking.roomsCount}</span>
        </div>
        {taxes !== 0 && (
          <div className="flex justify-between">
            <span>Taxes & fees</span>
            <span className="text-ink">{currency.format(Math.max(0, taxes))}</span>
          </div>
        )}
      </div>

      <div className="mt-4 flex justify-between border-t border-border/60 pt-4 text-[15px] font-semibold text-ink">
        <span>Total</span>
        <span>{currency.format(booking.amount)}</span>
      </div>

      <div className="mt-4 flex items-center gap-2 rounded-xl bg-primary/5 px-3 py-2.5 text-[12px] text-primary">
        <ShieldCheck className="h-3.5 w-3.5" /> Best-price promise · Secure Stripe checkout
      </div>

      <Link to="/bookings" className="mt-4 inline-flex items-center gap-1.5 text-[12px] text-muted-foreground hover:text-primary">
        <Users className="h-3.5 w-3.5" /> View my bookings
      </Link>
    </div>
  );
}
