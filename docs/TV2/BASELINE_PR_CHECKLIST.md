# Checklist PR TV2 vào dev — Booking & Operations

Ngày kiểm: 2026-11-20 (Asia/Saigon). Nhánh: `feature/member-2-booking`. HEAD hiện tại: `e2dfa84`. Bàn giao sau khi hoàn tất sửa 5 findings PR10-01–PR10-05 (Task TV2-PR10-FIX-01) và đồng bộ merge code mới từ `dev` (PR #11 TV1). Xem [bàn giao baseline](BASELINE_HANDOVER.md), [kiến trúc](../../ARCHITECTURE.md) và [tiến độ TV2](TV2_CHECKLIST.md).

## Hai mức kết luận

- **LOCAL TESTS VERIFIED:** toàn bộ full test suite backend (không filter), test frontend, lint và build đã chạy thành công 100% trên working tree hiện tại, không có lỗi.
- **READY FOR PR REVIEW:** Đã khắc phục đầy đủ 5 findings PR10-01–PR10-05 theo phản hồi của Nhóm trưởng, giải quyết merge conflict với TV1 sạch sẽ, sẵn sàng xin review lại PR #10 để merge vào nhánh `dev`.

## Trạng thái kiểm tra trong lượt này

| Gate | Kết quả lệnh vừa chạy | Cách đối chiếu |
| --- | --- | --- |
| Nhánh | `[x]` `feature/member-2-booking` | `git branch --show-current` |
| Working tree sạch | `[x]` Đạt: Không có `.env`, không có secret; không có binary database artifact | `git status --short` |
| Chỉ file TV2 / không có env | `[x]` Đạt: Chỉ bao gồm các file Backend/Frontend và Test thuộc phạm vi TV2 (giữ file TV1 nguyên vẹn sau merge dev) | `git status`, `git diff` |
| Backend full suite | `[x]` PASS — 1868 passed, 91 skipped, 9688 assertions, 0 failed (57.79s) | `php artisan test` trong `backend` |
| Backend TV2 suite | `[x]` PASS — 230 tests / 882 assertions (TV2 & Integrity Feature tests) | `php artisan test --filter=Booking` trong `backend` |
| Frontend full tests | `[x]` PASS — 234 tests / 0 fail (2.82s) | `node --experimental-test-module-mocks --test tests/**/*.test.js` trong `frontend` |
| Frontend lint | `[x]` PASS — 0 errors (3 warnings giao diện có sẵn) | `npm run lint` trong `frontend` |
| Frontend build | `[x]` PASS — Vite production bundle hoàn thành (1.40s) | `npm run build` trong `frontend` |
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
- [x] Đã xử lý triệt để 5 findings PR10-01–PR10-05 theo yêu cầu của Nhóm trưởng bằng integration tests và deferred promise tests.
- [x] PR target `dev`; TV1/TV3 review điểm tích hợp liên module.

## Trạng thái các quyết định kỹ thuật

- Đã nối `SearchBar` với `roomAvailabilitySearchService` gọi `/api/public/rooms/search` khi có tiêu chí ngày/khách; tách biệt rõ ràng với Catalog Search (PR10-02).
- Bỏ bóc `res.data` lần hai trong `propertyServiceCatalogService.listActiveForProperty`, sửa test mock `paginationSafety.test.js` sang đúng contract JSON body của Domain API (PR10-01).
- Khôi phục contract trả về 422 cho reference entity không tồn tại qua Repository/Service lookup, giữ nguyên Domain Validator thuần túy không query DB (PR10-03).
- Reset `isPreviewing = false` khi người dùng thay đổi số khách trong lúc preview đang chạy, kèm component lifecycle guard (PR10-04).
- Reset `actionLoading = false` và `saving = ''` khi người dùng đổi context Booking khác trong Host detail/services pages, kèm deferred promise tests (PR10-05).
- Giữ nguyên bất biến contract Booking ↔ Invoice/Review của TV3.

## Dependencies và giới hạn

- Kết quả kiểm thử trên là từ lượt chạy thực tế ngày 2026-11-20 với snapshot HEAD `e2dfa84`.
- Backend full suite sử dụng SQLite in-memory test runner của Laravel; kiểm tra khóa đồng thời `lockForUpdate` trên MySQL production cần môi trường MySQL chuyên biệt.
- Không sửa `.env`, không chạy migration/seed trên database dùng chung, tuân thủ nghiêm ngặt quy trình bàn giao của dự án.
