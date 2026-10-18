import { Link } from 'react-router-dom';

function CustomerDashboardPage() {
    return (
        <div style={{ maxWidth: '960px', margin: '0 auto', textAlign: 'left' }}>
            {/* Header */}
            <div style={{ marginBottom: '28px' }}>
                <p style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.24em', color: '#785a28', fontWeight: 600, margin: '0 0 6px' }}>
                    Tài khoản hội viên
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
                    Xin chào Quý khách
                </h1>
                <p style={{ color: '#5c6960', fontSize: '15px', margin: 0 }}>
                    Chào mừng bạn đến với trang quản lý tài khoản cá nhân tại StayHub.
                </p>
            </div>

            {/* Quick Cards Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
                {/* Thẻ Hóa đơn & Lịch sử thanh toán */}
                <div
                    style={{
                        background: '#ffffff',
                        border: '1px solid #e9e4db',
                        borderRadius: '16px',
                        padding: '24px',
                        boxShadow: '0 4px 16px rgba(17, 26, 19, 0.03)',
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'space-between',
                    }}
                >
                    <div>
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
                                marginBottom: '14px',
                            }}
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                            </svg>
                        </div>
                        <h2
                            style={{
                                fontFamily: "'Cormorant Garamond', Georgia, serif",
                                fontSize: '22px',
                                fontWeight: 700,
                                margin: '0 0 6px',
                                color: '#1a2e20',
                            }}
                        >
                            Hóa đơn của tôi
                        </h2>
                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '20px' }}>
                            Xem lại các hóa đơn thanh toán tiền phòng và các dịch vụ bổ sung trong các kỳ nghỉ của bạn.
                        </p>
                    </div>

                    <Link
                        to="/customer/invoices"
                        className="btn-invoice-action"
                        style={{
                            textDecoration: 'none',
                            display: 'inline-flex',
                            width: 'fit-content',
                        }}
                    >
                        <span>Xem danh sách hóa đơn</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </Link>
                </div>

                {/* Thẻ Khám phá chỗ nghỉ */}
                <div
                    style={{
                        background: '#ffffff',
                        border: '1px solid #e9e4db',
                        borderRadius: '16px',
                        padding: '24px',
                        boxShadow: '0 4px 16px rgba(17, 26, 19, 0.03)',
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'space-between',
                    }}
                >
                    <div>
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
                                marginBottom: '14px',
                            }}
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="10" />
                                <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" />
                            </svg>
                        </div>
                        <h2
                            style={{
                                fontFamily: "'Cormorant Garamond', Georgia, serif",
                                fontSize: '22px',
                                fontWeight: 700,
                                margin: '0 0 6px',
                                color: '#1a2e20',
                            }}
                        >
                            Khám phá kỳ nghỉ
                        </h2>
                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '20px' }}>
                            Tìm kiếm khách sạn và homestay cao cấp với không gian nghỉ dưỡng thanh lịch và dịch vụ chuẩn mực.
                        </p>
                    </div>

                    <Link
                        to="/"
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
                            width: 'fit-content',
                        }}
                    >
                        <span>Tìm chỗ nghỉ mới</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>
    );
}

export default CustomerDashboardPage;