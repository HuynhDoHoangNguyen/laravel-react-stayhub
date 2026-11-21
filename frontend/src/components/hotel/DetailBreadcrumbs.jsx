import { Link } from 'react-router-dom';
import { ChevronRight, ArrowLeft } from 'lucide-react';

export function DetailBreadcrumbs({ items }) {
    return <nav aria-label="Đường dẫn điều hướng" className="mb-6 min-w-0">
        <ol className="flex flex-wrap items-center gap-x-2 gap-y-2 text-sm">
            {items.map((item, index) => <li key={index} className="flex min-w-0 max-w-full items-center gap-2">
                {index > 0 && <ChevronRight className="h-3.5 w-3.5 shrink-0 text-muted-foreground/60" aria-hidden />}
                {item.to ? <Link to={item.to} state={item.state}
                    className="inline-flex min-w-0 items-center gap-2 rounded-lg px-2 py-1 text-muted-foreground transition-colors hover:bg-surface hover:text-primary focus-visible:outline-2 focus-visible:outline-primary">
                    {index === 0 && <ArrowLeft className="h-3.5 w-3.5 shrink-0" aria-hidden />}<span className="break-words">{item.label}</span>
                </Link> : <span aria-current={index === items.length - 1 ? 'page' : undefined} className="min-w-0 break-words px-2 py-1 font-medium text-primary">{item.label}</span>}
            </li>)}
        </ol>
    </nav>;
}
