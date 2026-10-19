import { RoomCard } from "./RoomCard";

export function RoomsList({
  rooms = [],
  nights = 1,
  selectedRoomId,
  onSelect,
}) {
  if (!rooms || rooms.length === 0) {
    return (
      <section className="rounded-3xl border border-border/70 bg-card p-6 text-center md:p-8">
        <h3 className="font-display text-lg text-ink">
          Thông tin phòng đang được cập nhật
        </h3>
        <p className="mt-1 text-sm text-muted-foreground">
          Vui lòng liên hệ trực tiếp với cơ sở lưu trú để biết thêm chi tiết về tình trạng phòng.
        </p>
      </section>
    );
  }

  return (
    <section id="rooms-list" className="space-y-4">
      <div className="flex items-end justify-between gap-4">
        <div>
          <h2 className="font-display text-xl text-primary md:text-2xl">
            Lựa chọn hạng phòng
          </h2>
          <p className="mt-1 text-[13px] text-muted-foreground">
            Giá hiển thị ước tính theo thời gian lưu trú{nights > 0 && ` — ${nights} đêm`}.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        {rooms.map((r) => (
          <RoomCard
            key={r.id}
            room={r}
            nights={nights}
            selected={selectedRoomId === r.id}
            onSelect={() => onSelect(r)}
          />
        ))}
      </div>
    </section>
  );
}
