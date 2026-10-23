Dự án StayHub (Laravel 12 + Sanctum SPA + MySQL; React 19 + Vite + React Router 7 + Axios). Tôi là THÀNH VIÊN 2, làm độc lập, phụ trách: Search, Availability, Booking, Booking Workflow, Services (bảng services, bookings, booking_services). Repo hiện đã có code nền và dữ liệu mẫu của TV1, TV2, TV3; cần kiểm tra code thực tế trước khi kết luận trạng thái bàn giao giữa các thành viên.

Tài liệu (chỉ đọc mục liên quan khi cần): docs/stayhub-team-work-assignment.md mục 4; docs/stayhub-erd.md (services, bookings, booking_services, Business rules); docs/stayhub-usecase-system.md mục 4.4-4.6, 6-9; docs/stayhub-screen-mockups.md mục 2.2, 2.5-2.7, 4, 6, 7; docs/api/booking-module.md.

## Trạng thái hiện tại
- **Backend B1 - B5**: Đã hoàn thành 100%.
- **Kiểm thử Backend (lần xác minh lịch sử)**: 83 tests (435 assertions) đã pass ngày 13/10/2026 theo ghi nhận cuối file. Chưa xác nhận lại bằng lần chạy mới trong phiên cập nhật context này; chạy `php artisan test` để biết trạng thái hiện tại.
- **Danh sách 22 endpoint (rút gọn)**:
  - *Public (3)*:
    - `GET  /api/public/rooms/search`: Tìm kiếm phòng trống (lọc ngày, khách, tiện nghi, giá, từ khóa).
    - `GET  /api/public/rooms/{room}/availability`: Kiểm tra tình trạng phòng & các khoảng ngày bị chặn.
    - `POST /api/public/bookings/preview`: Báo giá phòng trước khi đặt (tính số đêm, tiền phòng snapshot).
  - *Customer (5)*:
    - `POST /api/customer/bookings`: Tạo đơn đặt phòng (giao dịch an toàn, lockForUpdate, mã BK unique).
    - `GET  /api/customer/bookings`: Lịch sử đặt phòng cá nhân (lọc status, phân trang).
    - `GET  /api/customer/bookings/{booking}`: Chi tiết đặt phòng & tổng tiền dịch vụ (IDOR -> 404).
    - `POST /api/customer/bookings/{booking}/cancel`: Hủy đặt phòng (PENDING tự do, CONFIRMED trước hạn).
    - `GET  /api/customer/bookings/{booking}/services`: Danh sách dịch vụ đã dùng trong đặt phòng.
  - *Host Services (4)*:
    - `GET   /api/host/services`: Danh sách dịch vụ cơ sở lưu trú của host (phân trang).
    - `POST  /api/host/services`: Tạo dịch vụ mới cho cơ sở lưu trú.
    - `PUT   /api/host/services/{service}`: Cập nhật thông tin/giá dịch vụ cơ sở lưu trú.
    - `PATCH /api/host/services/{service}/status`: Bật/tắt trạng thái dịch vụ (không xóa cứng).
  - *Host Booking Workflow (6)*:
    - `GET  /api/host/bookings`: Danh sách đặt phòng của các cơ sở thuộc host quản lý.
    - `GET  /api/host/bookings/{booking}`: Chi tiết đặt phòng phía host (IDOR -> 404).
    - `POST /api/host/bookings/{booking}/confirm`: Xác nhận booking (PENDING -> CONFIRMED, check lại phòng).
    - `POST /api/host/bookings/{booking}/reject`: Từ chối booking (PENDING -> REJECTED, ghi lý do vào note).
    - `POST /api/host/bookings/{booking}/check-in`: Nhận phòng (CONFIRMED -> CHECKED_IN).
    - `POST /api/host/bookings/{booking}/check-out`: Trả phòng (CHECKED_IN -> COMPLETED, không tạo invoice).
  - *Host Booking Services (4)*:
    - `GET    /api/host/bookings/{booking}/services`: Danh sách dịch vụ trong booking.
    - `POST   /api/host/bookings/{booking}/services`: Thêm dịch vụ vào booking (chỉ khi CHECKED_IN).
    - `PUT    /api/host/bookings/{booking}/services/{bookingService}`: Sửa số lượng dịch vụ (route lồng -> 404).
    - `DELETE /api/host/bookings/{booking}/services/{bookingService}`: Xóa dịch vụ khỏi booking (route lồng -> 404).
- **Seeder demo & Tài khoản demo** (`DatabaseSeeder` gọi ba seeder theo chủ sở hữu):
  - Mật khẩu chung: `12345678`
  - Admin: `admin@stayhub.com`
  - Host 1: `host1@stayhub.com` (quản lý StayHub Central & Homestay Dalat Cloud)
  - Host 2: `host2@stayhub.com` (quản lý Ocean Resort Nha Trang)
  - Customer 1: `customer1@stayhub.com`
  - Customer 2: `customer2@stayhub.com`
  - Customer 3: `customer3@stayhub.com`
  - Customer 4 (BLOCKED): `customer4@stayhub.com`
  - Customer 5 (INACTIVE): `customer5@stayhub.com`
  - Chi tiết trạng thái, số bản ghi và kịch bản: `docs/seed-data.md`.

## Trạng thái Frontend TV2
- Các luồng giao diện TV2 đã được nối với domain service và API booking hiện có: tìm phòng, xem thông tin phòng/kiểm tra availability, preview và tạo booking, lịch sử/chi tiết/hủy booking phía Customer; danh sách/chi tiết và workflow confirm/reject/check-in/check-out phía Host; danh mục dịch vụ Host và dịch vụ phát sinh trong booking.
- Route thực tế trong `frontend/src/routes/AppRouter.jsx`: `/search`, `/properties/:propertyId`, `/customer/checkout`, `/customer/bookings`, `/customer/bookings/:bookingId`, `/host/bookings`, `/host/bookings/:bookingId`, `/host/bookings/:bookingId/services`, `/host/services`, `/host/properties/:propertyId/services`.
- Availability được dùng trong tìm kiếm và bước preview booking. `GET /api/public/rooms/{room}/availability` có trong API; cần kiểm tra lại UI hiện hành nếu yêu cầu một màn hình availability độc lập.
- Checkout lấy báo giá phòng từ API preview; frontend không tự tính tổng tiền chính thức. Backend không nhận số điện thoại riêng trong payload tạo booking, nên checkout không bắt nhập số điện thoại tùy ý.
- Khi chạy UI với API thật, đặt `VITE_ENABLE_PROPERTY_MOCKS=false` và `VITE_ENABLE_BOOKING_MOCKS=false` trong `frontend/.env`; đặt `VITE_BACKEND_URL=http://localhost:8000`. `frontend/.env.example` đang bật hai mock flag, cần chú ý khi sao chép mẫu.
- Trang đăng nhập nhanh development `/login-test` có preset Host 1, Host 2, Customer 1, Customer 2; mật khẩu seeder là `12345678`. Tài khoản chỉ đăng nhập được nếu seeder đã được chạy trên database tương ứng; customer4 đang BLOCKED, customer5 INACTIVE.
- Frontend `npm run lint` và `npm run build` đã pass sau các cập nhật UI TV2 gần nhất; lint còn 3 cảnh báo Fast Refresh trong shared components và build có cảnh báo bundle lớn. Chưa chạy lại trong lần cập nhật tài liệu này.
- Chưa xác nhận end-to-end trên browser với database của người dùng. Luồng kiểm tra đề xuất: Customer tạo booking → Host xác nhận → check-in → ghi nhận dịch vụ → check-out; đồng thời kiểm tra tìm kiếm, preview, lịch sử/chi tiết và hủy booking theo trạng thái.

---

## Kiến trúc & Quy chuẩn
- Luồng: Route -> Middleware -> Controller (mỏng) -> Form Request -> Service (nghiệp vụ, transaction) -> Eloquent -> API Resource.
- Prefix: `/api/public`, `/api/customer`, `/api/host`. Customer/Host dùng `auth:sanctum` + role middleware (`CheckRole`) + kiểm tra ownership (`BookingPolicy` / Service check).
- Response: `{"success":true,"message":"","data":{}}`; danh sách có `"meta":{current_page,last_page,per_page,total}`; lỗi `{"success":false,"message":"","errors":{}}`. Mã: 200/201/204, 401, 403, 404 (IDOR trả 404 để giấu dữ liệu), 409 (phòng vừa bị đặt trùng), 422 (validation, chuyển trạng thái sai, không được hủy, dịch vụ sai trạng thái).

## Quy tắc nghiệp vụ
- Backend là source of truth. KHÔNG tin price, number_of_nights, room_total, amount, total, status, customer_id từ client. Giá lấy từ database và snapshot. Tiền dùng decimal định dạng chuỗi 2 số thập phân, tính bằng phép nhân/cộng chuẩn xác.
- Tên cột/field theo ERD: `check_in_date`, `check_out_date`, `guest_count`, `number_of_nights`, `price_per_night`, `room_total` (dùng cả trong request và response).
- Giữ phòng: `PENDING`, `CONFIRMED`, `CHECKED_IN`. Không giữ: `REJECTED`, `CANCELLED`, `COMPLETED`. Giao nhau khi `existing_in < requested_out AND existing_out > requested_in`. Maintenance chặn phòng theo cùng công thức, bỏ qua `CANCELLED`/`COMPLETED`.
- Phòng trống = Room ACTIVE + Property ACTIVE + đủ sức chứa + không trùng bảo trì + không trùng booking giữ phòng.
- Workflow: `PENDING->CONFIRMED`, `PENDING->REJECTED`, `PENDING->CANCELLED`, `CONFIRMED->CHECKED_IN`, `CONFIRMED->CANCELLED`, `CHECKED_IN->COMPLETED`. Không có endpoint nhận status tùy ý.
- Hủy: `PENDING` hủy tự do; `CONFIRMED` chỉ khi hôm nay `<= check_in_date - config('booking.cancel_before_days')` (mặc định 1). Các trạng thái khác không hủy được.
- Check-out chỉ đổi `CHECKED_IN -> COMPLETED`, KHÔNG tạo Invoice (việc của Thành viên 3).
- Dịch vụ trong booking: chỉ thêm/sửa/xóa khi booking `CHECKED_IN` (cấu hình `service_allowed_statuses`); service phải thuộc đúng Property của booking và đang bật; snapshot `service_name`, `unit`, `unit_price`; `amount = quantity * unit_price` do backend tính; đổi giá Service sau đó không ảnh hưởng dòng cũ. Danh mục dịch vụ chỉ bật/tắt, không xóa.
- Đặt phòng: trong `DB::transaction`, `lockForUpdate` dòng Room, kiểm tra lại availability, snapshot giá, sinh `booking_code` unique (retry nếu trùng).
- Route lồng `/host/bookings/{booking}/services/{bookingService}`: phải kiểm tra `bookingService.booking_id == booking.id`, sai thì trả 404.
- Exception nghiệp vụ tự có phương thức `render()` trả JSON chuẩn, không sửa `bootstrap/app.php`.
- Model `BookingService` trùng tên service class `App\Services\BookingService`: khi import cùng lúc phải dùng alias `BookingServiceModel`. Tên service class cho phần dịch vụ: `PropertyServiceManagementService`, `BookingServiceManagementService`.

## Quản lý Database & Migration
- Schema database toàn dự án đã được gộp vào **MỘT migration duy nhất**: `database/migrations/2026_10_08_000000_create_stayhub_schema.php`.
- **KHÔNG sửa trực tiếp** file `2026_10_08_000000_create_stayhub_schema.php` sau khi nhóm đã sử dụng chung.
- **Mọi thay đổi database về sau bắt buộc phải tạo migration mới** (ví dụ `add_xxx_to_yyy_table`).
- Tuyệt đối không tạo lại migration tạm hay rollback migration khởi tạo.

## Các phần TEMP còn lại cần thay thế khi nhận code Thành viên 1
Khi Thành viên 1 bàn giao code chính thức (Identity, Property, Room, Amenity, Maintenance), cần thay thế các file stub tạm sau:
1. **Middleware Role**:
   - `app/Http/Middleware/CheckRole.php` (`TEMP-M1-STUB`): Thay bằng middleware chính thức của Thành viên 1.
   - `bootstrap/app.php` (`TEMP-M2-WIRING`): Cập nhật alias role middleware theo đúng class của Thành viên 1 nếu có thay đổi.
2. **Enums tạm (6 file `app/Enums/*`)**:
   - `MaintenanceStatus.php`
   - `PropertyStatus.php`
   - `PropertyType.php`
   - `RoomStatus.php`
   - `UserRole.php`
   - `UserStatus.php`
3. **Models tạm (8 file `app/Models/*`)**:
   - `Amenity.php`
   - `Maintenance.php`
   - `Property.php`
   - `PropertyImage.php`
   - `Room.php`
   - `RoomImage.php`
   - `RoomType.php`
   - `User.php`
4. **Trait ApiResponse tạm**:
   - `app/Traits/ApiResponse.php` (`TEMP-M1-STUB`): Thay bằng base response helper/trait chung của nhóm nếu có.
5. **Factories tạm (6 file `database/factories/*`)**:
   - `AmenityFactory.php`
   - `MaintenanceFactory.php`
   - `PropertyFactory.php`
   - `RoomFactory.php`
   - `RoomTypeFactory.php`
   - `UserFactory.php`
6. **Seeder demo & Wiring**:
   - `database/seeders/Member1DataSeeder.php` (`TEMP-M1-STUB`): Thay dữ liệu Member 1 khi nhận code chính thức.
   - `database/seeders/Member3DataSeeder.php` (`TEMP-M3-STUB`): Thay dữ liệu Member 3 khi nhận code chính thức.
   - `database/seeders/DatabaseSeeder.php`: Đang gọi `Member1DataSeeder`, `Member2DataSeeder`, `Member3DataSeeder` theo thứ tự.
7. **Routes Wiring**:
   - `routes/api.php` (`TEMP-M2-WIRING`): Giữ nguyên `require __DIR__ . '/api/booking.php';` hoặc tổ chức lại theo routing convention chung của nhóm.

---

## Cách làm việc
(1) Liệt kê ngắn file sẽ tạo/sửa và giả định, kiểm tra code thật trước khi đoán; (2) viết code kèm test; (3) chạy lệnh kiểm tra và báo kết quả thật, nếu chưa chạy được thì nói rõ; (4) cuối phản hồi liệt kê file đã tạo/sửa và việc còn lại. Giữ phản hồi ngắn gọn, không giải thích lại những gì đã có trong file này. Không chạy lệnh git.

## Trạng thái hiện tại - dữ liệu demo toàn ERD
- Seeder đã tách theo sở hữu: `Member1DataSeeder` (9 bảng), `Member2DataSeeder` (3 bảng), `Member3DataSeeder` (6 bảng); `DemoM2Seeder` đã được thay thế.
- Migration riêng `2026_10_13_064530_add_updated_at_to_chat_messages_table.php` bổ sung `updated_at`; migration khởi tạo không bị sửa.
- `docs/seed-data.md` mô tả tài khoản, số dòng, kịch bản demo và quy ước hóa đơn.
- Xác minh ngày 13/10/2026 trên MySQL 8.4.3: `php artisan migrate:fresh --seed` thành công; `php artisan db:show --counts` cho thấy dữ liệu đủ 18 bảng; `php artisan test` pass 83 tests (435 assertions).
- Số dòng 18 bảng: users 8, properties 4, property_images 8, room_types 8, rooms 13, room_images 26, amenities 12, room_amenities 52, maintenances 4, services 13, bookings 16, booking_services 10, invoices 4, invoice_items 11, reviews 3, faqs 8, chat_sessions 2, chat_messages 8. Chi tiết và kịch bản đăng nhập trong `docs/seed-data.md`.
