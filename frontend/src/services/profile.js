import { normalizeAuthError } from './authSession.js';
import { passwordErrors } from './credentialValidation.js';

const FIELDS = ['name', 'phone', 'address', 'gender', 'date_of_birth'];
const NULLABLE = ['phone', 'address', 'gender', 'date_of_birth'];
export const GENDERS = { MALE: 'Nam', FEMALE: 'Nữ', OTHER: 'Khác' };

export function todayInVietnam(date = new Date()) {
    const parts = new Intl.DateTimeFormat('en', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(date);
    const part = (type) => parts.find((value) => value.type === type).value;
    return `${part('year')}-${part('month')}-${part('day')}`;
}

export function birthDateError(value, today = todayInVietnam()) {
    if (!value) return null;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return 'Ngày sinh không đúng định dạng.';
    const [year, month, day] = value.split('-').map(Number);
    const parsed = new Date(Date.UTC(year, month - 1, day));
    if (parsed.getUTCFullYear() !== year || parsed.getUTCMonth() !== month - 1 || parsed.getUTCDate() !== day) return 'Ngày sinh không hợp lệ.';
    if (value < '1900-01-01' || value > today) return 'Ngày sinh phải từ 01/01/1900 đến hôm nay.';
    return null;
}

export function avatarFileError(file) {
    if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return 'Vui lòng chọn ảnh JPG, PNG hoặc WebP.';
    if (file.size <= 0 || file.size > 2 * 1024 * 1024) return 'Ảnh phải có dung lượng lớn hơn 0 và không quá 2 MB.';
    return null;
}

export function profileDraft(user) {
    return { ...Object.fromEntries(FIELDS.map((field) => [field, user?.[field] ?? ''])), email: user?.email ?? '' };
}

export function profilePatch(saved, draft) {
    const patch = {};
    for (const field of FIELDS) {
        const value = draft[field].trim() || (NULLABLE.includes(field) ? null : '');
        if (value !== (saved[field] ?? null)) patch[field] = value;
    }
    return patch;
}

export function validateProfile(draft) {
    // Email is read-only and never participates in a contact mutation.
    const errors = {};
    if (!draft.name.trim()) errors.name = ['Vui lòng nhập họ và tên.'];
    if ([...draft.name.trim()].length > 255) errors.name = ['Họ và tên không được vượt quá 255 ký tự.'];
    const phone = draft.phone.trim();
    if (phone && !/^[0-9]{10}$/.test(phone)) {
        errors.phone = ['Số điện thoại phải gồm đúng 10 chữ số (0–9), không có chữ, mã quốc gia hoặc dấu phân cách.'];
    }
    if ([...draft.address.trim()].length > 500) errors.address = ['Địa chỉ không được vượt quá 500 ký tự.'];
    if (draft.gender && !Object.hasOwn(GENDERS, draft.gender)) errors.gender = ['Giới tính không hợp lệ.'];
    const dateError = birthDateError(draft.date_of_birth);
    if (dateError) errors.date_of_birth = [dateError];
    return errors;
}

export function validatePasswordChange(values) {
    const errors = passwordErrors(values);
    if (!values.current_password) errors.current_password = ['Vui lòng nhập mật khẩu hiện tại.'];
    if (values.current_password === values.password) errors.password = ['Mật khẩu mới phải khác mật khẩu hiện tại.'];
    return errors;
}

export function normalizeProfileError(error, operation, submitted = false) {
    const result = normalizeAuthError(error);
    if (result.status === 422) result.message = 'Vui lòng kiểm tra thông tin hồ sơ.';
    if (result.status === 403 && !result.code) result.message = 'Bạn không có quyền thực hiện thao tác này.';
    if (result.status === 429) result.message = `Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau ${result.retryAfter} giây.`;
    result.outcomeUnknown = submitted && (!result.status || result.status >= 500);
    result.operation = operation;
    if (result.outcomeUnknown) result.message = operation === 'avatar'
        ? 'Chưa nhận được xác nhận thay ảnh. Ảnh có thể đã được lưu; hãy tải lại hồ sơ để đối chiếu.'
        : operation === 'password'
        ? 'Chưa nhận được xác nhận đổi mật khẩu. Mật khẩu có thể đã đổi; hãy thử đăng nhập lại trước khi gửi lại.'
        : 'Chưa nhận được xác nhận lưu hồ sơ. Thông tin có thể đã được lưu; hãy tải lại hồ sơ để đối chiếu trước khi gửi lại.';
    return result;
}

export function createProfileService(client) {
    async function request(operation, payload, { signal } = {}) {
        let submitted = false;
        try {
            const options = { timeout: 10000, signal };
            if (operation === 'read') return (await client.get('/api/profile', options)).data.user;
            await client.get('/sanctum/csrf-cookie', options);
            if (operation === 'avatar') {
                const data = new FormData();
                data.append('avatar', payload);
                submitted = true;
                return (await client.post('/api/profile/avatar', data, options)).data;
            }
            const allowed = operation === 'password' ? ['current_password', 'password', 'password_confirmation'] : FIELDS;
            const fields = Object.fromEntries(allowed.filter((field) => Object.hasOwn(payload, field)).map((field) => [field, payload[field]]));
            submitted = true;
            const url = operation === 'password' ? '/api/profile/password' : '/api/profile';
            return (await client.patch(url, fields, options)).data;
        } catch (error) {
            throw normalizeProfileError(error, operation, submitted);
        }
    }
    return {
        get: (options) => request('read', null, options),
        update: (payload, options) => request('update', payload, options),
        changePassword: (payload, options) => request('password', payload, options),
        uploadAvatar: (file, options) => request('avatar', file, options),
    };
}

// An editor owns its drafts; auth restores never replace unsaved input.
export function createProfileEditor(service, auth) {
    let version = 0;
    let state = { saved: null, draft: null, loading: true, pending: false, pendingOperation: null, editing: false, error: null, notice: '', needsReload: false };
    const listeners = new Set();
    const set = (patch) => { state = { ...state, ...patch }; listeners.forEach((listener) => listener()); };
    async function run(operation, payload) {
        if (state.pending) return null;
        const current = ++version;
        const snapshot = auth.capture();
        if (!auth.isCurrent(snapshot)) return null;
        set({ pending: true, pendingOperation: operation, loading: operation === 'read', error: null, notice: '' });
        try {
            const response = operation === 'read' ? await service.get()
                : operation === 'password' ? await service.changePassword(payload)
                    : operation === 'avatar' ? await service.uploadAvatar(payload) : await service.update(payload);
            if (current !== version || !auth.isCurrent(snapshot)) return null;
            if (operation === 'password') {
                set({ pending: false, loading: false, notice: response.message });
            } else {
                const user = operation === 'read' ? response : response.user;
                if (!auth.commit(user, snapshot)) return null;
                const keepDraft = operation === 'avatar' && state.editing;
                set({ saved: user, draft: keepDraft ? state.draft : profileDraft(user), pending: false, loading: false, editing: keepDraft, needsReload: false,
                    notice: operation === 'read' ? '' : response.message });
            }
            return response;
        } catch (error) {
            if (current !== version || !auth.isCurrent(snapshot)) return null;
            set({ pending: false, loading: false, error,
                needsReload: state.needsReload || (['update', 'avatar'].includes(operation) && !!error.outcomeUnknown) });
            throw error;
        } finally {
            if (current === version && state.pending) set({ pending: false, loading: false });
        }
    }
    return {
        subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
        getSnapshot: () => state,
        cancelRequests() { ++version; set({ pending: false, loading: false }); },
        load: () => run('read'),
        edit() { if (!state.pending && state.saved) set({ editing: true, error: null, notice: '' }); },
        change(field, value) { if (!state.pending && FIELDS.includes(field)) set({ draft: { ...state.draft, [field]: value } }); },
        cancel() { if (!state.pending && state.saved) set({ draft: profileDraft(state.saved), editing: false, error: null, notice: '' }); },
        save() {
            if (!state.saved || state.pending || state.needsReload) return Promise.resolve(null);
            const fields = validateProfile(state.draft);
            if (Object.keys(fields).length) {
                set({ error: { operation: 'update', message: 'Vui lòng kiểm tra thông tin hồ sơ.', fields }, notice: '' });
                return Promise.resolve(null);
            }
            const payload = profilePatch(state.saved, state.draft);
            if (!Object.keys(payload).length) { set({ editing: false, notice: 'Không có thay đổi cần lưu.' }); return Promise.resolve(null); }
            return run('update', payload);
        },
        changePassword: (payload) => state.needsReload ? Promise.resolve(null) : run('password', payload),
        uploadAvatar(file) {
            if (state.pending || state.needsReload) return Promise.resolve(null);
            const message = avatarFileError(file);
            if (message) {
                set({ error: { operation: 'avatar', message, fields: { avatar: [message] } }, notice: '' });
                return Promise.resolve(null);
            }
            return run('avatar', file);
        },
    };
}
