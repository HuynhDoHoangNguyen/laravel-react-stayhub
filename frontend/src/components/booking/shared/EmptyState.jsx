export default function EmptyState({ title = 'Không có dữ liệu', description = 'Hiện tại chưa có dữ liệu nào để hiển thị.', action }) {
  return <section className="mx-auto flex max-w-xl flex-col items-center gap-3 rounded-xl border border-dashed bg-card p-8 text-center">
    <span className="text-3xl" aria-hidden="true">⌕</span>
    <h4 className="text-lg font-semibold text-foreground">{title}</h4>
    <p className="text-sm text-muted-foreground">{description}</p>
    {action ? <div className="pt-1">{action}</div> : <p className="text-sm text-muted-foreground">Thử điều chỉnh bộ lọc hoặc quay lại sau.</p>}
  </section>;
}
