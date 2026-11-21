import { useEffect, useState, useSyncExternalStore } from 'react';
import { propertyService } from '../services/propertyService.js';
import { createRoomSearchLoader } from '../services/roomSearchService.js';

export function createPropertyAvailabilityLoader(propertyId, context, source, service = propertyService) {
    const loader = createRoomSearchLoader();
    return { ...loader, reload: () => loader.load(async signal => {
        if (context.status !== 'valid') throw new Error('Vui lòng kiểm tra ngày và số khách.');
        if (source !== 'api') throw new Error('Không thể đặt phòng từ dữ liệu giả lập.');
        const result = await service.getBookableProperty(propertyId, {
            startDate: context.checkIn, endDate: context.checkOut, adults: context.adults, children: context.children,
        }, { signal });
        if (result.source !== 'room-search' || (result.data && (String(result.data.id) !== String(propertyId)
            || !Array.isArray(result.data.rooms) || result.data.rooms.some(room => !room?.id
                || (room.property?.id != null && String(room.property.id) !== String(propertyId)))))) {
            throw new Error('Dữ liệu phòng trống không hợp lệ.');
        }
        return result;
    }) };
}

// The caller keys the booking view by property and URL, resetting selection and requests together.
export function usePropertyAvailability(propertyId, context, source) {
    const [loader] = useState(() => createPropertyAvailabilityLoader(propertyId, context, source));
    const state = useSyncExternalStore(loader.subscribe, loader.getSnapshot, loader.getSnapshot);
    useEffect(() => {
        if (context.status === 'valid') loader.reload();
        return () => loader.cancel();
    }, [loader, context.status]);
    return { ...state, retry: loader.reload };
}

export function propertyRoomStatus(availability, roomId) {
    if (availability.loading || availability.error || !availability.value) return 'unknown';
    if (availability.value.data?.rooms.some(room => String(room.id) === String(roomId))) return 'available';
    return availability.value.isTruncated || availability.value.hasMore || availability.value.data?.hasMoreRooms ? 'unknown' : 'unavailable';
}
