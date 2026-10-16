import { Skeleton } from '@/components/ui/skeleton';

export default function LoadingState({ message = 'Đang tải dữ liệu...' }) {
  return <div className="flex items-center gap-3 rounded-xl border bg-card p-4" role="status" aria-live="polite">
    <Skeleton className="size-8 rounded-full" />
    <div className="flex-1 space-y-2"><Skeleton className="h-3 w-2/3" /><Skeleton className="h-3 w-1/3" /></div>
    <span className="text-sm text-muted-foreground">{message}</span>
  </div>;
}
