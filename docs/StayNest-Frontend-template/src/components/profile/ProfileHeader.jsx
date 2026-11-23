import { useRef } from "react";
import { User, Camera, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { useUploadAvatar } from "@/hooks/queries/useUploadAvatar";
function initialsOf(name) {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0)
        return "SN";
    return ((parts[0]?.[0] ?? "") + (parts[1]?.[0] ?? "")).toUpperCase();
}
const MAX_BYTES = 5 * 1024 * 1024;
export function ProfileHeader({ user }) {
    const roleLabel = user.roles?.includes("HOTEL_MANAGER") ? "Hotel Manager" : "Guest";
    const fileInputRef = useRef(null);
    const { mutate: upload, isPending } = useUploadAvatar();
    const onPick = () => fileInputRef.current?.click();
    const onFile = (e) => {
        const file = e.target.files?.[0];
        e.target.value = "";
        if (!file)
            return;
        if (!file.type.startsWith("image/")) {
            toast.error("Please choose an image file (JPG, PNG, or WebP).");
            return;
        }
        if (file.size > MAX_BYTES) {
            toast.error("Image is too large. Please choose a file under 5 MB.");
            return;
        }
        upload(file, {
            onSuccess: () => toast.success("Profile photo updated."),
            onError: () => toast.error("We couldn't upload your photo. Please try again."),
        });
    };
    return (<section className="grid grid-cols-1 items-center gap-8 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
      <div className="min-w-0">
        <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2 inline h-3 w-3" aria-hidden>
            <path d="M5 18h14"/>
            <path d="M5 18l2-7 2 3 4-9 4 9 2-3 2 7"/>
          </svg>
          Member Profile
        </p>
        <h1 className="mt-2 font-display text-4xl leading-tight text-primary md:text-5xl">
          My Profile
        </h1>
        <p className="mt-2 max-w-md text-[14px] text-muted-foreground">
          Manage your personal information, preferences, and how StayNest tailors your journeys.
        </p>
        <span className="mt-4 inline-block h-[2px] w-14 rounded-full bg-gold"/>
      </div>

      <div className="flex flex-col items-center">
        <div className="relative">
          <div className="grid h-32 w-32 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-primary via-primary-mid to-primary/70 text-primary-foreground shadow-[0_20px_50px_-20px_rgba(17,26,19,0.45)] ring-4 ring-background md:h-36 md:w-36">
            {user.avatarUrl ? (<img src={user.avatarUrl} alt={`${user.name}'s profile photo`} className="h-full w-full object-cover"/>) : (<span className="font-display text-4xl tracking-wide">
                {initialsOf(user.name)}
              </span>)}
            {isPending && (<div className="absolute inset-0 grid place-items-center rounded-full bg-black/40 text-white">
                <Loader2 className="h-6 w-6 animate-spin"/>
              </div>)}
          </div>
          <button type="button" onClick={onPick} disabled={isPending} aria-label="Change profile photo" className="absolute -bottom-1 -right-1 grid h-10 w-10 place-items-center rounded-full border border-border/70 bg-white text-primary shadow-md transition-colors hover:border-primary/40 hover:text-primary-mid disabled:cursor-not-allowed disabled:opacity-60">
            <Camera className="h-4 w-4"/>
          </button>
          <input ref={fileInputRef} type="file" accept="image/png,image/jpeg,image/webp" className="hidden" onChange={onFile}/>
        </div>
      </div>

      <div className="min-w-0 text-center md:text-left">
        <h2 className="font-display text-3xl text-primary md:text-4xl">
          {user.name}
        </h2>
        <p className="mt-1 text-[13.5px] text-muted-foreground">{user.email}</p>
        <span className="mt-4 inline-flex items-center gap-1.5 rounded-full bg-primary/[0.08] px-3 py-1 text-[12px] font-medium text-primary">
          <User className="h-3.5 w-3.5" aria-hidden/>
          {roleLabel}
        </span>
      </div>
    </section>);
}
