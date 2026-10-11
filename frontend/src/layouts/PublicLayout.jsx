
import { Outlet, Link } from 'react-router-dom';

function PublicLayout() {
    return (
        <div>
            <header style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h2 style={{ margin: 0 }}>StayHub</h2>
                {/* TEMP-M2-WIRING */}
                <nav>
                    <Link to="/" className="bk-nav-link">Trang chủ</Link>
                    <Link to="/search" className="bk-nav-link">🔍 Tìm phòng</Link>
                    <Link to="/my-bookings" className="bk-nav-link">📦 Đặt phòng của tôi</Link>
                    <Link to="/login-test" className="bk-nav-link">🔑 Đăng nhập (Test)</Link>
                </nav>
            </header>

            <main>
                <Outlet />
            </main>
        </div>
    );
}

export default PublicLayout;