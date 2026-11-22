import { useEffect, useState } from 'react';
import AdminBookingOptionFilter from '../../components/booking/AdminBookingOptionFilter.jsx';
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
const controlClass = 'rounded-md border border-border bg-background px-3 py-2';

function BookingRows({ bookings }) {
  return <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
    {bookings.map((booking) => <article key={booking.id} className="min-w-0 rounded-xl border border-border bg-card p-4 [overflow-wrap:anywhere]">
      <h2 className="font-semibold">{booking.booking_code}</h2>
      <p className="mb-3 text-sm">{bookingStatusLabels[booking.status]}</p>
      <dl className="grid grid-cols-1 gap-2 text-sm">
        <div><dt className="font-medium">Khách</dt><dd>{booking.customer_name ?? '—'}</dd></div>
        <div><dt className="font-medium">Cơ sở</dt><dd>{booking.property_name ?? '—'}</dd></div>
        <div><dt className="font-medium">Chủ cơ sở</dt><dd>{booking.host_name ?? '—'}</dd></div>
        <div><dt className="font-medium">Phòng</dt><dd>{booking.room_name ?? '—'} · {booking.room_number ?? '—'}</dd></div>
        <div><dt className="font-medium">Nhận và trả phòng</dt><dd>{localDate(booking.check_in_date)} → {localDate(booking.check_out_date)}</dd></div>
        <div><dt className="font-medium">Lưu trú</dt><dd>{booking.guest_count} khách · {booking.number_of_nights} đêm</dd></div>
        <div><dt className="font-medium">Tiền phòng</dt><dd>{formatVnd(booking.room_total)} · {formatVnd(booking.price_per_night)}/đêm</dd></div>
        <div><dt className="font-medium">Ngày tạo</dt><dd>{booking.created_at === null ? '—' : bookingTime.format(new Date(booking.created_at))}</dd></div>
      </dl>
    </article>)}
  </div>;
}

function AdminBookingList({ user, captureAuth, isAuthCurrent }) {
  const [query, setQuery] = useState(initialQuery);
  const [draft, setDraft] = useState('');
  const [dateDraft, setDateDraft] = useState(emptyDates);
  const [rangeError, setRangeError] = useState(false);
  const [view, setView] = useState(null);
  const [optionsReset, setOptionsReset] = useState(0);

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
  function apply(event) {
    event.preventDefault();
    const { checkInFrom, checkInTo } = dateDraft;
    if (checkInFrom && checkInTo && checkInFrom > checkInTo) {
      setRangeError(true);
      event.currentTarget.elements.namedItem('check_in_to')?.focus();
      return;
    }
    setRangeError(false);
    update({ search: draft.trim(), ...dateDraft, page: 1 });
  }
  function reset() {
    setDraft('');
    setDateDraft(emptyDates);
    setRangeError(false);
    setQuery({ ...initialQuery });
    setOptionsReset((value) => value + 1);
  }
  const fieldErrors = error?.fields ?? {};
  const searchError = fieldErrors.search?.join(' ');
  const statusError = fieldErrors.status?.join(' ');
  const sizeError = fieldErrors.per_page?.join(' ');

  return <section className="space-y-4" aria-labelledby="admin-bookings-title">
    <h1 id="admin-bookings-title" className="text-2xl font-semibold">Đơn đặt phòng</h1>
    <form className="flex flex-wrap items-start gap-3" onSubmit={apply}>
      <label className="grid min-w-0 flex-1 basis-56 gap-1">
        Tìm theo mã Booking, tên khách hoặc cơ sở
        <input className={controlClass} value={draft} onChange={(event) => setDraft(event.target.value)}
          aria-invalid={Boolean(searchError)} aria-describedby={searchError ? 'admin-bookings-search-error' : undefined} />
        {searchError && <span id="admin-bookings-search-error">{searchError}</span>}
      </label>
      <label className="grid gap-1">Trạng thái
        <select className={controlClass} value={query.status} onChange={(event) => update({ status: event.target.value, page: 1 })}
          aria-invalid={Boolean(statusError)} aria-describedby={statusError ? 'admin-bookings-status-error' : undefined}>
          <option value="">Tất cả</option>
          {Object.entries(bookingStatusLabels).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
        </select>
        {statusError && <span id="admin-bookings-status-error">{statusError}</span>}
      </label>
      <label className="grid gap-1">Số đơn mỗi trang
        <select className={controlClass} value={query.perPage}
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
        return <label key={key} className="grid min-w-0 gap-1">
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
      <div className="flex flex-wrap gap-2 pt-6">
        <button className={controlClass} type="submit">Tìm kiếm</button>
        <button className={controlClass} type="button" onClick={reset}>Đặt lại</button>
      </div>
    </form>
    <div className="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
      <AdminBookingOptionFilter key={`host-${optionsReset}`} type="hosts" selected={query.host}
        onSelect={selectHost} {...optionsAuth} />
      <AdminBookingOptionFilter key={`property-${optionsReset}-${hostId}`} type="properties" hostId={hostId}
        selected={query.property} onSelect={(property) => update({ property, page: 1 })} {...optionsAuth} />
      <AdminBookingOptionFilter key={`customer-${optionsReset}`} type="customers" selected={query.customer}
        onSelect={(customer) => update({ customer, page: 1 })} {...optionsAuth} />
    </div>
    <div aria-busy={!visible} className="space-y-3">
      {!visible && <p role="status">Đang tải danh sách đặt phòng…</p>}
      {error && <div role="alert" className="rounded-md border border-border p-3">
        <p>{error.message}</p>
        {Object.entries(fieldErrors).filter(([key]) => !['search', 'status', 'per_page', 'check_in_from', 'check_in_to'].includes(key))
          .map(([key, messages]) => <p key={key}>{messages.join(' ')}</p>)}
        {![401, 403, 422].includes(error.status) && <button className={controlClass} type="button" onClick={reload}>Thử lại</button>}
      </div>}
      {result && <>
        <p role="status">{result.data.length ? `Có ${result.meta.total} đơn đặt phòng phù hợp.` : 'Không có đơn đặt phòng trên trang này.'}</p>
        <BookingRows bookings={result.data} />
        <nav className="flex flex-wrap items-center gap-2" aria-label="Phân trang đơn đặt phòng">
          <button className={controlClass} type="button" disabled={query.page === 1} onClick={() => update({ page: 1 })}>Trang đầu</button>
          <button className={controlClass} type="button" disabled={query.page === 1} onClick={() => update({ page: query.page - 1 })}>Trang trước</button>
          <span>Trang {result.meta.current_page} / {result.meta.last_page}</span>
          <button className={controlClass} type="button" disabled={query.page >= result.meta.last_page}
            onClick={() => update({ page: query.page + 1 })}>Trang sau</button>
        </nav>
      </>}
    </div>
  </section>;
}

export default function AdminBookingListPage() {
  const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  if (!user || isLoggingOut) return null;
  return <AdminBookingList key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
