import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { getCustomerBookings } from '../../services/bookingService';
import { formatCurrency } from '../../utils/formatCurrency';
import { formatDate } from '../../utils/formatDate';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import StatusBadge from '../../components/booking/shared/StatusBadge';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';
import EmptyState from '../../components/booking/shared/EmptyState';
import Pagination from '../../components/booking/shared/Pagination';

const TABS = [
    { key: '', label: 'Tất cả' },
    { key: 'PENDING', label: 'Chờ xác nhận' },
    { key: 'CONFIRMED', label: 'Đã xác nhận' },
    { key: 'CHECKED_IN', label: 'Đang lưu trú' },
    { key: 'COMPLETED', label: 'Đã hoàn thành' },
    { key: 'CANCELLED', label: 'Đã hủy' },
    { key: 'REJECTED', label: 'Từ chối' },
];

export default function MyBookingsPage() {
    const [searchParams, setSearchParams] = useSearchParams();

    const currentStatus = searchParams.get('status') || '';
    const currentPage = parseInt(searchParams.get('page') || '1', 10);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [result, setResult] = useState(null);

    const loadBookings = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = { page: currentPage };
            if (currentStatus) {
                params.status = currentStatus;
            }
            const res = await getCustomerBookings(params);
            setResult(res);
        } catch (err) {
            setError(getApiErrorMessage(err).message);
        } finally {
            setLoading(false);
        }
    }, [currentStatus, currentPage]);

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

    const handleTabChange = (statusKey) => {
        const next = new URLSearchParams();
        if (statusKey) next.set('status', statusKey);
        next.set('page', '1');
        setSearchParams(next);
    };

    const handlePageChange = (newPage) => {
        const next = new URLSearchParams(searchParams);
        next.set('page', String(newPage));
        setSearchParams(next);
    };

    return (
        <div className="bk-container">
            <div className="bk-page-header">
                <h1 className="bk-page-title">Lịch sử đặt phòng của tôi</h1>
                <p className="bk-page-subtitle">
                    Theo dõi trạng thái và quản lý các đơn đặt phòng lưu trú
                </p>
            </div>

            {/* Status Tabs */}
            <div className="bk-tabs">
                {TABS.map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        className={`bk-tab ${currentStatus === tab.key ? 'bk-tab-active' : ''}`}
                        onClick={() => handleTabChange(tab.key)}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Content Body */}
            {loading && <LoadingState message="Đang tải lịch sử đặt phòng..." />}

            {error && (
                <ErrorState
                    title="Lỗi tải dữ liệu"
                    message={error}
                    onRetry={loadBookings}
                />
            )}

            {!loading && !error && result && result.data && result.data.length === 0 && (
                <EmptyState
                    title="Chưa có đơn đặt phòng nào"
                    description={
                        currentStatus
                            ? 'Không tìm thấy đơn đặt phòng nào với trạng thái này.'
                            : 'Bạn chưa tạo đơn đặt phòng nào trên hệ thống StayHub.'
                    }
                    action={
                        <Link to="/search" className="bk-btn bk-btn-primary bk-btn-sm">
                            Tìm phòng ngay →
                        </Link>
                    }
                />
            )}

            {!loading && !error && result && result.data && result.data.length > 0 && (
                <>
                    <div className="bk-grid">
                        {result.data.map((booking) => (
                            <div key={booking.id} className="bk-card bk-card-hover">
                                <div className="bk-card-header">
                                    <div>
                                        <span style={{ fontSize: 13, fontWeight: 700, color: '#2563eb' }}>
                                            #{booking.booking_code}
                                        </span>
                                        <h3 className="bk-card-title" style={{ marginTop: 4 }}>
                                            {booking.room?.property?.name || 'Cơ sở lưu trú'}
                                        </h3>
                                        <p style={{ margin: 0, fontSize: 13, color: '#64748b' }}>
                                            {booking.room?.name} • Phòng {booking.room?.room_number}
                                        </p>
                                    </div>
                                    <StatusBadge status={booking.status} />
                                </div>

                                <div className="bk-card-body">
                                    <p style={{ margin: '0 0 6px 0' }}>
                                        📅 {formatDate(booking.check_in_date)} → {formatDate(booking.check_out_date)}{' '}
                                        ({booking.number_of_nights} đêm)
                                    </p>
                                    <p style={{ margin: '0 0 6px 0' }}>
                                        👥 {booking.guest_count} khách
                                    </p>
                                </div>

                                <div className="bk-card-footer">
                                    <div>
                                        <span style={{ fontSize: 12, color: '#64748b' }}>Tiền phòng:</span>
                                        <div className="bk-price-highlight" style={{ fontSize: 17 }}>
                                            {formatCurrency(booking.room_total)}
                                        </div>
                                    </div>

                                    <Link
                                        to={`/my-bookings/${booking.id}`}
                                        className="bk-btn bk-btn-outline bk-btn-sm"
                                    >
                                        Chi tiết →
                                    </Link>
                                </div>
                            </div>
                        ))}
                    </div>

                    {result.meta && (
                        <Pagination
                            currentPage={result.meta.current_page}
                            lastPage={result.meta.last_page}
                            total={result.meta.total}
                            perPage={result.meta.per_page}
                            onPageChange={handlePageChange}
                        />
                    )}
                </>
            )}
        </div>
    );
}
