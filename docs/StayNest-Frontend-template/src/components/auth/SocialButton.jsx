export function SocialButton({ onClick, icon, children }) {
    return (<button type="button" onClick={onClick} className="inline-flex h-[46px] w-full items-center justify-center gap-3 rounded-[14px] border border-border bg-background text-[13.5px] font-medium text-ink transition-all duration-200 hover:-translate-y-[2px] hover:border-primary/30 hover:shadow-[0_10px_30px_-18px_rgba(17,26,19,0.35)]">
      <span className="grid h-5 w-5 place-items-center" aria-hidden>
        {icon}
      </span>
      {children}
    </button>);
}
export function GoogleIcon() {
    return (<svg viewBox="0 0 48 48" className="h-5 w-5" aria-hidden>
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
    </svg>);
}
export function AppleIcon() {
    return (<svg viewBox="0 0 24 24" className="h-5 w-5 fill-current" aria-hidden>
      <path d="M16.365 1.43c0 1.14-.42 2.24-1.19 3.06-.83.89-2.16 1.58-3.27 1.49-.13-1.1.42-2.26 1.19-3.02.85-.87 2.28-1.53 3.27-1.53zM20.5 17.06c-.56 1.24-.83 1.8-1.55 2.9-.99 1.51-2.39 3.39-4.11 3.4-1.53.02-1.93-.99-4.02-.98-2.09.01-2.52 1-4.06.97-1.72-.02-3.05-1.72-4.04-3.23C.02 16.9-.28 12.13 1.35 9.5c1.16-1.86 2.99-2.94 4.71-2.94 1.75 0 2.85 1 4.3 1 1.4 0 2.25-1 4.28-1 1.53 0 3.15.84 4.31 2.28-3.79 2.08-3.17 7.5.55 8.22z"/>
    </svg>);
}
