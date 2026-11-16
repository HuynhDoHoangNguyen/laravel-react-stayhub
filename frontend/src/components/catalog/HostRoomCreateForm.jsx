import { useEffect, useRef, useState } from 'react';
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
    403: 'Bạn không có quyền thêm phòng.',
    404: 'Không tìm thấy cơ sở lưu trú.',
    409: 'Cơ sở đã có phòng cùng số.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại.',
    422: 'Vui lòng kiểm tra thông tin đã nhập.',
};

export default function HostRoomCreateForm({ propertyId, onCreated, onCancel, onCheck, attempt, ready }) {
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
            const room = await hostRoomService.create(propertyId, payload, { signal: controller.signal });
            if (!controller.signal.aborted) onCreated(room);
        } catch (failure) {
            if (controller.signal.aborted) return;
            const status = failure.response?.status ?? failure.status;
            setErrors(status === 422 ? failure.response?.data?.errors ?? {} : {});
            setUncertain(Boolean(failure.outcomeUnknown));
            setCheckAttempt(null);
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả thêm phòng. Kiểm tra danh sách trước khi quyết định gửi lại.'
                : messages[status] || 'Không thể thêm phòng. Vui lòng thử lại.');
        } finally {
            request.current = null;
            if (!controller.signal.aborted) setPending(false);
        }
    }

    const inputClass = 'mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2';
    const buttonClass = 'rounded-xl border border-border px-4 py-2 text-primary disabled:opacity-50';
    return <form onSubmit={submit} className="space-y-4 rounded-xl border border-border p-4">
        <h3 className="font-semibold">Thêm phòng</h3>
        {types.status === 'loading' && <p role="status">Đang tải loại phòng...</p>}
        {types.status === 'error' && <div><p role="alert">Không thể tải loại phòng.</p>
            <button type="button" onClick={reloadTypes} className={buttonClass}>Tải lại loại phòng</button></div>}
        {types.status === 'ready' && types.items.length === 0 && <p role="status">
            Cơ sở chưa có loại phòng. <a href="#host-room-types-title" className="text-primary underline">Thêm loại phòng trước</a>
            <button type="button" onClick={reloadTypes} className={'ml-3 ' + buttonClass}>Tải lại loại phòng</button>
        </p>}
        <fieldset disabled={pending || uncertain} className="grid min-w-0 gap-4 sm:grid-cols-2">
            {fields.map(({ name, label, type = 'text', ...attributes }) => <div key={name} className="min-w-0">
                <label htmlFor={'create-room-' + name}>{label}</label>
                <input id={'create-room-' + name} name={name} type={type} required {...attributes}
                    ref={name === 'room_number' ? firstInput : undefined} className={inputClass}
                    aria-invalid={Boolean(errors[name])} aria-describedby={errors[name] ? 'create-room-' + name + '-error' : undefined} />
                {errors[name] && <p id={'create-room-' + name + '-error'} className="mt-1 text-sm text-destructive">{errors[name].join(' ')}</p>}
            </div>)}
            <div className="min-w-0">
                <label htmlFor="create-room-type">Loại phòng</label>
                <select id="create-room-type" name="room_type_id" required className={inputClass}
                    disabled={types.status !== 'ready'} aria-invalid={Boolean(errors.room_type_id)}
                    aria-describedby={errors.room_type_id ? 'create-room-type-error' : undefined}>
                    <option value="">Chọn loại phòng</option>
                    {types.status === 'ready' && types.items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                {errors.room_type_id && <p id="create-room-type-error" className="mt-1 text-sm text-destructive">{errors.room_type_id.join(' ')}</p>}
            </div>
            <div className="min-w-0 sm:col-span-2">
                <label htmlFor="create-room-description">Mô tả</label>
                <textarea id="create-room-description" name="description" maxLength={10000} rows={3} className={inputClass}
                    aria-invalid={Boolean(errors.description)} aria-describedby={errors.description ? 'create-room-description-error' : undefined} />
                {errors.description && <p id="create-room-description-error" className="mt-1 text-sm text-destructive">{errors.description.join(' ')}</p>}
            </div>
            <button type="submit" disabled={!ready || types.status !== 'ready' || types.items.length === 0}
                className={buttonClass}>{pending ? 'Đang lưu phòng...' : 'Lưu phòng'}</button>
        </fieldset>
        {error && <p role="alert">{error}</p>}
        <div className="flex flex-wrap gap-3">
            {uncertain && <button type="button" onClick={() => { setCheckAttempt(attempt + 1); onCheck(); }}
                className={buttonClass}>Kiểm tra danh sách</button>}
            {uncertain && checkAttempt !== null && attempt >= checkAttempt && ready && <button type="button"
                onClick={() => { setUncertain(false); setError(''); }} className={buttonClass}>Cho phép gửi lại</button>}
            <button type="button" disabled={pending} onClick={onCancel} className={buttonClass}>Hủy</button>
        </div>
    </form>;
}
