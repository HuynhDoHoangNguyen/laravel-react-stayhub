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
  const meta = response.meta && typeof response.meta === 'object' ? response.meta : {
    current_page: 1,
    last_page: 1,
    per_page: list.length,
    total: list.length,
  };
  return {
    success: response.success ?? true,
    data: list,
    meta,
  };
}

export function mapHostBookingResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu chi tiết đặt phòng không hợp lệ.');
  }
  if ('data' in response && response.data !== null && response.data !== undefined) {
    return response.data;
  }
  return response;
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
  if ('data' in response && response.data !== null && response.data !== undefined) {
    return response.data;
  }
  return response;
}
