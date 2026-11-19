/** Public property transport. The shared client retains cookie/XSRF and Auth handling. */
export function createPublicPropertyApi(client) {
    return {
        async getProperties(params, options = {}) {
            return (await client.get('/api/public/properties', { timeout: 10000, ...options, params })).data;
        },
        async getPropertyById(id, options = {}) {
            return (await client.get(`/api/public/properties/${encodeURIComponent(id)}`, { timeout: 10000, ...options })).data;
        },
        // Existing Search consumer; its backend belongs to TV2 and is not registered yet.
        async searchProperties(params, options = {}) {
            return (await client.get('/api/public/properties/search', { ...options, params })).data;
        },
    };
}
