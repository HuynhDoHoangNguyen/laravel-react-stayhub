import { useEffect, useState } from "react";
import { toast } from "sonner";
import { Loader2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
export function HotelProfileForm({ initial, photos = [], submitLabel = "Save", onSubmit, onCancel, }) {
    const [name, setName] = useState("");
    const [city, setCity] = useState("");
    const [address, setAddress] = useState("");
    const [phone, setPhone] = useState("");
    const [email, setEmail] = useState("");
    const [location, setLocation] = useState("");
    const [amenities, setAmenities] = useState([]);
    const [amenityInput, setAmenityInput] = useState("");
    const [submitting, setSubmitting] = useState(false);
    useEffect(() => {
        setName(initial?.name ?? "");
        setCity(initial?.city ?? "");
        setAddress(initial?.contactInfo?.address ?? "");
        setPhone(initial?.contactInfo?.phoneNumber ?? "");
        setEmail(initial?.contactInfo?.email ?? "");
        setLocation(initial?.contactInfo?.location ?? "");
        setAmenities(initial?.amenities ?? []);
    }, [initial]);
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
        if (name.trim().length < 2)
            return toast.error("Hotel name is required.");
        if (city.trim().length < 2)
            return toast.error("City is required.");
        if (!email.includes("@"))
            return toast.error("Valid email is required.");
        if (phone.trim().length < 5)
            return toast.error("Phone number is required.");
        if (address.trim().length < 3)
            return toast.error("Address is required.");
        setSubmitting(true);
        try {
            await onSubmit({
                name: name.trim(),
                city: city.trim(),
                photos: initial?.photos ?? photos,
                amenities,
                contactInfo: {
                    address: address.trim(),
                    phoneNumber: phone.trim(),
                    email: email.trim(),
                    location: location.trim() || city.trim(),
                },
            });
        }
        finally {
            setSubmitting(false);
        }
    };
    return (<form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid gap-5 md:grid-cols-2">
        <Field label="Hotel Name" htmlFor="hotel-name">
          <Input id="hotel-name" value={name} onChange={(e) => setName(e.target.value)} placeholder="The Kayon Resort" className="h-11 rounded-xl border-border/70 bg-white"/>
        </Field>
        <Field label="City" htmlFor="hotel-city">
          <Input id="hotel-city" value={city} onChange={(e) => setCity(e.target.value)} placeholder="Bali" className="h-11 rounded-xl border-border/70 bg-white"/>
        </Field>
      </div>

      <Field label="Address" htmlFor="hotel-address">
        <Input id="hotel-address" value={address} onChange={(e) => setAddress(e.target.value)} placeholder="Ubud, Bali, Indonesia" className="h-11 rounded-xl border-border/70 bg-white"/>
      </Field>

      <div className="grid gap-5 md:grid-cols-3">
        <Field label="Phone Number" htmlFor="hotel-phone">
          <Input id="hotel-phone" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+62 361 9081915" className="h-11 rounded-xl border-border/70 bg-white"/>
        </Field>
        <Field label="Email" htmlFor="hotel-email">
          <Input id="hotel-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="info@hotel.com" className="h-11 rounded-xl border-border/70 bg-white"/>
        </Field>
        <Field label="Location tag" htmlFor="hotel-location">
          <Input id="hotel-location" value={location} onChange={(e) => setLocation(e.target.value)} placeholder="Ubud, Bali" className="h-11 rounded-xl border-border/70 bg-white"/>
        </Field>
      </div>

      <div className="space-y-2">
        <Label className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
          Amenities
        </Label>
        <div className="flex flex-wrap gap-2">
          {amenities.map((a) => (<span key={a} className="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-surface px-3 py-1 text-[12.5px] text-ink">
              {a}
              <button type="button" onClick={() => setAmenities(amenities.filter((x) => x !== a))} className="text-muted-foreground hover:text-destructive" aria-label={`Remove ${a}`}>
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
        }} placeholder="Wi-Fi, Pool, Spa…" className="h-11 flex-1 rounded-xl border-border/70 bg-white"/>
          <Button type="button" variant="outline" onClick={addAmenity} className="rounded-xl">
            Add
          </Button>
        </div>
      </div>

      <div className="flex items-center justify-end gap-3 border-t border-border/60 pt-4">
        {onCancel && (<Button type="button" variant="outline" onClick={onCancel} className="rounded-xl">
            Cancel
          </Button>)}
        <Button type="submit" disabled={submitting} className="rounded-xl bg-primary hover:bg-primary-mid">
          {submitting ? (<>
              <Loader2 className="h-4 w-4 animate-spin"/>
              Saving…
            </>) : (submitLabel)}
        </Button>
      </div>
    </form>);
}
function Field({ label, htmlFor, children, }) {
    return (<div className="space-y-2">
      <Label htmlFor={htmlFor} className="text-[12px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
        {label}
      </Label>
      {children}
    </div>);
}
