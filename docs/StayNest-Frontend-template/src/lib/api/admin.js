import {
  API_BASE_URL,
  ApiError,
  apiDelete,
  apiGet,
  apiPatch,
  apiPost,
  apiPut,
  getAuthHeaders,
} from "./client";

async function uploadPhoto(path, file) {
  const form = new FormData();
  form.append("file", file);
  const res = await fetch(`${API_BASE_URL}${path}`, {
    method: "POST",
    body: form,
    headers: {
      Accept: "application/json, text/plain, */*",
      ...getAuthHeaders(),
    },
    credentials: "include",
  });
  if (!res.ok) {
    throw new ApiError(
      `Request failed (${res.status}) for POST ${path}`,
      res.status,
    );
  }
  const text = (await res.text()).trim();
  if (text.startsWith("\"") && text.endsWith("\"")) return JSON.parse(text);
  if (text.startsWith("{")) {
    try {
      const j = JSON.parse(text);
      return j.url ?? j.data ?? text;
    } catch {
      /* not JSON */
    }
  }
  return text;
}

export function listHotels(q = {}) {
  return apiGet("/admin/hotels", {
    page: q.page ?? 0,
    size: q.size ?? 50,
  });
}

export function createHotel(body) {
  return apiPost("/admin/hotels", body);
}

export function updateHotel(hotelId, body) {
  return apiPut(`/admin/hotels/${hotelId}`, body).then((r) => {
    if (!r) throw new ApiError("Empty response updating hotel", 500);
    return r;
  });
}

export function deleteHotel(hotelId) {
  return apiDelete(`/admin/hotels/${hotelId}`);
}

export function uploadHotelPhoto(hotelId, file) {
  return uploadPhoto(`/admin/hotels/${hotelId}/photos`, file);
}

export function activateHotel(hotelId) {
  return apiPatch(`/admin/hotels/${hotelId}/activate`, {}).then(() => undefined);
}

export function listRooms(hotelId) {
  return apiGet(`/admin/hotels/${hotelId}/rooms`);
}

export function createRoom(hotelId, body) {
  return apiPost(`/admin/hotels/${hotelId}/rooms`, body);
}

export function updateRoom(hotelId, roomId, body) {
  return apiPut(`/admin/hotels/${hotelId}/rooms/${roomId}`, body).then((r) => {
    if (!r) throw new ApiError("Empty response updating room", 500);
    return r;
  });
}

export function deleteRoom(hotelId, roomId) {
  return apiDelete(`/admin/hotels/${hotelId}/rooms/${roomId}`);
}

export function uploadRoomPhoto(hotelId, roomId, file) {
  return uploadPhoto(`/admin/hotels/${hotelId}/rooms/${roomId}/photos`, file);
}

export function getRoomInventory(roomId) {
  return apiGet(`/admin/inventory/rooms/${roomId}`);
}

export function updateRoomInventory(roomId, body) {
  return apiPatch(`/admin/inventory/rooms/${roomId}`, body).then(() => undefined);
}

export function getHotelBookings(hotelId, q = {}) {
  return apiGet(`/admin/hotels/${hotelId}/bookings`, {
    page: q.page ?? 0,
    size: q.size ?? 20,
  });
}

export function getRefundPending() {
  return apiGet("/admin/hotels/bookings/refund-pending");
}

export function settleRefund(bookingId) {
  return apiPost(`/admin/hotels/bookings/${bookingId}/refund`, {}).then(() => undefined);
}

export function getHotelReport(hotelId, q = {}) {
  return apiGet(`/admin/hotels/${hotelId}/reports`, {
    startDate: q.startDate,
    endDate: q.endDate,
  });
}
