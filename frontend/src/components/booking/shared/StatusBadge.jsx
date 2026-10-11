import { getBookingStatusInfo } from '../../../utils/bookingStatus';

export default function StatusBadge({ status }) {
    const info = getBookingStatusInfo(status);

    return (
        <span
            className={`bk-status-badge bk-status-${info.variant}`}
            style={{
                color: info.color,
                backgroundColor: info.bgColor,
            }}
        >
            {info.label}
        </span>
    );
}
