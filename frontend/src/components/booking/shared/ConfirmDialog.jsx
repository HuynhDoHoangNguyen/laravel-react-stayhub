export default function ConfirmDialog({
    isOpen,
    title = 'Xác nhận thao tác',
    message = 'Bạn có chắc chắn muốn thực hiện hành động này?',
    confirmText = 'Xác nhận',
    cancelText = 'Hủy bỏ',
    isDanger = false,
    loading = false,
    onConfirm,
    onCancel,
}) {
    if (!isOpen) return null;

    return (
        <div className="bk-modal-backdrop">
            <div className="bk-modal-dialog">
                <h3 className="bk-modal-title">{title}</h3>
                <p className="bk-modal-message">{message}</p>
                <div className="bk-modal-actions">
                    <button
                        type="button"
                        className="bk-btn bk-btn-outline"
                        onClick={onCancel}
                        disabled={loading}
                    >
                        {cancelText}
                    </button>
                    <button
                        type="button"
                        className={`bk-btn ${isDanger ? 'bk-btn-danger' : 'bk-btn-primary'}`}
                        onClick={onConfirm}
                        disabled={loading}
                    >
                        {loading ? 'Đang xử lý...' : confirmText}
                    </button>
                </div>
            </div>
        </div>
    );
}
