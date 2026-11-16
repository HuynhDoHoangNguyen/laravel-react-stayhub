# StayHub TV3 — checklist triển khai và nghiệm thu

Phạm vi gồm 18 use case của TV3, FAQ Management, Chat History và các checkpoint refactor được giao, gồm công việc liên module Auth/Profile và Catalog.
Đối chiếu ngày 2026-11-16 (Asia/Bangkok) trên nhánh refactor/stayhub-layered-architecture, HEAD 7dac999 và working tree sau PROFILE-ARCH-FIX-01, chốt PROFILE-ARCH-AUDIT, nghiệm thu CAT-ROOM-05B và nghiệm thu CAT-ROOM-FIX-01 và tái kiểm CAT-ROOM-AUDIT đã nghiệm thu; CAT-ROOM-IMG-00 mới lập Mini Plan, chờ duyệt.
Nghiệm thu checkpoint được ghi theo xác nhận trên chat. Các UC thuộc TV1/TV2 trong phần refactor liên module không cộng vào số đếm 18 UC chức năng của TV3.
Nguồn phạm vi là [phân công TV3](../stayhub-team-work-assignment.md) và [đặc tả use case](../stayhub-usecase-system.md).
Quy chuẩn kỹ thuật nằm ở [ARCHITECTURE.md](../../ARCHITECTURE.md).

## Chốt đợt refactor và bàn giao — 2026-11-16

**Đã hoàn thành và nghiệm thu phạm vi refactor các luồng đã triển khai được giao trong đợt này.** Xác nhận theo chuỗi nghiệm thu trên chat và yêu cầu chốt/bàn giao hiện tại. Phạm vi gồm Invoice read/create, Review Backend/Frontend, Auth/Profile, Public Catalog UC04–UC05, Host UC18/UC19 và năm luồng UC20. Các checkpoint/bằng chứng E1–E16 bên dưới được giữ nguyên.

Kết luận này áp dụng cho chuẩn hóa cấu trúc của code hiện hữu trong phạm vi được giao. Không tính UC chưa triển khai hoặc phần mở rộng của TV1/TV2 là công việc refactor còn nợ của đợt này. Không đồng nghĩa toàn dự án đã chuẩn hóa hoặc từng UC chức năng đã qua đầy đủ nghiệm thu end-to-end.

### 10 UC TV3 đã có luồng chính và đã tích hợp cấu trúc mới

| UC | Luồng đã triển khai | Tích hợp/refactor | Việc chức năng/QA còn mở |
| --- | --- | --- | --- |
| UC15 | Customer xem danh sách/chi tiết hóa đơn | ACCEPTED — Invoice read Backend và React API/service/mapper | QA Invoice trên XAMPP/trình duyệt và nghiệm thu chức năng |
| UC16 | Customer tạo Review qua API/modal | ACCEPTED — Review Validator/DTO/Repository/Mapper và Frontend | Booking detail thật của TV2 và QA tích hợp |
| UC34 | Host tạo Invoice từ Booking | ACCEPTED — Invoice create, transaction/snapshot và luồng UI gọi service | QA từ Booking production, không chỉ fixture |
| UC35 | Host xem chi tiết Invoice | ACCEPTED — read projection header/items và Frontend | QA trình duyệt và nghiệm thu chức năng |
| UC37 | Host danh sách/lọc/tìm kiếm/phân trang Invoice | ACCEPTED — read query và Frontend | QA chức năng; payment UC36 là luồng riêng chưa triển khai |
| UC38 | Host xem danh sách/tổng hợp Review | ACCEPTED — Review query/DTO/mapper và React | Nghiệm thu chức năng theo REV-03, giữ bằng chứng E4 |
| UC56 | Tạo InvoiceItem ROOM/SERVICE cùng Invoice | ACCEPTED — nằm trong create transaction và mapping items | QA cùng luồng UC34, không yêu cầu UI riêng cho kỹ thuật nội bộ |
| UC57 | Tính subtotal | ACCEPTED — calculator/validation tích hợp create | Đối chiếu AC chức năng với snapshot từ Booking thật |
| UC58 | Tính total_amount | ACCEPTED — calculator/validation tích hợp create | QA discount/extra_fee theo contract đã chốt; workflow hiện dùng0 |
| UC60 | Kiểm tra điều kiện tạo Review | ACCEPTED — điều kiện COMPLETED/ownership/one-review trong create workflow | Gate concurrency XAMPP và workflow Booking thật |

Tổng: **7 UC Invoice (15,34,35,37,56,57,58) + 3 UC Review (16,38,60) = 10 UC có luồng chính. Cả10 đã tích hợp cấu trúc mới trong phạm vi code đã triển khai.** UC kỹ thuật56/57/58/60 nằm trong workflow cha, không cần tạo API/UI riêng chỉ để đủ số lớp. Bằng chứng refactor E1–E4 và các checkpoint đã nghiệm thu; không gọi đây là10UC đã nghiệm thu chức năng end-to-end. Ma trận18UC bên dưới tiếp tục giữ PARTIAL/PENDING cho các gate chức năng còn mở.

### Phần bàn giao TV1/TV2 và việc phối hợp

| Owner | Phạm vi nhận tiếp | Trạng thái bàn giao |
| --- | --- | --- |
| TV1 | Duy trì Auth/Profile, Public UC04–UC05, UC18/UC19 và5luồng UC20 đã chuẩn hóa | Nhận baseline đã nghiệm thu, giữ contract/quyền/transaction và regression khi tích hợp |
| TV1 | UC21 ảnh phòng, UC22 tiện nghi, UC23 bảo trì; luồng Public Room/filter và Admin thuộc phân công TV1 chưa được chốt trong đợt này | TV1 tự kiểm kê/triển khai/chuẩn hóa theo ARCHITECTURE; không tính là refactor còn nợ của đợt đã hoàn thành |
| TV1 | DELETE Room ngoài5luồng đã giao; uniqueness(property_id,room_number) ở DB | Chưa triển khai/chưa đổi schema trong đợt này; owner chốt task và quy tắc riêng |
| TV2 | Availability/Search, Booking lifecycle, check-in/check-out, service/pricing và các UC theo phân công TV2 | TV2 chuẩn hóa code module của mình; nhánh hiện tại chưa đăng ký API Booking trong api.php, không kết luận code nhánh khác chưa có |
| TV1 + TV2 | Booking/Maintenance writers với Room status | Phối hợp lock/recheck;14race harness không thay thế kiểm chứng workflow production |
| TV2 + TV3 | Điểm vào Invoice/Review từ Booking thật, snapshot và trạng thái COMPLETED | TV2 bàn giao contract/workflow; TV3 phối hợp QA Invoice/Review. Không chuyển trách nhiệm QA/chức năng TV3 hoàn toàn sang TV2 |

**UC21 chưa triển khai.** CAT-ROOM-IMG-00 chỉ có Mini Plan chờ duyệt; không tự thực hiện kiểm kê/runtime, tạo inventory hoặc API/UI ảnh phòng. Kế hoạch P1 giữ làm tài liệu tham khảo khi owner nhận tiếp.

### UC TV3 chưa triển khai và công việc tiếp theo của TV3

- 8 UC TODO: UC17 Chatbot; UC36 cập nhật thanh toán; UC39 Host Dashboard; UC44 Admin Booking; UC45 Admin Invoice; UC47 Admin Dashboard; UC48 Statistics; UC61 context nội bộ cho chatbot.
- Ngoài mã UC: FAQ Management và Chat History còn TODO.
- QA/nghiệm thu chức năng10UC hiện hữu và tích hợp TV2 là backlog riêng; không mở lại các checkpoint refactor đã được nghiệm thu chỉ vì các gate này chưa đóng.
- Audit toàn project sau khi team tích hợp là task riêng, chưa được thực hiện trong đợt refactor này.
- Hồ sơ lịch sử SETUP-01/VAL-STRUCT-01/MAP-01 còn mục xác nhận/bằng chứng riêng cần đối chiếu. Giữ trạng thái gốc, không tự báo gate MAP-01 PASS hoặc xóa lịch sử; tách việc hoàn thiện hồ sơ khỏi kết luận nghiệm thu các luồng đã chốt.
- Code bàn giao hiện ở working tree nhánh refactor/stayhub-layered-architecture, HEAD7dac999; thay đổi chưa commit. Cần thống nhất cách đóng gói/chuyển code để team nhận đủ; không tự commit/push/merge trong task tài liệu này.

Lượt chốt checklist chỉ cập nhật tài liệu, đối chiếu source wiring và bằng chứng lịch sử; không chạy lại suite runtime, không sửa source hoặc thay đổi quyết định nghiệp vụ.

## Tiến độ hiện tại

| Phạm vi | Đã làm và nghiệm thu | Việc còn lại |
| --- | --- | --- |
| Invoice | Refactor 5 API đọc/tạo, header/items, validation, transaction và React API/service/mapper | Nghiệm thu chức năng, QA theo luồng, quyết định và triển khai UC36 |
| Review | Refactor 4 API đã qua REV-ARCH-AUDIT. REV-FE-01 đã nghiệm thu mapper/service và UI | Điểm vào từ Booking thật của TV2 và gate Review concurrency trên XAMPP |
| Auth/Profile liên module | Auth 01A–01D, audit và Profile 01A–01C, FIX-01, audit đã nghiệm thu | F01/F02 đã đóng qua lượt tái kiểm. Tiến độ chức năng TV1 được quản lý theo phạm vi UC03 riêng |
| Catalog liên module | Public UC04–UC05, Host quản lý cơ sở UC18, loại phòng UC19, kiểm kê CAT-ROOM-00 và Room list/detail/create/update CAT-ROOM-01–04 đã nghiệm thu | CAT-ROOM-05A đặc tả/kiểm chứng đã nghiệm thu E12. CAT-ROOM-05B đã nghiệm thu E13; CAT-ROOM-FIX-01 đã nghiệm thu E15; F01 CLOSED qua tái kiểm E16. CAT-ROOM-AUDIT đã nghiệm thu E16; dependencies Booking/Maintenance và DELETE Room giữ mở. CAT-ROOM-IMG-00 kiểm kê UC21 mới PLANNED, chưa thực hiện |
| Reporting | Các trang Host/Admin có khung UI | Chốt KPI, thời gian, refund và triển khai API/UI |
| FAQ và Chat | Migrations và model nền | Chốt contract, làm API/UI và kiểm chứng provider/context |

**Số đếm chức năng:** 10 UC đã có luồng chính, đã refactor/tích hợp cấu trúc mới trong phạm vi hiện hữu và còn gate QA/nghiệm thu chức năng; 8 UC TODO. Chốt refactor không tự chuyển nghiệm thu từng UC chức năng sang CLOSED.
IMPLEMENTED là có luồng chính. PARTIAL là đã có bằng chứng cho một phần tiêu chí. NOT VERIFIED là chưa có bằng chứng cho cổng đang nói tới. ACCEPTED là có xác nhận nghiệm thu cho phạm vi cụ thể. Dùng [x] khi các tiêu chí bắt buộc đạt và đã được nghiệm thu.

## Ma trận 18 use case

| Đóng | UC | Triển khai | Refactor luồng hiện hữu | Kiểm chứng chức năng | Nghiệm thu UC | Đã có và bước còn lại |
| --- | --- | --- | --- | --- | --- | --- |
| [ ] | UC15 · Hóa đơn cá nhân | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | API/UI Customer list/detail. Còn browser/XAMPP và xác nhận theo INV-01, QA-02. |
| [ ] | UC16 · Tạo đánh giá | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | E3–E4 xác minh API và modal với fixture. Còn điểm vào Booking detail thật và QA-03. |
| [ ] | UC17 · Chat với chatbot | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Còn luồng gửi câu hỏi, provider, lưu message và UI theo CHAT-01..05. |
| [ ] | UC34 · Tạo Invoice từ Booking | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | Transaction, snapshot, uniqueness, rollback và UI Host. Còn QA-02 trên luồng Booking thật. |
| [ ] | UC35 · Chi tiết Invoice Host | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | API/UI, ownership và items. Còn QA-02 trình duyệt. |
| [ ] | UC36 · Cập nhật trạng thái thanh toán | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Chốt transition, role, paid_at, refund và retry trước INV-03. |
| [ ] | UC37 · Quản lý Invoice | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | Host list, lọc, tìm kiếm và phân trang. Còn QA-02 và UC36 riêng. |
| [ ] | UC38 · Host xem đánh giá | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | API list/summary và Edge/XAMPP theo E4. Còn nghiệm thu chức năng trong REV-03. |
| [ ] | UC39 · Host Dashboard | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Khung điều hướng. Cần KPI, query, API và UI theo RPT-01, HOST-01/02. |
| [ ] | UC44 · Admin xem toàn bộ booking | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Cần contract Booking TV2 và ADM-02. |
| [ ] | UC45 · Admin xem toàn bộ Invoice | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Cần API/UI, bộ lọc, phân trang và quyền theo ADM-01. |
| [ ] | UC47 · Admin Dashboard | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Có UI khung. Cần query và lát API/UI theo RPT-01, ADM-03/04. |
| [ ] | UC48 · Thống kê hệ thống | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Cần định nghĩa kỳ, chỉ tiêu và số liệu test độc lập theo ADM-05/06. |
| [ ] | UC56 · Tạo dòng Invoice | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | ROOM/SERVICE lưu nguyên tử cùng header. Còn nghiệm thu theo INV-01, QA-02. |
| [ ] | UC57 · Tính subtotal | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | Decimal chính xác từ snapshot, có unit/feature test. Còn xác nhận AC INV-01. |
| [ ] | UC58 · Tính tổng thanh toán | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | Calculator có discount/extra_fee, workflow hiện dùng 0. Còn đối chiếu AC INV-01. |
| [ ] | UC60 · Điều kiện Review | IMPLEMENTED | ACCEPTED | PARTIAL | PENDING | Booking COMPLETED, ownership và một Review mỗi Booking. Còn luồng TV2 và race trên XAMPP. |
| [ ] | UC61 · Ngữ cảnh nội bộ cho chatbot | TODO | Chưa có luồng hoàn chỉnh để chốt | NOT VERIFIED | PENDING | Cần allowlist, quyền dữ liệu và context service theo CHAT-01/02. |

### Công việc ngoài số đếm UC

| Đóng | Hạng mục | Trạng thái | Việc còn lại |
| --- | --- | --- | --- |
| [ ] | FAQ Management | TODO / NOT VERIFIED / PENDING | FAQ-01 list/create, FAQ-02 sửa/xóa, test quyền và UI. |
| [ ] | Chat History | TODO / NOT VERIFIED / PENDING | CHAT-03 list/detail, ownership và phân trang. Chốt retention ở CHAT-01. |

## Checkpoint refactor

### Invoice và nền tảng áp dụng cho TV3

| Đóng | Checkpoint | Phạm vi | Kiểm chứng và nghiệm thu |
| --- | --- | --- | --- |
| [ ] | SETUP-01 | Checklist, local rules và 5 skill | PASS thiết lập. Acceptance riêng chưa ghi nhận. |
| [x] | CLEANUP-ARCH-01 | Dọn thư mục rỗng và chuyển CredentialRules về Validators/Auth | TECHNICAL PASS E18 / ACCEPTED theo chat ngày2026-11-16.12app dirs/datadir test đã dọn; source/hash/route/autoload đạt. SQL dev chưa xác nhận;55offline fingerprints giữ baseline. UC21 chưa triển khai. |
| [ ] | HANDOVER-BASELINE-01 | Đóng gói baseline và kiểm tra trước PR vào dev | TECHNICAL PASS / PACKAGE VERIFIED E19; chờ nghiệm thu gói. Chưa stage/commit/push/merge, GitHub mergeability/CI/review/merge approval vẫn PENDING. UC21 chưa triển khai. |
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
| [x] | [REV-ARCH-AUDIT](#rev-arch-audit) | Rà 4 API, quyền, mapping, transaction và parity | PASS E3, ACCEPTED trên chat. |
| [x] | REV-ARCH-01 và REV-ARCH-02 | Phạm vi refactor Backend đã audit | ACCEPTED theo nghiệm thu audit. |
| [x] | [REV-FE-01](#rev-fe-01) | Mapper/service, ba màn hình đọc và modal tạo | PASS E4, ACCEPTED trên chat trước lượt cập nhật tài liệu. |

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
| [x] | CAT-ROOM-00 | Kiểm kê Host quản lý từng phòng UC20 | TECHNICAL PASS E7 / ACCEPTED trên chat. Có dữ liệu Room và projection Public, chưa có 5 route Host Room dự kiến hoặc UI quản lý từng phòng. |
| [x] | CAT-ROOM-01 | Host xem danh sách phòng của cơ sở thuộc mình | IMPLEMENTED / TECHNICAL PASS E8 / ACCEPTED trên chat. Có API và UI thật. Phạm vi 16 file cùng bổ sung 1 dòng trong AuthenticationTest đã được duyệt. |
| [x] | CAT-ROOM-02 | Host đọc chi tiết phòng thuộc mình | IMPLEMENTED / TECHNICAL PASS E9 / ACCEPTED trên chat. Có API, trang React và liên kết từ list. Đúng phạm vi 18 file đã duyệt. |
| [x] | CAT-ROOM-03 | Host tạo phòng thuộc cơ sở mình | IMPLEMENTED / TECHNICAL PASS E10 / ACCEPTED trên chat. API/UI thật, trạng thái đầu ACTIVE, đúng phạm vi 15 file đã duyệt. |
| [x] | CAT-ROOM-04 | Host cập nhật phòng thuộc cơ sở mình | IMPLEMENTED / TECHNICAL PASS E11 / ACCEPTED trên chat. API/UI thật, giữ trạng thái và Booking snapshot, đúng phạm vi 15 file. |
| [x] | CAT-ROOM-05A | Chốt ma trận trạng thái Room và ràng buộc Booking/Maintenance | SPECIFIED / TECHNICAL PASS E12 / ACCEPTED trên chat. Đúng 2 file tài liệu; API/UI status được triển khai sau ở05B, race workflow Booking/Maintenance thật vẫn chưa kiểm chứng. |
| [x] | CAT-ROOM-05B | Host đổi trạng thái phòng theo đặc tả 05A | IMPLEMENTED / TECHNICAL PASS E13 / ACCEPTED trên chat. API/UI thật, đúng16file, giữ matrix05A và baseline. |
| [x] | CAT-ROOM-AUDIT | Rà UC20 sau01–05B | TECHNICAL PASS E16, ACCEPTED theo chat ngày2026-11-16; F01 CLOSED. E14 giữ lịch sử PARTIAL; không đồng nghĩa đóng toàn UC20. |
| [x] | CAT-ROOM-FIX-01 | Sửa F01 file multipart trùng key khi tạo phòng | TECHNICAL PASS E15, ACCEPTED theo chat ngày2026-11-16. F01 CLOSED sau tái kiểm E16; đúng3file đã duyệt, contract giữ nguyên. |
| [ ] | CAT-ROOM-IMG-00 | Kiểm kê Host quản lý ảnh phòng UC21 | PLANNED / APPROVAL PENDING; bàn giao phần tiếp theo cho TV1. P1 chưa thực hiện; chưa kiểm kê runtime, chưa chạy test hoặc triển khai UC21. |

## Backlog và phụ thuộc trực tiếp

Các mục dưới đây là chức năng/QA/tích hợp tiếp theo của TV3 hoặc phối hợp owner; không phải danh sách refactor còn nợ của các luồng đã được nghiệm thu.

| Task | Việc còn lại | Phụ thuộc hoặc quyết định |
| --- | --- | --- |
| INV-01 | Đối chiếu UC15/34/35/37/56/57/58 với tiêu chí chức năng | Billing API, Booking snapshot và bằng chứng E1–E2 |
| INV-02, INV-03 | Chốt payment transition, rồi triển khai API/UI UC36 | Quyền, trạng thái đích, paid_at, refund, retry/409 |
| REV-01 | Nghiệm thu điểm vào tạo Review từ Booking detail thật | Endpoint và contract Booking detail TV2 |
| REV-02 | Chốt nghiệm thu chức năng phần Public Review đã chạy trong E4 | Public Property detail đã nối và được dùng trong E4 |
| REV-03 | Nghiệm thu list/summary và quyền Host/Customer | Edge/XAMPP của E4 đã cover các màn hình đọc |
| RPT-01 | Chốt KPI, kỳ, timezone và refund | Quyết định Invoice/payment |
| HOST-01, HOST-02 | Query, API và UI Dashboard Host | RPT-01 và ownership Property |
| ADM-01 | Admin Invoice list/filter/detail theo contract được duyệt | Invoice read và quyền Admin |
| ADM-02 | Admin Booking list/API/UI | Contract Booking TV2 |
| ADM-03, ADM-04 | Query KPI và Dashboard Admin API/UI | RPT-01 |
| ADM-05, ADM-06 | Query thống kê và giao diện theo kỳ | RPT-01, metric/dimension được duyệt |
| FAQ-01, FAQ-02 | Admin list/create và sửa/xóa FAQ | Contract, quyền Admin, lỗi và trạng thái UI |
| CHAT-01, CHAT-02 | Chốt provider/retention/allowlist rồi xây context | Quyền truy cập dữ liệu Catalog, dịch vụ và FAQ |
| CHAT-03 | Session và lịch sử chat, list/detail API/UI | CHAT-01 và schema history |
| CHAT-04, CHAT-05 | Provider adapter, chat API/UI và lưu message | CHAT-01/02/03, test double và gate live riêng |
| QA-01 | Dùng harness và schema cô lập phù hợp từng luồng | E4 có runtime Review. Chuẩn bị fixture riêng cho Invoice, Admin và Chat. |
| QA-02 | Invoice create/read/payment qua luồng thật | INV-01, INV-03 và QA-01 |
| QA-03 | Review chức năng từ Booking thật và race hai kết nối MySQL | E4, REV-01 và fixture Booking/Review |
| QA-04, QA-05 | Gate Reporting/Admin và FAQ/Chat/History | Các API/UI tương ứng và quyền theo actor |

## Bằng chứng kiểm thử theo lượt

Các kết quả dưới đây thuộc lượt triển khai và audit gốc. Số PHP được ghi theo tests/assertions. Lượt cập nhật tài liệu dùng các bằng chứng này để đối chiếu.

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

### REV-ARCH-AUDIT

**E3 · Audit 2026-11-14 · TECHNICAL PASS / ACCEPTED.**

- Laravel đã nhận đủ 4 route Review. Kiểm tra role/ownership, privacy, summary, pagination và parity POST–GET theo contract.
- SQLite: 201 tests/1.285 assertions tập trung Review, full 606 tests/3.806 assertions. Đã kiểm tra rollback, log outer commit và mapping phát sinh 0 query trên dữ liệu đã eager-load.
- Node 75, lint và build PASS. Lint có 3 cảnh báo Fast Refresh, build có cảnh báo chunk và outDir của lượt audit.
- Nguồn là Task Result REV-ARCH-AUDIT và checklist của lượt đó. E4 bổ sung bằng chứng browser/XAMPP sau audit.

### REV-FE-01

**E4 · Kiểm chứng 2026-11-15 · TECHNICAL PASS / ACCEPTED.**

- Node full 133 tests, gồm 8 tests mới. Laravel SQLite in-memory full 828 tests/5.311 assertions. Lint/build PASS với 3 cảnh báo Fast Refresh và cảnh báo chunk lớn.
- Edge + XAMPP, 390/768/1440 px. Public/Host/Customer dùng API thật. Đã kiểm tra phân trang, lọc, rỗng, quyền và response muộn. Lượt chạy lại tắt mock Public Catalog bằng Vite tạm.
- Modal React dùng fixture Booking COMPLETED và session Customer thật. Double-submit chỉ gửi một POST, API trả 201, lần gửi lại trả 409 và XAMPP lưu đúng một Review.
- Diff đúng 10 file, đã dọn 2 schema fixture và đóng các cổng tạm. Nguồn là Task Result REV-FE-01 và log stayhub-rev-fe-*.txt trong TEMP.
- Điểm vào từ trang Booking thật vẫn phụ thuộc TV2. Race tạo Review trên MySQL còn ở QA-03.

### E5 · Profile và chốt audit sau F01/F02

Các số PHPUnit dưới đây thuộc PHP XAMPP chạy SQLite in-memory theo guard của suite. Các ca API, transaction và file được ghi riêng là XAMPP MariaDB/InnoDB.

| Lượt | Bằng chứng đã ghi trong Task Result |
| --- | --- |
| PROFILE-ARCH-01A | Focused 254/1.553, full 830/5.321. Node 133, lint/build PASS. XAMPP API và React Customer lưu/reload/auth sync, Host/Admin smoke test. |
| PROFILE-ARCH-01B | Focused 255/1.558, full 831/5.326. Node 133, lint/build PASS. XAMPP kiểm hash, rollback, throttle và hai phiên Sanctum. |
| PROFILE-ARCH-01C | Focused 256/1.573, full 832/5.341. Node 133, lint/build PASS. Disk thật, stream/header, thay ảnh, rollback, ownership và cleanup. Hai fixture ảnh của lượt cấu hình storage ban đầu đã được đối chiếu hash và dọn, lượt cuối dùng storage riêng. |
| PROFILE-ARCH-AUDIT ban đầu | PARTIAL. Tái hiện F01 outer rollback làm pointer trỏ ảnh cũ đã bị xóa và F02 dùng chung bộ đếm upload/password. |
| PROFILE-ARCH-FIX-01 | Regression mới tái hiện lỗi trước sửa. Sau sửa focused 268/1.687, full 844/5.455. Node 133, lint/build PASS. InnoDB/disk thật kiểm outer commit/rollback, savepoint, nhiều upload, log sau commit, cleanup lỗi và hai bộ đếm độc lập. |
| PROFILE-ARCH-AUDIT chốt | Chạy lại 12 tests/114 assertions và runtime XAMPP/Edge ba role ở 390/1440 px. Kế thừa full suite 844/5.455 và Frontend gates của FIX-01 đã nghiệm thu. Hash 631 đường dẫn khớp, 0 file source/tài liệu đổi, fixture/schema/storage/Edge/cổng tạm đã dọn. TECHNICAL PASS / ACCEPTED. |

Nguồn là Task Results trên chat và log stayhub-profile01a-*, stayhub-profile01b-*, stayhub-profile01c-*, stayhub-profile-audit-*, stayhub-profile-fix01-* và stayhub-profile-audit-close-* trong TEMP.
Các warning nền được ghi nhận gồm 3 Fast Refresh, build chunk lớn và git diff --check toàn repo báo whitespace ở backend/app/Services/Invoice/InvoiceService.php dòng 91. Những cảnh báo này nằm ngoài phạm vi bản sửa Profile.

### E6 · Audit Catalog đã nghiệm thu

- CAT-MGMT-AUDIT có log full SQLite 787 tests/4.958 assertions. Nguồn local stayhub-mgmt-audit-* trong TEMP và Task Result đã nghiệm thu trên chat.
- CAT-RT-AUDIT có log full SQLite 828 tests/5.311 assertions và Node 125 PASS. Log Edge ghi luồng UC19 thật list/create/update/delete, 409, 419, quyền Host, response muộn và các viewport. Nguồn stayhub-rt-audit-* trong TEMP.
- Các số trên là bằng chứng của lượt audit gốc, chưa chạy lại ở lượt cập nhật checklist. Checkpoint được nghiệm thu theo phạm vi đã giao. UC20 được kiểm kê riêng ở E7.

### E7 · CAT-ROOM-00 kiểm kê UC20

**Kiểm chứng 2026-11-15 · TECHNICAL PASS / ACCEPTED trên chat.**

Task kiểm kê đã thực hiện theo Mini Plan được duyệt. Bảng dưới ghi baseline UC20 tại CAT-ROOM-00, trước triển khai. E8 cập nhật phần list Room đã bổ sung sau đó.

| Phạm vi | Hiện trạng đã xác nhận | Việc còn lại |
| --- | --- | --- |
| GET /api/host/properties/{property}/rooms | Chưa đăng ký route, fixture runtime trả 404 | API và UI list phòng thuộc cơ sở của Host |
| POST /api/host/properties/{property}/rooms | Chưa đăng ký route, fixture runtime trả 404 | Chốt input, trạng thái đầu, room_number và RoomType, rồi triển khai tạo phòng |
| GET /api/host/rooms/{room} | Chưa đăng ký route, fixture runtime trả 404 | API/UI chi tiết phòng có ownership |
| PUT /api/host/rooms/{room} | Chưa đăng ký route, fixture runtime trả 404 | Chốt semantics cập nhật, giá/sức chứa, quan hệ và tác động Booking |
| PATCH /api/host/rooms/{room}/status | Chưa đăng ký route, fixture runtime trả 404 | Chốt ma trận trạng thái và ràng buộc Booking/Maintenance |
| Host UI | Có Property detail, quản lý RoomType UC19 và ảnh Property. Chưa có route/component/service riêng cho Host quản lý từng Room | Điểm vào, list/detail/form và các trạng thái UI theo từng checkpoint |
| Public UI/API | GET /api/public/properties/{property} trả rooms. RoomsList/RoomCard hiển thị phòng ACTIVE với RoomType cùng Property | Bảo toàn projection khi triển khai mutation Host |
| GET /api/public/rooms/{room} | Có trong tài liệu dự kiến, chưa đăng ký route thực tế, runtime trả 404 | Xem xét riêng trong phạm vi Public Room, không tính là API đã có của UC20 |

**Schema và phụ thuộc đã kiểm tra**

- Kết nối ứng dụng thực tế dùng XAMPP MariaDB 10.4.32, schema stayhub, bảng nghiệp vụ InnoDB. Kiểm tra metadata và số đếm bằng transaction READ ONLY. Có 13 Room, 0 RoomType lệch Property, 0 nhóm trùng số phòng trong cùng cơ sở, 0 sức chứa không dương/giá âm và 0 liên kết Room/Booking mồ côi trong các truy vấn đã chạy.
- rooms có room_number VARCHAR(50), name VARCHAR(255), description nullable, capacity INT, price_per_night DECIMAL(12,2), status ACTIVE/MAINTENANCE/INACTIVE với default DB ACTIVE. Trạng thái đầu và chuyển trạng thái nghiệp vụ UC20 còn cần chốt.
- Có PK id và index FK property_id/room_type_id. Chưa có unique index (property_id, room_number) hoặc FK tổng hợp buộc RoomType cùng Property. Fixture riêng xác nhận DB chấp nhận số phòng trùng và liên kết RoomType lệch Property. Public query hiện loại Room lệch Property.
- bookings.room_id dùng RESTRICT khi xóa. rooms.property_id và rooms.room_type_id dùng CASCADE khi xóa cha. RoomImage, RoomAmenity và Maintenance tham chiếu Room với CASCADE. API xóa RoomType hiện có bảo vệ Room tham chiếu bằng 409.
- Booking lưu price_per_night, room_total và guest_count. Thay giá/sức chứa Room trong transaction fixture giữ nguyên các giá trị đó của Booking. InvoiceCalculation dùng giá Booking đã lưu. Ownership Invoice đi qua Booking → Room → Property, nên việc cho phép chuyển Room sang Property khác phải được quyết định trước khi có mutation tương ứng.
- BookingService phía React có lời gọi endpoint và nhánh mock bật bằng DEV cùng VITE_ENABLE_BOOKING_MOCKS. Route Backend Booking hiện chưa được tích hợp. Contract availability, Booking đang hiệu lực và Maintenance cần phối hợp TV2.
- UC21 ảnh phòng, UC22 tiện nghi và UC23 bảo trì là các phạm vi phụ thuộc riêng. Các model/quan hệ đã tồn tại.

**Kiểm thử của lượt CAT-ROOM-00**

| Cổng | Kết quả và giới hạn |
| --- | --- |
| Catalog regression | PHP XAMPP chạy SQLite in-memory, 213 tests/1.399 assertions PASS. Đây là các test Catalog đang có của UC04–UC05, UC18 và UC19. |
| Backend full suite | 844 tests/5.455 assertions PASS trên SQLite in-memory. |
| Frontend gates | Node 133 PASS, lint/build PASS. Có 3 warning Fast Refresh và warning chunk lớn. |
| XAMPP API/Edge | Dùng schema fixture riêng. Public projection giữ giá decimal string, lọc ACTIVE/cùng Property và trạng thái rỗng. Host Property/RoomType own 200, guest 401, Customer/Admin 403, Host khác privacy 404. RoomType có Room tham chiếu trả 409. |
| Browser UI | Public và Host Property detail chạy API thật với mock Catalog tắt, kiểm viewport 390/1440 px. Xác nhận UI RoomType hiện có và thiếu điểm vào quản lý từng Room. |
| Data probes | Fixture riêng xác nhận thiếu unique room_number, Booking snapshot giữ nguyên và Booking FK từ chối xóa Room. Transaction probe đã rollback. |
| Baseline và cleanup | Source được đối chiếu hash trước/sau. Chỉ checklist này được cập nhật theo scope. Schema fixture, storage/Edge tạm và các cổng test được dọn sau chạy. |

Nguồn local gồm stayhub-cat-room00-routes.json, stayhub-cat-room00-schema.txt, stayhub-cat-room00-catalog.txt, stayhub-cat-room00-full.txt, stayhub-cat-room00-node.txt, stayhub-cat-room00-lint.txt, stayhub-cat-room00-build.txt và stayhub-cat-room00-xampp.txt trong TEMP.
Runtime dùng DB 3306, origin 5173, Backend tạm 8157, Vite tạm 5174 và Edge CDP 9231.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOM00-AC1 | PASS | Ma trận route/API/UI có source và runtime. Các route dự kiến còn thiếu được ghi rõ. |
| CATROOM00-AC2 | PASS | Metadata schema thực tế, ownership và Room–Property–RoomType đã đối chiếu. |
| CATROOM00-AC3 | PASS | Liệt kê Booking/Invoice/Maintenance dependencies và quyết định còn mở. |
| CATROOM00-AC4 | PASS | Các API/UI đang có được kiểm chứng trên XAMPP và Edge. |
| CATROOM00-AC5 | PASS | Regression đạt, scope được kiểm soát và cleanup hoàn tất. |
| CATROOM00-AC6 | PASS | Đề xuất thứ tự triển khai và checkpoint đầu tiên dưới đây. |

**Thứ tự đề xuất**

1. CAT-ROOM-01 — Host xem danh sách phòng của cơ sở thuộc mình, gồm API và UI.
2. Host đọc chi tiết phòng thuộc mình.
3. Host tạo phòng, sau khi chốt uniqueness số phòng, trạng thái đầu và RoomType cùng Property.
4. Host cập nhật phòng, sau khi chốt field được sửa và bảo toàn Booking/Invoice.
5. Host đổi trạng thái, sau khi chốt ma trận và ràng buộc Booking/Maintenance.
6. Audit UC20 theo các checkpoint được giao và đã tích hợp.

Mỗi bước tiếp theo cần Mini Plan và nghiệm thu riêng. Tại CAT-ROOM-00, UC20 chức năng chưa triển khai. CAT-ROOM-00 đã được người dùng nghiệm thu trên chat. Phần list Room được triển khai sau đó tại E8.

### E8 · CAT-ROOM-01 Host xem danh sách phòng

**Kiểm chứng 2026-11-15 · IMPLEMENTED / TECHNICAL PASS / ACCEPTED trên chat.**

- GET /api/host/properties/{property}/rooms dùng Sanctum/account.active, role HOST và ownership Property. Cơ sở thuộc Host được đọc ở cả ACTIVE/INACTIVE/PENDING/BLOCKED. Danh sách gồm ba trạng thái Room, sắp room_number rồi id, empty data là mảng rỗng.
- Projection gồm id, room_number, name, description nullable, capacity, price_per_night dạng decimal string, status và room_type gồm id/name hoặc null. RoomType lệch Property được trả null, Room thuộc cơ sở vẫn hiển thị. Tham số query bổ sung trả 422, ID không canonical hoặc cơ sở khác chủ trả 404.
- Các lớp được bổ sung gồm HostRoomController, HostRoomListQuery, HostRoomQueryService, HostRoomResponse và các method Catalog Validator/Repository/Mapper. Mapper resolve qua container và Symfony ObjectMapper, phát sinh 0 query. Query eager-load RoomType có số query giữ ổn định khi tăng dữ liệu.
- React dùng hostRoomApi → hostRoomService → hostRoomMapper, gắn HostRoomList vào HostPropertyDetailPage. Có loading/empty/error/retry, nhãn trạng thái tiếng Việt, giá VND, marker RoomType không hợp lệ và bỏ response muộn sau chuyển Property/unmount.
- Phạm vi gốc 16 file được triển khai. Người dùng duyệt bổ sung đúng 1 dòng trong backend/tests/Feature/Auth/AuthenticationTest.php, đổi số route được bảo vệ từ 27 thành 28 sau khi full suite phát hiện mismatch. Các assertion auth/account.active và thứ tự middleware tiếp tục hoạt động.

| Cổng | Kết quả thực tế |
| --- | --- |
| Test mới SQLite | HostRoomListTest 14 tests/70 assertions PASS, gồm quyền, trạng thái, exact contract, query precedence, canonical ID, privacy, order, eager loading và Symfony mapping. |
| Test mới XAMPP MariaDB | HostRoomListTest 14 tests/70 assertions PASS trong schema riêng có guard và SELECT DATABASE(). Schema đã được dọn. |
| Catalog regression | 227 tests/1.469 assertions PASS bằng PHP XAMPP với SQLite in-memory. |
| Backend full suite cuối | 858 tests/5.527 assertions PASS với SQLite in-memory sau dòng test bổ sung được duyệt. Lượt đầu có 1 failure do đếm 27/28 route, được ghi riêng trong log. |
| Frontend Node | 138 PASS, gồm 5 test mới cho transport, ID, projection, decimal/null, response lỗi, HTTP và cancellation. |
| Frontend lint/build | PASS sau khi chuyển reset loading của retry vào event handler. Lint còn 3 warning Fast Refresh nền, build có warning chunk lớn. |
| Edge/XAMPP API | Sanctum thật, own list 200, guest 401, Customer/Admin 403, Host khác 404, query 422, empty, ba trạng thái Room và RoomType lệch Property. |
| Edge/React UI | Có dữ liệu thật từ API, nhãn/giá và 1440/768/390 px. Kiểm retry bằng một response 500 được fault-inject sau request thật. Response phòng thực tế bị giữ lại, sau đó chuyển Property qua React Router và release, danh sách cơ sở mới vẫn đúng. |
| Baseline/cleanup | Hash đối chiếu đúng 17 file được duyệt, các file còn lại khớp baseline. Schema, storage/Edge tạm và cổng kiểm thử đã được dọn. |

Nguồn local gồm stayhub-cat-room01-mysql-tests.txt, stayhub-cat-room01-catalog.txt, stayhub-cat-room01-full.txt (lượt đầu), stayhub-cat-room01-full-final.txt, stayhub-cat-room01-node.txt, stayhub-cat-room01-lint.txt, stayhub-cat-room01-build.txt và stayhub-cat-room01-xampp.txt trong TEMP.
XAMPP MariaDB dùng cổng 3306. Runtime dùng origin 5173, Backend 8157, Vite 5174 và Edge CDP 9231.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOM01-AC1 | PASS | Exact API contract, thứ tự, rỗng và projection đạt trên SQLite/MariaDB. |
| CATROOM01-AC2 | PASS | Role/account/ownership, privacy 404 và RoomType lệch Property đạt. |
| CATROOM01-AC3 | PASS | Validator/DTO/Query/Repository/Mapper, container/Symfony và eager loading được test. |
| CATROOM01-AC4 | PASS | React API thật, retry, responsive và response muộn được kiểm chứng. |
| CATROOM01-AC5 | PASS | Test MariaDB, Catalog/full Backend và Frontend gates đạt. |
| CATROOM01-AC6 | PASS | Đúng scope 16 file và 1 dòng test bổ sung đã duyệt, checklist và cleanup hoàn tất. |

UC20 đã nghiệm thu phần list Room CAT-ROOM-01 và chi tiết phòng CAT-ROOM-02 ở E9.

### E9 · CAT-ROOM-02 Host đọc chi tiết phòng

**Kiểm chứng 2026-11-15 · IMPLEMENTED / TECHNICAL PASS / ACCEPTED trên chat.**

- GET /api/host/rooms/{room} dùng Sanctum/account.active, role HOST và scope Room → Property → host_id. Hỗ trợ ba trạng thái Room và bốn trạng thái Property. Guest 401, role/account không hợp lệ 403, ID không canonical/phòng không tồn tại/khác chủ 404 với cùng thông báo Không tìm thấy phòng. Query bổ sung trả 422.
- Response success/message/data tái sử dụng 8 trường HostRoomResponse của list, bổ sung property {id,name,status}, images là URL array và amenities {id,name,status:boolean}. RoomType lệch Property trả null. Giá giữ decimal string.
- Gallery sắp is_primary giảm dần rồi id, URL đi qua PublicImageUrl để loại đường dẫn riêng tư/URL không hợp lệ. Tiện nghi được gán gồm cả status=false, sắp name rồi id. Quan hệ rỗng trả mảng rỗng.
- Backend dùng HostRoomDetailQuery/HostRoomDetailResponse và các method trong Controller/Validator/QueryService/Repository/Mapper hiện có. Mapper delegate cả hai DTO qua Symfony, phát sinh 0 query. QueryService eager-load đủ bốn relation với số query giữ ổn định, tối đa 5 trong test.
- React có liên kết Xem chi tiết từ list và route /host/rooms/:roomId trong Host guard. API/service/mapper dùng lại chuẩn hóa trường cơ bản. Trang có cơ sở, giá/trạng thái, mô tả, gallery, tiện nghi, loading/error/missing/retry và back link. Component được key theo user/room, hủy và bỏ response muộn khi điều hướng.
- Phạm vi đúng 18 file. AuthenticationTest cập nhật 28 → 29 route được bảo vệ như đã có trong Mini Plan được duyệt. Contract list CAT-ROOM-01 được giữ và có regression.

| Cổng | Kết quả thực tế |
| --- | --- |
| List/detail tập trung SQLite | 29 tests/139 assertions PASS, gồm 15 test mới detail và 14 test list baseline. |
| Detail trên XAMPP MariaDB | 15 tests/69 assertions PASS trong schema riêng có guard và SELECT DATABASE(). Schema đã dọn. |
| Catalog regression | 242 tests/1.538 assertions PASS bằng PHP XAMPP với SQLite in-memory. |
| Backend full suite | 873 tests/5.598 assertions PASS trên SQLite in-memory. |
| Frontend Node | 142 PASS. hostRoomService có 9 test gồm 5 test list baseline và 4 test detail mới. |
| Frontend lint/build | PASS với 3 warning Fast Refresh nền và warning build chunk lớn. |
| XAMPP API | Exact detail contract, thứ tự gallery/amenity, decimal/null, private URL bị loại, 401/403/404/422 và quyền Host khác được kiểm chứng bằng phiên Sanctum thật. |
| Edge/React | List → detail → Property, mở trực tiếp, gallery hiển thị bytes ảnh từ storage tạm, tiện nghi inactive, empty/missing và marker RoomType lệch Property. Có viewport 1440/768/390 px. |
| Retry/late response | GET 500 được fault-inject sau response thật để kiểm retry. Response detail thật bị giữ, sau đó chuyển Room qua React Router và release, dữ liệu Room mới vẫn đúng. |
| Browser rerun | Lượt đầu harness kỳ vọng role khác được chuyển URL, trong khi ProtectedRoute hiện hiển thị Khu vực giới hạn. Đã sửa assertion harness theo baseline và chạy lại toàn bộ lượt browser đạt. |
| Scope và cleanup | Đúng 18 file, các hash ngoài scope khớp baseline. PHPUnit schema và runtime schema, storage/Edge tạm, ảnh fixture và cổng test đã dọn. |

Nguồn local gồm stayhub-cat-room02-focused.txt, stayhub-cat-room02-mysql-tests.txt, stayhub-cat-room02-catalog.txt, stayhub-cat-room02-full.txt, stayhub-cat-room02-node.txt, stayhub-cat-room02-lint.txt, stayhub-cat-room02-build.txt, stayhub-cat-room02-xampp.txt (lượt đầu) và stayhub-cat-room02-xampp-final.txt trong TEMP.
Runtime dùng MariaDB 3306, origin 5173, Backend 8157, Vite 5174 và Edge CDP 9231. Ảnh fixture được phục vụ từ storage riêng của tiến trình kiểm thử.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOM02-AC1 | PASS | Exact contract, rỗng và thứ tự gallery/amenity đạt trên SQLite/MariaDB. |
| CATROOM02-AC2 | PASS | Role/account/ownership, privacy 404, RoomType và URL ảnh được kiểm chứng. |
| CATROOM02-AC3 | PASS | Phân tầng, container/Symfony, mapper 0 query và eager loading ổn định. |
| CATROOM02-AC4 | PASS | Điểm vào từ list, direct URL, back link, responsive, retry và response muộn đạt trên Edge. |
| CATROOM02-AC5 | PASS | Test MariaDB và regression Backend/Frontend đạt. |
| CATROOM02-AC6 | PASS | Scope 18 file, checklist và cleanup được kiểm tra. |

UC20 đã nghiệm thu list/detail/create/update CAT-ROOM-01–04; chưa đóng toàn bộ UC20. CAT-ROOM-05A đã nghiệm thu E12; CAT-ROOM-05B đã nghiệm thu E13; CAT-ROOM-AUDIT đã thực hiện E14, PARTIAL do F01.

### E10 · CAT-ROOM-03 Host tạo phòng

**Kiểm chứng 2026-11-15 · IMPLEMENTED / TECHNICAL PASS / ACCEPTED trên chat.**

- POST /api/host/properties/{property}/rooms dùng Sanctum/account.active, role HOST và ownership Property. Không lọc trạng thái Property: ACTIVE/INACTIVE/PENDING/BLOCKED đều được tạo phòng. Room luôn được server gán ACTIVE; client không được gửi status.
- Chỉ nhận room_type_id, room_number, name, description, capacity, price_per_night. ID loại phòng/sức chứa kiểm tra raw canonical integer trước khi cast; capacity 1..2147483647. Số phòng/name trim, tối đa 50/255 ký tự; description tùy chọn/null, chuỗi rỗng thành null, tối đa 10000 ký tự. Giá là decimal string dương tối đa 9999999999.99, nhiều nhất hai chữ số lẻ và chuẩn hóa bằng BigDecimal thành hai chữ số, không dùng float.
- 201 giữ envelope success/message/data và 8 trường HostRoomResponse qua CatalogMapper/Symfony hiện có. Guest 401, role/account 403, Property sai/khác chủ 404, CSRF 419, raw input/query/file/trường thừa 422. RoomType thiếu/khác Property trả 422 cùng errors.room_type_id. Trùng số phòng cùng Property trả 409; Property khác được dùng cùng số.
- Transaction khóa theo thứ tự Property → RoomType → current locking read số phòng → insert → map trước commit. Kiểm trùng tuân theo collation utf8mb4_unicode_ci của MySQL; khóa Property tuần tự hóa các writer cùng workflow. Chưa có unique index và không thay migration/schema: direct SQL không được bảo vệ bởi quy ước này, writer Room sau phải dùng cùng khóa hoặc cần task constraint riêng.
- Room tạo mới chưa có ảnh/tiện nghi. Không đổi Room cũ hoặc Booking price snapshot. Mapping/insert thất bại rollback; success log chỉ chạy sau outer commit, không log khi rollback.
- React mở form ngay trong HostRoomList, lấy RoomType qua service có sẵn; có trạng thái tải/rỗng/lỗi, field errors 422, conflict 409 và chống submit trùng bằng ref. Tạo thành công refresh list và liên kết detail. Khi timeout/5xx hoặc response không hợp lệ sau POST, khóa form, cho GET kiểm tra danh sách trước khi người dùng chủ động cho phép gửi lại; không tự replay POST.
- Phạm vi đúng 15 file đã duyệt, gồm 4 file tạo và 11 file sửa. AuthenticationTest chỉ đổi đúng một dòng 29 → 30 protected routes. Không sửa mapper Backend, Model, migration, shared Axios, AppRouter hoặc ARCHITECTURE.md.

| Cổng | Kết quả thực tế |
| --- | --- |
| Create tập trung SQLite | 51 tests/220 assertions PASS. |
| Create trên XAMPP MariaDB 10.4.32 | 51 tests/220 assertions PASS trên schema riêng có guard và SELECT DATABASE(); đã dọn schema. |
| Catalog regression | 293 tests/1.758 assertions PASS bằng PHP XAMPP với SQLite in-memory. |
| Backend full suite | 924 tests/5.820 assertions PASS trên SQLite in-memory; không gọi đây là full suite MySQL. |
| Frontend Node | 146 tests PASS; hostRoomService có 13 test, gồm 4 test create mới. |
| Frontend lint/build | Exit 0; còn 3 warning Fast Refresh nền và warning chunk lớn của build. |
| XAMPP API/Edge | Origin 5173, Backend 8157, Vite 5174, MariaDB 3306, CDP 9231. Phiên Sanctum/CSRF thật; 401/403/404/419, duplicate 409, field 422, POST 201 và detail ACTIVE đạt. Dataset tổng hợp được tạo riêng, không phải dữ liệu Booking production. |
| Pending/responsive | Giữ phản hồi POST 201 đã commit, submit hai lần chỉ có một POST. Form không tràn ngang tại 1440/768/390 px; empty RoomType khóa submit. |
| Mất phản hồi sau commit | Harness thay response 201 thật bằng 502 có kiểm soát. UI không tự gửi lại; GET reconciliation thấy phòng đã lưu, người dùng cho phép gửi lại mới phát sinh POST và nhận 409, DB chỉ có một row. Đây là fault injection, không phải lỗi server tự phát. |
| Outer transaction InnoDB | Rollback xóa row và không log; commit giữ row và ghi success đúng sau transaction level 0. |
| Hai connection độc lập | Quan sát INNODB_LOCK_WAITS thật: create/create khác hoa thường với snapshot cũ trả 201/409; create trước xóa RoomType trả 201/409; xóa RoomType trước create trả 200/422. Không trùng số hoặc tạo orphan. |
| Scope/baseline/cleanup | Hash ngoài 15 file giữ nguyên; thay đổi Auth test kiểm tra đúng một dòng. Schema PHPUnit/runtime, Edge/storage tạm và cổng test được dọn; dịch vụ nền 3306/8000/5173 giữ nguyên. |
| Whitespace baseline | Không có lỗi whitespace trong phần Task mới. Global git diff --check còn finding có trước tại InvoiceService.php:91, file được giữ nguyên hash. |

Nguồn local trong TEMP: stayhub-cat-room03-mysql-tests.txt, stayhub-cat-room03-catalog.txt, stayhub-cat-room03-full.txt, stayhub-cat-room03-node.txt, stayhub-cat-room03-lint.txt, stayhub-cat-room03-build.txt, stayhub-cat-room03-xampp.txt và stayhub-cat-room03-final-check.txt. Harness và fixture tạm không thêm vào source repository.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOM03-AC1 | PASS | POST 201 exact envelope/projection, ACTIVE, decimal/null và validation raw input đạt trên SQLite/MariaDB. |
| CATROOM03-AC2 | PASS | Auth/role/account/ownership, canonical ID và RoomType cùng Property được kiểm chứng; CSRF thật có 419. |
| CATROOM03-AC3 | PASS | Conflict CI, rollback/mapping failure, outer commit và ba race với khóa InnoDB thật đạt. Giới hạn uniqueness theo workflow được ghi rõ. |
| CATROOM03-AC4 | PASS | Form/create → list/detail, duplicate submit, 422/409, empty type, responsive và GET reconciliation sau fault 502 đạt trên Edge. |
| CATROOM03-AC5 | PASS | Test mới MariaDB và hồi quy Backend/Frontend đạt; số test và engine tách rõ. |
| CATROOM03-AC6 | PASS | Đúng 15 file, một dòng Auth test, checklist, baseline ngoài scope và cleanup được kiểm tra. |

Người dùng đã nghiệm thu CAT-ROOM-03 trên chat. UC20 còn sửa phòng và quy tắc trạng thái; CAT-ROOM-04 đã triển khai, kiểm chứng và được nghiệm thu tại E11.

### E11 · CAT-ROOM-04 Host cập nhật phòng

**Kiểm chứng 2026-11-15 · IMPLEMENTED / TECHNICAL PASS / ACCEPTED trên chat.**

- PUT /api/host/rooms/{room} dùng Sanctum/account.active, role HOST và ownership Room → Property → host_id. Route ID canonical, giữ thứ tự middleware → role/ID → payload → scope Room/Property → RoomType → duplicate.
- Body đầy đủ room_type_id, room_number, name, capacity, price_per_night; description tùy chọn, thiếu/null/rỗng thành null. Raw integer, trim, giới hạn và decimal string dùng chung quy tắc create, giữ baseline CAT-ROOM-03. Không nhận query/file/trường ngoài sáu trường.
- Trả 200 success/message/data với message Cập nhật phòng thành công. và tám trường HostRoomResponse qua CatalogMapper/Symfony hiện có. Room thiếu/khác chủ/ID sai cùng 404 Không tìm thấy phòng.; type thiếu/khác Property 422 errors.room_type_id; trùng số với Room khác cùng Property 409 Cơ sở đã có phòng cùng số.
- Cho cập nhật cả ba trạng thái Room và bốn trạng thái Property, giữ nguyên status và property_id. RoomType đích bắt buộc cùng Property; có thể sửa liên kết RoomType lệch Property từ dữ liệu cũ. Giá/sức chứa mới áp dụng Catalog; không chặn theo Booking hoặc sửa snapshot/guest_count/trạng thái Booking, Invoice, Maintenance, ảnh và tiện nghi.
- Transaction lookup Property của Room đã scope, khóa Property → Room → RoomType đích → current locking read duplicate loại trừ chính Room → update → map trước commit. Parent lock chung tuần tự hóa với create/xóa RoomType; duplicate theo collation MySQL. No-op trả 200, không save/đổi updated_at/log. Thay đổi thật chỉ log sau outer commit; persistence/mapping failure rollback.
- Frontend thêm Sửa thông tin trên trang detail đang có, không thêm route React. Form prefill, tải/retry RoomType, lỗi/rỗng, field 422, conflict 409, guard chống submit trùng, focus và abort/bỏ response muộn khi đổi Room.
- Kết quả PUT chưa rõ do timeout/network/5xx/malformed success giữ draft và khóa gửi; GET chi tiết đối chiếu trước khi cho phép gửi lại chủ động. Refresh giữ nguyên form đang mount và draft. Thành công tải lại detail; success và lỗi refresh có trạng thái riêng, không tự PUT lại.
- Phạm vi đúng 15 file đã duyệt (3 tạo, 12 sửa). AuthenticationTest chỉ đổi một dòng 30 → 31 protected routes. Không đổi schema, Model, Backend Mapper/Response DTO, shared Axios, AppRouter, .env hoặc ARCHITECTURE.md.

| Cổng | Kết quả thực tế |
| --- | --- |
| Update tập trung SQLite | 55 tests/288 assertions PASS. Có matrix 12 tổ hợp trạng thái, raw inputs, PUT required fields/query/file (kể cả file trùng tên field trong body), no-op không save, privacy/error order, CI duplicate theo engine, type/repair legacy, baseline và rollback. |
| Update trên XAMPP MariaDB 10.4.32 | 55 tests/288 assertions PASS trên schema riêng có guard và SELECT DATABASE(); schema đã dọn. |
| Catalog regression | 348 tests/2.046 assertions PASS bằng PHP XAMPP với SQLite in-memory, gồm toàn bộ create/list/detail baseline. |
| Backend full suite | 979 tests/6.110 assertions PASS trên SQLite in-memory; không gọi đây là full suite MySQL. |
| Frontend Node | 150 tests PASS; hostRoomService 17 test, giữ 13 test cũ và thêm 4 test update cho CSRF/PUT/projection/errors/no replay. |
| Lint/build | Exit 0; còn 3 warning Fast Refresh nền và warning build chunk lớn. |
| Edge/React/API thật | Origin 5173, Backend 8157, Vite 5174, MariaDB 3306, CDP 9231. Guard PUT 401/403/404/419, prefill/cancel, 409/422, double submit chỉ một PUT200, refresh detail/list/public giữ decimal, trả focus về nút sửa và viewport 1440/768/390 đạt. Dataset riêng là fixture tổng hợp, không phải dữ liệu production. |
| RoomType loading/error/empty | GET RoomType thật được fault-inject thành 500/rỗng để kiểm UI, sau retry quay lại dữ liệu API thật. |
| Mất response sau commit | Response PUT200 thật sau commit được thay bằng 502 có kiểm soát; UI giữ draft/form đang mount qua GET detail, không auto PUT; người dùng cho phép gửi lại mới có PUT no-op200. |
| Điều hướng khi pending | Giữ response PUT đã commit rồi điều hướng qua React Router sang Property và Room khác; abort/response muộn không đổi Room mới hoặc hiển thị success cũ. |
| Outer transaction InnoDB | Rollback giữ nguyên row và không log; outer commit giữ thay đổi và success log chỉ ở transaction level 0. |
| Hai connection InnoDB | Quan sát lock wait thật với snapshot cũ: update/update CI 200/409; create trước update 201/409; update trước create 200/409; update đổi loại trước delete 200/409; delete type trước update 200/422. Invariant dữ liệu không trùng/orphan, trạng thái được giữ. |
| Browser rerun | Lượt đầu harness click link detail khi list chưa tải xong. Đã bổ sung wait đúng link, không sửa source cho lỗi harness; chạy lại toàn bộ browser/DB/concurrency đạt. |
| Scope/baseline/cleanup | Hash ngoài 15 file khớp baseline, Auth test xác minh đúng một dòng. Schema PHPUnit/runtime, Edge/storage/process và cổng fixture được dọn; giữ các PID dịch vụ nền 3306/8000/5173. |
| Whitespace | Phần task không có lỗi whitespace. Global diff còn finding có trước InvoiceService.php:91, file được giữ nguyên hash. |

Nguồn local trong TEMP: stayhub-cat-room04-focused.txt, stayhub-cat-room04-mysql-tests.txt, stayhub-cat-room04-catalog.txt, stayhub-cat-room04-full.txt, stayhub-cat-room04-node.txt, stayhub-cat-room04-lint.txt, stayhub-cat-room04-build.txt, stayhub-cat-room04-xampp.txt (lượt đầu), stayhub-cat-room04-xampp-final.txt và stayhub-cat-room04-final-check.txt.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOM04-AC1 | PASS | PUT200 exact contract, raw validation/required fields và no-op không save/đổi updated_at đạt trên SQLite/MariaDB. |
| CATROOM04-AC2 | PASS | Auth/account/ownership, privacy 404, RoomType và CSRF thực đạt. |
| CATROOM04-AC3 | PASS | Duplicate exclude self/CI, rollback/mapping failure, outer commit/log và 5 race InnoDB đạt. |
| CATROOM04-AC4 | PASS | React prefill/save, pending/errors, giữ draft qua GET reconciliation, hủy/response muộn và responsive đạt. |
| CATROOM04-AC5 | PASS | Booking/Invoice/ảnh/tiện nghi/Maintenance/Room khác/status giữ baseline; regression Backend/Frontend đạt. |
| CATROOM04-AC6 | PASS | Đúng 15 file, một dòng Auth test, checklist và cleanup được kiểm tra. |

Giới hạn đã duyệt: uniqueness vẫn theo workflow parent lock, direct SQL chưa có unique index; cập nhật cùng Room tuần tự hóa và lần ghi sau cùng thắng, chưa có version/ETag. Không mở rộng quy tắc Booking/availability hoặc trạng thái.

Người dùng đã nghiệm thu CAT-ROOM-04 trên chat. UC20 còn chốt/triển khai trạng thái và audit; CAT-ROOM-05A đã nghiệm thu E12; CAT-ROOM-05B đã nghiệm thu E13; CAT-ROOM-AUDIT đã thực hiện E14, PARTIAL do F01.

### E12 · CAT-ROOM-05A chốt ma trận trạng thái Room

**Kiểm chứng 2026-11-15 · SPECIFIED / TECHNICAL PASS / ACCEPTED trên chat.**

Nguồn quy tắc: [UC20-ROOM-STATUS-RULES.md](../TV1/UC20-ROOM-STATUS-RULES.md). Thực hiện đúng Mini Plan 2 file tài liệu, không sửa source/schema. Đây là nghiệm thu đặc tả và kỹ thuật query/lock trên fixture; không phải triển khai API/UI trạng thái.

- XAMPP thực tế MariaDB 10.4.32, schema stayhub, rooms/bookings/maintenances InnoDB, REPEATABLE-READ. Timezone ứng dụng hiệu lực UTC, DB session SYSTEM. Dev metadata/data chỉ READ ONLY.
- Dev có 13 Room, 16 Booking, 4 Maintenance. Không thấy khoảng ngày đảo/rỗng/zero trong các truy vấn tổng hợp. Ngày UTC 2026-11-15 có 2 CHECKED_IN đã tới/quá checkout và 1 IN_PROGRESS đã tới/quá end_date; ghi nhận cho owner, không tự sửa hoặc coi hoàn tất.
- B: CHECKED_IN bất kể ngày; hoặc PENDING/CONFIRMED có ngày sai hoặc checkout > D. Terminal không chặn. M: IN_PROGRESS bất kể ngày; hoặc SCHEDULED có ngày sai hoặc start <= D < end. Lịch tương lai/terminal không chặn mở lại.
- D lấy một lần theo timezone ứng dụng và bind vào query, khoảng [start,end). CHECKED_IN/IN_PROGRESS quá hạn vẫn cần workflow owner xử lý. MAINTENANCE là khóa vận hành thủ công, không bắt buộc/tự tạo/hủy/hoàn tất Maintenance record.

| Từ → Đến | ACTIVE | INACTIVE | MAINTENANCE |
| --- | --- | --- | --- |
| ACTIVE | No-op | Không B | Không B |
| INACTIVE | Không M | No-op | Không B |
| MAINTENANCE | Không M | Không B và không M | No-op |

- Contract bàn giao 05B: PATCH /api/host/rooms/{room}/status, chỉ body status đúng enum, Sanctum/account.active/HOST/ownership. 200 tái sử dụng tám trường HostRoomResponse; privacy 404, input 422, B/M 409; no-op không save/log, B ưu tiên khi cùng vướng B/M.
- Giữ Booking/Invoice/snapshot/guest_count, Maintenance, ảnh/tiện nghi và Room khác. ACTIVE không đồng nghĩa phòng trống. Property gate và baseline create/update không đổi.
- Quy ước tương lai: Property → Room locks, current locking fact reads, map trước commit, log sau outer commit. Writer Booking/Maintenance phải khóa/recheck cùng quy ước. FK riêng lẻ không bảo đảm business rules.

| Cổng | Kết quả và giới hạn |
| --- | --- |
| Predicates MariaDB | 120/120 case PASS: 72 Booking, 48 Maintenance; đủ status và 12 dạng ngày, gồm biên D, quá hạn, zero component/ngày lịch sai trong session legacy riêng. Strict mode rejection được xác nhận và mode fixture được khôi phục. |
| Matrix MariaDB | 36/36 probe PASS: 9 ô × 4 tổ hợp B/M, expected literal độc lập. Outcome 200/409 là quyết định harness, không phải HTTP API mới. |
| Invariant/rollback | SQL transaction fixture giữ snapshot/relations/Room khác, no-op/reject không đổi row; rollback phục hồi fixture. Không thay thế test Service/no-op/log/mapper của 05B. |
| Scope/timezone | Record Room khác không ảnh hưởng, nhiều record đúng EXISTS; UTC/Bangkok qua ngày và bound DATE giữ kết quả khi DB session +07:00. |
| Hai connection | 4 probe có lock wait InnoDB thật: Booking/Maintenance fact mới không thấy qua snapshot cũ nhưng thấy qua current read; cooperating Booking writer thấy INACTIVE và chặn; writer bỏ recheck vẫn insert sau FK wait. |
| Negative control | Fixture xác nhận holding Booking trên Room INACTIVE khi bỏ business recheck. Đây là giới hạn được chứng minh, không phải lỗi dữ liệu dev; schema đã dọn. |
| Catalog regression | 348 tests/2.046 assertions PASS bằng PHP XAMPP với SQLite in-memory. |
| Backend full suite | 979 tests/6.110 assertions PASS trên SQLite in-memory. |
| Frontend | Node 150 PASS, lint/build exit 0; còn 3 warning Fast Refresh nền và warning chunk lớn. |
| Source/route/UI | Registry có GET/PUT host Room và Public Property GET; source giữ list/detail/create/update, chưa có PATCH status hoặc module route Booking/Maintenance. Không mở browser hoặc giả lập endpoint status trong 05A. |
| Baseline/cleanup | Source hash ngoài 2 tài liệu khớp; fingerprint 10 bảng dev trước/sau khớp. Schema fixture đã DROP, không tạo listener tạm; PID nền 3306/8000/5173 giữ nguyên. |
| Harness rerun | Metadata dùng @@tx_isolation của MariaDB; race đầu lỗi escape newline IPC, đã sửa harness và chạy lại toàn bộ đạt. Source không đổi cho các lỗi harness. |
| Whitespace | Hai tài liệu đạt; global diff còn finding cũ InvoiceService.php:91, giữ nguyên source. |

Nguồn local trong TEMP: stayhub-cat-room05a-dev-before.json, stayhub-cat-room05a-dev-after.json, stayhub-cat-room05a-predicate-cases.json, stayhub-cat-room05a-matrix-cases.json, stayhub-cat-room05a-xampp.txt (lượt đầu), stayhub-cat-room05a-xampp-final.txt, stayhub-cat-room05a-catalog.txt, stayhub-cat-room05a-full.txt, stayhub-cat-room05a-node.txt, stayhub-cat-room05a-lint.txt, stayhub-cat-room05a-build.txt, route registry JSON và stayhub-cat-room05a-final-check.txt.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOM05A-AC1 | PASS | Source/schema/route registry/owner và phần chưa tích hợp đã đối chiếu. |
| CATROOM05A-AC2 | PASS | Đủ 9 ô, B/M, biên ngày/timezone, no-op và 120 predicate cases. |
| CATROOM05A-AC3 | PASS | Contract 05B, ownership/error order và invariant được ghi rõ. |
| CATROOM05A-AC4 | PASS | 36 matrix + 4 lock probes, expected độc lập và negative control; giới hạn concurrency thật được ghi rõ. |
| CATROOM05A-AC5 | PASS | Regression, đúng 2 tài liệu, checklist, source/data baseline và cleanup. |

Người dùng đã nghiệm thu CAT-ROOM-05A trên chat. Tại mốc05A API/UI status chưa triển khai; kết quả triển khai mới xem E13. Race toàn workflow Booking/Maintenance vẫn NOT VERIFIED. CAT-ROOM-05B đã nghiệm thu E13; CAT-ROOM-AUDIT đã thực hiện E14, PARTIAL do F01; chưa đóng UC20.

### E13 · CAT-ROOM-05B Host đổi trạng thái phòng

**Kiểm chứng 2026-11-15 · IMPLEMENTED / TECHNICAL PASS / ACCEPTED trên chat.**

Giữ [quy tắc 05A đã nghiệm thu](../TV1/UC20-ROOM-STATUS-RULES.md), phần triển khai/evidence mới ở mục 10 của tài liệu đó.

- PATCH /api/host/rooms/{room}/status chỉ nhận status enum, Sanctum/account.active/HOST/ownership. Raw JSON không bị ép thành enum; thiếu/sai/query/file/extra trả422; 401/403/419 baseline; canonical/Room thiếu/khác chủ cùng404. 200 success/message/data, tám trường HostRoomResponse qua Symfony hiện có, legacy RoomType null/decimal giữ nguyên.
- Transaction Property → Room, current raw Booking/Maintenance facts theo transition, ngày D một lần theo UTC sau locks. Validator thuần dùng checkdate để giữ đúng ngày sai/zero/calendar invalid. B trước M, message409 đúng05A. Không query facts/save/updated_at/log khi no-op.
- Ghi whitelist status, map trước commit, persistence/map failure rollback; log safe IDs chỉ sau outer commit. Không tự tạo/hủy/hoàn tất Booking/Maintenance, không sửa snapshot/giá/sức chứa/ảnh/tiện nghi hoặc Room khác.
- React có form trạng thái và xác nhận trên detail; loại trừ với form sửa thông tin. Có field422/global409, pending ref chống submit trùng, cancel/focus, abort/late response guard. Unknown PATCH giữ lựa chọn/form, GET đối chiếu trước gửi lại chủ động, không auto replay.
- Đúng16file (3 tạo/13 sửa), AuthenticationTest đúng một dòng31→32. Không sửa Model/enum/schema/mapper Backend/ResponseDTO/AppRouter/Axios chung/ARCHITECTURE hoặc module Booking/Maintenance.

| Cổng | Kết quả và giới hạn |
| --- | --- |
| Focused SQLite | 177 tests/505 assertions PASS |
| Focused XAMPP MariaDB10.4.32 | 177 tests/505 assertions PASS trong schema owned có guard/SELECT DATABASE, đã dọn |
| Matrix/date thật | 36 tổ hợp matrix và120 case ngày/trạng thái qua endpoint/guard production PASS; SQL mode legacy chỉ fixture và khôi phục |
| No-op/security/invariant | No save/facts/updated_at/log; permissions/privacy/raw enum/query/file/extra, type null/decimal, liên quan giữ baseline |
| Transaction | Feature failure sau UPDATE và mapper rollback; InnoDB outer rollback/commit, success log chỉ level0 |
| Catalog SQLite | 525 tests/2.551 assertions PASS |
| Backend full SQLite | 1.156 tests/6.617 assertions PASS; không gọi full MySQL |
| Frontend Node/lint/build | Node154 PASS (hostRoomService21, giữ17cũ+4status), lint/build exit0; còn3FastRefresh warning và chunk lớn |
| Edge/API thật | Session/CSRF 401/403/404/419, B/M409 quá hạn, no-op/mutation200, list/public projection, focus và viewport1440/768/390 đạt |
| UI422 | Option enum sai được harness thêm vào DOM để kiểm request thật/field error; không phải option production |
| Double submit/unknown result | Giữ committed PATCH200 chỉ một request; fault200→502 giữ form/lựa chọn qua GET, không autoPATCH, explicit replay no-op200 |
| Baseline04/late response | PUT sửa thông tin thật vẫn đạt, hai form loại trừ; chuyển Room trong React Router khi response giữ không đổi Room mới/success cũ |
| Six InnoDB races | Fact Booking/Maintenance mới → actual status409; status → cooperating writer từ chối; status/status đọc state mới và M409; status/update info giữ INACTIVE; negative control bỏ recheck vẫn insert sau FK wait |
| Dev/source/cleanup | Hash ngoài16file và fingerprint10bảng dev giữ nguyên; schema PHPUnit/runtime, storage/Edge/process/cổng tạm dọn; PID3306/8000/5173 giữ nguyên |
| Whitespace | Task không có lỗi mới; global diff còn finding nền InvoiceService.php:91, giữ nguyên source |

Runtime fixture: origin5173, Backend8157, Vite5174, CDP9231, DB3306. Source React/API thật với dataset tổng hợp riêng. Phía status trong race dùng Service production; writer Booking/Maintenance là harness. Race toàn workflow API của các module chưa tích hợp vẫn NOT VERIFIED, không tự sửa ngoài scope.

Nguồn local trong TEMP: stayhub-cat-room05b-focused.txt, stayhub-cat-room05b-mysql-tests.txt, stayhub-cat-room05b-catalog.txt, stayhub-cat-room05b-full.txt, stayhub-cat-room05b-node.txt, stayhub-cat-room05b-lint.txt, stayhub-cat-room05b-build.txt, stayhub-cat-room05b-xampp.txt, stayhub-cat-room05b-dev-before.json, stayhub-cat-room05b-dev-after.json và stayhub-cat-room05b-final-check.txt.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOM05B-AC1 | PASS | Exact contract/input, auth/account/ownership/privacy/error order và CSRF thật |
| CATROOM05B-AC2 | PASS | Matrix/B/M/ngày/timezone đúng05A qua production guard, no-op không write/log/fact query |
| CATROOM05B-AC3 | PASS | Current reads, rollback/outer commit/log, sáu race và invariant |
| CATROOM05B-AC4 | PASS | React thật, mutual exclusion/pending/errors/focus/GET reconciliation/late response/responsive |
| CATROOM05B-AC5 | PASS | MariaDB và regression Backend/Frontend, baseline01–04/05A |
| CATROOM05B-AC6 | PASS | Đúng16file/1dòngAuth, docs/checklist, source/data fingerprint và cleanup; TV2 dependency ghi rõ |

Người dùng đã nghiệm thu CAT-ROOM-05B trên chat. CAT-ROOM-AUDIT đã thực hiện E14, PARTIAL do F01; không tự đóng UC20. Evidence E13 vẫn là lượt05B; E14 có bằng chứng chạy mới.

### E14 · CAT-ROOM-AUDIT rà UC20 sau01–05B

**Kiểm chứng 2026-11-15 · EXECUTED / PARTIAL · F01 OPEN · CHƯA CHỐT TECHNICAL PASS.**

Đã thực hiện toàn bộ lượt audit theo Mini Plan. Chỉ checklist này thay đổi; source/tests, đặc tả05A/05B, ARCHITECTURE và skills giữ nguyên. Giữ lịch sử nghiệm thu01–05B, không dùng nghiệm thu cũ thay kết quả audit mới hoặc tự đóng UC20.

#### Phạm vi đã rà

| API | Kết quả |
| --- | --- |
| GET /api/host/properties/{property}/rooms | Exact list, order/empty, ba trạng thái Room/bốn trạng thái Property, ownership/error order, mapper/eager loading đạt |
| GET /api/host/rooms/{room} | Exact detail, property/images/amenities, private URL loại bỏ, order/null/empty, role/privacy/canonical/query đạt |
| POST /api/host/properties/{property}/rooms | Luồng chuẩn201/ACTIVE/type/decimal/duplicate/transaction/recovery đạt; **file multipart trùng key name vi phạm contract — F01** |
| PUT /api/host/rooms/{room} | Full update, field validation/conflict, giữ status/property/snapshot, no-op/transaction/GET reconciliation đạt |
| PATCH /api/host/rooms/{room}/status | Raw enum/ma trận B/M/ngàyUTC/no-op/current read/log, role/privacy và UI recovery đạt |

- Đã trace route/middleware → Controller → Validator/typed Request → QueryService/Service → Repository/Model → CatalogMapper/EloquentObjectMapper/Symfony/ResponseDTO. DI/container được kiểm bằng test hiện có trên engine MariaDB. Catalog giữ envelope success/message/data.
- QueryService eager-load, list tối đa3query/detail tối đa5query trong test và không tăng theo số Room; mapper không query/mutate. Validator domain không tự query. Giá giữ decimal string/BigDecimal.
- Property/list → create → detail → edit/status → back/direct URL, loading/empty/missing/retry, gallery/amenities, focus, pending/mutual exclusion, cancellation/late response được chạy trên React/API thật.
- Source store ghép body/files bằng array union thiên trái. Đây là chỗ bỏ sót validation được HTTP probe mới phát hiện; các suite hiện có chưa có case cùng key body/file.

#### Finding F01 — P2 — create bỏ qua file trùng tên field hợp lệ

**Trạng thái lịch sử lượt audit 2026-11-15: OPEN, chặn CATROOMAUDIT-AC2. Cập nhật2026-11-16: FIX-01 đã nghiệm thu E15; F01 CLOSED sau tái kiểm E16.**

Vị trí: [HostRoomController.php:44](../../backend/app/Http/Controllers/Catalog/HostRoomController.php#L44), method store.

Tái hiện trên phiên HOST owner/Sanctum/CSRF thật, schema fixture owned:
1. POST multipart tới /api/host/properties/1/rooms với sáu trường hợp lệ: room_type_id=1, room_number=FILE-COLLISION, name=Collision file ignored, description rỗng, capacity=2, price_per_night=100.00.
2. Cùng FormData thêm file text fixture.txt dưới key name, trùng key scalar name.
3. Expected: 422 và không tạo Room, vì contract create không nhận file.
4. Actual: **201**, response data.id=8 trong fixture, status ACTIVE, name vẫn Collision file ignored; DB xác nhận row FILE-COLLISION đã được lưu.
5. Negative control: cùng payload/file nhưng key photo không trùng trả422 errors.photo đúng baseline.

Nguyên nhân: body + request.allFiles dùng array union, key bên body được giữ nên UploadedFile cùng key bị bỏ trước Validator. Whitelist DTO không phát hiện request có file bị cấm khi file đã bị loại khỏi input.

Ảnh hưởng đã quan sát: tạo Room thành công trên request lẽ ra phải bị từ chối. Không có bằng chứng vượt ownership/role hoặc ghi file vào storage; đây là lỗi validation/contract. Không sửa source trong audit.

Row fixture F01 đã được kiểm chứng rồi xóa trong schema owned trước các invariant/race probes; schema và upload temp cuối cùng được dọn. Không tác động dữ liệu dev. Hướng xử lý riêng: không để file trùng key bị bỏ khi dựng input, bổ sung regression cho cùng key body/file và control khác key. Đề xuất Mini Plan CAT-ROOM-FIX-01, chưa triển khai.

Bằng chứng: stayhub-cat-room-audit-create-runtime.txt và stayhub-cat-room-audit-F01.json trong TEMP. Harness exit0 nghĩa các assertion tái hiện finding/luồng chuẩn đã chạy xong, **không có nghĩa gate F01 PASS**.

#### Kết quả test mới của lượt audit

| Cổng | Kết quả thực tế |
| --- | --- |
| HostRoomListTest — MariaDB | 14 tests/70 assertions PASS |
| HostRoomDetailTest — MariaDB | 15 tests/69 assertions PASS |
| HostRoomCreateTest — MariaDB | 51 tests/220 assertions PASS; chưa bao phủ F01 |
| HostRoomUpdateTest — MariaDB | 55 tests/288 assertions PASS |
| HostRoomStatusTest — MariaDB | 177 tests/505 assertions PASS |
| Tổng focused MariaDB10.4.32 | **312 tests/1.152 assertions PASS**, năm schema owned riêng đúng guard/SELECT DATABASE, không nới test guard |
| Catalog SQLite | 525 tests/2.551 assertions PASS bằng PHP XAMPP |
| Backend full SQLite | 1.156 tests/6.617 assertions PASS; không báo là full MySQL |
| Frontend Node | 154 PASS |
| Lint/build | Exit0, còn3FastRefresh warning nền và warning build chunk lớn |
| HTTP probe F01 mới | **FAIL contract: expected422/không write, actual201/Room persisted**; control photo422 |
| Source/data/cleanup | Chỉ1checklist đổi, source hash khớp; 10bảng dev fingerprints giữ nguyên; fixture schemas/storage/Edge/process/cổng tạm dọn |

Năm focused suite chạy nguyên source, mỗi schema có GUID32 và prefix/biến guard tương ứng đã ghi trong Mini Plan. Không sửa source/test để đạt kết quả.

#### Runtime mới trên XAMPP và Edge

Bốn phase fixture riêng read/create/update/status; scripts được kế thừa từ checkpoint nhưng đã thực thi mới trên source hiện tại. Tên checkpoint trong một số caption log là tên harness, không phải tái sử dụng kết quả cũ.

- Session/CSRF thật: guest401, Customer/Admin403, Host khác404 và own200; mutation419; field/query/canonical422/404/409 đúng baseline ngoài F01.
- List/detail: exact projection, giá decimal, RoomType lệch Property trảnull và không lộ type khác; gallery primary/secondary đúng thứ tự, bytes PNG thật từ storage tạm, amenities gồm inactive/tie ordering; private avatar/unassigned amenities không lộ.
- UI loading/empty/missing/directURL/retry sau GET500 fault injection; chuyển qua React Router trong khi giữ response thật không bị dữ liệu Room cũ ghi đè.
- Create: POST201 thật, duplicate409, price field422, empty RoomType khóa submit, double submit một POST, lost response sau commit được thay502 có kiểm soát; GET list đối chiếu không tự replay, explicit resend nhận409.
- Update: PUT200 thật, prefill/type error/empty/retry, duplicate409/field422, double submit một PUT, refresh list/detail/public, focus; lost committed response502 giữ form/draft qua GET, explicit no-op200; điều hướng khi response giữ không đổi Room mới.
- Status: PATCH200/no-op, B/M409 với record quá hạn, raw enum422, hai form edit/status loại trừ, focus; giữ committed200 và fault502 kiểm duplicate/GET reconciliation/lựa chọn/no auto replay, explicit no-op200. Option sai trong test được harness thêm vào DOM, không phải option production.
- Viewports1440/768/390, gallery/amenities/empty states và các form không tràn ngang.
- Public projection ACTIVE/cùng Property được kiểm sau mutation; không gọi đây là availability theo ngày.
- Outer transaction InnoDB của create/update/status: rollback giữ baseline, commit lưu thay đổi, success log chỉ sau transaction level0. FeatureTest xác minh lỗi sau persistence/mapping rollback; no-op không save/log và status không đọc facts.

#### 14 race InnoDB có wait thật

Ba phase mutations dùng hai connection/tiến trình độc lập, prime snapshot trước lock và quan sát information_schema.INNODB_LOCK_WAITS. Catalog side gọi Service production.

| Nhóm | Cases/kết quả |
| --- | --- |
| Create —3cases | create/create CI201/409; create trước RoomType delete201/409; delete trước create200/422 |
| Update —5cases | update/update CI200/409; create trước update201/409; update trước create200/409; update đổi loại trước delete200/409; delete trước update200/422 |
| Status —6cases | Booking/Maintenance fact commit trước status →409; status trước cooperating writer200/409; status/status200/409 theo current MAINTENANCE; status/update200/200 giữ INACTIVE; negative-control bypass writer200/201 |

Kết quả và row cuối đạt expected ở14cases. Writer Booking/Maintenance là harness; chưa có route/workflow production tương ứng được tích hợp, nên race toàn workflow đó giữ NOT VERIFIED. Negative-control bypass writer vẫn tạo Booking trên Room INACTIVE sau FK wait là giới hạn đã chốt05A/05B, không phải finding mới.

#### Baseline và dependencies

- READ ONLY metadata/dev: MariaDB10.4.32/InnoDB/REPEATABLE-READ, appUTC/DBsessionSYSTEM. Fingerprint properties, room_types, rooms, bookings, booking_services, invoices, invoice_items, maintenances, room_images, room_amenities trước/sau khớp.
- 2 CHECKED_IN đã tới/quá checkout và1IN_PROGRESS đã tới/quá end_date giữ nguyên, ghi cho owner rà workflow; không tự sửa.
- D01: uniqueness vẫn theo workflow parent lock; schema chưa có unique(property_id,room_number). Fixture SQL trực tiếp tạo duplicate được rồi rollback, không xem đây là regression so với scope đã duyệt.
- D02: Booking/Maintenance writers phải tuân thủ Property→Room/recheck; API integration/race thật chưa xác nhận.
- D03: DELETE Room chưa có route và ngoài năm luồng được giao; Booking FK hiện RESTRICT. Không tự bổ sung hoặc tuyên bố UC20 trọn vẹn đã đóng.
- UC21/22/23 chỉ audit projection/dependency, không triển khai quản lý ảnh/tiện nghi/bảo trì.
- Runtime giữ3306/8000/5173, dùng Backend8157/Vite5174 qua origin5173/CDP9231. Bốn schema runtime, năm schema focused, ảnh/storage/Edge/process owned đã dọn; PID nền giữ nguyên.
- Global diff còn whitespace nền InvoiceService.php:91, các source/tài liệu khác giữ nguyên hash. Hai file rules/architecture chỉ đọc; không tự đồng bộ trạng thái hoặc sửa findings vào source.

Nguồn local trong TEMP:
- stayhub-cat-room-audit-mysql.txt và stayhub-cat-room-audit-mysql-HostRoom{List,Detail,Create,Update,Status}Test.txt.
- stayhub-cat-room-audit-catalog.txt, stayhub-cat-room-audit-full.txt, stayhub-cat-room-audit-node.txt, stayhub-cat-room-audit-lint.txt, stayhub-cat-room-audit-build.txt.
- stayhub-cat-room-audit-read-runtime.txt, stayhub-cat-room-audit-create-runtime.txt, stayhub-cat-room-audit-update-runtime.txt, stayhub-cat-room-audit-status-runtime.txt.
- stayhub-cat-room-audit-F01.json, stayhub-cat-room-audit-evidence-summary.json, stayhub-cat-room-audit-routes.json.
- stayhub-cat-room-audit-dev-before.json, stayhub-cat-room-audit-dev-after.json, stayhub-cat-room-audit-final-check.txt.

#### Checklist audit

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOMAUDIT-AC1 | PASS | Đủ5API/UI, route/contract/architecture/mapper/projection đã đối chiếu; sai lệch input tách F01 ởAC2 |
| CATROOMAUDIT-AC2 | **FAIL** | Auth/account/ownership/privacy/canonical đạt, nhưng POST file cùng key vi phạm validation: F01 |
| CATROOMAUDIT-AC3 | PASS | Các luồng payload hợp lệ, matrix05A, snapshots/no-op/rollback/outer commit/log đạt; input F01 vẫn FAIL ởAC2 |
| CATROOMAUDIT-AC4 | PASS | MariaDB collation/locks/current reads/14races và dependency/negative control ghi đúng |
| CATROOMAUDIT-AC5 | PASS | React/API thật, loading/empty/error/retry/focus/viewport/pending/reconciliation/late response đạt |
| CATROOMAUDIT-AC6 | PASS | Năm focused MariaDB + Catalog/full/Node/lint/build chạy mới có count/engine/evidence |
| CATROOMAUDIT-AC7 | PASS | Đúng1checklist thay đổi, source/data baseline, cleanup và findings/acceptance được ghi rõ |

**Kết luận lịch sử lượt audit 2026-11-15: PARTIAL.** Evidence201/AC2FAIL ở E14 giữ nguyên để tra cứu. F01 đã sửa và được nghiệm thu tại E15, CLOSED sau tái kiểm E16 ngày2026-11-16. Kết luận hiện hành xem E16; không tự đóng toàn UC20.

### E15 · CAT-ROOM-FIX-01 xử lý F01

**2026-11-16 (Asia/Bangkok) · IMPLEMENTED / TECHNICAL PASS / ACCEPTED theo xác nhận trên chat.**

Đúng Mini Plan3file đã duyệt: HostRoomController.php, HostRoomCreateTest.php và checklist này. Không đổi contract, Service/Repository/Validator/DTO/mapper, route/Auth count, Frontend, schema hoặc các quy tắc01–05B.

- Controller store chỉ đổi biểu thức ghép body/files từ union thiên trái sang array_replace(body,files), để UploadedFile/array file trùng key không bị scalar body che mất. Validator hiện có trả422 theo field, không thêm business logic vào Controller.
- Regression mới kiểm đủ sáu key được phép, file-only/unknown-key và nested/multiple; mọi request bị từ chối không tạo Room/log success. Multipart không có file giữ exact201/eight-field/ACTIVE/decimal/null contract.
- Đã chạy RED trước sửa Controller: **7 failed,53passed/239assertions**, gồm6case trùng key và test nested/multiple nhận201 thay422. Không coi expected RED là lỗi còn tồn tại sau fix.
- Sau đổi đúng một biểu thức, giữ toàn bộ51test create cũ và thêm9case test mới; không xóa/sửa test cũ hoặc nới DB guard.

| Cổng | Kết quả thực tế |
| --- | --- |
| Focused SQLite | 60 tests/271 assertions PASS |
| Focused XAMPP MariaDB10.4.32 | 60 tests/271 assertions PASS; schema stayhub_room03_test_GUID32, STAYHUB_ROOM_CREATE_TEST_DATABASE/SELECT DATABASE guard giữ nguyên; có xác nhận tables thực sự migrate trên schema owned, đã dọn |
| Catalog regression SQLite | 534 tests/2.602 assertions PASS bằng PHP XAMPP |
| Backend full SQLite | 1.165 tests/6.668 assertions PASS, không gọi đây là full MySQL |
| Frontend Node/lint/build | Node154 PASS, lint/build exit0; còn3FastRefresh warnings nền, chunk lớn và build timing diagnostic, không sửa Frontend |
| HTTP multipart thật | 10/10case trả422 đúng field:6collision keys, unknown photo, file-only name, nested name[child], multiple name[] |
| Không ghi sai dữ liệu | GET list trước valid control và DB probe xác nhận không có Room REJECT-* nào, không tăng count do10case bị từ chối |
| Positive controls | React JSON create201 và multipart không có file201, tám trường, trim/null, ACTIVE, RoomType và decimal99.90 đúng; fixture DB xác nhận |
| Browser/regression | Session/CSRF401/403/404/419, duplicate409/price422, empty RoomType, double submit mộtPOST, list/detail, recovery sau controlled502 qua GET không autoPOST, viewport1440/768/390 đạt |
| InnoDB baseline | Create Service outer rollback/commit và success log chỉ sau commit vẫn đạt trên fixture |
| Source/data/cleanup | Đúng3file; Controller reverse-expression hash khớp baseline; source ngoài scope/fingerprint10bảng dev khớp; schema/storage/Edge/process/cổng tạm dọn, PID3306/8000/5173 giữ nguyên |
| Whitespace | Ba file đạt; global diff vẫn có finding nền InvoiceService.php:91, source đó không đổi |

HTTP runtime dùng DB3306, origin5173, Backend8157, Vite5174, CDP9231; dataset tổng hợp nằm trong schema/storage owned. Invalid requests gửi FormData/file thật qua fetch với phiên Sanctum/CSRF, không giả lập response422. Fault502 chỉ dùng kiểm regression recovery sau create đã commit, ghi rõ riêng.

Harness runtime được kế thừa từ create03 và chạy mới trên code sau fix. Lúc chuẩn bị harness tạm có lỗi đọc UTF-8 bằng encoding mặc định Windows; đã sửa encoding harness trước khi chạy, không cần sửa source vì lỗi này. Caption checkpoint cũ trong log không phải tái sử dụng kết quả cũ.

Artifacts local trong TEMP: stayhub-room-fix01-red.txt, stayhub-room-fix01-focused.txt, stayhub-room-fix01-mysql-tests.txt, stayhub-room-fix01-catalog.txt, stayhub-room-fix01-full.txt, stayhub-room-fix01-node.txt, stayhub-room-fix01-lint.txt, stayhub-room-fix01-build.txt, stayhub-room-fix01-xampp.txt, stayhub-room-fix01-http-cases.json, stayhub-room-fix01-dev-before.json, stayhub-room-fix01-dev-after.json, stayhub-room-fix01-final-check.txt.

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| ROOMFIX01-AC1 | PASS | Sáu scalar/file key collisions và nested/multiple trả422, file không bị bỏ trước validation |
| ROOMFIX01-AC2 | PASS | Regression không thêm Room/log success;10HTTP rejected requests không có row persisted |
| ROOMFIX01-AC3 | PASS | JSON/multipart hợp lệ giữ201/eightfields/ACTIVE/decimal, toàn bộ51test cũ và baseline01–05B đạt |
| ROOMFIX01-AC4 | PASS | RED trước fix, GREEN SQLite/MariaDB, multipart HTTP/Sanctum thật chứng minh F01 không còn tái hiện |
| ROOMFIX01-AC5 | PASS | Regression, đúng3file, source/data hash/fingerprint, checklist và cleanup |

**CAT-ROOM-FIX-01: ACCEPTED theo chat ngày2026-11-16. F01: CLOSED sau tái kiểm E16.** Giữ kết luận lịch sử AC2FAIL của E14; kết luận audit hiện hành ở E16. Chưa đóng toàn UC20. Dependencies writer Booking/Maintenance, workflow uniqueness/direct SQL và DELETE Room giữ nguyên như E14.

Bước tái kiểm/chốt kỹ thuật CAT-ROOM-AUDIT đã được người dùng giao riêng và thực hiện tại E16; không tự chuyển module.

### E16 · CAT-ROOM-AUDIT tái kiểm sau FIX-01

**2026-11-16 (Asia/Bangkok) · TECHNICAL PASS / ACCEPTED cho kết quả audit theo xác nhận trên chat. F01 CLOSED.**

Người dùng đã nghiệm thu CAT-ROOM-FIX-01 và giao tái kiểm/chốt audit. Phạm vi giữ nguyên năm luồng UC20 đã triển khai: list, detail, create, update, status. Lượt này chỉ sửa checklist; không sửa source/test, contract, schema, rules hoặc dữ liệu dev. E14 giữ nguyên bằng chứng lịch sử finding201 và AC2FAIL; E16 là kết luận hiện hành sau sửa.

#### Bằng chứng chạy mới

| Cổng | Kết quả thực tế ngày2026-11-16 |
| --- | --- |
| HostRoomListTest — XAMPP MariaDB | 14 tests/70 assertions PASS |
| HostRoomDetailTest — XAMPP MariaDB | 15 tests/69 assertions PASS |
| HostRoomCreateTest — XAMPP MariaDB | 60 tests/271 assertions PASS, gồm regression F01 |
| HostRoomUpdateTest — XAMPP MariaDB | 55 tests/288 assertions PASS |
| HostRoomStatusTest — XAMPP MariaDB | 177 tests/505 assertions PASS |
| Tổng focused MariaDB10.4.32 | **321 tests/1.203 assertions PASS**, cả5command exit0 |
| Catalog SQLite qua PHP XAMPP | **534 tests/2.602 assertions PASS**, exit0 |
| Backend full SQLite qua PHP XAMPP | **1.165 tests/6.668 assertions PASS**, exit0; không gọi là full MySQL |
| HTTP multipart/Sanctum/CSRF thật | **10/10case422**, DB/list xác nhận không tạo Room:6collision keys, photo, file-only name, nested name[child], multiple name[] |
| Positive controls | React JSON create201 và multipart không file201; exact8field, ACTIVE, trim/null, RoomType/decimal99.90 giữ nguyên |
| React/API regression chạy mới | Guards401/403/404/419; duplicate409/inline422/empty type; double submit mộtPOST; committed response bị fault502 có kiểm soát, GET reconcile không autoPOST, explicit replay409; viewport1440/768/390 đạt |
| Create transaction InnoDB chạy mới | Outer rollback/commit và success log sau real commit đạt |
| Baseline/data/cleanup | 656path baseline đối chiếu, chỉ checklist đổi; metadata/fingerprint10bảng dev trước/sau khớp;5schema focused và1schema runtime owned đã dọn |

Focused suite giữ nguyên prefix/biến guard từng class, SELECT DATABASE và xác nhận rooms/bookings/maintenances thực sự được migrate trên từng schema riêng. Dùng DB3306 XAMPP, origin5173, backend tạm8157/Vite5174/CDP9231; giữ service gốc3306/8000/5173 và PID21044/11996/11944. Schema fixture runtime tổng hợp, storage/Edge/process tạm đã cleanup; không seed hoặc migrate database dev.

#### Bằng chứng kế thừa, không gọi là chạy mới

- E15: Node154 PASS, lint/build exit0; Frontend không đổi hash. Còn3FastRefresh warnings nền, warning chunk lớn và build timing diagnostic.
- E14: rà kiến trúc/route/mapper/projection, gallery/privacy, UI list/detail/update/status, late response và14race InnoDB có wait thật. Các source liên quan giữ baseline; FIX-01 chỉ đổi cách ghép input create và thêm regression.
- 14race chỉ chứng minh Catalog Service với cooperating harness writers; workflow Booking/Maintenance production chưa tích hợp nên vẫn NOT VERIFIED.
- Không chạy lại Node/lint/build hoặc14race trong lượt tái kiểm này; các gate tương ứng dùng bằng chứng đã ghi ở E14/E15 cùng đối chiếu hash, không cộng lại thành test mới.

#### Checklist chốt audit hiện hành

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CATROOMAUDIT-AC1 | PASS | Phạm vi5API/UI và kiến trúc/contract đã rà E14; hash giữ baseline,5suite MariaDB chạy lại đạt |
| CATROOMAUDIT-AC2 | PASS | F01 không còn tái hiện: regression60/271 và10HTTP multipart422/no write; guards/ownership/privacy đạt theo bằng chứng mới và E14 |
| CATROOMAUDIT-AC3 | PASS | Valid JSON/multipart201 giữ contract/ACTIVE; create outer rollback/commit chạy mới, update/status matrix/no-op đạt suite mới và runtime E14 |
| CATROOMAUDIT-AC4 | PASS | Focused MariaDB321/1203;14race/locks E14 kế thừa trên Service/Repository không đổi, limits writer production ghi riêng |
| CATROOMAUDIT-AC5 | PASS | Create React/API thật, recovery/pending/viewport chạy mới; các luồng UI còn lại kế thừa E14 trên source không đổi |
| CATROOMAUDIT-AC6 | PASS | Năm focused XAMPP + Catalog/full SQLite mới; Node154/lint/build kế thừa E15, engine và thời điểm phân biệt rõ |
| CATROOMAUDIT-AC7 | PASS | Chỉ1checklist đổi, source/data giữ baseline, cleanup đạt; FIX-01 ACCEPTED/F01 CLOSED và acceptance audit tách riêng |

**Kết luận: CAT-ROOM-AUDIT TECHNICAL PASS trong phạm vi5luồng; F01 CLOSED.** Người dùng đã nghiệm thu kết quả tái kiểm trên chat ngày2026-11-16; checkpoint audit được đánh dấu [x]. Không còn finding bắt buộc mở trong phạm vi audit; không đồng nghĩa nghiệm thu toàn bộ UC20.

Giới hạn giữ nguyên: chưa có unique(property_id,room_number) ở DB, uniqueness dựa workflow lock; Booking/Maintenance writers phải tuân thủ lock/recheck và chưa được kiểm chứng workflow production; DELETE Room chưa có và ngoài phạm vi5luồng. Dữ liệu dev2CHECKED_IN/1IN_PROGRESS quá mốc vẫn giữ nguyên. Global diff còn whitespace nền InvoiceService.php:91, file này không đổi; scoped checklist diff-check đạt.

Bằng chứng local trong TEMP: stayhub-room-audit-recheck-mysql.txt và5log class tương ứng; stayhub-room-audit-recheck-catalog.txt; stayhub-room-audit-recheck-full.txt; stayhub-room-audit-recheck-runtime.txt; stayhub-room-audit-recheck-dev-before.json; stayhub-room-audit-recheck-dev-after.json; stayhub-room-audit-recheck-final-check.txt. Scripts tái dùng harness FIX-01 nhưng thực thi mới, caption checkpoint cũ trong log không phải kết quả tái sử dụng.

Task kế tiếp đề xuất: lập Mini Plan kiểm kê Host quản lý ảnh phòng UC21, chờ phạm vi/duyệt riêng. Dừng sau báo cáo lượt này.

### E17 · CLEANUP-ARCH-01 — lịch sử lượt chuyển helper, gate dọn chưa đạt

**2026-11-16 (Asia/Bangkok) · Kết quả lịch sử PARTIAL.** Plan P2 đã được người dùng duyệt; kết quả tái kiểm hiện hành xem E18. Không tự triển khai UC21.

#### Kết quả và phạm vi thực tế

- Di chuyển Http/Requests/Auth/CredentialRules.php sang Validators/Auth/CredentialRules.php, đổi namespace sang App\Validators\Auth.
- AuthValidator và ProfileValidator chỉ đổi đúng import tới lớp mới. Thân helper và2consumer sau reverse-namespace/import khớp file trước task (chuẩn hóa LF/CRLF); không đổi rule/regex/messages/closure/business logic.
- File nguồn cũ không còn, class mới autoload được, class namespace cũ không resolve; không còn old namespace trong app/routes/tests/config/bootstrap.
- Chỉ5đường dẫn được duyệt thay đổi (rename tính2đường dẫn,2consumer và checklist). Không sửa tests, guard, routes/protected count, dependencies/lock, schema, Frontend, ARCHITECTURE hoặc skills.
- **12thư mục app vẫn tồn tại và rỗng**: Mapping; Http/Resources cùng Auth/Catalog/Invoice/Review; Http/Requests cùng Auth/Catalog/Invoice/Profile/Review. Không đánh dấu là đã xóa.

#### Kết quả kiểm chứng chạy mới

| Cổng | Kết quả |
| --- | --- |
| PHP syntax | php -l3file PHP PASS |
| Composer | composer dump-autoload --no-scripts exit0,7045classes; không update/install dependency |
| Autoload/static | Class mới có/class cũ không có; body/reverse-import comparison PASS |
| Route parity |43route entries trước/sau giống nhau, gồm method/URI/middleware; không sửa route count test |
| RegistrationTest — MariaDB10.4.32 | **90tests/501assertions PASS**, exit0; nguyên guard stayhub_auth01c_test_GUID32/STAYHUB_REGISTRATION_TEST_DATABASE; xác nhận migrated users/sessions/migrations; schema đã DROP |
| Auth/Profile focused SQLite | **265tests/1662assertions PASS**, exit0: RegistrationTest, AuthenticationTest, ProfileTest, AvatarTransactionTest |
| Full Backend SQLite | **1165tests/6668assertions PASS**, exit0; không gọi là full MySQL |
| HTTP XAMPP MariaDB thật | **33requests PASS**, origin5173/backend8157, không mock response; chi tiết case/status trong artifact |
| Dev baseline | **55file** dưới datadir stayhub khớp SHA256 trước/sau giai đoạn chuyển helper/test; đây là offline file fingerprints, **không phải SQL row fingerprints** |
| Scope/whitespace |657baseline paths (gồm đích rename chưa có ban đầu); chỉ5đường dẫn thay đổi; scoped checks đạt. Global InvoiceService.php:91 whitespace nền giữ nguyên |
| Frontend | Không đổi source; không chạy Node/lint/build hoặc browser UI mới, HTTP session được kiểm bằng client cookie thật |
| Dọn app dirs | **BLOCKED** bởi automatic approval review, cả lệnh kiểm tra rỗng và danh sách literal không recursive; reason trả về “blocked by policy” |
| Runtime fixture cleanup | Schema registration/runtime và storage runtime đã dọn; backend8157 và isolated MySQL owned đã dừng |
| Datadir server tạm | **BLOCKED cleanup** bởi automatic approval review; datadir còn tại TEMP, không chứa dữ liệu dev |

HTTP kiểm chứng: guest401, CSRF419, account BLOCKED403; register email sai/confirmation thiếu hoặc lệch/password yếu/vượt72byte UTF8/null/whitespace trả422, users fingerprint không đổi; valid register201 đúng message, duplicate422 và throttle429. Login200 với13field user; update profile200 và cấm email422/no-write; password wrong-current/confirmation/UTF8/weak422 không đổi hash; valid change200, phiên hiện tại200/phiên fingerprint cũ401, logout xóa phiên, old-password login422/new-password login200. Probe DB xác nhận đúng1user đăng ký thêm, profile và hashed password đúng, không có row ngoài dự kiến.

#### Môi trường XAMPP và sai khác phải ghi rõ

Đầu lượt không có listener3306/8000/5173. Khởi động MySQL theo my.ini XAMPP hiện hữu thất bại; log xác nhận **Fatal error: Can't open and lock privilege tables: Incorrect file format 'proxies_priv'**. Không sửa/repair/replace bảng quyền hoặc dữ liệu dev, không dùng skip-grant-tables.

Để vẫn có bằng chứng MariaDB, dùng **binary mysql_install_db/mysqld/php của XAMPP**, tạo data directory server riêng trong TEMP và chạy127.0.0.1:3306 khi trống. SQL SELECT @@datadir xác nhận target tạm; focused/runtime dùng schema owned riêng trên server đó. Không mô tả đây là test trên instance/data directory dev bị lỗi. SQL fingerprints dev không thực hiện được;55offline application-data file fingerprints được chụp sau lần startup lỗi, trước chuyển helper, và so sánh sau test.

Đã dừng isolated server owned PID19188 bằng mysqladmin sau xác nhận listener PID/@@datadir và0schema fixture còn lại. Sau đó quan sát MySQL khác PID9884, parent4836, dùng my.ini XAMPP; không thuộc tiến trình test owned nên không dừng/can thiệp. Không khẳng định toàn bộ trạng thái3306 cuối lượt giống ban đầu khi có tiến trình ngoài task khởi động.

Datadir test còn lại do lệnh dọn bị chặn: C:\Users\HOANGN~1\AppData\Local\Temp\stayhub-cleanup01-mysql-f323306ab6e14e7897e3489b8be49e4c. Các schema dữ liệu ứng dụng fixture bên trong đã DROP trước shutdown, không chứa database dev.

#### Checklist lượt triển khai — lịch sử

| AC | Trạng thái | Bằng chứng/việc còn lại |
| --- | --- | --- |
| CLEANUP01-AC1 | PASS | Helper mới/autoload, old namespace/path không còn, body và2imports giữ nguyên ý nghĩa |
| CLEANUP01-AC2 | BLOCKED |12app dirs rỗng chưa xóa vì automatic approval review từ chối; chưa đạt gate này |
| CLEANUP01-AC3 | PASS |90/501 MariaDB và33HTTP thật trên instance XAMPP binary/datadir cô lập; dev instance lỗi được ghi riêng |
| CLEANUP01-AC4 | PASS |265/1662focused,1165/6668full SQLite và43route parity; không nới guard |
| CLEANUP01-AC5 | PARTIAL | Scope/hash và offline dev files đạt; SQL dev fingerprint NOT VERIFIED, datadir server tạm chưa dọn do policy |

**Kết luận lịch sử PARTIAL; kết quả hiện hành xem E18.** Code relocation và regression đạt, nhưng task cleanup chưa hoàn tất. Bước tiếp theo chỉ đề xuất dọn thủ công12thư mục app rỗng và datadir tạm đã dừng, sau đó tái kiểm gate cleanup; xử lý lỗi XAMPP dev là việc môi trường riêng, không tự sửa trong task này. Không commit/push/merge, không triển khai UC21.

Artifacts local trong TEMP: stayhub-cleanup01-baseline.json; stayhub-cleanup01-autoload.txt; stayhub-cleanup01-registration.txt và registration-tests.txt; stayhub-cleanup01-focused.txt; stayhub-cleanup01-full.txt; stayhub-cleanup01-runtime.txt; stayhub-cleanup01-http-cases.json; stayhub-cleanup01-routes-before.json/routes-after.json; stayhub-cleanup01-dev-offline-before.json/dev-offline-after.json; stayhub-cleanup01-mysql-err.txt; stayhub-cleanup01-mysql-datadir.txt; stayhub-cleanup01-final-check.txt. Dev-before.json từ SQL attempt thất bại không được dùng làm evidence PASS.

### E18 · CLEANUP-ARCH-01 tái kiểm sau người dùng dọn thủ công

**2026-11-16 (Asia/Bangkok) · TECHNICAL PASS / ACCEPTED theo xác nhận người dùng trên chat.** Gate dọn thư mục/datadir đã đạt; checkpoint CLEANUP-ARCH-01 được nghiệm thu. UC21 chưa triển khai.

Người dùng gửi transcript PowerShell dọn12thư mục app và datadir test, bảng cuối13dòng ConLai=False. Transcript có lỗi preflight “Đường dẫn datadir test không hợp lệ”; vì dán từng khối vào interactive console, các khối sau vẫn chạy. Không coi toàn bộ script là PASS, không suy đoán nguyên nhân chuẩn hóa path hoặc khẳng định preflight đã đạt. Kết luận dựa vào kiểm tra filesystem/source/dev thực tế bên dưới; không chạy thêm lệnh xóa.

#### Kiểm chứng chạy mới của lượt chốt

| Kiểm tra | Kết quả |
| --- | --- |
|12app dirs | Tất cả không tồn tại: Mapping; Http/Resources cùng Auth/Catalog/Invoice/Review; Http/Requests cùng Auth/Catalog/Invoice/Profile/Review |
| Datadir test | Đúng stayhub-cleanup01-mysql-f323306ab6e14e7897e3489b8be49e4c không còn, kiểm cả path profile ngắn HOANGN~1 và dài HoangNguyen |
| Scope từ baseline triển khai | Chỉ đúng5đường dẫn đã duyệt thay đổi; các path/hash ngoài scope giữ nguyên; không thêm file repo |
| Source lượt tái kiểm | Không đổi helper hoặc consumer; chỉ cập nhật checklist. Không cần chạy lại suite vì chỉ dọn thư mục rỗng/datadir fixture ngoài source |
| Dev baseline |55file dưới E:/App/xampp/mysql/data/stayhub khớp SHA256 với snapshot trước chuyển helper; không thay bằng dữ liệu test |
| PHP syntax |3file helper/AuthValidator/ProfileValidator PASS |
| Autoload | Class mới App\Validators\Auth\CredentialRules có, old namespace không có sau dọn |
| Route parity |43entries chạy mới khớp trước cleanup, giữ method/URI/middleware |
| Runtime processes | Không thấy listener3306/8000/5173/8157/5174/9231 ở thời điểm kiểm; không khởi động/dừng service trong lượt chốt |
| SQL dev | Kết nối3306 bị refused, **NOT VERIFIED**; không repair hoặc khởi động lại XAMPP trong lượt này |

#### Bằng chứng kế thừa nguyên source E17

- RegistrationTest **90tests/501assertions PASS** trên MariaDB10.4.32 bằng binary XAMPP/datadir server cô lập, schema đúng guard đã DROP.
- Auth/Profile focused **265tests/1662assertions PASS**, full Backend **1165tests/6668assertions PASS**, đều SQLite; không gọi là full MySQL.
- **33HTTP requests PASS** qua Laravel/Sanctum/CSRF thật trên MariaDB fixture; runtime schema/storage đã dọn, server owned đã dừng ở E17.
- Helper body/reverse-import comparison và unchanged source hashes giữ nguyên. Frontend không đổi, không chạy mới Node/lint/build/browser UI.
- Phân biệt rõ các lượt chạy: test counts trên là E17 kế thừa, syntax/autoload/route/hash/filesystem là E18 mới; không báo là đã chạy lại full suite.

#### Checklist chốt kỹ thuật

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CLEANUP01-AC1 | PASS | Helper/import unchanged; fresh syntax/autoload đạt, vị trí cũ không còn |
| CLEANUP01-AC2 | PASS |12app dirs đã được người dùng dọn; filesystem xác nhận trực tiếp đều không tồn tại |
| CLEANUP01-AC3 | PASS | MariaDB90/501 và33HTTP E17 trên source không đổi; engine/isolated datadir được ghi rõ |
| CLEANUP01-AC4 | PASS | SQLite265/1662 và1165/6668 E17 kế thừa; route43entries mới khớp, guard không đổi |
| CLEANUP01-AC5 | PASS | Đúng5đường dẫn, hash ngoài scope/dev55file giữ nguyên, app dirs/datadir không còn; nguồn SQL dev vẫn NOT VERIFIED và không được nâng thành PASS |

**Kết luận TECHNICAL PASS / ACCEPTED trong phạm vi CLEANUP-ARCH-01 theo chat ngày2026-11-16.** Phần bảo toàn dữ liệu dev sử dụng bằng chứng55offline file fingerprints; giới hạn SQL dev và lỗi bảng quyền XAMPP là vấn đề môi trường riêng, không khẳng định database dev đã hoạt động hoặc từng query SQL đã được xác nhận. Không còn vật cản cleanup của E17. Không sửa source, không commit/push/merge và chưa triển khai UC21.

Chỉ đề xuất bước tiếp theo: người dùng nghiệm thu kết quả cleanup, sau đó đóng gói baseline để bàn giao theo task riêng. Không tự sửa XAMPP hoặc triển khai module.

Artifacts mới local TEMP: stayhub-cleanup01-close-baseline.json; stayhub-cleanup01-close-routes.json; stayhub-cleanup01-close-dev-offline.json; stayhub-cleanup01-close-final-check.txt. Transcript người dùng lưu trong attachment d63cd5aa-2858-4a1d-99a4-8551403798a7/Pasted text.txt; artifacts E17 giữ nguyên để tra cứu lịch sử.

### E19 · HANDOVER-BASELINE-01 — gói baseline bàn giao TV1/TV2

**2026-11-16 (Asia/Bangkok) · TECHNICAL PASS / PACKAGE VERIFIED / OWNER ACCEPTANCE PENDING. MERGE APPROVAL PENDING.** Người dùng duyệt P3 đúng5đường dẫn; task chỉ chuẩn bị source package/docs và kiểm tra local, không tạo PR thật, stage/commit/push/merge hoặc triển khai UC21.

#### Phạm vi hoàn thành

- Tạo docs/TV3/BASELINE_HANDOVER.md và docs/TV3/BASELINE_PR_CHECKLIST.md: scope đã nghiệm thu, TV1/TV2 nhận tiếp,10UC hiện hữu/8UC TODO, contract và dependencies, hướng dẫn nhận gói, các gate trước merge và draft PR local.
- ARCHITECTURE.md chỉ sửa đúng ô Auth/Profile trong bảng14.2 theo Validator/typed Request DTO/Repository hiện hành.
- InvoiceService.php chỉ xóa4space trên dòng trống91, so sánh trước/sau không có thay đổi logic.
- Checklist này ghi results/evidence; các source ngoài5đường dẫn giữ hash baseline. Không đổi tests/guard/routes/env/dependencies/Frontend.

#### Kết quả mới của lượt bàn giao

| Gate | Kết quả thực tế |
| --- | --- |
| Full Backend SQLite | **1165tests/6668assertions PASS**, exit0 |
| RegistrationTest MariaDB |90/501PASS |
| HostRoomListTest MariaDB |14/70PASS |
| HostRoomDetailTest MariaDB |15/69PASS |
| HostRoomCreateTest MariaDB |60/271PASS |
| HostRoomUpdateTest MariaDB |55/288PASS |
| HostRoomStatusTest MariaDB |177/505PASS |
| Tổng targeted XAMPP | **411tests/1704assertions PASS**,6suite/schema riêng, SELECT DATABASE guard/migration proof, không nới guard |
| Frontend Node22.23.4 | **154PASS**, node --experimental-test-module-mocks --test tests/*.test.js |
| Node invocation đầu |76pass/4fail do thiếu cờ module mocks; giữ log failed invocation, không đổi code/test; rerun đúng cờ PASS |
| Lint/build | Exit0;3FastRefresh warnings nền; chunk>500kB và plugin timing diagnostic còn nguyên |
| PHP syntax/autoload | **215PHPfiles PASS**, adapter/helper mới resolve, old namespaces không còn |
| Route/skills/links |43route entries giữ nguyên;5skill frontmatter và links của docs được rà đạt |
| Global whitespace | git diff --check exit0 sau đúng1dòng whitespace được duyệt |
| Target dev | Fetch thành công origin/dev951f62e; merge-base cùng target, ahead2/behind0; target-changed paths0/overlap0, không gọi đây là mergeability proof |
| Dev/index/HEAD |55offline dev file fingerprints giữ nguyên; HEAD7dac999 và staged content giữ nguyên, git diff --cached rỗng |
| Package | **643file source** trong ZIP, gồm117untrackedfile sau thêm2docs;14deletedpaths không xuất hiện lại;1envtracked loại rõ khỏi snapshot |

XAMPP dùng binary E:/App/xampp/mysql/bin/mysqld.exe10.4.32 tại127.0.0.1:3306 với datadir server tạm riêng khi port trống; không dùng/repair instance dev lỗi bảng quyền. Cả6schema, server và datadir tạm đã cleanup. Không gọi targeted suites là full MySQL; SQL dev vẫn NOT VERIFIED, bảo toàn dev dùng55offline hashes.

Git index content541entries vẫn giống HEAD và staged diff rỗng trước/sau. Git refresh stat-cache có thể thay bytes/index hash trong khi git status/diff kiểm tra; không có git add hoặc thay staged blobs. Không restore/reset index để làm giả binary hash không đổi.

#### Gói và exclusions

Artifact root: .agent-workspace/handover-baseline-3dbd49fe4a3d4410882f7c7efad786ed/, đã ignored.

- source.zip: snapshot643file hiện hữu cuối lượt, không chỉ HEAD archive/patch.
- manifest.json: relative path/domain/action/tracked/bytes/SHA256, ZIP hash và exclusions.
- deleted-paths.txt:14file tracked đã xóa.
- git-state.json: HEAD/fetched dev/merge-base/working tree và các gate PR còn mở.
- checks/: test/static/inventory/package verification và baseline data hashes; không chứa raw dev rows/secrets.
- Extract vào thư mục tạm riêng, kiểm path/count/bytes/SHA256/file thừa/deletions rồi dọn. Không ghi đè checkout hoặc database dev.

**Exclusion công khai:** frontend/.env đã track sẵn trên HEAD và fetched dev, không đổi và chỉ có3key cấu hình Vite công khai. Pattern scan không thấy token/private-key; ZIP vẫn loại file env thật, giữ .env.example sau rà. Không tự git rm/sửa ignore ngoài scope. Các env thật/secrets/dependencies/build/upload/datadir/local artifacts không có trong ZIP. Pattern scan không được coi là bảo đảm phát hiện mọi loại secret.

#### Checklist task hiện hành

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| HANDOVER01-AC1 | PASS | Inventory tracked/untracked/deleted, HEAD/target/merge-base đầy đủ, nhận đủ lớp mới và không phục hồi lớp cũ |
| HANDOVER01-AC2 | PASS | ZIP643entries, extract/hash/size/path/exclusions/deletions khớp manifest; envtracked được khai báo loại rõ |
| HANDOVER01-AC3 | PASS |2docs bàn giao/PR phân completed refactor, owner,10UC hiện hữu/8TODO và UC21 chưa triển khai |
| HANDOVER01-AC4 | PASS |2sửa nhỏ đúng giới hạn, global whitespace/syntax/autoload/route/skills/links đạt |
| HANDOVER01-AC5 | PASS | Full SQLite/Node/lint/build và6suite XAMPP chạy mới, failed invocation/engine/datadir/limits ghi rõ |
| HANDOVER01-AC6 | PASS | Đúng5path, source/dev hashes và index content/HEAD giữ baseline, tài nguyên owned dọn; không stage/commit/push/merge |

**Kết luận PACKAGE VERIFIED, chờ người dùng nghiệm thu gói.** Chưa có GitHub mergeability/CI/review trên commit cuối hoặc approval merge. Nếu source/target đổi thì regenerate/reverify package và kiểm lại PR/integration gates theo BASELINE_PR_CHECKLIST.md. Browser/HTTP/14race cũ chỉ kế thừa trên code logic không đổi; không gọi là lượt runtime mới.

Bước tiếp theo đề xuất: người dùng kiểm gói/tài liệu và nghiệm thu; thao tác commit/push/tạo PR/merge cần task/ủy quyền riêng. Không tự sửa hygiene frontend/.env hoặc triển khai UC21.

## P1 · Mini Plan CAT-ROOM-IMG-00 — kiểm kê UC21

**2026-11-16 (Asia/Bangkok) · PLANNED / APPROVAL PENDING. Chưa thực hiện task.** Theo chốt bàn giao, UC21 là phần tiếp theo của TV1; giữ kế hoạch tham khảo, không tự triển khai.

Lượt chuẩn bị chỉ đọc source và ghi nhận nghiệm thu CAT-ROOM-AUDIT. UC21 thuộc TV1, tiếp tục theo phạm vi liên module được giao. Mục tiêu sau duyệt là kiểm kê API/UI/schema/storage/test và khoảng trống, không tạo API/UI quản lý ảnh.

### Hiện trạng đọc source để lập kế hoạch

- RoomImage và bảng room_images đã có id, room_id, image_path500, is_primary mặc định false, timestamps; FK rooms cascade. Chưa thấy ràng buộc chỉ một ảnh chính mỗi Room trong migration này.
- GET /api/host/rooms/{room} qua HostRoomQueryService/Repository/CatalogMapper/Symfony ObjectMapper trả images là list URL string, không trả image ID/is_primary. Repository sort is_primary DESC, id ASC; PublicImageUrl lọc URL/path.
- React /host/rooms/:roomId có gallery và empty state; hostRoomApi/service/mapper có luồng đọc, chưa có thao tác ảnh riêng. Public GET /api/public/properties/{property} cũng có rooms[].images.
- Source catalog routes chưa có RoomImage list/upload/primary/delete API riêng. Room create/update không nhận file; không dùng làm API upload ảnh.
- HostPropertyImageController/Service/Manager quản lý ảnh cơ sở qua property_images; là nguồn tham khảo, không phải UC21 đã triển khai.
- Các nhận định trên là source inspection chuẩn bị plan, chưa thay thế kiểm chứng route/runtime/XAMPP của task.

### Phạm vi file sau khi duyệt — đúng2file tài liệu

| File | Hành động | Nội dung |
| --- | --- | --- |
| docs/TV1/UC21-ROOM-IMAGE-INVENTORY.md | Tạo | Ma trận API/UI/schema/storage/test hiện hữu; contract và ownership; gap/open decisions; bằng chứng thực thi và checkpoint đề xuất |
| docs/TV3/TV3_CHECKLIST.md | Sửa | Cập nhật tiến độ/bằng chứng/AC và acceptance, giữ lịch sử audit |

Source, migrations/seeders, .env, router, shared Axios, ARCHITECTURE và skills chỉ đọc. Không thêm test repo hoặc sửa guard. Harness/log/fixture tạm ngoài repo, chỉ dọn tài nguyên owned.

### Checklist/AC chờ thực hiện

| AC | Trạng thái đầu | Bằng chứng cần có sau duyệt |
| --- | --- | --- |
| ROOMIMG00-AC1 | NOT RUN | route:list đối chiếu code và UI navigation; phân biệt RoomImage/PropertyImage, endpoint thiếu không gọi là đã có |
| ROOMIMG00-AC2 | NOT RUN | Schema XAMPP thực tế: columns/default/index/FK/engine; thống kê tổng hợp path/primary/orphan, không đổi dữ liệu dev |
| ROOMIMG00-AC3 | NOT RUN | Contract gallery Host/Public, filter/order/empty/privacy; vai trò/account/ownership và response lỗi thực tế |
| ROOMIMG00-AC4 | NOT RUN | React gallery/API thật, PNG local qua isolated storage, empty/error/retry và viewport1440/768/390; dữ liệu fixture được ghi rõ |
| ROOMIMG00-AC5 | NOT RUN | Gap matrix upload/primary/delete, limit/MIME/size/count, cleanup/transaction/shared path/legacy URL và quyết định chưa chốt |
| ROOMIMG00-AC6 | NOT RUN | Focused tests XAMPP đúng guard, regression SQLite/Node/lint/build tách engine và kết quả thật; không báo PASS trước khi chạy |
| ROOMIMG00-AC7 | NOT RUN | Chỉ2file tài liệu đổi, baseline/dev fingerprints giữ nguyên, schema/storage/process owned dọn; báo cáo đúng7phần |

### Kiểm chứng dự kiến sau duyệt

- Giữ DB XAMPP127.0.0.1:3306, backend8000/frontend5173 hiện hữu; runtime fixture dùng backend8157/Vite5174/CDP9231 nếu trống, giữ origin5173. Nếu cổng bận không dừng service của người dùng.
- MySQL read-only kiểm schema/data aggregate trước/sau. Runtime dùng schema stayhub_roomimg00_GUID32 riêng và storage tạm; migrate chỉ schema owned, không seed/migrate/refresh dev.
- Chạy HostRoomDetailTest trên MariaDB với schema stayhub_room02_test_GUID32 và STAYHUB_ROOM_DETAIL_TEST_DATABASE; HostPropertyImagesTest (control ảnh cơ sở) dùng schema stayhub_mgmt06a_test_GUID32 và STAYHUB_PROPERTY_IMAGE_TEST_DATABASE. Mỗi suite/schema riêng; APP_ENV=testing, DB_URL rỗng, cache/session array, SELECT DATABASE xác nhận, giữ nguyên guard.
- Catalog và full Backend chạy cấu hình SQLite :memory: hiện có bằng PHP XAMPP, không gọi là full MySQL. Frontend node --test tests/*.test.js, npm run lint, npm run build.
- HTTP/React thật: owner200, guest401, Customer/Admin403, Host khác/missing404 và account không active theo middleware thực tế; projection/order/empty/invalid-private paths, không lộ ID/path nội bộ ngoài contract. Kiểm content ảnh PNG local200/image MIME; URL ngoài chỉ kiểm projection, không phụ thuộc provider bên ngoài.
- Không upload/chọn chính/xóa qua API RoomImage chưa có; không bịa status contract cho mutation. Không chạy lại toàn bộ14race UC20, không sửa F01 hoặc dependency Booking/Maintenance.
- Hash source và fingerprint10bảng dev trước/sau; cleanup chỉ schema/path/process được tạo bởi task. Global whitespace nền InvoiceService.php:91 ghi riêng nếu còn, không sửa ngoài scope.

### Kết quả và thứ tự dự kiến

1. Xác nhận baseline/route/schema và ma trận API/UI.
2. Chạy focused XAMPP, fixture gallery/runtime và regression.
3. Ghi inventory, gap/decision matrix, thứ tự checkpoint đề xuất.
4. Self-review2file, cleanup và báo cáo Task Result7phần rồi dừng.

Ước lượng60–90phút. Quy tắc chưa chốt: endpoint/payload ảnh riêng, có hiển thị image ID/primary trong contract quản lý không, MIME/size/count, primary khi upload/xóa, trạng thái Room/Property nào cho mutation, quản lý path/local/URL và cleanup sau commit/rollback. Không mặc nhiên dùng giới hạn ảnh cơ sở hoặc tự chốt nghiệp vụ. Finding cần sửa phải mở Mini Plan riêng; UC21 vẫn chưa triển khai/chưa nghiệm thu.

## P2 · Mini Plan CLEANUP-ARCH-01 — dọn trước bàn giao

**2026-11-16 (Asia/Bangkok) · PLAN APPROVED / TECHNICAL PASS / ACCEPTED sau tái kiểm E18.** Người dùng duyệt đúng5đường dẫn trên chat; helper đã chuyển, thư mục/datadir đã được người dùng dọn và kiểm tra lại. E17 giữ lịch sử bị chặn. Các AC NOT RUN dưới đây là trạng thái đầu của kế hoạch. UC21 chưa triển khai.

### 1. Mục tiêu và baseline

Dọn các thư mục app rỗng còn lại sau refactor và đưa CredentialRules (helper dùng chung, không phải Form Request) về Validators/Auth. Nhánh refactor/stayhub-layered-architecture, HEAD7dac999 cùng toàn bộ working tree đã nghiệm thu là baseline. Đây là task cleanup bổ sung, không mở lại các checkpoint refactor đã hoàn thành.

Source inspection: Mapping và Http/Resources hiện rỗng; Http/Requests chỉ còn Auth/CredentialRules.php. Hai consumer trực tiếp là AuthValidator và ProfileValidator. Không tìm thấy namespace13lớp cũ trong source/routes/config/tests/database ở lượt rà trước. Kiểm tra lại ngay trước khi thực hiện, không dựa duy nhất vào kết quả cũ.

### 2. Cách thực hiện và ranh giới

Di chuyển nguyên thân CredentialRules, chỉ đổi namespace sang App\Validators\Auth; cập nhật đúng2import. Không thay email regex, password rules/byte limits/closure, messages hoặc dependency RegistrationService. So sánh hash nội dung sau chuẩn hóa namespace và reverse-import để chứng minh thay đổi chỉ là vị trí.

Sau khi chuyển, kiểm tra lại file ẩn và file con; chỉ dọn thư mục rỗng được chỉ định. Nếu có file mới ngoài baseline trong thư mục đích dọn, không xóa file đó hoặc mở rộng scope. Xác minh đường dẫn tuyệt đối nằm trong backend/app; dọn từ thư mục con lên, không xóa đệ quy nội dung chưa kiểm tra. Không sửa code tương thích Resource trong Common, không xóa model nền.

### 3. Contract và dữ liệu giữ nguyên

Giữ nguyên login/register/profile/password/avatar contracts, HTTP status/field errors/messages/thứ tự validation; session/CSRF/account/ownership/throttle, hashing và transaction. Không thêm schema/seed, alias cũ hoặc dependency mới. Không đổi ARCHITECTURE: quy tắc Validators/domain hiện có đã bao phủ helper này; Requests trong sơ đồ là vị trí Form Request nếu module khác cần, không phải thư mục bắt buộc phải hiện diện trên disk.

### 4. Test và kiểm chứng sau duyệt

- Static: rg old namespace, class/autoload new namespace; php -l trên3file PHP sau chuyển; so sánh nội dung chỉ namespace/import đổi; route:list và signature method/URI/middleware giữ nguyên. composer dump-autoload --no-scripts để cập nhật generated autoload, không composer update/install hoặc sửa lock/dependency.
- XAMPP: chạy toàn RegistrationTest trên schema stayhub_auth01c_test_GUID32, guard STAYHUB_REGISTRATION_TEST_DATABASE giữ nguyên; APP_ENV=testing, DB_CONNECTION=mysql,127.0.0.1:3306,DB_URL rỗng,cache/session array, SELECT DATABASE và actual migration proof trước khi báo PASS.
- Runtime Laravel thật trên schema stayhub_cleanup01_GUID32/storage owned: Sanctum/session/CSRF, đăng ký hợp lệ/duplicate email/email sai, password thiếu xác nhận/không đủ độ mạnh/vượt72byte UTF8/null/whitespace; login, profile update, đổi password với current-password sai/password_confirmation sai/hợp lệ; verify persist/no-write, old-password fail/new-password login thành công và hành vi session baseline. Không log password/cookie/token.
- Giữ3306/8000/5173 hiện hữu; runtime tạm backend8157/Vite5174/CDP9231 nếu cần và còn trống, origin5173. Không dừng service người dùng; không đổi frontend source.
- Focused SQLite :memory: RegistrationTest, AuthenticationTest, ProfileTest, AvatarTransactionTest; sau đó full php artisan test theo phpunit.xml hiện có. Authentication/Profile/AvatarTransaction bị guard SQLite-only, không sửa guard và không báo là full MySQL.
- Frontend không đổi nên không bắt buộc chạy lại Node/lint/build; nếu browser smoke dùng UI hiện hữu thì ghi đúng case chạy, không coi các test Frontend cũ là lượt mới.
- Kiểm fingerprint dữ liệu dev trước/sau (gồm users và sessions nếu có cùng các bảng baseline liên quan), scope/hash/path, links/whitespace; dọn đúng schema/storage/cache/session/process fixture. Không migrate/seed/refresh database dev.

### 5. Phạm vi file — đúng5đường dẫn,4file logic

| File tính từ repository root | Hành động | Lý do |
| --- | --- | --- |
| backend/app/Http/Requests/Auth/CredentialRules.php | Di chuyển khỏi vị trí cũ | Không còn thuộc Http/Requests |
| backend/app/Validators/Auth/CredentialRules.php | Nhận file chuyển, đổi namespace | Helper quy tắc credentials của domain Auth, Profile tái dùng |
| backend/app/Validators/Auth/AuthValidator.php | Sửa đúng import | Tham chiếu namespace mới |
| backend/app/Validators/Profile/ProfileValidator.php | Sửa đúng import | Tham chiếu namespace mới |
| docs/TV3/TV3_CHECKLIST.md | Sửa | Ghi kế hoạch/kết quả/test/AC/acceptance |

Thư mục vật lý được phép dọn sau khi xác nhận rỗng:

- backend/app/Mapping.
- backend/app/Http/Resources và4thư mục con Auth/Catalog/Invoice/Review.
- backend/app/Http/Requests và5thư mục con Auth/Catalog/Invoice/Profile/Review; Auth chỉ rỗng sau khi chuyển helper.

Tổng12thư mục vật lý hiện hữu (3root+9con), không phải12file. Git không theo dõi thư mục rỗng;13file cũ trạng thái D là baseline, không xóa thêm hoặc báo là thay đổi mới của task này. Temp harness/log và generated autoload chỉ là artifact kiểm chứng; không đưa vào PR.

Không sửa test repo, routes, Controller/Service/DTO/Mapper/Model, bootstrap, .env, schema/seeders, Common, ARCHITECTURE, skills hoặc Frontend. Nếu cần thay ngoài5đường dẫn, báo scope cụ thể trước khi sửa.

### 6. Checklist nghiệm thu

| AC | Trạng thái đầu | Bằng chứng sau thực hiện |
| --- | --- | --- |
| CLEANUP01-AC1 | NOT RUN | Helper mới autoload được, thân lớp không đổi; old class/path không còn;2consumer đúng import |
| CLEANUP01-AC2 | NOT RUN | Chỉ dọn12thư mục rỗng cho phép, path absolute đã kiểm; Controllers/Middleware và file nền giữ nguyên |
| CLEANUP01-AC3 | NOT RUN | Registration suite MariaDB/HTTP XAMPP chứng minh email/password/contract/persistence/session không đổi |
| CLEANUP01-AC4 | NOT RUN | Focused SQLite/full suite và route parity đạt; không nới DB guard hoặc đổi protected-route count |
| CLEANUP01-AC5 | NOT RUN | Đúng5đường dẫn, hash ngoài scope/dev fingerprints giữ nguyên, cleanup tài nguyên owned và checklist chính xác |

### 7. Thứ tự và thời lượng

Chụp baseline/source/data/routes → chuyển helper và2import → dọn đúng thư mục rỗng → lint/autoload/static → XAMPP focused/runtime → SQLite focused/full → scope/data/cleanup → Task Result7phần và dừng. Ước lượng45–90phút.

Rủi ro chính là bỏ sót consumer, autoload cũ và xóa nhầm khi thư mục xuất hiện file mới. Gặp failure thì báo kết quả thực tế, không sửa test/contract để đạt PASS. Global whitespace nền InvoiceService.php:91 được ghi riêng nếu còn; không sửa ngoài scope. Không commit/push/merge và không triển khai UC21.

## P3 · Mini Plan HANDOVER-BASELINE-01 — đóng gói bàn giao và kiểm tra trước PR dev

**2026-11-16 (Asia/Bangkok) · PLAN APPROVED / TECHNICAL PASS / PACKAGE VERIFIED E19.** Người dùng duyệt đúng5đường dẫn trên chat. Trạng thái NOT RUN ở bảng kế hoạch là trạng thái đầu; kết quả hiện hành xem E19. Chưa stage/commit/push/merge hoặc triển khai UC21.

### 1. Mục tiêu và hiện trạng

Tạo bản snapshot source đầy đủ của baseline đã nghiệm thu, manifest/hash/deletions, tài liệu bàn giao TV1/TV2 và draft nội dung PR. Phân biệt gói đã kiểm chứng với PR đã được phép merge.

Tại lượt lập plan: nhánh refactor/stayhub-layered-architecture, HEAD7dac999;188đường dẫn Git thay đổi mở rộng gồm59modified/14deleted/115untracked. Local dev và origin/dev cùng đứng951f62e (metadata local); ahead/behind hiện0/2theo dev...HEAD. Chưa fetch nên không gọi đây là trạng thái dev mới nhất trên server. Không dùng git archive HEAD hoặc diff tracked đơn lẻ làm gói duy nhất vì bỏ sót115file untracked.

### 2. Cách đóng gói và ranh giới

- Chốt inventory từ git ls-files + untracked không ignored, phân nhóm domain, action added/modified/deleted và SHA256 của từng file hiện hữu. Rà tên/nội dung nhạy cảm mà không in giá trị secret ra log.
- Source ZIP là snapshot file hiện hữu thuộc allowlist đã rà, gồm file untracked cần chạy và lockfiles; không phải chỉ patch. Có manifest, deleted-paths và mô tả base/target/working tree đi cùng.
- Loại .git, .env thật, credentials/auth.json/private keys, vendor, node_modules, builds, public/storage link, upload, DB dumps/datadir, TEMP harness/log và artifact local. .env.example chỉ được đưa vào khi đã kiểm không chứa secret thật.
- Artifact đặt trong .agent-workspace/handover-baseline-<GUID32>/ đã ignored: source.zip, manifest.json, deleted-paths.txt, git-state.json và checks/log đã lọc. Không thêm archive/binary/generated artifact vào PR. Manifest/hash ZIP lưu ngoài ZIP để tránh tự hash chính nó.
- Extract vào thư mục tạm riêng rồi đối chiếu path/hash/count, absence các file deleted và blacklist; không ghi đè checkout/DB dev, không áp patch vào working tree để thử.
- Không git add vào index thật, stash/reset/checkout/rebase/commit/push/merge. Không gửi thông báo/email cho team. Nội dung bàn giao và PR chỉ tạo draft local.

### 3. Baseline/contract và hai sửa nhỏ trước gói

1. InvoiceService.php:91 có một dòng trống chứa4space; chỉ bỏ whitespace, không đổi logic/cấu trúc transaction. So sánh diff bỏ qua whitespace phải không có thay đổi mới.
2. ARCHITECTURE.md14.2 đang ghi Profile mutations còn Form Request, trái baseline đã nghiệm thu; chỉ sửa ô ví dụ Auth/Profile thành Validator + typed Request DTO + Repository. Không ghi tiến độ/count/checklist vào kiến trúc hoặc viết lại tài liệu.

Các luồng nghiệp vụ/contract đã nghiệm thu khác giữ nguyên. Manifest ghi rõ phạm vi Invoice/Review/Auth/Profile/Catalog,10UC TV3 đã có luồng chính so với8UC TODO; phần bàn giao TV1/TV2, QA/tích hợp Booking và giới hạn XAMPP/uniqueness/writers giữ nguyên. UC21 là chưa thực hiện, không đóng như đã tích hợp.

### 4. Cổng kiểm tra trước PR/merge

**Chạy được trong task sau duyệt, chưa commit:**
- Git inventory đầy đủ tracked/untracked/deleted; secret/artifact exclusion; git diff --check toàn working tree phải đạt sau sửa đúng whitespace. Syntax/autoload/namespace cũ, route count/signature, links và kỹ năng frontmatter nếu nằm trong gói.
- Full Backend theo phpunit.xml SQLite :memory:; Frontend node --test tests/*.test.js, npm run lint, npm run build. Ghi cảnh báo nền thực tế, không tự sửa ngoài scope nếu phát sinh failure.
- XAMPP targeted: RegistrationTest và5HostRoom{List,Detail,Create,Update,Status}Test, mỗi class/schema/guard riêng hiện có; APP_ENV testing, DB_URL rỗng/cache-session array, SELECT DATABASE và migration proof; không nới guards. Không gọi đây là full MySQL.
- Nếu instance XAMPP dev còn lỗi mysql.proxies_priv: không repair/replace/skip-grants; dùng binary XAMPP với datadir server tạm riêng khi cổng3306 trống. Nếu3306 đang được instance khác giữ nhưng không dùng được thì ghi BLOCKED, không dừng service để giành cổng. Báo đúng engine/datadir thay thế và không claim SQL dev PASS.
- Runtime14race/HTTP/browser cũ chỉ kế thừa nếu source tương ứng giữ hash; không báo là lượt mới. Test lại runtime khi có thay đổi/failure mới có liên quan; không mở rộng workflow ngoài baseline.
- Thu thập trạng thái branch/target và có thể git fetch origin dev sau duyệt; không checkout/merge. Rà đường dẫn trùng thay đổi từ merge-base trên target dev mới với188path hiện tại, đánh dấu conflict risks. So sánh path/diff không chứng minh GitHub mergeable.
- Verify ZIP bằng extract/hash, nhận đủ mapper/DTO/validators và xóa đúng lớp cũ; fingerprint dev trước/sau (offline nếu SQL không khả dụng), cleanup tài nguyên owned và scope.

**Gate cuối trước merge, chưa được phép thực hiện trong task này:**
- Người dùng commit/push đúng inventory đã duyệt, PR target dev; đối chiếu PR diff thực tế với manifest.
- Xác nhận GitHub mergeability/conflicts, CI bắt buộc và review/approval team trên commit cuối.
- Nếu dev thay đổi/resolve conflict hoặc code PR khác snapshot: chạy lại checks và integration cần thiết trên code kết hợp trước merge.
- Merge rehearsal/CI trên kết quả tích hợp phải giữ ownership/session/file cleanup/route và hợp đồng Booking/Maintenance. Chưa tích hợp module TV2 thì ghi dependency mở, không báo availability/workflow production đã được xác nhận.
- Chỉ owner merge sau khi duyệt riêng. Task này chỉ có thể chốt PACKAGE VERIFIED khi gate local/package đạt; MERGE APPROVAL vẫn PENDING.

### 5. Phạm vi file — đúng5đường dẫn repo sau duyệt

| File | Hành động | Nội dung giới hạn |
| --- | --- | --- |
| docs/TV3/BASELINE_HANDOVER.md | Tạo | Bản bàn giao TV1/TV2, phạm vi nghiệm thu/cấu trúc/contract, việc owner tiếp tục, cách nhận snapshot và restore source |
| docs/TV3/BASELINE_PR_CHECKLIST.md | Tạo | Gate local/package/PR/merge, kết quả engine/date, exclusions, dependencies và draft title/body PR |
| docs/TV3/TV3_CHECKLIST.md | Sửa | Task/result/evidence/acceptance, không tự đóng các UC chưa làm |
| ARCHITECTURE.md | Sửa đúng1ô bảng14.2 | Bỏ mô tả Profile Form Request lỗi thời, mô tả lớp thực tế đã nghiệm thu; không ghi tiến độ |
| backend/app/Services/Invoice/InvoiceService.php | Sửa whitespace đúng1dòng91 | Xóa4space trên dòng trống, không đổi logic |

Không sửa .gitignore, env/dependencies/lock, test/guard, routes/protected count, bootstrap, Frontend hoặc code module khác. Artifact/checks trong .agent-workspace ignored không phải source thêm vào PR. Nếu inventory phát hiện file nhạy cảm tracked hoặc failure cần thay ngoài5đường dẫn, ghi blocker và đề xuất scope cụ thể; không tự sửa/xóa/loại âm thầm file đang cần chạy.

### 6. Checklist nghiệm thu

| AC | Trạng thái đầu | Bằng chứng cần có |
| --- | --- | --- |
| HANDOVER01-AC1 | NOT RUN | Inventory đầy đủ tracked/untracked/deleted và HEAD/target rõ; không bỏ lớp mới hoặc đưa lại lớp cũ |
| HANDOVER01-AC2 | NOT RUN | Source ZIP/manifest/extract SHA256 khớp, không chứa secrets/env/build/dependencies/data local |
| HANDOVER01-AC3 | NOT RUN | Tài liệu bàn giao/PR phân rõ completed refactor, TV1/TV2,10UC hiện hữu/8TODO, integration và UC21 chưa triển khai |
| HANDOVER01-AC4 | NOT RUN | Whitespace/doc correction đúng giới hạn, diff-check/syntax/autoload/routes/links đạt |
| HANDOVER01-AC5 | NOT RUN | Full SQLite/Frontend và targeted XAMPP có kết quả mới, guard/engine/datadir/limits minh bạch |
| HANDOVER01-AC6 | NOT RUN | Đúng5file scope, data/source baseline và cleanup; không stage/commit/push/merge hoặc tự đóng gate PR tương lai |

### 7. Thứ tự, rủi ro và thời lượng

Chụp baseline/target → rà inventory và exclusions → sửa đúng whitespace/ô doc, viết2tài liệu → chạy gates → chốt docs/checklist → tạo ZIP/manifest từ source cuối, extract kiểm hash → final review/cleanup/report7phần rồi dừng.

Không sửa file đã đóng gói sau khi kiểm manifest; nếu cần sửa phải regenerate và verify gói. Ước lượng60–90phút, phụ thuộc XAMPP/network/CI; báo thực tế nếu blocked. Không gộp xử lý CI/conflict/schema hoặc triển khai UC mới vào task đóng gói. Trạng thái đầu là chưa kiểm chứng package/PR, không dùng các counts E17/E18 để tự đánh dấu gate mới PASS.

## Nguồn code hiện hành

- [Billing routes](../../backend/routes/api/billing.php), [Invoice services](../../backend/app/Services/Invoice) và [Invoice frontend service](../../frontend/src/services/invoiceService.js).
- [Review routes](../../backend/routes/api/reviews.php), [Review backend mapper](../../backend/app/Mappers/Review/ReviewMapper.php), [Review frontend mapper](../../frontend/src/mappers/reviewMapper.js) và [Review modal](../../frontend/src/components/reviews/ReviewCreateModal.jsx).
- [React routes](../../frontend/src/routes/AppRouter.jsx), [Host Dashboard](../../frontend/src/pages/host/HostDashboardPage.jsx) và [Admin Dashboard](../../frontend/src/pages/admin/AdminDashboardPage.jsx).
- [Schema migration](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php) và [models](../../backend/app/Models) cho nền dữ liệu FAQ/Chat.
- [Profile routes](../../backend/routes/api/profile.php), [Profile services](../../backend/app/Services/Profile), [Profile Validator](../../backend/app/Validators/Profile/ProfileValidator.php) và [Profile Repository](../../backend/app/Repositories/Profile/ProfileRepository.php).
- [Catalog routes](../../backend/routes/api/catalog.php), [Catalog Repository](../../backend/app/Repositories/Catalog/CatalogRepository.php) và [Host property frontend](../../frontend/src/pages/host/HostPropertyDetailPage.jsx).
- [Host Room controller](../../backend/app/Http/Controllers/Catalog/HostRoomController.php), [Host Room query](../../backend/app/Services/Catalog/HostRoomQueryService.php), [Host Room frontend](../../frontend/src/components/catalog/HostRoomList.jsx) và [Host Room test](../../backend/tests/Feature/Catalog/HostRoomListTest.php).
- [Host Room detail DTO](../../backend/app/DTOs/Catalog/Responses/HostRoomDetailResponse.php), [Host Room detail page](../../frontend/src/pages/host/HostRoomDetailPage.jsx) và [Host Room detail test](../../backend/tests/Feature/Catalog/HostRoomDetailTest.php).
- [Host Room create DTO](../../backend/app/DTOs/Catalog/Requests/CreateHostRoomRequest.php), [Host Room service](../../backend/app/Services/Catalog/HostRoomService.php), [Create form](../../frontend/src/components/catalog/HostRoomCreateForm.jsx) và [Create test](../../backend/tests/Feature/Catalog/HostRoomCreateTest.php).
- [Host Room update DTO](../../backend/app/DTOs/Catalog/Requests/UpdateHostRoomRequest.php), [Edit form](../../frontend/src/components/catalog/HostRoomEditForm.jsx) và [Update test](../../backend/tests/Feature/Catalog/HostRoomUpdateTest.php).
- [Host Room status Request](../../backend/app/DTOs/Catalog/Requests/ChangeHostRoomStatusRequest.php), [Status form](../../frontend/src/components/catalog/HostRoomStatusForm.jsx) và [Status feature tests](../../backend/tests/Feature/Catalog/HostRoomStatusTest.php).
