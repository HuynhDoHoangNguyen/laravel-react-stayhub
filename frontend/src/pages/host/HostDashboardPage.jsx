import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { hostCommissionService } from '../../services/hostCommissionService';

function formatCurrency(amount) {
    const num = Number(amount) || 0;
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
}

function HostDashboardPage() {
    const [summary, setSummary] = useState(null);
    const [loadingSummary, setLoadingSummary] = useState(true);
    const [summaryError, setSummaryError] = useState(null);

    useEffect(() => {
        let isMounted = true;
        const fetchSummary = async () => {
            try {
                setLoadingSummary(true);
                const res = await hostCommissionService.getFinancialSummary();
                if (isMounted && res.success) {
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

        fetchSummary();
        return () => {
            isMounted = false;
        };
    }, []);

    const totalOpenDue = summary
        ? (Number(summary.commission_due) || 0) + (Number(summary.commission_overdue) || 0) + (Number(summary.commission_under_review) || 0)
        : 0;

    return (
        <div style={{ maxWidth: '1060px', margin: '0 auto', textAlign: 'left' }}>
            {/* Header */}
            <div style={{ marginBottom: '28px' }}>
                <p style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.24em', color: '#785a28', fontWeight: 600, margin: '0 0 6px' }}>
                    Trung tâm điều hành
                </p>
                <h1
                    style={{
                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                        fontSize: '34px',
                        fontWeight: 700,
                        color: '#1a2e20',
                        margin: '0 0 8px 0',
                        letterSpacing: '-0.5px',
                    }}
                >
                    Host Console
                </h1>
                <p style={{ color: '#5c6960', fontSize: '15px', margin: 0 }}>
                    Chào mừng bạn đến với trung tâm quản trị cơ sở lưu trú, tài chính và dịch vụ StayHub.
                </p>
            </div>

            {/* Financial Overview Block */}
            <div style={{ marginBottom: '36px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                    <div>
                        <h2 style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '24px', fontWeight: 700, color: '#1a2e20', margin: 0 }}>
                            Tổng quan tài chính & Doanh thu
                        </h2>
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
                    <div style={{ background: '#fcfbf9', border: '1px solid #e9e4db', borderRadius: '16px', padding: '32px', textAlign: 'center', color: '#718096' }}>
                        Đang tổng hợp số liệu tài chính...
                    </div>
                ) : summaryError ? (
                    <div style={{ background: '#fff5f5', border: '1px solid #fed7d7', borderRadius: '16px', padding: '16px', color: '#c53030', fontSize: '14px' }}>
                        {summaryError}
                    </div>
                ) : summary ? (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))', gap: '16px' }}>
                        {/* Doanh thu thực nhận ước tính */}
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
                                Doanh thu thực nhận (Ước tính)
                            </span>
                            <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '30px', fontWeight: 700, margin: '8px 0 4px', color: '#faf8f5' }}>
                                {formatCurrency(summary.net_revenue_estimate)}
                            </div>
                            <span style={{ fontSize: '12px', color: '#97a89c' }}>
                                Đã trừ 10% hoa hồng trên doanh thu
                            </span>
                        </div>

                        {/* Tổng thu tiền phòng & DV (PAID) */}
                        <div
                            style={{
                                background: '#ffffff',
                                border: '1px solid #e9e4db',
                                borderRadius: '16px',
                                padding: '22px',
                                boxShadow: '0 2px 8px rgba(0,0,0,0.02)',
                            }}
                        >
                            <span style={{ fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.08em', color: '#5c6960', fontWeight: 600 }}>
                                Doanh thu thu trực tiếp
                            </span>
                            <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '26px', fontWeight: 700, margin: '8px 0 4px', color: '#1a2e20' }}>
                                {formatCurrency(summary.gross_revenue)}
                            </div>
                            <span style={{ fontSize: '12px', color: '#718096' }}>
                                Từ {summary.paid_invoices_count || 0} hóa đơn đã thanh toán
                            </span>
                        </div>

                        {/* Hoa hồng đã quyết toán */}
                        <div
                            style={{
                                background: '#ffffff',
                                border: '1px solid #e9e4db',
                                borderRadius: '16px',
                                padding: '22px',
                                boxShadow: '0 2px 8px rgba(0,0,0,0.02)',
                            }}
                        >
                            <span style={{ fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.08em', color: '#276749', fontWeight: 600 }}>
                                Hoa hồng đã nộp (Settled)
                            </span>
                            <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '26px', fontWeight: 700, margin: '8px 0 4px', color: '#276749' }}>
                                {formatCurrency(summary.commission_settled)}
                            </div>
                            <span style={{ fontSize: '12px', color: '#718096' }}>
                                {summary.settled_count || 0} kỳ đã đối soát thành công
                            </span>
                        </div>

                        {/* Hoa hồng cần nộp */}
                        <div
                            style={{
                                background: totalOpenDue > 0 ? '#fffaf0' : '#ffffff',
                                border: totalOpenDue > 0 ? '1px solid #feebc8' : '1px solid #e9e4db',
                                borderRadius: '16px',
                                padding: '22px',
                                boxShadow: '0 2px 8px rgba(0,0,0,0.02)',
                            }}
                        >
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span style={{ fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.08em', color: totalOpenDue > 0 ? '#b7791f' : '#5c6960', fontWeight: 600 }}>
                                    Hoa hồng cần nộp
                                </span>
                                {Number(summary.commission_overdue) > 0 && (
                                    <span style={{ fontSize: '10px', background: '#e53e3e', color: '#fff', padding: '2px 6px', borderRadius: '8px', fontWeight: 700 }}>
                                        Quá hạn
                                    </span>
                                )}
                            </div>
                            <div style={{ fontFamily: "'Cormorant Garamond', Georgia, serif", fontSize: '26px', fontWeight: 700, margin: '8px 0 4px', color: totalOpenDue > 0 ? '#b7791f' : '#1a2e20' }}>
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
                            </div>
                        </div>
                    </div>
                ) : null}
            </div>

            {/* Functional Cards Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(310px, 1fr))', gap: '24px' }}>
                {/* Thẻ Quản lý Hóa đơn */}
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
                                    <polyline points="10 9 9 9 8 9" />
                                </svg>
                            </div>
                            <div>
                                <h2
                                    style={{
                                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                                        fontSize: '22px',
                                        fontWeight: 700,
                                        margin: 0,
                                        color: '#1a2e20',
                                    }}
                                >
                                    Quản lý Hóa đơn
                                </h2>
                                <span style={{ fontSize: '12px', color: '#718096' }}>
                                    Hóa đơn & Thu phí lưu trú
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '24px' }}>
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
                                padding: '12px 18px',
                                borderRadius: '12px',
                                fontSize: '14px',
                                fontWeight: 600,
                            }}
                        >
                            <span>Xem danh sách hóa đơn</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
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
                                padding: '12px 16px',
                                borderRadius: '12px',
                                fontSize: '14px',
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
                                <h2
                                    style={{
                                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                                        fontSize: '22px',
                                        fontWeight: 700,
                                        margin: 0,
                                        color: '#1a2e20',
                                    }}
                                >
                                    Hoa hồng & Đối soát
                                </h2>
                                <span style={{ fontSize: '12px', color: '#718096' }}>
                                    Nghĩa vụ 10% nền tảng StayHub
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '24px' }}>
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
                                padding: '12px 20px',
                                borderRadius: '12px',
                                fontSize: '14px',
                                fontWeight: 600,
                            }}
                        >
                            <span>Danh sách hoa hồng</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </Link>
                    </div>
                </div>

                {/* Thẻ Quy trình Đơn đặt phòng */}
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
                                <h2
                                    style={{
                                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                                        fontSize: '22px',
                                        fontWeight: 700,
                                        margin: 0,
                                        color: '#1a2e20',
                                    }}
                                >
                                    Đơn đặt phòng
                                </h2>
                                <span style={{ fontSize: '12px', color: '#718096' }}>
                                    Lịch nhận & trả phòng
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '24px' }}>
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
                                padding: '12px 20px',
                                borderRadius: '12px',
                                fontSize: '14px',
                                fontWeight: 600,
                            }}
                        >
                            <span>Lập hóa đơn theo mã đơn</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default HostDashboardPage;
