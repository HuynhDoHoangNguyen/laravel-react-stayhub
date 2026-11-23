import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { hostReportService: service } = await import('../src/services/hostReportService.js');
test.after(() => mock.restoreAll());

const receipt = { id: 9, status: 'SUBMITTED', submitted_at: '2026-11-23T05:00:00Z' };
const choices = [{ value: 'WRONG_ADDRESS', label: 'Sai địa chỉ' }];
const current = {
  can_submit: true, blocked_reason: null, deadline: '2026-12-26', reasons: choices, report: null,
};
const url = '/api/customer/bookings/4/host-report';
const input = { reason: 'WRONG_ADDRESS', description: '  Địa chỉ trên đơn chưa đúng.  ', host_id: 99 };

test('eligibility maps the server’s report rules and forwards cancellation', async () => {
  const { signal } = new AbortController();
  adapter = async (config) => {
    assert.equal(config.url, url);
    assert.equal(config.method, 'get');
    assert.equal(config.signal, signal);
    return { data: { data: current } };
  };
  assert.deepEqual(await service.current('4', { signal }), {
    canSubmit: true, blockedReason: null, deadline: '2026-12-26', reasons: choices, report: null,
  });
});

test('submission uses CSRF and sends only reason and trimmed description', async () => {
  const calls = [];
  const { signal } = new AbortController();
  adapter = async (config) => {
    calls.push([config.method, config.url]);
    assert.equal(config.signal, signal);
    if (config.method === 'post') {
      assert.deepEqual(JSON.parse(config.data), {
        reason: input.reason, description: input.description.trim(),
      });
    }
    return { data: { data: { ...receipt, host_id: 10 } } };
  };
  assert.deepEqual(await service.submit(4, input, { signal }), receipt);
  assert.deepEqual(calls, [['get', '/sanctum/csrf-cookie'], ['post', url]]);
});

test('existing reports and blocked reasons survive refresh', async () => {
  for (const blockedReason of ['BOOKING_STATUS', 'DEADLINE_PASSED', 'ALREADY_SUBMITTED']) {
    const report = blockedReason === 'ALREADY_SUBMITTED' ? receipt : null;
    adapter = async () => ({ data: {
      data: { ...current, can_submit: false, blocked_reason: blockedReason, reasons: [], report },
    } });
    const result = await service.current(4);
    assert.equal(result.canSubmit, false);
    assert.equal(result.blockedReason, blockedReason);
    assert.deepEqual(result.report, report);
  }
});

test('submission with evidence uses multipart and retains the original File', async () => {
  const evidence = new File(['test'], 'demo.pdf', { type: 'application/pdf' });
  const requests = [];
  adapter = async ({ method, url: target, data }) => {
    requests.push([method, target]);
    if (method === 'post') {
      assert.ok(data instanceof FormData);
      assert.equal(data.get('reason'), input.reason);
      assert.equal(data.get('description'), input.description.trim());
      assert.equal(data.get('evidence'), evidence);
    }
    return { data: { data: receipt } };
  };
  assert.deepEqual(await service.submit(4, { ...input, evidence }), receipt);
  assert.deepEqual(requests, [['get', '/sanctum/csrf-cookie'], ['post', url]]);
});

test('invalid contracts and HTTP conflicts are distinct failures', async () => {
  adapter = async () => ({ data: { data: { ...current, report: receipt } } });
  await assert.rejects(service.current(4), { kind: 'contract' });
  adapter = async ({ method }) => {
    if (method === 'post') throw new AxiosError('Conflict', undefined, undefined, undefined, { status: 409 });
    return { data: {} };
  };
  await assert.rejects(service.submit(4, input), (error) => error.response?.status === 409);
  await assert.rejects(service.current('4/host-report'), { isLocalValidation: true });
});
