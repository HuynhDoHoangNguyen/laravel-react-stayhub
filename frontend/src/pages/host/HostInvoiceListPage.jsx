import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { getHostInvoices, formatVnd, parseInvoiceError } from '../../services/invoiceService';
import '../../components/invoice/Invoice.css';

/**
 * Trang danh sách quản lý Hóa đơn của Host (StayNest Manager Pattern).
 * Route: /host/invoices
 */
function HostInvoiceListPage() {
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
                const res = await getHostInvoices({
                    page,
                    perPage: 10,
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

    const handleStatusChange = (e) => {
        setStatus(e.target.value);
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
        <div style={{ maxWidth: '1100px', margin: '0 auto', textAlign: 'left' }}>
            {/* Header */}
            <div style={{ marginBottom: '24px' }}>
                <p style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.24em', color: '#785a28', fontWeight: 600, margin: '0 0 6px' }}>
                    Quản lý · Hóa đơn
                </p>
                <h1
                    style={{
                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                        fontSize: '32px',
                        fontWeight: 700,
                        color: '#1a2e20',
                        margin: '0 0 6px 0',
                        letterSpacing: '-0.4px',
                    }}
                >
                    Danh sách hóa đơn
                </h1>
                <p style={{ color: '#5c6960', fontSize: '14.5px', margin: 0 }}>
                    Theo dõi và kiểm soát các khoản thanh toán phát sinh từ các đơn đặt phòng thuộc cơ sở lưu trú của bạn.
                </p>
            </div>

            {/* Toolbar Filter & Search */}
            <div className="host-filter-bar">
                <div>
                    <select
                        className="host-filter-select"
                        value={status}
                        onChange={handleStatusChange}
                        aria-label="Lọc theo trạng thái thanh toán"
                    >
                        <option value="ALL">Tất cả trạng thái</option>
                        <option value="UNPAID">Chưa thanh toán (UNPAID)</option>
                        <option value="PAID">Đã thanh toán (PAID)</option>
                        <option value="REFUNDED">Đã hoàn tiền (REFUNDED)</option>
                    </select>
                </div>

                <form onSubmit={handleSearchSubmit} className="host-search-wrapper">
                    <span className="host-search-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        className="host-search-input"
                        placeholder="Tìm theo mã hóa đơn hoặc tên khách hàng..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </form>

                <div>
                    <Link to="/host/invoices/create" className="btn-create-invoice-link">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Lập hóa đơn mới</span>
                    </Link>
                </div>
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
                    <p style={{ fontSize: '14px' }}>Đang tải danh sách hóa đơn...</p>
                </div>
            )}

            {/* Empty State */}
            {!loading && !error && invoices.length === 0 && (
                <div className="empty-state-box">
                    <div className="empty-icon-circle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                    </div>
                    <h3 className="empty-title">Không tìm thấy hóa đơn nào</h3>
                    <p className="empty-desc">
                        {activeSearch || status !== 'ALL'
                            ? 'Không có hóa đơn nào khớp với tiêu chí tìm kiếm hoặc bộ lọc hiện tại của bạn.'
                            : 'Hiện tại chưa có hóa đơn nào được lập cho các đơn đặt phòng của bạn.'}
                    </p>
                    <Link to="/host/invoices/create" className="btn-create-invoice-link" style={{ display: 'inline-flex' }}>
                        <span>Lập hóa đơn mới</span>
                    </Link>
                </div>
            )}

            {/* Data Table */}
            {!loading && !error && invoices.length > 0 && (
                <>
                    <div className="host-table-card">
                        <table className="host-table">
                            <thead>
                                <tr>
                                    <th>Mã hóa đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Cơ sở & Phòng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày lập</th>
                                    <th style={{ textAlign: 'right' }}>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map((inv) => {
                                    const st = inv.payment_status || 'UNPAID';
                                    return (
                                        <tr key={inv.id}>
                                            <td>
                                                <div style={{ fontWeight: 600, color: '#1a2e20' }}>
                                                    #{inv.invoice_code}
                                                </div>
                                                <div style={{ fontSize: '12px', color: '#718096', marginTop: '2px' }}>
                                                    Booking #{inv.booking_id}
                                                </div>
                                            </td>
                                            <td>
                                                <div style={{ fontWeight: 500 }}>{inv.customer_name || '—'}</div>
                                                <div style={{ fontSize: '12px', color: '#718096' }}>
                                                    {inv.customer_phone || inv.customer_email || ''}
                                                </div>
                                            </td>
                                            <td>
                                                <div style={{ fontWeight: 500 }}>{inv.property_name || '—'}</div>
                                                <div style={{ fontSize: '12px', color: '#718096' }}>{inv.room_name || '—'}</div>
                                            </td>
                                            <td>
                                                <span style={{ fontWeight: 700, color: '#1a2e20', fontSize: '14.5px' }}>
                                                    {formatVnd(inv.total_amount)}
                                                </span>
                                            </td>
                                            <td>
                                                <span className={`status-badge ${st.toLowerCase()}`}>
                                                    {getStatusLabel(st)}
                                                </span>
                                            </td>
                                            <td style={{ color: '#5c6960', fontSize: '13px' }}>
                                                {formatDate(inv.created_at)}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <Link
                                                    to={`/host/invoices/${inv.id}`}
                                                    className="action-btn-link"
                                                    title="Xem chi tiết hóa đơn"
                                                >
                                                    <span>Chi tiết</span>
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                        <polyline points="9 18 15 12 9 6" />
                                                    </svg>
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
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

export default HostInvoiceListPage;
