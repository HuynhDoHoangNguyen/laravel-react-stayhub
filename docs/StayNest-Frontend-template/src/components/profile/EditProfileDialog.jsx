import { useEffect, useState } from "react";
import { toast } from "sonner";
import { Loader2, Calendar as CalendarIcon } from "lucide-react";
import { format } from "date-fns";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, } from "@/components/ui/select";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { useUpdateProfile } from "@/hooks/queries/useProfile";
export function EditProfileDialog({ open, onOpenChange, user }) {
    const [name, setName] = useState(user.name);
    const [dob, setDob] = useState(user.dateOfBirth ?? "");
    const [gender, setGender] = useState(user.gender ?? "");
    const { mutate, isPending } = useUpdateProfile();
    useEffect(() => {
        if (open) {
            setName(user.name);
            setDob(user.dateOfBirth ?? "");
            setGender(user.gender ?? "");
        }
    }, [open, user]);
    const handleSubmit = (e) => {
        e.preventDefault();
        if (name.trim().length < 2) {
            toast.error("Name must be at least 2 characters.");
            return;
        }
        mutate({
            name: name.trim(),
            dateOfBirth: dob || null,
            gender: (gender || null),
        }, {
            onSuccess: () => {
                toast.success("Profile updated");
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err instanceof Error ? err.message : "Update failed");
            },
        });
    };
    const parseDate = (str) => {
        if (!str)
            return undefined;
        const [y, m, d] = str.split("-").map(Number);
        return new Date(y, m - 1, d);
    };
    const formatDateStr = (date) => {
        if (!date)
            return "";
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const d = String(date.getDate()).padStart(2, "0");
        return `${y}-${m}-${d}`;
    };
    return (<Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg rounded-3xl border-border/60 bg-background p-0 sm:rounded-3xl">
        <form onSubmit={handleSubmit}>
          <DialogHeader className="border-b border-border/60 p-6">
            <DialogTitle className="font-display text-2xl text-primary">
              Edit Profile
            </DialogTitle>
            <DialogDescription className="text-[13px] text-muted-foreground">
              Update your personal information. Email and role cannot be changed here.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-5 p-6">
            <div className="space-y-2">
              <Label htmlFor="edit-name" className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                Full Name
              </Label>
              <Input id="edit-name" value={name} onChange={(e) => setName(e.target.value)} minLength={2} required className="h-11 rounded-xl border-border/70 bg-white"/>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div className="space-y-2 flex flex-col">
                <Label htmlFor="edit-dob" className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground mb-2">
                  Date of Birth
                </Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button id="edit-dob" variant="outline" className={`w-full h-11 justify-start text-left font-normal rounded-xl border border-border/70 bg-white px-3 shadow-none ${!dob && "text-muted-foreground/60"}`}>
                      <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground/70"/>
                      {dob ? (format(parseDate(dob), "PPP")) : (<span className="text-muted-foreground/60">Pick a date</span>)}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0 rounded-2xl border-border/70" align="start">
                    <Calendar mode="single" selected={parseDate(dob)} onSelect={(date) => setDob(formatDateStr(date))} disabled={(date) => date > new Date()} initialFocus captionLayout="dropdown" fromYear={1900} toYear={new Date().getFullYear()}/>
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

            <div className="space-y-2">
              <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
                Email Address
              </Label>
              <Input value={user.email} readOnly disabled className="h-11 rounded-xl border-border/70 bg-muted/40"/>
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
                </>) : ("Save Changes")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>);
}
