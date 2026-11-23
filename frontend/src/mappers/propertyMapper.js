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

export function mapSearchRoom(room) {
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

export function mapFeaturedProperty(room) {
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

export function mapPropertyRooms(rooms) {
  return rooms.map(mapRoom);
}

export function mapPropertyFromRooms(rooms) {
  if (rooms.length === 0) return null;

  const property = rooms[0].property || {};
  return {
    ...property,
    id: property.id,
    name: property.name,
    address: property.address,
    phone: property.phone,
    propertyType: property.type,
    photos: rooms.flatMap((room) => room.photos).slice(0, 8),
    amenities: [...new Map(rooms.flatMap((room) => (room.amenities || []).map((name, index) => [room.amenityIds[index], name]))).values()],
    rooms,
  };
}
