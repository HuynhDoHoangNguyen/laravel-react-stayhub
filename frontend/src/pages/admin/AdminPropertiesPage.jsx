import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { getAdminProperties, parseAdminPropertyError, propertySearchParams, readPropertySearch } from '../../services/adminPropertyService';
import { propertyStatuses, propertyTypes } from '../../mappers/adminPropertyMapper';
import AdminPropertyDetailDialog from '../../components/admin/properties/AdminPropertyDetailDialog';

const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const numberFormat = new Intl.NumberFormat('vi-VN');
const inputClass = 'min-w-0 rounded-lg border border-border bg-background px-3 py-2 text-foreground focus-visible:outline-2 focus-visible:outline-primary';
const buttonClass = 'cursor-pointer rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-default disabled:opacity-50';
const badgeClass = {
  ACTIVE: 'bg-emerald-100 text-emerald-900', INACTIVE: 'bg-slate-100 text-slate-800',
  BLOCKED: 'bg-rose-100 text-rose-900', PENDING: 'bg-amber-100 text-amber-900',
};

function ErrorNotice({ error, retry, reset }) {
  return <div role="alert" className="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
    <p>{error.message}</p>
    {Object.keys(error.fields ?? {}).length > 0 && <ul id="property-query-errors" className="mt-2 list-inside list-disc">
      {Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}
    </ul>}
    <div className="mt-3 flex flex-wrap gap-2">
      {retry && <button type="button" className={buttonClass} onClick={retry}>Thử lại</button>}
      <button type="button" className={buttonClass} onClick={reset}>Xóa bộ lọc</button>
    </div>
  </div>;
}

function PropertyList({ query, change, reset, user, captureProfileAuth, isProfileAuthCurrent }) {
  const [draft, setDraft] = useState({ q: query.q, host_id: String(query.host_id), type: query.type, status: query.status, per_page: query.per_page });
  const [formError, setFormError] = useState(null);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  const [selectedProperty, setSelectedProperty] = useState(null);
  useEffect(() => {
    const auth = captureProfileAuth();
    const controller = new AbortController();
    let disposed = false;
    if (isProfileAuthCurrent(auth)) {
      getAdminProperties(query, { signal: controller.signal }).then((result) => {
        if (!disposed && isProfileAuthCurrent(auth)) setView({ result, error: null, auth, revision });
      }, (failure) => {
        const error = parseAdminPropertyError(failure);
        if (!disposed && isProfileAuthCurrent(auth) && !error.isCancelled) setView({ result: null, error, auth, revision });
      });
    }
    return () => { disposed = true; controller.abort(); };
  }, [query, revision, user, captureProfileAuth, isProfileAuthCurrent]);

  const visible = view?.revision === revision && isProfileAuthCurrent(view.auth) ? view : null;
  const loading = visible === null;
  const result = visible?.result;
  const error = formError ?? visible?.error;
  const apply = (next) => {
    try {
      const params = propertySearchParams({ ...query, ...next });
      if (params.toString() === propertySearchParams(query).toString()) setRevision((previous) => previous + 1);
      change(params);
      setFormError(null);
    } catch (failure) {
      const error = parseAdminPropertyError(failure);
      setFormError(error);
      const field = Object.keys(error.fields ?? {})[0];
      const inputId = { q: 'property-search', host_id: 'property-host-id', type: 'property-type', status: 'property-status', per_page: 'property-page-size' }[field];
      if (inputId) document.getElementById(inputId)?.focus();
    }
  };
  const updateDraft = (field, value) => { setDraft((previous) => ({ ...previous, [field]: value })); setFormError(null); };
  const retry = () => { setFormError(null); setRevision((previous) => previous + 1); };
  const resetAll = () => {
    setDraft({ q: '', host_id: '', type: '', status: '', per_page: 10 });
    setFormError(null);
    reset(); retry();
  };

  return <>
    <form className="mb-6 grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" noValidate onSubmit={(event) => {
      event.preventDefault(); apply({ ...draft, page: 1 });
    }}>
      <label htmlFor="property-search" className="grid min-w-0 gap-2 text-sm font-medium sm:col-span-2">
        Tên cơ sở, địa chỉ, tên hoặc email Host
        <input id="property-search" type="search" className={inputClass} value={draft.q}
          placeholder="Nhập từ khóa tìm kiếm" onChange={(event) => updateDraft('q', event.target.value)}
          aria-invalid={Boolean(error?.fields?.q)} aria-describedby={error?.fields?.q ? 'property-query-errors' : undefined} />
      </label>
      <label htmlFor="property-host-id" className="grid min-w-0 gap-2 text-sm font-medium">
        Mã Host
        <input id="property-host-id" type="text" inputMode="numeric" className={inputClass} value={draft.host_id}
          placeholder="Tất cả Host" onChange={(event) => updateDraft('host_id', event.target.value)}
          aria-invalid={Boolean(error?.fields?.host_id)} aria-describedby={error?.fields?.host_id ? 'property-query-errors' : undefined} />
      </label>
      <label htmlFor="property-type" className="grid min-w-0 gap-2 text-sm font-medium">
        Loại cơ sở
        <select id="property-type" className={inputClass} value={draft.type}
          onChange={(event) => updateDraft('type', event.target.value)}
          aria-invalid={Boolean(error?.fields?.type)} aria-describedby={error?.fields?.type ? 'property-query-errors' : undefined}>
          <option value="">Tất cả loại cơ sở</option>
          {Object.entries(propertyTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </label>
      <label htmlFor="property-status" className="grid min-w-0 gap-2 text-sm font-medium">
        Trạng thái cơ sở
        <select id="property-status" className={inputClass} value={draft.status}
          onChange={(event) => updateDraft('status', event.target.value)}
          aria-invalid={Boolean(error?.fields?.status)} aria-describedby={error?.fields?.status ? 'property-query-errors' : undefined}>
          <option value="">Tất cả trạng thái</option>
          {Object.entries(propertyStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </label>
      <label htmlFor="property-page-size" className="grid min-w-0 gap-2 text-sm font-medium">
        Số dòng mỗi trang
        <select id="property-page-size" className={inputClass} value={draft.per_page}
          onChange={(event) => updateDraft('per_page', event.target.value)}
          aria-invalid={Boolean(error?.fields?.per_page)} aria-describedby={error?.fields?.per_page ? 'property-query-errors' : undefined}>
          {[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}
        </select>
      </label>
      <div className="flex flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-3">
        <button type="submit" className={`${buttonClass} bg-primary text-primary-foreground hover:bg-primary/90`}>Tìm kiếm</button>
        <button type="button" className={buttonClass} onClick={resetAll}>Xóa bộ lọc</button>
      </div>
    </form>
    <div aria-busy={loading} aria-live="polite">
      {loading && <p role="status" className="py-8 text-center text-muted-foreground">Đang tải danh sách cơ sở lưu trú…</p>}
      {error && <ErrorNotice error={error} retry={retry} reset={resetAll} />}
      {!error && result && <>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p role="status">Tìm thấy <strong>{numberFormat.format(result.meta.total)}</strong> cơ sở lưu trú.</p>
          <button type="button" className={buttonClass} onClick={retry}>Tải lại</button>
        </div>
        {result.data.length === 0 ? <div className="rounded-xl border border-dashed border-border p-8 text-center">
          <p className="font-medium">{result.meta.total > 0 ? 'Trang này không có cơ sở lưu trú.' : 'Không tìm thấy cơ sở lưu trú phù hợp.'}</p>
          <p className="mt-2 text-sm text-muted-foreground">Hãy thay đổi từ khóa, xóa bộ lọc hoặc quay về trang đầu.</p>
        </div> : <div className="overflow-x-auto rounded-xl border border-border" role="region" aria-label="Bảng danh sách cơ sở lưu trú" tabIndex={0}>
          <table className="w-full min-w-[800px] text-left text-sm">
            <caption className="sr-only">Danh sách cơ sở lưu trú, trang {result.meta.current_page}</caption>
            <thead className="bg-muted/60"><tr>
              {['Cơ sở lưu trú', 'Host', 'Loại', 'Số phòng', 'Trạng thái', 'Ngày tạo', 'Thao tác'].map((label) => <th key={label} scope="col" className="px-4 py-3 font-semibold">{label}</th>)}
            </tr></thead>
            <tbody className="divide-y divide-border">
              {result.data.map((property) => <tr key={property.id} className="hover:bg-muted/30">
                <td className="max-w-72 break-words px-4 py-4">
                  <p className="font-medium">{property.name}</p>
                  <p className="mt-1 text-xs text-muted-foreground">#{property.id} · {property.address}</p>
                </td>
                <td className="max-w-64 break-words px-4 py-4">
                  <p className="font-medium">{property.host.name}</p>
                  <p className="mt-1 break-all text-xs text-muted-foreground">#{property.host.id} · {property.host.email}</p>
                </td>
                <td className="whitespace-nowrap px-4 py-4">{propertyTypes[property.type]}</td>
                <td className="whitespace-nowrap px-4 py-4">{numberFormat.format(property.rooms_count)}</td>
                <td className="px-4 py-4"><span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${badgeClass[property.status]}`}>{propertyStatuses[property.status]}</span></td>
                <td className="whitespace-nowrap px-4 py-4">{property.created_at === null ? 'Chưa cập nhật' : dateFormat.format(new Date(property.created_at))}</td>
                <td className="px-4 py-4"><button type="button" className={`${buttonClass} whitespace-nowrap`}
                  aria-label={`Xem chi tiết cơ sở #${property.id}`}
                  onClick={(event) => setSelectedProperty({ id: property.id, trigger: event.currentTarget })}>Xem chi tiết</button></td>
              </tr>)}
            </tbody>
          </table>
        </div>}
        <nav aria-label="Phân trang cơ sở lưu trú" className="mt-5 flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-muted-foreground">Trang {result.meta.current_page} / {result.meta.last_page}</p>
          <div className="flex flex-wrap gap-2">
            <button type="button" className={buttonClass} disabled={query.page === 1} onClick={() => apply({ page: 1 })}>Trang đầu</button>
            <button type="button" className={buttonClass} disabled={query.page === 1} onClick={() => apply({ page: query.page - 1 })}>Trước</button>
            <button type="button" className={buttonClass} disabled={query.page >= result.meta.last_page} onClick={() => apply({ page: query.page + 1 })}>Sau</button>
          </div>
        </nav>
      </>}
    </div>
    {selectedProperty && <AdminPropertyDetailDialog key={selectedProperty.id} propertyId={selectedProperty.id}
      trigger={selectedProperty.trigger} onClose={() => setSelectedProperty(null)} onReloadList={retry} />}
  </>;
}

export default function AdminPropertiesPage() {
  const auth = useAuth();
  const [params, setParams] = useSearchParams();
  const search = params.toString();
  const parsed = useMemo(() => {
    try { return { query: readPropertySearch(search) }; }
    catch (failure) { return { error: parseAdminPropertyError(failure) }; }
  }, [search]);
  const reset = () => setParams({});
  return <section aria-labelledby="admin-properties-title" className="min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
    <header className="mb-6">
      <h1 id="admin-properties-title" tabIndex={-1} className="text-2xl font-bold tracking-tight">Giám sát cơ sở lưu trú</h1>
      <p className="mt-2 text-sm text-muted-foreground">Tra cứu cơ sở lưu trú của các Host trên toàn hệ thống.</p>
    </header>
    {parsed.error ? <ErrorNotice error={parsed.error} reset={reset} />
      : <PropertyList key={`${auth.user.id}:${search}`} query={parsed.query} change={setParams} reset={reset} {...auth} />}
  </section>;
}
