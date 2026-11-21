import { roomSearchApi } from '../api/roomSearchApi.js';
import {
  mapRoomAvailabilitySearchResponse,
  mapAvailabilityRoomItem,
} from '../mappers/roomAvailabilitySearchMapper.js';

function getTodayIso() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function getTomorrowIso() {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

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
    check_in_date: checkIn || getTodayIso(),
    check_out_date: checkOut || getTomorrowIso(),
    guest_count: Math.max(1, isNaN(guests) ? 2 : guests),
  };

  if (keyword.trim()) params.keyword = keyword.trim();
  if (url.has('min_price') && url.get('min_price') !== '') params.min_price = url.get('min_price');
  if (url.has('max_price') && url.get('max_price') !== '') params.max_price = url.get('max_price');
  if (url.has('sort') && url.get('sort') !== '') params.sort = url.get('sort');
  if (url.has('page') && url.get('page') !== '') params.page = Number(url.get('page'));
  if (url.has('per_page') && url.get('per_page') !== '') params.per_page = Number(url.get('per_page'));
  if (url.has('min_rating') && url.get('min_rating') !== '') params.min_rating = Number(url.get('min_rating'));

  const roomTypeIds = url.getAll('room_type_ids[]').length
    ? url.getAll('room_type_ids[]')
    : url.getAll('room_type_ids');
  if (roomTypeIds.length > 0) {
    params.room_type_ids = roomTypeIds.map(Number).filter((n) => Number.isInteger(n) && n > 0);
  } else if (url.has('room_type_id') && url.get('room_type_id') !== '') {
    params.room_type_id = Number(url.get('room_type_id'));
  }

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
    room_type_ids: params.room_type_ids ?? (params.room_type_id ? [params.room_type_id] : []),
    min_rating: params.min_rating ?? '',
  };
}

export function mapAvailabilityRooms(items) {
  if (!Array.isArray(items)) return [];
  return items.map(mapAvailabilityRoomItem);
}

export async function searchAvailableRooms(search, options = {}) {
  const { params } = parseAvailabilitySearchParams(search);
  const response = await roomSearchApi.search(params, options);
  return mapRoomAvailabilitySearchResponse(response.data);
}
