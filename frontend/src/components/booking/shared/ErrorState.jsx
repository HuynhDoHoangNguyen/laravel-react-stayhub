import { AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function ErrorState({ title = 'Đã xảy ra lỗi', message = 'Không thể tải được dữ liệu. Vui lòng thử lại.', onRetry }) {
  return <section className="mx-auto flex max-w-xl flex-col items-center gap-3 rounded-xl border border-destructive/30 bg-card p-6 text-center" role="alert">
    <AlertCircle className="size-8 text-destructive" aria-hidden="true" />
    <h4 className="text-lg font-semibold text-foreground">{title}</h4>
    <p className="text-sm text-muted-foreground">{message}</p>
    {onRetry && <Button type="button" variant="outline" onClick={onRetry}>Thử lại</Button>}
  </section>;
}
