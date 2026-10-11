export default function Pagination({
    currentPage = 1,
    lastPage = 1,
    total = 0,
    perPage = 15,
    onPageChange,
}) {
    if (lastPage <= 1) return null;

    const pages = [];
    for (let i = 1; i <= lastPage; i++) {
        // Show at most 5 page numbers around currentPage
        if (
            i === 1 ||
            i === lastPage ||
            (i >= currentPage - 2 && i <= currentPage + 2)
        ) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }

    return (
        <div className="bk-pagination">
            <span className="bk-pagination-info">
                Hiển thị {Math.min((currentPage - 1) * perPage + 1, total)} -{' '}
                {Math.min(currentPage * perPage, total)} trên tổng số {total}
            </span>

            <div className="bk-pagination-controls">
                <button
                    type="button"
                    className="bk-btn bk-btn-outline bk-btn-sm"
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                >
                    &laquo; Trước
                </button>

                {pages.map((p, idx) =>
                    p === '...' ? (
                        <span key={`dots-${idx}`} className="bk-pagination-ellipsis">
                            ...
                        </span>
                    ) : (
                        <button
                            key={p}
                            type="button"
                            className={`bk-btn bk-btn-sm ${
                                p === currentPage ? 'bk-btn-primary' : 'bk-btn-outline'
                            }`}
                            onClick={() => onPageChange(p)}
                        >
                            {p}
                        </button>
                    )
                )}

                <button
                    type="button"
                    className="bk-btn bk-btn-outline bk-btn-sm"
                    disabled={currentPage >= lastPage}
                    onClick={() => onPageChange(currentPage + 1)}
                >
                    Sau &raquo;
                </button>
            </div>
        </div>
    );
}
