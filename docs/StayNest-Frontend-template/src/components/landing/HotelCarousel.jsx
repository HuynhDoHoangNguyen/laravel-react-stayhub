import { ChevronLeft, ChevronRight } from "lucide-react";
import { useCarouselControls } from "@/hooks/useCarouselControls";
import { HotelCard } from "./HotelCard";
export function HotelCarousel({ hotels }) {
    const { scrollerRef, canScrollLeft, canScrollRight, scrollByAmount } = useCarouselControls();
    return (<div className="relative">
      <div ref={scrollerRef} className="no-scrollbar flex gap-6 overflow-x-auto scroll-smooth pr-6 pb-6" style={{ WebkitOverflowScrolling: "touch" }}>
        {hotels.map((h) => (<div key={h.id}>
            <HotelCard hotel={h}/>
          </div>))}
        <div className="w-2 shrink-0" aria-hidden/>
      </div>

      <button type="button" aria-label="Previous stays" onClick={() => scrollByAmount(-360)} disabled={!canScrollLeft} className="absolute -left-4 top-[38%] hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-background text-ink shadow-[0_10px_30px_-10px_rgba(17,26,19,0.35)] transition-all duration-200 hover:bg-surface disabled:pointer-events-none disabled:opacity-0 md:inline-flex">
        <ChevronLeft className="h-5 w-5" aria-hidden/>
      </button>
      <button type="button" aria-label="More stays" onClick={() => scrollByAmount(360)} disabled={!canScrollRight} className="absolute -right-4 top-[38%] hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-background text-ink shadow-[0_10px_30px_-10px_rgba(17,26,19,0.35)] transition-all duration-200 hover:bg-surface disabled:pointer-events-none disabled:opacity-0 md:inline-flex">
        <ChevronRight className="h-5 w-5" aria-hidden/>
      </button>
    </div>);
}
