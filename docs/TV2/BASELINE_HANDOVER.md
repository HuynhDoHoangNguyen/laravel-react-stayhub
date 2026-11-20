# Bàn giao baseline TV2 — Booking & Operations

Ngày đối chiếu: 2026-11-20 (Asia/Saigon). Nhánh: `feature/member-2-booking`. HEAD hiện tại theo `git log`: `e2dfa84` (kết thúc Lát E - sửa 5 findings PR #10). Đã giải quyết merge code mới từ `dev` (PR #11 TV1 tại commit `def4fbb`).

Trước khi tạo tài liệu, `git status` ghi nhận `docs/handover-member2.md` và `frontend/.env` đang thay đổi. Hai thay đổi này nằm ngoài nội dung baseline TV2; không chép nội dung `.env`. Ba file mới trong thư mục này cần được đưa vào diff riêng khi người dùng chuẩn bị PR.

Đối chiếu [ARCHITECTURE.md](../../ARCHITECTURE.md), [booking API](../api/booking-module.md), [handover TV2 hiện có](handover-member2.md) và khung [TV3 handover](../TV3/BASELINE_HANDOVER.md), [PR checklist](../TV3/BASELINE_PR_CHECKLIST.md), [checklist](../TV3/TV3_CHECKLIST.md).

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

Backend theo kiến trúc Domain-driven chuẩn hóa:
Route/Middleware → Controller → Domain Validator & Typed Request/Query DTO → Service/QueryService → Repository → Eloquent Model → Pure Mapper & Typed Response DTO → HTTP Response.
- Controller chỉ điều phối HTTP và phân quyền.
- Domain Validator thuần túy kiểm tra facts trong bộ nhớ (format, boundary, types, cross-field rules), không trực tiếp query cơ sở dữ liệu (`exists:*` đã được tách hoàn toàn).
- Service điều phối lookup qua Repository, quản lý workflow, tính toán tiền (BCMath DecimalMoney) và giao dịch nguyên tử (database transaction).
- Repository chịu trách nhiệm truy vấn, eager loading, persistence và database locking (`lockForUpdate`).
- Response DTO & Mapper chuẩn hóa JSON response envelope thay thế hoàn toàn JsonResource.

Frontend: Page/Component → Domain Service → Domain API / Shared Axios; Response normalization qua Pure Mappers.
- Giao diện có lifecycle guard chống stale mutation overwrites, preview invalidation khi tham số thay đổi, và khóa mutation đối với dữ liệu mock.
- Safe bounded pagination loop đảm bảo lấy đầy đủ dữ liệu phòng và dịch vụ khi vượt quá 50 dòng.
- Tiền hiển thị chuẩn VND qua `Intl.NumberFormat`, giữ nguyên decimal string từ backend. Xem mục 3 của [ARCHITECTURE.md](../../ARCHITECTURE.md).

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

- Checkout dùng `getBookableProperty` với source `room-search`. Dữ liệu mock (`source === 'mock'`) được chặn mutation tạo booking thật cả ở handler và UI (Finding R01).
- Route checkout thực tế là `/customer/checkout`; hướng dẫn `/booking/checkout` không khớp. Route tìm kiếm thực tế là `/search` tại [AppRouter.jsx](../../frontend/src/routes/AppRouter.jsx). Quyết định có giữ `/search` làm canonical route, thêm alias hay đổi hướng dẫn cần nhóm trưởng thống nhất.
- `/host/services` là entry; danh mục theo property tại `/host/properties/:propertyId/services`; dịch vụ booking tại `/host/bookings/:bookingId/services`.
- Điểm nối Invoice/Review của TV3 dùng booking COMPLETED; các lát TV2 này không đổi contract liên module.
- Đã hoàn tất di chuyển toàn bộ FormRequest thành Domain Validator kết hợp Typed Request/Query DTOs (`app/DTOs/Booking/Requests/`), và chuyển JsonResource thành Response DTOs & pure Mappers (`app/DTOs/Booking/Responses/` và `app/Mappers/Booking/`).

## Kết quả xử lý Findings R01–R09 (Task TV2-PR09-FIX)

| Finding | Mô tả & Yêu cầu | Giải pháp thực hiện | Bằng chứng kiểm thử |
| --- | --- | --- | --- |
| **R01** | Mock checkout có thể gọi mutation thật | Chặn tại handler `CustomerCheckoutPage` và UI khi `source !== 'room-search'`. Hiển thị warning banner; `CheckoutReviewStep` khóa nút khi `isMock`. | `frontend/tests/checkoutLifecycleGuard.test.js` PASS |
| **R02** | Response mutation cũ ghi đè khi đổi context/unmount | `activeBookingIdRef` và `isMountedRef` bảo vệ `HostBookingDetailPage` và `HostBookingServicesPage`. | `frontend/tests/checkoutLifecycleGuard.test.js` PASS |
| **R03** | Preview không gắn với payload hiện tại & race condition | `previewRequestIdRef` và derived state `isPreviewCurrent` snapshot. Bắt buộc preview khớp 100% payload mới cho phép create. | `frontend/tests/checkoutLifecycleGuard.test.js` PASS |
| **R04** | Cross-field validation giá có thể thành 500 | `RoomSearchValidator` kiểm tra numeric scalar hợp lệ trước khi so sánh `DecimalMoney::compare()`. Trả 422 chuẩn. | `backend/tests/Feature/BookingInputIntegrityTest.php` PASS |
| **R05** | Reject reason chưa validate trước typed service | Bổ sung `RejectBookingRequest` DTO & `validateReject()`. Error precedence: 404 binding → 422 validation → 404/403 ownership. | `backend/tests/Feature/BookingInputIntegrityTest.php` PASS |
| **R06** | Validator/query boundary chưa đúng kiến trúc | Tách triệt để `exists:*` khỏi Validators; chuyển lookup sang Repository/Service ném `ValidationException`. Bổ sung typed Query DTOs. | `backend/tests/Feature/BookingInputIntegrityTest.php` PASS |
| **R07** | Mapper chấp nhận malformed success/fake pagination | Loại bỏ fake meta, ném `mapperError` khi response thiếu `data`/`meta` hoặc `data: null`. | `frontend/tests/hostBookingMapper.test.js`, `propertyServiceCatalogMapper.test.js` PASS |
| **R08** | Trang đầu 50 dòng bị coi là toàn bộ dữ liệu | Safe Bounded Pagination Loop cho `getBookableProperty` và `listActiveForProperty`. Bảo toàn `options/signal`. | `frontend/tests/paginationSafety.test.js` PASS |
| **R09** | Amenity IDs lặp làm sai điều kiện AND | Deduplicate `amenity_ids` qua `array_values(array_unique(...))` trong cả Validator và Repository. | `backend/tests/Feature/BookingInputIntegrityTest.php` PASS |
| **Scope** | File SQLite `backend/stayhub` còn trong PR | Xóa khỏi Git tracking, giữ scope PR sạch 100%. | `git status` xác nhận `D backend/stayhub` |

## Kết quả xử lý 5 Findings PR #10 (Task TV2-PR10-FIX-01)

| Finding | Lát & Commit | Mô tả & Nguyên nhân | Giải pháp kỹ thuật | Bằng chứng kiểm thử thực tế |
| --- | --- | --- | --- | --- |
| **PR10-01** | Lát B (`4f4c182`) | Double-unwrap `res.data` làm mất `meta` phân trang khi tải dịch vụ host | `propertyServiceCatalogApi.list()` đã trả body `{success, data, meta}`. `listActiveForProperty()` truyền `res.data` khiến mapper coi là mảng và mất `meta`. Đã sửa truyền trực tiếp `res` tới mapper. Sửa mock test đúng contract. | `frontend/tests/paginationSafety.test.js`: 4/4 PASS; Frontend full suite: 234/234 PASS |
| **PR10-02** | Lát E (`e2dfa84`) | Form `SearchBar` điều hướng ngày/khách nhưng `/search` dùng Catalog parser không hỗ trợ | Tạo `roomAvailabilitySearchService.js` và kết nối SearchBar tới `/api/public/rooms/search` khi có ngày lưu trú; phân biệt rành mạch với Catalog room filter; bảo toàn link Đặt ngay kèm ngày/khách. | `frontend/tests/roomAvailabilitySearchService.test.js`: 4/4 PASS; Catalog tests: 13/13 PASS; Frontend full: 234/234 PASS |
| **PR10-03** | Lát A (`7bf62c2`) | Bỏ `exists:*` làm mất contract 422 cho `property_id` không tồn tại | Bổ sung phương thức lookup `propertyExists()`, `roomTypeExists()`, `findExistingAmenityIds()` vào `RoomAvailabilityRepository`. Tại `SearchService` ném `ValidationException` chuẩn 422, giữ Domain Validator thuần túy không query DB. | `backend/tests/Feature/BookingInputIntegrityTest.php`: 12/12 PASS; `RoomSearchValidationTest` PASS; Backend full suite: 1868 PASS |
| **PR10-04** | Lát C (`fe9f6a1`) | Thay đổi số khách khi preview pending làm kẹt `isPreviewing=true` | Gọi `setIsPreviewing(false)` ngay trong `updateGuestCount()`; bổ sung `isMountedRef` bảo vệ async resolve; request cũ resolve muộn bị bỏ qua không làm kẹt nút bấm. | `frontend/tests/checkoutLifecycleGuard.test.js`: Deferred promise test PASS (6/6 tests); Frontend full: 234/234 PASS |
| **PR10-05** | Lát D (`ad9d54a`) | Đổi Booking khi mutation pending khiến `actionLoading`/`saving` kẹt | Reset `actionLoading = false` và `saving = ''` khi `bookingId` thay đổi qua render guard; reset vô điều kiện trong `finally` nếu component còn mounted. | `frontend/tests/checkoutLifecycleGuard.test.js`: Deferred promise tests cho cả HostBookingDetail và HostBookingServices PASS (8/8 tests); Frontend full: 234/234 PASS |

## Bằng chứng kiểm thử toàn diện (Snapshot ngày 2026-11-20, HEAD e2dfa84)

Lượt chạy nghiệm thu sau khi hoàn tất sửa 5 findings PR #10 và merge code từ `origin/dev` (PR #11 TV1):

| Lệnh | Kết quả thực tế | Thời gian / Engine |
| --- | --- | --- |
| `php artisan test` (backend full suite, không filter) | PASS: 1868 passed, 91 skipped (9688 assertions); 0 failed; exit 0 | 57.79s (SQLite in-memory) |
| `php artisan test --filter=Booking` (backend TV2 filtered suite) | PASS: 230 passed, 882 assertions; 0 failed; exit 0 | 4.88s (SQLite in-memory) |
| `node --experimental-test-module-mocks --test tests/**/*.test.js` (frontend full suite) | PASS: 234 tests, 0 fail; exit 0 | 2.82s (Node.js test runner) |
| `npm run lint` (frontend) | PASS: 0 errors (3 warnings giao diện có sẵn); exit 0 | ESLint 9 |
| `npm run build` (frontend) | PASS: Vite production build thành công; exit 0 | 1.40s |
| `git diff --check` | PASS: Không có lỗi whitespace; exit 0 | Git diff engine |

## Việc còn lại và kết luận

- Đã khắc phục hoàn toàn 5 findings PR10-01 đến PR10-05, bổ sung các test hành vi thực tế (deferred promise tests, contract tests) bắt đúng lỗi thay vì chỉ kiểm tra regex.
- Đã giải quyết triệt để merge conflict với PR #11 của TV1 từ `dev`, bảo toàn dynamic route protection test suite.
- Backend full suite đạt 1868 passed / 91 skipped / 9688 assertions; Frontend full test đạt 234/234 passed; lint 0 lỗi; build thành công.
- Nhánh `feature/member-2-booking` đạt 100% điều kiện kỹ thuật để Nhóm trưởng review lại PR #10 và merge vào `dev`.

## Nguồn đối chiếu

[Handover TV2](handover-member2.md), [API booking](../api/booking-module.md), [kiến trúc](../../ARCHITECTURE.md), [handover TV3](../TV3/BASELINE_HANDOVER.md), [routes booking](../../backend/routes/api/booking.php), [Member1DataSeeder](../../backend/database/seeders/Member1DataSeeder.php), [Member2DataSeeder](../../backend/database/seeders/Member2DataSeeder.php), [BookingStatus](../../backend/app/Enums/BookingStatus.php), [propertyService](../../frontend/src/services/propertyService.js), [hostBookingService](../../frontend/src/services/hostBookingService.js).
