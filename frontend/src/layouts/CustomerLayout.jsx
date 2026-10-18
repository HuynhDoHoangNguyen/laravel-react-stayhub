import { Link, NavLink, Outlet } from 'react-router-dom';

function CustomerLayout() {
    return (
        <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', background: '#faf8f5' }}>
            {/* Top Navigation */}
            <header
                style={{
                    background: '#ffffff',
                    borderBottom: '1px solid #e9e4db',
                    position: 'sticky',
                    top: 0,
                    zIndex: 40,
                    boxShadow: '0 2px 10px rgba(17, 26, 19, 0.03)',
                }}
            >
                <div
                    style={{
                        maxWidth: '1200px',
                        margin: '0 auto',
                        padding: '0 24px',
                        height: '70px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    {/* Brand */}
                    <Link
                        to="/"
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '10px',
                            textDecoration: 'none',
                        }}
                    >
                        <div
                            style={{
                                width: '36px',
                                height: '36px',
                                borderRadius: '10px',
                                background: '#1a2e20',
                                color: '#fdfaf6',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: '18px',
                            }}
                        >
                            🌿
                        </div>
                        <div>
                            <span
                                style={{
                                    fontFamily: "'Cormorant Garamond', Georgia, serif",
                                    fontSize: '24px',
                                    fontWeight: 700,
                                    color: '#1a2e20',
                                    letterSpacing: '-0.3px',
                                }}
                            >
                                StayHub
                            </span>
                        </div>
                    </Link>

                    {/* Nav Links */}
                    <nav style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
                        <NavLink
                            to="/"
                            style={({ isActive }) => ({
                                textDecoration: 'none',
                                fontSize: '14px',
                                fontWeight: isActive ? 600 : 500,
                                color: isActive ? '#1a2e20' : '#718096',
                                padding: '8px 12px',
                                borderRadius: '8px',
                                transition: 'all 0.15s',
                            })}
                        >
                            Trang chủ
                        </NavLink>
                        <NavLink
                            to="/customer"
                            end
                            style={({ isActive }) => ({
                                textDecoration: 'none',
                                fontSize: '14px',
                                fontWeight: isActive ? 600 : 500,
                                color: isActive ? '#1a2e20' : '#718096',
                                padding: '8px 12px',
                                borderRadius: '8px',
                                transition: 'all 0.15s',
                            })}
                        >
                            Tổng quan
                        </NavLink>
                        <NavLink
                            to="/customer/invoices"
                            style={({ isActive }) => ({
                                textDecoration: 'none',
                                fontSize: '14px',
                                fontWeight: isActive ? 600 : 500,
                                color: isActive ? '#1a2e20' : '#718096',
                                padding: '8px 12px',
                                borderRadius: '8px',
                                transition: 'all 0.15s',
                            })}
                        >
                            Hóa đơn của tôi
                        </NavLink>
                    </nav>

                    {/* Member Profile Badge */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div
                            style={{
                                width: '36px',
                                height: '36px',
                                borderRadius: '50%',
                                background: 'rgba(176, 141, 87, 0.15)',
                                color: '#785a28',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontWeight: 600,
                                fontSize: '14px',
                            }}
                        >
                            K
                        </div>
                        <div style={{ display: 'none', textAlign: 'left', lineHeight: 1.2 }}>
                            <div style={{ fontSize: '13px', fontWeight: 600, color: '#1a2e20' }}>Khách hàng</div>
                        </div>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main style={{ flex: 1, maxWidth: '1200px', width: '100%', margin: '0 auto', padding: '32px 24px', boxSizing: 'border-box' }}>
                <Outlet />
            </main>

            {/* Footer */}
            <footer style={{ background: '#ffffff', borderTop: '1px solid #e9e4db', padding: '24px', textAlign: 'center', fontSize: '13px', color: '#718096' }}>
                <div style={{ maxWidth: '1200px', margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span style={{ fontWeight: 600, color: '#1a2e20' }}>StayHub</span>
                        <span>— Trải nghiệm kỳ nghỉ thanh lịch</span>
                    </div>
                    <div>Hệ thống quản lý đặt phòng & dịch vụ lưu trú</div>
                </div>
            </footer>
        </div>
    );
}

export default CustomerLayout;