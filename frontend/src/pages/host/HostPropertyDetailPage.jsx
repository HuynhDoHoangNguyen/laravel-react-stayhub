import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, ArrowUpRight, Building2, Clock3, MapPin, Pencil, Phone } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { hostPropertyService } from '../../services/hostPropertyService';
import HostPropertyStatusPanel from '../../components/catalog/HostPropertyStatusPanel';
import HostPropertyImageManager from '../../components/catalog/HostPropertyImageManager';
import HostPropertyVerificationPanel from '../../components/catalog/HostPropertyVerificationPanel';
import HostRoomTypeList from '../../components/catalog/HostRoomTypeList';
import HostRoomList from '../../components/catalog/HostRoomList';

const statuses = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngưng hoạt động', PENDING: 'Chờ duyệt', BLOCKED: 'Đã khóa' };
const statusStyles = {
    ACTIVE: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    INACTIVE: 'border-border bg-surface text-muted-foreground',
    PENDING: 'border-amber-200 bg-amber-50 text-amber-800',
    BLOCKED: 'border-red-200 bg-red-50 text-red-800',
};

export default function HostPropertyDetailPage() {
    const { propertyId } = useParams();
    const { user } = useAuth();
    return <PropertyDetail key={user?.id + ':' + propertyId} id={propertyId} />;
}

function PropertyDetail({ id }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading', property: null, error: '' });
    const focusImages = useRef(false);
    useEffect(() => {
        if (state.status === 'ready' && focusImages.current) {
            focusImages.current = false;
            document.getElementById('image-manager-title')?.focus();
        }
    }, [state.status]);
    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostPropertyService.detail(id, { signal: controller.signal }).then(
            (property) => {
                if (current) setState({ status: 'ready', property, error: '' });
            },
            (error) => {
                if (!current) return;
                const status = error.response?.status || error.status;
                const messages = {
                    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                    403: 'Bạn không có quyền xem cơ sở lưu trú này.',
                    404: 'Không tìm thấy cơ sở lưu trú.',
                };
                setState({ status: status === 404 ? 'missing' : 'error', property: null,
                    error: messages[status] || 'Không thể tải chi tiết cơ sở lưu trú. Vui lòng thử lại.' });
            },
        );
        return () => { current = false; controller.abort(); };
    }, [id, attempt]);

    function retry(returnToImages = false) {
        focusImages.current = returnToImages === true;
        setState({ status: 'loading', property: null, error: '' });
        setAttempt((value) => value + 1);
    }

    const property = state.property;
    return <main className="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-8 sm:py-8">
        <Link to="/host/properties" className="inline-flex min-h-10 items-center gap-2 rounded-lg text-sm font-medium text-muted-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <ArrowLeft size={16} aria-hidden="true" /> Cơ sở lưu trú của tôi
        </Link>
        {state.status === 'loading' && <p role="status" className="rounded-2xl border border-border bg-card p-8 shadow-sm">Đang tải chi tiết cơ sở lưu trú...</p>}
        {['error', 'missing'].includes(state.status) && <div role="alert" className="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <p>{state.error}</p>
            {state.status === 'error' && <button type="button" onClick={() => retry()} className="mt-4 rounded-xl border border-border px-4 py-2 text-primary shadow-sm focus-visible:ring-2 focus-visible:ring-primary">Thử lại</button>}
        </div>}
        {property && <article className="min-w-0 space-y-6">
            <header className="relative overflow-hidden rounded-3xl border border-border bg-card p-5 shadow-sm sm:p-7">
                <div className="absolute inset-y-0 left-0 w-1 bg-primary" aria-hidden="true" />
                <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0 space-y-3">
                        <div className="flex flex-wrap items-center gap-2 text-xs font-medium">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1.5 text-primary"><Building2 size={14} aria-hidden="true" />{property.type === 'HOTEL' ? 'Khách sạn' : 'Homestay'}</span>
                            <span className={'rounded-full border px-3 py-1.5 ' + statusStyles[property.status]}>{statuses[property.status]}</span>
                        </div>
                        <h1 className="break-words font-display text-2xl leading-tight text-primary sm:text-3xl">{property.name}</h1>
                        <p className="flex items-start gap-2 text-sm leading-relaxed text-muted-foreground"><MapPin size={16} className="mt-0.5 shrink-0" aria-hidden="true" /><span className="min-w-0 break-words">{property.address}</span></p>
                    </div>
                    <Link to={'/host/properties/' + property.id + '/edit'} className="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:shadow-lg active:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none">
                        <Pencil size={16} aria-hidden="true" /> Chỉnh sửa thông tin
                    </Link>
                </div>
            </header>
            <nav aria-label="Các phần của cơ sở" className="flex flex-wrap gap-2 rounded-2xl border border-border bg-card p-2 shadow-sm">
                {[['overview', 'Tổng quan'], ['verification', 'Xác minh cơ sở'], ['images', 'Hình ảnh'], ['room-types', 'Loại phòng'], ['rooms', 'Danh sách phòng']].map(([anchor, label]) => <a key={anchor} href={'#property-' + anchor}
                    className="inline-flex min-h-10 items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">{label}<ArrowUpRight size={14} aria-hidden="true" /></a>)}
            </nav>
            <div id="property-overview" className="grid min-w-0 scroll-mt-6 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <section aria-labelledby="property-overview-title" className="min-w-0 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
                    <h2 id="property-overview-title" className="text-lg font-semibold text-primary">Thông tin cơ sở</h2>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-3">
                        {[[Phone, 'Điện thoại', property.phone ?? 'Chưa có thông tin'], [Clock3, 'Giờ nhận phòng', property.check_in_time.slice(0, 5)], [Clock3, 'Giờ trả phòng', property.check_out_time.slice(0, 5)]].map(([Icon, label, value]) => <div key={label} className="min-w-0 rounded-xl border border-border/70 bg-surface/50 p-4">
                            <dt className="flex items-center gap-2 text-xs font-medium text-muted-foreground"><Icon size={14} aria-hidden="true" />{label}</dt><dd className="mt-2 break-words text-sm font-semibold text-foreground">{value}</dd>
                        </div>)}
                    </dl>
                    <div className="mt-6 border-t border-border pt-5"><h3 className="text-sm font-semibold text-foreground">Giới thiệu</h3><p className="mt-2 whitespace-pre-line break-words text-sm leading-7 text-muted-foreground">{property.description ?? 'Chưa có mô tả.'}</p></div>
                </section>
                <div className="min-w-0 lg:sticky lg:top-6"><HostPropertyStatusPanel property={property} onReload={() => retry()}
                    onChanged={(updated) => setState({ status: 'ready', property: updated, error: '' })} /></div>
            </div>
            <HostPropertyVerificationPanel propertyId={property.id} address={property.address} />
            <div id="property-images" className="min-w-0 scroll-mt-6"><HostPropertyImageManager propertyId={property.id} onImagesChanged={() => retry(true)} /></div>
            <div id="property-room-types" className="min-w-0 scroll-mt-6 rounded-2xl bg-card shadow-sm"><HostRoomTypeList key={property.id} propertyId={property.id} /></div>
            <div id="property-rooms" className="min-w-0 scroll-mt-6 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"><HostRoomList key={'rooms:' + property.id} propertyId={property.id} /></div>
        </article>}
    </main>;
}
