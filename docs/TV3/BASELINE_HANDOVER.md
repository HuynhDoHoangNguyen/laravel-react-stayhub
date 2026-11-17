# Bàn giao baseline StayHub

Cập nhật hồ sơ: 2026-11-17 (Asia/Bangkok). CLEANUP-ARCH-01 và HANDOVER-BASELINE-01 đã được owner nghiệm thu. Owner đã tạo/merge PR #5 từ refactor/stayhub-layered-architecture vào dev; không coi các trạng thái chờ PR/merge của lượt đóng gói cũ là trạng thái hiện hành.
Baseline hiện hành khi dọn tài liệu: `feature/tv3-uc36-payment-status`, HEAD `6bd6838` cộng working tree đã nghiệm thu. Chưa kiểm CI/remote mới trong lượt tài liệu này.
Tiến độ chức năng tại [checklist](TV3_CHECKLIST.md); contract UC36 tại [workflow](INVOICE_PAYMENT_WORKFLOW.md), nghiệm thu UC36 tại [hồ sơ UC36](UC36_TV2_HANDOVER.md).

## 1. Phạm vi refactor đã nghiệm thu

| Module | Baseline được bàn giao |
| --- | --- |
| Invoice | Năm API đọc/tạo, raw validation/typed DTO, Repository, transaction/snapshot, mapper header/items và React API/service/mapper |
| Review | Bốn API đọc/tạo, audit Backend, Frontend mapper/service, trang đọc và modal tạo |
| Auth/Profile | Login/register, user projection, hồ sơ/mật khẩu/avatar, audit/fix và CredentialRules trong Validators/Auth |
| Public Catalog | UC04–UC05 Property list/detail |
| Host Catalog | UC18 Property/ảnh cơ sở, UC19 RoomType, năm luồng UC20 Room list/detail/create/update/status và audit/fix |

Mười UC TV3 có luồng chính và refactor ACCEPTED: UC15/16/34/35/37/38/56/57/58/60. Không tự nghiệm thu toàn chức năng/end-to-end từ kết quả refactor. UC36 được triển khai và nghiệm thu sau đợt baseline, theo hồ sơ riêng.

## 2. Owner và dependency bàn giao

| Owner | Phạm vi nhận tiếp / giới hạn |
| --- | --- |
| TV1 | Duy trì Auth/Profile/Public Catalog/UC18/19 và năm luồng UC20; phần Catalog còn lại, UC21/22/23, Public Room/filter và Admin theo phân công |
| TV1 | DELETE Room và DB unique(property_id,room_number) chưa triển khai trong đợt này; uniqueness Room hiện dựa workflow lock |
| TV2 | Search/Availability, Booking lifecycle, check-in/check-out và dịch vụ/pricing; chuẩn hóa module về kiến trúc chung |
| TV1+TV2 | Booking/Maintenance writers phải phối hợp lock/recheck với Room status; race harness không chứng minh workflow production |
| TV2+TV3 | Booking thật cung cấp snapshot/COMPLETED và điểm vào Invoice/Review; TV3 giữ trách nhiệm QA/chức năng của mình |

UC21 chưa triển khai; CAT-ROOM-IMG-00 chỉ từng có kế hoạch, chưa thực hiện kiểm kê/runtime. Booking production chưa được đăng ký trong API root của baseline TV3; code ở nhánh TV2 không chứng minh đã tích hợp. Hiện owner hoãn lấy/merge nhánh TV2 vì còn phát triển và chưa phù hợp kiến trúc mới.
Hồ sơ SETUP-01/VAL-STRUCT-01 chưa ghi nghiệm thu riêng; MAP-01 giữ PARTIAL về hồ sơ bằng chứng. Không dùng các hồ sơ này để mở lại phạm vi refactor đã nghiệm thu.

## 3. Quy chuẩn và bảo toàn baseline

Đọc [ARCHITECTURE.md](../../ARCHITECTURE.md), [phân công](../stayhub-team-work-assignment.md) và [skills dự án](../../.agents/skills) trước thay đổi.

- Backend: Route/Middleware → Controller → Validator/typed Request DTO → Service/QueryService → Repository/Model → Mapper/Response DTO.
- Frontend: Page/Component/Hook → Domain Service → Domain API/shared Axios; mapper khi cần projection/normalization dùng chung.
- Dùng Symfony Validator/ObjectMapper và adapter hiện hữu; không khôi phục lớp cũ hoặc sinh lớp không có trách nhiệm thực tế.
- Giữ error order/status/messages, decimal strings, null/omission, pagination, session/CSRF/role/ownership, transaction/file lifecycle và cancellation/recovery; không tự replay mutation chưa rõ kết quả.
- Code hiện hữu gồm tracked/untracked đã nghiệm thu; không lấy HEAD-only archive thay baseline, không khôi phục các file đã xoá.
- Chỉ sửa exact scope được duyệt; không tự commit/push/merge, không sửa env hoặc seed/reset dữ liệu development.

## 4. Checkpoint đã ghi nhận

### Checkpoint Invoice/nền tảng

| Đóng | Checkpoint | Phạm vi | Kiểm chứng và nghiệm thu |
| --- | --- | --- | --- |
| [ ] | SETUP-01 | Checklist, local rules và 5 skill | PASS thiết lập. Acceptance riêng chưa ghi nhận. |
| [x] | CLEANUP-ARCH-01 | Dọn thư mục rỗng và chuyển CredentialRules về Validators/Auth | TECHNICAL PASS E18 / ACCEPTED theo chat ngày2026-11-16.12app dirs/datadir test đã dọn; source/hash/route/autoload đạt. SQL dev chưa xác nhận;55offline fingerprints giữ baseline. UC21 chưa triển khai. |
| [x] | HANDOVER-BASELINE-01 | Đóng gói baseline và kiểm tra trước PR vào dev | TECHNICAL PASS / PACKAGE VERIFIED E19; ACCEPTED theo owner trên chat. Owner đã merge PR #5 vào dev, đối chiếu local8913c5f; không tự xác nhận CI/remote checks. E19 giữ trạng thái lịch sử ở lượt đóng gói. UC21 chưa triển khai. |
| [x] | ARCH-01 | Quy chuẩn Repository/DTO/Mapper và ranh giới nhóm | PASS kiểm tra tài liệu/skill, ACCEPTED. |
| [x] | API-01 | Common response và exception rendering cho Invoice/Review | PASS E1, ACCEPTED. |
| [x] | FE-01 | Shared ID/error và Invoice/Review API modules | PASS E1, ACCEPTED. |
| [x] | INV-ARCH-01A | Nền Repository, DTO và mapper cho Invoice read | PASS E1, ACCEPTED. |
| [x] | INV-ARCH-01B | Nối Controller/QueryService của Invoice read | PASS E1, ACCEPTED. |
| [x] | INV-ARCH-01C | Invoice Frontend mapper và consumer đọc | PASS E1, ACCEPTED. |
| [x] | INV-ARCH-01 | Tổng hợp refactor read | CLOSED / ACCEPTED trong phạm vi read. |
| [x] | STRUCT-01A | Gom DTO, mapper, repository theo domain và wiring lúc đổi tên | PASS E1, ACCEPTED. |
| [x] | STRUCT-01B | Common/exception naming và tương thích Resource | PASS E1, ACCEPTED. |
| [x] | STRUCT-01C | Đồng bộ quy ước tên, tài liệu và skill | PASS E1, ACCEPTED. |
| [x] | SIMPLIFY-01A | Concrete InvoiceRepository và constructor DI | PASS E1, ACCEPTED. |
| [x] | SIMPLIFY-01B | Invoice read Validator, Response DTO và Common | PASS E1, ACCEPTED. |
| [x] | SIMPLIFY-01C | Đồng bộ quy chuẩn đơn giản hóa | PASS E1, ACCEPTED. |
| [x] | INV-ARCH-02A | Repository cho Invoice create và phân loại xung đột | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02B.1 | Symfony Validator/ObjectMapper và adapter ban đầu | PASS chức năng E2, ACCEPTED. Composer audit lịch sử có finding. |
| [x] | INV-ARCH-02B.2A | Raw DTO, canonical ID, gom Validator/Mapper | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02B.2B | Validator thuần, typed input, required DI và mapping ghi | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02B.3 | Explicit validation groups cho Invoice/ROOM trước khi lưu | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02C.1 | Nối HTTP create DTO/Common, bỏ Invoice Resources đã chuyển | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02C.2 | Gom create/read vào một Invoice service | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02C.3 | Đồng bộ kiến trúc, local rules và skill | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02-AUDIT | Audit read/create, quyền, snapshot, rollback, outer commit | PASS E2, ACCEPTED. |
| [x] | INV-ARCH-02 | Tổng hợp create, gồm parent 02B và 02C | CLOSED / ACCEPTED trong phạm vi read/create. |
| [ ] | VAL-STRUCT-01 | Thống nhất root Validators và Symfony constraints | PASS E2. Acceptance riêng PENDING theo hồ sơ nguồn. |
| [ ] | MAP-01 | Chuẩn Symfony và gate resolve InvoiceMapper qua Laravel container | Source có test resolve và có hồi quy ở lượt sau. Hồ sơ riêng giữ PARTIAL đến khi đối chiếu đủ bằng chứng lượt test quyền/full suite được yêu cầu. |
| [x] | MAP-02 | Symfony ObjectMapper cho Invoice header | ACCEPTED trên chat. Mapper và regression header có trong source. |

### Review

| Đóng | Checkpoint | Phạm vi | Kiểm chứng và nghiệm thu |
| --- | --- | --- | --- |
| [x] | REV-ARCH-01A | Nền tảng refactor Review read | PASS / ACCEPTED trên chat. |
| [x] | REV-ARCH-01B | Bước tách lớp luồng đọc | PASS / ACCEPTED trên chat. |
| [x] | REV-ARCH-01C.1 | Chuyển luồng đọc theo mốc C.1 | PASS / ACCEPTED trên chat. |
| [x] | REV-ARCH-01C.2 | Chuyển luồng đọc theo mốc C.2 | PASS / ACCEPTED trên chat. |
| [x] | REV-ARCH-02A | Tách lớp của luồng tạo Review | PASS / ACCEPTED trên chat. |
| [x] | MAP-03 | Symfony ObjectMapper cho Review Response DTOs | PASS / ACCEPTED trên chat. |
| [x] | REV-ARCH-02B | Typed Request cho POST và Customer Response DTO/Common | PASS / ACCEPTED trên chat. |
| [x] | REV-ARCH-AUDIT | Rà 4 API, quyền, mapping, transaction và parity | PASS E3, ACCEPTED trên chat. |
| [x] | REV-ARCH-01 và REV-ARCH-02 | Phạm vi refactor Backend đã audit | ACCEPTED theo nghiệm thu audit. |
| [x] | REV-FE-01 | Mapper/service, ba màn hình đọc và modal tạo | PASS E4, ACCEPTED trên chat trước lượt cập nhật tài liệu. |

### Auth và Profile liên module

| Đóng | Checkpoint | Phạm vi | Kiểm chứng và nghiệm thu |
| --- | --- | --- | --- |
| [x] | AUTH-01 / AUTH-ARCH-01A–01D | Response user chung, validation/DTO đăng nhập và đăng ký, persistence đăng ký qua Repository | ACCEPTED theo chuỗi nghiệm thu trên chat. Các số test lịch sử riêng chưa đối chiếu lại trong lượt cập nhật checklist này. |
| [x] | AUTH-ARCH-AUDIT | Rà Auth sau 01A–01D | ACCEPTED trên chat. Profile mutations được tách thành các checkpoint dưới đây. |
| [x] | PROFILE-ARCH-01A | PATCH /api/profile qua Validator, typed Request DTO và Repository | PASS E5, ACCEPTED trên chat. Giữ PATCH omission/null, whitelist, quyền và projection 13 trường. |
| [x] | PROFILE-ARCH-01B | PATCH /api/profile/password qua Validator, DTO và Repository | PASS E5, ACCEPTED trên chat. Giữ phiên thao tác và từ chối phiên có fingerprint cũ. |
| [x] | PROFILE-ARCH-01C | POST /api/profile/avatar qua Validator, DTO và Repository | PASS E5, ACCEPTED trên chat. Có kiểm chứng stream riêng tư, ownership, rollback và cleanup. |
| [x] | PROFILE-ARCH-FIX-01 | F01 vòng đời avatar theo outer commit/rollback và F02 hai bộ đếm throttle riêng | PASS E5, ACCEPTED trên chat. Phạm vi bản sửa 4 file. |
| [x] | PROFILE-ARCH-AUDIT | Rà đủ 5 endpoint UC03 và tái kiểm sau FIX-01 | TECHNICAL PASS E5, ACCEPTED trên chat sau lượt chốt audit. F01/F02 CLOSED. |

### Catalog liên module

Các dòng sau ghi nhận checkpoint người dùng giao và đã nghiệm thu. Chúng thể hiện phần refactor/triển khai được giao trên module TV1, cùng các phụ thuộc dữ liệu cần bảo toàn.

| Đóng | Checkpoint | Phạm vi | Kiểm chứng và việc còn lại |
| --- | --- | --- | --- |
| [x] | CAT-ARCH-00 | Kiểm kê Public Property Catalog | ACCEPTED trên chat, làm baseline cho các bước chuẩn hóa Public. |
| [x] | CAT-ARCH-01, CAT-ARCH-02A, CAT-ARCH-02B | Chuẩn hóa Public Property list/detail UC04–UC05 | ACCEPTED trên chat. Source có Validator, DTO, Repository và Symfony ObjectMapper. |
| [x] | CAT-ARCH-AUDIT | Audit Public Catalog sau 02A–02B | ACCEPTED trên chat. Số test lịch sử riêng chưa đối chiếu lại trong lượt cập nhật này. |
| [x] | CAT-MGMT-00 | Kiểm kê API Catalog quản lý TV1 | ACCEPTED trên chat, làm baseline UC18. |
| [x] | CAT-MGMT-01–05 | Host list/detail/create/update/status cơ sở thuộc mình | ACCEPTED trên chat. Giữ trạng thái tạo ACTIVE và ma trận trạng thái đã duyệt. |
| [x] | CAT-MGMT-06A | Upload ảnh cơ sở, giới hạn file và storage link | ACCEPTED trên chat sau khi kiểm tra cleanup và đóng MGMT06A-AC5. |
| [x] | CAT-MGMT-06B | Chọn ảnh đại diện và xóa ảnh cơ sở | ACCEPTED trên chat. Typed input dùng hậu tố Request theo quyết định đã duyệt. |
| [x] | CAT-MGMT-AUDIT | Audit UC18 sau 01–06B | ACCEPTED trên chat. E6 ghi nguồn kiểm chứng lịch sử. |
| [x] | CAT-RT-00 | Kiểm kê Host quản lý loại phòng UC19 | ACCEPTED trên chat, làm baseline các slice loại phòng. |
| [x] | CAT-RT-01–03 | Host list/create/update loại phòng của cơ sở thuộc mình | ACCEPTED theo các mốc chốt trên chat. Đã có API/UI thật. |
| [x] | CAT-RT-04A, CAT-RT-04B | Chốt quy tắc và triển khai xóa loại phòng chưa có phòng tham chiếu | ACCEPTED trên chat. Có dependency Room và kiểm tra FK/concurrency trong audit. |
| [x] | CAT-RT-AUDIT | Audit UC19 sau 01–04B | PASS E6, ACCEPTED trên chat. |
| [x] | CAT-ROOM-00 | Kiểm kê Host quản lý từng phòng UC20 | TECHNICAL PASS E7 / ACCEPTED trên chat. Đây là mốc kiểm kê trước triển khai; năm luồng Host Room đã được bổ sung ở các checkpoint sau. |
| [x] | CAT-ROOM-01 | Host xem danh sách phòng của cơ sở thuộc mình | IMPLEMENTED / TECHNICAL PASS E8 / ACCEPTED trên chat. Có API và UI thật. Phạm vi 16 file cùng bổ sung 1 dòng trong AuthenticationTest đã được duyệt. |
| [x] | CAT-ROOM-02 | Host đọc chi tiết phòng thuộc mình | IMPLEMENTED / TECHNICAL PASS E9 / ACCEPTED trên chat. Có API, trang React và liên kết từ list. Đúng phạm vi 18 file đã duyệt. |
| [x] | CAT-ROOM-03 | Host tạo phòng thuộc cơ sở mình | IMPLEMENTED / TECHNICAL PASS E10 / ACCEPTED trên chat. API/UI thật, trạng thái đầu ACTIVE, đúng phạm vi 15 file đã duyệt. |
| [x] | CAT-ROOM-04 | Host cập nhật phòng thuộc cơ sở mình | IMPLEMENTED / TECHNICAL PASS E11 / ACCEPTED trên chat. API/UI thật, giữ trạng thái và Booking snapshot, đúng phạm vi 15 file. |
| [x] | CAT-ROOM-05A | Chốt ma trận trạng thái Room và ràng buộc Booking/Maintenance | SPECIFIED / TECHNICAL PASS E12 / ACCEPTED trên chat. Đúng 2 file tài liệu; API/UI status được triển khai sau ở05B, race workflow Booking/Maintenance thật vẫn chưa kiểm chứng. |
| [x] | CAT-ROOM-05B | Host đổi trạng thái phòng theo đặc tả 05A | IMPLEMENTED / TECHNICAL PASS E13 / ACCEPTED trên chat. API/UI thật, đúng16file, giữ matrix05A và baseline. |
| [x] | CAT-ROOM-AUDIT | Rà UC20 sau01–05B | TECHNICAL PASS E16, ACCEPTED theo chat ngày2026-11-16; F01 CLOSED. E14 giữ lịch sử PARTIAL; không đồng nghĩa đóng toàn UC20. |
| [x] | CAT-ROOM-FIX-01 | Sửa F01 file multipart trùng key khi tạo phòng | TECHNICAL PASS E15, ACCEPTED theo chat ngày2026-11-16. F01 CLOSED sau tái kiểm E16; đúng3file đã duyệt, contract giữ nguyên. |

## 5. Bằng chứng refactor lịch sử

Các số dưới thuộc lượt chạy gốc, không phải kết quả vừa chạy lại. PHP ghi tests/assertions; full Backend lịch sử dùng SQLite, targeted DB/runtime ghi riêng engine. Ngày E3: 2026-11-14; E4 và Room triển khai bắt đầu 2026-11-15; các lượt chốt FIX/audit/cleanup/handover ngày 2026-11-16. Không suy ngày chi tiết nếu hồ sơ nguồn không ghi.

### E1 · Nền tảng và Invoice read

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

### E2 · Invoice create và namespace validation

| Lượt | Kiểm chứng đã ghi |
| --- | --- |
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
Security audit ghi nhận kết quả theo dependency lúc chạy. Chưa có bằng chứng xử lý các finding trong phạm vi này.

### E3–E19 · Tóm tắt nghiệm thu và giới hạn

| Evidence | Kết quả lịch sử cần giữ | Nguồn / giới hạn |
| --- | --- | --- |
| E3 REV-ARCH-AUDIT | SQLite focused 201/1285, full 606/3806; Node75, lint/build PASS; role/ownership/parity, rollback/outer commit, mapper 0query | Task Result đã nghiệm thu; không phải MySQL concurrency |
| E4 REV-FE-01 | SQLite full828/5311; Node133; lint/build PASS; Edge/XAMPP thật ở390/768/1440px; duplicate submit một POST201, gửi lại409/một Review | TEMP stayhub-rev-fe-*; fixture COMPLETED/session thật, chưa Booking production; Review race còn mở |
| E5 PROFILE-ARCH | 01A full830/5321;01B831/5326;01C832/5341; FIX full844/5455; Node133/lint/build PASS; audit chốt12/114 mới | TEMP stayhub-profile*; PHPUnit SQLite; MariaDB/disk/Edge thật tách riêng; F01 avatar outer transaction/F02 throttle đã đóng; full suite ở audit chốt là kế thừa FIX |
| E6 CAT-MGMT/RT-AUDIT | MGMT fullSQLite787/4958; RT828/5311,Node125; UC19 Edge CRUD/quyền/409/419/late response | TEMP stayhub-mgmt-audit-* và stayhub-rt-audit-*; counts là các lượt khác |
| E7 CAT-ROOM-00 | CatalogSQLite213/1399,full844/5455,Node133; XAMPP/Edge Catalog; kiểm kê Room chỉ đọc | TEMP stayhub-cat-room00-*; trước năm API/UI Host Room |
| E8 CAT-ROOM-01 | Room list14/70 trên SQLite và MariaDB; fullSQLite858/5527,Node138; browser/API thật | TEMP stayhub-cat-room01-*; lượt đầu route count27/28 FAIL, thêm đúng một dòng test đã duyệt, rerun PASS |
| E9 CAT-ROOM-02 | Room detail15/69 MariaDB, list/detailSQLite29/139; fullSQLite873/5598,Node142 | TEMP stayhub-cat-room02-*; session thật/decimal/null/ownership; lint/build PASS |
| E10 CAT-ROOM-03 | Create51/220 cả SQLite/MariaDB; fullSQLite924/5820,Node146; ba race InnoDB và Edge | TEMP stayhub-cat-room03-*; uniqueness theo workflow, không DB unique mới; lint/build PASS |
| E11 CAT-ROOM-04 | Update55/288 SQLite/MariaDB; fullSQLite979/6110,Node150; năm race InnoDB | TEMP stayhub-cat-room04-*; giữ status/snapshot/no-op/rollback; lint/build PASS |
| E12 CAT-ROOM-05A | Chốt ma trận trạng thái; CatalogSQLite348/2046,full979/6110,Node150/lint/build PASS | TEMP stayhub-cat-room05a-*; spec trước API/UI05B; lỗi IPC/metadata harness được sửa ngoài source rồi chạy lại |
| E13 CAT-ROOM-05B | Status177/505 SQLite/MariaDB; Catalog525/2551,full1156/6617,Node154/lint/build PASS; sáu race | TEMP stayhub-cat-room05b-*; Service status thật, writer Booking/Maintenance là harness |
| E14 CAT-ROOM-AUDIT | MariaDB312/1152; SQLite full1156/6617,Node154/lint/build PASS;14race InnoDB | TEMP stayhub-cat-room-audit-*; kết luận ban đầu PARTIAL do F01 multipart bỏ qua file trùng key |
| E15 CAT-ROOM-FIX-01 | Create60/271 SQLite/MariaDB; Catalog534/2602,full1165/6668,Node154/lint/build PASS; HTTP multipart thật | TEMP stayhub-room-fix01-*; RED trước fix/GREEN sau fix; F01 đóng qua E16 |
| E16 CAT-ROOM-AUDIT chốt | Năm focusedMariaDB321/1203, CatalogSQLite534/2602,full1165/6668; create outer commit/rollback mới | TEMP stayhub-room-audit-recheck-*; Node/lint/build kế thừa E15,14race kế thừa E14; ACCEPTED năm luồng, không toàn UC20 |
| E17 CLEANUP-ARCH-01 đầu | RegistrationMariaDB90/501; Auth/ProfileSQLite265/1662,full1165/6668;33HTTP thật | TEMP stayhub-cleanup01-*; lúc đó PARTIAL vì app dirs/datadir chưa dọn; XAMPP dev lỗi proxies_priv, không repair |
| E18 CLEANUP-ARCH-01 chốt | Helper/routes/autoload/hash đối chiếu;12app dirs và datadir test đã dọn;55offline dev hashes giữ nguyên | TEMP stayhub-cleanup01-close-*; test/runtime kế thừa E17,SQL dev refused/NOT VERIFIED; TECHNICAL PASS/ACCEPTED |
| E19 HANDOVER-BASELINE-01 | SQLite1165/6668; sáu targetedMariaDB411/1704;Node154,lint/build exit0;ZIP643file/manifest/extract PASS | Artifact bàn giao dưới đây; browser/HTTP/race kế thừa, không CI/mergeability proof |

Lint lịch sử còn ba Fast Refresh warnings; build có chunk>500kB và timing diagnostic ở lượt ghi nhận. Composer audit INV-ARCH-02B.1: exit1, chín advisories/sáu package; chưa có bằng chứng xử lý trong phạm vi refactor. Không suy là audit dependency hiện tại đã chạy.
Các đường dẫn TEMP là nguồn lịch sử, chưa xác nhận còn tồn tại ở lượt dọn tài liệu. Không giả định log local được lưu cùng Git.

## 6. Gói HANDOVER-BASELINE-01 và kiểm chứng trước PR

Snapshot lịch sử ngày2026-11-16: refactor/stayhub-layered-architecture tại HEAD7dac999b2808ca6fd583985e19d31248cae1b4cc cộng working tree; fetched dev/merge-base951f62efc70127a3719c840ae529f8d311842a49. Lúc đóng gói chưa commit/PR; owner đã merge sau đó. Gói cũ không đại diện source UC36 hiện hành.
Artifact: `.agent-workspace/handover-baseline-3dbd49fe4a3d4410882f7c7efad786ed/` (local, ignored).

| Thành phần | Nội dung đã kiểm |
| --- | --- |
| source.zip / manifest.json |643file; tracked/untracked/action/domain/size/SHA256, ZIP hash; extract kiểm không thừa/thiếu |
| deleted-paths.txt |14file đã xoá, không đưa lại vào checkout |
| git-state.json / checks | HEAD/target/merge-base/index, static/routes/test/package/data fingerprints |
| Exclusions |.git, env thật/secrets/private keys, vendor/node_modules, build/dist, uploads/datadir/local artifacts; scan pattern không bảo đảm phát hiện mọi secret |

frontend/.env đã track sẵn trên baseline và không đổi; snapshot loại env thật, giữ .env.example. Không tự untrack hoặc sửa ignore. Nhận gói phải extract vào thư mục mới, kiểm manifest/hash/deletions, tạo env riêng và cài từ lockfile; không ghi đè checkout/data cần giữ.

| Sáu suite targeted MariaDB10.4.32 | Tests/assertions | Guard giữ nguyên |
| --- | --- | --- |
| RegistrationTest |90/501 | STAYHUB_REGISTRATION_TEST_DATABASE |
| HostRoomListTest |14/70 | STAYHUB_ROOM_TEST_DATABASE |
| HostRoomDetailTest |15/69 | STAYHUB_ROOM_DETAIL_TEST_DATABASE |
| HostRoomCreateTest |60/271 | STAYHUB_ROOM_CREATE_TEST_DATABASE |
| HostRoomUpdateTest |55/288 | STAYHUB_ROOM_UPDATE_TEST_DATABASE |
| HostRoomStatusTest |177/505 | STAYHUB_ROOM_STATUS_TEST_DATABASE |

E19 full Backend SQLite1165/6668; targetedMariaDB411/1704; Node154 với `node --experimental-test-module-mocks --test tests/*.test.js`. Lần Node thiếu cờ:76pass/4fail, giữ như failed invocation; rerun đúng cờ đạt, không sửa source/test. Lint/build exit0 với warnings trên. Static215PHPfiles/43route entries/autoload/namespace/skills/links đạt; diff whitespace đạt sau sửa một dòng được duyệt.
MariaDB targeted dùng binary XAMPP10.4.32 với datadir server riêng/127.0.0.1:3306, sáu schema owned có migration/SELECT DATABASE proof, đã dọn server/schema/datadir. Không dùng instance dev lỗi mysql.proxies_priv; SQL dev vẫn NOT VERIFIED, bảo toàn bằng55offline fingerprints. Không gọi targeted là full MySQL.

Các kiểm tra local/package đã PASS và gói đã được nghiệm thu; CI/review/GitHub mergeability trên commit cuối chưa có bằng chứng mới ở đây. Với lần tích hợp sau phải kiểm lại target/diff/source cuối, quyền sửa shared files, regression engine/guard, session/ownership/transaction/file cleanup và workflow production. Nếu gói/source đổi phải regenerate/reverify manifest; không tái dùng số test cũ như kết quả mới.

Sau đợt baseline, DEV-DB-01 đã đưa DB development sang MySQL Docker8.4.13; PHP vẫn dùng XAMPP. Evidence DEV-DB-01 kế thừa: bảy migrations,27tables/count/hash giữ sau restart,21FK không orphan,Invoice read Host/Customer và Public Property/Review HTTP200. Đây là kết quả lúc thiết lập DB, không chứng minh payment hoặc Booking production. Giữ .env root/backend/.env theo cấu hình đã nghiệm thu,datadir XAMPP và backup nguyên vẹn.
