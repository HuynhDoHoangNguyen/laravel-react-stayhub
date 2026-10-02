
import { Outlet } from 'react-router-dom';

function PublicLayout() {
    return (
        <div>
            <header>
                <h2>StayHub - Public</h2>
            </header>

            <main>
                <Outlet />
            </main>
        </div>
    );
}

export default PublicLayout;