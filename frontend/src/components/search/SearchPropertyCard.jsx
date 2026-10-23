import { Heart, Star, Wifi, Waves, Coffee, Sparkles, MapPin } from "lucide-react";
import { motion } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";

const vndCurrency = new Intl.NumberFormat("vi-VN", {
  style: "currency",
  currency: "VND",
  maximumFractionDigits: 0,
});

const AMENITY_ICONS = {
  wifi: Wifi,
  pool: Waves,
  dining: Coffee,
  spa: Sparkles,
};

function AmenityIcon({ id }) {
  const Icon = AMENITY_ICONS[id] ?? Sparkles;
  return <Icon className="h-3.5 w-3.5" aria-hidden />;
}

const FALLBACK_PHOTOS = [
  "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=1200&q=80&fit=crop",
];

export function SearchPropertyCard({ property, context, view = "grid" }) {
  const [photoIndex, setPhotoIndex] = useState(0);
  const [hovered, setHovered] = useState(false);
  const [isFavorite, setIsFavorite] = useState(false);
  const rawPhotos = property.photos ?? [];
  const photos = rawPhotos.length > 0 ? rawPhotos : FALLBACK_PHOTOS;
  const amenities = (property.amenities ?? []).slice(0, 4);
  const intervalRef = useRef(null);
  const isList = view === "list";

  useEffect(() => {
    if (hovered && photos.length > 1) {
      intervalRef.current = setInterval(() => {
        setPhotoIndex((i) => (i + 1) % photos.length);
      }, 1400);
    }
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
      intervalRef.current = null;
    };
  }, [hovered, photos.length]);

  const detailUrl = `/properties/${property.id}?startDate=${context?.startDate ?? ""}&endDate=${context?.endDate ?? ""}&roomsCount=${context?.roomsCount ?? 1}&adults=${context?.adults ?? 2}`;

  return (
    <motion.article
      whileHover={{ y: -4 }}
      transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => {
        setHovered(false);
        setPhotoIndex(0);
      }}
      className={[
        "group overflow-hidden rounded-3xl bg-card shadow-[0_10px_40px_-25px_rgba(17,26,19,0.25)] ring-1 ring-black/[0.04] transition-shadow duration-500 hover:shadow-[0_25px_60px_-30px_rgba(17,26,19,0.4)]",
        isList ? "flex flex-col md:flex-row" : "flex h-full flex-col",
      ].join(" ")}
    >
      <div
        className={[
          "relative overflow-hidden bg-surface shrink-0",
          isList ? "w-full md:w-[320px] aspect-[16/10]" : "aspect-[16/11] w-full",
        ].join(" ")}
      >
        {photos.length > 0 ? (
          photos.map((src, i) => (
            <img
              key={src + i}
              src={src}
              alt={property.name}
              loading="lazy"
              onError={(e) => {
                e.currentTarget.onerror = null;
                e.currentTarget.src = FALLBACK_PHOTOS[i % FALLBACK_PHOTOS.length];
              }}
              className={[
                "absolute inset-0 h-full w-full object-cover transition-opacity duration-700 ease-out",
                i === photoIndex ? "opacity-100" : "opacity-0",
                i === photoIndex && hovered ? "scale-[1.04]" : "",
              ].join(" ")}
              style={{ transitionProperty: "opacity, transform" }}
            />
          ))
        ) : (
          <div className="shimmer h-full w-full" />
        )}

        {photos.length > 1 && (
          <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
            {photos.map((_, i) => (
              <span
                key={i}
                className={[
                  "h-1.5 w-1.5 rounded-full transition-all",
                  i === photoIndex ? "bg-white w-4" : "bg-white/60",
                ].join(" ")}
              />
            ))}
          </div>
        )}

        <button
          type="button"
          aria-label={isFavorite ? "Bỏ lưu yêu thích" : "Lưu yêu thích"}
          aria-pressed={isFavorite}
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            setIsFavorite((prev) => !prev);
          }}
          className="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-background/95 text-ink shadow-sm transition-transform duration-200 hover:scale-110 active:scale-95"
        >
          <Heart
            className={[
              "h-4 w-4 transition-colors",
              isFavorite ? "fill-primary text-primary" : "text-ink/80",
            ].join(" ")}
            strokeWidth={1.6}
            aria-hidden
          />
        </button>
      </div>

      <div className="flex flex-1 flex-col p-5">
        <div className="flex items-start justify-between gap-3">
          <h3 className="min-w-0 truncate text-[17px] font-medium text-ink">
            {property.name}
          </h3>
          {property.reviewCount > 0 && (
            <span className="inline-flex shrink-0 items-center gap-1 text-[12.5px] font-medium text-ink">
              <Star className="h-3.5 w-3.5 fill-accent text-accent" aria-hidden />
              {(property.averageRating ?? 4.8).toFixed(1)}
              <span className="text-muted-foreground">({property.reviewCount})</span>
            </span>
          )}
        </div>

        <p className="mt-1 inline-flex items-center gap-1 text-[13px] text-muted-foreground">
          <MapPin className="h-3.5 w-3.5" aria-hidden />
          {property.city}
        </p>

        {amenities.length > 0 && (
          <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[12px] text-muted-foreground">
            {amenities.map((a, idx) => (
              <span key={a} className="inline-flex items-center gap-1">
                <AmenityIcon id={property.amenityIds?.[idx] || "spa"} />
                {a}
              </span>
            ))}
          </div>
        )}

        {/* Bottom price and action: shrink-0 and whitespace-nowrap prevent button text wrapping */}
        <div className="mt-auto flex items-center justify-between gap-3 border-t border-border/40 pt-4">
          <div className="min-w-0 flex-1">
            <div className="truncate text-[16px] font-semibold tracking-tight text-ink sm:text-[17px]">
              {vndCurrency.format(property.price)}
            </div>
            <div className="text-[11.5px] text-muted-foreground">/ đêm</div>
          </div>
          <Link
            to={detailUrl}
            className="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-full bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary-mid active:scale-95"
          >
            Xem chi tiết
          </Link>
        </div>
      </div>
    </motion.article>
  );
}
