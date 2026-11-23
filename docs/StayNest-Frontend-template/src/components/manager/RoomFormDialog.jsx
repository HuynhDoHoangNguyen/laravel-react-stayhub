import { useEffect, useState } from "react";
import { toast } from "sonner";
import { Loader2, X } from "lucide-react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
export function RoomFormDialog({ open, onOpenChange, room, onSubmit }) {
    const isEdit = !!room;
    const [type, setType] = useState("");
    const [basePrice, setBasePrice] = useState("0");
    const [totalCount, setTotalCount] = useState("1");
    const [capacity, setCapacity] = useState("2");
    const [amenities, setAmenities] = useState([]);
    const [amenityInput, setAmenityInput] = useState("");
    const [submitting, setSubmitting] = useState(false);
    useEffect(() => {
        if (!open)
            return;
        setType(room?.type ?? "");
        setBasePrice(String(room?.basePrice ?? 0));
        setTotalCount(String(room?.totalCount ?? 1));
        setCapacity(String(room?.capacity ?? 2));
        setAmenities(room?.amenities ?? []);
    }, [open, room]);
    const addAmenity = () => {
        const v = amenityInput.trim();
        if (!v)
            return;
        if (!amenities.includes(v))
            setAmenities([...amenities, v]);
        setAmenityInput("");
    };
    const handleSubmit = async (e) => {
        e.preventDefault();
        if (type.trim().length < 2)
            return toast.error("Room type name is required.");
        const price = Number(basePrice);
        const count = Number(totalCount);
        const cap = Number(capacity);
        if (!Number.isFinite(price) || price < 0)
            return toast.error("Base price must be ≥ 0.");
        if (!Number.isInteger(count) || count < 1)
            return toast.error("Total count must be at least 1.");
        if (!Number.isInteger(cap) || cap < 1)
            return toast.error("Capacity must be at least 1 guest.");
        setSubmitting(true);
        try {
            await onSubmit({
                type: type.trim(),
                basePrice: price,
                totalCount: count,
                capacity: cap,
                photos: room?.photos ?? [],
                amenities,
            });
            onOpenChange(false);
        }
        finally {
            setSubmitting(false);
        }
    };
    return (<Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg rounded-3xl border-border/60 bg-background p-0 sm:rounded-3xl">
        <form onSubmit={handleSubmit}>
          <DialogHeader className="border-b border-border/60 p-6">
            <DialogTitle className="font-display text-2xl text-primary">
              {isEdit ? "Edit Room Category" : "Add Room Category"}
            </DialogTitle>
            <DialogDescription className="text-[13px] text-muted-foreground">
              Configure a room type, base nightly price, and inventory count.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-5 p-6">
            <div className="space-y-2">
              <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                Room Type
              </Label>
              <Input value={type} onChange={(e) => setType(e.target.value)} placeholder="Deluxe Villa with Pool" className="h-11 rounded-xl border-border/70 bg-white"/>
            </div>
            <div className="grid gap-5 sm:grid-cols-3">
              <div className="space-y-2">
                <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                  Base Price / Night
                </Label>
                <Input type="number" min={0} step="0.01" value={basePrice} onChange={(e) => setBasePrice(e.target.value)} className="h-11 rounded-xl border-border/70 bg-white"/>
              </div>
              <div className="space-y-2">
                <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                  Total Rooms
                </Label>
                <Input type="number" min={1} step={1} value={totalCount} onChange={(e) => setTotalCount(e.target.value)} className="h-11 rounded-xl border-border/70 bg-white"/>
              </div>
              <div className="space-y-2">
                <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                  Max Guests
                </Label>
                <Input type="number" min={1} step={1} value={capacity} onChange={(e) => setCapacity(e.target.value)} className="h-11 rounded-xl border-border/70 bg-white"/>
              </div>
            </div>

            <div className="space-y-2">
              <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                Amenities
              </Label>
              <div className="flex flex-wrap gap-2">
                {amenities.map((a) => (<span key={a} className="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-surface px-3 py-1 text-[12.5px] text-ink">
                    {a}
                    <button type="button" onClick={() => setAmenities(amenities.filter((x) => x !== a))} className="text-muted-foreground hover:text-destructive">
                      <X className="h-3 w-3"/>
                    </button>
                  </span>))}
              </div>
              <div className="flex gap-2">
                <Input value={amenityInput} onChange={(e) => setAmenityInput(e.target.value)} onKeyDown={(e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                addAmenity();
            }
        }} placeholder="King Bed, Private Pool…" className="h-11 flex-1 rounded-xl border-border/70 bg-white"/>
                <Button type="button" variant="outline" onClick={addAmenity} className="rounded-xl">
                  Add
                </Button>
              </div>
            </div>
          </div>

          <DialogFooter className="border-t border-border/60 bg-surface/40 p-4">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} className="rounded-xl">
              Cancel
            </Button>
            <Button type="submit" disabled={submitting} className="rounded-xl bg-primary hover:bg-primary-mid">
              {submitting ? (<>
                  <Loader2 className="h-4 w-4 animate-spin"/>
                  Saving…
                </>) : isEdit ? ("Save Changes") : ("Create Room")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>);
}
