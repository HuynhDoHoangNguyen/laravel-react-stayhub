import { Outlet, Link } from 'react-router-dom';

function CustomerLayout() {
    return (
        <div className="min-h-screen">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b bg-background px-4 py-3">
                <h2 className="shrink-0 font-display text-xl text-primary">StayHub · Khách hàng</h2>
                {/* TEMP-M2-WIRING */}
                <nav aria-label="Điều hướng khách hàng" className="flex max-w-full flex-wrap gap-1 overflow-x-auto sm:gap-2">
                    <Link to="/search" className="inline-flex min-h-11 items-center rounded-lg px-3 text-sm hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Tìm phòng</Link>
                    <Link to="/customer/bookings" className="inline-flex min-h-11 items-center rounded-lg px-3 text-sm hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Đặt phòng của tôi</Link>
                    <Link to="/customer" className="inline-flex min-h-11 items-center rounded-lg px-3 text-sm hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Bảng điều khiển</Link>
                </nav>
            </header>

            <main className="min-w-0">
                <Outlet />
            </main>
        </div>
    );
}

export default CustomerLayout;
