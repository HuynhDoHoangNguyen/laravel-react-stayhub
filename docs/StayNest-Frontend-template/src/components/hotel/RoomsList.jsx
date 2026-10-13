import { useEffect, useRef, useState } from "react";
import { Check } from "lucide-react";
const currency = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 });
const FALLBACK_ROOM_PHOTOS = [
  "https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1590490360182-c33d57733427?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=1200&q=80&fit=crop",
  "https://images.unsplash.com/photo-1591088398332-8a7791972843?w=1200&q=80&fit=crop",
];

export function RoomsList({ rooms, nights, selectedRoomId, onSelect }) {
    return (<section className="space-y-4">
      <div className="flex items-end justify-between gap-4">
        <div>
          <h2 className="font-display text-xl text-primary md:text-2xl">Choose your room</h2>
          <p className="mt-1 text-[13px] text-muted-foreground">
            Prices reflect your selected dates{nights > 0 && ` — ${nights} night${nights === 1 ? "" : "s"}`}.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {rooms.map((r) => (<RoomCard key={r.id} room={r} nights={nights} selected={selectedRoomId === r.id} onSelect={() => onSelect(r)}/>))}
      </div>
    </section>);
}
function RoomCard({ room, nights, selected, onSelect }) {
    const rawPhotos = room.photos?.filter((p) => p && p !== "/placeholder.svg") ?? [];
    const photos = rawPhotos.length > 0 ? rawPhotos : [FALLBACK_ROOM_PHOTOS[(room.id ?? 0) % FALLBACK_ROOM_PHOTOS.length]];
    const [i, setI] = useState(0);
    const [hover, setHover] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
        if (hover && photos.length > 1) {
            ref.current = setInterval(() => setI((v) => (v + 1) % photos.length), 1400);
        }
        return () => { if (ref.current)
            clearInterval(ref.current); ref.current = null; };
    }, [hover, photos.length]);
    const nightlyBase = Math.round(room.price);
    const totalStay = nightlyBase * (nights > 0 ? nights : 1);
    return (<article onMouseEnter={() => setHover(true)} onMouseLeave={() => { setHover(false); setI(0); }} className={[
            "group flex flex-col overflow-hidden rounded-2xl border bg-background transition-all",
            selected ? "border-primary shadow-[0_10px_30px_-12px_rgba(26,46,32,0.35)]" : "border-border/70 hover:border-primary/40 hover:shadow-[0_10px_30px_-16px_rgba(26,46,32,0.28)]",
        ].join(" ")}>
      <div className="relative aspect-[16/10] overflow-hidden bg-muted">
        <img
          src={photos[i]}
          alt={room.type}
          onError={(e) => {
            e.currentTarget.onerror = null;
            e.currentTarget.src = FALLBACK_ROOM_PHOTOS[(room.id ?? 0) % FALLBACK_ROOM_PHOTOS.length];
          }}
          className="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
        />
        {selected && (<span className="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-[11px] font-medium text-primary-foreground">
            <Check className="h-3 w-3"/> Selected
          </span>)}
      </div>
      <div className="flex flex-1 flex-col gap-3 p-5">
        <h3 className="font-display text-lg text-primary">{room.type}</h3>
        {room.amenities?.length > 0 && (<ul className="flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] text-muted-foreground">
            {room.amenities.slice(0, 4).map((a) => (<li key={a} className="inline-flex items-center gap-1.5">
                <span className="h-1 w-1 rounded-full bg-primary/50"/>
                {a}
              </li>))}
          </ul>)}
        <div className="mt-auto flex items-end justify-between gap-3 pt-2">
          <div>
            <div className="text-[20px] font-semibold text-ink">{currency.format(nightlyBase)}</div>
            <div className="text-[11px] text-muted-foreground">
              per night{nights > 0 && ` · ${currency.format(totalStay)} total`}
            </div>
          </div>
          <button type="button" onClick={onSelect} className={[
            "rounded-full px-4 py-2 text-[12.5px] font-medium transition-colors",
            selected
                ? "bg-primary text-primary-foreground"
                : "border border-primary/40 text-primary hover:bg-primary hover:text-primary-foreground",
        ].join(" ")}>
            {selected ? "Selected" : "Select room"}
          </button>
        </div>
      </div>
    </article>);
}
