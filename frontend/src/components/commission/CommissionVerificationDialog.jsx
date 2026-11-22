import { useState } from 'react';
import { ShieldCheck, CheckCircle2, XCircle, AlertCircle, X, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function CommissionVerificationDialog({
    remittance,
    isOpen,
    isSubmitting = false,
    onVerify,
    onReject,
    onClose,
}) {
    const [actionTab, setActionTab] = useState('VERIFY'); // 'VERIFY' | 'REJECT'
    const [verifiedBankRef, setVerifiedBankRef] = useState('');
    const [reviewNote, setReviewNote] = useState('');
    const [confirmedStatement, setConfirmedStatement] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [error, setError] = useState('');

    if (!isOpen || !remittance) return null;

    const formattedAmount = new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(Number(remittance.amount) || 0);

    const handleVerifySubmit = async (e) => {
        e.preventDefault();
        setError('');

        if (!verifiedBankRef.trim()) {
            setError('Vui lòng nhập mã giao dịch trích xuất từ sao kê ngân hàng.');
            return;
        }

        if (!confirmedStatement) {
            setError('Bạn phải đánh dấu xác nhận đã kiểm tra sao kê tài khoản thực tế.');
            return;
        }

        try {
            await onVerify({
                verified_bank_reference: verifiedBankRef.trim(),
                review_note: reviewNote.trim() || null,
            });
        } catch (err) {
            setError(err?.response?.data?.message || err?.message || 'Không thể xác minh giao dịch đối soát.');
        }
    };

    const handleRejectSubmit = async (e) => {
        e.preventDefault();
        setError('');

        if (!rejectReason.trim() || rejectReason.trim().length < 3) {
            setError('Vui lòng nhập lý do từ chối cụ thể (tối thiểu 3 ký tự).');
            return;
        }

        try {
            await onReject({
                reason: rejectReason.trim(),
            });
        } catch (err) {
            setError(err?.response?.data?.message || err?.message || 'Không thể từ chối giao dịch đối soát.');
        }
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="verification-dialog-title"
            className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-xs sm:p-6"
        >
            <div className="relative w-full max-w-xl rounded-3xl border border-border bg-card p-6 shadow-2xl sm:p-8">
                {/* Close Button */}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Đóng hộp thoại"
                    onClick={onClose}
                    className="absolute right-4 top-4 h-8 w-8 rounded-full"
                >
                    <X className="h-4 w-4" />
                </Button>

                {/* Header */}
                <div className="flex items-start gap-3.5 pb-4 border-b border-border/60">
                    <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <ShieldCheck className="h-6 w-6" aria-hidden />
                    </div>
                    <div>
                        <h2 id="verification-dialog-title" className="text-lg font-bold text-ink">
                            Đối soát sao kê ngân hàng
                        </h2>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Kiểm tra số tiền và mã chuyển khoản trên sao kê Vietcombank trước khi duyệt thực thu.
                        </p>
                    </div>
                </div>

                {/* Error Banner */}
                {error && (
                    <div role="alert" className="mt-4 flex items-start gap-2.5 rounded-2xl border border-destructive/30 bg-destructive/5 p-3.5 text-xs text-destructive">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span className="flex-1">{error}</span>
                    </div>
                )}

                {/* Remittance Fact Box */}
                <div className="mt-4 rounded-2xl border border-border/70 bg-surface/50 p-4 text-xs space-y-2">
                    <div className="flex justify-between items-center py-0.5">
                        <span className="text-muted-foreground">Chủ cơ sở (Host):</span>
                        <span className="font-semibold text-ink">{remittance.host?.name || 'Host'} ({remittance.host?.phone || remittance.host?.email || '—'})</span>
                    </div>
                    <div className="flex justify-between items-center py-0.5">
                        <span className="text-muted-foreground">Số tiền Host khai chuyển:</span>
                        <span className="font-bold text-sm text-primary">{formattedAmount}</span>
                    </div>
                    <div className="flex justify-between items-center py-0.5">
                        <span className="text-muted-foreground">Mã chuyển khoản SHF:</span>
                        <span className="font-mono font-bold text-amber-800 bg-amber-500/10 px-2 py-0.5 rounded">
                            {remittance.commission?.reference_code || 'SHF-XXXX'}
                        </span>
                    </div>
                    <div className="flex justify-between items-center py-0.5">
                        <span className="text-muted-foreground">Mã giao dịch do Host nhập:</span>
                        <span className="font-mono font-semibold text-ink">
                            {remittance.bank_transaction_reference || 'Không cung cấp'}
                        </span>
                    </div>
                </div>

                {/* Mode Selector Tabs */}
                <div className="mt-5 flex items-center gap-2 border-b border-border/60 pb-3">
                    <button
                        type="button"
                        onClick={() => { setActionTab('VERIFY'); setError(''); }}
                        className={`flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold transition-all ${
                            actionTab === 'VERIFY'
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'bg-surface text-muted-foreground hover:text-ink'
                        }`}
                    >
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        <span>Xác nhận đối soát (SETTLED)</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => { setActionTab('REJECT'); setError(''); }}
                        className={`flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold transition-all ${
                            actionTab === 'REJECT'
                                ? 'bg-destructive text-white shadow-xs'
                                : 'bg-surface text-muted-foreground hover:text-ink'
                        }`}
                    >
                        <XCircle className="h-3.5 w-3.5" />
                        <span>Từ chối yêu cầu</span>
                    </button>
                </div>

                {/* Form Body */}
                {actionTab === 'VERIFY' ? (
                    <form onSubmit={handleVerifySubmit} className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="verified-bank-ref-input" className="block text-xs font-semibold text-ink mb-1">
                                Mã giao dịch ngân hàng trích từ sao kê thực tế <span className="text-destructive">*</span>
                            </label>
                            <Input
                                id="verified-bank-ref-input"
                                type="text"
                                placeholder="Nhập mã giao dịch chính thức từ sao kê Vietcombank"
                                value={verifiedBankRef}
                                onChange={(e) => setVerifiedBankRef(e.target.value)}
                                disabled={isSubmitting}
                                className="h-10 text-xs"
                                required
                            />
                            <span className="text-[11px] text-muted-foreground mt-1 block">
                                Mã này là định danh duy nhất chống đối soát trùng lặp cho các khoản khác.
                            </span>
                        </div>

                        <div>
                            <label htmlFor="verify-note-input" className="block text-xs font-semibold text-ink mb-1">
                                Ghi chú kiểm chứng (tùy chọn)
                            </label>
                            <Input
                                id="verify-note-input"
                                type="text"
                                placeholder="Ví dụ: Đã khớp tiền trên sao kê ngày 21/11"
                                value={reviewNote}
                                onChange={(e) => setReviewNote(e.target.value)}
                                disabled={isSubmitting}
                                className="h-10 text-xs"
                            />
                        </div>

                        <div className="flex items-start gap-2.5 rounded-xl bg-surface/70 p-3">
                            <input
                                id="confirm-statement-checkbox"
                                type="checkbox"
                                checked={confirmedStatement}
                                onChange={(e) => setConfirmedStatement(e.target.checked)}
                                disabled={isSubmitting}
                                className="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary"
                            />
                            <label htmlFor="confirm-statement-checkbox" className="text-xs text-ink leading-relaxed cursor-pointer">
                                Tôi xác nhận đã kiểm tra tài khoản ngân hàng Vietcombank của StayHub và tiền hoa hồng ({formattedAmount}) đã về tài khoản đầy đủ.
                            </label>
                        </div>

                        <div className="flex flex-col-reverse gap-2.5 pt-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                                disabled={isSubmitting}
                                className="h-10 text-xs font-semibold rounded-xl"
                            >
                                Đóng
                            </Button>
                            <Button
                                type="submit"
                                disabled={isSubmitting || !verifiedBankRef.trim() || !confirmedStatement}
                                className="h-10 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 px-6 text-white"
                            >
                                {isSubmitting && <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" />}
                                <span>Phê duyệt thực thu</span>
                            </Button>
                        </div>
                    </form>
                ) : (
                    <form onSubmit={handleRejectSubmit} className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="reject-reason-input" className="block text-xs font-semibold text-ink mb-1">
                                Lý do từ chối yêu cầu đối soát <span className="text-destructive">*</span>
                            </label>
                            <textarea
                                id="reject-reason-input"
                                rows={3}
                                placeholder="Nêu rõ lý do (ví dụ: Không tìm thấy giao dịch, sai số tiền, sai mã chuyển khoản...)"
                                value={rejectReason}
                                onChange={(e) => setRejectReason(e.target.value)}
                                disabled={isSubmitting}
                                className="w-full rounded-xl border border-input bg-transparent px-3 py-2 text-xs text-ink shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                required
                            />
                            <span className="text-[11px] text-muted-foreground mt-1 block">
                                Lý do này sẽ được hiển thị cho Host để họ kiểm tra và nộp lại yêu cầu đối soát mới.
                            </span>
                        </div>

                        <div className="flex flex-col-reverse gap-2.5 pt-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                                disabled={isSubmitting}
                                className="h-10 text-xs font-semibold rounded-xl"
                            >
                                Hủy bỏ
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={isSubmitting || !rejectReason.trim()}
                                className="h-10 text-xs font-semibold rounded-xl px-6"
                            >
                                {isSubmitting && <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" />}
                                <span>Xác nhận từ chối</span>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
