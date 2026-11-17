import { useEffect, useRef, useState } from 'react';
import { hostPropertyService } from '../../services/hostPropertyService';

const messages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền cập nhật loại phòng.',
    404: 'Không tìm thấy loại phòng.',
    409: 'Cơ sở đã có loại phòng cùng tên.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại.',
    422: 'Vui lòng kiểm tra thông tin đã nhập.',
};

export default function HostRoomTypeEditForm({ item, onUpdated, onCancel, onCheck, attempt, ready }) {
    const request = useRef(null);
    const nameInput = useRef(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    const [fields, setFields] = useState({});
    const [uncertain, setUncertain] = useState(false);
    const [checkAttempt, setCheckAttempt] = useState(null);

    useEffect(() => {
        nameInput.current?.focus();
        return () => request.current?.abort();
    }, []);

    async function submit(event) {
        event.preventDefault();
        if (request.current || uncertain) return;
        const controller = new AbortController();
        request.current = controller;
        const values = Object.fromEntries(new FormData(event.currentTarget));
        setPending(true);
        setError('');
        setFields({});
        try {
            await hostPropertyService.updateRoomType(item.id, values, { signal: controller.signal });
            if (!controller.signal.aborted) onUpdated();
        } catch (failure) {
            if (controller.signal.aborted) return;
            const status = failure.response?.status ?? failure.status;
            setFields(status === 422 ? failure.response.data?.errors ?? {} : {});
            setUncertain(Boolean(failure.outcomeUnknown));
            setCheckAttempt(null);
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả. Kiểm tra danh sách trước khi quyết định gửi lại.'
                : messages[status] || 'Không thể cập nhật loại phòng. Vui lòng thử lại.');
        } finally {
            request.current = null;
            if (!controller.signal.aborted) setPending(false);
        }
    }

    const inputClass = 'mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2';
    const buttonClass = 'rounded-xl border border-border px-4 py-2 text-primary disabled:opacity-60';

    return <form onSubmit={submit} className="space-y-4 rounded-xl border border-border p-4">
        <fieldset disabled={pending || uncertain} className="min-w-0 space-y-4">
            <div>
                <label htmlFor="room-type-name">Tên loại phòng</label>
                <input ref={nameInput} id="room-type-name" name="name" defaultValue={item.name} required maxLength={255} className={inputClass}
                    aria-invalid={Boolean(fields.name)} aria-describedby={fields.name ? 'room-type-name-error' : undefined} />
                {fields.name && <p id="room-type-name-error">{fields.name.join(' ')}</p>}
            </div>
            <div>
                <label htmlFor="room-type-description">Mô tả</label>
                <textarea id="room-type-description" name="description" defaultValue={item.description ?? ''} maxLength={10000} rows={4} className={inputClass}
                    aria-invalid={Boolean(fields.description)} aria-describedby={fields.description ? 'room-type-description-error' : undefined} />
                {fields.description && <p id="room-type-description-error">{fields.description.join(' ')}</p>}
            </div>
            <button type="submit" className={buttonClass}>{pending ? 'Đang lưu...' : 'Lưu thay đổi'}</button>
        </fieldset>
        {error && <p role="alert">{error}</p>}
        <div className="flex flex-wrap gap-3">
            {uncertain && <button type="button" className={buttonClass} onClick={() => {
                setCheckAttempt(attempt + 1);
                onCheck();
            }}>Kiểm tra danh sách</button>}
            {uncertain && checkAttempt !== null && attempt >= checkAttempt && ready &&
                <button type="button" className={buttonClass} onClick={() => {
                    setUncertain(false);
                    setError('');
                }}>Cho phép gửi lại</button>}
            <button type="button" disabled={pending} onClick={onCancel} className={buttonClass}>Hủy</button>
        </div>
    </form>;
}
