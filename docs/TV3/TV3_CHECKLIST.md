# StayHub TV3 — checklist triển khai và nghiệm thu

Baseline kiểm tra ngày 2026-11-14 (Asia/Bangkok), HEAD `951f62efc70127a3719c840ae529f8d311842a49`; nhánh thiết lập `feature/stayhub-agent-workflow`. Trạng thái dưới đây là inventory code và kết quả lịch sử được ghi nguồn, không phải xác nhận tất cả UC đã nghiệm thu.

## Hiện trạng refactor — INV-ARCH-02 đã đóng; VAL-STRUCT-01 chờ nghiệm thu

- INV-ARCH-01 A/B/C/parent, SIMPLIFY-01 A/B/C, INV-ARCH-02A, 02B.1, 02B.2A, 02B.2B và 02B.3: ACCEPTED theo owner. 02C.1/02C.2/02C.3 ACCEPTED. INV-ARCH-02-AUDIT ACCEPTED; parent INV-ARCH-02 CLOSED / ACCEPTED theo owner trong phạm vi refactor Invoice read/create. Không suy nghiệm thu UC/browser/MySQL hoặc đủ CRUD.
- Invoice read: [InvoiceRepository](../../backend/app/Repositories/Invoice/InvoiceRepository.php) concrete một file, constructor DI, không interface/binding; [InvoiceValidator](../../backend/app/Validators/Invoice/InvoiceValidator.php) gom read/create; InvoiceMapper gom request/response và raw input dùng DTO attributes; [InvoiceResponse](../../backend/app/DTOs/Invoice/Responses/InvoiceResponse.php)/InvoiceItemResponse JsonSerializable thay Resources read/create. Common giữ chuẩn API và nhánh Resource legacy.
- Frontend invoiceMapper và invoiceService.js đã gom create/list/detail ở 02C.2; invoiceReadService.js đã xóa, không thêm invoiceCreateService.js. Invoice create queries đã qua Repository; Validator không query/lazy-load; typed create workflow, persistence Mapper và required workflow DI hoàn tất ở 02B.2B. Model validation đã active trước insert Invoice/ROOM với explicit groups ở 02B.3, giữ SERVICE semantics; HTTP create đã nối raw Validator/typed command/Response DTO/Common ở 02C.1; Invoice Resources đã xóa. Kiến trúc/local rules/5 skills đã đồng bộ theo code tại 02C.3 và được owner nghiệm thu. Review và QA gates còn mở. Dashboard/payment/FAQ/chat backlog không được đóng bởi refactor.
- Sections bên dưới lưu lịch sử theo task; PENDING/TODO/đường dẫn cũ trong snapshot được thay bằng trạng thái hiện hành này và owner acceptance sau đó. Không sửa số test/lệnh lịch sử thành bằng chứng mới.

- VAL-STRUCT-01: đã gom infrastructure/domain validation vào một root app/Validators; 3 namespace-only moves và 17 file sửa, TECHNICAL PASS / owner acceptance PENDING. Symfony attributes/API/Auth/nghiệp vụ giữ nguyên; INV-ARCH-02 vẫn CLOSED / ACCEPTED.

### Tiến độ refactor hiện hành

| Đóng | Phần | Implementation / Verification / Acceptance |
|---|---|---|
| [x] | 02A | IMPLEMENTED / PASS / ACCEPTED |
| [x] | 02B.1 | IMPLEMENTED / PASS / ACCEPTED |
| [x] | 02B.2A | IMPLEMENTED / PASS / ACCEPTED |
| [x] | 02B.2B | IMPLEMENTED / PASS / ACCEPTED |
| [x] | 02B.3 | IMPLEMENTED / PASS / ACCEPTED |
| [x] | 02C.1 Backend HTTP | IMPLEMENTED / PASS / ACCEPTED |
| [x] | 02C.2 Frontend | IMPLEMENTED / PASS / ACCEPTED |
| [x] | 02C.3 docs/skills | IMPLEMENTED / PASS / ACCEPTED |
| [x] | INV-ARCH-02-AUDIT | VERIFIED / PASS / ACCEPTED |
| [x] | Parent INV-ARCH-02 / 02B / 02C | IMPLEMENTED / PASS / ACCEPTED — CLOSED theo owner, phạm vi refactor Invoice read/create |
| [ ] | VAL-STRUCT-01 — unified Validators root | IMPLEMENTED / PASS / OWNER ACCEPTANCE PENDING |
| [ ] | Browser/MySQL gates | NOT VERIFIED; không suy từ SQLite/Node/lint/build |

Các bảng task/UC ở phần dưới lưu snapshot theo thời điểm triển khai; bảng hiện hành và owner acceptance sections thay trạng thái cũ, không thay số test/lệnh lịch sử. Nghiệm thu refactor không tự đóng use case UI hoặc backlog nghiệp vụ.

## Quy ước

- Implementation: TODO / IN PROGRESS / IMPLEMENTED. VERIFIED không thay cho IMPLEMENTED.
- Verification: NOT VERIFIED / PARTIAL / PASS / FAIL. BLOCKED là cờ dependency riêng, không xóa phần đã làm được.
- Acceptance: PENDING / ACCEPTED. `[x]` chỉ khi mọi AC bắt buộc PASS và owner đã nghiệm thu; mọi ô đóng task dưới đây ban đầu giữ `[ ]`.
- Duyệt Mini Plan không phải nghiệm thu. Không suy ra toàn bộ UC PASS từ test service hoặc tên trang.
- Mỗi task con dự kiến 30–90 phút; nếu Mini Plan thực tế vượt phạm vi phải chia thêm ID con trước triển khai. Mục tiêu UI vẫn cần Backend + Frontend đầy đủ, task nền tảng ghi rõ giới hạn.
- Plan và Task Result chi tiết gửi trên chat, không tạo file. Sau mỗi task cập nhật dòng tương ứng: code mới, AC, lệnh/kết quả, evidence, blocker, next action; giữ lịch sử có ý nghĩa, không chép log vào đây.
- Evidence runtime nếu cần đặt `.agent-workspace/tv3/<task-id>/<run-id>/`. Không tạo đường dẫn evidence giả; đường dẫn chỉ được ghi khi artifact tồn tại.

## Nguồn hiện trạng

| ID | Bằng chứng / giới hạn |
|---|---|
| S1 | [Phân công](../stayhub-team-work-assignment.md), [Use cases](../stayhub-usecase-system.md): 18 UC TV3; FAQ/Chat History là deliverable bổ sung |
| S2 | [billing routes](../../backend/routes/api/billing.php), [Invoice services](../../backend/app/Services/Invoice), [invoice UI](../../frontend/src/pages/host/HostInvoiceCreationPage.jsx), [React routes](../../frontend/src/routes/AppRouter.jsx): tạo/xem/list có; payment update chưa có |
| S3 | [review routes](../../backend/routes/api/reviews.php), [Review services](../../backend/app/Services/Review), [Customer booking detail](../../frontend/src/pages/customer/CustomerBookingDetailPage.jsx), [Host reviews](../../frontend/src/pages/host/HostReviewsPage.jsx): API/UI có; luồng tạo phụ thuộc booking detail |
| S4 | [API entry](../../backend/routes/api.php), [booking service](../../frontend/src/services/bookingService.js), [property service](../../frontend/src/services/propertyService.js): chưa tích hợp GET booking detail/public property detail mà UI cần; phải kiểm tra lại khi TV1/TV2 bàn giao |
| S5 | [Host dashboard](../../frontend/src/pages/host/HostDashboardPage.jsx) là điều hướng; [Admin dashboard](../../frontend/src/pages/admin/AdminDashboardPage.jsx) là placeholder; chưa có service/route reporting |
| S6 | [Schema migration](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php), [models](../../backend/app/Models), [TV3 seeder](../../backend/database/seeders/Member3DataSeeder.php): có nền dữ liệu; không chứng minh DB hiện tại đã áp dụng hay chức năng đã chạy |
| H1 | Lượt audit trước trong cùng chat ngày 2026-11-14: `php artisan test` 407 passed, 2066 assertions; lint exit 0, 3 warnings; build exit 0, cảnh báo chunk >500 kB. Nguồn là tool output lịch sử trong chat, chưa có artifact log riêng; không chạy lại trong task tài liệu này |
| H2 | Cùng lượt audit: `php artisan route:list --path=api` có 16 route. Inventory được đọc lại trong SETUP-01; route output lịch sử không thay cho runtime tương lai |

## Ma trận 18 use case

| Đóng | UC | Implementation | Verification | Acceptance | Đã có / còn thiếu | Task tiếp tục |
|---|---|---|---|---|---|---|
| [ ] | UC15 Customer invoice | IMPLEMENTED | PARTIAL | PENDING | API/list/detail UI (S2,H1); browser/MySQL và nghiệm thu còn thiếu | INV-01, QA-02 |
| [ ] | UC16 Tạo review | IMPLEMENTED | PARTIAL | PENDING | API/form/test (S3,H1); BLOCKED tích hợp booking thật TV2 | REV-01, QA-03 |
| [ ] | UC17 Chatbot | TODO | NOT VERIFIED | PENDING | Chưa có luồng chat thật | CHAT-01..05 |
| [ ] | UC34 Tạo invoice | IMPLEMENTED | PARTIAL | PENDING | Service/transaction/UI nhập ID (S2); cần gate thực tế | INV-01, QA-02 |
| [ ] | UC35 Invoice detail Host | IMPLEMENTED | PARTIAL | PENDING | API/UI (S2,H1); browser còn thiếu | INV-01, QA-02 |
| [ ] | UC36 Payment status | TODO | NOT VERIFIED | PENDING | Thiếu workflow đã chốt, API và UI | INV-02, INV-03 |
| [ ] | UC37 Invoice management | IMPLEMENTED | PARTIAL | PENDING | List/search/filter/pagination (S2); không coi UC36 đã có | INV-01, QA-02 |
| [ ] | UC38 Host reviews | IMPLEMENTED | PARTIAL | PENDING | List/filter/summary API/UI (S3,H1); browser còn thiếu | REV-03 |
| [ ] | UC39 Host dashboard | TODO | NOT VERIFIED | PENDING | Chỉ khung điều hướng (S5) | RPT-01, HOST-01, HOST-02 |
| [ ] | UC44 Admin bookings | TODO | NOT VERIFIED | PENDING | Chưa có API/UI | ADM-02 |
| [ ] | UC45 Admin invoices | TODO | NOT VERIFIED | PENDING | Chưa có API/UI | ADM-01 |
| [ ] | UC47 Admin dashboard | TODO | NOT VERIFIED | PENDING | Placeholder (S5) | RPT-01, ADM-03, ADM-04 |
| [ ] | UC48 System statistics | TODO | NOT VERIFIED | PENDING | Chưa có API/UI | RPT-01, ADM-05, ADM-06 |
| [ ] | UC56 Invoice items | IMPLEMENTED | PARTIAL | PENDING | Atomic room/service items (S2,H1); xác minh AC cụ thể | INV-01, QA-02 |
| [ ] | UC57 Subtotal | IMPLEMENTED | PARTIAL | PENDING | BCMath + tests (S2,H1); chưa đối chiếu AC mới | INV-01 |
| [ ] | UC58 Total payment | IMPLEMENTED | PARTIAL | PENDING | Hàm có discount/extra_fee; create dùng 0, không suy ra có UI chỉnh phí | INV-01 |
| [ ] | UC60 Review conditions | IMPLEMENTED | PARTIAL | PENDING | Validator/test (S3,H1); chưa đóng nghiệm thu | REV-01, QA-03 |
| [ ] | UC61 Chat context | TODO | NOT VERIFIED | PENDING | Chưa có retrieval service | CHAT-01, CHAT-02 |

Tổng inventory: 10 UC có implementation chính (kể cả UC16 bị chặn tích hợp), 8 UC TODO. **0 UC được đánh dấu ACCEPTED trong checklist mới**; điều này không xóa code đã làm. H1 là suite tổng, không đủ để tự đóng từng AC hoặc khẳng định MySQL/browser PASS.

## Backlog task con

Các ID đầy đủ có tiền tố `TV3-`, ví dụ `TV3-INV-02`. Acceptance của tất cả task đang PENDING. Cột verify chỉ phản ánh scope task, không phản ánh cả module.

| Đóng | Task | Kết quả cần đạt / AC chính | Dependency | Impl / Verify |
|---|---|---|---|---|
| [ ] | SETUP-01 | Local rules, checklist, 5 skills chung; AGENTS.md nguyên vẹn; plan/result trên chat | Plan đã duyệt; delivery chờ owner nghiệm thu | IMPLEMENTED / PASS |
| [ ] | INV-01 | Đối chiếu UC15/34/35/37/56/57/58 theo code/test; lập AC còn thiếu, kiểm tra snapshot, uniqueness, atomicity, decimal và API docs hiện có; sửa lỗi chỉ sau scope cụ thể | S2,H1 | TODO / NOT VERIFIED |
| [ ] | INV-02 | Chốt transition payment, role/ownership, paid_at, refund, retry/conflict và mã lỗi trên chat; cập nhật quyết định đã chốt vào checklist | Quyết định owner, không tự suy từ enum | TODO / NOT VERIFIED |
| [ ] | INV-03 | Vertical slice UC36: API cập nhật + Host UI + validation/ownership/transition tests; chặn submit trùng | INV-02; file chung phải được phép | TODO / NOT VERIFIED |
| [ ] | REV-01 | Kiểm tra UC16/60 với booking detail API thật; form → API → DB → UI, conflict/ownership; xác định sửa TV3 hoặc handoff | BLOCKED TV2: GET customer booking detail chưa tích hợp | TODO / NOT VERIFIED |
| [ ] | REV-02 | Public reviews nối property thật; phân trang/empty/privacy và không gọi API bằng ID mock | BLOCKED TV1: property detail thật | TODO / NOT VERIFIED |
| [ ] | REV-03 | Nghiệm thu Host/customer review lists: filters, summary scope, ownership, lỗi và responsive; sửa gap theo scope | S3; runtime QA phù hợp | TODO / NOT VERIFIED |
| [ ] | RPT-01 | Chốt invoice status/date/timezone/refund và định nghĩa từng KPI; bộ lọc thời gian; dùng cho Host/Admin | INV-02; owner xác nhận nghiệp vụ | TODO / NOT VERIFIED |
| [ ] | HOST-01 | Query service Host + test totals/empty/date/ownership theo RPT-01; task nền tảng, chưa đóng UC39 | RPT-01 | TODO / NOT VERIFIED |
| [ ] | HOST-02 | UC39 API/resource + React dashboard + filter/states + kiểm chứng; không chỉ thẻ điều hướng | HOST-01; route integration được phép | TODO / NOT VERIFIED |
| [ ] | ADM-01 | UC45 read-only Admin invoice list, API/UI/filter/pagination/role tests | S2; quyền Admin | TODO / NOT VERIFIED |
| [ ] | ADM-02 | UC44 read-only Admin booking list, API/UI/filter/pagination/role tests; không vận hành thay Host | Contract Booking TV2 | TODO / NOT VERIFIED |
| [ ] | ADM-03 | Query nền tảng dashboard Admin, KPI có fixture độc lập và date/refund tests | RPT-01 | TODO / NOT VERIFIED |
| [ ] | ADM-04 | UC47 API + dashboard UI + states/responsive/role checks | ADM-03 | TODO / NOT VERIFIED |
| [ ] | ADM-05 | Query thống kê theo kỳ và chiều phân tích được chốt; không tự thêm export/phân tích ngoài scope | RPT-01; metric scope | TODO / NOT VERIFIED |
| [ ] | ADM-06 | UC48 API + trang thống kê + filters/charts/states, kiểm chứng số với API thật | ADM-05 | TODO / NOT VERIFIED |
| [ ] | FAQ-01 | Chốt contract; vertical slice Admin list/create FAQ + validation/authorization/UI/test | S6; shared routes được phép | TODO / NOT VERIFIED |
| [ ] | FAQ-02 | Admin edit/delete FAQ + confirm/conflict/error UI và test quyền; không tự thay schema | FAQ-01 | TODO / NOT VERIFIED |
| [ ] | CHAT-01 | Chốt V1 context allowlist, provider, timeout/error, retention/ownership; xác định secrets/config scope | Owner; catalog/FAQ contracts | TODO / NOT VERIFIED |
| [ ] | CHAT-02 | UC61 context service: property/room/amenity/service/FAQ được phép; tests privacy/access | CHAT-01, FAQ-01 | TODO / NOT VERIFIED |
| [ ] | CHAT-03 | Session/history API + UI danh sách/đọc lịch sử; ownership, pagination, empty/error tests | CHAT-01, S6 | TODO / NOT VERIFIED |
| [ ] | CHAT-04 | Provider adapter nền tảng, timeout/error và tests bằng provider double được ghi rõ; chưa coi chat live PASS | CHAT-01, CHAT-02 | TODO / NOT VERIFIED |
| [ ] | CHAT-05 | UC17 gửi message → provider → lưu → UI; chống submit trùng, privacy, timeout; live-provider verification ghi riêng | CHAT-03, CHAT-04 | TODO / NOT VERIFIED |
| [ ] | QA-01 | Khảo sát/chốt browser harness: actual React build/Laravel/Sanctum, DB/storage cô lập và quyền chạy; đề xuất task con nếu cần xây runner | Runtime/tool availability; scope riêng | TODO / NOT VERIFIED |
| [ ] | QA-02 | Gate invoice thật: create/read/payment, quyền, duplicate, decimal và viewport; không seed invoice để thay create | INV-01, INV-03, QA-01 và runner nếu cần | TODO / NOT VERIFIED |
| [ ] | QA-03 | Gate review thật: booking COMPLETED, create/read/duplicate/ownership/public/Host và viewport | REV-01..03, QA-01 và runner nếu cần | TODO / NOT VERIFIED |
| [ ] | QA-04 | Gate reporting/admin: KPI fixture độc lập, date/refund/ownership, API/UI khớp nhau; ghi engine được kiểm chứng | HOST/ADM, QA-01 | TODO / NOT VERIFIED |
| [ ] | QA-05 | Gate FAQ/chat/history: quyền, privacy, provider errors, late response, real integration và viewport | FAQ/CHAT, QA-01 | TODO / NOT VERIFIED |

## Quyết định chưa chốt / blocker

| ID | Nội dung | Tác động / bước gỡ |
|---|---|---|
| D1 | Các transition UNPAID/PAID/REFUNDED, paid_at và retry semantics | INV-02 phải chốt trước mutation UC36 |
| D2 | Trạng thái invoice tính doanh thu, ngày ghi nhận, timezone, refund | RPT-01 phải chốt trước query reporting |
| D3 | Booking detail/list thật chưa tích hợp | TV2 bàn giao endpoint/contract; TV3 chỉ sửa tích hợp trong scope |
| D4 | Public property detail thật chưa tích hợp | TV1 bàn giao để nghiệm thu public review UI |
| D5 | Chưa có browser harness và MySQL test isolation được xác minh | QA-01; không dùng DB development để đóng gate |
| D6 | Provider/context/retention chatbot chưa thống nhất | CHAT-01; mock provider không thay bằng chứng live provider |
| D7 | Root AGENTS.md hiện là TV3 MASTER PROMPT dù skills dùng chung | Giữ nguyên theo yêu cầu; chuyển thành common instructions là task nhóm riêng |

## AC task thiết lập

| AC | Điều kiện | Verification / Evidence |
|---|---|---|
| SETUP-AC01 | AGENTS.md giữ nguyên; hướng dẫn gọi local rules rõ | PASS — so nội dung với git show HEAD:AGENTS.md; README có prompt đọc local file; tool output chat 2026-11-14 |
| SETUP-AC02 | Đủ 18 UC + FAQ/history + gaps code hiện có; không tự ACCEPTED | PASS — kiểm tra 18 UC duy nhất, không dòng task/UC nào [x]; FAQ-01/02 và CHAT-03 rõ; inventory S1–S6 |
| SETUP-AC03 | Năm skills chung có trigger/ownership, không auto áp TV3 cho TV1/TV2 | PASS — review nội dung năm SKILL.md; planning chỉ đọc local TV3 khi áp dụng; root instructions vẫn có hiệu lực |
| SETUP-AC04 | Skill validator, metadata và links hợp lệ | PASS — quick_validate.py 5/5 exit 0; inline audit 14 files/23 local links, metadata prompt/name/length hợp lệ; tool output chat |
| SETUP-AC05 | Review tình huống payment chưa chốt, Review thiếu TV2, revenue chưa chốt, SQLite vs MySQL, plan vs acceptance | PASS — walkthrough tĩnh: planning chặn D1/D2, handoff D3; database/verification không đồng nhất SQLite–MySQL; local/checklist phân biệt plan approval–acceptance. Không phải test hành vi host/subagent |
| SETUP-AC06 | Scope/diff sạch; plan/result chỉ trên chat; branch feature/ | PASS — git diff --check exit 0; inline whitespace audit gồm untracked/ignored local file; branch đúng; không có templates hoặc task report file |

## Lịch sử và bước tiếp theo

- 2026-11-14: owner duyệt SETUP-01; tạo nhánh từ HEAD nêu trên. Owner acceptance của delivery vẫn PENDING.
- 2026-11-14: SETUP-01 TECHNICAL PASS theo AC trên; local rules + README + checklist + 5 skills/metadata + gitignore (14 file deliverables). Generator gặp encoding Windows và thiếu PyYAML ban đầu; chạy thành công bằng Python UTF-8 với venv cô lập `.agent-workspace/tv3/setup-validation/venv` (PyYAML 6.0.3). Môi trường kiểm tra này được giữ local để dùng lại, không là dependency ứng dụng. Giữ dòng ignore AGENTS.local.md phát hiện khi làm; file local không đi theo Git. Chưa kiểm chứng tự chọn skill trong chat mới; không chạy lại test ứng dụng cho task tài liệu. Plan/Task Result chỉ trên chat; chưa stage/commit/push.
- Đề xuất sau SETUP-01: **TV3-INV-01**, kiểm tra gap hiện có trước khi chốt **TV3-INV-02** và triển khai UC36. Không bỏ qua các phần Invoice/Review chưa nghiệm thu.

## ARCH-01 — chuyển đổi kiến trúc chung (2026-11-14)

Owner duyệt target toàn StayHub, gồm Repository/DTO/Mapper, base API và exception tập trung; không chỉ TV3. Checklist này theo dõi rollout TV3 và dependency chung, không quản lý nghiệm thu TV1/TV2. Tất cả task code dưới đây chưa triển khai trong ARCH-01. Quyết định này thay đề xuất tiếp tục INV-01 ngay sau SETUP-01: ưu tiên migration nền tảng trước; nghiệp vụ INV-02/UC36 vẫn cần chốt riêng.

| Đóng | Task | Scope và kết quả | Dependency | Impl / Verify / Acceptance |
|---|---|---|---|---|
| [x] | ARCH-01 | 9 file kiến trúc/rules/skills/checklist; AGENTS.md nguyên vẹn, không production changes | Owner nghiệm thu trên chat trước API-01 | IMPLEMENTED / PASS / ACCEPTED |
| [x] | API-01 | ApiResponse tại Controllers/Api/V1, business exception và renderer tập trung cho 9 named routes Invoice/Review; giữ Web/Auth; chưa chuyển Controller nghiệp vụ | Owner nghiệm thu trên chat trước FE-01 | IMPLEMENTED / PASS / ACCEPTED |
| [x] | FE-01 | Base API error/ID, Invoice/Review API modules và service integration; giữ Axios/Auth | Owner nghiệm thu trên chat trước INV-ARCH-01A | IMPLEMENTED / PASS / ACCEPTED |
| [x] | INV-ARCH-01 | Invoice read Repository/DTO/Mapper/Resource + list/detail UI consumers; chia A nền tảng, B HTTP, C frontend | Owner nghiệm thu A/C và đóng parent trên chat; B đã ACCEPTED | IMPLEMENTED / PASS / ACCEPTED |
| [ ] | INV-ARCH-02 | Invoice create Repository + Validator + DTO/Mapper, transaction/logging và consumer compatibility; chia A/B/C | INV-ARCH-01 ACCEPTED; 02A technical PASS, B/C chưa làm | IN PROGRESS / PARTIAL / PENDING |
| [ ] | REV-ARCH-01 | Review read Repository/DTO/Mapper + customer/Host/public consumers | API-01, FE-01 | TODO / NOT VERIFIED / PENDING |
| [ ] | REV-ARCH-02 | Review create, ownership/privacy, exception/logging + form consumer | REV-ARCH-01; booking integration D3 còn mở | TODO / NOT VERIFIED / PENDING |
| [ ] | QA-ARCH-01 | Regression API/DB/UI, auth compatibility và kiến trúc; ghi giới hạn browser/MySQL | INV/REV migration, QA-01 nếu cần harness | TODO / NOT VERIFIED / PENDING |

Mỗi task phải lên Mini Plan chi tiết 30–90 phút; task lớn chia tiếp, không tự làm task sau. TV1/TV2 và các domain mới áp target bằng task riêng. Không suy từ ARCH-01 rằng code đã dùng Repository hoặc Global Exception.

| AC | Điều kiện ARCH-01 | Kết quả |
|---|---|---|
| ARCH-AC01 | Target toàn project rõ, phân biệt code hiện có, ownership/task scope | PASS — Review mục 13 kiến trúc: target chung và hiện trạng/ownership tách rõ. |
| ARCH-AC02 | Repository/DTO/Mapper/Resource trách nhiệm và dependency rõ | PASS — Review ma trận chiều phụ thuộc, DTO thuần/mapper không query, Service sở hữu transaction. |
| ARCH-AC03 | Base API giữ data/meta/links/summary, error status/code/headers an toàn | PASS — Review contract data/meta/links/summary, error code/field errors/headers, không double wrapping. |
| ARCH-AC04 | Exception tập trung rollout có giới hạn, giữ Auth/Profile/Web contract | PASS — Review rollout theo module; giữ Auth user/message và Web behavior. |
| ARCH-AC05 | Logging commit/rollback, safe context và một report boundary | PASS — Review after-commit success, rollback, calculator không log, unexpected report một lần. |
| ARCH-AC06 | Frontend API/service/mapper và Auth interceptor compatibility | PASS — Review frontend guide mục 17: API/service/mapper, cancel/network và Auth session semantics. |
| ARCH-AC07 | 5 skill validator, links mới và diff/scope checks PASS | PASS — quick_validate.py 5/5 exit 0; 21 local file links hợp lệ; whitespace và git diff --check PASS; AGENTS.md nguyên vẹn. |
| ARCH-AC08 | Backlog migration rõ, không tự đóng task runtime/UC | PASS — Migration tasks TODO, acceptance PENDING; chưa đóng runtime/UC; scope 9 file đã đối chiếu. |

Scope được duyệt gồm ARCHITECTURE.md, frontend guide, AGENTS.local.md, 5 SKILL.md, checklist này. Không sửa metadata, README, AGENTS.md hoặc code nghiệp vụ. Runtime API/browser/MySQL của target: NOT VERIFIED. ARCH-01 TECHNICAL PASS sau kiểm tra ngày 2026-11-14, owner acceptance ACCEPTED trên chat trước API-01; plan/result chỉ báo trên chat. Kiểm chứng là static review/skill validation, không chứng minh runtime mới. Chưa chạy PHPUnit/lint/build vì không đổi code ứng dụng. Audit snapshot ban đầu bỏ sót checklist do dấu phân cách đường dẫn Windows; đã kiểm tra bổ sung trực tiếp, không diễn giải lần audit lỗi là PASS. Tự chọn skill trong chat mới chưa kiểm chứng. AGENTS.local.md vẫn bị ignore như trước. Không stage/commit/push; đề xuất tiếp theo API-01, chưa bắt đầu.

## API-01 — base response và exception tập trung

Owner duyệt scope 7 file mới + 2 file sửa; xác nhận ApiResponse đặt trong Controllers/Api/V1, giữ routes/URL. ARCH-01 đã được owner nghiệm thu. API-01 technical PASS; owner đã ACCEPTED trên chat trước FE-01, đóng [x] ở backlog kiến trúc. Không cập nhật acceptance của SETUP-01 hoặc các UC khác theo suy đoán.

### Đã triển khai

- ApiResponse success nhận resource/normalized array, giữ status và Laravel resource pagination/summary; error chỉ thêm errors khi có; noContent trả 204 rỗng. Array input phải là dữ liệu chưa bọc envelope theo contract helper; không tự dò/unwrap payload tùy ý.
- ApiBusinessException có public message/code/status/field errors, chỉ chấp nhận status domain 403/404/409/422; ShouldntReport tránh ghi lỗi nghiệp vụ dự kiến như failure bất ngờ. Không migrate các exception cũ trong task nền tảng.
- ApiExceptionRenderer đăng ký withExceptions trong bootstrap/app.php; allowlist 9 route names hiện có Invoice/Review. Không tạo route mới, không đổi URL /api thành /api/v1. Namespace Api/V1 chỉ là vị trí class.
- Framework auth/validation/HTTP/explicit response giữ xử lý hiện có; AccountStatusException render vẫn được Laravel ưu tiên. Unexpected/non-domain InvalidArgumentException ở route trong scope trả 500 JSON an toàn kể cả app.debug=true; framework chịu report, renderer không log lần hai.
- Controllers cũ còn catch/return lỗi trực tiếp; nền mới chưa khiến mọi lỗi domain có code. ApiResponse chưa được đưa vào Controller nghiệp vụ; dùng dần trong INV/REV-ARCH. Provider/Repository/DTO/Mapper chưa triển khai bởi API-01.

### File scope

7 file mới: backend/app/Http/Controllers/Api/V1/ApiResponse.php; backend/app/Exceptions/Api/ApiBusinessException.php; backend/app/Exceptions/Api/ApiExceptionRenderer.php; backend/tests/Unit/Api/ApiBusinessExceptionTest.php; backend/tests/Feature/Api/ApiResponseTest.php; backend/tests/Feature/Api/ApiExceptionRenderingTest.php; backend/tests/Feature/Api/ApiCompatibilityTest.php.

2 file sửa: backend/bootstrap/app.php và checklist này. Giữ AGENTS.md, routes, frontend, Services/Models/Controllers nghiệp vụ, providers, config, migration/seeders. Các thay đổi ARCH-01 và file untracked từ task trước được giữ, không tính là API-01 mới làm.

### Kiểm chứng thực tế

- `php artisan test --filter=Api` lần đầu FAIL do test truyền header nhầm tham số previous của HttpException; sửa test, không thay contract để né lỗi. Lệnh filter rộng này cũng chạy test cũ có tên Api.
- Chạy lại filter namespace chính xác `php artisan test --filter='Tests\\(Unit|Feature)\\Api\\'`: 12 tests PASS, 93 assertions, exit 0.
- `php artisan test`: 419 tests PASS, 2159 assertions, exit 0 (23.07s); gồm Auth/Profile/Invoice/Review và API mới. Config test SQLite in-memory; bootstrap/cache/config.php không tồn tại. Không migrate/reseed DB development. Test API mới không ghi DB; suite hiện có dùng fixtures cô lập.
- `php artisan route:list --path=api`: 16 routes; test routes chỉ đăng ký trong test class, không có ở production route list.
- `git diff --check`: PASS. Kiểm tra diff AGENTS.md, routes, Services/Models, Controllers nghiệp vụ và frontend: nguyên vẹn so với HEAD. Bằng chứng là tool output trong chat, không tạo file Task Result riêng.
- Frontend lint/build NOT RUN vì không thay frontend; browser/MySQL concurrency NOT VERIFIED và không thuộc gate nền tảng này. 419/429 được kiểm tra exception pipeline/header, không phải browser CSRF/rate-limit E2E mới; Auth session regression được suite hiện có xác minh.

| AC | Kết quả / bằng chứng |
|---|---|
| API-AC01 | PASS — ApiResponseTest: 200/201, decimal/null, pagination/meta/links/summary, 204 rỗng; không double wrap resource |
| API-AC02 | PASS — ApiBusinessExceptionTest và domain pipeline cases: status/code/public message/fields; invalid contract ném programming error |
| API-AC03 | PASS — renderer registered; named allowlist, outside module/Web fallback tests; route list không đổi |
| API-AC04 | PASS — toàn suite Auth/Profile; tests ACCOUNT_BLOCKED/INACTIVE, HttpResponseException precedence; không thay Auth code |
| API-AC05 | PASS — framework 401/403/404/419/429 và Retry-After, authorization privacy-404, validation 422, domain 409; real Auth regression |
| API-AC06 | PASS — unexpected + InvalidArgumentException trả safe 500 khi debug true; mock logger expectation một error report, expected domain không report |
| API-AC07 | PASS — focused 12/12 và full 419/419, native exit 0; production 16 API routes |
| API-AC08 | PASS — đúng 7 file mới + 2 file sửa; base response đúng vị trí được duyệt; business/routes/Auth/AGENTS nguyên vẹn; checklist tách acceptance |

Recommended Next Task: FE-01 — Mini Plan base API/error frontend và domain API boundaries, bảo toàn Auth interceptors. Chưa bắt đầu. API-01 đã dừng sau báo cáo, chưa stage/commit/push.

## FE-01 — base API/error Frontend và domain API modules

Owner duyệt 7 file mới và 3 file sửa; implementation/technical verification PASS, delivery acceptance ACCEPTED trên chat trước INV-ARCH-01A. API-01 đã được owner nghiệm thu. Giữ nguyên Axios singleton, AuthContext, authService/authSession, cookie/XSRF và routes/URL.

### Implementation

- apiError.js chuẩn hóa status/code/message/fields/kind/retryAfter; HTTP, local validation, network, timeout và cancellation được phân biệt. Không giữ config/request; 500 không hiển thị raw message. Chuẩn hóa lại giữ kết quả; chỉ lọc mã lỗi hợp lệ và field errors đúng cấu trúc. Retry-After hỗ trợ giây nguyên không âm và IMF-fixdate GMT, giá trị sai trả 0; không tự retry.
- apiId.js chỉ nhận positive safe integer number hoặc canonical digit string; reject fractional/exponent string/leading-zero/whitespace/unsafe number, không parseInt đổi ID sang tài nguyên khác. Number literal đã là số nguyên thì không thể suy ra cách người gọi viết ban đầu.
- invoiceApi.js/reviewApi.js cung cấp factories nhận Axios client. 9 thao tác endpoint hiện có, trả response.data nguyên envelope, chuyển signal, không catch/replace rejection hoặc thêm interceptors/replay.
- invoiceService/reviewService dùng cùng Axios qua factory; giữ exports, payload validation/trim ở Review, review pagination scan và envelope cho UI. Invoice thêm optional options.signal; parser cũ delegate base normalization, giữ các cờ Review UI dùng. Request ID sai là local validation, không báo mất mạng. Mapper/view-model đầy đủ, thay UI và sửa các response fallback cũ ngoài service không nằm trong FE-01.

### File scope

7 file mới: frontend/src/api/apiError.js, apiId.js, invoiceApi.js, reviewApi.js; frontend/tests/apiError.test.js, domainApi.test.js, apiAuthCompatibility.test.js.

3 file sửa: frontend/src/services/invoiceService.js, frontend/src/services/reviewService.js, docs/TV3/TV3_CHECKLIST.md. Không sửa frontend/src/api/axios.js, Auth files, package/dependencies, Pages/Components, Backend hoặc AGENTS.md. Các diff ARCH/API trước đó giữ nguyên, không tính là FE-01 mới làm. Build cập nhật dist bị ignore là output phát sinh, không source deliverable.

### Tests và evidence

Bằng chứng là tool output trong chat thực thi FE-01, không tạo file plan/Task Result.

- Focused: `node --test tests/apiError.test.js tests/domainApi.test.js tests/apiAuthCompatibility.test.js`: 10/10 PASS, 0 skipped, exit 0.
- Full: `node --test tests/*.test.js`: 59/59 PASS, 0 skipped, exit 0; gồm Auth/Registration/Profile tests cũ. Node v22.23.4.
- `npm run lint`: exit 0, 0 errors, 3 warning react-refresh cũ tại ui/badge.jsx, button.jsx, sidebar.jsx.
- `npm run build`: exit 0, 3315 modules, bundle warning >500 kB như baseline. Không suy từ build rằng browser/responsive PASS.
- `git diff --check` và file audit gồm untracked: PASS. Diff Axios/Auth/package/AGENTS.md nguyên vẹn. Backend bootstrap diff thuộc API-01 từ trước, không phải sửa FE-01.
- Tests dùng Axios thật + custom adapter; kiểm chứng transport contract và interceptor integration, không Backend HTTP thật hoặc browser. Service consumer wiring được đọc lại và Vite build, chưa test UI DOM/end-to-end. PHPUnit NOT RUN vì không sửa Backend; MySQL/browser NOT VERIFIED ngoài gate này.

| AC | Kết quả / evidence |
|---|---|
| FE-AC01 | PASS — hai service không còn gọi api.get/post trực tiếp; API factories dùng client được inject từ singleton hiện có |
| FE-AC02 | PASS — exports/response shape giữ qua review diff/build; adapter tests giữ identity envelope, decimal/null/meta/links/summary; không claim browser acceptance |
| FE-AC03 | PASS — apiError tests cover 401/403/404/409/419/422/429/500, malformed bodies, field errors, timeout/cancel/network/local validation, idempotence và không giữ credentials |
| FE-AC04 | PASS — canonical ID matrix và invalid inputs request count 0 cho các API cần ID; property filter dùng strict helper |
| FE-AC05 | PASS — 9 endpoint verb/path/query/body/signal, unchanged rejection và cancellation; mỗi invocation gửi tối đa một request, không replay |
| FE-AC06 | PASS — Auth interceptors thật trên adapter client: protected/public 401, 2 account codes vs role denial, version snapshot đến muộn, cleanup/resubscribe không nhân callback |
| FE-AC07 | PASS — focused 10/10 + full Node 59/59, lint/build exit 0; warnings được báo riêng |
| FE-AC08 | PASS — đúng 7 new + 3 modified source/checklist, Axios/Auth/Backend/routes/AGENTS không đổi trong task; API-01 acceptance cập nhật |

Recommended Next Task: INV-ARCH-01 — lên Mini Plan chuyển Invoice read path sang Repository/DTO/Mapper và giữ list/detail UI contract. Chưa bắt đầu; không stage/commit/push.

## INV-ARCH-01A — Invoice read Repository/DTO/Mapper nền tảng

Owner duyệt cách chia A/B/C và scope 01A, cho phép sửa bootstrap/providers.php. FE-01 đã ACCEPTED trên chat. INV-ARCH-01A technical PASS, delivery acceptance PENDING; parent INV-ARCH-01 chưa hoàn tất. Lượt bị ngắt đã có 9 test mới PASS, lượt tiếp tục đã xác minh lại state và chạy regression. Không bắt đầu 01B/01C.

| Đóng | Task con | Dependency | Implementation / Verification / Acceptance |
|---|---|---|---|
| [x] | INV-ARCH-01A — Repository/DTO/Mapper/provider và tests | Owner nghiệm thu trực tiếp trên chat | IMPLEMENTED / PASS / ACCEPTED |
| [x] | INV-ARCH-01B — Request/mapper/resource, nối Controller/QueryService read path | Owner nghiệm thu trên chat trước 01C | IMPLEMENTED / PASS / ACCEPTED |
| [x] | INV-ARCH-01C — Frontend invoice mapper và regression consumers | Owner nghiệm thu trực tiếp trên chat | IMPLEMENTED / PASS / ACCEPTED |

### Implementation và scope

9 file mới: backend/app/Repositories/Invoice/Contracts/InvoiceReadRepository.php; backend/app/Repositories/Invoice/Eloquent/EloquentInvoiceReadRepository.php; backend/app/DTOs/Invoice/Requests/InvoiceFilterData.php; backend/app/DTOs/Invoice/Responses/InvoiceData.php; backend/app/DTOs/Invoice/Responses/InvoiceItemData.php; backend/app/Mappers/Invoice/InvoiceResponseMapper.php; backend/app/Providers/Modules/InvoiceServiceProvider.php; backend/tests/Feature/Invoice/InvoiceReadRepositoryTest.php; backend/tests/Unit/InvoiceResponseMapperTest.php.

2 file sửa: backend/bootstrap/providers.php đăng ký provider binding; checklist này. Controllers/InvoiceQueryService/Resources/routes hiện có giữ nguyên; chưa nối Repository vào HTTP. Không sửa frontend/schema/seeders/AGENTS.md và không thay workflow create/payment. Các diff ARCH/API/FE trước đó được giữ nguyên, không tính là thay đổi mới của 01A.

Repository có 4 methods scoped Host/Customer list/detail, grouped search chống OR thoát ownership, filters/status, created_at desc/id desc, explicit page/perPage, eager load items cho detail. Trả null cho missing/foreign-owner; Service của 01B chịu role và map thành privacy-404. Repository không nhận HTTP Request hoặc tự commit. Đây là read boundary, không thêm log INFO mỗi query.

InvoiceFilterData typed readonly, trim search, status chưa hỗ trợ thành null, clamp page >=1 và perPage 1..50. Chuỗi search '0' được giữ như input search có nghĩa; raw HTTP normalization/legacy edge-case contract sẽ được chốt và test ở 01B trước khi đưa vào API.

InvoiceData/InvoiceItemData giữ decimal string, nullable dates/description, list items=null phân biệt detail items=[]. InvoiceResponseMapper dùng cast attributes và relation đã tải; detail thiếu items ném LogicException thay vì lazy-load. Không tính lại total hoặc query; DTO chưa được serialize thành HTTP ở task này. Provider bind interface -> Eloquent implementation; chưa thay injection của Service đang chạy.

### Tests executed

- `php artisan test --filter='InvoiceReadRepositoryTest|InvoiceResponseMapperTest'`: 9/9 PASS, 63 assertions, exit 0.
- `php artisan test --filter=Invoice`: 84/84 PASS, 353 assertions, exit 0.
- `php artisan test`: 428/428 PASS, 2222 assertions, exit 0 (21.94s), gồm Auth/Profile/Invoice/Review/API regression.
- `php artisan route:list --path=api`: 16 route như trước, không đổi URL.
- `php -l`: 10 file PHP trong scope PASS; `git diff --check` PASS, audit whitespace/newline gồm file mới. Diff Controllers/InvoiceQueryService/Resources/routes/AGENTS.md không đổi so với HEAD.
- Safety guard kiểm tra env và effective config SQLite :memory: trước RefreshDatabase; từ chối cached config. Fixtures trực tiếp DB chỉ phục vụ read-query integration, không phải evidence tạo invoice qua workflow thật. Không reset/reseed DB development.
- Mapper unit không boot app/DB; integration query log xác nhận mapping detail tải sẵn phát sinh 0 query. Tests cover decimal max precision, total không bị tính lại, null/empty items, ngày ISO, ownership/filter/search/pagination/DI.
- Frontend lint/build NOT RUN vì không sửa frontend; browser/MySQL concurrency NOT VERIFIED. Không dùng kết quả test cũ làm kết quả chạy lại. Evidence là tool output trong chat, không tạo file report.

| AC 01A | Kết quả |
|---|---|
| A-AC01 — Repository interface/implementation và DI resolve | PASS — provider + repository integration tests |
| A-AC02 — Ownership và missing/foreign null nhất quán | PASS — Host/Customer list/detail tests; role/HTTP status thuộc 01B |
| A-AC03 — Filters/search/order/pagination/clamp | PASS — repository/DTO tests với fixture độc lập, ID tie ordering; HTTP compatibility vẫn dùng path cũ |
| A-AC04 — DTO fields, exact decimal/null/items/date projection | PASS — mapper unit và DB integration; không dùng tính toán production làm expected totals |
| A-AC05 — Mapper thuần, không query/mutate hoặc lazy-load | PASS — unloaded relation throws, attributes unchanged, query log rỗng |
| A-AC06 — API hiện có/create/Auth không regression, đúng scope | PASS — 428 tests; 16 routes; read/create source chưa đổi; 9 new + 2 modified |

Parent INVREAD-AC01/06/08 và các gate tích hợp sau này chưa được đóng bằng 01A. Technical PASS chỉ áp dụng foundation scope. Recommended Next Task: lên Mini Plan INV-ARCH-01B, không tự triển khai. Chưa stage/commit/push.

## STRUCT-01 — chuẩn hóa tên và thư mục theo quyết định owner

Owner duyệt STRUCT-01A; 01B/01C chưa thực hiện. Đây là đổi cấu trúc/naming sau foundation INV-ARCH-01A, không nối HTTP read path. Nghiệm thu INV-ARCH-01A không tự suy ra từ việc duyệt kế hoạch rename; giữ acceptance lịch sử PENDING. Các đường dẫn/tên cũ trong sections lịch sử phía trên mô tả code tại thời điểm test, không phải namespace hiện hành.

| Đóng | Task | Implementation / Verification / Acceptance |
|---|---|---|
| [x] | STRUCT-01A — Invoice DTO/Mapper/Repository + central binding | IMPLEMENTED / PASS / ACCEPTED (owner trên chat trước 01B) |
| [x] | STRUCT-01B — Common ApiResponse/PageResponse/Constants và Exceptions mới | IMPLEMENTED / PASS / ACCEPTED (owner trước 01C) |
| [x] | STRUCT-01C — Kiến trúc/local rules/5 skills theo naming mới | IMPLEMENTED / PASS / ACCEPTED (owner trước INV-ARCH-01B) |

### STRUCT-01A — thay đổi thực tế

7 file rename/move trong backend:

| Cũ | Mới |
|---|---|
| app/DTOs/Invoice/Requests/InvoiceFilterData.php | app/DTOs/Invoice/Requests/InvoiceFilterRequest.php |
| app/DTOs/Invoice/Responses/InvoiceData.php | app/DTOs/Invoice/Responses/InvoiceResponse.php |
| app/DTOs/Invoice/Responses/InvoiceItemData.php | app/DTOs/Invoice/Responses/InvoiceItemResponse.php |
| app/Mappers/Invoice/InvoiceResponseMapper.php | app/Mappers/Invoice/InvoiceMapper.php |
| app/Repositories/Invoice/Contracts/InvoiceReadRepository.php | app/Repositories/Invoice/InvoiceReadRepositoryInterface.php |
| app/Repositories/Invoice/Eloquent/EloquentInvoiceReadRepository.php | app/Repositories/Invoice/InvoiceReadRepository.php |
| tests/Unit/InvoiceResponseMapperTest.php | tests/Unit/InvoiceMapperTest.php |

Mapper methods toData/toItemData đổi thành toResponse/toItemResponse. Requests DTO là application data, không phải Http Form Request. Repository methods/query/filter và DTO fields/types/normalization không đổi. Các tests giữ nguyên assertions, chỉ đổi tên/import/method references.

4 file cập nhật tại chỗ: backend/app/Providers/AppServiceProvider.php (binding interface mới -> implementation mới), backend/bootstrap/providers.php (bỏ module provider), backend/tests/Feature/Invoice/InvoiceReadRepositoryTest.php (imports/resolve assertions), checklist này. Bỏ đúng backend/app/Providers/Modules/InvoiceServiceProvider.php sau khi chuyển binding và 3 thư mục rỗng Modules/Contracts/Eloquent bằng non-recursive rmdir. Không xóa file nào khác. Không đổi Composer dependencies hoặc tạo compatibility alias dư.

### Verification

- `php artisan test --filter='InvoiceReadRepositoryTest|InvoiceMapperTest'`: 9/9 PASS, 63 assertions, exit 0.
- `php artisan test --filter=Invoice`: 84/84 PASS, 353 assertions, exit 0.
- `php artisan test`: 428/428 PASS, 2222 assertions, exit 0 (25.83s). Gồm Auth/Profile/API/Invoice create-read/Review regression.
- `php artisan route:list --path=api`: 16 route hiện có, không đổi URL.
- `php -l` trên 10 file PHP trong scope: PASS. `git diff --check`: PASS; Git có thông báo chuẩn hóa CRLF->LF trên 2 file provider, không phải lỗi test.
- `rg` không còn InvoiceFilterData/InvoiceData/InvoiceItemData/InvoiceResponseMapper/EloquentInvoiceReadRepository, namespace Contracts/Eloquent/Providers Modules hoặc mapper method cũ trong backend/app, bootstrap và tests. Tên trong checklist/tài liệu lịch sử không phải runtime reference.
- Controller/Service/Resource/routes/AGENTS.md diff nguyên vẹn. API-01 bootstrap/app.php, frontend và kiến trúc/skills có diff từ các task trước, không sửa trong STRUCT-01A. Tài liệu/skills vẫn mô tả target trước rename, sẽ đồng bộ tại STRUCT-01C, không coi hiện tại đã hoàn toàn nhất quán.
- Lệnh artisan đầu tiên bị gọi ở repository root và không chạy test (Could not open input file: artisan); đã chạy lại đúng backend và có kết quả trên. Không báo lần gọi sai là PASS.
- SQLite in-memory, fixtures/query/mapper tests như 01A trước; không migrate/reseed MySQL development. Frontend lint/build NOT RUN vì không thay frontend. Browser/MySQL NOT VERIFIED ngoài scope naming refactor. Evidence là tool output trong chat; không tạo file plan/report.

| AC 01A | Kết quả |
|---|---|
| STRUCT-A01 — tên/path DTO/Mapper/Repository đúng quyết định | PASS — inventory và syntax checks |
| STRUCT-A02 — imports/type hints/method references đầy đủ | PASS — rg không references cũ runtime, focused tests |
| STRUCT-A03 — binding AppServiceProvider resolve implementation mới | PASS — repository integration, không module provider cũ |
| STRUCT-A04 — API/nghiệp vụ/contract/ownership giữ nguyên | PASS — 428 tests, business source/routes không đổi, 16 routes |
| STRUCT-A05 — scope/whitespace/checklist chính xác | PASS — rename/delete đúng danh sách, history giữ tên cũ có giải thích |

STRUCT-01A TECHNICAL PASS, owner acceptance PENDING, chưa stage/commit/push. Parent STRUCT-01 chưa đóng; Common/Exceptions chưa chuyển, PageResponse/Constants chưa tạo, architecture/skills chưa cập nhật. Recommended Next Task: STRUCT-01B theo Mini Plan đã trình bày, chờ scope được duyệt; chưa bắt đầu.

## STRUCT-01B — Common và exception naming

Owner nghiệm thu STRUCT-01A và duyệt 01B trước triển khai. Các ghi chú PENDING của 01A trong lịch sử phía trên được thay thế bởi acceptance này, không viết lại kết quả test cũ. STRUCT-01B IMPLEMENTED/technical PASS, owner acceptance PENDING; STRUCT-01C chưa bắt đầu.

### File scope và mapping hiện hành

| Nguồn cũ | Đích hiện tại |
|---|---|
| backend/app/Http/Controllers/Api/V1/ApiResponse.php | backend/app/Common/ApiResponse.php |
| backend/app/Exceptions/Api/ApiBusinessException.php | backend/app/Exceptions/AppException.php |
| backend/app/Exceptions/Api/ApiExceptionRenderer.php | backend/app/Exceptions/GlobalExceptionHandler.php |
| backend/tests/Unit/Api/ApiBusinessExceptionTest.php | backend/tests/Unit/Api/AppExceptionTest.php |

3 file mới: backend/app/Common/PageResponse.php, backend/app/Common/Constants.php, backend/app/Exceptions/ErrorCode.php.

6 file sửa tại chỗ: backend/bootstrap/app.php; backend/app/DTOs/Invoice/Requests/InvoiceFilterRequest.php; backend/tests/Feature/Api/ApiResponseTest.php; ApiExceptionRenderingTest.php; ApiCompatibilityTest.php (cùng thư mục Feature/Api); checklist này. Bỏ riêng thư mục nguồn Exceptions/Api và Controllers/Api/V1 cùng parent Api khi rỗng; không xóa file khác. Không đổi Provider/Repository/Mapper, Auth, routes/URL, Controller/Service/Resource nghiệp vụ, Frontend, schema hoặc dependencies.

### Behavior

ApiResponse success/error/noContent giữ chữ ký và output cũ, thêm page(ResourceCollection, status=200) delegate PageResponse. PageResponse chỉ nhận ResourceCollection chứa LengthAwarePaginator, trả response qua Laravel để giữ data/meta/links/additional summary/query links/header hooks; không tự dựng pagination và không query. Collection không phân trang ném InvalidArgumentException có chủ đích, không biến thành trang rỗng. success(collection) cũ vẫn tương thích.

Constants có DEFAULT_PAGE_SIZE=10, MIN_PAGE_SIZE=1, MAX_PAGE_SIZE=50; InvoiceFilterRequest dùng chúng mà không đổi default/clamp. Không thay mọi literal ở module khác.

AppException giữ nguyên constructor/public errorCode/status/errors/ShouldntReport của ApiBusinessException. ErrorCode::INTERNAL_SERVER_ERROR được GlobalExceptionHandler dùng, không thay string value JSON. Handler vẫn qua withExceptions, allowlist 9 route names như trước, không mở rộng Auth/Profile/Web; framework exceptions/AccountStatusException render/explicit response và unexpected-reporting behavior giữ nguyên. Không migrate exception nghiệp vụ cũ.

### Tests và evidence

- Focused `php artisan test --filter='AppExceptionTest|ApiResponseTest|ApiExceptionRenderingTest|ApiCompatibilityTest'`: 16/16 PASS, 117 assertions, exit 0.
- `php artisan test --filter='InvoiceReadRepositoryTest|InvoiceMapperTest'`: 9/9 PASS, 63 assertions, exit 0.
- `php artisan test`: 432/432 PASS, 2246 assertions, exit 0 (21.79s), gồm Auth/Profile/Invoice/Review/API.
- Test lần đầu FAIL ở fixture custom ResourceCollection chưa khai báo collects=JsonResource::class nên Laravel gọi toArray trên array; sửa fixture và rerun PASS. Không thay production behavior để bỏ assertion.
- PageResponse tests kiểm tra parity legacy success/page, exact data decimal/null, meta total/current/last page, query links, summary, custom header hook, explicit status, empty page và reject unpaginated. Constants được test số cụ thể/default/clamp. Existing reporting và Auth compatibility assertions giữ nguyên.
- php -l 12 file PHP trong scope PASS; git diff --check và whitespace/newline audit PASS (gồm untracked). rg references cũ trong app/bootstrap/tests không có kết quả (exit 1 của rg nghĩa no matches, không phải syntax fail).
- route:list --path=api có 16 route như baseline. Diff AGENTS.md/routes/Services/Controllers nghiệp vụ/Resources không đổi. Các diff task trước giữ nguyên, không tính là 01B mới sửa.
- SQLite in-memory cho suite, không migrate/reseed DB development. Frontend lint/build NOT RUN vì không sửa frontend; browser/MySQL NOT VERIFIED ngoài scope. Evidence là tool output trên chat, không file plan/Task Result.

| AC | Kết quả |
|---|---|
| STRUCT-B01 — Common/Exceptions đúng path/namespace | PASS — file inventory/syntax |
| STRUCT-B02 — references runtime cũ không còn | PASS — rg app/bootstrap/tests và focused tests |
| STRUCT-B03 — compatibility ApiResponse và pagination/additional/headers | PASS — ApiResponseTest gồm parity và hook/empty/error cases |
| STRUCT-B04 — Constants được sử dụng, default/clamp giữ nguyên | PASS — constants/filter tests + repository regression |
| STRUCT-B05 — exception contract và reporting giữ nguyên | PASS — AppException và rendering tests; JSON error code literal không đổi |
| STRUCT-B06 — Auth/Web/status/header không regression | PASS — compatibility + full suite |
| STRUCT-B07 — tests/syntax/diff checks đạt | PASS — 432 tests, 12 PHP lint, diff/whitespace |
| STRUCT-B08 — đúng scope và checklist tách lịch sử/acceptance | PASS — 4 move/rename + 3 new + 6 modified; acceptance 01B vẫn PENDING |

Recommended Next Task: STRUCT-01C — cập nhật kiến trúc/local rules/5 skills theo naming mới; chưa bắt đầu. Tài liệu hiện hành ngoài checklist vẫn có tên cũ chờ 01C; lịch sử checklist giữ nguồn cũ có mapping. Chưa stage/commit/push. Dừng sau Task Result.

## STRUCT-01C — tài liệu/skills theo cấu trúc hiện hành

Owner đã nghiệm thu STRUCT-01B và duyệt 01C. Các dòng PENDING của 01B trong báo cáo lịch sử phía trên là trạng thái tại thời điểm báo cáo, được thay thế bởi acceptance này. INV-ARCH-01A delivery vẫn PENDING vì không có xác nhận riêng; parent INV-ARCH-01 vẫn mở, không suy ra hoàn tất từ rename/DI tests.

### Cấu trúc hiện hành và phần chưa chuyển

| Boundary | Nguồn hiện tại / trạng thái |
|---|---|
| Common | [ApiResponse](../../backend/app/Common/ApiResponse.php), [PageResponse](../../backend/app/Common/PageResponse.php), [Constants](../../backend/app/Common/Constants.php) đã có |
| Exceptions | [AppException](../../backend/app/Exceptions/AppException.php), [ErrorCode](../../backend/app/Exceptions/ErrorCode.php), [GlobalExceptionHandler](../../backend/app/Exceptions/GlobalExceptionHandler.php); allowlist 9 route names, không đổi Auth/Web |
| DTO Invoice | [InvoiceFilterRequest](../../backend/app/DTOs/Invoice/Requests/InvoiceFilterRequest.php), [InvoiceResponse](../../backend/app/DTOs/Invoice/Responses/InvoiceResponse.php), [InvoiceItemResponse](../../backend/app/DTOs/Invoice/Responses/InvoiceItemResponse.php) |
| Mapper/Repository (snapshot STRUCT-01C) | [InvoiceMapper](../../backend/app/Mappers/Invoice/InvoiceMapper.php); lúc đó có InvoiceReadRepositoryInterface/InvoiceReadRepository (tên lịch sử, đã xóa/đổi tên ở SIMPLIFY-01A). Hiện dùng [InvoiceRepository](../../backend/app/Repositories/Invoice/InvoiceRepository.php), không interface/binding. |
| DI | Binding tại [AppServiceProvider](../../backend/app/Providers/AppServiceProvider.php), không còn module provider |
| HTTP Invoice | Controller/InvoiceQueryService/Resources còn path cũ; INV-ARCH-01B/02 chưa triển khai |
| Frontend | Invoice/Review API modules/error/ID có sau FE-01; domain mapper/list-detail integration thuộc INV-ARCH-01C |
| Module khác | Review migration và TV1/TV2/dashboard/FAQ/chat cần task riêng; không suy ra project đã migrated |

Tên cũ trong sections lịch sử API-01/INV-ARCH-01A/STRUCT-01A/B giữ nguyên để đối chiếu test commands tại thời điểm đó. Mapping tên cũ -> mới đã có ở STRUCT-01A/B; bảng hiện hành này và ARCHITECTURE mục 14 là hướng dẫn làm tiếp. Các số test 428/432 là lịch sử, không chạy lại trong 01C.

### Scope

Chỉ 9 file sửa: ARCHITECTURE.md, docs/ai-frontend-development-guide.md, AGENTS.local.md, 5 SKILL.md (planning/laravel/react/database/verification), checklist này. Không tạo/move/delete source files, không sửa metadata, README TV3, AGENTS.md hoặc code ứng dụng. Baseline hash 530 project files + local rules được ghi trước edit tại ignored .agent-workspace/tv3/struct-01c-validation/baseline.json; đây là evidence kiểm tra phạm vi, không file plan/Task Result.

### Acceptance criteria 01C

| AC | Kết quả |
|---|---|
| STRUCT-C01 — naming/path hiện hành khớp code | PASS — Static review ARCHITECTURE mục 14 + file inventory hiện có: Request/Response, Mapper, Repository và Common/Exceptions đúng tên. |
| STRUCT-C02 — central binding/Common responsibilities | PASS — Review AppServiceProvider binding convention; Common helper/pagination/constants responsibilities rõ, không module provider. |
| STRUCT-C03 — exception scope/reporting/Auth đúng | PASS — Review GlobalExceptionHandler allowlist 9 names, framework/Auth/Web precedence và một reporting boundary. |
| STRUCT-C04 — 5 skill nhất quán, không sinh cấu trúc cũ | PASS — 5/5 quick_validate.py exit 0; walkthrough planning/Laravel/database không sinh provider/Contracts/Eloquent cũ, React/verification giữ contract. |
| STRUCT-C05 — tách foundation khỏi HTTP/UI pending | PASS — Matrix hiện trạng ghi foundation có và INV-ARCH-01B/C, create/Review migration chưa hoàn tất. |
| STRUCT-C06 — acceptance/history đúng | PASS — STRUCT-01B ACCEPTED theo chat, 01C PENDING; lịch sử tên và 428/432 test counts giữ nguồn cũ; không suy acceptance 01A. |
| STRUCT-C07 — validator/links/scope checks | PASS — 42 local links + 2 anchors PASS; git diff --check/UTF-8/whitespace PASS; so hash đúng 9 file thay đổi. |
| STRUCT-C08 — AGENTS.md/application code nguyên vẹn | PASS — 530 baseline entries hash audit: code/AGENTS.md/metadata/README không đổi; không tạo/xóa project files. |

Recommended Next Task: INV-ARCH-01B — lập Mini Plan nối HTTP read path vào Repository/DTO/Mapper với tên mới, chưa triển khai. Plan/Task Result chỉ trên chat; STRUCT-01C TECHNICAL PASS sau validation, owner acceptance PENDING. Chưa stage/commit/push.

Validation evidence: quick_validate.py chạy cả 5 skill bằng venv UTF-8 hiện có, exit 0. Inline hash/link audit có output trên chat, dùng baseline.json chỉ để đối chiếu scope; 530 entries bao gồm ignored local rules. Không chạy lại PHPUnit/Node/lint/build cho task documentation; số tests ở sections cũ là historical. Chưa kiểm chứng auto-selection của skill trong chat mới. Git có warning CRLF->LF từ provider files đã đổi ở task trước, không là code edit của 01C. Task chỉ lưu checklist và evidence scope local; không tạo file plan/result. Dừng sau báo cáo; không làm INV-ARCH-01B.

## INV-ARCH-01B — HTTP Invoice read path

Owner đã nghiệm thu STRUCT-01C, duyệt plan 5 new/6 modified và cho phép thêm error code vào lỗi đọc. Trong thực thi, owner cho phép mở scope đúng một assertion Auth test (file modified thứ 7) để phù hợp contract code mới. Delivery INV-ARCH-01B technical PASS, acceptance PENDING. Parent INV-ARCH-01 vẫn mở vì 01C Frontend mapper chưa làm; acceptance INV-ARCH-01A không tự suy ra từ duyệt plan sau.

### Implementation và scope

5 file mới: backend/app/Http/Requests/Invoice/InvoiceQueryRequest.php, InvoiceDetailRequest.php; backend/app/Mappers/Invoice/InvoiceRequestMapper.php; backend/app/Http/Resources/Invoice/InvoiceReadResource.php, InvoiceItemReadResource.php.

7 file sửa sau scope extension: backend/app/Services/Invoice/InvoiceQueryService.php; backend/app/Http/Controllers/Invoice/HostInvoiceController.php; CustomerInvoiceController.php; backend/app/Exceptions/ErrorCode.php; backend/tests/Feature/Invoice/InvoiceQueryApiTest.php; backend/tests/Feature/Auth/AuthenticationTest.php (một assertion được owner cho phép); checklist này.

4 GET list/detail Host/Customer hiện đi qua Form Request -> request mapper/InvoiceFilterRequest -> QueryService -> InvoiceReadRepositoryInterface -> InvoiceMapper -> InvoiceResponse/InvoiceItemResponse -> Read Resources -> Common ApiResponse/PageResponse. QueryService không xây Eloquent query, kiểm tra role cả khi gọi ngoài HTTP. Form Request dùng authorization trước validation, Repository giữ ownership scoped; read-only không transaction mutation hay INFO logging từng query.

HTTP status/message đọc giữ cũ, thêm INVOICE_READ_FORBIDDEN cho 403 và INVOICE_NOT_FOUND cho privacy-404 qua AppException/GlobalExceptionHandler. Guest 401 giữ framework/middleware. ID sai/missing/foreign cùng 404. Query search/status arrays và page/per_page arrays trả 422 field errors; scalar defaults/clamp, unsupported status, search literal 0 và empty giữ compatibility như plan. Request validation đọc query, không trộn GET JSON body. DTO detail có items (kể cả []), list bỏ items; decimal/null/date/field envelope tương đương Resources cũ. Paginator map Model -> DTO giữ metadata/links.

store() tạo hóa đơn đã so hash chính xác không đổi; InvoiceResource/InvoiceItemResource cũ vẫn phục vụ create, không sửa. Không đổi routes/URL, Auth runtime, shared Axios/Frontend, schema, dependencies, Repository/Response Mapper/provider hoặc AGENTS.md. Tài liệu/skills baseline từ STRUCT-01C giữ nguyên trong task này; các matrix còn nói read path chưa nối là trạng thái trước 01B, hiện trạng mới theo section này.

### Tests và evidence

- `php artisan test --filter=InvoiceQueryApiTest`: 31 PASS, 191 assertions, exit 0, gồm 8 regression tests mới.
- `php artisan test --filter='InvoiceReadRepositoryTest|InvoiceMapperTest'`: 9 PASS, 63 assertions, exit 0.
- `php artisan test --filter=Invoice`: 92 PASS, 480 assertions, exit 0.
- Full suite lần đầu: 439 PASS/1 FAIL do Auth test cũ assertJsonMissingPath(code) trên Host read 403. Không phải session bị hủy; assertion dừng trước kiểm tra /api/me. Owner đã duyệt đổi đúng assertion sang assertJsonPath(code, INVOICE_READ_FORBIDDEN), giữ phần kiểm tra session/public routes.
- `php artisan test --filter=role_denial_keeps_the_session_and_public_routes_remain_public`: 1 PASS, 6 assertions, exit 0.
- Full suite cuối `php artisan test`: 440 PASS, 2373 assertions, exit 0, 22.51s. Không skip.
- `php artisan route:list --path=api`: 16 routes/URL như cũ. Syntax 10 file PHP theo scope gốc PASS; php -l Auth test bổ sung và final audit áp dụng khi kết thúc. `git diff --check`, UTF-8/whitespace, hash audit PASS.
- Hash baseline tại ignored .agent-workspace/tv3/inv-arch-01b-validation/baseline.json và store.sha256 dùng kiểm tra phạm vi/create body, không phải file report. Baseline bao gồm thay đổi tồn tại từ các task trước; giữ nguyên chúng. Lệnh chạy test bằng php backend/artisan từ root đầu tiên không tìm thấy relative PHPUnit vendor; đã chạy lại đúng backend, không tính lần sai là PASS.
- Tests mới đi qua routes thật; kiểm chứng role trước malformed input/lookup, 404 parity, malformed query, scalar defaults/clamp, exact legacy resource parity, empty detail/list, DTO pagination, 0 query khi Resource serialize, role check trước repository khi ngoài HTTP và GET không đổi invoices/items/payment.
- Suite dùng SQLite in-memory và synthetic fixtures; không reset/reseed MySQL development. Không chạy Frontend lint/build vì không sửa frontend; browser/UI và MySQL behavior NOT VERIFIED, không suy từ API tests rằng UC giao diện đã đóng. Auth runtime giữ nguyên, chỉ test expectation đổi theo contract approved. Evidence là tool output trên chat.

| AC | Kết quả |
|---|---|
| INVREAD-B01 — read Controller/Service qua Repository/DTO/Mapper | PASS — DTO paginator/service test + source review không Eloquent query |
| INVREAD-B02 — HTTP validation/authorization đúng tầng/thứ tự | PASS — Form Request tests, wrong-role trước malformed input |
| INVREAD-B03 — ownership/401/403/privacy-404 | PASS — existing + new HTTP tests và service ngoài HTTP |
| INVREAD-B04 — filters/search/order/pagination tương thích | PASS — query API/repository tests; defaults/clamp/search 0/unknown status |
| INVREAD-B05 — success response fields/envelope/decimal/null/items | PASS — exact legacy Resource parity Host/Customer, list/detail/empty |
| INVREAD-B06 — read error codes qua global handler, status/message giữ | PASS — exact 404 JSON và 403 code cases |
| INVREAD-B07 — create/Auth/Profile/API regression | PASS — full 440 tests; create store hash nguyên vẹn; Auth expectation mở scope được duyệt |
| INVREAD-B08 — GET không mutate, mapper/resource không query | PASS — before/after DB arrays và empty query log serialization |
| INVREAD-B09 — tests/syntax/diff/scope, URL không đổi | PASS — scope 5 new/7 modified sau approval bổ sung, 16 routes |

Recommended Next Task: INV-ARCH-01C — lên Mini Plan Frontend invoice mapper và list/detail consumer regression, dùng contract Backend read đã chuyển. Chưa bắt đầu. Chưa stage/commit/push; dừng sau Task Result.

## INV-ARCH-01C — Frontend invoice mapper/read service

Owner nghiệm thu INV-ARCH-01B và duyệt 01C (4 file mới, 2 file sửa). 01C TECHNICAL PASS ở phạm vi frontend mapper/service/contract tests và build; owner acceptance PENDING, parent INV-ARCH-01 vẫn mở. Không suy acceptance INV-ARCH-01A hoặc browser nghiệm thu từ task này.

### Implementation và scope

4 file mới: frontend/src/mappers/invoiceMapper.js; frontend/src/services/invoiceReadService.js; frontend/tests/invoiceMapper.test.js; frontend/tests/invoiceReadService.test.js.

2 file sửa do task: frontend/src/services/invoiceService.js và checklist này. Read entry points giữ exports/signatures, delegate createInvoiceReadService(invoiceApi) -> API module dùng Axios singleton. createHostInvoice/formatVnd/parseInvoiceError so exact source baseline không đổi. Không sửa Pages/Components, Axios/Auth/API modules, Backend, routes, package/dependencies hoặc AGENTS.md.

Mapper list/detail giữ snake_case fields và envelope Page đang dùng; giữ decimal strings không tính lại tổng, nullable dates/description, items detail (kể cả []), list không tự thêm items. Deep-copy metadata/links/additional fields và invoice/item projection mới, không mutate input. Strict response guard cho object/array/ID/decimal/date/pagination required types; malformed success ném safe normalized contract error INVALID_INVOICE_RESPONSE, không thay bằng data=[] và không chứa raw payload. Unknown payment status string giữ nguyên, không tự đổi UNPAID. Date chỉ kiểm tra shape/parseability rồi giữ string gốc; mapper không xác minh nghiệp vụ/thời điểm hay tổng tài chính.

Read factory giữ filter ALL/search trim/page/perPage và signal; chỉ map sau HTTP resolve. HTTP/network/cancel rejection giữ identity, Auth interceptors xử lý trước và không bị thêm logout/retry. Không mutation trong read service. Consumer regression là envelope/service/compile compatibility; chưa có browser/DOM gate.

### Tests và evidence

- `node --test tests/invoiceMapper.test.js tests/invoiceReadService.test.js`: 8/8 PASS, 0 skipped, exit 0.
- `node --test tests/*.test.js`: 67/67 PASS, 0 skipped, exit 0 (Node v22.23.4); bao gồm Auth/Registration/Profile và API tests trước.
- `npm run lint`: exit 0, 0 errors/3 react-refresh warnings cũ ở ui/badge.jsx, button.jsx, sidebar.jsx.
- `npm run build`: exit 0, 3317 modules; warning bundle >500 kB và plugin timing diagnostics; không phải browser evidence.
- Tests kiểm chứng deep-frozen input, independent expected values, large decimal/zero/null/items/unknown status, empty page, malformed fields/envelopes, four endpoints/filter/options, safe contract error, unchanged HTTP rejection, fields 422, 401 vs role403 vs account403, Auth version, cancellation và không retry.
- Hash baseline tại ignored .agent-workspace/tv3/inv-arch-01c-validation/baseline.json và protected-functions.json. Create/helpers nguyên văn; Axios/Auth/API modules/Pages/AGENTS và files ngoài scope giữ baseline ngoại trừ thay đổi ngoài task được nêu dưới đây. UTF-8/whitespace audit và git diff --check PASS, gồm file untracked.
- PHPUnit NOT RUN vì không sửa Backend trong task; browser/real Backend HTTP/MySQL NOT VERIFIED. Kết quả 440 tests của 01B là historical, không khẳng định backend hiện tại PASS sau external deletion.

### Thay đổi ngoài task phát hiện khi audit

backend/app/Http/Controllers/Controller.php tồn tại ở baseline nhưng bị xóa trong lúc thực thi, không bởi thao tác chỉnh sửa FE-01C của agent. Hash/scope audit ban đầu báo thêm path này; đã kiểm tra git diff xác nhận deleted. Giữ nguyên thay đổi bên ngoài, không tự khôi phục. Base class được Controllers sử dụng nên cần xử lý/kiểm tra Backend trước gate tích hợp tiếp theo; frontend test/build PASS không chứng minh Backend hoạt động. Agent đã thông báo trên chat. Scope changes do agent vẫn đúng 4 new + 2 modified; không gộp deletion này thành deliverable.

| AC | Kết quả |
|---|---|
| INVREAD-C01 — 4 read operations dùng mapper production | PASS — read factory tests + entry point wiring/build |
| INVREAD-C02 — Page envelope/snake_case tương thích | PASS — exact fixture parity, metadata/links/additional preserved |
| INVREAD-C03 — decimal/null/items/date không mutate/tính lại | PASS — frozen fixtures và independent expected snapshots |
| INVREAD-C04 — malformed response không giả empty/success | PASS — mapper/service rejection tests, safe contract error |
| INVREAD-C05 — filters/options/signal/HTTP errors giữ behavior | PASS — Axios adapter service integration và cancel/network tests |
| INVREAD-C06 — Auth interceptors không đổi | PASS — scoped hash và actual interceptor tests, role code không logout |
| INVREAD-C07 — create/helpers không regression | PASS — exact source hashes và existing API tests; chưa browser/create end-to-end mới |
| INVREAD-C08 — tests/lint/build/scope đạt | PASS frontend task — 67 tests, lint/build; external Controller deletion được tách rõ, không claim whole-repo unchanged |
| INVREAD-C09 — checklist phân biệt evidence và browser limits | PASS — scope/evidence/historical/external dependency ghi rõ |

Recommended Next Task: trước khi gate Backend tiếp theo, xác nhận và xử lý external base Controller deletion bằng scope được owner cho phép; sau đó review acceptance parent INV-ARCH-01 và lên Mini Plan INV-ARCH-02 (create path). Không tự khôi phục hoặc bắt đầu task mới; chưa stage/commit/push. Dừng sau Task Result.


## INV-ARCH-01 — owner acceptance và Controller restoration

Owner xác nhận nghiệm thu Invoice read A/C và đóng parent INV-ARCH-01 trên chat. Trạng thái hiện hành: 01A, 01B, 01C và parent ACCEPTED. Những trạng thái PENDING/TODO trong sections lịch sử phía trên phản ánh thời điểm báo cáo cũ và được thay thế bởi xác nhận này. Việc đóng task refactor không đóng các use case Invoice hoặc browser/MySQL gates còn thiếu.

Owner tự khôi phục backend/app/Http/Controllers/Controller.php. Kiểm tra Composer autoload sau khôi phục: cả 7 Controller Auth/Profile/Invoice/Review nạp thành công, exit 0. Không chạy lại full PHPUnit trong lượt xác nhận; 440 Backend tests và 67 Frontend tests/lint/build vẫn là evidence lịch sử của task tương ứng.

Owner yêu cầu xem xét đơn giản hóa Http/Requests và Http/Resources vì trùng tầng với DTO. Chưa thay đổi kiến trúc, skill hoặc code; cần Mini Plan và duyệt phạm vi riêng, giữ validation/authorization/JSON/pagination contract. Chỉ cập nhật checklist trong lượt acceptance này.


## SIMPLIFY-01A — một InvoiceRepository, constructor DI

Owner duyệt riêng 01A. Status: IMPLEMENTED / TECHNICAL PASS / acceptance PENDING. INV-ARCH-01 A/B/C và parent vẫn ACCEPTED theo xác nhận trước; simplification là task mới, không viết lại lịch sử nghiệm thu.

| Đóng | Task | Trạng thái hiện hành |
|---|---|---|
| [x] | SIMPLIFY-01A — concrete InvoiceRepository, bỏ interface/binding | IMPLEMENTED / PASS / ACCEPTED (owner trên chat trước plan 01B) |
| [x] | SIMPLIFY-01B — Invoice read Validator/DTO serialization, bỏ bốn lớp HTTP read | IMPLEMENTED / PASS / ACCEPTED (owner trước plan 01C) |
| [x] | SIMPLIFY-01C — đồng bộ kiến trúc/local rules/5 skills | IMPLEMENTED / PASS / ACCEPTED (owner trước plan INV-ARCH-02) |

### Implementation và file scope

- Rename app/Repositories/Invoice/InvoiceReadRepository.php -> InvoiceRepository.php; class concrete final, bỏ implements/import interface; giữ bốn phương thức đọc, ownership, grouped search, ordering, pagination và eager loading. Chưa bổ sung write methods; create migration vẫn thuộc INV-ARCH-02.
- Xóa app/Repositories/Invoice/InvoiceReadRepositoryInterface.php.
- Sửa app/Services/Invoice/InvoiceQueryService.php: constructor nhận trực tiếp InvoiceRepository; vẫn constructor injection, không tự new Repository trong Service.
- Sửa app/Providers/AppServiceProvider.php: bỏ hai imports và bind Invoice; giữ provider/register/boot.
- Rename tests/Feature/Invoice/InvoiceReadRepositoryTest.php -> InvoiceRepositoryTest.php: container tự resolve concrete class khi không đăng ký binding, giữ tests query/ownership/metadata/mapping.
- Sửa tests/Feature/Invoice/InvoiceQueryApiTest.php: thay mock interface bằng concrete Repository và query log; bốn thao tác role sai đều trả 403/code hiện hành và không chạy DB query, query logging tắt trong finally.
- Sửa checklist này. Tổng: 2 rename + 1 delete + 4 modified, tính checklist. Không sửa API routes/Controller/Form Request/Resource/Common/DTO/Mapper/Auth/Frontend/schema/config/dependencies/AGENTS.md. Không sửa architecture/skills ở 01A; conventions interface trong docs/skills là bản trước, sẽ đồng bộ ở 01C theo duyệt riêng. Quyết định owner 01A được ưu tiên cho InvoiceRepository hiện hành.

### Tests thực chạy

- php artisan test --filter='InvoiceRepositoryTest|InvoiceQueryApiTest': 36 PASS, 223 assertions, exit 0.
- php artisan test: 440 PASS, 2371 assertions, exit 0, 23.06s. Không skip. Count assertions khác historical 2373 vì bỏ Mockery expectations và thay bằng concrete container/query evidence; không xóa test case nghiệp vụ.
- php artisan route:list --path=api: 16 routes, exit 0, URL giữ nguyên.
- php -l: 5 PHP files mới/sửa (repository, provider, query service, hai test files) PASS.
- No obsolete InvoiceReadRepository references in backend/app hoặc backend/tests. Baseline hash audit 539 entries tại ignored .agent-workspace/tv3/simplify-01a-validation/baseline.json: đúng scope, tất cả files khác nguyên vẹn. git diff --check/UTF-8/whitespace PASS.
- Suite dùng SQLite in-memory theo phpunit.xml và test safety guards; không reset/reseed hoặc truy cập MySQL development. Frontend tests/lint/build NOT RUN vì không sửa frontend. Browser/MySQL locking/concurrency NOT VERIFIED; không suy từ PHPUnit thành E2E.

| AC | Kết quả và evidence |
|---|---|
| SIM-A01 — một concrete Repository, không interface/binding Invoice | PASS — inventory/search + container test không bound và resolve thành công |
| SIM-A02 — constructor DI giữ nguyên cơ chế | PASS — QueryService concrete typed injection + HTTP tests resolve qua container |
| SIM-A03 — scoped queries/filter/order/pagination/detail không đổi | PASS — repository + InvoiceQueryApi tests |
| SIM-A04 — role denial trước query, API/Auth/create không regression | PASS — query log rỗng cả bốn service operations role sai, full 440 tests |
| SIM-A05 — phạm vi/checklist/test/syntax đúng | PASS — 539 baseline hash audit, 5 php lint, diff check |

Recommended Next Task: nghiệm thu SIMPLIFY-01A rồi duyệt SIMPLIFY-01B theo Mini Plan đã trình bày (cần chốt lại danh sách file/AC nếu thay đổi). Không tự triển khai 01B/01C hoặc INV-ARCH-02. Chưa stage/commit/push; dừng sau Task Result trên chat.


### SIMPLIFY-01A — owner acceptance

Owner nghiệm thu SIMPLIFY-01A trực tiếp trên chat, trước yêu cầu Mini Plan SIMPLIFY-01B. Trạng thái hiện hành 01A ACCEPTED; dòng acceptance PENDING trong báo cáo lịch sử phía trên được thay thế bởi xác nhận này. 01B chỉ đang planning, chưa code; 01C chưa triển khai. Lượt này chỉ ghi acceptance vào checklist, không chạy lại application tests; 440 tests/2371 assertions là evidence lượt 01A.


## SIMPLIFY-01B — Invoice read Validator và DTO serialization

Owner duyệt đúng 1 file mới, 4 file xóa, 10 file sửa. Status IMPLEMENTED / TECHNICAL PASS / acceptance PENDING. SIMPLIFY-01A và INV-ARCH-01 A/B/C/parent vẫn ACCEPTED. Không tự đóng UC/browser/MySQL gates hoặc bắt đầu 01C.

### Implementation và scope

- Mới backend/app/Validators/Invoice/InvoiceReadValidator.php: authorize nullable actor/expected role, validateQuery(array) dùng Laravel Validator/rules/messages cũ. Không nhận HTTP Request, không query/log/calculate. Role check trước query validation/ID mapping; middleware vẫn sở hữu guest 401. Service giữ role check khi gọi ngoài HTTP.
- Xóa đúng bốn classes Invoice read: Http/Requests/Invoice/InvoiceQueryRequest.php, InvoiceDetailRequest.php; Http/Resources/Invoice/InvoiceReadResource.php, InvoiceItemReadResource.php.
- Sửa hai Invoice Controllers Host/Customer: constructor DI validator; index/show nhận Illuminate Http Request, authorize rồi validate query/map DTO/gọi service; response qua Common helpers nhận DTO/paginator trực tiếp. store() của Host so exact source baseline không đổi.
- Sửa DTOs/Invoice/Responses/InvoiceResponse.php và InvoiceItemResponse.php: JsonSerializable + explicit public field projection. Decimal/date/null giữ nguyên; items null bỏ field list, [] giữ detail; nested items serialize rõ. Không query/mutate/recompute hoặc xuất Model raw.
- Sửa Common/ApiResponse.php: nhận JsonSerializable và LengthAwarePaginator, giữ legacy array/JsonResource branches. Sửa Common/PageResponse.php: paginator DTO có data/links/meta tương đương Laravel PaginatedResourceResponse, giữ nhánh ResourceCollection/hook/additional/header cũ.
- Sửa tests/Feature/Invoice/InvoiceQueryApiTest.php, tests/Feature/Api/ApiResponseTest.php, tests/Unit/InvoiceMapperTest.php: 6 test methods mới, update no-query serialization test và giữ regression cũ.
- Sửa checklist này. Tổng 1 mới + 4 xóa + 10 sửa. Giữ nguyên Repository/QueryService/Request Mapper/Request DTO, InvoiceResource/InvoiceItemResource cho create, Auth/Profile/Review code, routes/bootstrap/global handler, schema/config/dependencies, Frontend, AGENTS.md và architecture/skills/local rules. Đồng bộ conventions thuộc SIMPLIFY-01C chưa làm.

### Tests thực chạy và fidelity

- Focused php artisan test --filter='InvoiceQueryApiTest|InvoiceMapperTest|ApiResponseTest': 48 PASS, 332 assertions, exit 0.
- Full php artisan test: 446 PASS, 2447 assertions, không skip, exit 0, 22.49s. Includes Auth/Profile/create/Review regressions.
- Frontend node --test tests/*.test.js: 67/67 PASS, 0 fail/cancel/skip, exit 0.
- php artisan route:list --path=api: 16 routes, exit 0. php -l cho 10 PHP files mới/sửa PASS.
- git diff --check và UTF-8/whitespace PASS; baseline hash scope audit đúng 1 new/4 deleted/10 modified; store exact source PASS. Baseline/store.txt ở ignored .agent-workspace/tv3/simplify-01b-validation là scope evidence, không file plan/result.
- Không còn InvoiceQueryRequest/InvoiceDetailRequest/InvoiceReadResource/InvoiceItemReadResource trong backend/app hoặc tests. rg no-match exit 1 là expected absence, không application failure.
- Một lần gọi artisan nhầm từ repo root không tìm thấy file (exit 1); đã chạy lại đúng backend và có focused/full results phía trên, không tính lần nhầm là PASS.
- Source-confirmed: rules/messages/error codes, role-before-input behavior, Request Mapper defaults/clamp, DTO fields và legacy create Resources. Pagination parity test so DTO branch với Laravel ResourceCollection thật trên cùng fixture độc lập; empty/normal/out-of-range/per-page-one, query parameters/fragment/status, immutable paginator items. Existing legacy additional/summary/header-hook tests giữ PASS.
- Tests mới kiểm chứng DTO JSON exact fields/no internal note/large decimal/zero/null/items, GET JSON body không override query, validated fields loại unknown input, exact validation field messages, role checks và no DB query. Serialization không query và GET không mutate giữ test cũ.
- SQLite in-memory/test safety guards, không reset/reseed hoặc truy cập MySQL development. Frontend source không đổi; lint/build NOT RUN theo plan. Browser/real HTTP Frontend-to-Backend/MySQL concurrency NOT VERIFIED. Node tests là service/contract regression, không browser evidence.

| AC | Kết quả/evidence |
|---|---|
| SIM-B01 — xóa bốn lớp HTTP read, không còn references | PASS — file inventory + app/tests search |
| SIM-B02 — Controller DI validator, không query/rules/business logic | PASS — source review + validator/HTTP tests |
| SIM-B03 — 401/403/404/422 và order/error codes giữ nguyên | PASS — guest/role-before-malformed/privacy/field-errors regressions |
| SIM-B04 — filters/default/clamp/query-only | PASS — scalar regressions + GET body isolation tests |
| SIM-B05 — DTO list/detail JSON giữ fields/decimal/null/items | PASS — exact legacy Resource parity + unit explicit expectations |
| SIM-B06 — pagination envelope và legacy branch behavior | PASS — boundary parity + additional/summary/header-hook/status tests |
| SIM-B07 — serialize no queries, GET no mutation | PASS — query log + before/after DB tests |
| SIM-B08 — store nguyên văn, Backend/Node regression | PASS — source baseline + 446 PHP/67 Node tests |
| SIM-B09 — syntax/diff/scope/checklist | PASS — 10 php lint + final scope/hash/whitespace audit |

Recommended Next Task: owner nghiệm thu SIMPLIFY-01B; sau đó lập/chốt Mini Plan SIMPLIFY-01C cập nhật 9 file architecture/local rules/5 skills/checklist theo concrete Repository + Validator + DTO serialization. Giữ tương thích legacy module chưa migrate. Chưa stage/commit/push; dừng sau Task Result trên chat.


### SIMPLIFY-01B — owner acceptance

Owner nghiệm thu SIMPLIFY-01B trực tiếp trên chat trước yêu cầu Mini Plan 01C. Trạng thái hiện hành 01B ACCEPTED; acceptance PENDING ở section delivery là lịch sử và được thay thế bởi xác nhận này. 01A và INV-ARCH-01 A/B/C/parent vẫn ACCEPTED. 01C chỉ đang planning, chưa sửa architecture/local rules/skills hoặc application code. 446 Backend tests/2447 assertions và 67 Node tests là evidence lượt 01B, không chạy lại ở lượt ghi acceptance này.


## SIMPLIFY-01C — kiến trúc/local rules/5 skills hiện hành

Owner duyệt đúng 9 file. Status IMPLEMENTED / TECHNICAL PASS / owner acceptance PENDING. 01A/B đã ACCEPTED; INV-ARCH-01 A/B/C/parent giữ ACCEPTED. Plan approval 01C không phải delivery acceptance. Không bắt đầu INV-ARCH-02 hoặc migration khác.

### Implementation và scope

9 file sửa: ARCHITECTURE.md, docs/ai-frontend-development-guide.md, AGENTS.local.md, .agents/skills/{stayhub-task-planning,stayhub-laravel,stayhub-react,stayhub-database,stayhub-verification}/SKILL.md và checklist này. Không thêm/xóa/rename project file, giữ tên/description/frontmatter skills và metadata/policy/README hiện có. AGENTS.md và code ứng dụng không đổi.

Architecture cập nhật sơ đồ, cây thư mục và trách nhiệm: concrete Repository một file/domain, constructor DI/container auto-resolution, không interface/binding mặc định; chỉ đề xuất abstraction/factory khi có nhu cầu resolve/config/thay implementation rõ trong scope. Controller -> Validator -> Request Mapper/DTO -> Service/Repository -> Mapper/Response DTO JsonSerializable -> Common response. Validator array/scalar/actor, role trước malformed input/lookup; Service giữ quyền ngoài HTTP, Repository giữ ownership query. Không query/tính tiền/log trong Mapper/DTO, không thêm Form Request/Resource song song cho target mới.

DTO explicit projection giữ decimal/date/null/items; Common giữ data/meta/links và legacy Resource additional/summary/header hooks theo branch. Global renderer vẫn 9 Invoice/Review named routes; không đổi Auth/Profile/Web/headers/exception reporting. Logging meaningful workflow success sau commit, không query INFO rải rác/log-and-throw/secrets.

Current-state matrix ghi Invoice read Backend/Frontend migrated, create/Review/Auth/Profile còn implementation hiện hữu. Một Repository tên chung không chứng minh write path đã chuyển. Không xóa toàn bộ Http/Requests/Resources; giữ Invoice create Resources và module chưa được duyệt migration. Target chung không mở ownership TV1/TV2; AGENTS.md được giữ theo owner, hướng dẫn cũ đọc cùng quyết định hiện hành.

Frontend guide/React skill ghi invoiceMapper.js và invoiceReadService.js đã có, malformed success không fake empty, giữ snake_case/meta/links/decimal/null/unknown status/HTTP errors/cancellation/Auth version. Không tự thêm libraries hoặc đổi URL/field/envelope. Không giả định skill auto-selection đã được kiểm thử.

### Verification thực hiện

- quick_validate.py của skill-creator chạy bằng venv UTF-8 hiện có cho 5 skill: 5/5 PASS, mỗi process exit 0. Đây là structural validation; không chứng minh auto-selection hoặc mọi hành vi agent.
- Local links/anchors trên cả 9 file: PASS sau sửa hai link lịch sử InvoiceReadRepository/interface đã không còn tồn tại. Giữ tên cũ thành plain historical text và liên kết concrete Repository hiện hành; không xóa lịch sử/đổi counts test.
- Static source inventory: 23 paths hiện có, 6 obsolete paths không tồn tại, concrete constructor DI/JsonSerializable signatures/no Invoice binding đúng code. Không chạy PHP/DB trong inventory audit. Một lần Python source audit dùng encoding mặc định Windows bị UnicodeDecodeError; đã chạy lại explicit UTF-8 PASS.
- Self-review walkthrough, không phải agent/runtime test: (1) Invoice read giữ concrete Repository/validator/DTO/Common, (2) Invoice create phải inventory store/snapshots/transaction và lập scope INV-ARCH-02, không sinh interface hoặc xóa legacy response trước parity, (3) Review phải inventory Requests/Resources/COMPLETED/ownership/duplicate và dependencies trước migration. Không case nào cấp quyền ngoài task.
- Baseline hash tại ignored .agent-workspace/tv3/simplify-01c-validation/baseline.json gồm 535 entries (cả AGENTS.local.md). Final audit: đúng 9 modified; không new/delete; AGENTS.md/application/metadata nguyên vẹn. UTF-8/whitespace và git diff --check PASS; links/content hiện hành đã rà soát.
- Không chạy PHPUnit/Node/lint/build trong task docs-only. Evidence 446 Backend tests/2447 assertions, 67 Node tests của SIMPLIFY-01B là historical. Browser/MySQL concurrency/skill auto-selection trong chat mới NOT VERIFIED, không chuyển thành skipped success.

| AC | Kết quả/evidence |
|---|---|
| SIM-C01 — sơ đồ/naming/paths khớp concrete Repository | PASS — architecture mục 4/13/14 và source inventory |
| SIM-C02 — constructor DI rõ, không bắt buộc interface/binding | PASS — 5 skills/local rules thống nhất; source review QueryService/AppServiceProvider |
| SIM-C03 — Validator/DTO/Common responsibilities/auth/JSON đúng | PASS — source signatures/projection/pagination và content review |
| SIM-C04 — migrated/legacy matrix chính xác | PASS — read Backend/Frontend migrated; create/Review/other-owner/browser/MySQL tách rõ |
| SIM-C05 — 5 skills nhất quán và validator đạt | PASS — 5/5 quick_validate exit 0 + content review |
| SIM-C06 — links/anchors/walkthrough không dẫn về cấu trúc cũ | PASS — links audit sau correction + ba self-review walkthrough |
| SIM-C07 — checklist giữ lịch sử/acceptance | PASS — A/B ACCEPTED, C PENDING; counts/commands lịch sử giữ nguyên |
| SIM-C08 — đúng 9 file, AGENTS.md/code giữ nguyên | PASS — 535-entry hash scope audit, không project new/delete |

Recommended Next Task: owner nghiệm thu SIMPLIFY-01C; sau đó lên Mini Plan INV-ARCH-02 chuyển Invoice create theo target mới, giữ snapshot/atomicity/decimal/Auth/API và consumer UI hiện có. Chưa triển khai; không stage/commit/push. Dừng sau Task Result chi tiết trên chat; không tạo file plan/result.


### SIMPLIFY-01C — owner acceptance

Owner nghiệm thu SIMPLIFY-01C trên chat trước yêu cầu Mini Plan INV-ARCH-02. Trạng thái hiện hành SIMPLIFY-01 A/B/C ACCEPTED; delivery PENDING trong sections cũ là lịch sử và được thay thế bởi xác nhận này. INV-ARCH-01 vẫn ACCEPTED; INV-ARCH-02 chỉ planning, chưa sửa application code. Lượt này chỉ ghi acceptance vào checklist, không chạy lại validators/application suites; evidence 01C và 01B giữ đúng lượt nguồn.


## INV-ARCH-02A — create Repository boundary và exception riêng

Owner duyệt 02A, không duyệt triển khai 02B/C ở lượt này. Status IMPLEMENTED / TECHNICAL PASS / owner acceptance PENDING. Parent INV-ARCH-02 IN PROGRESS/PARTIAL; INV-ARCH-01 và SIMPLIFY-01 A/B/C vẫn ACCEPTED.

| Đóng | Task con | Implementation / verification / acceptance |
|---|---|---|
| [x] | INV-ARCH-02A — Service queries/persistence qua InvoiceRepository, exception riêng | IMPLEMENTED / PASS / ACCEPTED (owner trước plan 02B) |
| [ ] | INV-ARCH-02B — DTO/Mapper/Validator + Symfony attributes, chia B.1/B.2/B.3 | IN PROGRESS / PARTIAL / PENDING; B.1 pilot technical PASS |
| [ ] | INV-ARCH-02C — HTTP DTO/Common, consumer regression và đồng bộ trạng thái tài liệu | TODO / NOT VERIFIED / PENDING |

### Implementation và scope

1 file mới: backend/app/Exceptions/Invoice/DuplicateInvoiceException.php, canonical DomainException subclass, tự autoload; xóa conditional class declarations trong Service/Validator. Không class_alias hoặc conditional fallback.

7 file sửa: backend/app/Repositories/Invoice/InvoiceRepository.php; backend/app/Services/Invoice/InvoiceService.php; backend/app/Validators/Invoice/InvoiceCreationValidator.php; backend/app/Http/Controllers/Invoice/HostInvoiceController.php; backend/tests/Feature/Invoice/InvoiceRepositoryTest.php; backend/tests/Feature/Invoice/InvoiceCreationTest.php; checklist này.

InvoiceRepository có sáu methods mới: findBookingForInvoiceWithLock, invoiceExistsForBooking, invoiceCodeExists, createInvoice, createInvoiceItem, loadInvoiceItems. Giữ bốn read methods/scopes/filter/order cũ. Booking lookup giữ customer/room.property/bookingServices eager loading và lockForUpdate; repository không tự authorize/transaction/commit/tính tiền/quyết định workflow. Attributes được Service chuẩn bị từ trusted snapshots như trước.

InvoiceService nhận InvoiceRepository bắt buộc qua constructor DI, container auto-resolve không binding; chuyển các Eloquent query/create/load trực tiếp của Service sang repo. Giữ createInvoice(int, User): Invoice, DB::transaction, Calculator, order/exception messages, generate code algorithm, handleQueryException classification và DB::afterCommit logging. Dependencies Validator/CalculationService/logger legacy optional sẽ chuyển ở 02B; chưa tuyên bố workflow fully migrated.

CreationValidator ở 02A chỉ bỏ conditional exception/import canonical exception và giữ nguyên nghiệp vụ/query hiện hữu. Query duplicate trong Validator sẽ chuyển ra ở 02B cùng thay đổi flow validation/DTO, đúng giới hạn plan 02A. Controller chỉ thay import exception; class body và mọi action giữ exact baseline. Test anonymous Service cho invoice-code collision cập nhật constructor thêm Repository.

Không sửa routes/Auth/global handler/bootstrap/providers, schema/Models/Calculator, Request/Response DTO/Mapper/Common/Resources, Frontend hoặc AGENTS.md/architecture/skills. Tài liệu current-state sẽ đồng bộ ở 02C; checklist này là trạng thái 02A hiện hành. Không xóa Invoice create Resources.

User hỏi Mapper/Validator có thể gom theo domain như Repository: có thể; đề xuất planning 02B tái sử dụng một InvoiceMapper thay vì mặc định tạo InvoiceCreationMapper, cân nhắc gom read/create validator thành InvoiceValidator và giữ calculation validator riêng vì quy tắc số/tiền. Đây là đề xuất cấu trúc cần chốt trong Mini Plan 02B, chưa đổi tên/gom lớp hoặc mở scope trong 02A.

### Tests và evidence

- Added 6 tests: repository snapshot lookup/eager-loading/missing/existence/caller transaction level; exact header/items persistence; real NOT NULL failure rollback controlled by caller; canonical exception autoload; real booking unique error translated with previous QueryException; other DB NOT NULL error rethrown, rollback unchanged. Controlled competing write is same-transaction fault injection, không chứng minh concurrent clients/InnoDB locking.
- Focused đầu: 40 PASS/1 FAIL do assertion test mới coi payment_status là enum thay vì string thực tế. Sửa đúng assertion test, không sửa production để khớp test.
- Focused cuối php artisan test --filter='InvoiceRepositoryTest|InvoiceCreationTest|HostInvoiceApiTest|InvoiceCreationValidatorTest': 41 PASS, 240 assertions, exit 0.
- Full php artisan test: 452 PASS, 2489 assertions, không skip, exit 0, 22.96s. Includes Auth/Profile/read/create/Review regression.
- node --test tests/*.test.js: 67/67 PASS, fail/cancel/skip 0, exit 0. Frontend không sửa; lint/build NOT RUN. Node regression không phải browser/real-backend E2E.
- php artisan route:list --path=api: 16 routes, exit 0. php -l cả 7 PHP files mới/sửa PASS. Search chỉ có một class DuplicateInvoiceException, không old FQCN trong app/tests.
- Source audit: Service không còn Booking::/Invoice:: hoặc relation create/load query trực tiếp; thân HostInvoiceController exact baseline; handleQueryException/logInfo methods exact source trước thay đổi. Log afterCommit registration/transaction/snapshots/calculation giữ nguyên; chưa thêm runtime gate logging mới (02B).
- Hash baseline 535 entries tại ignored .agent-workspace/tv3/inv-arch-02a-validation/baseline.json và protected controller-body.txt. Final scope đúng 1 new/7 modified, không deleted; tất cả files khác nguyên vẹn. UTF-8/whitespace và git diff --check PASS.
- SQLite in-memory với safety guards; không reset/reseed/truy cập MySQL development. MySQL concurrency/locks, browser và runtime commit logging gate mới NOT VERIFIED. Parent AC chỉ được đóng sau B/C/gates tương ứng.

| AC 02A | Kết quả/evidence |
|---|---|
| INV2-A01 — Service create queries/persistence qua một Repository | PASS — source audit + focused/full tests; Validator query còn thuộc 02B |
| INV2-A02 — Repository giữ transaction ownership ở Service | PASS — transactionLevel tests, actual failed item rollback và source review DB::transaction |
| INV2-A03 — canonical exception và DB error classification | PASS — autoload/unique/NOT NULL/invoice-code collision tests; single class search |
| INV2-A04 — API/Auth/read/create/snapshot/decimal không regression | PASS — 452 tests + Controller body baseline + 67 Node tests |
| INV2-A05 — constructor DI và không binding mới | PASS — container resolves required Repository, HTTP/Service integration tests |
| INV2-A06 — tests/syntax/checklist/scope đạt | PASS — 1 new/7 modified, 7 PHP lint, baseline/diff/UTF-8 audit |

Recommended Next Task: owner nghiệm thu 02A rồi lên/chốt Mini Plan 02B, giải quyết câu hỏi một Mapper/Validator theo domain, chuyển Validator khỏi query và required workflow dependencies/DTO. Không tự triển khai 02B/C; chưa stage/commit/push. Dừng sau Task Result chi tiết trên chat.


### INV-ARCH-02A — owner acceptance

Owner nghiệm thu INV-ARCH-02A trên chat trước yêu cầu Mini Plan 02B. Trạng thái hiện hành 02A ACCEPTED; parent 02 vẫn PARTIAL, 02B/C chưa triển khai. 452 Backend tests/2489 assertions và 67 Node tests là evidence lượt 02A, không chạy lại ở lượt ghi acceptance. 02B được yêu cầu lập plan gom Mapper/Validator theo domain, đưa query khỏi Validator, thêm DTO và giữ constructor DI/API/Auth/nghiệp vụ; chưa sửa application code.


## INV-ARCH-02B.1 — Symfony dependencies/adapters và pilot attributes

Owner duyệt hướng Symfony và B.1, chưa duyệt triển khai B.2/B.3. Status IMPLEMENTED / TECHNICAL PASS (pilot functionality) / owner acceptance PENDING. Security audit còn baseline advisories như phần dưới, không claim clean audit/production readiness. Parent INV-ARCH-02 và 02B vẫn PARTIAL; 02A ACCEPTED.

| Đóng | Checkpoint | Trạng thái |
|---|---|---|
| [x] | INV-ARCH-02B.1 — dependencies/adapter/Model-DTO attributes/mapping pilot | IMPLEMENTED / TECHNICAL PASS / ACCEPTED (owner trước plan B.2) |
| [ ] | INV-ARCH-02B.2 — chia 2A gom lớp/input attributes và 2B workflow/query/typed DI | IN PROGRESS / PARTIAL; 2A technical PASS, 2B chưa làm |
| [x] | INV-ARCH-02B.3 — tích hợp Model validation vào create workflow và full regression | IMPLEMENTED / PASS / ACCEPTED |

### Implementation và files

5 file mới:
- backend/app/Validation/AttributeValidator.php: Symfony ValidatorInterface constructor DI; errors(object, groups, fieldMap) chỉ message/path, không raw invalid value; validateHttp -> Laravel ValidationException 422 với alias fields; validateDomain -> InvalidArgumentException với public message do boundary hiện hành cung cấp. Constraint authors phải dùng safe explicit messages; adapter không tự sanitize arbitrary message interpolation của constraint tương lai.
- backend/app/Mapping/EloquentObjectMapper.php: constructor-injected ObjectMapperInterface; map Model qua developer-owned explicit loaded-attribute allowlist sang stdClass rồi engine. Reject unknown fields/relations trước getAttribute, không lazy-load, không map toàn bộ Model hoặc lấy allowlist từ user. Đây là Eloquent compatibility adapter trong pilot đã duyệt.
- backend/app/DTOs/Invoice/Requests/InvoiceCreateInputRequest.php: readonly DTO, mixed raw storage để tránh TypeError, Sequentially NotNull/Type(integer)/Positive. Pilot nhận ID integer đã normalize; string '7' bị từ chối theo schema pilot. Chưa là production route reader; preserve route string/overflow/default/error contract phải thiết kế ở B.2 trước integration. Không suy từ attributes rằng đã hoàn tất input pipeline.
- backend/tests/Feature/Validation/AttributeValidatorTest.php.
- backend/tests/Feature/Validation/InvoiceAttributeMappingTest.php, có test-only readonly DateMappingPilot fixture.

7 file sửa:
- backend/composer.json và composer.lock: require symfony/validator, symfony/object-mapper, symfony/property-access ~7.4.0; lock v7.4.23, thêm transitive property-info/type-info v7.4.23. Chính xác 5 additions, 0 updates/removals cho production+dev dependencies. Giữ Laravel/PHP requirements và Composer scripts hiện hữu; composer require chạy --no-scripts.
- backend/app/Providers/AppServiceProvider.php: singleton framework ValidatorInterface với attribute metadata loader và ObjectMapperInterface với PropertyAccessor. Framework engine registration cần configuration, không tái tạo InvoiceRepository interface/binding; Repository vẫn concrete auto-DI.
- backend/app/Models/Invoice.php và InvoiceItem.php: NotBlank getter attributes group invoice-pilot cho invoice_code/item_name. Getters đọc getAttribute; không khai báo public DB-column properties, không observer/global save hook. Default validation group không áp pilot rule, không sửa production workflow/schema/fillable/casts.
- backend/app/DTOs/Invoice/Responses/InvoiceItemResponse.php: Map source attributes trên promoted properties, giữ readonly constructor/jsonSerialize fields nguyên behavior. Mapping engine chỉ được pilot gọi, InvoiceMapper production vẫn như trước.
- Checklist này.

Tổng 5 new + 7 modified, không deleted/renamed. Giữ Controllers/Services/Repositories/current Validators/InvoiceMapper/RequestMapper, routes/Auth/global handler/bootstrap/schema/Frontend/AGENTS.md/architecture/skills nguyên baseline. Chưa rút gọn/replace production mapper hoặc bỏ Validator query ở B.1.

### Finding và giới hạn mapping

Pilot đầu: 9 PASS/1 FAIL; ObjectMapper + PropertyAccessor đọc trực tiếp Eloquent description=null gặp NoSuchPropertyException do magic attribute readability. Không đổi API/readonly/ORM hoặc bỏ ca null. Bổ sung adapter projection allowlist ở trên, test lại đạt và thêm negative control quan hệ/field không có. Nullable values được giữ bằng explicit stdClass fields. Date conversion được test bằng fixture transformation, không thay formatter ngày API hiện tại. Nonempty/empty item collection được map từng item, đối chiếu production mapper; chưa chứng minh library tự suy mọi nested relation/collection.

### Tests Executed

- Composer require dry-run: exit 0, chỉ 5 additions/0 updates/0 removals. Actual require --no-scripts: exit 0; lock/install thành công, optimized autoload generated.
- composer validate --no-check-publish: PASS, exit 0. composer check-platform-reqs: PASS trên PHP 8.2.12 và extensions hiện hành.
- Focused pilot sau adapter correction: 11 PASS/77 assertions, exit 0. Sau đó bổ sung 3 assertions cho nonempty collection vào cùng test, được kiểm chứng trong full suite cuối.
- Full php artisan test: 463 PASS, 2569 assertions, không skip, exit 0, 22.79s. Gồm 11 pilot tests và Auth/Profile/Invoice read/create/Review trước đó.
- node --test tests/*.test.js: 67/67 PASS, fail/cancel/skip 0, exit 0. Frontend không sửa nên lint/build NOT RUN.
- php artisan route:list --path=api: 16 routes, exit 0. php -l 9 new/modified PHP files PASS.
- Pilot checks: container registration/DI, DTO validation before coercion, safe alias/error envelope/domain message; explicit Model groups/no queries/no mutation, save-refresh-dirty tracking; no global validation on save; readonly target/constructor/null/decimal/internal field exclusion; mapping missing field/relation rejects without query; date/null transform; list omission/detail empty and nonempty items parity.
- Baseline hash tại ignored .agent-workspace/tv3/inv-arch-02b1-validation/baseline.json và packages.json: 536 entries, final 5 new/7 modified, không delete. UTF-8/whitespace và git diff --check PASS. Không file plan/result.
- Tests dùng SQLite in-memory với safety guards; synthetic pilot fixtures không thay thế create API gate. Không reset/reseed hoặc truy cập MySQL development. Browser/MySQL concurrency, integration B.2/B.3 và security remediation NOT VERIFIED.

### Dependency audit còn tồn tại

composer audit --locked --format=json: FAIL (native exit 1), 9 advisories/6 existing packages: laravel/framework (1), symfony/http-foundation (4), symfony/http-kernel (1), symfony/mime (1), symfony/routing (1), symfony/yaml (1). Đối chiếu lock trước task: các package/version này có từ baseline (yaml ở dev); không package mới nào nằm trong advisories hiện hành. Không tự nâng framework/dependencies ngoài task. Shell tổng hợp audit in rõ native exit, không đổi audit FAIL thành PASS. Cần task dependency/security riêng; pilot technical PASS không tuyên bố audit sạch.

| AC B.1 | Kết quả/evidence |
|---|---|
| B1-01 — dependency resolution phù hợp PHP/Laravel/lock | PASS — dry-run/install/validate/platform, 5 additions only |
| B1-02 — registered engines/adapter qua constructor DI | PASS — container singleton + adapter tests |
| B1-03 — attributes thực thi trên DTO/Model explicit groups | PASS — positive/negative cases, default group không auto-activate |
| B1-04 — Eloquent save/refresh/dirty/JSON giữ nguyên | PASS — SQLite persistence/attribute checks, no public column shadowing |
| B1-05 — readonly DTO/null/decimal/date mapping capability | PASS with projection adapter — direct nullable magic mapping unsupported, evidence/correction ghi rõ |
| B1-06 — no query/lazy-loading/internal-field leak | PASS — query log, loaded-field allowlist và relation rejection tests |
| B1-07 — API/Auth/create regression và boundaries giữ nguyên | PASS — full 463 + Node 67, no production consumers changed |
| B1-08 — scope/syntax/checklist/evidence đúng | PASS — 536-entry audit, 9 php lint, diff/UTF-8; audit FAIL disclosed separately |

Recommended Next Task: nghiệm thu B.1 rồi lên/chốt Mini Plan B.2 dựa trên finding Eloquent projection và normalized-vs-raw route DTO, gom InvoiceMapper/InvoiceValidator và chuyển query khỏi Validator. Dependency advisories cần scope remediation riêng. Không tự triển khai B.2/B.3/02C; chưa stage/commit/push. Dừng sau Task Result chi tiết trên chat.


### INV-ARCH-02B.1 — owner acceptance

Owner nghiệm thu B.1 trực tiếp trên chat trước yêu cầu Mini Plan B.2. Trạng thái hiện hành B.1 ACCEPTED; parent 02/02B vẫn PARTIAL, B.2/B.3/02C chưa triển khai. Security audit 9 advisories/6 baseline packages vẫn unresolved, không được coi đã xử lý bởi acceptance. 463 PHP tests/2569 assertions và 67 Node tests là evidence lượt B.1; lượt này chỉ ghi acceptance/planning, không chạy lại application suites hoặc sửa application code.


## INV-ARCH-02B.2A — gom Mapper/Validator và DTO input attributes

Owner duyệt cách chia và riêng 02B.2A. Status IMPLEMENTED / TECHNICAL PASS / owner acceptance PENDING. 02B.1 ACCEPTED; parent 02/02B/02B.2 vẫn PARTIAL, không bắt đầu 02B.2B/02B.3/02C.

| Đóng | Checkpoint | Trạng thái |
|---|---|---|
| [x] | INV-ARCH-02B.2A — constraints/DTO attributes, gom Mapper/Validator | IMPLEMENTED / PASS / ACCEPTED |
| [x] | INV-ARCH-02B.2B — Validator khỏi query, typed create workflow và required workflow DI | IMPLEMENTED / PASS / ACCEPTED |

### Implementation và scope

4 file mới: backend/app/Validation/Constraints/CanonicalPositiveId.php, CanonicalPositiveIdValidator.php; backend/app/DTOs/Invoice/Requests/InvoiceIdInputRequest.php, InvoiceQueryInputRequest.php.

2 rename có sửa: app/Validators/Invoice/InvoiceCreationValidator.php -> InvoiceValidator.php; tests/Unit/InvoiceCreationValidatorTest.php -> InvoiceValidatorTest.php.

2 file xóa sau gom: app/Mappers/Invoice/InvoiceRequestMapper.php; app/Validators/Invoice/InvoiceReadValidator.php.

9 file sửa: app/Mappers/Invoice/InvoiceMapper.php, app/Services/Invoice/InvoiceService.php, app/Http/Controllers/Invoice/HostInvoiceController.php, CustomerInvoiceController.php, app/DTOs/Invoice/Requests/InvoiceCreateInputRequest.php, tests/Unit/InvoiceMapperTest.php, tests/Feature/Invoice/InvoiceQueryApiTest.php, tests/Feature/Validation/AttributeValidatorTest.php và checklist này. Các app/tests paths ở trên thuộc backend/. Không tạo CreateInvoiceRequest typed command chưa có consumer; thuộc 2B.

InvoiceMapper nhận toFilterRequest normalization từ RequestMapper, giữ nguyên response projection/toItemResponse. ID validation chuyển sang InvoiceValidator và custom Symfony constraint. Response mapping engine vẫn chưa thay trong production ở 2A; EloquentObjectMapper pilot adapter giữ nguyên. Persistence payload mapping/typed create workflow thuộc 2B.

CanonicalPositiveId tái sử dụng đúng native PHP canonical-ID predicate cũ, kiểm tra raw trước cast. Read classifier trả AppException INVOICE_NOT_FOUND/404, booking classifier InvalidArgumentException message-only 422 cho boundary sau này; không active trong store() ở 2A. Integral float như 1.0 khi gọi nội bộ vẫn được chấp nhận giống code cũ; string '1.0'/'1e2'/leading-zero/whitespace/overflow bị từ chối. Không tự áp regex/type mới làm đổi semantics.

InvoiceCreateInputRequest đổi từ pilot normalized-int sang raw canonical ID; InvoiceIdInputRequest tương ứng read; InvoiceQueryInputRequest Type attributes giữ nullable/string/scalar behaviors. InvoiceValidator validateQuery giữ array boundary/presence, loại unknown keys, map validation.string qua Laravel translator như trước và giữ pagination messages. Query defaults/clamp/status/search behavior vẫn ở Mapper/InvoiceFilterRequest. No GET body merge; Controller authorize trước validate query/ID, giữ framework guest 401 và Service ownership checks.

InvoiceValidator gom read/create; các creation validation methods nguyên văn không đổi, query duplicate vẫn tồn tại và sẽ chuyển ra ở 2B. Controller read dependencies gom thành mapper/validator. InvoiceService import/type/getValidator đổi sang merged class; workflow create, transaction, calculator, error classification/logging và return Invoice giữ nguyên. store() Controller exact baseline.

### DI finding và điều chỉnh tối thiểu

Focused đầu: 63 PASS/20 FAIL vì Laravel resolve optional class dependencies bằng default null, không inject AttributeValidator vào merged class. Sửa bằng AttributeValidator bắt buộc trong InvoiceValidator và InvoiceValidator bắt buộc trong InvoiceService; bỏ fallback new Validator, không Service Locator/factory/binding mới. Unit test truyền engine/adapter trực tiếp. Đây là điều chỉnh cần cho consolidation ở 2A; Calculator/Logger optional legacy và required workflow DI đầy đủ vẫn thuộc 2B. Không dùng fallback mới để che dependency thiếu.

### Tests Executed

- Focused sau DI correction trước regressions mới: 83 PASS/504 assertions, exit 0.
- Thêm 3 test methods (canonical read/create ID/errors, query attributes/scalars/messages, consolidated filter normalization). Focused cuối php artisan test --filter='InvoiceValidatorTest|InvoiceMapperTest|InvoiceCreationTest|InvoiceQueryApiTest|HostInvoiceApiTest|AttributeValidatorTest|InvoiceAttributeMappingTest': 86 PASS, 608 assertions, exit 0.
- Full php artisan test: 466 PASS, 2679 assertions, không skip, exit 0, 23.22s. Includes Auth/Profile/read/create/Review regressions.
- node --test tests/*.test.js: 67/67 PASS, fail/cancel/skip 0, exit 0. Frontend source không sửa nên lint/build NOT RUN.
- php artisan route:list --path=api: 16 routes, exit 0. php -l 14 PHP files mới/sửa/rename PASS; no obsolete InvoiceRequestMapper/InvoiceReadValidator/InvoiceCreationValidator references in app/tests.
- Regression coverage includes canonical IDs native integer bounds/string/integral float; invalid array/object/null/bool/exponent/fraction/leading-zero/whitespace/overflow/INF/NAN; fixed 404 vs 422 messages; nullable/scalar query/presence/unknown field rejection; exact Laravel field messages; filters defaults/clamp/unknown status/search 0; DTO null/decimal/items JSON; role-before-input/privacy/no-query read validation; create unchanged.
- Protected source audit: Host store() và InvoiceService từ createInvoice() đến cuối file exact baseline; InvoiceValidator từ validateForCreation() đến cuối file exact creation-validator baseline. Không thay read Service/Repository/Common/Response DTO/Model constraints hoặc create Resources.
- Baseline 541 entries tại ignored .agent-workspace/tv3/inv-arch-02b2a-validation/baseline.json, workflow.txt/store.txt/creation-validator.txt là scope evidence, không file plan/result. Final audit 4 new + 2 rename + 2 delete + 9 modified; tất cả files khác nguyên vẹn. UTF-8/whitespace/git diff --check PASS. Checklist current-state link đổi sang InvoiceValidator; historical names/counts giữ nguồn cũ.
- Không sửa composer/lock/provider/framework registration, AttributeValidator/EloquentObjectMapper, Models/schema/Auth/Frontend, AGENTS.md/architecture/skills. Current-state naming trong architecture/skills sẽ đồng bộ theo checkpoint tài liệu đã plan; checklist này ghi trạng thái mới. Không chạy lại composer audit; 9 advisories/6 baseline packages của B.1 vẫn unresolved, không coi đã fix.
- SQLite in-memory/safety guards, không truy cập/reset/reseed MySQL development. Browser/MySQL concurrency và no-query toàn bộ creation Validator NOT VERIFIED; không được đóng bằng checkpoint này.

| AC 2A | Kết quả/evidence |
|---|---|
| B2A-01 — một InvoiceMapper/InvoiceValidator read-create, no obsolete references | PASS — inventory/search, consumers cập nhật, unit test rename |
| B2A-02 — canonical/raw DTO attributes giữ ID/query contract | PASS — positive/negative/error/native-boundary/query tests |
| B2A-03 — input/read validation no query, Mapper thuần | PASS — query-log/mapper immutability/source review; creation query deferred rõ |
| B2A-04 — read API/Auth/create không regression | PASS — 466 PHP/67 Node, protected workflow/store/creation validation baseline |
| B2A-05 — constructor DI cho merged input boundary | PASS — required AttributeValidator/InvoiceValidator, full container/API tests |
| B2A-06 — syntax/scope/checklist/history đúng | PASS — 14 php lint, 541-entry scope/hash/whitespace/diff |

Recommended Next Task: owner nghiệm thu 02B.2A, sau đó lên/chốt Mini Plan 02B.2B chuyển query khỏi Validator, typed create command/persistence mapping và required workflow dependencies còn lại. Không tự làm 02B.2B/02B.3/02C; chưa stage/commit/push; dừng sau Task Result trên chat.

### INV-ARCH-02B.2A — owner acceptance

Owner nghiệm thu 02B.2A trực tiếp trên chat trước yêu cầu Mini Plan 02B.2B. Trạng thái hiện hành 02B.2A ACCEPTED; phần PENDING và Recommended Next Task ở báo cáo phía trên là lịch sử trước nghiệm thu. Parent INV-ARCH-02/02B/02B.2 vẫn PARTIAL; 02B.2B chỉ planning, chưa được phép triển khai. Evidence 466 PHP tests/2679 assertions và 67 Node tests thuộc lượt 02B.2A, không phải test chạy lại trong lượt acceptance/planning này. Chỉ cập nhật checklist, không sửa code ứng dụng hoặc AGENTS.md, không tạo file plan/result, không commit/push.


## INV-ARCH-02B.2B — query-free Validator, typed create command và persistence mapping

Owner duyệt đúng 2 file mới và 7 file sửa trên chat. Status IMPLEMENTED / TECHNICAL PASS; Owner Acceptance PENDING. 02B.2A ACCEPTED; parent INV-ARCH-02/02B/02B.2 vẫn PARTIAL, chưa bắt đầu 02B.3/02C. Planning/PENDING/TODO ở các đoạn lịch sử phía trên không thay trạng thái hiện hành này.

### Implemented và file scope

2 file mới:
- backend/app/DTOs/Invoice/Requests/CreateInvoiceRequest.php: readonly application command với int bookingId; raw InvoiceCreateInputRequest/attributes giữ nguyên. Không mang actor/giá/status từ client, không thêm validation làm đổi legacy lookup behavior.
- backend/tests/Feature/Invoice/InvoiceCreationCommitTest.php: ba ca thực thi commit/rollback trên named SQLite :memory: connection riêng, production transaction manager, không RefreshDatabase wrapping transaction. Safety guards trước/sau boot; migrate chỉ trên connection test do test sở hữu, không fresh/refresh/rollback schema hoặc seed development. Rollback transaction còn lại và purge connection trong teardown.

7 file sửa:
- backend/app/Services/Invoice/InvoiceService.php: createInvoice(int, User): Invoice là adapter sang createInvoiceFromRequest(CreateInvoiceRequest, User): Invoice, dùng chung một workflow/transaction. Service gọi Repository duplicate query sau role/ownership/status và trước snapshot validation; giữ recheck trước persistence. Calculator/Mapper/Logger và Repository/Validator là required constructor DI; không fallback new/Log facade trong Service. Mapper cung cấp payload; workflow vẫn quyết định discount/extra_fee 0.00, UNPAID và paid_at null.
- backend/app/Validators/Invoice/InvoiceValidator.php: validateCreationEligibility rồi pure validateInvoiceNotExists(int, bool) và validateCreationSnapshots; không inject Repository/query callback, không query hoặc lazy-load. Required AttributeValidator/Calculator; loadedRelation guard dùng getRelation, role denial trước relation guard. Loaded null giữ business error cũ; unloaded relation là LogicException nội bộ. Giữ read/raw validation behavior.
- backend/app/Mappers/Invoice/InvoiceMapper.php: toCreateRequest/toServiceSnapshots/toInvoiceAttributes/toRoomItemAttributes/toServiceItemAttributes, chỉ projection loaded snapshots và kết quả calculator. Không query/tính tiền/log/mutate; giữ decimal strings, null, room description và explicit payload fields. Read mapping/response engine không thay.
- backend/tests/Unit/InvoiceValidatorTest.php: cập nhật required Calculator; thêm 5 ca pure duplicate, unloaded relation, role-before-relation, loaded null và fully loaded creation validation không cần DB.
- backend/tests/Unit/InvoiceMapperTest.php: thêm 2 ca typed command/persistence parity/immutability và unloaded relation; expected fixture độc lập, cố tình không khớp công thức để phát hiện mapper tự tính lại.
- backend/tests/Feature/Invoice/InvoiceCreationTest.php: sửa anonymous subclass theo DI mới; thêm 4 ca typed create persisted result, no-query validation/mapping, role/status/duplicate precedence và legacy ID 0 vẫn ModelNotFoundException.
- docs/TV3/TV3_CHECKLIST.md: cập nhật implementation, evidence, AC và acceptance pending; giữ lịch sử.

Service tiếp tục transaction/lock workflow, sinh invoice code và phân loại QueryException như trước. Đã loại bỏ hai duplicate queries từ Validator, thay bằng Repository existence check tại cùng vị trí nghiệp vụ; giữ thêm recheck hiện có. Không chuyển transaction vào Repository. Success message/context và DB::afterCommit giữ nguyên, log không có customer name/email/phone. Calculator legacy và logging nội bộ Calculator không nằm trong scope thay đổi này.

### Tests Executed và giới hạn

- Final focused: php artisan test --filter='InvoiceValidatorTest|InvoiceMapperTest|InvoiceCreationTest|InvoiceCreationCommitTest|InvoiceRepositoryTest|InvoiceQueryApiTest|HostInvoiceApiTest|InvoiceAttributeMappingTest': 102 PASS, 596 assertions, exit 0, 2.82s.
- Full php artisan test: 480 PASS, 2738 assertions, exit 0, 23.49s; includes Auth/Profile/Invoice read-create/Review. Không test skipped.
- Commit tests riêng: 3 PASS, 26 assertions, exit 0. Callback thấy transactionLevel 0 và PDO không inTransaction khi log; nested service commit chưa log, outer commit log đúng một lần và đúng bốn context fields; outer rollback không log/không persistence, commit sau đó không revive callback; real item NOT NULL failure rollback header/items, không log success.
- Frontend node --test tests/*.test.js: 67/67 PASS, fail/cancel/skip 0, exit 0. Frontend không sửa; lint/build NOT RUN.
- php -l: 8/8 PHP files mới/sửa PASS. php artisan route:list --path=api: 16 routes, exit 0. Git diff --check PASS (existing line-ending conversion warnings riêng, không whitespace errors).
- Static/runtime no-query gates: InvoiceValidator/InvoiceMapper không query tokens; loaded workflow validation và persistence mapping capture zero DB queries; pure unit tests không boot DB. No-query evidence không phải MySQL concurrency proof.
- Scope baseline 537 source/config/doc entries tại .agent-workspace/tv3/inv-arch-02b2b-validation/baseline.json; final audit đúng 2 new + 7 modified, không delete. App/Auth/Controllers/routes/Repository/Models/Calculator/Resources/Common/Composer/providers/architecture/skills/AGENTS.md/AGENTS.local.md/Frontend ngoài scope giữ baseline.
- Evidence logs: cùng thư mục baseline, focused.log, backend-full.log, frontend-node.log, routes.log; đây là execution evidence, không file Mini Plan/Task Result. Không stage/commit/push, không truy cập/reset/reseed MySQL development.
- Không rerun Composer security audit; 9 advisories/6 baseline packages từ 02B.1 vẫn unresolved. Model attributes chưa active trong create (02B.3); HTTP store()/Resource vẫn legacy tới 02C. Browser/MySQL concurrency NOT VERIFIED.

| AC | Trạng thái và evidence |
|---|---|
| B2B-01 — Validator không query/lazy-load, giữ thứ tự lỗi | PASS — pure unit tests, zero-query capture, role/status/duplicate precedence |
| B2B-02 — typed command có consumer, legacy entry tương thích | PASS — shared typed workflow, persisted-result regression, legacy ID 0 |
| B2B-03 — Mapper sở hữu persistence projection đúng dữ liệu | PASS — độc lập fixture, decimal/null/description/immutability, create persistence tests |
| B2B-04 — required constructor DI, không fallback Service/Validator | PASS — source review, container/API/full suite, subclass cập nhật |
| B2B-05 — atomicity/duplicate/DB exception giữ contract | PASS — unique booking/code, non-unique failure, rollback tests |
| B2B-06 — success log sau commit, không khi rollback | PASS — real outer/nested commit và rollback trên owned SQLite connection |
| B2B-07 — API/Auth/read/create không regression | PASS — 480 PHP tests, 67 Node tests; Controller/routes/Auth source unchanged |
| B2B-08 — đúng scope, checklist và evidence | PASS — 537-entry scope/hash audit, 8 php lint, routes/diff checks |

Recommended Next Task: owner nghiệm thu 02B.2B; sau đó lên Mini Plan 02B.3 kích hoạt Model validation trong create workflow. Không tự triển khai 02B.3/02C hoặc đóng parent. Dừng sau báo cáo trên chat.

### INV-ARCH-02B.2B — owner acceptance

Owner nghiệm thu 02B.2B trực tiếp trên chat trước yêu cầu Mini Plan 02B.3. Trạng thái hiện hành 02B.2B ACCEPTED; PENDING/Recommended Next Task trong báo cáo phía trên là lịch sử trước nghiệm thu. Parent INV-ARCH-02/02B vẫn PARTIAL; 02B.3 chỉ planning, chưa được phép triển khai. Evidence 480 PHP tests/2738 assertions và 67 Node tests thuộc lượt 02B.2B, không phải kết quả chạy lại ở lượt acceptance/planning này. Lượt này chỉ cập nhật checklist và rà soát; không sửa application code/AGENTS.md hoặc tạo file plan/result.


## INV-ARCH-02B.3 — kích hoạt Model validation trước persistence Invoice/ROOM

Owner duyệt đúng 9 file sửa trên chat. Status IMPLEMENTED / TECHNICAL PASS; Owner Acceptance PENDING. 02B.2B ACCEPTED; parent INV-ARCH-02/02B vẫn PARTIAL, không tự đóng parent hoặc bắt đầu 02C. Các đoạn TODO/planning phía trên là lịch sử; trạng thái hiện hành theo section này.

### Implemented và scope

0 file mới, 0 file xóa, đúng 9 file sửa:
- backend/app/Models/Invoice.php: thêm group invoice-create vào NotBlank(invoice_code) hiện có; giữ invoice-pilot/default semantics và getter/casts/fillable/relationships.
- backend/app/Models/InvoiceItem.php: thêm group invoice-create-room vào NotBlank(item_name); workflow chỉ gọi cho ROOM, không thêm business rule tên SERVICE. Không observer, global hook hoặc public property trùng DB column.
- backend/app/Validators/Invoice/InvoiceValidator.php: thêm validateInvoiceModel(Invoice) và validateRoomItemModel(InvoiceItem), gọi AttributeValidator.validateDomain với explicit group và safe public message. Không query/log/mutate Model.
- backend/app/Services/Invoice/InvoiceService.php: tạo Model candidates từ đúng Mapper payload, validate Invoice trước insert header và ROOM trước insert ROOM, rồi Repository nhận lại payload gốc như trước. Không đổi Repository/Mapper contracts, transaction/lock, quyền/status/duplicate/snapshot order, calculator/money/payment defaults, QueryException classifier hoặc DB::afterCommit. SERVICE persistence path giữ nguyên.
- backend/tests/Unit/InvoiceValidatorTest.php: thêm 2 methods cho valid candidates không mutation/DB và blank/null/whitespace bị từ chối với safe domain message.
- backend/tests/Feature/Invoice/InvoiceCreationTest.php: thêm invalid generated code trước header insert, test-only corrupt ROOM candidate qua real parent attributes engine và rollback header, HTTP 422 exact message-only ở hai ca; thêm data provider 3 trường hợp SERVICE name empty/whitespace/literal zero, HTTP 201 và response/persistence parity.
- backend/tests/Feature/Invoice/InvoiceCreationCommitTest.php: thêm Model validation failure Invoice/ROOM trên owned SQLite connection thực, không success log, không persisted records, transactionLevel 0/PDO ngoài transaction và subsequent unrelated commit không revive callback.
- backend/tests/Feature/Validation/InvoiceAttributeMappingTest.php: thêm group isolation/pilot parity/default behavior, no-query và attributes/dirty-state/toArray immutability; giữ test global save không validation và date mapping pilot.
- docs/TV3/TV3_CHECKLIST.md: cập nhật trạng thái/evidence/AC, không tạo file plan/result.

Constraints chỉ dùng trim để kiểm tra, không trim/sửa snapshot. Model candidate validation không đảm bảo mọi mutation ở Eloquent events sau đó; không thêm global enforcement. SERVICE vẫn qua snapshot/financial checks hiện có, chưa thêm NotBlank tên SERVICE. Đây không phải chuyển toàn bộ financial validation sang Model.

### Tests Executed

- Focused cuối: php artisan test --filter='InvoiceValidatorTest|InvoiceCreationTest|InvoiceCreationCommitTest|InvoiceAttributeMappingTest|InvoiceMapperTest|InvoiceRepositoryTest|HostInvoiceApiTest|InvoiceQueryApiTest': 111 PASS, 674 assertions, exit 0, 3.06s.
- Full php artisan test: 489 PASS, 2816 assertions, exit 0, 23.37s, không skip. So với 02B.2B thêm 9 test cases (7 methods gồm một provider 3 datasets), bao phủ Auth/Profile/Invoice read-create/Review regressions trong full gate.
- node --test tests/*.test.js: 67/67 PASS, fail/cancel/skip 0, exit 0. Frontend không sửa; lint/build NOT RUN.
- php -l: 8/8 PHP files sửa PASS. php artisan route:list --path=api: 16 routes, exit 0. Git diff --check PASS; existing line-ending conversion warnings không phải whitespace failure.
- Model invalid code: capture zero INSERT trước exception; HTTP 422 đúng duy nhất message an toàn. ROOM fault injection đi qua constraints thật: capture đúng một header INSERT, không item INSERT, rollback header/items và HTTP 422 exact message-only. Injection chỉ test candidate, không fake success JSON hoặc sửa Mapper production.
- SERVICE empty/whitespace/'0': create qua HTTP 201, response và persisted item giữ nguyên tên, total_amount 4150000.00, UNPAID và 3 items. Không thêm luật SERVICE-name.
- Explicit group tests: default/wrong group không active; pilot/production errors tương đương; normalizer không đổi attributes, dirty state hoặc JSON; query capture bằng 0. Ordinary Model save vẫn không tự validation.
- SQLite :memory: safety guards trước/sau boot; commit gate named connection riêng/production transaction manager, teardown rollback transaction còn lại/purge đúng ownership. Không truy cập/reset/reseed DB MySQL development.
- Baseline 539 source/config/doc entries: .agent-workspace/tv3/inv-arch-02b3-validation/baseline.json. Scope final đúng 9 modified, 0 new/deleted; các file khác giữ baseline, gồm AGENTS.md/AGENTS.local.md/architecture/skills/Controller/routes/Auth/Repository/Mapper/DTO/adapters/Composer/provider/Calculator/Frontend.
- Evidence cùng thư mục: focused.log, backend-full.log, frontend-node.log, routes.log, scope-audit.log và production before snapshots. Đây là execution evidence, không file plan/result. Không stage/commit/push.
- Không chạy lại Composer audit; 9 advisories/6 baseline packages ghi từ B.1 vẫn unresolved. Browser/MySQL concurrency NOT VERIFIED; SQLite không chứng minh InnoDB locking. HTTP create còn Resource legacy tới 02C.

| AC | Kết quả và evidence |
|---|---|
| B3-01 — production groups active, pilot/default tương thích | PASS — blank/null/whitespace, group isolation và pilot parity tests |
| B3-02 — create gọi Model validation trước persistence | PASS — invalid-code zero INSERT; invalid ROOM chỉ header INSERT rồi rollback; real engine negative controls |
| B3-03 — validation không query/mutate | PASS — unit không DB, query capture 0, attributes/dirty-state/JSON parity |
| B3-04 — validation failure rollback và không success log | PASS — persistence counts và owned-connection commit gate |
| B3-05 — error order/SERVICE/money/response giữ nguyên | PASS — HTTP 422 exact messages, 3 SERVICE HTTP/persistence cases, existing precedence/financial tests |
| B3-06 — Auth/read/create/full regression | PASS — 489 PHP tests/2816 assertions, 67 Node tests, exit 0 |
| B3-07 — đúng scope/checklist/evidence | PASS — 539-entry baseline, exactly 9 modified, php lint/routes/whitespace/diff checks |

Recommended Next Task: owner nghiệm thu 02B.3, sau đó lên Mini Plan 02C nối HTTP create sang DTO/Common, regression Frontend và đồng bộ tài liệu/skills theo phạm vi được duyệt. Parent INV-ARCH-02 vẫn mở. Dừng sau Task Result trên chat.

### INV-ARCH-02B.3 — owner acceptance

Owner nghiệm thu 02B.3 trực tiếp trên chat trước yêu cầu Mini Plan 02C. Trạng thái hiện hành 02B.3 ACCEPTED; PENDING/Recommended Next Task phía trên là lịch sử trước nghiệm thu. Parent INV-ARCH-02/02B chưa tự đóng; 02C chỉ planning, chưa được phép triển khai. Evidence 489 PHP tests/2816 assertions và 67 Node tests thuộc lượt 02B.3, không phải kết quả chạy lại ở lượt acceptance/planning này. Lượt này chỉ cập nhật checklist và rà soát; không sửa application code/AGENTS.md hoặc tạo file plan/result.


## INV-ARCH-02C.2 — gom Frontend Invoice create/read vào một service

Owner duyệt riêng 02C.2 với scope cập nhật trên chat. Status IMPLEMENTED / TECHNICAL PASS; Owner Acceptance PENDING. 02B.3 ACCEPTED. 02C.1 Backend HTTP DTO/Common và 02C.3 kiến trúc/skills chưa triển khai, không được suy hoàn tất từ checkpoint này. Parent INV-ARCH-02/02C vẫn PARTIAL.

| Đóng | Checkpoint | Trạng thái |
|---|---|---|
| [x] | INV-ARCH-02C.1 — HTTP create DTO/Common và bỏ Invoice Resources | IMPLEMENTED / PASS / ACCEPTED |
| [x] | INV-ARCH-02C.2 — một invoiceService.js cho create/read | IMPLEMENTED / PASS / ACCEPTED |
| [x] | INV-ARCH-02C.3 — kiến trúc, local rules và 5 skills | IMPLEMENTED / PASS / ACCEPTED |

### Implemented và file scope

5 file sửa:
- frontend/src/services/invoiceService.js: factory createInvoiceService(invoiceApi) gom create/list/detail; dùng Mapper detail cho create, giữ filter normalization và public named exports/defaults/options. Singleton dùng đúng shared Axios hiện tại qua createInvoiceApi; import thêm .js để Node resolve module test. formatVnd và parseInvoiceError giữ nguyên nội dung.
- frontend/src/components/invoice/CreateInvoiceButton.jsx: chỉ thay onSuccess(result.data || result) thành onSuccess(result.data); loading/disabled/callback/UI giữ nguyên.
- frontend/src/mappers/invoiceMapper.js: chỉ đổi mô tả phạm vi read/create; không thay behavior/projection.
- frontend/tests/invoiceMapper.test.js: thêm create response frozen fixture, decimal/null và SERVICE names empty/whitespace/literal zero, malformed items không fallback.
- docs/TV3/TV3_CHECKLIST.md: implementation, evidence, AC và owner acceptance pending.

1 rename có sửa: frontend/tests/invoiceReadService.test.js -> frontend/tests/invoiceService.test.js. Giữ cả 4 read regressions; thêm create mapping/options, malformed success, HTTP/Auth/version/error fields, cancel/timeout/network/no replay, invalid IDs no HTTP và cả 5 public exports qua shared-client mock. Tổng 8 test cases mới trong hai test files.

1 file xóa: frontend/src/services/invoiceReadService.js sau khi gom; không tạo invoiceCreateService.js, không thêm production helper/factory module khác.

Factory và public exports ở cùng file. API module vẫn sở hữu URL/method/params/body/signal; shared Axios và Auth interceptors không sửa/đăng ký lại. Create/detail result {data: Invoice}, list {data,meta,links} và metadata bổ sung giữ contract. Không tính lại tiền, giữ decimal strings/null/items/SERVICE names. Rejections giữ identity; không tự retry hoặc replay POST. Component nhận result shape chính thức, không che malformed success.

### Tests Executed

- Node v22.23.4; focused node --experimental-test-module-mocks --test tests/invoiceService.test.js tests/invoiceMapper.test.js: 16/16 PASS, fail/cancel/skip 0, exit 0.
- Full node --experimental-test-module-mocks --test tests/*.test.js: 75/75 PASS, fail/cancel/skip 0, exit 0. 67 cases cũ giữ và 8 cases mới.
- npm run lint: PASS, exit 0; 0 errors, 3 react-refresh/only-export-components warnings tại badge.jsx/button.jsx/sidebar.jsx không sửa trong scope.
- npm run build -- --outDir ../.agent-workspace/tv3/inv-arch-02c2-validation/build: PASS, exit 0, 3316 modules transformed. Build cô lập giữ output trước đó; Vite báo outDir ngoài project không tự empty và JS chunk >500 kB (khoảng 1,078.77 kB), không sửa chunk config ngoài scope.
- node --check: 4/4 JS files mới-path/sửa PASS; JSX được compile qua build. Git diff --check và UTF-8/whitespace/scope audit PASS.
- Test mock.module chỉ thay shared Axios module trước dynamic import invoiceService.js, vì module production đọc Vite import.meta.env. Factory/public functions thật được import và thực thi; các HTTP responses là adapter fixtures, không browser/real backend evidence. Node mock.module là experimental và cần flag trên; không thay package.json/scripts hoặc dependencies.
- No obsolete invoiceReadService/createInvoiceReadService/invoiceCreateService references trong frontend/src và frontend/tests. CreateInvoiceButton không còn raw result fallback. Helper formatVnd/parseInvoiceError exact baseline sau normalization newline.
- Baseline 539 source/config/doc entries tại .agent-workspace/tv3/inv-arch-02c2-validation/baseline.json. Scope đúng 5 modified + 1 test rename có sửa + 1 deleted service; không sửa Backend/API/Axios/Auth/pages/routes/AGENTS.md/AGENTS.local.md/architecture/skills/lockfiles. Historical names trong docs/skills giữ đến 02C.3.
- Evidence cùng thư mục: source-head.txt, git-before.txt, focused.log, node-full.log, lint.log, build.log, scope-audit.log và build/. Đây là execution evidence, không file plan/result. Không stage/commit/push; không truy cập DB.
- Backend tests NOT RUN: không đổi Backend; 489 PHP/2816 assertions là evidence lịch sử 02B.3, không claim chạy lại. Browser/real Auth backend, duplicate-click UI, stale responses/unmount/auth-change và responsive NOT VERIFIED; Node adapters/lint/build không thay browser gate.

| AC | Kết quả và evidence |
|---|---|
| C2-01 — một invoiceService.js, không runtime import service cũ | PASS — factory consolidation, reference search, build |
| C2-02 — 5 public functions giữ signature/result contract | PASS — shared-client mock public-export test, factory read/create regressions |
| C2-03 — create/read mapping giữ decimal/null/items/SERVICE | PASS — frozen projection fixtures, empty/whitespace/zero names, malformed responses |
| C2-04 — API/Auth/signal/errors và no replay | PASS — HTTP statuses/Auth session versions/identity/fields/signal/cancel/network/timeout tests |
| C2-05 — Node/lint/build | PASS — 75/75, lint exit 0 với 3 warnings, build exit 0 với bundle warning |
| C2-06 — scope/checklist/evidence | PASS — 539-entry baseline, 5 sửa + 1 rename + 1 xóa, syntax/whitespace/diff audit |

Recommended Next Task: owner nghiệm thu 02C.2; sau đó thực hiện 02C.1 theo plan Backend đã trình khi được duyệt, rồi 02C.3 đồng bộ kiến trúc/skills. Không tự triển khai task tiếp theo hoặc đóng parent. Dừng sau Task Result trên chat.


### INV-ARCH-02C.2 — owner acceptance

Owner nghiệm thu 02C.2 và duyệt riêng 02C.1 trực tiếp trên chat. 02C.2 ACCEPTED; PENDING/Recommended Next Task trong báo cáo 02C.2 phía trên là lịch sử. Evidence 75 Node tests, lint/build của 02C.2 giữ nguồn lượt đó; lần 02C.1 có chạy lại Node regression nhưng không chạy lại lint/build. Không sửa Frontend ở 02C.1.

## INV-ARCH-02C.1 — HTTP create dùng typed DTO và Common response

Owner duyệt đúng 4 file sửa và 2 file xóa. Status IMPLEMENTED / TECHNICAL PASS; Owner Acceptance PENDING. 02C.2 ACCEPTED, 02C.3 chưa triển khai; parent INV-ARCH-02/02C vẫn PARTIAL. Không tự đóng parent hoặc bắt đầu đồng bộ kiến trúc/skills.

### Implemented và scope

4 file sửa:
- backend/app/Http/Controllers/Invoice/HostInvoiceController.php: raw booking ID qua InvoiceValidator.validateBookingId (Symfony DTO attributes), Mapper.toCreateRequest -> CreateInvoiceRequest -> InvoiceService.createInvoiceFromRequest; loaded Invoice -> Mapper.toResponse(includeItems true) -> ApiResponse.success(status 201). Giữ một data envelope; không thêm error code hoặc chuyển catch sang Common.error. Guest middleware và thứ tự ID/actor bên trong create giữ contract. Toàn bộ catch blocks và các read methods exact baseline.
- backend/tests/Feature/Invoice/HostInvoiceApiTest.php: thêm 3 test methods cho typed entry point (legacy method cố tình fail nếu HTTP gọi nhầm), exact create response/date/null/decimals/items và create/read list-detail parity ở Host/Customer; raw ID invalid/overflow/native max; guest malformed ID, role denial message-only và /api/me chứng minh session giữ nguyên. Body financial/owner fields không thể ghi đè server snapshots/defaults.
- backend/tests/Feature/Invoice/InvoiceQueryApiTest.php: loại dependency hai Invoice Resources, dùng test-owned expected public payload giữ contract trước migration, không gọi production Mapper/DTO làm expected. Giữ list không items/detail items, paid_at/date/null/decimal, empty list/detail và ownership tests.
- docs/TV3/TV3_CHECKLIST.md: ghi nghiệm thu 02C.2, implementation/evidence/AC 02C.1 và acceptance pending.

2 file xóa:
- backend/app/Http/Resources/Invoice/InvoiceResource.php
- backend/app/Http/Resources/Invoice/InvoiceItemResource.php

Không còn InvoiceResource/InvoiceItemResource references trong backend/app và backend/tests. Không xóa Http/Resources/Auth/Profile/Review hoặc Common legacy Resource compatibility. Không sửa Service/Repository/Mapper/DTO/Validator/Models/Calculator/transactions/locks/logging, Auth/Sanctum/routes/Common/handler/Composer/provider/Frontend/AGENTS.md/AGENTS.local.md/architecture/skills.

### Tests Executed và evidence

- Focused php artisan test --filter='HostInvoiceApiTest|InvoiceQueryApiTest|InvoiceCreationTest|InvoiceCreationCommitTest|InvoiceValidatorTest|InvoiceMapperTest': 98 PASS, 597 assertions, exit 0, 2.89s.
- Full php artisan test: 492 PASS, 2860 assertions, exit 0, 23.68s, không skip. Includes Auth/Profile/Review/Invoice read-create, rollback/commit logging, Model validation faults và SERVICE empty/whitespace/literal-zero regressions.
- Frontend node --experimental-test-module-mocks --test tests/*.test.js: 75/75 PASS, fail/cancel/skip 0, exit 0. Frontend không sửa nên lint/build NOT RUN lượt này; giữ evidence PASS có warnings của 02C.2 riêng.
- php -l: 3/3 PHP files sửa PASS. php artisan route:list --path=api: 16 routes, exit 0. Git diff --check và UTF-8/whitespace/scope audit PASS (existing line-ending conversion warnings không phải whitespace errors).
- HTTP 201 exact data envelope/public fields; độc lập expected totals 4150000.00 và ROOM/SERVICE values, room description, timestamp 2026-11-14T01:02:03.000000Z, nullable paid_at/description. Create và đọc lại detail khớp; list chỉ bỏ items. Chỉ IDs/code tạo ngẫu nhiên lấy từ persisted record, không gọi production Mapper/DTO tính expected.
- Invalid canonical IDs 0/-1/fraction/exponent/leading-zero/plus/whitespace/text/overflow: 422 exact message-only; PHP_INT_MAX là canonical nhưng không có resource: 404 exact message-only. Guest invalid ID vẫn 401; ADMIN malformed ID 422 theo create order hiện hữu, valid resource 403 rồi /api/me 200 cùng user. Read authorization precedence không đổi.
- Test override legacy createInvoice throws LogicException; HTTP vẫn 201 qua createInvoiceFromRequest, capture typed DTO bookingId/actor từ session. Request body total/payment/customer/booking fields không override snapshots.
- SQLite in-memory safety guards, không truy cập/reset/reseed MySQL development. Browser/MySQL concurrency NOT VERIFIED; Node adapter/SQLite không thay E2E. Dependencies/advisories B.1 không thay và không chạy lại Composer audit.
- Baseline 538 source/config/doc entries tại .agent-workspace/tv3/inv-arch-02c1-validation/baseline.json; final scope đúng 4 modified + 2 deleted, không new source files. Evidence: source-head.txt, git-before.txt, Controller/Resource before snapshots, focused.log, backend-full.log, frontend-node.log, routes.log và scope-audit.log. Không file plan/result, không stage/commit/push.

| AC | Kết quả và evidence |
|---|---|
| C1-01 — HTTP create nối Validator/typed command/Response DTO/Common | PASS — typed entry spy và HTTP success, source review |
| C1-02 — 201 fields/envelope/date/null/decimal/items và read parity | PASS — exact fixture assertions, create-to-read Host/Customer tests |
| C1-03 — API/Auth/error classification và nghiệp vụ giữ nguyên | PASS — raw ID/guest/role/session tests, original catches exact baseline, full 492 tests |
| C1-04 — xóa Invoice Resources an toàn | PASS — no app/test references, test-owned public contract và full suite |
| C1-05 — required regression gates | PASS — 492 PHP/2860 assertions, 75 Node, syntax 3/3 và routes 16, exit 0 |
| C1-06 — scope/checklist/evidence | PASS — 538-entry baseline, 4 modified/2 deleted, UTF-8/whitespace/diff audit |

Recommended Next Task: owner nghiệm thu 02C.1; sau đó chốt/duyệt phạm vi 02C.3 cập nhật 9 file kiến trúc, AGENTS.local.md, 5 skills và checklist. Parent INV-ARCH-02 còn mở. Dừng sau Task Result trên chat.


### INV-ARCH-02C.1 — owner acceptance

Owner nghiệm thu 02C.1 trực tiếp trên chat trước yêu cầu Mini Plan 02C.3, sau đó duyệt triển khai riêng 02C.3. 02C.1 ACCEPTED; PENDING/Recommended Next Task trong báo cáo trước đó là lịch sử. 02C.2 giữ ACCEPTED. Evidence 492 PHP/2860 assertions, 75 Node của 02C.1 được đọc đối chiếu log nguồn, không chạy lại ở lượt tài liệu này. Parent INV-ARCH-02/02B/02C chưa tự đóng.

## INV-ARCH-02C.3 — đồng bộ 9 file kiến trúc, local rules và skills

Owner duyệt đúng 9 file sửa; giữ AGENTS.md và application code. Status IMPLEMENTED / TECHNICAL PASS; Owner Acceptance PENDING. 02C.1/02C.2 ACCEPTED; parent INV-ARCH-02 và các parent checkpoints vẫn chờ tổng hợp/owner acceptance, không tự đóng UC/browser/MySQL gates.

### Implemented và files changed

0 file source mới/xóa; đúng 9 file sửa:
- ARCHITECTURE.md: cập nhật sections 4/13/14, naming/tree và read/create DTO/Repository/Common; explicit Model groups và mapping runtime/pilot; Provider engine registrations khác Repository bindings. Sửa sơ đồ Invoice cũ CHECKED_IN/check-out sang thực tế COMPLETED/Host tạo Invoice, giữ UNPAID/defaults và không suy schema enum là feature đã hỗ trợ. Endpoint session table được ghi rõ là danh sách tiêu biểu, không toàn bộ inventory.
- docs/ai-frontend-development-guide.md: sections 17-19 dùng một invoiceService.js cho năm create/read operations, mapper/result shapes, expected errors/Auth/signal/no-replay và Node module-mocks flag; phân biệt module/build evidence với browser.
- AGENTS.local.md: inventory mới, phân tầng/DI, read-vs-create error order, raw/typed DTO, Model groups/SERVICE, logging legacy và trạng thái acceptance. Giữ quy trình TV3/ownership, chat-only plan/result và sự khác biệt guidance hiện hành với AGENTS.md được owner yêu cầu giữ nguyên.
- .agents/skills/stayhub-task-planning/SKILL.md: current inventory và scope planning dựa DTO/groups/persistence/Auth/transaction/logging, không sinh lớp hoặc module forwarding dư.
- .agents/skills/stayhub-laravel/SKILL.md: query-free Validator, typed HTTP create, no automatic Repository binding, explicit groups/candidate checks, Resource deletion và create message-only compatibility.
- .agents/skills/stayhub-react/SKILL.md: một service/factory/public exports, create/detail/list mapper, shared Axios/Auth/signal/identity, test module mock và browser evidence boundary.
- .agents/skills/stayhub-database/SKILL.md: bốn read/sáu create Repository methods, Service transaction/duplicate-fact order/eager loading, Model validation/rollback/outer commit và DB isolation.
- .agents/skills/stayhub-verification/SKILL.md: current acceptance matrix, independent contract expected values, Model/SERVICE/no-query/rollback/logging regression và docs-only checks.
- docs/TV3/TV3_CHECKLIST.md: owner nghiệm thu 02C.1, bảng refactor hiện hành, implementation/evidence/AC 02C.3; lịch sử naming/test counts giữ nguyên nguồn.

Năm skill names/descriptions/frontmatter và agents/openai.yaml giữ baseline; không thêm references/scripts/assets hoặc settings. Dọn phần inventory lặp/lỗi thời trong skills, giữ workflow tổng quát/ownership và trỏ kiến trúc làm nguồn chi tiết. Không sửa code để khớp tài liệu.

### Source fidelity và giới hạn

- Source-confirmed: một InvoiceRepository 4 read/6 create methods; InvoiceValidator gom read/create không query/lazy-load; InvoiceMapper projection tường minh; raw attribute DTO và typed CreateInvoiceRequest; required workflow DI; explicit invoice-create/invoice-create-room trước insert; SERVICE empty/whitespace/'0' giữ hành vi; Common success 201 và expected create errors message-only; frontend một invoiceService.js.
- Symfony engine bindings ở AppServiceProvider vẫn cần; EloquentObjectMapper chỉ runtime adapter/pilot loaded scalar allowlist, chưa thay mọi production mapping và không phải Java compile-time MapStruct.
- InvoiceCalculationService còn legacy begin/end INFO và optional Validator/Logger fallback. Không claim đã sửa toàn bộ Calculator/DI/logging. 9 security advisories/6 baseline packages được ghi từ 02B.1 vẫn unresolved, không chạy lại audit hoặc coi đã fix.
- Target áp toàn project nhưng Review/TV1/TV2 và backlog dashboard/payment/FAQ/chat vẫn có scope/ownership riêng; không coi đã migrate hoặc hoàn thành use case nhờ tài liệu.
- Browser/real backend Auth/UI stale-response/double-click/responsive và MySQL concurrency NOT VERIFIED. Static skill review không chứng minh auto-selection/behavior của agent ở lượt tương lai.

### Checks Executed

- Skill Creator original scripts/quick_validate.py: 5/5 PASS, từng process exit 0. Lần đầu thiếu PyYAML; cài PyYAML 6.0.3 vào riêng .agent-workspace/tv3/inv-arch-02c3-validation/tools và chạy validator bằng PYTHONPATH/PYTHONUTF8 của child process. Không sửa project/global Python dependencies hoặc validator gốc; YAML/frontmatter/naming/scaffold checks thật, không tự dựng PASS thay thế.
- Local Markdown links/anchors: 54/54 PASS, 0 missing paths/anchors trên cả 9 file. Current inventories không còn obsolete InvoiceReadValidator/InvoiceCreationValidator/InvoiceRequestMapper/invoiceReadService/Invoice Resource class references; checklist giữ tên cũ trong lịch sử hoặc ghi rõ đã xóa.
- Source inventory/contract review PASS: kiểm tra 10 Repository methods, 5 Request DTO files, Controller/Service/Validator/Mapper/Models/provider/frontend, Model groups/candidate calls và Calculator legacy. Lịch sử 02C.1 PHP 492/2860 + Node 75 và 02C.2 lint 3 warnings được đối chiếu logs nguồn, không execute lại các suites.
- UTF-8/BOM/trailing whitespace và git diff --check PASS; existing line-ending conversion warnings không phải failure.
- Baseline 536 source/config/doc entries tại .agent-workspace/tv3/inv-arch-02c3-validation/baseline.json, gồm ignored AGENTS.local.md/skills metadata; final hash/scope audit đúng 9 modified, 0 new/deleted source. AGENTS.md, app/tests/Frontend/dependencies/schema/skill metadata và mọi file khác giữ baseline.
- Evidence cùng thư mục: source-head.txt, git-before.txt, before snapshots, skill-validators.log, content-links-audit.log, scope-audit.log và tools/. Đây là execution evidence/tooling cô lập, không file plan/result. Không stage/commit/push, không DB operation.
- Application suites PHPUnit/Node/lint/build NOT RUN ở 02C.3 vì không đổi application code; các con số lịch sử bên trên không phải test mới. Không browser/MySQL gate hoặc independent live-agent behavior test trong checkpoint này.

| AC | Kết quả và evidence |
|---|---|
| C3-01 — Backend/Frontend architecture đúng code | PASS — source inventory/contracts/flow và active trees/links |
| C3-02 — local rules/5 skills thống nhất naming/phân tầng | PASS — current-state content review, no obsolete current references, 5 validators |
| C3-03 — groups/SERVICE/logging/legacy đúng giới hạn | PASS — Model/Validator/Service/Calculator/provider/mapper source đối chiếu |
| C3-04 — skill validators và links hợp lệ | PASS — original validator 5/5 exit 0, 54 links/anchors |
| C3-05 — checklist acceptance/evidence đúng nguồn | PASS — 02C.1 ACCEPTED, 02C.3 PENDING, lịch sử giữ nguồn và parent chưa tự đóng |
| C3-06 — đúng 9 file, giữ AGENTS.md/application code | PASS — 536-entry hash/scope audit, metadata/code untouched, UTF-8/whitespace/diff |

Recommended Next Task: owner nghiệm thu 02C.3, sau đó audit tổng hợp và đề nghị nghiệm thu parent INV-ARCH-02 theo phạm vi refactor; không suy đóng browser/MySQL/use cases hoặc tự bắt đầu Review. Dừng sau Task Result trên chat.


### INV-ARCH-02C.3 — owner acceptance

Owner nghiệm thu 02C.3 trên chat, sau đó duyệt INV-ARCH-02-AUDIT với scope chỉ sửa checklist. 02C.3 ACCEPTED; PENDING/Recommended Next Task phía trên là lịch sử trước nghiệm thu. Các kết quả 5 validators/54 links của 02C.3 giữ nguồn cũ; audit dưới đây chạy lại các gates và ghi evidence mới riêng.

## INV-ARCH-02-AUDIT — nghiệm thu kỹ thuật tổng hợp Invoice refactor

Status TECHNICAL PASS — đủ điều kiện đề nghị owner đóng parent INV-ARCH-02 trong phạm vi refactor Invoice read/create đã duyệt. Owner Acceptance của audit/parent PENDING; chưa đánh dấu [x] parent. Toàn bộ checkpoint 02A, 02B.1/02B.2A/02B.2B/02B.3, 02C.1/02C.2/02C.3 đã được owner nghiệm thu riêng. Không tự chuyển Review hoặc task nghiệp vụ mới.

### Scope và kết luận audit

Chỉ sửa docs/TV3/TV3_CHECKLIST.md. AGENTS.md, code Backend/Frontend, tests, dependencies/schema, architecture/local rules/5 skills và metadata giữ baseline. Không thêm/sửa test để đạt audit, không fix code ngoài scope. Evidence/logs/build nằm trong .agent-workspace/tv3/inv-arch-02-audit/, không file plan/result.

- Inventory/phân tầng: concrete InvoiceRepository 4 read/6 create methods, constructor DI; Controller không query/business/transaction, Validator/Mapper không query/lazy-load; Service/QueryService giữ workflow/quyền và Repository ownership/eager-loading/persistence. Không còn obsolete Invoice validator/mapper/Resources hoặc split frontend read/create service imports.
- DTO/Mapper: raw mixed input attributes trước normalize; CreateInvoiceRequest typed được HTTP consume, actor từ session; Mapper/JsonSerializable DTO giữ explicit fields/decimal/date/null/items. Symfony mapper adapter/pilot chưa thay hết production mapping; không claim compile-time MapStruct.
- API/Auth: HTTP create 201 một data envelope, read pagination/meta/links/detail parity; read role-before-input/privacy-404 và create raw-ID/actor/Service order giữ nguyên; expected create message-only errors, read codes/framework fields và Auth session semantics PASS.
- Tài chính/persistence: Booking COMPLETED, server Booking/BookingService snapshots, BCMath/exact decimal, chống client override, unique booking/code classification và atomic header/items rollback được regression gates xác nhận. SQLite controlled conflict tests không phải InnoDB race proof.
- Model/logging: explicit Invoice/ROOM validation trước respective insert, default/save không global hook, SERVICE blank/whitespace/'0' giữ behavior. Owned SQLite connection tests xác nhận outer commit mới success log, rollback/no stale callback; không suy toàn bộ Calculator logging/optional DI đã chuyển.
- Frontend: một invoiceService.js cho năm operations, factory/public exports giữ contract, invoiceApi/shared Axios và invoiceMapper phân trách nhiệm; signal/rejection identity/Auth account-vs-role/version/network/timeout/cancel/no replay và malformed-success regression PASS.
- Docs/skills: five validators/link/content/source fidelity PASS; dated histories không thay thành evidence mới. 02C.3 acceptance không suy parent đã được owner nghiệm thu.

### CRUD và phạm vi đóng parent

Repository/Mapper/Validator/InvoiceService/frontend invoiceService là các lớp/module theo domain, có thể bổ sung update/delete ở đó khi use case được duyệt. Raw/typed Request DTO vẫn tách theo input contract. Backend InvoiceQueryService hiện giữ read orchestration riêng, không tự tạo thêm một service cho từng CRUD method.

Hiện đã refactor Create và Read; Update payment thuộc UC36 còn backlog workflow/API/UI, Delete chưa có route/use case được chốt trong phạm vi này. Không thêm update/delete chỉ để đủ CRUD. Đóng INV-ARCH-02 chỉ xác nhận refactor các luồng đã triển khai; không đóng UC36, QA browser/MySQL hoặc toàn bộ Invoice business roadmap.

### Tests Executed — evidence mới của audit

- Safety: phpunit.xml SQLite :memory:, bootstrap/cache/config.php không tồn tại; process chạy PHP tests/route inventory được đặt APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL rỗng. Test guards xác nhận effective config; commit gate named in-memory connection riêng/production manager và cleanup owned resources. Không truy cập/reset/reseed MySQL development.
- Focused php artisan test --filter='InvoiceValidatorTest|InvoiceMapperTest|InvoiceRepositoryTest|InvoiceCreationTest|InvoiceCreationCommitTest|HostInvoiceApiTest|InvoiceQueryApiTest|InvoiceAttributeMappingTest': 114 PASS, 718 assertions, exit 0, 3.22s.
- Full php artisan test: 492 PASS, 2860 assertions, exit 0, 23.82s, không skip. Bao gồm Auth/Profile/Review regressions và toàn bộ Invoice read/create/Model/rollback/commit logging.
- Frontend node --experimental-test-module-mocks --test tests/*.test.js: 75/75 PASS, fail/cancel/skip 0, exit 0. Shared Axios module mock/adapter fixtures giữ đúng giới hạn module tests, không real browser/backend.
- npm run lint: PASS, exit 0; 0 errors, 3 react-refresh/only-export-components warnings tại badge.jsx/button.jsx/sidebar.jsx giữ baseline.
- npm run build -- --outDir ../.agent-workspace/tv3/inv-arch-02-audit/build: PASS, exit 0, 3316 modules transformed; JS chunk 1078.77 kB/gzip 303.68 kB, cảnh báo >500 kB; outDir cô lập ngoài project không tự empty. Không đổi chunk config để che warning.
- php artisan route:list --path=api: 16 routes, exit 0. Billing có 4 GET/1 POST hiện hành, không tự thêm update/delete.
- Original Skill Creator quick_validate.py: 5/5 PASS, mỗi process exit 0. Reuse PyYAML tooling isolated từ evidence 02C.3 bằng child PYTHONPATH/PYTHONUTF8/PYTHONDONTWRITEBYTECODE; không sửa project/global deps hoặc validator gốc.
- Markdown local links/anchors: 54/54 PASS trước cập nhật audit; source/inventory/ordering/groups/removed-file references reviewed và static assertions PASS. Sau cập nhật checklist chạy lại link/anchor/scope/whitespace checks.
- Git status trước/sau, baseline 536 source/config/doc entries; final hash audit chỉ checklist thay đổi, 0 new/deleted source; AGENTS.md và toàn bộ application code/tests/dependencies/skills/metadata giữ nguyên. UTF-8/BOM/trailing whitespace và git diff --check PASS; existing line-ending conversion warnings không phải whitespace failure.
- Evidence: .agent-workspace/tv3/inv-arch-02-audit/{baseline.json,source-head.txt,git-before.txt,checklist-before.txt,backend-focused.log,backend-full.log,frontend-node.log,frontend-lint.log,frontend-build.log,routes.log,skill-validators.log,static-audit.log,scope-audit.log} và build/. Không stage/commit/push, không standalone plan/result file.

### Acceptance criteria

| AC | Kết quả và evidence |
|---|---|
| AUD-01 — Inventory/phân tầng/DI/DTO/Mapper | PASS — static chain/source review, DI/repository/mapper tests |
| AUD-02 — API/Auth/error order/response parity | PASS — exact HTTP/read-create tests và Auth/Profile full suite |
| AUD-03 — Snapshot/money/uniqueness/atomicity | PASS — calculator/snapshot/client override/duplicate/rollback regression |
| AUD-04 — Model groups/SERVICE/logging | PASS — explicit-group/no-query/mutation/negative-control/outer-commit tests |
| AUD-05 — Frontend service/API/mapper/Auth/signal/errors | PASS — 75 Node tests và production compile |
| AUD-06 — Full tests/lint/build/5 validators | PASS — native exits 0; lint/build warnings ghi riêng |
| AUD-07 — Checklist/evidence/scope | PASS — chỉ checklist đổi so 536-entry baseline, links/whitespace/history đúng nguồn |
| AUD-08 — Closure scope và backlog tách rõ | PASS — chỉ refactor read/create; CRUD/domain files không đồng nghĩa đủ UC36/Delete/browser/MySQL |

### Risks / unresolved và đề nghị đóng parent

Browser/real backend Auth/UI duplicate-click/stale/unmount/auth-change/responsive và MySQL concurrency NOT VERIFIED, tiếp tục thuộc QA gates riêng. Calculator legacy begin/end INFO + optional DI và 9 security advisories/6 baseline packages từ 02B.1 chưa sửa; audit này không chạy lại Composer audit hoặc claim clean security/production readiness. Runtime mapping adapter vẫn pilot như kiến trúc ghi nhận. Không phát hiện regression/blocker trong các gates đã duyệt của parent refactor.

Recommended Next Task: owner nghiệm thu INV-ARCH-02-AUDIT và đóng parent INV-ARCH-02 (cùng parent checkpoint tổng hợp tương ứng) trong phạm vi refactor. Giữ backlog UC36/Review/QA và các giới hạn trên mở. Dừng, không tự triển khai task tiếp theo.


## INV-ARCH-02 — owner nghiệm thu audit và đóng parent

Owner nghiệm thu INV-ARCH-02-AUDIT và yêu cầu đóng parent INV-ARCH-02 trực tiếp trên chat. Trạng thái hiện hành: audit ACCEPTED; parent INV-ARCH-02 CLOSED / ACCEPTED, bao gồm các checkpoint tổng hợp 02B/02C và toàn bộ subtasks đã nghiệm thu. Các PENDING/TODO/đề nghị đóng trong sections lịch sử phía trên được thay bởi xác nhận này, không sửa lại counts/lệnh/evidence cũ.

| Đóng | Phần | Trạng thái hiện hành |
|---|---|---|
| [x] | INV-ARCH-01 — Invoice read Backend/Frontend | CLOSED / ACCEPTED |
| [x] | INV-ARCH-02A | ACCEPTED |
| [x] | 02B.1 / 02B.2A / 02B.2B / 02B.3 và parent 02B | CLOSED / ACCEPTED |
| [x] | 02C.1 / 02C.2 / 02C.3 và parent 02C | CLOSED / ACCEPTED |
| [x] | INV-ARCH-02-AUDIT | PASS / ACCEPTED |
| [x] | INV-ARCH-02 — Invoice create refactor parent | CLOSED / ACCEPTED |
| [ ] | UC36 — cập nhật thanh toán | Backlog nghiệp vụ, chưa có workflow/API/UI được nghiệm thu |
| [ ] | REV-ARCH-01 / REV-ARCH-02 | TODO / NOT VERIFIED |
| [ ] | QA-ARCH-01 và browser/MySQL gates | Còn mở / NOT VERIFIED |

Phạm vi đóng là refactor Invoice read/create đã triển khai, không chứng nhận đủ CRUD hoặc hoàn tất mọi Invoice use case. Update/delete có thể bổ sung trong các file domain chung khi có nghiệp vụ/scope được duyệt; không tự tạo cho đủ CRUD. Calculator legacy logging/optional DI, baseline security advisories và các QA giới hạn giữ nguyên như audit.

Lượt closure chỉ cập nhật checklist; giữ nguyên AGENTS.md, application code/tests/dependencies/architecture/local rules/skills/metadata. Git diff --check, UTF-8/whitespace và scope/hash trước–sau xác nhận chỉ file này thay đổi. Không chạy lại application suites: evidence audit 492 PHP tests/2860 assertions, 75 Node tests, lint/build/5 validators PASS thuộc lượt INV-ARCH-02-AUDIT, không phải execution mới của closure. Không stage/commit/push; không tạo file plan/result hoặc bắt đầu task tiếp theo. Dừng sau báo cáo checklist trên chat.


## VAL-STRUCT-01 — gom Validation/Validators về một root Validators

Owner duyệt đúng 3 file di chuyển và 17 file sửa theo Mini Plan. Status IMPLEMENTED / TECHNICAL PASS; Owner Acceptance PENDING. Parent INV-ARCH-02 và audit giữ CLOSED / ACCEPTED; task này không mở lại Invoice parent hoặc bổ sung nghiệp vụ CRUD.

### Cấu trúc và files changed

Root hiện hành backend/app/Validators/:
- AttributeValidator.php: shared adapter gọi Symfony Validator, không HTTP/container coupling trong DTO/Model.
- Constraints/CanonicalPositiveId.php và CanonicalPositiveIdValidator.php: custom attribute/validator cùng namespace để Symfony validatedBy() tự resolve.
- Invoice/{InvoiceValidator,InvoiceCalculationValidator}.php và Review/ReviewCreationValidator.php: domain validators giữ trách nhiệm hiện hữu.

3 file di chuyển có đổi namespace (logic nguyên vẹn):
- backend/app/Validation/AttributeValidator.php -> backend/app/Validators/AttributeValidator.php
- backend/app/Validation/Constraints/CanonicalPositiveId.php -> backend/app/Validators/Constraints/CanonicalPositiveId.php
- backend/app/Validation/Constraints/CanonicalPositiveIdValidator.php -> backend/app/Validators/Constraints/CanonicalPositiveIdValidator.php

Old root app/Validation đã hết file và bỏ directory rỗng; không alias/forwarder hoặc namespace runtime cũ. tests/Feature/Validation giữ tên test category, không phải application root thứ hai. AppServiceProvider/engine bindings không đổi.

8 consumer/test files sửa:
- backend/app/Validators/Invoice/InvoiceValidator.php: import AttributeValidator mới.
- backend/app/DTOs/Invoice/Requests/InvoiceCreateInputRequest.php và InvoiceIdInputRequest.php: import CanonicalPositiveId mới.
- backend/tests/Unit/InvoiceValidatorTest.php: adapter FQCN mới.
- backend/tests/Feature/Validation/AttributeValidatorTest.php: import mới và 2 test methods cho class autoload/container/no aliases, validatedBy/new namespace/actual engine/DTO Reflection attributes.
- backend/tests/Feature/Validation/InvoiceAttributeMappingTest.php: import adapter mới.
- backend/tests/Feature/Invoice/InvoiceCreationTest.php và InvoiceCreationCommitTest.php: test-only validator dependencies theo FQCN mới.

9 guidance/checklist files sửa:
- ARCHITECTURE.md: cây Validators/AttributeValidator/Constraints/domain và link/namespace mới, giải thích convention/DI không đổi; cập nhật trạng thái parent đã owner đóng.
- docs/ai-frontend-development-guide.md: internal namespace không làm đổi transport/API/Auth/UI; parent closure hiện hành.
- AGENTS.local.md: canonical shared/domain root, no alias/forwarder/provider dư; giữ closed parent và task mới nghiệm thu riêng.
- .agents/skills/stayhub-task-planning/SKILL.md: cấu trúc dùng khi lập task/import/autoload verification.
- .agents/skills/stayhub-laravel/SKILL.md: common/domain/constraint namespaces, validatedBy và no-scripts autoload.
- .agents/skills/stayhub-react/SKILL.md: PHP namespace move không kéo theo đổi API/frontend modules.
- .agents/skills/stayhub-database/SKILL.md: validation infrastructure/domain grouping không thay schema/persistence/transaction.
- .agents/skills/stayhub-verification/SKILL.md: moved-class/negative alias/engine resolution/scope checks.
- docs/TV3/TV3_CHECKLIST.md: implementation/evidence/AC/acceptance pending, lịch sử giữ nguồn.

Tổng 3 namespace-only moves + 17 modified (8 consumers/tests + 9 guidance/checklist). Không thêm class; AGENTS.md, Models/Symfony built-in attributes, Controller/Service/Repository/Mapper/Common/Exceptions/routes/Auth/Frontend/Review validator/Composer source và skill metadata giữ baseline. Chỉ refresh generated vendor autoload qua lệnh đã duyệt.

### Tests Executed và evidence

- composer dump-autoload --no-scripts: PASS, exit 0, optimized autoload 6974 classes. Không chạy setup/migration/discovery hooks, không update/install dependencies hoặc đổi composer.json/lock.
- Focused php artisan test --filter='AttributeValidatorTest|InvoiceAttributeMappingTest|InvoiceValidatorTest|InvoiceCalculationServiceTest|InvoiceRepositoryTest|InvoiceCreationTest|InvoiceCreationCommitTest|HostInvoiceApiTest|InvoiceQueryApiTest': 126 PASS, 866 assertions, exit 0, 4.22s.
- Full php artisan test: 494 PASS, 2875 assertions, exit 0, 24.39s, không skip. Includes Auth/Profile/Review, raw IDs/field errors/DTO/Model groups/no-query/SERVICE/financial/rollback/commit-log regressions. 2 new tests/15 assertions so với audit parent, không đổi test expectations nghiệp vụ cũ.
- node --experimental-test-module-mocks --test tests/*.test.js: 75/75 PASS, fail/cancel/skip 0, exit 0. Frontend không sửa nên lint/build NOT RUN, không relabel kết quả audit cũ là vừa chạy.
- New namespace tests: 3 classes autoload; old namespace class_exists false (không alias); container resolve adapter; Constraint.validatedBy bằng đúng new validator FQCN; explicit group valid/invalid ID đi qua Symfony engine; Reflection đọc đúng DTO attribute class, messages/semantics giữ nguyên.
- PHP syntax 11/11 files moved/sửa PASS. php artisan route:list --path=api: 16 routes, exit 0. Original Skill Creator validators 5/5 PASS/exit 0, reuse isolated PyYAML tooling từ evidence 02C.3 qua child env; không sửa global/project dependencies hoặc validator gốc.
- Content/source/link checks: 3 moved classes exact before bodies sau normalize newline và thay duy nhất namespace; no old imports/instantiations trong app/tests; old FQCN strings chỉ trong explicit negative-alias test và dated history. Local Markdown paths/anchors 54/54 PASS; active architecture tree/link đã đổi đúng root.
- DB safety: không cached config; PHP test process APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL rỗng; test effective-config guards/owned commit connection giữ nguyên. Không truy cập/reset/reseed development MySQL.
- Baseline 536 source/config/doc entries: .agent-workspace/tv3/val-struct-01/baseline.json. Final scope 3 moves + 17 modifications, no unexpected source; AGENTS.md/code ngoài scope/metadata/dependencies nguyên vẹn. UTF-8/BOM/trailing whitespace, links và git diff --check PASS, existing line-ending conversion warnings ghi riêng không phải test failure.
- Evidence cùng thư mục: source-head.txt, git-before.txt, original moved-class snapshots, autoload.log, backend-focused.log, backend-full.log, frontend-node.log, routes.log, skill-validators.log, content-links-audit.log và scope-audit.log. Không standalone plan/result, không stage/commit/push, không tự triển khai task tiếp theo.

### Acceptance criteria

| AC | Kết quả và evidence |
|---|---|
| VAL-01 — một root Validators, không legacy aliases | PASS — old root absent, inventory/import scan và negative alias tests |
| VAL-02 — autoload/DI/Symfony resolution | PASS — no-scripts autoload + container/validatedBy/actual engine tests |
| VAL-03 — attributes/groups/messages/ID semantics | PASS — moved bodies namespace-only, raw ID/DTO Reflection/Model groups/field errors regression |
| VAL-04 — API/Auth/nghiệp vụ/persistence/logging | PASS — 494 backend tests, exact HTTP/SERVICE/financial/rollback/afterCommit gates |
| VAL-05 — Full Backend/Node/5 validators | PASS — 494 PHP/2875 assertions, 75 Node, 5 validators và native exits 0 |
| VAL-06 — docs/scope/AGENTS.md | PASS — 9 docs/skills/checklist đồng bộ, 54 links, 536-entry scope hash và metadata preserved |

### Risks / deviations và next action

Không thay business logic hoặc API/Auth contract. Browser/MySQL concurrency/real UI/Auth integration vẫn NOT VERIFIED, không được đóng bằng namespace refactor. Calculator legacy logging/optional DI và security advisories từ 02B.1 giữ nguyên; không chạy lại Composer audit. Symfony mapping vẫn runtime adapter/pilot như kiến trúc ghi nhận.

Recommended Next Task: owner nghiệm thu VAL-STRUCT-01; sau đó chọn task nghiệp vụ/refactor tiếp theo qua Mini Plan riêng (Review/UC36/QA còn mở). INV-ARCH-02 vẫn CLOSED / ACCEPTED. Dừng sau Task Result trên chat.
