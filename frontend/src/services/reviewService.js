import api from '../api/axios.js';
import { createReviewApi } from '../api/reviewApi.js';
import { requireApiId } from '../api/apiId.js';
import { normalizeApiError, localValidationError } from '../api/apiError.js';
import { mapCreatedReview, mapReviewPage } from '../mappers/reviewMapper.js';

const reviewApi = createReviewApi(api);

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
  const numericId = requireApiId(propertyId, 'Mã cơ sở lưu trú');
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

  const response = await reviewApi.getPublicPropertyReviews(numericId, queryParams, options);
  return mapReviewPage(response, 'public');
}

/**
 * Gửi đánh giá cho đơn đặt phòng đã hoàn tất (COMPLETED) của khách hàng.
 *
 * @param {number|string} bookingId ID của đơn đặt phòng
 * @param {Object} payload { rating: number, comment: string }
 * @param {Object} [options]
 * @returns {Promise<Object>} Response chứa { data: Review }
 */
export async function createBookingReview(bookingId, payload, options = {}) {
  const numericId = requireApiId(bookingId, 'Mã đơn đặt phòng');

  const rating = Number(payload?.rating);
  if (!Number.isInteger(rating) || rating < 1 || rating > 5) {
    throw localValidationError('Điểm đánh giá phải là số nguyên từ 1 đến 5 sao.', { rating: ['Điểm phải từ 1 đến 5.'] });
  }

  const comment = typeof payload?.comment === 'string' ? payload.comment.trim() : '';
  if (comment.length < 5 || comment.length > 1000) {
    throw localValidationError('Nội dung đánh giá phải có từ 5 đến 1000 ký tự.', { comment: ['Nhận xét phải từ 5 đến 1000 ký tự.'] });
  }

  const cleanPayload = {
    rating,
    comment,
  };

  const response = await reviewApi.createBookingReview(numericId, cleanPayload, options);
  return mapCreatedReview(response);
}

/**
 * Lấy danh sách các đánh giá do khách hàng hiện tại đã gửi.
 *
 * @param {Object} [params]
 * @param {Object} [options]
 * @returns {Promise<Object>}
 */
export async function getCustomerReviews({ page = 1, perPage = 10 } = {}, options = {}) {
  const response = await reviewApi.getCustomerReviews({ page, per_page: perPage }, options);
  return mapReviewPage(response, 'customer');
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
  let numericId;
  try {
    numericId = requireApiId(bookingId, 'Mã đơn đặt phòng');
  } catch {
    return { status: 'unknown', review: null };
  }
  const maxPages = options.maxPages || 5;
  let page = 1;

  try {
    while (page <= maxPages) {
      const { data, pagination } = await getCustomerReviews({ page, perPage: 50 }, options);
      const match = data.find((item) => item.booking_id === numericId);
      if (match) {
        return { status: 'reviewed', review: match };
      }

      if (page >= pagination.lastPage || data.length === 0) {
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

  if (propertyId !== null && propertyId !== undefined && propertyId !== '') {
    params.property_id = requireApiId(propertyId, 'Mã cơ sở lưu trú');
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

  const response = await reviewApi.getHostReviews(params, options);
  return mapReviewPage(response, 'host');
}

/**
 * Chuẩn hóa thông điệp lỗi từ API response cho UI hiển thị thân thiện.
 *
 * @param {any} error
 * @returns {{ status: number, message: string, isNetworkError: boolean, isNotFound: boolean, isConflict: boolean, isValidation: boolean }}
 */
export function parseReviewError(error) {
  if (error?.code === 'INVALID_REVIEW_RESPONSE') {
    return { ...normalizeApiError(error), code: error.code, message: error.message };
  }
  return normalizeApiError(error);
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
