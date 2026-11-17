# Bàn giao baseline TV2 — Booking & Operations

Ngày đối chiếu: 2026-11-17 (Asia/Saigon). Nhánh: `feature/member-2-booking`. HEAD hiện tại theo `git log`: `b7a1981` (tài liệu TV2); commit U3 Host bookings + services là `65b6e00`. Phạm vi là các commit đã có trong lịch sử nhánh; tài liệu này không xác nhận PR hoặc approval merge.

Trước khi tạo tài liệu, `git status` ghi nhận `docs/handover-member2.md` và `frontend/.env` đang thay đổi. Hai thay đổi này nằm ngoài nội dung baseline TV2; không chép nội dung `.env`. Ba file mới trong thư mục này cần được đưa vào diff riêng khi người dùng chuẩn bị PR.

Đối chiếu [ARCHITECTURE.md](../../ARCHITECTURE.md), [booking API](../api/booking-module.md), [handover TV2 hiện có](../handover-member2.md) và khung [TV3 handover](../TV3/BASELINE_HANDOVER.md), [PR checklist](../TV3/BASELINE_PR_CHECKLIST.md), [checklist](../TV3/TV3_CHECKLIST.md).

## Phạm vi đã bàn giao

| Luồng/lát | Phạm vi | Commit xác nhận trong `git log` |
| --- | --- | --- |
| Availability + Search | Tìm kiếm, khả dụng, khoảng ngày nửa mở, maintenance và các booking giữ phòng | `266a1d8` |
| Checkout source | Tách nguồn dữ liệu phòng đặt được cho checkout bằng `getBookableProperty` | `b70a91d` |
| Booking preview + create | Preview, tạo booking, pricing snapshot và transaction | `ba70c47` |
| Customer đọc/hủy | Danh sách, chi tiết và hủy booking | `c8ccf5d` |
| Host workflow | Danh sách/chi tiết và confirm, reject, check-in, check-out | `b7221bf` |
| Services | Danh mục dịch vụ theo property và dịch vụ gắn booking | `8d66132` |
| U1 giao diện | Search + checkout | `6b7a676` |
| U2 giao diện | Customer bookings | `58d3f81` |
| U3 giao diện | Host bookings + services | `65b6e00` |

Các commit giao diện U1–U3 đã xuất hiện trong log; việc có commit không thay thế nghiệm thu trình duyệt. Trạng thái xác nhận thủ công xem [TV2_CHECKLIST.md](TV2_CHECKLIST.md).

## Quy chuẩn áp dụng

Backend theo luồng hiện có: Route/Middleware → Controller → Validator/Request → Service/QueryService → Repository/Model → Resource/Mapper khi cần. Controller điều phối; Service giữ workflow, thứ tự nghiệp vụ và transaction; Repository giữ query, lock, persistence; Resource giữ allowlist response. Không tạo lớp nếu không có trách nhiệm tương ứng.

Frontend: Page/Component → domain service → API/shared Axios; dùng mapper khi cần. Page điều phối state giao diện; service giữ điều phối dữ liệu; tiền chỉ format để hiển thị bằng `Intl.NumberFormat`, giữ decimal string từ backend. Xem mục 3 của [ARCHITECTURE.md](../../ARCHITECTURE.md).

Giữ session/Sanctum, authorization và contract hiện có. Không coi route guard phía React là phân quyền thay backend. Template chỉ là tham khảo trình bày; route, status và nghiệp vụ lấy từ StayHub.

## Cách chạy local

- Backend: vào `backend`, cài dependency bằng `composer install`, cấu hình `backend/.env` riêng theo `.env.example`, dùng database local đã chuẩn bị, rồi chạy `php artisan migrate`, `php artisan db:seed` và `php artisan serve --host=localhost --port=8000`.
- Frontend: vào `frontend`, chạy `npm ci`; cấu hình `VITE_BACKEND_URL` trỏ `http://localhost:8000`; chạy `npm run dev -- --host localhost` tại `http://localhost:5173`.
- Sanctum SPA cần `SANCTUM_STATEFUL_DOMAINS=localhost:5173` ở môi trường backend. Không ghi giá trị secret hoặc nội dung `.env` vào tài liệu.
- Chỉ migrate/seed database local dùng được để khởi tạo lại; không dùng `migrate:fresh`, rollback hoặc seed lên dữ liệu cần giữ.
- `DatabaseSeeder` gọi `Member1DataSeeder`, `Member2DataSeeder`, `Member3DataSeeder`. Member2 tạo booking `BKDEMO0001`–`BKDEMO0016`: PENDING (1–3), CONFIRMED (4–6), CHECKED_IN (7–8), COMPLETED (9–13), CANCELLED (14–15), REJECTED (16). Tài khoản demo customer/host được khai báo ở `Member1DataSeeder`; nguồn mật khẩu seed là seeder này. Không chép mật khẩu vào tài liệu.
- Luôn dùng `localhost` cho frontend/backend origin.

## Endpoint TV2

Danh sách dưới đây lấy từ route list và phần endpoint trong handover hiện tại. `401` là chưa đăng nhập; `403` sai vai trò; `404` resource ngoài phạm vi sở hữu hoặc sai quan hệ lồng; `422` validation/transition. Tình huống cụ thể quyết định response shape.

| Method | URI | Vai trò | Mô tả | Lỗi chính |
| --- | --- | --- | --- | --- |
| GET | `/api/public/rooms/search` | Public | Tìm phòng, lọc và phân trang | 422 |
| GET | `/api/public/rooms/{room}/availability` | Public | Kiểm tra khả dụng/maintenance | 404, 422 |
| POST | `/api/public/bookings/preview` | Public | Preview giá | 404, 409, 422 |
| POST | `/api/customer/bookings` | Customer | Tạo booking | 401, 403, 404, 409, 422 |
| GET | `/api/customer/bookings` | Customer | Danh sách, lọc và phân trang | 401, 403, 422 |
| GET | `/api/customer/bookings/{booking}` | Customer | Chi tiết booking | 401, 403, 404 |
| POST | `/api/customer/bookings/{booking}/cancel` | Customer | Hủy booking | 401, 403, 404, 422 |
| GET | `/api/customer/bookings/{booking}/services` | Customer | Xem dịch vụ booking | 401, 403, 404 |
| GET / POST | `/api/host/services` | Host | Liệt kê/tạo danh mục dịch vụ | 401, 403, 404, 422 |
| PUT | `/api/host/services/{service}` | Host | Cập nhật danh mục | 401, 403, 404, 422 |
| PATCH | `/api/host/services/{service}/status` | Host | Ẩn/hiện dịch vụ | 401, 403, 404, 422 |
| GET | `/api/host/bookings` | Host | Danh sách booking thuộc property của Host | 401, 403, 422 |
| GET | `/api/host/bookings/{booking}` | Host | Chi tiết booking | 401, 403, 404 |
| POST | `/api/host/bookings/{booking}/confirm` | Host | Confirm PENDING | 401, 403, 404, 409, 422 |
| POST | `/api/host/bookings/{booking}/reject` | Host | Reject PENDING | 401, 403, 404, 422 |
| POST | `/api/host/bookings/{booking}/check-in` | Host | Check-in | 401, 403, 404, 422 |
| POST | `/api/host/bookings/{booking}/check-out` | Host | Check-out | 401, 403, 404, 422 |
| GET / POST | `/api/host/bookings/{booking}/services` | Host | Liệt kê/thêm dịch vụ booking | 401, 403, 404, 422 |
| PUT / DELETE | `/api/host/bookings/{booking}/services/{bookingService}` | Host | Sửa/xóa dòng dịch vụ | 401, 403, 404, 422 |

## Quy tắc nghiệp vụ

- Field booking theo ERD: `check_in_date`, `check_out_date`, `guest_count`; khoảng lưu trú nửa mở `[check_in_date, check_out_date)`.
- `BookingStatus::holdingValues()` tại [BookingStatus.php](../../backend/app/Enums/BookingStatus.php) gồm PENDING, CONFIRMED, CHECKED_IN.
- Booking ngoài quyền sở hữu/property và route lồng sai quan hệ trả 404; sai vai trò trả 403; chưa đăng nhập trả 401.
- Hủy CONFIRMED chỉ khi còn ít nhất một ngày trước check-in. Transition sai trạng thái trả 422.
- Confirm khóa booking rồi room, sau đó recheck availability trong transaction. PENDING hiện được tính là giữ phòng.
- Check-out chuyển CHECKED_IN → COMPLETED, không tạo Invoice.
- Chỉ thêm/sửa/xóa dịch vụ booking khi CHECKED_IN. Snapshot gồm tên, đơn vị, giá và amount tại thời điểm thêm; thay đổi danh mục không làm đổi lịch sử.
- Tiền từ backend giữ decimal string; 4 FK RESTRICT gồm booking customer, booking room, booking service service và invoice booking. Schema gộp [không được sửa trực tiếp](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php); thay đổi schema cần migration mới.

## Hành vi hiện hành cần nhóm biết

| Tình huống | Hành vi hiện tại |
| --- | --- |
| Số khách vượt sức chứa | 409, `errors.reasons: CAPACITY_EXCEEDED`; ngày không hợp lệ là 422. |
| Customer list | Envelope `success/message/data/meta`, không có `links`. |
| Lọc dịch vụ theo property Host khác | 200 với danh sách rỗng; tạo/cập nhật dịch vụ của Host khác trả 404. |
| Thêm lại dịch vụ cùng loại vào booking | Cộng dồn quantity; tổng cộng dồn có thể vượt 9999.99. |
| Danh mục dịch vụ | Chỉ ẩn/hiện bằng PATCH status, không có xóa cứng; FK RESTRICT chặn xóa dịch vụ đã được tham chiếu. |
| Hai dạng lỗi | Validation không có `success:false`; một số lỗi nghiệp vụ có `errors` là mảng rỗng. FE chuẩn hóa ở tầng hiển thị qua `bookingDisplayError.js`. |

## Tích hợp và quyết định còn mở

- Checkout dùng `getBookableProperty` với source `room-search`. `propertyService.getPropertyById` hiện dùng room search và đang đè delegate `catalog.getPropertyById`; trang chi tiết dùng nguồn đó. Giữ điều kiện `source === 'api'` trong checkout. Khôi phục delegate/chuyển trang chi tiết chờ nhóm trưởng.
- Route checkout thực tế là `/customer/checkout`; hướng dẫn `/booking/checkout` không khớp. Route tìm kiếm thực tế là `/search` tại [AppRouter.jsx](../../frontend/src/routes/AppRouter.jsx). Quyết định có giữ `/search` làm canonical route, thêm alias hay đổi hướng dẫn cần nhóm trưởng thống nhất.
- `/host/services` là entry; danh mục theo property tại `/host/properties/:propertyId/services`; dịch vụ booking tại `/host/bookings/:bookingId/services`.
- Điểm nối Invoice/Review của TV3 dùng booking COMPLETED; các lát TV2 này không đổi contract liên module.
- `*Request` vẫn ở `Validators/`; chưa thống nhất chuyển sang FormRequest/Request DTO. Resource hiện có tiếp tục làm allowlist response.

## Bằng chứng và giới hạn

Lượt chạy ngày 2026-11-17:

| Lệnh | Kết quả thực tế |
| --- | --- |
| `php artisan test` trong `backend` | PASS: 1.278 tests, 7.320 assertions; 43,96 giây |
| `node --experimental-test-module-mocks --test tests/*.test.js` trong `frontend` | PASS: 172 tests, 0 fail |

Lint/build không chạy lại trong lượt tạo tài liệu này. Kiểm tra khóa đồng thời trên MySQL chưa được xác nhận; PHPUnit dùng SQLite. Chưa có E2E trình duyệt tự động. Thử thủ công booking/search/checkout, hủy, Host workflow và Services: chưa ghi nhận xác nhận của người dùng.

## Việc còn lại và kết luận

- Nhóm trưởng quyết định cách xử lý `getPropertyById` đang đè delegate Catalog, quy ước `*Request`/`Validators`, và canonical route `/search`.
- Chỉ thay error envelope backend nếu nhóm thống nhất; hiện FE xử lý khác biệt ở tầng hiển thị.
- Theo dõi quyết định nền StayNest/Tailwind + shadcn và các stub `TEMP-M1-STUB` thuộc owner tương ứng.
- Baseline này ghi nhận commit trên nhánh, không phải approval PR/merge. Chờ owner/nhóm trưởng review và nghiệm thu riêng.

## Nguồn đối chiếu

[Handover TV2 cũ](../handover-member2.md), [API booking](../api/booking-module.md), [kiến trúc](../../ARCHITECTURE.md), [handover TV3](../TV3/BASELINE_HANDOVER.md), [routes booking](../../backend/routes/api/booking.php), [Member1DataSeeder](../../backend/database/seeders/Member1DataSeeder.php), [Member2DataSeeder](../../backend/database/seeders/Member2DataSeeder.php), [BookingStatus](../../backend/app/Enums/BookingStatus.php), [propertyService](../../frontend/src/services/propertyService.js), [hostBookingService](../../frontend/src/services/hostBookingService.js).
