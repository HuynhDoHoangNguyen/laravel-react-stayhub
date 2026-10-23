import api from '@/api/axios';
import { MOCK_PROPERTIES } from '@/mocks/propertyMockData';

const MOCKS_ENABLED =
  import.meta.env.DEV && import.meta.env.VITE_ENABLE_PROPERTY_MOCKS === 'true';

function addDays(date, days) {
  const next = new Date(`${date}T12:00:00`);
  next.setDate(next.getDate() + days);
  return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
}

function defaultStay() {
  const today = new Date();
  const checkIn = addDays(
    `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`,
    1,
  );
  return { check_in_date: checkIn, check_out_date: addDays(checkIn, 2) };
}

function mapRoom(room) {
  const images = Array.isArray(room.images) ? room.images : [];
  const amenities = Array.isArray(room.amenities) ? room.amenities : [];
  return {
    ...room,
    price: Number(room.price_per_night),
    roomTypeName: room.room_type?.name || '',
    room_type_name: room.room_type?.name || '',
    photos: images.map((image) => image.image_path).filter(Boolean),
    amenities: amenities.map((amenity) => amenity.name),
    amenityIds: amenities.map((amenity) => amenity.id),
  };
}

function mapSearchRoom(room) {
  const normalizedRoom = mapRoom(room);
  const property = room.property || {};

  return {
    id: property.id,
    resultId: room.id,
    roomId: room.id,
    name: property.name || room.name,
    city: property.address || '',
    address: property.address || '',
    propertyType: property.type || '',
    price: normalizedRoom.price,
    roomName: room.name,
    roomTypeName: normalizedRoom.roomTypeName,
    capacity: room.capacity,
    photos: normalizedRoom.photos,
    amenities: normalizedRoom.amenities,
    amenityIds: normalizedRoom.amenityIds,
  };
}

function mapFeaturedProperty(room) {
  const property = room.property || {};
  const mappedRoom = mapRoom(room);
  return {
    ...property,
    id: property.id,
    name: property.name || room.name,
    city: property.address || '',
    address: property.address || '',
    propertyType: property.type || '',
    price: mappedRoom.price,
    photos: mappedRoom.photos,
    rooms: [mappedRoom],
  };
}

function buildSearchParams({
  city = '',
  keyword = '',
  startDate,
  endDate,
  adults = 2,
  children = 0,
  minPrice,
  maxPrice,
  amenities = [],
  sortBy = 'PRICE_ASC',
  page = 1,
  perPage = 15,
  propertyId,
}) {
  const stay = defaultStay();
  const params = {
    check_in_date: startDate || stay.check_in_date,
    check_out_date: endDate || stay.check_out_date,
    guest_count: Math.max(1, Number(adults) + Number(children)),
    per_page: perPage,
    page,
  };

  if (city || keyword) params.keyword = [city, keyword].filter(Boolean).join(' ');
  if (propertyId) params.property_id = propertyId;
  if (minPrice !== undefined) params.min_price = minPrice;
  if (maxPrice !== undefined) params.max_price = maxPrice;
  if (amenities.length) params.amenity_ids = amenities;

  const sort = {
    PRICE_ASC: 'price_asc',
    PRICE_DESC: 'price_desc',
    NEWEST: 'newest',
  }[sortBy] || 'price_asc';
  params.sort = sort;

  return params;
}

export const propertyService = {
  async getFeaturedProperties() {
    if (MOCKS_ENABLED) {
      return { data: MOCK_PROPERTIES.slice(0, 6), source: 'mock' };
    }

    // The booking API exposes date based room search, not a property catalog endpoint.
    const stay = defaultStay();
    const response = await api.get('/api/public/rooms/search', {
      params: buildSearchParams({ ...stay, adults: 2 }),
    });
    const featured = new Map();
    (response.data?.data || []).forEach((room) => {
      if (!featured.has(room.property_id)) {
        featured.set(room.property_id, mapFeaturedProperty(room));
      }
    });
    return {
      data: [...featured.values()].slice(0, 6),
      source: 'api',
    };
  },

  async searchProperties({
    city = '',
    startDate,
    endDate,
    adults = 2,
    children = 0,
    minPrice,
    maxPrice,
    amenities = [],
    sortBy = 'PRICE_ASC',
    keyword = '',
    page = 1,
  } = {}) {
    if (MOCKS_ENABLED) {
      let results = [...MOCK_PROPERTIES];
      const term = [city, keyword].filter(Boolean).join(' ').toLowerCase();
      if (term) {
        results = results.filter((item) =>
          `${item.name} ${item.city} ${item.description || ''}`.toLowerCase().includes(term),
        );
      }
      if (minPrice != null) results = results.filter((item) => item.price >= Number(minPrice));
      if (maxPrice != null) results = results.filter((item) => item.price <= Number(maxPrice));
      if (sortBy === 'PRICE_ASC') results.sort((a, b) => a.price - b.price);
      if (sortBy === 'PRICE_DESC') results.sort((a, b) => b.price - a.price);
      const perPage = 15;
      const start = (page - 1) * perPage;
      return {
        data: results.slice(start, start + perPage),
        total: results.length,
        meta: { current_page: page, last_page: Math.max(1, Math.ceil(results.length / perPage)), per_page: perPage, total: results.length },
        source: 'mock',
      };
    }

    const response = await api.get('/api/public/rooms/search', {
      params: buildSearchParams({
        city,
        keyword,
        startDate,
        endDate,
        adults,
        children,
        minPrice,
        maxPrice,
        amenities,
        sortBy,
        page,
      }),
    });

    return {
      data: (response.data?.data || []).map(mapSearchRoom),
      meta: response.data?.meta,
      source: 'api',
    };
  },

  async getPropertyById(id, { startDate, endDate, adults = 2, children = 0 } = {}) {
    if (MOCKS_ENABLED) {
      const found = MOCK_PROPERTIES.find((item) => String(item.id) === String(id));
      return { data: found || null, source: 'mock' };
    }

    const response = await api.get('/api/public/rooms/search', {
      params: buildSearchParams({
        propertyId: id,
        startDate,
        endDate,
        adults,
        children,
        perPage: 50,
      }),
    });
    const rooms = (response.data?.data || []).map(mapRoom);
    if (rooms.length === 0) return { data: null, source: 'api' };

    const property = rooms[0].property || {};
    return {
      data: {
        ...property,
        id: property.id,
        name: property.name,
        address: property.address,
        phone: property.phone,
        propertyType: property.type,
        photos: rooms.flatMap((room) => room.photos).slice(0, 8),
        amenities: [...new Map(rooms.flatMap((room) => (room.amenities || []).map((name, index) => [room.amenityIds[index], name]))).values()],
        rooms,
      },
      source: 'api',
    };
  },
};
