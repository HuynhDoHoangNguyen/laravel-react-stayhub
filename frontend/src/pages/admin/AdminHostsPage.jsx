import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { hostSearchParams, getAdminHosts, parseAdminHostError, readHostSearch } from '../../services/adminHostService';
import { hostStatuses } from '../../mappers/adminHostMapper';

const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const inputClass = 'min-w-0 rounded-lg border border-border bg-background px-3 py-2 text-foreground focus-visible:outline-2 focus-visible:outline-primary';
const buttonClass = 'cursor-pointer rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-default disabled:opacity-50';
const badgeClass = { ACTIVE: 'bg-emerald-100 text-emerald-900', INACTIVE: 'bg-slate-100 text-slate-800', BLOCKED: 'bg-rose-100 text-rose-900' };

function ErrorNotice({ error, retry, reset }) {
  return <div role="alert" className="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
    <p>{error.message}</p>
    {Object.keys(error.fields ?? {}).length > 0 && <ul id="host-query-errors" className="mt-2 list-inside list-disc">
      {Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}
    </ul>}
    <div className="mt-3 flex flex-wrap gap-2">
      {retry && <button type="button" className={buttonClass} onClick={retry}>Thử lại</button>}
      <button type="button" className={buttonClass} onClick={reset}>Xóa bộ lọc</button>
    </div>
  </div>;
}

function HostList({ query, change, reset, user, captureProfileAuth, isProfileAuthCurrent }) {
  const [draft, setDraft] = useState(query.q);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  const [formError, setFormError] = useState(null);
  useEffect(() => {
    const auth = captureProfileAuth();
    const controller = new AbortController();
    let disposed = false;
    if (isProfileAuthCurrent(auth)) {
      getAdminHosts(query, { signal: controller.signal }).then((result) => {
        if (!disposed && isProfileAuthCurrent(auth)) setView({ result, error: null, auth, revision });
      }, (failure) => {
        const error = parseAdminHostError(failure);
        if (!disposed && isProfileAuthCurrent(auth) && !error.isCancelled) setView({ result: null, error, auth, revision });
      });
    }
    return () => { disposed = true; controller.abort(); };
  }, [query, revision, user, captureProfileAuth, isProfileAuthCurrent]);

  const visible = view?.revision === revision && isProfileAuthCurrent(view.auth) ? view : null;
  const loading = visible === null;
  const result = visible?.result;
  const error = formError ?? visible?.error;
  function apply(next) {
    try {
      change(hostSearchParams({ ...query, ...next }));
      setFormError(null);
    } catch (failure) { setFormError(parseAdminHostError(failure)); }
  }
  function retry() { setFormError(null); setRevision((previous) => previous + 1); }
  function resetAll() { setDraft(''); setFormError(null); setRevision((previous) => previous + 1); reset(); }
  return <>
    <form className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_auto_auto_auto]" onSubmit={(event) => {
      event.preventDefault(); apply({ q: draft, page: 1 });
    }}>
      <label htmlFor="host-search" className="grid gap-2 text-sm font-medium">
        Tên, email hoặc số điện thoại
        <input id="host-search" type="search" className={inputClass} value={draft} placeholder="Nhập từ khóa tìm kiếm"
          onChange={(event) => { setDraft(event.target.value); setFormError(null); }}
          aria-invalid={Boolean(error?.fields?.q)} aria-describedby={error?.fields?.q ? 'host-query-errors' : undefined} />
      </label>
      <label htmlFor="host-status" className="grid gap-2 text-sm font-medium">
        Trạng thái tài khoản
        <select id="host-status" className={inputClass} value={query.status} onChange={(event) => apply({ status: event.target.value, page: 1 })}>
          <option value="">Tất cả trạng thái</option>
          {Object.entries(hostStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </label>
      <label htmlFor="host-page-size" className="grid gap-2 text-sm font-medium">
        Số dòng mỗi trang
        <select id="host-page-size" className={inputClass} value={query.per_page} onChange={(event) => apply({ per_page: event.target.value, page: 1 })}>
          {[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}
        </select>
      </label>
      <div className="flex flex-wrap items-end gap-2">
        <button type="submit" className={`${buttonClass} bg-primary text-primary-foreground hover:bg-primary/90`}>Tìm kiếm</button>
        <button type="button" className={buttonClass} onClick={resetAll}>Xóa bộ lọc</button>
      </div>
    </form>
    <div aria-busy={loading} aria-live="polite">
      {loading && <p role="status" className="py-8 text-center text-muted-foreground">Đang tải danh sách Host…</p>}
      {error && <ErrorNotice error={error} retry={retry} reset={resetAll} />}
      {!error && result && <>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p role="status">Tìm thấy <strong>{new Intl.NumberFormat('vi-VN').format(result.meta.total)}</strong> Host.</p>
          <button type="button" className={buttonClass} onClick={retry}>Tải lại</button>
        </div>
        {result.data.length === 0 ? <div className="rounded-xl border border-dashed border-border p-8 text-center">
          <p className="font-medium">{result.meta.total > 0 ? 'Trang này không có Host.' : 'Không tìm thấy Host phù hợp.'}</p>
          <p className="mt-2 text-sm text-muted-foreground">Hãy thay đổi từ khóa, xóa bộ lọc hoặc quay về trang đầu.</p>
        </div> : <div className="overflow-x-auto rounded-xl border border-border" role="region" aria-label="Bảng danh sách Host" tabIndex={0}>
          <table className="w-full min-w-[760px] text-left text-sm">
            <caption className="sr-only">Danh sách Host, trang {result.meta.current_page}</caption>
            <thead className="bg-muted/60"><tr>
              {['Mã Host', 'Họ tên', 'Email', 'Điện thoại', 'Số cơ sở', 'Trạng thái', 'Ngày tạo'].map((label) => <th key={label} scope="col" className="px-4 py-3 font-semibold">{label}</th>)}
            </tr></thead>
            <tbody className="divide-y divide-border">
              {result.data.map((host) => <tr key={host.id} className="hover:bg-muted/30">
                <td className="whitespace-nowrap px-4 py-4 text-muted-foreground">#{host.id}</td>
                <td className="max-w-60 break-words px-4 py-4 font-medium">{host.name}</td>
                <td className="max-w-64 break-all px-4 py-4">{host.email}</td>
                <td className="whitespace-nowrap px-4 py-4">{host.phone || 'Chưa cập nhật'}</td>
                <td className="whitespace-nowrap px-4 py-4">{new Intl.NumberFormat('vi-VN').format(host.properties_count)}</td>
                <td className="px-4 py-4"><span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${badgeClass[host.status]}`}>{hostStatuses[host.status]}</span></td>
                <td className="whitespace-nowrap px-4 py-4">{host.created_at === null ? 'Chưa cập nhật' : dateFormat.format(new Date(host.created_at))}</td>
              </tr>)}
            </tbody>
          </table>
        </div>}
        <nav aria-label="Phân trang Host" className="mt-5 flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-muted-foreground">Trang {result.meta.current_page} / {result.meta.last_page}</p>
          <div className="flex flex-wrap gap-2">
            <button type="button" className={buttonClass} disabled={query.page === 1} onClick={() => apply({ page: 1 })}>Trang đầu</button>
            <button type="button" className={buttonClass} disabled={query.page === 1} onClick={() => apply({ page: query.page - 1 })}>Trước</button>
            <button type="button" className={buttonClass} disabled={query.page >= result.meta.last_page} onClick={() => apply({ page: query.page + 1 })}>Sau</button>
          </div>
        </nav>
      </>}
    </div>
  </>;
}

export default function AdminHostsPage() {
  const auth = useAuth();
  const [params, setParams] = useSearchParams();
  const search = params.toString();
  const parsed = useMemo(() => {
    try { return { query: readHostSearch(search) }; }
    catch (failure) { return { error: parseAdminHostError(failure) }; }
  }, [search]);
  const reset = () => setParams({});
  return <section aria-labelledby="admin-hosts-title" className="min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
    <header className="mb-6">
      <h1 id="admin-hosts-title" tabIndex={-1} className="text-2xl font-bold tracking-tight">Quản lý Host</h1>
      <p className="mt-2 text-sm text-muted-foreground">Tra cứu tài khoản Host trên toàn hệ thống.</p>
    </header>
    {parsed.error ? <ErrorNotice error={parsed.error} reset={reset} />
      : <HostList key={`${auth.user.id}:${search}`} query={parsed.query} change={setParams} reset={reset} {...auth} />}
  </section>;
}
