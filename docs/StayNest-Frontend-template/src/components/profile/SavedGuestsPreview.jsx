import { Link } from "react-router-dom";
import { Users, ArrowRight, Loader2 } from "lucide-react";
import { useGuests } from "@/hooks/queries/useGuests";
function initials(name) {
    const p = name.trim().split(/\s+/);
    return ((p[0]?.[0] ?? "") + (p[1]?.[0] ?? "")).toUpperCase();
}
function ageFromDob(dob) {
    const d = new Date(dob);
    if (Number.isNaN(d.getTime()))
        return null;
    const now = new Date();
    let a = now.getFullYear() - d.getFullYear();
    const m = now.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < d.getDate()))
        a -= 1;
    return a;
}
const TONES = [
    "bg-rose-100 text-rose-700",
    "bg-sky-100 text-sky-700",
    "bg-amber-100 text-amber-700",
    "bg-emerald-100 text-emerald-700",
];
export function SavedGuestsPreview() {
    const { data, isLoading, isError } = useGuests({ page: 0, size: 3 });
    const guests = data?.content ?? [];
    return (<section className="rounded-3xl border border-border/60 bg-white p-6 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] md:p-8">
      <div className="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="grid h-10 w-10 place-items-center rounded-full bg-primary/[0.08] text-primary">
            <Users className="h-4 w-4"/>
          </div>
          <div>
            <h3 className="font-display text-xl text-primary md:text-2xl">
              Saved Guests
              {data && data.totalElements > 0 && (<span className="ml-2 align-middle text-[12px] font-medium text-muted-foreground">
                  ({data.totalElements})
                </span>)}
            </h3>
            <p className="mt-0.5 text-[12.5px] text-muted-foreground">
              Quickly add these companions to any future booking.
            </p>
          </div>
        </div>

        <Link to="/guests" className="group inline-flex items-center gap-1.5 self-start text-[13px] font-medium text-primary transition-colors hover:text-primary-mid">
          Manage Guests
          <ArrowRight className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"/>
        </Link>
      </div>

      {isLoading ? (<div className="grid h-28 place-items-center text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin"/>
        </div>) : isError ? (<p className="mt-5 text-[13px] text-muted-foreground">
          Unable to load saved guests.
        </p>) : guests.length === 0 ? (<div className="mt-5 rounded-2xl border border-dashed border-border/70 bg-background/60 p-6 text-center">
          <p className="text-[13.5px] text-ink">
            You haven't saved any guests yet.
          </p>
          <Link to="/guests" className="mt-3 inline-flex items-center gap-1.5 text-[12.5px] font-medium text-primary hover:text-primary-mid">
            Add your first guest
            <ArrowRight className="h-3.5 w-3.5"/>
          </Link>
        </div>) : (<ul className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {guests.map((g, i) => {
                const age = ageFromDob(g.dateOfBirth);
                return (<li key={g.id} className="group relative flex items-center gap-3 rounded-2xl border border-border/60 bg-background/60 p-3 transition-all hover:-translate-y-[1px] hover:border-primary/30 hover:shadow-[0_10px_28px_-18px_rgba(17,26,19,0.35)]">
                <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-full font-display text-[15px] ${TONES[i % TONES.length]}`}>
                  {initials(g.name)}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate font-display text-[15.5px] text-ink">
                    {g.name}
                  </p>
                  <p className="truncate text-[12px] text-muted-foreground">
                    {g.gender.charAt(0) + g.gender.slice(1).toLowerCase()}
                    {age !== null ? ` · Age ${age}` : ""}
                  </p>
                </div>
              </li>);
            })}
        </ul>)}
    </section>);
}
