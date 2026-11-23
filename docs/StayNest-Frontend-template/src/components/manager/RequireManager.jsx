import { useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { Loader2 } from "lucide-react";
import { useAuth } from "@/hooks/useAuth";
/**
 * Client-side gate for the /manage/* area. Redirects to home when the
 * signed-in session does not carry the HOTEL_MANAGER role. Backend still
 * enforces access; this only avoids blank/error screens for guests.
 */
export function RequireManager({ children }) {
    const { session } = useAuth();
    const navigate = useNavigate();
    const rawRoles = session?.roles || session?.user?.roles || [];
    const roleList = Array.isArray(rawRoles) ? rawRoles.map((r) => (typeof r === "string" ? r : r?.name || "")) : [];
    const isManager = roleList.includes("HOTEL_MANAGER") || session?.role === "HOTEL_MANAGER";
    const isReady = session !== null || typeof window === "undefined";
    useEffect(() => {
        if (session === null && typeof window !== "undefined") {
            // wait for hydration
            return;
        }
        if (!isManager) {
            toast.error("Hotel manager access required.");
            navigate("/auth");
        }
    }, [isManager, navigate, session]);
    if (!isReady || !isManager) {
        return (<div className="grid min-h-screen place-items-center bg-background text-muted-foreground">
        <Loader2 className="h-6 w-6 animate-spin" aria-hidden/>
      </div>);
    }
    return <>{children}</>;
}
