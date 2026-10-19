import {
  Wifi,
  Waves,
  Coffee,
  Sparkles,
  UtensilsCrossed,
  Dumbbell,
  Car,
  Bell,
  Palmtree,
  Tv,
  Flame,
  Wind,
  ShieldCheck,
} from "lucide-react";

const ICON_MAP = {
  // English keys
  "wifi": Wifi,
  "wi-fi": Wifi,
  "free wi-fi": Wifi,
  "pool": Waves,
  "infinity pool": Waves,
  "beach access": Waves,
  "breakfast": Coffee,
  "coffee": Coffee,
  "spa": Sparkles,
  "dining": UtensilsCrossed,
  "fine dining": UtensilsCrossed,
  "restaurant": UtensilsCrossed,
  "fitness": Dumbbell,
  "gym": Dumbbell,
  "fitness centre": Dumbbell,
  "parking": Car,
  "airport transfer": Car,
  "concierge": Bell,
  "24h concierge": Bell,

  // Vietnamese keywords
  "hồ bơi": Waves,
  "bể bơi": Waves,
  "trị liệu": Sparkles,
  "nhà hàng": UtensilsCrossed,
  "ẩm thực": UtensilsCrossed,
  "ăn uống": UtensilsCrossed,
  "cà phê": Coffee,
  "bãi đỗ xe": Car,
  "đưa đón": Car,
  "cano": Waves,
  "thể hình": Dumbbell,
  "lò sưởi": Flame,
  "điều hòa": Wind,
  "an ninh": ShieldCheck,
  "truyền hình": Tv,
};

function pickIcon(label) {
  if (!label || typeof label !== "string") return Palmtree;
  const lower = label.toLowerCase();
  for (const [key, Icon] of Object.entries(ICON_MAP)) {
    if (lower.includes(key)) {
      return Icon;
    }
  }
  return Palmtree;
}

export function PropertyAmenities({ amenities = [] }) {
  if (!amenities || amenities.length === 0) return null;

  return (
    <section className="rounded-3xl border border-border/70 bg-card/60 p-6 shadow-sm md:p-8">
      <h2 className="font-display text-xl text-primary md:text-2xl">
        Tiện ích & Dịch vụ nổi bật
      </h2>
      <p className="mt-1 text-[13px] text-muted-foreground">
        Những tiện nghi cao cấp đặc trưng dành cho mỗi kỳ lưu trú.
      </p>

      <div className="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
        {amenities.map((a) => {
          const Icon = pickIcon(a);
          return (
            <div
              key={a}
              className="flex items-center gap-3 rounded-2xl border border-border/60 bg-background px-3.5 py-3 text-[13.5px] text-ink transition hover:border-primary/40"
            >
              <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">
                <Icon className="h-4 w-4" strokeWidth={1.8} aria-hidden />
              </span>
              <span className="truncate font-medium">{a}</span>
            </div>
          );
        })}
      </div>
    </section>
  );
}
