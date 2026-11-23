import { useNavigate, useSearchParams } from "react-router-dom";
import { useEffect, useMemo, useRef, useState } from "react";
import { motion } from "framer-motion";
import { Loader2 } from "lucide-react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { SearchSummaryBar } from "@/components/search/SearchSummaryBar";
import { FilterSidebar, matchesPropertyType } from "@/components/search/FilterSidebar";
import { ResultsGrid } from "@/components/search/ResultsGrid";
import { ResultsHeader } from "@/components/search/ResultsHeader";
import { EmptyState } from "@/components/search/EmptyState";
import { BenefitsStrip } from "@/components/search/BenefitsStrip";
import { AssistantPanel } from "@/components/search/AssistantPanel";
import { useInfiniteHotelSearch } from "@/hooks/queries/useHotelSearch";
import { useNLHotelSearch } from "@/hooks/queries/useNLHotelSearch";

export default function SearchPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  const search = useMemo(() => {
    let startDate = searchParams.get("startDate") || "";
    let endDate = searchParams.get("endDate") || "";
    if (startDate && endDate) {
      const s = new Date(startDate);
      const e = new Date(endDate);
      if (!isNaN(s.getTime()) && !isNaN(e.getTime()) && e <= s) {
        if (e.getMonth() < s.getMonth()) {
          e.setFullYear(s.getFullYear() + 1);
        } else {
          e.setDate(s.getDate() + 2);
        }
        endDate = e.toISOString().slice(0, 10);
      }
    }

    return {
      q: searchParams.get("q") || "",
      city: searchParams.get("city") || "",
      startDate,
      endDate,
      roomsCount: Number(searchParams.get("roomsCount")) || 1,
      adults: Number(searchParams.get("adults")) || 2,
      children: Number(searchParams.get("children")) || 0,
      page: Number(searchParams.get("page")) || 0,
      size: Number(searchParams.get("size")) || 12,
      sortBy: searchParams.get("sortBy") || "PRICE_ASC",
      minPrice: searchParams.get("minPrice") ? Number(searchParams.get("minPrice")) : undefined,
      maxPrice: searchParams.get("maxPrice") ? Number(searchParams.get("maxPrice")) : undefined,
      minRating: searchParams.get("minRating") ? Number(searchParams.get("minRating")) : undefined,
      focus: searchParams.get("focus") || "",
    };
  }, [searchParams]);

  const [interpreted, setInterpreted] = useState(() => {
    try {
      const saved = sessionStorage.getItem("staynest_assistant_interpreted");
      return saved ? JSON.parse(saved) : undefined;
    } catch {
      return undefined;
    }
  });
  const [missing, setMissing] = useState();
  const [messages, setMessages] = useState(() => {
    try {
      const saved = sessionStorage.getItem("staynest_assistant_messages");
      return saved ? JSON.parse(saved) : [];
    } catch {
      return [];
    }
  });
  const [view, setView] = useState(() => {
    try {
      return sessionStorage.getItem("staynest_search_view") || "grid";
    } catch {
      return "grid";
    }
  });
  const [assistantHighlight, setAssistantHighlight] = useState(false);
  const [isThinking, setIsThinking] = useState(false);

  useEffect(() => {
    try {
      sessionStorage.setItem("staynest_assistant_messages", JSON.stringify(messages));
    } catch {}
  }, [messages]);

  useEffect(() => {
    try {
      if (interpreted) {
        sessionStorage.setItem("staynest_assistant_interpreted", JSON.stringify(interpreted));
      } else {
        sessionStorage.removeItem("staynest_assistant_interpreted");
      }
    } catch {}
  }, [interpreted]);

  useEffect(() => {
    try {
      sessionStorage.setItem("staynest_search_view", view);
    } catch {}
  }, [view]);

  const nl = useNLHotelSearch();
  const lastProcessedQ = useRef(null);

  useEffect(() => {
    if (search.focus !== "assistant") return;
    setAssistantHighlight(true);
    const el = document.getElementById("staynest-assistant");
    if (el) {
      el.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    const t = window.setTimeout(() => setAssistantHighlight(false), 2400);

    const newParams = new URLSearchParams(searchParams);
    newParams.delete("focus");
    setSearchParams(newParams, { replace: true });

    return () => window.clearTimeout(t);
  }, [search.focus, searchParams, setSearchParams]);

  useEffect(() => {
    if (!search.q) return;
    if (lastProcessedQ.current === search.q) return;

    lastProcessedQ.current = search.q;
    setMessages((prev) => [
      ...prev,
      { id: `u-${Date.now()}`, role: "user", text: search.q },
    ]);
    setIsThinking(true);

    nl.mutateAsync({
      query: search.q,
      city: search.city || undefined,
      startDate: search.startDate || undefined,
      endDate: search.endDate || undefined,
      roomsCount: search.roomsCount,
      adults: search.adults,
    })
      .then((data) => {
        setIsThinking(false);
        setInterpreted(data.interpretedQuery);
        setMissing(data.missingFields);
        const i = data.interpretedQuery;
        const friendlyNames = {
          startDate: "check-in date",
          endDate: "check-out date",
          roomsCount: "rooms",
          adults: "guests",
          city: "destination",
          minPrice: "min price",
          maxPrice: "max price",
          minRating: "min rating",
          sortBy: "sort preference",
        };
        const missingFriendly = data.missingFields?.map((f) => friendlyNames[f] ?? f) ?? [];
        let summary = "";
        if (data.missingFields?.includes("feature_disabled")) {
          summary = "Sorry, the StayNest AI search concierge is currently disabled in the backend configuration.";
        } else if (missingFriendly.length) {
          summary = `I've looked for stays in ${i?.city ?? "your destination"}. To refine, I still need: ${missingFriendly.join(", ")}.`;
        } else {
          summary = `Great! Here are the best matches for you.`;
        }

        setMessages((prev) => [
          ...prev,
          { id: `a-${Date.now()}`, role: "assistant", text: summary },
        ]);

        const nextParams = new URLSearchParams(searchParams);
        nextParams.delete("q");
        if (i?.city) nextParams.set("city", i.city);
        if (i?.startDate) nextParams.set("startDate", i.startDate);
        if (i?.endDate) nextParams.set("endDate", i.endDate);
        if (i?.roomsCount) nextParams.set("roomsCount", String(i.roomsCount));
        if (i?.adults) nextParams.set("adults", String(i.adults));
        if (i?.minPrice) nextParams.set("minPrice", String(i.minPrice));
        if (i?.maxPrice) nextParams.set("maxPrice", String(i.maxPrice));
        if (i?.minRating) nextParams.set("minRating", String(i.minRating));
        if (i?.sortBy) nextParams.set("sortBy", i.sortBy);

        setSearchParams(nextParams, { replace: true });
      })
      .catch((err) => {
        setIsThinking(false);
        setMessages((prev) => [
          ...prev,
          {
            id: `a-${Date.now()}`,
            role: "assistant",
            text: `Sorry, I couldn't process that. ${err.message}`,
          },
        ]);
      });
  }, [search.q, search.city, search.startDate, search.endDate, search.roomsCount, search.adults, searchParams, setSearchParams, nl]);

  const [selectedAmenities, setSelectedAmenities] = useState(() => {
    try {
      const saved = sessionStorage.getItem("staynest_amenities");
      return saved ? JSON.parse(saved) : [];
    } catch {
      return [];
    }
  });

  const [selectedPropertyTypes, setSelectedPropertyTypes] = useState(() => {
    try {
      const saved = sessionStorage.getItem("staynest_property_types");
      return saved ? JSON.parse(saved) : [];
    } catch {
      return [];
    }
  });

  useEffect(() => {
    try {
      sessionStorage.setItem("staynest_amenities", JSON.stringify(selectedAmenities));
    } catch {}
  }, [selectedAmenities]);

  useEffect(() => {
    try {
      sessionStorage.setItem("staynest_property_types", JSON.stringify(selectedPropertyTypes));
    } catch {}
  }, [selectedPropertyTypes]);

  const filters = useMemo(
    () => ({
      minPrice: search.minPrice,
      maxPrice: search.maxPrice,
      minRating: search.minRating,
      sortBy: search.sortBy,
      selectedAmenities,
      selectedPropertyTypes,
    }),
    [search.minPrice, search.maxPrice, search.minRating, search.sortBy, selectedAmenities, selectedPropertyTypes]
  );

  const query = useInfiniteHotelSearch({
    city: search.city,
    startDate: search.startDate,
    endDate: search.endDate,
    roomsCount: search.roomsCount,
    size: search.size,
    sortBy: filters.sortBy,
    minPrice: filters.minPrice,
    maxPrice: filters.maxPrice,
    minRating: filters.minRating,
  });

  const setFilters = (next) => {
    if (next.selectedAmenities !== undefined) setSelectedAmenities(next.selectedAmenities);
    if (next.selectedPropertyTypes !== undefined) setSelectedPropertyTypes(next.selectedPropertyTypes);

    const params = new URLSearchParams(searchParams);
    if (next.minPrice !== undefined) params.set("minPrice", String(next.minPrice));
    if (next.maxPrice !== undefined) params.set("maxPrice", String(next.maxPrice));
    if (next.minRating !== undefined) params.set("minRating", String(next.minRating));
    if (next.sortBy) params.set("sortBy", next.sortBy);
    params.set("page", "0");
    setSearchParams(params, { replace: true });
  };

  const resetFilters = () => {
    setSelectedAmenities([]);
    setSelectedPropertyTypes([]);
    setMessages([]);
    setInterpreted(undefined);
    try {
      sessionStorage.removeItem("staynest_amenities");
      sessionStorage.removeItem("staynest_property_types");
      sessionStorage.removeItem("staynest_assistant_messages");
      sessionStorage.removeItem("staynest_assistant_interpreted");
    } catch {}
    const params = new URLSearchParams(searchParams);
    params.delete("minPrice");
    params.delete("maxPrice");
    params.delete("minRating");
    params.set("sortBy", "PRICE_ASC");
    params.set("page", "0");
    setSearchParams(params, { replace: true });
  };

  const pages = query.data?.pages ?? [];
  const lastPage = pages[pages.length - 1];
  const rawHotels = pages.flatMap((p) => p.content);

  const hotels = useMemo(() => {
    let list = rawHotels;

    if (selectedAmenities.length > 0) {
      list = list.filter((h) => {
        const hAmenities = (h.amenities || []).map((x) => x.toLowerCase());
        return selectedAmenities.every((id) => {
          if (id === "wifi") return hAmenities.some((x) => x.includes("wifi") || x.includes("wi-fi"));
          if (id === "pool") return hAmenities.some((x) => x.includes("pool"));
          if (id === "spa") return hAmenities.some((x) => x.includes("spa"));
          if (id === "dining") return hAmenities.some((x) => x.includes("restaurant") || x.includes("dining"));
          if (id === "fitness") return hAmenities.some((x) => x.includes("fitness") || x.includes("gym"));
          if (id === "parking") return hAmenities.some((x) => x.includes("parking") || x.includes("valet"));
          return true;
        });
      });
    }

    if (selectedPropertyTypes.length > 0) {
      list = list.filter((h) => selectedPropertyTypes.some((type) => matchesPropertyType(h, type)));
    }

    return list;
  }, [rawHotels, selectedAmenities, selectedPropertyTypes]);

  const requiredReady = Boolean(search.city && search.startDate && search.endDate && search.roomsCount);
  const total = hotels.length;
  const from = total === 0 ? 0 : 1;
  const to = hotels.length;
  const hasMore = Boolean(query.hasNextPage);
  const showEndCap = requiredReady && !hasMore && hotels.length > 0;

  return (
    <div className="min-h-screen bg-background pt-20">
      <Nav />

      <SearchSummaryBar
        city={search.city}
        startDate={search.startDate}
        endDate={search.endDate}
        roomsCount={search.roomsCount}
        adults={search.adults}
        children={search.children}
      />

      <main className="mx-auto max-w-[1440px] px-6 pb-12 pt-6 md:px-10">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[280px_minmax(0,1fr)_340px]">
          <FilterSidebar value={filters} onChange={setFilters} onReset={resetFilters} availableHotels={rawHotels} />

          <section className="min-w-0 space-y-6">
            <div>
              <ResultsHeader
                total={total}
                city={search.city || interpreted?.city}
                from={from}
                to={to}
                sortBy={filters.sortBy}
                onSortChange={(s) => setFilters({ ...filters, sortBy: s })}
                view={view}
                onViewChange={setView}
              />

              <motion.div
                key={`grid-${hotels.length}`}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
              >
                {!requiredReady && !isThinking ? (
                  <EmptyState
                    title="Tell us where you're going"
                    message="Add a destination, dates and rooms — or ask our AI concierge on the right for a personal recommendation."
                  />
                ) : query.isError ? (
                  <EmptyState
                    title="Something went wrong"
                    message="We couldn't load stays right now. Please adjust your search or try again in a moment."
                  />
                ) : requiredReady && !query.isLoading && hotels.length === 0 ? (
                  <EmptyState
                    title="No stays match your filters"
                    message="Try widening your price range, lowering the minimum rating, or exploring nearby dates."
                  />
                ) : (
                  <ResultsGrid
                    hotels={hotels}
                    isLoading={query.isLoading}
                    view={view}
                    context={{
                      startDate: search.startDate,
                      endDate: search.endDate,
                      roomsCount: search.roomsCount,
                    }}
                  />
                )}
              </motion.div>

              {hasMore && (
                <div className="mt-12 flex justify-center">
                  <button
                    type="button"
                    onClick={() => query.fetchNextPage()}
                    disabled={query.isFetchingNextPage}
                    className="group inline-flex items-center gap-3 rounded-full border border-primary/30 bg-background px-8 py-3.5 text-[13px] font-medium uppercase tracking-[0.18em] text-primary shadow-[0_10px_30px_-12px_rgba(17,26,19,0.25)] transition-all duration-300 hover:border-primary hover:bg-primary hover:text-primary-foreground hover:shadow-[0_16px_40px_-14px_rgba(17,26,19,0.4)] disabled:cursor-not-allowed disabled:opacity-70"
                  >
                    {query.isFetchingNextPage ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
                        Loading more stays…
                      </>
                    ) : (
                      <>
                        Load more stays
                        <span className="text-[11px] tracking-[0.2em] opacity-70" aria-hidden>
                          {hotels.length} / {total}
                        </span>
                      </>
                    )}
                  </button>
                </div>
              )}

              {showEndCap && (
                <p className="mt-12 text-center text-[12px] uppercase tracking-[0.24em] text-muted-foreground">
                  Showing all {total} available stays
                </p>
              )}
            </div>
          </section>

          <AssistantPanel
            messages={messages}
            interpreted={interpreted}
            missing={missing?.filter((f) => f !== "feature_disabled")}
            isThinking={isThinking}
            highlight={assistantHighlight}
            onAsk={(q) => {
              if (q === "Cheapest hotels") {
                setFilters({ ...filters, sortBy: "PRICE_ASC" });
              } else if (q === "5-star resorts") {
                setSelectedPropertyTypes(["Resort"]);
                setFilters({ ...filters, minRating: 5 });
              } else if (q === "Near the beach" || q === "Pool & Spa") {
                setSelectedAmenities(["pool", "spa"]);
              }
              const p = new URLSearchParams(searchParams);
              p.set("q", q);
              setSearchParams(p, { replace: true });
            }}
            sortBy={filters.sortBy}
            onSortChange={(s) => setFilters({ ...filters, sortBy: s })}
            minPrice={filters.minPrice}
            maxPrice={filters.maxPrice}
            onPriceApply={(min, max) => setFilters({ ...filters, minPrice: min, maxPrice: max })}
          />
        </div>
      </main>

      <BenefitsStrip />
      <Footer />
    </div>
  );
}
