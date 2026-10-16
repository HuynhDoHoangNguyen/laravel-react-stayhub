import { Button } from '@/components/ui/button';

export default function Pagination({ currentPage = 1, lastPage = 1, total = 0, perPage = 15, onPageChange }) {
  if (lastPage <= 1) return null;
  const pages = [];
  for (let page = 1; page <= lastPage; page += 1) {
    if (page === 1 || page === lastPage || (page >= currentPage - 2 && page <= currentPage + 2)) pages.push(page);
    else if (pages[pages.length - 1] !== '...') pages.push('...');
  }
  const first = total ? Math.min((currentPage - 1) * perPage + 1, total) : 0;
  return <nav className="flex flex-wrap items-center justify-between gap-3" aria-label="Phân trang">
    <span className="text-sm text-muted-foreground">Hiển thị {first} - {Math.min(currentPage * perPage, total)} trên tổng số {total}</span>
    <div className="flex flex-wrap items-center gap-1">
      <Button variant="outline" size="sm" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}>« Trước</Button>
      {pages.map((page, index) => page === '...'
        ? <span key={`dots-${index}`} className="px-2 text-muted-foreground">…</span>
        : <Button key={page} variant={page === currentPage ? 'default' : 'outline'} size="sm" aria-current={page === currentPage ? 'page' : undefined} onClick={() => onPageChange(page)}>{page}</Button>)}
      <Button variant="outline" size="sm" disabled={currentPage >= lastPage} onClick={() => onPageChange(currentPage + 1)}>Sau »</Button>
    </div>
  </nav>;
}
