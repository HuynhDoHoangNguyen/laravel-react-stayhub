import { useState } from "react";
import { Star, ChevronDown, Loader2 } from "lucide-react";
import { useHotelReviews } from "@/hooks/queries/useHotelDetails";
function formatDate(iso) {
    try {
        return new Date(iso).toLocaleDateString(undefined, { month: "short", year: "numeric" });
    }
    catch {
        return "";
    }
}
function initials(name) {
    if (!name || typeof name !== "string") return "G";
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? "") + (parts[1]?.[0] ?? "")).toUpperCase() || "G";
}
export function ReviewsSection({ hotelId, averageRating, reviewCount }) {
    const [page, setPage] = useState(0);
    const size = 6;
    const { data, isLoading } = useHotelReviews(hotelId, page, size);
    return (<section id="reviews" className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="font-display text-xl text-primary md:text-2xl">Guest reviews</h2>
          <p className="mt-1 inline-flex items-center gap-2 text-[13.5px] text-muted-foreground">
            <span className="inline-flex items-center gap-1 text-ink">
              <Star className="h-4 w-4 fill-accent text-accent"/>
              <span className="font-semibold">{averageRating?.toFixed(1) ?? "—"}</span>
            </span>
            · Based on {reviewCount ?? 0} verified stays
          </p>
        </div>
      </div>

      {isLoading && !data ? (<div className="grid place-items-center py-14 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin"/>
        </div>) : (<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {(data?.content ?? []).map((r) => {
            const author = r.guestName || r.userName || "Verified Guest";
            const text = r.comment || r.reviewText || "";
            return (
              <article key={r.id} className="rounded-2xl border border-border/70 bg-background/70 p-5">
                <header className="flex items-center gap-3">
                  <span className="grid h-10 w-10 place-items-center rounded-full bg-primary/10 text-[13px] font-semibold text-primary">
                    {initials(author)}
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-[14px] font-medium text-ink">{author}</div>
                    <div className="text-[11.5px] text-muted-foreground">{formatDate(r.createdAt)}</div>
                  </div>
                  <div className="inline-flex items-center gap-0.5">
                    {Array.from({ length: 5 }).map((_, i) => (<Star key={i} className={`h-3.5 w-3.5 ${i < r.rating ? "fill-accent text-accent" : "text-muted-foreground/40"}`}/>))}
                  </div>
                </header>
                <p className="mt-3 text-[13.5px] leading-relaxed text-ink/80">{text}</p>
              </article>
            );
          })}
        </div>)}

      {data && data.totalPages > 1 && (<div className="flex justify-center pt-2">
          <button type="button" disabled={data.last} onClick={() => setPage((p) => p + 1)} className="inline-flex items-center gap-2 rounded-full border border-border px-5 py-2 text-[12.5px] font-medium text-ink transition hover:border-primary/40 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
            Load more reviews <ChevronDown className="h-3.5 w-3.5"/>
          </button>
        </div>)}
    </section>);
}
