import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { customerSearchParams, getAdminCustomers, parseAdminUserError, readCustomerSearch } from '../../services/adminUserService';
import { customerStatuses } from '../../mappers/adminUserMapper';
import AdminCustomerDetailDialog from '../../components/admin/users/AdminCustomerDetailDialog';

const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const inputClass = 'min-w-0 rounded-lg border border-border bg-background px-3 py-2 text-foreground focus-visible:outline-2 focus-visible:outline-primary';
const buttonClass = 'cursor-pointer rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-default disabled:opacity-50';
const badgeClass = { ACTIVE: 'bg-emerald-100 text-emerald-900', INACTIVE: 'bg-slate-100 text-slate-800', BLOCKED: 'bg-rose-100 text-rose-900' };

function ErrorNotice({ error, retry, reset }) {
  return <div role="alert" className="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
    <p>{error.message}</p>
    {Object.keys(error.fields ?? {}).length > 0 && <ul id="customer-query-errors" className="mt-2 list-inside list-disc">
      {Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}
    </ul>}
    <div className="mt-3 flex flex-wrap gap-2">
      {retry && <button type="button" className={buttonClass} onClick={retry}>Thử lại</button>}
      <button type="button" className={buttonClass} onClick={reset}>Xóa bộ lọc</button>
    </div>
  </div>;
}

function CustomerList({ query, change, reset, user, captureProfileAuth, isProfileAuthCurrent }) {
  const [draft, setDraft] = useState(query.q);
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  const [formError, setFormError] = useState(null);
  const [selectedCustomer, setSelectedCustomer] = useState(null);
  useEffect(() => {
    const auth = captureProfileAuth();
    const controller = new AbortController();
    let disposed = false;
    if (isProfileAuthCurrent(auth)) {
      getAdminCustomers(query, { signal: controller.signal }).then((result) => {
        if (!disposed && isProfileAuthCurrent(auth)) setView({ result, error: null, auth, revision });
      }, (failure) => {
        const error = parseAdminUserError(failure);
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
      change(customerSearchParams({ ...query, ...next }));
      setFormError(null);
    } catch (failure) { setFormError(parseAdminUserError(failure)); }
  }
  function retry() { setFormError(null); setRevision((previous) => previous + 1); }
  function resetAll() { setDraft(''); setFormError(null); setRevision((previous) => previous + 1); reset(); }
  return <>
    <form className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_auto_auto_auto]" onSubmit={(event) => {
      event.preventDefault(); apply({ q: draft, page: 1 });
    }}>
      <label htmlFor="customer-search" className="grid gap-2 text-sm font-medium">
        Tên, email hoặc số điện thoại
        <input id="customer-search" type="search" className={inputClass} value={draft} placeholder="Nhập từ khóa tìm kiếm"
          onChange={(event) => { setDraft(event.target.value); setFormError(null); }}
          aria-invalid={Boolean(error?.fields?.q)} aria-describedby={error?.fields?.q ? 'customer-query-errors' : undefined} />
      </label>
      <label htmlFor="customer-status" className="grid gap-2 text-sm font-medium">
        Trạng thái tài khoản
        <select id="customer-status" className={inputClass} value={query.status} onChange={(event) => apply({ status: event.target.value, page: 1 })}>
          <option value="">Tất cả trạng thái</option>
          {Object.entries(customerStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </label>
      <label htmlFor="customer-page-size" className="grid gap-2 text-sm font-medium">
        Số dòng mỗi trang
        <select id="customer-page-size" className={inputClass} value={query.per_page} onChange={(event) => apply({ per_page: event.target.value, page: 1 })}>
          {[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}
        </select>
      </label>
      <div className="flex flex-wrap items-end gap-2">
        <button type="submit" className={`${buttonClass} bg-primary text-primary-foreground hover:bg-primary/90`}>Tìm kiếm</button>
        <button type="button" className={buttonClass} onClick={resetAll}>Xóa bộ lọc</button>
      </div>
    </form>
    <div aria-busy={loading} aria-live="polite">
      {loading && <p role="status" className="py-8 text-center text-muted-foreground">Đang tải danh sách khách hàng…</p>}
      {error && <ErrorNotice error={error} retry={retry} reset={resetAll} />}
      {!error && result && <>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p role="status">Tìm thấy <strong>{new Intl.NumberFormat('vi-VN').format(result.meta.total)}</strong> khách hàng.</p>
          <button type="button" className={buttonClass} onClick={retry}>Tải lại</button>
        </div>
        {result.data.length === 0 ? <div className="rounded-xl border border-dashed border-border p-8 text-center">
          <p className="font-medium">{result.meta.total > 0 ? 'Trang này không có khách hàng.' : 'Không tìm thấy khách hàng phù hợp.'}</p>
          <p className="mt-2 text-sm text-muted-foreground">Hãy thay đổi từ khóa, xóa bộ lọc hoặc quay về trang đầu.</p>
        </div> : <div className="overflow-x-auto rounded-xl border border-border" role="region" aria-label="Bảng danh sách khách hàng" tabIndex={0}>
          <table className="w-full min-w-[760px] text-left text-sm">
            <caption className="sr-only">Danh sách khách hàng, trang {result.meta.current_page}</caption>
            <thead className="bg-muted/60"><tr>
              {['Mã khách hàng', 'Họ tên', 'Email', 'Điện thoại', 'Trạng thái', 'Ngày tạo', 'Thao tác'].map((label) => <th key={label} scope="col" className="px-4 py-3 font-semibold">{label}</th>)}
            </tr></thead>
            <tbody className="divide-y divide-border">
              {result.data.map((customer) => <tr key={customer.id} className="hover:bg-muted/30">
                <td className="whitespace-nowrap px-4 py-4 text-muted-foreground">#{customer.id}</td>
                <td className="max-w-60 break-words px-4 py-4 font-medium">{customer.name}</td>
                <td className="max-w-64 break-all px-4 py-4">{customer.email}</td>
                <td className="whitespace-nowrap px-4 py-4">{customer.phone || 'Chưa cập nhật'}</td>
                <td className="px-4 py-4"><span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${badgeClass[customer.status]}`}>{customerStatuses[customer.status]}</span></td>
                <td className="whitespace-nowrap px-4 py-4">{customer.created_at === null ? 'Chưa cập nhật' : dateFormat.format(new Date(customer.created_at))}</td>
                <td className="whitespace-nowrap px-4 py-4"><button type="button" className={buttonClass}
                  aria-label={`Xem chi tiết khách hàng #${customer.id}`} onClick={(event) => setSelectedCustomer({ id: customer.id, trigger: event.currentTarget })}>Xem chi tiết</button></td>
              </tr>)}
            </tbody>
          </table>
        </div>}
        <nav aria-label="Phân trang khách hàng" className="mt-5 flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-muted-foreground">Trang {result.meta.current_page} / {result.meta.last_page}</p>
          <div className="flex flex-wrap gap-2">
            <button type="button" className={buttonClass} disabled={query.page === 1} onClick={() => apply({ page: 1 })}>Trang đầu</button>
            <button type="button" className={buttonClass} disabled={query.page === 1} onClick={() => apply({ page: query.page - 1 })}>Trước</button>
            <button type="button" className={buttonClass} disabled={query.page >= result.meta.last_page} onClick={() => apply({ page: query.page + 1 })}>Sau</button>
          </div>
        </nav>
      </>}
    </div>
    {selectedCustomer && <AdminCustomerDetailDialog key={selectedCustomer.id} customerId={selectedCustomer.id}
      trigger={selectedCustomer.trigger} onClose={() => setSelectedCustomer(null)} onReloadList={retry} />}
  </>;
}

export default function AdminCustomersPage() {
  const auth = useAuth();
  const [params, setParams] = useSearchParams();
  const search = params.toString();
  const parsed = useMemo(() => {
    try { return { query: readCustomerSearch(search) }; }
    catch (failure) { return { error: parseAdminUserError(failure) }; }
  }, [search]);
  const reset = () => setParams({});
  return <section aria-labelledby="admin-customers-title" className="min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
    <header className="mb-6">
      <h1 id="admin-customers-title" tabIndex={-1} className="text-2xl font-bold tracking-tight">Quản lý khách hàng</h1>
      <p className="mt-2 text-sm text-muted-foreground">Tra cứu tài khoản khách hàng trên toàn hệ thống.</p>
    </header>
    {parsed.error ? <ErrorNotice error={parsed.error} reset={reset} />
      : <CustomerList key={`${auth.user.id}:${search}`} query={parsed.query} change={setParams} reset={reset} {...auth} />}
  </section>;
}
