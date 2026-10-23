import api from '@/api/axios';
import { MOCK_BOOKINGS } from '@/mocks/bookingMockData';

/**
 * Cấu hình hiển thị chuẩn hóa 6 trạng thái từ Backend BookingStatus:
 * PENDING, CONFIRMED, CHECKED_IN, COMPLETED, CANCELLED, REJECTED
 */
export const BOOKING_STATUS_CONFIG = {
  PENDING: {
    label: 'Chờ xác nhận',
    dot: 'bg-amber-500',
    text: 'text-amber-800',
    bg: 'bg-amber-50 border border-amber-200/80',
    hint: 'Chờ chủ chỗ nghỉ phản hồi',
  },
  CONFIRMED: {
    label: 'Đã xác nhận',
    dot: 'bg-emerald-600',
    text: 'text-emerald-800',
    bg: 'bg-emerald-50 border border-emerald-200/80',
    hint: 'Phòng đã sẵn sàng đón tiếp',
  },
  CHECKED_IN: {
    label: 'Đang lưu trú',
    dot: 'bg-teal-600',
    text: 'text-teal-800',
    bg: 'bg-teal-50 border border-teal-200/80',
    hint: 'Quý khách đang nhận phòng lưu trú',
  },
  COMPLETED: {
    label: 'Đã hoàn thành',
    dot: 'bg-sky-600',
    text: 'text-sky-800',
    bg: 'bg-sky-50 border border-sky-200/80',
    hint: 'Kỳ nghỉ đã hoàn tất thanh toán',
  },
  CANCELLED: {
    label: 'Đã hủy',
    dot: 'bg-rose-500',
    text: 'text-rose-800',
    bg: 'bg-rose-50 border border-rose-200/80',
    hint: 'Đơn đã hủy theo yêu cầu',
  },
  REJECTED: {
    label: 'Bị từ chối',
    dot: 'bg-stone-500',
    text: 'text-stone-700',
    bg: 'bg-stone-100 border border-stone-200/80',
    hint: 'Cơ sở lưu trú từ chối tiếp nhận',
  },
};

/**
 * Định dạng tiền tệ VND chính xác.
 */
export function formatVnd(amount) {
  const numeric = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(numeric);
}

/**
 * Định dạng ngày YYYY-MM-DD sang định dạng tiếng Việt ngắn (DD/MM/YYYY).
 */
export function formatDateVi(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(d);
}

/**
 * Định dạng ngày chi tiết kèm thứ (ví dụ: Thứ Sáu, 10 tháng 11, 2026).
 */
export function formatLongDateVi(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return new Intl.DateTimeFormat('vi-VN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(d);
}

/**
 * Tính số đêm giữa 2 ngày.
 */
export function nightsBetween(checkInStr, checkOutStr) {
  if (!checkInStr || !checkOutStr) return 1;
  const inDate = new Date(checkInStr);
  const outDate = new Date(checkOutStr);
  const diffTime = outDate.getTime() - inDate.getTime();
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
  return Math.max(1, isNaN(diffDays) ? 1 : diffDays);
}

/**
 * Lọc danh sách booking theo tab khách hàng:
 * - UPCOMING: CONFIRMED (không gộp CHECKED_IN)
 * - CHECKED_IN: CHECKED_IN (Đang lưu trú)
 * - COMPLETED: chỉ khi backend trả đúng COMPLETED (không tự động suy đoán từ ngày checkout)
 * - CANCELLED: CANCELLED hoặc REJECTED (với nhãn phân biệt)
 * - ALL: toàn bộ đơn (bao gồm cả PENDING)
 */
function filterBookingsByTab(list, tab) {
  switch (tab) {
    case 'UPCOMING':
      return list.filter((b) => b.status === 'CONFIRMED');
    case 'CHECKED_IN':
      return list.filter((b) => b.status === 'CHECKED_IN');
    case 'COMPLETED':
      return list.filter((b) => b.status === 'COMPLETED');
    case 'CANCELLED':
      return list.filter((b) => b.status === 'CANCELLED' || b.status === 'REJECTED');
    case 'ALL':
    default:
      return list;
  }
}

/**
 * Sắp xếp danh sách booking.
 */
function sortBookings(list, sort) {
  const arr = [...list];
  switch (sort) {
    case 'NEW':
      return arr.sort((a, b) => new Date(b.check_in_date).getTime() - new Date(a.check_in_date).getTime());
    case 'OLD':
      return arr.sort((a, b) => new Date(a.check_in_date).getTime() - new Date(b.check_in_date).getTime());
    case 'PRICE_DESC':
      return arr.sort((a, b) => (parseFloat(b.room_total) || 0) - (parseFloat(a.room_total) || 0));
    case 'PRICE_ASC':
      return arr.sort((a, b) => (parseFloat(a.room_total) || 0) - (parseFloat(b.room_total) || 0));
    case 'RECENT':
      return arr.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
    default:
      return arr;
  }
}

/**
 * Tách biệt Adapter gọi API để dễ dàng thay đổi endpoint khi TV2 bàn giao.
 */
const ENDPOINTS = {
  customerBookings: '/api/customer/bookings',
  customerBookingDetail: (id) => `/api/customer/bookings/${id}`,
  customerBookingCancel: (id) => `/api/customer/bookings/${id}/cancel`,
  customerBookingServices: (id) => `/api/customer/bookings/${id}/services`,
  bookingPreview: '/api/public/bookings/preview',
  createBooking: '/api/customer/bookings',
};

function bookingApiError(error, fallback) {
  const status = error.response?.status;
  const serverMessage = error.response?.data?.message;
  const messages = {
    401: 'Vui lòng đăng nhập để tiếp tục.',
    403: 'Tài khoản hiện tại không có quyền thực hiện thao tác này.',
    404: 'Không tìm thấy thông tin đơn đặt phòng hoặc bạn không có quyền truy cập.',
    409: 'Phòng vừa được đặt trong khoảng ngày này. Vui lòng chọn ngày hoặc phòng khác.',
    422: 'Yêu cầu không thể thực hiện. Vui lòng kiểm tra trạng thái đơn đặt phòng.',
  };
  const parsed = new Error(serverMessage || messages[status] || fallback);
  parsed.status = status;
  parsed.errors = error.response?.data?.errors || null;
  parsed.isNetworkError = !error.response;
  return parsed;
}

export const bookingService = {
  async previewBooking(payload) {
    try {
      const response = await api.post(ENDPOINTS.bookingPreview, payload);
      return response.data?.data || response.data;
    } catch (error) {
      throw bookingApiError(error, 'Không thể kiểm tra giá và tình trạng phòng.');
    }
  },

  async createBooking(payload) {
    try {
      const response = await api.post(ENDPOINTS.createBooking, payload);
      return response.data?.data || response.data;
    } catch (error) {
      throw bookingApiError(error, 'Không thể hoàn tất đặt phòng. Vui lòng thử lại.');
    }
  },

  /**
   * Lấy danh sách đơn đặt phòng của khách hàng hiện tại.
   * Tuân thủ Mock Safety & Aggregate Statistics Rules:
   * - Chỉ dùng mock khi DEV === true VÀ VITE_ENABLE_BOOKING_MOCKS === 'true'.
   * - Không fallback sang mock nếu API thật gặp lỗi.
   * - Trong Dev Mock: tính stats từ toàn bộ tập mock.
   * - Với Real API: chỉ dùng aggregate stats từ API nếu có, tuyệt đối KHÔNG tính từ dữ liệu 1 trang.
   */
  async getMyBookings({ tab = 'ALL', sort = 'NEW', page = 1, perPage = 5 } = {}) {
    const isMockEnabled =
      import.meta.env.DEV === true &&
      import.meta.env.VITE_ENABLE_BOOKING_MOCKS === 'true';

    if (isMockEnabled) {
      // Tính toán trực tiếp trên toàn bộ tập dữ liệu Mock
      const allRaw = [...MOCK_BOOKINGS];
      const filtered = sortBookings(filterBookingsByTab(allRaw, tab), sort);

      const total = filtered.length;
      const totalPages = Math.max(1, Math.ceil(total / perPage));
      const validPage = Math.min(Math.max(1, page), totalPages);
      const start = (validPage - 1) * perPage;
      const paginated = filtered.slice(start, start + perPage);

      return {
        data: paginated,
        all: allRaw, // Toàn bộ tập dữ liệu để tính stats chuẩn xác không phụ thuộc pagination
        stats: {
          upcoming: allRaw.filter((b) => b.status === 'CONFIRMED').length,
          checkedIn: allRaw.filter((b) => b.status === 'CHECKED_IN').length,
          completed: allRaw.filter((b) => b.status === 'COMPLETED').length,
          cancelled: allRaw.filter((b) => b.status === 'CANCELLED' || b.status === 'REJECTED').length,
        },
        meta: {
          total,
          page: validPage,
          per_page: perPage,
          total_pages: totalPages,
        },
        source: 'mock',
        isMock: true,
      };
    }

    // Khi không bật mock (hoặc trên production) -> Gọi API thật
    try {
      const statusByTab = {
        PENDING: 'PENDING',
        UPCOMING: 'CONFIRMED',
        CHECKED_IN: 'CHECKED_IN',
        COMPLETED: 'COMPLETED',
        CANCELLED: 'CANCELLED',
        REJECTED: 'REJECTED',
      };
      const params = { page, per_page: perPage };
      if (statusByTab[tab]) params.status = statusByTab[tab];

      const response = await api.get(ENDPOINTS.customerBookings, {
        params,
      });

      const resData = response.data;
      const list = resData?.data || (Array.isArray(resData) ? resData : []);
      const apiMeta = resData?.meta || {};
      const meta = {
        ...apiMeta,
        total: apiMeta.total ?? list.length,
        page: apiMeta.current_page || apiMeta.page || page,
        per_page: apiMeta.per_page || perPage,
        total_pages: apiMeta.last_page || apiMeta.total_pages || Math.ceil(list.length / perPage) || 1,
      };

      // Chỉ lấy stats tổng hợp khi backend có cung cấp aggregate metadata
      const aggregateStats = resData?.stats || resData?.meta?.stats || null;
      const fullList = Array.isArray(resData?.all) ? resData.all : null;

      return {
        data: list,
        all: fullList, // Tuyệt đối KHÔNG fallback về list phân trang của 1 trang đơn lẻ
        stats: aggregateStats,
        meta,
        source: 'api',
        isMock: false,
      };
    } catch (err) {
      // Phân loại mã lỗi rõ ràng, không giả mạo thành công hay ngầm tráo mock
      throw bookingApiError(err, 'Không thể tải danh sách đơn đặt phòng. Vui lòng thử lại sau.');
    }
  },

  /**
   * Lấy chi tiết một đơn đặt phòng theo ID.
   */
  async getBookingDetail(bookingId) {
    if (!bookingId || isNaN(Number(bookingId)) || Number(bookingId) <= 0) {
      const invalidErr = new Error('Mã đơn đặt phòng không hợp lệ.');
      invalidErr.status = 400;
      throw invalidErr;
    }

    const numericId = parseInt(bookingId, 10);
    const isMockEnabled =
      import.meta.env.DEV === true &&
      import.meta.env.VITE_ENABLE_BOOKING_MOCKS === 'true';

    if (isMockEnabled) {
      const found = MOCK_BOOKINGS.find((b) => b.id === numericId);
      if (!found) {
        const notFoundErr = new Error('Không tìm thấy đơn đặt phòng yêu cầu.');
        notFoundErr.status = 404;
        throw notFoundErr;
      }
      return {
        data: found,
        source: 'mock',
        isMock: true,
      };
    }

    // Gọi API thật
    try {
      const response = await api.get(ENDPOINTS.customerBookingDetail(numericId));
      return {
        data: response.data?.data || response.data,
        source: 'api',
        isMock: false,
      };
    } catch (err) {
      const status = err.response?.status;
      const message =
        status === 404
          ? 'Không tìm thấy thông tin đơn đặt phòng này.'
          : err.response?.data?.message || 'Không thể tải chi tiết đơn đặt phòng.';

      const parsedError = new Error(message);
      parsedError.status = status;
      throw parsedError;
    }
  },

  async getBookingServices(bookingId) {
    try {
      const response = await api.get(ENDPOINTS.customerBookingServices(bookingId));
      return response.data?.data || [];
    } catch (error) {
      throw bookingApiError(error, 'Không thể tải danh sách dịch vụ của đơn đặt phòng.');
    }
  },

  async cancelBooking(bookingId) {
    try {
      const response = await api.post(ENDPOINTS.customerBookingCancel(bookingId));
      return response.data?.data || response.data;
    } catch (error) {
      throw bookingApiError(error, 'Không thể hủy đơn đặt phòng. Vui lòng thử lại.');
    }
  },
};

export default bookingService;
