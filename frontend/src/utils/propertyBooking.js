import { requireApiId } from '../api/apiId.js';

function calendarDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    const date = new Date(value + 'T00:00:00Z');
    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

// URL context is shared by room details and the future property booking panel.
export function readPropertyBooking(search = '', today = new Date()) {
    const url = new URLSearchParams(search);
    const errors = [];
    const field = (names, fallback = '') => {
        const values = names.flatMap(name => url.getAll(name));
        if (names.some(name => url.getAll(name).length > 1) || new Set(values).size > 1) errors.push('Thông tin lưu trú bị trùng hoặc mâu thuẫn.');
        return values[0] ?? fallback;
    };
    const checkIn = field(['checkIn', 'startDate', 'check_in_date']);
    const checkOut = field(['checkOut', 'endDate', 'check_out_date']);
    const rawGuests = field(['guest_count']);
    const adults = field(['adults'], rawGuests || '2');
    const children = field(['children'], '0');
    const integer = value => /^(0|[1-9]\d*)$/.test(value) && Number.isSafeInteger(Number(value));
    if (!integer(adults) || Number(adults) < 1 || !integer(children)
        || !Number.isSafeInteger(Number(adults) + Number(children))
        || (rawGuests !== '' && (!integer(rawGuests) || Number(rawGuests) !== Number(adults) + Number(children)))) {
        errors.push('Số khách không hợp lệ hoặc không khớp với tổng khách.');
    }
    const localToday = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
    if ((checkIn && !calendarDate(checkIn)) || (checkOut && !calendarDate(checkOut))
        || (checkIn && checkIn < localToday) || (checkIn && checkOut && checkOut <= checkIn)) errors.push('Ngày nhận hoặc trả phòng không hợp lệ.');
    return { status: errors.length ? 'invalid' : !checkIn || !checkOut ? 'missing' : 'valid',
        checkIn, checkOut, adults: Number(adults), children: Number(children), errors };
}

export function bookingContextQuery(context) {
    return new URLSearchParams({ checkIn: context.checkIn, checkOut: context.checkOut,
        adults: String(context.adults), children: String(context.children) }).toString();
}

export function checkoutTarget(propertyId, roomId, context) {
    if (context.status !== 'valid') return null;
    return '/customer/checkout?' + new URLSearchParams({ propertyId: String(requireApiId(propertyId, 'Mã cơ sở')),
        roomId: String(requireApiId(roomId, 'Mã phòng')) }).toString() + '&' + bookingContextQuery(context);
}

export function bookingSearchTarget(context) {
    const url = new URLSearchParams();
    if (context.checkIn) url.set('startDate', context.checkIn);
    if (context.checkOut) url.set('endDate', context.checkOut);
    if (Number.isSafeInteger(context.adults) && context.adults > 0) url.set('adults', String(context.adults));
    if (Number.isSafeInteger(context.children) && context.children >= 0) url.set('children', String(context.children));
    return '/search?' + url.toString();
}
