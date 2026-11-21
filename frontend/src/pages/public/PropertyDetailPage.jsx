import { Link, useLocation, useParams } from 'react-router-dom';
import { useState } from 'react';
import { useCatalogResource } from '../../hooks/useCatalogResource';
import { propertyService } from '../../services/propertyService';
import { catalogBackLink } from '../../services/catalog';
import { PropertyHeader } from '../../components/hotel/PropertyHeader';
import { PropertyGallery } from '../../components/hotel/PropertyGallery';
import { PropertyAmenities } from '../../components/hotel/PropertyAmenities';
import { RoomsList } from '../../components/hotel/RoomsList';
import { ReviewsSection } from '../../components/hotel/ReviewsSection';
import { PropertyBookingPanel } from '../../components/hotel/PropertyBookingPanel';
import { usePropertyAvailability, propertyRoomStatus } from '../../hooks/usePropertyAvailability';
import { readPropertyBooking } from '../../utils/propertyBooking';
import { DetailAction } from '../../components/hotel/DetailAction';
import { DetailBreadcrumbs } from '../../components/hotel/DetailBreadcrumbs';

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
        <DetailBreadcrumbs items={[{ label: 'Cơ sở lưu trú', to: back }, { label: property?.name || 'Chi tiết cơ sở' }]} />
        {status === 'loading' && <p role="status" className="py-20">Đang tải thông tin cơ sở...</p>}
        {status === 'error' && <div role="alert" className="my-10 rounded-3xl border border-border p-8"><p>{error.message}</p><DetailAction tone="secondary" type="button" onClick={retry} className="mt-4">Thử lại</DetailAction></div>}
        {status === 'ready' && !property && <section className="my-12 rounded-3xl bg-surface p-10">
            <h1 className="font-display text-3xl text-primary">Không tìm thấy cơ sở lưu trú</h1><p className="mt-3 text-sm">Cơ sở không tồn tại hoặc chưa được công khai.</p><DetailAction asChild tone="secondary" className="mt-5"><Link to="/properties">Khám phá cơ sở khác</Link></DetailAction>
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
    const [selectedRoomId, setSelectedRoomId] = useState(() => new URLSearchParams(location.search).get('roomId'));
    const selectedRoom = context.status === 'valid' && propertyRoomStatus(availability, selectedRoomId) === 'available'
        ? property.rooms.find(room => String(room.id) === String(selectedRoomId)) : null;
    const retry = () => { setSelectedRoomId(null); availability.retry(); };
    return (
            <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div data-detail-region="gallery" className="min-w-0 lg:col-start-1 lg:row-start-1">
                    <PropertyGallery photos={property.photos} name={property.name} />
                </div>
                <div data-detail-region="booking" className="min-w-0 lg:sticky lg:top-24 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start">
                    <PropertyBookingPanel property={property} context={context} availability={availability} selectedRoom={selectedRoom} onRetry={retry} />
                </div>
                <div data-detail-region="content" className="min-w-0 space-y-10 lg:col-start-1 lg:row-start-2">
                    <section><h2 className="font-display text-2xl text-primary">Giới thiệu</h2><p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{property.description || 'Chưa có mô tả cơ sở.'}</p></section>
                    <PropertyAmenities amenities={property.amenities} />
                    <RoomsList rooms={property.rooms} selectedRoomId={selectedRoom?.id} onSelect={room => setSelectedRoomId(room.id)}
                        booking={{ propertyId: property.id, context, availability, search: location.search, state: location.state }} />
                    <ReviewsSection propertyId={property.id} propertySource={source} />
                </div>
            </div>
    );
}
