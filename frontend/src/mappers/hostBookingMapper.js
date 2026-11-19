function mapperError(message = 'Dữ liệu đặt phòng của chủ nhà không hợp lệ.') {
  const err = new Error(message);
  err.isMapperError = true;
  return err;
}

export function mapHostBookingListResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu danh sách đặt phòng không hợp lệ.');
  }
  const list = Array.isArray(response.data) ? response.data : null;
  if (list === null) {
    throw mapperError('Dữ liệu đặt phòng không đúng định dạng mảng.');
  }
  if (!response.meta || typeof response.meta !== 'object' ||
      response.meta.current_page === undefined ||
      response.meta.last_page === undefined ||
      response.meta.per_page === undefined ||
      response.meta.total === undefined) {
    throw mapperError('Dữ liệu phân trang meta không hợp lệ.');
  }
  return {
    success: response.success ?? true,
    data: list,
    meta: {
      current_page: Number(response.meta.current_page),
      last_page: Number(response.meta.last_page),
      per_page: Number(response.meta.per_page),
      total: Number(response.meta.total),
    },
  };
}

export function mapHostBookingResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu chi tiết đặt phòng không hợp lệ.');
  }
  if ('data' in response) {
    if (response.data === null || response.data === undefined || typeof response.data !== 'object') {
      throw mapperError('Dữ liệu chi tiết đặt phòng không tồn tại hoặc rỗng.');
    }
    return response.data;
  }
  if ('id' in response) {
    return response;
  }
  throw mapperError('Dữ liệu chi tiết đặt phòng không hợp lệ.');
}

export function mapHostBookingServicesListResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu dịch vụ đặt phòng không hợp lệ.');
  }
  const list = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : null);
  if (list === null) {
    throw mapperError('Dữ liệu dịch vụ đặt phòng không đúng định dạng mảng.');
  }
  return list;
}

export function mapHostBookingServiceResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu dịch vụ không hợp lệ.');
  }
  if ('data' in response) {
    if (response.data === null || response.data === undefined || typeof response.data !== 'object') {
      throw mapperError('Dữ liệu dịch vụ không tồn tại hoặc rỗng.');
    }
    return response.data;
  }
  if ('id' in response) {
    return response;
  }
  throw mapperError('Dữ liệu dịch vụ không hợp lệ.');
}
