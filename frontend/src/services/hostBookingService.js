import { hostBookingApi } from '@/api/hostBookingApi';
import {
  mapHostBookingListResponse,
  mapHostBookingResponse,
  mapHostBookingServiceResponse,
  mapHostBookingServicesListResponse,
} from '@/mappers/hostBookingMapper';
import { propertyServiceCatalogService } from '@/services/propertyServiceCatalogService';

export const hostBookingService = {
  async list({ page = 1, perPage = 10, status = '', keyword = '', startDate = '', endDate = '' } = {}, options = {}) {
    const params = { page, per_page: perPage };
    if (status) params.status = status;
    if (keyword.trim()) params.keyword = keyword.trim();
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;
    const response = await hostBookingApi.list(params, options);
    return mapHostBookingListResponse(response.data);
  },

  async detail(id, options = {}) {
    const response = await hostBookingApi.detail(id, options);
    return mapHostBookingResponse(response.data);
  },

  async transition(id, action, payload = {}) {
    const response = await hostBookingApi.transition(id, action, payload);
    return mapHostBookingResponse(response.data);
  },

  async listPropertyServices(propertyId, options = {}) {
    const response = await propertyServiceCatalogService.listActiveForProperty(propertyId, options);
    return response?.data || [];
  },

  async listBookingServices(bookingId, options = {}) {
    const response = await hostBookingApi.listBookingServices(bookingId, options);
    return mapHostBookingServicesListResponse(response.data);
  },

  async addBookingService(bookingId, payload) {
    const response = await hostBookingApi.addBookingService(bookingId, payload);
    return mapHostBookingServiceResponse(response.data);
  },

  async updateBookingService(bookingId, bookingServiceId, quantity) {
    const response = await hostBookingApi.updateBookingService(bookingId, bookingServiceId, quantity);
    return mapHostBookingServiceResponse(response.data);
  },

  async removeBookingService(bookingId, bookingServiceId) {
    await hostBookingApi.removeBookingService(bookingId, bookingServiceId);
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
