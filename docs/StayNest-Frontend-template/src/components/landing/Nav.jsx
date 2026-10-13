import { Link } from "react-router-dom";
import { Globe } from "lucide-react";
import logoMark from "@/assets/logo-mark.png";
import { useAuth } from "@/hooks/useAuth";
import { UserMenu } from "./UserMenu";
const NAV_ITEMS = [
    { label: "Stays", href: "/" },
    { label: "Destinations", href: "/" },
    { label: "Offers", href: "/" },
    { label: "Concierge", href: "/" },
    { label: "About Us", href: "/" },
];
export function Nav() {
    const { isAuthenticated } = useAuth();
    return (<header className="absolute inset-x-0 top-0 z-30">
      <div aria-hidden className="pointer-events-none absolute inset-x-0 top-0 h-28 -z-10" style={{
            background: "linear-gradient(180deg, rgba(253,250,246,0.85) 0%, rgba(253,250,246,0.5) 55%, rgba(253,250,246,0) 100%)",
        }}/>
      <div className="mx-auto flex h-20 max-w-[1440px] items-center justify-between px-6 md:px-10">

        <Link to="/" className="flex items-center gap-2" aria-label="StayNest home">
          <img src={logoMark} alt="" className="h-9 w-9"/>
          <span className="font-display text-2xl leading-none text-primary">
            StayNest
          </span>
        </Link>

        <nav aria-label="Primary" className="hidden items-center gap-10 lg:flex">
          {NAV_ITEMS.map((item) => (<a key={item.label} href={item.href} className="text-[15px] font-medium text-ink/85 transition-colors duration-200 hover:text-primary">
              {item.label}
            </a>))}
        </nav>

        <div className="flex items-center gap-4 md:gap-6">
          <button type="button" className="hidden items-center gap-1.5 text-sm font-medium text-ink/80 transition-colors hover:text-primary md:flex">
            <Globe className="h-4 w-4" aria-hidden/>
            EN
          </button>
          {isAuthenticated ? (<UserMenu />) : (<>
              <Link to="/auth" className="hidden text-sm font-medium text-ink/85 transition-colors hover:text-primary md:inline-flex">
                Sign in
              </Link>
              <Link to="/signup" className="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors duration-200 hover:bg-primary-mid">
                Sign up
              </Link>
            </>)}

        </div>

      </div>
    </header>);
}
