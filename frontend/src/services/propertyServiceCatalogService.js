import api from '@/api/axios';

const SERVICES_URL = '/api/host/services';

function catalogError(error, fallback) {
  const status = error.response?.status;
  const messages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Tài khoản hiện tại không có quyền quản lý danh mục dịch vụ.',
    404: 'Không tìm thấy cơ sở hoặc dịch vụ thuộc quyền quản lý của bạn.',
    409: 'Dữ liệu vừa được thay đổi. Vui lòng tải lại danh mục.',
    422: 'Thông tin chưa hợp lệ. Vui lòng kiểm tra lại biểu mẫu.',
  };
  const serverMessage = error.response?.data?.message;
  const message = status === 422 || status === 409
    ? (serverMessage || messages[status])
    : (messages[status] || serverMessage || (error.response ? error.message : fallback));
  const parsed = new Error(message || fallback);
  parsed.status = status || 0;
  parsed.errors = error.response?.data?.errors || null;
  parsed.isNetworkError = !error.response;
  return parsed;
}

async function request(promise, fallback) {
  try {
    return await promise;
  } catch (error) {
    throw catalogError(error, fallback);
  }
}

export const propertyServiceCatalogService = {
  async getHostProperties(options = {}) {
    const response = await request(
      api.get('/api/host/properties', { signal: options.signal }),
      'Không thể tải danh sách cơ sở lưu trú.'
    );
    return response.data?.data || [];
  },

  async list({ propertyId, page = 1, perPage = 10, status = '', keyword = '' } = {}, options = {}) {
    const params = { property_id: propertyId, page, per_page: perPage };
    if (status !== '') params.status = status;
    if (keyword.trim()) params.keyword = keyword.trim();
    const response = await request(
      api.get(SERVICES_URL, { params, signal: options.signal }),
      'Không thể tải danh mục dịch vụ.'
    );
    return response.data;
  },

  async create(payload) {
    const response = await request(api.post(SERVICES_URL, payload), 'Không thể tạo dịch vụ.');
    return response.data?.data || response.data;
  },

  async update(serviceId, payload) {
    const response = await request(api.put(`${SERVICES_URL}/${serviceId}`, payload), 'Không thể cập nhật dịch vụ.');
    return response.data?.data || response.data;
  },

  async setStatus(serviceId, status) {
    const response = await request(api.patch(`${SERVICES_URL}/${serviceId}/status`, { status }), 'Không thể cập nhật trạng thái dịch vụ.');
    return response.data?.data || response.data;
  },
};
