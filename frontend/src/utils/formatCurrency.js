/** Format a number as Vietnamese Dong. */
export function formatCurrency(amount) {
  const value = amount === null || amount === undefined || Number.isNaN(Number(amount)) ? 0 : Number(amount);
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}
