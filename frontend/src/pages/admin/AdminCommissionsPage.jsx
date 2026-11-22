import { useState, useEffect } from 'react';
import {
    ShieldCheck,
    CheckCircle2,
    AlertTriangle,
    Loader2,
    RefreshCw,
    Building2,
    Clock,
} from 'lucide-react';
import { adminCommissionService } from '@/services/adminCommissionService';
import { CommissionVerificationDialog } from '@/components/commission/CommissionVerificationDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export function AdminCommissionsPage() {
    const [viewMode, setViewMode] = useState('QUEUE'); // 'QUEUE' | 'ALL_COMMISSIONS'
    const [remittances, setRemittances] = useState([]);
    const [commissions, setCommissions] = useState([]);
    const [remittanceStatusFilter, setRemittanceStatusFilter] = useState('SUBMITTED');
    const [commissionStatusFilter, setCommissionStatusFilter] = useState('ALL');
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [successMessage, setSuccessMessage] = useState('');
    const [activeRemittance, setActiveRemittance] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [reloadTrigger, setReloadTrigger] = useState(0);

    // Fetch data based on active viewMode
    useEffect(() => {
        let isMounted = true;

        const loadData = async () => {
            try {
                if (viewMode === 'QUEUE') {
                    const params = {};
                    if (remittanceStatusFilter !== 'ALL') {
                        params.status = remittanceStatusFilter;
                    }
                    const res = await adminCommissionService.getRemittanceQueue(params);
                    if (isMounted) {
                        setRemittances(res?.data || []);
                    }
                } else {
                    const params = {};
                    if (commissionStatusFilter !== 'ALL') {
                        params.status = commissionStatusFilter;
                    }
                    const res = await adminCommissionService.getAdminCommissions(params);
                    if (isMounted) {
                        setCommissions(res?.data || []);
                    }
                }
            } catch (err) {
                if (isMounted) {
                    setError(err?.response?.data?.message || err?.message || 'Không thể tải dữ liệu hoa hồng.');
                }
            } finally {
                if (isMounted) {
                    setIsLoading(false);
                }
            }
        };

        loadData();

        return () => {
            isMounted = false;
        };
    }, [viewMode, remittanceStatusFilter, commissionStatusFilter, reloadTrigger]);

    const handleReload = () => {
        setIsLoading(true);
        setError('');
        setReloadTrigger((prev) => prev + 1);
    };

    const handleVerify = async (payload) => {
        if (!activeRemittance) return;
        setIsSubmitting(true);
        try {
            await adminCommissionService.verifyRemittance(activeRemittance.id, payload);
            setActiveRemittance(null);
            setSuccessMessage(`Đã xác minh đối soát thành công giao dịch #${activeRemittance.id}.`);
            setTimeout(() => setSuccessMessage(''), 5000);
            handleReload();
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleReject = async (payload) => {
        if (!activeRemittance) return;
        setIsSubmitting(true);
        try {
            await adminCommissionService.rejectRemittance(activeRemittance.id, payload);
            setActiveRemittance(null);
            setSuccessMessage(`Đã từ chối giao dịch #${activeRemittance.id}.`);
            setTimeout(() => setSuccessMessage(''), 5000);
            handleReload();
        } finally {
            setIsSubmitting(false);
        }
    };

    const formatCurrency = (val) =>
        new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val) || 0);

    const formatDateTime = (isoStr) => {
        if (!isoStr) return '—';
        return new Date(isoStr).toLocaleString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };

    const renderRemittanceBadge = (status) => {
        switch (status) {
            case 'SUBMITTED':
                return <Badge variant="outline" className="border-blue-500/40 bg-blue-500/10 text-blue-800 text-[11px] font-semibold">Chờ đối soát</Badge>;
            case 'VERIFIED':
                return <Badge variant="outline" className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 text-[11px] font-semibold">Đã xác minh (SETTLED)</Badge>;
            case 'REJECTED':
                return <Badge variant="outline" className="border-destructive/40 bg-destructive/10 text-destructive text-[11px] font-semibold">Đã từ chối</Badge>;
            default:
                return <Badge variant="outline" className="text-[11px]">{status}</Badge>;
        }
    };

    return (
        <div className="space-y-6 p-4 sm:p-8 max-w-7xl mx-auto">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
                        ✦ Ban quản trị StayHub
                    </span>
                    <h1 className="font-display text-2xl font-bold text-primary mt-1">
                        Hàng chờ đối soát & Quản lý hoa hồng
                    </h1>
                    <p className="text-xs text-muted-foreground mt-1">
                        Kiểm tra sao kê ngân hàng Vietcombank, đối chiếu mã giao dịch và xác nhận thực thu hoa hồng 10%.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleReload}
                    disabled={isLoading}
                    className="h-9 gap-2 text-xs font-semibold rounded-xl self-start sm:self-auto"
                >
                    <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
                    <span>Làm mới</span>
                </Button>
            </div>

            {/* Success Notification */}
            {successMessage && (
                <div role="status" className="flex items-center gap-2.5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-medium text-emerald-900">
                    <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-700" />
                    <span>{successMessage}</span>
                </div>
            )}

            {/* Error Notification */}
            {error && (
                <div role="alert" className="flex items-center gap-2.5 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-xs text-destructive">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    <span>{error}</span>
                </div>
            )}

            {/* View Mode Tabs */}
            <div className="flex items-center gap-3 border-b border-border/60 pb-3">
                <button
                    type="button"
                    onClick={() => { setIsLoading(true); setViewMode('QUEUE'); }}
                    className={`rounded-xl px-4 py-2 text-xs font-bold transition-all ${
                        viewMode === 'QUEUE'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-surface/80 text-muted-foreground hover:bg-surface hover:text-ink'
                    }`}
                >
                    Hàng chờ đối soát (Remittance Queue)
                </button>
                <button
                    type="button"
                    onClick={() => { setIsLoading(true); setViewMode('ALL_COMMISSIONS'); }}
                    className={`rounded-xl px-4 py-2 text-xs font-bold transition-all ${
                        viewMode === 'ALL_COMMISSIONS'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-surface/80 text-muted-foreground hover:bg-surface hover:text-ink'
                    }`}
                >
                    Tất cả hoa hồng toàn hệ thống
                </button>
            </div>

            {/* Sub-filters */}
            {viewMode === 'QUEUE' ? (
                <div className="flex items-center gap-2">
                    {['SUBMITTED', 'VERIFIED', 'REJECTED', 'ALL'].map((st) => (
                        <button
                            key={st}
                            type="button"
                            onClick={() => { setIsLoading(true); setRemittanceStatusFilter(st); }}
                            className={`rounded-lg px-3 py-1 text-xs font-semibold ${
                                remittanceStatusFilter === st
                                    ? 'bg-ink text-background'
                                    : 'bg-surface/60 text-muted-foreground hover:text-ink'
                            }`}
                        >
                            {st === 'SUBMITTED' ? 'Chờ kiểm tra' : st === 'VERIFIED' ? 'Đã duyệt' : st === 'REJECTED' ? 'Đã từ chối' : 'Tất cả'}
                        </button>
                    ))}
                </div>
            ) : (
                <div className="flex items-center gap-2">
                    {['ALL', 'DUE', 'UNDER_REVIEW', 'OVERDUE', 'SETTLED'].map((st) => (
                        <button
                            key={st}
                            type="button"
                            onClick={() => { setIsLoading(true); setCommissionStatusFilter(st); }}
                            className={`rounded-lg px-3 py-1 text-xs font-semibold ${
                                commissionStatusFilter === st
                                    ? 'bg-ink text-background'
                                    : 'bg-surface/60 text-muted-foreground hover:text-ink'
                            }`}
                        >
                            {st === 'ALL' ? 'Tất cả' : st === 'DUE' ? 'Chờ thanh toán' : st === 'UNDER_REVIEW' ? 'Đang đối soát' : st === 'OVERDUE' ? 'Quá hạn' : 'Đã thu'}
                        </button>
                    ))}
                </div>
            )}

            {/* Content Table */}
            <div className="rounded-3xl border border-border/70 bg-card shadow-xs overflow-hidden">
                {isLoading ? (
                    <div className="p-12 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                        <Loader2 className="h-4 w-4 animate-spin text-primary" />
                        <span>Đang tải dữ liệu đối soát…</span>
                    </div>
                ) : viewMode === 'QUEUE' ? (
                    remittances.length === 0 ? (
                        <div className="p-12 text-center">
                            <ShieldCheck className="mx-auto h-10 w-10 text-muted-foreground/40 mb-3" />
                            <h3 className="text-sm font-bold text-ink">Hàng chờ trống</h3>
                            <p className="text-xs text-muted-foreground mt-1">Không có yêu cầu nộp đối soát nào cần xử lý.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs text-ink">
                                <thead className="bg-surface/60 text-muted-foreground font-semibold text-[11px] uppercase tracking-wider border-b border-border/60">
                                    <tr>
                                        <th className="px-5 py-3.5">Mã chuyển khoản SHF</th>
                                        <th className="px-5 py-3.5">Chủ cơ sở (Host)</th>
                                        <th className="px-5 py-3.5 text-right">Số tiền khai nộp</th>
                                        <th className="px-5 py-3.5">Mã giao dịch Host nhập</th>
                                        <th className="px-5 py-3.5">Thời gian gửi</th>
                                        <th className="px-5 py-3.5">Trạng thái</th>
                                        <th className="px-5 py-3.5 text-right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {remittances.map((item) => (
                                        <tr key={item.id} className="hover:bg-surface/40 transition-colors">
                                            <td className="px-5 py-4 font-mono font-bold text-primary">
                                                {item.commission?.reference_code || 'SHF-XXXX'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-ink">{item.host?.name || 'Host'}</div>
                                                <div className="text-[11px] text-muted-foreground">{item.host?.phone || item.host?.email || '—'}</div>
                                            </td>
                                            <td className="px-5 py-4 text-right font-bold text-ink text-sm">
                                                {formatCurrency(item.amount)}
                                            </td>
                                            <td className="px-5 py-4 font-mono text-xs">
                                                {item.bank_transaction_reference || <span className="text-muted-foreground italic">Không có</span>}
                                            </td>
                                            <td className="px-5 py-4 text-muted-foreground text-[11px]">
                                                {formatDateTime(item.submitted_at)}
                                            </td>
                                            <td className="px-5 py-4">
                                                {renderRemittanceBadge(item.status)}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                {item.status === 'SUBMITTED' ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() => setActiveRemittance(item)}
                                                        className="h-8 rounded-xl px-3.5 text-xs font-semibold bg-primary"
                                                    >
                                                        <span>Đối soát sao kê</span>
                                                    </Button>
                                                ) : item.status === 'VERIFIED' ? (
                                                    <span className="text-[11px] text-emerald-700 font-mono font-medium">
                                                        Ref: {item.verified_bank_reference}
                                                    </span>
                                                ) : (
                                                    <span className="text-[11px] text-destructive italic">
                                                        Lý do: {item.review_note}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )
                ) : (
                    commissions.length === 0 ? (
                        <div className="p-12 text-center text-xs text-muted-foreground">Không có khoản hoa hồng nào.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs text-ink">
                                <thead className="bg-surface/60 text-muted-foreground font-semibold text-[11px] uppercase tracking-wider border-b border-border/60">
                                    <tr>
                                        <th className="px-5 py-3.5">Mã SHF</th>
                                        <th className="px-5 py-3.5">Host / Cơ sở</th>
                                        <th className="px-5 py-3.5 text-right">Doanh thu base</th>
                                        <th className="px-5 py-3.5 text-right">Hoa hồng (10%)</th>
                                        <th className="px-5 py-3.5">Hạn nộp</th>
                                        <th className="px-5 py-3.5">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {commissions.map((c) => (
                                        <tr key={c.id} className="hover:bg-surface/40 transition-colors">
                                            <td className="px-5 py-4 font-mono font-bold text-primary">{c.reference_code}</td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-ink">{c.host_name || 'Host'}</div>
                                                <div className="text-[11px] text-muted-foreground flex items-center gap-1">
                                                    <Building2 className="h-3 w-3" />
                                                    <span>{c.property_name || 'Cơ sở'}</span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-right">{formatCurrency(c.commission_base)}</td>
                                            <td className="px-5 py-4 text-right font-bold text-ink">{formatCurrency(c.commission_amount)}</td>
                                            <td className="px-5 py-4 text-muted-foreground text-[11px]">
                                                {c.due_at ? (
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-3.5 w-3.5" />
                                                        <span>{formatDateTime(c.due_at)}</span>
                                                    </div>
                                                ) : 'Chưa kích hoạt'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <Badge variant="outline" className="text-[11px]">{c.display_status}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )
                )}
            </div>

            {/* Verification Dialog */}
            <CommissionVerificationDialog
                remittance={activeRemittance}
                isOpen={Boolean(activeRemittance)}
                isSubmitting={isSubmitting}
                onVerify={handleVerify}
                onReject={handleReject}
                onClose={() => setActiveRemittance(null)}
            />
        </div>
    );
}

export default AdminCommissionsPage;
