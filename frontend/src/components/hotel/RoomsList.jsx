import { RoomCard } from './RoomCard';
import { Link } from 'react-router-dom';
import { checkoutTarget } from '../../utils/propertyBooking.js';
import { propertyRoomStatus } from '../../hooks/usePropertyAvailability.js';
import { DetailAction } from './DetailAction';
import { ArrowUpRight, Check, CircleCheck, CircleHelp } from 'lucide-react';
export function RoomsList({ rooms = [], selectedRoomId, onSelect, booking }) {
    return <section id="rooms-list" className="scroll-mt-28 space-y-4">
        <h2 className="font-display text-2xl text-primary">Thông tin phòng</h2>
        <p className="text-sm text-muted-foreground">Giá mỗi đêm để tham khảo. Trạng thái phòng trống theo ngày và số khách được hiển thị tại từng phòng.</p>
        {rooms.length ? <div className="grid items-stretch gap-5 sm:grid-cols-2">{rooms.map(room => {
            const status = booking?.context.status === 'valid' ? propertyRoomStatus(booking.availability, room.id) : 'unknown';
            const canBook = booking?.context.status === 'valid' && status === 'available';
            const selected = String(selectedRoomId) === String(room.id);
            return <RoomCard key={room.id} room={room} selected={selected} footer={(booking || onSelect) && <>
                {booking && <p className="flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                    {canBook ? <CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden /> : <CircleHelp className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />}
                    <span>{canBook ? 'Còn phòng theo ngày và số khách đã chọn.' : status === 'unavailable'
                        ? 'Không khả dụng theo ngày hoặc số khách đã chọn.' : 'Chưa xác nhận phòng trống.'}</span>
                </p>}
                <div className="flex flex-wrap gap-2">
                    {booking && <DetailAction asChild tone="secondary" className="flex-1 text-xs"><Link to={'/rooms/' + room.id + booking.search} state={booking.state}>Xem chi tiết phòng<ArrowUpRight aria-hidden /></Link></DetailAction>}
                    {onSelect && (!booking || canBook) && <DetailAction tone="secondary" type="button" aria-pressed={selected}
                        onClick={() => onSelect(room)} className={['flex-1 text-xs', selected ? 'border-primary/40 bg-primary/5' : ''].join(' ')}>
                        {selected && <Check aria-hidden />}{selected ? 'Đã chọn phòng này' : 'Chọn phòng'}
                    </DetailAction>}
                </div>
                {canBook && <DetailAction asChild className="w-full"><Link to={checkoutTarget(booking.propertyId, room.id, booking.context)}>Đặt phòng</Link></DetailAction>}
            </>} />;
        })}</div>
            : <p className="rounded-3xl border border-border p-8 text-sm text-muted-foreground">Chưa có phòng để hiển thị tại cơ sở này.</p>}
    </section>;
}
