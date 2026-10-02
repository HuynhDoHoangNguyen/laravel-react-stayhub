import { Outlet } from 'react-router-dom';

function HostLayout() {
    return (
        <div>
            <aside>
                <h2>Host Dashboard</h2>
            </aside>

            <main>
                <Outlet />
            </main>
        </div>
    );
}

export default HostLayout;