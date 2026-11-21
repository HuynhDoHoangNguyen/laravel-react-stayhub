import { useState } from 'react';
import { CreditCard, Copy, Check, AlertCircle, X, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function CommissionRemittanceDialog({
    commission,
    isOpen,
    isSubmitting = false,
    onSubmit,
    onClose,
}) {
    const [copiedRef, setCopiedRef] = useState(false);
    const [copiedAccount, setCopiedAccount] = useState(false);
    const [bankRef, setBankRef] = useState('');
    const [error, setError] = useState('');

    if (!isOpen || !commission) return null;

    const formattedAmount = new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(Number(commission.commission_amount) || 0);

    const bankInfo = {
        bankName: 'Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)',
        accountNumber: '1029384756',
        accountHolder: 'CONG TY CP CONG NGHE STAYHUB',
    };

    const handleCopy = (text, type) => {
        navigator.clipboard?.writeText(text);
        if (type === 'ref') {
            setCopiedRef(true);
            setTimeout(() => setCopiedRef(false), 2000);
        } else {
            setCopiedAccount(true);
            setTimeout(() => setCopiedAccount(false), 2000);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');

        const idempotencyKey = typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `IDEM-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;

        try {
            await onSubmit({
                amount: Number(commission.commission_amount),
                bank_transaction_reference: bankRef.trim() || null,
                idempotency_key: idempotencyKey,
            });
        } catch (err) {
            setError(err?.response?.data?.message || err?.message || 'Không thể gửi thông tin đối soát. Vui lòng thử lại.');
        }
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="remittance-dialog-title"
            className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-xs sm:p-6"
        >
            <div className="relative w-full max-w-lg rounded-3xl border border-border bg-card p-6 shadow-2xl sm:p-8">
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
                        <CreditCard className="h-5 w-5" aria-hidden />
                    </div>
                    <div>
                        <h2 id="remittance-dialog-title" className="text-lg font-bold text-ink">
                            Khai báo chuyển khoản hoa hồng
                        </h2>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Chuyển khoản chính xác số tiền và nội dung bên dưới, sau đó xác nhận để hệ thống đối soát.
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

                {/* Bank Instructions Card */}
                <div className="mt-4 rounded-2xl border border-border/70 bg-surface/50 p-4 text-xs space-y-2.5">
                    <div className="flex justify-between items-center py-1 border-b border-border/40">
                        <span className="text-muted-foreground">Ngân hàng thụ hưởng:</span>
                        <span className="font-semibold text-ink text-right">{bankInfo.bankName}</span>
                    </div>

                    <div className="flex justify-between items-center py-1 border-b border-border/40">
                        <span className="text-muted-foreground">Số tài khoản:</span>
                        <div className="flex items-center gap-1.5 font-mono font-bold text-primary text-sm">
                            <span>{bankInfo.accountNumber}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-6 w-6"
                                onClick={() => handleCopy(bankInfo.accountNumber, 'acc')}
                                title="Sao chép số tài khoản"
                            >
                                {copiedAccount ? <Check className="h-3 w-3 text-emerald-600" /> : <Copy className="h-3 w-3" />}
                            </Button>
                        </div>
                    </div>

                    <div className="flex justify-between items-center py-1 border-b border-border/40">
                        <span className="text-muted-foreground">Chủ tài khoản:</span>
                        <span className="font-semibold text-ink uppercase">{bankInfo.accountHolder}</span>
                    </div>

                    <div className="flex justify-between items-center py-1 border-b border-border/40">
                        <span className="text-muted-foreground">Số tiền hoa hồng (10%):</span>
                        <span className="font-bold text-base text-primary">{formattedAmount}</span>
                    </div>

                    <div className="flex justify-between items-center py-1">
                        <span className="text-muted-foreground">Nội dung chuyển khoản (bắt buộc):</span>
                        <div className="flex items-center gap-1.5 font-mono font-bold text-amber-700 bg-amber-500/10 px-2 py-0.5 rounded-md">
                            <span>{commission.reference_code}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-6 w-6"
                                onClick={() => handleCopy(commission.reference_code, 'ref')}
                                title="Sao chép mã chuyển khoản"
                            >
                                {copiedRef ? <Check className="h-3 w-3 text-emerald-600" /> : <Copy className="h-3 w-3" />}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Remittance Form */}
                <form onSubmit={handleSubmit} className="mt-5 space-y-4">
                    <div>
                        <label htmlFor="bank-ref-input" className="block text-xs font-semibold text-ink mb-1.5">
                            Mã tham chiếu ngân hàng (nếu có)
                        </label>
                        <Input
                            id="bank-ref-input"
                            type="text"
                            placeholder="Ví dụ: FT2611210099 hoặc mã giao dịch app ngân hàng"
                            value={bankRef}
                            onChange={(e) => setBankRef(e.target.value)}
                            disabled={isSubmitting}
                            className="h-10 text-xs"
                        />
                        <span className="text-[11px] text-muted-foreground mt-1 block">
                            Nhập mã giao dịch để Ban quản trị đối soát sao kê nhanh chóng hơn.
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
                            disabled={isSubmitting}
                            className="h-10 text-xs font-semibold rounded-xl px-6"
                        >
                            {isSubmitting && <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" />}
                            <span>{isSubmitting ? 'Đang gửi khai báo…' : 'Xác nhận đã chuyển khoản'}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
