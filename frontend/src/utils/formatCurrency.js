/**
 * Format currency to Vietnamese Dong (VND).
 * e.g., 500000 -> "500.000 ₫"
 */
export function formatCurrency(amount) {
    if (amount === null || amount === undefined || isNaN(Number(amount))) {
        return '0 ₫';
    }

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(Number(amount));
}
