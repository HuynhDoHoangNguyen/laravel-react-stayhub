import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Link, useNavigate } from "react-router-dom";
import { ArrowLeft, CheckCircle2, Eye, EyeOff, Loader2, Lock, ShieldCheck, TriangleAlert } from "lucide-react";
import { toast } from "sonner";
import { resetPassword } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/client";

const schema = z
  .object({
    newPassword: z.string().min(8, "Password must be at least 8 characters").max(128),
    confirmPassword: z.string(),
  })
  .refine((v) => v.newPassword === v.confirmPassword, {
    message: "Passwords do not match",
    path: ["confirmPassword"],
  });

export function ResetPasswordForm({ token }) {
  const [showPw, setShowPw] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [isPending, setIsPending] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const navigate = useNavigate();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: { newPassword: "", confirmPassword: "" },
  });

  const onSubmit = async (values) => {
    setIsPending(true);
    try {
      const res = await resetPassword({ token: token ?? "", newPassword: values.newPassword });
      setIsSuccess(true);
      toast.success(res.data || "Password updated successfully.");
      setTimeout(() => navigate("/auth"), 1200);
    } catch (err) {
      const msg =
        err instanceof ApiError
          ? err.status === 400 || err.status === 404
            ? "This reset link is invalid or has expired. Please request a new one."
            : err.message
          : "We couldn't reset your password. Please try again.";
      toast.error(msg);
    } finally {
      setIsPending(false);
    }
  };

  const inputBase =
    "w-full h-full bg-transparent pl-12 pr-12 text-[14px] text-ink placeholder:text-muted-foreground/70 focus:outline-none";
  const shellBase =
    "group relative flex h-[60px] items-center rounded-[16px] border border-border bg-background transition-all duration-200 focus-within:border-primary focus-within:shadow-[0_0_0_4px_rgba(26,46,32,0.08)]";

  if (!token) {
    return (
      <div className="w-full max-w-[420px]">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10 text-destructive">
          <TriangleAlert className="h-6 w-6" aria-hidden />
        </div>
        <h1 className="mt-5 font-display text-[clamp(1.8rem,3vw,2.4rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
          Invalid reset link
        </h1>
        <p className="mt-2 text-[13.5px] text-muted-foreground">
          This password reset link is missing its token or is malformed. Please request a new one.
        </p>
        <Link
          to="/forgot-password"
          className="mt-6 inline-flex h-[46px] items-center justify-center gap-2 rounded-[14px] bg-primary px-5 text-[14px] font-semibold text-primary-foreground transition-all duration-200 hover:-translate-y-[2px] hover:bg-primary-mid"
        >
          Request new link
        </Link>
      </div>
    );
  }

  if (isSuccess) {
    return (
      <div className="w-full max-w-[420px]">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
          <CheckCircle2 className="h-6 w-6" aria-hidden />
        </div>
        <h1 className="mt-5 font-display text-[clamp(1.8rem,3vw,2.4rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
          Password updated
        </h1>
        <p className="mt-2 text-[13.5px] text-muted-foreground">
          You can now sign in with your new password. Redirecting you to sign in…
        </p>
      </div>
    );
  }

  return (
    <div className="w-full max-w-[460px]">
      <div className="text-center">
        <h1 className="font-display text-[clamp(1.9rem,3vw,2.5rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
          Reset your password
        </h1>
        <p className="mx-auto mt-3 max-w-[380px] text-[13.5px] leading-relaxed text-muted-foreground">
          Enter your new password below. Make sure it&apos;s strong and something you&apos;ll remember.
        </p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="mt-7 space-y-4" noValidate>
        <div>
          <label htmlFor="newPassword" className="sr-only">
            New password
          </label>
          <div className={shellBase}>
            <Lock
              className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary"
              aria-hidden
            />
            <input
              id="newPassword"
              type={showPw ? "text" : "password"}
              autoComplete="new-password"
              placeholder="New password"
              aria-invalid={!!errors.newPassword}
              className={inputBase}
              {...register("newPassword")}
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
          <p
            className={`mt-2 text-[12px] ${
              errors.newPassword ? "text-destructive" : "text-muted-foreground"
            }`}
          >
            {errors.newPassword?.message ?? "At least 8 characters with a mix of letters, numbers & symbols."}
          </p>
        </div>

        <div>
          <label htmlFor="confirmPassword" className="sr-only">
            Confirm new password
          </label>
          <div className={shellBase}>
            <Lock
              className="absolute left-4 h-[17px] w-[17px] text-muted-foreground/80 transition-colors group-focus-within:text-primary"
              aria-hidden
            />
            <input
              id="confirmPassword"
              type={showConfirm ? "text" : "password"}
              autoComplete="new-password"
              placeholder="Confirm new password"
              aria-invalid={!!errors.confirmPassword}
              className={inputBase}
              {...register("confirmPassword")}
            />
            <button
              type="button"
              onClick={() => setShowConfirm((s) => !s)}
              aria-label={showConfirm ? "Hide password" : "Show password"}
              className="absolute right-3 grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:text-primary"
            >
              {showConfirm ? <EyeOff className="h-[17px] w-[17px]" /> : <Eye className="h-[17px] w-[17px]" />}
            </button>
          </div>
          {errors.confirmPassword && (
            <p className="mt-2 text-[12px] text-destructive">{errors.confirmPassword.message}</p>
          )}
        </div>

        <div className="flex items-start gap-3 rounded-[16px] bg-surface/70 p-4 ring-1 ring-black/[0.03]">
          <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">
            <ShieldCheck className="h-4 w-4" aria-hidden />
          </div>
          <div className="min-w-0">
            <p className="text-[13px] font-semibold text-ink">Security tip</p>
            <p className="mt-0.5 text-[12.5px] leading-relaxed text-muted-foreground">
              Avoid using common words or personal information.
            </p>
          </div>
        </div>

        <button
          type="submit"
          disabled={isPending}
          className="inline-flex h-[56px] w-full items-center justify-center gap-2 rounded-[16px] bg-primary text-[14px] font-semibold text-primary-foreground transition-all duration-200 hover:-translate-y-[2px] hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-70"
        >
          {isPending ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
              Updating…
            </>
          ) : (
            "Reset password"
          )}
        </button>
      </form>

      <div className="mt-6 flex items-center gap-3 text-[13px] text-muted-foreground">
        <Link
          to="/auth"
          aria-label="Back to sign in"
          className="grid h-8 w-8 place-items-center rounded-full bg-surface text-ink transition-colors hover:bg-surface/80 hover:text-primary"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden />
        </Link>
        <span>
          Remembered your password?{" "}
          <Link to="/auth" className="font-medium text-primary underline-offset-4 hover:underline">
            Sign in instead
          </Link>
        </span>
      </div>
    </div>
  );
}
