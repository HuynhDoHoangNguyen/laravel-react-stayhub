export function mapHostBookingListResponse(response) {
  return response;
}

export function mapHostBookingResponse(response) {
  return response?.data || response;
}
