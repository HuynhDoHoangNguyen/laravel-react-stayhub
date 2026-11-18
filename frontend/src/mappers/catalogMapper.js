/** Pure public projections; prices stay decimal strings and missing values stay nullable. */
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

export function mapCatalogListResponse(body) {
    if (!Array.isArray(body?.data) || !body.meta
        || !['current_page', 'last_page', 'per_page', 'total'].every((key) => Number.isSafeInteger(body.meta[key]))
        || body.meta.current_page < 1 || body.meta.last_page < 1 || body.meta.per_page < 1 || body.meta.per_page > 24 || body.meta.total < 0) {
        throw new Error('Invalid list response');
    }
    return { data: body.data.map(adaptCatalogProperty), meta: body.meta, links: body.links || {} };
}

export function mapCatalogDetailResponse(body) {
    return { data: adaptCatalogProperty(body?.data) };
}

// Preserve the existing Search shape until TV2 supplies its own real API contract.
export function mapPropertySearchResponse(body) {
    const list = Array.isArray(body) ? body : body?.data;
    if (!Array.isArray(list)) throw new Error('Invalid property search response');
    return { data: list, total: list.length };
}
