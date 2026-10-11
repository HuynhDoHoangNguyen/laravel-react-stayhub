import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
    getHostBookings,
    confirmBooking,
    rejectBooking,
} from '../../services/hostBookingService';
import { getHostProperties } from '../../services/propertyServiceCatalogService';
import { formatCurrency } from '../../utils/formatCurrency';
import { formatDate } from '../../utils/formatDate';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import StatusBadge from '../../components/booking/shared/StatusBadge';
import ConfirmDialog from '../../components/booking/shared/ConfirmDialog';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';
import EmptyState from '../../components/booking/shared/EmptyState';
import Pagination from '../../components/booking/shared/Pagination';

const STATUS_OPTIONS = [
    { value: '', label: 'Tất cả trạng thái' },
    { value: 'PENDING', label: 'Chờ xác nhận' },
    { value: 'CONFIRMED', label: 'Đã xác nhận' },
    { value: 'CHECKED_IN', label: 'Đang lưu trú' },
    { value: 'COMPLETED', label: 'Đã hoàn thành' },
    { value: 'CANCELLED', label: 'Đã hủy' },
    { value: 'REJECTED', label: 'Từ chối' },
];

export default function HostBookingListPage() {
    const [searchParams, setSearchParams] = useSearchParams();

    const statusParam = searchParams.get('status') || '';
    const propertyIdParam = searchParams.get('property_id') || '';
    const startDateParam = searchParams.get('start_date') || '';
    const endDateParam = searchParams.get('end_date') || '';
    const keywordParam = searchParams.get('keyword') || '';
    const pageParam = parseInt(searchParams.get('page') || '1', 10);

    const [filterInputs, setFilterInputs] = useState({
        status: statusParam,
        property_id: propertyIdParam,
        start_date: startDateParam,
        end_date: endDateParam,
        keyword: keywordParam,
    });

    const [properties, setProperties] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [result, setResult] = useState(null);

    // Dialog state for quick confirm / reject
    const [dialogState, setDialogState] = useState({
        isOpen: false,
        type: null, // 'confirm' | 'reject'
        booking: null,
        reason: '',
        loading: false,
        error: null,
    });

    // Load host properties for filter dropdown
    useEffect(() => {
        let isMounted = true;
        getHostProperties()
            .then((res) => {
                if (isMounted) {
                    setProperties(res.data || []);
                }
            })
            .catch(() => {});
        return () => {
            isMounted = false;
        };
    }, []);

    const loadBookings = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = { page: pageParam };
            if (statusParam) params.status = statusParam;
            if (propertyIdParam) params.property_id = propertyIdParam;
            if (startDateParam) params.start_date = startDateParam;
            if (endDateParam) params.end_date = endDateParam;
            if (keywordParam) params.keyword = keywordParam;

            const res = await getHostBookings(params);
            setResult(res);
        } catch (err) {
            setError(getApiErrorMessage(err).message);
        } finally {
            setLoading(false);
        }
    }, [statusParam, propertyIdParam, startDateParam, endDateParam, keywordParam, pageParam]);

    useEffect(() => {
        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                loadBookings();
            }
        });
        return () => {
            isMounted = false;
        };
    }, [loadBookings]);

    const handleFilterSubmit = (e) => {
        e.preventDefault();
        const next = new URLSearchParams();
        if (filterInputs.status) next.set('status', filterInputs.status);
        if (filterInputs.property_id) next.set('property_id', filterInputs.property_id);
        if (filterInputs.start_date) next.set('start_date', filterInputs.start_date);
        if (filterInputs.end_date) next.set('end_date', filterInputs.end_date);
        if (filterInputs.keyword) next.set('keyword', filterInputs.keyword);
        next.set('page', '1');
        setSearchParams(next);
    };

    const handlePageChange = (newPage) => {
        const next = new URLSearchParams(searchParams);
        next.set('page', String(newPage));
        setSearchParams(next);
    };

    const openConfirmDialog = (booking) => {
        setDialogState({
            isOpen: true,
            type: 'confirm',
            booking,
            reason: '',
            loading: false,
            error: null,
        });
    };

    const openRejectDialog = (booking) => {
        setDialogState({
            isOpen: true,
            type: 'reject',
            booking,
            reason: '',
            loading: false,
            error: null,
        });
    };

    const handleDialogAction = async () => {
        const { type, booking, reason } = dialogState;
        if (!booking) return;

        setDialogState((prev) => ({ ...prev, loading: true, error: null }));
        try {
            if (type === 'confirm') {
                await confirmBooking(booking.id);
            } else if (type === 'reject') {
                await rejectBooking(booking.id, reason);
            }
            setDialogState({ isOpen: false, type: null, booking: null, reason: '', loading: false, error: null });
            loadBookings();
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setDialogState((prev) => ({
                ...prev,
                loading: false,
                error: parsed.message,
            }));
        }
    };

    return (
        <div className="bk-container">
            <div className="bk-page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <h1 className="bk-page-title">Quản lý đặt phòng</h1>
                    <p className="bk-page-subtitle">
                        Xem và xử lý các đơn đặt phòng của các cơ sở lưu trú trực thuộc
                    </p>
                </div>

                <div>
                    <Link to="/host/services" className="bk-btn bk-btn-outline bk-btn-sm">
                        Quản lý dịch vụ cơ sở →
                    </Link>
                </div>
            </div>

            {/* Filter Bar */}
            <form onSubmit={handleFilterSubmit} className="bk-search-bar" style={{ marginBottom: 20 }}>
                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Cơ sở lưu trú</label>
                    <select
                        className="bk-select"
                        value={filterInputs.property_id}
                        onChange={(e) =>
                            setFilterInputs({ ...filterInputs, property_id: e.target.value })
                        }
                    >
                        <option value="">Tất cả cơ sở</option>
                        {properties.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Trạng thái</label>
                    <select
                        className="bk-select"
                        value={filterInputs.status}
                        onChange={(e) =>
                            setFilterInputs({ ...filterInputs, status: e.target.value })
                        }
                    >
                        {STATUS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Từ ngày</label>
                    <input
                        type="date"
                        className="bk-input"
                        value={filterInputs.start_date}
                        onChange={(e) =>
                            setFilterInputs({ ...filterInputs, start_date: e.target.value })
                        }
                    />
                </div>

                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Đến ngày</label>
                    <input
                        type="date"
                        className="bk-input"
                        value={filterInputs.end_date}
                        onChange={(e) =>
                            setFilterInputs({ ...filterInputs, end_date: e.target.value })
                        }
                    />
                </div>

                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Tìm kiếm</label>
                    <input
                        type="text"
                        placeholder="Mã đơn, tên, email..."
                        className="bk-input"
                        value={filterInputs.keyword}
                        onChange={(e) =>
                            setFilterInputs({ ...filterInputs, keyword: e.target.value })
                        }
                    />
                </div>

                <button type="submit" className="bk-btn bk-btn-primary" disabled={loading}>
                    Lọc danh sách
                </button>
            </form>

            {/* Content Table */}
            {loading && <LoadingState message="Đang tải danh sách đặt phòng..." />}

            {error && (
                <ErrorState
                    title="Lỗi tải danh sách"
                    message={error}
                    onRetry={loadBookings}
                />
            )}

            {!loading && !error && result && result.data && result.data.length === 0 && (
                <EmptyState
                    title="Không có đơn đặt phòng nào"
                    description="Không tìm thấy đơn đặt phòng nào theo điều kiện lọc hiện tại."
                />
            )}

            {!loading && !error && result && result.data && result.data.length > 0 && (
                <div className="bk-card" style={{ padding: 0, overflowX: 'auto' }}>
                    <table className="bk-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Cơ sở / Phòng</th>
                                <th>Nhận phòng</th>
                                <th>Trả phòng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th style={{ textAlign: 'right' }}>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {result.data.map((b) => (
                                <tr key={b.id}>
                                    <td>
                                        <strong style={{ color: '#2563eb' }}>
                                            #{b.booking_code}
                                        </strong>
                                    </td>
                                    <td>
                                        <div><strong>{b.customer?.name || 'Khách'}</strong></div>
                                        <div style={{ fontSize: 12, color: '#64748b' }}>
                                            {b.customer?.email}
                                        </div>
                                    </td>
                                    <td>
                                        <div>{b.room?.property?.name}</div>
                                        <div style={{ fontSize: 12, color: '#64748b' }}>
                                            {b.room?.name} (P.{b.room?.room_number})
                                        </div>
                                    </td>
                                    <td>{formatDate(b.check_in_date)}</td>
                                    <td>{formatDate(b.check_out_date)}</td>
                                    <td>
                                        <strong>{formatCurrency(b.room_total)}</strong>
                                    </td>
                                    <td>
                                        <StatusBadge status={b.status} />
                                    </td>
                                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                                        {b.status === 'PENDING' && (
                                            <>
                                                <button
                                                    type="button"
                                                    className="bk-btn bk-btn-primary bk-btn-sm"
                                                    style={{ marginRight: 6 }}
                                                    onClick={() => openConfirmDialog(b)}
                                                >
                                                    Duyệt
                                                </button>
                                                <button
                                                    type="button"
                                                    className="bk-btn bk-btn-danger bk-btn-sm"
                                                    style={{ marginRight: 6 }}
                                                    onClick={() => openRejectDialog(b)}
                                                >
                                                    Từ chối
                                                </button>
                                            </>
                                        )}
                                        <Link
                                            to={`/host/bookings/${b.id}`}
                                            className="bk-btn bk-btn-outline bk-btn-sm"
                                        >
                                            Chi tiết
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {result.meta && (
                        <div style={{ padding: '0 16px 16px 16px' }}>
                            <Pagination
                                currentPage={result.meta.current_page}
                                lastPage={result.meta.last_page}
                                total={result.meta.total}
                                perPage={result.meta.per_page}
                                onPageChange={handlePageChange}
                            />
                        </div>
                    )}
                </div>
            )}

            {/* Confirm / Reject Quick Action Dialog */}
            <ConfirmDialog
                isOpen={dialogState.isOpen && dialogState.type === 'confirm'}
                title="Xác nhận duyệt đặt phòng"
                message={`Bạn có chắc chắn muốn xác nhận đơn đặt phòng #${dialogState.booking?.booking_code}? Sau khi xác nhận, phòng sẽ chính thức được giữ chỗ.`}
                confirmText="Xác nhận duyệt"
                cancelText="Đóng"
                loading={dialogState.loading}
                onConfirm={handleDialogAction}
                onCancel={() => setDialogState({ isOpen: false, type: null, booking: null, reason: '', loading: false, error: null })}
            />

            {/* Custom Reject Dialog with Reason */}
            {dialogState.isOpen && dialogState.type === 'reject' && (
                <div className="bk-modal-backdrop">
                    <div className="bk-modal-dialog">
                        <h3 className="bk-modal-title">Từ chối đơn đặt phòng</h3>
                        <p className="bk-modal-message">
                            Đơn đặt phòng #{dialogState.booking?.booking_code} sẽ chuyển sang trạng thái TỪ CHỐI và phòng sẽ được giải phóng.
                        </p>

                        {dialogState.error && (
                            <div className="bk-alert bk-alert-danger" style={{ marginBottom: 12 }}>
                                {dialogState.error}
                            </div>
                        )}

                        <div className="bk-form-group">
                            <label className="bk-label">Lý do từ chối (tùy chọn)</label>
                            <input
                                type="text"
                                placeholder="Nhập lý do từ chối..."
                                className="bk-input"
                                value={dialogState.reason}
                                onChange={(e) =>
                                    setDialogState({ ...dialogState, reason: e.target.value })
                                }
                            />
                        </div>

                        <div className="bk-modal-actions">
                            <button
                                type="button"
                                className="bk-btn bk-btn-outline"
                                disabled={dialogState.loading}
                                onClick={() =>
                                    setDialogState({ isOpen: false, type: null, booking: null, reason: '', loading: false, error: null })
                                }
                            >
                                Hủy bỏ
                            </button>
                            <button
                                type="button"
                                className="bk-btn bk-btn-danger"
                                disabled={dialogState.loading}
                                onClick={handleDialogAction}
                            >
                                {dialogState.loading ? 'Đang xử lý...' : 'Xác nhận từ chối'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
