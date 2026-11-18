import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Users, UserRoundCheck, Building2, BedDouble, CalendarCheck, Wallet, ArrowUpRight, RefreshCw, Clock3 } from 'lucide-react';
import AdminDashboardCharts from '../../components/dashboard/AdminDashboardCharts';
import { useAuth } from '../../hooks/useAuth';
import { getAdminDashboard, formatDashboardRevenue, parseDashboardError } from '../../services/adminDashboardService';

const countFormat = new Intl.NumberFormat('vi-VN');
const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const metrics = [
  ['users_count', 'Tổng tài khoản', Users, 'blue'], ['hosts_count', 'Host', UserRoundCheck, 'violet'],
  ['properties_count', 'Cơ sở lưu trú', Building2, 'amber'], ['rooms_count', 'Phòng', BedDouble, 'teal'],
  ['bookings_count', 'Đơn đặt phòng', CalendarCheck, 'blue'], ['revenue_total', 'Doanh thu đã xác nhận', Wallet, 'teal'],
];

function Dashboard({ user, captureAuth, isAuthCurrent }) {
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
      getAdminDashboard({ signal: controller.signal }).then((result) => {
        if (current()) setView({ result, error: null, revision, auth });
      }, (failure) => {
        if (!current()) return;
        const error = parseDashboardError(failure);
        if (!error.isCancelled) setView({ result: null, error, revision, auth });
      });
    }
    return () => { disposed = true; ++scope.serial; controller.abort(); };
  }, [revision, user, captureAuth, isAuthCurrent]);

  const visible = view?.revision === revision && isAuthCurrent(view.auth) ? view : null;
  const loading = visible === null;
  const result = visible?.result;
  const error = visible?.error;
  function reload() {
    ++gate.current.serial;
    gate.current.controller?.abort();
    setRevision((value) => value + 1);
  }

  return <section className="uc47-dashboard min-w-0 space-y-6" aria-labelledby="dashboard-title" aria-busy={loading}>
    <header className="flex flex-wrap items-start justify-between gap-4">
      <div><h1 id="dashboard-title" className="text-2xl font-bold">Tổng quan hệ thống</h1>
        <p className="mt-2 text-muted-foreground">Toàn thời gian · Dữ liệu toàn hệ thống, gồm mọi trạng thái tài khoản và tài nguyên.</p></div>
      <button type="button" onClick={reload} disabled={loading}
        className="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-wait disabled:opacity-50"><RefreshCw size={16} aria-hidden="true" />Tải lại</button>
    </header>
    <div aria-live="polite">
      {loading && <p role="status">Đang tải tổng quan hệ thống…</p>}
      {error && <div role="alert" className="rounded-xl border border-destructive p-4">
        <p>{error.message}</p><button type="button" onClick={reload}
          className="mt-3 rounded-lg border px-4 py-2 focus-visible:outline-2 focus-visible:outline-primary">Thử lại</button>
      </div>}
      {result && <div className="space-y-5">
        <p className="flex items-center gap-2 text-xs text-muted-foreground"><Clock3 size={14} className="shrink-0" aria-hidden="true" />Cập nhật lúc {dateFormat.format(new Date(result.generated_at))} (giờ Việt Nam).</p>
        <dl className="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {metrics.map(([field, label, Icon, tone]) => <div key={field} data-tone={tone} className={`uc47-metric${field === 'revenue_total' ? ' uc47-metric-revenue' : ''}`}>
            <dt className="uc47-metric-top"><span className="text-sm text-muted-foreground">{label}</span><span className="uc47-metric-icon"><Icon size={21} aria-hidden="true" /></span></dt>
            <dd className="mt-4 break-words text-3xl font-semibold tracking-tight tabular-nums [overflow-wrap:anywhere]">
              {field === 'revenue_total' ? formatDashboardRevenue(result.summary[field]) : countFormat.format(result.summary[field])}
              <div className="uc47-metric-footer">{field === 'revenue_total'
                ? <Link className="uc47-dashboard-link" to="/admin/invoices">Xem hóa đơn <ArrowUpRight size={15} aria-hidden="true" /></Link>
                : 'Toàn hệ thống · Mọi trạng thái'}</div>
            </dd>
          </div>)}
        </dl>
        <AdminDashboardCharts statuses={result.booking_status_counts} revenue={result.revenue_by_month} />
        {result.summary.bookings_count === 0 && <p>Chưa có đơn đặt phòng trong hệ thống.</p>}
        <p className="text-sm text-muted-foreground">Doanh thu từ hóa đơn đã thanh toán có ngày xác nhận. Hóa đơn chưa thanh toán hoặc đã hoàn tiền không được tính; tổng có thể thay đổi khi trạng thái hóa đơn thay đổi.</p>
        {result.excluded_paid_invoices_count > 0 && <p role="status" className="rounded-xl border border-amber-500 p-4">
          Có {countFormat.format(result.excluded_paid_invoices_count)} hóa đơn đã thanh toán thiếu ngày xác nhận, chưa được tính vào doanh thu. Vui lòng kiểm tra dữ liệu.
        </p>}
      </div>}
    </div>
  </section>;
}

export default function AdminDashboardPage() {
  const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  if (!user || isLoggingOut) return null;
  return <Dashboard key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
