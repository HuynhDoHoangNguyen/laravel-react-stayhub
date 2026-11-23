import { ArrowRight } from "lucide-react";
import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import { HotelCarousel } from "./HotelCarousel";
import { useCuratedHotels } from "@/hooks/queries/useCuratedHotels";
function CardSkeleton() {
    return (<div className="w-[300px] shrink-0 sm:w-[330px]">
      <div className="shimmer aspect-[4/3] rounded-3xl"/>
      <div className="mt-4 space-y-2 px-1">
        <div className="shimmer h-4 w-2/3 rounded"/>
        <div className="shimmer h-3 w-1/3 rounded"/>
      </div>
    </div>);
}
function LoadingRow() {
    return (<div className="flex gap-6 overflow-hidden">
      {Array.from({ length: 4 }).map((_, i) => (<CardSkeleton key={i}/>))}
    </div>);
}
function EmptyState() {
    return (<div className="rounded-3xl bg-surface/60 px-10 py-16 text-center">
      <h3 className="font-display text-2xl text-ink">
        No curated stays are available at the moment.
      </h3>
      <p className="mx-auto mt-3 max-w-md text-sm text-muted-foreground">
        Our collection is being carefully refreshed. Check back soon to
        discover thoughtfully selected retreats.
      </p>
      <button type="button" className="mt-6 inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary-mid">
        Explore Hotels
      </button>
    </div>);
}
function ErrorState({ onRetry }) {
    return (<div className="rounded-3xl border border-border bg-background px-8 py-12 text-center">
      <h3 className="font-display text-xl text-ink">
        We couldn't load stays right now.
      </h3>
      <p className="mt-2 text-sm text-muted-foreground">
        Please check your connection and try again.
      </p>
      <button type="button" onClick={onRetry} className="mt-5 inline-flex items-center rounded-2xl border border-primary px-5 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-accent-pale/40">
        Retry
      </button>
    </div>);
}
function CuratedStaysView({ hotels, isLoading, isError, onRetry }) {
    return (<section aria-label="Curated stays" className="bg-background py-20 md:py-28">
      <div className="mx-auto grid max-w-[1440px] gap-12 px-6 md:px-10 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)] lg:gap-16">
        <motion.div initial={{ opacity: 0, y: 18 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-80px" }} transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }} className="lg:pt-4">
          <p className="eyebrow">Curated Stays</p>
          <h2 className="font-display mt-4 text-[clamp(2rem,3.6vw,3rem)] font-light leading-[1.05] tracking-[-0.01em] text-ink">
            Spaces that
            <br />
            stay with you.
          </h2>
          <p className="mt-5 max-w-sm text-[14.5px] leading-relaxed text-muted-foreground">
            From serene cabins to urban retreats, discover stays designed to
            help you slow down and soak in more.
          </p>
          <Link to="/search?city=Mumbai&startDate=2026-07-28&endDate=2026-07-29&roomsCount=1" className="mt-8 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-medium text-primary-foreground transition-colors duration-200 hover:bg-primary-mid">
            Explore all stays
            <ArrowRight className="h-4 w-4" aria-hidden/>
          </Link>
        </motion.div>

        <div className="min-w-0">
          {isLoading && <LoadingRow />}
          {!isLoading && isError && <ErrorState onRetry={onRetry}/>}
          {!isLoading && !isError && hotels.length === 0 && <EmptyState />}
          {!isLoading && !isError && hotels.length > 0 && (<HotelCarousel hotels={hotels}/>)}
        </div>
      </div>
    </section>);
}
export function CuratedStays() {
    const { hotels, isLoading, isError, refetch } = useCuratedHotels();
    return (<CuratedStaysView hotels={hotels} isLoading={isLoading} isError={isError} onRetry={refetch}/>);
}
