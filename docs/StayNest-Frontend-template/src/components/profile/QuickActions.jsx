import { Link } from "react-router-dom";
import { CalendarCheck, CreditCard, Heart, HelpCircle, Sparkles, Users, ChevronRight } from "lucide-react";
const ACTIONS = [
    { icon: CalendarCheck, label: "My Bookings", hint: "View your trips and reservations", to: "/bookings", tone: "bg-primary/[0.08] text-primary" },
    { icon: Users, label: "Manage Guests", hint: "Add, edit or remove guests", to: "/profile", tone: "bg-emerald-500/10 text-emerald-700" },
    { icon: CreditCard, label: "Payment Methods", hint: "Manage your saved cards", to: "/profile", tone: "bg-amber-500/10 text-amber-700" },
    { icon: Sparkles, label: "Ask StayNest", hint: "Plan your next journey with AI", to: "/search", tone: "bg-primary/[0.08] text-primary" },
    { icon: Heart, label: "Wishlist", hint: "See your favourite stays", to: "/wishlist", tone: "bg-rose-500/10 text-rose-700" },
    { icon: HelpCircle, label: "Support Center", hint: "Get help with your bookings", to: "/", tone: "bg-sky-500/10 text-sky-700" },
];
export function QuickActions() {
    return (<aside className="rounded-3xl border border-border/60 bg-white p-5 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] md:p-6">
      <div className="flex items-center gap-2 border-b border-border/60 pb-4">
        <Sparkles className="h-4 w-4 text-gold" aria-hidden/>
        <h3 className="font-display text-lg text-primary">Quick Actions</h3>
      </div>

      <ul className="mt-3 space-y-2">
        {ACTIONS.map((a) => {
            const Icon = a.icon;
            return (<li key={a.label}>
              <Link to={a.to} className="group flex items-center gap-3 rounded-2xl border border-transparent p-3 transition-all hover:-translate-y-[1px] hover:border-border/60 hover:bg-primary/[0.03]">
                <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl ${a.tone}`}>
                  <Icon className="h-4 w-4"/>
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-[13.5px] font-semibold text-ink group-hover:text-primary">
                    {a.label}
                  </p>
                  <p className="truncate text-[11.5px] text-muted-foreground">
                    {a.hint}
                  </p>
                </div>
                <ChevronRight className="h-4 w-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"/>
              </Link>
            </li>);
        })}
      </ul>
    </aside>);
}
