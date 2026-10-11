import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getHostBookingDetail } from '../../services/hostBookingService';
import { getHostServices } from '../../services/propertyServiceCatalogService';
import {
    getBookingServices,
    addBookingService,
    updateBookingService,
    deleteBookingService,
} from '../../services/bookingServiceUsageService';
import { formatCurrency } from '../../utils/formatCurrency';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import StatusBadge from '../../components/booking/shared/StatusBadge';
import ConfirmDialog from '../../components/booking/shared/ConfirmDialog';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';

export default function HostBookingServicesPage() {
    const { id } = useParams();

    const [loading, setLoading] = useState(true);
    const [booking, setBooking] = useState(null);
    const [availableServices, setAvailableServices] = useState([]);
    const [usedServices, setUsedServices] = useState([]);
    const [error, setError] = useState(null);
    const [feedback, setFeedback] = useState(null);

    // Add service form inputs
    const [selectedServiceId, setSelectedServiceId] = useState('');
    const [quantityInput, setQuantityInput] = useState('1');
    const [submittingAdd, setSubmittingAdd] = useState(false);

    // Edit service quantity dialog/modal
    const [editState, setEditState] = useState({
        isOpen: false,
        item: null,
        quantity: '1',
        loading: false,
    });

    // Delete service confirmation dialog
    const [deleteState, setDeleteState] = useState({
        isOpen: false,
        item: null,
        loading: false,
    });

    const loadData = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const bRes = await getHostBookingDetail(id);
            const bData = bRes.data;
            setBooking(bData);

            // Fetch used services list
            const usedRes = await getBookingServices(id);
            setUsedServices(usedRes.data || []);

            // Fetch active services belonging to this property
            const propId = bData.room?.property?.id;
            if (propId) {
                const sRes = await getHostServices({ property_id: propId, status: true });
                const catalog = sRes.data || [];
                setAvailableServices(catalog);
                if (catalog.length > 0 && !selectedServiceId) {
                    setSelectedServiceId(String(catalog[0].id));
                }
            }
        } catch (err) {
            setError(getApiErrorMessage(err).message);
        } finally {
            setLoading(false);
        }
    }, [id, selectedServiceId]);

    useEffect(() => {
        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                loadData();
            }
        });
        return () => {
            isMounted = false;
        };
    }, [loadData]);

    const isCheckedIn = booking?.status === 'CHECKED_IN';

    // Calculate preview estimated amount
    const currentServiceObj = availableServices.find(
        (s) => String(s.id) === String(selectedServiceId)
    );
    const estimatedAmount = currentServiceObj && !isNaN(Number(quantityInput))
        ? Number(quantityInput) * Number(currentServiceObj.price)
        : 0;

    const handleAddService = async (e) => {
        e.preventDefault();
        if (!selectedServiceId || !quantityInput) return;

        setSubmittingAdd(true);
        setFeedback(null);
        try {
            await addBookingService(id, {
                service_id: Number(selectedServiceId),
                quantity: Number(quantityInput),
            });
            setFeedback({ type: 'success', text: 'Thêm dịch vụ vào đơn thành công.' });
            setQuantityInput('1');
            loadData();
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setFeedback({ type: 'danger', text: parsed.message });
        } finally {
            setSubmittingAdd(false);
        }
    };

    const handleConfirmUpdateQuantity = async () => {
        const { item, quantity } = editState;
        if (!item) return;

        setEditState((prev) => ({ ...prev, loading: true }));
        try {
            await updateBookingService(id, item.id, {
                quantity: Number(quantity),
            });
            setFeedback({ type: 'success', text: 'Cập nhật số lượng dịch vụ thành công.' });
            setEditState({ isOpen: false, item: null, quantity: '1', loading: false });
            loadData();
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setFeedback({ type: 'danger', text: parsed.message });
            setEditState((prev) => ({ ...prev, loading: false }));
        }
    };

    const handleConfirmDelete = async () => {
        const { item } = deleteState;
        if (!item) return;

        setDeleteState((prev) => ({ ...prev, loading: true }));
        try {
            await deleteBookingService(id, item.id);
            setFeedback({ type: 'success', text: 'Đã xóa dịch vụ khỏi đơn đặt phòng.' });
            setDeleteState({ isOpen: false, item: null, loading: false });
            loadData();
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setFeedback({ type: 'danger', text: parsed.message });
            setDeleteState((prev) => ({ ...prev, loading: false }));
        }
    };

    if (loading) {
        return (
            <div className="bk-container">
                <LoadingState message="Đang tải danh sách dịch vụ của phòng..." />
            </div>
        );
    }

    if (error || !booking) {
        return (
            <div className="bk-container">
                <ErrorState
                    title="Lỗi tải dữ liệu"
                    message={error || 'Đã xảy ra lỗi.'}
                    onRetry={loadData}
                />
            </div>
        );
    }

    const servicesTotal = usedServices.reduce(
        (sum, s) => sum + Number(s.amount || 0),
        0
    );

    return (
        <div className="bk-container" style={{ maxWidth: 960 }}>
            <div className="bk-page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 4 }}>
                        <h1 className="bk-page-title" style={{ margin: 0 }}>
                            Dịch vụ đặt phòng #{booking.booking_code}
                        </h1>
                        <StatusBadge status={booking.status} />
                    </div>
                    <p className="bk-page-subtitle">
                        Phòng {booking.room?.room_number} • Khách hàng: {booking.customer?.name}
                    </p>
                </div>

                <Link to={`/host/bookings/${id}`} className="bk-btn bk-btn-outline bk-btn-sm">
                    ← Trở lại chi tiết đơn
                </Link>
            </div>

            {feedback && (
                <div className={`bk-alert bk-alert-${feedback.type}`}>
                    {feedback.text}
                </div>
            )}

            {/* Condition Notice */}
            {!isCheckedIn ? (
                <div className="bk-alert bk-alert-warning" style={{ marginBottom: 24 }}>
                    <strong>Lưu ý: </strong>
                    Chỉ được thêm / sửa / xóa dịch vụ khi đặt phòng ở trạng thái <strong>Đang lưu trú (CHECKED_IN)</strong>. Đơn này hiện đang ở trạng thái <strong>{booking.status_label || booking.status}</strong>.
                </div>
            ) : (
                /* Add Service Form */
                <div className="bk-card" style={{ marginBottom: 24 }}>
                    <h3 className="bk-card-title" style={{ marginBottom: 14 }}>
                        + Thêm dịch vụ vào phòng
                    </h3>

                    <form onSubmit={handleAddService} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr)) auto', gap: 14, alignItems: 'flex-end' }}>
                        <div className="bk-form-group" style={{ margin: 0 }}>
                            <label className="bk-label">Chọn dịch vụ</label>
                            <select
                                className="bk-select"
                                value={selectedServiceId}
                                onChange={(e) => setSelectedServiceId(e.target.value)}
                                disabled={submittingAdd || availableServices.length === 0}
                            >
                                {availableServices.map((svc) => (
                                    <option key={svc.id} value={svc.id}>
                                        {svc.name} ({formatCurrency(svc.price)} / {svc.unit})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="bk-form-group" style={{ margin: 0 }}>
                            <label className="bk-label">Số lượng ({currentServiceObj?.unit || 'lần'})</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="9999.99"
                                className="bk-input"
                                required
                                value={quantityInput}
                                onChange={(e) => setQuantityInput(e.target.value)}
                                disabled={submittingAdd}
                            />
                        </div>

                        <div className="bk-form-group" style={{ margin: 0 }}>
                            <label className="bk-label">Thành tiền dự kiến</label>
                            <div style={{ padding: '9px 12px', background: '#f8fafc', borderRadius: 6, fontWeight: 700, color: '#0284c7', border: '1px solid #e2e8f0' }}>
                                {formatCurrency(estimatedAmount)}
                            </div>
                        </div>

                        <button
                            type="submit"
                            className="bk-btn bk-btn-primary"
                            disabled={submittingAdd || availableServices.length === 0}
                        >
                            {submittingAdd ? 'Đang thêm...' : 'Thêm dịch vụ'}
                        </button>
                    </form>
                    <p style={{ margin: '8px 0 0 0', fontSize: 12, color: '#94a3b8' }}>
                        * Thành tiền dự kiến chỉ mang tính chất tham khảo. Đơn giá và số tiền chính thức do backend tính toán và lưu snapshot.
                    </p>
                </div>
            )}

            {/* List of Used Services */}
            <div className="bk-card">
                <h3 className="bk-card-title" style={{ marginBottom: 14 }}>
                    Danh sách dịch vụ đã sử dụng (Services Used)
                </h3>

                {usedServices.length === 0 ? (
                    <p style={{ margin: '12px 0', color: '#64748b', fontSize: 14 }}>
                        Chưa có dịch vụ nào được ghi nhận cho kỳ nghỉ này.
                    </p>
                ) : (
                    <table className="bk-table">
                        <thead>
                            <tr>
                                <th>Tên dịch vụ</th>
                                <th>Đơn vị</th>
                                <th>Số lượng</th>
                                <th>Đơn giá (Snapshot)</th>
                                <th>Thành tiền</th>
                                {isCheckedIn && <th style={{ textAlign: 'right' }}>Thao tác</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {usedServices.map((item) => (
                                <tr key={item.id}>
                                    <td><strong>{item.service_name}</strong></td>
                                    <td>{item.unit}</td>
                                    <td>{Number(item.quantity)}</td>
                                    <td>{formatCurrency(item.unit_price)}</td>
                                    <td><strong>{formatCurrency(item.amount)}</strong></td>
                                    {isCheckedIn && (
                                        <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                                            <button
                                                type="button"
                                                className="bk-btn bk-btn-outline bk-btn-sm"
                                                style={{ marginRight: 6 }}
                                                onClick={() =>
                                                    setEditState({
                                                        isOpen: true,
                                                        item,
                                                        quantity: String(item.quantity),
                                                        loading: false,
                                                    })
                                                }
                                            >
                                                Sửa SL
                                            </button>
                                            <button
                                                type="button"
                                                className="bk-btn bk-btn-danger bk-btn-sm"
                                                onClick={() =>
                                                    setDeleteState({
                                                        isOpen: true,
                                                        item,
                                                        loading: false,
                                                    })
                                                }
                                            >
                                                Xóa
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            <tr style={{ background: '#f8fafc' }}>
                                <td colSpan={4} style={{ textAlign: 'right', fontWeight: 700, fontSize: 15 }}>
                                    Tổng tiền dịch vụ:
                                </td>
                                <td>
                                    <span className="bk-price-highlight" style={{ fontSize: 18 }}>
                                        {formatCurrency(servicesTotal)}
                                    </span>
                                </td>
                                {isCheckedIn && <td />}
                            </tr>
                        </tbody>
                    </table>
                )}
            </div>

            {/* Edit Quantity Modal */}
            {editState.isOpen && editState.item && (
                <div className="bk-modal-backdrop">
                    <div className="bk-modal-dialog">
                        <h3 className="bk-modal-title">Cập nhật số lượng dịch vụ</h3>
                        <p className="bk-modal-message">
                            Dịch vụ: <strong>{editState.item.service_name}</strong> (Đơn giá: {formatCurrency(editState.item.unit_price)} / {editState.item.unit})
                        </p>

                        <div className="bk-form-group">
                            <label className="bk-label">Số lượng mới</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="9999.99"
                                className="bk-input"
                                value={editState.quantity}
                                onChange={(e) =>
                                    setEditState({ ...editState, quantity: e.target.value })
                                }
                            />
                        </div>

                        <div className="bk-modal-actions">
                            <button
                                type="button"
                                className="bk-btn bk-btn-outline"
                                disabled={editState.loading}
                                onClick={() =>
                                    setEditState({ isOpen: false, item: null, quantity: '1', loading: false })
                                }
                            >
                                Hủy bỏ
                            </button>
                            <button
                                type="button"
                                className="bk-btn bk-btn-primary"
                                disabled={editState.loading}
                                onClick={handleConfirmUpdateQuantity}
                            >
                                {editState.loading ? 'Đang lưu...' : 'Lưu thay đổi'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Modal */}
            <ConfirmDialog
                isOpen={deleteState.isOpen}
                title="Xóa dịch vụ khỏi phòng"
                message={`Bạn có chắc muốn xóa dịch vụ "${deleteState.item?.service_name}" khỏi đơn đặt phòng này?`}
                confirmText="Xác nhận xóa"
                cancelText="Hủy"
                isDanger
                loading={deleteState.loading}
                onConfirm={handleConfirmDelete}
                onCancel={() => setDeleteState({ isOpen: false, item: null, loading: false })}
            />
        </div>
    );
}
