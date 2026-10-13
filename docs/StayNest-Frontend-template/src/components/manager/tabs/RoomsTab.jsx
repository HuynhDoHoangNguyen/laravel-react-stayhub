import { useState } from "react";
import { toast } from "sonner";
import { DoorOpen, Pencil, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { PhotoUploader } from "@/components/manager/PhotoUploader";
import { RoomFormDialog } from "@/components/manager/RoomFormDialog";
import { ConfirmDeleteDialog } from "@/components/manager/ConfirmDeleteDialog";
import { useCreateRoom, useDeleteRoom, useManagerRooms, useUpdateRoom, useUploadRoomPhoto, } from "@/hooks/queries/manager";
export function RoomsTab({ hotelId }) {
    const { data: rooms = [] } = useManagerRooms(hotelId);
    const createMut = useCreateRoom(hotelId);
    const updateMut = useUpdateRoom(hotelId);
    const deleteMut = useDeleteRoom(hotelId);
    const uploadMut = useUploadRoomPhoto(hotelId);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [toDelete, setToDelete] = useState(null);
    return (<div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-display text-xl text-primary">Room categories</h2>
          <p className="text-[13px] text-muted-foreground">
            Configure room types, capacities, and nightly base prices.
          </p>
        </div>
        <Button className="rounded-xl bg-primary hover:bg-primary-mid" onClick={() => {
            setEditing(null);
            setFormOpen(true);
        }}>
          <Plus className="h-4 w-4"/>
          Add room type
        </Button>
      </div>

      {rooms.length === 0 && (<div className="rounded-3xl border border-dashed border-border bg-surface/40 p-12 text-center">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary/[0.06]">
            <DoorOpen className="h-5 w-5 text-primary/70"/>
          </div>
          <h3 className="mt-4 font-display text-xl text-primary">No rooms yet</h3>
          <p className="mx-auto mt-1 max-w-md text-[13px] text-muted-foreground">
            Add at least one room category so guests can book your property.
          </p>
        </div>)}

      <div className="grid gap-5 lg:grid-cols-2">
        {rooms.map((room) => (<article key={room.id} className="space-y-4 rounded-2xl border border-border/60 bg-white p-5">
            <header className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <h3 className="truncate font-display text-lg text-primary">
                  {room.type}
                </h3>
                <p className="mt-1 text-[13px] text-muted-foreground">
                  ₹{room.basePrice.toFixed(2)} / night · {room.totalCount} rooms
                </p>
              </div>
              <div className="flex items-center gap-1.5">
                <button onClick={() => {
                setEditing(room);
                setFormOpen(true);
            }} className="rounded-lg p-1.5 text-muted-foreground hover:bg-surface hover:text-primary" aria-label="Edit room">
                  <Pencil className="h-4 w-4"/>
                </button>
                <button onClick={() => setToDelete(room)} className="rounded-lg p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" aria-label="Delete room">
                  <Trash2 className="h-4 w-4"/>
                </button>
              </div>
            </header>

            {room.amenities.length > 0 && (<div className="flex flex-wrap gap-1.5">
                {room.amenities.map((a) => (<span key={a} className="rounded-full border border-border/60 bg-surface px-2.5 py-1 text-[11.5px] text-ink">
                    {a}
                  </span>))}
              </div>)}

            <PhotoUploader label="Photos" photos={room.photos} onUpload={(file) => uploadMut.mutateAsync({ roomId: room.id, file })}/>
          </article>))}
      </div>

      <RoomFormDialog open={formOpen} onOpenChange={setFormOpen} room={editing} onSubmit={async (body) => {
            try {
                if (editing) {
                    await updateMut.mutateAsync({ roomId: editing.id, body });
                    toast.success("Room updated");
                }
                else {
                    await createMut.mutateAsync(body);
                    toast.success("Room added");
                }
            }
            catch (err) {
                toast.error(err instanceof Error ? err.message : "Failed");
                throw err;
            }
        }}/>

      <ConfirmDeleteDialog open={!!toDelete} onOpenChange={(o) => !o && setToDelete(null)} title="Delete this room category?" description="This removes the room type and its inventory calendar. Existing bookings are unaffected." loading={deleteMut.isPending} onConfirm={() => {
            if (!toDelete)
                return;
            deleteMut.mutate(toDelete.id, {
                onSuccess: () => {
                    toast.success("Room deleted");
                    setToDelete(null);
                },
                onError: (err) => toast.error(err.message),
            });
        }}/>
    </div>);
}
