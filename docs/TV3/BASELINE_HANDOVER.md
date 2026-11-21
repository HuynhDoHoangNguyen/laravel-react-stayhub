# Bàn giao baseline StayHub

Biên tập: 2026-11-21 (Asia/Bangkok); evidence giữ ngày chạy gốc. **CLEANUP-ARCH-01 và HANDOVER-BASELINE-01 ACCEPTED**; owner đã merge PR#5 từ refactor/stayhub-layered-architecture vào dev. Không xác nhận CI/remote mới trong lượt dọn tài liệu.
Đây là hồ sơ refactor lịch sử. Hiện **15/18 UC CLOSED TV3**, theo [checklist](TV3_CHECKLIST.md); nghiệm thu 10 UC Invoice/Review ở [ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md). Refactor acceptance không thay nghiệm thu chức năng hoặc tích hợp end-to-end.

## 1. Phạm vi refactor đã nghiệm thu

| Module | Baseline được bàn giao |
| --- | --- |
| Invoice | Năm API đọc/tạo, raw validation/typed DTO, Repository, transaction/snapshot, mapper header/items và React API/service/mapper |
| Review | Bốn API đọc/tạo, audit Backend, Frontend mapper/service, trang đọc và modal tạo |
| Auth/Profile | Login/register, user projection, hồ sơ/mật khẩu/avatar, audit/fix và CredentialRules trong Validators/Auth |
| Public Catalog | UC04–UC05 Property list/detail |
| Host Catalog | UC18 Property/ảnh cơ sở, UC19 RoomType, năm luồng UC20 Room list/detail/create/update/status và audit/fix |

Refactor ACCEPTED cho UC15/16/34/35/37/38/56/57/58/60. Nghiệm thu chức năng của nhóm này và UC36 diễn ra sau baseline, tra hồ sơ tương ứng tại [mục lục](README.md).

## 2. Owner và dependency bàn giao

| Owner | Phạm vi nhận tiếp / giới hạn |
| --- | --- |
| TV1 | Duy trì Auth/Profile/Public Catalog/UC18/19 và năm luồng UC20; phần Catalog còn lại, UC21/22/23, Public Room/filter và Admin theo phân công |
| TV1 | DELETE Room và DB unique(property_id,room_number) chưa triển khai trong đợt này; uniqueness Room hiện dựa workflow lock |
| TV2 | Search/Availability, Booking lifecycle, check-in/check-out và dịch vụ/pricing; chuẩn hóa module về kiến trúc chung |
| TV1+TV2 | Booking/Maintenance writers phải phối hợp lock/recheck với Room status; race harness không chứng minh workflow production |
| TV2+TV3 | Booking thật cung cấp snapshot/COMPLETED và điểm vào Invoice/Review; TV3 giữ trách nhiệm QA/chức năng của mình |

Tại baseline bàn giao: UC21 chưa triển khai; CAT-ROOM-IMG-00 mới có kế hoạch, chưa kiểm kê/runtime. API root khi đó chưa đăng ký Booking production; owner hoãn lấy/merge nhánh TV2 vì còn phát triển và chưa phù hợp kiến trúc. Đây là nhận định lịch sử, không phải kết luận về source TV2 hiện tại. Source hiện tại đã include `api/booking.php`; T01–T03 vẫn chưa có evidence tích hợp được nghiệm thu.
Acceptance riêng SETUP-01/VAL-STRUCT-01 và evidence MAP-01 theo bảng checkpoint dưới; không mở lại refactor đã nghiệm thu.

## 3. Tương thích và trạng thái checkpoint

Áp dụng [ARCHITECTURE.md](../../ARCHITECTURE.md), không dùng bản handover cũ thay source/accepted working tree hiện hành. Giữ status/error order/messages, decimal/null/omission/pagination, session/CSRF/role/ownership, transaction/file lifecycle, cancellation/recovery; không tự replay mutation chưa rõ kết quả.

| Nhóm checkpoint | Trạng thái đã ghi nhận |
| --- | --- |
| ARCH/API/FE, INV-ARCH01/02, STRUCT/SIMPLIFY, MAP02 | ACCEPTED theo chuỗi chat; phạm vi refactor read/create, không tự đóng chức năng |
| REV-ARCH01/02/AUDIT, MAP03, REV-FE01 | ACCEPTED; API/mapper/service/pages/modal |
| AUTH/PROFILE-ARCH và FIX/AUDIT | ACCEPTED; F01 avatar outer transaction và F02 throttle CLOSED |
| CAT Public/MGMT/RT, Room01–05B/FIX/AUDIT | ACCEPTED năm luồng Room; multipart F01 CLOSED qua E16, không toàn UC20 |
| SETUP-01 / VAL-STRUCT-01 | Technical PASS lịch sử; acceptance riêng chưa ghi nhận/PENDING |
| MAP-01 | Hồ sơ bằng chứng PARTIAL; không dùng để mở lại refactor đã nghiệm thu |

Code tracked/untracked đã nghiệm thu là baseline; không khôi phục deleted paths hoặc extract đè checkout/data. Quyền shared files/TV1/TV2 phải theo exact task scope; không tự commit/push/merge/reset/seed dev.

## 4. Evidence index lịch sử

Counts là tests/assertions của từng lượt gốc; không cộng thành suite mới. SQLite không thay InnoDB/race; TEMP/artifacts local ngoài Git chưa được xác nhận tồn tại trong cleanup.

| Lượt | Kiểm chứng đã ghi |
| --- | --- |
| API-01 | SQLite focused 12/93, full 419/2.159. Renderer, exception và parity Auth. |
| FE-01 | Node focused 10, full 59. Adapter/Auth, lint/build. |
| INV-ARCH-01A | SQLite focused 9/63, Invoice 84/353, full 428/2.222. |
| STRUCT-01A | SQLite full 428/2.222, syntax và namespace. |
| STRUCT-01B | SQLite API focused 16/117, full 432/2.246. |
| STRUCT-01C | 5 skill validators, liên kết và scope. |
| INV-ARCH-01B | SQLite read HTTP focused 31/191, full 440/2.373 sau khi đổi assertion quyền theo scope duyệt. |
| INV-ARCH-01C | Node focused 8, full 67, lint/build. |
| SIMPLIFY-01A | SQLite full 440/2.371, container và repository/query. |
| SIMPLIFY-01B | SQLite full 446/2.447, Node 67. |
| SIMPLIFY-01C | Skill validators, nội dung, liên kết và scope. |
| INV-ARCH-02A | SQLite 452/2.489, Node 67. |
| INV-ARCH-02B.1 | SQLite 463/2.569, Node 67. Composer audit lúc đó exit 1 với 9 advisory trên 6 package baseline. |
| INV-ARCH-02B.2A | SQLite 466/2.679, Node 67. |
| INV-ARCH-02B.2B | SQLite 480/2.738, Node 67. |
| INV-ARCH-02B.3 | SQLite 489/2.816, Node 67. |
| INV-ARCH-02C.1 | SQLite 492/2.860, Node 75. |
| INV-ARCH-02C.2 | Node focused 16, full 75, lint/build đạt cùng các warning đã ghi. |
| INV-ARCH-02C.3 | 5 skill validators và 54 liên kết/anchor. |
| INV-ARCH-02-AUDIT | SQLite focused 114/718, full 492/2.860. Node 75, lint/build và 5 validators. Lượt này chưa chạy browser hoặc MySQL concurrency cho Invoice. |
| VAL-STRUCT-01 | SQLite focused 126/866, full 494/2.875. Node 75, 5 validators và 54 liên kết/anchor. |

Nguồn E1–E2 là Task Result/checklist lịch sử và log local khi có, ví dụ .agent-workspace/tv3/inv-arch-02-audit/backend-full.log và .agent-workspace/tv3/val-struct-01/backend-full.log.

| Evidence | Kết quả lịch sử cần giữ | Nguồn / giới hạn |
| --- | --- | --- |
| E3 REV-ARCH-AUDIT | SQLite focused 201/1285, full 606/3806; Node 75, lint/build PASS; role/ownership/parity, rollback/outer commit, mapper 0query | Task Result đã nghiệm thu; không phải MySQL concurrency |
| E4 REV-FE-01 | SQLite full 828/5311; Node 133; lint/build PASS; Edge/XAMPP thật ở390/768/1440px; duplicate submit một POST201, gửi lại409/một Review | TEMP stayhub-rev-fe-*; fixture COMPLETED/session thật, chưa Booking production; Review race còn mở |
| E5 PROFILE-ARCH | 01A full 830/5321;01B831/5326;01C832/5341; FIX full 844/5455; Node 133/lint/build PASS; audit chốt12/114 mới | TEMP stayhub-profile*; PHPUnit SQLite; MariaDB/disk/Edge thật tách riêng; F01 avatar outer transaction/F02 throttle đã đóng; full suite ở audit chốt là kế thừa FIX |
| E6 CAT-MGMT/RT-AUDIT | MGMT fullSQLite787/4958; RT828/5311,Node 125; UC19 Edge CRUD/quyền/409/419/late response | TEMP stayhub-mgmt-audit-* và stayhub-rt-audit-*; counts là các lượt khác |
| E7 CAT-ROOM-00 | CatalogSQLite213/1399,full 844/5455,Node 133; XAMPP/Edge Catalog; kiểm kê Room chỉ đọc | TEMP stayhub-cat-room00-*; trước năm API/UI Host Room |
| E8 CAT-ROOM-01 | Room list14/70 trên SQLite và MariaDB; fullSQLite858/5527,Node 138; browser/API thật | TEMP stayhub-cat-room01-*; lượt đầu route count27/28 FAIL, thêm đúng một dòng test đã duyệt, rerun PASS |
| E9 CAT-ROOM-02 | Room detail15/69 MariaDB, list/detailSQLite29/139; fullSQLite873/5598,Node 142 | TEMP stayhub-cat-room02-*; session thật/decimal/null/ownership; lint/build PASS |
| E10 CAT-ROOM-03 | Create51/220 cả SQLite/MariaDB; fullSQLite924/5820,Node 146; ba race InnoDB và Edge | TEMP stayhub-cat-room03-*; uniqueness theo workflow, không DB unique mới; lint/build PASS |
| E11 CAT-ROOM-04 | Update55/288 SQLite/MariaDB; fullSQLite979/6110,Node 150; năm race InnoDB | TEMP stayhub-cat-room04-*; giữ status/snapshot/no-op/rollback; lint/build PASS |
| E12 CAT-ROOM-05A | Chốt ma trận trạng thái; CatalogSQLite348/2046,full 979/6110,Node 150/lint/build PASS | TEMP stayhub-cat-room05a-*; spec trước API/UI05B; lỗi IPC/metadata harness được sửa ngoài source rồi chạy lại |
| E13 CAT-ROOM-05B | Status177/505 SQLite/MariaDB; Catalog 525/2551,full 1156/6617,Node 154/lint/build PASS; sáu race | TEMP stayhub-cat-room05b-*; Service status thật, writer Booking/Maintenance là harness |
| E14 CAT-ROOM-AUDIT | MariaDB 312/1152; SQLite full 1156/6617,Node 154/lint/build PASS;14race InnoDB | TEMP stayhub-cat-room-audit-*; kết luận ban đầu PARTIAL do F01 multipart bỏ qua file trùng key |
| E15 CAT-ROOM-FIX-01 | Create60/271 SQLite/MariaDB; Catalog 534/2602,full 1165/6668,Node 154/lint/build PASS; HTTP multipart thật | TEMP stayhub-room-fix01-*; RED trước fix/GREEN sau fix; F01 đóng qua E16 |
| E16 CAT-ROOM-AUDIT chốt | Năm focusedMariaDB321/1203, CatalogSQLite534/2602,full 1165/6668; create outer commit/rollback mới | TEMP stayhub-room-audit-recheck-*; Node/lint/build kế thừa E15,14race kế thừa E14; ACCEPTED năm luồng, không toàn UC20 |
| E17 CLEANUP-ARCH-01 đầu | RegistrationMariaDB90/501; Auth/ProfileSQLite265/1662,full 1165/6668;33HTTP thật | TEMP stayhub-cleanup01-*; lúc đó PARTIAL vì app dirs/datadir chưa dọn; XAMPP dev lỗi proxies_priv, không repair |
| E18 CLEANUP-ARCH-01 chốt | Helper/routes/autoload/hash đối chiếu;12app dirs và datadir test đã dọn;55offline dev hashes giữ nguyên | TEMP stayhub-cleanup01-close-*; test/runtime kế thừa E17,SQL dev refused/NOT VERIFIED; TECHNICAL PASS/ACCEPTED |
| E19 HANDOVER-BASELINE-01 | SQLite 1165/6668; sáu targetedMariaDB411/1704;Node 154,lint/build exit 0;ZIP643 file/manifest/extract PASS | Artifact bàn giao dưới đây; browser/HTTP/race kế thừa, không CI/mergeability proof |

Lint lịch sử còn ba Fast Refresh warnings; build có chunk>500kB và timing diagnostic ở lượt ghi nhận. Composer audit INV-ARCH-02B.1: exit 1, chín advisories/sáu package; chưa có bằng chứng xử lý trong phạm vi refactor. Không suy là audit dependency hiện tại đã chạy.
TEMP và artifacts local không theo Git; chưa xác nhận còn tồn tại. Không dùng hồ sơ lịch sử để kết luận dependency audit hiện tại đã đạt.

## 5. Gói handover và hạ tầng lịch sử

- Snapshot 2026-11-16: HEAD `7dac999b2808ca6fd583985e19d31248cae1b4cc` cộng working tree; merge-base/dev `951f62efc70127a3719c840ae529f8d311842a49`. Owner merge sau đóng gói; gói không đại diện source UC36/UC39/UC48 hiện hành.
- Artifact `.agent-workspace/handover-baseline-3dbd49fe4a3d4410882f7c7efad786ed/`: source.zip643 files, manifest SHA256/ZIP/extract, deleted-paths14, git-state/checks. Loại env/secrets/vendor/node_modules/build/uploads/datadir/artifacts; scan không đảm bảo mọi secret. Nhận gói vào thư mục mới, kiểm hash/deletions, tạo env riêng/cài lockfiles; source đổi phải đóng gói và kiểm lại.
- E19: SQLite 1165/6668; targeted MariaDB 10.4.32 sáu suite411/1704 (Registration90/501,RoomList14/70,Detail15/69,Create60/271,Update55/288,Status177/505); Node 154/lint/buildexit0. Guard từng suite giữ nguyên; browser/HTTP/race kế thừa, không CI hoặc mergeability proof.
- XAMPP dev lúc đó lỗi mysql.proxies_priv, SQL dev NOT VERIFIED;55offline fingerprints giữ. Sau đó DEV-DB-01 chuyển dev sang MySQL Docker 8.4.13:7migrations/27 tables hash/count sau restart,21FK không orphan,HTTP Invoice/PublicProperty/Review 200; không chứng minh payment/Booking production. Các ghi chú env trong gói là lịch sử; cấu hình hiện hành theo README/ARCHITECTURE, không áp lại backend/.env cũ.
- Tích hợp tiếp phải kiểm source/SHA hiện tại, exact shared-file scope/contract/regression/engine/guards/session/ownership/transaction/cleanup và workflow thật. Dependency TV2 hiện hành giữ T01–T03 ở ma trận.
