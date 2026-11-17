import { RoomCard } from './RoomCard';
export function RoomsList({ rooms = [], selectedRoomId, onSelect }) {
    return <section id="rooms-list" className="space-y-4">
        <h2 className="font-display text-2xl text-primary">Thông tin phòng</h2>
        <p className="text-sm text-muted-foreground">Giá mỗi đêm để tham khảo, chưa xác nhận phòng trống theo ngày hoặc số khách.</p>
        {rooms.length ? <div className="grid gap-5 sm:grid-cols-2">{rooms.map((room) => <div key={room.id} className="space-y-3">
            <RoomCard room={room} />
            {onSelect && <button type="button" aria-pressed={String(selectedRoomId) === String(room.id)} onClick={() => onSelect(room)} className="w-full rounded-xl border border-primary px-4 py-2 text-sm font-medium text-primary hover:bg-primary/5">
                {String(selectedRoomId) === String(room.id) ? 'Đã chọn phòng này' : 'Chọn phòng'}
            </button>}
        </div>)}</div>
            : <p className="rounded-3xl border border-border p-8 text-sm text-muted-foreground">Chưa có phòng để hiển thị tại cơ sở này.</p>}
    </section>;
}
