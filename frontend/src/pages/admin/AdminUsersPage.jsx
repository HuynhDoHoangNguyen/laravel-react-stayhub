import { useState, useEffect } from 'react';
import {
    Users,
    Building2,
    ShieldCheck,
    UserCheck,
    UserX,
    Clock,
    Search,
    RefreshCw,
    Loader2,
    CheckCircle2,
    AlertTriangle,
    Lock,
    Unlock,
} from 'lucide-react';
import { adminUserService } from '@/services/adminUserService';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const FILTER_TABS = [
    { id: 'ALL', label: 'Tất cả người dùng' },
    { id: 'PENDING_HOST', label: 'Đối tác chờ duyệt' },
    { id: 'HOST', label: 'Đối tác (Host)' },
    { id: 'CUSTOMER', label: 'Khách hàng' },
    { id: 'ADMIN', label: 'Quản trị viên' },
];

export default function AdminUsersPage() {
    const [activeTab, setActiveTab] = useState('ALL');
    const [statusFilter, setStatusFilter] = useState('ALL');
    const [keywordInput, setKeywordInput] = useState('');
    const [appliedKeyword, setAppliedKeyword] = useState('');
    const [page, setPage] = useState(1);
    const [users, setUsers] = useState([]);
    const [summary, setSummary] = useState({
        total_users: 0,
        pending_hosts: 0,
        approved_hosts: 0,
        total_customers: 0,
        active_users: 0,
        blocked_users: 0,
    });
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [successMessage, setSuccessMessage] = useState('');
    const [actingUserId, setActingUserId] = useState(null);
    const [reloadTrigger, setReloadTrigger] = useState(0);

    useEffect(() => {
        let isMounted = true;
        const controller = new AbortController();

        const fetchUsers = async () => {
            setIsLoading(true);
            setError('');
            try {
                const params = { page, per_page: 15 };
                if (appliedKeyword) params.keyword = appliedKeyword;
                if (statusFilter !== 'ALL') params.status = statusFilter;

                if (activeTab === 'PENDING_HOST') {
                    params.role = 'HOST';
                    params.host_approval = 'PENDING';
                } else if (activeTab !== 'ALL') {
                    params.role = activeTab;
                }

                const res = await adminUserService.listUsers(params, { signal: controller.signal });
                if (!isMounted) return;

                setUsers(res?.data || []);
                if (res?.summary) setSummary(res.summary);
                if (res?.meta) setMeta(res.meta);
            } catch (err) {
                if (!isMounted || err?.name === 'CanceledError' || err?.name === 'AbortError') return;
                setError(err?.response?.data?.message || err?.message || 'Không thể tải danh sách người dùng.');
            } finally {
                if (isMounted) setIsLoading(false);
            }
        };

        fetchUsers();

        return () => {
            isMounted = false;
            controller.abort();
        };
    }, [activeTab, statusFilter, appliedKeyword, page, reloadTrigger]);

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        setPage(1);
        setAppliedKeyword(keywordInput.trim());
    };

    const handleTabChange = (tabId) => {
        setActiveTab(tabId);
        setPage(1);
    };

    const handleReload = () => {
        setReloadTrigger((prev) => prev + 1);
    };

    const handleApproveHost = async (user) => {
        if (actingUserId) return;
        setActingUserId(user.id);
        setError('');
        try {
            const res = await adminUserService.approveHost(user.id);
            setSuccessMessage(res?.message || `Đã phê duyệt mở khóa tài khoản Đối tác "${user.name}".`);
            setTimeout(() => setSuccessMessage(''), 5000);
            handleReload();
        } catch (err) {
            setError(err?.response?.data?.message || 'Không thể phê duyệt tài khoản Đối tác. Vui lòng thử lại.');
        } finally {
            setActingUserId(null);
        }
    };

    const handleToggleStatus = async (user) => {
        if (actingUserId) return;
        const targetStatus = user.status === 'BLOCKED' ? 'ACTIVE' : 'BLOCKED';
        setActingUserId(user.id);
        setError('');
        try {
            const res = await adminUserService.updateUserStatus(user.id, targetStatus);
            setSuccessMessage(
                res?.message ||
                (targetStatus === 'BLOCKED'
                    ? `Đã khóa tài khoản "${user.name}".`
                    : `Đã mở khóa tài khoản "${user.name}".`)
            );
            setTimeout(() => setSuccessMessage(''), 5000);
            handleReload();
        } catch (err) {
            const fieldError = err?.response?.data?.errors?.status?.[0];
            setError(fieldError || err?.response?.data?.message || 'Không thể cập nhật trạng thái tài khoản.');
        } finally {
            setActingUserId(null);
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
        });
    };

    const renderRoleBadge = (role) => {
        switch (role) {
            case 'ADMIN':
                return <Badge variant="outline" className="border-rose-500/40 bg-rose-500/10 text-rose-800 text-[11px] font-semibold">Quản trị viên</Badge>;
            case 'HOST':
                return <Badge variant="outline" className="border-primary/40 bg-primary/10 text-primary text-[11px] font-semibold">Đối tác (Host)</Badge>;
            default:
                return <Badge variant="outline" className="border-slate-400/40 bg-slate-500/10 text-slate-700 text-[11px] font-semibold">Khách hàng</Badge>;
        }
    };

    const renderStatusBadge = (status) => {
        switch (status) {
            case 'ACTIVE':
                return <Badge variant="outline" className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 text-[11px] font-semibold">Đang hoạt động</Badge>;
            case 'BLOCKED':
                return <Badge variant="outline" className="border-destructive/40 bg-destructive/10 text-destructive text-[11px] font-semibold">Đã khóa</Badge>;
            default:
                return <Badge variant="outline" className="border-amber-500/40 bg-amber-500/10 text-amber-800 text-[11px] font-semibold">Chưa kích hoạt</Badge>;
        }
    };

    const renderHostApprovalBadge = (user) => {
        if (user.role !== 'HOST') {
            return <span className="text-xs text-muted-foreground">—</span>;
        }
        if (user.is_host_approved) {
            return (
                <Badge variant="outline" className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 text-[11px] font-semibold">
                    <CheckCircle2 className="mr-1 h-3 w-3" />
                    Đã duyệt mở khóa
                </Badge>
            );
        }
        return (
            <Badge variant="outline" className="border-amber-500/50 bg-amber-500/15 text-amber-900 text-[11px] font-semibold">
                <Clock className="mr-1 h-3 w-3" />
                Chờ Admin duyệt
            </Badge>
        );
    };

    return (
        <div className="space-y-6 p-4 sm:p-8 max-w-7xl mx-auto text-left">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
                        ✦ Ban quản trị StayHub
                    </span>
                    <h1 className="font-display text-2xl font-bold text-primary mt-1">
                        Quản lý Người dùng & Phê duyệt Đối tác
                    </h1>
                    <p className="text-xs text-muted-foreground mt-1">
                        Xem danh sách tài khoản toàn hệ thống, phê duyệt mở khóa chức năng kinh doanh cho Đối tác (Host) mới đăng ký và kiểm soát trạng thái hoạt động.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={handleReload}
                    disabled={isLoading}
                    className="rounded-xl text-xs font-medium self-start sm:self-auto"
                >
                    <RefreshCw className={`h-3.5 w-3.5 mr-1.5 ${isLoading ? 'animate-spin' : ''}`} />
                    Làm mới
                </Button>
            </div>

            {/* KPI Summary Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-muted-foreground">Tổng người dùng</span>
                        <Users className="h-4 w-4 text-primary" />
                    </div>
                    <p className="mt-2 font-display text-2xl font-bold text-ink">{summary.total_users}</p>
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        {summary.total_customers} khách hàng · {summary.approved_hosts + summary.pending_hosts} đối tác
                    </p>
                </div>

                <button
                    type="button"
                    onClick={() => handleTabChange('PENDING_HOST')}
                    className={`rounded-2xl border p-5 text-left transition-all shadow-xs ${
                        summary.pending_hosts > 0
                            ? 'border-amber-500/50 bg-amber-50/70 hover:bg-amber-50'
                            : 'border-border/70 bg-card hover:border-primary/40'
                    }`}
                >
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-amber-900">Đối tác chờ duyệt</span>
                        <Clock className="h-4 w-4 text-amber-700" />
                    </div>
                    <p className="mt-2 font-display text-2xl font-bold text-amber-900">{summary.pending_hosts}</p>
                    <p className="mt-1 text-[11px] text-amber-800/90">
                        Đã đồng ý hoa hồng 10% · Chờ mở khóa
                    </p>
                </button>

                <div className="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-muted-foreground">Đối tác đã mở khóa</span>
                        <Building2 className="h-4 w-4 text-emerald-700" />
                    </div>
                    <p className="mt-2 font-display text-2xl font-bold text-emerald-800">{summary.approved_hosts}</p>
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        Được phép đăng cơ sở & nhận đặt phòng
                    </p>
                </div>

                <div className="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-muted-foreground">Trạng thái tài khoản</span>
                        <UserCheck className="h-4 w-4 text-primary" />
                    </div>
                    <p className="mt-2 font-display text-2xl font-bold text-ink">{summary.active_users} hoạt động</p>
                    <p className="mt-1 text-[11px] text-destructive">
                        {summary.blocked_users} tài khoản đang bị khóa
                    </p>
                </div>
            </div>

            {/* Alerts */}
            {successMessage && (
                <div role="status" className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex items-center gap-3 text-emerald-900">
                    <CheckCircle2 className="h-5 w-5 text-emerald-700 shrink-0" />
                    <p className="text-xs font-medium">{successMessage}</p>
                </div>
            )}

            {error && (
                <div role="alert" className="rounded-2xl border border-destructive/30 bg-destructive/10 p-4 flex items-center gap-3 text-destructive">
                    <AlertTriangle className="h-5 w-5 shrink-0" />
                    <p className="text-xs font-medium">{error}</p>
                </div>
            )}

            {/* Filter Tabs & Search Bar */}
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-border/70 pb-4">
                <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Lọc người dùng theo nhóm">
                    {FILTER_TABS.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={activeTab === tab.id}
                            onClick={() => handleTabChange(tab.id)}
                            className={`rounded-xl px-3.5 py-2 text-xs font-semibold transition-all inline-flex items-center gap-1.5 ${
                                activeTab === tab.id
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'border border-border/70 bg-card text-muted-foreground hover:text-ink'
                            }`}
                        >
                            <span>{tab.label}</span>
                            {tab.id === 'PENDING_HOST' && summary.pending_hosts > 0 && (
                                <span className={`rounded-full px-1.5 py-0.2 text-[10px] font-bold ${
                                    activeTab === tab.id ? 'bg-white text-primary' : 'bg-amber-500/20 text-amber-900'
                                }`}>
                                    {summary.pending_hosts}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2">
                    <select
                        aria-label="Lọc trạng thái tài khoản"
                        value={statusFilter}
                        onChange={(e) => {
                            setStatusFilter(e.target.value);
                            setPage(1);
                        }}
                        className="h-9 rounded-xl border border-border/80 bg-card px-3 text-xs text-ink focus:border-primary focus:outline-none"
                    >
                        <option value="ALL">Mọi trạng thái</option>
                        <option value="ACTIVE">Đang hoạt động</option>
                        <option value="BLOCKED">Đã khóa</option>
                    </select>

                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                        <input
                            type="search"
                            value={keywordInput}
                            onChange={(e) => setKeywordInput(e.target.value)}
                            placeholder="Tìm tên, email, SĐT..."
                            className="h-9 w-56 rounded-xl border border-border/80 bg-card pl-8 pr-3 text-xs text-ink focus:border-primary focus:outline-none"
                        />
                    </div>
                    <Button type="submit" size="sm" className="h-9 rounded-xl text-xs font-semibold">
                        Tìm kiếm
                    </Button>
                </form>
            </div>

            {/* Users Table */}
            <div className="rounded-2xl border border-border/70 bg-card shadow-xs overflow-hidden">
                {isLoading ? (
                    <div className="flex items-center justify-center py-16">
                        <Loader2 className="h-6 w-6 animate-spin text-primary mr-2" />
                        <span className="text-xs text-muted-foreground">Đang tải danh sách người dùng...</span>
                    </div>
                ) : users.length === 0 ? (
                    <div className="py-16 text-center px-4">
                        <UserX className="h-10 w-10 text-muted-foreground/40 mx-auto mb-2" />
                        <p className="text-sm font-semibold text-ink">Không tìm thấy người dùng phù hợp</p>
                        <p className="text-xs text-muted-foreground mt-1">
                            Hãy thử thay đổi bộ lọc vai trò hoặc từ khóa tìm kiếm.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-border/60 bg-surface/40 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    <th className="py-3.5 px-4">Người dùng</th>
                                    <th className="py-3.5 px-4">Vai trò</th>
                                    <th className="py-3.5 px-4">Phê duyệt Đối tác</th>
                                    <th className="py-3.5 px-4">Hoạt động</th>
                                    <th className="py-3.5 px-4">Trạng thái</th>
                                    <th className="py-3.5 px-4">Ngày tạo</th>
                                    <th className="py-3.5 px-4 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/50 text-xs">
                                {users.map((u) => {
                                    const isPendingHost = u.role === 'HOST' && !u.is_host_approved;
                                    const isBusy = actingUserId === u.id;
                                    return (
                                        <tr
                                            key={u.id}
                                            className={`transition-colors hover:bg-surface/30 ${
                                                isPendingHost ? 'bg-amber-50/35' : ''
                                            }`}
                                        >
                                            <td className="py-3.5 px-4">
                                                <div className="font-semibold text-ink">{u.name}</div>
                                                <div className="text-[11px] text-muted-foreground">{u.email}</div>
                                                {u.phone && (
                                                    <div className="text-[11px] text-muted-foreground">SĐT: {u.phone}</div>
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4">{renderRoleBadge(u.role)}</td>
                                            <td className="py-3.5 px-4">{renderHostApprovalBadge(u)}</td>
                                            <td className="py-3.5 px-4 text-[11.5px] text-muted-foreground">
                                                {u.role === 'HOST'
                                                    ? `${u.properties_count} cơ sở lưu trú`
                                                    : u.role === 'CUSTOMER'
                                                        ? `${u.bookings_count} đơn đặt phòng`
                                                        : 'Toàn hệ thống'}
                                            </td>
                                            <td className="py-3.5 px-4">{renderStatusBadge(u.status)}</td>
                                            <td className="py-3.5 px-4 text-muted-foreground">
                                                {formatDateTime(u.created_at)}
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <div className="inline-flex items-center justify-end gap-2">
                                                    {isPendingHost && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            disabled={isBusy}
                                                            onClick={() => handleApproveHost(u)}
                                                            className="rounded-xl text-xs font-semibold h-8 px-3 bg-primary text-primary-foreground"
                                                        >
                                                            {isBusy ? (
                                                                <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" />
                                                            ) : (
                                                                <ShieldCheck className="h-3.5 w-3.5 mr-1" />
                                                            )}
                                                            Phê duyệt Đối tác
                                                        </Button>
                                                    )}

                                                    {u.role !== 'ADMIN' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={isBusy}
                                                            onClick={() => handleToggleStatus(u)}
                                                            className={`rounded-xl text-xs font-semibold h-8 px-3 ${
                                                                u.status === 'BLOCKED'
                                                                    ? 'border-emerald-500/40 text-emerald-800 hover:bg-emerald-50'
                                                                    : 'border-destructive/40 text-destructive hover:bg-destructive/10'
                                                            }`}
                                                        >
                                                            {u.status === 'BLOCKED' ? (
                                                                <>
                                                                    <Unlock className="h-3.5 w-3.5 mr-1" />
                                                                    Mở khóa
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Lock className="h-3.5 w-3.5 mr-1" />
                                                                    Khóa
                                                                </>
                                                            )}
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination Footer */}
                {meta.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border/60 bg-surface/20 px-4 py-3 text-xs">
                        <span className="text-muted-foreground">
                            Trang <strong>{meta.current_page}</strong> / <strong>{meta.last_page}</strong> ({meta.total} tài khoản)
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={meta.current_page <= 1 || isLoading}
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                                className="h-8 rounded-xl text-xs"
                            >
                                Trang trước
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={meta.current_page >= meta.last_page || isLoading}
                                onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                                className="h-8 rounded-xl text-xs"
                            >
                                Trang sau
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
