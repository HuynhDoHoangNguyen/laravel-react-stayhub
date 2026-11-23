import { useEffect, useRef, useState } from 'react';
import { Pencil, RefreshCw, Save } from 'lucide-react';
import { hostPropertyService } from '../../services/hostPropertyService';
import { hostRoomService } from '../../services/hostRoomService';

const fields = [
    { name: 'room_number', label: 'Số phòng', maxLength: 50 },
    { name: 'name', label: 'Tên phòng', maxLength: 255 },
    { name: 'capacity', label: 'Sức chứa', type: 'number', min: 1, max: 2147483647, step: 1 },
    { name: 'price_per_night', label: 'Giá mỗi đêm (VND)', inputMode: 'decimal', pattern: '[0-9]+([.][0-9]{1,2})?' },
];
const messages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền cập nhật phòng.',
    404: 'Không tìm thấy cơ sở lưu trú.',
    409: 'Cơ sở đã có phòng cùng số.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại.',
    422: 'Vui lòng kiểm tra thông tin đã nhập.',
};

const inputClass = 'mt-2 block min-h-11 w-full min-w-0 rounded-xl border border-border bg-background px-3 py-2.5 text-sm shadow-sm focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/20 aria-invalid:border-red-400 disabled:opacity-60';
const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
const secondaryButton = buttonClass + ' border border-border bg-card text-primary hover:bg-surface';

export default function HostRoomEditForm({ room, onUpdated, onCancel, onCheck, attempt, ready }) {
    const propertyId = room.property.id;
    const request = useRef(null);
    const firstInput = useRef(null);
    const [types, setTypes] = useState({ status: 'loading' });
    const [typeAttempt, setTypeAttempt] = useState(0);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    const [errors, setErrors] = useState({});
    const [uncertain, setUncertain] = useState(false);
    const [checkAttempt, setCheckAttempt] = useState(null);

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostPropertyService.listRoomTypes(propertyId, { signal: controller.signal }).then(
            (items) => { if (current) setTypes({ status: 'ready', items }); },
            () => { if (current) setTypes({ status: 'error' }); },
        );
        return () => { current = false; controller.abort(); };
    }, [propertyId, typeAttempt]);

    useEffect(() => {
        firstInput.current?.focus();
        return () => request.current?.abort();
    }, []);

    function reloadTypes() {
        setTypes({ status: 'loading' });
        setTypeAttempt((value) => value + 1);
    }

    async function submit(event) {
        event.preventDefault();
        if (request.current || uncertain || !ready || types.status !== 'ready' || types.items.length === 0) return;
        const controller = new AbortController();
        request.current = controller;
        const payload = Object.fromEntries(new FormData(event.currentTarget));
        setPending(true);
        setError('');
        setErrors({});
        try {
            const updated = await hostRoomService.update(room.id, payload, { signal: controller.signal });
            if (!controller.signal.aborted) onUpdated(updated);
        } catch (failure) {
            if (controller.signal.aborted) return;
            const status = failure.response?.status ?? failure.status;
            setErrors(status === 422 ? failure.response?.data?.errors ?? {} : {});
            setUncertain(Boolean(failure.outcomeUnknown));
            setCheckAttempt(null);
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả cập nhật phòng. Kiểm tra chi tiết trước khi quyết định gửi lại.'
                : messages[status] || 'Không thể cập nhật phòng. Vui lòng thử lại.');
        } finally {
            request.current = null;
            if (!controller.signal.aborted) setPending(false);
        }
    }

    function renderField({ name, label, type = 'text', ...attributes }) {
        const id = 'edit-room-' + name;
        return <div key={name} className="min-w-0">
            <label htmlFor={id} className="text-sm font-medium">{label}</label>
            <input id={id} name={name} type={type} defaultValue={room[name]} required {...attributes}
                ref={name === 'room_number' ? firstInput : undefined} className={inputClass}
                aria-invalid={Boolean(errors[name])} aria-describedby={errors[name] ? id + '-error' : undefined} />
            {errors[name] && <p id={id + '-error'} className="mt-2 break-words text-sm text-destructive">{errors[name].join(' ')}</p>}
        </div>;
    }

    const noTypes = types.status === 'ready' && types.items.length === 0;
    const canSubmit = ready && types.status === 'ready' && !noTypes && !pending && !uncertain;
    const canResubmit = uncertain && checkAttempt !== null && attempt >= checkAttempt && ready;

    return <form onSubmit={submit} aria-labelledby="edit-room-title" aria-busy={pending}
        className="min-w-0 space-y-5 rounded-2xl border border-primary/20 bg-surface/40 p-4 shadow-sm sm:p-6">
        <h2 id="edit-room-title" className="flex items-center gap-2 text-lg font-semibold text-primary">
            <Pencil size={18} aria-hidden="true" />
            Sửa thông tin phòng
        </h2>
        {error && <p role="alert" className="break-words rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800">{error}</p>}
        <fieldset disabled={pending || uncertain} className="min-w-0 space-y-5">
            <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                {fields.slice(0, 2).map(renderField)}
            </div>
            <div className="min-w-0">
                <label htmlFor="edit-room-type" className="text-sm font-medium">Loại phòng</label>
                <select key={types.status === 'ready' ? 'loaded' : 'unavailable'} id="edit-room-type" name="room_type_id"
                    defaultValue={types.status === 'ready' ? room.room_type?.id ?? '' : ''} required className={inputClass}
                    disabled={types.status !== 'ready'} aria-invalid={Boolean(errors.room_type_id)}
                    aria-describedby={errors.room_type_id ? 'edit-room-type-error' : undefined}>
                    <option value="">Chọn loại phòng</option>
                    {types.status === 'ready' && types.items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                {errors.room_type_id && <p id="edit-room-type-error" className="mt-2 break-words text-sm text-destructive">{errors.room_type_id.join(' ')}</p>}
                {types.status === 'loading' && <p role="status" className="mt-2 text-sm text-muted-foreground">Đang tải loại phòng...</p>}
                {(types.status === 'error' || noTypes) && <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    {types.status === 'error'
                        ? <p role="alert">Không thể tải loại phòng.</p>
                        : <div className="space-y-1">
                            <p role="status">Cơ sở chưa có loại phòng.</p>
                            <a href={'/host/properties/' + propertyId + '#host-room-types-title'}
                                className="rounded-sm font-medium text-primary underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-primary">
                                Thêm loại phòng trước
                            </a>
                        </div>}
                    <button type="button" onClick={reloadTypes} className={secondaryButton}>
                        <RefreshCw size={16} aria-hidden="true" />
                        Tải lại loại phòng
                    </button>
                </div>}
            </div>
            <div className="grid min-w-0 gap-4 rounded-xl border border-border/70 bg-card/60 p-4 sm:grid-cols-2">
                {fields.slice(2).map(renderField)}
            </div>
            <div className="min-w-0">
                <label htmlFor="edit-room-description" className="text-sm font-medium">
                    Mô tả <span className="font-normal text-muted-foreground">(không bắt buộc)</span>
                </label>
                <textarea id="edit-room-description" name="description" defaultValue={room.description ?? ''} maxLength={10000} rows={3} className={inputClass}
                    aria-invalid={Boolean(errors.description)} aria-describedby={errors.description ? 'edit-room-description-error' : undefined} />
                {errors.description && <p id="edit-room-description-error" className="mt-2 break-words text-sm text-destructive">{errors.description.join(' ')}</p>}
            </div>
        </fieldset>
        <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
            <button type="submit" disabled={!canSubmit}
                className={buttonClass + ' bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-md'}>
                <Save size={16} aria-hidden="true" />
                {pending ? 'Đang lưu phòng...' : 'Lưu phòng'}
            </button>
            <button type="button" disabled={pending} onClick={onCancel} className={secondaryButton}>Hủy</button>
            {uncertain && <button type="button" onClick={() => { setCheckAttempt(attempt + 1); onCheck(); }}
                className={secondaryButton}>Kiểm tra chi tiết</button>}
            {canResubmit && <button type="button"
                onClick={() => { setUncertain(false); setError(''); }} className={secondaryButton}>Cho phép gửi lại</button>}
        </div>
    </form>;
}
