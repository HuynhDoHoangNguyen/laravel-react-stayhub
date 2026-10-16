# Bàn giao frontend Member 2

## Phạm vi và route giao diện

| Route | Trang |
|---|---|
| `/search` | Tìm phòng và lọc theo query string |
| `/booking/checkout` | Xem báo giá và tạo booking |
| `/customer/bookings` | Lịch sử booking khách hàng |
| `/customer/bookings/:bookingId` | Chi tiết, hủy booking |
| `/host/bookings` | Danh sách, lọc, xác nhận/từ chối booking |
| `/host/bookings/:bookingId` | Chi tiết và workflow booking host |
| `/host/bookings/:bookingId/services` | Quản lý dịch vụ phát sinh trong booking |
| `/host/properties/:propertyId/services` | Danh mục dịch vụ của một cơ sở |

`/host/services` là route tương thích tạm thời: lấy cơ sở đầu tiên từ `GET /api/host/properties` rồi điều hướng sang `/host/properties/:propertyId/services`. Nhóm trưởng cần xác nhận route chuẩn và quyết định khi nào bỏ alias này.

## Service frontend

- `searchService.js`, `availabilityService.js`: tìm phòng và kiểm tra khả dụng.
- `bookingService.js`: preview, tạo/hủy booking, danh sách/chi tiết khách hàng.
- `hostBookingService.js`: danh sách/chi tiết và confirm, reject, check-in, check-out.
- `bookingServiceUsageService.js`: danh sách/thêm/sửa/xóa dịch vụ đã dùng trong booking.
- `propertyServiceCatalogService.js`: danh sách cơ sở, danh mục dịch vụ, tạo/sửa và bật/tắt dịch vụ.

Các service giữ nguyên API contract trong `docs/api/booking-module.md`; không gửi giá hoặc tổng tiền client tự tính làm dữ liệu chính thức.

## Gói giao diện

- Tailwind: `tailwindcss`, `@tailwindcss/vite`, `tw-animate-css` cho utility CSS, tích hợp Vite và animation.
- Class helpers: `clsx`, `tailwind-merge`, `class-variance-authority` cho ghép class và biến thể component.
- Icon/toast: `lucide-react`, `sonner`.
- Primitive shadcn/Radix đang dùng: `@radix-ui/react-slot`, `@radix-ui/react-alert-dialog`, `@radix-ui/react-dialog`, `@radix-ui/react-select`, `@radix-ui/react-popover`, `@radix-ui/react-tabs`, `@radix-ui/react-label`, `@radix-ui/react-separator`, `@radix-ui/react-checkbox`, `@radix-ui/react-switch`, `@radix-ui/react-slider`.
- Ngày: `react-day-picker`, `date-fns`.

Không cài Framer Motion, React Hook Form, Zod, Recharts, Embla, Vaul, Cmdk hoặc Input OTP cho phần giao diện này.

## TEMP-M2-WIRING cần thay/gỡ khi tích hợp nhóm

- `frontend/src/App.jsx`: mount `<Toaster />` một lần; giữ nếu app chưa có toast provider chung.
- `frontend/src/routes/AppRouter.jsx`: ghép các route booking vào router chính; Thành viên 1/nhóm trưởng thay bằng route tree và auth/role guard dùng chung khi bàn giao.
- `frontend/src/routes/bookingRoutes.jsx`: module route Member 2; giữ các route booking, chỉ bỏ wiring alias `/host/services` khi nhóm thống nhất không cần tương thích.
- `frontend/src/layouts/PublicLayout.jsx`, `CustomerLayout.jsx`, `HostLayout.jsx`: điều hướng tạm. Thay bằng layout chung và auth navigation sau khi Thành viên 1/nhóm trưởng bàn giao; `HostLayout` hiện có link Booking và Dịch vụ.
- `frontend/src/pages/host/HostServiceListPage.jsx`: alias `/host/services` chọn cơ sở đầu tiên qua endpoint `GET /api/host/properties`, endpoint được tài liệu backend đánh dấu `TEMP-M1-STUB`. Thay phần lấy danh sách bằng service chính thức của Thành viên 1 nếu response contract thay đổi.
- `frontend/src/pages/public/LoginTestPage.jsx` và route `/login-test`: màn kiểm tra đăng nhập tạm đang được layout public liên kết. Thay link này bằng luồng đăng nhập thật sau khi Thành viên 1/nhóm trưởng bàn giao auth.
- `backend/routes/api.php`: comment `TEMP-M2-WIRING` quanh include route booking; giữ include hoặc chuyển theo quy ước router chung của nhóm.
- `backend/bootstrap/app.php`: middleware role alias `TEMP-M2-WIRING`; đối chiếu middleware chính thức của Thành viên 1 khi nhận code Identity.
- `frontend/src/pages/customer/BookingDetailPage.jsx` và `frontend/src/pages/host/HostBookingDetailPage.jsx`: comment chờ Thành viên 3 gắn action xem/tạo hóa đơn theo contract Invoice.

`AvailabilityChecker.jsx` và `availabilityService.js` đã có nhưng chưa được gắn vào route vì hiện chưa có trang chi tiết phòng trong router Member 2; nối khi trang phòng được bàn giao, không tự thêm route phòng ở đợt dọn dẹp này.

## Kiểm tra giao diện

Sau rà soát, `frontend/src` không còn tên StayNest, thuật ngữ hotel/manager, giá INR/Stripe/Cloudinary/India, import runtime từ `docs/`, `bk-`, import `styles/booking.css`, `console.log` hoặc dữ liệu mẫu trong component. Layout và bảng có cuộn/wrap cho màn nhỏ; trang dùng Tailwind và shared loading/empty/error state.

Lệnh bàn giao cần chạy trước khi merge: `php artisan test` tại `backend/`, `npm run lint` và `npm run build` tại `frontend/`.
