import { Github, Instagram, Linkedin, Mail } from "lucide-react";
import logoMark from "@/assets/logo-mark.png";
const EXPLORE_LINKS = [
    "Stays",
    "Destinations",
    "Offers",
    "Concierge",
    "About Us",
    "Terms & Conditions",
];
const SOCIALS = [
    { label: "GitHub", href: "https://github.com/ankit5609", icon: Github },
    { label: "LinkedIn", href: "https://www.linkedin.com/in/ankit5609/", icon: Linkedin },
    { label: "Instagram", href: "https://www.instagram.com/_.ankkkit/?hl=en", icon: Instagram },
    { label: "Gmail", href: "mailto:ankitkr5609@gmail.com", icon: Mail },
];
export function Footer() {
    return (<footer className="border-t border-border bg-background">
      <div className="mx-auto max-w-[1440px] px-6 py-16 md:px-10 md:py-20">
        <div className="grid gap-12 md:grid-cols-3">
          <div>
            <div className="flex items-center gap-2">
              <img src={logoMark} alt="" className="h-9 w-9"/>
              <span className="font-display text-2xl text-primary">StayNest</span>
            </div>
            <p className="mt-4 max-w-xs text-[14px] leading-relaxed text-muted-foreground">
              A quiet collection of boutique hotels for travelers who value
              stillness, craft, and unhurried moments.
            </p>
          </div>

          <div>
            <div className="text-[13px] font-semibold uppercase tracking-[0.14em] text-ink">
              Explore
            </div>
            <ul className="mt-5 grid grid-cols-2 gap-y-2.5 gap-x-6 text-[14px] text-muted-foreground">
              {EXPLORE_LINKS.map((label) => (<li key={label}>
                  <a href="#" className="transition-colors duration-200 hover:text-primary">
                    {label}
                  </a>
                </li>))}
            </ul>
          </div>

          <div>
            <div className="text-[13px] font-semibold uppercase tracking-[0.14em] text-ink">
              Follow Us
            </div>
            <div className="mt-5 flex items-center gap-3">
              {SOCIALS.map(({ label, href, icon: Icon }) => (<a key={label} href={href} target="_blank" rel="noreferrer noopener" aria-label={label} className="grid h-10 w-10 place-items-center rounded-full border border-border text-ink/80 transition-colors duration-200 hover:border-primary hover:text-primary">
                  <Icon className="h-4 w-4" strokeWidth={1.6} aria-hidden/>
                </a>))}
            </div>
          </div>
        </div>

        <div className="mt-14 border-t border-border pt-6 text-center text-[13px] text-muted-foreground">
          © 2026 StayNest. All rights reserved. Crafted with{" "}
          <span aria-label="love" className="text-accent">♥</span> by Ankit Kumar.
        </div>
      </div>
    </footer>);
}
