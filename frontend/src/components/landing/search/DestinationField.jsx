import { MapPin } from "lucide-react";
import { useMemo, useState } from "react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { filterCities } from "@/lib/cities";
import { FieldShell } from "./FieldShell";

export function DestinationField({ value = "", onChange }) {
  const [open, setOpen] = useState(false);
  const query = value || "";

  const suggestions = useMemo(() => filterCities(query), [query]);

  const pick = (c) => {
    onChange(c.city);
    setOpen(false);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <FieldShell icon={MapPin} label="Bạn muốn đi đâu?" active={open} grow={1.4}>
          <input
            type="text"
            value={query}
            onChange={(e) => {
              onChange(e.target.value);
              if (!open) setOpen(true);
            }}
            onFocus={() => setOpen(true)}
            placeholder="Tìm điểm đến (Đà Lạt, Sa Pa, Phú Quốc...)"
            className="mt-0.5 w-full bg-transparent text-[13px] text-ink placeholder:text-muted-foreground/70 focus:outline-none"
          />
        </FieldShell>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        sideOffset={12}
        className="w-[360px] p-2 pointer-events-auto rounded-2xl border-border/70 shadow-[0_20px_60px_-25px_rgba(17,26,19,0.35)]"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        <div className="px-3 pt-2 pb-1 text-[11px] font-medium tracking-[0.14em] uppercase text-muted-foreground">
          {query.trim() ? "Điểm đến phù hợp" : "Điểm đến thịnh hành"}
        </div>
        {suggestions.length === 0 ? (
          <div className="px-3 py-6 text-center text-sm text-muted-foreground">
            Không tìm thấy điểm đến phù hợp với &ldquo;{query}&rdquo;
          </div>
        ) : (
          <ul className="max-h-80 overflow-y-auto">
            {suggestions.map((c) => (
              <li key={`${c.city}-${c.region || c.country}`}>
                <button
                  type="button"
                  onClick={() => pick(c)}
                  className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-accent-pale/50"
                >
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-surface">
                    <MapPin className="h-4 w-4 text-primary/70" aria-hidden />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-[14px] text-ink">{c.city}</span>
                    <span className="block truncate text-[12px] text-muted-foreground">
                      {c.country}
                      {c.region ? ` · ${c.region}` : ""}
                    </span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </PopoverContent>
    </Popover>
  );
}
