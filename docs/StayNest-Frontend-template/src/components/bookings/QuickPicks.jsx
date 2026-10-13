import { ChevronRight, Users, CreditCard, Receipt, UserRound, Heart, Headphones } from "lucide-react";
import { Link } from "react-router-dom";
import askBg from "@/assets/ask-staynest-bg.png";

const ITEMS = [
  { icon: Users, title: "Manage Guests", desc: "Add or update guest details", tint: "bg-[#eef1ea]", iconColor: "text-[#3a5a40]", to: "/guests" },
  { icon: CreditCard, title: "Manage Payment Methods", desc: "Add or update payment options", tint: "bg-[#efe8dc]", iconColor: "text-[#8b6f3a]" },
  { icon: Receipt, title: "My Transactions", desc: "View your payment history", tint: "bg-[#e5edf3]", iconColor: "text-[#3b6a8a]" },
  { icon: UserRound, title: "My Profile", desc: "Update your personal details", tint: "bg-[#eef1ea]", iconColor: "text-[#3a5a40]", to: "/profile" },
  { icon: Heart, title: "Wishlist", desc: "View your favorite stays", tint: "bg-[#f6e4e4]", iconColor: "text-[#b04a5a]", to: "/wishlist" },
  { icon: Headphones, title: "Support Center", desc: "Get help for your bookings", tint: "bg-[#e5eef3]", iconColor: "text-[#3b6a8a]" },
];

export function QuickPicks() {
  return (
    <aside className="space-y-6">
      <div className="rounded-2xl border border-border/60 bg-white p-4 shadow-[0_1px_2px_rgba(17,26,19,0.04)]">
        <div className="flex items-center gap-2 px-2 pb-3 pt-1">
          <span aria-hidden className="text-primary">✦</span>
          <h3 className="font-display text-xl text-primary">Quick Picks</h3>
        </div>
        <div className="space-y-1">
          {ITEMS.map((it) => {
            const Icon = it.icon;
            const inner = (
              <>
                <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-full ${it.tint}`}>
                  <Icon className={`h-4.5 w-4.5 ${it.iconColor}`} aria-hidden />
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
                  aria-hidden
                />
              </>
            );
            const className =
              "group flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors hover:bg-primary/[0.04]";
            return it.to ? (
              <Link key={it.title} to={it.to} className={className}>
                {inner}
              </Link>
            ) : (
              <button key={it.title} type="button" className={className}>
                {inner}
              </button>
            );
          })}
        </div>
      </div>

      <div className="relative overflow-hidden rounded-2xl border border-border/60 shadow-[0_1px_2px_rgba(17,26,19,0.04)]">
        <img src={askBg} alt="" aria-hidden className="absolute inset-0 h-full w-full object-cover" />
        <div
          aria-hidden
          className="absolute inset-0"
          style={{
            backgroundImage:
              "linear-gradient(to right, rgba(244,241,232,0.92) 0%, rgba(244,241,232,0.72) 35%, rgba(244,241,232,0.25) 65%, rgba(244,241,232,0) 100%)",
          }}
        />
        <div className="relative z-10 max-w-[220px] p-5">
          <h4 className="font-display text-xl leading-tight text-primary">
            Need help planning your next trip?
          </h4>
          <p className="mt-2 text-[12.5px] text-ink/75">
            Let our AI assistant help you find the perfect stay.
          </p>
          <Link
            to="/search?focus=assistant"
            className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-[12.5px] font-medium text-primary-foreground transition-colors hover:bg-primary-mid"
          >
            Ask StayNest <span aria-hidden>✦</span>
          </Link>
        </div>
      </div>
    </aside>
  );
}
