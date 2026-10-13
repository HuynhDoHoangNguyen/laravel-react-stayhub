import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import authHero from "@/assets/forgot-hero.png";
import logoMark from "@/assets/logo-mark.png";
import { AuthTopBar } from "@/components/auth/AuthTopBar";
import { ForgotPasswordForm } from "@/components/auth/ForgotPasswordForm";
export default function ForgotPasswordPage() {
    return (<main className="min-h-screen bg-background px-3 py-3 md:flex md:items-center md:justify-center md:px-6 md:py-6">
      <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }} className="mx-auto grid w-full max-w-[1180px] overflow-hidden rounded-[28px] bg-background shadow-[0_30px_100px_-40px_rgba(17,26,19,0.35)] ring-1 ring-black/[0.04] md:h-[min(700px,calc(100vh-3rem))] md:grid-cols-[54%_46%]">
        <section className="relative isolate overflow-hidden bg-surface min-h-[320px] md:min-h-full">
          <img src={authHero} alt="Cliffside villa with infinity pool overlooking the sea at golden hour" className="absolute inset-0 h-full w-full object-cover animate-ken-burns"/>
          {/* Soft ivory scrim on the left third to keep editorial text legible over any scenery */}
          <div aria-hidden className="pointer-events-none absolute inset-y-0 left-0 w-2/3 bg-gradient-to-r from-background/70 via-background/25 to-transparent"/>
          <div className="relative flex h-full flex-col px-8 pt-10 pb-8 md:px-14 md:pt-16 md:pb-14">
            <Link to="/" className="flex items-center gap-2" aria-label="StayNest home">
              <img src={logoMark} alt="" className="h-8 w-8"/>
              <span className="font-display text-xl leading-none text-primary">StayNest</span>
            </Link>
            <div className="mt-16 max-w-[340px] md:mt-24">
              <h2 className="font-display text-[clamp(2.25rem,3.6vw,3rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
                We&rsquo;ve all
                <br />
                misplaced
                <br />
                things.
              </h2>
              <div className="mt-8 h-px w-12 bg-primary/50" aria-hidden/>
              <p className="mt-7 max-w-[320px] text-[14px] leading-[1.7] text-primary/85">
                No worries! Enter your email and we&apos;ll send you a link to reset your password.
              </p>
            </div>
          </div>
        </section>

        <section className="relative flex min-h-[440px] flex-col bg-background md:min-h-full md:overflow-y-auto">
          <AuthTopBar right={<Link to="/auth" className="inline-flex items-center gap-2 text-[13px] font-medium text-ink transition-colors hover:text-primary">
                Back to sign in
              </Link>}/>
          <div className="flex flex-1 items-center justify-center px-6 pb-8 pt-4 md:px-10">
            <ForgotPasswordForm />
          </div>
        </section>
      </motion.div>
    </main>);
}
