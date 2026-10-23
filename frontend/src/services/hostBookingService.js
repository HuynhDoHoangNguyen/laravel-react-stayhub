import api from '@/api/axios';

const BASE_URL = '/api/host/bookings';
const SERVICE_URL = '/api/host/services';

function toHostBookingError(error, fallback) {
  const status = error.response?.status;
  const serverMessage = error.response?.data?.message;
  const messages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Tài khoản hiện tại không có quyền quản lý booking này.',
    404: 'Không tìm thấy booking hoặc booking không thuộc cơ sở của bạn.',
    409: 'Phòng vừa phát sinh lịch trùng. Hãy kiểm tra lịch trước khi xác nhận.',
    422: 'Booking không thể chuyển sang trạng thái tiếp theo.',
  };
  const message = status === 409 || status === 422
    ? (serverMessage || messages[status])
    : (messages[status] || serverMessage || error.message || fallback);
  const parsed = new Error(message || fallback);
  parsed.status = status || 0;
  parsed.errors = error.response?.data?.errors || null;
  parsed.isNetworkError = !error.response;
  return parsed;
}

async function request(promise, fallback) {
  try {
    return await promise;
  } catch (error) {
    throw toHostBookingError(error, fallback);
  }
}

export const hostBookingService = {
  async list({ page = 1, perPage = 10, status = '', keyword = '', startDate = '', endDate = '' } = {}, options = {}) {
    const params = { page, per_page: perPage };
    if (status) params.status = status;
    if (keyword.trim()) params.keyword = keyword.trim();
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    const response = await request(
      api.get(BASE_URL, { params, signal: options.signal }),
      'Không thể tải danh sách booking.'
    );
    return response.data;
  },

  async detail(id, options = {}) {
    const response = await request(
      api.get(`${BASE_URL}/${id}`, { signal: options.signal }),
      'Không thể tải chi tiết booking.'
    );
    return response.data?.data || response.data;
  },

  async transition(id, action, payload = {}) {
    const response = await request(
      api.post(`${BASE_URL}/${id}/${action}`, payload),
      'Không thể cập nhật trạng thái booking.'
    );
    return response.data?.data || response.data;
  },

  async listPropertyServices(propertyId, options = {}) {
    const response = await request(
      api.get(SERVICE_URL, { params: { property_id: propertyId, status: true, per_page: 50 }, signal: options.signal }),
      'Không thể tải danh mục dịch vụ của cơ sở.'
    );
    return response.data?.data || [];
  },

  async listBookingServices(bookingId, options = {}) {
    const response = await request(
      api.get(`${BASE_URL}/${bookingId}/services`, { signal: options.signal }),
      'Không thể tải dịch vụ đã ghi nhận trong booking.'
    );
    return response.data?.data || [];
  },

  async addBookingService(bookingId, payload) {
    const response = await request(
      api.post(`${BASE_URL}/${bookingId}/services`, payload),
      'Không thể thêm dịch vụ vào booking.'
    );
    return response.data?.data || response.data;
  },

  async updateBookingService(bookingId, bookingServiceId, quantity) {
    const response = await request(
      api.put(`${BASE_URL}/${bookingId}/services/${bookingServiceId}`, { quantity }),
      'Không thể cập nhật số lượng dịch vụ.'
    );
    return response.data?.data || response.data;
  },

  async removeBookingService(bookingId, bookingServiceId) {
    await request(
      api.delete(`${BASE_URL}/${bookingId}/services/${bookingServiceId}`),
      'Không thể xóa dịch vụ khỏi booking.'
    );
  },
};

export function formatHostVnd(amount) {
  if (amount === null || amount === undefined || amount === '') return '0 ₫';
  const numeric = Number(amount);
  if (!Number.isFinite(numeric)) return '0 ₫';
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(numeric);
}

export function formatHostDate(value) {
  if (!value) return '—';
  const date = new Date(`${value.slice(0, 10)}T00:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

export const HOST_BOOKING_STATUSES = {
  PENDING: { label: 'Chờ xác nhận', style: 'border-amber-200 bg-amber-50 text-amber-800' },
  CONFIRMED: { label: 'Đã xác nhận', style: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
  CHECKED_IN: { label: 'Đang lưu trú', style: 'border-sky-200 bg-sky-50 text-sky-800' },
  COMPLETED: { label: 'Đã hoàn thành', style: 'border-slate-200 bg-slate-100 text-slate-700' },
  CANCELLED: { label: 'Đã hủy', style: 'border-rose-200 bg-rose-50 text-rose-800' },
  REJECTED: { label: 'Đã từ chối', style: 'border-rose-200 bg-rose-50 text-rose-800' },
};
