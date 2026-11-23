import { Sparkles, MapPin, Calendar, BedDouble, Send } from "lucide-react";
import { useState } from "react";
import { SORT_OPTIONS } from "./FilterSidebar";
const friendlyMissingNames = {
    startDate: "check-in date",
    endDate: "check-out date",
    roomsCount: "rooms",
    adults: "guests",
    city: "destination",
    minPrice: "min price",
    maxPrice: "max price",
    minRating: "min rating",
    sortBy: "sort preference",
};
function MissingFields({ missing }) {
    const labels = missing.map((f) => friendlyMissingNames[f] ?? f);
    return (<div className="mr-6 rounded-2xl bg-accent-pale/40 px-3.5 py-2.5 text-[12.5px] text-ink">
      I still need: <span className="font-medium">{labels.join(", ")}</span>.
      Add them in the search bar to continue.
    </div>);
}
function ConfirmSummary({ interpreted }) {
    if (!interpreted)
        return null;
    return (<div className="mr-6 space-y-2.5 rounded-2xl bg-accent-pale/40 px-4 py-3 text-[12.5px] text-ink">
      <p className="font-medium">Please confirm or update:</p>
      {interpreted.city && (<p className="inline-flex items-center gap-2">
          <MapPin className="h-3.5 w-3.5 text-primary" aria-hidden/>
          Destination: {interpreted.city}
        </p>)}
      {interpreted.startDate && (<p className="inline-flex items-center gap-2">
          <Calendar className="h-3.5 w-3.5 text-primary" aria-hidden/>
          Check-in: {interpreted.startDate}
        </p>)}
      {interpreted.endDate && (<p className="inline-flex items-center gap-2">
          <Calendar className="h-3.5 w-3.5 text-primary" aria-hidden/>
          Check-out: {interpreted.endDate}
        </p>)}
      {(interpreted.roomsCount || interpreted.adults) && (<p className="inline-flex items-center gap-2">
          <BedDouble className="h-3.5 w-3.5 text-primary" aria-hidden/>
          Rooms: {interpreted.roomsCount ?? 1} Room
          {interpreted.roomsCount === 1 ? "" : "s"}
          {interpreted.adults ? ` · ${interpreted.adults} Guests` : ""}
        </p>)}
    </div>);
}
export function AssistantPanel({ messages, interpreted, missing, onAsk, isThinking, highlight, sortBy, onSortChange, minPrice, maxPrice, onPriceApply, }) {
    const [minLocal, setMinLocal] = useState(minPrice?.toString() ?? "");
    const [maxLocal, setMaxLocal] = useState(maxPrice?.toString() ?? "");
    const [input, setInput] = useState("");
    const handleSubmit = (e) => {
        e.preventDefault();
        const q = input.trim();
        if (!q)
            return;
        onAsk(q);
        setInput("");
    };
    const suggestions = [
        "Cheapest hotels",
        "5-star resorts",
        "Near the beach",
    ];
    return (<aside className="z-20 w-full lg:sticky lg:top-[240px]">
      <div id="staynest-assistant" className={[
            "flex flex-col gap-4 rounded-3xl bg-card p-5 shadow-[0_10px_40px_-25px_rgba(17,26,19,0.2)] ring-1 ring-black/[0.04] transition-all duration-500",
            highlight
                ? "ring-2 ring-primary/70 shadow-[0_0_0_6px_rgba(58,90,64,0.12),0_20px_60px_-20px_rgba(17,26,19,0.35)] scale-[1.01]"
                : "",
        ].join(" ")}>

    <header className="shrink-0 flex items-center gap-2">
          <div className="grid h-8 w-8 place-items-center rounded-full bg-primary/10 text-primary">
            <Sparkles className="h-4 w-4" aria-hidden/>
          </div>
          <h3 className="text-[15px] font-semibold text-ink">
            StayNest Assistant
          </h3>
        </header>

        <div className="max-h-[360px] space-y-3 overflow-y-auto scroll-pt-4 pt-6 pb-4 pr-1">
          {messages.length === 0 && !interpreted && (<p className="text-[13px] leading-relaxed text-muted-foreground">
              Ask me anything — "a luxury stay in Mumbai for two under ₹5,000".
            </p>)}
          {messages.map((m) => (<div key={m.id} className={m.role === "user"
                ? "ml-6 flex justify-end"
                : "mr-6 flex items-start gap-2"}>
              {m.role === "assistant" && (<div className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">
                  <Sparkles className="h-3 w-3" aria-hidden/>
                </div>)}
              <div className={[
                "rounded-2xl px-3.5 py-2.5 text-[13px] leading-relaxed",
                m.role === "user"
                    ? "bg-primary text-primary-foreground"
                    : "bg-accent-pale/40 text-ink",
            ].join(" ")}>
                {m.text}
              </div>
            </div>))}
          {interpreted && <ConfirmSummary interpreted={interpreted}/>}
          {missing && missing.length > 0 && (<MissingFields missing={missing}/>)}
          {isThinking && (<div className="mr-6 rounded-2xl bg-accent-pale/40 px-3.5 py-2.5 text-[13px] text-muted-foreground">
              Thinking…
            </div>)}
        </div>

        {/* Refine card */}
        <div className="rounded-2xl bg-surface/50 p-4 ring-1 ring-black/[0.04]">
          <p className="text-[13px] font-semibold text-ink">Refine your search</p>
          <label className="mt-3 block text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
            Sort by
          </label>
          <select value={sortBy} onChange={(e) => onSortChange(e.target.value)} className="mt-1.5 w-full appearance-none rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-none">
            {SORT_OPTIONS.map((s) => (<option key={s.value} value={s.value}>
                {s.label}
              </option>))}
          </select>

          <div className="mt-3 grid grid-cols-2 gap-2">
            <label className="block">
              <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                Min price (₹)
              </span>
              <input type="number" value={minLocal} onChange={(e) => setMinLocal(e.target.value)} placeholder="₹1,000" className="mt-1.5 w-full rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-none"/>
            </label>
            <label className="block">
              <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                Max price (₹)
              </span>
              <input type="number" value={maxLocal} onChange={(e) => setMaxLocal(e.target.value)} placeholder="₹50,000+" className="mt-1.5 w-full rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-none"/>
            </label>
          </div>

          <button type="button" onClick={() => onPriceApply(minLocal ? Number(minLocal) : undefined, maxLocal ? Number(maxLocal) : undefined)} className="mt-3 w-full rounded-full bg-primary py-2.5 text-[12.5px] font-medium text-primary-foreground transition-colors hover:bg-primary-mid">
            Apply
          </button>
        </div>

        <form onSubmit={handleSubmit} className="flex items-center gap-2 rounded-full bg-accent-pale/40 pl-4 pr-1 py-1 ring-1 ring-black/[0.04] focus-within:ring-primary/40">
          <input type="text" value={input} onChange={(e) => setInput(e.target.value)} placeholder="Ask anything about your stay…" className="min-w-0 flex-1 bg-transparent py-2 text-[13px] text-ink placeholder:text-muted-foreground/80 focus:outline-none"/>
          <button type="submit" aria-label="Send" disabled={!input.trim()} className="grid h-9 w-9 place-items-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary-mid disabled:opacity-40">
            <Send className="h-3.5 w-3.5" aria-hidden/>
          </button>
        </form>

        <div className="flex flex-wrap gap-2">
          {suggestions.map((s) => (<button key={s} type="button" onClick={() => onAsk(s)} className="rounded-full border border-border bg-background px-3 py-1.5 text-[11.5px] text-ink transition hover:border-primary hover:text-primary">
              {s}
            </button>))}
        </div>
      </div>
    </aside>);
}
