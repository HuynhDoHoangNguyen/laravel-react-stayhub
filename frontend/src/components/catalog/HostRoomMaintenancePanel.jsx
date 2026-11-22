import { useEffect, useRef, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react';
import { hostRoomMaintenanceService } from '../../services/hostRoomMaintenanceService';

const statuses = {
    SCHEDULED: { label: 'Đã lên lịch', color: 'border-sky-200 bg-sky-50 text-sky-900' },
    IN_PROGRESS: { label: 'Đang thực hiện', color: 'border-amber-200 bg-amber-50 text-amber-900' },
    COMPLETED: { label: 'Hoàn tất', color: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
    CANCELLED: { label: 'Đã hủy', color: 'border-border bg-surface text-muted-foreground' },
};
const errors = {
    400: 'Không tìm thấy phòng.',
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền xem lịch bảo trì của phòng này.',
    404: 'Không tìm thấy phòng.',
    422: 'Trang lịch bảo trì không hợp lệ.',
};
const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-2 text-sm font-semibold text-primary shadow-sm transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';

function formatDate(value) {
    if (/^[1-9]\d{3}-\d{2}-\d{2}$/.test(value)) {
        const date = new Date(value + 'T00:00:00Z');
        if (!Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value) {
            const [year, month, day] = value.split('-');
            return day + '/' + month + '/' + year;
        }
    }
    return 'Ngày chưa hợp lệ';
}

export default function HostRoomMaintenancePanel({ roomId }) {
    const [page, setPage] = useState(1);
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading' });
    const title = useRef(null);

    useEffect(() => {
        const controller = new AbortController();
        hostRoomMaintenanceService.list(roomId, page, { signal: controller.signal }).then(
            (result) => {
                if (!controller.signal.aborted) setState({ status: 'ready', result });
            },
            (error) => {
                if (controller.signal.aborted) return;
                const status = error.response?.status ?? error.status;
                setState({ status: 'error', message: errors[status] || 'Không thể tải lịch bảo trì. Vui lòng thử lại.' });
            },
        );
        return () => controller.abort();
    }, [roomId, page, attempt]);

    function goToPage(nextPage) {
        title.current?.focus();
        setState({ status: 'loading' });
        setPage(nextPage);
    }

    function retry() {
        title.current?.focus();
        setState({ status: 'loading' });
        setAttempt((value) => value + 1);
    }

    const result = state.result;
    return <section aria-labelledby="room-maintenance-title"
        className="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
        <header className="flex flex-wrap items-center justify-between gap-3">
            <h2 ref={title} tabIndex={-1} id="room-maintenance-title"
                className="flex items-center gap-2 rounded-sm text-lg font-semibold text-primary focus-visible:ring-2 focus-visible:ring-primary">
                <CalendarDays size={19} className="shrink-0" aria-hidden="true" />
                Lịch bảo trì
            </h2>
            {result && <span className="rounded-full bg-surface px-3 py-1 text-sm text-muted-foreground">{result.meta.total} lịch</span>}
        </header>
        {state.status === 'loading' && <p role="status" className="text-sm text-muted-foreground">Đang tải lịch bảo trì...</p>}
        {state.status === 'error' && <div className="space-y-3 rounded-xl border border-red-200 bg-red-50 p-4">
            <p role="alert" className="break-words text-sm text-red-800">{state.message}</p>
            <button type="button" className={buttonClass} onClick={retry}>
                <RefreshCw size={16} aria-hidden="true" />
                Thử tải lại lịch
            </button>
        </div>}
        {result?.data.length === 0 && <p className="rounded-xl border border-dashed border-border bg-surface/40 p-4 text-sm text-muted-foreground">
            {result.meta.total === 0 ? 'Phòng chưa có lịch bảo trì.' : 'Trang này không có lịch bảo trì.'}
        </p>}
        {result?.data.length > 0 && <ul className="space-y-3">
            {result.data.map((item) => {
                const status = statuses[item.status];
                return <li key={item.id} className="min-w-0 space-y-2 rounded-xl border border-border bg-surface/40 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-semibold text-primary">{formatDate(item.start_date)} – {formatDate(item.end_date)}</p>
                        <span className={'rounded-full border px-2.5 py-1 text-xs font-medium ' + status.color}>{status.label}</span>
                    </div>
                    <p className="whitespace-pre-wrap break-words text-sm text-muted-foreground">{item.reason?.trim() || 'Chưa có lý do.'}</p>
                </li>;
            })}
        </ul>}
        {result && (result.meta.last_page > 1 || page > 1) && <nav aria-label="Phân trang lịch bảo trì" className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
            <p className="text-sm text-muted-foreground" role="status">Trang {result.meta.current_page} / {result.meta.last_page}</p>
            <div className="flex flex-wrap gap-2">
                <button type="button" className={buttonClass} disabled={page <= 1}
                    onClick={() => goToPage(Math.min(page - 1, result.meta.last_page))}>
                    <ChevronLeft size={16} aria-hidden="true" />
                    Trang trước
                </button>
                <button type="button" className={buttonClass} disabled={page >= result.meta.last_page}
                    onClick={() => goToPage(page + 1)}>
                    Trang sau
                    <ChevronRight size={16} aria-hidden="true" />
                </button>
            </div>
        </nav>}
    </section>;
}
