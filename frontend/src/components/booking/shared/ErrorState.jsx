export default function ErrorState({
    title = 'Đã xảy ra lỗi',
    message = 'Không thể tải được dữ liệu. Vui lòng thử lại.',
    onRetry,
}) {
    return (
        <div className="bk-state bk-state-error">
            <div className="bk-state-icon">⚠️</div>
            <h4 className="bk-state-title">{title}</h4>
            <p className="bk-state-description">{message}</p>
            {onRetry && (
                <button
                    type="button"
                    className="bk-btn bk-btn-outline bk-btn-sm"
                    onClick={onRetry}
                >
                    Thử lại
                </button>
            )}
        </div>
    );
}
