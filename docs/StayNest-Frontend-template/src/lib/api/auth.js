import { apiPost } from "./client";

const LOGIN_PATH = "/auth/login";
const REGISTER_PATH = "/auth/signup";
const FORGOT_PASSWORD_PATH = "/auth/forgot-password";
const RESET_PASSWORD_PATH = "/auth/reset-password";
const TOKEN_STORAGE_KEY = "staynest.auth.session";
const SESSION_EVENT = "staynest:session-changed";

export function login(body) {
  return apiPost(LOGIN_PATH, body);
}

export function register(body) {
  return apiPost(REGISTER_PATH, body);
}

export function forgotPassword(body) {
  return apiPost(FORGOT_PASSWORD_PATH, body);
}

export function resetPassword(body) {
  return apiPost(RESET_PASSWORD_PATH, body);
}

export function persistSession(res) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(TOKEN_STORAGE_KEY, JSON.stringify(res));
  window.dispatchEvent(new Event(SESSION_EVENT));
}

export function readSession() {
  if (typeof window === "undefined") return null;
  const raw = window.localStorage.getItem(TOKEN_STORAGE_KEY);
  if (!raw) return null;
  try {
    const session = JSON.parse(raw);
    if (session && typeof session === "object" && "timeStamp" in session && "data" in session) {
      const unwrapped = session.data;
      return {
        ...unwrapped,
        email: unwrapped.email ?? session.email,
        name: unwrapped.name ?? session.name,
      };
    }
    return session;
  } catch {
    return null;
  }
}

export function clearSession() {
  if (typeof window === "undefined") return;
  window.localStorage.removeItem(TOKEN_STORAGE_KEY);
  window.dispatchEvent(new Event(SESSION_EVENT));
}

export function subscribeSession(cb) {
  if (typeof window === "undefined") return () => {};
  const handler = () => cb();
  window.addEventListener(SESSION_EVENT, handler);
  window.addEventListener("storage", handler);
  return () => {
    window.removeEventListener(SESSION_EVENT, handler);
    window.removeEventListener("storage", handler);
  };
}
