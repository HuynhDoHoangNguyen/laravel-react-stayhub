import api from '../api/axios';

/**
 * Service quản lý các yêu cầu API liên quan đến Hóa đơn (Invoice / Billing)
 */

/**
 * Gửi yêu cầu Host tạo hóa đơn cho Booking đã hoàn tất (COMPLETED).
 *
 * @param {number|string} bookingId ID của Booking cần lập hóa đơn
 * @returns {Promise<Object>} Dữ liệu Invoice trả về từ backend (chứa data: { id, invoice_code, items, ... })
 */
export async function createHostInvoice(bookingId) {
    if (!bookingId || isNaN(Number(bookingId)) || Number(bookingId) <= 0) {
        throw new Error('Mã đơn đặt phòng không hợp lệ.');
    }

    const numericId = parseInt(bookingId, 10);
    const response = await api.post(`/api/host/bookings/${numericId}/invoice`);
    return response.data;
}

/**
 * Lấy danh sách hóa đơn phân trang của Host có bộ lọc và tìm kiếm.
 *
 * @param {Object} [params]
 * @param {number} [params.page=1]
 * @param {number} [params.perPage=10]
 * @param {string} [params.status='']
 * @param {string} [params.search='']
 * @returns {Promise<Object>} Object gồm { data: Invoice[], meta: Object, links: Object }
 */
export async function getHostInvoices({ page = 1, perPage = 10, status = '', search = '' } = {}) {
    const params = { page, per_page: perPage };
    if (status && status !== 'ALL') {
        params.status = status;
    }
    if (search && search.trim() !== '') {
        params.search = search.trim();
    }

    const response = await api.get('/api/host/invoices', { params });
    return response.data;
}

/**
 * Lấy chi tiết một hóa đơn của Host theo ID.
 *
 * @param {number|string} invoiceId
 * @returns {Promise<Object>} Object gồm { data: Invoice }
 */
export async function getHostInvoiceDetail(invoiceId) {
    if (!invoiceId || isNaN(Number(invoiceId)) || Number(invoiceId) <= 0) {
        throw new Error('Mã hóa đơn không hợp lệ.');
    }

    const numericId = parseInt(invoiceId, 10);
    const response = await api.get(`/api/host/invoices/${numericId}`);
    return response.data;
}

/**
 * Lấy danh sách hóa đơn phân trang của Customer.
 *
 * @param {Object} [params]
 * @param {number} [params.page=1]
 * @param {number} [params.perPage=10]
 * @param {string} [params.status='']
 * @param {string} [params.search='']
 * @returns {Promise<Object>} Object gồm { data: Invoice[], meta: Object, links: Object }
 */
export async function getCustomerInvoices({ page = 1, perPage = 10, status = '', search = '' } = {}) {
    const params = { page, per_page: perPage };
    if (status && status !== 'ALL') {
        params.status = status;
    }
    if (search && search.trim() !== '') {
        params.search = search.trim();
    }

    const response = await api.get('/api/customer/invoices', { params });
    return response.data;
}

/**
 * Lấy chi tiết một hóa đơn của Customer theo ID.
 *
 * @param {number|string} invoiceId
 * @returns {Promise<Object>} Object gồm { data: Invoice }
 */
export async function getCustomerInvoiceDetail(invoiceId) {
    if (!invoiceId || isNaN(Number(invoiceId)) || Number(invoiceId) <= 0) {
        throw new Error('Mã hóa đơn không hợp lệ.');
    }

    const numericId = parseInt(invoiceId, 10);
    const response = await api.get(`/api/customer/invoices/${numericId}`);
    return response.data;
}

/**
 * Định dạng số tiền decimal string sang tiền tệ Việt Nam Đồng (VND).
 * Tuyệt đối không dùng float để tính toán lại giá trị tài chính.
 *
 * @param {string|number} amount Chuỗi decimal string (ví dụ "2750000.00")
 * @returns {string} Chuỗi hiển thị tiền tệ (ví dụ "2.750.000 ₫")
 */
export function formatVnd(amount) {
    if (amount === null || amount === undefined || amount === '') {
        return '0 ₫';
    }

    const numericVal = typeof amount === 'number' ? amount : parseFloat(amount);
    if (isNaN(numericVal)) {
        return '0 ₫';
    }

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(numericVal);
}

/**
 * Chuẩn hóa thông điệp lỗi từ API response cho UI hiển thị thân thiện.
 *
 * @param {any} error Đối tượng lỗi từ Axios hoặc Error thông thường
 * @returns {{ status: number, message: string }}
 */
export function parseInvoiceError(error) {
    if (!error.response) {
        return {
            status: 0,
            message: error.message || 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại mạng.',
        };
    }

    const status = error.response.status;
    const serverMessage = error.response.data?.message;

    switch (status) {
        case 401:
            return {
                status,
                message: 'Phiên làm việc đã hết hạn hoặc chưa đăng nhập. Vui lòng đăng nhập lại.',
            };
        case 403:
            return {
                status,
                message: serverMessage || 'Bạn không có quyền lập hóa đơn cho đơn đặt phòng này.',
            };
        case 404:
            return {
                status,
                message: serverMessage || 'Không tìm thấy thông tin đơn đặt phòng.',
            };
        case 409:
            return {
                status,
                message: serverMessage || 'Đơn đặt phòng này đã có hóa đơn trong hệ thống.',
            };
        case 422:
            return {
                status,
                message: serverMessage || 'Dữ liệu đơn đặt phòng chưa đủ điều kiện lập hóa đơn.',
            };
        case 500:
        default:
            return {
                status,
                message: serverMessage || 'Đã xảy ra lỗi hệ thống khi lập hóa đơn. Vui lòng thử lại sau.',
            };
    }
}
