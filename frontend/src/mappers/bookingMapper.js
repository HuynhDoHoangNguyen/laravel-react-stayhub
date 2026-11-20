function mapperError(message = 'Dữ liệu phản hồi từ máy chủ không hợp lệ.') {
  const err = new Error(message);
  err.isMapperError = true;
  return err;
}

/** Keep the existing command-response envelope projection used by checkout. */
export function mapBookingCommandResponse(response) {
  if (response === null || response === undefined) {
    return response;
  }
  if (typeof response !== 'object') {
    throw mapperError('Dữ liệu phản hồi thao tác đặt phòng không hợp lệ.');
  }
  if ('data' in response && response.data !== null && response.data !== undefined) {
    return response.data;
  }
  return response;
}

export function mapCustomerBookingListResponse(response, { page = 1, perPage = 5 } = {}) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu danh sách đặt phòng không hợp lệ.');
  }
  const list = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : null);
  if (list === null) {
    throw mapperError('Dữ liệu danh sách đặt phòng không đúng định dạng mảng.');
  }
  const apiMeta = response.meta && typeof response.meta === 'object' ? response.meta : {};
  const fullList = Array.isArray(response.all) ? response.all : null;

  return {
    data: list,
    all: fullList,
    stats: response.stats || response.meta?.stats || null,
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
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu chi tiết đặt phòng không hợp lệ.');
  }
  const data = 'data' in response && response.data !== null && response.data !== undefined ? response.data : response;
  if (!data || typeof data !== 'object') {
    throw mapperError('Dữ liệu chi tiết đặt phòng không đúng định dạng.');
  }
  return {
    data,
    source: 'api',
    isMock: false,
  };
}

export function mapCustomerBookingServicesResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu dịch vụ đặt phòng không hợp lệ.');
  }
  const list = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : null);
  if (list === null) {
    throw mapperError('Dữ liệu dịch vụ đặt phòng không đúng định dạng mảng.');
  }
  return list;
}
