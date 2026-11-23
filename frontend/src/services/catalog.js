import { mapCatalogListResponse, mapCatalogDetailResponse } from '../mappers/catalogMapper.js';

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

export function catalogError(error) {
    const failure = new Error('Không thể tải thông tin cơ sở lưu trú. Vui lòng thử lại.');
    failure.status = error?.response?.status || error?.status || 0;
    failure.fields = failure.status === 422 ? (error.response?.data?.errors || error.fields || {}) : {};
    if (failure.status === 422) failure.message = 'Tham số danh sách không hợp lệ. Chỉ hỗ trợ trang và số cơ sở mỗi trang.';
    if (failure.status === 404) failure.message = 'Không tìm thấy cơ sở lưu trú.';
    return failure;
}

export function createCatalogService(propertyApi, { dev = false, enableMocks = 'false', properties = [] } = {}) {
    const mocked = dev === true && enableMocks === 'true';
    async function getProperties({ page = 1, per_page = 12 } = {}, options = {}) {
        if (mocked) return { data: properties.slice((page - 1) * per_page, page * per_page), source: 'mock',
            meta: { current_page: page, per_page, total: properties.length, last_page: Math.max(1, Math.ceil(properties.length / per_page)) }, links: {} };
        try {
            const body = await propertyApi.getProperties({ page, per_page }, options);
            return { ...mapCatalogListResponse(body), source: 'api' };
        } catch (error) { throw catalogError(error); }
    }
    async function getPropertyById(id, options = {}) {
        if (mocked) return { data: properties.find((item) => String(item.id) === String(id)) || null, source: 'mock' };
        if (!/^[1-9]\d*$/.test(String(id))) return { data: null, source: 'api', unavailable: true };
        try {
            const body = await propertyApi.getPropertyById(id, options);
            return { ...mapCatalogDetailResponse(body), source: 'api' };
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
