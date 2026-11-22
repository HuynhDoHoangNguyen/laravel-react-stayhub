import { useEffect, useState } from 'react';
import { normalizeApiError } from '../../api/apiError.js';
import { getAdminBookingOptions } from '../../services/adminBookingService.js';

const inputClass = 'min-w-0 rounded-md border border-border bg-background px-3 py-2';
const selectLabel = (option) => `${option.name} — #${option.id}`;

export default function AdminBookingOptionFilter({
  type, hostId = null, selected, onSelect, user, captureAuth, isAuthCurrent,
}) {
  const label = { hosts: 'Host', properties: 'cơ sở', customers: 'khách đặt phòng' }[type];
  const [draft, setDraft] = useState('');
  const [query, setQuery] = useState({ search: '', page: 1 });
  const [view, setView] = useState(null);

  useEffect(() => {
    const controller = new AbortController();
    const auth = captureAuth();
    const current = () => !controller.signal.aborted && isAuthCurrent(auth);
    if (current()) {
      getAdminBookingOptions({ ...query, type, hostId }, { signal: controller.signal }).then(
        (result) => { if (current()) setView({ query, type, hostId, auth, result }); },
        (failure) => {
          const error = normalizeApiError(failure);
          if (current() && !error.isCancelled) setView({ query, type, hostId, auth, error });
        },
      );
    }
    return () => controller.abort();
  }, [query, type, hostId, user, captureAuth, isAuthCurrent]);

  const visible = view?.query === query && view.type === type && view.hostId === hostId && isAuthCurrent(view.auth) ? view : null;
  const result = visible?.result;
  const error = visible?.error;
  const rows = result?.data ?? [];
  const options = selected && !rows.some(({ id }) => id === selected.id) ? [selected, ...rows] : rows;
  const searchError = error?.fields.search?.join(' ');
  const searchErrorId = `admin-booking-${type}-search-error`;
  const move = (page) => setQuery((previous) => ({ ...previous, page }));

  return <fieldset className="min-w-0 space-y-2 rounded-xl border border-border p-3">
    <legend className="px-1 font-medium">Lọc theo {label}</legend>
    <form className="flex flex-wrap items-start gap-2" onSubmit={(event) => {
      event.preventDefault();
      setQuery({ search: draft.trim(), page: 1 });
    }}>
      <label className="grid min-w-0 flex-1 gap-1">
        Tìm tên {label}
        <input className={inputClass} value={draft} onChange={(event) => setDraft(event.target.value)}
          aria-invalid={Boolean(searchError)} aria-describedby={searchError ? searchErrorId : undefined} />
        {searchError && <span id={searchErrorId}>{searchError}</span>}
      </label>
      <button className={`${inputClass} mt-6`} type="submit">Tìm {label}</button>
    </form>
    <label className="grid gap-1">
      Chọn {label}
      <select className={inputClass} value={selected?.id ?? ''} onChange={(event) => {
        const value = event.target.value;
        if (value === '') onSelect(null);
        else {
          const option = options.find(({ id }) => String(id) === value);
          if (option) onSelect(option);
        }
      }}>
        <option value="">Tất cả {label}</option>
        {options.map((option) => <option key={option.id} value={option.id}>{selectLabel(option)}</option>)}
      </select>
    </label>
    <div aria-busy={!visible}>
      {!visible && <p role="status">Đang tải tùy chọn {label}…</p>}
      {error && <div role="alert">
        <p>{error.message}</p>
        {Object.entries(error.fields).filter(([field]) => field !== 'search')
          .map(([field, messages]) => <p key={field}>{messages.join(' ')}</p>)}
        {![401, 403, 422].includes(error.status)
          && <button className={inputClass} type="button" onClick={() => move(query.page)}>Thử lại {label}</button>}
      </div>}
      {result && <>
        {rows.length === 0 && <p role="status">Không có tùy chọn {label} phù hợp.</p>}
        <nav className="flex flex-wrap items-center gap-2" aria-label={`Phân trang tùy chọn ${label}`}>
          <button className={inputClass} type="button" disabled={query.page === 1} onClick={() => move(1)}>Trang đầu</button>
          <button className={inputClass} type="button" disabled={query.page === 1} onClick={() => move(query.page - 1)}>Trang trước</button>
          <span>Trang {result.meta.current_page} / {result.meta.last_page}</span>
          <button className={inputClass} type="button" disabled={query.page >= result.meta.last_page}
            onClick={() => move(query.page + 1)}>Trang sau</button>
        </nav>
      </>}
    </div>
  </fieldset>;
}
