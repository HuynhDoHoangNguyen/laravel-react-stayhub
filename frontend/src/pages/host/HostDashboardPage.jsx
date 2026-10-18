import { Link } from 'react-router-dom';

function HostDashboardPage() {
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
                    Chào mừng bạn đến với trung tâm quản trị cơ sở lưu trú và dịch vụ StayHub.
                </p>
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
                            className="btn-invoice-action"
                            style={{
                                textDecoration: 'none',
                                display: 'inline-flex',
                                width: 'auto',
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