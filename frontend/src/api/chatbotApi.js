const sendTimeout = 18000;

export function createChatbotApi(client) {
    async function post(path, payload, { signal } = {}) {
        await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
        signal?.throwIfAborted();
        const response = await client.post(path, payload, { signal, timeout: sendTimeout });
        return response.data;
    }

    return {
        send: (payload, options) => post('/api/customer/chat/messages', payload, options),
        sendPublic: (payload, options) => post('/api/public/chat/messages', payload, options),
    };
}
