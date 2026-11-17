/** Keep the existing command-response envelope projection used by checkout. */
export function mapBookingCommandResponse(response) {
  return response?.data || response;
}
