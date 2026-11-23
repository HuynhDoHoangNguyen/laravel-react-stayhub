import api from '../api/axios.js';
import { createAdminBookingApi } from '../api/adminBookingApi.js';
import { mapAdminBookingOptionsPage, mapAdminBookingPage } from '../mappers/adminBookingMapper.js';

const transport = createAdminBookingApi(api);

export async function getAdminBookings({ search = '', status = '', page = 1, perPage = 10, hostId = null, propertyId = null,
  customerId = null, checkInFrom = '', checkInTo = '' } = {}, options = {}) {
  const params = { page, per_page: perPage };
  const term = search.trim();
  if (term !== '') params.search = term;
  if (status !== '') params.status = status;
  if (hostId !== null) params.host_id = hostId;
  if (propertyId !== null) params.property_id = propertyId;
  if (customerId !== null) params.customer_id = customerId;
  if (checkInFrom !== '') params.check_in_from = checkInFrom;
  if (checkInTo !== '') params.check_in_to = checkInTo;
  return mapAdminBookingPage(await transport.list(params, options));
}

export async function getAdminBookingOptions({ type, search = '', page = 1, perPage = 10, hostId = null }, options = {}) {
  const params = { type, page, per_page: perPage };
  const term = search.trim();
  if (term !== '') params.search = term;
  if (type === 'properties' && hostId !== null) params.host_id = hostId;
  return mapAdminBookingOptionsPage(await transport.options(params, options), type);
}
