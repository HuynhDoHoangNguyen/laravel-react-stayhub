import { DoorOpen } from 'lucide-react';

const vndFormatter = new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
  maximumFractionDigits: 0,
});

export function CheckoutPriceSummary({
  room,
  nights = 1,
  roomTotal = 0,
}) {
  const nightlyPrice = room ? Number(room.price || room.price_per_night || 0) : 0;
  const validNights = Math.max(1, Number(nights) || 1);

  return (
    <aside className="space-y-5 rounded-3xl border border-border/70 bg-white p-6 shadow-[0_20px_50px_-24px_rgba(26,46,32,0.22)] ring-1 ring-black/[0.03]">
      <div>
        <p className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
          ✦ Tạm tính kỳ nghỉ
        </p>
        <h3 className="mt-1 font-display text-xl font-bold text-primary">
          Chi tiết dự toán
        </h3>
      </div>

      {/* Selected Room Tag */}
      {room && (
        <div className="flex items-center gap-3 rounded-2xl bg-surface/70 p-3.5 border border-border/60">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/[0.08] text-primary">
            <DoorOpen className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <div className="truncate text-xs font-bold text-ink">
              {room.name || room.roomTypeName || 'Hạng phòng nghỉ'}
            </div>
            <div className="text-[11px] text-muted-foreground truncate">
              {room.roomTypeName || 'Phòng tiêu chuẩn'} · Sức chứa tối đa {room.capacity || 2} khách
            </div>
          </div>
        </div>
      )}

      {/* Breakdown Items */}
      <div className="space-y-3 text-xs text-muted-foreground border-t border-border/60 pt-4">
        <div className="flex items-center justify-between">
          <span>
            {vndFormatter.format(nightlyPrice)} × {validNights} đêm
          </span>
          <span className="font-semibold text-ink">
            {vndFormatter.format(roomTotal)}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span>Quy mô phòng</span>
          <span className="font-medium text-ink">1 phòng</span>
        </div>
      </div>

      {/* Total Section */}
      <div className="border-t border-border/60 pt-4">
        <div className="flex items-baseline justify-between">
          <div>
            <div className="text-[13px] font-bold text-ink">Dự toán tiền phòng</div>
            <div className="text-[11px] text-muted-foreground">Tính theo đơn giá và số đêm đã chọn</div>
          </div>
          <div className="text-right">
            <div className="font-display text-2xl font-bold text-primary">
              {vndFormatter.format(roomTotal)}
            </div>
          </div>
        </div>
      </div>

      {/* Factual Note */}
      <div className="rounded-2xl bg-surface/70 p-3.5 text-[11px] text-muted-foreground leading-relaxed border border-border/60">
        Chi phí trên là ước tính tiền phòng dựa trên đơn giá niêm yết của hạng phòng và khoảng thời gian lưu trú được chọn.
      </div>
    </aside>
  );
}

export default CheckoutPriceSummary;
