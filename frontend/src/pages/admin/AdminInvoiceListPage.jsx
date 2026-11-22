import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown, RefreshCw, SlidersHorizontal } from 'lucide-react';
import AdminPageHeader from '../../components/admin/AdminPageHeader.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import { Button } from '../../components/ui/button.jsx';
import { getAdminInvoices, getAdminInvoiceHostOptions, getAdminInvoicePropertyOptions, formatVnd, parseInvoiceError } from '../../services/invoiceService';
import { useAuth } from '../../hooks/useAuth';

const statuses = { UNPAID: 'Chưa thanh toán', PAID: 'Đã thanh toán', REFUNDED: 'Đã hoàn tiền' };
const statusTone = { UNPAID: 'bg-amber-100 text-amber-900', PAID: 'bg-emerald-100 text-emerald-900', REFUNDED: 'bg-muted text-muted-foreground' };
const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const displayDate = (value) => value === null ? '—' : dateFormat.format(new Date(value));
const initialQuery = { page: 1, perPage: 10, status: 'ALL', search: '', hostId: null, propertyId: null };
const controlClass = 'h-10 w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const labelClass = 'grid min-w-0 content-start gap-1.5 text-sm font-medium';

function InvoiceOptionFilter({ kind, hostId = null, user, captureAuth, isAuthCurrent, selected, onSelect }) {
    const propertyMode = kind === 'properties';
    const label = propertyMode ? 'cơ sở' : 'Host';
    const prefix = propertyMode ? 'invoice-property' : 'invoice-host';
    const [draft, setDraft] = useState('');
    const [query, setQuery] = useState({ page: 1, perPage: 10, search: '' });
    const [revision, setRevision] = useState(0);
    const [view, setView] = useState(null);
    const gate = useRef({ serial: 0, controller: null });

    useEffect(() => {
        const scope = gate.current;
        const serial = ++scope.serial;
        const controller = new AbortController();
        const auth = captureAuth();
        scope.controller = controller;
        let disposed = false;
        const current = () => !disposed && scope.serial === serial && isAuthCurrent(auth);
        if (isAuthCurrent(auth)) {
            const request = kind === 'properties' ? getAdminInvoicePropertyOptions({ ...query, hostId }, { signal: controller.signal })
                : getAdminInvoiceHostOptions(query, { signal: controller.signal });
            request.then((result) => {
                if (current()) setView({ query, revision, auth, result, error: null });
            }, (error) => {
                if (!current()) return;
                const parsed = parseInvoiceError(error);
                if (!parsed.isCancelled) setView({ query, revision, auth, result: null, error: parsed });
            });
        }
        return () => { disposed = true; ++scope.serial; controller.abort(); };
    }, [query, revision, user, captureAuth, isAuthCurrent, kind, hostId]);

    const visible = view?.query === query && view?.revision === revision && isAuthCurrent(view.auth) ? view : null;
    const loading = visible === null;
    const result = visible?.result;
    const error = visible?.error;
    function change(next) {
        ++gate.current.serial;
        gate.current.controller?.abort();
        setQuery((previous) => ({ ...previous, ...next }));
    }
    function retry() {
        ++gate.current.serial;
        gate.current.controller?.abort();
        setRevision((previous) => previous + 1);
    }
    // Keep the selected option available even when it is outside the current search/page.
    const rows = result?.data ?? [];
    const options = selected && !rows.some(({ id }) => id === selected.id) ? [selected, ...rows] : rows;
    return <fieldset className="min-w-0 space-y-3 rounded-xl border border-border p-3 text-sm">
        <legend className="px-1 font-semibold">Lọc theo {label}</legend>
        <form className="space-y-2" onSubmit={(event) => { event.preventDefault(); change({ search: draft.trim(), page: 1 }); }}>
            <label htmlFor={`${prefix}-search`} className={labelClass}>
                Tìm tên {label}
                <input id={`${prefix}-search`} className={controlClass} value={draft} maxLength={255}
                    onChange={(event) => setDraft(event.target.value)} aria-invalid={Boolean(error?.fields.search)}
                    aria-describedby={error?.fields.search ? `${prefix}-errors` : undefined} />
            </label>
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" type="submit">Tìm {label}</Button>
                <Button variant="outline" size="sm" type="button" disabled={loading} onClick={retry}>Tải lại {label}</Button>
            </div>
        </form>
        <div className={labelClass}>
            <label htmlFor={`${prefix}-select`}>Chọn {label}</label>
            <select id={`${prefix}-select`} className={controlClass} value={selected?.id ?? ''}
                onChange={(event) => onSelect(event.target.value === '' ? null : options.find(({ id }) => id === Number(event.target.value)))}>
                <option value="">Tất cả {label}</option>
                {options.map(({ id, name }) => <option key={id} value={id}>{name} — #{id}</option>)}
            </select>
        </div>
        {selected && <Button variant="ghost" size="sm" type="button" onClick={() => onSelect(null)}>Bỏ lọc {label}</Button>}
        <div className="space-y-2 text-muted-foreground" aria-live="polite" aria-busy={loading}>
            {loading && <p role="status">Đang tải danh sách {label}…</p>}
            {error && <div role="alert"><p>{error.message}</p>
                <ul id={`${prefix}-errors`}>{Object.entries(error.fields).map(([key, messages]) => <li key={key}>{messages.join(' ')}</li>)}</ul>
                <Button variant="outline" size="sm" type="button" onClick={retry}>Thử tải lại {label}</Button></div>}
            {result && <>
                <p role="status">{result.data.length === 0 ? `Không có ${label} phù hợp.` : `Có ${result.meta.total} ${label} phù hợp.`}</p>
                <AdminPagination label={`Phân trang danh sách ${label}`} currentPage={result.meta.current_page}
                    lastPage={result.meta.last_page} onPageChange={(page) => change({ page })} />
            </>}
        </div>
    </fieldset>;
}

function InvoiceRow({ invoice }) {
    const [expanded, setExpanded] = useState(false);
    const detailsId = `admin-invoice-details-${invoice.id}`;
    const facts = [
        ['Phòng', invoice.room_name],
        invoice.booking_code
            ? ['Mã đơn đặt phòng', <Link className="rounded text-primary underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-ring"
                to={{ pathname: '/admin/bookings', search: new URLSearchParams({ search: invoice.booking_code }).toString() }}>
                {invoice.booking_code}
            </Link>]
            : ['ID đơn đặt phòng', `#${invoice.booking_id}`],
        ['Ngày lập', displayDate(invoice.created_at)],
        ['Ngày thanh toán', displayDate(invoice.paid_at)],
    ];

    return <tbody className="block min-w-0 rounded-2xl border border-border bg-card [overflow-wrap:anywhere] lg:table-row-group lg:rounded-none lg:border-0 lg:border-t" role="rowgroup">
        <tr className="grid grid-cols-2 items-start gap-x-3 gap-y-2 p-4 lg:table-row" role="row">
            <th scope="row" className="col-span-2 text-left font-semibold lg:px-4 lg:py-4" role="rowheader">
                {invoice.invoice_code}
            </th>
            <td className="col-span-2 lg:px-4 lg:py-4" role="cell">
                <p className="font-medium">{invoice.customer_name}</p>
                <p className="text-muted-foreground">{invoice.property_name}</p>
            </td>
            <td className="self-center font-semibold tabular-nums lg:px-4 lg:py-4" role="cell">{formatVnd(invoice.total_amount)}</td>
            <td className="text-right lg:px-4 lg:py-4 lg:text-left" role="cell">
                <span className={`inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone[invoice.payment_status]}`}>
                    {statuses[invoice.payment_status]}
                </span>
            </td>
            <td className="col-span-2 lg:px-4 lg:py-4" role="cell">
                <Button variant="ghost" size="sm" type="button" className="-ml-2 px-2 lg:ml-0"
                    aria-label={`Thông tin hóa đơn ${invoice.invoice_code}`} aria-expanded={expanded} aria-controls={detailsId}
                    onClick={() => setExpanded((open) => !open)}>
                    Thông tin hóa đơn
                    <ChevronDown aria-hidden="true" className={expanded ? 'rotate-180' : ''} />
                </Button>
            </td>
        </tr>
        <tr id={detailsId} hidden={!expanded} className={expanded ? 'block lg:table-row' : 'hidden'} role="row">
            <td colSpan={5} className="block border-t border-border bg-muted/30 p-4 lg:table-cell" role="cell">
                <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {facts.map(([label, value]) => <div key={label}>
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="mt-0.5 font-medium">{value}</dd>
                    </div>)}
                </dl>
            </td>
        </tr>
    </tbody>;
}

function AdminInvoiceList({ user, captureAuth, isAuthCurrent }) {
    const [query, setQuery] = useState(initialQuery);
    const [draft, setDraft] = useState('');
    const [revision, setRevision] = useState(0);
    const [view, setView] = useState(null);
    const gate = useRef({ serial: 0, controller: null });
    const [selectedHost, setSelectedHost] = useState(null);
    const [selectedProperty, setSelectedProperty] = useState(null);
    const [filtersReset, setFiltersReset] = useState(0);
    const [showMoreFilters, setShowMoreFilters] = useState(false);

    useEffect(() => {
        const scope = gate.current;
        const serial = ++scope.serial;
        const controller = new AbortController();
        const auth = captureAuth();
        scope.controller = controller;
        let disposed = false;
        const current = () => !disposed && scope.serial === serial && isAuthCurrent(auth);
        if (isAuthCurrent(auth)) {
            getAdminInvoices(query, { signal: controller.signal }).then((result) => {
                if (current()) setView({ query, revision, auth, result, error: null });
            }, (error) => {
                if (!current()) return;
                const parsed = parseInvoiceError(error);
                if (!parsed.isCancelled) setView({ query, revision, auth, result: null, error: parsed });
            });
        }
        return () => { disposed = true; ++scope.serial; controller.abort(); };
    }, [query, revision, user, captureAuth, isAuthCurrent]);

    // Filter/reload changes hide the previous result during render, before effect cleanup.
    const visible = view?.query === query && view?.revision === revision && isAuthCurrent(view.auth) ? view : null;
    const loading = visible === null;
    const result = visible?.result;
    const error = visible?.error;
    function changeQuery(next) {
        ++gate.current.serial;
        gate.current.controller?.abort();
        setQuery((previous) => ({ ...previous, ...next }));
    }
    function refresh() {
        ++gate.current.serial;
        gate.current.controller?.abort();
        setRevision((previous) => previous + 1);
    }
    const activeOptionCount = [selectedHost, selectedProperty].filter(Boolean).length;
    const filtered = query.status !== 'ALL' || query.search !== '' || activeOptionCount > 0;

    function resetFilters() {
        setDraft('');
        setSelectedHost(null);
        setSelectedProperty(null);
        setFiltersReset((previous) => previous + 1);
        changeQuery(initialQuery);
    }

    function selectHost(host) {
        if ((host?.id ?? null) === query.hostId) return;
        setSelectedHost(host);
        setSelectedProperty(null);
        changeQuery({ hostId: host?.id ?? null, propertyId: null, page: 1 });
    }

    function selectProperty(property) {
        setSelectedProperty(property);
        changeQuery({ propertyId: property?.id ?? null, page: 1 });
    }

    return <section className="mx-auto w-full max-w-7xl space-y-5" aria-labelledby="admin-invoice-title">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <AdminPageHeader id="admin-invoice-title" title="Hóa đơn hệ thống" description="Tra cứu hóa đơn trên toàn hệ thống." />
            <Button variant="outline" type="button" onClick={refresh} disabled={loading}>
                <RefreshCw aria-hidden="true" />
                Tải lại
            </Button>
        </div>

        <div className="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5">
            <form className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                onSubmit={(event) => { event.preventDefault(); changeQuery({ search: draft.trim(), page: 1 }); }}>
                <div className={labelClass + ' sm:col-span-2 xl:col-span-1'}>
                    <label htmlFor="admin-invoice-search">Tìm theo mã hóa đơn, tên khách hoặc cơ sở</label>
                    <input id="admin-invoice-search" className={controlClass} type="search" value={draft} maxLength={255}
                        onChange={(event) => setDraft(event.target.value)} aria-invalid={Boolean(error?.fields.search)}
                        aria-describedby={error?.fields.search ? 'admin-invoice-errors' : undefined} />
                </div>
                <div className={labelClass}>
                    <label htmlFor="admin-invoice-status">Trạng thái thanh toán</label>
                    <select id="admin-invoice-status" className={controlClass} value={query.status}
                        aria-invalid={Boolean(error?.fields.status)} aria-describedby={error?.fields.status ? 'admin-invoice-errors' : undefined}
                        onChange={(event) => changeQuery({ status: event.target.value, page: 1 })}>
                        <option value="ALL">Tất cả trạng thái</option>
                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </div>
                <div className={labelClass}>
                    <label htmlFor="admin-invoice-size">Số hóa đơn mỗi trang</label>
                    <select id="admin-invoice-size" className={controlClass} value={query.perPage}
                        aria-invalid={Boolean(error?.fields.per_page)} aria-describedby={error?.fields.per_page ? 'admin-invoice-errors' : undefined}
                        onChange={(event) => changeQuery({ perPage: Number(event.target.value), page: 1 })}>
                        {[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}
                    </select>
                </div>
                <div className="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-3">
                    <Button className="h-10" type="submit">Tìm kiếm</Button>
                    <Button className="h-10" variant="outline" type="button" onClick={resetFilters}>Đặt lại</Button>
                </div>
            </form>

            <div className="border-t border-border pt-3">
                <Button type="button" variant="ghost" className="-ml-2 px-2" aria-expanded={showMoreFilters}
                    aria-controls="admin-invoice-more-filters" onClick={() => setShowMoreFilters((open) => !open)}>
                    <SlidersHorizontal aria-hidden="true" />
                    Bộ lọc bổ sung{activeOptionCount > 0 && (' (' + activeOptionCount + ')')}
                    <ChevronDown aria-hidden="true" className={showMoreFilters ? 'rotate-180' : ''} />
                </Button>
                <div id="admin-invoice-more-filters" hidden={!showMoreFilters} className="mt-2">
                    <div className="grid gap-3 lg:grid-cols-2">
                        <InvoiceOptionFilter key={'hosts:' + filtersReset} kind="hosts" user={user} captureAuth={captureAuth}
                            isAuthCurrent={isAuthCurrent} selected={selectedHost} onSelect={selectHost} />
                        <InvoiceOptionFilter key={'properties:' + (query.hostId ?? 'all') + ':' + filtersReset} kind="properties"
                            hostId={query.hostId} user={user} captureAuth={captureAuth} isAuthCurrent={isAuthCurrent}
                            selected={selectedProperty} onSelect={selectProperty} />
                    </div>
                </div>
            </div>
        </div>

        <div className="space-y-3" aria-live="polite" aria-busy={loading}>
            {loading && <p role="status" className="rounded-2xl border border-border bg-card p-4 text-sm text-muted-foreground">
                Đang tải danh sách hóa đơn…
            </p>}
            {error && <div className="space-y-2 rounded-2xl border border-border bg-card p-4" role="alert">
                <p>{error.message}</p>
                <ul id="admin-invoice-errors">{Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}</ul>
                {![401, 403, 422].includes(error.status) && <Button variant="outline" type="button" onClick={refresh}>Thử lại</Button>}
            </div>}
            {result && <>
                <p role="status" className="text-sm text-muted-foreground">Tổng cộng {result.meta.total} hóa đơn.</p>
                {result.data.length === 0 ? <p className="rounded-2xl border border-border bg-card p-5 text-sm text-muted-foreground">
                    {query.page > 1 ? 'Trang này không có hóa đơn. Hãy quay về trang đầu.'
                        : filtered ? 'Không có hóa đơn phù hợp với bộ lọc.' : 'Chưa có hóa đơn trong hệ thống.'}
                </p> : <div className="min-w-0 lg:rounded-2xl lg:border lg:border-border lg:bg-card lg:shadow-sm">
                    <table role="table" className="block w-full space-y-3 text-left text-sm lg:table lg:table-fixed lg:space-y-0">
                        <caption className="sr-only">Danh sách hóa đơn toàn hệ thống</caption>
                        <thead role="rowgroup" className="sr-only text-xs text-muted-foreground lg:not-sr-only lg:table-header-group">
                            <tr role="row">
                                {['Mã hóa đơn', 'Khách hàng / Cơ sở', 'Tổng tiền', 'Thanh toán', 'Thông tin'].map((label) => (
                                    <th key={label} scope="col" className="px-4 py-3 font-semibold">{label}</th>
                                ))}
                            </tr>
                        </thead>
                        {result.data.map((invoice) => <InvoiceRow key={invoice.id} invoice={invoice} />)}
                    </table>
                </div>}
                <AdminPagination label="Phân trang hóa đơn" currentPage={result.meta.current_page}
                    lastPage={result.meta.last_page} onPageChange={(page) => changeQuery({ page })} />
            </>}
        </div>
    </section>;
}

export default function AdminInvoiceListPage() {
    const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
    if (!user || isLoggingOut) return null;
    return <AdminInvoiceList key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
