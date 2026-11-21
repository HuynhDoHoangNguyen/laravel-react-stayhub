import { useEffect, useRef, useState } from 'react';
import { getAdminInvoices, getAdminInvoiceHostOptions, getAdminInvoicePropertyOptions, formatVnd, parseInvoiceError } from '../../services/invoiceService';
import { useAuth } from '../../hooks/useAuth';
import '../../components/invoice/Invoice.css';

const statuses = { UNPAID: 'Chưa thanh toán', PAID: 'Đã thanh toán', REFUNDED: 'Đã hoàn tiền' };
const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const displayDate = (value) => value === null ? '—' : dateFormat.format(new Date(value));
const initialQuery = { page: 1, perPage: 10, status: 'ALL', search: '', hostId: null, propertyId: null };

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
    return <fieldset className="mb-5 min-w-0 rounded-xl border border-border p-4">
        <legend className="px-2 font-semibold">Lọc theo {label}</legend>
        <form className="flex flex-wrap items-end gap-3" onSubmit={(event) => { event.preventDefault(); change({ search: draft.trim(), page: 1 }); }}>
            <label htmlFor={`${prefix}-search`} className="grid min-w-0 flex-1 gap-1">
                Tìm tên {label}
                <input id={`${prefix}-search`} className="invoice-search-input" style={{ paddingLeft: 14 }} value={draft} maxLength={255}
                    onChange={(event) => setDraft(event.target.value)} aria-invalid={Boolean(error?.fields.search)}
                    aria-describedby={error?.fields.search ? `${prefix}-errors` : undefined} />
            </label>
            <button type="submit">Tìm {label}</button>
            <button type="button" disabled={loading} onClick={retry}>Tải lại {label}</button>
        </form>
        <label htmlFor={`${prefix}-select`} className="mt-3 grid min-w-0 gap-1">
            {propertyMode ? 'Cơ sở' : 'Host'} đang lọc
            <select id={`${prefix}-select`} className="invoice-filter-select w-full min-w-0" value={selected?.id ?? ''}
                onChange={(event) => onSelect(event.target.value === '' ? null : options.find(({ id }) => id === Number(event.target.value)))}>
                <option value="">Tất cả {label}</option>
                {options.map(({ id, name }) => <option key={id} value={id}>{name} — #{id}</option>)}
            </select>
        </label>
        {selected && <p className="mt-2 break-words">Đang chọn: {selected.name} — #{selected.id}
            {' '}<button type="button" onClick={() => onSelect(null)}>Bỏ lọc {label}</button></p>}
        <div aria-live="polite" aria-busy={loading}>
            {loading && <p role="status">Đang tải danh sách {label}…</p>}
            {error && <div role="alert"><p>{error.message}</p>
                <ul id={`${prefix}-errors`}>{Object.entries(error.fields).map(([key, messages]) => <li key={key}>{messages.join(' ')}</li>)}</ul>
                <button type="button" onClick={retry}>Thử tải lại {label}</button></div>}
            {result && <>
                <p role="status">{result.data.length === 0 ? `Không có ${label} phù hợp.` : `Có ${result.meta.total} ${label} phù hợp.`}</p>
                <nav aria-label={`Phân trang danh sách ${label}`} className="mt-2 flex flex-wrap items-center gap-3">
                    <button type="button" disabled={query.page === 1} onClick={() => change({ page: 1 })}>{label}: trang đầu</button>
                    <button type="button" disabled={query.page === 1} onClick={() => change({ page: query.page - 1 })}>{label}: trước</button>
                    <span>Trang {result.meta.current_page} / {result.meta.last_page}</span>
                    <button type="button" disabled={query.page >= result.meta.last_page} onClick={() => change({ page: query.page + 1 })}>{label}: sau</button>
                </nav>
            </>}
        </div>
    </fieldset>;
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
    const filtered = query.status !== 'ALL' || query.search !== '' || query.hostId !== null || query.propertyId !== null;

    return <section className="invoice-card [&_button]:rounded-lg [&_button]:border [&_button]:border-border [&_button]:bg-surface [&_button]:px-4 [&_button]:py-2 [&_button]:text-primary [&_button]:cursor-pointer [&_button:disabled]:opacity-50 [&_button:focus-visible]:outline-2 [&_button:focus-visible]:outline-primary" style={{ minWidth: 0 }} aria-labelledby="admin-invoice-title">
        <header className="invoice-header-row">
            <div><h1 id="admin-invoice-title" className="invoice-title">Quản lý hóa đơn</h1>
                <p>Theo dõi hóa đơn trên toàn hệ thống.</p></div>
            <button type="button" onClick={refresh} disabled={loading}>Tải lại</button>
        </header>
        <InvoiceOptionFilter key={`hosts:${filtersReset}`} kind="hosts" user={user} captureAuth={captureAuth} isAuthCurrent={isAuthCurrent} selected={selectedHost}
            onSelect={(host) => { setSelectedHost(host); setSelectedProperty(null); changeQuery({ hostId: host?.id ?? null, propertyId: null, page: 1 }); }} />
        <InvoiceOptionFilter key={`properties:${query.hostId ?? 'all'}:${filtersReset}`} kind="properties" hostId={query.hostId}
            user={user} captureAuth={captureAuth} isAuthCurrent={isAuthCurrent} selected={selectedProperty}
            onSelect={(property) => { setSelectedProperty(property); changeQuery({ propertyId: property?.id ?? null, page: 1 }); }} />
        <form onSubmit={(event) => { event.preventDefault(); changeQuery({ search: draft.trim(), page: 1 }); }}
            style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'end', gap: 16, marginBottom: 20 }}>
            <label htmlFor="admin-invoice-search" style={{ display: 'grid', gap: 6, flex: '1 1 220px' }}>
                Tìm hóa đơn, khách hàng hoặc cơ sở
                <input id="admin-invoice-search" className="invoice-search-input" style={{ paddingLeft: 14 }} type="search" value={draft} maxLength={255}
                    onChange={(event) => setDraft(event.target.value)} aria-invalid={Boolean(error?.fields.search)}
                    aria-describedby={error?.fields.search ? 'admin-invoice-errors' : undefined} />
            </label>
            <label htmlFor="admin-invoice-status" style={{ display: 'grid', gap: 6 }}>
                Trạng thái thanh toán
                <select id="admin-invoice-status" className="invoice-filter-select" value={query.status}
                    aria-invalid={Boolean(error?.fields.status)} aria-describedby={error?.fields.status ? 'admin-invoice-errors' : undefined}
                    onChange={(event) => changeQuery({ status: event.target.value, page: 1 })}>
                    <option value="ALL">Tất cả trạng thái</option>
                    {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
            </label>
            <button type="submit">Tìm kiếm</button>
            <button type="button" onClick={() => { setDraft(''); setSelectedHost(null); setSelectedProperty(null); setFiltersReset((previous) => previous + 1); changeQuery(initialQuery); }}>Xóa bộ lọc</button>
        </form>
        <div aria-live="polite" aria-busy={loading}>
            {loading && <p role="status">Đang tải danh sách hóa đơn…</p>}
            {error && <div className="invoice-alert error" role="alert">
                <p>{error.message}</p>
                <ul id="admin-invoice-errors">{Object.entries(error.fields).map(([field, messages]) => <li key={field}>{messages.join(' ')}</li>)}</ul>
                <button type="button" onClick={refresh}>Thử lại</button>
            </div>}
            {result && <>
                <p role="status">Đã tải {result.data.length} hóa đơn. Tổng cộng: {result.meta.total} hóa đơn.</p>
                {result.data.length === 0 ? <p>{query.page > 1 ? 'Trang này không có hóa đơn. Hãy quay về trang đầu.'
                    : filtered ? 'Không có hóa đơn phù hợp với bộ lọc.' : 'Chưa có hóa đơn trong hệ thống.'}</p> :
                    <div className="invoice-table-wrapper" tabIndex={0} role="region" aria-label="Bảng hóa đơn, có thể cuộn ngang">
                        <table className="invoice-table" style={{ minWidth: 1000, width: '100%' }}>
                            <caption className="sr-only">Danh sách hóa đơn toàn hệ thống</caption>
                            <thead><tr>{['Mã hóa đơn', 'Mã đặt phòng', 'Khách hàng', 'Cơ sở / Phòng', 'Tổng tiền', 'Thanh toán', 'Ngày thanh toán', 'Ngày lập'].map((label) => <th key={label} scope="col">{label}</th>)}</tr></thead>
                            <tbody>{result.data.map((invoice) => <tr key={invoice.id}>
                                <td>{invoice.invoice_code}</td><td>#{invoice.booking_id}</td><td>{invoice.customer_name}</td>
                                <td>{invoice.property_name}<br /><small>{invoice.room_name}</small></td>
                                <td style={{ whiteSpace: 'nowrap' }}>{formatVnd(invoice.total_amount)}</td>
                                <td><span className={`status-badge ${invoice.payment_status.toLowerCase()}`}>{statuses[invoice.payment_status]}</span></td>
                                <td>{displayDate(invoice.paid_at)}</td><td>{displayDate(invoice.created_at)}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>}
                <nav aria-label="Phân trang hóa đơn" style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center' }}>
                    <label htmlFor="admin-invoice-size">Số dòng mỗi trang </label>
                    <select id="admin-invoice-size" className="invoice-filter-select" value={query.perPage} onChange={(event) => changeQuery({ perPage: Number(event.target.value), page: 1 })}>
                        {[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}
                    </select>
                    <button type="button" disabled={query.page === 1} onClick={() => changeQuery({ page: 1 })}>Trang đầu</button>
                    <button type="button" disabled={query.page === 1} onClick={() => changeQuery({ page: query.page - 1 })}>Trước</button>
                    <span>Trang {result.meta.current_page} / {result.meta.last_page}</span>
                    <button type="button" disabled={query.page >= result.meta.last_page} onClick={() => changeQuery({ page: query.page + 1 })}>Sau</button>
                </nav>
            </>}
        </div>
    </section>;
}

export default function AdminInvoiceListPage() {
    const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
    if (!user || isLoggingOut) return null;
    return <AdminInvoiceList key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
