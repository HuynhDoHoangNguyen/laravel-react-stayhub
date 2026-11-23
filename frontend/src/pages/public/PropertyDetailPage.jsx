import { Link, useLocation, useParams } from 'react-router-dom';
import { useCatalogResource } from '../../hooks/useCatalogResource';
import { propertyService } from '../../services/propertyService';
import { searchBackLink } from '../../utils/searchNavigation';
import { PropertyHeader } from '../../components/hotel/PropertyHeader';
import { PropertyGallery } from '../../components/hotel/PropertyGallery';
import { PropertyAmenities } from '../../components/hotel/PropertyAmenities';
import { RoomsList } from '../../components/hotel/RoomsList';
import { ReviewsSection } from '../../components/hotel/ReviewsSection';
import { usePropertyAvailability } from '../../hooks/usePropertyAvailability';
import { readPropertyBooking } from '../../utils/propertyBooking';
import { DetailAction } from '../../components/hotel/DetailAction';
import { DetailBreadcrumbs } from '../../components/hotel/DetailBreadcrumbs';

export default function PropertyDetailPage() {
    const { propertyId } = useParams();
    return <PropertyView key={propertyId} id={propertyId} />;
}
function PropertyView({ id }) {
    const location = useLocation();
    const back = searchBackLink(location.state);
    const { status, result, error, retry } = useCatalogResource((options) => propertyService.getPropertyById(id, options));
    const property = result?.data;
    return <div className="mx-auto max-w-[1360px] px-6 py-8 md:px-10">
        <DetailBreadcrumbs items={[{ label: 'Kết quả khám phá', to: back }, { label: property?.name || 'Chi tiết cơ sở' }]} />
        {status === 'loading' && <p role="status" className="py-20">Đang tải thông tin cơ sở...</p>}
        {status === 'error' && <div role="alert" className="my-10 rounded-3xl border border-border p-8"><p>{error.message}</p><DetailAction tone="secondary" type="button" onClick={retry} className="mt-4">Thử lại</DetailAction></div>}
        {status === 'ready' && !property && <section className="my-12 rounded-3xl bg-surface p-10">
            <h1 className="font-display text-3xl text-primary">Không tìm thấy cơ sở lưu trú</h1><p className="mt-3 text-sm">Cơ sở không tồn tại hoặc chưa được công khai.</p><DetailAction asChild tone="secondary" className="mt-5"><Link to="/search?tab=properties">Khám phá cơ sở khác</Link></DetailAction>
        </section>}
        {status === 'ready' && property && <div className="mt-6 space-y-8">
            {result.source === 'mock' && <p className="text-sm text-amber-800">Dữ liệu giả lập DEV — không hỗ trợ đặt phòng.</p>}
            <PropertyHeader property={property} />
            <PropertyStay key={id + ':' + location.search} property={property} source={result.source} location={location} />
        </div>}
    </div>;
}

function PropertyStay({ property, source, location }) {
    const context = readPropertyBooking(location.search);
    const availability = usePropertyAvailability(property.id, context, source);
    return (
        <div className="space-y-10">
            <div className="grid items-start gap-8 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div data-detail-region="gallery" className="min-w-0">
                    <PropertyGallery photos={property.photos} name={property.name} />
                </div>
                <RoomsList rooms={property.rooms}
                    booking={{ propertyId: property.id, source, context, availability, search: location.search, state: location.state }} />
            </div>
            <div data-detail-region="content" className="min-w-0 space-y-10">
                <section><h2 className="font-display text-2xl text-primary">Giới thiệu</h2><p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{property.description || 'Chưa có mô tả cơ sở.'}</p></section>
                <PropertyAmenities amenities={property.amenities} />
                <div className="min-w-0 overflow-x-clip">
                    <ReviewsSection propertyId={property.id} propertySource={source} />
                </div>
            </div>
        </div>
    );
}
