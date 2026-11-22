export function mapAccountStatus(body, id, payload) {
  const row = body?.data;
  const fail = () => { throw new Error('Phản hồi thay đổi trạng thái tài khoản không hợp lệ. Vui lòng kiểm tra lại thông tin.'); };
  if (!body || typeof body !== 'object' || Array.isArray(body) || !row || typeof row !== 'object' || Array.isArray(row)
    || !Number.isSafeInteger(row.id) || row.id < 1 || row.id !== id || row.status !== payload.status
    || typeof row.changed !== 'boolean' || row.changed !== (payload.status !== payload.expected_status)) fail();
  if (row.updated_at === null) {
    if (row.changed) fail();
  } else {
    if (typeof row.updated_at !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(row.updated_at)) fail();
    const date = new Date(row.updated_at);
    if (!Number.isFinite(date.getTime()) || date.toISOString().slice(0, 19) !== row.updated_at.slice(0, 19)) fail();
  }
  return { id: row.id, status: row.status, updated_at: row.updated_at, changed: row.changed };
}
