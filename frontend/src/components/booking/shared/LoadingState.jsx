export default function LoadingState({ message = 'Đang tải dữ liệu...' }) {
    return (
        <div className="bk-state bk-state-loading">
            <div className="bk-spinner" />
            <p className="bk-state-message">{message}</p>
        </div>
    );
}
