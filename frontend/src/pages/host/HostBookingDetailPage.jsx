import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import {
    getHostBookingDetail,
    confirmBooking,
    rejectBooking,
    checkInBooking,
    checkOutBooking,
} from '../../services/hostBookingService';
import { formatCurrency } from '../../utils/formatCurrency';
import { formatDate } from '../../utils/formatDate';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import StatusBadge from '../../components/booking/shared/StatusBadge';
import ConfirmDialog from '../../components/booking/shared/ConfirmDialog';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';

export default function HostBookingDetailPage() {
    const { id } = useParams();

    const [loading, setLoading] = useState(true);
    const [booking, setBooking] = useState(null);
    const [notFound, setNotFound] = useState(false);
    const [loadError, setLoadError] = useState(null);

    // Action dialog state
    const [actionDialog, setActionDialog] = useState({
        isOpen: false,
        action: null, // 'confirm' | 'reject' | 'check-in' | 'check-out'
        title: '',
        message: '',
        isDanger: false,
        confirmText: '',
        reason: '',
        loading: false,
    });

    const [actionFeedback, setActionFeedback] = useState(null);

    const loadBooking = useCallback(async () => {
        setLoading(true);
        setLoadError(null);
        setNotFound(false);
        try {
            const res = await getHostBookingDetail(id);
            setBooking(res.data);
        } catch (err) {
            if (err.response?.status === 404) {
                setNotFound(true);
            } else {
                setLoadError(getApiErrorMessage(err).message);
            }
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => {
        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                loadBooking();
            }
        });
        return () => {
            isMounted = false;
        };
    }, [loadBooking]);

    const handleActionExecute = async () => {
        const { action, reason } = actionDialog;
        setActionDialog((prev) => ({ ...prev, loading: true }));
        setActionFeedback(null);

        try {
            if (action === 'confirm') {
                await confirmBooking(id);
                setActionFeedback({ type: 'success', text: 'Xác nhận đơn đặt phòng thành công.' });
            } else if (action === 'reject') {
                await rejectBooking(id, reason);
                setActionFeedback({ type: 'success', text: 'Từ chối đơn đặt phòng thành công.' });
            } else if (action === 'check-in') {
                await checkInBooking(id);
                setActionFeedback({ type: 'success', text: 'Xác nhận khách đã nhận phòng (Check-In) thành công.' });
            } else if (action === 'check-out') {
                await checkOutBooking(id);
                setActionFeedback({ type: 'success', text: 'Xác nhận trả phòng (Check-Out) thành công.' });
            }

            setActionDialog({
                isOpen: false,
                action: null,
                title: '',
                message: '',
                isDanger: false,
                confirmText: '',
                reason: '',
                loading: false,
            });
            loadBooking();
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setActionFeedback({ type: 'danger', text: parsed.message });
            setActionDialog((prev) => ({ ...prev, loading: false }));
        }
    };

    if (loading) {
        return (
            <div className="bk-container">
                <LoadingState message="Đang tải thông tin đặt phòng phía Host..." />
            </div>
        );
    }

    if (notFound) {
        return (
            <div className="bk-container">
                <div className="bk-state bk-state-error">
                    <div className="bk-state-icon">🔍</div>
                    <h2 className="bk-state-title">Không tìm thấy booking</h2>
                    <p className="bk-state-description">
                        Đơn đặt phòng #{id} không tồn tại hoặc không thuộc cơ sở lưu trú do bạn quản lý.
                    </p>
                    <Link to="/host/bookings" className="bk-btn bk-btn-primary">
                        ← Quay lại danh sách đặt phòng
                    </Link>
                </div>
            </div>
        );
    }

    if (loadError || !booking) {
        return (
            <div className="bk-container">
                <ErrorState
                    title="Lỗi tải chi tiết đặt phòng"
                    message={loadError || 'Đã xảy ra lỗi.'}
                    onRetry={loadBooking}
                />
            </div>
        );
    }

    const { status } = booking;

    return (
        <div className="bk-container" style={{ maxWidth: 960 }}>
            {/* Header */}
            <div className="bk-page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 6 }}>
                        <h1 className="bk-page-title" style={{ margin: 0 }}>
                            Đơn đặt phòng #{booking.booking_code}
                        </h1>
                        <StatusBadge status={status} />
                    </div>
                    <p className="bk-page-subtitle">
                        Cơ sở: <strong>{booking.room?.property?.name}</strong> • Phòng {booking.room?.room_number} ({booking.room?.name})
                    </p>
                </div>

                <Link to="/host/bookings" className="bk-btn bk-btn-outline bk-btn-sm">
                    ← Danh sách đơn
                </Link>
            </div>

            {/* Feedback notification */}
            {actionFeedback && (
                <div className={`bk-alert bk-alert-${actionFeedback.type}`}>
                    {actionFeedback.text}
                </div>
            )}

            {/* Workflow Action Bar */}
            <div className="bk-card" style={{ marginBottom: 24, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12 }}>
                <div>
                    <span style={{ fontSize: 13, color: '#64748b' }}>Trạng thái hiện tại: </span>
                    <strong style={{ fontSize: 15 }}>{booking.status_label || status}</strong>
                </div>

                <div style={{ display: 'flex', gap: 10 }}>
                    {status === 'PENDING' && (
                        <>
                            <button
                                type="button"
                                className="bk-btn bk-btn-primary"
                                onClick={() =>
                                    setActionDialog({
                                        isOpen: true,
                                        action: 'confirm',
                                        title: 'Xác nhận đơn đặt phòng',
                                        message: `Bạn có chắc muốn duyệt đơn #${booking.booking_code}? Hệ thống sẽ kiểm tra lại phòng trống và chuyển sang Đã xác nhận.`,
                                        isDanger: false,
                                        confirmText: 'Xác nhận duyệt',
                                        reason: '',
                                        loading: false,
                                    })
                                }
                            >
                                ✓ Duyệt đặt phòng
                            </button>
                            <button
                                type="button"
                                className="bk-btn bk-btn-danger"
                                onClick={() =>
                                    setActionDialog({
                                        isOpen: true,
                                        action: 'reject',
                                        title: 'Từ chối đơn đặt phòng',
                                        message: `Đơn #${booking.booking_code} sẽ bị từ chối và phòng sẽ được giải phóng cho khách khác.`,
                                        isDanger: true,
                                        confirmText: 'Từ chối đơn',
                                        reason: '',
                                        loading: false,
                                    })
                                }
                            >
                                ✗ Từ chối đơn
                            </button>
                        </>
                    )}

                    {status === 'CONFIRMED' && (
                        <button
                            type="button"
                            className="bk-btn bk-btn-primary"
                            onClick={() =>
                                setActionDialog({
                                    isOpen: true,
                                    action: 'check-in',
                                    title: 'Xác nhận khách nhận phòng (Check-In)',
                                    message: `Xác nhận khách hàng đã đến và nhận phòng #${booking.room?.room_number}? Trạng thái sẽ chuyển sang Đang lưu trú (CHECKED_IN).`,
                                    isDanger: false,
                                    confirmText: 'Nhận phòng (Check-In)',
                                    reason: '',
                                    loading: false,
                                    })
                            }
                        >
                            🔑 Nhận phòng (Check-In)
                        </button>
                    )}

                    {status === 'CHECKED_IN' && (
                        <>
                            <Link
                                to={`/host/bookings/${booking.id}/services`}
                                className="bk-btn bk-btn-outline"
                            >
                                🛒 Quản lý dịch vụ phòng
                            </Link>
                            <button
                                type="button"
                                className="bk-btn bk-btn-primary"
                                onClick={() =>
                                    setActionDialog({
                                        isOpen: true,
                                        action: 'check-out',
                                        title: 'Xác nhận khách trả phòng (Check-Out)',
                                        message: `Xác nhận khách đã hoàn tất kỳ nghỉ và trả phòng #${booking.room?.room_number}? Trạng thái sẽ chuyển sang Đã hoàn thành (COMPLETED).`,
                                        isDanger: false,
                                        confirmText: 'Trả phòng (Check-Out)',
                                        reason: '',
                                        loading: false,
                                    })
                                }
                            >
                                🚪 Trả phòng (Check-Out)
                            </button>
                        </>
                    )}

                    {status === 'COMPLETED' && (
                        <div>
                            {/* TODO: Nút "Tạo hóa đơn" sẽ được tích hợp tại đây bởi Thành viên 3 (Billing/Invoice Module) */}
                            <span style={{ fontSize: 13, color: '#16a34a', fontWeight: 600 }}>
                                ✓ Kỳ nghỉ đã hoàn tất thành công
                            </span>
                        </div>
                    )}
                </div>
            </div>

            {/* Information Grid: Customer + Stay Details */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(360px, 1fr))', gap: 20, marginBottom: 24 }}>
                {/* Customer card */}
                <div className="bk-card">
                    <h3 className="bk-card-title">Thông tin khách hàng</h3>
                    <table className="bk-table">
                        <tbody>
                            <tr>
                                <td style={{ width: '35%', color: '#64748b' }}>Họ và tên:</td>
                                <td><strong>{booking.customer?.name || 'Khách'}</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Email:</td>
                                <td>{booking.customer?.email}</td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Số điện thoại:</td>
                                <td>{booking.customer?.phone || 'Chưa cập nhật'}</td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Ghi chú của khách:</td>
                                <td><em>{booking.note || 'Không có ghi chú'}</em></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {/* Stay card */}
                <div className="bk-card">
                    <h3 className="bk-card-title">Chi tiết lịch trình</h3>
                    <table className="bk-table">
                        <tbody>
                            <tr>
                                <td style={{ width: '40%', color: '#64748b' }}>Ngày nhận phòng:</td>
                                <td><strong>{formatDate(booking.check_in_date)}</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Ngày trả phòng:</td>
                                <td><strong>{formatDate(booking.check_out_date)}</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Thời gian lưu trú:</td>
                                <td>{booking.number_of_nights} đêm</td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Số lượng khách:</td>
                                <td>{booking.guest_count} khách</td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Đơn giá mỗi đêm:</td>
                                <td>{formatCurrency(booking.price_per_night)}</td>
                            </tr>
                            <tr style={{ background: '#f8fafc' }}>
                                <td style={{ fontWeight: 600 }}>Tiền phòng:</td>
                                <td>
                                    <strong style={{ fontSize: 16 }}>{formatCurrency(booking.room_total)}</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Services Used Section */}
            <div className="bk-card" style={{ marginBottom: 24 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <h3 className="bk-card-title" style={{ margin: 0 }}>
                        Dịch vụ đã sử dụng trong kỳ nghỉ
                    </h3>
                    {status === 'CHECKED_IN' && (
                        <Link
                            to={`/host/bookings/${booking.id}/services`}
                            className="bk-btn bk-btn-outline bk-btn-sm"
                        >
                            + Thêm / Sửa dịch vụ
                        </Link>
                    )}
                </div>

                {(!booking.booking_services || booking.booking_services.length === 0) ? (
                    <p style={{ margin: '8px 0', color: '#64748b', fontSize: 14 }}>
                        Chưa có dịch vụ nào được ghi nhận cho đơn đặt phòng này.
                    </p>
                ) : (
                    <table className="bk-table">
                        <thead>
                            <tr>
                                <th>Tên dịch vụ</th>
                                <th>Đơn vị</th>
                                <th>Số lượng</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            {booking.booking_services.map((svc) => (
                                <tr key={svc.id}>
                                    <td><strong>{svc.service_name}</strong></td>
                                    <td>{svc.unit}</td>
                                    <td>{Number(svc.quantity)}</td>
                                    <td>{formatCurrency(svc.unit_price)}</td>
                                    <td><strong>{formatCurrency(svc.amount)}</strong></td>
                                </tr>
                            ))}
                            <tr style={{ background: '#f8fafc' }}>
                                <td colSpan={4} style={{ textAlign: 'right', fontWeight: 600 }}>
                                    Tổng tiền dịch vụ:
                                </td>
                                <td>
                                    <strong style={{ color: '#0284c7' }}>
                                        {formatCurrency(booking.services_total || '0.00')}
                                    </strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                )}
            </div>

            {/* Action Confirmation Modal */}
            <ConfirmDialog
                isOpen={actionDialog.isOpen && actionDialog.action !== 'reject'}
                title={actionDialog.title}
                message={actionDialog.message}
                confirmText={actionDialog.confirmText}
                cancelText="Đóng"
                isDanger={actionDialog.isDanger}
                loading={actionDialog.loading}
                onConfirm={handleActionExecute}
                onCancel={() => setActionDialog({ isOpen: false, action: null, title: '', message: '', isDanger: false, confirmText: '', reason: '', loading: false })}
            />

            {/* Custom Reject Dialog with Reason input */}
            {actionDialog.isOpen && actionDialog.action === 'reject' && (
                <div className="bk-modal-backdrop">
                    <div className="bk-modal-dialog">
                        <h3 className="bk-modal-title">{actionDialog.title}</h3>
                        <p className="bk-modal-message">{actionDialog.message}</p>

                        <div className="bk-form-group">
                            <label className="bk-label">Lý do từ chối (tùy chọn)</label>
                            <input
                                type="text"
                                placeholder="Nhập lý do từ chối..."
                                className="bk-input"
                                value={actionDialog.reason}
                                onChange={(e) =>
                                    setActionDialog({ ...actionDialog, reason: e.target.value })
                                }
                            />
                        </div>

                        <div className="bk-modal-actions">
                            <button
                                type="button"
                                className="bk-btn bk-btn-outline"
                                disabled={actionDialog.loading}
                                onClick={() =>
                                    setActionDialog({ isOpen: false, action: null, title: '', message: '', isDanger: false, confirmText: '', reason: '', loading: false })
                                }
                            >
                                Hủy bỏ
                            </button>
                            <button
                                type="button"
                                className="bk-btn bk-btn-danger"
                                disabled={actionDialog.loading}
                                onClick={handleActionExecute}
                            >
                                {actionDialog.loading ? 'Đang xử lý...' : actionDialog.confirmText}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
