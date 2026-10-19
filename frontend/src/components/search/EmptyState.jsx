import { Compass, RefreshCw } from "lucide-react";

export function EmptyState({ title, message, onRetry }) {
  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-6 py-20 text-center">
      <div className="grid h-16 w-16 place-items-center rounded-full bg-accent-pale/60 text-primary">
        <Compass className="h-7 w-7" strokeWidth={1.4} aria-hidden />
      </div>
      <h3 className="font-display mt-6 text-[26px] text-ink">{title}</h3>
      <p className="mt-3 text-[14px] leading-relaxed text-muted-foreground">
        {message}
      </p>
      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          className="mt-6 inline-flex items-center gap-2 rounded-2xl border border-primary px-5 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-accent-pale/40"
        >
          <RefreshCw className="h-4 w-4" />
          Thử lại
        </button>
      )}
    </div>
  );
}
