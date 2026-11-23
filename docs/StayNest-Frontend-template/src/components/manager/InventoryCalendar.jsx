import { useMemo, useState } from "react";
import { ChevronLeft, ChevronRight, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Popover, PopoverContent, PopoverTrigger, } from "@/components/ui/popover";
import { useRoomInventory, useUpdateInventory } from "@/hooks/queries/manager";
function fmt(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}
export function InventoryCalendar({ roomId }) {
    const [cursor, setCursor] = useState(() => {
        const d = new Date();
        d.setDate(1);
        return d;
    });
    const [editorOpen, setEditorOpen] = useState(false);
    const [startDate, setStartDate] = useState(fmt(new Date()));
    const [endDate, setEndDate] = useState(fmt(new Date()));
    const [surge, setSurge] = useState("1");
    const [closed, setClosed] = useState(false);
    const { data, isLoading } = useRoomInventory(roomId);
    const updateMut = useUpdateInventory(roomId);
    const byDate = useMemo(() => {
        const m = new Map();
        for (const slot of data ?? [])
            m.set(slot.date, slot);
        return m;
    }, [data]);
    const monthLabel = cursor.toLocaleDateString(undefined, {
        month: "long",
        year: "numeric",
    });
    const firstDay = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const daysInMonth = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
    const startOffset = firstDay.getDay();
    const cells = [];
    for (let i = 0; i < startOffset; i++)
        cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = fmt(new Date(cursor.getFullYear(), cursor.getMonth(), d));
        cells.push({ date: dateStr, day: d });
    }
    const handleSubmit = () => {
        if (!startDate || !endDate)
            return toast.error("Pick a date range.");
        if (startDate > endDate)
            return toast.error("Start date must be before end date.");
        const s = Number(surge);
        if (!Number.isFinite(s) || s < 0 || s > 10)
            return toast.error("Surge factor must be between 0 and 10.");
        updateMut.mutate({ startDate, endDate, surgeFactor: s, closed }, {
            onSuccess: () => {
                toast.success("Inventory updated");
                setEditorOpen(false);
            },
            onError: (err) => toast.error(err.message),
        });
    };
    return (<div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Button variant="outline" size="icon" className="h-9 w-9 rounded-lg" onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1))} aria-label="Previous month">
            <ChevronLeft className="h-4 w-4"/>
          </Button>
          <span className="min-w-[160px] text-center font-display text-lg text-primary">
            {monthLabel}
          </span>
          <Button variant="outline" size="icon" className="h-9 w-9 rounded-lg" onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1))} aria-label="Next month">
            <ChevronRight className="h-4 w-4"/>
          </Button>
        </div>

        <Popover open={editorOpen} onOpenChange={setEditorOpen}>
          <PopoverTrigger asChild>
            <Button className="rounded-xl bg-primary hover:bg-primary-mid">
              Adjust range
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-80 rounded-2xl border-border/60 p-4">
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                    Start
                  </Label>
                  <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="h-10 rounded-lg"/>
                </div>
                <div className="space-y-1">
                  <Label className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                    End
                  </Label>
                  <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="h-10 rounded-lg"/>
                </div>
              </div>
              <div className="space-y-1">
                <Label className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                  Surge factor (0.0 – 10.0)
                </Label>
                <Input type="number" step="0.05" min={0} max={10} value={surge} onChange={(e) => setSurge(e.target.value)} className="h-10 rounded-lg"/>
              </div>
              <div className="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2">
                <span className="text-[13px]">Block bookings</span>
                <Switch checked={closed} onCheckedChange={setClosed}/>
              </div>
              <Button onClick={handleSubmit} disabled={updateMut.isPending} className="w-full rounded-xl bg-primary hover:bg-primary-mid">
                {updateMut.isPending ? "Saving…" : "Apply"}
              </Button>
            </div>
          </PopoverContent>
        </Popover>
      </div>

      {isLoading && (<div className="grid place-items-center py-16 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin"/>
        </div>)}

      {!isLoading && (<div className="grid grid-cols-7 gap-1.5 rounded-2xl border border-border/60 bg-white p-3">
          {["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].map((d) => (<div key={d} className="pb-1 text-center text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
              {d}
            </div>))}
          {cells.map((cell, i) => !cell ? (<div key={i} className="min-h-[68px]"/>) : (<InventoryCell key={cell.date} day={cell.day} slot={byDate.get(cell.date)}/>))}
        </div>)}
    </div>);
}
function InventoryCell({ day, slot, }) {
    const closed = slot?.closed;
    const surge = slot?.surgeFactor ?? 1;
    const booked = slot?.bookedCount ?? 0;
    const reserved = slot?.reservedCount ?? 0;
    const total = slot?.totalCount ?? 1;
    const occupancy = slot ? (booked + reserved) / Math.max(total, 1) : 0;
    const bg = closed
        ? "bg-destructive/10 border-destructive/30"
        : surge > 1
            ? "bg-accent/15 border-accent/40"
            : occupancy > 0.7
                ? "bg-primary/10 border-primary/25"
                : "bg-surface/40 border-border/60";
    return (<div className={`min-h-[68px] rounded-lg border p-1.5 text-[11px] leading-tight ${bg}`}>
      <div className="flex items-baseline justify-between">
        <span className="font-medium text-ink">{day}</span>
        {closed && (<span className="text-[9px] uppercase tracking-wide text-destructive">
            Closed
          </span>)}
      </div>
      {slot ? (<>
          <div className="mt-1 text-[10.5px] font-semibold text-primary">
            ₹{Math.round(slot.price)}
          </div>
          <div className="text-[10px] text-muted-foreground flex items-center gap-1 flex-wrap">
            <span>{booked}/{total}</span>
            {reserved > 0 && (<span className="text-amber-600 font-medium text-[9px] bg-amber-50 px-1 rounded border border-amber-200/50" title={`${reserved} pending checkout`}>
                +{reserved}p
              </span>)}
          </div>
          {surge > 1 && (<div className="text-[10px] font-medium text-accent-foreground">
              ×{surge.toFixed(2)}
            </div>)}
        </>) : (<div className="mt-1 text-[10px] text-muted-foreground/70">—</div>)}
    </div>);
}
