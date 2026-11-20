import { CheckCircle2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

const STEPS = [
  { key: 'guests', label: 'Thông tin khách' },
  { key: 'review', label: 'Xem lại thông tin' },
];

export function CheckoutStepper({ currentStep }) {
  const activeIdx = STEPS.findIndex((s) => s.key === currentStep);

  return (
    <ol aria-label="Các bước đặt phòng" className="flex flex-wrap items-center gap-3">
      {STEPS.map((s, idx) => {
        const isActive = idx === activeIdx;
        const isDone = idx < activeIdx;

        return (
          <li key={s.key} className="flex items-center gap-2.5 sm:gap-3">
            <Badge
              aria-current={isActive ? 'step' : undefined}
              className={`grid h-8 w-8 shrink-0 place-items-center rounded-full p-0 text-[11px] font-semibold transition-all ${
                isDone
                  ? 'bg-primary text-primary-foreground shadow-xs'
                  : isActive
                  ? 'border-2 border-primary bg-primary/[0.08] text-primary'
                  : 'border border-border/80 bg-surface/50 text-muted-foreground'
              }`}
            >
              {isDone ? <CheckCircle2 className="h-4 w-4" /> : idx + 1}
            </Badge>

            <span
              className={`text-xs sm:text-[13px] transition-colors ${
                isActive
                  ? 'font-bold text-primary'
                  : isDone
                  ? 'font-medium text-ink'
                  : 'font-normal text-muted-foreground'
              }`}
            >
              {s.label}
            </span>

            {idx < STEPS.length - 1 && (
              <span className="h-px w-6 bg-border/80 sm:w-10" aria-hidden="true" />
            )}
          </li>
        );
      })}
    </ol>
  );
}

export default CheckoutStepper;
