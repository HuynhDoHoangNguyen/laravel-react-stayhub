import { LayoutGrid, List } from "lucide-react";
import { SORT_OPTIONS } from "@/lib/filterConstants";
import { Button } from "@/components/ui/button";

export function ResultsHeader({
  total,
  city,
  from,
  to,
  sortBy,
  onSortChange,
  view,
  onViewChange,
}) {
  return (
    <div
      id="results-header"
      className="mb-6 flex flex-wrap items-end justify-between gap-4"
    >
      <div>
        <h2 className="font-display text-[26px] leading-tight text-ink md:text-[30px]">
          {total.toLocaleString("vi-VN")} phòng phù hợp
          {city ? ` tại ${city}` : ""}
        </h2>
        <p className="mt-1 text-[12.5px] text-muted-foreground">
          Hiển thị {from}–{to} trên tổng số {total.toLocaleString("vi-VN")} kết quả
        </p>
      </div>

      <div className="flex items-center gap-3">
        <label className="inline-flex items-center gap-2 text-[12.5px] text-muted-foreground">
          Sắp xếp
          <div className="relative">
            <select
              aria-label="Sắp xếp kết quả"
              value={sortBy}
              onChange={(e) => onSortChange(e.target.value)}
              className="appearance-none rounded-xl border border-border bg-card py-2 pl-3 pr-9 text-[13px] font-medium text-ink focus:border-primary focus:outline-none"
            >
              {SORT_OPTIONS.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
            <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">
              ▾
            </span>
          </div>
        </label>

        <div className="inline-flex overflow-hidden rounded-xl border border-border bg-card">
          <Button
            type="button"
            variant={view === "grid" ? "default" : "ghost"}
            size="icon"
            aria-label="Xem dạng lưới"
            aria-pressed={view === "grid"}
            onClick={() => onViewChange("grid")}
            className="h-9 w-9 rounded-lg"
          >
            <LayoutGrid className="h-4 w-4" aria-hidden />
          </Button>
          <Button
            type="button"
            variant={view === "list" ? "default" : "ghost"}
            size="icon"
            aria-label="Xem dạng danh sách"
            aria-pressed={view === "list"}
            onClick={() => onViewChange("list")}
            className="h-9 w-9 rounded-lg"
          >
            <List className="h-4 w-4" aria-hidden />
          </Button>
        </div>
      </div>
    </div>
  );
}
