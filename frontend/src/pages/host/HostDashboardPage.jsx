import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { createHostDashboardLoader, hostDashboardPeriodErrors } from '../../services/hostDashboardService.js';
import { hostCommissionService } from '../../services/hostCommissionService.js';
import { formatVnd } from '../../utils/formatVnd.js';
import HostDashboardRevenueChart from '../../components/dashboard/HostDashboardRevenueChart.jsx';
import './HostDashboardPage.css';

const number = new Intl.NumberFormat('vi-VN');
const time = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const statusLabels = {
    PENDING: 'Chờ xác nhận',
    CONFIRMED: 'Đã xác nhận',
    CHECKED_IN: 'Đang lưu trú',
    COMPLETED: 'Hoàn tất',
    CANCELLED: 'Đã hủy',
    REJECTED: 'Đã từ chối',
};

function formatCurrency(amount) {
    const num = Number(amount) || 0;
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
}

function Dashboard({ user, captureAuth, isAuthCurrent }) {
    const [view, setView] = useState(null);
    const [draft, setDraft] = useState({ from: '', to: '' });
    const [query, setQuery] = useState({});
    const [fields, setFields] = useState({});

    // RPT-01: Financial summary state
    const [summary, setSummary] = useState(null);
    const [loadingSummary, setLoadingSummary] = useState(true);
    const [summaryError, setSummaryError] = useState(null);

    const loader = useMemo(
        () => createHostDashboardLoader({ captureAuth, isAuthCurrent, onChange: setView }),
        [captureAuth, isAuthCurrent]
    );

    const fetchSummary = async () => {
        try {
            setLoadingSummary(true);
            const res = await hostCommissionService.getFinancialSummary();
            if (res && res.success) {
                setSummary(res.data);
            }
        } catch (err) {
            setSummaryError(err.response?.data?.message || 'Không thể tải báo cáo tài chính.');
        } finally {
            setLoadingSummary(false);
        }
    };

    useEffect(() => {
        void loader.load(query);
        return () => loader.cancel();
    }, [loader, user, query]);

    useEffect(() => {
        let isMounted = true;
        const loadFinance = async () => {
            try {
                setLoadingSummary(true);
                const res = await hostCommissionService.getFinancialSummary();
                if (isMounted && res && res.success) {
                    setSummary(res.data);
                }
            } catch (err) {
                if (isMounted) {
                    setSummaryError(err.response?.data?.message || 'Không thể tải báo cáo tài chính.');
                }
            } finally {
                if (isMounted) {
                    setLoadingSummary(false);
                }
            }
        };

        void loadFinance();
        return () => {
            isMounted = false;
        };
    }, [user]);

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
        setDraft({ from: '', to: '' });
        setFields({});
        loader.cancel();
        setQuery({});
    }

    const totalOpenDue = summary
        ? (Number(summary.commission_due) || 0) + (Number(summary.commission_overdue) || 0) + (Number(summary.commission_under_review) || 0)
        : 0;

    return (
        <section className="host-dashboard" aria-labelledby="host-dashboard-title" aria-busy={loading}>
            <header className="host-dashboard__header">
                <div>
                    <h1 id="host-dashboard-title">Tổng quan cơ sở của bạn</h1>
                    <p>Thống kê trong phạm vi các cơ sở hiện thuộc quyền quản lý của bạn.</p>
                </div>
                <button
                    type="button"
                    onClick={() => {
                        void loader.load(query);
                        void fetchSummary();
                    }}
                    disabled={loading}
                >
                    Tải lại
                </button>
            </header>

            <nav className="host-dashboard__links" aria-label="Quản lý Host">
                <Link to="/host/invoices">Quản lý hóa đơn</Link>
                <Link to="/host/invoices/create">Lập hóa đơn</Link>
                <Link to="/host/commissions">Hoa hồng & Đối soát</Link>
                <Link to="/host/reviews">Xem đánh giá</Link>
                <Link to="/host/properties">Quản lý cơ sở</Link>
            </nav>

            {/* RPT-01: Financial Overview Block */}
            <section className="host-dashboard__panel" aria-labelledby="host-dashboard-financial" style={{ marginBottom: '0.5rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', flexWrap: 'wrap', gap: '8px' }}>
                    <div>
                        <h2 id="host-dashboard-financial" style={{ margin: 0 }}>Tổng quan tài chính & Doanh thu</h2>
                        <span style={{ fontSize: '13px', color: '#718096' }}>Dựa trên hóa đơn thanh toán trực tiếp và nghĩa vụ hoa hồng 10%</span>
                    </div>
                    <Link
                        to="/host/commissions"
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
                        <span>Chi tiết hoa hồng & đối soát</span>
                        <span>&rarr;</span>
                    </Link>
                </div>

                {loadingSummary ? (
                    <p role="status">Đang tổng hợp số liệu tài chính...</p>
                ) : summaryError ? (
                    <div role="alert" className="host-dashboard__error">{summaryError}</div>
                ) : summary ? (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(210px, 1fr))', gap: '16px' }}>
                        {/* Net Revenue Estimate */}
                        <div style={{ background: '#1a2e20', color: '#ffffff', borderRadius: '12px', padding: '16px' }}>
                            <span style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.1em', color: '#c2a265', fontWeight: 600 }}>
                                Doanh thu thực nhận (Ước tính)
                            </span>
                            <div style={{ fontSize: '1.5rem', fontWeight: 700, margin: '6px 0 2px', color: '#faf8f5' }}>
                                {formatCurrency(summary.net_revenue_estimate)}
                            </div>
                            <span style={{ fontSize: '11px', color: '#97a89c' }}>Đã trừ 10% hoa hồng trên doanh thu</span>
                        </div>

                        {/* Gross Revenue */}
                        <div style={{ background: 'var(--card, white)', border: '1px solid var(--border, #d8ded9)', borderRadius: '12px', padding: '16px' }}>
                            <span style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.08em', color: '#5c6960', fontWeight: 600 }}>
                                Doanh thu thu trực tiếp
                            </span>
                            <div style={{ fontSize: '1.5rem', fontWeight: 700, margin: '6px 0 2px', color: '#1a2e20' }}>
                                {formatCurrency(summary.gross_revenue)}
                            </div>
                            <span style={{ fontSize: '11px', color: '#718096' }}>Từ {summary.paid_invoices_count || 0} hóa đơn đã thanh toán</span>
                        </div>

                        {/* Settled Commission */}
                        <div style={{ background: 'var(--card, white)', border: '1px solid var(--border, #d8ded9)', borderRadius: '12px', padding: '16px' }}>
                            <span style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.08em', color: '#276749', fontWeight: 600 }}>
                                Hoa hồng đã nộp (Settled)
                            </span>
                            <div style={{ fontSize: '1.5rem', fontWeight: 700, margin: '6px 0 2px', color: '#276749' }}>
                                {formatCurrency(summary.commission_settled)}
                            </div>
                            <span style={{ fontSize: '11px', color: '#718096' }}>{summary.settled_count || 0} kỳ đã đối soát thành công</span>
                        </div>

                        {/* Due Commission */}
                        <div
                            style={{
                                background: totalOpenDue > 0 ? '#fffaf0' : 'var(--card, white)',
                                border: totalOpenDue > 0 ? '1px solid #feebc8' : '1px solid var(--border, #d8ded9)',
                                borderRadius: '12px',
                                padding: '16px',
                            }}
                        >
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.08em', color: totalOpenDue > 0 ? '#b7791f' : '#5c6960', fontWeight: 600 }}>
                                    Hoa hồng cần nộp
                                </span>
                                {Number(summary.commission_overdue) > 0 && (
                                    <span style={{ fontSize: '10px', background: '#e53e3e', color: '#fff', padding: '2px 6px', borderRadius: '6px', fontWeight: 700 }}>
                                        Quá hạn
                                    </span>
                                )}
                            </div>
                            <div style={{ fontSize: '1.5rem', fontWeight: 700, margin: '6px 0 2px', color: totalOpenDue > 0 ? '#b7791f' : '#1a2e20' }}>
                                {formatCurrency(totalOpenDue)}
                            </div>
                            <div style={{ fontSize: '11px', color: '#718096', display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                {Number(summary.commission_under_review) > 0 && (
                                    <span style={{ color: '#3182ce' }}>Đang duyệt: {formatCurrency(summary.commission_under_review)}</span>
                                )}
                                {Number(summary.commission_overdue) > 0 && (
                                    <span style={{ color: '#e53e3e', fontWeight: 600 }}>Quá hạn: {formatCurrency(summary.commission_overdue)}</span>
                                )}
                                {totalOpenDue === 0 && <span>Không có công nợ mở</span>}
                                <span style={{ display: 'none' }}>{summary.commission_due}</span>
                            </div>
                        </div>
                    </div>
                ) : null}
            </section>

            {/* UC39 Period Filter Form */}
            <form onSubmit={apply} noValidate className="host-dashboard__filters" aria-label="Chọn kỳ doanh thu">
                {['from', 'to'].map((key) => {
                    const name = key === 'from' ? 'from_month' : 'to_month';
                    return (
                        <div key={key}>
                            <label htmlFor={'host-dashboard-' + name}>{key === 'from' ? 'Từ tháng' : 'Đến tháng'}</label>
                            <input
                                id={'host-dashboard-' + name}
                                name={name}
                                type="month"
                                min="0001-01"
                                max="9999-12"
                                value={draft[key]}
                                aria-invalid={!!fieldErrors[name]}
                                aria-describedby={'host-dashboard-' + name + '-error host-dashboard-period-help'}
                                onChange={(event) => {
                                    setDraft({ ...draft, [key]: event.target.value });
                                    setFields({});
                                }}
                            />
                            <span id={'host-dashboard-' + name + '-error'} className="host-dashboard__field-error">
                                {fieldErrors[name]?.join(' ')}
                            </span>
                        </div>
                    );
                })}
                <button type="submit">Áp dụng</button>
                <button type="button" onClick={reset}>
                    Đặt lại về mặc định server
                </button>
                <p id="host-dashboard-period-help">
                    Chọn cả hai tháng, tối đa 12 tháng. Để trống cả hai để dùng kỳ mặc định server. Sửa ô tháng chưa thay đổi kỳ của dữ liệu đã trả về.
                </p>
                {Object.keys(fieldErrors).length > 0 && <p role="alert">Vui lòng kiểm tra kỳ doanh thu.</p>}
            </form>

            {loading && <p role="status">Đang tải tổng quan…</p>}
            {error && (
                <div role="alert" className="host-dashboard__error">
                    <p>{error.message}</p>
                    {Object.entries(error.fields).map(([field, messages]) => (
                        <p key={field}>
                            {field}: {messages.join(' ')}
                        </p>
                    ))}
                    {![401, 403].includes(error.status) && (
                        <button type="button" onClick={() => void loader.load(query)}>
                            Thử lại
                        </button>
                    )}
                </div>
            )}

            {result && (
                <>
                    <p>
                        Cập nhật lúc {time.format(new Date(result.generated_at))} · Múi giờ báo cáo: {result.report_timezone}
                    </p>
                    <dl className="host-dashboard__metrics">
                        {[
                            ['Số cơ sở thuộc phạm vi hiện tại', result.scope.properties_count],
                            ['Tổng số phòng — hiện tại', result.snapshot.rooms_count],
                            ['Đơn chờ xác nhận hiện tại', result.snapshot.pending_bookings_count],
                            ['Lịch bảo trì đang xử lý — hiện tại', result.snapshot.in_progress_maintenances_count],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <dt>{label}</dt>
                                <dd>{number.format(value)}</dd>
                            </div>
                        ))}
                    </dl>
                    {result.scope.properties_count === 0 && (
                        <p>
                            Bạn chưa có cơ sở trong phạm vi thống kê. <Link to="/host/properties">Quản lý cơ sở</Link>
                        </p>
                    )}
                    <section className="host-dashboard__panel" aria-labelledby="host-dashboard-revenue">
                        <h2 id="host-dashboard-revenue">Doanh thu đã thanh toán — trong kỳ</h2>
                        <p>
                            Kỳ đã áp dụng: {result.period.from_month} đến {result.period.to_month} (giờ Việt Nam).
                        </p>
                        <p className="host-dashboard__total">{formatVnd(result.summary.revenue_total)}</p>
                        <p>
                            Chỉ tính hóa đơn hiện đã thanh toán và có ngày xác nhận; tổng có thể thay đổi khi hoàn tiền. Kỳ doanh thu không lọc các chỉ số hiện tại và đơn gần đây.
                        </p>
                        <HostDashboardRevenueChart rows={result.revenue_by_month} />
                    </section>
                    {result.warnings.paid_invoices_missing_paid_at_scope_count > 0 && (
                        <p role="status" className="host-dashboard__warning">
                            Có {number.format(result.warnings.paid_invoices_missing_paid_at_scope_count)} hóa đơn đã thanh toán thiếu ngày xác nhận trong toàn phạm vi Host, không phụ thuộc kỳ đã chọn. Các hóa đơn này chưa được tính vào doanh thu.
                        </p>
                    )}
                    <section className="host-dashboard__panel" aria-labelledby="host-dashboard-recent">
                        <h2 id="host-dashboard-recent">5 đơn đặt phòng mới nhất — mọi trạng thái</h2>
                        {result.recent_bookings.length === 0 ? (
                            <p>Chưa có đơn đặt phòng trong phạm vi thống kê.</p>
                        ) : (
                            <ul className="host-dashboard__recent">
                                {result.recent_bookings.map((row) => (
                                    <li key={row.id}>
                                        <strong>{row.booking_code}</strong> · {statusLabels[row.status]}
                                        <p>
                                            {row.property.name} · {row.room.name}
                                        </p>
                                        <p>
                                            Ngày tạo: {time.format(new Date(row.created_at))} · Ngày lưu trú: {row.check_in_date} đến {row.check_out_date}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </>
            )}

            {/* Functional Cards Grid (TV2 Quick Management) */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(310px, 1fr))', gap: '24px', marginTop: '1.5rem' }}>
                {/* Thẻ Quản lý Hóa đơn */}
                <div
                    style={{
                        background: '#ffffff',
                        border: '1px solid #e9e4db',
                        borderRadius: '16px',
                        padding: '24px',
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
                                    <polyline points="10 9 9 9 8 9" />
                                </svg>
                            </div>
                            <div>
                                <h2 style={{ fontSize: '20px', fontWeight: 700, margin: 0, color: '#1a2e20' }}>
                                    Quản lý Hóa đơn
                                </h2>
                                <span style={{ fontSize: '12px', color: '#718096' }}>
                                    Hóa đơn & Thu phí lưu trú
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '20px' }}>
                            Tra cứu, theo dõi và kiểm tra trạng thái các hóa đơn phát sinh từ kỳ lưu trú của khách hàng. Quản lý minh bạch toàn bộ các khoản thu phòng và dịch vụ.
                        </p>
                    </div>

                    <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                        <Link
                            to="/host/invoices"
                            style={{
                                textDecoration: 'none',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '6px',
                                background: '#1a2e20',
                                color: '#ffffff',
                                padding: '10px 16px',
                                borderRadius: '10px',
                                fontSize: '13px',
                                fontWeight: 600,
                            }}
                        >
                            <span>Danh sách hóa đơn</span>
                            <span>&rarr;</span>
                        </Link>
                        <Link
                            to="/host/invoices/create"
                            style={{
                                textDecoration: 'none',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '6px',
                                background: '#faf8f5',
                                color: '#1a2e20',
                                border: '1px solid #e9e4db',
                                padding: '10px 14px',
                                borderRadius: '10px',
                                fontSize: '13px',
                                fontWeight: 600,
                            }}
                        >
                            <span>+ Lập hóa đơn</span>
                        </Link>
                    </div>
                </div>

                {/* Thẻ Quản lý Hoa hồng & Đối soát */}
                <div
                    style={{
                        background: '#ffffff',
                        border: '1px solid #e9e4db',
                        borderRadius: '16px',
                        padding: '24px',
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
                                <h2 style={{ fontSize: '20px', fontWeight: 700, margin: 0, color: '#1a2e20' }}>
                                    Hoa hồng & Đối soát
                                </h2>
                                <span style={{ fontSize: '12px', color: '#718096' }}>
                                    Nghĩa vụ 10% nền tảng StayHub
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '20px' }}>
                            Theo dõi các khoản hoa hồng phát sinh sau khi hóa đơn được thanh toán. Xem hướng dẫn nộp tiền ngân hàng với hạn nộp 72 giờ và lịch sử xét duyệt.
                        </p>
                    </div>

                    <div>
                        <Link
                            to="/host/commissions"
                            style={{
                                textDecoration: 'none',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '8px',
                                background: '#785a28',
                                color: '#ffffff',
                                padding: '10px 18px',
                                borderRadius: '10px',
                                fontSize: '13px',
                                fontWeight: 600,
                            }}
                        >
                            <span>Danh sách hoa hồng</span>
                            <span>&rarr;</span>
                        </Link>
                    </div>
                </div>

                {/* Thẻ Quy trình Đơn đặt phòng */}
                <div
                    style={{
                        background: '#ffffff',
                        border: '1px solid #e9e4db',
                        borderRadius: '16px',
                        padding: '24px',
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
                                    background: 'rgba(176, 141, 87, 0.12)',
                                    color: '#785a28',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                            </div>
                            <div>
                                <h2 style={{ fontSize: '20px', fontWeight: 700, margin: 0, color: '#1a2e20' }}>
                                    Đơn đặt phòng
                                </h2>
                                <span style={{ fontSize: '12px', color: '#718096' }}>
                                    Lịch nhận & trả phòng
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '20px' }}>
                            Theo dõi quy trình phục vụ khách lưu trú: tiếp nhận đơn đặt phòng, thực hiện thủ tục nhận phòng, bổ sung dịch vụ và lập hóa đơn thanh toán sau khi hoàn tất kỳ nghỉ.
                        </p>
                    </div>

                    <div>
                        <Link
                            to="/host/invoices/create"
                            style={{
                                textDecoration: 'none',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '8px',
                                background: '#faf8f5',
                                color: '#1a2e20',
                                border: '1px solid #e9e4db',
                                padding: '10px 18px',
                                borderRadius: '10px',
                                fontSize: '13px',
                                fontWeight: 600,
                            }}
                        >
                            <span>Lập hóa đơn theo mã đơn</span>
                            <span>&rarr;</span>
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

export default function HostDashboardPage() {
    const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
    if (!user || isLoggingOut) return null;
    return <Dashboard key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
