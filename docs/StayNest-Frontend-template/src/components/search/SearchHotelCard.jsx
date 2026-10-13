import { Heart, Star, Wifi, Waves, Coffee, Sparkles, MapPin } from "lucide-react";
import { motion } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { toast } from "sonner";
import { useToggleWishlist, useWishlistIds } from "@/hooks/queries/useWishlist";

const currency = new Intl.NumberFormat("en-IN", {
  style: "currency",
  currency: "INR",
  maximumFractionDigits: 0,
});

const AMENITY_ICON = {
  "wi-fi": Wifi,
  wifi: Wifi,
  pool: Waves,
  breakfast: Coffee,
  spa: Sparkles,
};

function amenityIcon(name) {
  const key = name.toLowerCase();
  const Icon = AMENITY_ICON[key] ?? Sparkles;
  return <Icon className="h-3.5 w-3.5" aria-hidden />;
}

const FALLBACK_HOTEL_PHOTOS = [
  "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=1200&q=80&fit=crop",
];

export function SearchHotelCard({ hotel, context, view = "grid" }) {
  const { ids } = useWishlistIds();
  const toggle = useToggleWishlist();
  const fav = ids.has(hotel.id);
  const [photoIndex, setPhotoIndex] = useState(0);
  const [hovered, setHovered] = useState(false);
  const rawPhotos = hotel.photos ?? [];
  const photos = rawPhotos.length > 0 ? rawPhotos : FALLBACK_HOTEL_PHOTOS;
  const amenities = (hotel.amenities ?? []).slice(0, 4);
  const intervalRef = useRef(null);
  const isList = view === "list";

  useEffect(() => {
    if (hovered && photos.length > 1) {
      intervalRef.current = setInterval(() => {
        setPhotoIndex((i) => (i + 1) % photos.length);
      }, 1200);
    }
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
      intervalRef.current = null;
    };
  }, [hovered, photos.length]);

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
      <div className={[
        "relative overflow-hidden bg-surface shrink-0",
        isList ? "w-full md:w-[320px] aspect-[16/10]" : "aspect-[16/11] w-full"
      ].join(" ")}>
        {photos.length > 0 ? (
          photos.map((src, i) => (
            <img
              key={src + i}
              src={src}
              alt={hotel.name}
              loading="lazy"
              onError={(e) => {
                e.currentTarget.onerror = null;
                e.currentTarget.src = FALLBACK_HOTEL_PHOTOS[i % FALLBACK_HOTEL_PHOTOS.length];
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
          aria-label={fav ? "Remove from wishlist" : "Save to wishlist"}
          aria-pressed={fav}
          disabled={toggle.isLoading}
          onClick={async () => {
            try {
              await toggle.mutateAsync({ hotelId: hotel.id, next: !fav });
            } catch (e) {
              toast.error(e?.message ?? "Couldn't update wishlist.");
            }
          }}
          className="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-background/95 text-ink shadow-sm transition-transform duration-200 hover:scale-110 active:scale-95 disabled:opacity-60"
        >
          <Heart className={["h-4 w-4", fav ? "fill-primary text-primary" : ""].join(" ")} strokeWidth={1.6} aria-hidden />
        </button>
      </div>

      <div className="flex flex-1 flex-col p-4">
        <div className="flex items-start justify-between gap-3">
          <h3 className="min-w-0 truncate text-[16px] font-medium text-ink">{hotel.name}</h3>
          {hotel.reviewCount > 0 && (
            <span className="inline-flex shrink-0 items-center gap-1 text-[12.5px] font-medium text-ink">
              <Star className="h-3.5 w-3.5 fill-accent text-accent" aria-hidden />
              {(hotel.averageRating ?? 4.5).toFixed(1)}
              <span className="text-muted-foreground">({hotel.reviewCount})</span>
            </span>
          )}
        </div>

        <p className="mt-1 inline-flex items-center gap-1 text-[12.5px] text-muted-foreground">
          <MapPin className="h-3 w-3" aria-hidden />
          {hotel.city}
        </p>

        {amenities.length > 0 && (
          <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[11.5px] text-muted-foreground">
            {amenities.map((a) => (
              <span key={a} className="inline-flex items-center gap-1">
                {amenityIcon(a)}
                {a}
              </span>
            ))}
          </div>
        )}

        <div className="mt-4 flex items-end justify-between gap-3 pt-2">
          <div>
            <div className="text-[17px] font-semibold text-ink">{currency.format(hotel.price)}</div>
            <div className="text-[11px] text-muted-foreground">/ night</div>
          </div>
          <Link
            to={`/hotels/${hotel.id}?checkIn=${context?.startDate ?? ""}&checkOut=${context?.endDate ?? ""}&rooms=${context?.roomsCount ?? 1}`}
            className="rounded-full bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground transition-colors hover:bg-primary-mid"
          >
            View Details
          </Link>
        </div>
      </div>
    </motion.article>
  );
}
