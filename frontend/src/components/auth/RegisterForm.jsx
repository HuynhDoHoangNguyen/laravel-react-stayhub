import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { AlertCircle, CheckCircle2, ChevronDown, ChevronUp, Eye, EyeOff, FileText, Loader2, ShieldCheck } from 'lucide-react';
import { authService } from '../../services/authService';
import {
    createRegistrationSubmission,
    validateHostRegistration,
    validateRegistration,
} from '../../services/registration';

const FIELDS = [
    { name: 'name', label: 'Họ và tên', type: 'text', autoComplete: 'name', maxLength: 255 },
    { name: 'email', label: 'Địa chỉ email', type: 'email', autoComplete: 'email', maxLength: 254 },
    { name: 'password', label: 'Mật khẩu', type: 'password', autoComplete: 'new-password', maxLength: 72 },
    { name: 'password_confirmation', label: 'Xác nhận mật khẩu', type: 'password', autoComplete: 'new-password', maxLength: 72 },
];

export default function RegisterForm({ role = 'CUSTOMER', onRegistered, onBackToLogin, disabled = false }) {
    const isHost = role === 'HOST';
    const [values, setValues] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [accepted, setAccepted] = useState(false);
    const [agreement, setAgreement] = useState(null);
    const [agreementLoading, setAgreementLoading] = useState(false);
    const [agreementError, setAgreementError] = useState('');
    const [showFullTerms, setShowFullTerms] = useState(false);
    const [fieldErrors, setFieldErrors] = useState({});
    const [error, setError] = useState(null);
    const [pending, setPending] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [retryAfter, setRetryAfter] = useState(0);
    const [customerSubmission] = useState(() => createRegistrationSubmission(authService.register));
    const [hostSubmission] = useState(() => createRegistrationSubmission(authService.registerHost));
    const submission = isHost ? hostSubmission : customerSubmission;
    const inputRefs = useRef({});
    const rateLimited = retryAfter > 0;

    // Invalidate synchronously when the new login view commits, before late promises settle.
    useLayoutEffect(() => () => {
        customerSubmission.cancel();
        hostSubmission.cancel();
    }, [customerSubmission, hostSubmission]);

    useEffect(() => {
        if (!rateLimited) return;
        const timer = setInterval(() => setRetryAfter((seconds) => Math.max(0, seconds - 1)), 1000);
        return () => clearInterval(timer);
    }, [rateLimited]);

    useEffect(() => {
        setFieldErrors({});
        setError(null);
    }, [isHost]);

    const loadPublicAgreement = (signal) => {
        setAgreementLoading(true);
        setAgreementError('');

        return authService.getPublicPartnerAgreement(signal ? { signal } : {})
            .then((res) => {
                if (signal?.aborted) return;
                if (res?.data) {
                    setAgreement(res.data);
                } else {
                    setAgreementError('Không tải được nội dung hợp đồng đối tác.');
                }
            })
            .catch((err) => {
                if (signal?.aborted || err?.name === 'CanceledError' || err?.name === 'AbortError') return;
                setAgreementError('Không thể tải Hợp đồng Đối tác. Vui lòng thử lại.');
            })
            .finally(() => {
                if (!signal?.aborted) setAgreementLoading(false);
            });
    };

    useEffect(() => {
        if (!isHost) return;
        const controller = new AbortController();
        loadPublicAgreement(controller.signal);

        return () => {
            controller.abort();
        };
    }, [isHost]);

    const focusError = (errors) => {
        const field = FIELDS.find(({ name }) => errors[name]);
        if (field) {
            inputRefs.current[field.name]?.focus();
            return;
        }
        if (errors.accepted || errors.content_sha256) {
            inputRefs.current.accepted?.focus();
        }
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        if (submission.isPending() || rateLimited || disabled) return;

        const payload = isHost
            ? {
                ...values,
                accepted,
                content_sha256: agreement?.content_sha256 || '',
            }
            : values;

        const errors = isHost ? validateHostRegistration(payload) : validateRegistration(values);
        setFieldErrors(errors);
        setError(null);
        if (Object.keys(errors).length) {
            focusError(errors);
            return;
        }

        setPending(true);
        try {
            const result = await submission.submit(payload);
            if (result === null) return;
            setValues((current) => ({ ...current, password: '', password_confirmation: '' }));
            setPending(false);
            onRegistered({
                email: values.email.trim(),
                message: result.data.message,
                registeredRole: isHost ? 'HOST' : 'CUSTOMER',
            });
        } catch (failure) {
            setPending(false);
            setValues((current) => ({ ...current, password: '', password_confirmation: '' }));
            setError(failure);
            setFieldErrors(failure.fields || {});
            if (failure.retryAfter) setRetryAfter(failure.retryAfter);
            requestAnimationFrame(() => focusError(failure.fields || {}));
        }
    };

    const ratePercent = agreement?.commission_rate_bps
        ? (Number(agreement.commission_rate_bps) / 100).toFixed(0)
        : '10';

    return (
        <form onSubmit={handleSubmit} noValidate className="mt-5 space-y-4" aria-busy={pending}>
            <p className="rounded-xl border border-primary/20 bg-primary/5 p-3 text-xs leading-relaxed text-ink">
                {isHost
                    ? 'Tài khoản Đối tác (Host) dành cho chủ khách sạn & homestay đăng tải cơ sở lưu trú, quản lý đặt phòng và hóa đơn.'
                    : 'Tài khoản đăng ký mới dành cho khách hàng đặt phòng và theo dõi kỳ nghỉ.'}
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
                    <label htmlFor={`register-${name}`} className="block text-xs font-semibold text-ink">
                        {isHost && name === 'name' ? 'Họ và tên người đại diện / Chủ cơ sở' : label}
                    </label>
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

            {isHost && (
                <div className="space-y-3 rounded-2xl border border-primary/25 bg-surface/60 p-3.5">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-4 w-4 shrink-0 text-primary" aria-hidden />
                            <h3 className="text-xs font-bold text-primary">
                                {agreement?.title || 'Hợp đồng Đối tác & Biểu phí Hoa hồng StayHub (v1.0)'}
                            </h3>
                        </div>
                        <span className="shrink-0 rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-bold text-primary">
                            Hoa hồng {ratePercent}%
                        </span>
                    </div>

                    <ul className="space-y-1.5 text-[11.5px] leading-relaxed text-ink/90">
                        <li className="flex items-start gap-1.5">
                            <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" aria-hidden />
                            <span><strong>Thanh toán trực tiếp:</strong> Khách hàng thanh toán 100% tiền phòng và dịch vụ trực tiếp cho Đối tác (Host).</span>
                        </li>
                        <li className="flex items-start gap-1.5">
                            <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" aria-hidden />
                            <span><strong>Mức phí hoa hồng nền tảng {ratePercent}%:</strong> Tính trên doanh thu thực tế (Tiền phòng + Dịch vụ - Giảm giá).</span>
                        </li>
                        <li className="flex items-start gap-1.5">
                            <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" aria-hidden />
                            <span><strong>Thời hạn đối soát 72 giờ:</strong> Nộp chuyển khoản hoa hồng trong vòng 72 giờ kể từ khi xác nhận hóa đơn khách đã thanh toán (PAID).</span>
                        </li>
                    </ul>

                    {agreementLoading && (
                        <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                            <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />
                            <span>Đang tải điều khoản hợp đồng…</span>
                        </p>
                    )}

                    {agreementError && (
                        <div className="flex items-center justify-between gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-2.5 py-1.5">
                            <p className="text-[11px] text-destructive">{agreementError}</p>
                            <button
                                type="button"
                                onClick={() => loadPublicAgreement()}
                                className="shrink-0 text-[11px] font-semibold text-primary underline"
                            >
                                Tải lại
                            </button>
                        </div>
                    )}

                    {agreement?.terms_body && (
                        <div>
                            <button
                                type="button"
                                onClick={() => setShowFullTerms((prev) => !prev)}
                                className="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline"
                            >
                                <FileText className="h-3 w-3" />
                                <span>{showFullTerms ? 'Thu gọn toàn văn hợp đồng' : 'Xem toàn văn điều khoản hợp đồng'}</span>
                                {showFullTerms ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                            </button>
                            {showFullTerms && (
                                <div className="mt-2 max-h-40 overflow-y-auto rounded-xl border border-border/80 bg-background p-3 text-[11px] leading-relaxed whitespace-pre-line text-ink">
                                    {agreement.terms_body}
                                </div>
                            )}
                        </div>
                    )}

                    <label htmlFor="register-host-accepted" className="flex cursor-pointer items-start gap-2.5 border-t border-border/60 pt-2.5">
                        <input
                            id="register-host-accepted"
                            ref={(input) => { inputRefs.current.accepted = input; }}
                            type="checkbox"
                            checked={accepted}
                            disabled={pending || disabled || agreementLoading}
                            onChange={(e) => setAccepted(e.target.checked)}
                            aria-invalid={!!(fieldErrors.accepted || fieldErrors.content_sha256)}
                            aria-describedby={(fieldErrors.accepted || fieldErrors.content_sha256) ? 'register-accepted-error' : undefined}
                            className="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary"
                        />
                        <span className="text-[11.5px] font-medium leading-snug text-ink">
                            Tôi đã đọc, hiểu rõ và đồng ý với <strong>Hợp đồng Đối tác StayHub</strong> (mức phí hoa hồng nền tảng <strong>{ratePercent}%</strong> và thời hạn đối soát <strong>72 giờ</strong>).
                        </span>
                    </label>
                    {(fieldErrors.accepted || fieldErrors.content_sha256) && (
                        <p id="register-accepted-error" className="text-xs text-destructive">
                            {(fieldErrors.accepted || fieldErrors.content_sha256)[0]}
                        </p>
                    )}
                </div>
            )}

            <button type="submit" disabled={pending || rateLimited || disabled || (isHost && agreementLoading)}
                className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary text-xs font-semibold text-primary-foreground shadow-xs disabled:cursor-not-allowed disabled:opacity-60">
                {pending
                    ? <><Loader2 className="h-4 w-4 animate-spin" /> Đang đăng ký...</>
                    : rateLimited
                        ? `Thử lại sau ${retryAfter} giây`
                        : isHost
                            ? 'Đăng ký Đối tác & Chấp thuận Hợp đồng'
                            : 'Đăng ký Khách hàng'}
            </button>
            <p className="text-center text-xs text-muted-foreground">
                Đã có tài khoản? <button type="button" onClick={() => onBackToLogin(values.email.trim())} className="font-semibold text-primary underline">Đăng nhập</button>
            </p>
        </form>
    );
}
