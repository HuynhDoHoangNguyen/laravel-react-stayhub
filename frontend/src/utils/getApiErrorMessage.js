/**
 * Translate backend availability error reason codes to Vietnamese.
 */
export const REASON_TRANSLATIONS = {
    CAPACITY_EXCEEDED: 'Số lượng khách vượt quá sức chứa tối đa của phòng.',
    BOOKING_OVERLAP: 'Phòng đã có khách đặt trong khoảng thời gian yêu cầu.',
    MAINTENANCE_OVERLAP: 'Phòng đang có lịch bảo trì trong khoảng thời gian yêu cầu.',
    ROOM_NOT_ACTIVE: 'Phòng hiện đang tạm ngưng hoạt động.',
    PROPERTY_NOT_ACTIVE: 'Cơ sở lưu trú hiện đang tạm ngưng hoạt động.',
};

/**
 * Parse API error response from Axios into a standardized format.
 * Handles:
 * 1. Validation errors: { message, errors: { field: ['...'] } }
 * 2. Business exceptions: { success: false, message, errors: [] }
 * 3. 409 Conflict with reasons: { success: false, message, errors: { reasons: [...] } }
 *
 * @param {any} error
 * @returns {{ message: string, fieldErrors: Record<string, string[]> }}
 */
export function getApiErrorMessage(error) {
    if (!error) {
        return { message: 'Đã xảy ra lỗi không xác định.', fieldErrors: {} };
    }

    const response = error.response;
    if (!response || !response.data) {
        return {
            message: error.message || 'Không thể kết nối đến máy chủ. Vui lòng thử lại.',
            fieldErrors: {},
        };
    }

    const data = response.data;
    let message = data.message || 'Yêu cầu không thành công.';
    let fieldErrors = {};

    // Check 409 Conflict with reasons
    if (response.status === 409 && data.errors && Array.isArray(data.errors.reasons)) {
        const translatedReasons = data.errors.reasons.map(
            (code) => REASON_TRANSLATIONS[code] || code
        );
        if (translatedReasons.length > 0) {
            message = `${message} Lý do: ${translatedReasons.join(' ')}`;
        }
        return { message, fieldErrors: {} };
    }

    // Check validation errors: data.errors is an object (not an array)
    if (data.errors && typeof data.errors === 'object' && !Array.isArray(data.errors)) {
        fieldErrors = data.errors;
    }

    return { message, fieldErrors };
}
