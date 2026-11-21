import { useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import CatalogImage from '../../components/catalog/CatalogImage';
import { roomService } from '../../services/roomService.js';
import { normalizeApiError } from '../../api/apiError.js';
import { readPropertyBooking } from '../../utils/propertyBooking.js';
import { RoomBookingPanel } from '../../components/hotel/RoomBookingPanel.jsx';

const currency = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 2 });

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
        <Link to={room ? `/properties/${room.property.id}${location.search}` : '/properties'} state={location.state} className="text-sm font-semibold text-primary underline">
            {room ? '← Về cơ sở lưu trú' : '← Khám phá cơ sở lưu trú'}
        </Link>
        {state.status === 'loading' && <p role="status" className="py-20">Đang tải thông tin phòng...</p>}
        {state.status === 'missing' && <section className="my-12 rounded-3xl bg-surface p-8">
            <h1 className="font-display text-3xl text-primary">Không tìm thấy phòng</h1>
            <p className="mt-3 text-sm">Phòng không tồn tại hoặc chưa được công khai.</p>
        </section>}
        {state.status === 'error' && <section role="alert" className="my-10 rounded-3xl border border-border p-8">
            <h1 className="font-display text-2xl text-primary">Chưa thể tải thông tin phòng</h1>
            <p className="mt-3 break-words text-sm">{state.error}</p>
            <button type="button" onClick={retry} className="mt-4 rounded-xl border border-border px-5 py-2 text-primary">Thử lại</button>
        </section>}
        {state.status === 'ready' && room && <article className="mt-6 min-w-0 space-y-8">
            <header className="space-y-3">
                <p className="text-sm text-muted-foreground">{room.property.type === 'HOTEL' ? 'Khách sạn' : 'Homestay'} · <Link className="text-primary underline" to={`/properties/${room.property.id}${location.search}`} state={location.state}>{room.property.name}</Link></p>
                <h1 className="break-words font-display text-3xl text-primary md:text-4xl">{room.name}</h1>
                <p className="break-words text-sm text-muted-foreground">{room.property.address || 'Chưa cập nhật địa chỉ'}</p>
                <p className="break-words text-sm">Mã phòng: {room.room_number} · Loại phòng: {room.room_type.name}</p>
            </header>
            <section aria-labelledby="room-images-title" className="space-y-4">
                <h2 id="room-images-title" className="font-display text-2xl text-primary">Hình ảnh phòng</h2>
                {room.images.length === 0 ? <div className="space-y-3"><CatalogImage alt={room.name} /><p className="text-sm text-muted-foreground">Chưa có hình ảnh phòng.</p></div>
                    : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{room.images.map((url, index) =>
                        <div key={`${index}:${url}`} className="aspect-[4/3] overflow-hidden rounded-2xl"><CatalogImage src={url} alt={`${room.name} - ảnh ${index + 1}`} /></div>)}</div>}
            </section>
            <div className="grid min-w-0 items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="min-w-0 space-y-8">
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
                <aside aria-label="Giá và sức chứa phòng" className="min-w-0 space-y-4 rounded-3xl border border-border bg-card p-6 lg:sticky lg:top-24">
                    <h2 className="font-display text-xl text-primary">Giá tham khảo mỗi đêm</h2>
                    <p className="break-words text-2xl font-semibold text-primary">{currency.format(room.price_per_night)} <span className="text-sm font-normal text-muted-foreground">/ đêm</span></p>
                    <p className="text-sm">Sức chứa tối đa: {room.capacity.toLocaleString('vi-VN')} khách / phòng</p>
                    <p className="text-sm leading-relaxed text-muted-foreground">Giá tham khảo mỗi đêm. Tổng tiền được xác nhận tại Checkout.</p>
                    <RoomBookingPanel room={room} context={context} />
                </aside>
            </div>
        </article>}
    </div>;
}
