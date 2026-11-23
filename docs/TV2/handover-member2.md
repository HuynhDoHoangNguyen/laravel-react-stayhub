# Bàn giao TV2 — Booking & Operations

Ngày cập nhật: 18/11/2026. Nhánh: `feature/member-2-booking`.
Đã hoàn thành toàn bộ yêu cầu refactor kiến trúc từ nhóm trưởng phục vụ Merge PR #6 theo `ARCHITECTURE.md` (mục 4, 13, 14).

## 1. Phạm vi và trạng thái

| Luồng | Kiến trúc chuẩn hóa (PR #6) |
| --- | --- |
| Availability + Search | Controller → `RoomSearchValidator` / `CheckAvailabilityQuery` & `SearchRoomsQuery` → `AvailabilityService` / `SearchService` → `RoomAvailabilityRepository` → `BookingMapper` (`RoomAvailabilityResponse`, `RoomSearchResponse`). |
| Booking preview + create | Controller → `BookingValidator` / `PreviewBookingRequest` & `StoreBookingRequest` → `BookingService` → `BookingRepository` → `BookingMapper` (`CustomerBookingResponse`). |
| Customer đọc/hủy | Controller → `BookingQueryService` / `BookingWorkflowService` → `BookingRepository` → `BookingMapper` (`CustomerBookingResponse`, `BookingServiceResponse`). |
| Host workflow | Controller → `BookingWorkflowService` / `BookingQueryService` → `BookingRepository` → `BookingMapper` (`HostBookingResponse`). |
| Services (danh mục + dịch vụ booking) | Controller → `ServiceValidator` / Request DTOs → `PropertyServiceManagementService` / `BookingServiceManagementService` → Repositories → `ServiceMapper` (`PropertyServiceResponse`) & `BookingMapper` (`BookingServiceResponse`). |
| Frontend Booking Integration | `bookingApi.js` (tách riêng Axios endpoints) → `bookingService.js` (Domain Service) → `bookingMapper.js` / `hostBookingMapper.js` (sanitization, strict validation, zero fallback to mock) → Components (AbortSignal cancellation). |

Kiểm thử toàn diện tại lượt nghiệm thu Lát 4:

| Lệnh | Kết quả |
| --- | --- |
| `php artisan test` (trong `backend`) | PASS: 1.791 passed, 8.553 assertions (101 tests TV2 passed 100%) |
| `node --experimental-test-module-mocks --test tests/*.test.js` (trong `frontend`) | PASS: 216 passed, 0 failed |
| `npm run lint` (trong `frontend`) | Exit 0; 0 errors, 3 cảnh báo Fast Refresh tại `badge.jsx`, `button.jsx`, `sidebar.jsx` |
| `npm run build` (trong `frontend`) | PASS: Vite build production thành công trong ~1.0s |

## 2. Chạy local

- Backend: `cd backend`; `composer install`; tạo `.env` tại thư mục gốc repository từ `.env.example`, cấu hình DB local đã chuẩn bị; `php artisan key:generate`; `php artisan migrate`; `php artisan db:seed`; `php artisan serve --host=localhost --port=8000`.
- Frontend: `cd frontend`; `npm ci`; đặt `VITE_BACKEND_URL=http://localhost:8000`; `npm run dev -- --host localhost` (Vite tại `http://localhost:5173`).
- SPA session cần `SANCTUM_STATEFUL_DOMAINS=localhost:5173` trong env backend. `.env.example` không khai báo khóa này; cấu hình theo môi trường local, không sao chép `.env` thật vào tài liệu.
- `DatabaseSeeder` gọi `Member1DataSeeder`, `Member2DataSeeder`, `Member3DataSeeder`. `Member2DataSeeder` tạo booking `BKDEMO0001`–`BKDEMO0016`: PENDING (1–3), CONFIRMED (4–6), CHECKED_IN (7–8), COMPLETED (9–13), CANCELLED (14–15), REJECTED (16). Seeder tài khoản dùng `Hash::make('12345678')`; customer `customer1@stayhub.com`, host `host1@stayhub.com` (được khai báo tại `Member1DataSeeder`). Chỉ seed cơ sở dữ liệu local có thể khởi tạo lại; không chạy `migrate:fresh` trên DB cần giữ.

Luôn dùng `localhost`, không đổi sang hostname/IP khác trong origin/API.

## 3. Endpoint TV2

Đã đối chiếu `php artisan route:list --path=api/public/rooms`, `--path=api/public/bookings`, `--path=api/customer/bookings`, `--path=api/host/services`, `--path=api/host/bookings`. Middleware customer/host trong `backend/routes/api/booking.php` lần lượt yêu cầu `auth:sanctum`, `account.active`, `role:CUSTOMER`/`role:HOST`. Endpoint của TV3 như invoice/review không thuộc bảng này.

| Method | URI | Vai trò | Mô tả | Lỗi chính |
| --- | --- | --- | --- | --- |
| GET | `/api/public/rooms/search` | Public | Tìm phòng, lọc ngày/khách/điều kiện tìm kiếm, phân trang | 422 input không hợp lệ |
| GET | `/api/public/rooms/{room}/availability` | Public | Kiểm tra khả dụng và khoảng bảo trì | 404 phòng không tồn tại; 422 input |
| POST | `/api/public/bookings/preview` | Public | Tính preview giá booking | 404 phòng; 409 không khả dụng/sức chứa; 422 ngày/input |
| POST | `/api/customer/bookings` | Customer | Tạo booking | 401; 403 vai trò; 404; 409 xung đột/khả dụng; 422 validation |
| GET | `/api/customer/bookings` | Customer | Danh sách booking, filter và pagination | 401; 403; 422 query |
| GET | `/api/customer/bookings/{booking}` | Customer | Chi tiết booking của customer | 401; 403; 404 ngoài quyền sở hữu |
| POST | `/api/customer/bookings/{booking}/cancel` | Customer | Hủy booking | 401; 403; 404; 422 trạng thái/hạn hủy |
| GET | `/api/customer/bookings/{booking}/services` | Customer | Dịch vụ gắn booking | 401; 403; 404 |
| GET / POST | `/api/host/services` | Host | Liệt kê/tạo danh mục dịch vụ | 401; 403; 404 property; 422 validation |
| PUT | `/api/host/services/{service}` | Host | Cập nhật danh mục dịch vụ | 401; 403; 404; 422 validation |
| PATCH | `/api/host/services/{service}/status` | Host | Ẩn/hiện dịch vụ | 401; 403; 404; 422 validation |
| GET | `/api/host/bookings` | Host | Danh sách booking thuộc property của host | 401; 403; 422 query |
| GET | `/api/host/bookings/{booking}` | Host | Chi tiết booking | 401; 403; 404 ngoài quyền/property |
| POST | `/api/host/bookings/{booking}/confirm` | Host | Xác nhận PENDING | 401; 403; 404; 409 khả dụng; 422 transition |
| POST | `/api/host/bookings/{booking}/reject` | Host | Từ chối PENDING | 401; 403; 404; 422 transition |
| POST | `/api/host/bookings/{booking}/check-in` | Host | Nhận phòng | 401; 403; 404; 422 transition |
| POST | `/api/host/bookings/{booking}/check-out` | Host | Hoàn tất CHECKED_IN | 401; 403; 404; 422 transition |
| GET / POST | `/api/host/bookings/{booking}/services` | Host | Liệt kê/thêm dịch vụ booking | 401; 403; 404 quan hệ; 422 trạng thái/validation |
| PUT / DELETE | `/api/host/bookings/{booking}/services/{bookingService}` | Host | Sửa/xóa dòng dịch vụ booking | 401; 403; 404 route lồng; 422 trạng thái/validation |

Các mã lỗi chính là tổng hợp theo HTTP validation, middleware và exception/resource flow; response cụ thể phụ thuộc lỗi đầu vào/quan hệ.

## 4. Quy tắc nghiệp vụ

- Booking lưu `check_in_date`, `check_out_date`, `guest_count` theo ERD; mốc ngày giao nhau là khoảng nửa mở. Tiền trả dạng decimal string.
- `BookingStatus::holdingValues()` trong [`BookingStatus.php`](../../backend/app/Enums/BookingStatus.php#L27) trả `PENDING`, `CONFIRMED`, `CHECKED_IN`; đây là các trạng thái giữ phòng.
- Booking ngoài quyền sở hữu/property trả 404 để giữ privacy; sai role trả 403; chưa đăng nhập trả 401.
- Hủy `PENDING` theo transition; `CONFIRMED` chỉ khi còn ít nhất 1 ngày trước check-in (`config/booking.php`, `BookingWorkflowService`).
- Confirm chạy transaction, khóa booking rồi room và recheck availability. Check-out chỉ chuyển `CHECKED_IN → COMPLETED`, không tạo Invoice.
- Thêm/sửa/xóa dịch vụ booking chỉ khi `CHECKED_IN`. Khi thêm, lưu snapshot tên, đơn vị, giá và amount; thay đổi danh mục sau đó không cập nhật lịch sử.
- Bốn FK RESTRICT trong migration schema gộp: `bookings.customer_id`, `bookings.room_id`, `booking_services.service_id`, `invoices.booking_id`. Không sửa migration này.

## 5. Hành vi hiện hành cần biết

| Trường hợp | Hành vi đã kiểm chứng |
| --- | --- |
| Khách vượt sức chứa | 409, `errors.reasons: CAPACITY_EXCEEDED`; không phải lỗi validation 422. |
| Ngày không hợp lệ | 422 validation. |
| Customer list | Envelope `success/message/data/meta`; không có `links`. |
| Lọc danh mục theo property host khác | 200 với danh sách rỗng; tạo/cập nhật dịch vụ của host khác trả 404. |
| Thêm lại dịch vụ cùng loại vào booking | Cộng dồn quantity; có thể vượt 9999.99 tổng cộng vì giới hạn áp dụng trên quantity của từng request. |
| Xóa dịch vụ danh mục | Chỉ có PATCH status để ẩn/hiện, không có hard delete endpoint; FK RESTRICT ngăn xóa dịch vụ đã được booking service tham chiếu. |
| Lỗi API | Validation Laravel không có `success:false`; một số lỗi nghiệp vụ có `success:false` cùng `errors: []`, còn availability trả `errors.reasons`. FE chuẩn hóa thông báo tại `frontend/src/utils/bookingDisplayError.js`. |

## 6. Tích hợp module

- Checkout dùng `getBookableProperty` với nguồn `room-search` để lấy phòng có thể đặt. Trong trạng thái merge hiện tại, `propertyService.getPropertyById` của TV2 gọi room search và ghi đè delegate `catalog.getPropertyById`; trang chi tiết đang dùng dữ liệu room search. Việc khôi phục delegate/chuyển trang thuộc quyết định nhóm trưởng.
- Checkout vẫn giữ chốt `source === 'api'`; nhánh booking dùng `getBookableProperty` trả `source: 'room-search'`.
- Router hiện đăng ký `/customer/checkout`; hướng dẫn `/booking/checkout` không khớp route thực tế.
- Router có `/host/services` làm trang vào danh mục và `/host/properties/:propertyId/services` cho danh mục theo property; chi tiết booking host là `/host/bookings/:bookingId/services`.
- Invoice/Review TV3 nối với booking `COMPLETED` theo quy tắc của module đó. Chưa đổi contract liên module trong các lát bàn giao này.

## 7. Quy ước kiến trúc đã hoàn thiện theo ARCHITECTURE.md (PR #6)

- **Validators & Request DTOs:** Toàn bộ 9 FormRequest cũ đã được chuyển sang Domain Validators (`App\Validators\Booking\`) và Typed Request DTOs (`App\DTOs\Booking\Requests\`). Validation raw input diễn ra trước khi ép kiểu; DTO không chứa float; tiền/số lượng sử dụng chuỗi decimal chuẩn qua `DecimalMoney`.
- **Services:** Toàn bộ 7 services nghiệp vụ của TV2 đã được di chuyển vào namespace `App\Services\Booking\`.
- **Response DTOs & Pure Mappers:** Đã xóa bỏ toàn bộ 6 `JsonResource` cũ (`AvailabilityResource`, `RoomSearchResource`, `BookingResource`, `HostBookingResource`, `BookingServiceResource`, `ServiceResource`). Thay thế bằng typed Response DTOs (`App\DTOs\Booking\Responses\`) và pure Mappers (`App\Mappers\Booking\BookingMapper`, `ServiceMapper`). Mapper không query DB, không lazy-load, không kiểm quyền.
- **Frontend Architecture:** `bookingApi.js` chịu trách nhiệm giao tiếp HTTP; `bookingService.js` điều phối logic; `bookingMapper.js` và `hostBookingMapper.js` kiểm soát kiểu dữ liệu và projection; hỗ trợ `AbortSignal` hủy request tại tầng trang UI. Chuẩn hóa trạng thái COMPLETED hiển thị "Kỳ nghỉ đã hoàn tất lưu trú".

## 8. Chưa xác minh / giới hạn

- Tranh chấp khóa đồng thời trên MySQL chưa được xác minh; PHPUnit cấu hình SQLite (`backend/phpunit.xml`), không đại diện hành vi khóa của engine production.
- Chưa có E2E tự động trên trình duyệt. Thử thủ công tìm phòng → chi tiết → checkout → preview → tạo booking, hủy, host confirm/check-in/check-out và dịch vụ: **chưa ghi nhận xác nhận trong phiên bàn giao này**.
- Chưa xác minh hiệu năng với dữ liệu lớn và cấu hình production.

## 9. Việc còn lại / đề xuất

- Áp giao diện StayNest U0–U3 sau khi nhóm trưởng xác nhận nền Tailwind + shadcn.
- Còn marker `TEMP-M1-STUB` trong `Member1DataSeeder`, các factory và một số enum/middleware/API trait; đây là phần TV1/nền tảng, cần owner xác nhận và xử lý, không thuộc phạm vi TV2.
- Chỉ đồng nhất error envelope backend nếu nhóm thống nhất thay đổi; hiện frontend đã chuẩn hóa ở tầng hiển thị.

## 10. Kết quả hoàn thành xử lý Findings R01–R09 (Task TV2-PR09-FIX)

Toàn bộ 9 finding R01–R09 và yêu cầu vệ sinh scope PR đã được xử lý triệt để:
1. **R01 (Data Provenance & Mock Safety):** Chặn mutation tạo booking/preview thật từ chỗ nghỉ mock trong `CustomerCheckoutPage` và `CheckoutReviewStep`.
2. **R02 (Lifecycle Guard):** `activeBookingIdRef` và `isMountedRef` bảo vệ `HostBookingDetailPage` và `HostBookingServicesPage` chống response cũ ghi đè khi đổi booking context.
3. **R03 (Preview Snapshot & Race Guard):** `previewRequestIdRef` và derived state snapshot bảo đảm preview luôn thuộc về đúng payload hiện tại; input thay đổi lập tức vô hiệu hóa preview cũ.
4. **R04 (Price Cross-field Validation):** So sánh giá an toàn trong `RoomSearchValidator`, không gây lỗi 500 khi input sai kiểu.
5. **R05 (Reject Booking Validation):** Bổ sung `RejectBookingRequest` DTO, tuân thủ thứ tự ưu tiên lỗi (404 binding → 422 validation → 404/403 ownership).
6. **R06 (Pure Domain Validator Boundary):** Khử hoàn toàn quy tắc `exists:*` khỏi các Validator; chuyển trách nhiệm lookup sang Repository/Service; bổ sung Typed Query DTOs.
7. **R07 (Frontend Mapper Safety):** Loại bỏ fake pagination, ném `mapperError` khi response thiếu `data`/`meta` hoặc `data: null`.
8. **R08 (Safe Bounded Pagination):** Bổ sung vòng lặp an toàn duyệt tiếp trang 2, 3... cho `getBookableProperty` và `listActiveForProperty`, bảo toàn `AbortSignal`.
9. **R09 (Amenity Deduplication):** Deduplicate mảng `amenity_ids` tránh làm sai điều kiện đếm AND trong tìm kiếm phòng.
10. **Scope PR:** Loại bỏ file artifact SQLite `backend/stayhub` khỏi Git tracking.

## Nguồn đối chiếu

[`STAYHUB_M2_CONTEXT.md`](../../STAYHUB_M2_CONTEXT.md), [`docs/api/booking-module.md`](../api/booking-module.md), [`ARCHITECTURE.md`](../../ARCHITECTURE.md), [`docs/TV3/BASELINE_HANDOVER.md`](../TV3/BASELINE_HANDOVER.md), `backend/routes/api/booking.php`, `backend/app/Enums/BookingStatus.php`, `backend/database/seeders/Member1DataSeeder.php`, `backend/database/seeders/Member2DataSeeder.php`, `backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php`, `backend/phpunit.xml`; `git log --oneline -12`; lệnh kiểm thử và `route:list` ở trên.
