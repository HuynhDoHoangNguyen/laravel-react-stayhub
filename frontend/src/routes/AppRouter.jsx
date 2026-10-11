import { Routes, Route } from 'react-router-dom';

import PublicLayout from '../layouts/PublicLayout';
import CustomerLayout from '../layouts/CustomerLayout';
import HostLayout from '../layouts/HostLayout';
import AdminLayout from '../layouts/AdminLayout';

import HomePage from '../pages/public/HomePage';
import CustomerDashboardPage from '../pages/customer/CustomerDashboardPage';
import HostDashboardPage from '../pages/host/HostDashboardPage';
import AdminDashboardPage from '../pages/admin/AdminDashboardPage';

import ApiTestPage from '../pages/public/ApiTestPage';
import LoginTestPage from '../pages/public/LoginTestPage';

// TEMP-M2-WIRING
import {
    publicBookingRoutes,
    customerBookingRoutes,
    hostBookingRoutes,
} from './bookingRoutes';

function AppRouter() {
    return (
        <Routes>
            {/* Public */}
            <Route element={<PublicLayout />}>
                <Route path="/" element={<HomePage />} />
                <Route path="/api-test" element={<ApiTestPage />} />
                <Route path="/login-test" element={<LoginTestPage />} />
                {/* TEMP-M2-WIRING */}
                {publicBookingRoutes}
            </Route>

            {/* Customer */}
            <Route path="/customer" element={<CustomerLayout />}>
                <Route index element={<CustomerDashboardPage />} />
            </Route>
            {/* TEMP-M2-WIRING */}
            <Route element={<CustomerLayout />}>
                {customerBookingRoutes}
            </Route>

            {/* Host */}
            <Route path="/host" element={<HostLayout />}>
                <Route index element={<HostDashboardPage />} />
                {/* TEMP-M2-WIRING */}
                {hostBookingRoutes}
            </Route>

            {/* Admin */}
            <Route path="/admin" element={<AdminLayout />}>
                <Route index element={<AdminDashboardPage />} />
            </Route>
        </Routes>
    );
}

export default AppRouter;