export function mapHostBookingListResponse(response) {
  return response;
}

export function mapHostBookingResponse(response) {
  return response?.data || response;
}

export function mapHostBookingServicesListResponse(response) {
  return response?.data || [];
}

export function mapHostBookingServiceResponse(response) {
  return response?.data || response;
}
