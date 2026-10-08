Dự án StayHub (Laravel 12 + Sanctum SPA + MySQL; React 19 + Vite + React Router 7 + Axios). Tôi là THÀNH VIÊN 2, làm độc lập, phụ trách: Search, Availability, Booking, Booking Workflow, Services (bảng services, bookings, booking_services). Thành viên 1 (Identity, Property, Room, Amenity, Maintenance) và 3 (Invoice, Review, Dashboard, Chatbot) chưa có code trong repo.

Tài liệu (chỉ đọc mục liên quan khi cần): docs/stayhub-team-work-assignment.md mục 4; docs/stayhub-erd.md (services, bookings, booking_services, Business rules); docs/stayhub-usecase-system.md mục 4.4-4.6, 6-9; docs/stayhub-screen-mockups.md mục 2.2, 2.5-2.7, 4, 6, 7.

Kiến trúc: Route -> Middleware -> Controller (mỏng) -> Form Request -> Service (nghiệp vụ, transaction) -> Eloquent -> API Resource.
Prefix: /api/public, /api/customer, /api/host. Customer/Host dùng auth:sanctum + role middleware + kiểm tra ownership.
Response: {"success":true,"message":"","data":{}}; danh sách thêm "meta":{current_page,last_page,per_page,total}; lỗi {"success":false,"message":"","errors":{}}. Mã: 200/201/204, 401, 403, 404, 409 (phòng vừa bị đặt), 422 (validation, chuyển trạng thái sai, không được hủy).

Quy tắc nghiệp vụ:
- Backend là source of truth. KHÔNG tin price, number_of_nights, room_total, amount, total, status, customer_id từ client. Giá lấy từ database và snapshot. Tiền dùng decimal, tính bằng brick/math hoặc bcmath, không dùng float.
- Tên cột/field theo ERD: check_in_date, check_out_date, guest_count, number_of_nights, price_per_night, room_total (dùng cả trong request và response).
- Giữ phòng: PENDING, CONFIRMED, CHECKED_IN. Không giữ: REJECTED, CANCELLED, COMPLETED. Giao nhau khi existing_in < requested_out AND existing_out > requested_in. Maintenance chặn phòng theo cùng công thức, bỏ qua CANCELLED/COMPLETED.
- Phòng trống = Room ACTIVE + Property ACTIVE + đủ sức chứa + không trùng bảo trì + không trùng booking giữ phòng.
- Workflow: PENDING->CONFIRMED, PENDING->REJECTED, PENDING->CANCELLED, CONFIRMED->CHECKED_IN, CONFIRMED->CANCELLED, CHECKED_IN->COMPLETED. Không có endpoint nhận status tùy ý.
- Hủy: PENDING hủy tự do; CONFIRMED chỉ khi hôm nay <= check_in_date - config('booking.cancel_before_days') (mặc định 1). Các trạng thái khác không hủy được.
- Check-out chỉ đổi CHECKED_IN -> COMPLETED, KHÔNG tạo Invoice (việc của Thành viên 3).
- Dịch vụ trong booking: chỉ thêm/sửa/xóa khi booking CHECKED_IN (cấu hình service_allowed_statuses); service phải thuộc đúng Property của booking và đang bật; snapshot service_name, unit, unit_price; amount = quantity * unit_price do backend tính; đổi giá Service sau đó không ảnh hưởng dòng cũ. Danh mục dịch vụ chỉ bật/tắt, không xóa.
- Đặt phòng: trong DB::transaction, lockForUpdate dòng Room, kiểm tra lại availability, snapshot giá, sinh booking_code unique (retry nếu trùng).
- Route lồng /host/bookings/{booking}/services/{bookingService}: phải kiểm tra bookingService.booking_id == booking.id, sai thì 404.
- Exception nghiệp vụ tự có phương thức render() trả JSON chuẩn, không sửa bootstrap/app.php.
- Model BookingService trùng tên service class App\Services\BookingService: khi import cùng lúc phải dùng alias. Tên service class cho phần dịch vụ: PropertyServiceManagementService, BookingServiceManagementService.

Phạm vi file: chỉ tạo file thuộc module booking (Service.php, Booking.php, BookingService.php, routes/api/booking.php, các service/page/component booking). Ngoài ra chỉ được tạo file TẠM cho phần của Thành viên 1 (xem S0), mỗi file tạm có comment TEMP-M1-STUB ở đầu. Chỉ sửa routes/api.php, bootstrap/app.php, AppRouter.jsx ở mức tối thiểu để chạy thử, đánh dấu comment TEMP-M2-WIRING. Không cài thêm thư viện khi chưa hỏi tôi. Không chạy lệnh git.

- Migration khởi tạo: file `2026_10_08_000000_create_stayhub_schema.php` là migration khởi tạo duy nhất, sau khi nhóm đã dùng thì KHÔNG sửa trực tiếp. Mọi thay đổi database về sau phải tạo migration mới (ví dụ `add_xxx_to_yyy_table`).

Cách làm: (1) liệt kê ngắn file sẽ tạo/sửa và giả định, kiểm tra code thật trước khi đoán; (2) viết code kèm test; (3) chạy lệnh kiểm tra và báo kết quả thật, nếu chưa chạy được thì nói rõ; (4) cuối phản hồi liệt kê file đã tạo/sửa và việc còn lại. Giữ phản hồi ngắn gọn, không giải thích lại những gì đã có trong file này.