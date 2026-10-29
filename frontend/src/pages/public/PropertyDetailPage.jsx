import { Link, useLocation, useParams } from 'react-router-dom';
import { useCatalogResource } from '../../hooks/useCatalogResource';
import { propertyService } from '../../services/propertyService';
import { catalogBackLink, formatCatalogPrice } from '../../services/catalog';
import { PropertyHeader } from '../../components/hotel/PropertyHeader';
import { PropertyGallery } from '../../components/hotel/PropertyGallery';
import { PropertyAmenities } from '../../components/hotel/PropertyAmenities';
import { RoomsList } from '../../components/hotel/RoomsList';
import { ReviewsSection } from '../../components/hotel/ReviewsSection';

export default function PropertyDetailPage() {
    const { propertyId } = useParams();
    return <PropertyView key={propertyId} id={propertyId} />;
}
function PropertyView({ id }) {
    const location = useLocation();
    const back = catalogBackLink(location.state?.catalogPage || '');
    const { status, result, error, retry } = useCatalogResource((options) => propertyService.getPropertyById(id, options));
    const property = result?.data;
    return <div className="mx-auto max-w-[1360px] px-6 py-8 md:px-10">
        <Link to={back} className="text-sm font-semibold text-primary">← Về danh sách cơ sở</Link>
        {status === 'loading' && <p role="status" className="py-20">Đang tải thông tin cơ sở...</p>}
        {status === 'error' && <div role="alert" className="my-10 rounded-3xl border border-border p-8"><p>{error.message}</p><button onClick={retry} className="mt-4 text-primary underline">Thử lại</button></div>}
        {status === 'ready' && !property && <section className="my-12 rounded-3xl bg-surface p-10">
            <h1 className="font-display text-3xl text-primary">Không tìm thấy cơ sở lưu trú</h1><p className="mt-3 text-sm">Cơ sở không tồn tại hoặc chưa được công khai.</p><Link to="/properties" className="mt-5 inline-block text-primary underline">Khám phá cơ sở khác</Link>
        </section>}
        {status === 'ready' && property && <div className="mt-6 space-y-8">
            {result.source === 'mock' && <p className="text-sm text-amber-800">Dữ liệu giả lập DEV.</p>}
            <PropertyHeader property={property} />
            <PropertyGallery photos={property.photos} name={property.name} />
            <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="min-w-0 space-y-10">
                    <section><h2 className="font-display text-2xl text-primary">Giới thiệu</h2><p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{property.description || 'Chưa có mô tả cơ sở.'}</p></section>
                    <PropertyAmenities amenities={property.amenities} />
                    <RoomsList rooms={property.rooms} />
                    <ReviewsSection propertyId={property.id} propertySource={result.source} />
                </div>
                <aside className="space-y-4 rounded-3xl border border-border bg-white p-6 lg:sticky lg:top-24">
                    <h2 className="font-display text-xl text-primary">Giá tham khảo mỗi đêm</h2><p className="text-xl font-semibold">{formatCatalogPrice(property.price)}</p>
                    {(property.checkInTime || property.checkOutTime) && <p className="text-sm text-muted-foreground">Nhận phòng: {property.checkInTime || 'Chưa có thông tin'}<br />Trả phòng: {property.checkOutTime || 'Chưa có thông tin'}</p>}
                    <p className="text-sm text-muted-foreground">Giá trên chưa xác nhận phòng trống theo ngày. Hiện chưa thể đặt phòng trực tuyến tại đây.</p>
                    <button disabled className="w-full rounded-xl bg-primary px-4 py-3 text-sm text-primary-foreground opacity-50">Đặt phòng chưa khả dụng</button>
                </aside>
            </div>
        </div>}
    </div>;
}
