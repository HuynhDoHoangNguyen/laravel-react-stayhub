import { Link } from 'react-router-dom';

function HostDashboardPage() {
    return (
        <div style={{ padding: '24px 16px', maxWidth: '1000px', margin: '0 auto', textAlign: 'left' }}>
            <h1 style={{ fontSize: '28px', color: '#1a2e20', margin: '0 0 16px 0' }}>Host Dashboard</h1>
            <p style={{ color: '#718096', fontSize: '15px', marginBottom: '24px' }}>
                Chào mừng bạn đến với khu vực quản lý dành cho đối tác lưu trú StayHub.
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
                {/* Tiện ích Quản lý Hóa đơn (TV3) */}
                <div
                    style={{
                        background: '#ffffff',
                        border: '1px solid #e5e4e7',
                        borderRadius: '12px',
                        padding: '20px',
                        boxShadow: '0 2px 8px rgba(0,0,0,0.04)',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '12px' }}>
                        <div
                            style={{
                                width: '36px',
                                height: '36px',
                                borderRadius: '8px',
                                background: '#e6fffa',
                                color: '#234e52',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                            </svg>
                        </div>
                        <h2 style={{ fontSize: '18px', margin: 0, color: '#1a202c' }}>Quản lý Hóa đơn</h2>
                    </div>

                    <p style={{ fontSize: '14px', color: '#4a5568', lineHeight: '1.5', marginBottom: '16px' }}>
                        Lập hóa đơn thanh toán cho các đơn đặt phòng đã hoàn tất kỳ nghỉ (COMPLETED).
                    </p>

                    <Link
                        to="/host/invoices/create"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '6px',
                            background: '#1a2e20',
                            color: '#ffffff',
                            textDecoration: 'none',
                            padding: '8px 16px',
                            borderRadius: '6px',
                            fontSize: '14px',
                            fontWeight: 600,
                        }}
                    >
                        <span>Lập hóa đơn mới</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </Link>

                    <div style={{ marginTop: '12px', fontSize: '12px', color: '#a0aec0' }}>
                        * Ghi chú: Nút &quot;Lập hóa đơn&quot; cũng sẵn sàng được nhúng trực tiếp vào danh sách đơn đặt phòng khi TV2 hoàn thiện màn hình Quản lý Booking.
                    </div>
                </div>
            </div>
        </div>
    );
}

export default HostDashboardPage;