import { RefundsTab } from "@/components/manager/tabs/RefundsTab";
export default function RefundsPage() {
    return (<div className="mx-auto max-w-[1100px] space-y-6">
      <header>
        <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
          Manager · Payouts
        </p>
        <h1 className="mt-1 font-display text-3xl text-primary">Refund queue</h1>
        <p className="mt-1 text-[14px] text-muted-foreground">
          Cancellations awaiting manual refund across all your properties.
        </p>
      </header>
      <RefundsTab />
    </div>);
}
