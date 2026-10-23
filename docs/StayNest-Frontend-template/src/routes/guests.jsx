import { Link } from "react-router-dom";
import { useState } from "react";
import { toast } from "sonner";
import { ArrowLeft, Loader2, Pencil, Plus, Trash2, Users } from "lucide-react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { GuestFormDialog } from "@/components/guests/GuestFormDialog";
import { useDeleteGuest, useGuests } from "@/hooks/queries/useGuests";
const TONES = [
    "bg-rose-100 text-rose-700",
    "bg-sky-100 text-sky-700",
    "bg-amber-100 text-amber-700",
    "bg-emerald-100 text-emerald-700",
    "bg-primary/[0.08] text-primary",
];
function initials(name) {
    const p = name.trim().split(/\s+/);
    return ((p[0]?.[0] ?? "") + (p[1]?.[0] ?? "")).toUpperCase();
}
function formatDob(iso) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime()))
        return iso;
    return d.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" });
}
function ageOf(iso) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime()))
        return null;
    const now = new Date();
    let a = now.getFullYear() - d.getFullYear();
    const m = now.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < d.getDate()))
        a -= 1;
    return a;
}
export default function GuestsPage() {
    const [page, setPage] = useState(0);
    const size = 12;
    const { data, isLoading, isError, refetch } = useGuests({ page, size });
    const deleteMut = useDeleteGuest();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const openAdd = () => {
        setEditing(null);
        setDialogOpen(true);
    };
    const openEdit = (g) => {
        setEditing(g);
        setDialogOpen(true);
    };
    const handleDelete = (g) => {
        if (!window.confirm(`Remove ${g.name} from your saved guests?`))
            return;
        deleteMut.mutate(g.id, {
            onSuccess: () => toast.success("Guest removed"),
            onError: (err) => {
                if (err?.status === 401 || err?.status === 403)
                    return; // AuthGateModal handles this
                if (err?.status === 409) {
                    toast.error("This guest is linked to an active booking and cannot be removed right now.");
                }
                else {
                    toast.error(err instanceof Error ? err.message : "Could not remove this guest. Please try again.");
                }
            },
        });
    };
    const guests = data?.content ?? [];
    return (<div className="min-h-screen bg-background">
      <Nav />

      <main className="mx-auto max-w-[1200px] px-4 pb-24 pt-28 sm:px-6 lg:px-10">
        <Link to="/profile" className="inline-flex items-center gap-1.5 text-[13px] text-muted-foreground transition-colors hover:text-primary">
          <ArrowLeft className="h-3.5 w-3.5"/>
          Back to profile
        </Link>

        <section className="mt-6 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
              Booking Companions
            </p>
            <h1 className="mt-2 font-display text-4xl leading-tight text-primary md:text-5xl">
              Manage Guests
            </h1>
            <p className="mt-2 max-w-xl text-[14px] text-muted-foreground">
              Save the people you travel with so you can add them to any reservation in a single tap.
            </p>
            <span className="mt-4 inline-block h-[2px] w-14 rounded-full bg-gold"/>
          </div>

          <button type="button" onClick={openAdd} className="inline-flex items-center gap-2 self-start rounded-xl bg-primary px-5 py-3 text-[13px] font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary-mid hover:shadow-[0_10px_24px_-12px_rgba(17,26,19,0.4)]">
            <Plus className="h-4 w-4"/>
            Add Guest
          </button>
        </section>

        <section className="mt-10">
          {isLoading ? (<div className="grid h-64 place-items-center text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin"/>
            </div>) : isError ? (<div className="mx-auto max-w-md rounded-2xl border border-border/60 bg-white p-8 text-center">
              <p className="text-[14px] text-ink">Unable to load your guests.</p>
              <button onClick={() => refetch()} className="mt-4 inline-flex items-center rounded-xl bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground hover:bg-primary-mid">
                Retry
              </button>
            </div>) : guests.length === 0 ? (<div className="rounded-3xl border border-dashed border-border/70 bg-white p-12 text-center">
              <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-primary/[0.08] text-primary">
                <Users className="h-5 w-5"/>
              </div>
              <h3 className="mt-5 font-display text-2xl text-primary">No saved guests yet</h3>
              <p className="mx-auto mt-2 max-w-md text-[13.5px] text-muted-foreground">
                Add family, friends, or frequent travel companions to speed up your next booking.
              </p>
              <button type="button" onClick={openAdd} className="mt-6 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[13px] font-medium text-primary-foreground hover:bg-primary-mid">
                <Plus className="h-4 w-4"/>
                Add your first guest
              </button>
            </div>) : (<>
              <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {guests.map((g, i) => {
                const age = ageOf(g.dateOfBirth);
                return (<li key={g.id} className="group rounded-3xl border border-border/60 bg-white p-5 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] transition-all hover:-translate-y-[2px] hover:border-primary/30">
                      <div className="flex items-center gap-4">
                        <div className={`grid h-14 w-14 shrink-0 place-items-center rounded-full font-display text-lg ${TONES[i % TONES.length]}`}>
                          {initials(g.name)}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate font-display text-lg text-ink">
                            {g.name}
                          </p>
                          <p className="truncate text-[12.5px] text-muted-foreground">
                            {g.gender.charAt(0) + g.gender.slice(1).toLowerCase()}
                            {age !== null ? ` · Age ${age}` : ""}
                          </p>
                        </div>
                      </div>

                      <div className="mt-4 border-t border-border/60 pt-3 text-[12.5px] text-muted-foreground">
                        Date of birth · <span className="text-ink">{formatDob(g.dateOfBirth)}</span>
                      </div>

                      <div className="mt-4 flex items-center gap-2">
                        <button type="button" onClick={() => openEdit(g)} className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-border/70 bg-background px-3 py-2 text-[12.5px] font-medium text-ink transition-colors hover:border-primary/40 hover:text-primary">
                          <Pencil className="h-3.5 w-3.5"/>
                          Edit
                        </button>
                        <button type="button" onClick={() => handleDelete(g)} disabled={deleteMut.isPending} className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-border/70 bg-background px-3 py-2 text-[12.5px] font-medium text-rose-700 transition-colors hover:border-rose-300 hover:bg-rose-50 disabled:opacity-50" aria-label={`Remove ${g.name}`}>
                          <Trash2 className="h-3.5 w-3.5"/>
                        </button>
                      </div>
                    </li>);
            })}
              </ul>

              {data && data.totalPages > 1 && (<div className="mt-8 flex items-center justify-between">
                  <p className="text-[12.5px] text-muted-foreground">
                    Page {data.number + 1} of {data.totalPages} · {data.totalElements} guests
                  </p>
                  <div className="flex items-center gap-2">
                    <button type="button" disabled={data.first} onClick={() => setPage((p) => Math.max(0, p - 1))} className="rounded-xl border border-border/70 px-4 py-2 text-[12.5px] font-medium text-ink hover:border-primary/40 hover:text-primary disabled:opacity-40">
                      Previous
                    </button>
                    <button type="button" disabled={data.last} onClick={() => setPage((p) => p + 1)} className="rounded-xl border border-border/70 px-4 py-2 text-[12.5px] font-medium text-ink hover:border-primary/40 hover:text-primary disabled:opacity-40">
                      Next
                    </button>
                  </div>
                </div>)}
            </>)}
        </section>

        <GuestFormDialog open={dialogOpen} onOpenChange={setDialogOpen} guest={editing}/>
      </main>

      <Footer />
    </div>);
}
