import { emailError, passwordErrors } from './credentialValidation.js';
import { normalizeAuthError } from './authSession.js';

// Mirror signup rules for immediate feedback; the server remains authoritative.
export function validateRegistration(values) {
    const errors = passwordErrors(values);
    if (!values.name.trim()) errors.name = ['Vui lòng nhập họ và tên.'];
    const email = emailError(values.email);
    if (email) errors.email = [email];
    return errors;
}

export function validateHostRegistration(values) {
    const errors = validateRegistration(values);
    if (!values.accepted) {
        errors.accepted = ['Bạn phải đồng ý với Hợp đồng Đối tác và mức phí hoa hồng 10% trước khi đăng ký.'];
    }
    if (!String(values.content_sha256 ?? '').trim()) {
        errors.content_sha256 = ['Chưa tải được thông tin hợp đồng đối tác. Vui lòng thử lại.'];
        if (!errors.accepted) {
            errors.accepted = ['Chưa tải được thông tin hợp đồng đối tác. Vui lòng thử lại.'];
        }
    }
    return errors;
}

function normalizeRegistrationError(error, submitted) {
    const result = normalizeAuthError(error);
    result.outcomeUnknown = submitted && (!result.status || result.status >= 500);
    if (result.status === 422) result.message = 'Vui lòng kiểm tra thông tin đăng ký.';
    if (result.status === 429) result.message = `Bạn đã gửi quá nhiều yêu cầu đăng ký. Vui lòng thử lại sau ${result.retryAfter} giây.`;
    if (result.status === 409) {
        result.code = error.response?.data?.code === 'ALREADY_AUTHENTICATED' ? 'ALREADY_AUTHENTICATED' : null;
        result.message = result.code
            ? 'Bạn đang đăng nhập. Vui lòng đăng xuất trước khi đăng ký tài khoản mới.'
            : (error.response?.data?.message || 'Yêu cầu đăng ký đang có xung đột. Vui lòng kiểm tra lại.');
    }
    if (result.outcomeUnknown) {
        result.message = 'Chưa nhận được xác nhận đăng ký từ máy chủ. Tài khoản có thể đã được tạo. Bạn có thể thử đăng nhập trước khi gửi lại.';
    }
    return result;
}

// Accept the shared client from authService; no extra Axios instance or auth state.
export async function registerCustomer(client, { name, email, password, password_confirmation }, { signal } = {}) {
    let submitted = false;
    try {
        const options = { timeout: 10000, signal };
        const payload = { name: name.trim(), email: email.trim(), password, password_confirmation };
        await client.get('/sanctum/csrf-cookie', options);
        submitted = true;
        const response = await client.post('/register', payload, options);
        return response.data;
    } catch (error) {
        throw normalizeRegistrationError(error, submitted);
    }
}

export async function registerHost(
    client,
    { name, email, password, password_confirmation, accepted, content_sha256 },
    { signal } = {}
) {
    let submitted = false;
    try {
        const options = { timeout: 10000, signal };
        const payload = {
            name: name.trim(),
            email: email.trim(),
            password,
            password_confirmation,
            accepted: Boolean(accepted),
            content_sha256: String(content_sha256 ?? '').trim(),
        };
        await client.get('/sanctum/csrf-cookie', options);
        submitted = true;
        const response = await client.post('/api/public/host-register', payload, options);
        return response.data;
    } catch (error) {
        throw normalizeRegistrationError(error, submitted);
    }
}

// One pending submission; leaving the form invalidates late successes and errors.
export function createRegistrationSubmission(register) {
    let version = 0;
    let pending = false;
    return {
        isPending: () => pending,
        cancel() { ++version; pending = false; },
        async submit(payload) {
            if (pending) return null;
            pending = true;
            const current = ++version;
            try {
                const data = await register(payload);
                return current === version ? { data } : null;
            } catch (error) {
                if (current === version) throw error;
                return null;
            } finally {
                if (current === version) pending = false;
            }
        },
    };
}
