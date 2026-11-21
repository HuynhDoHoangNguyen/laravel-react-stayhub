import { Button } from '../ui/button';
import { cn } from '../../lib/utils';

export function DetailAction({ tone = 'primary', className, ...props }) {
    return <Button {...props} variant={tone === 'primary' ? 'default' : 'outline'}
        className={cn('h-auto min-h-11 min-w-0 whitespace-normal rounded-2xl px-4 py-2.5 text-center text-sm font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-safe:transition-[transform,box-shadow,background-color] motion-safe:duration-200 motion-safe:hover:-translate-y-0.5 motion-safe:active:translate-y-0 disabled:transform-none disabled:shadow-none',
            tone === 'primary' ? 'border border-primary/10 bg-primary text-primary-foreground shadow-[0_6px_14px_-6px_rgba(26,46,32,0.6)] hover:bg-primary-mid hover:shadow-[0_10px_20px_-8px_rgba(26,46,32,0.55)]'
                : 'border-border bg-background text-primary shadow-sm hover:border-primary/40 hover:bg-surface hover:text-primary', className)} />;
}
