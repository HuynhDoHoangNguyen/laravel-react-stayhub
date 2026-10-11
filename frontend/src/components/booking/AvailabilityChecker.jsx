import { useState, useEffect } from 'react';
import { checkRoomAvailability } from '../../services/availabilityService';
import { REASON_TRANSLATIONS, getApiErrorMessage } from '../../utils/getApiErrorMessage';
import { formatDate } from '../../utils/formatDate';

export default function AvailabilityChecker({
    roomId,
    checkInDate,
    checkOutDate,
    guestCount,
}) {
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState(null);
    const [errorMsg, setErrorMsg] = useState(null);

    useEffect(() => {
        if (!roomId || !checkInDate || !checkOutDate || !guestCount) {
            return;
        }

        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                setLoading(true);
                setErrorMsg(null);
            }
        });

        checkRoomAvailability(roomId, {
            check_in_date: checkInDate,
            check_out_date: checkOutDate,
            guest_count: Number(guestCount),
        })
            .then((res) => {
                if (isMounted) {
                    setResult(res.data);
                }
            })
            .catch((err) => {
                if (isMounted) {
                    const parsed = getApiErrorMessage(err);
                    setErrorMsg(parsed.message);
                }
            })
            .finally(() => {
                if (isMounted) {
                    setLoading(false);
                }
            });

        return () => {
            isMounted = false;
        };
    }, [roomId, checkInDate, checkOutDate, guestCount]);

    if (!roomId || !checkInDate || !checkOutDate) {
        return null;
    }

    if (loading) {
        return (
            <div className="bk-alert bk-alert-info">
                <span>Đang kiểm tra tình trạng phòng...</span>
            </div>
        );
    }

    if (errorMsg) {
        return (
            <div className="bk-alert bk-alert-danger">
                <strong>Không thể kiểm tra phòng:</strong> {errorMsg}
            </div>
        );
    }

    if (!result) {
        return null;
    }

    const { available, reasons = [], blocked_ranges = [] } = result;

    return (
        <div className="bk-availability-result" style={{ marginBottom: 16 }}>
            {available ? (
                <div className="bk-alert bk-alert-success">
                    <strong>✓ Phòng khả dụng:</strong> Phòng hoàn toàn trống và sẵn sàng cho lịch trình của bạn.
                </div>
            ) : (
                <div className="bk-alert bk-alert-danger">
                    <strong>✗ Phòng không khả dụng:</strong>
                    <ul style={{ margin: '6px 0 0 16px', padding: 0 }}>
                        {reasons.map((r, idx) => (
                            <li key={idx}>
                                {REASON_TRANSLATIONS[r] || r}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {blocked_ranges && blocked_ranges.length > 0 && (
                <div className="bk-alert bk-alert-warning" style={{ fontSize: 13 }}>
                    <strong>Lịch bận của phòng:</strong>
                    <ul style={{ margin: '4px 0 0 16px', padding: 0 }}>
                        {blocked_ranges.map((range, idx) => (
                            <li key={idx}>
                                Từ {formatDate(range.start_date)} đến {formatDate(range.end_date)}{' '}
                                ({range.type === 'BOOKING' ? 'Khách đã đặt' : 'Bảo trì'})
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
