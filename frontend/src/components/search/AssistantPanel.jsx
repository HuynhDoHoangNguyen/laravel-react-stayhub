import { SlidersHorizontal, Search, ArrowRight, Tag } from "lucide-react";
import { useState } from "react";
import { SORT_OPTIONS } from "@/lib/filterConstants";

const SUGGESTIONS = [
  {
    label: "Giá tốt nhất",
    apply: (setFilters, filters) => setFilters({ ...filters, sortBy: "PRICE_ASC" }),
  },
  {
    label: "Resort 5 sao",
    apply: (setFilters, filters) =>
      setFilters({
        ...filters,
        selectedPropertyTypes: ["Resort"],
        minRating: 4.8,
      }),
  },
  {
    label: "Hồ bơi & Spa",
    apply: (setFilters, filters) =>
      setFilters({
        ...filters,
        selectedAmenities: ["pool", "spa"],
      }),
  },
  {
    label: "Khách sạn trung tâm",
    apply: (setFilters, filters) =>
      setFilters({
        ...filters,
        selectedPropertyTypes: ["Hotel"],
      }),
  },
];

export function AssistantPanel({
  filters,
  onFiltersChange,
  keyword = "",
  onKeywordChange,
}) {
  const [minLocal, setMinLocal] = useState(filters.minPrice?.toString() ?? "");
  const [maxLocal, setMaxLocal] = useState(filters.maxPrice?.toString() ?? "");
  const [input, setInput] = useState(keyword);

  const [prevFilters, setPrevFilters] = useState(filters);
  const [prevKeyword, setPrevKeyword] = useState(keyword);

  // Sync state during render when props change, following React 19 guidelines
  if (
    filters.minPrice !== prevFilters.minPrice ||
    filters.maxPrice !== prevFilters.maxPrice
  ) {
    setPrevFilters(filters);
    setMinLocal(filters.minPrice?.toString() ?? "");
    setMaxLocal(filters.maxPrice?.toString() ?? "");
  }

  if (keyword !== prevKeyword) {
    setPrevKeyword(keyword);
    setInput(keyword);
  }

  const handlePriceApply = (e) => {
    e.preventDefault();
    onFiltersChange({
      ...filters,
      minPrice: minLocal ? Number(minLocal) : undefined,
      maxPrice: maxLocal ? Number(maxLocal) : undefined,
    });
  };

  const handleKeywordSubmit = (e) => {
    e.preventDefault();
    onKeywordChange(input.trim());
  };

  return (
    <aside className="z-20 w-full h-fit self-start lg:sticky lg:top-[120px]">
      <div
        id="stayhub-refine-panel"
        className="flex flex-col gap-4 rounded-3xl bg-card p-5 shadow-[0_10px_40px_-25px_rgba(17,26,19,0.2)] ring-1 ring-black/[0.04]"
      >
        <header className="shrink-0 flex items-center gap-2">
          <div className="grid h-8 w-8 place-items-center rounded-full bg-primary/10 text-primary">
            <SlidersHorizontal className="h-4 w-4" aria-hidden />
          </div>
          <h3 className="text-[15px] font-semibold text-ink">
            Tinh chỉnh tìm kiếm
          </h3>
        </header>

        {/* Keyword Search Box */}
        <form
          onSubmit={handleKeywordSubmit}
          className="flex items-center gap-2 rounded-2xl bg-accent-pale/40 pl-3 pr-1 py-1 ring-1 ring-black/[0.04] focus-within:ring-primary/40"
        >
          <Search className="h-4 w-4 shrink-0 text-primary/70" aria-hidden />
          <input
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="Tìm theo tên hoặc từ khóa..."
            className="min-w-0 flex-1 bg-transparent py-2 text-[13px] text-ink placeholder:text-muted-foreground/80 focus:outline-none"
          />
          <button
            type="submit"
            aria-label="Tìm kiếm"
            disabled={!input.trim() && !keyword}
            className="grid h-8 w-8 place-items-center rounded-xl bg-primary text-primary-foreground transition-colors hover:bg-primary-mid disabled:opacity-40"
          >
            <ArrowRight className="h-3.5 w-3.5" aria-hidden />
          </button>
        </form>

        {/* Refine card */}
        <div className="rounded-2xl bg-surface/50 p-4 ring-1 ring-black/[0.04]">
          <p className="text-[13px] font-semibold text-ink">Điều chỉnh nhanh</p>

          <label className="mt-3 block text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
            Sắp xếp theo
          </label>
          <select
            value={filters.sortBy}
            onChange={(e) =>
              onFiltersChange({ ...filters, sortBy: e.target.value })
            }
            className="mt-1.5 w-full appearance-none rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-none"
          >
            {SORT_OPTIONS.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>

          <div className="mt-3 grid grid-cols-2 gap-2">
            <label className="block">
              <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                Tối thiểu (₫)
              </span>
              <input
                type="number"
                value={minLocal}
                onChange={(e) => setMinLocal(e.target.value)}
                placeholder="500,000"
                className="mt-1.5 w-full rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-none"
              />
            </label>
            <label className="block">
              <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                Tối đa (₫)
              </span>
              <input
                type="number"
                value={maxLocal}
                onChange={(e) => setMaxLocal(e.target.value)}
                placeholder="20,000,000"
                className="mt-1.5 w-full rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-none"
              />
            </label>
          </div>

          <button
            type="button"
            onClick={handlePriceApply}
            className="mt-3 w-full rounded-full bg-primary py-2.5 text-[12.5px] font-medium text-primary-foreground transition-colors hover:bg-primary-mid"
          >
            Áp dụng
          </button>
        </div>

        {/* Suggestion Chips */}
        <div>
          <div className="mb-2 flex items-center gap-1.5 text-[12px] font-medium text-muted-foreground">
            <Tag className="h-3.5 w-3.5" />
            Gợi ý chọn nhanh:
          </div>
          <div className="flex flex-wrap gap-2">
            {SUGGESTIONS.map((s) => (
              <button
                key={s.label}
                type="button"
                onClick={() => s.apply(onFiltersChange, filters)}
                className="rounded-full border border-border bg-background px-3 py-1.5 text-[11.5px] text-ink transition hover:border-primary hover:text-primary active:scale-95"
              >
                {s.label}
              </button>
            ))}
          </div>
        </div>
      </div>
    </aside>
  );
}
