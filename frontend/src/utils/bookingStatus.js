/**
 * Configuration and labels for 6 booking statuses.
 */
export const BOOKING_STATUSES = {
    PENDING: {
        key: 'PENDING',
        label: 'Chờ xác nhận',
        color: '#d97706', // amber-600
        bgColor: '#fef3c7', // amber-100
        variant: 'warning',
    },
    CONFIRMED: {
        key: 'CONFIRMED',
        label: 'Đã xác nhận',
        color: '#2563eb', // blue-600
        bgColor: '#dbeafe', // blue-100
        variant: 'primary',
    },
    CHECKED_IN: {
        key: 'CHECKED_IN',
        label: 'Đã nhận phòng',
        color: '#0d9488', // teal-600
        bgColor: '#ccfbf1', // teal-100
        variant: 'info',
    },
    COMPLETED: {
        key: 'COMPLETED',
        label: 'Đã hoàn thành',
        color: '#16a34a', // green-600
        bgColor: '#dcfce7', // green-100
        variant: 'success',
    },
    CANCELLED: {
        key: 'CANCELLED',
        label: 'Đã hủy',
        color: '#64748b', // slate-500
        bgColor: '#f1f5f9', // slate-100
        variant: 'neutral',
    },
    REJECTED: {
        key: 'REJECTED',
        label: 'Từ chối',
        color: '#dc2626', // red-600
        bgColor: '#fee2e2', // red-100
        variant: 'danger',
    },
};

export function getBookingStatusInfo(statusKey) {
    if (!statusKey) {
        return {
            label: 'Không xác định',
            color: '#64748b',
            bgColor: '#f1f5f9',
            variant: 'neutral',
        };
    }

    const key = String(statusKey).toUpperCase();
    return BOOKING_STATUSES[key] || {
        label: key,
        color: '#64748b',
        bgColor: '#f1f5f9',
        variant: 'neutral',
    };
}
