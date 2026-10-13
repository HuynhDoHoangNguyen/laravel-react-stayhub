import { useState } from "react";
import { Star, Loader2, MessageSquareQuote, ChevronLeft, ChevronRight } from "lucide-react";
import { useMyReviews } from "@/hooks/queries/useMyReviews";
function formatDate(iso) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime()))
        return iso;
    return d.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" });
}
function Stars({ rating }) {
    return (<div className="flex items-center gap-0.5" aria-label={`${rating} out of 5 stars`}>
      {[1, 2, 3, 4, 5].map((i) => (<Star key={i} className={`h-3.5 w-3.5 ${i <= rating ? "fill-gold text-gold" : "text-border"}`} strokeWidth={1.5}/>))}
    </div>);
}
function ReviewCard({ review }) {
    return (<article className="group flex gap-4 rounded-2xl border border-border/60 bg-background/60 p-4 transition-all hover:-translate-y-[1px] hover:border-primary/30 hover:shadow-[0_10px_28px_-18px_rgba(17,26,19,0.35)] md:p-5">
      {review.hotelPhoto && (<img src={review.hotelPhoto} alt={review.hotelName ?? "Hotel"} className="hidden h-20 w-20 shrink-0 rounded-xl object-cover sm:block md:h-24 md:w-24" loading="lazy"/>)}
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            <h4 className="truncate font-display text-[16px] text-primary md:text-[17px]">
              {review.hotelName ?? "Your stay"}
            </h4>
            {review.hotelCity && (<p className="mt-0.5 truncate text-[12px] text-muted-foreground">
                {review.hotelCity}
              </p>)}
          </div>
          <div className="text-right">
            <Stars rating={review.rating}/>
            <p className="mt-1 text-[11px] text-muted-foreground">
              {formatDate(review.createdAt)}
            </p>
          </div>
        </div>
        <p className="mt-3 line-clamp-3 text-[13.5px] leading-relaxed text-ink/85">
          {review.reviewText}
        </p>
      </div>
    </article>);
}
export function MyReviewsSection() {
    const [page, setPage] = useState(0);
    const size = 5;
    const { data, isLoading, isError, refetch } = useMyReviews(page, size);
    const reviews = data?.content ?? [];
    const total = data?.totalElements ?? 0;
    return (<section className="rounded-3xl border border-border/60 bg-white p-6 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] md:p-8">
      <div className="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="grid h-10 w-10 place-items-center rounded-full bg-primary/[0.08] text-primary">
            <MessageSquareQuote className="h-4 w-4"/>
          </div>
          <div>
            <h3 className="font-display text-xl text-primary md:text-2xl">
              My Reviews
              {total > 0 && (<span className="ml-2 align-middle text-[12px] font-medium text-muted-foreground">
                  ({total})
                </span>)}
            </h3>
            <p className="mt-0.5 text-[12.5px] text-muted-foreground">
              Ratings and notes you've shared from past stays.
            </p>
          </div>
        </div>
      </div>

      {isLoading ? (<div className="grid h-40 place-items-center text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin"/>
        </div>) : isError ? (<div className="mt-5 rounded-2xl border border-dashed border-border/70 bg-background/60 p-6 text-center">
          <p className="text-[13.5px] text-ink">Unable to load your reviews.</p>
          <button onClick={() => refetch()} className="mt-3 inline-flex items-center rounded-xl bg-primary px-4 py-2 text-[12px] font-medium text-primary-foreground hover:bg-primary-mid">
            Retry
          </button>
        </div>) : reviews.length === 0 ? (<div className="mt-5 rounded-2xl border border-dashed border-border/70 bg-background/60 p-8 text-center">
          <MessageSquareQuote className="mx-auto h-6 w-6 text-primary/60"/>
          <p className="mt-3 text-[13.5px] text-ink">
            You haven't written a review yet.
          </p>
          <p className="mt-1 text-[12px] text-muted-foreground">
            After a completed stay, your ratings and reviews will appear here.
          </p>
        </div>) : (<>
          <ul className="mt-5 grid grid-cols-1 gap-4">
            {reviews.map((r) => (<li key={r.id}>
                <ReviewCard review={r}/>
              </li>))}
          </ul>

          {data && data.totalPages > 1 && (<div className="mt-6 flex items-center justify-between text-[12.5px] text-muted-foreground">
              <span>
                Page {data.number + 1} of {data.totalPages}
              </span>
              <div className="flex items-center gap-2">
                <button onClick={() => setPage((p) => Math.max(0, p - 1))} disabled={data.first} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border/70 text-primary transition-colors hover:border-primary/40 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Previous page">
                  <ChevronLeft className="h-4 w-4"/>
                </button>
                <button onClick={() => setPage((p) => p + 1)} disabled={data.last} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border/70 text-primary transition-colors hover:border-primary/40 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Next page">
                  <ChevronRight className="h-4 w-4"/>
                </button>
              </div>
            </div>)}
        </>)}
    </section>);
}
