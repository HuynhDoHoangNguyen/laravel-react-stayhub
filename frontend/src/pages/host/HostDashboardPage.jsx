import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { createHostDashboardLoader, hostDashboardPeriodErrors } from '../../services/hostDashboardService.js';
import { formatVnd } from '../../utils/formatVnd.js';
import HostDashboardRevenueChart from '../../components/dashboard/HostDashboardRevenueChart.jsx';
import './HostDashboardPage.css';

const number = new Intl.NumberFormat('vi-VN');
const time = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const statusLabels = { PENDING: 'Chờ xác nhận', CONFIRMED: 'Đã xác nhận', CHECKED_IN: 'Đang lưu trú',
  COMPLETED: 'Hoàn tất', CANCELLED: 'Đã hủy', REJECTED: 'Đã từ chối' };

function Dashboard({ user, captureAuth, isAuthCurrent }) {
  const [view, setView] = useState(null);
  const [draft, setDraft] = useState({ from: '', to: '' });
  const [query, setQuery] = useState({});
  const [fields, setFields] = useState({});
  const loader = useMemo(() => createHostDashboardLoader({ captureAuth, isAuthCurrent, onChange: setView }),
    [captureAuth, isAuthCurrent]);
  useEffect(() => {
    void loader.load(query);
    return () => loader.cancel();
  }, [loader, user, query]);
  const visible = view && isAuthCurrent(view.auth) ? view : null;
  const loading = !visible || visible.loading;
  const result = visible?.result;
  const error = visible?.error;
  const fieldErrors = { ...(error?.status === 422 ? error.fields : {}), ...fields };
  function apply(event) {
    event.preventDefault();
    const errors = hostDashboardPeriodErrors(draft.from, draft.to);
    setFields(errors);
    if (Object.keys(errors).length) {
      event.currentTarget.elements.namedItem(Object.keys(errors)[0])?.focus();
      return;
    }
    loader.cancel();
    setQuery(draft.from === '' && draft.to === '' ? {} : { fromMonth: draft.from, toMonth: draft.to });
  }
  function reset() {
    setDraft({ from: '', to: '' }); setFields({}); loader.cancel(); setQuery({});
  }
  return <section className="host-dashboard" aria-labelledby="host-dashboard-title" aria-busy={loading}>
    <header className="host-dashboard__header">
      <div><h1 id="host-dashboard-title">Tổng quan cơ sở của bạn</h1>
        <p>Thống kê trong phạm vi các cơ sở hiện thuộc quyền quản lý của bạn.</p></div>
      <button type="button" onClick={() => void loader.load(query)} disabled={loading}>Tải lại</button>
    </header>
    <nav className="host-dashboard__links" aria-label="Quản lý Host">
      <Link to="/host/invoices">Quản lý hóa đơn</Link><Link to="/host/invoices/create">Lập hóa đơn</Link>
      <Link to="/host/reviews">Xem đánh giá</Link><Link to="/host/properties">Quản lý cơ sở</Link>
    </nav>
    <form onSubmit={apply} noValidate className="host-dashboard__filters" aria-label="Chọn kỳ doanh thu">
      {['from', 'to'].map((key) => {
        const name = key === 'from' ? 'from_month' : 'to_month';
        return <div key={key}><label htmlFor={'host-dashboard-' + name}>{key === 'from' ? 'Từ tháng' : 'Đến tháng'}</label>
          <input id={'host-dashboard-' + name} name={name} type="month" min="0001-01" max="9999-12"
            value={draft[key]} aria-invalid={!!fieldErrors[name]} aria-describedby={'host-dashboard-' + name + '-error host-dashboard-period-help'}
            onChange={(event) => { setDraft({ ...draft, [key]: event.target.value }); setFields({}); }} />
          <span id={'host-dashboard-' + name + '-error'} className="host-dashboard__field-error">{fieldErrors[name]?.join(' ')}</span>
        </div>;
      })}
      <button type="submit">Áp dụng</button><button type="button" onClick={reset}>Đặt lại về mặc định server</button>
      <p id="host-dashboard-period-help">Chọn cả hai tháng, tối đa 12 tháng. Để trống cả hai để dùng kỳ mặc định server. Sửa ô tháng chưa thay đổi kỳ của dữ liệu đã trả về.</p>
      {Object.keys(fieldErrors).length > 0 && <p role="alert">Vui lòng kiểm tra kỳ doanh thu.</p>}
    </form>
    {loading && <p role="status">Đang tải tổng quan…</p>}
    {error && <div role="alert" className="host-dashboard__error">
      <p>{error.message}</p>
      {Object.entries(error.fields).map(([field, messages]) => <p key={field}>{field}: {messages.join(' ')}</p>)}
      {![401, 403].includes(error.status) && <button type="button" onClick={() => void loader.load(query)}>Thử lại</button>}
    </div>}
    {result && <>
      <p>Cập nhật lúc {time.format(new Date(result.generated_at))} · Múi giờ báo cáo: {result.report_timezone}</p>
      <dl className="host-dashboard__metrics">
        {[
          ['Số cơ sở thuộc phạm vi hiện tại', result.scope.properties_count],
          ['Tổng số phòng — hiện tại', result.snapshot.rooms_count],
          ['Đơn chờ xác nhận hiện tại', result.snapshot.pending_bookings_count],
          ['Lịch bảo trì đang xử lý — hiện tại', result.snapshot.in_progress_maintenances_count],
        ].map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{number.format(value)}</dd></div>)}
      </dl>
      {result.scope.properties_count === 0 && <p>Bạn chưa có cơ sở trong phạm vi thống kê. <Link to="/host/properties">Quản lý cơ sở</Link></p>}
      <section className="host-dashboard__panel" aria-labelledby="host-dashboard-revenue">
        <h2 id="host-dashboard-revenue">Doanh thu đã thanh toán — trong kỳ</h2>
        <p>Kỳ đã áp dụng: {result.period.from_month} đến {result.period.to_month} (giờ Việt Nam).</p>
        <p className="host-dashboard__total">{formatVnd(result.summary.revenue_total)}</p>
        <p>Chỉ tính hóa đơn hiện đã thanh toán và có ngày xác nhận; tổng có thể thay đổi khi hoàn tiền.
          Kỳ doanh thu không lọc các chỉ số hiện tại và đơn gần đây.</p>
        <HostDashboardRevenueChart rows={result.revenue_by_month} />
      </section>
      {result.warnings.paid_invoices_missing_paid_at_scope_count > 0 && <p role="status" className="host-dashboard__warning">
        Có {number.format(result.warnings.paid_invoices_missing_paid_at_scope_count)} hóa đơn đã thanh toán thiếu ngày xác nhận
        trong toàn phạm vi Host, không phụ thuộc kỳ đã chọn. Các hóa đơn này chưa được tính vào doanh thu.
      </p>}
      <section className="host-dashboard__panel" aria-labelledby="host-dashboard-recent">
        <h2 id="host-dashboard-recent">5 đơn đặt phòng mới nhất — mọi trạng thái</h2>
        {result.recent_bookings.length === 0 ? <p>Chưa có đơn đặt phòng trong phạm vi thống kê.</p> :
          <ul className="host-dashboard__recent">{result.recent_bookings.map((row) => <li key={row.id}>
            <strong>{row.booking_code}</strong> · {statusLabels[row.status]}
            <p>{row.property.name} · {row.room.name}</p>
            <p>Ngày tạo: {time.format(new Date(row.created_at))} · Ngày lưu trú: {row.check_in_date} đến {row.check_out_date}</p>
          </li>)}</ul>}
      </section>
    </>}
  </section>;
}

export default function HostDashboardPage() {
  const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  if (!user || isLoggingOut) return null;
  return <Dashboard key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
