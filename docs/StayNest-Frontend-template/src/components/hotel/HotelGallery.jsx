import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { ChevronLeft, ChevronRight } from "lucide-react";
export function HotelGallery({ photos, name }) {
    const [active, setActive] = useState(0);
    const list = photos.length ? photos : ["/placeholder.svg"];
    const main = list[active];
    const prev = () => setActive((i) => (i - 1 + list.length) % list.length);
    const next = () => setActive((i) => (i + 1) % list.length);
    return (<section className="grid grid-cols-1 gap-3 md:grid-cols-4 md:grid-rows-2">
      <div className="relative overflow-hidden rounded-2xl bg-muted md:col-span-3 md:row-span-2 aspect-[16/10] md:aspect-auto">
        <AnimatePresence mode="wait">
          <motion.img key={main} src={main} alt={`${name} photo ${active + 1}`} initial={{ opacity: 0, scale: 1.02 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.5, ease: "easeOut" }} className="absolute inset-0 h-full w-full object-cover"/>
        </AnimatePresence>
        {list.length > 1 && (<>
            <button type="button" onClick={prev} aria-label="Previous photo" className="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-background/90 text-ink shadow-md transition hover:scale-105">
              <ChevronLeft className="h-5 w-5"/>
            </button>
            <button type="button" onClick={next} aria-label="Next photo" className="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-background/90 text-ink shadow-md transition hover:scale-105">
              <ChevronRight className="h-5 w-5"/>
            </button>
            <div className="absolute bottom-3 right-3 rounded-full bg-ink/70 px-3 py-1 text-[11px] font-medium text-background">
              {active + 1} / {list.length}
            </div>
          </>)}
      </div>
      {list.slice(1, 5).map((src, i) => {
            const idx = i + 1;
            return (<button key={src + idx} type="button" onClick={() => setActive(idx)} className={[
                    "relative overflow-hidden rounded-2xl bg-muted aspect-[4/3] transition-all",
                    active === idx ? "ring-2 ring-primary/60 ring-offset-2 ring-offset-background" : "opacity-90 hover:opacity-100",
                ].join(" ")}>
            <img src={src} alt="" className="absolute inset-0 h-full w-full object-cover"/>
          </button>);
        })}
    </section>);
}
