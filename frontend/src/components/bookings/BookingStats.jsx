import { CalendarCheck, BadgeCheck, Luggage, XCircle } from 'lucide-react';

/**
 * Hiển thị 4 thẻ thống kê trạng thái kỳ nghỉ của khách hàng theo phong cách StayNest Quiet Luxury.
 * QUY TẮC BẢO TOÀN DỮ LIỆU:
 * - Nhận toàn bộ tập dữ liệu bookings để tính toán (không tính từ 1 trang phân trang).
 */
export function BookingStats({ stats, bookings = [] }) {
  const allList = Array.isArray(bookings) ? bookings : [];

  // Không có aggregate metadata hoặc tập dữ liệu đầy đủ -> Không hiển thị KPI thiếu chính xác
  if (!stats && allList.length === 0) {
    return null;
  }

  // 1. Sắp diễn ra: Trạng thái CONFIRMED
  const upcoming = stats?.upcoming ?? allList.filter((b) => b.status === 'CONFIRMED').length;

  // 2. Đang lưu trú: Trạng thái CHECKED_IN (không gộp vào Sắp tới)
  const checkedIn =
    (stats?.checkedIn ?? stats?.checked_in) ??
    allList.filter((b) => b.status === 'CHECKED_IN').length;

  // 3. Đã hoàn thành: Trạng thái COMPLETED do Backend trả về (không tự suy đoán)
  const completed = stats?.completed ?? allList.filter((b) => b.status === 'COMPLETED').length;

  // 4. Đã hủy hoặc Bị từ chối: CANCELLED hoặc REJECTED
  const cancelled = stats
    ? (stats.cancelled ?? 0) + (stats.rejected ?? 0)
    : allList.filter((b) => b.status === 'CANCELLED' || b.status === 'REJECTED').length;

  const cards = [
    {
      icon: CalendarCheck,
      count: upcoming,
      title: 'Sắp diễn ra',
      hint: 'Kỳ nghỉ đã xác nhận',
      tint: 'bg-[#e8efe4]',
      iconColor: 'text-[#3a5a40]',
    },
    {
      icon: Luggage,
      count: checkedIn,
      title: 'Đang lưu trú',
      hint: 'Kỳ nghỉ đang diễn ra',
      tint: 'bg-[#f2ead9]',
      iconColor: 'text-[#8b6f3a]',
    },
    {
      icon: BadgeCheck,
      count: completed,
      title: 'Đã hoàn thành',
      hint: 'Kỷ niệm lưu trú tuyệt vời',
      tint: 'bg-[#e2ecf3]',
      iconColor: 'text-[#3b6a8a]',
    },
    {
      icon: XCircle,
      count: cancelled,
      title: 'Đã hủy & Từ chối',
      hint: 'Lịch sử đơn không thực hiện',
      tint: 'bg-[#f7e3df]',
      iconColor: 'text-[#b04a3a]',
    },
  ];

  return (
    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
      {cards.map((c) => {
        const Icon = c.icon;
        return (
          <div
            key={c.title}
            className="group rounded-2xl border border-border/60 bg-white p-5 shadow-[0_1px_2px_rgba(17,26,19,0.04)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_12px_30px_-16px_rgba(17,26,19,0.18)]"
          >
            <div className="flex items-start gap-4">
              <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-xl ${c.tint}`}>
                <Icon className={`h-5 w-5 ${c.iconColor}`} aria-hidden="true" />
              </div>
              <div className="min-w-0">
                <div className="font-display text-3xl leading-none text-primary">{c.count}</div>
                <div className="mt-1.5 text-[13px] font-semibold text-ink">{c.title}</div>
                <div className="mt-0.5 text-[11.5px] text-muted-foreground truncate">{c.hint}</div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default BookingStats;
