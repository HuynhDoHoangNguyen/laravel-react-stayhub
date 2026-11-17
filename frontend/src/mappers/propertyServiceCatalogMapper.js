export function mapHostPropertiesResponse(response) {
  return response?.data || [];
}

export function mapPropertyServiceListResponse(response) {
  return response;
}

export function mapPropertyServiceResponse(response) {
  return response?.data || response;
}
