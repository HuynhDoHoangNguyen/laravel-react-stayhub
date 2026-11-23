import { apiGet, apiPost, apiPut, apiDelete } from "./client";

const BASE = "/users/guests";

export function listGuests(q = {}) {
  return apiGet(BASE, {
    page: q.page ?? 0,
    size: q.size ?? 20,
    sort: q.sort ?? "id,desc",
  });
}

export function createGuest(body) {
  return apiPost(BASE, body);
}

export function updateGuest(id, body) {
  return apiPut(`${BASE}/${id}`, body).then(() => undefined);
}

export function deleteGuest(id) {
  return apiDelete(`${BASE}/${id}`);
}
