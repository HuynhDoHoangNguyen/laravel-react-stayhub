const EMAIL_PATTERN = /^[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/;

export function emailError(value) {
    const email = value.trim();
    if (!email) return 'Vui lòng nhập địa chỉ email.';
    if (email.length > 254 || email.split('@')[0].length > 64 || !EMAIL_PATTERN.test(email)) {
        return 'Email phải đúng định dạng, ví dụ: ten@example.com.';
    }
    return null;
}

export function passwordErrors({ password, password_confirmation }) {
    const errors = {};
    if (!password) errors.password = ['Vui lòng nhập mật khẩu.'];
    else if ([...password].length < 8) errors.password = ['Mật khẩu phải có ít nhất 8 ký tự.'];
    else if (new TextEncoder().encode(password).length > 72) errors.password = ['Mật khẩu không được vượt quá 72 byte UTF-8.'];
    else if (/[\s\p{C}]/u.test(password)) errors.password = ['Mật khẩu không được chứa khoảng trắng hoặc ký tự điều khiển.'];
    else if (!/\p{Lu}/u.test(password) || !/\p{Ll}/u.test(password) || !/[0-9]/.test(password)
        || !/[^\p{L}\p{N}\s\p{C}]/u.test(password)) {
        errors.password = ['Mật khẩu phải có chữ hoa, chữ thường, số và ký tự đặc biệt.'];
    }
    if (!password_confirmation) errors.password_confirmation = ['Vui lòng xác nhận mật khẩu.'];
    else if (password !== password_confirmation) errors.password_confirmation = ['Mật khẩu xác nhận không khớp.'];
    return errors;
}
