import api from '../api/axios.js';
import { createAdminUserManagementApi } from '../api/adminUserManagementApi.js';
import { localValidationError } from '../api/apiError.js';

export function createAdminUserManagementService(managementApi) {
  return {
    async listUsers(params = {}, options = {}) {
      const result = await managementApi.listUsers(params, options);
      if (!result || result.success !== true || !Array.isArray(result.data) || !result.summary || !result.meta) {
        throw new Error('Dữ liệu danh sách người dùng từ máy chủ không hợp lệ.');
      }
      return result;
    },
    async approveHost(value, options = {}) {
      const id = ['string', 'number'].includes(typeof value) ? Number(value) : NaN;
      if (!Number.isSafeInteger(id) || id < 1 || String(id) !== String(value)) {
        throw localValidationError('Mã Host không hợp lệ.', { user_id: ['Mã Host phải là số nguyên dương hợp lệ.'] });
      }
      return managementApi.approveHost(id, options);
    },
  };
}

export const adminUserManagementService = createAdminUserManagementService(createAdminUserManagementApi(api));
