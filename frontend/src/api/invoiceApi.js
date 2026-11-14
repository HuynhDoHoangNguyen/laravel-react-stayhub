import { requireApiId } from './apiId.js';

/** Transport only; client must be the shared Axios instance in production. */
export function createInvoiceApi(client) {
  return {
    async createHostInvoice(id, { signal } = {}) {
      return (await client.post(`/api/host/bookings/${requireApiId(id, 'Mã đơn đặt phòng')}/invoice`, undefined, { signal })).data;
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
