import { normalizeApiError } from './apiError.js';

export class UncertainAmenityWrite extends Error {
  constructor(cause) {
    super('Chưa xác nhận được kết quả lưu.', { cause });
  }
}

export function createAdminAmenityApi(client) {
  async function write(method, path, payload, options) {
    await client.get('/sanctum/csrf-cookie', options);
    try {
      return await client[method](path, payload, options);
    } catch (failure) {
      const { status } = normalizeApiError(failure);
      if (!status || status === 408 || status >= 500) {
        throw new UncertainAmenityWrite(failure);
      }
      throw failure;
    }
  }

  const collection = '/api/admin/amenities';
  const item = (id) => collection + '/' + encodeURIComponent(id);

  return {
    async list(params, { signal } = {}) {
      return (await client.get(collection, { params, signal })).data;
    },
    async get(id, { signal } = {}) {
      return (await client.get(item(id), { signal })).data;
    },
    create(payload, { signal } = {}) {
      return write('post', collection, payload, { signal });
    },
    update(id, payload, { signal } = {}) {
      return write('put', item(id), payload, { signal });
    },
  };
}
