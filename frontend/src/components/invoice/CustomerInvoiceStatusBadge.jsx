/**
 * Component hiển thị huy hiệu trạng thái thanh toán của hóa đơn theo chuẩn StayNest Quiet Luxury.
 * Hỗ trợ 3 trạng thái chuẩn xác từ Backend: PAID, UNPAID, REFUNDED.
 *
 * @param {Object} props
 * @param {string} props.status Trạng thái thanh toán ('PAID' | 'UNPAID' | 'REFUNDED')
 * @param {string} [props.className] Class CSS bổ sung
 */
export default function CustomerInvoiceStatusBadge({ status, className = '' }) {
    const normalized = (status || 'UNPAID').toUpperCase();

    const config = {
        PAID: {
            label: 'Đã thanh toán',
            dot: 'bg-emerald-600',
            badge: 'bg-emerald-50 text-emerald-800 border-emerald-200/70',
        },
        UNPAID: {
            label: 'Chưa thanh toán',
            dot: 'bg-amber-600',
            badge: 'bg-amber-50 text-amber-800 border-amber-200/70',
        },
        REFUNDED: {
            label: 'Đã hoàn tiền',
            dot: 'bg-purple-600',
            badge: 'bg-purple-50 text-purple-800 border-purple-200/70',
        },
    };

    const current = config[normalized] || {
        label: normalized,
        dot: 'bg-stone-500',
        badge: 'bg-stone-100 text-stone-700 border-stone-200',
    };

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide ${current.badge} ${className}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${current.dot}`} aria-hidden="true" />
            <span>{current.label}</span>
        </span>
    );
}
