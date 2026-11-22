export default function AdminPageHeader({ id, title, description }) {
  return (
    <header className="space-y-1.5">
      <h1 id={id} className="text-3xl font-bold tracking-tight sm:text-4xl">{title}</h1>
      {description && <p className="text-sm text-muted-foreground">{description}</p>}
    </header>
  );
}
