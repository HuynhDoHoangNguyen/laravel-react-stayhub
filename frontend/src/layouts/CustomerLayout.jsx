import { Outlet } from 'react-router-dom';

function CustomerLayout() {
    return (
        <div>
            <header>
                <h2>StayHub - Customer</h2>
            </header>

            <main>
                <Outlet />
            </main>
        </div>
    );
}

export default CustomerLayout;