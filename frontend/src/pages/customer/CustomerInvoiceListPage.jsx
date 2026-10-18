import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { getCustomerInvoices, formatVnd, parseInvoiceError } from '../../services/invoiceService';
import '../../components/invoice/Invoice.css';

/**
 * Trang danh sách hóa đơn dành cho Khách hàng (StayNest Member Pattern).
 * Route: /customer/invoices
 */
function CustomerInvoiceListPage() {
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [reloadTrigger, setReloadTrigger] = useState(0);

    // Filters & Pagination
    const [status, setStatus] = useState('ALL');
    const [searchTerm, setSearchTerm] = useState('');
    const [activeSearch, setActiveSearch] = useState('');
    const [page, setPage] = useState(1);
    const [paginationMeta, setPaginationMeta] = useState({
        currentPage: 1,
        lastPage: 1,
        total: 0,
    });

    useEffect(() => {
        let isMounted = true;

        const load = async () => {
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

        load();

        return () => {
            isMounted = false;
        };
    }, [page, status, activeSearch, reloadTrigger]);

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        setPage(1);
        setActiveSearch(searchTerm);
    };

    const handleTabChange = (newStatus) => {
        setStatus(newStatus);
        setPage(1);
    };

    const getStatusLabel = (st) => {
        switch (st) {
            case 'PAID':
                return 'Đã thanh toán';
            case 'REFUNDED':
                return 'Đã hoàn tiền';
            case 'UNPAID':
            default:
                return 'Chưa thanh toán';
        }
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        try {
            return new Date(dateStr).toLocaleDateString('vi-VN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });
        } catch {
            return dateStr;
        }
    };

    return (
        <div style={{ maxWidth: '1060px', margin: '0 auto', textAlign: 'left' }}>
            {/* Header */}
            <div style={{ marginBottom: '24px' }}>
                <p style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.24em', color: '#785a28', fontWeight: 600, margin: '0 0 6px' }}>
                    Hội viên · Lịch sử thanh toán
                </p>
                <h1
                    style={{
                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                        fontSize: '34px',
                        fontWeight: 700,
                        color: '#1a2e20',
                        margin: '0 0 6px 0',
                        letterSpacing: '-0.4px',
                    }}
                >
                    Hóa đơn của tôi
                </h1>
                <p style={{ color: '#5c6960', fontSize: '15px', margin: 0 }}>
                    Theo dõi chi phí lưu trú, dịch vụ phát sinh và trạng thái thanh toán cho các chuyến đi của bạn.
                </p>
            </div>

            {/* Filter Tabs & Search Bar */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px', marginBottom: '24px' }}>
                <div className="customer-tabs">
                    <button
                        type="button"
                        className={`customer-tab-btn ${status === 'ALL' ? 'active' : ''}`}
                        onClick={() => handleTabChange('ALL')}
                    >
                        Tất cả hóa đơn
                    </button>
                    <button
                        type="button"
                        className={`customer-tab-btn ${status === 'UNPAID' ? 'active' : ''}`}
                        onClick={() => handleTabChange('UNPAID')}
                    >
                        Chưa thanh toán
                    </button>
                    <button
                        type="button"
                        className={`customer-tab-btn ${status === 'PAID' ? 'active' : ''}`}
                        onClick={() => handleTabChange('PAID')}
                    >
                        Đã thanh toán
                    </button>
                    <button
                        type="button"
                        className={`customer-tab-btn ${status === 'REFUNDED' ? 'active' : ''}`}
                        onClick={() => handleTabChange('REFUNDED')}
                    >
                        Đã hoàn tiền
                    </button>
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearchSubmit} className="host-search-wrapper" style={{ maxWidth: '480px' }}>
                    <span className="host-search-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        className="host-search-input"
                        placeholder="Tìm theo mã hóa đơn hoặc tên khách sạn..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </form>
            </div>

            {/* Error State */}
            {error && (
                <div className="invoice-alert error" style={{ justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <span>{error}</span>
                    </div>
                    <button
                        onClick={() => setReloadTrigger((r) => r + 1)}
                        style={{
                            background: 'transparent',
                            border: '1px solid currentColor',
                            borderRadius: '8px',
                            color: 'inherit',
                            padding: '4px 10px',
                            fontSize: '12px',
                            cursor: 'pointer',
                        }}
                    >
                        Thử lại
                    </button>
                </div>
            )}

            {/* Loading State */}
            {loading && (
                <div style={{ padding: '60px 0', textAlign: 'center', color: '#718096' }}>
                    <div className="spinner" style={{ margin: '0 auto 12px', borderColor: '#1a2e20', borderTopColor: 'transparent', width: '28px', height: '28px' }} />
                    <p style={{ fontSize: '14px' }}>Đang tải danh sách hóa đơn của bạn...</p>
                </div>
            )}

            {/* Empty State */}
            {!loading && !error && invoices.length === 0 && (
                <div className="empty-state-box">
                    <div className="empty-icon-circle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <rect x="2" y="4" width="20" height="16" rx="2" />
                            <line x1="6" y1="8" x2="10" y2="8" />
                            <line x1="6" y1="12" x2="18" y2="12" />
                            <line x1="6" y1="16" x2="14" y2="16" />
                        </svg>
                    </div>
                    <h3 className="empty-title">Bạn chưa có hóa đơn nào</h3>
                    <p className="empty-desc">
                        {activeSearch || status !== 'ALL'
                            ? 'Không tìm thấy hóa đơn nào phù hợp với bộ lọc tìm kiếm hiện tại.'
                            : 'Khi bạn đặt phòng và hoàn tất kỳ lưu trú, các hóa đơn thanh toán sẽ xuất hiện tại đây.'}
                    </p>
                    <Link
                        to="/"
                        className="btn-create-invoice-link"
                        style={{ display: 'inline-flex' }}
                    >
                        <span>Khám phá chỗ nghỉ StayHub</span>
                    </Link>
                </div>
            )}

            {/* Invoices Cards Grid */}
            {!loading && !error && invoices.length > 0 && (
                <>
                    <div className="customer-invoice-grid">
                        {invoices.map((inv) => {
                            const st = inv.payment_status || 'UNPAID';
                            return (
                                <div key={inv.id} className="customer-invoice-card">
                                    <div>
                                        <div className="customer-card-header">
                                            <div>
                                                <h3 className="customer-property-title">
                                                    {inv.property_name || 'Cơ sở lưu trú StayHub'}
                                                </h3>
                                                <div style={{ fontSize: '12.5px', color: '#785a28', fontWeight: 500 }}>
                                                    {inv.room_name || 'Phòng nghỉ'}
                                                </div>
                                            </div>
                                            <span className={`status-badge ${st.toLowerCase()}`}>
                                                {getStatusLabel(st)}
                                            </span>
                                        </div>

                                        <div className="customer-card-meta">
                                            <div>Mã hóa đơn: <strong>#{inv.invoice_code}</strong></div>
                                            <div>Mã đặt phòng: <strong>#{inv.booking_id}</strong></div>
                                            <div>Ngày lập: {formatDate(inv.created_at)}</div>
                                        </div>
                                    </div>

                                    <div className="customer-card-footer">
                                        <div>
                                            <div style={{ fontSize: '11px', textTransform: 'uppercase', color: '#718096', letterSpacing: '0.5px' }}>
                                                Tổng chi phí
                                            </div>
                                            <div className="customer-card-amount">
                                                {formatVnd(inv.total_amount)}
                                            </div>
                                        </div>

                                        <Link
                                            to={`/customer/invoices/${inv.id}`}
                                            className="action-btn-link"
                                            title="Xem chi tiết hóa đơn này"
                                        >
                                            <span>Xem chi tiết</span>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                <polyline points="9 18 15 12 9 6" />
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Pagination */}
                    {paginationMeta.lastPage > 1 && (
                        <div className="host-pagination">
                            <button
                                type="button"
                                className="page-btn"
                                disabled={page <= 1}
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <polyline points="15 18 9 12 15 6" />
                                </svg>
                                <span>Trước</span>
                            </button>

                            <span className="page-indicator">
                                Trang {paginationMeta.currentPage} / {paginationMeta.lastPage} (Tổng {paginationMeta.total} hóa đơn)
                            </span>

                            <button
                                type="button"
                                className="page-btn"
                                disabled={page >= paginationMeta.lastPage}
                                onClick={() => setPage((p) => Math.min(paginationMeta.lastPage, p + 1))}
                            >
                                <span>Sau</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg>
                            </button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

export default CustomerInvoiceListPage;
