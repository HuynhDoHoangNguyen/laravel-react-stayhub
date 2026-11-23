import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { adminHostReportService: service } = await import('../src/services/adminHostReportService.js');
test.after(() => mock.restoreAll());

const base = '/api/admin/host-reports';
const summary = {
  id: 4, booking_code: 'BKDEMO0004', property_name: 'Sông Hàn', host_name: 'Host 1',
  reason: 'WRONG_ADDRESS', reason_label: 'Sai địa chỉ', status: 'SUBMITTED', submitted_at: '2026-11-23T05:00:00Z',
};
const detail = {
  ...summary, booking_status: 'Đã xác nhận', check_in_date: '2026-11-24', check_out_date: '2026-11-26',
  customer_name: 'Customer 1', description: 'Địa chỉ ghi trên đơn khác nơi lưu trú.',
  evidence: null,
  reviewed_by: null, reviewer_name: null, reviewed_at: null, review_note: null,
};
const reviewed = {
  ...detail, status: 'SUBSTANTIATED', reviewed_by: 7, reviewer_name: 'Admin', reviewed_at: '2026-11-23T06:00:00Z',
  review_note: 'Đã đối chiếu nội dung.',
};

test('list forwards filter and pagination, projecting only summary fields', async () => {
  const signal = new AbortController().signal;
  adapter = async (request) => {
    assert.equal(request.url, base);
    assert.equal(request.method, 'get');
    assert.equal(request.signal, signal);
    assert.deepEqual(request.params, { status: 'ALL', booking_code: 'BKDEMO0004', page: 2 });
    return { data: { data: [detail], meta: { current_page: 2, last_page: 2, total: 11 } } };
  };
  assert.deepEqual(await service.list({ status: 'ALL', booking_code: ' BKDEMO0004 ', page: 2 }, { signal }),
    { data: [summary], meta: { current_page: 2, last_page: 2, total: 11 } });
});

test('evidence metadata is allowlisted and download verifies the returned bytes and MIME type', async () => {
  const blob = new Blob(['PDF'], { type: 'application/pdf' });
  const evidence = { filename: 'chung-cu-bao-cao-4.pdf', mime: blob.type, size: blob.size };
  const signal = new AbortController().signal;
  adapter = async (request) => {
    if (request.url.endsWith('/evidence')) {
      assert.equal(request.url, `${base}/4/evidence`);
      assert.equal(request.signal, signal);
      assert.equal(request.responseType, 'blob');
      return { data: blob };
    }
    return { data: { data: { ...detail, evidence: { ...evidence, path: 'private' } } } };
  };
  assert.deepEqual((await service.detail(4)).evidence, evidence);
  assert.equal(await service.download(4, evidence, { signal }), blob);
  await assert.rejects(service.download(4, { ...evidence, size: 5 }, { signal }), { kind: 'contract' });
});

test('detail projects pending and reviewed records and rejects inconsistent review fields', async () => {
  for (const entry of [detail, reviewed, { ...reviewed, status: 'UNSUBSTANTIATED' }]) {
    adapter = async ({ url }) => {
      assert.equal(url, `${base}/4`);
      return { data: { data: { ...entry, email: 'private@example.test' } } };
    };
    assert.deepEqual(await service.detail(4), entry);
  }
  adapter = async () => ({ data: { data: { ...reviewed, reviewed_at: null } } });
  await assert.rejects(service.detail(4), { kind: 'contract' });
});

test('decision refreshes CSRF, allows only approved payload and preserves 409', async () => {
  const requests = [];
  const signal = new AbortController().signal;
  const input = { decision: 'SUBSTANTIATED', note: '  Đã đối chiếu nội dung.  ', reviewed_by: 999 };
  adapter = async ({ method, url, data, signal: sentSignal }) => {
    requests.push([method, url]);
    assert.equal(sentSignal, signal);
    if (method === 'patch') {
      assert.deepEqual(JSON.parse(data), { decision: input.decision, note: reviewed.review_note });
    }
    return { data: { data: reviewed } };
  };
  assert.deepEqual(await service.decide(4, input, { signal }), reviewed);
  assert.deepEqual(requests, [['get', '/sanctum/csrf-cookie'], ['patch', `${base}/4/decision`]]);
  adapter = async ({ method }) => {
    if (method === 'patch') throw new AxiosError('Conflict', undefined, undefined, undefined, { status: 409 });
    return { data: {} };
  };
  await assert.rejects(service.decide(4, input), (failure) => failure.response.status === 409);
});
