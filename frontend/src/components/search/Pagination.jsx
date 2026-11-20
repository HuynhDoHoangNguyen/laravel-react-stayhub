import { ChevronLeft, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";

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
      <Button
        type="button"
        variant="outline"
        aria-label="Trang trước"
        disabled={currentPage <= 1}
        onClick={() => onPageChange(currentPage - 1)}
        className="h-10 rounded-full px-4 text-xs font-medium"
      >
        <ChevronLeft className="h-4 w-4" />
        <span className="hidden sm:inline">Trang trước</span>
      </Button>

      <div className="flex items-center gap-1.5">
        {pages.map((p) => {
          const isActive = p === currentPage;
          return (
            <Button
              key={p}
              type="button"
              variant={isActive ? "default" : "outline"}
              onClick={() => onPageChange(p)}
              aria-current={isActive ? "page" : undefined}
              aria-label={`Trang ${p}`}
              className="h-10 w-10 rounded-full p-0 text-xs font-medium"
            >
              {p}
            </Button>
          );
        })}
      </div>

      <Button
        type="button"
        variant="outline"
        aria-label="Trang sau"
        disabled={currentPage >= totalPages}
        onClick={() => onPageChange(currentPage + 1)}
        className="h-10 rounded-full px-4 text-xs font-medium"
      >
        <span className="hidden sm:inline">Trang sau</span>
        <ChevronRight className="h-4 w-4" />
      </Button>
    </nav>
  );
}
