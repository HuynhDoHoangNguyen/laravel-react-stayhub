import api from '../api/axios';

const options = { timeout: 10000 };

export const adminUserService = {
    async listUsers(params = {}, reqOptions = {}) {
        const response = await api.get('/api/admin/users', {
            ...options,
            ...reqOptions,
            params,
        });
        return response.data;
    },

    async approveHost(userId, reqOptions = {}) {
        await api.get('/sanctum/csrf-cookie', options);
        const response = await api.post(`/api/admin/users/${encodeURIComponent(userId)}/approve-host`, {}, {
            ...options,
            ...reqOptions,
        });
        return response.data;
    },

    async updateUserStatus(userId, status, reqOptions = {}) {
        await api.get('/sanctum/csrf-cookie', options);
        const response = await api.patch(`/api/admin/users/${encodeURIComponent(userId)}/status`, { status }, {
            ...options,
            ...reqOptions,
        });
        return response.data;
    },
};
