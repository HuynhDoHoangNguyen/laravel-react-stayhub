export default function FieldShell({ icon: Icon, label, children, className = '' }) {
  return <div className={`min-w-0 rounded-2xl px-4 py-3 text-left transition-colors hover:bg-surface/70 ${className}`}>
    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">
      {Icon && <Icon className="size-4 text-primary" aria-hidden="true" />}
      <span>{label}</span>
    </div>
    <div className="mt-2">{children}</div>
  </div>;
}
