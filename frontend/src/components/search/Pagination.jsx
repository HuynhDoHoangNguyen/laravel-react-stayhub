import { ChevronLeft, ChevronRight } from 'lucide-react';

export function Pagination({ currentPage, totalPages, onPageChange }) {
    if (totalPages <= 1) return null;

    // Keep the number of controls bounded even when the server reports millions of pages.
    const selected = new Set([1, totalPages]);
    const start = currentPage <= 3 ? 2 : Math.max(2, Math.min(currentPage - 1, totalPages - 4));
    const end = currentPage <= 3 ? Math.min(5, totalPages - 1) : Math.min(totalPages - 1, currentPage + 1);
    for (let page = start; page <= end; page++) selected.add(page);
    const pages = [...selected].sort((a, b) => a - b);
    const controls = [];
    for (const page of pages) {
        const previous = controls.at(-1);
        if (typeof previous === 'number' && page - previous > 1) controls.push('gap-' + page);
        controls.push(page);
    }

    return <nav aria-label="Phân trang kết quả tìm kiếm" className="mt-12 flex flex-wrap items-center justify-center gap-2">
        <button type="button" aria-label="Trang trước" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}
            className="inline-flex h-10 items-center gap-1.5 rounded-full border border-border bg-card px-3 text-xs font-medium text-ink transition-colors hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40">
            <ChevronLeft className="h-4 w-4" aria-hidden /><span className="hidden sm:inline">Trang trước</span>
        </button>
        {controls.map(page => typeof page === 'string' ? <span key={page} aria-hidden className="px-1 text-muted-foreground">…</span>
            : <button key={page} type="button" aria-label={'Trang ' + page} aria-current={page === currentPage ? 'page' : undefined} onClick={() => onPageChange(page)}
                className={['grid h-10 min-w-10 place-items-center rounded-full px-2 text-xs font-medium transition-colors',
                    page === currentPage ? 'bg-primary text-primary-foreground shadow-sm' : 'border border-border bg-card text-ink hover:border-primary hover:text-primary'].join(' ')}>{page.toLocaleString('vi-VN')}</button>)}
        <button type="button" aria-label="Trang sau" disabled={currentPage >= totalPages} onClick={() => onPageChange(currentPage + 1)}
            className="inline-flex h-10 items-center gap-1.5 rounded-full border border-border bg-card px-3 text-xs font-medium text-ink transition-colors hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40">
            <span className="hidden sm:inline">Trang sau</span><ChevronRight className="h-4 w-4" aria-hidden />
        </button>
    </nav>;
}
