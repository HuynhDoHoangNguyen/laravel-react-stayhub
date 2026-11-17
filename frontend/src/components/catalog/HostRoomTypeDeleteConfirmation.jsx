import { useEffect, useRef, useState } from 'react';
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

    const buttonClass = 'rounded-xl border border-border px-4 py-2 disabled:opacity-60';

    return <div role="group" aria-labelledby="room-type-delete-title" className="space-y-3 rounded-xl border border-border p-4">
        <p id="room-type-delete-title" className="break-words">Xóa vĩnh viễn loại phòng <strong>{item.name}</strong>?</p>
        {error && <p role="alert">{error}</p>}
        {checked && !stillPresent && <p role="status">Loại phòng không còn trong danh sách.</p>}
        <div className="flex flex-wrap gap-3">
            {!mustCheck && <button type="button" disabled={pending} className={buttonClass + ' text-red-700'} onClick={confirm}>
                {pending ? 'Đang xóa...' : 'Xác nhận xóa'}
            </button>}
            {mustCheck && !checked && <button type="button" className={buttonClass} onClick={() => {
                setCheckAttempt(attempt + 1);
                onCheck();
            }}>Kiểm tra danh sách</button>}
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
