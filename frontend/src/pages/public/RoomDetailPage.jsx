import { useEffect, useState } from 'react';
import { useLocation, useParams } from 'react-router-dom';
import { roomService } from '../../services/roomService.js';
import { normalizeApiError } from '../../api/apiError.js';
import { readPropertyBooking } from '../../utils/propertyBooking.js';
import { RoomBookingPanel } from '../../components/hotel/RoomBookingPanel.jsx';
import { PropertyGallery } from '../../components/hotel/PropertyGallery.jsx';
import { DetailAction } from '../../components/hotel/DetailAction.jsx';
import { DetailBreadcrumbs } from '../../components/hotel/DetailBreadcrumbs.jsx';
import { MapPin, BedDouble } from 'lucide-react';
import { RelatedPropertyRooms } from '../../components/hotel/RelatedPropertyRooms.jsx';

export default function RoomDetailPage() {
    const { roomId } = useParams();
    return <RoomView key={roomId} roomId={roomId} />;
}

function RoomView({ roomId }) {
    const location = useLocation();
    const context = readPropertyBooking(location.search);
    const [state, setState] = useState({ status: 'loading', room: null, error: null });
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        roomService.getRoomById(roomId, { signal: controller.signal }).then(
            result => {
                if (current) setState({ status: 'ready', room: result.data, error: null });
            },
            failure => {
                if (!current || controller.signal.aborted) return;
                const error = normalizeApiError(failure);
                if (error.isCancelled) return;
                const missing = error.status === 404 || (failure.isLocalValidation && error.status === 400);
                const message = failure.code === 'INVALID_PUBLIC_ROOM_RESPONSE' ? failure.message : error.message;
                setState({ status: missing ? 'missing' : 'error', room: null, error: message });
            },
        );
        return () => { current = false; controller.abort(); };
    }, [roomId, attempt]);

    function retry() {
        setState({ status: 'loading', room: null, error: null });
        setAttempt(value => value + 1);
    }

    const room = state.room;
    return <div className="mx-auto max-w-[1360px] px-6 py-8 md:px-10">
        <DetailBreadcrumbs items={[
            { label: 'Cơ sở lưu trú', to: '/properties' },
            ...(room ? [{ label: room.property.name, to: `/properties/${room.property.id}${location.search}`, state: location.state }] : []),
            { label: room?.name || 'Chi tiết phòng' },
        ]} />
        {state.status === 'loading' && <p role="status" className="py-20">Đang tải thông tin phòng...</p>}
        {state.status === 'missing' && <section className="my-12 rounded-3xl bg-surface p-8">
            <h1 className="font-display text-3xl text-primary">Không tìm thấy phòng</h1>
            <p className="mt-3 text-sm">Phòng không tồn tại hoặc chưa được công khai.</p>
        </section>}
        {state.status === 'error' && <section role="alert" className="my-10 rounded-3xl border border-border p-8">
            <h1 className="font-display text-2xl text-primary">Chưa thể tải thông tin phòng</h1>
            <p className="mt-3 break-words text-sm">{state.error}</p>
            <DetailAction tone="secondary" type="button" onClick={retry} className="mt-4">Thử lại</DetailAction>
        </section>}
        {state.status === 'ready' && room && <article className="mt-6 min-w-0 space-y-8">
            <header className="space-y-3">
                <p className="text-xs uppercase tracking-[0.2em] text-accent">{room.property.type === 'HOTEL' ? 'Khách sạn' : 'Homestay'} · {room.room_type.name}</p>
                <h1 className="break-words font-display text-3xl text-primary md:text-5xl">{room.name}</h1>
                <p className="flex items-start gap-2 text-sm text-muted-foreground"><MapPin className="mt-0.5 h-4 w-4 shrink-0" aria-hidden /><span className="min-w-0 break-words">{room.property.address || 'Chưa cập nhật địa chỉ'}</span></p>
                <p className="flex items-center gap-2 text-sm"><BedDouble className="h-4 w-4 shrink-0 text-primary" aria-hidden /><span>Mã phòng: {room.room_number}</span></p>
            </header>
            <div className="grid min-w-0 items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div data-detail-region="gallery" className="min-w-0 lg:col-start-1 lg:row-start-1">
                    <PropertyGallery key={room.id} photos={room.images} name={room.name} label="Ảnh phòng" />
                </div>
                <div data-detail-region="booking" className="min-w-0 lg:sticky lg:top-24 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start">
                    <RoomBookingPanel room={room} context={context} />
                </div>
                <div data-detail-region="content" className="min-w-0 space-y-8 lg:col-start-1 lg:row-start-2">
                    <section aria-labelledby="room-description-title">
                        <h2 id="room-description-title" className="font-display text-2xl text-primary">Giới thiệu phòng</h2>
                        <p className="mt-3 whitespace-pre-line break-words text-sm leading-relaxed text-muted-foreground">{room.description?.trim() ? room.description : 'Chưa có mô tả phòng.'}</p>
                    </section>
                    <section aria-labelledby="room-amenities-title">
                        <h2 id="room-amenities-title" className="font-display text-2xl text-primary">Tiện nghi</h2>
                        {room.amenities.length === 0 ? <p className="mt-3 text-sm text-muted-foreground">Chưa có thông tin tiện nghi.</p>
                            : <ul className="mt-3 flex flex-wrap gap-3">{room.amenities.map(item => <li key={item.id} className="max-w-full break-words rounded-xl bg-surface px-4 py-2 text-sm">{item.name}</li>)}</ul>}
                    </section>
                </div>
            </div>
            <RelatedPropertyRooms key={room.property.id + ':' + room.id} propertyId={room.property.id} currentRoomId={room.id} />
        </article>}
    </div>;
}
