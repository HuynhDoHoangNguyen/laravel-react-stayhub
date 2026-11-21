import { useEffect, useState, useSyncExternalStore } from 'react';
import { Link } from 'react-router-dom';
import { roomService } from '../../services/roomService.js';
import { createRoomSearchLoader } from '../../services/roomSearchService.js';
import { normalizeApiError } from '../../api/apiError.js';
import { checkoutTarget, bookingSearchTarget } from '../../utils/propertyBooking.js';

function AvailabilityResult({ room, context }) {
    const [loader] = useState(createRoomSearchLoader);
    const state = useSyncExternalStore(loader.subscribe, loader.getSnapshot, loader.getSnapshot);
    const [request] = useState(() => signal => roomService.checkAvailability(room.id, context, { signal }));
    useEffect(() => { loader.load(request); return () => loader.cancel(); }, [loader, request]);
    if (state.loading) return <p role="status">Đang kiểm tra phòng trống…</p>;
    if (state.error) {
        const failure = normalizeApiError(state.error);
        const errors = state.error.response?.data?.errors ?? {};
        return <div role="alert" className="space-y-3 text-sm">
            <p>{failure.message}</p>
            {Object.entries(errors).map(([key, values]) => <p key={key}>{Array.isArray(values) ? values.join(' ') : String(values)}</p>)}
            <button type="button" onClick={() => loader.load(request)} className="text-primary underline">Thử lại kiểm tra phòng trống</button>
        </div>;
    }
    return <div className="space-y-3 text-sm">
        <p role="status">{state.value?.available ? 'Phòng còn trống theo ngày và số khách đã chọn.' : 'Phòng không khả dụng theo ngày hoặc số khách đã chọn.'}</p>
        {state.value?.available && <Link to={checkoutTarget(room.property.id, room.id, context)} className="block rounded-xl bg-primary px-4 py-3 text-center font-semibold text-primary-foreground">Đặt phòng</Link>}
        <p className="text-muted-foreground">Giá và phòng trống được xác nhận lại tại bước đặt phòng.</p>
    </div>;
}

export function RoomBookingPanel({ room, context }) {
    return <section aria-label="Đặt phòng" className="space-y-4 border-t border-border pt-4">
        <h3 className="font-semibold text-primary">Lưu trú của bạn</h3>
        {context.status === 'valid' ? <>
            <p className="break-words text-sm">{context.checkIn} → {context.checkOut}<br />{context.adults} người lớn · {context.children} trẻ em</p>
            <AvailabilityResult key={room.id + ':' + JSON.stringify(context)} room={room} context={context} />
        </> : <div role={context.status === 'invalid' ? 'alert' : undefined} className="space-y-2 text-sm">
            {context.errors.map((error, index) => <p key={index}>{error}</p>)}
            <p>Vui lòng chọn ngày nhận, trả phòng và số khách để kiểm tra phòng trống.</p>
        </div>}
        <Link to={bookingSearchTarget(context)} className="inline-block text-sm text-primary underline">Chọn lại ngày và số khách</Link>
    </section>;
}
