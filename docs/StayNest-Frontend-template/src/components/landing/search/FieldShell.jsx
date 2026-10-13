import { forwardRef } from "react";
/**
 * Shared visual shell for each field in the search bar. Keeps the pill
 * layout consistent whether the field is an input or a popover trigger.
 */
export const FieldShell = forwardRef(function FieldShell({ icon: Icon, label, active, grow = 1, children, onClick }, ref) {
    return (<div ref={ref} role={onClick ? "button" : undefined} onClick={onClick} style={{ flexGrow: grow }} className={[
            "flex min-w-0 flex-1 items-center gap-3 px-5 py-4 transition-colors duration-200 cursor-pointer text-left",
            active ? "bg-accent-pale/40" : "hover:bg-surface/60",
            "first:rounded-t-3xl last:rounded-b-3xl md:first:rounded-l-3xl md:first:rounded-t-none md:last:rounded-r-3xl md:last:rounded-b-none",
        ].join(" ")}>
      <Icon className="h-4 w-4 shrink-0 text-primary/70" aria-hidden/>
      <div className="min-w-0 flex-1">
        <div className="text-[13px] font-medium text-ink">{label}</div>
        {children}
      </div>
    </div>);
});
