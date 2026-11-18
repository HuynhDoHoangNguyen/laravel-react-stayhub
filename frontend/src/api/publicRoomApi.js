export function createPublicRoomApi(client) {
    return {
        list: (params, { signal } = {}) => client.get('/api/public/rooms', {
            params, signal, paramsSerializer: { indexes: false },
        }),
        filterOptions: ({ signal } = {}) => client.get('/api/public/rooms/filter-options', { signal }),
    };
}
