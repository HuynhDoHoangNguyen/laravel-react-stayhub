import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { hostPropertyService } from '../../services/hostPropertyService';
import CatalogImage from '../../components/catalog/CatalogImage';
import HostPropertyStatusPanel from '../../components/catalog/HostPropertyStatusPanel';
import HostPropertyImageManager from '../../components/catalog/HostPropertyImageManager';
import HostRoomTypeList from '../../components/catalog/HostRoomTypeList';
import HostRoomList from '../../components/catalog/HostRoomList';

const statuses = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngưng hoạt động', PENDING: 'Chờ duyệt', BLOCKED: 'Đã khóa' };

export default function HostPropertyDetailPage() {
    const { propertyId } = useParams();
    const { user } = useAuth();
    return <PropertyDetail key={user?.id + ':' + propertyId} id={propertyId} />;
}

function PropertyDetail({ id }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading', property: null, error: '' });
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

    function retry() {
        setState({ status: 'loading', property: null, error: '' });
        setAttempt((value) => value + 1);
    }

    const property = state.property;
    return <main className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-8">
        <Link to="/host/properties" className="text-primary underline">← Về danh sách cơ sở</Link>
        {state.status === 'loading' && <p role="status" className="mt-8">Đang tải chi tiết cơ sở lưu trú...</p>}
        {['error', 'missing'].includes(state.status) && <div role="alert" className="mt-8 rounded-2xl border border-border p-6">
            <p>{state.error}</p>
            {state.status === 'error' && <button type="button" onClick={retry} className="mt-4 rounded-xl border border-border px-4 py-2 text-primary">Thử lại</button>}
        </div>}
        {property && <article className="mt-8 space-y-8">
            <h1 className="break-words font-display text-3xl text-primary">{property.name}</h1>
            <Link to={"/host/properties/" + property.id + "/edit"} className="inline-block text-primary underline">Chỉnh sửa thông tin</Link>
            <dl className="grid gap-6 rounded-2xl border border-border p-6 sm:grid-cols-2">
                {[
                    ['Loại cơ sở', property.type === 'HOTEL' ? 'Khách sạn' : 'Homestay'],
                    ['Trạng thái', statuses[property.status]], ['Địa chỉ', property.address],
                    ['Điện thoại', property.phone ?? 'Chưa có thông tin'],
                    ['Giờ nhận phòng', property.check_in_time], ['Giờ trả phòng', property.check_out_time],
                ].map(([label, value]) => <div key={label} className="min-w-0">
                    <dt className="text-sm text-muted-foreground">{label}</dt><dd className="mt-1 break-words">{value}</dd>
                </div>)}
            </dl>
            <HostPropertyStatusPanel property={property} onReload={retry}
                onChanged={(updated) => setState({ status: 'ready', property: updated, error: '' })} />
            <section><h2 className="text-xl font-semibold">Mô tả</h2><p className="mt-3 whitespace-pre-line break-words">{property.description ?? 'Chưa có mô tả.'}</p></section>
            <HostRoomTypeList key={property.id} propertyId={property.id} />
            <HostRoomList key={'rooms:' + property.id} propertyId={property.id} />
            <HostPropertyImageManager propertyId={property.id} onImagesChanged={retry} />
            <section><h2 className="text-xl font-semibold">Hình ảnh</h2>
                {property.images.length ? <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    {property.images.map((src, index) => <div key={index} className="aspect-[4/3] overflow-hidden rounded-2xl">
                        <CatalogImage src={src} alt={property.name + ' — ảnh ' + (index + 1)} />
                    </div>)}
                </div> : <p className="mt-3 text-muted-foreground">Chưa có hình ảnh.</p>}
            </section>
        </article>}
    </main>;
}
