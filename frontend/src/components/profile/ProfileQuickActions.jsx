import { Link } from 'react-router-dom';
import {
  CalendarCheck,
  FileText,
  Compass,
  LayoutDashboard,
  Sparkles,
  ChevronRight,
} from 'lucide-react';

const ACTIONS = [
  {
    icon: CalendarCheck,
    label: 'Đơn đặt phòng của tôi',
    hint: 'Xem các kỳ nghỉ và mã giữ chỗ của bạn',
    to: '/customer/bookings',
    tone: 'bg-primary/[0.08] text-primary',
  },
  {
    icon: FileText,
    label: 'Hóa đơn & Thanh toán',
    hint: 'Theo dõi hóa đơn thanh toán lưu trú',
    to: '/customer/invoices',
    tone: 'bg-amber-500/10 text-amber-700',
  },
  {
    icon: Compass,
    label: 'Khám phá chỗ nghỉ',
    hint: 'Tìm kiếm khách sạn và homestay StayHub',
    to: '/search',
    tone: 'bg-sky-500/10 text-sky-700',
  },
  {
    icon: LayoutDashboard,
    label: 'Tổng quan tài khoản',
    hint: 'Trang tổng quan hoạt động khách hàng',
    to: '/customer',
    tone: 'bg-emerald-500/10 text-emerald-700',
  },
];

/**
 * Component ProfileQuickActions hiển thị các lối tắt hợp lệ 100% trong hệ thống:
 * Tuân thủ phong cách StayNest QuickActions với các tone màu trang nhã và micro-animation.
 * Tuyệt đối không chứa liên kết chết (dead links).
 */
export function ProfileQuickActions() {
  return (
    <aside className="rounded-3xl border border-border/60 bg-white p-5 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] md:p-6">
      <div className="flex items-center gap-2 border-b border-border/60 pb-4">
        <Sparkles className="h-4 w-4 text-accent" aria-hidden />
        <h3 className="font-display text-lg font-bold text-primary">
          Lối tắt quản lý
        </h3>
      </div>

      <ul className="mt-3 space-y-2">
        {ACTIONS.map((a) => {
          const Icon = a.icon;
          return (
            <li key={a.label}>
              <Link
                to={a.to}
                className="group flex items-center gap-3 rounded-2xl border border-transparent p-3 transition-all hover:-translate-y-[1px] hover:border-border/60 hover:bg-primary/[0.03]"
              >
                <div
                  className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl ${a.tone}`}
                >
                  <Icon className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1 text-left">
                  <p className="truncate text-[13.5px] font-semibold text-ink group-hover:text-primary">
                    {a.label}
                  </p>
                  <p className="truncate text-[11.5px] text-muted-foreground">
                    {a.hint}
                  </p>
                </div>
                <ChevronRight className="h-4 w-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
              </Link>
            </li>
          );
        })}
      </ul>
    </aside>
  );
}

export default ProfileQuickActions;
