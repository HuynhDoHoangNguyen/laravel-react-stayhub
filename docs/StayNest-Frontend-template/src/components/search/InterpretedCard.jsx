import { CheckCircle2 } from "lucide-react";
function Chip({ children }) {
    return (<span className="rounded-full border border-border bg-background px-3.5 py-1.5 text-[12.5px] text-ink">
      {children}
    </span>);
}
/** "Understood!" card with grouped interpretation chips (matches reference). */
export function InterpretedCard({ interpreted, missing }) {
    if (!interpreted)
        return null;
    const has = interpreted.city ||
        interpreted.startDate ||
        interpreted.endDate ||
        interpreted.roomsCount;
    if (!has && !(missing && missing.length))
        return null;
    return (<div className="rounded-3xl bg-card p-5 shadow-[0_10px_40px_-25px_rgba(17,26,19,0.15)] ring-1 ring-black/[0.04]">
      <div className="flex items-center justify-center gap-2 text-[13px] font-medium text-ink">
        <CheckCircle2 className="h-4 w-4 text-primary" aria-hidden/>
        Understood! I found your preferences below.
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[110px_minmax(0,1fr)_110px_minmax(0,1fr)]">
        <div className="text-[10.5px] font-semibold uppercase tracking-[0.14em] text-muted-foreground sm:self-center">
          Interpreted
        </div>
        <div className="flex flex-wrap gap-2">
          {interpreted.city && <Chip>{interpreted.city}</Chip>}
          {interpreted.startDate && <Chip>{interpreted.startDate}</Chip>}
          {interpreted.endDate && <Chip>{interpreted.endDate}</Chip>}
          {(interpreted.roomsCount || interpreted.adults) && (<Chip>
              {interpreted.roomsCount ?? 1} Room
              {interpreted.roomsCount === 1 ? "" : "s"}
              {interpreted.adults ? ` · ${interpreted.adults} Guests` : ""}
            </Chip>)}
        </div>
        <div className="text-[10.5px] font-semibold uppercase tracking-[0.14em] text-muted-foreground sm:self-center">
          Missing
        </div>
        <div className="text-[13px] text-muted-foreground">
          {missing && missing.length ? missing.join(", ") : "—"}
        </div>
      </div>
    </div>);
}
