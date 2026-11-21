import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { useAuth } from '../../hooks/useAuth';
import { hostPropertyService } from '../../services/hostPropertyService';
import { hostAgreementService } from '../../services/hostAgreementService';
import { HostPartnerAgreementModal } from '../../components/host/HostPartnerAgreementModal';

export default function HostPropertyCreatePage() {
    const { user } = useAuth();
    return <CreateForm key={user?.id ?? 'guest'} />;
}

function CreateForm() {
    const navigate = useNavigate();
    const active = useRef(true);
    const locked = useRef(false);
    const request = useRef(null);
    const [pending, setPending] = useState(false);
    const [uncertain, setUncertain] = useState(false);
    const [error, setError] = useState('');
    const [fields, setFields] = useState({});
    const [agreement, setAgreement] = useState(null);
    const [showAgreementModal, setShowAgreementModal] = useState(false);
    const [acceptingAgreement, setAcceptingAgreement] = useState(false);

    useEffect(() => {
        active.current = true;
        hostAgreementService.getCurrentAgreement()
            .then(res => {
                if (!active.current) return;
                if (res?.data) {
                    setAgreement(res.data);
                    if (!res.data.is_accepted) {
                        setShowAgreementModal(true);
                    }
                }
            })
            .catch(() => {
                // Bỏ qua nếu lỗi mạng hoặc chưa cấu hình hợp đồng
            });
        return () => { active.current = false; request.current?.abort(); };
    }, []);

    async function handleAcceptAgreement(ag) {
        setAcceptingAgreement(true);
        try {
            await hostAgreementService.acceptAgreement(ag.id, {
                accepted: true,
                content_sha256: ag.content_sha256,
            });
            if (!active.current) return;
            toast.success('Chấp thuận hợp đồng đối tác thành công.');
            setShowAgreementModal(false);
            setAgreement(prev => prev ? { ...prev, is_accepted: true } : null);
        } finally {
            if (active.current) setAcceptingAgreement(false);
        }
    }

    async function submit(event) {
        event.preventDefault();
        if (locked.current || uncertain) return;
        locked.current = true;
        setPending(true);
        setError('');
        setFields({});
        const data = Object.fromEntries(new FormData(event.currentTarget));
        data.description = data.description || null;
        data.phone = data.phone || null;
        request.current = new AbortController();
        try {
            const property = await hostPropertyService.create(data, { signal: request.current.signal });
            if (!active.current) return;
            toast.success('Tạo cơ sở lưu trú thành công.');
            navigate('/host/properties/' + property.id, { replace: true });
        } catch (failure) {
            if (!active.current) return;
            const status = failure.response?.status;
            if (status === 403 && failure.response?.data?.code === 'AGREEMENT_REQUIRED') {
                setShowAgreementModal(true);
                setError('Bạn cần chấp thuận hợp đồng đối tác trước khi tạo cơ sở lưu trú.');
                return;
            }
            setUncertain(Boolean(failure.outcomeUnknown));
            setFields(status === 422 ? failure.response.data?.errors || {} : {});
            const messages = {
                401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                403: 'Bạn không có quyền tạo cơ sở lưu trú.',
                419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại biểu mẫu.',
                422: 'Vui lòng kiểm tra các trường thông tin.',
            };
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả tạo cơ sở. Vui lòng kiểm tra danh sách trước khi gửi lại.'
                : messages[status] || 'Không thể tạo cơ sở lưu trú. Vui lòng thử lại.');
        } finally {
            if (active.current) { setPending(false); locked.current = false; }
        }
    }

    const control = 'mt-2 w-full rounded-xl border border-border bg-background px-4 py-3';
    function fieldError(name) {
        return fields[name] ? <p id={name + '-error'} className="mt-2 text-sm text-red-700">{fields[name].join(' ')}</p> : null;
    }

    return <main className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-8">
        <Link to="/host/properties" className="text-primary underline">← Về danh sách cơ sở</Link>
        <h1 className="mt-6 font-display text-3xl text-primary">Thêm cơ sở lưu trú</h1>
        {error && <p role="alert" className="mt-6 rounded-xl border border-border p-4">{error}</p>}
        <form onSubmit={submit} className="mt-8 space-y-6">
            <fieldset disabled={pending || uncertain} className="space-y-6 disabled:opacity-70">
                {[
                    ['name', 'Tên cơ sở', 'text', 255, true],
                    ['address', 'Địa chỉ', 'text', 500, true],
                    ['phone', 'Điện thoại', 'tel', 20, false],
                ].map(([name, label, type, maxLength, required]) => <div key={name}>
                    <label htmlFor={name}>{label}</label>
                    <input id={name} name={name} type={type} maxLength={maxLength} required={required}
                        aria-invalid={Boolean(fields[name])} aria-describedby={fields[name] ? name + '-error' : undefined} className={control} />
                    {fieldError(name)}
                </div>)}
                <div><label htmlFor="type">Loại cơ sở</label>
                    <select id="type" name="type" className={control} aria-invalid={Boolean(fields.type)}>
                        <option value="HOTEL">Khách sạn</option><option value="HOMESTAY">Homestay</option>
                    </select>{fieldError('type')}
                </div>
                <div><label htmlFor="description">Mô tả</label>
                    <textarea id="description" name="description" maxLength={10000} rows={5} className={control}
                        aria-invalid={Boolean(fields.description)} />{fieldError('description')}
                </div>
                <div className="grid gap-6 sm:grid-cols-2">
                    {[['check_in_time', 'Giờ nhận phòng', '14:00'], ['check_out_time', 'Giờ trả phòng', '12:00']].map(([name, label, value]) => <div key={name}>
                        <label htmlFor={name}>{label}</label>
                        <input id={name} name={name} type="time" defaultValue={value} required className={control}
                            aria-invalid={Boolean(fields[name])} />{fieldError(name)}
                    </div>)}
                </div>
                <button type="submit" className="rounded-xl bg-primary px-6 py-3 text-primary-foreground">
                    {pending ? 'Đang tạo cơ sở...' : 'Tạo cơ sở lưu trú'}
                </button>
            </fieldset>
        </form>
        {uncertain && <Link to="/host/properties" className="mt-6 inline-block text-primary underline">Kiểm tra danh sách cơ sở</Link>}
        <HostPartnerAgreementModal
            agreement={agreement}
            isOpen={showAgreementModal}
            isSubmitting={acceptingAgreement}
            onAccept={handleAcceptAgreement}
            onCancel={() => navigate('/host/properties')}
        />
    </main>;
}
