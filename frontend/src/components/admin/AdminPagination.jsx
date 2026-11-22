import { Button } from '../ui/button.jsx';

export default function AdminPagination({ label, currentPage, lastPage, onPageChange }) {
  const atFirst = currentPage <= 1;

  return (
    <nav className="flex flex-wrap items-center gap-2" aria-label={label}>
      <Button type="button" variant="outline" size="sm" disabled={atFirst} onClick={() => onPageChange(1)}>
        Trang đầu
      </Button>
      <Button type="button" variant="outline" size="sm" disabled={atFirst} onClick={() => onPageChange(currentPage - 1)}>
        Trang trước
      </Button>
      <span className="px-1 text-sm tabular-nums text-muted-foreground">Trang {currentPage} / {lastPage}</span>
      <Button type="button" variant="outline" size="sm" disabled={currentPage >= lastPage} onClick={() => onPageChange(currentPage + 1)}>
        Trang sau
      </Button>
    </nav>
  );
}
