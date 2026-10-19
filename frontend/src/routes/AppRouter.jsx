import { Routes, Route } from 'react-router-dom';

import PublicLayout from '../layouts/PublicLayout';
import CustomerLayout from '../layouts/CustomerLayout';
import HostLayout from '../layouts/HostLayout';
import AdminLayout from '../layouts/AdminLayout';
import ProtectedRoute from './ProtectedRoute';

import HomePage from '../pages/public/HomePage';
import LoginPage from '../pages/public/LoginPage';
import SearchResultsPage from '../pages/public/SearchResultsPage';
import PropertyDetailPage from '../pages/public/PropertyDetailPage';
import ApiTestPage from '../pages/public/ApiTestPage';
import LoginTestPage from '../pages/public/LoginTestPage';

import CustomerDashboardPage from '../pages/customer/CustomerDashboardPage';
import CustomerBookingsPage from '../pages/customer/CustomerBookingsPage';
import CustomerBookingDetailPage from '../pages/customer/CustomerBookingDetailPage';
import CustomerCheckoutPage from '../pages/customer/CustomerCheckoutPage';
import CustomerProfilePage from '../pages/customer/CustomerProfilePage';
import CustomerInvoiceListPage from '../pages/customer/CustomerInvoiceListPage';
import CustomerInvoiceDetailPage from '../pages/customer/CustomerInvoiceDetailPage';
import CustomerReviewsPage from '../pages/customer/CustomerReviewsPage';

import HostDashboardPage from '../pages/host/HostDashboardPage';
import HostInvoiceListPage from '../pages/host/HostInvoiceListPage';
import HostInvoiceDetailPage from '../pages/host/HostInvoiceDetailPage';
import HostInvoiceCreationPage from '../pages/host/HostInvoiceCreationPage';
import HostReviewsPage from '../pages/host/HostReviewsPage';

import AdminDashboardPage from '../pages/admin/AdminDashboardPage';

function AppRouter() {
    return (
        <Routes>
            {/* Public Accessible Routes */}
            <Route element={<PublicLayout />}>
                <Route path="/" element={<HomePage />} />
                <Route path="/search" element={<SearchResultsPage />} />
                <Route path="/properties/:propertyId" element={<PropertyDetailPage />} />
                <Route path="/login" element={<LoginPage />} />
                <Route path="/api-test" element={<ApiTestPage />} />
                {/* Cổng test chỉ khả dụng trong môi trường development, không xuất hiện ở production */}
                {import.meta.env.DEV && (
                    <Route path="/login-test" element={<LoginTestPage />} />
                )}
            </Route>

            {/* Customer Area (Protected: CUSTOMER only) */}
            <Route element={<ProtectedRoute allowedRoles={['CUSTOMER']} />}>
                <Route path="/customer" element={<CustomerLayout />}>
                    <Route index element={<CustomerDashboardPage />} />
                    <Route path="bookings" element={<CustomerBookingsPage />} />
                    <Route path="bookings/:bookingId" element={<CustomerBookingDetailPage />} />
                    <Route path="checkout" element={<CustomerCheckoutPage />} />
                    <Route path="profile" element={<CustomerProfilePage />} />
                    <Route path="invoices" element={<CustomerInvoiceListPage />} />
                    <Route path="invoices/:invoiceId" element={<CustomerInvoiceDetailPage />} />
                    <Route path="reviews" element={<CustomerReviewsPage />} />
                </Route>
            </Route>

            {/* Host Area (Protected: HOST only) */}
            <Route element={<ProtectedRoute allowedRoles={['HOST']} />}>
                <Route path="/host" element={<HostLayout />}>
                    <Route index element={<HostDashboardPage />} />
                    <Route path="invoices" element={<HostInvoiceListPage />} />
                    <Route path="invoices/:invoiceId" element={<HostInvoiceDetailPage />} />
                    <Route path="bookings/:bookingId/create-invoice" element={<HostInvoiceCreationPage />} />
                    <Route path="invoices/create" element={<HostInvoiceCreationPage />} />
                    <Route path="reviews" element={<HostReviewsPage />} />
                </Route>
            </Route>

            {/* Admin Area (Protected: ADMIN only) */}
            <Route element={<ProtectedRoute allowedRoles={['ADMIN']} />}>
                <Route path="/admin" element={<AdminLayout />}>
                    <Route index element={<AdminDashboardPage />} />
                </Route>
            </Route>
        </Routes>
    );
}

export default AppRouter;