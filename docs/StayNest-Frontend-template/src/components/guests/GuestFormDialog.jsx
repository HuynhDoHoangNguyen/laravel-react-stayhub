import { useEffect, useState } from "react";
import { format, parse, isValid } from "date-fns";
import { toast } from "sonner";
import { CalendarIcon, Loader2 } from "lucide-react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, } from "@/components/ui/select";
import { Popover, PopoverContent, PopoverTrigger, } from "@/components/ui/popover";
import { Calendar } from "@/components/ui/calendar";
import { useCreateGuest, useUpdateGuest } from "@/hooks/queries/useGuests";
import { cn } from "@/lib/utils";
export function GuestFormDialog({ open, onOpenChange, guest }) {
    const isEdit = !!guest;
    const [name, setName] = useState("");
    const [dob, setDob] = useState("");
    const [gender, setGender] = useState("");
    const [calOpen, setCalOpen] = useState(false);
    const createMut = useCreateGuest();
    const updateMut = useUpdateGuest();
    const isPending = createMut.isPending || updateMut.isPending;
    // dob is stored as "YYYY-MM-DD" string; Date object for Calendar
    const selectedDate = dob
        ? parse(dob, "yyyy-MM-dd", new Date())
        : undefined;
    const validDate = selectedDate && isValid(selectedDate) ? selectedDate : undefined;
    useEffect(() => {
        if (open) {
            setName(guest?.name ?? "");
            setDob(guest?.dateOfBirth ?? "");
            setGender(guest?.gender ?? "");
            setCalOpen(false);
        }
    }, [open, guest]);
    const handleDaySelect = (day) => {
        if (day) {
            setDob(format(day, "yyyy-MM-dd"));
            setCalOpen(false);
        }
    };
    const handleSubmit = (e) => {
        e.preventDefault();
        if (name.trim().length < 2) {
            toast.error("Name must be at least 2 characters.");
            return;
        }
        if (!gender) {
            toast.error("Please select a gender.");
            return;
        }
        if (!dob) {
            toast.error("Please provide a date of birth.");
            return;
        }
        const body = { name: name.trim(), gender: gender, dateOfBirth: dob };
        const opts = {
            onSuccess: () => {
                toast.success(isEdit ? "Guest updated" : "Guest added");
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err instanceof Error ? err.message : "Something went wrong");
            },
        };
        if (isEdit && guest) {
            updateMut.mutate({ id: guest.id, body }, opts);
        }
        else {
            createMut.mutate(body, opts);
        }
    };
    return (<Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg rounded-3xl border-border/60 bg-background p-0 sm:rounded-3xl">
        <form onSubmit={handleSubmit}>
          <DialogHeader className="border-b border-border/60 p-6">
            <DialogTitle className="font-display text-2xl text-primary">
              {isEdit ? "Edit Guest" : "Add Guest"}
            </DialogTitle>
            <DialogDescription className="text-[13px] text-muted-foreground">
              Saved guests can be attached to bookings in one tap.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-5 p-6">
            <div className="space-y-2">
              <Label htmlFor="guest-name" className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                Full Name
              </Label>
              <Input id="guest-name" value={name} onChange={(e) => setName(e.target.value)} minLength={2} required className="h-11 rounded-xl border-border/70 bg-white"/>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              {/* ── Premium Date of Birth Picker ── */}
              <div className="space-y-2">
                <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                  Date of Birth
                </Label>
                <Popover open={calOpen} onOpenChange={setCalOpen}>
                  <PopoverTrigger asChild>
                    <Button id="guest-dob" variant="outline" type="button" className={cn("h-11 w-full justify-start rounded-xl border-border/70 bg-white px-3 text-left font-normal transition-colors hover:bg-surface/60", !validDate && "text-muted-foreground")}>
                      <CalendarIcon className="mr-2 h-4 w-4 shrink-0 opacity-50"/>
                      {validDate
            ? format(validDate, "dd MMM yyyy")
            : "Select date of birth"}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0 rounded-2xl shadow-float border border-border/60 bg-background overflow-hidden" align="start" side="bottom" sideOffset={8}>
                    <Calendar mode="single" selected={validDate} onSelect={handleDaySelect} captionLayout="dropdown" defaultMonth={validDate ?? new Date(2000, 0)} fromYear={1930} toYear={new Date().getFullYear() - 1} disabled={(date) => date >= new Date()} initialFocus className="p-3"/>
                  </PopoverContent>
                </Popover>
              </div>

              <div className="space-y-2">
                <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                  Gender
                </Label>
                <Select value={gender} onValueChange={(v) => setGender(v)}>
                  <SelectTrigger className="h-11 rounded-xl border-border/70 bg-white">
                    <SelectValue placeholder="Select gender"/>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="MALE">Male</SelectItem>
                    <SelectItem value="FEMALE">Female</SelectItem>
                    <SelectItem value="OTHER">Other</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <DialogFooter className="border-t border-border/60 bg-surface/40 p-4">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} className="rounded-xl">
              Cancel
            </Button>
            <Button type="submit" disabled={isPending} className="rounded-xl bg-primary hover:bg-primary-mid">
              {isPending ? (<>
                  <Loader2 className="h-4 w-4 animate-spin"/>
                  Saving…
                </>) : isEdit ? ("Save Changes") : ("Add Guest")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>);
}
