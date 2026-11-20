import { Compass, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export function EmptyState({ title, message, onRetry }) {
  return (
    <Card className="mx-auto flex max-w-md flex-col items-center border-dashed bg-card px-6 py-14 text-center shadow-sm sm:py-20">
      <div className="grid h-16 w-16 place-items-center rounded-full bg-accent-pale/60 text-primary">
        <Compass className="h-7 w-7" strokeWidth={1.4} aria-hidden />
      </div>
      <h3 className="font-display mt-6 text-[26px] text-ink">{title}</h3>
      <p className="mt-3 text-[14px] leading-relaxed text-muted-foreground">
        {message}
      </p>
      {onRetry && (
        <Button
          type="button"
          onClick={onRetry}
          variant="outline"
          className="mt-6 h-11 rounded-2xl border-primary px-5 text-sm font-medium text-primary hover:bg-accent-pale/40"
        >
          <RefreshCw className="h-4 w-4" />
          Thử lại
        </Button>
      )}
    </Card>
  );
}
