import { ShieldCheck, Building2, Tag, Compass, Users } from "lucide-react";

const ITEMS = [
  {
    icon: ShieldCheck,
    title: "Thông tin rõ ràng",
    sub: "Phòng, sức chứa và tiện nghi",
  },
  {
    icon: Compass,
    title: "Dễ dàng khám phá",
    sub: "Lựa chọn theo nhu cầu lưu trú",
  },
  {
    icon: Tag,
    title: "Giá tham khảo",
    sub: "Hiển thị theo từng đêm",
  },
  {
    icon: Users,
    title: "Đánh giá cơ sở",
    sub: "Điểm và số đánh giá thực tế",
  },
  {
    icon: Building2,
    title: "Thông tin cơ sở",
    sub: "Xem mô tả và hình ảnh chi tiết",
  },
];

export function BenefitsStrip() {
  return (
    <section className="border-t border-border/60 bg-surface/40">
      <div className="mx-auto grid max-w-[1440px] grid-cols-1 gap-6 px-6 py-8 md:grid-cols-3 md:px-10 lg:grid-cols-5">
        {ITEMS.map((b) => (
          <div key={b.title} className="flex items-center gap-3">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-background text-primary ring-1 ring-black/[0.04]">
              <b.icon className="h-4 w-4" aria-hidden />
            </div>
            <div className="min-w-0">
              <div className="truncate text-[13px] font-medium text-ink">
                {b.title}
              </div>
              <div className="truncate text-[11.5px] text-muted-foreground">
                {b.sub}
              </div>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
