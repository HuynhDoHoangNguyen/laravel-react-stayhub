const currency = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 });

export function formatCatalogPrice(value) {
    if (!['string', 'number'].includes(typeof value) || String(value).trim() === '' || !Number.isFinite(Number(value))) return 'Chưa có giá tham khảo';
    return currency.format(Number(value));
}

export function catalogParams(search = '') {
    const params = new URLSearchParams(search);
    const result = { page: 1, per_page: 12 };
    for (const key of params.keys()) {
        if (!Object.hasOwn(result, key) || params.getAll(key).length !== 1) return null;
        const value = params.get(key);
        if (!/^[1-9]\d*$/.test(value) || !Number.isSafeInteger(Number(value))) return null;
        result[key] = Number(value);
    }
    return result.page <= 2147483647 && result.per_page <= 24 ? result : null;
}

export function catalogBackLink(search) {
    return typeof search === 'string' && (search === '' || search.startsWith('?')) && catalogParams(search)
        ? '/properties' + search : '/properties';
}

export function adaptCatalogProperty(dto) {
    if (!dto || !dto.id || typeof dto.name !== 'string') throw new Error('Invalid catalog response');
    const names = (items) => (items || []).map((item) => item.name);
    return {
        id: dto.id, name: dto.name, address: dto.address, description: dto.description,
        propertyType: dto.type, price: dto.starting_price ?? null,
        averageRating: dto.rating ?? null, reviewCount: dto.review_count ?? 0,
        photos: dto.images || (dto.primary_image ? [dto.primary_image] : []),
        checkInTime: dto.check_in_time, checkOutTime: dto.check_out_time,
        amenities: names(dto.amenities), amenityIds: (dto.amenities || []).map((item) => item.id),
        rooms: (dto.rooms || []).map((room) => ({
            id: room.id, name: room.name, roomNumber: room.room_number, description: room.description,
            capacity: room.capacity, price: room.price_per_night ?? null,
            roomTypeName: room.room_type?.name, photos: room.images || [], amenities: names(room.amenities),
        })),
    };
}

export function catalogError(error) {
    const failure = new Error('Không thể tải thông tin cơ sở lưu trú. Vui lòng thử lại.');
    failure.status = error?.response?.status || error?.status || 0;
    failure.fields = failure.status === 422 ? (error.response?.data?.errors || error.fields || {}) : {};
    if (failure.status === 422) failure.message = 'Tham số danh sách không hợp lệ. Chỉ hỗ trợ trang và số cơ sở mỗi trang.';
    if (failure.status === 404) failure.message = 'Không tìm thấy cơ sở lưu trú.';
    return failure;
}

export function createCatalogService(client, { dev = false, enableMocks = 'false', properties = [] } = {}) {
    const mocked = dev === true && enableMocks === 'true';
    async function getProperties({ page = 1, per_page = 12 } = {}, options = {}) {
        if (mocked) return { data: properties.slice((page - 1) * per_page, page * per_page), source: 'mock',
            meta: { current_page: page, per_page, total: properties.length, last_page: Math.max(1, Math.ceil(properties.length / per_page)) }, links: {} };
        try {
            const { data: body } = await client.get('/api/public/properties', { timeout: 10000, ...options, params: { page, per_page } });
            if (!Array.isArray(body?.data) || !body.meta
                || !['current_page', 'last_page', 'per_page', 'total'].every((key) => Number.isSafeInteger(body.meta[key]))
                || body.meta.current_page < 1 || body.meta.last_page < 1 || body.meta.per_page < 1 || body.meta.per_page > 24 || body.meta.total < 0) {
                throw new Error('Invalid list response');
            }
            return { data: body.data.map(adaptCatalogProperty), meta: body.meta, links: body.links || {}, source: 'api' };
        } catch (error) { throw catalogError(error); }
    }
    async function getPropertyById(id, options = {}) {
        if (mocked) return { data: properties.find((item) => String(item.id) === String(id)) || null, source: 'mock' };
        if (!/^[1-9]\d*$/.test(String(id))) return { data: null, source: 'api', unavailable: true };
        try {
            const response = await client.get(`/api/public/properties/${encodeURIComponent(id)}`, { timeout: 10000, ...options });
            return { data: adaptCatalogProperty(response.data?.data), source: 'api' };
        } catch (error) {
            if (error?.response?.status === 404) return { data: null, source: 'api', unavailable: true };
            throw catalogError(error);
        }
    }
    return { getProperties, getPropertyById, getFeaturedProperties: (options) => getProperties({ page: 1, per_page: 6 }, options) };
}

// Abort where supported and check the generation even when a transport ignores abort.
export function createCatalogLoader(load) {
    let version = 0, controller;
    let state = { status: 'loading', result: null, error: null };
    const listeners = new Set();
    const set = (value) => { state = value; listeners.forEach((listener) => listener()); };
    return {
        subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
        getSnapshot: () => state,
        cancel() { ++version; controller?.abort(); },
        async reload() {
            controller?.abort(); controller = new AbortController(); const current = ++version;
            set({ status: 'loading', result: null, error: null });
            try {
                const result = await load({ signal: controller.signal });
                if (version === current) set({ status: 'ready', result, error: null });
            } catch (error) {
                if (version === current) set({ status: 'error', result: null, error: catalogError(error) });
            }
        },
    };
}
