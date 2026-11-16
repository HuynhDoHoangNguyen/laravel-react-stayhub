# Checklist PR vào dev — HANDOVER-BASELINE-01

Ngày kiểm:2026-11-16, Asia/Bangkok. Phạm vi: baseline refactor đã nghiệm thu cộng working tree; xem [bàn giao](BASELINE_HANDOVER.md), [kiến trúc](../../ARCHITECTURE.md), [checklist TV3](TV3_CHECKLIST.md).

## Hai mức kết luận

- **PACKAGE VERIFIED:** đủ bằng chứng source inventory/test/ZIP/hash/cleanup cho snapshot local.
- **MERGE APPROVAL PENDING:** chưa commit/push/PR/CI/review trên code cuối; không suy ra mergeable từ các gate local.

Task hiện tại không stage index, commit, push, merge, checkout/rebase hoặc tự gửi nội dung cho team.

## Các gate local trước khi tạo PR

| Gate | Kết quả của lượt này | Cách đối chiếu |
| --- | --- | --- |
| Source inventory | Tracked/untracked/deleted đầy đủ trong manifest; không dùng HEAD-only archive | Relative path/action/domain/SHA256/size |
| Secrets/artifact | Không thấy token/private-key theo pattern scan; env thật và local artifacts ngoài ZIP | inventory-safety.json/exclusions; không khẳng định scan phát hiện mọi dạng secret |
| Git diff whitespace | PASS sau bỏ4space trên đúng dòng91InvoiceService | git diff --check, không đổi logic |
| Kiến trúc | Sửa đúng ô Auth/Profile ở bảng14.2, không ghi tiến độ vào ARCHITECTURE | diff/scope check |
| Full Backend |1165tests/6668assertions PASS, SQLite :memory: | php artisan test --compact |
| Targeted XAMPP |411tests/1704assertions PASS, MariaDB10.4.32/datadir riêng |6class, nguyên guard, actual migration proof |
| Frontend tests |154PASS | node --experimental-test-module-mocks --test tests/*.test.js |
| Lint | Exit0,3FastRefresh warnings nền | npm run lint |
| Build | Exit0, chunk lớn và timing diagnostic | npm run build |
| Static/routes | Syntax/autoload/namespace/route/links/skills được kiểm trước đóng gói | checks/static-check.json |
| ZIP/extract | Đối chiếu toàn bộ file/hash/exclusions/deletions | manifest.json và checks/package-verification.json |
| Baseline/data/index | Giữ hash ngoài5path,55dev file offline, index/HEAD không đổi | checks/final-verification.json |

Các kết quả static/package/final được xác nhận bởi artifact cuối; không dùng bảng này thay việc kiểm manifest/extract. Counts Backend/MariaDB/Frontend là lượt chạy mới của task, runtime browser/race cũ chỉ kế thừa.

Lần Node không có --experimental-test-module-mocks:76pass/4fail (mock.module is not a function) được giữ log. Rerun với cờ runtime phù hợp đạt154PASS, không sửa test/source để đạt kết quả.

## Matrix targeted MariaDB

| Suite | Tests/assertions | Guard |
| --- | --- | --- |
| RegistrationTest |90/501 | STAYHUB_REGISTRATION_TEST_DATABASE |
| HostRoomListTest |14/70 | STAYHUB_ROOM_TEST_DATABASE |
| HostRoomDetailTest |15/69 | STAYHUB_ROOM_DETAIL_TEST_DATABASE |
| HostRoomCreateTest |60/271 | STAYHUB_ROOM_CREATE_TEST_DATABASE |
| HostRoomUpdateTest |55/288 | STAYHUB_ROOM_UPDATE_TEST_DATABASE |
| HostRoomStatusTest |177/505 | STAYHUB_ROOM_STATUS_TEST_DATABASE |

Mỗi class dùng schema GUID32/prefix hiện hữu riêng; APP_ENV=testing, DB_URL rỗng, cache/session array, SELECT DATABASE/migration proof. Không sửa guard SQLite-only của Auth/Profile hoặc gọi full SQLite là full MySQL.

## Target dev và nguy cơ tích hợp

Đã git fetch --no-tags origin dev: target951f62efc70127a3719c840ae529f8d311842a49, merge-base cùng target; HEAD7dac999b2808ca6fd583985e19d31248cae1b4cc ahead2/behind0. Không có target-changed paths kể từ merge-base tại thời điểm kiểm, nên overlap set0.

**Đây không phải GitHub mergeability hoặc thử merge.** Working tree chưa commit, PR thực tế chưa tồn tại trong task. Nếu target/commit/source đổi, so sánh lại và chạy gates trên kết quả tích hợp.

## Gate bắt buộc ngay trước merge — chưa thực hiện

- [ ] Người dùng/owner duyệt phạm vi source cuối và tạo commit/push riêng.
- [ ] PR target dev; actual diff đủ file mới/xóa, khớp manifest và không kèm local artifact/secret/env mới.
- [ ] Team TV1/TV2 review thay đổi liên module, mapping namespace và contract; consumer ở nhánh khác không được mặc định tương thích.
- [ ] GitHub xác nhận mergeability/conflicts trên commit cuối.
- [ ] CI bắt buộc đạt trên PR cuối; kiểm code sau conflict resolution hoặc target mới.
- [ ] Integration session/ownership/transactions/file cleanup/route và Booking/Maintenance theo phạm vi thật được xác nhận.
- [ ] Owner duyệt merge riêng; không merge chỉ vì gói snapshot đã kiểm chứng.

## Dependencies và exclusions phải giữ trong PR

- Refactor completed trong phạm vi đã nghiệm thu;10UC TV3 có luồng chính không đồng nghĩa nghiệm thu end-to-end.
- Booking thật/Review concurrency/Invoice QA; Room DB uniqueness và writers Booking/Maintenance; DELETE Room và các UC chưa làm giữ backlog.
- UC21 chưa triển khai, không mở rộng vào PR này chỉ để đóng checklist.
- frontend/.env đã track trên dev/HEAD và không đổi; snapshot loại rõ file này, giữ .env.example. Muốn untrack/đổi .gitignore phải task/phê duyệt riêng.
- MySQL dev lỗi bảng quyền trước đó chưa sửa; test dùng datadir cô lập. SQL dev vẫn NOT VERIFIED,55offline fingerprints là evidence bảo toàn.
- Gói source.zip là snapshot chia sẻ; không đưa ZIP, checks hoặc datadir vào PR.

## Draft PR — chỉ nội dung local

Title: Chuẩn hóa kiến trúc StayHub và tích hợp baseline Host Catalog

Body:

Chuẩn hóa các luồng Invoice/Review/Auth/Profile và Catalog theo Validator, typed DTO, Service/QueryService, Repository, Mapper/Response DTO; nối các consumer React qua domain API/service/mapper. Phạm vi Host Catalog gồm Property/ảnh cơ sở, RoomType và5luồng Room đã được nghiệm thu; dọn lớp cũ và chuyển CredentialRules về Validators/Auth.

Giữ contract/session/ownership, snapshots và transaction theo baseline. Kèm tài liệu bàn giao TV1/TV2, ma trận phần còn mở và checklist pre-merge. Không triển khai UC21 hoặc workflow Booking TV2 chưa tích hợp.

Validation: full Backend SQLite1165/6668; targeted MariaDB411/1704; Node154PASS với module-mock flag; lint/build exit0 với warnings đã ghi. Source ZIP được verify bằng manifest/extract/hash, không thay CI/review trên PR cuối.

Cần TV1/TV2 rà contract/consumer liên module và phối hợp Booking/Maintenance. Chưa chứng minh availability/workflow production khi module chưa tích hợp; các UC TV3 TODO và QA end-to-end được theo dõi riêng.

## Sau task này

Dừng ở package local và report. Chưa commit/push/merge, chưa tạo PR thật hoặc triển khai UC21. Nếu sửa source sau manifest phải regenerate/reverify gói; nếu source/target thay đổi sau đó phải kiểm lại PR gates.
