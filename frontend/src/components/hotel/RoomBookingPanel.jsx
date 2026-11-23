import { useEffect, useState, useSyncExternalStore } from 'react';
import { Link } from 'react-router-dom';
import { roomService } from '../../services/roomService.js';
import { createRoomSearchLoader } from '../../services/roomSearchService.js';
import { normalizeApiError } from '../../api/apiError.js';
import { directRoomCheckoutTarget, readPropertyBooking } from '../../utils/propertyBooking.js';
import { DetailAction } from './DetailAction.jsx';
import { CalendarDays, Users, BedDouble, ArrowRight, RefreshCw } from 'lucide-react';

const currency = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 2 });

export function RoomAvailabilityError({ failure, onRetry }) {
    return <div role="alert" className="space-y-3 text-sm">
        <p>{failure.message}</p>
        {Object.entries(failure.fields).map(([key, messages]) => <p key={key}>{messages.join(' ')}</p>)}
        <DetailAction tone="secondary" type="button" onClick={onRetry} className="w-full"><RefreshCw aria-hidden />Thử lại kiểm tra phòng trống</DetailAction>
    </div>;
}

function AvailabilityResult({ room, context }) {
    const [loader] = useState(createRoomSearchLoader);
    const state = useSyncExternalStore(loader.subscribe, loader.getSnapshot, loader.getSnapshot);
    const [request] = useState(() => signal => roomService.checkAvailability(room.id, context, { signal }));
    useEffect(() => { loader.load(request); return () => loader.cancel(); }, [loader, request]);
    if (state.loading) return <p role="status">Đang kiểm tra phòng trống…</p>;
    if (state.error) return <RoomAvailabilityError failure={normalizeApiError(state.error)} onRetry={() => loader.load(request)} />;
    return <p role="status" className="text-sm">{state.value?.available ? 'Phòng còn trống theo ngày và số khách đã chọn.' : 'Phòng không khả dụng theo ngày hoặc số khách đã chọn.'}</p>;
}

export function RoomBookingPanel({ room, context }) {
    const checkoutContext = context.status === 'invalid' ? readPropertyBooking()
        : context.status === 'missing' ? { ...context, checkIn: '', checkOut: '' }
            : context;
    const checkoutHref = directRoomCheckoutTarget(room.property.id, room.id, checkoutContext);

    return <aside aria-label="Đặt phòng" className="min-w-0 space-y-5 overflow-hidden rounded-3xl border border-primary/10 bg-card p-5 shadow-[0_18px_50px_-25px_rgba(26,46,32,0.35)] sm:p-6">
        <header className="space-y-2 border-b border-border/70 pb-5">
            <h2 className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Lưu trú của bạn</h2>
            <p className="break-words font-display text-3xl text-primary">{currency.format(room.price_per_night)}</p>
            <p className="text-xs text-muted-foreground">Giá tham khảo mỗi đêm</p>
        </header>
        <div className="flex items-start gap-3 rounded-2xl bg-surface/60 p-3.5">
            <BedDouble className="mt-0.5 h-5 w-5 shrink-0 text-primary" aria-hidden />
            <div className="min-w-0 text-sm"><p className="break-words font-semibold text-primary">{room.name}</p>
                <p className="mt-1 break-words text-xs text-muted-foreground">{room.room_type.name}</p>
                <p className="mt-1 text-xs text-muted-foreground">Sức chứa tối đa: {room.capacity.toLocaleString('vi-VN')} khách</p>
            </div>
        </div>
        {context.status === 'valid' ? <>
            <dl className="space-y-3 rounded-2xl border border-border/70 p-3.5 text-sm">
                <div className="flex items-start gap-2.5"><CalendarDays className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden />
                    <div className="grid min-w-0 flex-1 grid-cols-2 gap-2">
                        <div><dt className="text-xs text-muted-foreground">Nhận phòng</dt><dd className="mt-1 break-words font-medium">{context.checkIn}</dd></div>
                        <div><dt className="text-xs text-muted-foreground">Trả phòng</dt><dd className="mt-1 break-words font-medium">{context.checkOut}</dd></div>
                    </div>
                </div>
                <div className="flex items-start gap-2.5 border-t border-border/60 pt-3"><Users className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden />
                    <div><dt className="text-xs text-muted-foreground">Số khách</dt><dd className="mt-1 font-medium">{context.adults} người lớn · {context.children} trẻ em</dd></div>
                </div>
            </dl>
            <AvailabilityResult key={room.id + ':' + JSON.stringify(context)} room={room} context={context} />
        </> : context.status === 'invalid' && <div role="alert" className="space-y-2 text-sm">
            {context.errors.map((error, index) => <p key={index}>{error}</p>)}
        </div>}
        <DetailAction asChild className="w-full"><Link to={checkoutHref}>Đặt phòng<ArrowRight aria-hidden /></Link></DetailAction>
        <p className="border-t border-border/60 pt-4 text-xs leading-relaxed text-muted-foreground">Giá và phòng trống được xác nhận lại tại bước đặt phòng.</p>
    </aside>;
}
