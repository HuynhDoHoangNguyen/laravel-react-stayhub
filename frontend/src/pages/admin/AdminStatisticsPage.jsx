import { useEffect, useRef, useState } from 'react';
import { AlertTriangle, ArrowRight, CalendarDays, CalendarCheck, Clock3, RefreshCw, SlidersHorizontal, Wallet } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useAdminStatistics } from '../../hooks/useAdminStatistics';
import { formatStatisticsRevenue } from '../../services/statisticsService';
import './AdminStatisticsPage.css';

const numbers = new Intl.NumberFormat('vi-VN');
const dates = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const labels = { PENDING: 'Chờ xác nhận', CONFIRMED: 'Đã xác nhận', CHECKED_IN: 'Đã nhận phòng',
  COMPLETED: 'Hoàn thành', CANCELLED: 'Đã hủy', REJECTED: 'Bị từ chối' };

function Statistics({ auth }) {
  const [draft, setDraft] = useState({ from_month: '', to_month: '' });
  const form = useRef(null);
  const { result, error, loading, params, apply, reload } = useAdminStatistics(auth);
  const draftMatchesRequest = draft.from_month === (params.from_month ?? '') && draft.to_month === (params.to_month ?? '');
  const fields = draftMatchesRequest ? error?.fields ?? {} : {};
  useEffect(() => {
    if (error?.status === 422 && draftMatchesRequest) form.current?.querySelector('input[aria-invalid="true"]')?.focus();
  }, [error, draftMatchesRequest]);
  function submit(event) {
    event.preventDefault();
    apply(draft.from_month === '' && draft.to_month === '' ? {} : draft);
  }
  function reset() { setDraft({ from_month: '', to_month: '' }); apply({}); }

  return <section className="admin-statistics" aria-labelledby="statistics-title">
    <header className="admin-statistics__heading">
      <div><p className="admin-statistics__eyebrow">Báo cáo quản trị</p><h1 id="statistics-title">Thống kê hệ thống</h1>
        <p className="admin-statistics__description">Theo dõi đơn đặt phòng và doanh thu đã xác nhận trong kỳ bạn chọn.</p></div>
      <button type="button" className="admin-statistics__button" onClick={reload} disabled={loading}>
        <RefreshCw size={16} aria-hidden="true" />Tải lại</button>
    </header>
    <form ref={form} onSubmit={submit} className="admin-statistics__filter" aria-labelledby="statistics-filter-title" aria-describedby="statistics-period-help">
      <div className="admin-statistics__filter-heading"><SlidersHorizontal size={18} aria-hidden="true" /><h2 id="statistics-filter-title">Kỳ báo cáo</h2></div>
      <div className="admin-statistics__filter-controls">
        {['from_month', 'to_month'].map((field) => <div key={field} className="admin-statistics__field">
          <label htmlFor={`statistics-${field}`}>{field === 'from_month' ? 'Từ tháng' : 'Đến tháng'}</label>
          <input id={`statistics-${field}`} type="month" min="0001-01" max="9999-12" value={draft[field]}
            onChange={(event) => setDraft((value) => ({ ...value, [field]: event.target.value }))}
            aria-invalid={!!fields[field]} aria-describedby={`statistics-period-help${fields[field] ? ` statistics-${field}-error` : ''}`} />
          {fields[field] && <p id={`statistics-${field}-error`} className="admin-statistics__field-error">{fields[field].join(' ')}</p>}
        </div>)}
        <div className="admin-statistics__actions"><button className="admin-statistics__button admin-statistics__button--primary" type="submit">Áp dụng<ArrowRight size={15} aria-hidden="true" /></button>
          <button className="admin-statistics__button" type="button" onClick={reset}>Đặt lại</button></div>
      </div>
      <p id="statistics-period-help" className="admin-statistics__help">Chọn đủ hai tháng, tối đa 12 tháng. Để trống cả hai hoặc đặt lại để dùng 12 tháng gần nhất theo máy chủ. Kết quả chỉ đổi khi áp dụng.</p>
    </form>
    {loading && <div className="admin-statistics__loading"><p role="status" className="admin-statistics__loading-label">Đang tải thống kê…</p>
      <div className="admin-statistics__skeleton" aria-hidden="true"><div /><div /><div /></div></div>}
    {error && <div role="alert" className="admin-statistics__notice admin-statistics__notice--error">
      <AlertTriangle size={20} aria-hidden="true" /><div><h2>Chưa thể hiển thị thống kê</h2><p>{error.message}</p>
        {error.status === 401 ? <a href="/login" className="admin-statistics__button">Đăng nhập lại</a>
          : error.status === 403 ? <p>Vui lòng kiểm tra quyền hoặc trạng thái tài khoản.</p>
            : <><p>{error.status === 422 ? 'Kiểm tra kỳ báo cáo rồi chọn Áp dụng.' : 'Bạn có thể thử tải lại kỳ vừa yêu cầu.'}</p>
              <button className="admin-statistics__button" onClick={reload} type="button"><RefreshCw size={15} aria-hidden="true" />Thử lại</button></>}
      </div>
    </div>}
    <div className="admin-statistics__results" aria-busy={loading}>
      {result && <>
        <p role="status" className="admin-statistics__sr-only">Đã tải thống kê kỳ {result.period.from_month} đến {result.period.to_month}.</p>
        <div className="admin-statistics__period"><div className="admin-statistics__period-title"><CalendarDays size={19} aria-hidden="true" />
          <div><h2>Kỳ đã áp dụng</h2><p>{result.period.from_month}<span aria-hidden="true"> → </span><span className="admin-statistics__sr-only"> đến </span>{result.period.to_month}</p></div></div>
          <p className="admin-statistics__updated"><Clock3 size={15} aria-hidden="true" /><span>Cập nhật <time dateTime={result.generated_at}>{dates.format(new Date(result.generated_at))}</time><br />Giờ Việt Nam · {result.report_timezone}</span></p>
        </div>
        <dl className="admin-statistics__summary">
          <div className="admin-statistics__metric"><dt><span>Đơn đặt phòng trong kỳ</span><CalendarCheck size={22} aria-hidden="true" /></dt>
            <dd>{numbers.format(result.summary.bookings_count)}<span className="admin-statistics__metric-note">Theo ngày tạo · Gồm mọi trạng thái</span></dd></div>
          <div className="admin-statistics__metric admin-statistics__metric--revenue"><dt><span>Doanh thu đã xác nhận</span><Wallet size={22} aria-hidden="true" /></dt>
            <dd>{formatStatisticsRevenue(result.summary.revenue_total)}<span className="admin-statistics__metric-note">Đã thanh toán · Có ngày xác nhận</span></dd></div>
        </dl>
        <section className="admin-statistics__status-panel" aria-labelledby="statistics-status-title"><div className="admin-statistics__panel-heading"><h2 id="statistics-status-title">Trạng thái đơn đặt phòng</h2><p>Trạng thái hiện tại của các đơn được tạo trong kỳ</p></div>
          <dl className="admin-statistics__status-grid">{result.booking_status_counts.map((row) => <div key={row.status} className="admin-statistics__status" data-status={row.status}>
            <dt><span className="admin-statistics__status-dot" aria-hidden="true" />{labels[row.status]}</dt><dd>{numbers.format(row.count)}</dd>
          </div>)}</dl>
        </section>
        {result.summary.bookings_count === 0 && result.summary.revenue_total === '0.00' && <div role="status" className="admin-statistics__empty"><CalendarDays size={24} aria-hidden="true" />
          <div><h2>Chưa có số liệu trong kỳ này</h2><p>Không có đơn đặt phòng được tạo hoặc doanh thu hợp lệ. Bạn có thể chọn kỳ khác rồi áp dụng.</p></div></div>}
        <section className="admin-statistics__table-panel" aria-labelledby="statistics-monthly-title">
          <div className="admin-statistics__panel-heading"><h2 id="statistics-monthly-title">Chi tiết từng tháng</h2><p id="statistics-table-help">Đủ các tháng trong kỳ, kể cả tháng có số liệu bằng 0. Cuộn ngang để xem đủ cột; khi bảng được chọn, dùng phím mũi tên để cuộn.</p></div>
          <div className="admin-statistics__table-scroll" role="region" aria-labelledby="statistics-monthly-title" aria-describedby="statistics-table-help" tabIndex={0}>
            <table><caption className="admin-statistics__sr-only">Đơn đặt phòng, doanh thu và sáu trạng thái theo tháng, kỳ {result.period.from_month} đến {result.period.to_month}</caption>
              <thead><tr>{['Tháng', 'Đơn đặt phòng', 'Doanh thu (VND)', ...Object.values(labels)].map((label) => <th key={label} scope="col">{label}</th>)}</tr></thead>
              <tbody>{result.monthly.map((row) => <tr key={row.month}><th scope="row">{row.month}</th>
                <td>{numbers.format(row.bookings_count)}</td><td className="admin-statistics__table-money">{formatStatisticsRevenue(row.revenue_total)}</td>
                {row.booking_status_counts.map((status) => <td key={status.status}>{numbers.format(status.count)}</td>)}
              </tr>)}</tbody>
            </table>
          </div>
        </section>
        {result.warnings.paid_invoices_missing_paid_at_global_count > 0 && <div role="status" className="admin-statistics__notice admin-statistics__notice--warning"><AlertTriangle size={20} aria-hidden="true" />
          <div><h2>Hóa đơn thiếu ngày xác nhận</h2><p>Có <strong>{numbers.format(result.warnings.paid_invoices_missing_paid_at_global_count)}</strong> hóa đơn đã thanh toán thiếu ngày xác nhận: <strong>toàn hệ thống, không phân bổ theo kỳ</strong> và chưa tính vào doanh thu.</p></div></div>}
        <p className="admin-statistics__footnote">Doanh thu chỉ gồm hóa đơn hiện đã thanh toán có ngày xác nhận; chưa thanh toán và đã hoàn tiền bị loại. Booking và doanh thu được tổng hợp độc lập. Số liệu có thể thay đổi khi trạng thái hiện tại thay đổi.</p>
      </>}
    </div>
  </section>;
}

export default function AdminStatisticsPage() {
  const auth = useAuth();
  if (!auth.user || auth.isLoggingOut) return null;
  return <Statistics key={auth.user.id} auth={auth} />;
}
