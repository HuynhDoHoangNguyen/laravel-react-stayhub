import { ArrowRight, Sparkles } from "lucide-react";
import { Link } from "react-router-dom";
export function PromoCard() {
    return (<div className="w-[300px] max-w-full rounded-2xl bg-background/95 p-5 shadow-[0_20px_60px_-25px_rgba(17,26,19,0.45)] ring-1 ring-black/[0.04] backdrop-blur-sm">
      <div className="flex items-start gap-3">
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-accent-pale/70 text-accent">
          <Sparkles className="h-4 w-4" aria-hidden/>
        </span>
        <div className="min-w-0">
          <h3 className="font-display text-[17px] leading-tight text-primary">
            Not a member yet?
          </h3>
          <p className="mt-0.5 text-[12.5px] leading-relaxed text-muted-foreground">
            Join StayNest and unlock exclusive deals, member rates and more.
          </p>
          <Link to="/signup" className="mt-3 inline-flex items-center gap-2 rounded-full bg-primary px-3.5 py-2 text-[12.5px] font-semibold text-primary-foreground transition-all duration-200 hover:-translate-y-[2px] hover:bg-primary-mid">
            Create an account
            <ArrowRight className="h-3.5 w-3.5" aria-hidden/>
          </Link>
        </div>
      </div>
    </div>);
}
