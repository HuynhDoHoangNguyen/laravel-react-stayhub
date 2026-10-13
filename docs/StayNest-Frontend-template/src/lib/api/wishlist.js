import { apiDelete, apiGet, apiPost } from "./client";

const WISHLIST_PATH = "/users/wishlist";

export async function getWishlist(q = {}) {
  return apiGet(WISHLIST_PATH, {
    page: q.page ?? 0,
    size: q.size ?? 24,
  });
}

export async function addToWishlist(hotelId) {
  await apiPost(`${WISHLIST_PATH}/${hotelId}`, {});
}

export async function removeFromWishlist(hotelId) {
  await apiDelete(`${WISHLIST_PATH}/${hotelId}`);
}
