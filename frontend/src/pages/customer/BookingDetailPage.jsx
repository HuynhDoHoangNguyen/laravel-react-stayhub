import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getCustomerBookingDetail, cancelBooking } from '../../services/bookingService';
import { formatCurrency } from '../../utils/formatCurrency';
import { formatDate } from '../../utils/formatDate';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import StatusBadge from '../../components/booking/shared/StatusBadge';
import ConfirmDialog from '../../components/booking/shared/ConfirmDialog';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';

const WORKFLOW_STEPS = [
    { key: 'PENDING', label: 'Chờ xác nhận' },
    { key: 'CONFIRMED', label: 'Đã xác nhận' },
    { key: 'CHECKED_IN', label: 'Đã nhận phòng' },
    { key: 'COMPLETED', label: 'Đã hoàn thành' },
];

export default function BookingDetailPage() {
    const { id } = useParams();

    const [loading, setLoading] = useState(true);
    const [booking, setBooking] = useState(null);
    const [notFound, setNotFound] = useState(false);
    const [error, setError] = useState(null);

    const [showCancelDialog, setShowCancelDialog] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [actionMessage, setActionMessage] = useState(null);

    const loadBooking = useCallback(async () => {
        setLoading(true);
        setError(null);
        setNotFound(false);
        try {
            const res = await getCustomerBookingDetail(id);
            setBooking(res.data);
        } catch (err) {
            if (err.response?.status === 404) {
                setNotFound(true);
            } else {
                setError(getApiErrorMessage(err).message);
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

    const handleConfirmCancel = async () => {
        setCancelling(true);
        setActionMessage(null);
        try {
            const res = await cancelBooking(id);
            setBooking(res.data);
            setShowCancelDialog(false);
            setActionMessage({ type: 'success', text: 'Hủy đơn đặt phòng thành công.' });
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setActionMessage({ type: 'danger', text: parsed.message });
            setShowCancelDialog(false);
        } finally {
            setCancelling(false);
        }
    };

    if (loading) {
        return (
            <div className="bk-container">
                <LoadingState message="Đang tải thông tin đặt phòng..." />
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
                        Đơn đặt phòng #{id} không tồn tại hoặc bạn không có quyền truy cập.
                    </p>
                    <Link to="/my-bookings" className="bk-btn bk-btn-primary">
                        ← Quay lại danh sách đặt phòng
                    </Link>
                </div>
            </div>
        );
    }

    if (error || !booking) {
        return (
            <div className="bk-container">
                <ErrorState
                    title="Không thể tải chi tiết đặt phòng"
                    message={error || 'Đã xảy ra lỗi.'}
                    onRetry={loadBooking}
                />
            </div>
        );
    }

    const isTerminated = booking.status === 'CANCELLED' || booking.status === 'REJECTED';
    const canCancel = booking.status === 'PENDING' || booking.status === 'CONFIRMED';
    const currentStepIndex = WORKFLOW_STEPS.findIndex((s) => s.key === booking.status);

    return (
        <div className="bk-container" style={{ maxWidth: 900 }}>
            <div className="bk-page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 6 }}>
                        <h1 className="bk-page-title" style={{ margin: 0 }}>
                            Đơn đặt phòng #{booking.booking_code}
                        </h1>
                        <StatusBadge status={booking.status} />
                    </div>
                    <p className="bk-page-subtitle">
                        Tạo lúc: {booking.created_at ? new Date(booking.created_at).toLocaleString('vi-VN') : ''}
                    </p>
                </div>

                <Link to="/my-bookings" className="bk-btn bk-btn-outline bk-btn-sm">
                    ← Danh sách đặt phòng
                </Link>
            </div>

            {actionMessage && (
                <div className={`bk-alert bk-alert-${actionMessage.type}`}>
                    {actionMessage.text}
                </div>
            )}

            {/* Status Progress Timeline */}
            <div className="bk-card" style={{ marginBottom: 24 }}>
                <h3 className="bk-card-title">Tiến trình đặt phòng</h3>

                {isTerminated ? (
                    <div className="bk-alert bk-alert-warning" style={{ marginTop: 16 }}>
                        <strong>Đơn đặt phòng đã kết thúc với trạng thái: </strong>
                        <StatusBadge status={booking.status} />
                        {booking.note && (
                            <p style={{ margin: '8px 0 0 0', fontSize: 13 }}>
                                <em>Ghi chú / Lý do: {booking.note}</em>
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="bk-timeline">
                        {WORKFLOW_STEPS.map((step, idx) => {
                            const isDone = currentStepIndex > idx;
                            const isActive = currentStepIndex === idx;

                            let nodeClass = 'bk-timeline-node';
                            if (isDone) nodeClass += ' bk-timeline-node-done';
                            else if (isActive) nodeClass += ' bk-timeline-node-active';

                            return (
                                <div key={step.key} className="bk-timeline-step">
                                    <div className={nodeClass}>
                                        {isDone ? '✓' : idx + 1}
                                    </div>
                                    <span
                                        className={`bk-timeline-label ${
                                            isActive ? 'bk-timeline-label-active' : ''
                                        }`}
                                    >
                                        {step.label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Room & Stay Details */}
            <div className="bk-card" style={{ marginBottom: 24 }}>
                <h3 className="bk-card-title">Chi tiết phòng & lưu trú</h3>
                <table className="bk-table">
                    <tbody>
                        <tr>
                            <td style={{ width: '35%', color: '#64748b' }}>Cơ sở lưu trú:</td>
                            <td>
                                <strong>{booking.room?.property?.name || '---'}</strong>
                                <div style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>
                                    📍 {booking.room?.property?.address} • ☎ {booking.room?.property?.phone}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style={{ color: '#64748b' }}>Phòng đặt:</td>
                            <td>
                                <strong>{booking.room?.name || '---'}</strong> (Số phòng: {booking.room?.room_number})
                            </td>
                        </tr>
                        <tr>
                            <td style={{ color: '#64748b' }}>Thời gian lưu trú:</td>
                            <td>
                                <strong>{formatDate(booking.check_in_date)}</strong> →{' '}
                                <strong>{formatDate(booking.check_out_date)}</strong>{' '}
                                ({booking.number_of_nights} đêm)
                            </td>
                        </tr>
                        <tr>
                            <td style={{ color: '#64748b' }}>Số lượng khách:</td>
                            <td>{booking.guest_count} khách (Sức chứa tối đa: {booking.room?.capacity} khách)</td>
                        </tr>
                        <tr>
                            <td style={{ color: '#64748b' }}>Giá mỗi đêm:</td>
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

            {/* Services Used Section */}
            <div className="bk-card" style={{ marginBottom: 24 }}>
                <h3 className="bk-card-title">Dịch vụ sử dụng trong kỳ nghỉ</h3>

                {(!booking.booking_services || booking.booking_services.length === 0) ? (
                    <p style={{ margin: '12px 0', color: '#64748b', fontSize: 14 }}>
                        Chưa có dịch vụ phát sinh nào được thêm vào đơn đặt phòng này.
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

            {/* Actions Bar */}
            <div className="bk-card" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    {/* TODO: Nút "Xem hóa đơn" sẽ được tích hợp tại đây bởi Thành viên 3 (Billing/Invoice Module) */}
                    <span style={{ fontSize: 13, color: '#94a3b8' }}>
                        StayHub Booking Module • Member 2
                    </span>
                </div>

                <div>
                    {canCancel && (
                        <button
                            type="button"
                            className="bk-btn bk-btn-danger"
                            onClick={() => setShowCancelDialog(true)}
                        >
                            Hủy đặt phòng
                        </button>
                    )}
                </div>
            </div>

            {/* Cancel Confirmation Dialog */}
            <ConfirmDialog
                isOpen={showCancelDialog}
                title="Xác nhận hủy đặt phòng"
                message={`Bạn có chắc chắn muốn hủy đơn đặt phòng #${booking.booking_code}? Sau khi hủy, phòng sẽ được giải phóng cho khách khác.`}
                confirmText="Hủy phòng ngay"
                cancelText="Giữ lại đơn"
                isDanger
                loading={cancelling}
                onConfirm={handleConfirmCancel}
                onCancel={() => setShowCancelDialog(false)}
            />
        </div>
    );
}
