import axios from '../api/axios.js';
import { createChatbotApi } from '../api/chatbotApi.js';

const messages = {
    CHAT_IN_PROGRESS: 'Lượt gửi đang được xử lý. Vui lòng thử lại.',
    CHAT_RETRY_REQUIRED: 'Lượt gửi cần được thử lại.',
    CHAT_BUSY: 'Trợ lý đang bận. Vui lòng thử lại.',
    CHAT_PROVIDER_TIMEOUT: 'Trợ lý phản hồi quá thời gian. Vui lòng thử lại.',
    CHAT_PROVIDER_RATE_LIMITED: 'Trợ lý đang nhận quá nhiều yêu cầu.',
    CHAT_PROVIDER_UNAVAILABLE: 'Trợ lý chưa thể trả lời lúc này.',
    CHAT_REQUEST_CONFLICT: 'Lượt gửi đã được dùng cho câu hỏi khác.',
    CHAT_REQUEST_EXPIRED: 'Lượt gửi đã hết hạn. Hãy bắt đầu câu hỏi mới.',
    CHAT_ATTEMPTS_EXHAUSTED: 'Lượt gửi đã hết số lần thử.',
    CHAT_RESULT_UNAVAILABLE: 'Kết quả của lượt gửi không còn khả dụng.',
    CHAT_GUEST_DUPLICATE: 'Lượt gửi này đã được tiếp nhận. Bạn có thể gửi một lượt mới.',
};

const statusMessages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền dùng trợ lý.',
    404: 'Phiên trò chuyện không còn khả dụng.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng thử lại.',
    422: 'Vui lòng kiểm tra lại câu hỏi.',
    429: 'Bạn đã gửi quá nhiều yêu cầu.',
};

function isPositiveId(value) {
    return Number.isSafeInteger(value) && value > 0;
}

function projectMessage(value, sender) {
    if (!isPositiveId(value?.id) || value.sender !== sender
        || typeof value.message !== 'string' || !value.message.trim()
        || typeof value.created_at !== 'string' || !Number.isFinite(Date.parse(value.created_at))) {
        throw new Error('Phản hồi từ trợ lý không hợp lệ.');
    }

    return { id: value.id, sender, message: value.message, created_at: value.created_at };
}

function projectTurn(envelope, requestId) {
    const data = envelope?.data;
    if (!isPositiveId(data?.session_id) || data.request_id !== requestId.toLowerCase()) {
        throw new Error('Phản hồi từ trợ lý không hợp lệ.');
    }

    const user = projectMessage(data.user_message, 'USER');
    const assistant = projectMessage(data.assistant_message, 'ASSISTANT');
    if (user.id === assistant.id) {
        throw new Error('Phản hồi từ trợ lý không hợp lệ.');
    }

    return {
        session_id: data.session_id,
        request_id: data.request_id,
        user_message: user,
        assistant_message: assistant,
    };
}

function normalizeFailure(error) {
    const { status = 0, data, headers } = error?.response ?? {};
    const code = data?.code;
    const retry = headers?.['retry-after'];
    const retryAfter = /^[0-9]+$/.test(retry ?? '') ? Number(retry) : null;
    const fieldMessage = data?.errors?.message?.[0];

    const message = (status === 422 && typeof fieldMessage === 'string' && fieldMessage)
        || (Object.hasOwn(messages, code) && messages[code])
        || (Object.hasOwn(statusMessages, status) && statusMessages[status])
        || 'Không thể xác nhận kết quả. Vui lòng thử lại.';

    return Object.assign(new Error(message), {
        status,
        code,
        retryAfter: Number.isSafeInteger(retryAfter) ? retryAfter : null,
        retryable: [0, 419, 429, 500, 502, 503, 504].includes(status) || ['CHAT_IN_PROGRESS', 'CHAT_RETRY_REQUIRED'].includes(code),
    });
}

export function createChatbotService(client) {
    const api = createChatbotApi(client);

    return {
        async send({ request_id, session_id, message }, { signal } = {}) {
            try {
                const envelope = await api.send({ request_id, session_id, message: message.trim() }, { signal });
                return projectTurn(envelope, request_id);
            } catch (error) {
                if (signal?.aborted || error?.code === 'ERR_CANCELED') {
                    throw error;
                }

                throw normalizeFailure(error);
            }
        },
    };
}

export const chatbotService = createChatbotService(axios);

export const publicChatEnabled = import.meta.env?.VITE_ENABLE_GUEST_CHAT === 'true';

export function createGuestChatbotService(client) {
    const api = createChatbotApi(client);
    return {
        async send({ request_id, message }, { signal } = {}) {
            try {
                const response = await api.sendPublic({ request_id, message: message.trim() }, { signal });
                const { message: reply, rooms, action } = response?.data ?? {};
                if (typeof reply !== 'string' || !reply.trim() || !Array.isArray(rooms) || rooms.length > 3
                    || ![null, 'bookings', 'invoices', 'search'].includes(action)
                    || rooms.some((room) => !isPositiveId(room?.room_id) || !isPositiveId(room?.property_id)
                        || typeof room.room_name !== 'string' || typeof room.property_name !== 'string')) {
                    throw new Error('Phản hồi từ trợ lý không hợp lệ.');
                }

                return { reply, action, rooms: rooms.map((room) => ({
                    roomId: room.room_id, roomName: room.room_name,
                    propertyId: room.property_id, propertyName: room.property_name,
                })) };
            } catch (error) {
                if (signal?.aborted || error?.code === 'ERR_CANCELED') throw error;
                throw normalizeFailure(error);
            }
        },
    };
}

export const guestChatbotService = createGuestChatbotService(axios);
