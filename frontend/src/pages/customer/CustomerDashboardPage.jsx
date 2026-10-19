import { useState, useEffect } from 'react';
import { Link, useOutletContext } from 'react-router-dom';
import {
    Compass,
    Sparkles,
    FileText,
    BadgeCheck,
    Clock,
    CreditCard,
    ChevronRight,
    MapPin,
    ArrowRight,
    CalendarCheck,
    UserRound,
    User,
} from 'lucide-react';
import { getCustomerInvoices, formatVnd, parseInvoiceError } from '../../services/invoiceService';

/**
 * Format date string to Vietnamese localized date.
 */
function formatDateVi(dateString) {
    if (!dateString) return '—';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return dateString;
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(d);
}

/**
 * Customer Quiet Luxury Dashboard Page (UI-03A).
 * Tái sử dụng trọn vẹn phong cách thiết kế, typography và layout từ StayNest.
 * Chỉ hiển thị KPI thật từ Laravel Sanctum và Invoice Query API.
 */
export default function CustomerDashboardPage() {
    const outletCtx = useOutletContext() || {};
    const user = outletCtx.user;
    const authLoading = outletCtx.loading;

    const [invoices, setInvoices] = useState([]);
    const [totalInvoicesCount, setTotalInvoicesCount] = useState(0);
    const [loadingInvoices, setLoadingInvoices] = useState(false);
    const [invoiceError, setInvoiceError] = useState(null);

    useEffect(() => {
        let isMounted = true;

        if (!user) {
            return;
        }

        const fetchInvoices = async () => {
            setLoadingInvoices(true);
            setInvoiceError(null);
            try {
                // Lấy tối đa 50 hóa đơn để tính toán chính xác các chỉ số tài chính thật của Customer
                const res = await getCustomerInvoices({ page: 1, perPage: 50 });
                if (isMounted) {
                    const list = res.data || [];
                    setInvoices(list);
                    // Dùng meta.total nếu có, không coi số item của trang hiện tại là tổng số
                    const total = res.meta?.total !== undefined ? res.meta.total : list.length;
                    setTotalInvoicesCount(total);
                }
            } catch (err) {
                if (isMounted) {
                    const parsed = parseInvoiceError(err);
                    setInvoiceError(parsed.message);
                }
            } finally {
                if (isMounted) {
                    setLoadingInvoices(false);
                }
            }
        };

        fetchInvoices();

        return () => {
            isMounted = false;
        };
    }, [user]);

    // Tính toán KPI tài chính 100% từ dữ liệu thật của API
    const paidInvoices = invoices.filter((inv) => inv.payment_status === 'PAID');
    const pendingInvoices = invoices.filter((inv) => inv.payment_status === 'UNPAID' || inv.payment_status === 'PENDING');
    const totalSettledAmount = paidInvoices.reduce((acc, inv) => {
        const val = parseFloat(inv.total_amount) || 0;
        return acc + val;
    }, 0);

    const statsCards = [
        {
            title: 'Tổng hóa đơn',
            count: totalInvoicesCount,
            hint: 'Toàn bộ kỳ nghỉ đã xuất hóa đơn',
            icon: FileText,
            tint: 'bg-[#e8efe4]',
            iconColor: 'text-[#3a5a40]',
        },
        {
            title: 'Đã thanh toán',
            count: paidInvoices.length,
            hint: 'Hóa đơn đã quyết toán đầy đủ',
            icon: BadgeCheck,
            tint: 'bg-[#e2ecf3]',
            iconColor: 'text-[#3b6a8a]',
        },
        {
            title: 'Chờ thanh toán',
            count: pendingInvoices.length,
            hint: 'Hóa đơn cần xử lý thanh toán',
            icon: Clock,
            tint: 'bg-[#f2ead9]',
            iconColor: 'text-[#8b6f3a]',
        },
        {
            title: 'Tổng chi tiêu',
            count: formatVnd(totalSettledAmount),
            hint: 'Quyết toán từ hóa đơn hợp lệ',
            icon: CreditCard,
            tint: 'bg-[#f6e4e4]',
            iconColor: 'text-[#b04a5a]',
        },
    ];

    const quickPicks = [
        {
            icon: FileText,
            title: 'Hóa đơn & Thanh toán',
            desc: 'Xem lại hóa đơn và chi phí kỳ nghỉ',
            tint: 'bg-[#e5edf3]',
            iconColor: 'text-[#3b6a8a]',
            to: '/customer/invoices',
        },
        {
            icon: CalendarCheck,
            title: 'Đơn đặt phòng',
            desc: 'Theo dõi hành trình và đặt phòng',
            tint: 'bg-[#eef1ea]',
            iconColor: 'text-[#3a5a40]',
            to: '/customer/bookings',
        },
        {
            icon: UserRound,
            title: 'Hồ sơ cá nhân',
            desc: 'Xem thông tin hội viên đã xác thực',
            tint: 'bg-[#efe8dc]',
            iconColor: 'text-[#8b6f3a]',
            to: '/customer/profile',
        },
        {
            icon: Compass,
            title: 'Khám phá chỗ nghỉ',
            desc: 'Tìm khách sạn & homestay mới',
            tint: 'bg-[#eef1ea]',
            iconColor: 'text-[#3a5a40]',
            to: '/search',
        },
    ];

    // Trạng thái chưa đăng nhập
    if (!authLoading && !user) {
        return (
            <div className="mx-auto max-w-2xl py-16 text-center">
                <div className="rounded-3xl border border-border/70 bg-white p-8 shadow-xs sm:p-12">
                    <div className="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-surface text-primary">
                        <User className="h-8 w-8 text-primary" />
                    </div>
                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
                        ✦ Hội viên StayHub
                    </p>
                    <h1 className="mt-2 font-display text-3xl font-bold text-primary sm:text-4xl">
                        Khu vực Khách hàng
                    </h1>
                    <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-muted-foreground">
                        Vui lòng đăng nhập với tài khoản khách hàng để truy cập tổng quan kỳ nghỉ, xem lịch sử hóa đơn và các tiện ích dành riêng cho bạn.
                    </p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            to="/login?role=customer"
                            className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 text-xs font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary-mid"
                        >
                            <span>Đăng nhập tài khoản</span>
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                        <Link
                            to="/"
                            className="inline-flex items-center rounded-xl border border-border/80 bg-surface px-5 py-3 text-xs font-medium text-ink transition-colors hover:border-primary/40 hover:text-primary"
                        >
                            Về trang chủ
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="pb-16 text-left">
            {/* Header Section (StayNest Member Dashboard Pattern) */}
            <section className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent">
                        ✦ Tài khoản hội viên
                    </p>
                    <h1 className="mt-1 font-display text-3xl font-bold leading-tight text-primary sm:text-4xl md:text-5xl">
                        {authLoading ? (
                            <span className="inline-block h-10 w-64 animate-pulse rounded-lg bg-surface" />
                        ) : (
                            `Xin chào, ${user?.name || 'Quý khách'}`
                        )}
                    </h1>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                        Chào mừng bạn trở lại với StayHub. Theo dõi các kỳ nghỉ thanh lịch và xem lại lịch sử hóa đơn thanh toán của bạn.
                    </p>
                </div>

                <Link
                    to="/search"
                    className="inline-flex items-center gap-2 self-start rounded-xl bg-primary px-5 py-3 text-xs font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary-mid hover:shadow-[0_10px_24px_-12px_rgba(17,26,19,0.4)]"
                >
                    <Compass className="h-4 w-4" aria-hidden />
                    <span>Khám phá chỗ nghỉ</span>
                </Link>
            </section>

            {/* Real Stats Row (StayNest BookingStats Pattern) */}
            <section className="mt-8">
                {loadingInvoices || authLoading ? (
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {Array.from({ length: 4 }).map((_, i) => (
                            <div key={i} className="h-24 animate-pulse rounded-2xl bg-white/70" />
                        ))}
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {statsCards.map((c) => {
                            const Icon = c.icon;
                            return (
                                <div
                                    key={c.title}
                                    className="group rounded-2xl border border-border/60 bg-white p-5 shadow-[0_1px_2px_rgba(17,26,19,0.04)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_12px_30px_-16px_rgba(17,26,19,0.2)]"
                                >
                                    <div className="flex items-start gap-3.5">
                                        <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl ${c.tint}`}>
                                            <Icon className={`h-5 w-5 ${c.iconColor}`} aria-hidden />
                                        </div>
                                        <div className="min-w-0">
                                            <div className="font-display text-2xl font-bold leading-none text-primary sm:text-3xl">
                                                {c.count}
                                            </div>
                                            <div className="mt-1.5 text-xs font-semibold text-ink">
                                                {c.title}
                                            </div>
                                            <div className="mt-0.5 truncate text-[11px] text-muted-foreground">
                                                {c.hint}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </section>

            {/* Main Content Grid: Left Content (1fr) + Right QuickPicks (340px) */}
            <section className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
                {/* Left Column: Recent Invoices & Exploration */}
                <div className="min-w-0 space-y-8">
                    {/* Recent Invoices Box */}
                    <div className="rounded-3xl border border-border/60 bg-white p-6 shadow-xs sm:p-7">
                        <div className="flex items-center justify-between border-b border-border/60 pb-4">
                            <div>
                                <h2 className="font-display text-xl font-bold text-primary sm:text-2xl">
                                    Hóa đơn gần đây
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Các chứng từ và dịch vụ lưu trú đã lập gần đây nhất
                                </p>
                            </div>
                            {totalInvoicesCount > 0 && (
                                <Link
                                    to="/customer/invoices"
                                    className="inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:text-primary-mid"
                                >
                                    <span>Xem tất cả ({totalInvoicesCount})</span>
                                    <ChevronRight className="h-3.5 w-3.5" />
                                </Link>
                            )}
                        </div>

                        {/* Invoices List / Empty State */}
                        <div className="mt-6 space-y-4">
                            {loadingInvoices && (
                                <div className="space-y-3">
                                    <div className="h-24 animate-pulse rounded-2xl bg-surface/60" />
                                    <div className="h-24 animate-pulse rounded-2xl bg-surface/60" />
                                </div>
                            )}

                            {invoiceError && (
                                <div className="rounded-2xl border border-destructive/20 bg-destructive/5 p-6 text-center text-xs text-destructive">
                                    <p>{invoiceError}</p>
                                </div>
                            )}

                            {!loadingInvoices && !invoiceError && invoices.length === 0 && (
                                <div className="rounded-2xl border border-dashed border-border/80 bg-background/50 p-10 text-center">
                                    <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface text-primary">
                                        <Sparkles className="h-6 w-6 text-accent" />
                                    </div>
                                    <h3 className="mt-4 font-display text-xl font-bold text-primary">
                                        Chưa có hóa đơn nào
                                    </h3>
                                    <p className="mx-auto mt-1.5 max-w-sm text-xs leading-relaxed text-muted-foreground">
                                        Sau khi bạn hoàn tất một kỳ nghỉ tại StayHub, thông tin hóa đơn và các dịch vụ phòng sẽ hiển thị đầy đủ tại đây.
                                    </p>
                                    <div className="mt-6 flex justify-center">
                                        <Link
                                            to="/search"
                                            className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground transition-all hover:bg-primary-mid"
                                        >
                                            <Compass className="h-3.5 w-3.5" />
                                            <span>Khám phá chỗ nghỉ ngay</span>
                                        </Link>
                                    </div>
                                </div>
                            )}

                            {!loadingInvoices && invoices.slice(0, 4).map((inv) => {
                                const isPaid = inv.payment_status === 'PAID';
                                return (
                                    <div
                                        key={inv.id}
                                        className="group flex flex-col justify-between gap-4 rounded-2xl border border-border/60 bg-surface/30 p-4 transition-all duration-200 hover:border-primary/30 hover:bg-white hover:shadow-xs sm:flex-row sm:items-center"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-xs font-semibold text-primary">
                                                    {inv.invoice_code}
                                                </span>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10.5px] font-semibold ${
                                                        isPaid
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-amber-50 text-amber-700'
                                                    }`}
                                                >
                                                    {isPaid ? 'Đã thanh toán' : 'Chờ thanh toán'}
                                                </span>
                                                <span className="text-[11px] text-muted-foreground">
                                                    • {formatDateVi(inv.created_at)}
                                                </span>
                                            </div>
                                            <div className="mt-1.5 font-display text-base font-bold text-ink transition-colors group-hover:text-primary">
                                                {inv.property_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Phòng: <span className="font-medium text-ink">{inv.room_name}</span>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between gap-4 sm:flex-col sm:items-end sm:justify-center">
                                            <div className="font-display text-base font-bold text-primary">
                                                {formatVnd(inv.total_amount)}
                                            </div>
                                            <Link
                                                to={`/customer/invoices/${inv.id}`}
                                                className="inline-flex items-center gap-1 rounded-lg border border-border/70 bg-white px-3 py-1.5 text-xs font-medium text-ink shadow-xs transition-colors hover:border-primary/40 hover:text-primary"
                                            >
                                                <span>Xem chi tiết</span>
                                                <ChevronRight className="h-3 w-3" />
                                            </Link>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Boutique Stays Inspiration Card (StayNest Luxury Exploration Pattern) */}
                    <div className="relative overflow-hidden rounded-3xl border border-border/60 bg-gradient-to-br from-surface via-background to-accent-pale/20 p-6 sm:p-8">
                        <div className="relative z-10 max-w-xl">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-accent">
                                ✦ Điểm đến được tuyển chọn
                            </p>
                            <h3 className="mt-2 font-display text-2xl font-bold leading-tight text-primary sm:text-3xl">
                                Cảm hứng cho kỳ nghỉ sắp tới
                            </h3>
                            <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                                Trải nghiệm những không gian nghỉ dưỡng Quiet Luxury tinh tế tại các địa danh nổi tiếng với dịch vụ phòng chu đáo và cảnh sắc nên thơ.
                            </p>

                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                {['Đà Lạt', 'Phú Quốc', 'Sa Pa', 'Hội An'].map((loc) => (
                                    <Link
                                        key={loc}
                                        to={`/search?keyword=${encodeURIComponent(loc)}`}
                                        className="inline-flex items-center gap-1.5 rounded-full border border-border/80 bg-white/90 px-3 py-1 text-xs font-medium text-ink transition-colors hover:border-primary/40 hover:text-primary"
                                    >
                                        <MapPin className="h-3 w-3 text-accent" />
                                        <span>{loc}</span>
                                    </Link>
                                ))}
                            </div>

                            <div className="mt-6">
                                <Link
                                    to="/search"
                                    className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary-mid"
                                >
                                    <span>Khám phá toàn bộ phòng trống</span>
                                    <ArrowRight className="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right Column: QuickPicks & Concierge Support */}
                <aside className="space-y-6">
                    {/* QuickPicks Card (Directly adapted from StayNest QuickPicks.jsx) */}
                    <div className="rounded-3xl border border-border/60 bg-white p-5 shadow-[0_1px_2px_rgba(17,26,19,0.04)]">
                        <div className="flex items-center gap-2 border-b border-border/60 pb-3 pt-1">
                            <span aria-hidden className="text-accent">✦</span>
                            <h3 className="font-display text-lg font-bold text-primary">Lối tắt quản lý</h3>
                        </div>
                        <div className="mt-3 space-y-1.5">
                            {quickPicks.map((it) => {
                                const Icon = it.icon;
                                return (
                                    <Link
                                        key={it.title}
                                        to={it.to}
                                        className="group flex w-full items-center gap-3 rounded-2xl p-2.5 text-left transition-colors hover:bg-surface"
                                    >
                                        <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${it.tint}`}>
                                            <Icon className={`h-4.5 w-4.5 ${it.iconColor}`} aria-hidden />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-xs font-semibold text-ink transition-colors group-hover:text-primary">
                                                {it.title}
                                            </span>
                                            <span className="block truncate text-[11px] text-muted-foreground">
                                                {it.desc}
                                            </span>
                                        </span>
                                        <ChevronRight
                                            className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                                            aria-hidden
                                        />
                                    </Link>
                                );
                            })}
                        </div>
                    </div>

                    {/* Support & Concierge Card (StayNest Ask StayNest pattern) */}
                    <div className="rounded-3xl border border-border/60 bg-gradient-to-br from-surface to-[#ede4d2] p-5 shadow-xs">
                        <span className="text-[10px] font-semibold uppercase tracking-[0.2em] text-accent">
                            ✦ Dịch vụ tận tâm
                        </span>
                        <h4 className="mt-1 font-display text-xl font-bold leading-tight text-primary">
                            Cần hỗ trợ về kỳ nghỉ?
                        </h4>
                        <p className="mt-2 text-xs leading-relaxed text-ink/75">
                            Đội ngũ lễ tân và trợ lý StayHub luôn đồng hành cùng bạn để chuẩn bị chu đáo nhất cho chuyến đi.
                        </p>
                        <div className="mt-4">
                            <Link
                                to="/customer/invoices"
                                className="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary-mid"
                            >
                                <span>Kiểm tra giao dịch</span>
                                <span aria-hidden>✦</span>
                            </Link>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    );
}