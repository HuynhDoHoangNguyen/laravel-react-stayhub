/** Keep the existing command-response envelope projection used by checkout. */
export function mapBookingCommandResponse(response) {
  return response?.data || response;
}

export function mapCustomerBookingListResponse(response, { page = 1, perPage = 5 } = {}) {
  const list = response?.data || (Array.isArray(response) ? response : []);
  const apiMeta = response?.meta || {};
  const fullList = Array.isArray(response?.all) ? response.all : null;

  return {
    data: list,
    all: fullList,
    stats: response?.stats || response?.meta?.stats || null,
    meta: {
      ...apiMeta,
      total: apiMeta.total ?? list.length,
      page: apiMeta.current_page || apiMeta.page || page,
      per_page: apiMeta.per_page || perPage,
      total_pages: apiMeta.last_page || apiMeta.total_pages || Math.ceil(list.length / perPage) || 1,
    },
    source: 'api',
    isMock: false,
  };
}

export function mapCustomerBookingDetailResponse(response) {
  return {
    data: mapBookingCommandResponse(response),
    source: 'api',
    isMock: false,
  };
}
