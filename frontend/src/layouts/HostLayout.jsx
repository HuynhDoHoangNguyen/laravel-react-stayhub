import { Link, useLocation, Outlet } from 'react-router-dom';
import './HostLayout.css';

function HostLayout() {
    const location = useLocation();
    const currentPath = location.pathname;

    const navItems = [
        {
            to: '/host',
            label: 'Tổng quan',
            exact: true,
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="3" y="3" width="7" height="9" />
                    <rect x="14" y="3" width="7" height="5" />
                    <rect x="14" y="12" width="7" height="9" />
                    <rect x="3" y="16" width="7" height="5" />
                </svg>
            ),
        },
        {
            to: '/host/invoices',
            label: 'Quản lý Hóa đơn',
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                </svg>
            ),
        },
        {
            to: '/host/bookings',
            label: 'Đơn đặt phòng',
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
            ),
        },
        {
            to: '/host/properties',
            label: 'Cơ sở lưu trú',
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
            ),
        },
    ];

    return (
        <div className="host-shell">
            {/* Sidebar điều hướng */}
            <aside className="host-sidebar">
                <Link to="/host" className="host-sidebar-brand">
                    <div className="brand-icon">🌿</div>
                    <div>
                        <div className="brand-text">StayHub</div>
                        <div className="brand-subtext">Host Console</div>
                    </div>
                </Link>

                <nav className="host-sidebar-nav">
                    <div className="nav-section-title">Menu quản lý</div>
                    {navItems.map((item, idx) => {
                        const isActive = item.exact
                            ? currentPath === item.to
                            : currentPath.startsWith(item.to) && item.to !== '#';

                        return (
                            <Link
                                key={idx}
                                to={item.to}
                                className={`host-nav-item ${isActive ? 'active' : ''}`}
                            >
                                {item.icon}
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>

                <div className="host-sidebar-footer">
                    <div className="host-profile-snippet">
                        <div className="host-avatar">H</div>
                        <div className="host-profile-info">
                            <div className="host-name">Đối tác lưu trú</div>
                            <div className="host-role">Quản trị cơ sở</div>
                        </div>
                    </div>
                    <div style={{ marginTop: '8px' }}>
                        <Link
                            to="/"
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                                fontSize: '13px',
                                color: '#718096',
                                textDecoration: 'none',
                                padding: '6px 10px',
                                borderRadius: '6px',
                            }}
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <line x1="19" y1="12" x2="5" y2="12" />
                                <polyline points="12 19 5 12 12 5" />
                            </svg>
                            <span>Về trang chủ StayHub</span>
                        </Link>
                    </div>
                </div>
            </aside>

            {/* Nội dung chính */}
            <div className="host-main-wrapper">
                <header className="host-topbar">
                    <div className="host-topbar-breadcrumb">
                        <span>StayHub</span>
                        {' / '}
                        <span style={{ color: '#1a2e20', fontWeight: 600 }}>Khu vực Đối tác (Host)</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span
                            style={{
                                fontSize: '12px',
                                padding: '4px 10px',
                                borderRadius: '9999px',
                                background: '#ecfdf5',
                                color: '#047857',
                                fontWeight: 600,
                            }}
                        >
                            ● Trực tuyến
                        </span>
                    </div>
                </header>

                <main className="host-content-area">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}

export default HostLayout;