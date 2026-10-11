Dự án StayHub (Laravel 12 + Sanctum SPA + MySQL; React 19 + Vite + React Router 7 + Axios). Tôi là THÀNH VIÊN 2, làm độc lập, phụ trách: Search, Availability, Booking, Booking Workflow, Services (bảng services, bookings, booking_services). Thành viên 1 (Identity, Property, Room, Amenity, Maintenance) và 3 (Invoice, Review, Dashboard, Chatbot) chưa có code trong repo.

Tài liệu (chỉ đọc mục liên quan khi cần): docs/stayhub-team-work-assignment.md mục 4; docs/stayhub-erd.md (services, bookings, booking_services, Business rules); docs/stayhub-usecase-system.md mục 4.4-4.6, 6-9; docs/stayhub-screen-mockups.md mục 2.2, 2.5-2.7, 4, 6, 7; docs/api/booking-module.md.

## Trạng thái hiện tại
- **Backend B1 - B5**: Đã hoàn thành 100%.
- **Số test đang pass**: 79/79 tests (296 assertions) qua lệnh `php artisan test`.
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
- **Seeder demo & Tài khoản demo** (`Database\Seeders\DemoM2Seeder`):
  - Mật khẩu chung: `12345678`
  - Admin: `admin@stayhub.com`
  - Host 1: `host1@stayhub.com` (quản lý StayHub Central & Homestay Dalat Cloud)
  - Host 2: `host2@stayhub.com` (quản lý Ocean Resort Nha Trang)
  - Customer 1: `customer1@stayhub.com`
  - Customer 2: `customer2@stayhub.com`
  - Customer 3: `customer3@stayhub.com`

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
   - `database/seeders/DemoM2Seeder.php` (`TEMP-M1-STUB`): Gộp hoặc thay thế bằng seeder chung của toàn đội ngũ.
   - `database/seeders/DatabaseSeeder.php` (`TEMP-M2-WIRING`): Thay thế lệnh gọi `DemoM2Seeder` bằng danh sách seeder hoàn chỉnh của nhóm.
7. **Routes Wiring**:
   - `routes/api.php` (`TEMP-M2-WIRING`): Giữ nguyên `require __DIR__ . '/api/booking.php';` hoặc tổ chức lại theo routing convention chung của nhóm.

---

## Cách làm việc
(1) Liệt kê ngắn file sẽ tạo/sửa và giả định, kiểm tra code thật trước khi đoán; (2) viết code kèm test; (3) chạy lệnh kiểm tra và báo kết quả thật, nếu chưa chạy được thì nói rõ; (4) cuối phản hồi liệt kê file đã tạo/sửa và việc còn lại. Giữ phản hồi ngắn gọn, không giải thích lại những gì đã có trong file này. Không chạy lệnh git.