import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
    Mail,
    Lock,
    Eye,
    EyeOff,
    User,
    Building2,
    ShieldCheck,
    AlertCircle,
    Loader2,
    ArrowLeft,
    Sparkles,
} from 'lucide-react';
import { toast } from 'sonner';
import logoMark from '@/assets/logo-mark.png';
import heroAsset from '@/assets/hero.png';
import { useAuth } from '@/hooks/useAuth';
import { getAuthDestination } from '@/utils/authNavigation';
import RegisterForm from '@/components/auth/RegisterForm';

const LOGIN_ROLES = [
    { value: 'CUSTOMER', param: 'customer', label: 'Khách hàng', icon: User },
    { value: 'HOST', param: 'host', label: 'Đối tác', icon: Building2 },
    { value: 'ADMIN', param: 'admin', label: 'Quản trị viên', icon: ShieldCheck },
];

/**
 * Trang Đăng nhập chuẩn thương mại Quiet Luxury (StayNest Auth Pattern).
 * Chọn khu vực đăng nhập Khách hàng, Đối tác hoặc Quản trị viên.
 */
export default function LoginPage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const navigate = useNavigate();
    const { login, isAuthenticated, user, loading: authChecking, authError: sessionError } = useAuth();

    // The backend verifies this requested context against the authenticated user's actual role.
    const roleParam = searchParams.get('role');
    const returnToParam = searchParams.get('returnTo');
    const modeParam = searchParams.get('mode');
    const selectedRole = roleParam === 'admin' ? 'ADMIN' : roleParam === 'host' ? 'HOST' : 'CUSTOMER';
    const isAdminContext = selectedRole === 'ADMIN';
    const isSignupMode = modeParam === 'signup';
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [authError, setAuthError] = useState(null);
    const [registrationNotice, setRegistrationNotice] = useState(null);
    const [fieldErrors, setFieldErrors] = useState({});
    const [retryAfter, setRetryAfter] = useState(0);
    const submitLock = useRef(false);
    const emailInput = useRef(null);
    const passwordInput = useRef(null);
    const isRateLimited = retryAfter > 0;

    useEffect(() => {
        if (!isRateLimited) return;
        const timer = setInterval(() => setRetryAfter((seconds) => Math.max(0, seconds - 1)), 1000);
        return () => clearInterval(timer);
    }, [isRateLimited]);

    const isHostContext = selectedRole === 'HOST';
    const contextLabel = isAdminContext ? 'Quản trị viên' : isHostContext ? 'Đối tác' : 'Khách hàng';

    const handleModeChange = (mode) => {
        setPassword('');
        setShowPassword(false);
        setAuthError(null);
        setFieldErrors({});
        if (mode === 'signup') setRegistrationNotice(null);
        setSearchParams((prev) => {
            const next = new URLSearchParams(prev);
            if (mode === 'signup') {
                next.set('mode', 'signup');
                next.set('role', 'customer');
            } else {
                next.delete('mode');
            }
            return next;
        }, { replace: true });
    };

    const handleRegistered = ({ email: registeredEmail, message }) => {
        setEmail(registeredEmail);
        setPassword('');
        setAuthError(null);
        setFieldErrors({});
        setRegistrationNotice(message);
        setSearchParams((prev) => {
            const next = new URLSearchParams(prev);
            next.delete('mode');
            next.set('role', 'customer');
            return next;
        }, { replace: true });
    };

    // Nếu đã đăng nhập, tự động chuyển hướng theo vai trò thật
    useEffect(() => {
        if (!authChecking && !sessionError && isAuthenticated && user) {
            navigate(getAuthDestination(user.role, returnToParam), { replace: true });
        }
    }, [authChecking, sessionError, isAuthenticated, user, navigate, returnToParam]);

    const handleRoleChange = (role) => {
        const context = LOGIN_ROLES.find((option) => option.value === role);
        if (!context) return;
        setAuthError(null);
        setFieldErrors({});
        setSearchParams((prev) => {
            const next = new URLSearchParams(prev);
            next.set('role', context.param);
            if (role === 'ADMIN') next.delete('mode');
            return next;
        }, { replace: true });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (submitLock.current || isRateLimited || isSignupMode || authChecking) return;
        setAuthError(null);
        const errors = {};
        if (!email.trim()) errors.email = ['Vui lòng nhập địa chỉ email.'];
        if (!password) errors.password = ['Vui lòng nhập mật khẩu.'];
        setFieldErrors(errors);
        if (Object.keys(errors).length) {
            (errors.email ? emailInput : passwordInput).current?.focus();
            return;
        }

        submitLock.current = true;
        setSubmitting(true);
        try {
            const loggedInUser = await login({ email: email.trim(), password, expectedRole: selectedRole });
            if (!loggedInUser) return;

            // Xử lý thông báo và điều hướng theo vai trò thực tế từ Backend
            if (loggedInUser.role === 'HOST') {
                toast.success(`Chào mừng Đối tác ${loggedInUser.name}`);
            } else if (loggedInUser.role === 'ADMIN') {
                toast.success(`Chào mừng Quản trị viên ${loggedInUser.name}`);
            } else {
                toast.success(`Chào mừng ${loggedInUser.name} trở lại StayHub`);
            }
            navigate(getAuthDestination(loggedInUser.role, returnToParam), { replace: true });
        } catch (err) {
            setAuthError(err.message);
            setFieldErrors(err.fields || {});
            if (err.retryAfter) setRetryAfter(err.retryAfter);
            if (err.fields?.email) emailInput.current?.focus();
            else if (err.fields?.password) passwordInput.current?.focus();
        } finally {
            setPassword('');
            submitLock.current = false;
            setSubmitting(false);
        }
    };

    return (
        <main className="min-h-screen bg-background px-3 py-6 md:flex md:items-center md:justify-center md:px-6">
            <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
                className="mx-auto grid w-full max-w-[1140px] overflow-hidden rounded-[28px] border border-border/70 bg-background shadow-[0_30px_90px_-35px_rgba(17,26,19,0.3)] md:h-[min(720px,calc(100vh-3rem))] md:grid-cols-[52%_48%]"
            >
                {/* LEFT HERO PANEL (StayNest Auth Hero Pattern) */}
                <section className="relative isolate flex min-h-[300px] flex-col justify-between overflow-hidden bg-surface p-6 md:min-h-full md:p-10">
                    <img
                        src={heroAsset}
                        alt="Không gian lưu trú StayHub"
                        className="absolute inset-0 h-full w-full object-cover animate-ken-burns"
                    />
                    <div
                        aria-hidden
                        className="absolute inset-0"
                        style={{
                            background:
                                'linear-gradient(180deg, rgba(17,26,19,0.45) 0%, rgba(17,26,19,0.2) 40%, rgba(17,26,19,0.7) 100%)',
                        }}
                    />

                    {/* Brand header on left */}
                    <div className="relative z-10 flex items-center justify-between">
                        <Link to="/" className="flex items-center gap-2.5 transition-opacity hover:opacity-85" aria-label="Trang chủ StayHub">
                            <img src={logoMark} alt="StayHub" className="h-8 w-8" />
                            <span className="font-display text-2xl font-bold tracking-tight text-white drop-shadow-sm">
                                StayHub
                            </span>
                        </Link>
                    </div>

                    {/* Headline and quote */}
                    <div className="relative z-10 my-auto py-8 text-left">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.26em] text-accent-pale drop-shadow-sm">
                            ✦ Kỳ nghỉ thanh lịch
                        </p>
                        <h2 className="mt-3 font-display text-[clamp(2rem,3.2vw,3rem)] font-light leading-[1.08] tracking-[-0.02em] text-white drop-shadow-sm">
                            Chào mừng trở lại.
                            <br />
                            Không gian an yên
                            <br />
                            đang đón chờ bạn.
                        </h2>
                        <p className="mt-4 max-w-sm text-sm leading-relaxed text-white/85">
                            Đăng nhập để theo dõi hành trình lưu trú, kiểm tra hóa đơn thanh toán và tận hưởng dịch vụ chuẩn mực.
                        </p>
                    </div>

                    {/* Promo banner */}
                    <div className="relative z-10 rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md">
                        <div className="flex items-center gap-2.5 text-xs font-medium text-white">
                            <Sparkles className="h-4 w-4 text-accent-pale" />
                            <span>Trải nghiệm du lịch tinh tế & hỗ trợ tận tâm 24/7</span>
                        </div>
                    </div>
                </section>

                {/* RIGHT FORM PANEL (StayNest LoginForm & RoleToggle Pattern) */}
                <section className="relative flex flex-col justify-between bg-background p-6 md:p-10 md:overflow-y-auto">
                    {/* Top Bar with back link & Mode Switch */}
                    <div className="flex items-center justify-between">
                        <Link
                            to="/"
                            className="inline-flex items-center gap-2 text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            <span>Về trang chủ</span>
                        </Link>

                        {(!isAdminContext || isSignupMode) && <div className="flex items-center gap-2 text-xs">
                            <span className="hidden text-muted-foreground sm:inline">
                                {isSignupMode ? 'Đã có tài khoản?' : 'Chưa có tài khoản?'}
                            </span>
                            <button
                                type="button"
                                disabled={submitting || authChecking}
                                onClick={() => handleModeChange(isSignupMode ? 'login' : 'signup')}
                                className="inline-flex items-center rounded-full border border-border/80 bg-surface/60 px-3.5 py-1.5 text-xs font-semibold text-ink transition-all hover:border-primary/40 hover:text-primary"
                            >
                                {isSignupMode ? 'Đăng nhập' : 'Đăng ký'}
                            </button>
                        </div>}
                    </div>

                    {/* Form Container */}
                    <div className="mx-auto my-auto w-full max-w-[380px] py-6 text-left">
                        <h1 className="font-display text-3xl font-bold tracking-tight text-primary sm:text-4xl">
                            {isSignupMode ? 'Đăng ký Khách hàng' : `Đăng nhập ${contextLabel}`}
                        </h1>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                            {isSignupMode
                                ? 'Trở thành thành viên để tận hưởng trọn vẹn đặc quyền lưu trú tại StayHub.'
                                : isAdminContext ? 'Dành cho quản trị viên hệ thống StayHub.' : (isHostContext
                                    ? 'Dành cho đối tác quản lý cơ sở lưu trú và hóa đơn phòng.'
                                    : 'Dành cho khách hàng đặt phòng và theo dõi kỳ nghỉ.')}
                        </p>

                        {registrationNotice && !isSignupMode && (
                            <div role="status" className="mt-4 rounded-xl border border-primary/25 bg-primary/5 p-3 text-xs leading-relaxed text-primary">
                                {registrationNotice}
                            </div>
                        )}
                        {isSignupMode && (
                            <RegisterForm onRegistered={handleRegistered} disabled={authChecking}
                                onBackToLogin={(registrationEmail) => {
                                    if (registrationEmail) setEmail(registrationEmail);
                                    handleModeChange('login');
                                }} />
                        )}

                        {!isSignupMode && <>

                        {/* Login context selector */}
                        <div className="mt-6 grid grid-cols-3 gap-1 rounded-2xl border border-border/70 bg-surface/60 p-1"
                            role="group" aria-label="Khu vực đăng nhập">
                            {LOGIN_ROLES.map(({ value, label, icon: Icon }) => (
                                <button key={value} type="button"
                                    disabled={submitting || authChecking}
                                    aria-pressed={selectedRole === value}
                                    onClick={() => handleRoleChange(value)}
                                    className={`flex min-w-0 items-center justify-center gap-1 rounded-xl px-1 py-2.5 text-[11px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 sm:gap-1.5 sm:px-2 sm:text-xs ${
                                        selectedRole === value
                                            ? 'bg-primary text-primary-foreground shadow-xs'
                                            : 'text-ink/70 hover:bg-primary/5 hover:text-ink'
                                    }`}>
                                    <Icon className="hidden h-3.5 w-3.5 shrink-0 sm:block" aria-hidden />
                                    <span className="leading-tight">{label}</span>
                                </button>
                            ))}
                        </div>

                        {/* Error Alert Box */}
                        {authError && (
                            <div role="alert" className="mt-4 flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs font-medium text-destructive">
                                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{authError}</span>
                            </div>
                        )}

                        {/* Form */}
                        <form onSubmit={handleSubmit} className="mt-5 space-y-4" noValidate>
                            <div>
                                <label htmlFor="login-email" className="block text-xs font-semibold text-ink">
                                    Địa chỉ Email
                                </label>
                                <div className="group relative mt-1.5 flex h-11 items-center rounded-xl border border-border/80 bg-background transition-all focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                    <Mail className="absolute left-3.5 h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                                    <input
                                        id="login-email"
                                        ref={emailInput}
                                        name="email"
                                        type="email"
                                        required
                                        disabled={submitting}
                                        aria-invalid={!!fieldErrors.email}
                                        aria-describedby={fieldErrors.email ? 'login-email-error' : undefined}
                                        autoComplete="email"
                                        placeholder={isAdminContext ? 'admin@stayhub.com' : isHostContext ? 'host@stayhub.com' : 'khachhang@stayhub.com'}
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        className="h-full w-full bg-transparent pl-10 pr-4 text-xs font-medium text-ink placeholder:text-muted-foreground/60 focus:outline-none"
                                    />
                                </div>
                                {fieldErrors.email && <p id="login-email-error" className="mt-1 text-xs text-destructive">{fieldErrors.email[0]}</p>}
                            </div>

                            <div>
                                <label htmlFor="login-password" className="block text-xs font-semibold text-ink">
                                    Mật khẩu
                                </label>
                                <div className="group relative mt-1.5 flex h-11 items-center rounded-xl border border-border/80 bg-background transition-all focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                    <Lock className="absolute left-3.5 h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                                    <input
                                        id="login-password"
                                        ref={passwordInput}
                                        name="password"
                                        type={showPassword ? 'text' : 'password'}
                                        required
                                        disabled={submitting}
                                        aria-invalid={!!fieldErrors.password}
                                        aria-describedby={fieldErrors.password ? 'login-password-error' : undefined}
                                        autoComplete="current-password"
                                        placeholder="Nhập mật khẩu"
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        className="h-full w-full bg-transparent pl-10 pr-10 text-xs font-medium text-ink placeholder:text-muted-foreground/60 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((s) => !s)}
                                        aria-label={showPassword ? 'Ẩn mật khẩu' : 'Hiển thị mật khẩu'}
                                        className="absolute right-2.5 grid h-7 w-7 place-items-center rounded-lg text-muted-foreground hover:text-primary"
                                    >
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                {fieldErrors.password && <p id="login-password-error" className="mt-1 text-xs text-destructive">{fieldErrors.password[0]}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={submitting || isRateLimited || authChecking || isSignupMode}
                                className="mt-2 inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary text-xs font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {submitting ? (
                                    <>
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        <span>Đang xác thực...</span>
                                    </>
                                ) : (
                                    <span>{isSignupMode ? 'Đăng ký chưa khả dụng' : isRateLimited ? `Thử lại sau ${retryAfter} giây` : `Đăng nhập ${contextLabel}`}</span>
                                )}
                            </button>

                            {/* Mode Toggle Link below submit */}
                            {!isAdminContext && <div className="mt-4 text-center text-xs text-muted-foreground">
                                {isSignupMode ? (
                                    <p>
                                        Đã có tài khoản?{' '}
                                        <button
                                            type="button"
                                            onClick={() => handleModeChange('login')}
                                            className="font-semibold text-primary hover:underline cursor-pointer"
                                        >
                                            Đăng nhập ngay
                                        </button>
                                    </p>
                                ) : (
                                    <p>
                                        Chưa có tài khoản?{' '}
                                        <button
                                            type="button"
                                            onClick={() => handleModeChange('signup')}
                                            className="font-semibold text-primary hover:underline cursor-pointer"
                                        >
                                            Đăng ký ngay
                                        </button>
                                    </p>
                                )}
                            </div>}
                        </form>
                        </>}
                    </div>

                    {/* Footer text */}
                    <div className="text-center text-[11px] text-muted-foreground">
                        Hệ thống xác thực bảo mật chuẩn Laravel Sanctum & StayHub
                    </div>
                </section>
            </motion.div>
        </main>
    );
}
