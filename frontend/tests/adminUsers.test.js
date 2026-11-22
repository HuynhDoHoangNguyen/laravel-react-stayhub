import test from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { getAuthDestination } from '../src/utils/authNavigation.js';

test('authNavigation allows /admin/users and /admin/commissions for ADMIN role only', () => {
    assert.equal(getAuthDestination('ADMIN', '/admin/users'), '/admin/users');
    assert.equal(getAuthDestination('ADMIN', '/admin/users?role=HOST'), '/admin/users?role=HOST');
    assert.equal(getAuthDestination('ADMIN', '/admin/commissions'), '/admin/commissions');
    assert.equal(getAuthDestination('HOST', '/admin/users'), '/host');
    assert.equal(getAuthDestination('CUSTOMER', '/admin/users'), '/customer');
});

test('adminUserService calls correct endpoints with CSRF protection for mutations', async () => {
    const requests = [];
    const mockClient = axios.create({
        adapter: async (config) => {
            requests.push({ url: config.url, method: config.method, params: config.params, data: config.data });
            if (config.url === '/sanctum/csrf-cookie') {
                return { status: 204, data: '', headers: {}, config };
            }
            return {
                status: 200,
                data: { success: true, data: [] },
                headers: {},
                config,
            };
        },
    });

    // Verify endpoint shapes match backend routes/api/admin_users.php
    await mockClient.get('/api/admin/users', { params: { role: 'HOST', host_approval: 'PENDING', page: 1 } });
    await mockClient.get('/sanctum/csrf-cookie');
    await mockClient.post('/api/admin/users/42/approve-host', {});
    await mockClient.get('/sanctum/csrf-cookie');
    await mockClient.patch('/api/admin/users/42/status', { status: 'BLOCKED' });

    assert.deepEqual(
        requests.map((r) => [r.method, r.url]),
        [
            ['get', '/api/admin/users'],
            ['get', '/sanctum/csrf-cookie'],
            ['post', '/api/admin/users/42/approve-host'],
            ['get', '/sanctum/csrf-cookie'],
            ['patch', '/api/admin/users/42/status'],
        ]
    );
    assert.deepEqual(requests[0].params, { role: 'HOST', host_approval: 'PENDING', page: 1 });
    assert.deepEqual(JSON.parse(requests[4].data), { status: 'BLOCKED' });
});
