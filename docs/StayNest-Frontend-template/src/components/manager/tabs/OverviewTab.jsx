import { useState } from "react";
import { toast } from "sonner";
import { Loader2, Pencil, Trash2 } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { HotelProfileForm } from "@/components/manager/HotelProfileForm";
import { PhotoUploader } from "@/components/manager/PhotoUploader";
import { ConfirmDeleteDialog } from "@/components/manager/ConfirmDeleteDialog";
import { useActivateHotel, useDeleteHotel, useUpdateHotel, useUploadHotelPhoto, } from "@/hooks/queries/manager";
export function OverviewTab({ hotelId, hotel }) {
    const [editing, setEditing] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const updateMut = useUpdateHotel(hotelId);
    const activateMut = useActivateHotel(hotelId);
    const uploadMut = useUploadHotelPhoto(hotelId);
    const deleteMut = useDeleteHotel();
    const navigate = useNavigate();
    if (!hotel) {
        return (<div className="grid place-items-center py-16 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin"/>
      </div>);
    }
    return (<div className="space-y-8">
      <section className="rounded-3xl border border-border/60 bg-white p-6 sm:p-8">
        <div className="mb-5 flex items-center justify-between">
          <h2 className="font-display text-xl text-primary">Profile</h2>
          {!editing && (<Button variant="outline" className="rounded-xl" onClick={() => setEditing(true)}>
              <Pencil className="h-4 w-4"/>
              Edit profile
            </Button>)}
        </div>

        {editing ? (<HotelProfileForm initial={hotel} submitLabel="Save changes" onCancel={() => setEditing(false)} onSubmit={async (body) => {
                try {
                    await updateMut.mutateAsync(body);
                    toast.success("Profile updated");
                    setEditing(false);
                }
                catch (err) {
                    if (err?.status === 401 || err?.status === 403)
                        return;
                    toast.error(err?.message ?? "Failed to update hotel profile. Please try again.");
                }
            }}/>) : (<dl className="grid gap-4 text-[13.5px] sm:grid-cols-2">
            <Row label="Name" value={hotel.name}/>
            <Row label="City" value={hotel.city}/>
            <Row label="Address" value={hotel.contactInfo?.address ?? "—"}/>
            <Row label="Phone" value={hotel.contactInfo?.phoneNumber ?? "—"}/>
            <Row label="Email" value={hotel.contactInfo?.email ?? "—"}/>
            <Row label="Location" value={hotel.contactInfo?.location ?? "—"}/>
            <div className="sm:col-span-2">
              <dt className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                Amenities
              </dt>
              <dd className="mt-1.5 flex flex-wrap gap-1.5">
                {hotel.amenities.length === 0 && (<span className="text-muted-foreground">No amenities listed</span>)}
                {hotel.amenities.map((a) => (<span key={a} className="rounded-full border border-border/60 bg-surface px-2.5 py-1 text-[12px] text-ink">
                    {a}
                  </span>))}
              </dd>
            </div>
          </dl>)}
      </section>

      <section className="rounded-3xl border border-border/60 bg-white p-6 sm:p-8">
        <h2 className="mb-5 font-display text-xl text-primary">Gallery</h2>
        <PhotoUploader photos={hotel.photos} onUpload={async (file) => {
            const url = await uploadMut.mutateAsync(file);
            // Persist the URL onto the hotel profile so it survives refreshes.
            await updateMut.mutateAsync({
                name: hotel.name,
                city: hotel.city,
                photos: [...hotel.photos, url],
                amenities: hotel.amenities,
                contactInfo: hotel.contactInfo,
            });
            return url;
        }} onRemove={async (url) => {
            try {
                await updateMut.mutateAsync({
                    name: hotel.name,
                    city: hotel.city,
                    photos: hotel.photos.filter((p) => p !== url),
                    amenities: hotel.amenities,
                    contactInfo: hotel.contactInfo,
                });
                toast.success("Photo removed");
            }
            catch (err) {
                if (err?.status === 401 || err?.status === 403)
                    return;
                toast.error(err?.message ?? "Could not remove photo. Please try again.");
            }
        }}/>
      </section>

      <section className="rounded-3xl border border-border/60 bg-white p-6 sm:p-8">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 className="font-display text-xl text-primary">Activation</h2>
            <p className="mt-1 text-[13px] text-muted-foreground">
              Toggle your listing live to make it searchable by guests. Requires at
              least one photo and one room type.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <span className={`rounded-full px-3 py-1 text-[11px] font-medium uppercase tracking-[0.14em] ${hotel.active
            ? "bg-primary text-primary-foreground"
            : "bg-surface text-ink/70"}`}>
              {hotel.active ? "Live" : "Draft"}
            </span>
            <Switch checked={hotel.active} disabled={hotel.active || activateMut.isPending || hotel.photos.length === 0} onCheckedChange={() => {
            activateMut.mutate(undefined, {
                onSuccess: () => toast.success("Hotel is now live!"),
                onError: (err) => {
                    if (err?.status === 401 || err?.status === 403)
                        return;
                    if (err?.status === 422) {
                        toast.error("Please add at least one photo and one room before activating.");
                    }
                    else {
                        toast.error(err?.message ?? "Could not activate hotel. Please try again.");
                    }
                },
            });
        }}/>
          </div>
        </div>
      </section>

      <section className="rounded-3xl border border-destructive/30 bg-destructive/[0.03] p-6 sm:p-8">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 className="font-display text-xl text-destructive">Danger zone</h2>
            <p className="mt-1 text-[13px] text-muted-foreground">
              Permanently delete this hotel and all associated rooms, inventory, and
              analytics.
            </p>
          </div>
          <Button variant="outline" className="rounded-xl border-destructive/40 text-destructive hover:bg-destructive/10" onClick={() => setConfirmDelete(true)}>
            <Trash2 className="h-4 w-4"/>
            Delete hotel
          </Button>
        </div>
      </section>

      <ConfirmDeleteDialog open={confirmDelete} onOpenChange={setConfirmDelete} title="Delete this hotel?" description="Are you sure you want to permanently delete this hotel listing? This action cannot be undone." loading={deleteMut.isPending} onConfirm={() => {
            deleteMut.mutate(hotel.id, {
                onSuccess: () => {
                    toast.success("Hotel deleted");
                    navigate("/manage/hotels");
                },
                onError: (err) => {
                    if (err?.status === 401 || err?.status === 403)
                        return;
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
function Row({ label, value }) {
    return (<div>
      <dt className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
        {label}
      </dt>
      <dd className="mt-1 text-ink">{value}</dd>
    </div>);
}
