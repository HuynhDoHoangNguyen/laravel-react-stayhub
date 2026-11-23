import { catalogParams } from '../services/catalog.js';

const roomFilters = ['min_price', 'max_price', 'min_rating', 'sort', 'room_type_id', 'room_type_ids', 'room_type_ids[]',
    'amenity_ids', 'amenity_ids[]'];
const stayKeys = new Set(['checkIn', 'checkOut', 'startDate', 'endDate', 'check_in_date', 'check_out_date',
    'adults', 'children', 'guest_count']);
const keys = new Set(['tab', 'page', 'per_page', 'roomsCount', 'city', 'keyword', 'q', 'property_min_rating', ...roomFilters, ...stayKeys]);

export function readSearchNavigation(search = '') {
    const url = new URLSearchParams(search);
    const tabs = url.getAll('tab');
    const tab = tabs[0] ?? (roomFilters.some(key => url.has(key)) ? 'rooms' : 'properties');
    const error = tabs.length > 1 || !['rooms', 'properties'].includes(tab) ? 'Tab tìm kiếm không hợp lệ.' : null;
    const propertyRating = url.get('property_min_rating') ?? '';
    url.delete('tab');
    url.delete('property_min_rating');
    const pagination = new URLSearchParams();
    for (const key of ['page', 'per_page']) for (const value of url.getAll(key)) pagination.append(key, value);
    return { tab, error, roomSearch: url.toString(), propertyParams: catalogParams(pagination.toString()), propertyRating };
}

export function changePropertyFilters(search, { keyword = '', minRating = '' } = {}) {
    const url = new URLSearchParams(search);
    for (const key of ['city', 'keyword', 'q', 'property_min_rating', 'page']) url.delete(key);
    if (keyword.trim()) url.set('city', keyword.trim());
    if (minRating) url.set('property_min_rating', minRating);
    url.set('tab', 'properties');
    return url.toString();
}

export function changeSearchTab(search, tab) {
    if (!['rooms', 'properties'].includes(tab)) throw new Error('Tab tìm kiếm không hợp lệ.');
    const url = new URLSearchParams(search);
    // Each data source owns its page-size limit and default.
    url.delete('page');
    url.delete('per_page');
    url.set('tab', tab);
    return url.toString();
}

export function searchStayQuery(search = '') {
    const query = new URLSearchParams(search);
    for (const key of new Set(query.keys())) {
        if (!stayKeys.has(key)) query.delete(key);
    }
    return query.size ? '?' + query.toString() : '';
}

export function legacyPropertiesTarget(search = '') {
    const url = new URLSearchParams(search);
    url.set('tab', 'properties');
    return '/search?' + url.toString();
}

export function searchBackLink(state) {
    const search = state?.searchPage;
    if (typeof search === 'string' && (search === '' || search.startsWith('?'))) {
        const url = new URLSearchParams(search);
        if ([...url.keys()].every(key => keys.has(key)) && !readSearchNavigation(search).error) return '/search' + search;
    }
    const legacy = state?.catalogPage;
    if (typeof legacy === 'string' && (legacy === '' || legacy.startsWith('?')) && catalogParams(legacy)) return legacyPropertiesTarget(legacy);
    return '/search?tab=properties';
}
