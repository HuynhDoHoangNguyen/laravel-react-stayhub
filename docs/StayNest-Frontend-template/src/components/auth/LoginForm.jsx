import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Link, useNavigate } from "react-router-dom";
import { Eye, EyeOff, Lock, Mail, Loader2, ShieldCheck, Building2, AlertCircle } from "lucide-react";
import { toast } from "sonner";
import { login, persistSession } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/client";
import { GoogleIcon, AppleIcon, SocialButton } from "./SocialButton";
import { RoleToggle } from "./RoleToggle";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

const schema = z.object({
  email: z.string().trim().email("Enter a valid email address").max(255),
  password: z.string().min(6, "Password must be at least 6 characters").max(128),
});

export function LoginForm() {
  const [showPw, setShowPw] = useState(false);
  const [asManager, setAsManager] = useState(false);
  const [gateOpen, setGateOpen] = useState(false);
  const [isPending, setIsPending] = useState(false);
  const [authError, setAuthError] = useState(null);
  const navigate = useNavigate();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: { email: "", password: "" },
  });

  const onSubmit = async (values) => {
    setIsPending(true);
    setAuthError(null);
    try {
      const res = await login(values);
      const roles = Array.isArray(res.roles) ? res.roles : [];
      const isManager = roles.includes("HOTEL_MANAGER");
      if (asManager && !isManager) {
        setGateOpen(true);
        return;
      }
      persistSession({ ...res, email: res.email ?? values.email });
      toast.success(asManager ? "Welcome back, host" : "Welcome back to StayNest");
      const returnUrl = sessionStorage.getItem("staynest.return_url");
      sessionStorage.removeItem("staynest.return_url");
      if (returnUrl && !returnUrl.includes("/auth") && !returnUrl.includes("/signup")) {
        window.location.href = returnUrl;
      } else {
        navigate(asManager ? "/manage" : "/");
      }
    } catch (err) {
      const msg =
        err instanceof ApiError
          ? err.status === 401 || err.status === 403
            ? "Invalid email or password. Please check your credentials and try again."
            : err.message
          : "We couldn't sign you in. Please try again.";
      setAuthError(msg);
    } finally {
      setIsPending(false);
    }
  };

  const inputBase =
    "w-full h-full bg-transparent pl-11 pr-4 text-[14px] text-ink placeholder:text-muted-foreground/70 focus:outline-none";
  const shellBase =
    "group relative flex h-[50px] items-center rounded-[14px] border border-border bg-background transition-all duration-200 focus-within:border-primary focus-within:shadow-[0_0_0_4px_rgba(26,46,32,0.08)]";

  return (
    <div className="w-full max-w-[420px]">
      <h1 className="font-display text-[clamp(1.8rem,3vw,2.4rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
        {asManager ? "Sign in as a host" : "Login to StayNest"}
      </h1>
      <p className="mt-2 text-[13.5px] text-muted-foreground">
        {asManager
          ? "Manage your listings, bookings and guests."
          : "Enter your details to access your account"}
      </p>

      <div className="mt-4">
        <RoleToggle
          asManager={asManager}
          onChange={setAsManager}
          hint={
            asManager
              ? "We'll verify your account has hotel manager access."
              : "Standard guest sign-in for booking stays."
          }
        />
      </div>

      {authError && (
        <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 text-[13px] font-medium text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" aria-hidden />
          <span>{authError}</span>
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="mt-5 space-y-3" noValidate>
        <div>
          <label htmlFor="email" className="sr-only">
            Email address
          </label>
          <div className={shellBase}>
            <Mail
              className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary"
              aria-hidden
            />
            <input
              id="email"
              type="email"
              autoComplete="email"
              placeholder="Email address"
              aria-invalid={!!errors.email}
              className={inputBase}
              {...register("email")}
            />
          </div>
          {errors.email && (
            <p className="mt-1 text-[12px] text-destructive">{errors.email.message}</p>
          )}
        </div>

        <div>
          <label htmlFor="password" className="sr-only">
            Password
          </label>
          <div className={shellBase}>
            <Lock
              className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary"
              aria-hidden
            />
            <input
              id="password"
              type={showPw ? "text" : "password"}
              autoComplete="current-password"
              placeholder="Password"
              aria-invalid={!!errors.password}
              className={`${inputBase} pr-11`}
              {...register("password")}
            />
            <button
              type="button"
              onClick={() => setShowPw((s) => !s)}
              aria-label={showPw ? "Hide password" : "Show password"}
              className="absolute right-3 grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:text-primary"
            >
              {showPw ? <EyeOff className="h-[17px] w-[17px]" /> : <Eye className="h-[17px] w-[17px]" />}
            </button>
          </div>
          {errors.password && (
            <p className="mt-1 text-[12px] text-destructive">{errors.password.message}</p>
          )}
          <div className="mt-1.5 flex justify-end">
            <Link
              to="/forgot-password"
              className="text-[12.5px] font-medium text-ink/80 transition-colors hover:text-primary hover:underline underline-offset-4"
            >
              Forgot password?
            </Link>
          </div>
        </div>

        <button
          type="submit"
          disabled={isPending}
          className="inline-flex h-[50px] w-full items-center justify-center gap-2 rounded-[14px] bg-primary text-[14px] font-semibold text-primary-foreground transition-all duration-200 hover:-translate-y-[2px] hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-70"
        >
          {isPending ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
              Signing in…
            </>
          ) : asManager ? (
            "Sign in as host"
          ) : (
            "Login"
          )}
        </button>

        <div className="flex items-center gap-4 pt-1">
          <span className="h-px flex-1 bg-border" />
          <span className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
            or
          </span>
          <span className="h-px flex-1 bg-border" />
        </div>

        <div className="grid gap-2.5">
          <SocialButton
            icon={<GoogleIcon />}
            onClick={() => toast.info("Google sign-in isn't configured yet.")}
          >
            Continue with Google
          </SocialButton>
          <SocialButton
            icon={<AppleIcon />}
            onClick={() => toast.info("Apple sign-in isn't configured yet.")}
          >
            Continue with Apple
          </SocialButton>
        </div>
      </form>

      <div className="mt-5 flex items-center justify-center gap-2 text-[12px] text-muted-foreground">
        <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
        Your data is secure with us.
      </div>

      <AlertDialog open={gateOpen} onOpenChange={setGateOpen}>
        <AlertDialogContent className="max-w-[440px] rounded-[20px] border-border">
          <AlertDialogHeader>
            <div className="mx-auto mb-2 grid h-12 w-12 place-items-center rounded-full bg-primary/10 text-primary">
              <Building2 className="h-6 w-6" aria-hidden />
            </div>
            <AlertDialogTitle className="text-center font-display text-[22px] font-light tracking-[-0.01em] text-primary">
              Hotel manager access required
            </AlertDialogTitle>
            <AlertDialogDescription className="text-center text-[13.5px] leading-relaxed text-muted-foreground">
              Thank you for your interest in hosting with StayNest. Your account
              isn't registered as a hotel manager yet. Please reach out to us on
              our social channels and our team will help you get set up.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className="sm:justify-center">
            <AlertDialogAction
              onClick={() => setGateOpen(false)}
              className="h-[46px] rounded-[12px] bg-primary px-6 text-[13.5px] font-semibold text-primary-foreground hover:bg-primary-mid"
            >
              Got it
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
