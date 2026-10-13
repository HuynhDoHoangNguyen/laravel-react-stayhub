import { useEffect, useState, useCallback } from "react";
import {
  readSession,
  clearSession,
  subscribeSession,
} from "@/lib/api/auth";

export function useAuth() {
  const [session, setSession] = useState(null);

  useEffect(() => {
    setSession(readSession());
    return subscribeSession(() => setSession(readSession()));
  }, []);

  const signOut = useCallback(() => {
    clearSession();
  }, []);

  return {
    session,
    isAuthenticated: !!session?.accessToken,
    signOut,
  };
}
