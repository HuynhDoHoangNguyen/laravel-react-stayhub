import { toast } from "sonner";
import { Loader2, Wallet } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useRefundPending, useSettleRefund } from "@/hooks/queries/manager";
/** hotelId is used only to filter the global queue when displayed inside a hotel workspace. */
export function RefundsTab({ hotelId }) {
    const { data, isLoading } = useRefundPending();
    const settleMut = useSettleRefund();
    const items = (data ?? []).filter((b) => hotelId == null || b.hotel?.id === hotelId);
    if (isLoading) {
        return (<div className="grid place-items-center py-16 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin"/>
      </div>);
    }
    if (items.length === 0) {
        return (<div className="rounded-3xl border border-dashed border-border bg-surface/40 p-12 text-center">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary/[0.06]">
          <Wallet className="h-5 w-5 text-primary/70"/>
        </div>
        <h3 className="mt-4 font-display text-xl text-primary">
          No refunds pending
        </h3>
        <p className="mx-auto mt-1 max-w-md text-[13px] text-muted-foreground">
          Refund requests appear here when a Stripe refund fails or a booking is
          settled off-platform.
        </p>
      </div>);
    }
    return (<div className="space-y-4">
      <h2 className="font-display text-xl text-primary">Refund queue</h2>
      <div className="grid gap-4 md:grid-cols-2">
        {items.map((b) => (<RefundCard key={b.id} booking={b} loading={settleMut.isPending} onSettle={() => settleMut.mutate(b.id, {
                onSuccess: () => toast.success(`Refund #${b.id} settled`),
                onError: (err) => toast.error(err.message),
            })}/>))}
      </div>
    </div>);
}
function RefundCard({ booking: b, loading, onSettle, }) {
    const amount = b.refundAmount ?? b.amount;
    return (<article className="space-y-3 rounded-2xl border border-border/60 bg-white p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-[12px] uppercase tracking-[0.14em] text-muted-foreground">
            Booking #{b.id}
          </div>
          <div className="font-display text-lg text-primary">
            {b.hotel?.name ?? "Hotel"}
          </div>
          <div className="text-[12.5px] text-muted-foreground">
            {b.checkInDate} → {b.checkOutDate}
          </div>
        </div>
        <div className="text-right">
          <div className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
            Refund
          </div>
          <div className="font-display text-2xl text-primary">
            ₹{amount.toFixed(2)}
          </div>
        </div>
      </div>
      {b.guests?.length ? (<div className="flex flex-wrap gap-1">
          {b.guests.map((g, i) => (<span key={i} className="rounded-full bg-surface px-2 py-0.5 text-[11.5px] text-ink">
              {g.name}
            </span>))}
        </div>) : null}
      <Button onClick={onSettle} disabled={loading} className="w-full rounded-xl bg-primary hover:bg-primary-mid">
        Confirm manual refund
      </Button>
    </article>);
}
