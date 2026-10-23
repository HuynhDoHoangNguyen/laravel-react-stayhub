import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import {
    ArrowLeft,
    Printer,
    FileText,
    Clock,
    Hash,
    Building2,
    BedDouble,
    User,
    AlertCircle,
    ExternalLink,
} from 'lucide-react';
import logoMark from '@/assets/logo-mark.png';
import { getCustomerInvoiceDetail, formatVnd, parseInvoiceError } from '@/services/invoiceService';
import CustomerInvoiceStatusBadge from '@/components/invoice/CustomerInvoiceStatusBadge';

function formatDateVi(dateStr, includeTime = false) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        const options = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            ...(includeTime && { hour: '2-digit', minute: '2-digit' }),
        };
        return new Intl.DateTimeFormat('vi-VN', options).format(d);
    } catch {
        return dateStr;
    }
}

/**
 * Trang xem chi tiết hóa đơn dành cho Khách hàng (StayNest Quiet Luxury Pattern).
 * Tuyến đường: /customer/invoices/:invoiceId
 */
export default function CustomerInvoiceDetailPage() {
    const { invoiceId } = useParams();
    const [invoice, setInvoice] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let isMounted = true;

        async function fetchDetail() {
            setLoading(true);
            setError(null);
            try {
                const res = await getCustomerInvoiceDetail(invoiceId);
                if (isMounted) {
                    setInvoice(res.data);
                }
            } catch (err) {
                if (isMounted) {
                    const parsed = parseInvoiceError(err);
                    setError(parsed.message);
                }
            } finally {
                if (isMounted) {
                    setLoading(false);
                }
            }
        }

        if (invoiceId) {
            fetchDetail();
        }

        return () => {
            isMounted = false;
        };
    }, [invoiceId]);

    // Trình kích hoạt in hóa đơn chuẩn trình duyệt
    const handlePrint = () => {
        if (!invoice) return;
        window.print();
    };

    // 1. Loading Skeleton State (2-column layout)
    if (loading) {
        return (
            <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
                {/* Back Link Skeleton */}
                <div className="h-4 w-44 rounded bg-surface animate-pulse" />

                <div className="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                    {/* Left Document Skeleton */}
                    <div className="rounded-3xl border border-border/60 bg-white p-8 shadow-xs animate-pulse space-y-6">
                        <div className="flex justify-between border-b border-border/40 pb-6">
                            <div className="space-y-2">
                                <div className="h-6 w-36 rounded-lg bg-surface" />
                                <div className="h-4 w-48 rounded bg-surface" />
                            </div>
                            <div className="h-6 w-24 rounded-full bg-surface" />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="h-16 rounded-xl bg-surface" />
                            <div className="h-16 rounded-xl bg-surface" />
                            <div className="h-16 rounded-xl bg-surface" />
                            <div className="h-16 rounded-xl bg-surface" />
                        </div>
                        <div className="h-44 rounded-2xl bg-surface" />
                        <div className="h-28 w-64 ml-auto rounded-2xl bg-surface" />
                    </div>

                    {/* Right Aside Skeleton */}
                    <div className="space-y-6">
                        <div className="h-36 rounded-3xl border border-border/60 bg-white p-6 shadow-xs animate-pulse" />
                        <div className="h-44 rounded-3xl border border-border/60 bg-white p-6 shadow-xs animate-pulse" />
                    </div>
                </div>
            </div>
        );
    }

    // 2. Error State (Not Found, Forbidden, Network Error)
    if (error || !invoice) {
        return (
            <div className="mx-auto max-w-lg py-16 text-center">
                <div className="rounded-3xl border border-border/70 bg-white p-8 shadow-xs sm:p-12">
                    <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber-50 text-amber-700">
                        <AlertCircle className="h-7 w-7" aria-hidden="true" />
                    </div>
                    <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
                        ✦ Hóa đơn thanh toán
                    </p>
                    <h1 className="mt-2 font-display text-2xl font-bold text-primary sm:text-3xl">
                        Không tìm thấy hóa đơn
                    </h1>
                    <p className="mx-auto mt-3 max-w-sm text-xs leading-relaxed text-muted-foreground">
                        {error || 'Hóa đơn không tồn tại hoặc bạn không có quyền truy cập.'}
                    </p>
                    <div className="mt-8 flex justify-center">
                        <Link
                            to="/customer/invoices"
                            className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-2xs hover:bg-primary-mid transition-all"
                        >
                            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                            <span>Quay lại danh sách hóa đơn</span>
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    const items = invoice.items || [];
    const isPaid = invoice.payment_status === 'PAID';
    const isRefunded = invoice.payment_status === 'REFUNDED';

    return (
        <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
            {/* Scoped Print Stylesheet: Isolation so print never prints system Navbar/Footer */}
            <style>{`
                @media print {
                    header, footer, nav, .print-hidden {
                        display: none !important;
                    }
                    body, main {
                        background: #ffffff !important;
                        padding: 0 !important;
                        margin: 0 !important;
                    }
                    .print-sheet {
                        border: none !important;
                        box-shadow: none !important;
                        padding: 0 !important;
                        max-width: 100% !important;
                        width: 100% !important;
                    }
                    .print-table tr {
                        break-inside: avoid;
                    }
                }
            `}</style>

            {/* Top Navigation & Breadcrumb (Ẩn khi in) */}
            <div className="print-hidden mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link
                    to="/customer/invoices"
                    className="inline-flex items-center gap-2 text-xs font-medium text-muted-foreground hover:text-primary transition-colors cursor-pointer"
                >
                    <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                    <span>Quay lại danh sách hóa đơn của tôi</span>
                </Link>

                {invoice.booking_id && (
                    <Link
                        to={`/customer/bookings/${invoice.booking_id}`}
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline cursor-pointer"
                    >
                        <span>Xem đơn đặt phòng #{invoice.booking_id}</span>
                        <ExternalLink className="h-3.5 w-3.5 opacity-70" aria-hidden="true" />
                    </Link>
                )}
            </div>

            {/* Main 2-Column Layout */}
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                {/* ========================================================
                    LEFT COLUMN: The Official StayHub Invoice Document Sheet
                   ======================================================== */}
                <div className="print-sheet rounded-3xl border border-border/70 bg-white p-6 sm:p-8 md:p-10 shadow-xs">
                    {/* Invoice Brand Header */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/60 pb-6">
                        <div className="flex items-center gap-3">
                            <img src={logoMark} alt="StayHub" className="h-10 w-10 shrink-0" />
                            <div>
                                <div className="font-display text-2xl font-bold tracking-tight text-primary">
                                    StayHub
                                </div>
                                <div className="text-[10px] font-semibold uppercase tracking-[0.2em] text-accent">
                                    Chứng từ thanh toán lưu trú
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col sm:items-end gap-1.5">
                            <div className="flex items-center gap-2.5">
                                <span className="font-mono text-sm sm:text-base font-bold text-primary">
                                    #{invoice.invoice_code}
                                </span>
                                <CustomerInvoiceStatusBadge status={invoice.payment_status} />
                            </div>
                            <span className="text-xs text-muted-foreground">
                                Ngày lập: {formatDateVi(invoice.created_at, true)}
                            </span>
                        </div>
                    </div>

                    {/* Information Grid: Customer, Property, Room & Booking Ref */}
                    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 rounded-2xl border border-border/60 bg-surface/30 p-5 text-xs">
                        {/* Customer Info */}
                        <div className="space-y-1">
                            <div className="flex items-center gap-1.5 font-medium uppercase tracking-wider text-muted-foreground text-[10.5px]">
                                <User className="h-3 w-3 text-primary" aria-hidden="true" />
                                <span>Khách hàng</span>
                            </div>
                            <div className="font-semibold text-ink text-sm">
                                {invoice.customer_name || '—'}
                            </div>
                            {(invoice.customer_phone || invoice.customer_email) && (
                                <div className="text-muted-foreground truncate">
                                    {invoice.customer_phone}
                                    {invoice.customer_phone && invoice.customer_email ? ' • ' : ''}
                                    {invoice.customer_email}
                                </div>
                            )}
                        </div>

                        {/* Booking Ref */}
                        <div className="space-y-1">
                            <div className="flex items-center gap-1.5 font-medium uppercase tracking-wider text-muted-foreground text-[10.5px]">
                                <Hash className="h-3 w-3 text-accent" aria-hidden="true" />
                                <span>Đơn đặt phòng liên kết</span>
                            </div>
                            <div className="font-mono font-semibold text-ink text-sm">
                                #{invoice.booking_id}
                            </div>
                            <div className="text-muted-foreground">
                                Đơn lưu trú đã xác nhận
                            </div>
                        </div>

                        {/* Property Info */}
                        <div className="space-y-1">
                            <div className="flex items-center gap-1.5 font-medium uppercase tracking-wider text-muted-foreground text-[10.5px]">
                                <Building2 className="h-3 w-3 text-primary" aria-hidden="true" />
                                <span>Cơ sở lưu trú</span>
                            </div>
                            <div className="font-semibold text-ink text-sm">
                                {invoice.property_name || '—'}
                            </div>
                        </div>

                        {/* Room Info */}
                        <div className="space-y-1">
                            <div className="flex items-center gap-1.5 font-medium uppercase tracking-wider text-muted-foreground text-[10.5px]">
                                <BedDouble className="h-3 w-3 text-accent" aria-hidden="true" />
                                <span>Phòng lưu trú</span>
                            </div>
                            <div className="font-semibold text-ink text-sm">
                                {invoice.room_name || '—'}
                            </div>
                        </div>
                    </div>

                    {/* Invoice Items Table */}
                    <div className="mt-8">
                        <div className="overflow-x-auto rounded-2xl border border-border/70 bg-white">
                            <table className="print-table w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr className="border-b border-border/70 bg-surface/50 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        <th scope="col" className="px-4 py-3">Loại</th>
                                        <th scope="col" className="px-4 py-3">Hạng mục</th>
                                        <th scope="col" className="px-4 py-3 text-right">Số lượng</th>
                                        <th scope="col" className="px-4 py-3 text-right">Đơn giá</th>
                                        <th scope="col" className="px-4 py-3 text-right">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/50 text-ink">
                                    {items.length === 0 ? (
                                        <tr>
                                            <td colSpan="5" className="p-8 text-center text-muted-foreground">
                                                Không có dòng chi tiết chi phí nào trong hóa đơn này.
                                            </td>
                                        </tr>
                                    ) : (
                                        items.map((item) => {
                                            const isRoom = item.item_type === 'ROOM';
                                            return (
                                                <tr key={item.id || item.item_name} className="hover:bg-surface/20 transition-colors">
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <span
                                                            className={`inline-block rounded-md px-2 py-0.5 text-[10.5px] font-semibold tracking-wide ${
                                                                isRoom
                                                                    ? 'bg-primary/10 text-primary'
                                                                    : 'bg-accent/15 text-accent-foreground'
                                                            }`}
                                                        >
                                                            {isRoom ? 'Phòng' : 'Dịch vụ'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <div className="font-semibold text-ink">
                                                            {item.item_name}
                                                        </div>
                                                        {item.description && (
                                                            <div className="mt-0.5 text-[11px] text-muted-foreground">
                                                                {item.description}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right font-medium tabular-nums">
                                                        {item.quantity ? parseFloat(item.quantity) : 0}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right font-medium text-muted-foreground tabular-nums">
                                                        {formatVnd(item.unit_price)}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right font-semibold text-primary tabular-nums">
                                                        {formatVnd(item.amount)}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Financial Summary Box (No frontend arithmetic, pure backend snapshots) */}
                    <div className="mt-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
                        <div className="text-xs text-muted-foreground max-w-xs space-y-1">
                            <p className="font-medium text-ink">✦ Ghi chú chứng từ:</p>
                            <p>
                                Số tiền trên chứng từ đã bao gồm các dịch vụ lưu trú phát sinh trong kỳ nghỉ và được ghi nhận theo hợp đồng điện tử StayHub.
                            </p>
                        </div>

                        <div className="w-full sm:max-w-xs rounded-2xl border border-border/70 bg-surface/40 p-4 space-y-2 text-xs">
                            <div className="flex items-center justify-between text-muted-foreground">
                                <span>Tạm tính (Subtotal):</span>
                                <span className="font-semibold text-ink tabular-nums">
                                    {formatVnd(invoice.subtotal)}
                                </span>
                            </div>

                            {invoice.discount_amount && parseFloat(invoice.discount_amount) > 0 && (
                                <div className="flex items-center justify-between text-rose-700">
                                    <span>Giảm giá khuyến mãi:</span>
                                    <span className="font-semibold tabular-nums">
                                        - {formatVnd(invoice.discount_amount)}
                                    </span>
                                </div>
                            )}

                            {invoice.extra_fee && parseFloat(invoice.extra_fee) > 0 && (
                                <div className="flex items-center justify-between text-ink">
                                    <span>Phụ phí lưu trú:</span>
                                    <span className="font-semibold tabular-nums">
                                        + {formatVnd(invoice.extra_fee)}
                                    </span>
                                </div>
                            )}

                            <div className="border-t border-border/70 pt-2.5 mt-2 flex items-baseline justify-between">
                                <span className="text-xs font-bold uppercase tracking-wider text-primary">
                                    Tổng thanh toán:
                                </span>
                                <span className="font-display text-2xl font-bold text-primary tabular-nums">
                                    {formatVnd(invoice.total_amount)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Footer Signature Note */}
                    <div className="mt-10 border-t border-border/60 pt-6 text-center text-[11px] text-muted-foreground">
                        Chứng từ điện tử này được khởi tạo tự động bởi Hệ thống StayHub · Trải nghiệm kỳ nghỉ thanh lịch Quiet Luxury.
                    </div>
                </div>

                {/* ========================================================
                    RIGHT COLUMN: Actions & Reference Sidebar (print:hidden)
                   ======================================================== */}
                <aside className="print-hidden space-y-6">
                    {/* Action 1: Print Invoice */}
                    <div className="rounded-3xl border border-border/70 bg-white p-6 shadow-xs">
                        <div className="flex items-center gap-2.5">
                            <FileText className="h-4 w-4 text-primary" aria-hidden="true" />
                            <h2 className="font-display text-lg font-bold text-primary">
                                Thao tác chứng từ
                            </h2>
                        </div>
                        <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                            Quý khách có thể in chứng từ ra giấy hoặc chọn mục <strong className="text-ink font-semibold">Lưu dưới dạng PDF</strong> trực tiếp từ trình duyệt.
                        </p>

                        <button
                            type="button"
                            onClick={handlePrint}
                            className="mt-5 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-2xs hover:bg-primary-mid transition-all"
                        >
                            <Printer className="h-4 w-4" aria-hidden="true" />
                            <span>In hóa đơn</span>
                        </button>
                    </div>

                    {/* Action 2: Payment Status Overview */}
                    <div className="rounded-3xl border border-border/70 bg-white p-6 shadow-xs">
                        <div className="flex items-center gap-2.5">
                            <Clock className="h-4 w-4 text-accent" aria-hidden="true" />
                            <h2 className="font-display text-lg font-bold text-primary">
                                Trạng thái quyết toán
                            </h2>
                        </div>

                        <div className="mt-4 rounded-2xl bg-surface/50 p-4 border border-border/60 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-muted-foreground">Tình trạng:</span>
                                <CustomerInvoiceStatusBadge status={invoice.payment_status} />
                            </div>

                            {isPaid && invoice.paid_at && (
                                <div className="text-[11.5px] text-muted-foreground pt-1 border-t border-border/40">
                                    Đã quyết toán vào lúc:{' '}
                                    <strong className="text-ink font-medium">
                                        {formatDateVi(invoice.paid_at, true)}
                                    </strong>
                                </div>
                            )}

                            {!isPaid && !isRefunded && (
                                <p className="text-[11.5px] text-amber-900 leading-relaxed pt-1">
                                    Hóa đơn này đang trong trạng thái chờ quyết toán tại quầy lễ tân hoặc theo phương thức thỏa thuận với cơ sở lưu trú.
                                </p>
                            )}

                            {isRefunded && (
                                <p className="text-[11.5px] text-purple-900 leading-relaxed pt-1">
                                    Hóa đơn này đã được hoàn tiền theo chính sách quy định.
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Action 3: Booking Reference Card */}
                    {invoice.booking_id && (
                        <div className="rounded-3xl border border-border/70 bg-white p-6 shadow-xs">
                            <div className="flex items-center gap-2.5">
                                <Building2 className="h-4 w-4 text-primary" aria-hidden="true" />
                                <h2 className="font-display text-lg font-bold text-primary">
                                    Đơn đặt phòng gốc
                                </h2>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Hóa đơn được phát sinh từ đơn đặt phòng <span className="font-mono font-semibold text-ink">#{invoice.booking_id}</span> tại cơ sở {invoice.property_name}.
                            </p>

                            <Link
                                to={`/customer/bookings/${invoice.booking_id}`}
                                className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-border/80 bg-surface/50 px-4 py-2 text-xs font-semibold text-ink shadow-2xs hover:border-primary/40 hover:bg-surface transition-all cursor-pointer"
                            >
                                <span>Xem chi tiết kỳ nghỉ</span>
                                <ExternalLink className="h-3.5 w-3.5 opacity-70" aria-hidden="true" />
                            </Link>
                        </div>
                    )}
                </aside>
            </div>
        </div>
    );
}
