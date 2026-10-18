import { Link } from 'react-router-dom';

function HostDashboardPage() {
    return (
        <div style={{ maxWidth: '1000px', margin: '0 auto', textAlign: 'left' }}>
            {/* Header */}
            <div style={{ marginBottom: '28px' }}>
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

            {/* Quick Action Cards Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '24px' }}>
                {/* Tiện ích Quản lý Hóa đơn (TV3) */}
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
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '14px' }}>
                            <div
                                style={{
                                    width: '40px',
                                    height: '40px',
                                    borderRadius: '10px',
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
                                <h2
                                    style={{
                                        fontFamily: "'Cormorant Garamond', Georgia, serif",
                                        fontSize: '20px',
                                        fontWeight: 700,
                                        margin: 0,
                                        color: '#1a2e20',
                                    }}
                                >
                                    Quản lý Hóa đơn
                                </h2>
                                <span style={{ fontSize: '11px', color: '#b08d57', fontWeight: 600, textTransform: 'uppercase' }}>
                                    Module TV3
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '20px' }}>
                            Lập hóa đơn thanh toán cho các đơn đặt phòng đã hoàn tất kỳ lưu trú (COMPLETED). Toàn bộ chi phí được tính toán chính xác từ snapshot hệ thống.
                        </p>
                    </div>

                    <div>
                        <Link
                            to="/host/invoices/create"
                            className="btn-invoice-action"
                            style={{
                                textDecoration: 'none',
                                display: 'inline-flex',
                                width: 'auto',
                            }}
                        >
                            <span>Lập hóa đơn mới</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </Link>

                        <div style={{ marginTop: '14px', fontSize: '12px', color: '#718096', fontStyle: 'italic' }}>
                            * Khi TV2 hoàn thiện danh sách đơn đặt phòng, nút lập hóa đơn sẽ xuất hiện trực tiếp tại từng dòng booking.
                        </div>
                    </div>
                </div>

                {/* Thẻ Quản lý Đơn đặt phòng (TV2 Placeholder) */}
                <div
                    style={{
                        background: '#faf8f5',
                        border: '1px dashed #d5cfc5',
                        borderRadius: '16px',
                        padding: '24px',
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'space-between',
                        opacity: 0.85,
                    }}
                >
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '14px' }}>
                            <div
                                style={{
                                    width: '40px',
                                    height: '40px',
                                    borderRadius: '10px',
                                    background: 'rgba(176, 141, 87, 0.15)',
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
                                        fontSize: '20px',
                                        fontWeight: 700,
                                        margin: 0,
                                        color: '#1a2e20',
                                    }}
                                >
                                    Đơn đặt phòng
                                </h2>
                                <span style={{ fontSize: '11px', color: '#718096', fontWeight: 600, textTransform: 'uppercase' }}>
                                    Module TV2 (Đang hoàn thiện)
                                </span>
                            </div>
                        </div>

                        <p style={{ fontSize: '14px', color: '#5c6960', lineHeight: '1.6', marginBottom: '20px' }}>
                            Quản lý quy trình tiếp nhận khách: xác nhận đơn, check-in nhận phòng, cung cấp dịch vụ bổ sung và check-out trả phòng.
                        </p>
                    </div>

                    <div style={{ fontSize: '13px', color: '#718096' }}>
                        Trạng thái: Đang chờ tích hợp API từ TV2.
                    </div>
                </div>
            </div>
        </div>
    );
}

export default HostDashboardPage;