import { apiGet, apiPost } from "./client";

export function searchHotels(params, init) {
  return apiGet("/hotels/search", params, init);
}

export function searchHotelsNL(body, init) {
  return apiPost("/hotels/search/nl", body, init);
}

export function getHotelInfo(hotelId, params, init) {
  return apiGet(`/hotels/${hotelId}/info`, params, init);
}

export function getHotelReviews(hotelId, page = 0, size = 10, init) {
  return apiGet(`/hotels/${hotelId}/reviews`, { page, size }, init);
}

export function askHotelQuestion(hotelId, question, init) {
  return apiGet(`/hotels/${hotelId}/ask`, { question }, init);
}
