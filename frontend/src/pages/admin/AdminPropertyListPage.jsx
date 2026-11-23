import { useEffect, useState } from 'react';
import { normalizeApiError } from '../../api/apiError.js';
import AdminPageHeader from '../../components/admin/AdminPageHeader.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import AdminPropertyVerificationDialog from '../../components/admin/AdminPropertyVerificationDialog.jsx';
import { Button } from '../../components/ui/button.jsx';
import { useAuth } from '../../hooks/useAuth';
import { propertyStatusLabels, propertyTypeLabels } from '../../mappers/adminPropertyMapper.js';
import { getAdminProperties } from '../../services/adminPropertyService.js';

const initialQuery = { search: '', status: '', page: 1 };
const panelClass = 'min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5';
const labelClass = 'grid min-w-0 content-start gap-1.5 text-sm font-medium';
const controlClass = 'h-10 w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const statusTone = {
  ACTIVE: 'bg-emerald-100 text-emerald-900',
  INACTIVE: 'bg-muted text-muted-foreground',
  BLOCKED: 'bg-rose-100 text-rose-900',
  PENDING: 'bg-amber-100 text-amber-900',
};

function PropertyCard({ property }) {
  return <article className={`${panelClass} [overflow-wrap:anywhere]`}>
    <header className="flex flex-wrap items-start justify-between gap-2">
      <h2 className="font-sans text-base font-semibold tracking-tight">{property.name}</h2>
      <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone[property.status]}`}>
        {propertyStatusLabels[property.status]}
      </span>
    </header>
    <p className="mt-1 text-sm text-muted-foreground">{property.address}</p>
    <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-border pt-3 text-sm">
      <div><dt className="text-muted-foreground">Loại cơ sở</dt><dd className="mt-0.5 font-medium">{propertyTypeLabels[property.type]}</dd></div>
      <div><dt className="text-muted-foreground">Chủ cơ sở</dt><dd className="mt-0.5 font-medium">{property.host_name ?? '—'}</dd></div>
    </dl>
    <div className="mt-4">
      <AdminPropertyVerificationDialog property={property} />
    </div>
  </article>;
}

function AdminPropertyList({ user, captureAuth, isAuthCurrent }) {
  const [draft, setDraft] = useState('');
  const [query, setQuery] = useState(initialQuery);
  const [view, setView] = useState(null);

  useEffect(() => {
    const controller = new AbortController();
    const auth = captureAuth();
    const current = () => !controller.signal.aborted && isAuthCurrent(auth);

    if (current()) {
      getAdminProperties(query, { signal: controller.signal }).then(
        (result) => { if (current()) setView({ query, auth, result }); },
        (failure) => {
          const error = normalizeApiError(failure);
          if (current() && !error.isCancelled) setView({ query, auth, error });
        },
      );
    }
    return () => controller.abort();
  }, [query, user, captureAuth, isAuthCurrent]);

  const sessionView = view && isAuthCurrent(view.auth) ? view : null;
  const visible = sessionView?.query === query ? sessionView : null;
  const result = visible?.result;
  const error = visible?.error;
  const pagination = !error && sessionView?.query.search === query.search && sessionView.query.status === query.status
    ? sessionView.result?.meta : null;
  const searchError = error?.fields.search?.join(' ');
  const statusError = error?.fields.status?.join(' ');
  const update = (patch) => setQuery((previous) => ({ ...previous, ...patch }));

  function applySearch(event) {
    event.preventDefault();
    update({ search: draft.trim(), page: 1 });
  }

  function reset() {
    setDraft('');
    setQuery({ ...initialQuery });
  }

  return <section className="mx-auto w-full max-w-7xl space-y-5" aria-labelledby="admin-properties-title">
    <AdminPageHeader id="admin-properties-title" title="Cơ sở lưu trú" description="Tra cứu cơ sở lưu trú trên toàn hệ thống." />
    <form className={`${panelClass} grid gap-3 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_auto]`} onSubmit={applySearch}>
      <label className={labelClass}>
        Tìm theo tên hoặc địa chỉ
        <input className={controlClass} type="search" maxLength={255} value={draft}
          onChange={(event) => setDraft(event.target.value)}
          aria-invalid={Boolean(searchError)} aria-describedby={searchError ? 'property-search-error' : undefined} />
        {searchError && <span id="property-search-error">{searchError}</span>}
      </label>
      <label className={labelClass}>
        Trạng thái
        <select className={controlClass} value={query.status} onChange={(event) => update({ status: event.target.value, page: 1 })}
          aria-invalid={Boolean(statusError)} aria-describedby={statusError ? 'property-status-error' : undefined}>
          <option value="">Tất cả</option>
          {Object.entries(propertyStatusLabels).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
        </select>
        {statusError && <span id="property-status-error">{statusError}</span>}
      </label>
      <div className="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-1 xl:pt-6">
        <Button className="h-10" type="submit">Tìm kiếm</Button>
        <Button className="h-10" type="button" variant="outline" onClick={reset}>Đặt lại</Button>
      </div>
    </form>
    <div aria-busy={!visible} className="space-y-3">
      {!visible && <p role="status" className={`${panelClass} text-sm text-muted-foreground`}>Đang tải danh sách cơ sở lưu trú…</p>}
      {error && <div role="alert" className={`${panelClass} space-y-2`}>
        <p>{error.message}</p>
        {Object.entries(error.fields).filter(([key]) => key !== 'search' && key !== 'status')
          .map(([key, messages]) => <p key={key}>{messages.join(' ')}</p>)}
        {![401, 403, 422].includes(error.status) &&
          <Button type="button" variant="outline" onClick={() => update({})}>Thử lại</Button>}
      </div>}
      {result && <>
        <p role="status" className={result.data.length ? 'text-sm text-muted-foreground' : `${panelClass} text-sm text-muted-foreground`}>
          {result.data.length ? `Có ${result.meta.total} cơ sở lưu trú phù hợp.` : 'Không có cơ sở lưu trú phù hợp trên trang này.'}
        </p>
        {result.data.length > 0 && <div className="grid items-start gap-4 lg:grid-cols-2">
          {result.data.map((property) => <PropertyCard key={property.id} property={property} />)}
        </div>}
      </>}
    </div>
    {pagination && <AdminPagination label="Phân trang cơ sở lưu trú" currentPage={pagination.current_page}
      lastPage={pagination.last_page} onPageChange={(page) => update({ page })} />}
  </section>;
}

export default function AdminPropertyListPage() {
  const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  if (!user || isLoggingOut) return null;

  return <AdminPropertyList key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
