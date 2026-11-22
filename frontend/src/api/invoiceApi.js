import { requireApiId } from './apiId.js';

/** Transport only; client must be the shared Axios instance in production. */
export function createInvoiceApi(client) {
  return {
    async getAdminInvoicePropertyOptions(params, { signal } = {}) {
      return (await client.get('/api/admin/invoices/filter-options', { params, signal })).data;
    },
    async getAdminInvoiceHostOptions(params, { signal } = {}) {
      return (await client.get('/api/admin/invoices/filter-options', { params, signal })).data;
    },
    async getAdminInvoices(params, { signal } = {}) {
      return (await client.get('/api/admin/invoices', { params, signal })).data;
    },
    async updateHostInvoicePaymentStatus(id, { signal } = {}) {
      return (await client.patch(`/api/host/invoices/${requireApiId(id, 'Mã hóa đơn')}/payment-status`, { payment_status: 'PAID' }, { signal })).data;
    },
    async createHostInvoice(id, { signal } = {}) {
      return (await client.post(`/api/host/bookings/${requireApiId(id, 'Mã đơn đặt phòng')}/invoice`, undefined, { signal })).data;
    },
    async getHostInvoiceBookingContext(id, { signal } = {}) {
      return (await client.get(`/api/host/bookings/${requireApiId(id, 'Mã đơn đặt phòng')}/invoice-context`, { signal })).data;
    },
    async getHostEligibleBookings(params, { signal } = {}) {
      return (await client.get('/api/host/invoices/eligible-bookings', { params, signal })).data;
    },
    async getHostInvoices(params, { signal } = {}) {
      return (await client.get('/api/host/invoices', { params, signal })).data;
    },
    async getHostInvoiceDetail(id, { signal } = {}) {
      return (await client.get(`/api/host/invoices/${requireApiId(id, 'Mã hóa đơn')}`, { signal })).data;
    },
    async getCustomerInvoices(params, { signal } = {}) {
      return (await client.get('/api/customer/invoices', { params, signal })).data;
    },
    async getCustomerInvoiceDetail(id, { signal } = {}) {
      return (await client.get(`/api/customer/invoices/${requireApiId(id, 'Mã hóa đơn')}`, { signal })).data;
    },
  };
}
