import { Calendar as CalendarIcon } from "lucide-react";
import { useState } from "react";
import { format } from "date-fns";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { FieldShell } from "./FieldShell";
export function DateField({ label, value, onChange, minDate }) {
    const [open, setOpen] = useState(false);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const floor = minDate ?? today;
    return (<Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <FieldShell icon={CalendarIcon} label={label} active={open}>
          <div className="mt-0.5 text-[13px] text-ink">
            {value ? (format(value, "EEE, MMM d")) : (<span className="text-muted-foreground/70">Add dates</span>)}
          </div>
        </FieldShell>
      </PopoverTrigger>
      <PopoverContent align="start" sideOffset={12} className="w-auto p-0 pointer-events-auto rounded-2xl border-border/70 shadow-[0_20px_60px_-25px_rgba(17,26,19,0.35)]">
        <Calendar mode="single" selected={value} onSelect={(d) => {
            onChange(d);
            if (d)
                setOpen(false);
        }} disabled={(d) => d < floor} initialFocus className="p-3 pointer-events-auto"/>
      </PopoverContent>
    </Popover>);
}
