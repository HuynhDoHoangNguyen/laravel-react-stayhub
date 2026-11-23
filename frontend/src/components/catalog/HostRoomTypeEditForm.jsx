import { useEffect, useRef, useState } from 'react';
import { Pencil, Save } from 'lucide-react';
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

    const inputClass = 'mt-2 block min-h-11 w-full rounded-xl border border-border bg-background px-4 py-3 text-sm shadow-sm transition-colors focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/15 aria-invalid:border-red-400 aria-invalid:bg-red-50/30';
    const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition hover:bg-surface hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
    const saveClass = 'inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none sm:w-auto';

    return <form onSubmit={submit} aria-labelledby="room-type-form-title" aria-busy={pending} className="min-w-0 space-y-5 rounded-2xl border border-primary/20 bg-surface/40 p-4 shadow-sm sm:p-5">
        <h3 id="room-type-form-title" className="flex items-center gap-2 text-base font-semibold text-primary"><Pencil size={18} aria-hidden="true" />Chỉnh sửa loại phòng</h3>
        <fieldset disabled={pending || uncertain} className="min-w-0 space-y-4">
            <div>
                <label htmlFor="room-type-name" className="text-sm font-medium text-foreground">Tên loại phòng <span className="text-red-700" aria-hidden="true">*</span></label>
                <input ref={nameInput} id="room-type-name" name="name" defaultValue={item.name} required maxLength={255} className={inputClass}
                    aria-invalid={Boolean(fields.name)} aria-describedby={fields.name ? 'room-type-name-error' : undefined} />
                {fields.name && <p id="room-type-name-error" className="mt-2 text-sm text-red-700">{fields.name.join(' ')}</p>}
            </div>
            <div>
                <label htmlFor="room-type-description" className="text-sm font-medium text-foreground">Mô tả <span className="font-normal text-muted-foreground">(không bắt buộc)</span></label>
                <textarea id="room-type-description" name="description" defaultValue={item.description ?? ''} maxLength={10000} rows={4} className={inputClass}
                    aria-invalid={Boolean(fields.description)} aria-describedby={fields.description ? 'room-type-description-error' : undefined} />
                {fields.description && <p id="room-type-description-error" className="mt-2 text-sm text-red-700">{fields.description.join(' ')}</p>}
            </div>
            <button type="submit" className={saveClass}><Save size={16} aria-hidden="true" />{pending ? 'Đang lưu...' : 'Lưu thay đổi'}</button>
        </fieldset>
        {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800">{error}</p>}
        <div className="flex flex-wrap gap-2 border-t border-border pt-4">
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
