export default function EmptyState({
    title = 'Không có dữ liệu',
    description = 'Hiện tại chưa có dữ liệu nào để hiển thị.',
    action,
}) {
    return (
        <div className="bk-state bk-state-empty">
            <div className="bk-state-icon">📭</div>
            <h4 className="bk-state-title">{title}</h4>
            <p className="bk-state-description">{description}</p>
            {action && <div className="bk-state-action">{action}</div>}
        </div>
    );
}
