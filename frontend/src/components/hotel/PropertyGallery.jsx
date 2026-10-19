import { useState, useEffect, useCallback } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { ChevronLeft, ChevronRight, Maximize2, X } from "lucide-react";

const FALLBACK_PHOTO =
  "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80&fit=crop";

export function PropertyGallery({ photos = [], name = "Chỗ nghỉ" }) {
  const [active, setActive] = useState(0);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);

  const list = photos && photos.length > 0 ? photos : [FALLBACK_PHOTO];
  const main = list[active] || FALLBACK_PHOTO;

  const prev = useCallback(() => {
    setActive((i) => (i - 1 + list.length) % list.length);
  }, [list.length]);

  const next = useCallback(() => {
    setActive((i) => (i + 1) % list.length);
  }, [list.length]);

  // Keyboard navigation for Lightbox (Escape to close, ArrowLeft/Right to navigate)
  useEffect(() => {
    if (!isLightboxOpen) return;

    const handleKeyDown = (e) => {
      if (e.key === "Escape") {
        setIsLightboxOpen(false);
      } else if (e.key === "ArrowLeft") {
        prev();
      } else if (e.key === "ArrowRight") {
        next();
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [isLightboxOpen, prev, next]);

  return (
    <>
      <section className="grid grid-cols-1 gap-3 md:grid-cols-4 md:grid-rows-2">
        {/* Main large image */}
        <div className="relative aspect-[16/10] overflow-hidden rounded-2xl bg-muted md:col-span-3 md:row-span-2 md:aspect-auto">
          <AnimatePresence mode="wait">
            <motion.img
              key={main}
              src={main}
              alt={`${name} — hình ảnh ${active + 1}`}
              onError={(e) => {
                e.currentTarget.onerror = null;
                e.currentTarget.src = FALLBACK_PHOTO;
              }}
              initial={{ opacity: 0, scale: 1.02 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.45, ease: "easeOut" }}
              className="absolute inset-0 h-full w-full cursor-pointer object-cover"
              onClick={() => setIsLightboxOpen(true)}
            />
          </AnimatePresence>

          {/* Expand Lightbox Button */}
          <button
            type="button"
            onClick={() => setIsLightboxOpen(true)}
            aria-label="Xem ảnh phóng to"
            className="absolute left-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-background/80 text-ink shadow-sm backdrop-blur transition hover:bg-background hover:scale-105"
          >
            <Maximize2 className="h-4 w-4" />
          </button>

          {list.length > 1 && (
            <>
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  prev();
                }}
                aria-label="Ảnh trước đó"
                className="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-background/90 text-ink shadow-md transition hover:scale-105 active:scale-95"
              >
                <ChevronLeft className="h-5 w-5" />
              </button>
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  next();
                }}
                aria-label="Ảnh tiếp theo"
                className="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-background/90 text-ink shadow-md transition hover:scale-105 active:scale-95"
              >
                <ChevronRight className="h-5 w-5" />
              </button>
              <div className="absolute bottom-3 right-3 rounded-full bg-black/65 px-3 py-1 text-[11px] font-medium text-white backdrop-blur">
                {active + 1} / {list.length}
              </div>
            </>
          )}
        </div>

        {/* Thumbnail grid on the right (up to 4 thumbnails) */}
        {list.slice(1, 5).map((src, i) => {
          const idx = i + 1;
          const isSelected = active === idx;
          return (
            <button
              key={`${src}-${idx}`}
              type="button"
              onClick={() => setActive(idx)}
              className={[
                "relative aspect-[4/3] overflow-hidden rounded-2xl bg-muted transition-all",
                isSelected
                  ? "ring-2 ring-primary ring-offset-2 ring-offset-background"
                  : "opacity-90 hover:opacity-100",
              ].join(" ")}
            >
              <img
                src={src}
                alt=""
                onError={(e) => {
                  e.currentTarget.onerror = null;
                  e.currentTarget.src = FALLBACK_PHOTO;
                }}
                className="absolute inset-0 h-full w-full object-cover"
              />
            </button>
          );
        })}
      </section>

      {/* Lightbox Modal */}
      {isLightboxOpen && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Bộ sưu tập ảnh chi tiết"
          className="fixed inset-0 z-50 flex flex-col items-center justify-between bg-black/92 p-4 backdrop-blur-md animate-in fade-in duration-200"
          onClick={() => setIsLightboxOpen(false)}
        >
          {/* Top bar */}
          <div
            className="flex w-full max-w-6xl items-center justify-between py-2 text-white"
            onClick={(e) => e.stopPropagation()}
          >
            <span className="text-sm font-medium">
              {name} ({active + 1} / {list.length})
            </span>
            <button
              type="button"
              onClick={() => setIsLightboxOpen(false)}
              aria-label="Đóng thư viện ảnh"
              className="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 active:scale-95"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* Main Stage */}
          <div
            className="relative flex w-full max-w-5xl flex-1 items-center justify-center px-4"
            onClick={(e) => e.stopPropagation()}
          >
            <img
              src={list[active]}
              alt={`${name} — ${active + 1}`}
              onError={(e) => {
                e.currentTarget.onerror = null;
                e.currentTarget.src = FALLBACK_PHOTO;
              }}
              className="max-h-[75vh] max-w-full rounded-2xl object-contain shadow-2xl"
            />

            {list.length > 1 && (
              <>
                <button
                  type="button"
                  onClick={prev}
                  aria-label="Ảnh trước"
                  className="absolute left-2 grid h-12 w-12 place-items-center rounded-full bg-white/15 text-white backdrop-blur transition hover:bg-white/25 active:scale-95 md:left-6"
                >
                  <ChevronLeft className="h-6 w-6" />
                </button>
                <button
                  type="button"
                  onClick={next}
                  aria-label="Ảnh sau"
                  className="absolute right-2 grid h-12 w-12 place-items-center rounded-full bg-white/15 text-white backdrop-blur transition hover:bg-white/25 active:scale-95 md:right-6"
                >
                  <ChevronRight className="h-6 w-6" />
                </button>
              </>
            )}
          </div>

          {/* Bottom Thumbnails */}
          <div
            className="flex max-w-3xl gap-2 overflow-x-auto py-3"
            onClick={(e) => e.stopPropagation()}
          >
            {list.map((src, i) => (
              <button
                key={`lightbox-thumb-${src}-${i}`}
                type="button"
                onClick={() => setActive(i)}
                className={[
                  "relative h-14 w-20 shrink-0 overflow-hidden rounded-xl border-2 transition-all",
                  active === i ? "border-primary opacity-100 scale-105" : "border-transparent opacity-60 hover:opacity-100",
                ].join(" ")}
              >
                <img
                  src={src}
                  alt=""
                  onError={(e) => {
                    e.currentTarget.onerror = null;
                    e.currentTarget.src = FALLBACK_PHOTO;
                  }}
                  className="h-full w-full object-cover"
                />
              </button>
            ))}
          </div>
        </div>
      )}
    </>
  );
}
