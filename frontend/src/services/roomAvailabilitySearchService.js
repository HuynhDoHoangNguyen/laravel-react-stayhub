import { roomSearchApi } from '../api/roomSearchApi.js';

export function isAvailabilitySearch(search) {
  if (!search) return false;
  const url = typeof search === 'string' ? new URLSearchParams(search) : search;
  const hasCheckIn = Boolean(url.get('startDate') || url.get('check_in_date'));
  const hasCheckOut = Boolean(url.get('endDate') || url.get('check_out_date'));
  return hasCheckIn && hasCheckOut;
}

export function parseAvailabilitySearchParams(search) {
  const url = typeof search === 'string' ? new URLSearchParams(search) : search;
  const checkIn = url.get('startDate') || url.get('check_in_date') || '';
  const checkOut = url.get('endDate') || url.get('check_out_date') || '';
  const adults = parseInt(url.get('adults') || '2', 10);
  const children = parseInt(url.get('children') || '0', 10);
  const rawGuests = url.get('guest_count');
  const guests = rawGuests ? parseInt(rawGuests, 10) : (adults + children);
  const keyword = url.get('city') || url.get('q') || url.get('keyword') || '';

  const params = {
    check_in_date: checkIn,
    check_out_date: checkOut,
    guest_count: Math.max(1, isNaN(guests) ? 2 : guests),
  };

  if (keyword.trim()) params.keyword = keyword.trim();
  if (url.has('min_price') && url.get('min_price') !== '') params.min_price = url.get('min_price');
  if (url.has('max_price') && url.get('max_price') !== '') params.max_price = url.get('max_price');
  if (url.has('sort') && url.get('sort') !== '') params.sort = url.get('sort');
  if (url.has('page') && url.get('page') !== '') params.page = Number(url.get('page'));
  if (url.has('per_page') && url.get('per_page') !== '') params.per_page = Number(url.get('per_page'));
  if (url.has('room_type_id') && url.get('room_type_id') !== '') params.room_type_id = Number(url.get('room_type_id'));

  const amenityIds = url.getAll('amenity_ids[]').length
    ? url.getAll('amenity_ids[]')
    : url.getAll('amenity_ids');
  if (amenityIds.length > 0) {
    params.amenity_ids = amenityIds.map(Number).filter((n) => Number.isInteger(n) && n > 0);
  }

  return {
    params,
    checkIn,
    checkOut,
    keyword,
    adults,
    children,
    guests: params.guest_count,
  };
}

export function mapAvailabilityRooms(items) {
  if (!Array.isArray(items)) return [];
  return items.map((room) => ({
    id: room.id,
    name: room.name,
    capacity: room.capacity,
    price_per_night: String(room.price_per_night ?? '0.00'),
    property: {
      id: room.property?.id,
      name: room.property?.name ?? '',
      address: room.property?.address ?? '',
      rating: room.property?.rating ?? null,
      review_count: room.property?.review_count ?? 0,
    },
    room_type: {
      id: room.room_type?.id,
      name: room.room_type?.name ?? '',
    },
    amenities: Array.isArray(room.amenities)
      ? room.amenities.map((a) => ({ id: a.id, name: a.name }))
      : [],
    images: Array.isArray(room.images)
      ? room.images.map((img) => (typeof img === 'string' ? img : (img.image_path || '')))
      : [],
  }));
}

export async function searchAvailableRooms(search, options = {}) {
  const { params } = parseAvailabilitySearchParams(search);
  const response = await roomSearchApi.search(params, options);
  const payload = response.data;
  return {
    data: mapAvailabilityRooms(payload?.data || []),
    meta: {
      current_page: payload?.meta?.current_page ?? 1,
      last_page: payload?.meta?.last_page ?? 1,
      per_page: payload?.meta?.per_page ?? 15,
      total: payload?.meta?.total ?? (payload?.data?.length || 0),
    },
  };
}
