import { CalendarDays } from 'lucide-react';
import { format } from 'date-fns';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import FieldShell from './FieldShell';

function fromIso(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
  return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : undefined;
}

function toIso(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export default function DateField({ label, value, minDate, onChange }) {
  const selected = fromIso(value);
  const min = fromIso(minDate);
  return <Popover>
    <PopoverTrigger asChild>
      <Button type="button" variant="ghost" className="h-auto w-full justify-start p-0 text-left font-normal hover:bg-transparent data-[state=open]:bg-transparent focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background" aria-label={`${label}: ${selected ? format(selected, 'dd/MM/yyyy') : 'Chọn ngày'}`}>
        <FieldShell icon={CalendarDays} label={label} className="w-full">
          <span className="text-sm font-medium text-foreground">{selected ? format(selected, 'dd/MM/yyyy') : 'Chọn ngày'}</span>
        </FieldShell>
      </Button>
    </PopoverTrigger>
    <PopoverContent align="start" className="w-auto p-0">
      <Calendar mode="single" selected={selected} onSelect={(date) => date && onChange(toIso(date))} disabled={(date) => min && date < min} initialFocus />
    </PopoverContent>
  </Popover>;
}
