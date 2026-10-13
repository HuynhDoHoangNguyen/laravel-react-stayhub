import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useNavigate } from "react-router-dom";
import { Eye, EyeOff, Lock, Mail, User, Loader2, ShieldCheck } from "lucide-react";
import { toast } from "sonner";
import { register as registerUser, login as loginUser, persistSession } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/client";
import { GoogleIcon, SocialButton } from "./SocialButton";
import { RoleToggle } from "./RoleToggle";

const schema = z
  .object({
    name: z.string().trim().min(2, "Please enter your full name").max(80),
    email: z.string().trim().email("Enter a valid email address").max(255),
    password: z.string().min(8, "Password must be at least 8 characters").max(128),
    confirmPassword: z.string(),
    agree: z.literal(true, {
      errorMap: () => ({ message: "Please accept the Terms to continue" }),
    }),
  })
  .refine((v) => v.password === v.confirmPassword, {
    message: "Passwords do not match",
    path: ["confirmPassword"],
  });

export function SignUpForm() {
  const [showPw, setShowPw] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [asManager, setAsManager] = useState(false);
  const [isPending, setIsPending] = useState(false);
  const navigate = useNavigate();

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isValid },
  } = useForm({
    resolver: zodResolver(schema),
    mode: "onChange",
    defaultValues: { name: "", email: "", password: "", confirmPassword: "", agree: undefined },
  });

  const onSubmit = async (v) => {
    setIsPending(true);
    try {
      await registerUser({
        name: v.name.trim(),
        email: v.email.trim(),
        password: v.password,
        role: asManager ? "HOTEL_MANAGER" : "GUEST",
      });
      const loginRes = await loginUser({
        email: v.email.trim(),
        password: v.password,
      });
      persistSession({
        ...loginRes,
        email: loginRes.email ?? v.email.trim(),
        name: loginRes.name ?? v.name.trim(),
      });
      toast.success("Welcome to StayNest");
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
          ? err.status === 409
            ? "An account with this email already exists."
            : err.message
          : "We couldn't create your account. Please try again.";
      toast.error(msg);
    } finally {
      setIsPending(false);
    }
  };

  const inputBase =
    "w-full h-full bg-transparent pl-11 pr-4 text-[14px] text-ink placeholder:text-muted-foreground/70 focus:outline-none";
  const shellBase =
    "group relative flex h-[50px] items-center rounded-[14px] border border-border bg-background transition-all duration-200 focus-within:border-primary focus-within:shadow-[0_0_0_4px_rgba(26,46,32,0.08)]";
  const agree = watch("agree");

  return (
    <div className="w-full max-w-[440px]">
      <h1 className="font-display text-[clamp(1.8rem,3vw,2.4rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
        {asManager ? "Create your host account" : "Create your account"}
      </h1>
      <p className="mt-2 text-[13.5px] text-muted-foreground">
        {asManager
          ? "List your property on StayNest and reach travelers worldwide."
          : "Join StayNest and make every stay memorable."}
      </p>

      <div className="mt-4">
        <RoleToggle
          asManager={asManager}
          onChange={setAsManager}
          hint={
            asManager
              ? "You'll be able to list and manage properties once verified."
              : "Book stays, save favorites, and manage your trips."
          }
        />
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="mt-5 space-y-3" noValidate>
        <div>
          <label htmlFor="name" className="sr-only">Full name</label>
          <div className={shellBase}>
            <User className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary" aria-hidden/>
            <input id="name" type="text" autoComplete="name" placeholder="Full name" aria-invalid={!!errors.name} className={inputBase} {...register("name")}/>
          </div>
          {errors.name && <p className="mt-1 text-[12px] text-destructive">{errors.name.message}</p>}
        </div>

        <div>
          <label htmlFor="email" className="sr-only">Email address</label>
          <div className={shellBase}>
            <Mail className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary" aria-hidden/>
            <input id="email" type="email" autoComplete="email" placeholder="Email address" aria-invalid={!!errors.email} className={inputBase} {...register("email")}/>
          </div>
          {errors.email && <p className="mt-1 text-[12px] text-destructive">{errors.email.message}</p>}
        </div>

        <div>
          <label htmlFor="password" className="sr-only">Password</label>
          <div className={shellBase}>
            <Lock className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary" aria-hidden/>
            <input id="password" type={showPw ? "text" : "password"} autoComplete="new-password" placeholder="Password" aria-invalid={!!errors.password} className={`${inputBase} pr-11`} {...register("password")}/>
            <button type="button" onClick={() => setShowPw((s) => !s)} aria-label={showPw ? "Hide password" : "Show password"} className="absolute right-3 grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:text-primary">
              {showPw ? <EyeOff className="h-[17px] w-[17px]"/> : <Eye className="h-[17px] w-[17px]"/>}
            </button>
          </div>
          {errors.password && <p className="mt-1 text-[12px] text-destructive">{errors.password.message}</p>}
        </div>

        <div>
          <label htmlFor="confirmPassword" className="sr-only">Confirm password</label>
          <div className={shellBase}>
            <Lock className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary" aria-hidden/>
            <input id="confirmPassword" type={showConfirm ? "text" : "password"} autoComplete="new-password" placeholder="Confirm password" aria-invalid={!!errors.confirmPassword} className={`${inputBase} pr-11`} {...register("confirmPassword")}/>
            <button type="button" onClick={() => setShowConfirm((s) => !s)} aria-label={showConfirm ? "Hide password" : "Show password"} className="absolute right-3 grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:text-primary">
              {showConfirm ? <EyeOff className="h-[17px] w-[17px]"/> : <Eye className="h-[17px] w-[17px]"/>}
            </button>
          </div>
          {errors.confirmPassword && (<p className="mt-1 text-[12px] text-destructive">{errors.confirmPassword.message}</p>)}
        </div>

        <label className="flex items-start gap-2.5 pt-1 text-[12.5px] text-ink/85">
          <input type="checkbox" className="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-primary" {...register("agree")}/>
          <span>
            I agree to the{" "}
            <a href="#" className="font-medium text-primary underline underline-offset-4 hover:text-primary-mid">
              Terms of Service
            </a>{" "}
            and{" "}
            <a href="#" className="font-medium text-primary underline underline-offset-4 hover:text-primary-mid">
              Privacy Policy
            </a>
            .
          </span>
        </label>
        {errors.agree && !agree && (<p className="text-[12px] text-destructive">{errors.agree.message}</p>)}

        <button type="submit" disabled={isPending || !isValid} className="inline-flex h-[50px] w-full items-center justify-center gap-2 rounded-[14px] bg-primary text-[14px] font-semibold text-primary-foreground transition-all duration-200 hover:-translate-y-[2px] hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0">
          {isPending ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden/>
              Creating account…
            </>
          ) : (
            "Sign up"
          )}
        </button>

        <div className="flex items-center gap-4 pt-1">
          <span className="h-px flex-1 bg-border"/>
          <span className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">or</span>
          <span className="h-px flex-1 bg-border"/>
        </div>

        <SocialButton icon={<GoogleIcon />} onClick={() => toast.info("Google sign-up isn't configured yet.")}>
          Continue with Google
        </SocialButton>
      </form>

      <div className="mt-5 flex items-center justify-center gap-2 text-[12px] text-muted-foreground">
        <ShieldCheck className="h-3.5 w-3.5" aria-hidden/>
        Your data is safe with us. We never share your information.
      </div>
    </div>
  );
}
