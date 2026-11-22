import { useEffect, useRef, useState } from 'react';
import { Check, RefreshCw } from 'lucide-react';
import { hostRoomService } from '../../services/hostRoomService';

const labels = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngưng hoạt động', MAINTENANCE: 'Đang bảo trì' };
const messages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền đổi trạng thái phòng.',
    404: 'Không tìm thấy phòng.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại.',
    422: 'Vui lòng kiểm tra trạng thái đã chọn.',
};

const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
const secondaryButton = buttonClass + ' border border-border bg-card text-primary hover:bg-surface';

export default function HostRoomStatusForm({ room, ready, attempt, onCancel, onCheck, onChanged }) {
    const request = useRef(null);
    const select = useRef(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    const [fieldError, setFieldError] = useState('');
    const [uncertain, setUncertain] = useState(false);
    const [checkAttempt, setCheckAttempt] = useState(null);

    useEffect(() => {
        select.current?.focus();
        return () => request.current?.abort();
    }, []);

    async function submit(event) {
        event.preventDefault();
        if (request.current || uncertain || !ready) return;
        const controller = new AbortController();
        request.current = controller;
        const status = new FormData(event.currentTarget).get('status');
        setPending(true);
        setError('');
        setFieldError('');
        try {
            await hostRoomService.changeStatus(room.id, { status }, { signal: controller.signal });
            if (!controller.signal.aborted) onChanged();
        } catch (failure) {
            if (controller.signal.aborted) return;
            const statusCode = failure.response?.status ?? failure.status;
            setUncertain(Boolean(failure.outcomeUnknown));
            setCheckAttempt(null);
            setFieldError(statusCode === 422 ? failure.response?.data?.errors?.status?.join(' ') ?? '' : '');
            const conflict = failure.response?.data?.message;
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả đổi trạng thái. Kiểm tra chi tiết trước khi quyết định gửi lại.'
                : statusCode === 409 && typeof conflict === 'string' ? conflict
                    : messages[statusCode] || 'Không thể đổi trạng thái phòng. Vui lòng thử lại.');
        } finally {
            request.current = null;
            if (!controller.signal.aborted) setPending(false);
        }
    }

    const canResubmit = uncertain && checkAttempt !== null && attempt >= checkAttempt && ready;

    return <form onSubmit={submit} aria-labelledby="room-status-title" aria-busy={pending}
        className="min-w-0 space-y-5 rounded-2xl border border-primary/20 bg-surface/40 p-4 shadow-sm sm:p-6">
        <h2 id="room-status-title" className="flex items-center gap-2 text-lg font-semibold text-primary">
            <RefreshCw size={18} aria-hidden="true" />
            Đổi trạng thái phòng
        </h2>
        {error && <p role="alert" className="break-words rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800">{error}</p>}
        <fieldset disabled={pending || uncertain} className="grid min-w-0 items-start gap-4 sm:grid-cols-2">
            <dl className="rounded-xl border border-border/70 bg-card/60 p-3.5">
                <dt className="text-sm text-muted-foreground">Trạng thái hiện tại</dt>
                <dd className="mt-1 font-semibold text-primary">{labels[room.status]}</dd>
            </dl>
            <div className="min-w-0">
                <label htmlFor="room-status-target" className="text-sm font-medium">Trạng thái mới</label>
                <select ref={select} id="room-status-target" name="status" defaultValue={room.status} required
                    aria-invalid={Boolean(fieldError)} aria-describedby={fieldError ? 'room-status-error' : undefined}
                    className="mt-2 block min-h-11 w-full min-w-0 rounded-xl border border-border bg-background px-3 py-2.5 text-sm shadow-sm focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/20 aria-invalid:border-red-400 disabled:opacity-60">
                    {Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
                {fieldError && <p id="room-status-error" className="mt-2 break-words text-sm text-destructive">{fieldError}</p>}
            </div>
        </fieldset>
        <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
            <button type="submit" disabled={!ready || pending || uncertain}
                className={buttonClass + ' bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-md'}>
                <Check size={16} aria-hidden="true" />
                {pending ? 'Đang đổi trạng thái...' : 'Xác nhận đổi trạng thái'}
            </button>
            <button type="button" disabled={pending} className={secondaryButton} onClick={onCancel}>Hủy</button>
            {uncertain && <button type="button" className={secondaryButton}
                onClick={() => { setCheckAttempt(attempt + 1); onCheck(); }}>Kiểm tra chi tiết</button>}
            {canResubmit && <button type="button" className={secondaryButton}
                onClick={() => { setUncertain(false); setError(''); }}>Cho phép gửi lại</button>}
        </div>
    </form>;
}
