import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import CreateInvoiceButton from '../../components/invoice/CreateInvoiceButton';
import InvoiceDetail from '../../components/invoice/InvoiceDetail';
import '../../components/invoice/Invoice.css';

/**
 * Trang tạo và xem Hóa đơn dành cho đối tác Host (TV3 - Billing).
 * Route: /host/bookings/:bookingId/create-invoice hoặc /host/invoices/create
 */
function HostInvoiceCreationPage() {
    const { bookingId: paramBookingId } = useParams();

    const [manualBookingId, setManualBookingId] = useState('');
    const [createdInvoice, setCreatedInvoice] = useState(null);
    const [errorMessage, setErrorMessage] = useState('');
    const [successMessage, setSuccessMessage] = useState('');

    // Booking ID hoạt động: ưu tiên lấy từ URL params (:bookingId), nếu không có thì lấy từ ô nhập
    const activeBookingId = paramBookingId ? paramBookingId.trim() : manualBookingId.trim();

    const handleSuccess = (invoiceData) => {
        setErrorMessage('');
        setSuccessMessage('Hóa đơn đã được lập thành công và lưu vào hệ thống!');
        setCreatedInvoice(invoiceData);
    };

    const handleError = (errorObj) => {
        setSuccessMessage('');
        setErrorMessage(errorObj.message);
    };

    const handleReset = () => {
        setCreatedInvoice(null);
        setErrorMessage('');
        setSuccessMessage('');
        if (!paramBookingId) {
            setManualBookingId('');
        }
    };

    const handleBookingIdChange = (e) => {
        setManualBookingId(e.target.value.trim());
        setErrorMessage('');
    };

    return (
        <div style={{ maxWidth: '960px', margin: '0 auto', textAlign: 'left' }}>
            {/* Header & Breadcrumb */}
            <div style={{ marginBottom: '28px' }}>
                <nav style={{ fontSize: '13px', color: '#718096', marginBottom: '10px' }}>
                    <Link to="/host" style={{ color: '#1a2e20', textDecoration: 'none', fontWeight: 500 }}>
                        Host Console
                    </Link>
                    {' / '}
                    <span>Lập hóa đơn</span>
                </nav>
                <h1
                    style={{
                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                        fontSize: '32px',
                        fontWeight: 700,
                        margin: '0 0 8px 0',
                        color: '#1a2e20',
                        letterSpacing: '-0.3px',
                    }}
                >
                    Lập hóa đơn thanh toán
                </h1>
                <p style={{ fontSize: '15px', color: '#5c6960', margin: 0 }}>
                    Tạo chứng từ thanh toán chính thức cho khách hàng sau khi hoàn tất kỳ nghỉ (COMPLETED).
                </p>
            </div>

            {/* Thông báo Thành công / Thất bại */}
            {successMessage && (
                <div className="invoice-alert success" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <span>{successMessage}</span>
                </div>
            )}

            {errorMessage && (
                <div className="invoice-alert error" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span>{errorMessage}</span>
                </div>
            )}

            {/* Khung tạo hóa đơn khi chưa có hóa đơn được tạo */}
            {!createdInvoice && (
                <div className="invoice-card">
                    <h2
                        style={{
                            fontFamily: "'Cormorant Garamond', Georgia, serif",
                            fontSize: '22px',
                            fontWeight: 700,
                            marginBottom: '16px',
                            color: '#1a2e20',
                        }}
                    >
                        Thông tin đơn đặt phòng
                    </h2>

                    <div style={{ marginBottom: '20px' }}>
                        <label
                            htmlFor="booking-id-input"
                            style={{ display: 'block', fontSize: '13.5px', fontWeight: 600, color: '#2d3748', marginBottom: '8px' }}
                        >
                            Mã ID đơn đặt phòng (Booking ID) *
                        </label>
                        <input
                            id="booking-id-input"
                            type="text"
                            value={paramBookingId || manualBookingId}
                            onChange={handleBookingIdChange}
                            placeholder="Ví dụ: 13"
                            style={{
                                width: '100%',
                                maxWidth: '320px',
                                padding: '12px 16px',
                                borderRadius: '12px',
                                border: '1px solid #e9e4db',
                                fontSize: '15px',
                                boxSizing: 'border-box',
                                backgroundColor: paramBookingId ? '#f7fafc' : '#ffffff',
                                color: '#111a13',
                                outline: 'none',
                            }}
                            disabled={!!paramBookingId}
                        />
                        {paramBookingId && (
                            <span style={{ display: 'block', fontSize: '12px', color: '#718096', marginTop: '6px' }}>
                                (Mã Booking được tiếp nhận tự động từ đường dẫn của TV2)
                            </span>
                        )}
                    </div>

                    <div className="invoice-alert info" style={{ fontSize: '13.5px', margin: '20px 0' }}>
                        <span>
                            <strong>Quy tắc nghiệp vụ StayHub:</strong> Đơn đặt phòng phải ở trạng thái <code>COMPLETED</code>, 
                            thuộc cơ sở lưu trú của bạn và chưa từng được lập hóa đơn trước đó. 
                            Các chi phí tiền phòng và dịch vụ phát sinh được tự động lấy từ snapshot hệ thống.
                        </span>
                    </div>

                    <div style={{ display: 'flex', gap: '14px', alignItems: 'center', marginTop: '24px' }}>
                        <CreateInvoiceButton
                            bookingId={activeBookingId}
                            onSuccess={handleSuccess}
                            onError={handleError}
                            disabled={!activeBookingId || activeBookingId === ''}
                        />
                        <Link
                            to="/host"
                            style={{
                                padding: '12px 20px',
                                fontSize: '14px',
                                fontWeight: 500,
                                color: '#5c6960',
                                textDecoration: 'none',
                                border: '1px solid #e9e4db',
                                borderRadius: '12px',
                                background: '#ffffff',
                                transition: 'background 0.15s',
                            }}
                        >
                            Quay lại Console
                        </Link>
                    </div>
                </div>
            )}

            {/* Chi tiết hóa đơn vừa tạo thành công */}
            {createdInvoice && (
                <div>
                    <InvoiceDetail invoice={createdInvoice} />

                    <div style={{ display: 'flex', gap: '14px', marginTop: '20px' }}>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="btn-invoice-action"
                            style={{ background: '#2d4f38' }}
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="6 9 6 2 18 2 18 9" />
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                <rect x="6" y="14" width="12" height="8" />
                            </svg>
                            <span>In hóa đơn</span>
                        </button>

                        <button
                            type="button"
                            onClick={handleReset}
                            style={{
                                padding: '12px 20px',
                                fontSize: '14px',
                                fontWeight: 500,
                                color: '#5c6960',
                                border: '1px solid #e9e4db',
                                borderRadius: '12px',
                                background: '#ffffff',
                                cursor: 'pointer',
                            }}
                        >
                            Lập hóa đơn khác
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default HostInvoiceCreationPage;
