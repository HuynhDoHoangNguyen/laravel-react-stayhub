import { Heart, MapPin, Star } from "lucide-react";
import { useRef, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useImageRotation } from "@/hooks/useImageRotation";
import { useInViewport } from "@/hooks/useInViewport";

const vndCurrency = new Intl.NumberFormat("vi-VN", {
  style: "currency",
  currency: "VND",
  maximumFractionDigits: 0,
});

const FALLBACK_PROPERTY_PHOTOS = [
  "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=1200&q=80&fit=crop",
];

export function PropertyCard({ property }) {
  const [hovered, setHovered] = useState(false);
  const [loaded, setLoaded] = useState({});
  const [isSaved, setIsSaved] = useState(false);
  const cardRef = useRef(null);
  const inView = useInViewport(cardRef, 0.35);
  const navigate = useNavigate();

  const rawPhotos = property.photos ?? [];
  const photos = rawPhotos.length > 0 ? rawPhotos : FALLBACK_PROPERTY_PHOTOS;
  const index = useImageRotation({
    count: photos.length,
    intervalMs: 1800,
    paused: hovered || !inView,
  });
  const primaryAmenities = (property.amenities ?? []).slice(0, 2);

  const targetUrl = `/properties/${property.id}`;
  const safePrice =
    Number.isFinite(Number(property.price)) && Number(property.price) > 0
      ? Number(property.price)
      : 2500000;

  const handleToggleWishlist = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsSaved((prev) => !prev);
  };

  return (
    <Link
      to={targetUrl}
      ref={cardRef}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      onClick={(e) => {
        if (e.target.closest("[data-wishlist-btn]")) return;
        navigate(targetUrl);
      }}
      className="group relative block w-[300px] shrink-0 cursor-pointer sm:w-[330px]"
    >
      <div className="relative aspect-[4/3] overflow-hidden rounded-3xl bg-surface shadow-[0_10px_30px_-18px_rgba(17,26,19,0.35)] transition-shadow duration-500 group-hover:shadow-[0_30px_60px_-25px_rgba(17,26,19,0.45)]">
        {photos.length === 0 && <div className="shimmer h-full w-full" />}

        {photos.map((src, i) => (
          <img
            key={src + i}
            src={src}
            alt={`${property.name} — hình ảnh ${i + 1}`}
            loading={i === 0 ? "eager" : "lazy"}
            decoding="async"
            onLoad={() => setLoaded((s) => ({ ...s, [i]: true }))}
            onError={(e) => {
              e.currentTarget.onerror = null;
              e.currentTarget.src =
                FALLBACK_PROPERTY_PHOTOS[i % FALLBACK_PROPERTY_PHOTOS.length];
            }}
            className={[
              "pointer-events-none absolute inset-0 h-full w-full object-cover transition-all duration-[1200ms] ease-out",
              i === index ? "opacity-100 scale-[1.02]" : "opacity-0 scale-100",
              loaded[i] ? "" : "blur-md",
              "group-hover:scale-[1.06]",
            ].join(" ")}
            style={{ transitionProperty: "opacity, transform, filter" }}
          />
        ))}

        {property.reviewCount > 0 && (
          <div className="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full bg-background/95 px-2.5 py-1 text-[12px] font-medium text-ink shadow-sm">
            <Star className="h-3.5 w-3.5 fill-accent text-accent" aria-hidden />
            {(property.averageRating ?? 4.8).toFixed(1)}
            <span className="text-muted-foreground">({property.reviewCount})</span>
          </div>
        )}
      </div>

      <div className="mt-4 flex items-start justify-between gap-4 px-1">
        <div className="min-w-0">
          <h3 className="truncate text-[17px] font-medium text-ink transition-transform duration-500 group-hover:-translate-y-0.5">
            {property.name}
          </h3>
          <p className="mt-1 inline-flex items-center gap-1 text-[13px] text-muted-foreground">
            <MapPin className="h-3.5 w-3.5" aria-hidden />
            {property.city}
          </p>
        </div>
        <div className="text-right">
          <div className="text-[11px] uppercase tracking-wider text-muted-foreground">Giá từ</div>
          <div className="text-[16px] font-semibold text-ink">{vndCurrency.format(safePrice)}</div>
          <div className="text-[11px] text-muted-foreground">/đêm</div>
        </div>
      </div>

      {primaryAmenities.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 px-1 text-[12px] text-muted-foreground">
          {primaryAmenities.map((a) => (
            <span key={a}>· {a}</span>
          ))}
        </div>
      )}

      <div
        aria-hidden
        className="pointer-events-none mt-3 px-1 text-[13px] font-medium text-primary opacity-0 transition-opacity duration-300 group-hover:opacity-100"
      >
        Xem nơi nghỉ →
      </div>

      <button
        type="button"
        data-wishlist-btn
        aria-label={isSaved ? "Bỏ lưu khỏi mục yêu thích" : "Lưu vào mục yêu thích"}
        aria-pressed={isSaved}
        onClick={handleToggleWishlist}
        className="absolute right-4 top-4 z-10 grid h-9 w-9 place-items-center rounded-full bg-background/95 text-ink transition-transform duration-300 hover:scale-110 active:scale-95"
      >
        <Heart
          className={["h-4 w-4", isSaved ? "fill-primary text-primary" : ""].join(" ")}
          strokeWidth={1.6}
          aria-hidden
        />
      </button>
    </Link>
  );
}
