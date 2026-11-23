import { Wifi, Waves, Coffee, Sparkles, UtensilsCrossed, Dumbbell, Car, Bell, PalmtreeIcon } from "lucide-react";
const ICONS = {
    "wi-fi": Wifi, "wifi": Wifi, "free wi-fi": Wifi,
    "pool": Waves, "infinity pool": Waves, "beach access": Waves,
    "breakfast": Coffee, "coffee": Coffee,
    "spa": Sparkles,
    "fine dining": UtensilsCrossed, "restaurant": UtensilsCrossed,
    "fitness centre": Dumbbell, "gym": Dumbbell,
    "airport transfer": Car, "parking": Car,
    "24h concierge": Bell, "concierge": Bell,
};
function pick(name) {
    return ICONS[name.toLowerCase()] ?? PalmtreeIcon;
}
export function HotelAmenities({ amenities }) {
    if (!amenities?.length)
        return null;
    return (<section className="rounded-2xl border border-border/70 bg-background/60 p-6 md:p-8">
      <h2 className="font-display text-xl text-primary md:text-2xl">What this stay offers</h2>
      <p className="mt-1 text-[13px] text-muted-foreground">Signature amenities included for every guest.</p>
      <div className="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
        {amenities.map((a) => {
            const Icon = pick(a);
            return (<div key={a} className="flex items-center gap-3 rounded-xl border border-border/60 bg-background px-3.5 py-3 text-[13.5px] text-ink">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-primary/8 text-primary">
                <Icon className="h-4 w-4" strokeWidth={1.6}/>
              </span>
              <span className="truncate">{a}</span>
            </div>);
        })}
      </div>
    </section>);
}
