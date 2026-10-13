import { Globe, Headset } from "lucide-react";
export function AuthTopBar({ right }) {
    return (<div className="flex items-center justify-end gap-5 px-6 pt-5 md:px-10">
      <button type="button" className="inline-flex items-center gap-1.5 text-[13px] font-medium text-ink/80 transition-colors hover:text-primary">
        <Globe className="h-4 w-4" aria-hidden/>
        EN
      </button>
      {right ?? (<>
          <a href="#" className="text-[13px] font-medium text-ink/80 transition-colors hover:text-primary">
            Need help?
          </a>
          <a href="#" className="inline-flex items-center gap-2 rounded-full border border-border bg-background px-4 py-2 text-[13px] font-medium text-ink transition-all duration-200 hover:-translate-y-[1px] hover:border-primary/40 hover:text-primary">
            <Headset className="h-4 w-4" aria-hidden/>
            Contact us
          </a>
        </>)}
    </div>);
}
