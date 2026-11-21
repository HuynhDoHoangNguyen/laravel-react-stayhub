import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Users, UserRoundCheck, Building2, BedDouble, CalendarCheck, Wallet, ArrowUpRight, RefreshCw, Clock3 } from 'lucide-react';
import AdminDashboardCharts from '../../components/dashboard/AdminDashboardCharts';
import { useAuth } from '../../hooks/useAuth';
import { getAdminDashboard, formatDashboardRevenue, parseDashboardError } from '../../services/adminDashboardService';
import { adminCommissionService } from '../../services/adminCommissionService';

const countFormat = new Intl.NumberFormat('vi-VN');
const dateFormat = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const metrics = [
  ['users_count', 'Tổng tài khoản', Users, 'blue'], ['hosts_count', 'Host', UserRoundCheck, 'violet'],
  ['properties_count', 'Cơ sở lưu trú', Building2, 'amber'], ['rooms_count', 'Phòng', BedDouble, 'teal'],
  ['bookings_count', 'Đơn đặt phòng', CalendarCheck, 'blue'], ['revenue_total', 'Doanh thu đã xác nhận', Wallet, 'teal'],
];

function formatCurrency(amount) {
  const num = Number(amount) || 0;
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
}

function Dashboard({ user, captureAuth, isAuthCurrent }) {
  const [revision, setRevision] = useState(0);
  const [view, setView] = useState(null);
  const gate = useRef({ serial: 0, controller: null });

  // Commission Summary State (RPT-02)
  const [summary, setSummary] = useState(null);
  const [loadingSummary, setLoadingSummary] = useState(true);
  const [summaryError, setSummaryError] = useState(null);

  useEffect(() => {
    let isMounted = true;
    const fetchCommissionSummary = async () => {
      try {
        setLoadingSummary(true);
        const res = await adminCommissionService.getCommissionSummary();
        if (isMounted && res.success) {
          setSummary(res.data);
        }
      } catch (err) {
        if (isMounted) {
          setSummaryError(err.response?.data?.message || 'Không thể tải báo cáo đối soát hệ thống.');
        }
      } finally {
        if (isMounted) {
          setLoadingSummary(false);
        }
      }
    };

    fetchCommissionSummary();
    return () => {
      isMounted = false;
    };
  }, [revision]);

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

  return (
    <div className="space-y-10">
      {/* 1. UC47: System Overview & Charts */}
      <section className="admin-dashboard min-w-0 space-y-6" aria-labelledby="dashboard-title" aria-busy={loading}>
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 id="dashboard-title" className="text-2xl font-bold">Tổng quan hệ thống</h1>
            <p className="mt-2 text-muted-foreground">Toàn thời gian · Dữ liệu toàn hệ thống, gồm mọi trạng thái tài khoản và tài nguyên.</p>
          </div>
          <button
            type="button"
            onClick={reload}
            disabled={loading}
            className="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-wait disabled:opacity-50"
          >
            <RefreshCw size={16} aria-hidden="true" />Tải lại
          </button>
        </header>

        <div aria-live="polite">
          {loading && <p role="status">Đang tải tổng quan hệ thống…</p>}
          {error && (
            <div role="alert" className="rounded-xl border border-destructive p-4">
              <p>{error.message}</p>
              <button
                type="button"
                onClick={reload}
                className="mt-3 rounded-lg border px-4 py-2 focus-visible:outline-2 focus-visible:outline-primary"
              >
                Thử lại
              </button>
            </div>
          )}
          {result && (
            <div className="space-y-5">
              <p className="flex items-center gap-2 text-xs text-muted-foreground">
                <Clock3 size={14} className="shrink-0" aria-hidden="true" />
                Cập nhật lúc {dateFormat.format(new Date(result.generated_at))} (giờ Việt Nam).
              </p>
              <dl className="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {metrics.map(([field, label, Icon, tone]) => (
                  <div key={field} data-tone={tone} className={`admin-dashboard__metric${field === 'revenue_total' ? ' admin-dashboard__metric--revenue' : ''}`}>
                    <dt className="admin-dashboard__metric-top">
                      <span className="text-sm text-muted-foreground">{label}</span>
                      <span className="admin-dashboard__metric-icon"><Icon size={21} aria-hidden="true" /></span>
                    </dt>
                    <dd className="mt-4 break-words text-3xl font-semibold tracking-tight tabular-nums [overflow-wrap:anywhere]">
                      {field === 'revenue_total' ? formatDashboardRevenue(result.summary[field]) : countFormat.format(result.summary[field])}
                      <div className="admin-dashboard__metric-footer">
                        {field === 'revenue_total' ? (
                          <Link className="admin-dashboard__link" to="/admin/invoices">
                            Xem hóa đơn <ArrowUpRight size={15} aria-hidden="true" />
                          </Link>
                        ) : (
                          'Toàn hệ thống · Mọi trạng thái'
                        )}
                      </div>
                    </dd>
                  </div>
                ))}
              </dl>
              <AdminDashboardCharts statuses={result.booking_status_counts} revenue={result.revenue_by_month} />
              {result.summary.bookings_count === 0 && <p>Chưa có đơn đặt phòng trong hệ thống.</p>}
              <p className="text-sm text-muted-foreground">
                Doanh thu từ hóa đơn đã thanh toán có ngày xác nhận. Hóa đơn chưa thanh toán hoặc đã hoàn tiền không được tính; tổng có thể thay đổi khi trạng thái hóa đơn thay đổi.
              </p>
              {result.excluded_paid_invoices_count > 0 && (
                <p role="status" className="rounded-xl border border-amber-500 p-4">
                  Có {countFormat.format(result.excluded_paid_invoices_count)} hóa đơn đã thanh toán thiếu ngày xác nhận, chưa được tính vào doanh thu. Vui lòng kiểm tra dữ liệu.
                </p>
              )}
            </div>
          )}
        </div>
      </section>

      {/* 2. RPT-02: Platform Commission & Remittance Management Section */}
      <section className="space-y-6 pt-6 border-t border-border/60">
        <div>
          <p style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.24em', color: '#785a28', fontWeight: 600, margin: '0 0 6px' }}>
            Quản trị dòng tiền nền tảng
          </p>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <h2 style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '26px', fontWeight: 700, color: '#1a2e20', margin: 0 }}>
                Chỉ số Hoa hồng & Dòng tiền Nền tảng (10%)
              </h2>
              <span style={{ fontSize: '13px', color: '#718096' }}>
                Phân biệt tuyệt đối giữa Doanh thu thực thu (Settled) và Công nợ mở (Outstanding)
              </span>
            </div>
            <Link
              to="/admin/commissions"
              style={{
                fontSize: '13px',
                fontWeight: 600,
                color: '#785a28',
                textDecoration: 'none',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '4px',
              }}
            >
              <span>Quản lý hoa hồng & đối soát</span>
              <span>&rarr;</span>
            </Link>
          </div>
        </div>

        {/* Pending Remittances Alert Banner (if any) */}
        {summary && summary.pending_remittances_count > 0 && (
          <div
            style={{
              background: '#fffaf0',
              border: '1px solid #feebc8',
              borderRadius: '16px',
              padding: '18px 24px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              gap: '16px',
              flexWrap: 'wrap',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
              <span style={{ fontSize: '24px' }}>🔔</span>
              <div>
                <div style={{ fontWeight: 700, color: '#975a16', fontSize: '15px' }}>
                  Có {summary.pending_remittances_count} giao dịch chuyển khoản đang chờ Admin xác minh
                </div>
                <div style={{ fontSize: '13px', color: '#744210' }}>
                  Tổng số tiền đối soát đang chờ duyệt: {formatCurrency(summary.total_under_review)}
                </div>
              </div>
            </div>
            <Link
              to="/admin/commissions"
              style={{
                background: '#785a28',
                color: '#ffffff',
                padding: '10px 18px',
                borderRadius: '10px',
                fontSize: '13px',
                fontWeight: 600,
                textDecoration: 'none',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
              }}
            >
              <span>Mở hàng chờ đối soát</span>
              <span>&rarr;</span>
            </Link>
          </div>
        )}

        {loadingSummary ? (
          <div style={{ background: '#fcfbf9', border: '1px solid #e9e4db', borderRadius: '16px', padding: '32px', textAlign: 'center', color: '#718096' }}>
            Đang tổng hợp số liệu hoa hồng hệ thống...
          </div>
        ) : summaryError ? (
          <div style={{ background: '#fff5f5', border: '1px solid #fed7d7', borderRadius: '16px', padding: '16px', color: '#c53030', fontSize: '14px' }}>
            {summaryError}
          </div>
        ) : summary ? (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))', gap: '16px' }}>
            {/* Doanh thu thực thu nền tảng (SETTLED) */}
            <div
              style={{
                background: '#1a2e20',
                color: '#ffffff',
                borderRadius: '16px',
                padding: '22px',
                boxShadow: '0 4px 16px rgba(26, 46, 32, 0.12)',
              }}
            >
              <span style={{ fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.12em', color: '#c2a265', fontWeight: 600 }}>
                Doanh thu thực thu (Settled)
              </span>
              <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '30px', fontWeight: 700, margin: '8px 0 4px', color: '#faf8f5' }}>
                {formatCurrency(summary.total_settled)}
              </div>
              <span style={{ fontSize: '12px', color: '#97a89c' }}>
                Từ {summary.settled_count || 0} khoản hoa hồng đã khớp sao kê
              </span>
            </div>

            {/* Tổng công nợ mở (Outstanding) */}
            <div
              style={{
                background: '#ffffff',
                border: '1px solid #e9e4db',
                borderRadius: '16px',
                padding: '22px',
                boxShadow: '0 2px 8px rgba(0,0,0,0.02)',
              }}
            >
              <span style={{ fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.08em', color: '#b45309', fontWeight: 600 }}>
                Công nợ mở (Phải thu)
              </span>
              <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '26px', fontWeight: 700, margin: '8px 0 4px', color: '#b45309' }}>
                {formatCurrency(summary.total_outstanding)}
              </div>
              <span style={{ fontSize: '12px', color: '#718096' }}>
                Gồm: Đang hạn ({formatCurrency(summary.total_due)})
              </span>
            </div>

            {/* Đang đối soát (Under Review) */}
            <div
              style={{
                background: '#ffffff',
                border: '1px solid #e9e4db',
                borderRadius: '16px',
                padding: '22px',
                boxShadow: '0 2px 8px rgba(0,0,0,0.02)',
              }}
            >
              <span style={{ fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.08em', color: '#2b6cb0', fontWeight: 600 }}>
                Chờ duyệt sao kê
              </span>
              <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '26px', fontWeight: 700, margin: '8px 0 4px', color: '#2b6cb0' }}>
                {formatCurrency(summary.total_under_review)}
              </div>
              <span style={{ fontSize: '12px', color: '#718096' }}>
                {summary.under_review_count || 0} khoản Host đã nộp ủy nhiệm chi
              </span>
            </div>

            {/* Quá hạn 72h */}
            <div
              style={{
                background: Number(summary.total_overdue) > 0 ? '#fff5f5' : '#ffffff',
                border: Number(summary.total_overdue) > 0 ? '1px solid #fed7d7' : '1px solid #e9e4db',
                borderRadius: '16px',
                padding: '22px',
                boxShadow: '0 2px 8px rgba(0,0,0,0.02)',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.08em', color: Number(summary.total_overdue) > 0 ? '#c53030' : '#5c6960', fontWeight: 600 }}>
                  Quá hạn 72 giờ
                </span>
                {Number(summary.total_overdue) > 0 && (
                  <span style={{ fontSize: '10px', background: '#e53e3e', color: '#fff', padding: '2px 6px', borderRadius: '8px', fontWeight: 700 }}>
                    Cần nhắc nhở
                  </span>
                )}
              </div>
              <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '26px', fontWeight: 700, margin: '8px 0 4px', color: Number(summary.total_overdue) > 0 ? '#c53030' : '#1a2e20' }}>
                {formatCurrency(summary.total_overdue)}
              </div>
              <span style={{ fontSize: '12px', color: '#718096' }}>
                {summary.overdue_count || 0} khoản chậm nộp đối soát
              </span>
            </div>
          </div>
        ) : null}

        {/* Quick Management Links */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(310px, 1fr))', gap: '24px', paddingTop: '8px' }}>
          {/* Quản lý hoa hồng & đối soát */}
          <div
            style={{
              background: '#ffffff',
              border: '1px solid #e9e4db',
              borderRadius: '16px',
              padding: '26px',
              boxShadow: '0 4px 20px -2px rgba(17, 26, 19, 0.04)',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between',
            }}
          >
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '14px', marginBottom: '16px' }}>
                <div
                  style={{
                    width: '42px',
                    height: '42px',
                    borderRadius: '12px',
                    background: 'rgba(217, 119, 6, 0.12)',
                    color: '#b45309',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                  </svg>
                </div>
                <div>
                  <h2 style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '22px', fontWeight: 700, margin: 0, color: '#1a2e20' }}>
                    Quản lý Hoa hồng & Đối soát
                  </h2>
                  <span style={{ fontSize: '12px', color: '#718096' }}>
                    Hàng chờ đối soát & Xác minh ngân hàng
                  </span>
                </div>
              </div>

              <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '24px' }}>
                Tra cứu toàn bộ danh sách hoa hồng hệ thống, kiểm tra sao kê chuyển khoản từ các Host và thực hiện xác minh thanh toán hoặc từ chối có lý do.
              </p>
            </div>

            <div>
              <Link
                to="/admin/commissions"
                style={{
                  textDecoration: 'none',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '8px',
                  background: '#1a2e20',
                  color: '#ffffff',
                  padding: '12px 20px',
                  borderRadius: '12px',
                  fontSize: '14px',
                  fontWeight: 600,
                }}
              >
                <span>Mở danh sách đối soát</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <polyline points="9 18 15 12 9 6" />
                </svg>
              </Link>
            </div>
          </div>

          {/* Hợp đồng đối tác & Chính sách */}
          <div
            style={{
              background: '#ffffff',
              border: '1px solid #e9e4db',
              borderRadius: '16px',
              padding: '26px',
              boxShadow: '0 4px 20px -2px rgba(17, 26, 19, 0.04)',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between',
            }}
          >
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '14px', marginBottom: '16px' }}>
                <div
                  style={{
                    width: '42px',
                    height: '42px',
                    borderRadius: '12px',
                    background: 'rgba(26, 46, 32, 0.08)',
                    color: '#1a2e20',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                  </svg>
                </div>
                <div>
                  <h2 style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '22px', fontWeight: 700, margin: 0, color: '#1a2e20' }}>
                    Hợp đồng đối tác (v1.0)
                  </h2>
                  <span style={{ fontSize: '12px', color: '#718096' }}>
                    Điều khoản chia sẻ doanh thu 10%
                  </span>
                </div>
              </div>

              <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '24px' }}>
                Quy chế hợp tác số v1.0 đang có hiệu lực. Tất cả các Host đăng ký chỗ nghỉ mới bắt buộc chấp thuận thỏa thuận này trước khi khởi tạo phòng.
              </p>
            </div>

            <div>
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px',
                  background: '#f0fdf4',
                  color: '#166534',
                  padding: '8px 14px',
                  borderRadius: '8px',
                  fontSize: '13px',
                  fontWeight: 600,
                }}
              >
                ✓ Phiên bản đang áp dụng: v1.0 (10% BPS)
              </span>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}

export default function AdminDashboardPage() {
  const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  if (!user || isLoggingOut) return null;
  return <Dashboard key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
