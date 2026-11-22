import { useState, useEffect } from 'react';
import {
    CreditCard,
    Clock,
    CheckCircle2,
    AlertTriangle,
    Loader2,
    RefreshCw,
    Building2,
} from 'lucide-react';
import { hostCommissionService } from '@/services/hostCommissionService';
import { CommissionRemittanceDialog } from '@/components/commission/CommissionRemittanceDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const STATUS_TABS = [
    { key: 'ALL', label: 'Tất cả' },
    { key: 'DUE', label: 'Chờ thanh toán' },
    { key: 'UNDER_REVIEW', label: 'Đang đối soát' },
    { key: 'OVERDUE', label: 'Quá hạn' },
    { key: 'SETTLED', label: 'Đã đối soát' },
    { key: 'AWAITING_CUSTOMER_PAYMENT', label: 'Chờ khách thanh toán' },
];

export function HostCommissionsPage() {
    const [commissions, setCommissions] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [selectedStatus, setSelectedStatus] = useState('ALL');
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [activeCommission, setActiveCommission] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [successMessage, setSuccessMessage] = useState('');
    const [reloadTrigger, setReloadTrigger] = useState(0);

    useEffect(() => {
        let isMounted = true;

        const load = async () => {
            try {
                const params = { page: meta.current_page };
                if (selectedStatus !== 'ALL') {
                    params.status = selectedStatus;
                }
                const res = await hostCommissionService.getCommissions(params);
                if (isMounted) {
                    setCommissions(res?.data || []);
                    if (res?.meta) {
                        setMeta(res.meta);
                    }
                }
            } catch (err) {
                if (isMounted) {
                    setError(err?.response?.data?.message || err?.message || 'Không thể tải danh sách hoa hồng.');
                }
            } finally {
                if (isMounted) {
                    setIsLoading(false);
                }
            }
        };

        load();

        return () => {
            isMounted = false;
        };
    }, [selectedStatus, meta.current_page, reloadTrigger]);

    const handleFilterStatus = (key) => {
        setIsLoading(true);
        setSelectedStatus(key);
        setMeta((prev) => ({ ...prev, current_page: 1 }));
    };

    const handleReload = () => {
        setIsLoading(true);
        setError('');
        setReloadTrigger((prev) => prev + 1);
    };

    const handleRemittanceSubmit = async (payload) => {
        if (!activeCommission) return;
        setIsSubmitting(true);
        try {
            await hostCommissionService.submitRemittance(activeCommission.id, payload);
            setActiveCommission(null);
            setSuccessMessage(`Đã gửi thông tin đối soát cho mã ${activeCommission.reference_code} thành công.`);
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
        const d = new Date(isoStr);
        return d.toLocaleString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };

    const renderStatusBadge = (displayStatus) => {
        switch (displayStatus) {
            case 'DUE':
                return (
                    <Badge variant="outline" className="border-amber-500/40 bg-amber-500/10 text-amber-800 font-semibold text-[11px]">
                        Chờ thanh toán (72h)
                    </Badge>
                );
            case 'UNDER_REVIEW':
                return (
                    <Badge variant="outline" className="border-blue-500/40 bg-blue-500/10 text-blue-800 font-semibold text-[11px]">
                        Đang đối soát
                    </Badge>
                );
            case 'OVERDUE':
                return (
                    <Badge variant="outline" className="border-destructive/40 bg-destructive/10 text-destructive font-semibold text-[11px]">
                        Quá hạn 72h
                    </Badge>
                );
            case 'SETTLED':
                return (
                    <Badge variant="outline" className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 font-semibold text-[11px]">
                        Đã đối soát
                    </Badge>
                );
            case 'VOID':
                return (
                    <Badge variant="outline" className="border-muted bg-muted/40 text-muted-foreground font-semibold text-[11px]">
                        Miễn phí (0đ)
                    </Badge>
                );
            case 'AWAITING_CUSTOMER_PAYMENT':
            default:
                return (
                    <Badge variant="outline" className="border-border text-muted-foreground font-normal text-[11px]">
                        Chờ khách thanh toán
                    </Badge>
                );
        }
    };

    return (
        <div className="space-y-6 p-4 sm:p-8 max-w-7xl mx-auto">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
                        ✦ Nghĩa vụ tài chính nền tảng
                    </span>
                    <h1 className="font-display text-2xl font-bold text-primary mt-1">
                        Hoa hồng & Đối soát nền tảng StayHub
                    </h1>
                    <p className="text-xs text-muted-foreground mt-1">
                        Quản lý các khoản phí hoa hồng 10% doanh thu thực tế, thời hạn nộp 72 giờ và lịch sử đối soát.
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

            {/* Filter Tabs */}
            <div className="flex flex-wrap items-center gap-2 border-b border-border/60 pb-3">
                {STATUS_TABS.map((tab) => {
                    const isActive = selectedStatus === tab.key;
                    return (
                        <button
                            key={tab.key}
                            type="button"
                            onClick={() => handleFilterStatus(tab.key)}
                            className={`rounded-xl px-3.5 py-1.5 text-xs font-semibold transition-all ${
                                isActive
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'bg-surface/80 text-muted-foreground hover:bg-surface hover:text-ink'
                            }`}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            {/* Main Content Table */}
            <div className="rounded-3xl border border-border/70 bg-card shadow-xs overflow-hidden">
                {isLoading ? (
                    <div className="p-12 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                        <Loader2 className="h-4 w-4 animate-spin text-primary" />
                        <span>Đang tải danh sách hoa hồng…</span>
                    </div>
                ) : commissions.length === 0 ? (
                    <div className="p-12 text-center">
                        <CreditCard className="mx-auto h-10 w-10 text-muted-foreground/40 mb-3" />
                        <h3 className="text-sm font-bold text-ink">Không có khoản hoa hồng nào</h3>
                        <p className="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                            Hiện không có khoản phí hoa hồng nào thuộc bộ lọc đã chọn.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-ink">
                            <thead className="bg-surface/60 text-muted-foreground font-semibold text-[11px] uppercase tracking-wider border-b border-border/60">
                                <tr>
                                    <th className="px-5 py-3.5">Mã chuyển khoản</th>
                                    <th className="px-5 py-3.5">Hóa đơn / Cơ sở</th>
                                    <th className="px-5 py-3.5 text-right">Doanh thu tính phí</th>
                                    <th className="px-5 py-3.5 text-right">Phí hoa hồng (10%)</th>
                                    <th className="px-5 py-3.5">Hạn nộp (72h)</th>
                                    <th className="px-5 py-3.5">Trạng thái</th>
                                    <th className="px-5 py-3.5 text-right">Hành động</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {commissions.map((item) => {
                                    const canRemit = item.display_status === 'DUE' || item.display_status === 'OVERDUE';
                                    return (
                                        <tr key={item.id} className="hover:bg-surface/40 transition-colors">
                                            <td className="px-5 py-4 font-mono font-bold text-primary">
                                                {item.reference_code}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-ink">{item.invoice_code || '—'}</div>
                                                <div className="text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                                    <Building2 className="h-3 w-3" />
                                                    <span>{item.property_name || 'Cơ sở lưu trú'}</span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-right font-medium">
                                                {formatCurrency(item.commission_base)}
                                            </td>
                                            <td className="px-5 py-4 text-right font-bold text-ink text-sm">
                                                {formatCurrency(item.commission_amount)}
                                            </td>
                                            <td className="px-5 py-4 text-muted-foreground text-[11px]">
                                                {item.due_at ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <Clock className="h-3.5 w-3.5 text-muted-foreground" />
                                                        <span>{formatDateTime(item.due_at)}</span>
                                                    </div>
                                                ) : (
                                                    <span>Chưa kích hoạt</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                {renderStatusBadge(item.display_status)}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                {canRemit ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() => setActiveCommission(item)}
                                                        className="h-8 rounded-xl px-3.5 text-xs font-semibold"
                                                    >
                                                        <span>Nộp đối soát</span>
                                                    </Button>
                                                ) : (
                                                    <span className="text-[11px] text-muted-foreground italic">
                                                        {item.display_status === 'UNDER_REVIEW' ? 'Đang kiểm tra' : 'Hoàn tất'}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Remittance Dialog */}
            <CommissionRemittanceDialog
                commission={activeCommission}
                isOpen={Boolean(activeCommission)}
                isSubmitting={isSubmitting}
                onSubmit={handleRemittanceSubmit}
                onClose={() => setActiveCommission(null)}
            />
        </div>
    );
}

export default HostCommissionsPage;
