import { ShieldCheck, Headphones, Tag, Ban, Users } from "lucide-react";
const ITEMS = [
    {
        icon: ShieldCheck,
        title: "Secure Booking",
        sub: "Your information is protected",
    },
    { icon: Headphones, title: "24/7 Support", sub: "We're here to help anytime" },
    { icon: Tag, title: "Best Price Guarantee", sub: "Get the best rates only with us" },
    { icon: Ban, title: "Free Cancellation", sub: "On most properties" },
    { icon: Users, title: "Trusted by travelers", sub: "4.6/5 average rating" },
];
export function BenefitsStrip() {
    return (<section className="border-t border-border/60 bg-surface/40">
      <div className="mx-auto grid max-w-[1440px] grid-cols-1 gap-6 px-6 py-8 md:grid-cols-3 md:px-10 lg:grid-cols-5">
        {ITEMS.map((b) => (<div key={b.title} className="flex items-center gap-3">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-background text-primary ring-1 ring-black/[0.04]">
              <b.icon className="h-4 w-4" aria-hidden/>
            </div>
            <div className="min-w-0">
              <div className="truncate text-[13px] font-medium text-ink">
                {b.title}
              </div>
              <div className="truncate text-[11.5px] text-muted-foreground">
                {b.sub}
              </div>
            </div>
          </div>))}
      </div>
    </section>);
}
