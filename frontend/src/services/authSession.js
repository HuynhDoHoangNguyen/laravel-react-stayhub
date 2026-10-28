const ACCOUNT_CODES = new Set(['ACCOUNT_BLOCKED', 'ACCOUNT_INACTIVE']);

export function isAccountFailure(error) {
    return error?.response?.status === 403 && ACCOUNT_CODES.has(error.response.data?.code);
}

export function normalizeAuthError(error) {
    if (error?.isAuthError) return error;
    const status = error?.response?.status || 0;
    const data = error?.response?.data;
    const retryAfter = Math.max(1, Number(error?.response?.headers?.['retry-after'] || data?.retry_after) || 60);
    let message = 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra mạng và thử lại.';
    if (status === 401) message = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
    else if (status === 419) message = 'Phiên bảo mật đã hết hạn. Vui lòng thử lại để làm mới phiên.';
    else if (status === 429) message = `Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau ${retryAfter} giây.`;
    else if (status >= 500) message = 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.';
    else if (status === 403) message = data?.message || 'Bạn không có quyền thực hiện thao tác này.';
    else if (status === 422) message = 'Vui lòng kiểm tra thông tin đăng nhập.';

    const result = new Error(message);
    result.isAuthError = true;
    result.status = status;
    result.code = isAccountFailure(error) ? data.code : null;
    result.fields = status === 422 ? (data?.errors || {}) : {};
    result.retryAfter = status === 429 ? retryAfter : 0;
    // Do not retain Axios config/request: they can contain login credentials.
    return result;
}

function isProtectedRequest(url = '') {
    return url === '/logout' || (url.startsWith('/api/') && !url.startsWith('/api/public/') && url !== '/api/test');
}

// Install once per provider mount and eject both interceptors during cleanup.
export function subscribeToAuthFailures(client, onFailure, getVersion) {
    const requestId = client.interceptors.request.use((config) => {
        config.stayhubAuthVersion = getVersion();
        return config;
    });
    const responseId = client.interceptors.response.use(undefined, (error) => {
        if (isAccountFailure(error) || (error?.response?.status === 401 && isProtectedRequest(error.config?.url))) {
            onFailure(normalizeAuthError(error), error.config?.stayhubAuthVersion);
        }
        return Promise.reject(error);
    });

    return () => {
        client.interceptors.request.eject(requestId);
        client.interceptors.response.eject(responseId);
    };
}
