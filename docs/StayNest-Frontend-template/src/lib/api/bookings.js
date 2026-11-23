import { apiGet, apiPost } from "./client";

const MY_BOOKINGS_PATH = "/users/myBookings";

export async function getMyBookings(q = {}) {
  const raw = await apiGet(MY_BOOKINGS_PATH, {
    page: q.page ?? 0,
    size: q.size ?? 50,
  });

  if (Array.isArray(raw)) {
    const size = q.size ?? raw.length;
    return {
      content: raw,
      totalElements: raw.length,
      totalPages: 1,
      number: 0,
      size,
      first: true,
      last: true,
      empty: raw.length === 0,
      numberOfElements: raw.length,
    };
  }
  return raw;
}

export function initBooking(body) {
  return apiPost("/bookings/init", body);
}

export function addBookingGuests(bookingId, guestIds) {
  return apiPost(`/bookings/${bookingId}/addGuests`, guestIds);
}

export function initiatePayment(bookingId) {
  return apiPost(`/bookings/${bookingId}/payments`, {});
}

export function getBookingStatus(bookingId) {
  return apiGet(`/bookings/${bookingId}/status`);
}

export function getBooking(bookingId) {
  return apiGet(`/bookings/${bookingId}`);
}

export function cancelBooking(bookingId) {
  return apiPost(`/bookings/${bookingId}/cancel`, {});
}

export function verifyPayment(bookingId, sessionId) {
  return apiPost(`/bookings/${bookingId}/verify-payment?sessionId=${encodeURIComponent(sessionId || "")}`, {});
}
