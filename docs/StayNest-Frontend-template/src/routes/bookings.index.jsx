import { Link } from "react-router-dom";
import { useMemo, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { ChevronDown, ChevronLeft, ChevronRight, Compass, Loader2, Sparkles } from "lucide-react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { BookingStats } from "@/components/bookings/BookingStats";
import { BookingCard } from "@/components/bookings/BookingCard";
import { QuickPicks } from "@/components/bookings/QuickPicks";
import { useMyBookings } from "@/hooks/queries/useMyBookings";
const TABS = [
    { key: "UPCOMING", label: "Upcoming" },
    { key: "COMPLETED", label: "Completed" },
    { key: "CANCELLED", label: "Cancelled" },
    { key: "ALL", label: "All Bookings" },
];
const SORT_LABEL = {
    NEW: "Newest First",
    OLD: "Oldest First",
    PRICE_DESC: "Highest Price",
    PRICE_ASC: "Lowest Price",
    RECENT: "Recently Booked",
};
const PAGE_SIZE = 5;
function filterByTab(list, tab) {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    const todayStr = `${year}-${month}-${day}`;
    switch (tab) {
        case "UPCOMING":
            return list.filter((b) => b.bookingStatus === "CONFIRMED" &&
                b.checkOutDate >= todayStr);
        case "COMPLETED":
            return list.filter((b) => b.bookingStatus === "COMPLETED" ||
                (b.bookingStatus === "CONFIRMED" &&
                    b.checkOutDate < todayStr));
        case "CANCELLED":
            return list.filter((b) => b.bookingStatus === "CANCELLED");
        case "ALL":
        default:
            return list;
    }
}
function sortBookings(list, sort) {
    const arr = [...list];
    switch (sort) {
        case "NEW":
            return arr.sort((a, b) => +new Date(b.checkInDate) - +new Date(a.checkInDate));
        case "OLD":
            return arr.sort((a, b) => +new Date(a.checkInDate) - +new Date(b.checkInDate));
        case "PRICE_DESC":
            return arr.sort((a, b) => b.amount - a.amount);
        case "PRICE_ASC":
            return arr.sort((a, b) => a.amount - b.amount);
        case "RECENT":
            return arr.sort((a, b) => +new Date(b.createdAt) - +new Date(a.createdAt));
    }
}
export default function BookingsPage() {
    const { data, isLoading, isError, refetch } = useMyBookings({ page: 0, size: 50 });
    const [tab, setTab] = useState("UPCOMING");
    const [sort, setSort] = useState("NEW");
    const [sortOpen, setSortOpen] = useState(false);
    const [page, setPage] = useState(1);
    const all = data?.content ?? [];
    const filtered = useMemo(() => sortBookings(filterByTab(all, tab), sort), [all, tab, sort]);
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const pageStart = (currentPage - 1) * PAGE_SIZE;
    const pageItems = filtered.slice(pageStart, pageStart + PAGE_SIZE);
    return (<div className="min-h-screen bg-background">
      <Nav />

      <main className="mx-auto max-w-[1360px] px-4 pb-24 pt-28 sm:px-6 lg:px-10">
        {/* Header */}
        <section className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
              Member Dashboard
            </p>
            <h1 className="mt-2 font-display text-4xl leading-tight text-primary md:text-5xl">
              My Bookings
            </h1>
            <p className="mt-2 max-w-xl text-[14px] text-muted-foreground">
              Manage your upcoming journeys and revisit your travel memories.
            </p>
          </div>
          <Link to="/search" className="inline-flex items-center gap-2 self-start rounded-xl bg-primary px-5 py-3 text-[13px] font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary-mid hover:shadow-[0_10px_24px_-12px_rgba(17,26,19,0.4)]">
            <Compass className="h-4 w-4" aria-hidden/>
            Explore More Stays
          </Link>
        </section>

        {/* Stats */}
        <section className="mt-8">
          {isLoading ? (<div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
              {Array.from({ length: 4 }).map((_, i) => (<div key={i} className="h-[104px] animate-pulse rounded-2xl bg-white/70"/>))}
            </div>) : (<BookingStats bookings={all}/>)}
        </section>

        {/* Main grid */}
        <section className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
          <div className="min-w-0">
            {/* Tabs + Sort */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="relative flex flex-wrap items-center gap-1 border-b border-border/60">
                {TABS.map((t) => {
            const active = tab === t.key;
            return (<button key={t.key} type="button" onClick={() => {
                    setTab(t.key);
                    setPage(1);
                }} className={`relative px-4 py-3 text-[13px] transition-colors ${active
                    ? "font-semibold text-primary"
                    : "font-medium text-muted-foreground hover:text-ink"}`}>
                      {t.label}
                      {active && (<motion.span layoutId="booking-tab-underline" className="absolute inset-x-3 -bottom-px h-[2px] rounded-full bg-primary"/>)}
                    </button>);
        })}
              </div>

              <div className="relative">
                <button type="button" onClick={() => setSortOpen((v) => !v)} className="inline-flex items-center gap-2 rounded-xl border border-border/60 bg-white px-3.5 py-2.5 text-[12.5px] text-ink shadow-sm transition-colors hover:border-primary/40">
                  <span className="text-muted-foreground">Sort by</span>
                  <span className="font-medium">{SORT_LABEL[sort]}</span>
                  <ChevronDown className={`h-4 w-4 text-muted-foreground transition-transform ${sortOpen ? "rotate-180" : ""}`}/>
                </button>
                <AnimatePresence>
                  {sortOpen && (<motion.div initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -4 }} className="absolute right-0 top-full z-20 mt-2 w-56 overflow-hidden rounded-xl border border-border/60 bg-white p-1 shadow-lg">
                      {Object.keys(SORT_LABEL).map((k) => (<button key={k} type="button" onClick={() => {
                    setSort(k);
                    setSortOpen(false);
                }} className={`block w-full rounded-lg px-3 py-2 text-left text-[12.5px] transition-colors ${sort === k
                    ? "bg-primary/[0.06] font-semibold text-primary"
                    : "text-ink hover:bg-primary/[0.04]"}`}>
                          {SORT_LABEL[k]}
                        </button>))}
                    </motion.div>)}
                </AnimatePresence>
              </div>
            </div>

            {/* Booking list */}
            <div className="mt-6 space-y-5">
              {isLoading && (<>
                  {Array.from({ length: 2 }).map((_, i) => (<div key={i} className="h-[260px] animate-pulse rounded-2xl bg-white/70"/>))}
                </>)}

              {isError && (<div className="rounded-2xl border border-border/60 bg-white p-8 text-center">
                  <p className="text-[14px] text-ink">Unable to load bookings.</p>
                  <button onClick={() => refetch()} className="mt-4 inline-flex items-center rounded-xl bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground hover:bg-primary-mid">
                    Retry
                  </button>
                </div>)}

              {!isLoading && !isError && filtered.length === 0 && (() => {
            const info = {
                UPCOMING: {
                    title: "No upcoming journeys planned",
                    desc: "Your next adventure awaits. Explore curated boutique stays to start planning your getaway.",
                    showCtas: true,
                },
                COMPLETED: {
                    title: "No completed stays yet",
                    desc: "Once you return from a trip, your memories and stay receipts will be waiting here.",
                    showCtas: false,
                },
                CANCELLED: {
                    title: "No cancelled bookings",
                    desc: "Any trips you cancel will appear here for your records.",
                    showCtas: false,
                },
                ALL: {
                    title: "Your booking history is empty",
                    desc: "Discover handpicked boutique stays and make your first booking today.",
                    showCtas: true,
                },
            }[tab] || {
                title: "Your booking history is empty",
                desc: "Discover handpicked boutique stays and make your first booking today.",
                showCtas: true,
            };
            return (<div className="rounded-2xl border border-border/60 bg-white p-12 text-center">
                    <Sparkles className="mx-auto h-6 w-6 text-primary/70" aria-hidden/>
                    <h3 className="mt-4 font-display text-2xl text-primary">
                      {info.title}
                    </h3>
                    <p className="mt-2 text-[13px] text-muted-foreground">
                      {info.desc}
                    </p>
                    {info.showCtas && (<div className="mt-5 flex flex-wrap items-center justify-center gap-3">
                        <Link to="/search" className="inline-flex items-center rounded-xl bg-primary px-4 py-2.5 text-[12.5px] font-medium text-primary-foreground hover:bg-primary-mid">
                          Explore Stays
                        </Link>
                        <button type="button" className="inline-flex items-center rounded-xl border border-border/60 bg-white px-4 py-2.5 text-[12.5px] font-medium text-ink hover:border-primary/40">
                          Ask StayNest AI
                        </button>
                      </div>)}
                  </div>);
        })()}

              <AnimatePresence mode="popLayout">
                {pageItems.map((b, i) => (<BookingCard key={b.id} booking={b} index={i}/>))}
              </AnimatePresence>

              {isLoading && (<div className="flex items-center justify-center py-6 text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin"/>
                </div>)}
            </div>

            {/* Pagination */}
            {!isLoading && filtered.length > PAGE_SIZE && (<div className="mt-8 flex flex-col items-center gap-3">
                <div className="inline-flex items-center gap-1 rounded-full border border-border/60 bg-white p-1 shadow-sm">
                  <button type="button" disabled={currentPage === 1} onClick={() => setPage((p) => Math.max(1, p - 1))} className="grid h-9 w-9 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-primary/[0.06] hover:text-primary disabled:opacity-40 disabled:hover:bg-transparent" aria-label="Previous page">
                    <ChevronLeft className="h-4 w-4"/>
                  </button>
                  {Array.from({ length: totalPages }).map((_, i) => {
                const n = i + 1;
                const active = n === currentPage;
                return (<button key={n} type="button" onClick={() => setPage(n)} className={`min-w-9 rounded-full px-3 py-1.5 text-[12.5px] transition-colors ${active
                        ? "bg-primary text-primary-foreground shadow-sm"
                        : "text-ink hover:bg-primary/[0.06] hover:text-primary"}`}>
                        {n}
                      </button>);
            })}
                  <button type="button" disabled={currentPage === totalPages} onClick={() => setPage((p) => Math.min(totalPages, p + 1))} className="grid h-9 w-9 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-primary/[0.06] hover:text-primary disabled:opacity-40 disabled:hover:bg-transparent" aria-label="Next page">
                    <ChevronRight className="h-4 w-4"/>
                  </button>
                </div>
                <p className="text-[11.5px] text-muted-foreground">
                  Showing {pageStart + 1}–{Math.min(pageStart + PAGE_SIZE, filtered.length)} of {filtered.length} bookings
                </p>
              </div>)}
          </div>

          <QuickPicks />
        </section>
      </main>

      <Footer />
    </div>);
}
