import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';

const requests = [];
const client = axios.create({ adapter: async (config) => {
  requests.push(config);
  return { config, status: 200, data: { success: true, data: [] } };
} });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { propertyServiceCatalogApi } = await import('../src/api/propertyServiceCatalogApi.js');

test.after(() => mock.restoreAll());

test('property service catalog API preserves endpoints, methods, params, payloads, and signal', async () => {
  const signal = new AbortController().signal;
  await propertyServiceCatalogApi.getHostProperties({ signal });
  await propertyServiceCatalogApi.list({ property_id: 4, page: 2, per_page: 5, status: 'false', keyword: 'meal' }, { signal });
  await propertyServiceCatalogApi.list({ property_id: 4, status: true, per_page: 50 });
  await propertyServiceCatalogApi.create({ property_id: 4, name: 'Meal', unit: 'set', price: '0.00' });
  await propertyServiceCatalogApi.update(11, { name: 'Meal Plus', unit: 'set', price: '10.00' });
  await propertyServiceCatalogApi.setStatus(11, false);

  assert.deepEqual(requests.map(({ method, url, params, signal: requestSignal, data }) => ({
    method, url, params, signal: requestSignal, data: typeof data === 'string' ? JSON.parse(data) : data,
  })), [
    { method: 'get', url: '/api/host/properties', params: undefined, signal, data: undefined },
    { method: 'get', url: '/api/host/services', params: { property_id: 4, page: 2, per_page: 5, status: 'false', keyword: 'meal' }, signal, data: undefined },
    { method: 'get', url: '/api/host/services', params: { property_id: 4, status: true, per_page: 50 }, signal: undefined, data: undefined },
    { method: 'post', url: '/api/host/services', params: undefined, signal: undefined, data: { property_id: 4, name: 'Meal', unit: 'set', price: '0.00' } },
    { method: 'put', url: '/api/host/services/11', params: undefined, signal: undefined, data: { name: 'Meal Plus', unit: 'set', price: '10.00' } },
    { method: 'patch', url: '/api/host/services/11/status', params: undefined, signal: undefined, data: { status: false } },
  ]);
});
