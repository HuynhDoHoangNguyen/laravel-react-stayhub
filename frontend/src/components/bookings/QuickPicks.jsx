import { ChevronRight, FileText, UserRound, Compass, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';

const ITEMS = [
  {
    icon: FileText,
    title: 'Hóa đơn & Thanh toán',
    desc: 'Xem lịch sử hóa đơn và dịch vụ',
    tint: 'bg-[#e5edf3]',
    iconColor: 'text-[#3b6a8a]',
    to: '/customer/invoices',
  },
  {
    icon: UserRound,
    title: 'Hồ sơ hội viên',
    desc: 'Cập nhật thông tin tài khoản',
    tint: 'bg-[#eef1ea]',
    iconColor: 'text-[#3a5a40]',
    to: '/customer/profile',
  },
  {
    icon: Compass,
    title: 'Khám phá chỗ nghỉ',
    desc: 'Tìm khách sạn & homestay boutique',
    tint: 'bg-[#efe8dc]',
    iconColor: 'text-[#8b6f3a]',
    to: '/search',
  },
];

export function QuickPicks() {
  return (
    <aside className="space-y-6">
      {/* Quick Links Card */}
      <div className="rounded-2xl border border-border/60 bg-white p-4 shadow-[0_1px_2px_rgba(17,26,19,0.04)]">
        <div className="flex items-center gap-2 px-2 pb-3 pt-1">
          <span aria-hidden="true" className="text-primary text-sm">✦</span>
          <h3 className="font-display text-xl font-bold text-primary">Tiện ích nhanh</h3>
        </div>
        <div className="space-y-1">
          {ITEMS.map((it) => {
            const Icon = it.icon;
            return (
              <Link
                key={it.title}
                to={it.to}
                className="group flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors hover:bg-primary/[0.04]"
              >
                <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-full ${it.tint}`}>
                  <Icon className={`h-4.5 w-4.5 ${it.iconColor}`} aria-hidden="true" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-[13.5px] font-semibold text-ink">
                    {it.title}
                  </span>
                  <span className="block truncate text-[11.5px] text-muted-foreground">
                    {it.desc}
                  </span>
                </span>
                <ChevronRight
                  className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                  aria-hidden="true"
                />
              </Link>
            );
          })}
        </div>
      </div>

      {/* Luxury Inspiration Banner */}
      <div className="relative overflow-hidden rounded-2xl border border-border/60 bg-gradient-to-br from-[#f8f5ee] via-[#f1ebe0] to-[#e7decb] p-6 shadow-[0_1px_2px_rgba(17,26,19,0.04)]">
        <div className="relative z-10">
          <div className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1 text-[10.5px] font-semibold text-primary backdrop-blur-xs">
            <Sparkles className="h-3 w-3 text-accent" />
            <span>Kỳ nghỉ tiếp theo</span>
          </div>
          <h4 className="mt-3 font-display text-xl font-bold leading-tight text-primary">
            Lên kế hoạch cho hành trình mới?
          </h4>
          <p className="mt-2 text-[12.5px] leading-relaxed text-ink/75">
            Khám phá những không gian nghỉ dưỡng boutique tuyệt đẹp và chuẩn bị cho chuyến đi tiếp theo của bạn.
          </p>
          <Link
            to="/search"
            className="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary-mid"
          >
            <span>Khám phá ngay</span>
            <span aria-hidden="true">✦</span>
          </Link>
        </div>
      </div>
    </aside>
  );
}

export default QuickPicks;
