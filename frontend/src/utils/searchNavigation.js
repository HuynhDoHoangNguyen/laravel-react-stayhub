import { catalogParams } from '../services/catalog.js';

const keys = new Set(['tab', 'min_price', 'max_price', 'guest_count', 'min_rating', 'sort', 'page', 'per_page', 'room_type_ids[]', 'amenity_ids[]',
    'startDate', 'endDate', 'check_in_date', 'check_out_date', 'adults', 'children', 'roomsCount', 'city', 'keyword', 'q', 'room_type_id', 'room_type_ids', 'amenity_ids']);

export function readSearchNavigation(search = '') {
    const url = new URLSearchParams(search);
    const tabs = url.getAll('tab');
    const tab = tabs[0] ?? 'rooms';
    const error = tabs.length > 1 || !['rooms', 'properties'].includes(tab) ? 'Tab tìm kiếm không hợp lệ.' : null;
    url.delete('tab');
    const pagination = new URLSearchParams();
    for (const key of ['page', 'per_page']) for (const value of url.getAll(key)) pagination.append(key, value);
    return { tab, error, roomSearch: url.toString(), propertyParams: catalogParams(pagination.toString()) };
}

export function changeSearchTab(search, tab) {
    if (!['rooms', 'properties'].includes(tab)) throw new Error('Tab tìm kiếm không hợp lệ.');
    const url = new URLSearchParams(search);
    url.delete('page');
    if (tab === 'rooms') url.delete('tab');
    else url.set('tab', 'properties');
    return url.toString();
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
