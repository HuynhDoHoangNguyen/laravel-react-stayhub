import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ChevronDown, SlidersHorizontal } from 'lucide-react';
import AdminPageHeader from '../../components/admin/AdminPageHeader.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import AdminBookingOptionFilter from '../../components/booking/AdminBookingOptionFilter.jsx';
import { Button } from '../../components/ui/button.jsx';
import { normalizeApiError } from '../../api/apiError.js';
import { useAuth } from '../../hooks/useAuth';
import { bookingStatusLabels } from '../../mappers/adminBookingMapper.js';
import { getAdminBookings } from '../../services/adminBookingService.js';
import { formatVnd } from '../../utils/formatVnd.js';

const emptyDates = { checkInFrom: '', checkInTo: '' };
const initialQuery = { page: 1, perPage: 10, status: '', search: '', host: null, property: null, customer: null, ...emptyDates };
const bookingTime = new Intl.DateTimeFormat('vi-VN', {
  dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh',
});
const localDate = (date) => date.split('-').reverse().join('/');
const controlClass = 'h-10 w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const labelClass = 'grid min-w-0 content-start gap-1.5 text-sm font-medium';
const statusTone = {
  PENDING: 'bg-amber-100 text-amber-900',
  CONFIRMED: 'bg-sky-100 text-sky-900',
  CHECKED_IN: 'bg-violet-100 text-violet-900',
  COMPLETED: 'bg-emerald-100 text-emerald-900',
  CANCELLED: 'bg-muted text-muted-foreground',
  REJECTED: 'bg-rose-100 text-rose-900',
};

function BookingFact({ label, children }) {
  return <div>
    <dt className="text-muted-foreground">{label}</dt>
    <dd className="mt-0.5 font-medium">{children}</dd>
  </div>;
}

function BookingRows({ bookings }) {
  return <div className="grid items-start gap-4 lg:grid-cols-2 2xl:grid-cols-3">
    {bookings.map((booking) => <article key={booking.id} className="min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm [overflow-wrap:anywhere] sm:p-5">
      <header className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 className="font-sans text-base font-semibold tracking-tight">{booking.booking_code}</h2>
        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone[booking.status]}`}>
          {bookingStatusLabels[booking.status]}
        </span>
      </header>
      <dl className="grid grid-cols-2 gap-3 text-sm">
        <BookingFact label="Khách">{booking.customer_name ?? '—'}</BookingFact>
        <BookingFact label="Cơ sở">{booking.property_name ?? '—'}</BookingFact>
        <BookingFact label="Ngày nhận phòng">{localDate(booking.check_in_date)}</BookingFact>
        <BookingFact label="Ngày trả phòng">{localDate(booking.check_out_date)}</BookingFact>
      </dl>
      <details className="group mt-4 border-t border-border pt-3">
        <summary className="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
          Thông tin lưu trú
          <ChevronDown aria-hidden="true" size={16} className="group-open:rotate-180" />
        </summary>
        <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
          <BookingFact label="Chủ cơ sở">{booking.host_name ?? '—'}</BookingFact>
          <BookingFact label="Phòng">{booking.room_name ?? '—'} · {booking.room_number ?? '—'}</BookingFact>
          <BookingFact label="Lưu trú">{booking.guest_count} khách · {booking.number_of_nights} đêm</BookingFact>
          <BookingFact label="Ngày tạo">{booking.created_at === null ? '—' : bookingTime.format(new Date(booking.created_at))}</BookingFact>
          <BookingFact label="Tiền phòng">{formatVnd(booking.room_total)} · {formatVnd(booking.price_per_night)}/đêm</BookingFact>
        </dl>
      </details>
    </article>)}
  </div>;
}

function AdminBookingList({ user, captureAuth, isAuthCurrent }) {
  const [params, setParams] = useSearchParams();
  const urlSearch = params.get('search')?.trim() ?? '';
  const [query, setQuery] = useState(() => ({ ...initialQuery, search: urlSearch }));
  const [draft, setDraft] = useState(urlSearch);
  const [dateDraft, setDateDraft] = useState(emptyDates);
  const [rangeError, setRangeError] = useState(false);
  const [view, setView] = useState(null);
  const [optionsReset, setOptionsReset] = useState(0);
  const [showMoreFilters, setShowMoreFilters] = useState(false);

  // Apply history navigation before rendering or fetching with the old search.
  if (query.search !== urlSearch) {
    setQuery({ ...query, search: urlSearch, page: 1 });
    setDraft(urlSearch);
  }

  useEffect(() => {
    const controller = new AbortController();
    const auth = captureAuth();
    const current = () => !controller.signal.aborted && isAuthCurrent(auth);
    if (current()) {
      getAdminBookings({ ...query, hostId: query.host?.id, propertyId: query.property?.id,
        customerId: query.customer?.id }, { signal: controller.signal }).then(
        (result) => { if (current()) setView({ query, auth, result }); },
        (failure) => {
          const error = normalizeApiError(failure);
          if (current() && !error.isCancelled) setView({ query, auth, error });
        },
      );
    }
    return () => controller.abort();
  }, [query, user, captureAuth, isAuthCurrent]);

  const visible = view?.query === query && isAuthCurrent(view.auth) ? view : null;
  const result = visible?.result;
  const error = visible?.error;
  const update = (patch) => setQuery((previous) => ({ ...previous, ...patch }));
  const reload = () => update({});
  const hostId = query.host?.id ?? null;
  const optionsAuth = { user, captureAuth, isAuthCurrent };
  function selectHost(host) {
    if ((host?.id ?? null) !== hostId) update({ host, property: null, page: 1 });
  }
  function updateSearch(search) {
    if (params.get('search') === (search || null)) return;
    const next = new URLSearchParams(params);
    if (search) next.set('search', search);
    else next.delete('search');
    setParams(next);
  }
  function apply(event) {
    event.preventDefault();
    const { checkInFrom, checkInTo } = dateDraft;
    if (checkInFrom && checkInTo && checkInFrom > checkInTo) {
      setRangeError(true);
      event.currentTarget.elements.namedItem('check_in_to')?.focus();
      return;
    }
    setRangeError(false);
    update({ ...dateDraft, page: 1 });
    updateSearch(draft.trim());
  }
  function reset() {
    setDraft('');
    setDateDraft(emptyDates);
    setRangeError(false);
    setQuery({ ...initialQuery, search: query.search });
    updateSearch('');
    setOptionsReset((value) => value + 1);
  }
  const fieldErrors = error?.fields ?? {};
  const searchError = fieldErrors.search?.join(' ');
  const statusError = fieldErrors.status?.join(' ');
  const sizeError = fieldErrors.per_page?.join(' ');
  const activeOptionCount = [query.host, query.property, query.customer].filter(Boolean).length;

  return <section className="mx-auto w-full max-w-7xl space-y-5" aria-labelledby="admin-bookings-title">
    <AdminPageHeader id="admin-bookings-title" title="Đơn đặt phòng" description="Tra cứu đơn đặt phòng trên toàn hệ thống." />
    <div className="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5">
      <form className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" onSubmit={apply}>
        <label className={`${labelClass} sm:col-span-2 xl:col-span-1`}>
          Tìm theo mã đơn, tên khách hoặc cơ sở
          <input className={controlClass} type="search" value={draft} onChange={(event) => setDraft(event.target.value)}
            aria-invalid={Boolean(searchError)} aria-describedby={searchError ? 'admin-bookings-search-error' : undefined} />
          {searchError && <span id="admin-bookings-search-error">{searchError}</span>}
        </label>
        <label className={labelClass}>
          <span id="admin-bookings-status-label">Trạng thái</span>
          <select aria-labelledby="admin-bookings-status-label" className={controlClass} value={query.status}
            onChange={(event) => update({ status: event.target.value, page: 1 })}
            aria-invalid={Boolean(statusError)} aria-describedby={statusError ? 'admin-bookings-status-error' : undefined}>
            <option value="">Tất cả</option>
            {Object.entries(bookingStatusLabels).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
          </select>
          {statusError && <span id="admin-bookings-status-error">{statusError}</span>}
        </label>
        <label className={labelClass}>
          <span id="admin-bookings-size-label">Số đơn mỗi trang</span>
          <select aria-labelledby="admin-bookings-size-label" className={controlClass} value={query.perPage}
            aria-invalid={Boolean(sizeError)} aria-describedby={sizeError ? 'admin-bookings-size-error' : undefined}
            onChange={(event) => update({ perPage: Number(event.target.value), page: 1 })}>
            {[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}
          </select>
          {sizeError && <span id="admin-bookings-size-error">{sizeError}</span>}
        </label>
        {[
          ['checkInFrom', 'check_in_from', 'Ngày nhận phòng từ'],
          ['checkInTo', 'check_in_to', 'Ngày nhận phòng đến'],
        ].map(([key, field, label]) => {
          const message = field === 'check_in_to' && rangeError
            ? 'Ngày nhận phòng đến phải từ ngày bắt đầu trở đi.'
            : dateDraft[key] === query[key] ? fieldErrors[field]?.join(' ') : undefined;
          const errorId = `admin-bookings-${field}-error`;
          return <label key={key} className={labelClass}>
            {label}
            <input className={controlClass} type="date" name={field} min="1000-01-01" max="9999-12-31"
              value={dateDraft[key]} aria-invalid={Boolean(message)} aria-describedby={message ? errorId : undefined}
              onChange={(event) => {
                const value = event.target.value;
                setDateDraft((previous) => ({ ...previous, [key]: value }));
                setRangeError(false);
              }} />
            {message && <span id={errorId} role="alert">{message}</span>}
          </label>;
        })}
        <div className="flex flex-wrap items-start gap-2 sm:col-span-2 xl:col-span-1 xl:pt-6">
          <Button className="h-10" type="submit">Tìm kiếm</Button>
          <Button className="h-10" variant="outline" type="button" onClick={reset}>Đặt lại</Button>
        </div>
      </form>
      <div className="border-t border-border pt-3">
        <Button type="button" variant="ghost" className="-ml-2 px-2" aria-expanded={showMoreFilters}
          aria-controls="admin-booking-more-filters" onClick={() => setShowMoreFilters((open) => !open)}>
          <SlidersHorizontal aria-hidden="true" />
          Bộ lọc bổ sung{activeOptionCount > 0 && ` (${activeOptionCount})`}
          <ChevronDown aria-hidden="true" className={showMoreFilters ? 'rotate-180' : ''} />
        </Button>
        <div id="admin-booking-more-filters" hidden={!showMoreFilters} className="mt-2">
          <div className="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
            <AdminBookingOptionFilter key={`host-${optionsReset}`} type="hosts" selected={query.host}
              onSelect={selectHost} {...optionsAuth} />
            <AdminBookingOptionFilter key={`property-${optionsReset}-${hostId}`} type="properties" hostId={hostId}
              selected={query.property} onSelect={(property) => update({ property, page: 1 })} {...optionsAuth} />
            <AdminBookingOptionFilter key={`customer-${optionsReset}`} type="customers" selected={query.customer}
              onSelect={(customer) => update({ customer, page: 1 })} {...optionsAuth} />
          </div>
        </div>
      </div>
    </div>
    <div aria-busy={!visible} className="space-y-3">
      {!visible && <p role="status" className="rounded-2xl border border-border bg-card p-4 text-sm text-muted-foreground">Đang tải danh sách đặt phòng…</p>}
      {error && <div role="alert" className="space-y-2 rounded-2xl border border-border bg-card p-4">
        <p>{error.message}</p>
        {Object.entries(fieldErrors).filter(([key]) => !['search', 'status', 'per_page', 'check_in_from', 'check_in_to'].includes(key))
          .map(([key, messages]) => <p key={key}>{messages.join(' ')}</p>)}
        {![401, 403, 422].includes(error.status) && <Button variant="outline" type="button" onClick={reload}>Thử lại</Button>}
      </div>}
      {result && <>
        <p role="status" className={result.data.length ? 'text-sm text-muted-foreground' : 'rounded-2xl border border-border bg-card p-5 text-sm text-muted-foreground'}>
          {result.data.length ? `Có ${result.meta.total} đơn đặt phòng phù hợp.` : 'Không có đơn đặt phòng trên trang này.'}
        </p>
        {result.data.length > 0 && <BookingRows bookings={result.data} />}
        <AdminPagination label="Phân trang đơn đặt phòng" currentPage={result.meta.current_page}
          lastPage={result.meta.last_page} onPageChange={(page) => update({ page })} />
      </>}
    </div>
  </section>;
}

export default function AdminBookingListPage() {
  const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  if (!user || isLoggingOut) return null;
  return <AdminBookingList key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
