function mapperError(message = 'Dữ liệu dịch vụ cơ sở lưu trú không hợp lệ.') {
  const err = new Error(message);
  err.isMapperError = true;
  return err;
}

export function mapHostPropertiesResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu danh sách cơ sở lưu trú không hợp lệ.');
  }
  const list = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : null);
  if (list === null) {
    throw mapperError('Dữ liệu cơ sở lưu trú không đúng định dạng mảng.');
  }
  return list;
}

export function mapPropertyServiceListResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu danh sách dịch vụ không hợp lệ.');
  }
  const list = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : null);
  if (list === null) {
    throw mapperError('Dữ liệu danh sách dịch vụ không đúng định dạng mảng.');
  }
  const meta = response.meta && typeof response.meta === 'object' ? response.meta : null;
  if (!meta || (meta.current_page === undefined && meta.page === undefined)) {
    throw mapperError('Dữ liệu phân trang dịch vụ không hợp lệ.');
  }
  return {
    success: response.success ?? true,
    data: list,
    meta: {
      current_page: Number(meta.current_page ?? meta.page ?? 1),
      last_page: Number(meta.last_page ?? meta.total_pages ?? 1),
      per_page: Number(meta.per_page ?? 15),
      total: Number(meta.total ?? list.length),
    },
  };
}

export function mapPropertyServiceResponse(response) {
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
