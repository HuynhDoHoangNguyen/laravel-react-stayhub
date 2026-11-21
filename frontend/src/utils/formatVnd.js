const currency = new Intl.NumberFormat('vi-VN', {
  style: 'currency', currency: 'VND', minimumFractionDigits: 0, maximumFractionDigits: 0,
});

/** Presentation only: round canonical nonnegative decimal strings to the nearest dong, half up. */
export function formatVnd(amount) {
  if (typeof amount !== 'string' || !/^(?:0|[1-9][0-9]*)\.[0-9]{2}(?![\s\S])/.test(amount)) {
    throw new TypeError('Giá trị tiền VND không hợp lệ.');
  }
  const scaled = BigInt(amount.replace('.', ''));
  return currency.format((scaled + 50n) / 100n);
}
