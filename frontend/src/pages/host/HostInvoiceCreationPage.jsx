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
        <div style={{ padding: '24px 16px', maxWidth: '900px', margin: '0 auto', textAlign: 'left' }}>
            {/* Header & Breadcrumb */}
            <div style={{ marginBottom: '24px' }}>
                <nav style={{ fontSize: '13px', color: '#718096', marginBottom: '8px' }}>
                    <Link to="/host" style={{ color: '#2b6cb0', textDecoration: 'none' }}>
                        Host Dashboard
                    </Link>
                    {' / '}
                    <span>Lập hóa đơn</span>
                </nav>
                <h1 style={{ fontSize: '28px', margin: '0 0 8px 0', color: '#1a2e20' }}>
                    Lập hóa đơn đơn đặt phòng
                </h1>
                <p style={{ fontSize: '15px', color: '#718096', margin: 0 }}>
                    Tạo hóa đơn thanh toán chính thức cho khách hàng sau khi hoàn tất kỳ nghỉ (COMPLETED).
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
                    <h2 style={{ fontSize: '18px', marginBottom: '16px', color: '#2d3748' }}>
                        Thông tin đơn đặt phòng
                    </h2>

                    <div style={{ marginBottom: '20px' }}>
                        <label
                            htmlFor="booking-id-input"
                            style={{ display: 'block', fontSize: '14px', fontWeight: 600, color: '#4a5568', marginBottom: '6px' }}
                        >
                            Mã ID đơn đặt phòng (Booking ID) *
                        </label>
                        <input
                            id="booking-id-input"
                            type="text"
                            value={paramBookingId || manualBookingId}
                            onChange={handleBookingIdChange}
                            placeholder="Ví dụ: 10"
                            style={{
                                width: '100%',
                                maxWidth: '300px',
                                padding: '10px 14px',
                                borderRadius: '8px',
                                border: '1px solid #cbd5e0',
                                fontSize: '15px',
                                boxSizing: 'border-box',
                            }}
                            disabled={!!paramBookingId}
                        />
                        {paramBookingId && (
                            <span style={{ display: 'block', fontSize: '12px', color: '#718096', marginTop: '4px' }}>
                                (Mã Booking được tiếp nhận tự động từ URL đường dẫn)
                            </span>
                        )}
                    </div>

                    <div className="invoice-alert info" style={{ fontSize: '13px', margin: '16px 0' }}>
                        <span>
                            <strong>Quy tắc nghiệp vụ:</strong> Đơn đặt phòng phải ở trạng thái <code>COMPLETED</code>, 
                            thuộc cơ sở lưu trú của bạn và chưa từng được lập hóa đơn trước đó. 
                            Các chi phí tiền phòng và dịch vụ sẽ được tính toán tự động từ snapshot hệ thống.
                        </span>
                    </div>

                    <div style={{ display: 'flex', gap: '12px', alignItems: 'center', marginTop: '20px' }}>
                        <CreateInvoiceButton
                            bookingId={activeBookingId}
                            onSuccess={handleSuccess}
                            onError={handleError}
                            disabled={!activeBookingId || activeBookingId === ''}
                        />
                        <Link
                            to="/host"
                            style={{
                                padding: '10px 18px',
                                fontSize: '15px',
                                color: '#4a5568',
                                textDecoration: 'none',
                                border: '1px solid #e2e8f0',
                                borderRadius: '8px',
                                background: '#f8fafc',
                            }}
                        >
                            Quay lại Dashboard
                        </Link>
                    </div>
                </div>
            )}

            {/* Chi tiết hóa đơn vừa tạo thành công */}
            {createdInvoice && (
                <div>
                    <InvoiceDetail invoice={createdInvoice} />

                    <div style={{ display: 'flex', gap: '12px', marginTop: '16px' }}>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="btn-invoice-action"
                            style={{ background: '#2b6cb0' }}
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
                                padding: '10px 18px',
                                fontSize: '15px',
                                color: '#4a5568',
                                border: '1px solid #cbd5e0',
                                borderRadius: '8px',
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
