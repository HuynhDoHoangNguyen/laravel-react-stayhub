import { formatVnd } from '../../services/invoiceService';
import './Invoice.css';

/**
 * Component hiển thị chi tiết hóa đơn (Invoice Detail).
 * Dữ liệu hoàn toàn được nạp từ response của Backend, không tự tính toán lại tiền.
 */
function InvoiceDetail({ invoice }) {
    if (!invoice) return null;

    const items = invoice.items || [];
    const status = invoice.payment_status || 'UNPAID';

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
            return new Date(dateStr).toLocaleString('vi-VN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return dateStr;
        }
    };

    return (
        <div className="invoice-card" id={`invoice-${invoice.id}`}>
            {/* Header: Mã hóa đơn & Trạng thái */}
            <div className="invoice-header-row">
                <div className="invoice-code-badge">
                    <h3 className="invoice-title">Hóa đơn #{invoice.invoice_code}</h3>
                    <span className={`status-badge ${status.toLowerCase()}`}>
                        {getStatusLabel(status)}
                    </span>
                </div>
                <div className="invoice-date-meta">
                    <span style={{ fontSize: '13px', color: '#718096' }}>
                        Ngày lập: {formatDate(invoice.created_at)}
                    </span>
                </div>
            </div>

            {/* Thông tin khách hàng & phòng lưu trú */}
            <div className="invoice-info-grid">
                <div className="info-item">
                    <h4>Đơn đặt phòng</h4>
                    <p>Mã Booking: #{invoice.booking_id}</p>
                </div>
                <div className="info-item">
                    <h4>Khách hàng</h4>
                    <p>{invoice.customer_name || '—'}</p>
                    <span style={{ fontSize: '13px', color: '#718096' }}>
                        {invoice.customer_phone || ''} {invoice.customer_email ? `• ${invoice.customer_email}` : ''}
                    </span>
                </div>
                <div className="info-item">
                    <h4>Cơ sở lưu trú</h4>
                    <p>{invoice.property_name || '—'}</p>
                </div>
                <div className="info-item">
                    <h4>Phòng</h4>
                    <p>{invoice.room_name || '—'}</p>
                </div>
            </div>

            {/* Bảng chi tiết các mục dòng hóa đơn (ROOM & SERVICES) */}
            <div className="invoice-table-wrapper">
                <table className="invoice-table">
                    <thead>
                        <tr>
                            <th className="col-type">Loại</th>
                            <th>Hạng mục</th>
                            <th className="col-numeric">Số lượng</th>
                            <th className="col-numeric">Đơn giá</th>
                            <th className="col-numeric">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 ? (
                            <tr>
                                <td colSpan="5" style={{ textAlign: 'center', color: '#a0aec0', padding: '20px' }}>
                                    Không có dòng chi tiết nào.
                                </td>
                            </tr>
                        ) : (
                            items.map((item) => (
                                <tr key={item.id || item.item_name}>
                                    <td className="col-type">
                                        <span className={`item-type-pill ${item.item_type?.toLowerCase()}`}>
                                            {item.item_type === 'ROOM' ? 'Phòng' : 'Dịch vụ'}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{item.item_name}</strong>
                                        {item.description && (
                                            <div style={{ fontSize: '12px', color: '#718096', marginTop: '2px' }}>
                                                {item.description}
                                            </div>
                                        )}
                                    </td>
                                    <td className="col-numeric">
                                        {item.quantity ? parseFloat(item.quantity) : 0}
                                    </td>
                                    <td className="col-numeric">
                                        {formatVnd(item.unit_price)}
                                    </td>
                                    <td className="col-numeric">
                                        <strong>{formatVnd(item.amount)}</strong>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Tóm tắt tổng tiền */}
            <div className="invoice-summary-box">
                <div className="summary-row">
                    <span>Tạm tính (Subtotal):</span>
                    <span className="summary-value">{formatVnd(invoice.subtotal)}</span>
                </div>
                {invoice.discount_amount && parseFloat(invoice.discount_amount) > 0 && (
                    <div className="summary-row" style={{ color: '#c53030' }}>
                        <span>Giảm giá:</span>
                        <span className="summary-value">- {formatVnd(invoice.discount_amount)}</span>
                    </div>
                )}
                {invoice.extra_fee && parseFloat(invoice.extra_fee) > 0 && (
                    <div className="summary-row">
                        <span>Phụ phí:</span>
                        <span className="summary-value">+ {formatVnd(invoice.extra_fee)}</span>
                    </div>
                )}
                <div className="summary-row total">
                    <span>Tổng thanh toán:</span>
                    <span className="summary-value">{formatVnd(invoice.total_amount)}</span>
                </div>
            </div>
        </div>
    );
}

export default InvoiceDetail;
