import { API_BASE_URL, ApiError, apiGet, apiPatch, getAuthHeaders } from "./client";

const PROFILE_PATH = "/users/profile";

export function getProfile() {
  return apiGet(PROFILE_PATH);
}

export function updateProfile(body) {
  return apiPatch(PROFILE_PATH, body).then(() => undefined);
}

export async function uploadProfilePhoto(file) {
  const form = new FormData();
  form.append("file", file);

  const res = await fetch(`${API_BASE_URL}${PROFILE_PATH}/photo`, {
    method: "POST",
    body: form,
    headers: {
      Accept: "application/json, text/plain, */*",
      ...getAuthHeaders(),
    },
    credentials: "include",
  });

  if (!res.ok) {
    throw new ApiError(
      `Request failed (${res.status}) for POST ${PROFILE_PATH}/photo`,
      res.status,
    );
  }

  const text = (await res.text()).trim();
  if (text.startsWith("\"") && text.endsWith("\"")) return JSON.parse(text);
  if (text.startsWith("{")) {
    try {
      const j = JSON.parse(text);
      return j.url ?? j.avatarUrl ?? j.data ?? text;
    } catch {
      /* not JSON */
    }
  }
  return text;
}
