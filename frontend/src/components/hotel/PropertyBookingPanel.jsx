import { Link } from 'react-router-dom';
import { formatCatalogPrice } from '../../services/catalog.js';
import { normalizeApiError } from '../../api/apiError.js';
import { checkoutTarget, bookingSearchTarget } from '../../utils/propertyBooking.js';
import { propertyRoomStatus } from '../../hooks/usePropertyAvailability.js';
import { DetailAction } from './DetailAction';
import { CalendarDays, Users, BedDouble, ArrowRight, SlidersHorizontal, CheckCircle2, RefreshCw } from 'lucide-react';

export function PropertyBookingPanel({ property, context, availability, selectedRoom, onRetry }) {
    const ready = context.status === 'valid' && !availability.loading && !availability.error;
    const bookable = ready && selectedRoom && propertyRoomStatus(availability, selectedRoom.id) === 'available';
    return <aside aria-label="Đặt phòng tại cơ sở" className="min-w-0 space-y-5 overflow-hidden rounded-3xl border border-primary/10 bg-card p-5 shadow-[0_18px_50px_-25px_rgba(26,46,32,0.35)] sm:p-6">
        <header className="space-y-2 border-b border-border/70 pb-5">
            <h2 className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Lưu trú của bạn</h2>
            <p className="break-words font-display text-3xl text-primary">{formatCatalogPrice(bookable ? selectedRoom.price : property.price)}</p>
            <p className="text-xs text-muted-foreground">Giá tham khảo mỗi đêm</p>
        </header>
        <div className="flex items-start gap-3 rounded-2xl bg-surface/60 p-3.5">
            <BedDouble className="mt-0.5 h-5 w-5 shrink-0 text-primary" aria-hidden />
            <div className="min-w-0 text-sm"><p className="text-xs text-muted-foreground">Phòng đã chọn</p>
                <p className="mt-1 break-words font-semibold text-primary">{bookable ? selectedRoom.name : 'Chưa chọn phòng'}</p>
                {bookable && <p className="mt-1 text-xs text-muted-foreground">Sức chứa tối đa: {selectedRoom.capacity} khách</p>}
            </div>
        </div>
        {(property.checkInTime || property.checkOutTime) && <p className="text-xs leading-relaxed text-muted-foreground">Giờ nhận phòng: {property.checkInTime || 'Chưa có thông tin'}<br />Giờ trả phòng: {property.checkOutTime || 'Chưa có thông tin'}</p>}
        {context.status !== 'valid' ? <div className="space-y-2 text-sm" role={context.status === 'invalid' ? 'alert' : undefined}>
            {context.errors.map((message, index) => <p key={index}>{message}</p>)}
            <p>Chọn ngày nhận, trả phòng và số khách để kiểm tra phòng trống.</p>
        </div> : <>
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
            {availability.loading && <p role="status" className="text-sm">Đang kiểm tra phòng trống…</p>}
            {availability.error && <div role="alert" className="space-y-2 text-sm">
                <p>{normalizeApiError(availability.error).message}</p>
                <DetailAction tone="secondary" type="button" onClick={onRetry} className="w-full"><RefreshCw aria-hidden />Thử lại kiểm tra phòng trống</DetailAction>
            </div>}
            {ready && (availability.value?.isTruncated || availability.value?.hasMore) && <p role="status" className="text-sm">Danh sách phòng trống chưa đầy đủ. Phòng chưa xuất hiện chưa được xác nhận khả dụng.</p>}
            {bookable ? <>
                <p className="flex items-start gap-2 rounded-xl bg-primary/5 p-3 text-xs text-primary"><CheckCircle2 className="h-4 w-4 shrink-0" aria-hidden />Phòng còn trống theo kỳ lưu trú đã chọn.</p>
                <DetailAction asChild className="w-full"><Link to={checkoutTarget(property.id, selectedRoom.id, context)}>Tiến hành đặt phòng<ArrowRight aria-hidden /></Link></DetailAction>
            </> : ready && <DetailAction asChild tone="secondary" className="w-full"><a href="#rooms-list"><BedDouble aria-hidden />Chọn phòng</a></DetailAction>}
        </>}
        <DetailAction asChild tone="secondary" className="w-full"><Link to={bookingSearchTarget(context)}><SlidersHorizontal aria-hidden />Chọn lại ngày và số khách</Link></DetailAction>
        <p className="border-t border-border/60 pt-4 text-xs leading-relaxed text-muted-foreground">Giá và phòng trống được xác nhận lại tại Checkout.</p>
    </aside>;
}
