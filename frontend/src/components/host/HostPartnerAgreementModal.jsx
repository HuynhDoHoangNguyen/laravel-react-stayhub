import { useState } from 'react';
import { ShieldCheck, FileText, AlertCircle } from 'lucide-react';

export function HostPartnerAgreementModal({
    agreement,
    isOpen,
    isSubmitting = false,
    onAccept,
    onCancel,
}) {
    const [agreed, setAgreed] = useState(false);
    const [error, setError] = useState('');

    if (!isOpen || !agreement) return null;

    const ratePercent = ((agreement.commission_rate_bps || 1000) / 100).toFixed(0);

    async function handleConfirm() {
        if (!agreed) {
            setError('Vui lòng đánh dấu xác nhận đồng ý với điều khoản hợp đồng.');
            return;
        }
        setError('');
        try {
            await onAccept(agreement);
        } catch (err) {
            setError(err?.response?.data?.message || err?.message || 'Không thể ghi nhận chấp thuận hợp đồng. Vui lòng thử lại.');
        }
    }

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="agreement-modal-title"
            className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-xs sm:p-6"
        >
            <div className="relative w-full max-w-2xl rounded-3xl border border-border bg-card p-6 shadow-2xl sm:p-8">
                {/* Header */}
                <div className="flex items-start gap-4">
                    <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <ShieldCheck className="h-6 w-6" aria-hidden />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 id="agreement-modal-title" className="text-xl font-semibold text-ink">
                                {agreement.title || 'Hợp đồng Đối tác Kinh doanh StayHub'}
                            </h2>
                            <span className="inline-flex items-center rounded-full bg-accent/15 px-2.5 py-0.5 text-xs font-semibold text-accent">
                                {agreement.version || 'v1.0'} · Hoa hồng {ratePercent}%
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Quý đối tác vui lòng đọc và xác nhận chấp thuận hợp đồng trước khi tạo cơ sở lưu trú mới trên hệ thống.
                        </p>
                    </div>
                </div>

                {/* Important Notice */}
                <div className="mt-4 flex items-start gap-3 rounded-2xl border border-accent/20 bg-accent-pale/40 p-3.5 text-xs text-ink/90">
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-accent" aria-hidden />
                    <div>
                        <span className="font-semibold text-accent">Dòng tiền & Hạn nộp phí:</span> Khách hàng thanh toán trực tiếp cho Host. Host có nghĩa vụ chuyển khoản 10% hoa hồng nền tảng trong vòng <span className="font-semibold text-ink">72 giờ</span> kể từ khi xác nhận hóa đơn của khách đã thanh toán thành công (Invoice PAID).
                    </div>
                </div>

                {/* Agreement Body */}
                <div className="mt-4">
                    <div className="flex items-center gap-1.5 pb-2 text-xs font-medium text-muted-foreground">
                        <FileText className="h-3.5 w-3.5" aria-hidden />
                        <span>Nội dung văn bản hợp đồng:</span>
                    </div>
                    <div
                        tabIndex={0}
                        aria-label="Nội dung chi tiết hợp đồng"
                        className="max-h-64 overflow-y-auto rounded-2xl border border-border/80 bg-surface/50 p-4 text-xs leading-relaxed text-muted-foreground whitespace-pre-line focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        {agreement.terms_body}
                    </div>
                </div>

                {/* Error Banner */}
                {error && (
                    <div role="alert" className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                        {error}
                    </div>
                )}

                {/* Clickwrap Checkbox */}
                <div className="mt-4 rounded-xl border border-border/70 p-3.5 transition-colors hover:bg-surface/30">
                    <label className="flex cursor-pointer items-start gap-3">
                        <input
                            id="agree-checkbox"
                            type="checkbox"
                            checked={agreed}
                            onChange={(e) => {
                                setAgreed(e.target.checked);
                                if (e.target.checked) setError('');
                            }}
                            disabled={isSubmitting}
                            className="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary"
                        />
                        <span className="text-xs font-medium text-ink">
                            Tôi đã đọc, hiểu rõ và tự nguyện chấp thuận toàn bộ các điều khoản của Hợp đồng Đối tác Kinh doanh Lưu trú StayHub (bao gồm mức phí hoa hồng {ratePercent}% và thời hạn nộp 72 giờ).
                        </span>
                    </label>
                </div>

                {/* Actions */}
                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={isSubmitting}
                        className="inline-flex min-h-10 items-center justify-center rounded-xl border border-border px-4 py-2 text-xs font-medium text-muted-foreground transition hover:bg-surface disabled:opacity-50"
                    >
                        Quay lại danh sách
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={!agreed || isSubmitting}
                        className="inline-flex min-h-10 items-center justify-center rounded-xl bg-primary px-6 py-2 text-xs font-semibold text-primary-foreground shadow-sm transition hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {isSubmitting ? 'Đang ghi nhận...' : 'Xác nhận và đồng ý hợp đồng'}
                    </button>
                </div>
            </div>
        </div>
    );
}
