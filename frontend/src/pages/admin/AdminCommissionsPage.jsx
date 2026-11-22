import { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
    ShieldCheck,
    CheckCircle2,
    AlertTriangle,
    Loader2,
    RefreshCw,
    Building2,
    Clock,
} from 'lucide-react';
import { normalizeApiError } from '@/api/apiError';
import AdminDateRangeFilter from '@/components/admin/AdminDateRangeFilter';
import AdminPagination from '@/components/admin/AdminPagination';
import { useAuth } from '@/hooks/useAuth';
import { formatVnd } from '@/utils/formatVnd';
import { adminCommissionService } from '@/services/adminCommissionService';
import { CommissionVerificationDialog } from '@/components/commission/CommissionVerificationDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const commissionStatuses = { ALL: 'Tất cả', DUE: 'Chờ thanh toán', UNDER_REVIEW: 'Đang đối soát', OVERDUE: 'Quá hạn', SETTLED: 'Đã thu' };
const remittanceStatuses = { SUBMITTED: 'Chờ kiểm tra', VERIFIED: 'Đã duyệt', REJECTED: 'Đã từ chối', ALL: 'Tất cả' };
const dateFields = { created_at: 'Ngày phát sinh', settled_at: 'Ngày đối soát', due_at: 'Ngày đến hạn' };
const emptyRange = { from_date: '', to_date: '' };
const initialDates = { ...emptyRange, date_field: 'created_at' };
const initialQuery = { ...initialDates, status: 'ALL', page: 1 };
const initialQueueQuery = { ...emptyRange, status: 'SUBMITTED', page: 1 };
const vietnamTimeZone = 'Asia/Ho_Chi_Minh';
const formatDay = (day) => day.split('-').reverse().join('/');

function readStatus(params, key, statuses, fallback) {
    const status = params.get(key);
    return Object.hasOwn(statuses, status) ? status : fallback;
}

export function AdminCommissionsPage() {
    const { captureProfileAuth, isProfileAuthCurrent } = useAuth();
    const [params, setParams] = useSearchParams();
    const isQueue = params.get('tab') !== 'commissions';
    const queueStatus = readStatus(params, 'queue_status', remittanceStatuses, initialQueueQuery.status);
    const commissionStatus = readStatus(params, 'commission_status', commissionStatuses, initialQuery.status);
    const initialCommissionDates = commissionStatus === 'OVERDUE' ? { ...initialDates, date_field: 'due_at' } : initialDates;
    const [queueQuery, setQueueQuery] = useState(() => ({ ...initialQueueQuery, status: queueStatus }));
    const [queueDraft, setQueueDraft] = useState(emptyRange);
    const [queueRangeError, setQueueRangeError] = useState(false);
    const [commissionQuery, setCommissionQuery] = useState(() => ({ ...initialQuery, ...initialCommissionDates, status: commissionStatus }));
    const [commissionDraft, setCommissionDraft] = useState(initialCommissionDates);
    const [commissionRangeError, setCommissionRangeError] = useState(false);
    const [view, setView] = useState(null);
    const [successMessage, setSuccessMessage] = useState('');
    const [activeRemittance, setActiveRemittance] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [reloadTrigger, setReloadTrigger] = useState(0);
    // Reconcile browser navigation before rendering or fetching with the previous status.
    if (queueQuery.status !== queueStatus) {
        setQueueQuery({ ...queueQuery, status: queueStatus, page: 1 });
    }
    if (commissionQuery.status !== commissionStatus) {
        const dateField = commissionStatus === 'OVERDUE' ? 'due_at' : commissionQuery.date_field;
        if (commissionStatus === 'OVERDUE') setCommissionDraft({ ...commissionDraft, date_field: dateField });
        setCommissionQuery({ ...commissionQuery, status: commissionStatus, date_field: dateField, page: 1 });
    }

    const query = isQueue ? queueQuery : commissionQuery;
    const setQuery = isQueue ? setQueueQuery : setCommissionQuery;
    const dateDraft = isQueue ? queueDraft : commissionDraft;
    const setDateDraft = isQueue ? setQueueDraft : setCommissionDraft;
    const rangeError = isQueue ? queueRangeError : commissionRangeError;
    const setRangeError = isQueue ? setQueueRangeError : setCommissionRangeError;
    const filterId = isQueue ? 'remittance-filter' : 'commission-filter';

    useEffect(() => {
        const controller = new AbortController();
        const auth = captureProfileAuth();
        const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
        const options = { signal: controller.signal };
        if (current()) {
            const request = isQueue
                ? adminCommissionService.getRemittanceQueue({
                    page: query.page,
                    status: query.status,
                    from_date: query.from_date || undefined,
                    to_date: query.to_date || undefined,
                }, options)
                : adminCommissionService.getAdminCommissions({
                    page: query.page,
                    status: query.status === 'ALL' ? undefined : query.status,
                    date_field: query.date_field,
                    from_date: query.from_date || undefined,
                    to_date: query.to_date || undefined,
                }, options);

            request.then(
                (result) => {
                    if (!current()) return;
                    if (isQueue && query.page > result.meta.last_page) {
                        setQueueQuery((previous) => previous === query ? { ...previous, page: result.meta.last_page } : previous);
                        return;
                    }
                    setView({ isQueue, query, reloadTrigger, auth, result });
                },
                (failure) => {
                    const error = normalizeApiError(failure);
                    if (current() && !error.isCancelled) setView({ isQueue, query, reloadTrigger, auth, error });
                },
            );
        }
        return () => controller.abort();
    }, [isQueue, query, reloadTrigger, captureProfileAuth, isProfileAuthCurrent]);

    const visible = view?.isQueue === isQueue && view.query === query && view.reloadTrigger === reloadTrigger
        && isProfileAuthCurrent(view.auth) ? view : null;
    const isLoading = visible === null;
    const error = visible?.error;
    const result = visible?.result;
    const rows = result?.data ?? [];
    const fields = rangeError ? { to_date: ['Ngày kết thúc phải từ ngày bắt đầu trở đi.'] }
        : dateDraft.date_field === query.date_field && dateDraft.from_date === query.from_date
            && dateDraft.to_date === query.to_date ? error?.fields : undefined;

    const handleReload = () => setReloadTrigger((prev) => prev + 1);
    const changePage = (page) => setQuery((previous) => ({ ...previous, page }));

    function updateParam(key, value) {
        if (params.get(key) === value) return;
        const next = new URLSearchParams(params);
        if (value === null) next.delete(key);
        else next.set(key, value);
        setParams(next);
    }

    function selectStatus(status) {
        if (status === query.status) return;
        const defaultStatus = isQueue ? initialQueueQuery.status : initialQuery.status;
        updateParam(isQueue ? 'queue_status' : 'commission_status', status === defaultStatus ? null : status);
    }

    function changeDateDraft(field, value) {
        setRangeError(false);
        setDateDraft((previous) => ({ ...previous, [field]: value }));
    }

    function applyDates() {
        if (dateDraft.from_date && dateDraft.to_date && dateDraft.from_date > dateDraft.to_date) {
            setRangeError(true);
            document.getElementById(`${filterId}-to_date`)?.focus();
            return;
        }
        setRangeError(false);
        setQuery((previous) => ({ ...previous, ...dateDraft, page: 1 }));
    }

    function resetFilters() {
        setRangeError(false);
        setDateDraft(isQueue ? emptyRange : initialDates);
        setQuery({ ...(isQueue ? initialQueueQuery : initialQuery), status: query.status });
        updateParam(isQueue ? 'queue_status' : 'commission_status', null);
    }

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

    const formatDateTime = (isoStr) => {
        if (!isoStr) return '—';
        return new Date(isoStr).toLocaleString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: vietnamTimeZone,
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
                    <span>{error.message}</span>
                    <Button type="button" variant="outline" size="sm" onClick={handleReload}>Thử lại</Button>
                </div>
            )}

            {/* View Mode Tabs */}
            <div className="flex flex-wrap items-center gap-3 border-b border-border/60 pb-3">
                <button
                    type="button"
                    onClick={() => { if (!isQueue) updateParam('tab', null); }}
                    aria-pressed={isQueue}
                    className={`rounded-xl px-4 py-2 text-xs font-bold transition-all ${
                        isQueue
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-surface/80 text-muted-foreground hover:bg-surface hover:text-ink'
                    }`}
                >
                    Hàng chờ đối soát (Remittance Queue)
                </button>
                <button
                    type="button"
                    onClick={() => { if (isQueue) updateParam('tab', 'commissions'); }}
                    aria-pressed={!isQueue}
                    className={`rounded-xl px-4 py-2 text-xs font-bold transition-all ${
                        !isQueue
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-surface/80 text-muted-foreground hover:bg-surface hover:text-ink'
                    }`}
                >
                    Tất cả hoa hồng toàn hệ thống
                </button>
            </div>

            {/* Sub-filters */}
            {isQueue ? (
                <div className="flex flex-wrap items-center gap-2">
                    {Object.entries(remittanceStatuses).map(([st, label]) => (
                        <button
                            key={st}
                            type="button"
                            onClick={() => selectStatus(st)}
                            aria-pressed={queueQuery.status === st}
                            className={`rounded-lg px-3 py-1 text-xs font-semibold ${
                                queueQuery.status === st
                                    ? 'bg-ink text-background'
                                    : 'bg-surface/60 text-muted-foreground hover:text-ink'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            ) : (
                <div className="flex flex-wrap items-center gap-2">
                    {Object.entries(commissionStatuses).map(([st, label]) => (
                        <button
                            key={st}
                            type="button"
                            onClick={() => selectStatus(st)}
                            aria-pressed={commissionQuery.status === st}
                            className={`rounded-lg px-3 py-1 text-xs font-semibold ${
                                commissionQuery.status === st
                                    ? 'bg-ink text-background'
                                    : 'bg-surface/60 text-muted-foreground hover:text-ink'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            )}

            <div className="space-y-2">
                {isQueue && <h2 className="text-sm font-semibold">Ngày Host gửi yêu cầu</h2>}
                <AdminDateRangeFilter id={filterId} value={dateDraft} fields={fields} dateFields={isQueue ? undefined : dateFields}
                    onChange={changeDateDraft} onApply={applyDates} onReset={resetFilters} />
                <p className="text-xs text-muted-foreground">
                    {isQueue ? (query.from_date || query.to_date ? 'Khoảng ngày đang lọc' : 'Mọi ngày gửi') : dateFields[query.date_field]}
                    {query.from_date && ' · Từ ' + formatDay(query.from_date)}
                    {query.to_date && ' · Đến ' + formatDay(query.to_date)}
                    {' · Giờ Việt Nam'}
                </p>
                {result && <p role="status" className="text-sm text-muted-foreground">
                    {result.meta.total} {isQueue ? 'yêu cầu' : 'khoản hoa hồng'} phù hợp.
                </p>}
            </div>

            {/* Content Table */}
            {!error && <div className="rounded-3xl border border-border/70 bg-card shadow-xs overflow-hidden" aria-busy={isLoading}>
                {isLoading ? (
                    <div role="status" className="p-12 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                        <Loader2 className="h-4 w-4 animate-spin text-primary" />
                        <span>Đang tải dữ liệu đối soát…</span>
                    </div>
                ) : isQueue ? (
                    rows.length === 0 ? (
                        <div className="p-12 text-center">
                            <ShieldCheck className="mx-auto h-10 w-10 text-muted-foreground/40 mb-3" />
                            <h3 className="text-sm font-bold text-ink">Hàng chờ trống</h3>
                            <p className="text-xs text-muted-foreground mt-1">Không có yêu cầu đối soát phù hợp với bộ lọc.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto focus-visible:outline-2 focus-visible:outline-ring"
                            tabIndex={0} role="region" aria-label="Bảng hàng chờ đối soát">
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
                                    {rows.map((item) => (
                                        <tr key={item.id} className="hover:bg-surface/40 transition-colors">
                                            <td className="px-5 py-4 font-mono font-bold text-primary">
                                                {item.commission?.reference_code || 'SHF-XXXX'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-ink">{item.host?.name || 'Host'}</div>
                                                <div className="text-[11px] text-muted-foreground">{item.host?.phone || item.host?.email || '—'}</div>
                                            </td>
                                            <td className="px-5 py-4 text-right font-bold text-ink text-sm">
                                                {formatVnd(item.amount)}
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
                    rows.length === 0 ? (
                        <div className="p-12 text-center text-xs text-muted-foreground">Không có khoản hoa hồng phù hợp.</div>
                    ) : (
                        <div className="overflow-x-auto focus-visible:outline-2 focus-visible:outline-ring"
                            tabIndex={0} role="region" aria-label="Bảng hoa hồng toàn hệ thống">
                            <table className="w-full text-left text-xs text-ink">
                                <thead className="bg-surface/60 text-muted-foreground font-semibold text-[11px] uppercase tracking-wider border-b border-border/60">
                                    <tr>
                                        <th className="px-5 py-3.5">Mã SHF</th>
                                        <th className="px-5 py-3.5">Host / Cơ sở</th>
                                        <th className="px-5 py-3.5 text-right">Doanh thu base</th>
                                        <th className="px-5 py-3.5 text-right">Hoa hồng</th>
                                        <th className="px-5 py-3.5">Hạn nộp</th>
                                        {commissionQuery.date_field !== 'due_at' &&
                                            <th className="px-5 py-3.5">{dateFields[commissionQuery.date_field]}</th>}
                                        <th className="px-5 py-3.5">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {rows.map((c) => (
                                        <tr key={c.id} className="hover:bg-surface/40 transition-colors">
                                            <td className="px-5 py-4">
                                                <div className="font-mono font-bold text-primary">{c.reference_code}</div>
                                                {c.invoice_code && <div className="mt-1 text-muted-foreground">{c.invoice_code}</div>}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-ink">{c.host_name || 'Host'}</div>
                                                <div className="text-[11px] text-muted-foreground flex items-center gap-1">
                                                    <Building2 className="h-3 w-3" />
                                                    <span>{c.property_name || 'Cơ sở'}</span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-right">{formatVnd(c.commission_base)}</td>
                                            <td className="px-5 py-4 text-right font-bold text-ink">{formatVnd(c.commission_amount)}</td>
                                            <td className="px-5 py-4 text-muted-foreground text-[11px]">
                                                {c.due_at ? (
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-3.5 w-3.5" />
                                                        <span>{formatDateTime(c.due_at)}</span>
                                                    </div>
                                                ) : 'Chưa kích hoạt'}
                                            </td>
                                            {commissionQuery.date_field !== 'due_at' && <td className="px-5 py-4 text-muted-foreground">
                                                {formatDateTime(c[commissionQuery.date_field])}
                                            </td>}
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
            </div>}
            {result && <AdminPagination label={isQueue ? 'Phân trang hàng chờ đối soát' : 'Phân trang hoa hồng'}
                currentPage={result.meta.current_page} lastPage={result.meta.last_page} onPageChange={changePage} />}

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
