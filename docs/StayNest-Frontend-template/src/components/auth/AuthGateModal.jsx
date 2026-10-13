import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { motion, AnimatePresence } from "framer-motion";
import { X, LogIn, UserPlus, KeyRound } from "lucide-react";
import logoMark from "@/assets/logo-mark.png";
/**
 * AuthGateModal
 *
 * Listens for the custom "staynest:auth-required" browser event that the API
 * client dispatches whenever a 401 or 403 response is received. When fired it
 * shows a themed Sign-in / Sign-up overlay so the user knows exactly what to
 * do next, without ever seeing a raw error string.
 */
export function AuthGateModal() {
    const [open, setOpen] = useState(false);
    useEffect(() => {
        const handler = () => setOpen(true);
        window.addEventListener("staynest:auth-required", handler);
        return () => window.removeEventListener("staynest:auth-required", handler);
    }, []);
    // Close on Escape key
    useEffect(() => {
        if (!open)
            return;
        const handler = (e) => {
            if (e.key === "Escape")
                setOpen(false);
        };
        window.addEventListener("keydown", handler);
        return () => window.removeEventListener("keydown", handler);
    }, [open]);
    return (<AnimatePresence>
      {open && (<>
          {/* Backdrop */}
          <motion.div key="backdrop" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.22 }} onClick={() => setOpen(false)} className="fixed inset-0 z-[9998] bg-ink/40 backdrop-blur-sm" aria-hidden/>

          {/* Panel */}
          <motion.div key="panel" role="dialog" aria-modal="true" aria-labelledby="auth-gate-title" initial={{ opacity: 0, scale: 0.95, y: 16 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.95, y: 16 }} transition={{ type: "spring", stiffness: 380, damping: 30 }} className="fixed left-1/2 top-1/2 z-[9999] w-[calc(100vw-2rem)] max-w-[420px] -translate-x-1/2 -translate-y-1/2 rounded-3xl border border-border/60 bg-white p-8 shadow-[0_32px_80px_-16px_rgba(17,26,19,0.22)]">
            {/* Close button */}
            <button onClick={() => setOpen(false)} aria-label="Close" className="absolute right-5 top-5 rounded-full p-1.5 text-muted-foreground transition-colors hover:bg-surface hover:text-ink">
              <X className="h-4 w-4"/>
            </button>

            {/* Logo + heading */}
            <div className="flex flex-col items-center text-center">
              <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/[0.06]">
                <img src={logoMark} alt="" className="h-8 w-8"/>
              </div>

              <div className="mt-4 flex items-center gap-2 text-primary/60">
                <KeyRound className="h-4 w-4"/>
                <span className="text-[11px] font-semibold uppercase tracking-[0.22em]">
                  Sign in required
                </span>
              </div>

              <h2 id="auth-gate-title" className="mt-2 font-display text-2xl leading-snug text-primary">
                Sign in to continue
              </h2>
              <p className="mt-2 max-w-[300px] text-[13.5px] text-muted-foreground">
                You need to be signed in to access this feature. Join StayNest to book stays,
                manage guests, and more.
              </p>
            </div>

            {/* Actions */}
            <div className="mt-7 flex flex-col gap-3">
              <Link to="/auth" onClick={() => {
                sessionStorage.setItem("staynest.return_url", window.location.href);
                setOpen(false);
            }} className="flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-[14px] font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary-mid active:scale-[0.98]">
                <LogIn className="h-4 w-4"/>
                Sign in
              </Link>
              <Link to="/signup" onClick={() => {
                sessionStorage.setItem("staynest.return_url", window.location.href);
                setOpen(false);
            }} className="flex items-center justify-center gap-2 rounded-xl border border-border bg-white px-5 py-3 text-[14px] font-medium text-ink shadow-sm transition-all hover:bg-surface active:scale-[0.98]">
                <UserPlus className="h-4 w-4"/>
                Create an account
              </Link>
            </div>

            {/* Fine print */}
            <p className="mt-5 text-center text-[11.5px] text-muted-foreground">
              By signing in you agree to our{" "}
              <span className="underline underline-offset-2 cursor-pointer hover:text-primary">
                Terms of Service
              </span>
              .
            </p>
          </motion.div>
        </>)}
    </AnimatePresence>);
}
