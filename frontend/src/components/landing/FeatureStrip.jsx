import { BadgeCheck, Headphones, Leaf, Tag } from "lucide-react";

const ITEMS = [
  {
    icon: BadgeCheck,
    title: "Bộ sưu tập tuyển chọn",
    desc: "Những không gian lưu trú độc đáo và truyền cảm hứng",
  },
  {
    icon: Leaf,
    title: "An tâm & Tin cậy",
    desc: "Sự an toàn và tiện nghi của bạn là ưu tiên hàng đầu",
  },
  {
    icon: Headphones,
    title: "Hỗ trợ tận tâm",
    desc: "Đội ngũ chăm sóc khách hàng đồng hành 24/7",
  },
  {
    icon: Tag,
    title: "Cam kết giá tốt",
    desc: "Trải nghiệm cao cấp với mức giá minh bạch, hợp lý",
  },
];

export function FeatureStrip() {
  return (
    <section aria-label="Vì sao chọn StayHub" className="bg-surface/60 py-14">
      <div className="mx-auto grid max-w-[1440px] grid-cols-1 gap-x-10 gap-y-8 px-6 sm:grid-cols-2 md:px-10 lg:grid-cols-4">
        {ITEMS.map(({ icon: Icon, title, desc }) => (
          <div key={title} className="flex items-start gap-4">
            <Icon
              className="mt-1 h-6 w-6 shrink-0 text-primary"
              strokeWidth={1.4}
              aria-hidden
            />
            <div className="min-w-0">
              <div className="text-[15px] font-semibold text-ink">{title}</div>
              <p className="mt-1 text-[14px] leading-relaxed text-muted-foreground">
                {desc}
              </p>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
