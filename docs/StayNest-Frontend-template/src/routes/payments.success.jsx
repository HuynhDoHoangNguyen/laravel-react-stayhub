import { Link, useSearchParams } from "react-router-dom";
import { useEffect, useState, useCallback } from "react";
import { motion } from "framer-motion";
import { BedDouble, CalendarDays, CheckCircle2, Loader2, MapPin, Sparkles, AlertCircle, RefreshCw } from "lucide-react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { getBooking, getBookingStatus, verifyPayment } from "@/lib/api/bookings";

const currency = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 });

function fmtDate(iso) {
  try {
    return new Date(iso).toLocaleDateString(undefined, { weekday: "short", day: "numeric", month: "short", year: "numeric" });
  } catch {
    return iso;
  }
}

export default function SuccessPage() {
  const [searchParams] = useSearchParams();
  const bookingId = searchParams.get("bookingId") || "";
  const sessionId = searchParams.get("session_id") || "";
  const id = Number(bookingId);

  const [confirmed, setConfirmed] = useState(false);
  const [pollTime, setPollTime] = useState(0);
  const [timedOut, setTimedOut] = useState(false);
  const [bookingDetail, setBookingDetail] = useState(null);

  const checkStatus = useCallback(async () => {
    if (!Number.isFinite(id) || confirmed) return;
    try {
      const verified = await verifyPayment(id, sessionId);
      if (verified?.bookingStatus === "CONFIRMED" || verified?.bookingStatus === "COMPLETED") {
        setConfirmed(true);
        setTimedOut(false);
        setBookingDetail(verified);
        return;
      }
      const statusRes = await getBookingStatus(id);
      const status = statusRes?.bookingStatus;
      if (status === "CONFIRMED" || status === "COMPLETED") {
        setConfirmed(true);
        setTimedOut(false);
        const detail = await getBooking(id);
        setBookingDetail(detail);
      }
    } catch {
      // Keep trying silently
    }
  }, [id, sessionId, confirmed]);

  useEffect(() => {
    if (confirmed || !Number.isFinite(id)) return;
    checkStatus();
    const interval = setInterval(() => {
      checkStatus();
      setPollTime((prev) => {
        if (prev >= 120) {
          setTimedOut(true);
          return prev;
        }
        return prev + 2;
      });
    }, 2000);

    return () => clearInterval(interval);
  }, [checkStatus, confirmed, id]);

  const handleRetry = () => {
    setTimedOut(false);
    setPollTime(0);
    checkStatus();
  };

  return (
    <div className="min-h-screen bg-background">
      <Nav />
      <main className="mx-auto max-w-[900px] px-4 pt-24 pb-20 md:px-8">
        {!confirmed && !timedOut && (
          <div className="grid place-items-center py-24">
            <div className="text-center">
              <Loader2 className="mx-auto h-8 w-8 animate-spin text-primary" />
              <h1 className="mt-6 font-display text-2xl text-primary md:text-3xl">Confirming your booking…</h1>
              <p className="mt-2 text-[13.5px] text-muted-foreground">
                Payment received. We're finalising your reservation — hang tight.
              </p>
            </div>
          </div>
        )}

        {!confirmed && timedOut && (
          <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="mx-auto max-w-md rounded-3xl border border-destructive/20 bg-destructive/[0.02] p-8 text-center shadow-sm">
            <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-destructive/10 text-destructive">
              <AlertCircle className="h-6 w-6" />
            </div>
            <h2 className="mt-4 font-display text-xl text-primary">Confirmation delayed</h2>
            <p className="mt-3 text-[13.5px] leading-relaxed text-muted-foreground">
              We did not receive a confirmation from our booking system in time.
            </p>
            <p className="mt-2 text-[13px] font-medium text-destructive/80 leading-relaxed">
              If the transaction succeeded and money was deducted, it will be automatically refunded within 5 working days.
            </p>
            {Number.isFinite(id) && (
              <p className="mt-4 text-[12px] text-muted-foreground font-mono bg-surface/50 py-1.5 px-3 rounded-lg inline-block border border-border/40">
                Booking Ref: #BK{id}
              </p>
            )}

            <div className="mt-6 flex flex-col gap-2">
              <button type="button" onClick={handleRetry} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-[13.5px] font-medium text-primary-foreground transition hover:bg-primary-mid">
                <RefreshCw className="h-4 w-4" /> Check confirmation again
              </button>
              <Link to="/bookings" className="inline-flex h-11 items-center justify-center rounded-xl border border-border/70 bg-white px-5 text-[13.5px] font-medium text-ink transition hover:bg-surface/50">
                View my bookings
              </Link>
            </div>
          </motion.div>
        )}

        {confirmed && bookingDetail && (
          <>
            <motion.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }} className="mx-auto flex flex-col items-center text-center">
              <div className="grid h-16 w-16 place-items-center rounded-full bg-primary/10 text-primary">
                <CheckCircle2 className="h-9 w-9" />
              </div>
              <h1 className="mt-6 font-display text-3xl text-primary md:text-4xl">Your stay is confirmed</h1>
              <p className="mt-2 max-w-md text-[14px] text-muted-foreground">
                A confirmation has been sent to your email. Booking reference <span className="font-medium text-ink">#BK{bookingDetail.id}</span>.
              </p>
            </motion.div>

            <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5, delay: 0.15 }} className="mt-10 overflow-hidden rounded-3xl border border-border/70 bg-background shadow-[0_20px_50px_-24px_rgba(26,46,32,0.25)]">
              <div className="grid grid-cols-1 md:grid-cols-[280px_1fr]">
                <img src={bookingDetail.hotel.photos?.[0] ?? "/placeholder.svg"} alt={bookingDetail.hotel.name} className="aspect-[16/12] h-full w-full object-cover" />
                <div className="p-6 md:p-8">
                  <div className="font-display text-2xl text-primary">{bookingDetail.hotel.name}</div>
                  <div className="mt-1 inline-flex items-center gap-1.5 text-[13px] text-muted-foreground">
                    <MapPin className="h-3.5 w-3.5" /> {bookingDetail.hotel.city}
                    {bookingDetail.hotel.country ? `, ${bookingDetail.hotel.country}` : ""}
                  </div>
                  <div className="mt-3 inline-flex items-center gap-1.5 text-[13.5px] text-ink">
                    <BedDouble className="h-3.5 w-3.5 text-primary" /> {bookingDetail.room.type} · {bookingDetail.roomsCount} room{bookingDetail.roomsCount === 1 ? "" : "s"}
                  </div>

                  <div className="mt-5 grid grid-cols-2 gap-3">
                    <InfoTile label="Check-in" value={fmtDate(bookingDetail.checkInDate)} />
                    <InfoTile label="Check-out" value={fmtDate(bookingDetail.checkOutDate)} />
                  </div>

                  {bookingDetail.guests?.length > 0 && (
                    <div className="mt-5">
                      <div className="text-[10.5px] font-medium uppercase tracking-wider text-muted-foreground">Guests</div>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {bookingDetail.guests.map((g) => (
                          <span key={g.id} className="inline-flex items-center gap-1.5 rounded-full border border-border/70 px-3 py-1 text-[12.5px] text-ink">
                            <span className="grid h-5 w-5 place-items-center rounded-full bg-primary/10 text-[10.5px] font-semibold text-primary">
                              {g.name.charAt(0)}
                            </span>
                            {g.name}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </div>

              <div className="flex items-center justify-between border-t border-border/60 bg-primary/5 px-6 py-4 md:px-8">
                <div className="text-[12.5px] text-muted-foreground">Total paid</div>
                <div className="font-display text-xl text-primary">{currency.format(bookingDetail.amount)}</div>
              </div>
            </motion.div>

            <div className="mt-8 flex flex-wrap justify-center gap-3">
              <Link to="/bookings" className="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-[13.5px] font-medium text-primary-foreground transition hover:bg-primary-mid">
                View my bookings
              </Link>
              <Link to="/" className="inline-flex items-center gap-2 rounded-full border border-border/70 px-5 py-2.5 text-[13.5px] font-medium text-ink transition hover:border-primary/50">
                <Sparkles className="h-4 w-4 text-primary" /> Discover more stays
              </Link>
            </div>
          </>
        )}
      </main>
      <Footer />
    </div>
  );
}

function InfoTile({ label, value }) {
  return (
    <div className="rounded-xl border border-border/70 px-4 py-3">
      <div className="text-[10.5px] font-medium uppercase tracking-wider text-muted-foreground">{label}</div>
      <div className="mt-1 inline-flex items-center gap-1.5 text-[13px] text-ink">
        <CalendarDays className="h-3.5 w-3.5 text-primary" /> {value}
      </div>
    </div>
  );
}
