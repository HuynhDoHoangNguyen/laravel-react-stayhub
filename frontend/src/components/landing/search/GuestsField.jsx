import { Minus, Plus, Users } from "lucide-react";
import { useState } from "react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { FieldShell } from "./FieldShell";

const ROWS = [
  { key: "adults", label: "Người lớn", hint: "Từ 13 tuổi trở lên", min: 1, max: 12 },
  { key: "children", label: "Trẻ em", hint: "Từ 2–12 tuổi", min: 0, max: 8 },
  { key: "rooms", label: "Phòng nghỉ", hint: "Tối đa 6 phòng", min: 1, max: 6 },
];

export function GuestsField({ value, onChange }) {
  const [open, setOpen] = useState(false);
  const totalGuests = value.adults + value.children;
  const summary = `${totalGuests} khách · ${value.rooms} phòng`;

  const step = (key, delta) => {
    const row = ROWS.find((r) => r.key === key);
    if (!row) return;
    const next = Math.min(row.max, Math.max(row.min, value[key] + delta));
    onChange({ ...value, [key]: next });
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <FieldShell icon={Users} label="Khách & Phòng" active={open}>
          <div className="mt-0.5 text-[13px] text-ink">{summary}</div>
        </FieldShell>
      </PopoverTrigger>
      <PopoverContent
        align="end"
        sideOffset={12}
        className="w-[320px] p-2 pointer-events-auto rounded-2xl border-border/70 shadow-[0_20px_60px_-25px_rgba(17,26,19,0.35)]"
      >
        <ul className="divide-y divide-border/70">
          {ROWS.map((r) => {
            const v = value[r.key];
            const atMin = v <= r.min;
            const atMax = v >= r.max;
            return (
              <li key={r.key} className="flex items-center justify-between px-3 py-3.5">
                <div>
                  <div className="text-[14px] text-ink">{r.label}</div>
                  {r.hint ? (
                    <div className="text-[12px] text-muted-foreground">{r.hint}</div>
                  ) : null}
                </div>
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    aria-label={`Giảm ${r.label}`}
                    onClick={() => step(r.key, -1)}
                    disabled={atMin}
                    className="grid h-8 w-8 place-items-center rounded-full border border-border text-ink transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
                  >
                    <Minus className="h-3.5 w-3.5" />
                  </button>
                  <span className="w-5 text-center text-[14px] tabular-nums text-ink">{v}</span>
                  <button
                    type="button"
                    aria-label={`Tăng ${r.label}`}
                    onClick={() => step(r.key, 1)}
                    disabled={atMax}
                    className="grid h-8 w-8 place-items-center rounded-full border border-border text-ink transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
                  >
                    <Plus className="h-3.5 w-3.5" />
                  </button>
                </div>
              </li>
            );
          })}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
