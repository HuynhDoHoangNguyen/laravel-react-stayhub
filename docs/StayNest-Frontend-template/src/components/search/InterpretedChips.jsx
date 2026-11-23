import { Sparkles } from "lucide-react";
const LABELS = {
    city: "Destination",
    startDate: "Check-in date",
    endDate: "Check-out date",
    roomsCount: "Rooms",
    adults: "Guests",
};
/** Read-only summary of what the backend understood from the NL query. */
export function InterpretedChips({ interpreted, missing }) {
    if (!interpreted)
        return null;
    const chips = [];
    if (interpreted.city)
        chips.push({ key: "city", label: interpreted.city });
    if (interpreted.startDate && interpreted.endDate)
        chips.push({
            key: "dates",
            label: `${interpreted.startDate} → ${interpreted.endDate}`,
        });
    if (interpreted.adults)
        chips.push({ key: "guests", label: `${interpreted.adults} guests` });
    if (interpreted.roomsCount)
        chips.push({ key: "rooms", label: `${interpreted.roomsCount} rooms` });
    if (interpreted.minRating)
        chips.push({ key: "rating", label: `${interpreted.minRating}+ rating` });
    if (interpreted.maxPrice)
        chips.push({ key: "max", label: `Under $${interpreted.maxPrice}` });
    if (chips.length === 0 && (!missing || missing.length === 0))
        return null;
    return (<div className="rounded-2xl bg-accent-pale/40 p-4 ring-1 ring-black/[0.04]">
      <div className="flex items-center gap-2 text-[12px] font-medium uppercase tracking-wider text-primary">
        <Sparkles className="h-3.5 w-3.5" aria-hidden/>
        StayNest understood
      </div>
      {chips.length > 0 && (<div className="mt-3 flex flex-wrap gap-2">
          {chips.map((c) => (<span key={c.key} className="rounded-full bg-background px-3 py-1 text-[12px] text-ink ring-1 ring-black/[0.04]">
              {c.label}
            </span>))}
        </div>)}
      {missing && missing.length > 0 && (<p className="mt-3 text-[12.5px] text-muted-foreground">
          Still need: {missing.map((m) => LABELS[m]).join(", ")}.
        </p>)}
    </div>);
}
