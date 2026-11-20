# StayHub TV2 — checklist tiến độ và nghiệm thu

Phạm vi: Availability, Search, Booking, Booking Workflow, Services và các lát giao diện U1–U3. Đối chiếu 2026-11-20 trên `feature/member-2-booking`, HEAD `e2dfa84`. Commit được xác nhận bằng `git log`; các số kiểm thử tổng hợp là lượt chạy hiện tại, phân biệt rõ giữa test filtered và test toàn suite (full suite).

## Use case TV2 đã có triển khai

Đối chiếu tên và số UC theo [stayhub-usecase-system.md](../stayhub-usecase-system.md), ranh giới sở hữu theo [phân công nhóm](../stayhub-team-work-assignment.md), mã thay đổi theo `git log` và phạm vi đã ghi trong [handover TV2](BASELINE_HANDOVER.md).

| UC | Tên trong Use Case System | Phần TV2 hiện có | Commit refactor liên quan | Tình trạng |
| --- | --- | --- | --- | --- |
| UC6 | Tìm kiếm phòng trống | Search API/UI, tiêu chí lọc, phân trang | `266a1d8`, UI `6b7a676` | Đã triển khai |
| UC7 | Lọc phòng | Bộ lọc tìm kiếm | `266a1d8`, UI `6b7a676` | Đã triển khai |
| UC9 | Đặt phòng | Preview và tạo booking | `ba70c47` | Đã triển khai |
| UC10 | Xem tổng tiền phòng | Preview giá phòng trước khi tạo booking | `ba70c47` | Đã triển khai |
| UC11 | Xem lịch sử booking | Customer list/filter/pagination | `c8ccf5d`, UI `58d3f81` | Đã triển khai |
| UC12 | Xem chi tiết booking | Customer booking detail | `c8ccf5d`, UI `58d3f81` | Đã triển khai |
| UC13 | Hủy booking | Hủy PENDING và CONFIRMED theo điều kiện hiện hành | `c8ccf5d` | Đã triển khai |
| UC14 | Xem dịch vụ đã sử dụng | Booking detail tải các service gắn booking | Có trong luồng Customer detail; không gán commit refactor riêng | Đã triển khai |
| UC24 | Quản lý dịch vụ | Host list/create/update/ẩn-hiện danh mục theo property | `8d66132`, UI `65b6e00` | Đã triển khai |
| UC25 | Thêm dịch vụ vào booking | Thêm service vào booking CHECKED_IN | `8d66132` | Đã triển khai |
| UC26 | Cập nhật số lượng dịch vụ | Sửa quantity dòng booking service | `8d66132` | Đã triển khai |
| UC27 | Xóa dịch vụ khỏi booking | Xóa dòng booking service | `8d66132` | Đã triển khai |
| UC28 | Xem booking của cơ sở | Host list/filter/pagination theo property | `b7221bf`, UI `65b6e00` | Đã triển khai |
| UC29 | Xem chi tiết booking | Host booking detail | `b7221bf`, UI `65b6e00` | Đã triển khai |
| UC30 | Xác nhận booking | PENDING → CONFIRMED, recheck availability | `b7221bf` | Đã triển khai |
| UC31 | Từ chối booking | PENDING → REJECTED | `b7221bf` | Đã triển khai |
| UC32 | Check-in | CONFIRMED → CHECKED_IN | `b7221bf` | Đã triển khai |
| UC33 | Check-out | CHECKED_IN → COMPLETED | `b7221bf` | Đã triển khai (không tạo Invoice theo bất biến) |
| UC49 | Kiểm tra Availability | Availability service/query | `266a1d8` | Đã triển khai |
| UC50 | Kiểm tra booking bị trùng | Overlap booking giữ phòng | `266a1d8` | Đã triển khai |
| UC51 | Kiểm tra lịch bảo trì | Availability loại trừ maintenance | `266a1d8` | Đã triển khai (loại trừ maintenance) |
| UC52 | Kiểm tra sức chứa | Validate guest count/capacity | `266a1d8`, `ba70c47` | Đã triển khai |
| UC53 | Tính số đêm | Booking pricing/preview | `ba70c47` | Đã triển khai |
| UC54 | Tính tiền phòng | Preview/create dùng snapshot giá phòng | `ba70c47` | Đã triển khai |
| UC55 | Tính tiền dịch vụ | Amount theo snapshot dịch vụ và quantity | `8d66132` | Đã triển khai |
| UC59 | Kiểm tra điều kiện hủy | Kiểm tra trạng thái và hạn hủy CONFIRMED | `c8ccf5d` | Đã triển khai |

**Lưu ý lệch tài liệu:** mục 9 của `stayhub-usecase-system.md` mô tả check-out tiếp tục tạo Invoice. Bất biến đã thống nhất cho code TV2 là check-out chỉ chuyển booking sang COMPLETED, không tạo Invoice; Invoice là điểm nối riêng với TV3. Ghi nhận để nhóm trưởng thống nhất tài liệu, không thay đổi workflow/contract trong cập nhật này.

UC8 (chi tiết phòng) và UC23 (quản lý lịch bảo trì) thuộc Catalog/Maintenance của TV1, không tính là UC TV2. UC34–UC37 và Invoice/Review thuộc TV3; UC49–UC55/UC59 ở đây chỉ ghi phần include/internal do các luồng TV2 sử dụng.

| Luồng/lát | Phạm vi | Commit | Test/build hiện có | Trạng thái kỹ thuật | Nhóm trưởng nghiệm thu |
| --- | --- | --- | --- | --- | --- |
| Availability + Search | Query khả dụng, ngày nửa mở, maintenance, booking giữ phòng; API Search | `266a1d8` | Backend TV2 PASS; frontend PASS. | Refactor đã commit | Chưa ghi nhận |
| Checkout source | `getBookableProperty` cho dữ liệu room-search; giữ `getPropertyById` hiện tại | `b70a91d` | Backend & frontend PASS | Đã commit | Chưa ghi nhận |
| Booking preview + create | Preview, pricing snapshot, tạo booking và transaction | `ba70c47` | Backend & frontend PASS | Refactor đã commit | Chưa ghi nhận |
| Customer đọc/hủy | List, detail, cancel và ownership | `c8ccf5d` | Backend & frontend PASS | Refactor đã commit | Chưa ghi nhận |
| Host workflow | Host list/detail, confirm/reject/check-in/check-out | `b7221bf` | Backend & frontend PASS | Refactor đã commit | Chưa ghi nhận |
| Services | Service catalog theo property và dịch vụ gắn booking | `8d66132` | Backend & frontend PASS | Refactor đã commit | Chưa ghi nhận |
| U1 giao diện | Search + checkout | `6b7a676` | Frontend test, lint/build PASS | Đã commit | Chưa ghi nhận |
| U2 giao diện | Customer bookings/detail, hộp hủy cục bộ | `58d3f81` | Frontend test, lint/build PASS | Đã commit | Chưa ghi nhận |
| U3 giao diện | Host bookings/workflow/services và danh mục | `65b6e00` | Frontend test, lint 0 lỗi, build PASS | Đã commit | Chưa ghi nhận |
| **Lát 1 (R04, R05, R09)** | Cross-field price comparison, Host reject reason validation DTO, Amenity deduplication | `6a76ca1` | `BookingInputIntegrityTest`: 8/8 tests PASS | Refactor đã commit | Chưa ghi nhận |
| **Lát 2 (R06)** | Pure Domain Validators (bỏ exists:*), lookup qua Repo/Service, typed Query DTOs | `903bda9` | `BookingInputIntegrityTest`: 11/11 tests PASS; Backend full 229 tests PASS | Refactor đã commit | Chưa ghi nhận |
| **Lát 3 (R01, R02, R03)** | Chặn mutation mock checkout, lifecycle guard, preview sequence & snapshot invalidation | `72828b1` | `checkoutLifecycleGuard.test.js`: 5/5 tests PASS; Frontend 221 tests PASS | Đã commit | Chưa ghi nhận |
| **Lát 4 (R07, R08)** | Mapper bỏ fake pagination, Safe Bounded Pagination Loop cho checkout & active services | `1fa89f7` | `paginationSafety.test.js`: 3/3 tests PASS; Mapper tests 7/7 PASS; Frontend 225 tests PASS | Đã commit | Chưa ghi nhận |
| **Lát 5 (Scope)** | Loại bỏ binary artifact `backend/stayhub` khỏi Git tracking, đồng bộ tài liệu TV2 | `498f203` | Working tree sạch, không còn binary database | Đã commit | Chưa ghi nhận |
| **Lát A (PR10-03)** | Khôi phục 422 contract cho reference ID không tồn tại tại SearchService qua Repository | `7bf62c2` | `RoomSearchValidationTest` PASS; `BookingInputIntegrityTest`: 12/12 PASS | Đã commit | Chờ nghiệm thu |
| **Sync dev** | Merge `origin/dev` (PR #11 TV1), giải quyết conflict AuthenticationTest giữ dynamic auth check | `def4fbb` | `AuthenticationTest`: 77/77 tests PASS (1308 assertions) | Đã commit | Chờ nghiệm thu |
| **Lát B (PR10-01)** | Bỏ double-unwrap trong `propertyServiceCatalogService.js`, sửa mock test `paginationSafety.test.js` | `4f4c182` | `paginationSafety.test.js`: 4/4 PASS; Frontend 226 tests PASS | Đã commit | Chờ nghiệm thu |
| **Lát C (PR10-04)** | Reset `isPreviewing` khi thay đổi `guest_count` và bảo vệ lifecycle component checkout | `fe9f6a1` | `checkoutLifecycleGuard.test.js`: 6/6 PASS; Frontend 228 tests PASS | Đã commit | Chờ nghiệm thu |
| **Lát D (PR10-05)** | Reset `actionLoading` và `saving` khi chuyển booking context, bảo vệ deferred mutation | `ad9d54a` | `checkoutLifecycleGuard.test.js`: 8/8 PASS; Frontend 230 tests PASS | Đã commit | Chờ nghiệm thu |
| **Lát E (PR10-02)** | Nối SearchBar với `roomAvailabilitySearchService` & `/api/public/rooms/search`, tách Availability vs Catalog | `e2dfa84` | `roomAvailabilitySearchService.test.js`: 4/4 PASS; Frontend 234 tests PASS | Đã commit | Chờ nghiệm thu |
| **Lát 1 (PR12-06, PR12-08)** | An toàn DB test (fail-closed guard) & chặn tràn số tiền/số khoa học trả 422 thay vì 500 | Đã commit | `BookingInputIntegrityTest`: 15/15 PASS; `DatabaseSafetyTest` PASS; Backend full 1872 PASS | Đã commit | Chờ nghiệm thu |
| **Lát 2 (PR12-03, PR12-05)** | Tách Availability Mapper vào `src/mappers/` & hỗ trợ bộ lọc UC07 (`room_type_ids[]`, `min_rating`) | Đã commit | `roomAvailabilitySearchMapper.test.js`: 6/6 PASS; Frontend 240 PASS | Đã commit | Chờ nghiệm thu |
| **Lát 3 (PR12-01, PR12-04, PR12-07)** | React 19 StrictMode lifecycle guard & cô lập mutation context chống stale unlock/overwrite | Đã commit | `checkoutLifecycleGuard.test.js`: 12/12 PASS; Frontend 243 PASS | Đã commit | Chờ nghiệm thu |
| **Lát 4 (PR12-02, PR12-09)** | Nối "Đặt ngay" trực tiếp vào checkout URL & minh bạch hóa cắt cụt phân trang (`hasMore`, `isTruncated`, `total`) | Đang hoàn thiện | `paginationSafety.test.js`: 7/7 PASS; Frontend full 247 PASS; lint 0 lỗi; build PASS | Đang hoàn thiện | Chờ nghiệm thu |

## Bằng chứng kiểm thử toàn nhánh

| Lệnh ngày 2026-11-20 | Kết quả thực tế |
| --- | --- |
| `php artisan test` (backend full suite, không filter) | PASS: 1872 passed, 91 skipped (9700 assertions); 0 failed; exit 0 (54.99s) |
| `php artisan test --filter=BookingInputIntegrityTest` (backend TV2 integrity suite) | PASS: 15 passed, 81 assertions; 0 failed; exit 0 (1.24s) |
| `node --experimental-test-module-mocks --test tests/**/*.test.js` (frontend full tests) | PASS: 247 passed, 0 failed; exit 0 (2.25s) |
| `npm run lint` | PASS: 0 errors (3 warnings giao diện có sẵn); exit 0 |
| `npm run build` | PASS: Vite production build thành công (1.35s); exit 0 |
| `git diff --check` | PASS: Không có lỗi whitespace; exit 0 |

Kiểm thử full-suite xác nhận 100% độ tin cậy của code TV2 và sự tương thích hoàn hảo với mã nguồn mới nhất từ `dev` (sau khi merge PR #11 của TV1).

## Quyết định đã thống nhất

- Đã sửa triệt để 5 findings PR10 (PR10-01 đến PR10-05) với đầy đủ test hành vi thực tế (deferred promises, integration contracts).
- Phân biệt rành mạch giữa Availability Search (`/api/public/rooms/search` khi có ngày/khách) và Catalog Room Search (khi duyệt catalog phòng thuần túy), bảo toàn 100% chức năng TV1.
- Duy trì kiến trúc phân lớp: Controller mỏng → Pure Domain Validator (không query DB) → Service điều phối lookup qua Repository (ném ValidationException 422 chuẩn) → Eloquent Model → Response DTO & Pure Mapper.
- Đã tách nguồn checkout an toàn, chặn hoàn toàn mutation tạo booking thật từ dữ liệu mock.
- Giữ nguyên bất biến contract Booking ↔ Invoice/Review của TV3.

## Nguồn tiến độ

[Handover baseline](BASELINE_HANDOVER.md), [PR checklist](BASELINE_PR_CHECKLIST.md), [handover TV2](handover-member2.md), [booking API](../api/booking-module.md), [ARCHITECTURE.md](../../ARCHITECTURE.md).