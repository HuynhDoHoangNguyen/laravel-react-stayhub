import api from '../api/axios.js';
import { createAccountStatusApi } from '../api/accountStatusApi.js';
import { localValidationError, normalizeApiError } from '../api/apiError.js';
import { mapAccountStatus } from '../mappers/accountStatusMapper.js';

export function normalizeAccountStatusInput(value, values) {
  const fields = Object.create(null);
  const id = ['string', 'number'].includes(typeof value) ? Number(value) : NaN;
  if (!Number.isSafeInteger(id) || id < 1 || String(id) !== String(value)) fields.user = ['Mã tài khoản không hợp lệ.'];
  const object = values && typeof values === 'object' && !Array.isArray(values);
  if (!object) fields.status = ['Dữ liệu thay đổi trạng thái không hợp lệ.'];
  else {
    for (const field of Object.keys(values)) {
      if (!['status', 'expected_status'].includes(field)) fields[field] = ['Trường dữ liệu không được hỗ trợ.'];
    }
    for (const field of ['status', 'expected_status']) {
      if (!['ACTIVE', 'BLOCKED'].includes(values[field])) fields[field] = ['Trạng thái phải là ACTIVE hoặc BLOCKED.'];
    }
  }
  if (Object.keys(fields).length) throw localValidationError('Vui lòng kiểm tra lại thông tin.', fields);
  return { id, payload: { status: values.status, expected_status: values.expected_status } };
}

export function createAccountStatusService(accountApi) {
  return {
    async update(value, values, options) {
      const { id, payload } = normalizeAccountStatusInput(value, values);
      const response = await accountApi.update(id, payload, options);
      try {
        if (response.status !== 200) throw new Error('Phản hồi thay đổi trạng thái tài khoản không hợp lệ.');
        return mapAccountStatus(response.data, id, payload);
      } catch (error) {
        error.outcomeUnknown = true;
        throw error;
      }
    },
  };
}

const service = createAccountStatusService(createAccountStatusApi(api));
export const updateAccountStatus = (id, values, options) => service.update(id, values, options);
export function parseAccountStatusError(failure) {
  const error = normalizeApiError(failure);
  return { ...error, message: error.status === 419
    ? 'Phiên bảo mật đã hết hạn. Hãy đọc lại thông tin và xác nhận lại.' : error.message,
  outcomeUnknown: failure?.outcomeUnknown === true };
}
