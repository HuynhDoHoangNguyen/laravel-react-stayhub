import { Link } from 'react-router-dom';
import { Calendar, ChevronRight, FileText, Hash } from 'lucide-react';
import { formatVnd } from '@/services/invoiceService';
import CustomerInvoiceStatusBadge from './CustomerInvoiceStatusBadge';

function formatDateVi(dateStr) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(d);
    } catch {
        return dateStr;
    }
}

/**
 * Thẻ hiển thị tóm tắt một hóa đơn thanh toán trong danh sách của Khách hàng (StayNest Quiet Luxury).
 *
 * @param {Object} props
 * @param {Object} props.invoice Dữ liệu hóa đơn từ backend
 */
export default function CustomerInvoiceCard({ invoice }) {
    if (!invoice) return null;

    return (
        <article className="group flex flex-col justify-between rounded-3xl border border-border/70 bg-white p-5 sm:p-6 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md text-left">
            <div>
                {/* Header: Property, Room & Status */}
                <div className="flex items-start justify-between gap-3 border-b border-border/60 pb-4">
                    <div className="min-w-0 flex-1">
                        <h3 className="font-display text-lg sm:text-xl font-bold leading-snug text-primary truncate">
                            {invoice.property_name || 'Cơ sở lưu trú StayHub'}
                        </h3>
                        <p className="mt-0.5 text-xs text-muted-foreground truncate">
                            {invoice.room_name || 'Phòng nghỉ'}
                        </p>
                    </div>
                    <CustomerInvoiceStatusBadge status={invoice.payment_status} />
                </div>

                {/* Metadata: Invoice Code, Booking Ref, Date */}
                <div className="mt-4 space-y-2 text-xs">
                    <div className="flex items-center gap-2 text-ink">
                        <FileText className="h-3.5 w-3.5 text-primary/70 shrink-0" aria-hidden="true" />
                        <span className="text-muted-foreground">Mã hóa đơn:</span>
                        <span className="font-mono font-semibold text-primary tracking-wide">
                            #{invoice.invoice_code}
                        </span>
                    </div>

                    <div className="flex items-center gap-2 text-ink">
                        <Hash className="h-3.5 w-3.5 text-accent shrink-0" aria-hidden="true" />
                        <span className="text-muted-foreground">Mã đặt phòng:</span>
                        <span className="font-mono font-medium text-ink">
                            #{invoice.booking_id}
                        </span>
                    </div>

                    <div className="flex items-center gap-2 text-ink">
                        <Calendar className="h-3.5 w-3.5 text-muted-foreground shrink-0" aria-hidden="true" />
                        <span className="text-muted-foreground">Ngày lập:</span>
                        <span className="text-ink">
                            {formatDateVi(invoice.created_at)}
                        </span>
                    </div>
                </div>
            </div>

            {/* Footer: Amount & Action */}
            <div className="mt-5 flex items-end justify-between gap-3 border-t border-border/60 pt-4">
                <div>
                    <span className="block text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                        Tổng thanh toán
                    </span>
                    <span className="font-display text-xl sm:text-2xl font-bold leading-tight text-primary tabular-nums">
                        {formatVnd(invoice.total_amount)}
                    </span>
                </div>

                <Link
                    to={`/customer/invoices/${invoice.id}`}
                    className="inline-flex items-center gap-1.5 rounded-xl border border-border/80 bg-surface/50 px-3.5 py-2 text-xs font-semibold text-ink shadow-2xs transition-all hover:border-primary/40 hover:bg-primary hover:text-primary-foreground group-hover:border-primary/30"
                    title={`Xem chi tiết hóa đơn #${invoice.invoice_code}`}
                >
                    <span>Xem chi tiết</span>
                    <ChevronRight className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                </Link>
            </div>
        </article>
    );
}
