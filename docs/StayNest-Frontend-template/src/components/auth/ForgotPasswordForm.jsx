import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Link } from "react-router-dom";
import { ArrowLeft, CheckCircle2, Clock, Lightbulb, Loader2, Lock, Mail, ShieldCheck } from "lucide-react";
import { toast } from "sonner";
import { forgotPassword } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/client";

const schema = z.object({
  email: z.string().trim().email("Enter a valid email address").max(255),
});

const trustItems = [
  {
    icon: ShieldCheck,
    title: "Secure & Private",
    body: "Your information is always protected",
  },
  {
    icon: Clock,
    title: "Quick & Easy",
    body: "Reset your password in a few clicks",
  },
  {
    icon: Lock,
    title: "Safe & Trusted",
    body: "We never share your data with anyone",
  },
];

export function ForgotPasswordForm() {
  const [isPending, setIsPending] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);

  const {
    register,
    handleSubmit,
    getValues,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: { email: "" },
  });

  const onSubmit = async (values) => {
    setIsPending(true);
    try {
      await forgotPassword({ email: values.email.trim() });
      setIsSuccess(true);
    } catch (err) {
      const msg =
        err instanceof ApiError
          ? err.message
          : "We couldn't send a reset link. Please try again.";
      toast.error(msg);
    } finally {
      setIsPending(false);
    }
  };

  if (isSuccess) {
    return (
      <div className="w-full max-w-[460px] text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary">
          <CheckCircle2 className="h-7 w-7" aria-hidden />
        </div>
        <h1 className="mt-5 font-display text-[clamp(1.9rem,2.6vw,2.4rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
          Check your inbox
        </h1>
        <p className="mt-3 text-[13.5px] leading-relaxed text-muted-foreground">
          We&apos;ve sent a secure password reset link to{" "}
          <span className="font-medium text-ink">{getValues("email")}</span>.
        </p>
        <div className="mt-6 flex flex-col items-center gap-3">
          <button
            type="button"
            onClick={() => setIsSuccess(false)}
            className="text-[13px] font-medium text-primary underline underline-offset-4 hover:text-primary-mid"
          >
            Resend email
          </button>
          <Link
            to="/auth"
            className="inline-flex items-center gap-2 text-[13px] font-medium text-ink/80 transition-colors hover:text-primary"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden />
            Back to sign in
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-[460px]">
      <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-surface text-ink/80">
        <Mail className="h-6 w-6" strokeWidth={1.5} aria-hidden />
      </div>

      <h1 className="mt-5 text-center font-display text-[clamp(1.9rem,2.6vw,2.4rem)] font-light leading-[1.05] tracking-[-0.02em] text-primary">
        Forgot your password?
      </h1>
      <p className="mx-auto mt-3 max-w-[380px] text-center text-[13.5px] leading-relaxed text-muted-foreground">
        Enter the email address associated with your account and we&apos;ll send you a secure link to reset it.
      </p>

      <form onSubmit={handleSubmit(onSubmit)} className="mt-6 space-y-3" noValidate>
        <div>
          <label htmlFor="email" className="sr-only">
            Email address
          </label>
          <div className="group relative flex h-[54px] items-center rounded-[14px] border border-border bg-background transition-all duration-200 focus-within:border-primary focus-within:shadow-[0_0_0_4px_rgba(26,46,32,0.08)]">
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
              className="h-full w-full bg-transparent pl-11 pr-4 text-[14px] text-ink placeholder:text-muted-foreground/70 focus:outline-none"
              {...register("email")}
            />
          </div>
          {errors.email && (
            <p className="mt-1 text-[12px] text-destructive">{errors.email.message}</p>
          )}
        </div>

        <button
          type="submit"
          disabled={isPending}
          className="inline-flex h-[54px] w-full items-center justify-center gap-2 rounded-[16px] bg-primary text-[14px] font-semibold text-primary-foreground shadow-[0_10px_28px_-14px_rgba(17,26,19,0.55)] transition-all duration-200 hover:-translate-y-[2px] hover:bg-primary-mid disabled:cursor-not-allowed disabled:opacity-70"
        >
          {isPending ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
              Sending link…
            </>
          ) : (
            "Send reset link"
          )}
        </button>
      </form>

      <div className="mt-7 grid grid-cols-3 gap-3">
        {trustItems.map(({ icon: Icon, title, body }) => (
          <div key={title} className="flex items-start gap-2">
            <Icon className="mt-[2px] h-4 w-4 shrink-0 text-ink/70" strokeWidth={1.5} aria-hidden />
            <div className="min-w-0">
              <div className="text-[12.5px] font-semibold text-ink">{title}</div>
              <div className="mt-[2px] text-[11.5px] leading-snug text-muted-foreground">{body}</div>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-6 flex items-start gap-3 rounded-[16px] bg-surface px-4 py-3.5">
        <Lightbulb className="mt-[2px] h-4 w-4 shrink-0 text-ink/70" strokeWidth={1.5} aria-hidden />
        <div className="min-w-0">
          <div className="text-[13px] font-semibold text-ink">Didn&apos;t receive the email?</div>
          <div className="mt-[2px] text-[12px] leading-snug text-muted-foreground">
            Check your spam folder or try again.
          </div>
        </div>
      </div>
    </div>
  );
}
