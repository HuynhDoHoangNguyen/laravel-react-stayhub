Dự án StayHub (Laravel 12 + Sanctum SPA + MySQL; React 19 + Vite + React Router 7 + Axios). Tôi là THÀNH VIÊN 2, làm độc lập, phụ trách: Search, Availability, Booking, Booking Workflow, Services (bảng services, bookings, booking_services). Repo hiện đã có code nền và dữ liệu mẫu của TV1, TV2, TV3; cần kiểm tra code thực tế trước khi kết luận trạng thái bàn giao giữa các thành viên.

Tài liệu (chỉ đọc mục liên quan khi cần): docs/stayhub-team-work-assignment.md mục 4; docs/stayhub-erd.md (services, bookings, booking_services, Business rules); docs/stayhub-usecase-system.md mục 4.4-4.6, 6-9; docs/stayhub-screen-mockups.md mục 2.2, 2.5-2.7, 4, 6, 7; docs/api/booking-module.md.

## Trạng thái hiện tại
- **Đánh giá cập nhật 01/11/2026**: Chức năng Search, Availability, Booking, Booking Workflow và Services có backend/frontend và route tương ứng. Các vấn đề tương thích factory, account status và Host Properties phát hiện sau khi ghép `origin/dev` đã được xử lý trong nhánh hiện tại. Backend test hiện pass 490 tests (2.545 assertions).
- **Kiểm thử lịch sử**: 83 tests (435 assertions) pass ngày 13/10/2026; kết quả này không đại diện cho trạng thái hiện tại sau khi ghép code TV1.
- **Kiểm tra hiện tại 01/11/2026**: `php artisan route:list --path=api` liệt kê 39 route toàn ứng dụng; `GET /api/host/properties` được đăng ký qua `HostPropertyController`. `php artisan migrate:fresh --seed` chạy thành công trên MySQL cấu hình localhost. Frontend lint pass với 3 warning Fast Refresh; build pass với cảnh báo bundle JS vượt 500 kB.
- **Tích hợp đã hoàn tất**: `account.active` chạy sau `auth:sanctum` và trước `role` trên route booking; `CheckRole` chỉ kiểm tra role; factory model/User tương thích với test TV2; Host Properties API dùng controller/query service/resource và chỉ trả Property của Host hiện tại.
- **Còn lại**: Chưa xác nhận end-to-end trên browser. Chi tiết thay đổi và test: `docs/member-2-tv1-integration-adjustments.md`.
- **Danh sách 22 endpoint TV2 (rút gọn)**; không tính `GET /api/host/properties`, API Property dùng chung được ghi ở tài liệu API module:
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
- Các luồng giao diện TV2 đã có: tìm phòng; xem thông tin Property; preview/tạo booking; lịch sử, chi tiết và hủy booking Customer; danh sách/chi tiết và confirm/reject/check-in/check-out phía Host; quản lý danh mục dịch vụ và dịch vụ phát sinh trong booking.
- Route thực tế trong `frontend/src/routes/AppRouter.jsx`: `/search`, `/properties/:propertyId`, `/customer/checkout`, `/customer/bookings`, `/customer/bookings/:bookingId`, `/host/bookings`, `/host/bookings/:bookingId`, `/host/bookings/:bookingId/services`, `/host/services`, `/host/properties/:propertyId/services`. `/host/services` là lối vào tương thích tạm thời, dùng endpoint `GET /api/host/properties` để chọn cơ sở đầu tiên.
- `GET /api/public/rooms/{room}/availability` có trong backend API; không tìm thấy trang hoặc component `AvailabilityChecker` đang dùng endpoint trực tiếp. Tìm kiếm nhận tiêu chí ngày/khách và checkout hiển thị availability từ response preview.
- Checkout lấy báo giá phòng từ API preview; frontend không tự tính tổng tiền chính thức. Backend không nhận số điện thoại riêng trong payload tạo booking, nên checkout không bắt nhập số điện thoại tùy ý.
- Khi chạy UI với API thật, đặt `VITE_ENABLE_PROPERTY_MOCKS=false` và `VITE_ENABLE_BOOKING_MOCKS=false` trong `frontend/.env`; đặt `VITE_BACKEND_URL=http://localhost:8000`. `frontend/.env.example` đang bật hai mock flag, cần chú ý khi sao chép mẫu.
- Trang đăng nhập nhanh development `/login-test` có preset Host 1, Host 2, Customer 1, Customer 2; mật khẩu seeder là `12345678`. Tài khoản chỉ đăng nhập được nếu seeder đã được chạy trên database tương ứng; customer4 đang BLOCKED, customer5 INACTIVE.
- Kết quả gần nhất sau tích hợp: `npm run lint` pass với 3 warning Fast Refresh; `npm run build` pass ngày 01/11/2026 với cảnh báo bundle lớn. Các cảnh báo hiện tại không làm fail lint/build.
- Chưa xác nhận end-to-end trên browser với database của người dùng. Luồng kiểm tra đề xuất: Customer tạo booking → Host xác nhận → check-in → ghi nhận dịch vụ → check-out; đồng thời kiểm tra tìm kiếm, preview, lịch sử/chi tiết và hủy booking theo trạng thái.

---

## Kiến trúc & Quy chuẩn
- Luồng: Route -> Middleware -> Controller (mỏng) -> Form Request -> Service (nghiệp vụ, transaction) -> Eloquent -> API Resource.
- Prefix: `/api/public`, `/api/customer`, `/api/host`. Customer/Host dùng `auth:sanctum` + role middleware (`CheckRole`) + kiểm tra ownership (`BookingPolicy` / Service check).
- Xác thực SPA hiện dùng Sanctum session/cookie. Route booking dùng thứ tự `auth:sanctum`, `account.active`, rồi `role` để tách xác thực, trạng thái tài khoản và phân quyền vai trò.
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

## Tình trạng tích hợp code Thành viên 1 (01/11/2026)
Sau khi ghép `origin/dev`, các model Property Catalog (`Amenity`, `Maintenance`, `Property`, `PropertyImage`, `Room`, `RoomImage`, `RoomType`) được giữ theo phiên bản TV1 và bổ sung `HasFactory` tại các model có factory tương ứng để test/seeder dùng chung. `UserFactory` giữ dữ liệu mặc định cùng state role/status mà test các module đang dùng. Các phần tạm chưa có bản thay thế thực tế trên Dev vẫn giữ nguyên:
1. **Middleware Role**:
   - `app/Http/Middleware/CheckRole.php` còn marker `TEMP-M1-STUB`, nhưng hiện chỉ kiểm tra role; account status do `EnsureAccountIsActive` đảm nhiệm.
   - `bootstrap/app.php` đăng ký alias `role` và `account.active`.
2. **Enums tạm (6 file `app/Enums/*`)**:
   - `MaintenanceStatus.php`
   - `PropertyStatus.php`
   - `PropertyType.php`
   - `RoomStatus.php`
   - `UserRole.php`
   - `UserStatus.php`
3. **Models**: 7 model Property Catalog đã cập nhật từ Dev; `User.php` đã ghép `gender`, `date_of_birth` và giữ Sanctum/quan hệ cần cho các module hiện tại.
4. **Trait ApiResponse tạm**:
   - `app/Traits/ApiResponse.php` vẫn có `TEMP-M1-STUB`; chưa có bản thay thế trên Dev.
5. **Factories**:
   - `UserFactory.php` bổ sung các state `host()`, `customer()`, `admin()` và `blocked()` cùng role/status mặc định.
   - Các factory tạm còn lại:
     - `AmenityFactory.php`
     - `MaintenanceFactory.php`
     - `PropertyFactory.php`
     - `RoomFactory.php`
     - `RoomTypeFactory.php`
6. **Seeder demo & Wiring**:
   - `database/seeders/Member1DataSeeder.php` vẫn có `TEMP-M1-STUB`; seeder chạy thành công với dữ liệu hiện tại.
   - `database/seeders/Member3DataSeeder.php` vẫn có `TEMP-M3-STUB`.
   - `database/seeders/DatabaseSeeder.php` gọi `Member1DataSeeder`, `Member2DataSeeder`, `Member3DataSeeder` theo thứ tự.
7. **Routes Wiring**:
   - `routes/api.php` vẫn include `api/booking.php`, đồng thời include billing, reviews và profile.
   - `GET /api/host/properties` đã được chuyển sang `routes/api/properties.php` và gọi `HostPropertyController`; query giới hạn theo Host, resource chỉ trả `id` và `name`. Contract frontend không đổi.

---

## Cách làm việc
(1) Liệt kê ngắn file sẽ tạo/sửa và giả định, kiểm tra code thật trước khi đoán; (2) viết code kèm test; (3) chạy lệnh kiểm tra và báo kết quả thật, nếu chưa chạy được thì nói rõ; (4) cuối phản hồi liệt kê file đã tạo/sửa và việc còn lại. Giữ phản hồi ngắn gọn, không giải thích lại những gì đã có trong file này. Không chạy lệnh git.

## Trạng thái hiện tại - dữ liệu demo toàn ERD
- Seeder đã tách theo sở hữu: `Member1DataSeeder` (9 bảng), `Member2DataSeeder` (3 bảng), `Member3DataSeeder` (6 bảng); `DemoM2Seeder` đã được thay thế.
- Migration riêng `2026_10_13_064530_add_updated_at_to_chat_messages_table.php` bổ sung `updated_at`; migration khởi tạo không bị sửa.
- `docs/seed-data.md` mô tả tài khoản, số dòng, kịch bản demo và quy ước hóa đơn.
- Xác minh ngày 13/10/2026 trên MySQL 8.4.3: `php artisan migrate:fresh --seed` thành công; `php artisan db:show --counts` cho thấy dữ liệu đủ 18 bảng; `php artisan test` pass 83 tests (435 assertions).
- Số dòng 18 bảng: users 8, properties 4, property_images 8, room_types 8, rooms 13, room_images 26, amenities 12, room_amenities 52, maintenances 4, services 13, bookings 16, booking_services 10, invoices 4, invoice_items 11, reviews 3, faqs 8, chat_sessions 2, chat_messages 8. Chi tiết và kịch bản đăng nhập trong `docs/seed-data.md`.
