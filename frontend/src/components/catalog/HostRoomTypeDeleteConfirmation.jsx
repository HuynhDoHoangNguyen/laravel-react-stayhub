import { useEffect, useRef, useState } from 'react';
import { CheckCircle2, RefreshCw, Trash2, TriangleAlert } from 'lucide-react';
import { hostPropertyService } from '../../services/hostPropertyService';

const messages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền xóa loại phòng.',
    404: 'Không tìm thấy loại phòng. Vui lòng kiểm tra lại danh sách.',
    409: 'Loại phòng đang được sử dụng và không thể xóa. Hãy kiểm tra các phòng đang dùng loại này.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng xác nhận lại.',
    422: 'Yêu cầu xóa loại phòng không hợp lệ.',
};

export default function HostRoomTypeDeleteConfirmation({ item, onDeleted, onCancel, onCheck, attempt, items }) {
    const cancelButton = useRef(null);
    const request = useRef(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    const [mustCheck, setMustCheck] = useState(false);
    const [checkAttempt, setCheckAttempt] = useState(null);
    const checked = checkAttempt !== null && attempt >= checkAttempt && Array.isArray(items);
    const stillPresent = checked && items.some(({ id }) => id === item.id);

    useEffect(() => {
        cancelButton.current?.focus();
        return () => request.current?.abort();
    }, []);

    async function confirm() {
        if (request.current || mustCheck) return;
        const controller = new AbortController();
        request.current = controller;
        setPending(true);
        setError('');
        try {
            await hostPropertyService.deleteRoomType(item.id, { signal: controller.signal });
            if (!controller.signal.aborted) onDeleted();
        } catch (failure) {
            if (controller.signal.aborted) return;
            const status = failure.response?.status ?? failure.status;
            setMustCheck(Boolean(failure.outcomeUnknown) || status === 404);
            setCheckAttempt(null);
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả xóa. Vui lòng kiểm tra danh sách.'
                : messages[status] || 'Không thể xóa loại phòng. Vui lòng thử lại.');
        } finally {
            request.current = null;
            if (!controller.signal.aborted) setPending(false);
        }
    }

    const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition hover:bg-surface hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
    const deleteClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-red-700 bg-red-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';

    return <div role="group" aria-labelledby="room-type-delete-title" aria-busy={pending} className="min-w-0 space-y-4 rounded-2xl border border-red-200 bg-red-50/60 p-4 shadow-sm sm:p-5">
        <div className="flex items-start gap-3"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700"><TriangleAlert size={20} aria-hidden="true" /></span>
            <div className="min-w-0"><h3 id="room-type-delete-title" className="text-base font-semibold text-red-900">Xác nhận xóa loại phòng</h3><p className="mt-1 break-words text-sm leading-6 text-red-900">Xóa vĩnh viễn loại phòng <strong>{item.name}</strong>?</p>
                <p className="mt-1 text-sm leading-6 text-muted-foreground">Chỉ có thể xóa loại phòng chưa được phòng nào sử dụng.</p></div></div>
        {error && <p role="alert" className="rounded-xl border border-red-200 bg-card p-3 text-sm leading-6 text-red-800">{error}</p>}
        {checked && !stillPresent && <p role="status" className="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm leading-6 text-emerald-800"><CheckCircle2 size={17} className="mt-0.5 shrink-0" aria-hidden="true" />Loại phòng không còn trong danh sách.</p>}
        <div className="flex flex-wrap gap-2 border-t border-red-200/70 pt-4">
            {!mustCheck && <button type="button" disabled={pending} className={deleteClass} onClick={confirm}>
                <Trash2 size={16} aria-hidden="true" />{pending ? 'Đang xóa...' : 'Xác nhận xóa'}
            </button>}
            {mustCheck && !checked && <button type="button" className={buttonClass} onClick={() => {
                setCheckAttempt(attempt + 1);
                onCheck();
            }}><RefreshCw size={16} aria-hidden="true" />Kiểm tra danh sách</button>}
            {stillPresent && <button type="button" className={buttonClass} onClick={() => {
                setMustCheck(false);
                setCheckAttempt(null);
                setError('');
            }}>Cho phép xác nhận lại</button>}
            <button ref={cancelButton} type="button" disabled={pending} className={buttonClass} onClick={onCancel}>
                {checked && !stillPresent ? 'Đóng' : 'Hủy xóa'}
            </button>
        </div>
    </div>;
}
