import { Outlet } from 'react-router-dom';

function AdminLayout() {
    return (
        <div>
            <aside>
                <h2>Admin Dashboard</h2>
            </aside>

            <main>
                <Outlet />
            </main>
        </div>
    );
}

export default AdminLayout;