function mapperError(message = 'Dữ liệu tìm kiếm phòng trống không hợp lệ.') {
  const err = new Error(message);
  err.isMapperError = true;
  return err;
}

export function mapAvailabilityRoomItem(room) {
  if (!room || typeof room !== 'object') {
    throw mapperError('Thông tin phòng không hợp lệ.');
  }
  if (!room.id) {
    throw mapperError('Phòng thiếu định danh (id).');
  }

  return {
    id: room.id,
    name: room.name ?? '',
    capacity: Number(room.capacity ?? 1),
    price_per_night: String(room.price_per_night ?? '0.00'),
    property: {
      id: room.property?.id,
      name: room.property?.name ?? '',
      address: room.property?.address ?? '',
      rating: room.property?.rating !== null && room.property?.rating !== undefined ? Number(room.property.rating) : null,
      review_count: Number(room.property?.review_count ?? 0),
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
  };
}

export function mapRoomAvailabilitySearchResponse(response) {
  if (!response || typeof response !== 'object') {
    throw mapperError('Dữ liệu phản hồi tìm kiếm không hợp lệ.');
  }

  const body = ('data' in response && response.data && typeof response.data === 'object' && ('meta' in response.data || 'success' in response.data))
    ? response.data
    : response;

  if (!body || typeof body !== 'object') {
    throw mapperError('Dữ liệu kết quả tìm kiếm phòng trống không hợp lệ.');
  }

  if (!Array.isArray(body.data)) {
    throw mapperError('Dữ liệu kết quả tìm kiếm thiếu danh sách phòng hợp lệ.');
  }

  const meta = body.meta;
  if (!meta || typeof meta !== 'object' || meta.current_page === undefined || meta.last_page === undefined) {
    throw mapperError('Dữ liệu phân trang kết quả tìm kiếm không hợp lệ.');
  }

  return {
    success: body.success ?? true,
    data: body.data.map(mapAvailabilityRoomItem),
    meta: {
      current_page: Number(meta.current_page ?? 1),
      last_page: Number(meta.last_page ?? 1),
      per_page: Number(meta.per_page ?? 15),
      total: Number(meta.total ?? body.data.length),
    },
  };
}
