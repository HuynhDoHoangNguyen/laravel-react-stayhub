import { useState } from "react";
import { Bell, CreditCard, Shield, User as UserIcon } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Separator } from "@/components/ui/separator";
import { useAuth } from "@/hooks/useAuth";
export default function SettingsPage() {
    const { session } = useAuth();
    const [name, setName] = useState(session?.name ?? "Meera Kapoor");
    const [email, setEmail] = useState(session?.email ?? "meera@staynest.dev");
    const [phone, setPhone] = useState("+91 98200 12345");
    const [payoutIban, setPayoutIban] = useState("DE89 3704 0044 0532 0130 00");
    const [notifBookings, setNotifBookings] = useState(true);
    const [notifRefunds, setNotifRefunds] = useState(true);
    const [notifDigest, setNotifDigest] = useState(false);
    const [twoFactor, setTwoFactor] = useState(false);
    const save = (section) => () => toast.success(`${section} saved.`);
    return (<div className="mx-auto max-w-[900px] space-y-6">
      <header>
        <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
          Manager · Settings
        </p>
        <h1 className="mt-1 font-display text-3xl text-primary">Account & preferences</h1>
        <p className="mt-1 text-[14px] text-muted-foreground">
          Manage your manager profile, payout details, notifications and security.
        </p>
      </header>

      <Card icon={<UserIcon className="h-4 w-4"/>} title="Profile" description="How guests and the StayNest team reach you." onSave={save("Profile")}>
        <Field label="Full name" value={name} onChange={setName}/>
        <Field label="Email" value={email} onChange={setEmail} type="email"/>
        <Field label="Phone" value={phone} onChange={setPhone}/>
      </Card>

      <Card icon={<CreditCard className="h-4 w-4"/>} title="Payouts" description="Where confirmed bookings are settled." onSave={save("Payout details")}>
        <Field label="Bank IBAN" value={payoutIban} onChange={setPayoutIban}/>
        <p className="text-[12px] text-muted-foreground">
          Payouts run every Monday for the previous week. Refunds are deducted from the next cycle.
        </p>
      </Card>

      <Card icon={<Bell className="h-4 w-4"/>} title="Notifications" description="Choose what pings your inbox." onSave={save("Notification preferences")}>
        <Toggle label="New booking alerts" hint="Email me every time a guest confirms." value={notifBookings} onChange={setNotifBookings}/>
        <Separator />
        <Toggle label="Refund requests" hint="Email when a cancellation lands in the refund queue." value={notifRefunds} onChange={setNotifRefunds}/>
        <Separator />
        <Toggle label="Weekly performance digest" hint="Monday summary of bookings, revenue and occupancy." value={notifDigest} onChange={setNotifDigest}/>
      </Card>

      <Card icon={<Shield className="h-4 w-4"/>} title="Security" description="Protect access to your properties." onSave={save("Security settings")}>
        <Toggle label="Two-factor authentication" hint="Require a one-time code at sign-in." value={twoFactor} onChange={setTwoFactor}/>
      </Card>
    </div>);
}
function Card({ icon, title, description, children, onSave, }) {
    return (<section className="rounded-3xl border border-border/60 bg-white p-6">
      <header className="flex items-start justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-[12px] uppercase tracking-[0.18em] text-muted-foreground">
            {icon} {title}
          </div>
          <p className="mt-1 text-[13px] text-muted-foreground">{description}</p>
        </div>
        <Button className="rounded-xl" onClick={onSave}>
          Save
        </Button>
      </header>
      <div className="mt-5 space-y-4">{children}</div>
    </section>);
}
function Field({ label, value, onChange, type = "text", }) {
    return (<div className="space-y-1.5">
      <Label className="text-[12px] text-muted-foreground">{label}</Label>
      <Input type={type} value={value} onChange={(e) => onChange(e.target.value)} className="h-11 rounded-xl"/>
    </div>);
}
function Toggle({ label, hint, value, onChange, }) {
    return (<div className="flex items-start justify-between gap-4 py-1">
      <div>
        <div className="font-medium text-ink">{label}</div>
        <div className="text-[12.5px] text-muted-foreground">{hint}</div>
      </div>
      <Switch checked={value} onCheckedChange={onChange}/>
    </div>);
}
