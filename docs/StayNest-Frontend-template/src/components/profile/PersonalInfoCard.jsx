import { Calendar, Mail, User, VenetianMask, Shield, Pencil } from "lucide-react";
function formatDate(iso) {
    if (!iso)
        return "Not provided";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime()))
        return iso;
    return d.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" });
}
function formatGender(g) {
    if (!g)
        return "Not specified";
    return g.charAt(0) + g.slice(1).toLowerCase();
}
function formatRole(roles) {
    if (roles?.includes("HOTEL_MANAGER"))
        return "Hotel Manager";
    return "Guest";
}
export function PersonalInfoCard({ user, onEdit, }) {
    const rows = [
        { icon: User, label: "Full Name", value: user.name },
        { icon: Calendar, label: "Date of Birth", value: formatDate(user.dateOfBirth) },
        { icon: Mail, label: "Email Address", value: user.email },
        { icon: VenetianMask, label: "Gender", value: formatGender(user.gender) },
        { icon: Shield, label: "Account Role", value: formatRole(user.roles) },
    ];
    return (<div className="rounded-3xl border border-border/60 bg-white p-6 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] md:p-8">
      <div className="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="grid h-10 w-10 place-items-center rounded-full bg-primary/[0.08] text-primary">
            <User className="h-4 w-4"/>
          </div>
          <h3 className="font-display text-xl text-primary md:text-2xl">
            Personal Information
          </h3>
        </div>
        <button type="button" onClick={onEdit} className="inline-flex items-center gap-2 self-start rounded-xl bg-primary px-4 py-2.5 text-[12.5px] font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary-mid hover:shadow-[0_10px_24px_-12px_rgba(17,26,19,0.4)]">
          <Pencil className="h-3.5 w-3.5"/>
          Edit Profile
        </button>
      </div>

      <dl className="mt-2 grid grid-cols-1 divide-y divide-border/60 sm:grid-cols-2 sm:divide-y-0">
        {rows.map((row, i) => {
            const Icon = row.icon;
            const isLeft = i % 2 === 0;
            return (<div key={row.label} className={`flex items-start gap-4 py-5 ${isLeft ? "sm:pr-8" : "sm:border-l sm:border-border/60 sm:pl-8"} ${i >= 2 ? "sm:border-t sm:border-border/60" : ""}`}>
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/[0.06] text-primary">
                <Icon className="h-4 w-4"/>
              </div>
              <div className="min-w-0">
                <dt className="text-[11.5px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                  {row.label}
                </dt>
                <dd className="mt-1 truncate font-display text-[17px] text-ink">
                  {row.value}
                </dd>
              </div>
            </div>);
        })}
      </dl>
    </div>);
}
