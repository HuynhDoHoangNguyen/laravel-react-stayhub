import { localValidationError } from './apiError.js';

/** Canonical positive safe integer. Strings such as 1e2, 1.9 or 01 are rejected. */
export function requireApiId(value, label = 'Mã thông tin') {
  const validType = typeof value === 'number' || (typeof value === 'string' && /^[1-9]\d*$/.test(value));
  const number = validType ? Number(value) : NaN;
  if (!Number.isSafeInteger(number) || number <= 0) {
    throw localValidationError(`${label} không hợp lệ.`, {}, 400);
  }
  return number;
}
