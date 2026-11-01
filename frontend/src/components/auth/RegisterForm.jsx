import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { AlertCircle, Eye, EyeOff, Loader2 } from 'lucide-react';
import { authService } from '../../services/authService';
import { createRegistrationSubmission, validateRegistration } from '../../services/registration';

const FIELDS = [
    { name: 'name', label: 'Họ và tên', type: 'text', autoComplete: 'name', maxLength: 255 },
    { name: 'email', label: 'Địa chỉ email', type: 'email', autoComplete: 'email', maxLength: 254 },
    { name: 'password', label: 'Mật khẩu', type: 'password', autoComplete: 'new-password', maxLength: 72 },
    { name: 'password_confirmation', label: 'Xác nhận mật khẩu', type: 'password', autoComplete: 'new-password', maxLength: 72 },
];

export default function RegisterForm({ onRegistered, onBackToLogin, disabled = false }) {
    const [values, setValues] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [fieldErrors, setFieldErrors] = useState({});
    const [error, setError] = useState(null);
    const [pending, setPending] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [retryAfter, setRetryAfter] = useState(0);
    const [submission] = useState(() => createRegistrationSubmission(authService.register));
    const inputRefs = useRef({});
    const rateLimited = retryAfter > 0;

    // Invalidate synchronously when the new login view commits, before late promises settle.
    useLayoutEffect(() => () => submission.cancel(), [submission]);
    useEffect(() => {
        if (!rateLimited) return;
        const timer = setInterval(() => setRetryAfter((seconds) => Math.max(0, seconds - 1)), 1000);
        return () => clearInterval(timer);
    }, [rateLimited]);

    const focusError = (errors) => {
        const field = FIELDS.find(({ name }) => errors[name]);
        if (field) inputRefs.current[field.name]?.focus();
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        if (submission.isPending() || rateLimited || disabled) return;
        const errors = validateRegistration(values);
        setFieldErrors(errors);
        setError(null);
        if (Object.keys(errors).length) { focusError(errors); return; }

        setPending(true);
        try {
            const result = await submission.submit(values);
            if (result === null) return;
            setValues((current) => ({ ...current, password: '', password_confirmation: '' }));
            setPending(false);
            onRegistered({ email: values.email.trim(), message: result.data.message });
        } catch (failure) {
            setPending(false);
            setValues((current) => ({ ...current, password: '', password_confirmation: '' }));
            setError(failure);
            setFieldErrors(failure.fields || {});
            if (failure.retryAfter) setRetryAfter(failure.retryAfter);
            requestAnimationFrame(() => focusError(failure.fields || {}));
        }
    };

    return (
        <form onSubmit={handleSubmit} noValidate className="mt-5 space-y-4" aria-busy={pending}>
            <p className="rounded-xl border border-primary/20 bg-primary/5 p-3 text-xs leading-relaxed text-ink">
                Tài khoản đăng ký mới dành cho khách hàng đặt phòng và theo dõi kỳ nghỉ.
            </p>
            {error && <div role="alert" className="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <p>{error.message}</p>
                    {(error.outcomeUnknown || error.code === 'ALREADY_AUTHENTICATED') && (
                        <button type="button" onClick={() => onBackToLogin(values.email.trim())} className="mt-2 underline">Về đăng nhập</button>
                    )}
                </div>
            </div>}
            {FIELDS.map(({ name, label, type, autoComplete, maxLength }) => (
                <div key={name}>
                    <label htmlFor={`register-${name}`} className="block text-xs font-semibold text-ink">{label}</label>
                    <div className="relative mt-1.5">
                        <input id={`register-${name}`} name={name}
                            ref={(input) => { inputRefs.current[name] = input; }}
                            type={type === 'password' && showPassword ? 'text' : type}
                            autoComplete={autoComplete} maxLength={maxLength} required disabled={pending || disabled}
                            minLength={type === 'password' ? 8 : undefined}
                            value={values[name]} onChange={(event) => setValues((current) => ({ ...current, [name]: event.target.value }))}
                            aria-invalid={!!fieldErrors[name]}
                            aria-describedby={fieldErrors[name] ? `register-${name}-error` : name === 'password' ? 'register-password-hint' : undefined}
                            className="h-11 w-full rounded-xl border border-border/80 bg-background px-3.5 pr-12 text-xs text-ink focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60" />
                        {name === 'password' && <button type="button" disabled={pending || disabled}
                            onClick={() => setShowPassword((current) => !current)}
                            aria-label={showPassword ? 'Ẩn mật khẩu đăng ký' : 'Hiện mật khẩu đăng ký'}
                            className="absolute right-3 top-3 text-muted-foreground">
                            {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                        </button>}
                    </div>
                    {name === 'password' && <p id="register-password-hint" className="mt-1 text-[11px] text-muted-foreground">Ít nhất 8 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt. Không có khoảng trắng; tối đa 72 byte UTF-8.</p>}
                    {fieldErrors[name] && <p id={`register-${name}-error`} className="mt-1 text-xs text-destructive">{fieldErrors[name][0]}</p>}
                </div>
            ))}
            <button type="submit" disabled={pending || rateLimited || disabled}
                className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary text-xs font-semibold text-primary-foreground shadow-xs disabled:cursor-not-allowed disabled:opacity-60">
                {pending ? <><Loader2 className="h-4 w-4 animate-spin" /> Đang đăng ký...</> : rateLimited ? `Thử lại sau ${retryAfter} giây` : 'Đăng ký Khách hàng'}
            </button>
            <p className="text-center text-xs text-muted-foreground">
                Đã có tài khoản? <button type="button" onClick={() => onBackToLogin(values.email.trim())} className="font-semibold text-primary underline">Đăng nhập</button>
            </p>
        </form>
    );
}
