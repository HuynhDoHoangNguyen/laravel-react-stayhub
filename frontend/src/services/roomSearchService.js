import api from '../api/axios.js';
import { createPublicRoomApi } from '../api/publicRoomApi.js';
import { mapRoomSearchPage, mapRoomFilterOptions } from '../mappers/roomSearchMapper.js';

const scalarFields = ['min_price', 'max_price', 'guest_count', 'min_rating', 'sort', 'page', 'per_page'];
const listFields = ['room_type_ids', 'amenity_ids'];
const supported = new Set([...scalarFields, ...listFields.map(key => `${key}[]`)]);

// Preserve raw strings for server validation, including malformed/blank URL values.
export function readRoomSearch(search) {
    const url = new URLSearchParams(search);
    const params = {};
    const errors = {};
    for (const key of scalarFields) {
        if (url.has(key)) params[key] = url.get(key);
        if (url.getAll(key).length > 1) errors[key] = ['Bộ lọc này chỉ nhận một giá trị.'];
    }
    for (const key of listFields) if (url.has(`${key}[]`)) params[key] = url.getAll(`${key}[]`);
    return { params, unsupported: [...new Set([...url.keys()].filter(key => !supported.has(key)))], errors };
}

export function updateRoomSearch(search, patch, { clearUnsupported = false } = {}) {
    const url = new URLSearchParams(search);
    if (clearUnsupported) for (const key of [...url.keys()]) if (!supported.has(key)) url.delete(key);
    for (const [key, value] of Object.entries(patch)) {
        if (listFields.includes(key)) {
            url.delete(`${key}[]`);
            for (const item of value ?? []) url.append(`${key}[]`, String(item));
        } else if (scalarFields.includes(key)) {
            url.delete(key);
            if (value !== null && value !== undefined && value !== '') url.set(key, String(value));
        }
    }
    // Any change to a criterion/sort/page-size restarts server pagination.
    if (Object.keys(patch).some(key => key !== 'page') || clearUnsupported) url.delete('page');
    return url.toString();
}

export function createRoomSearchService(roomApi) {
    return {
        async list(search, { signal } = {}) {
            const parsed = readRoomSearch(search);
            if (parsed.unsupported.length || Object.keys(parsed.errors).length) {
                const error = new Error('Vui lòng kiểm tra các bộ lọc trên địa chỉ trang.');
                error.response = { status: 422, data: { errors: { ...parsed.errors,
                    ...Object.fromEntries(parsed.unsupported.map(key => [key, ['Bộ lọc chưa được hỗ trợ.']])) } } };
                throw error;
            }
            return mapRoomSearchPage((await roomApi.list(parsed.params, { signal })).data);
        },
        async filterOptions({ signal } = {}) {
            return mapRoomFilterOptions((await roomApi.filterOptions({ signal })).data);
        },
    };
}

export const roomSearchService = createRoomSearchService(createPublicRoomApi(api));

// A reusable request lifecycle: abort on replacement/unmount and reject late results.
export function createRoomSearchLoader() {
    let revision = 0;
    let controller;
    let state = { loading: true, value: null, error: null };
    const listeners = new Set();
    const emit = next => { state = next; for (const listener of listeners) listener(); };
    return {
        subscribe: listener => { listeners.add(listener); return () => listeners.delete(listener); },
        getSnapshot: () => state,
        async load(request) {
            const current = ++revision;
            controller?.abort();
            controller = new AbortController();
            const signal = controller.signal;
            emit({ loading: true, value: null, error: null });
            try {
                const value = await request(signal);
                if (current === revision && !signal.aborted) emit({ loading: false, value, error: null });
            } catch (error) {
                if (current === revision && !signal.aborted) emit({ loading: false, value: null, error });
            }
        },
        cancel() { ++revision; controller?.abort(); },
    };
}
