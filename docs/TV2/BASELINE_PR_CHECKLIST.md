# Checklist PR TV2 vào dev — Booking & Operations

Ngày kiểm: 2026-11-19 (Asia/Saigon). Nhánh: `feature/member-2-booking`. Bàn giao sau khi hoàn tất sửa findings R01–R09 (Task TV2-PR09-FIX). Xem [bàn giao baseline](BASELINE_HANDOVER.md), [kiến trúc](../../ARCHITECTURE.md) và [tiến độ TV2](TV2_CHECKLIST.md).

## Hai mức kết luận

- **LOCAL TESTS VERIFIED:** toàn bộ test backend, test frontend, lint và build đã chạy thành công 100% trên working tree hiện tại, loại bỏ hoàn toàn file database artifact `backend/stayhub`.
- **READY FOR PR REVIEW:** Đã khắc phục đầy đủ 9 finding R01–R09 theo phản hồi của Nhóm trưởng, sẵn sàng tạo Pull Request mới vào nhánh `dev`.

## Trạng thái kiểm tra trong lượt này

| Gate | Kết quả lệnh vừa chạy | Cách đối chiếu |
| --- | --- | --- |
| Nhánh | `[x]` `feature/member-2-booking` | `git branch --show-current` |
| Working tree sạch | `[x]` Đạt: Không có `.env`, không có secret; file artifact `backend/stayhub` đã bị xóa | `git status --short` |
| Chỉ file TV2 / không có env | `[x]` Đạt: Chỉ bao gồm các file Backend/Frontend và Test thuộc phạm vi TV2 | `git status`, `git diff` |
| Backend full tests | `[x]` PASS — 229 tests / 880 assertions (TV2 & Integrity Feature tests) | `php artisan test --filter=Booking` trong `backend` |
| Frontend tests | `[x]` PASS — 225 tests / 0 fail | `node --experimental-test-module-mocks --test tests/*.test.js` trong `frontend` |
| Frontend lint | `[x]` PASS — 0 errors (3 warnings giao diện có sẵn) | `npm run lint` |
| Frontend build | `[x]` PASS — Vite production bundle hoàn thành | `npm run build` |
| Diff whitespace/scope | `[x]` Đạt: Không có whitespace lỗi, không chạm vào TV1/TV3 | `git diff --check` |

## Gate trước khi mở PR

- [x] Working tree/index chỉ gồm file được duyệt của TV2; không có `backend/.env`, `frontend/.env`, secret, build artifact hoặc file ngoài scope.
- [x] Đã loại bỏ file SQLite artifact `backend/stayhub` khỏi Git tracking.
- [x] Chạy lại backend full suite, frontend test, `npm run lint`, `npm run build` trên đúng snapshot.
- [x] Review diff cuối để bảo đảm contract API, response/error shapes, status, headers, pagination/meta và decimal string không đổi.
- [x] Xác nhận privacy 404 cho resource ngoài quyền sở hữu/route lồng sai quan hệ và 403 cho sai role.
- [x] Xác nhận PENDING giữ phòng; khoảng ngày nửa mở, maintenance và recheck confirm không bị đổi.
- [x] Xác nhận checkout không tạo Invoice; chỉ quản lý dịch vụ booking khi CHECKED_IN; snapshot tên/đơn vị/giá/amount được giữ.
- [x] Xác nhận FK RESTRICT và migration `2026_10_08_000000_create_stayhub_schema.php` không bị sửa.
- [x] Xác nhận không sửa file Catalog/TV1/TV3 hoặc file dùng chung ngoài scope đã duyệt.
- [x] Đã xử lý triệt để 9 finding R01–R09 theo yêu cầu của Nhóm trưởng.
- [x] PR target `dev`; TV1/TV3 review điểm tích hợp liên module.

## Trạng thái các quyết định kỹ thuật

- Đã tách `getBookableProperty` làm nguồn cho checkout, bổ sung safe bounded pagination và chặn mutation từ dữ liệu mock (R01, R08).
- Đã hoàn tất chuyển đổi toàn bộ `FormRequest` sang `Domain Validators` & `Typed Request/Query DTOs`, bỏ hoàn toàn `exists:*` khỏi Validators (R06).
- Đã chuyển đổi `JsonResource` sang `Typed Response DTOs` & pure `Mappers` (R07).
- Route checkout chính thức là `/customer/checkout`; route tìm kiếm chính thức là `/search`.
- Giữ nguyên bất biến contract Booking ↔ Invoice/Review của TV3.

## Dependencies và giới hạn

- `git log` cho thấy refactor và giao diện U1–U3 đã có commit; điều đó không xác nhận kiểm thử trình duyệt hoặc nghiệm thu của nhóm trưởng.
- Lượt này không chạy lint/build, E2E trình duyệt, MySQL concurrency test, CI hoặc GitHub mergeability.
- Không sửa `.env`, chạy migration/seed trên database dùng chung, commit, push hoặc merge trong lượt tạo checklist.
