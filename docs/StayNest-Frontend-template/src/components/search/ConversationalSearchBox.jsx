import { Sparkles, Send } from "lucide-react";
import { useState } from "react";
export function ConversationalSearchBox({ onSubmit, title, placeholder = "Ask StayNest anything… (e.g. Find me a luxury stay in Bali next week)", defaultValue = "", compact = false, disabled = false, }) {
    const [value, setValue] = useState(defaultValue);
    const handleSubmit = (e) => {
        e.preventDefault();
        const q = value.trim();
        if (!q || disabled)
            return;
        onSubmit(q);
    };
    return (<div className={compact ? "" : "rounded-3xl bg-card p-6 md:p-7 shadow-[0_20px_60px_-30px_rgba(17,26,19,0.25)] ring-1 ring-black/[0.04]"}>
      {title ? (<h2 className="font-display text-[22px] md:text-[24px] text-ink mb-4">
          {title}
        </h2>) : null}
      <form onSubmit={handleSubmit} className={[
            "flex items-center gap-3 rounded-2xl bg-accent-pale/40 pl-4 pr-2 py-2 ring-1 ring-black/[0.04] transition-shadow",
            "focus-within:ring-primary/40 focus-within:shadow-[0_10px_30px_-15px_rgba(17,26,19,0.25)]",
        ].join(" ")}>
        <Sparkles className="h-4 w-4 shrink-0 text-primary/70" aria-hidden/>
        <input type="text" value={value} onChange={(e) => setValue(e.target.value)} placeholder={placeholder} disabled={disabled} className="min-w-0 flex-1 bg-transparent py-2 text-[14px] text-ink placeholder:text-muted-foreground/80 focus:outline-none disabled:opacity-60"/>
        <button type="submit" disabled={disabled || !value.trim()} aria-label="Ask StayNest" className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary-mid disabled:opacity-50 disabled:pointer-events-none">
          <Send className="h-4 w-4" aria-hidden/>
        </button>
      </form>
    </div>);
}
