import { ChevronDown } from "lucide-react";
import { useState } from "react";

const RATINGS = [
  { label: "Any", value: undefined },
  { label: "3+", value: 3 },
  { label: "4+", value: 4 },
  { label: "4.5+", value: 4.5 },
  { label: "5", value: 5 },
];

export const AMENITIES = [
  { id: "wifi", label: "Wi-Fi", keywords: ["wifi", "wi-fi"] },
  { id: "pool", label: "Pool", keywords: ["pool"] },
  { id: "spa", label: "Spa & Wellness", keywords: ["spa"] },
  { id: "dining", label: "Restaurant & Dining", keywords: ["restaurant", "dining"] },
  { id: "fitness", label: "Fitness Center", keywords: ["fitness", "gym"] },
  { id: "parking", label: "Parking & Valet", keywords: ["parking", "valet"] },
];

export const PROPERTY_TYPES = [
  { id: "Hotel", label: "Hotel" },
  { id: "Resort", label: "Resort" },
];

export function matchesPropertyType(h, type) {
  const name = (h.name || "").toLowerCase();
  const hAmenities = (h.amenities || []).map((x) => x.toLowerCase());
  const isResortProp =
    name.includes("resort") ||
    name.includes("villas") ||
    name.includes("palace") ||
    name.includes("retreat") ||
    name.includes("spa") ||
    hAmenities.some((a) => a.includes("pool") || a.includes("spa"));

  if (type === "Resort") return isResortProp;
  if (type === "Hotel") return true;
  return true;
}

function Section({ title, children, defaultOpen = true }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <section className="border-b border-border/60 pb-5 last:border-b-0 last:pb-0">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between text-left"
      >
        <span className="text-[13px] font-semibold uppercase tracking-[0.14em] text-ink">
          {title}
        </span>
        <ChevronDown
          className={[
            "h-4 w-4 text-muted-foreground transition-transform duration-300",
            open ? "" : "-rotate-90",
          ].join(" ")}
          aria-hidden
        />
      </button>
      <div
        className={[
          "grid transition-all duration-300",
          open ? "mt-4 grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0",
        ].join(" ")}
      >
        <div className="min-h-0 overflow-hidden">{children}</div>
      </div>
    </section>
  );
}

export function FilterSidebar({ value, onChange, onReset, availableHotels = [] }) {
  const set = (k, v) => onChange({ ...value, [k]: v });
  const min = value.minPrice ?? 50;
  const max = value.maxPrice ?? 1000;

  const selectedAmenities = value.selectedAmenities || [];
  const selectedPropertyTypes = value.selectedPropertyTypes || [];

  const toggleAmenity = (id) => {
    const next = selectedAmenities.includes(id)
      ? selectedAmenities.filter((x) => x !== id)
      : [...selectedAmenities, id];
    set("selectedAmenities", next);
  };

  const togglePropertyType = (id) => {
    const next = selectedPropertyTypes.includes(id)
      ? selectedPropertyTypes.filter((x) => x !== id)
      : [...selectedPropertyTypes, id];
    set("selectedPropertyTypes", next);
  };

  const handleApply = () => {
    const el = document.getElementById("results-header");
    if (el) {
      el.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  return (
    <aside className="w-full space-y-6 rounded-3xl bg-card p-6 shadow-[0_10px_40px_-25px_rgba(17,26,19,0.2)] ring-1 ring-black/[0.04] lg:sticky lg:top-[240px]">
      <div className="flex items-center justify-between">
        <h3 className="text-[13px] font-semibold uppercase tracking-[0.16em] text-ink">
          Filters
        </h3>
        <button
          type="button"
          onClick={onReset}
          className="text-[12px] text-accent underline-offset-4 transition hover:underline"
        >
          Clear all
        </button>
      </div>

      <Section title="Price per night">
        <div className="space-y-4">
          <input
            type="range"
            min={500}
            max={50000}
            step={500}
            value={max}
            onChange={(e) => set("maxPrice", Number(e.target.value))}
            className="w-full accent-[color:var(--primary)]"
          />
          <div className="flex justify-between text-[11.5px] text-muted-foreground font-medium">
            <span>₹{min.toLocaleString("en-IN")}</span>
            <span>₹{max.toLocaleString("en-IN")}+</span>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <label className="rounded-xl border border-border bg-background px-3 py-2">
              <div className="text-[10px] uppercase tracking-wider text-muted-foreground">
                Min (₹)
              </div>
              <input
                type="number"
                inputMode="numeric"
                placeholder="₹1,000"
                value={value.minPrice ?? ""}
                onChange={(e) =>
                  set("minPrice", e.target.value ? Number(e.target.value) : undefined)
                }
                className="w-full bg-transparent text-[13px] font-medium text-ink focus:outline-none"
              />
            </label>
            <label className="rounded-xl border border-border bg-background px-3 py-2">
              <div className="text-[10px] uppercase tracking-wider text-muted-foreground">
                Max (₹)
              </div>
              <input
                type="number"
                inputMode="numeric"
                placeholder="₹50,000+"
                value={value.maxPrice ?? ""}
                onChange={(e) =>
                  set("maxPrice", e.target.value ? Number(e.target.value) : undefined)
                }
                className="w-full bg-transparent text-[13px] font-medium text-ink focus:outline-none"
              />
            </label>
          </div>
        </div>
      </Section>

      <Section title="Guest Rating">
        <div className="flex flex-wrap gap-2">
          {RATINGS.map((r) => {
            const active = value.minRating === r.value;
            return (
              <button
                key={r.label}
                type="button"
                onClick={() => set("minRating", r.value)}
                className={[
                  "rounded-full border px-3.5 py-1.5 text-[12px] transition-colors",
                  active
                    ? "border-primary bg-primary text-primary-foreground"
                    : "border-border bg-background text-ink hover:border-primary",
                ].join(" ")}
              >
                {r.label}
              </button>
            );
          })}
        </div>
      </Section>

      <Section title="Amenities">
        <ul className="space-y-2.5">
          {AMENITIES.map((a) => {
            const isChecked = selectedAmenities.includes(a.id);
            const count = availableHotels.filter((h) => {
              const hAmenities = (h.amenities || []).map((x) => x.toLowerCase());
              return a.keywords.some((k) => hAmenities.some((x) => x.includes(k)));
            }).length;

            return (
              <li key={a.id} className="flex items-center justify-between">
                <label className="inline-flex cursor-pointer items-center gap-2 text-[13px] text-ink">
                  <input
                    type="checkbox"
                    checked={isChecked}
                    onChange={() => toggleAmenity(a.id)}
                    className="h-4 w-4 rounded border-border accent-[color:var(--primary)]"
                  />
                  {a.label}
                </label>
                <span className="text-[11.5px] text-muted-foreground">
                  {count > 0 ? count : (availableHotels.length > 0 ? 0 : "-")}
                </span>
              </li>
            );
          })}
        </ul>
      </Section>

      <Section title="Property Type">
        <ul className="space-y-2.5">
          {PROPERTY_TYPES.map((p) => {
            const isChecked = selectedPropertyTypes.includes(p.id);
            const count = availableHotels.filter((h) => matchesPropertyType(h, p.id)).length;

            return (
              <li key={p.id} className="flex items-center justify-between">
                <label className="inline-flex cursor-pointer items-center gap-2 text-[13px] text-ink">
                  <input
                    type="checkbox"
                    checked={isChecked}
                    onChange={() => togglePropertyType(p.id)}
                    className="h-4 w-4 rounded border-border accent-[color:var(--primary)]"
                  />
                  {p.label}
                </label>
                <span className="text-[11.5px] text-muted-foreground">
                  {count > 0 ? count : (availableHotels.length > 0 ? 0 : "-")}
                </span>
              </li>
            );
          })}
        </ul>
      </Section>

      <button
        type="button"
        onClick={handleApply}
        className="w-full rounded-full bg-primary px-5 py-3 text-[13px] font-medium text-primary-foreground transition-colors hover:bg-primary-mid"
      >
        Apply Filters
      </button>
    </aside>
  );
}

export const SORT_OPTIONS = [
  { value: "PRICE_ASC", label: "Price: Low to High" },
  { value: "PRICE_DESC", label: "Price: High to Low" },
  { value: "RATING_DESC", label: "Top Rated" },
];
