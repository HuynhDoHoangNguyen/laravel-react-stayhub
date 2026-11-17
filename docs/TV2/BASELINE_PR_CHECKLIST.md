# Checklist PR TV2 vào dev — Booking & Operations

Ngày kiểm: 2026-11-17 (Asia/Saigon). Nhánh: `feature/member-2-booking`, HEAD `65b6e00`. Xem [bàn giao baseline](BASELINE_HANDOVER.md), [kiến trúc](../../ARCHITECTURE.md) và [tiến độ TV2](TV2_CHECKLIST.md).

## Hai mức kết luận

- **LOCAL TESTS VERIFIED:** hai bộ test đầy đủ đã chạy trên working tree hiện tại và đạt kết quả ghi bên dưới.
- **PR / MERGE APPROVAL PENDING:** chưa xác nhận diff sạch đúng scope, review, CI hoặc mergeability. Không suy ra được phép merge từ test local.

## Trạng thái kiểm tra trong lượt này

| Gate | Kết quả lệnh vừa chạy | Cách đối chiếu |
| --- | --- | --- |
| Nhánh | `[x]` `feature/member-2-booking` | `git branch --show-current` |
| Working tree sạch | `[ ]` Chưa đạt: status có `docs/handover-member2.md`, `frontend/.env` thay đổi; tài liệu TV2 hiện tại là file mới | `git status --short`; không đưa env vào PR |
| Chỉ file TV2 / không có env | `[ ]` Chưa chứng minh bằng diff staged cuối; xác nhận từng path trước PR | `git status`, `git diff`, staged file list khi chuẩn bị PR |
| Backend full tests | `[x]` PASS — 1.278 tests / 7.320 assertions | `php artisan test` trong `backend` |
| Frontend tests | `[x]` PASS — 172 tests / 0 fail | `node --experimental-test-module-mocks --test tests/*.test.js` trong `frontend` |
| Frontend lint | `[ ]` Chưa chạy trong lượt này | `npm run lint` |
| Frontend build | `[ ]` Chưa chạy trong lượt này | `npm run build` |
| Diff whitespace/scope | `[ ]` Chưa kiểm diff PR cuối | `git diff --check`, kiểm path staged |

## Gate trước khi mở PR

- [ ] Working tree/index chỉ gồm file được duyệt của TV2; không có `backend/.env`, `frontend/.env`, secret, build artifact hoặc file ngoài scope.
- [ ] Xác nhận `git status` và danh sách file staged; không lẫn thay đổi `docs/handover-member2.md` đang có từ trước.
- [ ] Chạy lại backend full suite, frontend test, `npm run lint`, `npm run build` trên đúng snapshot sẽ push.
- [ ] Review diff cuối để bảo đảm contract API, response/error shapes, status, headers, pagination/meta và decimal string không đổi.
- [ ] Xác nhận privacy 404 cho resource ngoài quyền sở hữu/route lồng sai quan hệ và 403 cho sai role.
- [ ] Xác nhận PENDING giữ phòng; khoảng ngày nửa mở, maintenance và recheck confirm không bị đổi.
- [ ] Xác nhận checkout không tạo Invoice; chỉ quản lý dịch vụ booking khi CHECKED_IN; snapshot tên/đơn vị/giá/amount được giữ.
- [ ] Xác nhận FK RESTRICT và migration `2026_10_08_000000_create_stayhub_schema.php` không bị sửa.
- [ ] Xác nhận không sửa file Catalog/TV1/TV3 hoặc file dùng chung ngoài scope đã duyệt.
- [ ] Đối chiếu route: app hiện dùng `/customer/checkout`; `/booking/checkout` không phải route hiện tại.
- [ ] Xác nhận các quyết định ở mục “Cần nhóm trưởng quyết định” bên dưới trước khi thay đổi chúng.
- [ ] PR target `dev`; TV1/TV3 review điểm tích hợp liên module; CI và mergeability đạt trên commit PR cuối.
- [ ] Owner/nhóm trưởng nghiệm thu riêng trước merge.

## Cần nhóm trưởng quyết định

- `propertyService.getPropertyById` đang dùng dữ liệu room search và đè delegate `catalog.getPropertyById`; checkout vẫn giữ chốt `source === 'api'`. Chốt khi nào khôi phục delegate hoặc chuyển trang chi tiết sang nguồn khác.
- Có chuyển `*Request` khỏi `Validators/` sang FormRequest/Request DTO hay giữ quy ước hiện tại.
- Có giữ `/search` làm canonical route, thêm alias hay đổi tài liệu/điều hướng.
- Không đổi contract Booking ↔ Invoice/Review trong PR nếu chưa thống nhất với TV3.

## Dependencies và giới hạn

- `git log` cho thấy refactor và giao diện U1–U3 đã có commit; điều đó không xác nhận kiểm thử trình duyệt hoặc nghiệm thu của nhóm trưởng.
- Lượt này không chạy lint/build, E2E trình duyệt, MySQL concurrency test, CI hoặc GitHub mergeability.
- Không sửa `.env`, chạy migration/seed trên database dùng chung, commit, push hoặc merge trong lượt tạo checklist.
