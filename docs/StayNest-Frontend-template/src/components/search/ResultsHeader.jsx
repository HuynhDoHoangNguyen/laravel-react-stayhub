import { LayoutGrid, List } from "lucide-react";
import { SORT_OPTIONS } from "./FilterSidebar";
export function ResultsHeader({ total, city, from, to, sortBy, onSortChange, view, onViewChange, }) {
    return (<div id="results-header" className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 className="font-display text-[26px] leading-tight text-ink md:text-[30px]">
          {total.toLocaleString()} properties found
          {city ? ` in ${city}` : ""}
        </h2>
        <p className="mt-1 text-[12.5px] text-muted-foreground">
          Showing {from}–{to} of {total.toLocaleString()} results
        </p>
      </div>

      <div className="flex items-center gap-3">
        <label className="inline-flex items-center gap-2 text-[12.5px] text-muted-foreground">
          Sort by
          <div className="relative">
            <select value={sortBy} onChange={(e) => onSortChange(e.target.value)} className="appearance-none rounded-xl border border-border bg-card py-2 pl-3 pr-9 text-[13px] font-medium text-ink focus:border-primary focus:outline-none">
              {SORT_OPTIONS.map((s) => (<option key={s.value} value={s.value}>
                  {s.label}
                </option>))}
            </select>
            <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">
              ▾
            </span>
          </div>
        </label>

        <div className="inline-flex overflow-hidden rounded-xl border border-border bg-card">
          <button type="button" aria-label="Grid view" onClick={() => onViewChange("grid")} className={[
            "grid h-9 w-9 place-items-center transition-colors",
            view === "grid"
                ? "bg-primary text-primary-foreground"
                : "text-ink hover:bg-surface",
        ].join(" ")}>
            <LayoutGrid className="h-4 w-4" aria-hidden/>
          </button>
          <button type="button" aria-label="List view" onClick={() => onViewChange("list")} className={[
            "grid h-9 w-9 place-items-center transition-colors",
            view === "list"
                ? "bg-primary text-primary-foreground"
                : "text-ink hover:bg-surface",
        ].join(" ")}>
            <List className="h-4 w-4" aria-hidden/>
          </button>
        </div>
      </div>
    </div>);
}
