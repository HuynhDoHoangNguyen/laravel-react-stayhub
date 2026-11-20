# Checklist PR TV2 vào dev — Booking & Operations

Ngày kiểm: 2026-11-20 (Asia/Saigon). Nhánh: `feature/member-2-booking`. HEAD hiện tại: `7e14686`. Bàn giao sau khi hoàn tất khắc phục toàn diện 9 findings kiến trúc & chức năng PR12-01–PR12-09 chuẩn bị cho **PR #12**, kế thừa 5 findings PR10 và đồng bộ merge code mới từ `dev` (PR #11 TV1). Xem [bàn giao baseline](BASELINE_HANDOVER.md), [kiến trúc](../../ARCHITECTURE.md) và [tiến độ TV2](TV2_CHECKLIST.md).

## Hai mức kết luận

- **LOCAL TESTS VERIFIED:** toàn bộ full test suite backend (không filter), test frontend, lint và build đã chạy thành công 100% trên working tree hiện tại, không có lỗi.
- **READY FOR PR REVIEW:** Đã khắc phục đầy đủ 9 findings PR12-01–PR12-09 và 5 findings PR10 theo yêu cầu Nhóm trưởng, giải quyết merge conflict với TV1 sạch sẽ, sẵn sàng mở và merge **PR #12** vào nhánh `dev`.

## Trạng thái kiểm tra trong lượt này

| Gate | Kết quả lệnh vừa chạy | Cách đối chiếu |
| --- | --- | --- |
| Nhánh | `[x]` `feature/member-2-booking` | `git branch --show-current` |
| Working tree sạch | `[x]` Đạt: Không có `.env`, không có secret; không có binary database artifact | `git status --short` |
| Chỉ file TV2 / không có env | `[x]` Đạt: Chỉ bao gồm các file Backend/Frontend và Test thuộc phạm vi TV2 (giữ file TV1 nguyên vẹn sau merge dev) | `git status`, `git diff` |
| Backend full suite | `[x]` PASS — 1872 passed, 91 skipped, 9700 assertions, 0 failed (54.99s) | `php artisan test` trong `backend` |
| Backend TV2 suite | `[x]` PASS — 233 passed, 4 skipped, 919 assertions, 0 failed | `php artisan test --filter=Booking` trong `backend` |
| Frontend full tests | `[x]` PASS — 247 tests / 0 fail (2.25s) | `node --experimental-test-module-mocks --test tests/**/*.test.js` trong `frontend` |
| Frontend lint | `[x]` PASS — 0 errors (3 warnings giao diện có sẵn) | `npm run lint` trong `frontend` |
| Frontend build | `[x]` PASS — Vite production bundle hoàn thành (1.35s) | `npm run build` trong `frontend` |
| Diff whitespace/scope | `[x]` Đạt: Không có whitespace lỗi, không chạm vào TV1/TV3 ngoài phạm vi | `git diff --check` |

## Gate trước khi mở PR

- [x] Working tree/index chỉ gồm file được duyệt của TV2; không có `backend/.env`, `frontend/.env`, secret, build artifact hoặc file ngoài scope.
- [x] Đã kéo code mới nhất từ `dev` (PR #11 của TV1), giải quyết conflict bảo toàn dynamic route protection test.
- [x] Chạy lại backend full suite, frontend test, `npm run lint`, `npm run build` trên đúng snapshot sau khi sửa.
- [x] Review diff cuối để bảo đảm contract API, response/error shapes, status, headers, pagination/meta và decimal string không đổi.
- [x] Xác nhận privacy 404 cho resource ngoài quyền sở hữu/route lồng sai quan hệ và 403 cho sai role.
- [x] Xác nhận PENDING giữ phòng; khoảng ngày nửa mở, maintenance và recheck confirm không bị đổi.
- [x] Xác nhận checkout không tạo Invoice; chỉ quản lý dịch vụ booking khi CHECKED_IN; snapshot tên/đơn vị/giá/amount được giữ.
- [x] Xác nhận FK RESTRICT và migration `2026_10_08_000000_create_stayhub_schema.php` không bị sửa.
- [x] Xác nhận không sửa file Catalog/TV1/TV3 hoặc file dùng chung ngoài scope đã duyệt.
- [x] Đã xử lý triệt để 9 findings PR12-01–PR12-09 bằng integration tests, deferred promise tests và database safety guards.
- [x] PR target `dev`; TV1/TV3 review điểm tích hợp liên module.

## Trạng thái các quyết định kỹ thuật PR #12

- **PR12-01:** Bảo vệ `isMountedRef` trong `CustomerCheckoutPage` sống sót qua chu kỳ double-mount của React 19 StrictMode.
- **PR12-02:** Nối hoàn chỉnh nút "Đặt ngay" tại `SearchRoomCard` sang direct checkout URL kèm đầy đủ param phòng & ngày/khách khi `isAvailableDate === true`.
- **PR12-03:** Thống nhất luồng UC07 `room_type_ids[]` và `min_rating` từ URL → Validator → DTO → Query; tính điểm trung bình thực tế từ bảng `reviews`.
- **PR12-04:** Bổ sung `mutationRequestIdRef` cô lập async mutation context tại `HostBookingServicesPage` và `HostBookingDetailPage`, ngăn request cũ mở khóa thao tác mới.
- **PR12-05:** Tách mapper thuần túy `roomAvailabilitySearchMapper.js` vào `frontend/src/mappers/`, ném `MapperError` khi payload sai cấu trúc, phân biệt danh sách rỗng hợp lệ.
- **PR12-06:** An toàn database test: Hook `beforeRefreshingDatabase()` trong `TestCase.php` fail-closed nếu DB target không thuộc whitelist test.
- **PR12-07:** Bổ sung `activeBookingIdRef`, `cancelRequestIdRef`, `isMountedRef` tại `CustomerBookingDetailPage`, ngăn late cancel response ghi đè booking mới.
- **PR12-08:** Chặn số khoa học (`1e101`) và vượt trần `MAX_MONEY` tại `RoomSearchValidator`; bắt `OverflowException` trả HTTP 422 chuẩn thay vì 500.
- **PR12-09:** Safe Bounded Pagination (`propertyServiceCatalogService` & `propertyService.getBookableProperty`): Giữ đúng server `total`, gắn cờ minh bạch `hasMore` và `isTruncated` khi chạm giới hạn an toàn.
- Giữ nguyên bất biến contract Booking ↔ Invoice/Review của TV3.

## Dependencies và giới hạn

- Kết quả kiểm thử trên là từ lượt chạy thực tế ngày 2026-11-20 với snapshot HEAD `7e14686`.
- Backend full suite sử dụng SQLite in-memory test runner của Laravel; kiểm tra khóa đồng thời `lockForUpdate` trên MySQL production cần môi trường MySQL chuyên biệt.
- Không sửa `.env`, không chạy migration/seed trên database dùng chung, tuân thủ nghiêm ngặt quy trình bàn giao của dự án.
