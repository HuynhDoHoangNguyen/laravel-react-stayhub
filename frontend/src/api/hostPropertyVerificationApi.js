import { requireApiId } from './apiId.js';

const endpoint = (propertyId) => `/api/host/properties/${requireApiId(propertyId, 'Mã cơ sở')}/verification`;

export function createHostPropertyVerificationApi(client) {
    return {
        async current(propertyId, { signal } = {}) {
            return (await client.get(endpoint(propertyId), { signal, timeout: 10000 })).data;
        },
        async submit(propertyId, document, { signal } = {}) {
            const url = endpoint(propertyId);
            const body = new FormData();
            body.append('document', document);
            await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });

            return (await client.post(url, body, { signal, timeout: 30000 })).data;
        },
    };
}
