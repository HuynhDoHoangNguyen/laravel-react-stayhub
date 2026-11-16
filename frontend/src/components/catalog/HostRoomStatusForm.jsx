import { useEffect, useRef, useState } from 'react';
import { hostRoomService } from '../../services/hostRoomService';

const labels = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngưng hoạt động', MAINTENANCE: 'Đang bảo trì' };
const messages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền đổi trạng thái phòng.',
    404: 'Không tìm thấy phòng.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại.',
    422: 'Vui lòng kiểm tra trạng thái đã chọn.',
};

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

    const buttonClass = 'rounded-xl border border-border px-4 py-2 text-primary disabled:opacity-50';
    return <form onSubmit={submit} className="space-y-4 rounded-xl border border-border p-4">
        <h2 className="text-xl font-semibold">Đổi trạng thái phòng</h2>
        <p>Trạng thái hiện tại: {labels[room.status]}</p>
        <fieldset disabled={pending || uncertain} className="min-w-0 space-y-4">
            <div>
                <label htmlFor="room-status-target">Trạng thái mới</label>
                <select ref={select} id="room-status-target" name="status" defaultValue={room.status} required
                    aria-invalid={Boolean(fieldError)} aria-describedby={fieldError ? 'room-status-error' : undefined}
                    className="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2">
                    {Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
                {fieldError && <p id="room-status-error" className="mt-1 text-sm text-destructive">{fieldError}</p>}
            </div>
            <button type="submit" disabled={!ready} className={buttonClass}>
                {pending ? 'Đang đổi trạng thái...' : 'Xác nhận đổi trạng thái'}
            </button>
        </fieldset>
        {error && <p role="alert">{error}</p>}
        <div className="flex flex-wrap gap-3">
            {uncertain && <button type="button" className={buttonClass}
                onClick={() => { setCheckAttempt(attempt + 1); onCheck(); }}>Kiểm tra chi tiết</button>}
            {uncertain && checkAttempt !== null && attempt >= checkAttempt && ready && <button type="button" className={buttonClass}
                onClick={() => { setUncertain(false); setError(''); }}>Cho phép gửi lại</button>}
            <button type="button" disabled={pending} className={buttonClass} onClick={onCancel}>Hủy</button>
        </div>
    </form>;
}
