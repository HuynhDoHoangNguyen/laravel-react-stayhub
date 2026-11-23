import api from '../api/axios.js';
import { createAdminPropertyApi } from '../api/adminPropertyApi.js';
import { mapAdminPropertyPage } from '../mappers/adminPropertyMapper.js';

const transport = createAdminPropertyApi(api);
const extensions = { 'application/pdf': 'pdf', 'image/jpeg': 'jpg', 'image/png': 'png' };

function invalidVerification() {
  return Object.assign(new Error('Dữ liệu hồ sơ xác minh từ máy chủ không hợp lệ.'), {
    isApiError: true, kind: 'contract', status: 0, fields: {}, isCancelled: false,
  });
}

const timestamp = (value) => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value)
  && Number.isFinite(Date.parse(value));

function mapVerification(body, allowEmpty) {
  const entry = body?.data;
  if (allowEmpty && entry === null) return null;
  if (!Number.isSafeInteger(entry?.id) || entry.id <= 0 || !['SUBMITTED', 'APPROVED', 'REJECTED', 'INVALIDATED'].includes(entry.status)
    || typeof entry.address_snapshot !== 'string' || typeof entry.address_current !== 'string'
    || !timestamp(entry.submitted_at)) throw invalidVerification();

  const { id, status, address_snapshot, address_current, submitted_at, reviewed_at, rejection_reason } = entry;
  if ((status === 'SUBMITTED' ? reviewed_at !== null : !timestamp(reviewed_at))
    || (status === 'REJECTED' ? typeof rejection_reason !== 'string' || !rejection_reason.trim() : rejection_reason !== null)) {
    throw invalidVerification();
  }
  return { id, status, address_snapshot, address_current, submitted_at, reviewed_at, rejection_reason };
}

export async function getAdminPropertyVerification(id, options) {
  return mapVerification(await transport.verification(id, options), true);
}

export async function reviewAdminPropertyVerification(id, decision, reason, options) {
  const input = decision === 'REJECT' ? { decision, reason: reason.trim() } : { decision };
  return mapVerification(await transport.reviewVerification(id, input, options), false);
}

export async function downloadAdminPropertyVerification(id, verificationId, options) {
  const blob = await transport.downloadVerification(id, options);
  if (!(blob instanceof Blob) || blob.size === 0 || !Object.hasOwn(extensions, blob.type)) throw invalidVerification();
  return { blob, filename: `ho-so-xac-minh-${verificationId}.${extensions[blob.type]}` };
}

export async function getAdminProperties({ search = '', status = '', page = 1, perPage = 10 } = {}, options = {}) {
  const params = { page, per_page: perPage };
  const term = search.trim();
  if (term) params.search = term;
  if (status) params.status = status;

  return mapAdminPropertyPage(await transport.list(params, options));
}
