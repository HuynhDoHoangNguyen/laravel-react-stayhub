import { apiGet } from "./client";

export function getMyReviews(page = 0, size = 10) {
  return apiGet("/users/myReviews", { page, size });
}
