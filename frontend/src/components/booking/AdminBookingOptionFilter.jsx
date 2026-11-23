import { useEffect, useState } from 'react';
import { normalizeApiError } from '../../api/apiError.js';
import AdminPagination from '../admin/AdminPagination.jsx';
import { Button } from '../ui/button.jsx';
import { getAdminBookingOptions } from '../../services/adminBookingService.js';

const inputClass = 'h-9 w-full min-w-0 rounded-lg border border-input bg-background px-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
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
  const selectLabelId = `admin-booking-${type}-select-label`;
  const move = (page) => setQuery((previous) => ({ ...previous, page }));

  return <fieldset className="min-w-0 space-y-3 rounded-xl border border-border p-3 text-sm">
    <legend className="px-1 font-semibold">Lọc theo {label}</legend>
    <form className="space-y-2" onSubmit={(event) => {
      event.preventDefault();
      setQuery({ search: draft.trim(), page: 1 });
    }}>
      <label className="grid min-w-0 gap-1 font-medium">
        Tìm tên {label}
        <input className={inputClass} value={draft} onChange={(event) => setDraft(event.target.value)}
          aria-invalid={Boolean(searchError)} aria-describedby={searchError ? searchErrorId : undefined} />
        {searchError && <span id={searchErrorId}>{searchError}</span>}
      </label>
      <Button variant="outline" size="sm" type="submit">Tìm {label}</Button>
    </form>
    <label className="grid min-w-0 gap-1 font-medium">
      <span id={selectLabelId}>Chọn {label}</span>
      <select aria-labelledby={selectLabelId} className={inputClass} value={selected?.id ?? ''} onChange={(event) => {
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
          && <Button variant="outline" size="sm" type="button" onClick={() => move(query.page)}>Thử lại {label}</Button>}
      </div>}
      {result && <>
        {rows.length === 0 && <p role="status">Không có tùy chọn {label} phù hợp.</p>}
        <AdminPagination label={`Phân trang tùy chọn ${label}`} currentPage={result.meta.current_page}
          lastPage={result.meta.last_page} onPageChange={move} />
      </>}
    </div>
  </fieldset>;
}
