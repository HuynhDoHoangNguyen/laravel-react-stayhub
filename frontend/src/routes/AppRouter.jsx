import { Routes, Route } from 'react-router-dom';

import PublicLayout from '../layouts/PublicLayout';
import CustomerLayout from '../layouts/CustomerLayout';
import HostLayout from '../layouts/HostLayout';
import AdminLayout from '../layouts/AdminLayout';

import HomePage from '../pages/public/HomePage';
import CustomerDashboardPage from '../pages/customer/CustomerDashboardPage';
import CustomerInvoiceListPage from '../pages/customer/CustomerInvoiceListPage';
import CustomerInvoiceDetailPage from '../pages/customer/CustomerInvoiceDetailPage';
import HostDashboardPage from '../pages/host/HostDashboardPage';
import HostInvoiceListPage from '../pages/host/HostInvoiceListPage';
import HostInvoiceDetailPage from '../pages/host/HostInvoiceDetailPage';
import HostInvoiceCreationPage from '../pages/host/HostInvoiceCreationPage';
import AdminDashboardPage from '../pages/admin/AdminDashboardPage';

import ApiTestPage from '../pages/public/ApiTestPage';
import LoginTestPage from '../pages/public/LoginTestPage';

function AppRouter() {
    return (
        <Routes>
            {/* Public */}
            <Route element={<PublicLayout />}>
                <Route path="/" element={<HomePage />} />
                <Route path="/api-test" element={<ApiTestPage />} />
                <Route path="/login-test" element={<LoginTestPage />} />
            </Route>

            {/* Customer */}
            <Route path="/customer" element={<CustomerLayout />}>
                <Route index element={<CustomerDashboardPage />} />
                <Route path="invoices" element={<CustomerInvoiceListPage />} />
                <Route path="invoices/:invoiceId" element={<CustomerInvoiceDetailPage />} />
            </Route>

            {/* Host */}
            <Route path="/host" element={<HostLayout />}>
                <Route index element={<HostDashboardPage />} />
                <Route path="invoices" element={<HostInvoiceListPage />} />
                <Route path="invoices/:invoiceId" element={<HostInvoiceDetailPage />} />
                <Route path="bookings/:bookingId/create-invoice" element={<HostInvoiceCreationPage />} />
                <Route path="invoices/create" element={<HostInvoiceCreationPage />} />
            </Route>

            {/* Admin */}
            <Route path="/admin" element={<AdminLayout />}>
                <Route index element={<AdminDashboardPage />} />
            </Route>
        </Routes>
    );
}

export default AppRouter;