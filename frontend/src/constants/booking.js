export const BOOKING_STATUSES = {
  PENDING: { key: 'PENDING', label: 'Chờ xác nhận' },
  CONFIRMED: { key: 'CONFIRMED', label: 'Đã xác nhận' },
  CHECKED_IN: { key: 'CHECKED_IN', label: 'Đã nhận phòng' },
  COMPLETED: { key: 'COMPLETED', label: 'Đã hoàn thành' },
  CANCELLED: { key: 'CANCELLED', label: 'Đã hủy' },
  REJECTED: { key: 'REJECTED', label: 'Từ chối' },
};

export const BOOKING_API_ROUTES = {
  publicRoomSearch: '/api/public/rooms/search',
  publicRoomAvailability: (roomId) => `/api/public/rooms/${roomId}/availability`,
  publicPreview: '/api/public/bookings/preview',
  customerBookings: '/api/customer/bookings',
  customerBooking: (bookingId) => `/api/customer/bookings/${bookingId}`,
  customerCancel: (bookingId) => `/api/customer/bookings/${bookingId}/cancel`,
  customerBookingServices: (bookingId) => `/api/customer/bookings/${bookingId}/services`,
  hostBookings: '/api/host/bookings',
  hostBooking: (bookingId) => `/api/host/bookings/${bookingId}`,
  hostConfirm: (bookingId) => `/api/host/bookings/${bookingId}/confirm`,
  hostReject: (bookingId) => `/api/host/bookings/${bookingId}/reject`,
  hostCheckIn: (bookingId) => `/api/host/bookings/${bookingId}/check-in`,
  hostCheckOut: (bookingId) => `/api/host/bookings/${bookingId}/check-out`,
  hostBookingServices: (bookingId) => `/api/host/bookings/${bookingId}/services`,
};

export function getBookingStatusInfo(status) {
  const key = String(status || '').toUpperCase();
  return BOOKING_STATUSES[key] || { key, label: key || 'Không xác định' };
}
