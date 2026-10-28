import { normalizeAuthError } from './authSession.js';

const EMAIL_PATTERN = /^[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/;

// Mirror signup rules for immediate feedback; the server remains authoritative.
export function validateRegistration(values) {
    const errors = {};
    const email = values.email.trim();
    const password = values.password;
    if (!values.name.trim()) errors.name = ['Vui lòng nhập họ và tên.'];
    if (!email) errors.email = ['Vui lòng nhập địa chỉ email.'];
    else if (email.length > 254 || email.split('@')[0].length > 64 || !EMAIL_PATTERN.test(email)) {
        errors.email = ['Email phải đúng định dạng, ví dụ: ten@example.com.'];
    }
    if (!password) errors.password = ['Vui lòng nhập mật khẩu.'];
    else if ([...password].length < 8) errors.password = ['Mật khẩu phải có ít nhất 8 ký tự.'];
    else if (new TextEncoder().encode(password).length > 72) errors.password = ['Mật khẩu không được vượt quá 72 byte UTF-8.'];
    else if (/[\s\p{C}]/u.test(password)) errors.password = ['Mật khẩu không được chứa khoảng trắng hoặc ký tự điều khiển.'];
    else if (!/\p{Lu}/u.test(password) || !/\p{Ll}/u.test(password) || !/[0-9]/.test(password)
        || !/[^\p{L}\p{N}\s\p{C}]/u.test(password)) {
        errors.password = ['Mật khẩu phải có chữ hoa, chữ thường, số và ký tự đặc biệt.'];
    }
    if (!values.password_confirmation) errors.password_confirmation = ['Vui lòng xác nhận mật khẩu.'];
    else if (password !== values.password_confirmation) errors.password_confirmation = ['Mật khẩu xác nhận không khớp.'];
    return errors;
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
        const result = normalizeAuthError(error);
        result.outcomeUnknown = submitted && (!result.status || result.status >= 500);
        if (result.status === 422) result.message = 'Vui lòng kiểm tra thông tin đăng ký.';
        if (result.status === 429) result.message = `Bạn đã gửi quá nhiều yêu cầu đăng ký. Vui lòng thử lại sau ${result.retryAfter} giây.`;
        if (result.status === 409) {
            result.code = error.response?.data?.code === 'ALREADY_AUTHENTICATED' ? 'ALREADY_AUTHENTICATED' : null;
            result.message = result.code
                ? 'Bạn đang đăng nhập. Vui lòng đăng xuất trước khi đăng ký tài khoản mới.'
                : 'Yêu cầu đăng ký đang có xung đột. Vui lòng kiểm tra lại.';
        }
        if (result.outcomeUnknown) {
            result.message = 'Chưa nhận được xác nhận đăng ký từ máy chủ. Tài khoản có thể đã được tạo. Bạn có thể thử đăng nhập trước khi gửi lại.';
        }
        throw result;
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
