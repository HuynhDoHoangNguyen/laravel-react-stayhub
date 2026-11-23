import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import authHero from "@/assets/auth-hero.png";
import logoMark from "@/assets/logo-mark.png";
import { AuthTopBar } from "@/components/auth/AuthTopBar";
import { PromoCard } from "@/components/auth/PromoCard";
import { LoginForm } from "@/components/auth/LoginForm";
export default function AuthPage() {
    return (<main className="min-h-screen bg-background px-3 py-3 md:flex md:items-center md:justify-center md:px-6 md:py-6">
      <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }} className="mx-auto grid w-full max-w-[1180px] overflow-hidden rounded-[28px] bg-background shadow-[0_30px_100px_-40px_rgba(17,26,19,0.35)] ring-1 ring-black/[0.04] md:h-[min(760px,calc(100vh-3rem))] md:grid-cols-[54%_46%]">
        {/* LEFT — hero panel */}
        <section className="relative isolate overflow-hidden bg-surface min-h-[300px] md:min-h-full">
          <img src={authHero} alt="Cliffside villa with infinity pool overlooking the sea at golden hour" className="absolute inset-0 h-full w-full object-cover animate-ken-burns"/>
          <div aria-hidden className="absolute inset-0" style={{
            background: "linear-gradient(180deg, rgba(17,26,19,0.35) 0%, rgba(17,26,19,0.1) 40%, rgba(17,26,19,0.55) 100%)",
        }}/>

          <div className="relative flex h-full flex-col justify-between p-6 md:p-9">
            <Link to="/" className="flex items-center gap-2" aria-label="StayNest home">
              <img src={logoMark} alt="" className="h-8 w-8"/>
              <span className="font-display text-xl leading-none text-white drop-shadow-sm">StayNest</span>
            </Link>

            <div className="max-w-md">
              <h2 className="font-display text-[clamp(2rem,3.6vw,3rem)] font-light leading-[1] tracking-[-0.02em] text-white drop-shadow-sm">
                Welcome back!
                <br />
                Your next stay
                <br />
                awaits.
              </h2>
              <p className="mt-4 max-w-sm text-[14px] leading-relaxed text-white/85">
                Sign in to continue your journey and discover handpicked stays that feel like home.
              </p>
            </div>

            <div>
              <PromoCard />
            </div>
          </div>
        </section>

        {/* RIGHT — form panel */}
        <section className="relative flex min-h-[520px] flex-col bg-background md:min-h-full md:overflow-y-auto">
          <AuthTopBar />
          <div className="flex flex-1 items-center justify-center px-6 pb-8 pt-4 md:px-10">
            <LoginForm />
          </div>
        </section>
      </motion.div>
    </main>);
}
