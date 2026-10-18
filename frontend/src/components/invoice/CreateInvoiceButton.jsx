import { useState } from 'react';
import { createHostInvoice, parseInvoiceError } from '../../services/invoiceService';
import './Invoice.css';

/**
 * Nút hành động Lập hóa đơn độc lập, tái sử dụng cho Host Dashboard hoặc Bảng Booking.
 */
function CreateInvoiceButton({ bookingId, onSuccess, onError, disabled = false, className = '' }) {
    const [loading, setLoading] = useState(false);

    const handleClick = async () => {
        if (loading || disabled || !bookingId) return;

        setLoading(true);
        try {
            const result = await createHostInvoice(bookingId);
            if (onSuccess) {
                onSuccess(result.data || result);
            }
        } catch (err) {
            const parsed = parseInvoiceError(err);
            if (onError) {
                onError(parsed);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <button
            type="button"
            className={`btn-invoice-action ${className}`}
            onClick={handleClick}
            disabled={loading || disabled || !bookingId}
            aria-busy={loading}
        >
            {loading ? (
                <>
                    <span className="spinner" aria-hidden="true" />
                    <span>Đang lập hóa đơn...</span>
                </>
            ) : (
                <>
                    <svg
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <polyline points="10 9 9 9 8 9" />
                    </svg>
                    <span>Lập hóa đơn</span>
                </>
            )}
        </button>
    );
}

export default CreateInvoiceButton;
