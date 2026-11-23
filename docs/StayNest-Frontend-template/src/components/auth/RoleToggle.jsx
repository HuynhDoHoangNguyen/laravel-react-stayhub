import { Building2, User } from "lucide-react";
/**
 * Small on/off toggle used on the auth screens to switch between
 * guest and hotel manager flows. Matches the primary/ink theme.
 */
export function RoleToggle({ asManager, onChange, guestLabel = "Guest", managerLabel = "Hotel manager", hint, }) {
    return (<div className="w-full">
      <div role="group" aria-label="Account type" className="relative flex h-[46px] w-full items-center rounded-[14px] border border-border bg-surface/60 p-1">
        {/* Sliding indicator */}
        <span aria-hidden className={`absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-[10px] bg-primary shadow-[0_6px_18px_-8px_rgba(17,26,19,0.55)] transition-transform duration-300 ease-out ${asManager ? "translate-x-[calc(100%+0px)]" : "translate-x-0"}`} style={{ left: 4 }}/>

        <button type="button" onClick={() => onChange(false)} aria-pressed={!asManager} className={`relative z-10 flex h-full flex-1 items-center justify-center gap-2 rounded-[10px] text-[13px] font-medium transition-colors duration-200 ${!asManager ? "text-primary-foreground" : "text-ink/70 hover:text-ink"}`}>
          <User className="h-[15px] w-[15px]" aria-hidden/>
          {guestLabel}
        </button>
        <button type="button" onClick={() => onChange(true)} aria-pressed={asManager} className={`relative z-10 flex h-full flex-1 items-center justify-center gap-2 rounded-[10px] text-[13px] font-medium transition-colors duration-200 ${asManager ? "text-primary-foreground" : "text-ink/70 hover:text-ink"}`}>
          <Building2 className="h-[15px] w-[15px]" aria-hidden/>
          {managerLabel}
        </button>
      </div>
      {hint && (<p className="mt-1.5 text-[11.5px] leading-relaxed text-muted-foreground">
          {hint}
        </p>)}
    </div>);
}
