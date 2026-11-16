import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import { useAuth } from '../../hooks/useAuth';
import { hostPropertyService } from '../../services/hostPropertyService';

const statuses = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngưng hoạt động', PENDING: 'Chờ duyệt', BLOCKED: 'Đã khóa' };

export default function HostPropertyEditPage() {
    const { propertyId } = useParams();
    const { user } = useAuth();
    return <EditProperty key={user?.id + ':' + propertyId} id={propertyId} />;
}

function EditProperty({ id }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading' });
    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostPropertyService.detail(id, { signal: controller.signal }).then(
            (property) => { if (current) setState({ status: 'ready', property }); },
            (error) => {
                if (!current) return;
                const status = error.response?.status || error.status;
                const messages = { 401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                    403: 'Bạn không có quyền truy cập.', 404: 'Không tìm thấy cơ sở lưu trú.' };
                setState({ status: status === 404 ? 'missing' : 'error',
                    error: messages[status] || 'Không thể tải cơ sở lưu trú. Vui lòng thử lại.' });
            },
        );
        return () => { current = false; controller.abort(); };
    }, [id, attempt]);
    return <main className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-8">
        <Link to="/host/properties" className="text-primary underline">← Về danh sách cơ sở</Link>
        <h1 className="mt-6 font-display text-3xl text-primary">Chỉnh sửa thông tin cơ sở</h1>
        {state.status === 'loading' && <p role="status" className="mt-6">Đang tải cơ sở lưu trú...</p>}
        {['error', 'missing'].includes(state.status) && <div role="alert" className="mt-6">
            <p>{state.error}</p>
            {state.status === 'error' && <button type="button" className="mt-4 text-primary underline" onClick={() => {
                setState({ status: 'loading' }); setAttempt((value) => value + 1);
            }}>Thử lại</button>}
        </div>}
        {state.status === 'ready' && <EditForm property={state.property} />}
    </main>;
}

function EditForm({ property }) {
    const navigate = useNavigate();
    const active = useRef(true);
    const locked = useRef(false);
    const request = useRef(null);
    const [pending, setPending] = useState(false);
    const [uncertain, setUncertain] = useState(false);
    const [error, setError] = useState('');
    const [fields, setFields] = useState({});
    useEffect(() => {
        active.current = true;
        return () => { active.current = false; request.current?.abort(); };
    }, []);

    async function submit(event) {
        event.preventDefault();
        if (locked.current || uncertain) return;
        locked.current = true;
        const data = Object.fromEntries(new FormData(event.currentTarget));
        data.description = data.description || null;
        data.phone = data.phone || null;
        setPending(true); setError(''); setFields({});
        request.current = new AbortController();
        try {
            const result = await hostPropertyService.update(property.id, data, { signal: request.current.signal });
            if (!active.current) return;
            toast.success('Cập nhật cơ sở lưu trú thành công.');
            navigate('/host/properties/' + result.id, { replace: true });
        } catch (failure) {
            if (!active.current) return;
            const status = failure.response?.status || failure.status;
            setUncertain(Boolean(failure.outcomeUnknown));
            setFields(status === 422 ? failure.response.data?.errors || {} : {});
            const messages = { 401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                403: 'Bạn không có quyền cập nhật cơ sở lưu trú.', 404: 'Không tìm thấy cơ sở lưu trú.',
                419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại biểu mẫu.', 422: 'Vui lòng kiểm tra các trường thông tin.' };
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả cập nhật. Vui lòng kiểm tra chi tiết cơ sở trước khi gửi lại.'
                : messages[status] || 'Không thể cập nhật cơ sở lưu trú. Vui lòng thử lại.');
        } finally {
            if (active.current) { setPending(false); locked.current = false; }
        }
    }

    const detailUrl = '/host/properties/' + property.id;
    const control = 'mt-2 w-full rounded-xl border border-border bg-background px-4 py-3';
    const accessibility = (name) => ({ 'aria-invalid': Boolean(fields[name]),
        'aria-describedby': fields[name] ? name + '-error' : undefined });
    const fieldError = (name) => fields[name]
        ? <p id={name + '-error'} className="mt-2 text-sm text-red-700">{fields[name].join(' ')}</p> : null;
    return <>
        <p className="mt-6">Trạng thái: {statuses[property.status]}</p>
        {error && <p role="alert" className="mt-6 rounded-xl border border-border p-4">{error}</p>}
        <form onSubmit={submit} className="mt-8 space-y-6">
            <fieldset disabled={pending || uncertain} className="space-y-6 disabled:opacity-70">
                {[
                    ['name', 'Tên cơ sở', 'text', 255, true], ['address', 'Địa chỉ', 'text', 500, true],
                    ['phone', 'Điện thoại', 'tel', 20, false],
                ].map(([name, label, type, maxLength, required]) => <div key={name}>
                    <label htmlFor={name}>{label}</label>
                    <input id={name} name={name} type={type} maxLength={maxLength} required={required}
                        defaultValue={property[name] ?? ''} {...accessibility(name)} className={control} />
                    {fieldError(name)}
                </div>)}
                <div><label htmlFor="type">Loại cơ sở</label>
                    <select id="type" name="type" defaultValue={property.type} className={control} {...accessibility('type')}>
                        <option value="HOTEL">Khách sạn</option><option value="HOMESTAY">Homestay</option>
                    </select>{fieldError('type')}
                </div>
                <div><label htmlFor="description">Mô tả</label>
                    <textarea id="description" name="description" maxLength={10000} rows={5} className={control}
                        defaultValue={property.description ?? ''} {...accessibility('description')} />{fieldError('description')}
                </div>
                <div className="grid gap-6 sm:grid-cols-2">
                    {[['check_in_time', 'Giờ nhận phòng'], ['check_out_time', 'Giờ trả phòng']].map(([name, label]) => <div key={name}>
                        <label htmlFor={name}>{label}</label>
                        <input id={name} name={name} type="time" defaultValue={property[name].slice(0, 5)} required
                            className={control} {...accessibility(name)} />{fieldError(name)}
                    </div>)}
                </div>
                <button type="submit" className="rounded-xl bg-primary px-6 py-3 text-primary-foreground">
                    {pending ? 'Đang lưu...' : 'Lưu thay đổi'}
                </button>
            </fieldset>
        </form>
        <Link to={detailUrl} className="mt-6 inline-block text-primary underline">{uncertain ? 'Kiểm tra chi tiết cơ sở' : 'Hủy và về chi tiết'}</Link>
    </>;
}
