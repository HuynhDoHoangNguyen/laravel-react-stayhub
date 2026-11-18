import api from '../api/axios.js';
import { createInvoiceApi } from '../api/invoiceApi.js';
import { normalizeApiError } from '../api/apiError.js';
import { mapInvoiceListResponse, mapInvoiceDetailResponse, mapAdminInvoiceListResponse, mapAdminInvoiceHostOptionsResponse, mapAdminInvoicePropertyOptionsResponse } from '../mappers/invoiceMapper.js';
import { requireApiId } from '../api/apiId.js';

/** Invoice domain boundary; injected API preserves the production Axios/Auth pipeline. */
export function createInvoiceService(invoiceApi) {
  function listParams({ page = 1, perPage = 10, status = '', search = '' } = {}) {
    const params = { page, per_page: perPage };
    if (status && status !== 'ALL') params.status = status;
    if (search && search.trim() !== '') params.search = search.trim();
    return params;
  }

  return {
    async getAdminInvoicePropertyOptions({ page = 1, perPage = 10, search = '', hostId = null } = {}, options = {}) {
      const query = { type: 'properties', page, per_page: perPage };
      if (search.trim() !== '') query.search = search.trim();
      const owner = hostId === null || hostId === '' ? null : Number(requireApiId(hostId, 'Mã Host'));
      if (owner !== null) query.host_id = owner;
      return mapAdminInvoicePropertyOptionsResponse(await invoiceApi.getAdminInvoicePropertyOptions(query, options), owner);
    },
    async getAdminInvoiceHostOptions({ page = 1, perPage = 10, search = '' } = {}, options = {}) {
      const query = { type: 'hosts', page, per_page: perPage };
      if (search.trim() !== '') query.search = search.trim();
      return mapAdminInvoiceHostOptionsResponse(await invoiceApi.getAdminInvoiceHostOptions(query, options));
    },
    async getAdminInvoices(params = {}, options = {}) {
      const query = listParams(params);
      if (params.hostId !== undefined && params.hostId !== null && params.hostId !== '') query.host_id = params.hostId;
      if (params.propertyId !== undefined && params.propertyId !== null && params.propertyId !== '') query.property_id = params.propertyId;
      return mapAdminInvoiceListResponse(await invoiceApi.getAdminInvoices(query, options));
    },
    async updateHostInvoicePaymentStatus(id, options = {}) {
      const result = mapInvoiceDetailResponse(await invoiceApi.updateHostInvoicePaymentStatus(id, options));
      if (result.data.id !== Number(id) || result.data.payment_status !== 'PAID' || result.data.paid_at === null) {
        throw Object.assign(new Error('Dữ liệu hóa đơn từ máy chủ không hợp lệ.'), {
          isApiError: true, status: 0, kind: 'contract', code: 'INVALID_INVOICE_RESPONSE', fields: {},
        });
      }
      return result;
    },
    async createHostInvoice(id, options = {}) {
      return mapInvoiceDetailResponse(await invoiceApi.createHostInvoice(id, options));
    },
    async getHostInvoices(params = {}, options = {}) {
      return mapInvoiceListResponse(await invoiceApi.getHostInvoices(listParams(params), options));
    },
    async getCustomerInvoices(params = {}, options = {}) {
      return mapInvoiceListResponse(await invoiceApi.getCustomerInvoices(listParams(params), options));
    },
    async getHostInvoiceDetail(id, options = {}) {
      return mapInvoiceDetailResponse(await invoiceApi.getHostInvoiceDetail(id, options));
    },
    async getCustomerInvoiceDetail(id, options = {}) {
      return mapInvoiceDetailResponse(await invoiceApi.getCustomerInvoiceDetail(id, options));
    },
  };
}

const invoiceService = createInvoiceService(createInvoiceApi(api));

export async function getAdminInvoicePropertyOptions(params = {}, options = {}) {
    return invoiceService.getAdminInvoicePropertyOptions(params, options);
}

export async function getAdminInvoiceHostOptions(params = {}, options = {}) {
    return invoiceService.getAdminInvoiceHostOptions(params, options);
}

export async function getAdminInvoices(params = {}, options = {}) {
    return invoiceService.getAdminInvoices(params, options);
}

export async function updateHostInvoicePaymentStatus(invoiceId, options = {}) {
    return invoiceService.updateHostInvoicePaymentStatus(invoiceId, options);
}

/**
 * Service quản lý các yêu cầu API liên quan đến Hóa đơn (Invoice / Billing)
 */

/**
 * Gửi yêu cầu Host tạo hóa đơn cho Booking đã hoàn tất (COMPLETED).
 *
 * @param {number|string} bookingId ID của Booking cần lập hóa đơn
 * @returns {Promise<Object>} Dữ liệu Invoice trả về từ backend (chứa data: { id, invoice_code, items, ... })
 */
export async function createHostInvoice(bookingId, options = {}) {
    return invoiceService.createHostInvoice(bookingId, options);
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
export async function getHostInvoices({ page = 1, perPage = 10, status = '', search = '' } = {}, options = {}) {
    return invoiceService.getHostInvoices({ page, perPage, status, search }, options);
}

/**
 * Lấy chi tiết một hóa đơn của Host theo ID.
 *
 * @param {number|string} invoiceId
 * @returns {Promise<Object>} Object gồm { data: Invoice }
 */
export async function getHostInvoiceDetail(invoiceId, options = {}) {
    return invoiceService.getHostInvoiceDetail(invoiceId, options);
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
export async function getCustomerInvoices({ page = 1, perPage = 10, status = '', search = '' } = {}, options = {}) {
    return invoiceService.getCustomerInvoices({ page, perPage, status, search }, options);
}

/**
 * Lấy chi tiết một hóa đơn của Customer theo ID.
 *
 * @param {number|string} invoiceId
 * @returns {Promise<Object>} Object gồm { data: Invoice }
 */
export async function getCustomerInvoiceDetail(invoiceId, options = {}) {
    return invoiceService.getCustomerInvoiceDetail(invoiceId, options);
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
    return normalizeApiError(error);
}
