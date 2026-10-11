import { useState, useEffect, useCallback } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { previewBooking, createBooking } from '../../services/bookingService';
import { formatCurrency } from '../../utils/formatCurrency';
import { formatDate } from '../../utils/formatDate';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import AvailabilityChecker from '../../components/booking/AvailabilityChecker';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';

export default function BookingCheckoutPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    const roomId = searchParams.get('room_id');
    const checkInDate = searchParams.get('check_in_date');
    const checkOutDate = searchParams.get('check_out_date');
    const guestCount = searchParams.get('guest_count') || '1';

    const [note, setNote] = useState('');
    const [previewLoading, setPreviewLoading] = useState(true);
    const [previewError, setPreviewError] = useState(null);
    const [quoteData, setQuoteData] = useState(null);

    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(null);
    const [fieldErrors, setFieldErrors] = useState({});
    const [is401Error, setIs401Error] = useState(false);
    const [is409Conflict, setIs409Conflict] = useState(false);

    const loadQuote = useCallback(async () => {
        if (!roomId || !checkInDate || !checkOutDate) {
            setPreviewError('Thiếu thông tin đặt phòng cần thiết (phòng, ngày nhận/trả).');
            setPreviewLoading(false);
            return;
        }

        setPreviewLoading(true);
        setPreviewError(null);
        try {
            const res = await previewBooking({
                room_id: Number(roomId),
                check_in_date: checkInDate,
                check_out_date: checkOutDate,
                guest_count: Number(guestCount),
            });
            setQuoteData(res.data);
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setPreviewError(parsed.message);
        } finally {
            setPreviewLoading(false);
        }
    }, [roomId, checkInDate, checkOutDate, guestCount]);

    useEffect(() => {
        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                loadQuote();
            }
        });
        return () => {
            isMounted = false;
        };
    }, [loadQuote]);

    const handleSubmitBooking = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setSubmitError(null);
        setFieldErrors({});
        setIs401Error(false);
        setIs409Conflict(false);

        try {
            const payload = {
                room_id: Number(roomId),
                check_in_date: checkInDate,
                check_out_date: checkOutDate,
                guest_count: Number(guestCount),
                note: note.trim() || undefined,
            };

            const res = await createBooking(payload);
            const created = res.data;
            navigate(`/my-bookings/${created.id}`);
        } catch (err) {
            if (err.response?.status === 401) {
                setIs401Error(true);
                setSubmitError('Bạn cần đăng nhập tài khoản Khách hàng để tiến hành đặt phòng.');
            } else if (err.response?.status === 409) {
                setIs409Conflict(true);
                const parsed = getApiErrorMessage(err);
                setSubmitError(parsed.message || 'Phòng vừa được khách khác đặt hoặc không còn khả dụng.');
            } else {
                const parsed = getApiErrorMessage(err);
                setSubmitError(parsed.message);
                if (parsed.fieldErrors) {
                    setFieldErrors(parsed.fieldErrors);
                }
            }
        } finally {
            setSubmitting(false);
        }
    };

    if (previewLoading) {
        return (
            <div className="bk-container">
                <LoadingState message="Đang tải thông tin báo giá đặt phòng..." />
            </div>
        );
    }

    if (previewError) {
        return (
            <div className="bk-container">
                <ErrorState
                    title="Không thể khởi tạo đặt phòng"
                    message={previewError}
                    onRetry={loadQuote}
                />
                <div style={{ textAlign: 'center', marginTop: 16 }}>
                    <Link to="/search" className="bk-btn bk-btn-outline">
                        ← Quay lại tìm kiếm phòng
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="bk-container" style={{ maxWidth: 800 }}>
            <div className="bk-page-header">
                <h1 className="bk-page-title">Xác nhận đặt phòng</h1>
                <p className="bk-page-subtitle">
                    Kiểm tra chi tiết và hoàn tất đơn đặt phòng lưu trú của bạn
                </p>
            </div>

            {/* Availability Checker Live Block */}
            <AvailabilityChecker
                roomId={roomId}
                checkInDate={checkInDate}
                checkOutDate={checkOutDate}
                guestCount={guestCount}
            />

            {/* Error notifications */}
            {is401Error && (
                <div className="bk-alert bk-alert-danger">
                    <p style={{ margin: '0 0 8px 0', fontWeight: 600 }}>
                        {submitError}
                    </p>
                    <Link to="/login-test" className="bk-btn bk-btn-primary bk-btn-sm">
                        Đi tới trang Đăng nhập (Login Test) →
                    </Link>
                </div>
            )}

            {is409Conflict && (
                <div className="bk-alert bk-alert-danger">
                    <p style={{ margin: '0 0 8px 0', fontWeight: 600 }}>
                        ⚠️ {submitError}
                    </p>
                    <Link to="/search" className="bk-btn bk-btn-outline bk-btn-sm">
                        ← Quay lại tìm kiếm phòng khác
                    </Link>
                </div>
            )}

            {!is401Error && !is409Conflict && submitError && (
                <div className="bk-alert bk-alert-danger">
                    {submitError}
                </div>
            )}

            {/* Checkout Form & Summary */}
            <form onSubmit={handleSubmitBooking}>
                <div className="bk-card" style={{ marginBottom: 24 }}>
                    <h3 className="bk-card-title" style={{ marginBottom: 16 }}>
                        Thông tin lưu trú
                    </h3>

                    <table className="bk-table">
                        <tbody>
                            <tr>
                                <td style={{ width: '40%', color: '#64748b' }}>Mã phòng:</td>
                                <td><strong>#{quoteData?.room_id}</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Ngày nhận phòng:</td>
                                <td><strong>{formatDate(quoteData?.check_in_date)}</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Ngày trả phòng:</td>
                                <td><strong>{formatDate(quoteData?.check_out_date)}</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Số lượng khách:</td>
                                <td><strong>{quoteData?.guest_count} khách</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Số đêm lưu trú:</td>
                                <td><strong>{quoteData?.number_of_nights} đêm</strong></td>
                            </tr>
                            <tr>
                                <td style={{ color: '#64748b' }}>Đơn giá một đêm:</td>
                                <td>{formatCurrency(quoteData?.price_per_night)}</td>
                            </tr>
                            <tr style={{ background: '#f8fafc' }}>
                                <td style={{ fontWeight: 700, fontSize: 16 }}>Tổng tiền phòng:</td>
                                <td>
                                    <span className="bk-price-highlight">
                                        {formatCurrency(quoteData?.room_total)}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div className="bk-card" style={{ marginBottom: 24 }}>
                    <h3 className="bk-card-title" style={{ marginBottom: 12 }}>
                        Yêu cầu đặc biệt / Ghi chú
                    </h3>

                    <div className="bk-form-group" style={{ margin: 0 }}>
                        <label className="bk-label">Ghi chú cho cơ sở lưu trú (tùy chọn)</label>
                        <textarea
                            rows={3}
                            placeholder="Ví dụ: Xin chuẩn bị phòng tầng cao, giờ đến dự kiến..."
                            className="bk-textarea"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            disabled={submitting}
                        />
                        {fieldErrors.note && (
                            <p className="bk-error-text">{fieldErrors.note[0]}</p>
                        )}
                    </div>
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Link to="/search" className="bk-btn bk-btn-outline">
                        ← Hủy & Quay lại
                    </Link>

                    <button
                        type="submit"
                        className="bk-btn bk-btn-primary"
                        style={{ padding: '10px 24px', fontSize: 16 }}
                        disabled={submitting || (quoteData?.availability && !quoteData.availability.available)}
                    >
                        {submitting ? 'Đang xử lý...' : '✓ Xác nhận đặt phòng'}
                    </button>
                </div>
            </form>
        </div>
    );
}
