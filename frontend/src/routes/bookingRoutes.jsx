import { Route } from 'react-router-dom';

import SearchResultPage from '../pages/public/SearchResultPage';
import BookingCheckoutPage from '../pages/customer/BookingCheckoutPage';
import MyBookingsPage from '../pages/customer/MyBookingsPage';
import BookingDetailPage from '../pages/customer/BookingDetailPage';

import HostBookingListPage from '../pages/host/HostBookingListPage';
import HostBookingDetailPage from '../pages/host/HostBookingDetailPage';
import HostBookingServicesPage from '../pages/host/HostBookingServicesPage';
import HostServiceListPage from '../pages/host/HostServiceListPage';

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
        <Route path="/booking/checkout" element={<BookingCheckoutPage />} />
        <Route path="/customer/bookings" element={<MyBookingsPage />} />
        <Route path="/customer/bookings/:bookingId" element={<BookingDetailPage />} />
    </>
);

/**
 * Host Booking & Service routes for Member 2 (exported as JSX Fragment, not a component)
 * Nested under /host layout
 */
export const hostBookingRoutes = (
    <>
        <Route path="bookings" element={<HostBookingListPage />} />
        <Route path="bookings/:bookingId" element={<HostBookingDetailPage />} />
        <Route path="bookings/:bookingId/services" element={<HostBookingServicesPage />} />
        {/* TEMP-M2-WIRING: redirect legacy entry to the host's first property. */}
        <Route path="services" element={<HostServiceListPage />} />
        <Route path="properties/:propertyId/services" element={<HostServiceListPage />} />
    </>
);
