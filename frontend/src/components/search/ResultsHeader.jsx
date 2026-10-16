export default function ResultsHeader({ total, page }) {
  const first = total ? (page - 1) * 15 + 1 : 0;
  const last = Math.min(page * 15, total);
  return <div className="mb-5 flex flex-wrap items-end justify-between gap-2">
    <div><p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">StayHub · Tìm phòng</p><h2 className="mt-1 text-2xl font-medium">Phòng phù hợp với bạn</h2></div>
    <p className="text-sm text-muted-foreground">{first}–{last} / {total} phòng</p>
  </div>;
}
