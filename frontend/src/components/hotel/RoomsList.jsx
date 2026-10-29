import { RoomCard } from './RoomCard';
export function RoomsList({ rooms = [] }) {
    return <section id="rooms-list" className="space-y-4">
        <h2 className="font-display text-2xl text-primary">Thông tin phòng</h2>
        <p className="text-sm text-muted-foreground">Giá mỗi đêm để tham khảo, chưa xác nhận phòng trống theo ngày hoặc số khách.</p>
        {rooms.length ? <div className="grid gap-5 sm:grid-cols-2">{rooms.map((room) => <RoomCard key={room.id} room={room} />)}</div>
            : <p className="rounded-3xl border border-border p-8 text-sm text-muted-foreground">Chưa có phòng để hiển thị tại cơ sở này.</p>}
    </section>;
}
