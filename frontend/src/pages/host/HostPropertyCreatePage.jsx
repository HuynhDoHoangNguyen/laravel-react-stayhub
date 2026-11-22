import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { ArrowLeft, Building2, Clock3, Plus } from 'lucide-react';
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

    const control = 'mt-2 min-h-11 w-full rounded-xl border border-border bg-background px-4 py-3 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/15 aria-invalid:border-red-400 aria-invalid:bg-red-50/30 disabled:cursor-not-allowed';
    function fieldError(name) {
        return fields[name] ? <p id={name + '-error'} className="mt-2 text-sm text-red-700">{fields[name].join(' ')}</p> : null;
    }

    return <main className="mx-auto w-full max-w-4xl space-y-6 px-4 py-6 sm:px-8 sm:py-8">
        <Link to="/host/properties" className="inline-flex min-h-10 items-center gap-2 rounded-lg text-sm font-medium text-muted-foreground hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"><ArrowLeft size={16} aria-hidden="true" />Cơ sở lưu trú của tôi</Link>
        <header className="rounded-3xl border border-border bg-card p-5 shadow-sm sm:p-7"><span className="mb-3 inline-flex items-center gap-2 rounded-full bg-surface px-3 py-1.5 text-xs font-semibold text-primary"><Building2 size={14} aria-hidden="true" />Cơ sở mới</span><h1 className="font-display text-2xl text-primary sm:text-3xl">Thêm cơ sở lưu trú</h1><p className="mt-2 text-sm leading-6 text-muted-foreground">Điền thông tin cơ bản. Bạn có thể thêm hình ảnh và phòng sau khi tạo cơ sở.</p></header>
        {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800">{error}</p>}
        <form onSubmit={submit} aria-busy={pending} className="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-7">
            <fieldset disabled={pending || uncertain} className="grid min-w-0 gap-5 disabled:opacity-70 sm:grid-cols-2">
                <legend className="mb-5 text-base font-semibold text-primary">Thông tin cơ sở</legend>
                {[
                    ['name', 'Tên cơ sở', 'text', 255, true],
                    ['address', 'Địa chỉ', 'text', 500, true],
                    ['phone', 'Điện thoại', 'tel', 20, false],
                ].map(([name, label, type, maxLength, required]) => <div key={name} className={name === 'phone' ? 'min-w-0' : 'min-w-0 sm:col-span-2'}>
                    <label htmlFor={name} className="text-sm font-medium text-foreground">{label}{required && <span className="ml-1 text-red-700" aria-hidden="true">*</span>}</label>
                    <input id={name} name={name} type={type} maxLength={maxLength} required={required}
                        aria-invalid={Boolean(fields[name])} aria-describedby={fields[name] ? name + '-error' : undefined} className={control} />
                    {fieldError(name)}
                </div>)}
                <div className="min-w-0"><label htmlFor="type" className="text-sm font-medium text-foreground">Loại cơ sở</label>
                    <select id="type" name="type" className={control} aria-invalid={Boolean(fields.type)} aria-describedby={fields.type ? 'type-error' : undefined}>
                        <option value="HOTEL">Khách sạn</option><option value="HOMESTAY">Homestay</option>
                    </select>{fieldError('type')}
                </div>
                <div className="min-w-0 sm:col-span-2"><label htmlFor="description" className="text-sm font-medium text-foreground">Mô tả</label>
                    <textarea id="description" name="description" maxLength={10000} rows={5} className={control}
                        aria-invalid={Boolean(fields.description)} aria-describedby={fields.description ? 'description-error' : undefined} />{fieldError('description')}
                </div>
                <div className="min-w-0 space-y-4 rounded-2xl border border-border/70 bg-surface/50 p-4 sm:col-span-2 sm:p-5">
                    <h2 className="flex items-center gap-2 text-sm font-semibold text-primary"><Clock3 size={16} aria-hidden="true" />Giờ nhận và trả phòng</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                    {[['check_in_time', 'Giờ nhận phòng', '14:00'], ['check_out_time', 'Giờ trả phòng', '12:00']].map(([name, label, value]) => <div key={name}>
                        <label htmlFor={name} className="text-sm font-medium text-foreground">{label}</label>
                        <input id={name} name={name} type="time" defaultValue={value} required className={control}
                            aria-invalid={Boolean(fields[name])} aria-describedby={fields[name] ? name + '-error' : undefined} />{fieldError(name)}
                    </div>)}
                    </div>
                </div>
                <div className="flex flex-col gap-3 border-t border-border pt-5 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs text-muted-foreground">Các trường có dấu * là bắt buộc.</p>
                <button type="submit" className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none">
                    <Plus size={17} aria-hidden="true" />
                    {pending ? 'Đang tạo cơ sở...' : 'Tạo cơ sở lưu trú'}
                </button>
                </div>
            </fieldset>
        </form>
        {uncertain && <Link to="/host/properties" className="inline-flex min-h-11 items-center rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold text-primary shadow-sm focus-visible:ring-2 focus-visible:ring-primary">Kiểm tra danh sách cơ sở</Link>}
        <HostPartnerAgreementModal
            agreement={agreement}
            isOpen={showAgreementModal}
            isSubmitting={acceptingAgreement}
            onAccept={handleAcceptAgreement}
            onCancel={() => navigate('/host/properties')}
        />
    </main>;
}
