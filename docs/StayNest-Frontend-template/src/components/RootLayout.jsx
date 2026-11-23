import { useEffect } from "react";
import { Outlet } from "react-router-dom";
import { Toaster } from "@/components/ui/sonner";
import { AuthGateModal } from "@/components/auth/AuthGateModal";

export function RootLayout() {
  useEffect(() => {
    const PURGE_KEY = "staynest.mock-purged.v1";
    if (typeof window !== "undefined" && !window.localStorage.getItem(PURGE_KEY)) {
      window.localStorage.removeItem("staynest.auth.session");
      window.localStorage.setItem(PURGE_KEY, "1");
      window.dispatchEvent(new Event("staynest:session-changed"));
    }
  }, []);

  return (
    <>
      <Outlet />
      <Toaster position="top-center" />
      <AuthGateModal />
    </>
  );
}
