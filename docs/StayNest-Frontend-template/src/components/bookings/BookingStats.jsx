import { CalendarCheck, BadgeCheck, Luggage, XCircle } from "lucide-react";
export function BookingStats({ bookings }) {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    const todayStr = `${year}-${month}-${day}`;
    const upcoming = bookings.filter((b) => b.bookingStatus === "CONFIRMED" && b.checkOutDate >= todayStr).length;
    const confirmed = bookings.filter((b) => b.bookingStatus === "CONFIRMED").length;
    const completed = bookings.filter((b) => b.bookingStatus === "COMPLETED" || (b.bookingStatus === "CONFIRMED" && b.checkOutDate < todayStr)).length;
    const cancelled = bookings.filter((b) => b.bookingStatus === "CANCELLED").length;
    const cards = [
        {
            icon: CalendarCheck,
            count: upcoming,
            title: "Upcoming Trips",
            hint: "Your next adventure awaits",
            tint: "bg-[#e8efe4]",
            iconColor: "text-[#3a5a40]",
        },
        {
            icon: Luggage,
            count: confirmed,
            title: "Confirmed Bookings",
            hint: "All set for your stays",
            tint: "bg-[#f2ead9]",
            iconColor: "text-[#8b6f3a]",
        },
        {
            icon: BadgeCheck,
            count: completed,
            title: "Completed Trips",
            hint: "Memories worth cherishing",
            tint: "bg-[#e2ecf3]",
            iconColor: "text-[#3b6a8a]",
        },
        {
            icon: XCircle,
            count: cancelled,
            title: "Cancelled Trips",
            hint: "View your cancellations",
            tint: "bg-[#f7e3df]",
            iconColor: "text-[#b04a3a]",
        },
    ];
    return (<div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
      {cards.map((c) => {
            const Icon = c.icon;
            return (<div key={c.title} className="group rounded-2xl border border-border/60 bg-white p-5 shadow-[0_1px_2px_rgba(17,26,19,0.04)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_12px_30px_-16px_rgba(17,26,19,0.25)]">
            <div className="flex items-start gap-4">
              <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-xl ${c.tint}`}>
                <Icon className={`h-5 w-5 ${c.iconColor}`} aria-hidden/>
              </div>
              <div className="min-w-0">
                <div className="font-display text-3xl leading-none text-primary">{c.count}</div>
                <div className="mt-1.5 text-[13px] font-semibold text-ink">{c.title}</div>
                <div className="mt-0.5 text-[11.5px] text-muted-foreground">{c.hint}</div>
              </div>
            </div>
          </div>);
        })}
    </div>);
}
