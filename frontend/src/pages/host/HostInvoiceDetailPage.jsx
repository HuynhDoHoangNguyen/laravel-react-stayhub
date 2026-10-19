import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getHostInvoiceDetail, parseInvoiceError } from '../../services/invoiceService';
import InvoiceDetail from '../../components/invoice/InvoiceDetail';
import '../../components/invoice/Invoice.css';

/**
 * Trang xem chi tiết hóa đơn dành cho Host.
 * Route: /host/invoices/:invoiceId
 */
function HostInvoiceDetailPage() {
    const { invoiceId } = useParams();
    const [invoice, setInvoice] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let isMounted = true;

        async function fetchDetail() {
            setLoading(true);
            setError(null);
            try {
                const res = await getHostInvoiceDetail(invoiceId);
                if (isMounted) {
                    setInvoice(res.data);
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
        }

        if (invoiceId) {
            fetchDetail();
        }

        return () => {
            isMounted = false;
        };
    }, [invoiceId]);

    return (
        <div style={{ maxWidth: '960px', margin: '0 auto', textAlign: 'left' }}>
            {/* Top Navigation & Breadcrumb */}
            <div style={{ marginBottom: '20px' }}>
                <Link
                    to="/host/invoices"
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '6px',
                        color: '#718096',
                        textDecoration: 'none',
                        fontSize: '13.5px',
                        fontWeight: 500,
                    }}
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    <span>Quay lại danh sách hóa đơn</span>
                </Link>
            </div>

            {/* Error State */}
            {error && (
                <div className="invoice-alert error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span>{error}</span>
                </div>
            )}

            {/* Loading State */}
            {loading && (
                <div style={{ padding: '60px 0', textAlign: 'center', color: '#718096' }}>
                    <div className="spinner" style={{ margin: '0 auto 12px', borderColor: '#1a2e20', borderTopColor: 'transparent', width: '28px', height: '28px' }} />
                    <p style={{ fontSize: '14px' }}>Đang tải thông tin chi tiết hóa đơn...</p>
                </div>
            )}

            {/* Invoice Detail Component */}
            {!loading && !error && invoice && (
                <InvoiceDetail invoice={invoice} />
            )}
        </div>
    );
}

export default HostInvoiceDetailPage;
