import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { RootLayout } from "./components/RootLayout";

import LandingPage from "./routes/index";
import AuthPage from "./routes/auth";
import SignupPage from "./routes/signup";
import ForgotPasswordPage from "./routes/forgot-password";
import ResetPasswordPage from "./routes/reset-password";
import SearchPage from "./routes/search";
import HotelDetailsPage from "./routes/hotels.$hotelId";
import CheckoutPage from "./routes/checkout.$bookingId";
import BookingsIndexPage from "./routes/bookings.index";
import BookingDetailsPage from "./routes/bookings.$bookingId";
import GuestsPage from "./routes/guests";
import WishlistPage from "./routes/wishlist";
import ProfilePage from "./routes/profile";
import PaymentSuccessPage from "./routes/payments.success";
import PaymentFailurePage from "./routes/payments.failure";

import ManageLayout from "./routes/manage";
import ManageHotelsIndex from "./routes/manage.hotels.index";
import ManageHotelsNew from "./routes/manage.hotels.new";
import ManageHotelDetail from "./routes/manage.hotels.$hotelId";
import ManageBookings from "./routes/manage.bookings";
import ManageRefunds from "./routes/manage.refunds";
import ManageReports from "./routes/manage.reports";
import ManageSettings from "./routes/manage.settings";

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<RootLayout />}>
          <Route path="/" element={<LandingPage />} />
          <Route path="/auth" element={<AuthPage />} />
          <Route path="/signup" element={<SignupPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
          <Route path="/search" element={<SearchPage />} />
          <Route path="/hotels/:hotelId" element={<HotelDetailsPage />} />
          <Route path="/checkout/:bookingId" element={<CheckoutPage />} />
          <Route path="/bookings" element={<BookingsIndexPage />} />
          <Route path="/bookings/:bookingId" element={<BookingDetailsPage />} />
          <Route path="/guests" element={<GuestsPage />} />
          <Route path="/wishlist" element={<WishlistPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/payments/success" element={<PaymentSuccessPage />} />
          <Route path="/payments/failure" element={<PaymentFailurePage />} />

          <Route path="/manage" element={<ManageLayout />}>
            <Route index element={<Navigate to="/manage/hotels" replace />} />
            <Route path="hotels" element={<ManageHotelsIndex />} />
            <Route path="hotels/new" element={<ManageHotelsNew />} />
            <Route path="hotels/:hotelId" element={<ManageHotelDetail />} />
            <Route path="bookings" element={<ManageBookings />} />
            <Route path="refunds" element={<ManageRefunds />} />
            <Route path="reports" element={<ManageReports />} />
            <Route path="settings" element={<ManageSettings />} />
          </Route>
        </Route>
      </Routes>
    </BrowserRouter>
  );
}
