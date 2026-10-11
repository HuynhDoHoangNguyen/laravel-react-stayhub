import { Outlet, Link } from 'react-router-dom';

function HostLayout() {
    return (
        <div>
            <header style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h2 style={{ margin: 0 }}>StayHub - Dành cho Chủ nhà (Host)</h2>
                {/* TEMP-M2-WIRING */}
                <nav>
                    <Link to="/host" className="bk-nav-link">Tổng quan</Link>
                    <Link to="/host/bookings" className="bk-nav-link">📋 Quản lý đặt phòng</Link>
                    <Link to="/host/services" className="bk-nav-link">🛎️ Dịch vụ cơ sở</Link>
                    <Link to="/search" className="bk-nav-link">🔍 Tìm phòng (Khách)</Link>
                </nav>
            </header>

            <main>
                <Outlet />
            </main>
        </div>
    );
}

export default HostLayout;