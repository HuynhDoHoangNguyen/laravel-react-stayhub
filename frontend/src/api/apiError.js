/** Safe error view for domain consumers. Never retains Axios config/request. */
export function normalizeApiError(error, { now = Date.now() } = {}) {
  if (error?.isApiError === true) return error;

  const cancelled = error?.code === 'ERR_CANCELED' || ['CanceledError', 'AbortError'].includes(error?.name);
  const timeout = !cancelled && ['ECONNABORTED', 'ETIMEDOUT'].includes(error?.code);
  const local = error?.isLocalValidation === true;
  const rawStatus = error?.response?.status ?? (local ? error.status : 0);
  const status = Number.isInteger(rawStatus) && rawStatus >= 100 && rawStatus <= 599 ? rawStatus : 0;
  const body = error?.response?.data;
  const data = body && typeof body === 'object' && !Array.isArray(body) ? body : {};
  const network = !cancelled && !timeout && !local && !status && error?.isAxiosError === true;
  const kind = cancelled ? 'cancelled' : timeout ? 'timeout' : local ? 'validation'
    : status ? 'http' : network ? 'network' : 'unknown';
  const fields = {};
  const rawFields = local ? error.fields : data.errors;
  if ((status === 422 || local) && rawFields && typeof rawFields === 'object' && !Array.isArray(rawFields)) {
    for (const [key, messages] of Object.entries(rawFields)) {
      if (['__proto__', 'constructor', 'prototype'].includes(key)) continue;
      if (Array.isArray(messages) && messages.every((message) => typeof message === 'string')) fields[key] = [...messages];
    }
  }
  let retryAfter = 0;
  if (status === 429) {
    const raw = error.response?.headers?.get?.('retry-after') ?? error.response?.headers?.['retry-after'];
    if (typeof raw === 'number' || (typeof raw === 'string' && /^\d+$/.test(raw.trim()))) {
      const seconds = Number(raw);
      if (Number.isSafeInteger(seconds) && seconds >= 0) retryAfter = seconds;
    } else if (typeof raw === 'string' && /^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/.test(raw)) {
      const time = Date.parse(raw);
      if (Number.isFinite(time)) retryAfter = Math.max(0, Math.ceil((time - now) / 1000));
    }
  }
  const defaults = {
    401: 'Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền thực hiện thao tác này.',
    404: 'Không tìm thấy thông tin yêu cầu.',
    409: 'Dữ liệu đã thay đổi hoặc thao tác bị trùng. Vui lòng kiểm tra lại.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng thử lại.',
    422: 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.',
    429: 'Bạn thao tác quá nhanh. Vui lòng thử lại sau.',
  };
  let message = defaults[status] || 'Đã xảy ra lỗi. Vui lòng thử lại sau.';
  if (status >= 500) message = 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.';
  else if (local) message = error.message;
  else if (status >= 400 && status < 500 && typeof data.message === 'string' && data.message.trim()) message = data.message;
  if (cancelled) message = 'Yêu cầu đã được hủy.';
  else if (timeout) message = 'Máy chủ phản hồi quá chậm. Vui lòng thử lại.';
  else if (network) message = 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại mạng.';
  const candidateCode = local ? error.code : data.code;
  return {
    isApiError: true, status, kind, message,
    code: typeof candidateCode === 'string' && /^[A-Z][A-Z0-9_]*$/.test(candidateCode) ? candidateCode : null,
    fields, retryAfter, isNetworkError: network, isCancelled: cancelled, isTimeout: timeout,
    isNotFound: status === 404, isConflict: status === 409, isValidation: status === 422 || local,
  };
}

export function localValidationError(message, fields = {}, status = 422) {
  return Object.assign(new Error(message), { isLocalValidation: true, status, code: 'INVALID_INPUT', fields });
}
