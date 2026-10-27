import api from '../api/axios';
import { normalizeAuthError, subscribeToAuthFailures } from './authSession';

const options = { timeout: 10000 };

export const authService = {
    async login({ email, password }) {
        try {
            await api.get('/sanctum/csrf-cookie', options);
            const response = await api.post('/login', { email: email.trim(), password }, options);
            return response.data.user;
        } catch (error) {
            throw normalizeAuthError(error);
        }
    },

    async restoreSession() {
        try {
            const response = await api.get('/api/me', options);
            return response.data.user;
        } catch (error) {
            throw normalizeAuthError(error);
        }
    },

    async logout() {
        try {
            // A previously invalidated/expired session may need a fresh CSRF cookie.
            await api.get('/sanctum/csrf-cookie', options);
            const response = await api.post('/logout', {}, options);
            return response.data;
        } catch (error) {
            throw normalizeAuthError(error);
        }
    },

    subscribeToAuthFailures(listener, getVersion) {
        return subscribeToAuthFailures(api, listener, getVersion);
    },
};
