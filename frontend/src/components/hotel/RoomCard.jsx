import { useEffect, useRef, useState } from "react";
import { Check, Users, Maximize2 } from "lucide-react";

const vndFormatter = new Intl.NumberFormat("vi-VN", {
  style: "currency",
  currency: "VND",
  maximumFractionDigits: 0,
});

const FALLBACK_ROOM_PHOTOS = [
  "https://images.unsplash.com/photo-1590490360182-c33d57733427?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=1200&q=80&fit=crop",
];

export function RoomCard({ room, nights = 1, selected = false, onSelect }) {
  const rawPhotos = room.photos?.filter((p) => p && p !== "/placeholder.svg") ?? [];
  const photos =
    rawPhotos.length > 0
      ? rawPhotos
      : [FALLBACK_ROOM_PHOTOS[(room.id ?? 0) % FALLBACK_ROOM_PHOTOS.length]];

  const [activePhotoIdx, setActivePhotoIdx] = useState(0);
  const [isHovered, setIsHovered] = useState(false);
  const timerRef = useRef(null);

  useEffect(() => {
    if (isHovered && photos.length > 1) {
      timerRef.current = setInterval(() => {
        setActivePhotoIdx((prev) => (prev + 1) % photos.length);
      }, 1400);
    }
    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
        timerRef.current = null;
      }
    };
  }, [isHovered, photos.length]);

  const nightlyBase = Math.round(Number(room.price) || 0);
  const validNights = nights > 0 ? nights : 1;
  const totalStay = nightlyBase * validNights;

  return (
    <article
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => {
        setIsHovered(false);
        setActivePhotoIdx(0);
      }}
      className={[
        "group flex flex-col overflow-hidden rounded-3xl border bg-card transition-all duration-300",
        selected
          ? "border-primary shadow-[0_12px_36px_-12px_rgba(26,46,32,0.35)] ring-2 ring-primary/40"
          : "border-border/70 hover:border-primary/40 hover:shadow-[0_12px_36px_-16px_rgba(26,46,32,0.25)]",
      ].join(" ")}
    >
      {/* Room Photo Gallery Preview */}
      <div className="relative aspect-[16/10] overflow-hidden bg-muted">
        <img
          src={photos[activePhotoIdx]}
          alt={room.name || room.roomTypeName || "Phòng nghỉ"}
          onError={(e) => {
            e.currentTarget.onerror = null;
            e.currentTarget.src =
              FALLBACK_ROOM_PHOTOS[(room.id ?? 0) % FALLBACK_ROOM_PHOTOS.length];
          }}
          className="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
        />

        {selected && (
          <span className="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-primary px-3 py-1 text-[11.5px] font-medium text-primary-foreground shadow-sm">
            <Check className="h-3.5 w-3.5" /> Đã chọn
          </span>
        )}

        {photos.length > 1 && (
          <div className="absolute bottom-3 right-3 rounded-full bg-black/60 px-2.5 py-0.5 text-[10.5px] font-medium text-white backdrop-blur">
            {activePhotoIdx + 1} / {photos.length}
          </div>
        )}
      </div>

      {/* Room Details Body */}
      <div className="flex flex-1 flex-col gap-3 p-5 md:p-6">
        <div>
          {room.roomTypeName && (
            <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
              {room.roomTypeName}
            </span>
          )}
          <h3 className="font-display text-lg text-primary md:text-xl">
            {room.name}
          </h3>
        </div>

        {/* Room specs: capacity & area */}
        <div className="flex flex-wrap items-center gap-3 text-[12px] text-muted-foreground">
          {room.capacity && (
            <span className="inline-flex items-center gap-1.5">
              <Users className="h-3.5 w-3.5 text-primary/70" />
              Tối đa {room.capacity} khách
            </span>
          )}
          {room.area && (
            <span className="inline-flex items-center gap-1.5">
              <Maximize2 className="h-3 w-3 text-primary/70" />
              {room.area} m²
            </span>
          )}
        </div>

        {/* Room amenities bullets */}
        {room.amenities?.length > 0 && (
          <ul className="flex flex-wrap gap-x-4 gap-y-1.5 text-[12.5px] text-muted-foreground">
            {room.amenities.slice(0, 4).map((a) => (
              <li key={a} className="inline-flex items-center gap-1.5">
                <span className="h-1 w-1 rounded-full bg-primary/60" />
                {a}
              </li>
            ))}
          </ul>
        )}

        {/* Footer: Price & Select action */}
        <div className="mt-auto flex items-end justify-between gap-3 border-t border-border/50 pt-4">
          <div>
            <div className="text-[20px] font-semibold text-ink">
              {vndFormatter.format(nightlyBase)}
            </div>
            <div className="text-[11.5px] text-muted-foreground">
              mỗi đêm{nights > 0 && ` · ${vndFormatter.format(totalStay)} tổng ${nights} đêm`}
            </div>
          </div>

          <button
            type="button"
            onClick={onSelect}
            className={[
              "rounded-full px-5 py-2.5 text-[13px] font-medium transition-all active:scale-95",
              selected
                ? "bg-primary text-primary-foreground shadow-sm"
                : "border border-primary/40 text-primary hover:bg-primary hover:text-primary-foreground",
            ].join(" ")}
          >
            {selected ? "Đã chọn" : "Chọn phòng này"}
          </button>
        </div>
      </div>
    </article>
  );
}
