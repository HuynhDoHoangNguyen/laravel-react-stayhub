import { useState } from "react";
import { Link } from "react-router-dom";
import { Loader2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { ConfirmDeleteDialog } from "@/components/manager/ConfirmDeleteDialog";
import { HotelIcon, MapPinIcon, PlusIcon, StarIcon, TrashIcon, } from "@/components/manager/icons";
import { useDeleteHotel, useManagerHotels, } from "@/hooks/queries/manager";
export default function HotelsListPage() {
    const { data, isLoading, isError, refetch } = useManagerHotels({ page: 0, size: 50 });
    const deleteMut = useDeleteHotel();
    const [toDelete, setToDelete] = useState(null);
    const hotels = data?.content ?? [];
    return (<div className="mx-auto max-w-[1200px] space-y-8">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
            Manager · Properties
          </p>
          <h1 className="mt-1 font-display text-4xl leading-tight text-primary">
            My Hotels
          </h1>
          <p className="mt-1 text-[14px] text-muted-foreground">
            Manage your registered properties, rooms, inventory, and payouts.
          </p>
        </div>
        <Button asChild className="rounded-xl bg-primary hover:bg-primary-mid">
          <Link to="/manage/hotels/new">
            <PlusIcon className="h-4 w-4"/>
            Register hotel
          </Link>
        </Button>
      </header>

      {isLoading && (<div className="grid place-items-center py-20 text-muted-foreground">
          <Loader2 className="h-6 w-6 animate-spin"/>
        </div>)}

      {isError && (<div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-8 text-center">
          <p className="text-[14px] text-ink">Unable to load your hotels.</p>
          <Button onClick={() => refetch()} className="mt-4 rounded-xl">
            Retry
          </Button>
        </div>)}

      {!isLoading && !isError && hotels.length === 0 && (<div className="rounded-3xl border border-border/60 bg-white p-14 text-center">
          <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-primary/[0.06]">
            <HotelIcon className="h-6 w-6 text-primary/70" aria-hidden/>
          </div>
          <h3 className="mt-5 font-display text-2xl text-primary">
            No hotels registered yet
          </h3>
          <p className="mx-auto mt-2 max-w-md text-[13.5px] text-muted-foreground">
            Register your first property to start accepting bookings on StayNest.
          </p>
          <Button asChild className="mt-6 rounded-xl bg-primary hover:bg-primary-mid">
            <Link to="/manage/hotels/new">
              <PlusIcon className="h-4 w-4"/>
              Register hotel
            </Link>
          </Button>
        </div>)}

      {!isLoading && !isError && hotels.length > 0 && (<div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {hotels.map((hotel) => (<HotelCard key={hotel.id} hotel={hotel} onDelete={() => setToDelete(hotel)}/>))}
        </div>)}

      <ConfirmDeleteDialog open={!!toDelete} onOpenChange={(o) => !o && setToDelete(null)} title="Delete this hotel?" description="Are you sure you want to permanently delete this hotel listing? This action cannot be undone." loading={deleteMut.isPending} onConfirm={() => {
            if (!toDelete)
                return;
            deleteMut.mutate(toDelete.id, {
                onSuccess: () => {
                    toast.success("Hotel deleted");
                    setToDelete(null);
                },
                onError: (err) => {
                    if (err?.status === 401 || err?.status === 403)
                        return; // AuthGateModal handles this
                    if (err?.status === 409) {
                        toast.error("This hotel has active bookings and cannot be deleted.");
                    }
                    else {
                        toast.error(err?.message ?? "Failed to delete hotel. Please try again.");
                    }
                },
            });
        }}/>
    </div>);
}
function HotelCard({ hotel, onDelete, }) {
    const cover = hotel.photos[0];
    return (<div className="group overflow-hidden rounded-2xl border border-border/60 bg-white shadow-[0_2px_10px_-6px_rgba(17,26,19,0.15)] transition-shadow hover:shadow-[0_10px_30px_-10px_rgba(17,26,19,0.25)]">
      <Link to={`/manage/hotels/${hotel.id}`} className="block">
        <div className="relative aspect-[16/10] bg-surface">
          {cover ? (<img src={cover} alt={hotel.name} className="h-full w-full object-cover" loading="lazy"/>) : (<div className="grid h-full place-items-center text-muted-foreground">
              <HotelIcon className="h-10 w-10 opacity-30"/>
            </div>)}
          <span className={`absolute left-3 top-3 rounded-full px-2.5 py-1 text-[10.5px] font-medium uppercase tracking-[0.14em] ${hotel.active
            ? "bg-primary text-primary-foreground"
            : "bg-surface text-ink/70"}`}>
            {hotel.active ? "Live" : "Draft"}
          </span>
        </div>
      </Link>
      <div className="space-y-2 p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <Link to={`/manage/hotels/${hotel.id}`}>
              <h3 className="truncate font-display text-lg text-primary hover:underline">
                {hotel.name}
              </h3>
            </Link>
            <p className="mt-0.5 flex items-center gap-1 text-[12.5px] text-muted-foreground">
              <MapPinIcon className="h-3 w-3"/> {hotel.city}
            </p>
          </div>
          <button onClick={onDelete} className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" aria-label="Delete hotel">
            <TrashIcon className="h-4 w-4"/>
          </button>
        </div>
        <div className="flex items-center gap-3 text-[12px] text-muted-foreground">
          <span className="inline-flex items-center gap-1">
            <StarIcon className="h-3.5 w-3.5 fill-accent text-accent"/>
            {hotel.averageRating?.toFixed(1) ?? "—"}
          </span>
          <span>·</span>
          <span>{hotel.reviewCount ?? 0} reviews</span>
          <span>·</span>
          <span>{hotel.photos.length} photos</span>
        </div>
      </div>
    </div>);
}
