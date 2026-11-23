import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createInvoiceApi } from '../src/api/invoiceApi.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';
import { normalizeApiError } from '../src/api/apiError.js';

// Mock only the Vite-configured transport module; import and exercise the real service.
let publicAdapter;
const sharedClient = axios.create({ adapter: (config) => publicAdapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const invoiceServiceModule = await import('../src/services/invoiceService.js');
const { createInvoiceService } = invoiceServiceModule;
test.after(() => mock.restoreAll());

const row = {
  id: 7, booking_id: 11, invoice_code: 'INV-7', customer_name: 'Fixture', customer_email: 'fixture@example.test', customer_phone: '0900000000',
  property_name: 'Snapshot Property', room_name: 'Snapshot Room', subtotal: '100.00', discount_amount: '0.00', extra_fee: '0.00', total_amount: '100.00',
  payment_status: 'UNPAID', paid_at: null, created_at: '2026-11-14T00:00:00.000000Z',
};
const page = { data: [row], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 }, links: { first: '?page=1', last: '?page=1', prev: null, next: null } };
const detail = { data: { ...row, items: [] } };

test('booking context uses GET, validates its booking identity and preserves AbortSignal', async () => {
  const data = {
    booking_id: 11, booking_code: 'BK-11', customer_name: 'Khách', property_name: 'Cơ sở', room_name: 'Phòng',
    check_in_date: '2026-11-20', check_out_date: '2026-11-22', booking_status: 'CONFIRMED', can_create: false, invoice: null,
  };
  const controller = new AbortController();
  let calls = 0;
  const { service } = setup(async (config) => {
    calls++;
    assert.equal(config.method, 'get');
    assert.equal(config.url, '/api/host/bookings/11/invoice-context');
    assert.equal(config.signal, controller.signal);
    return { status: 200, data: { data }, config, headers: {} };
  });
  assert.deepEqual(await service.getHostInvoiceBookingContext('11', { signal: controller.signal }), data);
  controller.abort();
  await assert.rejects(service.getHostInvoiceBookingContext(11, { signal: controller.signal }), CanceledError);
  assert.equal(calls, 1);
});

test('eligible-booking picker sends only pagination and normalized search on an abortable GET', async () => {
  const response = { ...page, data: [], meta: { ...page.meta, current_page: 2, per_page: 5, total: 0 } };
  const controller = new AbortController();
  const calls = [];
  const { service } = setup(async (config) => {
    calls.push(config);
    return { status: 200, data: response, config, headers: {} };
  });

  assert.deepEqual(await service.getHostEligibleBookings({ page: 2, perPage: 5, search: '  Khách  ', hostId: 42 }, { signal: controller.signal }), response);
  assert.equal(calls[0].method, 'get');
  assert.equal(calls[0].url, '/api/host/invoices/eligible-bookings');
  assert.equal(calls[0].signal, controller.signal);
  assert.deepEqual(calls[0].params, { page: 2, per_page: 5, search: 'Khách' });
  controller.abort();
  await assert.rejects(service.getHostEligibleBookings({}, { signal: controller.signal }), CanceledError);
  assert.equal(calls.length, 1);
});

test('payment PATCH uses the real API/service, fixed body and signal; maps paid detail for factory and public export', async () => {
  const calls = [];
  const paid = { data: { ...row, payment_status: 'PAID', paid_at: '2026-11-17T01:00:00.000000Z', items: [] } };
  const adapter = async (config) => {
    calls.push(config);
    return { status: 200, data: paid, config, headers: {} };
  };
  const { service } = setup(adapter);
  const signal = new AbortController().signal;
  for (let i = 0; i < 2; i++) {
    assert.deepEqual(await service.updateHostInvoicePaymentStatus('7', { signal }), paid);
  }
  publicAdapter = adapter;
  assert.deepEqual(await invoiceServiceModule.updateHostInvoicePaymentStatus(7, { signal }), paid);
  assert.equal(calls.length, 3);
  for (const config of calls) {
    assert.equal(config.method, 'patch');
    assert.equal(config.url, '/api/host/invoices/7/payment-status');
    assert.deepEqual(JSON.parse(config.data), { payment_status: 'PAID' });
    assert.equal(config.signal, signal);
  }
});

test('payment HTTP/network/timeout failures retain identity and are never replayed', async () => {
  for (const status of [401, 403, 404, 409, 419, 422, 500, 0]) {
    let calls = 0;
    const error = new AxiosError('Fixture', status ? undefined : 'ECONNABORTED', undefined, undefined,
      status ? { status, data: { message: 'Fixture error', errors: { payment_status: ['Invalid'] } }, headers: {} } : undefined);
    const { service } = setup(async () => { ++calls; throw error; });
    await assert.rejects(service.updateHostInvoicePaymentStatus(7), (actual) => actual === error);
    assert.equal(calls, 1);
  }
});

test('payment rejects malformed or wrong-state success so callers can recover by GET', async () => {
  for (const body of [{}, detail, { data: { ...detail.data, payment_status: 'PAID' } },
    { data: { ...detail.data, id: 8, payment_status: 'PAID', paid_at: '2026-11-17T01:00:00Z' } }]) {
    let calls = 0;
    const { service } = setup(async (config) => { ++calls; return { status: 200, data: body, config, headers: {} }; });
    await assert.rejects(service.updateHostInvoicePaymentStatus(7), (error) => error.kind === 'contract' && error.code === 'INVALID_INVOICE_RESPONSE');
    assert.equal(calls, 1);
  }
});

test('cancelled payment does not send or retry a mutation', async () => {
  let calls = 0;
  const { service } = setup(async () => { ++calls; throw new Error('Must not reach transport'); });
  const controller = new AbortController(); controller.abort();
  await assert.rejects(service.updateHostInvoicePaymentStatus(7, { signal: controller.signal }), CanceledError);
  assert.equal(calls, 0);
});

function setup(adapter) {
  const client = axios.create({ adapter });
  return { client, service: createInvoiceService(createInvoiceApi(client)) };
}

test('Property options factory and public export normalize owner/query, preserve signal and map three fields', async () => {
  const body = { ...page, data: [{ id: 21, name: 'Cơ sở hiện tại', host_id: 17, address: 'Private' }] };
  const calls = [];
  const adapter = async (config) => { calls.push(config); return { status: 200, data: body, config, headers: {} }; };
  const { service } = setup(adapter); publicAdapter = adapter;
  const signal = new AbortController().signal;
  for (const invoke of [service.getAdminInvoicePropertyOptions, invoiceServiceModule.getAdminInvoicePropertyOptions]) {
    const result = await invoke({ hostId: '17', page: 2, perPage: 20, search: ' 0 ', type: 'hosts', status: 'PAID', propertyId: 21 }, { signal });
    assert.deepEqual(result.data, [{ id: 21, name: 'Cơ sở hiện tại', host_id: 17 }]);
    assert.equal(calls.at(-1).url, '/api/admin/invoices/filter-options');
    assert.equal(calls.at(-1).method, 'get'); assert.equal(calls.at(-1).signal, signal);
    assert.deepEqual(calls.at(-1).params, { type: 'properties', page: 2, per_page: 20, search: '0', host_id: 17 });
  }
  for (const hostId of [undefined, null, '']) {
    await service.getAdminInvoicePropertyOptions({ hostId, search: ' ' });
    assert.deepEqual(calls.at(-1).params, { type: 'properties', page: 1, per_page: 10 });
  }
});

test('Property options reject malformed successes or wrong-owner data and invalid IDs before transport', async () => {
  let body = {}, calls = 0;
  const adapter = async (config) => { ++calls; return { status: 200, data: body, config, headers: {} }; };
  const { service } = setup(adapter); publicAdapter = adapter;
  for (const invoke of [service.getAdminInvoicePropertyOptions, invoiceServiceModule.getAdminInvoicePropertyOptions]) {
    await assert.rejects(invoke(), (error) => error.kind === 'contract');
    body = { ...page, data: [{ id: 21, name: 'Wrong owner', host_id: 18 }] };
    await assert.rejects(invoke({ hostId: 17 }), (error) => error.kind === 'contract');
    body = {};
  }
  assert.equal(calls, 4);
  for (const hostId of [0, '01', '1e2', true, [], {}, Number.MAX_SAFE_INTEGER + 1]) {
    await assert.rejects(service.getAdminInvoicePropertyOptions({ hostId }), (error) => error.isLocalValidation === true);
  }
  assert.equal(calls, 4);
});

test('Property options preserve HTTP/Auth/validation and cancellation identity without retries', async () => {
  let original, calls = 0;
  const { client, service } = setup(async (config) => { ++calls; original.config = config; throw original; });
  const failures = [];
  const cleanup = subscribeToAuthFailures(client, (error, version) => failures.push({ error, version }), () => 81);
  try {
    for (const [status, code] of [[401], [403, 'ACCOUNT_BLOCKED'], [403, 'ACCOUNT_INACTIVE'], [403, 'INVOICE_READ_FORBIDDEN'], [422], [500]]) {
      original = new AxiosError('Fixture', '', {}, null, { status, data: { code, errors: { host_id: ['Invalid'] } }, headers: {} });
      await assert.rejects(service.getAdminInvoicePropertyOptions(), (error) => {
        assert.equal(error, original);
        if (status === 422) assert.deepEqual(normalizeApiError(error).fields, { host_id: ['Invalid'] });
        return true;
      });
    }
    assert.deepEqual(failures.map(({ version }) => version), [81, 81, 81]);
    for (const error of [new CanceledError('Abort'), new AxiosError('Timeout', 'ECONNABORTED'), new AxiosError('Offline', 'ERR_NETWORK')]) {
      original = error; await assert.rejects(service.getAdminInvoicePropertyOptions(), (received) => received === error);
    }
    const controller = new AbortController(); controller.abort();
    await assert.rejects(service.getAdminInvoicePropertyOptions({}, { signal: controller.signal }), CanceledError);
    assert.equal(calls, 9);
  } finally { cleanup(); }
});

test('Host options factory and public export normalize only options queries and preserve signals', async () => {
  const body = { ...page, data: [{ id: 17, name: 'Host hiện tại', email: 'private@example.test' }] };
  const calls = [];
  const adapter = async (config) => { calls.push(config); return { status: 200, data: body, config, headers: {} }; };
  const { service } = setup(adapter);
  publicAdapter = adapter;
  const signal = new AbortController().signal;
  for (const invoke of [service.getAdminInvoiceHostOptions, invoiceServiceModule.getAdminInvoiceHostOptions]) {
    const result = await invoke({ page: 2, perPage: 20, search: ' 0 ', hostId: 55, status: 'PAID', type: 'properties' }, { signal });
    assert.deepEqual(result.data, [{ id: 17, name: 'Host hiện tại' }]);
    assert.equal(calls.at(-1).url, '/api/admin/invoices/filter-options');
    assert.equal(calls.at(-1).method, 'get');
    assert.equal(calls.at(-1).signal, signal);
    assert.deepEqual(calls.at(-1).params, { type: 'hosts', page: 2, per_page: 20, search: '0' });
  }
  await service.getAdminInvoiceHostOptions({ search: ' ' });
  assert.deepEqual(calls.at(-1).params, { type: 'hosts', page: 1, per_page: 10 });
});

test('Host options HTTP/Auth/422 errors and transport cancellation retain identity without retry', async () => {
  let original, calls = 0;
  const { client, service } = setup(async (config) => { ++calls; original.config = config; throw original; });
  const failures = [];
  const cleanup = subscribeToAuthFailures(client, (error, version) => failures.push({ error, version }), () => 71);
  try {
    for (const [status, code] of [[401], [403, 'ACCOUNT_BLOCKED'], [403, 'ACCOUNT_INACTIVE'], [403, 'INVOICE_READ_FORBIDDEN'], [422], [500]]) {
      original = new AxiosError('Fixture', '', {}, null, { status, data: { code, errors: { search: ['Invalid'] } }, headers: {} });
      await assert.rejects(service.getAdminInvoiceHostOptions(), (error) => {
        assert.equal(error, original);
        if (status === 422) assert.deepEqual(normalizeApiError(error).fields, { search: ['Invalid'] });
        return true;
      });
    }
    assert.deepEqual(failures.map(({ version }) => version), [71, 71, 71]);
    for (const error of [new CanceledError('Abort'), new AxiosError('Timeout', 'ECONNABORTED'), new AxiosError('Offline', 'ERR_NETWORK')]) {
      original = error;
      await assert.rejects(service.getAdminInvoiceHostOptions(), (received) => received === error);
    }
    const controller = new AbortController(); controller.abort();
    await assert.rejects(service.getAdminInvoiceHostOptions({}, { signal: controller.signal }), CanceledError);
    assert.equal(calls, 9);
  } finally { cleanup(); }
});

test('Host options reject malformed HTTP successes for factory and public export', async () => {
  const adapter = async (config) => ({ status: 200, data: {}, config, headers: {} });
  const { service } = setup(adapter);
  publicAdapter = adapter;
  for (const invoke of [service.getAdminInvoiceHostOptions, invoiceServiceModule.getAdminInvoiceHostOptions]) {
    await assert.rejects(invoke(), (error) => error.kind === 'contract' && error.code === 'INVALID_INVOICE_RESPONSE');
  }
});

test('Admin factory and public operation use the real API, normalized params, ten-field mapper and signal', async () => {
  const calls = [];
  const adapter = async (config) => { calls.push(config); return { status: 200, data: page, config, headers: {} }; };
  const { service } = setup(adapter);
  const signal = new AbortController().signal;
  publicAdapter = adapter;
  for (const invoke of [service.getAdminInvoices, invoiceServiceModule.getAdminInvoices]) {
    const result = await invoke({ page: 2, perPage: 20, status: 'ALL', search: ' 0 ', hostId: 17, propertyId: '19' }, { signal });
    assert.equal(Object.keys(result.data[0]).length, 10);
    assert.equal(result.data[0].total_amount, '100.00');
    assert.equal('customer_email' in result.data[0], false);
    assert.equal(calls.at(-1).url, '/api/admin/invoices');
    assert.equal(calls.at(-1).method, 'get');
    assert.equal(calls.at(-1).signal, signal);
    assert.deepEqual(calls.at(-1).params, { page: 2, per_page: 20, search: '0', host_id: 17, property_id: '19' });
  }
  await service.getAdminInvoices({ status: 'PAID', search: ' ', hostId: '', propertyId: null });
  assert.deepEqual(calls.at(-1).params, { page: 1, per_page: 10, status: 'PAID' });
});

test('Admin preserves HTTP/Auth error identity, fields and cancellation without replay', async () => {
  let original, calls = 0;
  const { client, service } = setup(async (config) => { ++calls; original.config = config; throw original; });
  const failures = [];
  const cleanup = subscribeToAuthFailures(client, (error, version) => failures.push({ error, version }), () => 55);
  try {
    for (const [status, code] of [[401], [403, 'ACCOUNT_BLOCKED'], [403, 'ACCOUNT_INACTIVE'], [403, 'INVOICE_READ_FORBIDDEN'], [422], [500]]) {
      original = new AxiosError('Fixture', '', {}, null, { status, data: { code, errors: { search: ['Invalid'] } }, headers: {} });
      await assert.rejects(service.getAdminInvoices(), (error) => {
        assert.equal(error, original);
        if (status === 422) assert.deepEqual(normalizeApiError(error).fields, { search: ['Invalid'] });
        return true;
      });
    }
    assert.equal(calls, 6);
    assert.deepEqual(failures.map(({ version }) => version), [55, 55, 55]);
    for (const error of [new CanceledError('Abort'), new AxiosError('Timeout', 'ECONNABORTED'), new AxiosError('Offline', 'ERR_NETWORK')]) {
      original = error;
      await assert.rejects(service.getAdminInvoices(), (actual) => actual === error);
    }
    const controller = new AbortController(); controller.abort();
    await assert.rejects(service.getAdminInvoices({}, { signal: controller.signal }), CanceledError);
    assert.equal(calls, 9);
  } finally { cleanup(); }
});

test('Admin rejects malformed HTTP success through factory and public export', async () => {
  const adapter = async (config) => ({ status: 200, data: {}, config, headers: {} });
  const { service } = setup(adapter);
  publicAdapter = adapter;
  for (const invoke of [service.getAdminInvoices, invoiceServiceModule.getAdminInvoices]) {
    await assert.rejects(invoke(), (error) => error.code === 'INVALID_INVOICE_RESPONSE' && error.kind === 'contract');
  }
});

test('all four production read operations map data and preserve Page-facing envelope/options', async () => {
  const calls = [];
  const { service } = setup(async (config) => {
    calls.push(config);
    return { status: 200, data: config.url.endsWith('/7') ? detail : page, config, headers: {} };
  });
  const options = { signal: new AbortController().signal };
  for (const [method, path] of [['getHostInvoices', '/api/host/invoices'], ['getCustomerInvoices', '/api/customer/invoices']]) {
    const result = await service[method]({ page: 2, perPage: 5, status: 'PAID', search: '  INV-7  ' }, options);
    assert.deepEqual(result, page);
    assert.notEqual(result.data[0], row);
    assert.equal(calls.at(-1).url, path);
    assert.deepEqual(calls.at(-1).params, { page: 2, per_page: 5, status: 'PAID', search: 'INV-7' });
    assert.equal(calls.at(-1).signal, options.signal);
    await service[method]({ status: 'ALL', search: ' ' });
    assert.deepEqual(calls.at(-1).params, { page: 1, per_page: 10 });
  }
  for (const method of ['getHostInvoiceDetail', 'getCustomerInvoiceDetail']) {
    const result = await service[method]('7', options);
    assert.deepEqual(result, detail);
    assert.notEqual(result.data, detail.data);
    assert.equal(calls.at(-1).signal, options.signal);
  }
  assert.equal(calls.length, 6);
  assert.ok(calls.every((config) => config.method === 'get'));
});

test('malformed HTTP success is rejected by service mapper, not shown as empty or detail success', async () => {
  const { service } = setup(async (config) => ({ status: 200, data: {}, config, headers: {} }));
  for (const invoke of [() => service.getHostInvoices(), () => service.getCustomerInvoices(), () => service.getHostInvoiceDetail(7), () => service.getCustomerInvoiceDetail(7)]) {
    await assert.rejects(invoke, (error) => error.code === 'INVALID_INVOICE_RESPONSE' && error.kind === 'contract');
  }
});

test('HTTP errors retain rejection identity, fields and Auth semantics without retries', async () => {
  let status = 401, code, original;
  let requests = 0;
  const failures = [];
  const { client, service } = setup(async (config) => {
    ++requests;
    original = new AxiosError('Fixture', '', config, null, { status, data: { message: 'Denied', code, errors: { search: ['Invalid'] } }, headers: {}, config });
    throw original;
  });
  const cleanup = subscribeToAuthFailures(client, (error, version) => failures.push({ error, version }), () => 12);
  try {
    for (const [nextStatus, nextCode] of [[401, undefined], [403, 'INVOICE_READ_FORBIDDEN'], [404, 'INVOICE_NOT_FOUND'], [422, undefined], [500, 'INTERNAL_SERVER_ERROR'], [403, 'ACCOUNT_BLOCKED'], [403, 'ACCOUNT_INACTIVE']]) {
      status = nextStatus; code = nextCode;
      await assert.rejects(service.getHostInvoices(), (error) => {
        assert.equal(error, original);
        const parsed = normalizeApiError(error);
        assert.equal(parsed.status, nextStatus);
        if (nextStatus === 422) assert.deepEqual(parsed.fields, { search: ['Invalid'] });
        return true;
      });
    }
    assert.equal(requests, 7);
    assert.equal(failures.length, 3);
    assert.deepEqual(failures.map((failure) => failure.error.code), [null, 'ACCOUNT_BLOCKED', 'ACCOUNT_INACTIVE']);
    assert.ok(failures.every((failure) => failure.version === 12));
  } finally { cleanup(); }
});

test('cancel and ambiguous network errors are not converted into response-contract errors', async () => {
  let calls = 0;
  const network = new AxiosError('Fixture offline', 'ERR_NETWORK');
  const { service } = setup(async () => { ++calls; throw network; });
  await assert.rejects(service.getCustomerInvoices(), (error) => error === network);
  const controller = new AbortController(); controller.abort();
  await assert.rejects(service.getCustomerInvoiceDetail(7, { signal: controller.signal }), CanceledError);
  assert.equal(calls, 1);
});


test('create maps the success envelope, preserves SERVICE names and forwards transport options once', async () => {
  const calls = [];
  const body = { data: { ...row, total_amount: '1234567.89', items: ['', '   ', '0'].map((name, index) => ({
    id: index + 1, invoice_id: 7, item_type: 'SERVICE', item_name: name, description: null,
    quantity: '1.25', unit_price: '12.34', amount: '15.43',
  })) } };
  const before = structuredClone(body);
  const { service } = setup(async (config) => { calls.push(config); return { status: 201, data: body, config, headers: {} }; });
  const signal = new AbortController().signal;
  const output = await service.createHostInvoice('11', { signal });
  assert.deepEqual(output, before);
  assert.deepEqual(body, before);
  assert.notEqual(output.data, body.data);
  assert.notEqual(output.data.items[0], body.data.items[0]);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].method, 'post');
  assert.equal(calls[0].url, '/api/host/bookings/11/invoice');
  assert.equal(calls[0].data, undefined);
  assert.equal(calls[0].signal, signal);
});

test('malformed create successes are rejected without replay or raw-body fallback', async () => {
  let body, calls = 0;
  const { service } = setup(async (config) => { ++calls; return { status: 201, data: body, headers: {}, config }; });
  for (const invalid of [{}, row, { data: row }, { data: { ...row, items: null } }, { data: { ...row, items: [], total_amount: 100 } }]) {
    body = invalid;
    await assert.rejects(service.createHostInvoice(11), (error) => error.kind === 'contract' && error.code === 'INVALID_INVOICE_RESPONSE');
  }
  assert.equal(calls, 5);
});

test('create HTTP errors preserve identity, Auth session versions, fields and no automatic replay', async () => {
  let status, code, original, version = 20, calls = 0;
  const failures = [];
  const { client, service } = setup(async (config) => {
    ++calls;
    original = new AxiosError('Synthetic rejection', '', config, null, {
      status, data: { message: 'Denied', ...(code ? { code } : {}), ...(status === 422 ? { errors: { booking: ['Invalid'] } } : {}) }, config, headers: {},
    });
    throw original;
  });
  const cleanup = subscribeToAuthFailures(client, (error, capturedVersion) => failures.push({ error, capturedVersion }), () => version);
  const cases = [[401], [403], [403, 'ROLE_DENIED'], [404], [409], [422], [500, 'INTERNAL_SERVER_ERROR'], [403, 'ACCOUNT_BLOCKED'], [403, 'ACCOUNT_INACTIVE']];
  try {
    for (const [nextStatus, nextCode] of cases) {
      status = nextStatus; code = nextCode;
      await assert.rejects(service.createHostInvoice(11), (error) => {
        assert.equal(error, original);
        const parsed = normalizeApiError(error);
        assert.equal(parsed.status, status);
        if (status === 422) assert.deepEqual(parsed.fields, { booking: ['Invalid'] });
        return true;
      });
      ++version;
    }
    assert.equal(calls, cases.length);
    assert.equal(failures.length, 3);
    assert.deepEqual(failures.map(({ capturedVersion }) => capturedVersion), [20, 27, 28]);
  } finally { cleanup(); }
});

test('create cancellation, timeout and ambiguous network failure preserve identity and never retry', async () => {
  let calls = 0, original;
  const { service } = setup(async () => { ++calls; throw original; });
  for (const error of [new AxiosError('Offline', 'ERR_NETWORK'), new AxiosError('Timeout', 'ECONNABORTED'), new CanceledError('Cancelled')]) {
    original = error;
    await assert.rejects(service.createHostInvoice(11), (received) => received === original);
  }
  assert.equal(calls, 3);
  const controller = new AbortController(); controller.abort();
  await assert.rejects(service.createHostInvoice(11, { signal: controller.signal }), CanceledError);
  assert.equal(calls, 3);
});

test('invalid create and detail IDs issue no HTTP request through the consolidated service', async () => {
  let calls = 0;
  const { service } = setup(async () => { ++calls; throw new Error('Unexpected request'); });
  for (const id of [0, -1, 1.5, '1e2', '01', ' 1 ', '', true, null, undefined, {}, [], Number.MAX_SAFE_INTEGER + 1]) {
    for (const method of ['createHostInvoice', 'getHostInvoiceDetail', 'getCustomerInvoiceDetail']) {
      await assert.rejects(service[method](id), (error) => error.isLocalValidation === true);
    }
  }
  assert.equal(calls, 0);
});

test('all five public exports use the shared client and mapped Page-facing contracts', async () => {
  const calls = [];
  publicAdapter = async (config) => {
    calls.push(config);
    const body = config.method === 'post' || config.url.endsWith('/7') ? detail : page;
    return { status: config.method === 'post' ? 201 : 200, data: body, headers: {}, config };
  };
  const options = { signal: new AbortController().signal };
  for (const [method, arg, expected, path] of [
    ['createHostInvoice', '11', detail, '/api/host/bookings/11/invoice'],
    ['getHostInvoices', { page: 2, perPage: 5, status: 'ALL', search: '  INV-7  ' }, page, '/api/host/invoices'],
    ['getCustomerInvoices', undefined, page, '/api/customer/invoices'],
    ['getHostInvoiceDetail', '7', detail, '/api/host/invoices/7'],
    ['getCustomerInvoiceDetail', 7, detail, '/api/customer/invoices/7'],
  ]) {
    const result = await invoiceServiceModule[method](arg, options);
    assert.deepEqual(result, expected);
    assert.notEqual(result, expected);
    assert.equal(calls.at(-1).url, path);
    assert.equal(calls.at(-1).signal, options.signal);
  }
  assert.equal(calls.length, 5);
  assert.deepEqual(calls[1].params, { page: 2, per_page: 5, search: 'INV-7' });
  assert.deepEqual(calls[2].params, { page: 1, per_page: 10 });
  assert.equal(calls[0].data, undefined);
  assert.equal(calls.filter(({ method }) => method === 'post').length, 1);
});

test('public create keeps rejection identity and existing formatting/error helper exports', async () => {
  const error = new AxiosError('Synthetic conflict', '', {}, null, { status: 409, data: { message: 'Already exists.' }, headers: {} });
  let calls = 0;
  publicAdapter = async () => { ++calls; throw error; };
  await assert.rejects(invoiceServiceModule.createHostInvoice(11), (received) => received === error);
  assert.equal(calls, 1);
  assert.equal(invoiceServiceModule.parseInvoiceError(error).status, 409);
  assert.equal(invoiceServiceModule.formatVnd('1234567.89'), new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(1234567.89));
});
