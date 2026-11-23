import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Car, ListChecks, Snowflake, Tv, Wifi } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { adminAmenityService, amenitySearchParams, parseAdminAmenityError, readAmenitySearch } from '../../services/adminAmenityService';
import AdminAmenityFormDialog from '../../components/admin/amenities/AdminAmenityFormDialog';

const button = 'min-h-10 rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-50';
const control = 'min-w-0 rounded-lg border border-border bg-background px-3 py-2 focus-visible:outline-2 focus-visible:outline-primary';
const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const numberFormat = new Intl.NumberFormat('vi-VN');
const icons = new Map([['wi-fi', Wifi], ['wifi', Wifi], ['điều-hòa', Snowflake], ['tv', Tv], ['bãi-đỗ-xe', Car]]);

function FilterError({ error, onRetry, onReset }) {
  return <div role="alert" id="amenity-filter-error" className="space-y-3 rounded-lg border border-destructive/30 p-4">
    <p>{error.message}</p>
    {Object.values(error.fields ?? {}).map((messages, index) => <p key={index} className="text-sm">{messages.join(' ')}</p>)}
    <div className="flex flex-wrap gap-2">
      {onRetry && <button type="button" className={button} onClick={onRetry}>Thử lại</button>}
      <button type="button" className={button} onClick={onReset}>Xóa bộ lọc</button>
    </div>
  </div>;
}

function AmenityList({ query, onNavigate, auth }) {
  const { user, captureProfileAuth, isProfileAuthCurrent } = auth;
  const [draft, setDraft] = useState(query);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  const [formError, setFormError] = useState(null);
  const [dialog, setDialog] = useState(null);
  const [notice, setNotice] = useState('');

  useEffect(() => {
    const snapshot = captureProfileAuth();
    if (!isProfileAuthCurrent(snapshot)) return;
    const controller = new AbortController();
    adminAmenityService.list(query, { signal: controller.signal }).then((result) => {
      if (!controller.signal.aborted && isProfileAuthCurrent(snapshot)) setView({ result, snapshot, revision });
    }, (failure) => {
      const error = parseAdminAmenityError(failure);
      if (!controller.signal.aborted && !error.isCancelled && isProfileAuthCurrent(snapshot)) setView({ error, snapshot, revision });
    });
    return () => controller.abort();
  }, [query, revision, user, captureProfileAuth, isProfileAuthCurrent]);

  const current = view?.revision === revision && isProfileAuthCurrent(view.snapshot) ? view : null;
  const result = current?.result;

  function reload() {
    setRevision((n) => n + 1);
  }

  function navigate(values) {
    try {
      const params = amenitySearchParams(values);
      setFormError(null);
      if (params.toString() === amenitySearchParams(query).toString()) reload();
      else onNavigate(params);
    } catch (failure) {
      setFormError(parseAdminAmenityError(failure));
    }
  }

  function reset() {
    setDraft({ q: '', status: '', per_page: 10 });
    navigate({});
  }

  function openDialog(trigger, itemId, desiredStatus) {
    setNotice('');
    setDialog({ trigger, itemId, desiredStatus });
  }

  function saved(row, showNotice = true) {
    setDialog(null);
    if (showNotice) {
      setNotice('Đã lưu tiện nghi “' + row.name + '”.');
    }
    reload();
  }

  return <>
    <div className="mb-5">
      <button type="button" className={button + ' bg-primary text-primary-foreground hover:bg-primary/90'}
        onClick={(event) => openDialog(event.currentTarget)}>Thêm tiện nghi</button>
    </div>
    <form noValidate className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_auto_auto_auto]"
      onSubmit={(event) => { event.preventDefault(); navigate({ ...draft, page: 1 }); }}>
      <label htmlFor="amenity-search" className="grid min-w-0 gap-1 text-sm font-medium">
        Tên tiện nghi
        <input id="amenity-search" type="search" className={control} value={draft.q} placeholder="Nhập từ khóa"
          onChange={(event) => setDraft({ ...draft, q: event.target.value })}
          aria-invalid={Boolean(formError?.fields?.q)} aria-describedby={formError?.fields?.q ? 'amenity-filter-error' : undefined} />
      </label>
      <label htmlFor="amenity-status-filter" className="grid gap-1 text-sm font-medium">
        Trạng thái
        <select id="amenity-status-filter" className={control} value={draft.status}
          onChange={(event) => setDraft({ ...draft, status: event.target.value })}>
          <option value="">Tất cả</option><option value="active">Đang hoạt động</option><option value="inactive">Ngưng sử dụng</option>
        </select>
      </label>
      <label htmlFor="amenity-page-size" className="grid gap-1 text-sm font-medium">
        Số dòng mỗi trang
        <select id="amenity-page-size" className={control} value={draft.per_page}
          onChange={(event) => setDraft({ ...draft, per_page: event.target.value })}>
          {[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}
        </select>
      </label>
      <div className="flex flex-wrap items-end gap-2">
        <button type="submit" className={button}>Tìm kiếm</button>
        <button type="button" className={button} onClick={reset}>Xóa bộ lọc</button>
      </div>
    </form>
    {notice && <p role="status" className="mb-4 rounded-lg border border-border bg-muted p-3 [overflow-wrap:anywhere]">{notice}</p>}
    <div aria-busy={!current}>
      {!current && <p role="status" className="py-3">Đang tải danh mục tiện nghi…</p>}
      {(formError || current?.error) && <FilterError error={formError ?? current.error} onReset={reset}
        onRetry={current?.error && ![401, 403, 422].includes(current.error.status) ? reload : undefined} />}
      {result && !formError && <>
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <p role="status">{numberFormat.format(result.meta.total)} tiện nghi</p>
          <button type="button" className={button} onClick={reload}>Tải lại</button>
        </div>
        {result.data.length === 0 ? <p className="rounded-xl border border-dashed border-border p-6 text-center">
          {result.meta.total ? 'Trang này không còn tiện nghi. Hãy về trang đầu.' : 'Không tìm thấy tiện nghi phù hợp.'}
        </p> : <div className="overflow-x-auto rounded-xl border border-border" role="region" tabIndex={0} aria-label="Bảng danh mục tiện nghi">
          <table className="w-full min-w-[690px] text-left text-sm">
            <caption className="sr-only">Danh mục tiện nghi dùng chung</caption>
            <thead className="bg-muted"><tr>
              {['Tiện nghi', 'Trạng thái', 'Phòng đang gán', 'Cập nhật', 'Thao tác'].map((label) =>
                <th key={label} scope="col" className="px-4 py-3">{label}</th>)}
            </tr></thead>
            <tbody className="divide-y divide-border">
              {result.data.map((row) => {
                const Icon = icons.get(row.icon?.toLocaleLowerCase('vi-VN')) ?? ListChecks;
                return <tr key={row.id}>
                  <th scope="row" className="max-w-64 px-4 py-3 font-medium">
                    <div className="flex items-center gap-2 [overflow-wrap:anywhere]"><Icon aria-hidden="true" className="h-4 w-4 shrink-0" />{row.name}</div>
                  </th>
                  <td className="px-4 py-3">{row.status ? 'Đang hoạt động' : 'Ngưng sử dụng'}</td>
                  <td className="px-4 py-3">{numberFormat.format(row.rooms_count)}</td>
                  <td className="px-4 py-3">{row.updated_at ? dateFormat.format(new Date(row.updated_at)) : 'Chưa cập nhật'}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-2">
                      <button type="button" className={button} aria-label={'Sửa tiện nghi #' + row.id}
                        onClick={(event) => openDialog(event.currentTarget, row.id)}>Sửa</button>
                      <button type="button" className={button}
                        aria-label={(row.status ? 'Ngưng sử dụng' : 'Kích hoạt lại') + ' tiện nghi #' + row.id}
                        onClick={(event) => openDialog(event.currentTarget, row.id, !row.status)}>
                        {row.status ? 'Ngưng sử dụng' : 'Kích hoạt lại'}
                      </button>
                    </div>
                  </td>
                </tr>;
              })}
            </tbody>
          </table>
        </div>}
        <nav className="mt-4 flex flex-wrap items-center justify-between gap-2" aria-label="Phân trang tiện nghi">
          <p>Trang {result.meta.current_page} / {result.meta.last_page}</p>
          <div className="flex flex-wrap gap-2">
            <button type="button" className={button} disabled={query.page === 1} onClick={() => navigate({ ...query, page: 1 })}>Trang đầu</button>
            <button type="button" className={button} disabled={query.page === 1} onClick={() => navigate({ ...query, page: query.page - 1 })}>Trước</button>
            <button type="button" className={button} disabled={query.page >= result.meta.last_page}
              onClick={() => navigate({ ...query, page: query.page + 1 })}>Sau</button>
          </div>
        </nav>
      </>}
    </div>
    {dialog && <AdminAmenityFormDialog {...dialog} onClose={() => setDialog(null)} onSaved={saved} />}
  </>;
}

export default function AdminAmenitiesPage() {
  const auth = useAuth();
  const [params, setParams] = useSearchParams();
  const search = params.toString();
  const parsed = useMemo(() => {
    try {
      return { query: readAmenitySearch(search) };
    } catch (failure) {
      return { error: parseAdminAmenityError(failure) };
    }
  }, [search]);

  return <section aria-labelledby="admin-amenities-title" className="min-w-0 rounded-2xl border border-border bg-card p-4 sm:p-6">
    <h1 id="admin-amenities-title" tabIndex={-1} className="mb-5 text-2xl font-bold">Danh mục tiện nghi</h1>
    {parsed.error ? <FilterError error={parsed.error} onReset={() => setParams({})} />
      : <AmenityList key={auth.user.id + ':' + search} auth={auth} query={parsed.query} onNavigate={setParams} />}
  </section>;
}
