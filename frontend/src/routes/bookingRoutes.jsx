import { Route } from 'react-router-dom';
import '../styles/booking.css';

import SearchResultPage from '../pages/public/SearchResultPage';
import BookingCheckoutPage from '../pages/customer/BookingCheckoutPage';
import MyBookingsPage from '../pages/customer/MyBookingsPage';
import BookingDetailPage from '../pages/customer/BookingDetailPage';

import HostBookingListPage from '../pages/host/HostBookingListPage';
import HostBookingDetailPage from '../pages/host/HostBookingDetailPage';
import HostBookingServicesPage from '../pages/host/HostBookingServicesPage';
import HostServiceListPage from '../pages/host/HostServiceListPage';
import HostServiceFormPage from '../pages/host/HostServiceFormPage';

/**
 * Public Booking routes (exported as JSX Fragment, not a component)
 */
export const publicBookingRoutes = (
    <>
        <Route path="/search" element={<SearchResultPage />} />
    </>
);

/**
 * Customer Booking routes (exported as JSX Fragment, not a component)
 */
export const customerBookingRoutes = (
    <>
        <Route path="/bookings/create" element={<BookingCheckoutPage />} />
        <Route path="/my-bookings" element={<MyBookingsPage />} />
        <Route path="/my-bookings/:id" element={<BookingDetailPage />} />
    </>
);

/**
 * Host Booking & Service routes for Member 2 (exported as JSX Fragment, not a component)
 * Nested under /host layout
 */
export const hostBookingRoutes = (
    <>
        <Route path="bookings" element={<HostBookingListPage />} />
        <Route path="bookings/:id" element={<HostBookingDetailPage />} />
        <Route path="bookings/:id/services" element={<HostBookingServicesPage />} />
        <Route path="services" element={<HostServiceListPage />} />
        <Route path="services/create" element={<HostServiceFormPage />} />
        <Route path="services/:id/edit" element={<HostServiceFormPage />} />
    </>
);
