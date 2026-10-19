import { ChevronLeft, ChevronRight } from "lucide-react";

export function Pagination({ currentPage, totalPages, onPageChange }) {
  if (totalPages <= 1) return null;

  const pages = [];
  for (let i = 1; i <= totalPages; i++) {
    pages.push(i);
  }

  return (
    <nav
      aria-label="Phân trang kết quả tìm kiếm"
      className="mt-12 flex items-center justify-center gap-2"
    >
      <button
        type="button"
        disabled={currentPage <= 1}
        onClick={() => onPageChange(currentPage - 1)}
        className="inline-flex h-10 items-center gap-1.5 rounded-full border border-border bg-card px-4 text-xs font-medium text-ink transition-colors hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40"
      >
        <ChevronLeft className="h-4 w-4" />
        <span className="hidden sm:inline">Trang trước</span>
      </button>

      <div className="flex items-center gap-1.5">
        {pages.map((p) => {
          const isActive = p === currentPage;
          return (
            <button
              key={p}
              type="button"
              onClick={() => onPageChange(p)}
              className={[
                "grid h-10 w-10 place-items-center rounded-full text-xs font-medium transition-all",
                isActive
                  ? "bg-primary text-primary-foreground shadow-sm"
                  : "border border-border bg-card text-ink hover:border-primary hover:text-primary",
              ].join(" ")}
            >
              {p}
            </button>
          );
        })}
      </div>

      <button
        type="button"
        disabled={currentPage >= totalPages}
        onClick={() => onPageChange(currentPage + 1)}
        className="inline-flex h-10 items-center gap-1.5 rounded-full border border-border bg-card px-4 text-xs font-medium text-ink transition-colors hover:border-primary hover:text-primary disabled:pointer-events-none disabled:opacity-40"
      >
        <span className="hidden sm:inline">Trang sau</span>
        <ChevronRight className="h-4 w-4" />
      </button>
    </nav>
  );
}
