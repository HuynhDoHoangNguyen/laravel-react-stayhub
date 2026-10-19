import api from '@/api/axios';

/**
 * Domain Service cho module Đánh giá & Xếp hạng (Review & Rating - TV3)
 * Tuân thủ Backend API contract và nguyên tắc an toàn dữ liệu.
 */

/**
 * Lấy danh sách đánh giá công khai của một cơ sở lưu trú (Property) từ Backend.
 *
 * @param {number|string} propertyId ID của cơ sở lưu trú (phải là số nguyên dương hợp lệ)
 * @param {Object} [params]
 * @param {number} [params.page=1] Số trang (>= 1)
 * @param {number} [params.perPage=10] Số lượng trên mỗi trang (1..50)
 * @param {number|null} [params.rating=null] Lọc theo số sao (1..5)
 * @param {string} [params.sortBy='latest'] Sắp xếp ('latest' | 'rating_desc' | 'rating_asc')
 * @param {Object} [options] Tùy chọn bổ sung (như signal của AbortController)
 * @returns {Promise<Object>} Dữ liệu trả về gồm { data, summary, links, meta }
 */
export async function getPublicPropertyReviews(
  propertyId,
  { page = 1, perPage = 10, rating = null, sortBy = 'latest' } = {},
  options = {}
) {
  if (!propertyId || isNaN(Number(propertyId)) || Number(propertyId) <= 0) {
    const error = new Error('Mã cơ sở lưu trú không hợp lệ.');
    error.status = 400;
    throw error;
  }

  const numericId = parseInt(propertyId, 10);
  const queryParams = {
    page: Math.max(1, parseInt(page, 10) || 1),
    per_page: Math.min(50, Math.max(1, parseInt(perPage, 10) || 10)),
  };

  if (rating !== null && rating !== undefined && rating !== '') {
    const r = parseInt(rating, 10);
    if (r >= 1 && r <= 5) {
      queryParams.rating = r;
    }
  }

  if (sortBy && ['latest', 'rating_desc', 'rating_asc'].includes(sortBy)) {
    queryParams.sort_by = sortBy;
  }

  const response = await api.get(`/api/public/properties/${numericId}/reviews`, {
    params: queryParams,
    signal: options.signal,
  });

  return response.data;
}

/**
 * Gửi đánh giá cho đơn đặt phòng đã hoàn tất (COMPLETED) của khách hàng.
 *
 * @param {number|string} bookingId ID của đơn đặt phòng
 * @param {Object} payload { rating: number, comment: string }
 * @param {Object} [options]
 * @returns {Promise<Object>} Response chứa { data: ReviewResource }
 */
export async function createBookingReview(bookingId, payload, options = {}) {
  if (!bookingId || isNaN(Number(bookingId)) || Number(bookingId) <= 0) {
    const err = new Error('Mã đơn đặt phòng không hợp lệ.');
    err.status = 400;
    throw err;
  }
  const numericId = parseInt(bookingId, 10);

  const rating = Number(payload?.rating);
  if (!Number.isInteger(rating) || rating < 1 || rating > 5) {
    const err = new Error('Điểm đánh giá phải là số nguyên từ 1 đến 5 sao.');
    err.status = 422;
    throw err;
  }

  const comment = typeof payload?.comment === 'string' ? payload.comment.trim() : '';
  if (comment.length < 5 || comment.length > 1000) {
    const err = new Error('Nội dung đánh giá phải có từ 5 đến 1000 ký tự.');
    err.status = 422;
    throw err;
  }

  const cleanPayload = {
    rating,
    comment,
  };

  const response = await api.post(`/api/customer/bookings/${numericId}/review`, cleanPayload, {
    signal: options.signal,
  });

  return response.data;
}

/**
 * Lấy danh sách các đánh giá do khách hàng hiện tại đã gửi.
 *
 * @param {Object} [params]
 * @param {Object} [options]
 * @returns {Promise<Object>}
 */
export async function getCustomerReviews({ page = 1, perPage = 10 } = {}, options = {}) {
  const response = await api.get('/api/customer/reviews', {
    params: { page, per_page: perPage },
    signal: options.signal,
  });
  return response.data;
}

/**
 * Quét toàn bộ các trang đánh giá của Customer để kiểm tra xem đơn đặt phòng đã có đánh giá chưa.
 * Phân biệt rõ ràng 4 trạng thái:
 * - 'reviewed': Tìm thấy review cho bookingId này (kèm dữ liệu review thật).
 * - 'not_reviewed': Đã quét hết tất cả các trang mà không có bookingId này.
 * - 'unknown': Vượt quá giới hạn quét an toàn hoặc chưa xác định được.
 * - 'error': Lỗi API khi gọi backend.
 *
 * @param {number|string} bookingId
 * @param {Object} [options]
 * @param {number} [options.maxPages=5] Giới hạn số trang tối đa cần quét (mỗi trang 50 mục)
 * @param {AbortSignal} [options.signal]
 * @returns {Promise<{ status: 'reviewed'|'not_reviewed'|'unknown'|'error', review: Object|null, error?: Object, reason?: string }>}
 */
export async function getCustomerReviewForBooking(bookingId, options = {}) {
  if (!bookingId || isNaN(Number(bookingId)) || Number(bookingId) <= 0) {
    return { status: 'unknown', review: null };
  }

  const numericId = parseInt(bookingId, 10);
  const maxPages = options.maxPages || 5;
  let page = 1;

  try {
    while (page <= maxPages) {
      const response = await api.get('/api/customer/reviews', {
        params: { page, per_page: 50 },
        signal: options.signal,
      });

      const resData = response.data;
      const list = resData?.data || (Array.isArray(resData) ? resData : []);
      const meta = resData?.meta;

      const match = list.find((item) => Number(item.booking_id) === numericId);
      if (match) {
        return { status: 'reviewed', review: match };
      }

      const lastPage = meta?.last_page || 1;
      if (page >= lastPage || list.length === 0) {
        return { status: 'not_reviewed', review: null };
      }

      page++;
    }

    return {
      status: 'unknown',
      review: null,
      reason: 'Vượt quá số trang quét tối đa',
    };
  } catch (err) {
    if (err.name === 'CanceledError' || err.name === 'AbortError' || err.code === 'ERR_CANCELED') {
      throw err;
    }
    return {
      status: 'error',
      review: null,
      error: parseReviewError(err),
    };
  }
}

/**
 * Lấy danh sách đánh giá các cơ sở do Host sở hữu.
 *
 * @param {Object} [params]
 * @param {number|null} [params.propertyId]
 * @param {number|null} [params.rating]
 * @param {string|null} [params.search]
 * @param {number} [params.page=1]
 * @param {number} [params.perPage=10]
 * @param {Object} [options]
 * @param {AbortSignal} [options.signal]
 * @returns {Promise<Object>} Response chứa { data, summary, links, meta }
 */
export async function getHostReviews(
  { propertyId = null, rating = null, search = null, page = 1, perPage = 10 } = {},
  options = {}
) {
  const params = {
    page: Math.max(1, parseInt(page, 10) || 1),
    per_page: Math.min(50, Math.max(1, parseInt(perPage, 10) || 10)),
  };

  if (propertyId && !isNaN(Number(propertyId)) && Number(propertyId) > 0) {
    params.property_id = parseInt(propertyId, 10);
  }

  if (rating !== null && rating !== undefined && rating !== '') {
    const r = parseInt(rating, 10);
    if (r >= 1 && r <= 5) {
      params.rating = r;
    }
  }

  if (typeof search === 'string' && search.trim() !== '') {
    params.search = search.trim().slice(0, 255);
  }

  const response = await api.get('/api/host/reviews', {
    params,
    signal: options.signal,
  });
  return response.data;
}

/**
 * Chuẩn hóa thông điệp lỗi từ API response cho UI hiển thị thân thiện.
 *
 * @param {any} error
 * @returns {{ status: number, message: string, isNetworkError: boolean, isNotFound: boolean, isConflict: boolean, isValidation: boolean }}
 */
export function parseReviewError(error) {
  if (!error.response) {
    return {
      status: 0,
      message: error.message || 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại mạng.',
      isNetworkError: true,
      isNotFound: false,
      isConflict: false,
      isValidation: false,
    };
  }

  const status = error.response.status;
  const serverMessage = error.response.data?.message;

  switch (status) {
    case 401:
      return {
        status,
        message: 'Phiên làm việc đã hết hạn hoặc chưa đăng nhập. Vui lòng đăng nhập lại.',
        isNetworkError: false,
        isNotFound: false,
        isConflict: false,
        isValidation: false,
      };
    case 403:
      return {
        status,
        message: serverMessage || 'Bạn không có quyền thực hiện chức năng này.',
        isNetworkError: false,
        isNotFound: false,
        isConflict: false,
        isValidation: false,
      };
    case 404:
      return {
        status,
        message: serverMessage || 'Không tìm thấy thông tin đơn đặt phòng hoặc cơ sở lưu trú.',
        isNetworkError: false,
        isNotFound: true,
        isConflict: false,
        isValidation: false,
      };
    case 409:
      return {
        status,
        message: serverMessage || 'Đơn đặt phòng này đã có đánh giá trong hệ thống.',
        isNetworkError: false,
        isNotFound: false,
        isConflict: true,
        isValidation: false,
      };
    case 422:
      return {
        status,
        message: serverMessage || 'Dữ liệu đánh giá không hợp lệ (Điểm từ 1-5 sao, nhận xét từ 5-1000 ký tự).',
        isNetworkError: false,
        isNotFound: false,
        isConflict: false,
        isValidation: true,
      };
    case 500:
    default:
      return {
        status,
        message: serverMessage || 'Đã xảy ra lỗi hệ thống khi xử lý đánh giá. Vui lòng thử lại sau.',
        isNetworkError: false,
        isNotFound: false,
        isConflict: false,
        isValidation: false,
      };
  }
}

export const reviewService = {
  getPublicPropertyReviews,
  createBookingReview,
  getCustomerReviews,
  getCustomerReviewForBooking,
  getHostReviews,
  parseReviewError,
};

export default reviewService;
