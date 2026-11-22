import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { getAuthDestination } from '../src/utils/authNavigation.js';
import { createAdminUserManagementApi } from '../src/api/adminUserManagementApi.js';

let adapter;
const sharedClient = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { adminUserManagementService, createAdminUserManagementService } = await import('../src/services/adminUserManagementService.js');
const { getAdminCustomers } = await import('../src/services/adminUserService.js');
test.after(() => mock.restoreAll());
const listing = () => ({ success: true, data: [{ id: 42, name: 'Pending Host', role: 'HOST', status: 'ACTIVE' }],
  summary: { total_users: 1, pending_hosts: 1 }, meta: { current_page: 1, per_page: 15, last_page: 1, total: 1 } });

test('Admin returnTo admits integrated screens while retaining role and URL boundaries', () => {
  for (const path of ['/admin/users', '/admin/hosts', '/admin/user-management', '/admin/bookings', '/admin/commissions']) {
    assert.equal(getAuthDestination('ADMIN', path+'?page=2'), path+'?page=2');
    assert.equal(getAuthDestination('HOST', path), '/host');
    assert.equal(getAuthDestination('CUSTOMER', path), '/customer');
  }
  for (const path of ['//evil.test/admin/user-management', '/admin/user-management/extra',
    '/admin/%2Fuser-management', 'https://evil.test/admin/users']) assert.equal(getAuthDestination('ADMIN', path), '/admin');
});

test('real management service uses separate endpoint, filters and signal', async () => {
  const calls = [];
  adapter = async config => { calls.push(config); return { status: 200, data: listing(), headers: {}, config }; };
  const signal = new AbortController().signal;
  const params = { keyword: 'Host', role: 'HOST', host_approval: 'PENDING', page: 2, per_page: 15 };
  assert.deepEqual(await adminUserManagementService.listUsers(params, { signal }), listing());
  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, '/api/admin/user-management');
  assert.deepEqual(calls[0].params, params);
  assert.equal(calls[0].signal, signal);
  assert.equal(calls[0].timeout, 10000);
});

test('Customer service retains endpoint and rejects role filters before transport', async () => {
  let calls = 0;
  adapter = async config => {
    calls++;
    assert.equal(config.url, '/api/admin/users');
    assert.equal(config.params.q, 'Customer');
    return { status: 200, data: { data: [], links: { first: '', last: '', prev: null, next: null },
      meta: { current_page: 1, per_page: 10, last_page: 1, total: 0 } }, headers: {}, config };
  };
  await getAdminCustomers({ q: 'Customer' });
  await assert.rejects(getAdminCustomers({ role: 'HOST' }), error => error.isLocalValidation === true);
  assert.equal(calls, 1);
});

test('approval retains unique endpoint and sends CSRF before one POST', async () => {
  const calls = [];
  adapter = async config => { calls.push(config); return { status: 200,
    data: { success: true, message: 'Approved', data: { id: 42 } }, headers: {}, config }; };
  const signal = new AbortController().signal;
  const result = await adminUserManagementService.approveHost('42', { signal });
  assert.deepEqual(calls.map(c => [c.method, c.url]), [
    ['get', '/sanctum/csrf-cookie'], ['post', '/api/admin/users/42/approve-host'],
  ]);
  assert.deepEqual(JSON.parse(calls[1].data), {});
  assert.equal(calls[0].signal, signal); assert.equal(calls[1].signal, signal);
  assert.equal(result.data.id, 42);
  assert.equal(adminUserManagementService.updateUserStatus, undefined);
});

test('invalid approval IDs never reach transport', async () => {
  let calls = 0;
  const service = createAdminUserManagementService({ approveHost: async () => { calls++; } });
  for (const id of [0, -1, '01', null, [], true, Number.MAX_SAFE_INTEGER+1]) {
    await assert.rejects(service.approveHost(id), error => error.isLocalValidation === true);
  }
  assert.equal(calls, 0);
});

test('management preserves network and cancellation identity and rejects malformed results', async () => {
  for (const failure of [new AxiosError('offline', 'ERR_NETWORK'), new CanceledError()]) {
    const api = createAdminUserManagementApi({ get: async () => { throw failure; } });
    await assert.rejects(createAdminUserManagementService(api).listUsers(), error => error === failure);
  }
  for (const value of [null, { data: [] }, { ...listing(), summary: null }, { ...listing(), data: {} }]) {
    await assert.rejects(createAdminUserManagementService({ listUsers: async () => value }).listUsers());
  }
});

test('failed CSRF request does not attempt or retry approval', async () => {
  const failure = new AxiosError('csrf failed', 'ERR_NETWORK');
  let posts = 0;
  const api = createAdminUserManagementApi({ get: async () => { throw failure; }, post: async () => { posts++; } });
  await assert.rejects(createAdminUserManagementService(api).approveHost(42), error => error === failure);
  assert.equal(posts, 0);
});

test('approval result keeps account status separate from approval and preserves actual counts', async () => {
  for (const status of ['ACTIVE', 'BLOCKED', 'INACTIVE']) {
    adapter = async config => ({ status: 200, headers: {}, config, data: config.url === '/sanctum/csrf-cookie'
      ? null : { success: true, message: 'Đã phê duyệt Đối tác thành công.', data: {
        id: 42, role: 'HOST', status, is_host_approved: true, host_approval_status: 'APPROVED',
        properties_count: 4, bookings_count: 0,
      } } });
    const result = await adminUserManagementService.approveHost(42);
    assert.equal(result.message, 'Đã phê duyệt Đối tác thành công.');
    assert.equal(result.data.status, status);
    assert.equal(result.data.is_host_approved, true);
    assert.equal(result.data.properties_count, 4);
    assert.equal(result.data.bookings_count, 0);
  }
});

test('useHostApproval isolates approval cache per user ID to prevent cross-account approval contamination', async () => {
    const { readFile } = await import('node:fs/promises');
    const { fileURLToPath } = await import('node:url');
    const hookPath = fileURLToPath(new URL('../src/hooks/useHostApproval.js', import.meta.url));
    const code = await readFile(hookPath, 'utf8');

    assert.match(code, /cachedUserId/);
    assert.match(code, /cachedUserId\s*===\s*currentUserId/);
    assert.match(code, /invalidateHostApprovalCache/);
});
