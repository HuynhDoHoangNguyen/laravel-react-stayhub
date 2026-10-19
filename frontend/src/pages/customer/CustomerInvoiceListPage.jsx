import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
    Compass,
    Search,
    X,
    RotateCcw,
    AlertCircle,
    Sparkles,
    ChevronLeft,
    ChevronRight,
    RefreshCw,
} from 'lucide-react';
import { getCustomerInvoices, parseInvoiceError } from '@/services/invoiceService';
import CustomerInvoiceCard from '@/components/invoice/CustomerInvoiceCard';

const TABS = [
    { key: 'ALL', label: 'Tất cả' },
    { key: 'UNPAID', label: 'Chưa thanh toán' },
    { key: 'PAID', label: 'Đã thanh toán' },
    { key: 'REFUNDED', label: 'Đã hoàn tiền' },
];

/**
 * Trang danh sách hóa đơn dành cho Khách hàng (StayNest Quiet Luxury Pattern).
 * Tuyến đường: /customer/invoices
 */
export default function CustomerInvoiceListPage() {
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [reloadTrigger, setReloadTrigger] = useState(0);

    // Bộ lọc, tìm kiếm và phân trang phía Server
    const [status, setStatus] = useState('ALL');
    const [searchTerm, setSearchTerm] = useState('');
    const [activeSearch, setActiveSearch] = useState('');
    const [page, setPage] = useState(1);
    const [paginationMeta, setPaginationMeta] = useState({
        currentPage: 1,
        lastPage: 1,
        total: 0,
        perPage: 9,
    });

    useEffect(() => {
        let isMounted = true;

        const fetchInvoices = async () => {
            setLoading(true);
            setError(null);
            try {
                const res = await getCustomerInvoices({
                    page,
                    perPage: 9,
                    status,
                    search: activeSearch,
                });

                if (isMounted) {
                    setInvoices(res.data || []);
                    if (res.meta) {
                        setPaginationMeta({
                            currentPage: res.meta.current_page || 1,
                            lastPage: res.meta.last_page || 1,
                            total: res.meta.total || 0,
                            perPage: res.meta.per_page || 9,
                        });
                    }
                }
            } catch (err) {
                if (isMounted) {
                    const parsed = parseInvoiceError(err);
                    setError(parsed.message);
                }
            } finally {
                if (isMounted) {
                    setLoading(false);
                }
            }
        };

        fetchInvoices();

        return () => {
            isMounted = false;
        };
    }, [page, status, activeSearch, reloadTrigger]);

    // Xử lý đổi Tab lọc
    const handleTabChange = (newStatus) => {
        if (newStatus === status) return;
        setStatus(newStatus);
        setPage(1);
    };

    // Xử lý tìm kiếm
    const handleSearchSubmit = (e) => {
        e.preventDefault();
        setPage(1);
        setActiveSearch(searchTerm.trim());
    };

    // Xử lý xóa tìm kiếm
    const handleClearSearch = () => {
        setSearchTerm('');
        setActiveSearch('');
        setPage(1);
    };

    // Reset toàn bộ bộ lọc
    const handleResetAllFilters = () => {
        setStatus('ALL');
        setSearchTerm('');
        setActiveSearch('');
        setPage(1);
    };

    // Xử lý chuyển trang
    const handlePageChange = (newPage) => {
        setPage(newPage);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const isFiltered = status !== 'ALL' || activeSearch !== '';

    return (
        <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
            {/* 1. Page Header (StayNest Quiet Luxury Overline & Title) */}
            <div className="border-b border-border/60 pb-6">
                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
                    ✦ Hội viên · Lịch sử thanh toán
                </p>
                <h1 className="mt-1 font-display text-2xl font-bold leading-tight text-primary sm:text-3xl md:text-4xl">
                    Hóa đơn của tôi
                </h1>
                <p className="mt-1.5 text-xs text-muted-foreground sm:text-sm">
                    Theo dõi chi phí lưu trú, chứng từ dịch vụ phát sinh và trạng thái quyết toán cho các kỳ nghỉ của bạn.
                </p>
            </div>

            {/* 2. Controls Bar: Status Filter Tabs & Search Form */}
            <div className="mt-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                {/* Status Tabs */}
                <div
                    className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0"
                    role="tablist"
                    aria-label="Lọc hóa đơn theo trạng thái"
                >
                    {TABS.map((t) => {
                        const isActive = status === t.key;
                        return (
                            <button
                                key={t.key}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                onClick={() => handleTabChange(t.key)}
                                className={`inline-flex shrink-0 items-center rounded-xl px-4 py-2 text-xs font-semibold transition-all cursor-pointer ${
                                    isActive
                                        ? 'bg-primary text-primary-foreground shadow-2xs'
                                        : 'bg-surface/50 text-ink/80 hover:bg-surface hover:text-primary'
                                }`}
                            >
                                <span>{t.label}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Search Form */}
                <form
                    onSubmit={handleSearchSubmit}
                    className="relative w-full sm:max-w-md"
                >
                    <div className="relative flex items-center">
                        <Search
                            className="pointer-events-none absolute left-3.5 h-4 w-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Tìm theo mã hóa đơn hoặc tên khách sạn..."
                            className="h-10 w-full rounded-xl border border-border/70 bg-white pl-10 pr-10 text-xs text-ink placeholder:text-muted-foreground/70 outline-none transition-all focus:border-primary/50 focus:ring-2 focus:ring-primary/10 shadow-2xs"
                        />
                        {searchTerm && (
                            <button
                                type="button"
                                onClick={handleClearSearch}
                                className="absolute right-3 grid h-5 w-5 place-items-center rounded-full text-muted-foreground hover:bg-surface hover:text-ink transition-colors cursor-pointer"
                                aria-label="Xóa từ khóa tìm kiếm"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                </form>
            </div>

            {/* 3. Error State */}
            {error && (
                <div className="mt-8 rounded-3xl border border-destructive/20 bg-destructive/5 p-8 text-center sm:p-10">
                    <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-destructive/10 text-destructive">
                        <AlertCircle className="h-6 w-6" aria-hidden="true" />
                    </div>
                    <h3 className="mt-4 font-display text-xl font-bold text-ink">
                        Không thể tải danh sách hóa đơn
                    </h3>
                    <p className="mx-auto mt-1.5 max-w-md text-xs leading-relaxed text-muted-foreground">
                        {error}
                    </p>
                    <div className="mt-6 flex justify-center">
                        <button
                            type="button"
                            onClick={() => setReloadTrigger((r) => r + 1)}
                            className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-2xs transition-all hover:bg-primary-mid cursor-pointer"
                        >
                            <RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />
                            <span>Thử lại</span>
                        </button>
                    </div>
                </div>
            )}

            {/* 4. Loading Skeleton State */}
            {loading && !error && (
                <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {[1, 2, 3, 4, 5, 6].map((i) => (
                        <div
                            key={i}
                            className="flex flex-col justify-between rounded-3xl border border-border/60 bg-white/80 p-6 shadow-xs animate-pulse"
                        >
                            <div>
                                <div className="flex items-start justify-between gap-3 border-b border-border/40 pb-4">
                                    <div className="space-y-2 flex-1">
                                        <div className="h-5 w-40 rounded-lg bg-surface" />
                                        <div className="h-3 w-24 rounded bg-surface" />
                                    </div>
                                    <div className="h-5 w-20 rounded-full bg-surface" />
                                </div>
                                <div className="mt-4 space-y-2.5">
                                    <div className="h-3.5 w-32 rounded bg-surface" />
                                    <div className="h-3.5 w-28 rounded bg-surface" />
                                    <div className="h-3.5 w-36 rounded bg-surface" />
                                </div>
                            </div>
                            <div className="mt-6 flex items-end justify-between border-t border-border/40 pt-4">
                                <div className="space-y-1">
                                    <div className="h-2.5 w-16 rounded bg-surface" />
                                    <div className="h-6 w-28 rounded-lg bg-surface" />
                                </div>
                                <div className="h-8 w-24 rounded-xl bg-surface" />
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* 5. Empty State */}
            {!loading && !error && invoices.length === 0 && (
                <div className="mt-8 rounded-3xl border border-dashed border-border/80 bg-background/50 p-10 text-center sm:p-14">
                    <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface text-primary">
                        <Sparkles className="h-6 w-6 text-accent" aria-hidden="true" />
                    </div>

                    <h3 className="mt-4 font-display text-xl sm:text-2xl font-bold text-primary">
                        {isFiltered ? 'Không tìm thấy hóa đơn phù hợp' : 'Bạn chưa có hóa đơn nào'}
                    </h3>

                    <p className="mx-auto mt-2 max-w-md text-xs leading-relaxed text-muted-foreground">
                        {isFiltered
                            ? 'Không có hóa đơn nào khớp với bộ lọc trạng thái hoặc từ khóa tìm kiếm của bạn. Hãy thử thay đổi tiêu chí.'
                            : 'Khi bạn đặt phòng và hoàn tất kỳ lưu trú tại các cơ sở StayHub, chứng từ hóa đơn chính thức sẽ xuất hiện đầy đủ tại đây.'}
                    </p>

                    <div className="mt-6 flex flex-wrap justify-center gap-3">
                        {isFiltered ? (
                            <button
                                type="button"
                                onClick={handleResetAllFilters}
                                className="inline-flex items-center gap-2 rounded-xl border border-border/80 bg-white px-4 py-2.5 text-xs font-semibold text-ink shadow-2xs hover:border-primary/40 hover:bg-surface transition-all cursor-pointer"
                            >
                                <RotateCcw className="h-3.5 w-3.5" aria-hidden="true" />
                                <span>Xóa bộ lọc tìm kiếm</span>
                            </button>
                        ) : (
                            <Link
                                to="/search"
                                className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-2xs transition-all hover:bg-primary-mid"
                            >
                                <Compass className="h-4 w-4" aria-hidden="true" />
                                <span>Khám phá chỗ nghỉ StayHub</span>
                            </Link>
                        )}
                    </div>
                </div>
            )}

            {/* 6. Invoices Cards Grid */}
            {!loading && !error && invoices.length > 0 && (
                <>
                    <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {invoices.map((inv) => (
                            <CustomerInvoiceCard key={inv.id} invoice={inv} />
                        ))}
                    </div>

                    {/* 7. Pagination Controls */}
                    {paginationMeta.lastPage > 1 && (
                        <div className="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-6">
                            <span className="text-xs text-muted-foreground">
                                Hiển thị trang <strong className="font-semibold text-ink">{paginationMeta.currentPage}</strong> /{' '}
                                <strong className="font-semibold text-ink">{paginationMeta.lastPage}</strong> (Tổng cộng{' '}
                                <strong className="font-semibold text-ink">{paginationMeta.total}</strong> hóa đơn)
                            </span>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    disabled={page <= 1}
                                    onClick={() => handlePageChange(page - 1)}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-border/70 bg-white px-3.5 py-2 text-xs font-medium text-ink shadow-2xs transition-colors hover:border-primary/40 hover:bg-surface disabled:pointer-events-none disabled:opacity-40 cursor-pointer"
                                    aria-label="Trang trước"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                    <span>Trước</span>
                                </button>

                                <button
                                    type="button"
                                    disabled={page >= paginationMeta.lastPage}
                                    onClick={() => handlePageChange(page + 1)}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-border/70 bg-white px-3.5 py-2 text-xs font-medium text-ink shadow-2xs transition-colors hover:border-primary/40 hover:bg-surface disabled:pointer-events-none disabled:opacity-40 cursor-pointer"
                                    aria-label="Trang sau"
                                >
                                    <span>Sau</span>
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
