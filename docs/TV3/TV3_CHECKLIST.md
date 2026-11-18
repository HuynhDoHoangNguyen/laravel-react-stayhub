# StayHub TV3 — Checklist

Cập nhật: 2026-11-18 (Asia/Bangkok). Phạm vi theo [phân công TV3](../stayhub-team-work-assignment.md): 18 UC, FAQ Management và Chat History.
Baseline hiện hành: `feature/tv3-uc47-admin-dashboard`, HEAD `5001a61` cộng working tree UC47 đã được nghiệm thu; không dùng HEAD riêng thay code hiện hữu. [Kiến trúc](../../ARCHITECTURE.md) quy định cách triển khai.

## 1. Quy ước trạng thái

- `[x]` ở cột triển khai: đã có luồng chính; không đồng nghĩa nghiệm thu toàn chức năng.
- `[x]` ở cột nghiệm thu: owner đã nghiệm thu chức năng trong phạm vi ghi rõ. `[ ]` là chưa hoàn thành hoặc chưa đủ bằng chứng nghiệm thu.
- `NOT VERIFIED`: chưa có bằng chứng kiểm chứng cho phần đang nói tới. Kết quả test trong hồ sơ là lịch sử, không phải vừa chạy lại.
- Checklist chỉ lưu kết quả và việc còn thiếu. Mini Plan/Task Result trình bày trên chat.

## 2. Checklist 18 UC

| UC | Luồng chính | Nghiệm thu chức năng | Đã có / còn thiếu |
| --- | --- | --- | --- |
| UC15 — Xem hóa đơn cá nhân | [x] | [ ] | Customer list/detail API/UI; refactor ACCEPTED. Còn QA/nghiệm thu chức năng Invoice. |
| UC16 — Tạo Review | [x] | [ ] | API/modal và refactor ACCEPTED; fixture/browser đã kiểm. Còn điểm vào Booking thật TV2 và QA tích hợp. |
| UC17 — Chat với chatbot | [ ] | [ ] | Còn provider, API/UI gửi câu hỏi, lưu message và xử lý lỗi. |
| UC34 — Tạo Invoice từ Booking | [x] | [ ] | API/UI, snapshot, transaction, uniqueness đã có; refactor ACCEPTED. Còn nghiệm thu chức năng với Booking thật. |
| UC35 — Chi tiết Invoice Host | [x] | [ ] | API/UI, items và ownership đã có; refactor ACCEPTED. Còn nghiệm thu chức năng Invoice riêng. |
| UC36 — Cập nhật trạng thái thanh toán | [x] | [x] TV3 | API/UI và QA đã nghiệm thu; F01/F02 CLOSED. Tích hợp Booking TV2 hoãn riêng; xem [hồ sơ UC36](UC36_TV2_HANDOVER.md). |
| UC37 — Quản lý Invoice | [x] | [ ] | Host list/lọc/tìm kiếm/phân trang đã có; refactor ACCEPTED. Còn QA/nghiệm thu chức năng quản lý Invoice. |
| UC38 — Host xem Review | [x] | [ ] | API list/summary và UI có evidence browser/XAMPP; refactor ACCEPTED. Còn nghiệm thu chức năng. |
| UC39 — Dashboard Host | [ ] | [ ] | Có khung UI; còn chốt KPI/kỳ/timezone/refund và triển khai query/API/UI. |
| UC44 — Admin xem toàn bộ Booking | [ ] | [ ] | Còn contract Booking TV2, query/API/UI, lọc/phân trang và kiểm quyền. |
| UC45 — Admin xem toàn bộ Invoice | [x] | [x] TV3 | Owner đã nghiệm thu UC45-CLOSE-01 và đóng UC45 phạm vi TV3 ngày 2026-11-18; contract, evidence và giới hạn tại [hồ sơ UC45](UC45_ACCEPTANCE_HANDOVER.md). |
| UC47 — Dashboard Admin | [x] | [x] TV3 | Owner đã nghiệm thu UC47-UI-01, xác nhận UI01–UI06 đạt và đóng UC47 trong phạm vi thống nhất ngày 2026-11-18. API/sáu KPI/cột/đường/điều hướng, QA và ngoại lệ screen reader tại [hồ sơ UC47](UC47_ACCEPTANCE_HANDOVER.md). |
| UC48 — Thống kê hệ thống | [ ] | [ ] | Còn kỳ/chỉ tiêu/dimension, query/API/UI và đối chiếu số liệu độc lập. |
| UC56 — Tạo InvoiceItem | [x] | [ ] | ROOM/SERVICE cùng transaction tạo Invoice; refactor ACCEPTED. Còn nghiệm thu cùng UC34. |
| UC57 — Tính subtotal | [x] | [ ] | Calculator decimal từ snapshot đã tích hợp; refactor ACCEPTED. Còn đối chiếu AC chức năng với Booking thật. |
| UC58 — Tính tổng thanh toán | [x] | [ ] | Calculator có discount/extra_fee; create hiện dùng 0.00; refactor ACCEPTED. Còn nghiệm thu AC chức năng. |
| UC60 — Kiểm tra điều kiện Review | [x] | [ ] | COMPLETED/ownership/one-review đã có; refactor ACCEPTED. Còn race MySQL và workflow Booking thật. |
| UC61 — Ngữ cảnh nội bộ chatbot | [ ] | [ ] | Còn allowlist, quyền truy cập và context service. |

**Tổng:** 13/18 UC có luồng chính; 3/18 UC được nghiệm thu chức năng phạm vi TV3 (UC36, UC45, UC47); 10 UC còn gate chức năng; 5 UC chưa có luồng hoàn chỉnh.
UC56/57/58/60 nằm trong workflow cha, không cần API/UI riêng chỉ để đủ số lớp.

### UC36 — phần TV3 đã hoàn thành

- [x] UC36-INV-02: contract/quyền/transition/paid_at/no-op được nghiệm thu.
- [x] UC36-INV-03A/03B: Backend + Frontend xác nhận thanh toán và test mở rộng đã nghiệm thu.
- [x] UC36-QA-01: MySQL Docker transaction/outer commit/rollback/concurrency đã nghiệm thu.
- [x] UC36-QA-02A/02B: browser/session/CSRF/pending/recovery/cancellation đã nghiệm thu theo phạm vi kiểm.
- [x] UC36-FIX-01: sửa Host paid_at và hướng dẫn 419; F01/F02 CLOSED.
- [x] UC36-QA-03/04: hồi quy và bổ sung T04/T10 đã nghiệm thu; FK/timeout override chỉ trong harness được duyệt.
- [x] UC36-CLOSE-01: tổng hợp nghiệm thu và chuẩn bị bàn giao đã nghiệm thu.
- [x] UC36 hoàn thành trong phạm vi TV3 theo quyết định owner; Booking production TV2 chưa kiểm chứng và được hoãn riêng.

Contract tại [workflow](INVOICE_PAYMENT_WORKFLOW.md); 12 gate, evidence/giới hạn và dependency tại [hồ sơ UC36](UC36_TV2_HANDOVER.md).

### UC45 — checkpoint QA browser

- [x] UC45-QA-BROWSER-01B: owner xác nhận kiểm tra thủ công B01–B13 và cleanup đều PASS; đã nghiệm thu UI danh sách, phân trang, tìm kiếm/trạng thái, Host/cơ sở, reset và responsive. Không đồng nghĩa đóng toàn UC45.
- Kiểm tra bổ trợ ngày 2026-11-18: 16 đối chiếu HTTP thật PASS trên schema QA riêng (60 hóa đơn, 12 Host, 24 cơ sở); kết quả này không thay thế bằng chứng UI.
- Nguồn bằng chứng UI: owner kiểm tra thủ công; agent không quan sát tự động do connector browser không khả dụng và native Brave từ chối input khi có người dùng thao tác. Lần chạy đầu gate fingerprint FAIL vì checklist được cập nhật trong lúc chạy; lần chạy lại với file ổn định và cleanup đều PASS.
- ENV-ROOT-01 đã được owner nghiệm thu riêng; cấu hình root và các runner hiện có được giữ nguyên trong task này.
- Hướng dẫn thủ công và trạng thái từng case nằm trong evidence local ignored `.agent-workspace/tv3/uc45-qa-browser-01b/`; báo cáo trên chat phải ghi riêng nguồn quan sát UI.
- [x] UC45-QA-BROWSER-01C: owner xác nhận thủ công C01–C10 PASS, đã thấy Pending trước C07–C10, bỏ blocking/throttling và cleanup PASS; task đã nghiệm thu. Nguồn UI là owner, không phải agent quan sát tự động.
- Kiểm tra bổ trợ 01C ngày 2026-11-18: 30 test Node về lỗi/Auth/invoice service PASS; runner HTTP session/CSRF và cleanup trên schema riêng PASS. Không dùng kết quả này thay thế các case UI hoặc kết luận về response cũ ghi đè.
- Giới hạn 01C: IAB đã khả dụng nhưng API điều khiển được cung cấp không có Request blocking/throttling cho fault injection. Lần đầu runner dừng trước provision vì Docker daemon chưa chạy; sau khởi động Docker Desktop, chạy lại trên container MySQL hiện có và cleanup PASS. Checklist chỉ cập nhật sau khi runner đã kết thúc.
- [x] UC45-CLOSE-01: owner đã nghiệm thu task và đóng UC45 phạm vi TV3 ngày 2026-11-18. Ma trận 11 AC, contract, giới hạn và hướng dẫn tái kiểm nằm trong [hồ sơ nghiệm thu/bàn giao](UC45_ACCEPTANCE_HANDOVER.md). Đối chiếu 331 file core khớp QA-API; baseline sau ENV và browser C khớp ngoại trừ checklist tiến độ. Các kết quả test trong hồ sơ là lịch sử, không phải vừa chạy lại tại CLOSE-01.

### UC47 — môi trường QA (UC47-ENV-01)

- Đã triển khai harness MySQL riêng trong `backend/tests/Support/Uc47MySqlEnvironment.php`, `Uc47MySqlTestCase.php`, `RunUc47MySqlTests.ps1`; unit guard và Feature smoke tests thuộc UC47. Chưa triển khai CORE/API/UI dashboard.
- Kiểm chứng ngày 2026-11-18: unit guard 48 tests/61 assertions PASS; bản cuối MySQL Docker 8.4.13, loopback 3307: 53 tests/88 assertions PASS. PHP syntax và PowerShell parser PASS.
- Negative control `-InjectTestFailure`: đúng 1 failed/52 passed, 88 assertions; failure marker xuất hiện sau ghi transaction QA, test tiếp theo xác nhận không rò dữ liệu. Runner exit 1 như thiết kế; đây là kiểm chứng failure-path, không phải suite PASS.
- Hai run cuối: positive `9e2bbe3752494fb69c4f7152e7f2cd1e`, negative `62cee9dca4eb47bdaef5969e1d677211`; cleanup schema/user/connection đều 0, fingerprint dev/source/env trước–sau khớp, không phát hiện password/APP_KEY QA trong evidence. Evidence local ignored: `.agent-workspace/tv3/uc47-env-01/<run_id>/`.
- Thiếu `-Provision` bị từ chối trước kết nối. Suite mặc định bỏ qua 5 Feature MySQL UC47 khi không opt-in. Chỉ migrate schema QA riêng, không chạy seeder; giữ nguyên harness UC36/UC45 và root env.
- Lịch sử gate trước FIX-01: `php artisan test --no-ansi --do-not-cache-result`: 1430 passed, 96 skipped, 1 failed, 7136 assertions. Lỗi duy nhất: `AuthenticationTest::test_every_existing_protected_route_checks_account_status_after_auth` kỳ vọng 32 protected routes nhưng thực tế 35; chạy riêng tái hiện 1 failed/75 assertions. Registry và production code không thay đổi trong ENV-01.
- **UC47-ENV-FIX-01 — TECHNICAL PASS, gate hồi quy ENV-01 PASS (2026-11-18):** cập nhật expected count thành 35 và chú thích trong `backend/tests/Feature/Auth/AuthenticationTest.php`; giữ nguyên kiểm tra middleware/thứ tự/public routes. Baseline 32 cộng route PATCH payment-status UC36 (`482db46`) và hai GET Admin Invoice UC45 (`5001a61`). Không đổi route hoặc cấu hình Auth.
- Kiểm thử FIX-01: PHP syntax PASS; test protected routes 1 passed/75 assertions; toàn AuthenticationTest 53 passed/389 assertions; toàn backend mặc định SQLite in-memory 1431 passed, 96 skipped, 0 failed, 7136 assertions (exit 0). Các test MySQL yêu cầu opt-in tiếp tục skip; không coi skipped là MySQL PASS. Log local ignored: `.agent-workspace/tv3/uc47-env-fix-01/backend-regression.txt`.
- FIX-AC01–06 đạt; ENV-AC01–08 đủ evidence kỹ thuật, ENV-01 TECHNICAL PASS. SHA256 cả 5 file harness ENV-01 trước–sau FIX-01 khớp; không chạy lại MySQL trong FIX-01, giữ bằng chứng các run trước ở trên. Git diff/whitespace đạt, thay đổi bổ sung chỉ Auth test và checklist; không commit/push.
- Chưa kiểm fault injection timeout/kill tiến trình hoặc Docker mất kết nối; chưa có browser/frontend evidence vì đây là task nền tảng. Owner đã nghiệm thu ENV-01 và ENV-FIX-01 trên chat ngày 2026-11-18. UC47 chưa đóng; tiến độ CORE-01 ở dưới.

### UC47 — sáu KPI tổng quan (UC47-CORE-01)

- **TECHNICAL PASS trong phạm vi CORE-01**, ngày 2026-11-18. Owner đã nghiệm thu CORE-01 trên chat ngày 2026-11-18. Không triển khai biểu đồ CHART-01 hoặc tự đóng UC47.
- `GET /api/admin/dashboard`, tên route `admin.dashboard.show`, middleware `auth:sanctum` rồi `account.active`; QueryService kiểm lại ADMIN/ACTIVE khi gọi trực tiếp. Guest 401, role sai 403 `ADMIN_DASHBOARD_FORBIDDEN`, account lỗi giữ mã hiện hành, unexpected error trả 500 an toàn.
- Response `{data: {summary: {users_count, hosts_count, properties_count, rooms_count, bookings_count, revenue_total}, excluded_paid_invoices_count, generated_at}}`. Counts integer; tiền decimal string hai chữ số; timestamp ISO UTC. Chưa có filter hoặc field biểu đồ; query/body không điều khiển scope. Không trả PII/Model.
- Count toàn hệ thống, mọi trạng thái, chỉ các bản ghi hiện tồn tại. Hosts là tài khoản role HOST kể cả chưa có cơ sở. Doanh thu toàn thời gian SUM Invoice.total_amount với PAID và paid_at khác null; loại UNPAID/REFUNDED, PAID thiếu paid_at được đếm riêng để cảnh báo. Không gate Booking status, không cộng lại items/discount/extra_fee; trạng thái refund hiện tại làm thay đổi tổng lịch sử, chưa phải sổ dòng tiền.
- Repository dùng một SQL statement với scalar subqueries, không join nhân tiền, không float hoặc write. UI `/admin`: sáu thẻ, loading/zero/error/retry, giờ Việt Nam, cảnh báo hóa đơn thiếu ngày; AbortController/serial/Auth snapshot bỏ kết quả hết hiệu lực. Formatter dùng Intl + BigInt/decimal parts, bảo toàn số tiền lớn.

| CORE AC | Trạng thái / evidence |
| --- | --- |
| CORE-AC01 | PASS: MySQL API tests kiểm guest/role/account, route middleware; unit direct-service từ chối trước repository. |
| CORE-AC02 | PASS: fixture 7 tài khoản/3 Host/4 cơ sở/4 phòng/6 Booking đủ trạng thái, nhiều Host và Host không có cơ sở; counts khớp độc lập. |
| CORE-AC03 | PASS: hai PAID hợp lệ tổng `19999999999.98`, hai PAID thiếu paid_at bị loại; kiểm refund còn `9999999999.99`, multiple items/services không nhân tiền; zero `0.00`; Node kiểm `9007199254740993.01`. |
| CORE-AC04 | PASS: exact JSON projection, một aggregate statement, snapshot 7 bảng trước–sau GET khớp; 500 không lộ exception. |
| CORE-AC05 | PASS theo gate CORE đã duyệt: source review UI states/request guards + Node/build. Browser React lifecycle/responsive chưa kiểm chứng, giữ cho UC47-QA-01; không suy Node thành bằng chứng UI. |
| CORE-AC06 | PASS: Auth baseline cập nhật 35 → 36 cho route dashboard, giữ assertions middleware; toàn backend exit 0. |
| CORE-AC07 | PASS: MySQL/Node/lint/build và cleanup/fingerprint; chi tiết bên dưới. |
| CORE-AC08 | PASS: scope 19 file bên dưới; guard/TestCase/unit guard/smoke ENV giữ nguyên SHA256; không sửa env/schema/Auth config hoặc commit/push. |

Kết quả thực chạy CORE-01:

- `php artisan test tests/Unit/AdminDashboardContractTest.php --no-ansi --do-not-cache-result`: 5 passed, 13 assertions.
- `php artisan test --no-ansi --do-not-cache-result`: 1436 passed, 100 skipped, 0 failed, 7151 assertions. Môi trường mặc định SQLite in-memory; 4 MySQL CORE tests mới skip có chủ đích, không dùng skipped làm MySQL evidence.
- `RunUc47MySqlTests.ps1 -Provision`: **62 passed, 128 assertions**, MySQL Docker 8.4.13; run `4c4904f69d044374aefaf954a1b3638a`. Gồm 4 CORE API/query tests, 5 contract cases và các ENV tests. API tests dùng Laravel getJson/actingAs trên MySQL thật, chưa phải live HTTP/session browser. Schema/user/connection còn 0; source/dev/env fingerprint khớp. Guard/cleanup giữ nguyên, runner chỉ thêm tests và coverage fingerprint source untracked.
- `node --experimental-test-module-mocks --test tests/adminDashboard.test.js`: 6 passed; toàn `tests/*.test.js`: **181 passed**, 0 fail/skip. Transport mocked qua Axios adapter; không phải HTTP/browser runtime.
- `npm run lint`: exit 0, 0 errors; còn 3 warning react-refresh hiện có tại badge/button/sidebar. `npm run build`: exit 0, còn warning chunk lớn hơn 500 kB. Không mở scope tối ưu bundle/UI primitives.
- Evidence local ignored: `.agent-workspace/tv3/uc47-core-01/backend.txt`, `node.txt`; MySQL tại `.agent-workspace/tv3/uc47-env-01/4c4904f69d044374aefaf954a1b3638a/`. Không có browser evidence hoặc kiểm workflow Booking TV2 production; fixtures được tạo trực tiếp trên schema QA riêng.

File tạo mới (12):

- `backend/app/Http/Controllers/Dashboard/AdminDashboardController.php`
- `backend/app/Services/Dashboard/AdminDashboardQueryService.php`
- `backend/app/Repositories/Dashboard/AdminDashboardRepository.php`
- `backend/app/DTOs/Dashboard/Responses/AdminDashboardResponse.php`
- `backend/routes/api/dashboard.php`
- `frontend/src/api/adminDashboardApi.js`
- `frontend/src/services/adminDashboardService.js`
- `frontend/src/mappers/adminDashboardMapper.js`
- `backend/tests/Support/Uc47DashboardFixtures.php`
- `backend/tests/Feature/Dashboard/Uc47AdminDashboardMySqlTest.php`
- `backend/tests/Unit/AdminDashboardContractTest.php`
- `frontend/tests/adminDashboard.test.js`

File sửa trong CORE-01 (7), so với baseline ENV/FIX đã nghiệm thu:

- `backend/routes/api.php`
- `backend/app/Exceptions/ErrorCode.php`
- `backend/app/Exceptions/GlobalExceptionHandler.php`
- `frontend/src/pages/admin/AdminDashboardPage.jsx`
- `backend/tests/Support/RunUc47MySqlTests.ps1`
- `backend/tests/Feature/Auth/AuthenticationTest.php`
- `docs/TV3/TV3_CHECKLIST.md`

UC47-CHART-01 đã được owner duyệt Mini Plan sau nghiệm thu CORE-01; kết quả triển khai ở checkpoint dưới.

### UC47 — hai biểu đồ (UC47-CHART-01)

- **TECHNICAL PASS trong phạm vi CHART-01**, ngày 2026-11-18; Owner đã nghiệm thu CHART-01 trên chat ngày 2026-11-18. Chưa thực hiện UC47-QA-01 hoặc đóng toàn UC47.
- Mở rộng cùng GET `/api/admin/dashboard`: thêm `booking_status_counts` (đủ 6 trạng thái theo enum), `revenue_by_month` (đủ 12 tháng tăng dần, zero-fill) và `report_timezone = Asia/Ho_Chi_Minh`. Giữ field/KPI CORE, auth/error/envelope và baseline 36 protected routes; không thêm endpoint.
- Booking chart toàn thời gian; tổng 6 count khớp KPI Booking. Revenue chart theo paid_at, PAID có ngày xác nhận, tháng hiện tại và 11 tháng trước theo giờ Việt Nam; tháng hiện tại tính đến generated_at, bao gồm đúng thời điểm cutoff. UNPAID/REFUNDED/PAID thiếu ngày không tính. KPI doanh thu vẫn toàn thời gian, không thay bằng tổng 12 tháng; refund hiện tại làm thay đổi cả tổng lịch sử/tháng tương ứng.
- QueryService chụp một CarbonImmutable UTC clock, dựng biên tháng địa phương rồi truyền epoch boundaries. Repository so TIMESTAMP với FROM_UNIXTIME(bound epoch), không đổi session timezone hoặc cần named timezone tables; tổng hợp bằng conditional aggregates trong derived tables một dòng, chung một SQL statement/read view. Không float tiền, không write hoặc join nhân tiền. MySQL session kiểm chứng có UTC offset 00:00:00; môi trường non-UTC chưa được kiểm chứng trong task này.
- UI dùng component AdminDashboardCharts thuần props: hai biểu đồ thanh ngang, đủ nhãn/count/VND, mô tả kỳ, danh sách dt/dd đọc được, thanh chỉ trang trí aria-hidden; grid responsive và zero states. Chiều rộng thanh dùng BigInt rồi chỉ chuyển tỷ lệ giới hạn sang Number, giữ tiền gốc decimal string. Mapper từ chối thiếu/trùng/sai thứ tự trạng thái/tháng, tổng Booking lệch hoặc timezone/decimal không hợp lệ.

| CHART AC | Trạng thái / evidence |
| --- | --- |
| CHART-AC01 | PASS: 6 trạng thái enum, missing-state zero-fill, tổng count khớp KPI; MySQL và Node. |
| CHART-AC02 | PASS: 12 tháng, UTC/VN half-open boundaries, đầu tháng/giao năm, leap day, inclusive cutoff và future exclusion; unit period + MySQL + Node. |
| CHART-AC03 | PASS: tiền decimal/string, lớn hơn giới hạn một Invoice/JS safe integer; UNPAID/REFUNDED/null bị loại, refund hồi quy. |
| CHART-AC04 | PASS: KPI CORE/permissions/error/36 routes hồi quy; snapshot 7 bảng trước–sau GET khớp, aggregate chỉ 1 statement. |
| CHART-AC05 | PASS theo gate CHART đã duyệt: component/page source review, Node/helper và build; có nhãn/zero states. Browser layout/keyboard/screen-reader thực tế chưa kiểm chứng, giữ cho QA-01. |
| CHART-AC06 | PASS: mapper negative controls và BigInt scale với zero/extreme ratios; không tính lại KPI từ monthly series. |
| CHART-AC07 | PASS: backend/MySQL/Node/lint/build; fingerprint và cleanup đạt. |
| CHART-AC08 | PASS: đúng 12 file; không đổi env/schema/routes/Auth/dependency/runner/guard hoặc commit/push. |

Kết quả thực chạy CHART-01:

- Focused backend contract: **7 passed, 55 assertions**. Full `php artisan test --no-ansi --do-not-cache-result`: **1438 passed, 102 skipped, 0 failed, 7193 assertions**, exit 0, SQLite in-memory theo guard. Hai MySQL chart tests mới skip trong default suite; không dùng skip làm evidence MySQL.
- Runner UC47 `-Provision`: **66 passed, 185 assertions**, MySQL Docker 8.4.13, run `63e6ede60d3b4cb8a7cd1b407af3b599`. Sáu CORE/chart API/query tests, bảy contract cases và ENV suite đều đạt. Fixtures synthetic/getJson/actingAs; không phải live HTTP/session browser hay Booking TV2 production.
- MySQL tests đối chiếu độc lập `2025-11-30 16:59:59/17:00:00 UTC`, `2025-12-31 16:59:59/17:00:00`, `2026-10-31 16:59:59/17:00:00`, cutoff `2026-11-18 07:00:00` và +1 giây; leap day `2024-02-29`. Zero-fill, ngoài kỳ/future, dữ liệu rỗng và refund đạt.
- Schema/user/connection QA còn 0; source/dev/env fingerprint trước–sau khớp. Runner, guard và TestCase không thay đổi so baseline CORE.
- Focused Node dashboard **9 passed**; toàn Node `--experimental-test-module-mocks --test tests/*.test.js`: **184 passed**, 0 fail/skip. Transport dùng mocked adapter; không coi là browser/runtime HTTP.
- `npm run lint`: exit 0, 0 errors, còn 3 warning react-refresh hiện hữu ở badge/button/sidebar. `npm run build`: exit 0, còn warning chunk lớn hơn 500 kB. PHP syntax/Git whitespace/scope đạt.
- Evidence local ignored: `.agent-workspace/tv3/uc47-chart-01/` (baseline hashes, regression logs, scope) và `.agent-workspace/tv3/uc47-env-01/63e6ede60d3b4cb8a7cd1b407af3b599/` (MySQL tests/fingerprints/cleanup). Chưa có browser evidence, chưa benchmark production data hoặc kiểm session non-UTC.

Scope CHART-01 so với CORE đã nghiệm thu: tạo mới `frontend/src/components/dashboard/AdminDashboardCharts.jsx`; sửa 11 file:

- `backend/app/Repositories/Dashboard/AdminDashboardRepository.php`
- `backend/app/Services/Dashboard/AdminDashboardQueryService.php`
- `backend/app/DTOs/Dashboard/Responses/AdminDashboardResponse.php`
- `frontend/src/mappers/adminDashboardMapper.js`
- `frontend/src/services/adminDashboardService.js`
- `frontend/src/pages/admin/AdminDashboardPage.jsx`
- `backend/tests/Support/Uc47DashboardFixtures.php`
- `backend/tests/Unit/AdminDashboardContractTest.php`
- `backend/tests/Feature/Dashboard/Uc47AdminDashboardMySqlTest.php`
- `frontend/tests/adminDashboard.test.js`
- `docs/TV3/TV3_CHECKLIST.md`

Task tiếp theo đề xuất: UC47-QA-01, cần Mini Plan/approval riêng. Dừng sau CHART-01.

### UC47 — browser harness/HTTP (UC47-QA-01A)

- **TECHNICAL PASS**, ngày 2026-11-18; owner đã nghiệm thu QA-01A trên chat. Evidence QA-01A không có browser UI observation; QA-01B được báo cáo riêng dưới đây, UC47 chưa đóng.
- Tạo guard/browser bootstrap/fixture-oracle/runner riêng trong `backend/tests/Support/Uc47BrowserEnvironment.php`, `Uc47BrowserServer.php`, `Uc47BrowserFixtures.php`, `RunUc47Browser.ps1`; unit guard/oracle tại `backend/tests/Unit/Uc47BrowserEnvironmentTest.php`. Root env, production/Auth/config/schema/frontend và harness API/UC36/UC45 giữ nguyên.
- Bốn scenario Populated/Zero/NoRevenue/Large: **215 HTTP status assertions và 16 full oracle comparisons PASS**, qua login/cookie/database session thật, không actingAs. Missing/wrong XSRF trả 419 (8 controls), đúng quyền/account, /me/repeated GET giữ phiên, logout về 401. Snapshot 9 bảng nghiệp vụ khớp, authenticated sessions sau logout 0. Đây không phải số PHPUnit tests hoặc bằng chứng browser UI.
- Unit guard/oracle **49 tests/105 assertions PASS**; full backend mặc định **1487 passed, 102 skipped, 0 failed, 7298 assertions**, exit 0. PHP syntax/PowerShell parser/whitespace/scope PASS. Không chạy frontend lint/build/Node vì không sửa frontend.
- Hai negative controls fail đúng marker Prepare/Ready (exit 1), cleanup đều PASS. Sáu run cuối giữ nguyên dev/source/env fingerprint, owned processes exited, ports hết listener, schema/user/connection 0; SQL cuối toàn prefix UC47 cũng 0/0/0. Không phát hiện secrets trong evidence. Docs cập nhật sau các runner kết thúc.
- A01–A08 đạt theo ma trận và run IDs tại [hồ sơ UC47](UC47_ACCEPTANCE_HANDOVER.md). Evidence local ignored `.agent-workspace/tv3/uc47-qa-01a/` (final-runs.json, verification-summary.json, backend.txt và từng run). Scope đúng 6 file mới/1 file checklist sửa; không commit/push.
- Giới hạn: chưa kiểm UI/responsive/keyboard/race/fault, KeepAlive/lease expiry hoặc browser context. QA-01B cần Mini Plan/approval riêng; dừng sau QA-01A.

### UC47 — browser UI/session/responsive/accessibility (UC47-QA-01B)

- **PARTIAL**, ngày 2026-11-18; chưa TECHNICAL PASS, chưa có owner acceptance kết quả. Run `685296bf324342bf900c24bee3fff55e`, Populated trên React/Vite + Laravel thật, MySQL QA/database session riêng; ChatGPT in-app browser context của task. Không sửa production/frontend; chưa chuyển QA-01C.
- PASS browser: guest redirect; Admin A/B login và đúng danh tính; reload giữ phiên; Tải lại có disabled/loading rồi đúng dữ liệu; logout qua menu/sidebar và Back về login; Host/Customer bị giới hạn /admin; sai khu vực/blocked/inactive hiển thị lỗi. KPI 8/3/4/4/10, doanh thu 300.75, excluded 1; sáu status 1/1/1/5/1/1; 12 tháng đúng, 100.25/200.50 hai tháng cuối.
- PASS responsive Populated tại 1440×900, 768×1024, 375×812: ảnh/DOM không có page horizontal overflow, card/chart đọc được. Tab/Space/Enter và focus visible đã quan sát một phần; menu tài khoản trả focus đúng sau Escape.
- **F-B01 P2:** mobile sidebar đóng bằng native Escape nhưng focus về BODY, không về trigger (tái hiện). **F-B02 P3:** accessible sidebar title/description/toggle còn tiếng Anh. Chưa sửa shared UI trong scope QA.
- **NOT VERIFIED:** browser Zero/NoRevenue/Large, Large mobile/tablet, Shift+Tab, zoom 200% và screen reader thật. AX/DOM inspection không thay screen reader/WCAG acceptance. Computer Use dừng khi chọn Brave để kiểm zoom do không xác minh URL Windows đủ tin cậy; mọi UI action dừng, Brave chưa được điều khiển. Không thể kiểm đóng tab/reset viewport sau tool stop; tab IAB tạm không đánh dấu handoff.
- Runner thêm audit sau KeepAlive; run exit 0/audit PASS: hash 9 bảng nghiệp vụ khớp, authenticated sessions 0; stop file/cleanup đạt, processes exited, ports released, schema/user/connections 0/0/0, dev/source/env fingerprints giữ nguyên. Exit 0 của harness không phải browser gate PASS. PowerShell parser/Git whitespace/scope PASS; không chạy lại PHPUnit/Node/lint/build vì chỉ sửa PowerShell/docs.
- B-AC01/02/07/08 PASS; B-AC03/04/06 PARTIAL; B-AC05 FAIL/PARTIAL. Ma trận B01–B19, finding và evidence ở [hồ sơ UC47](UC47_ACCEPTANCE_HANDOVER.md). Local ignored evidence `.agent-workspace/tv3/uc47-qa-01b/` và thư mục run QA-01A cùng ID. Scope đúng ba file: `RunUc47Browser.ps1`, hồ sơ UC47 và checklist này; không commit/push.
- Đề xuất tiếp theo: Mini Plan xử lý findings và hoàn tất QA-01B còn thiếu với scope riêng. UC47 chưa đóng; dừng sau báo cáo, chưa chuyển UC47-QA-01C.

### UC47 — sửa accessibility và bổ sung browser QA (UC47-QA-01B-FIX-01)

- **PARTIAL**, ngày 2026-11-18; plan đã duyệt, kết quả chưa owner nghiệm thu, gate QA-01B chưa TECHNICAL PASS. **F-B01/F-B02 đã sửa và tái kiểm PASS** trong shared `frontend/src/components/ui/sidebar.jsx`: ref opener/trigger, onCloseAutoFocus kiểm connected/visible/enabled, không trả về BODY/HTML; nhãn dialog/description/trigger/rail bằng tiếng Việt. Không sửa backend/harness/Auth/router/Sheet primitive/dependency.
- Browser source cuối: Admin Enter/Space/click mở sidebar; Tab/Shift+Tab wrap, focus visible; Escape/overlay trả focus về trigger; menu tài khoản Escape trả focus đúng. Admin/Host desktop collapse/expand, Host mobile focus và logout khi sidebar mở đạt; reload session/Enter Tải lại/logout/guest redirect smoke đạt. Các role/account cases trước FIX giữ evidence lịch sử, không nói đã chạy lại toàn bộ browser matrix.
- B12/B14 đã bổ sung: Zero đúng 2/0/0/0/0 và empty Booking/Revenue; NoRevenue đúng booking chart có dữ liệu, revenue/12 tháng 0; Large tổng 19999999999.98 và hai tháng 9999999999.99. Large và Populated kiểm ba viewport 1440×900, 768×1024, 375×812 không tràn ngang, ảnh/DOM đọc được (tiền dài wrap ở tablet). Populated smoke giữ 300.75 và 100.25/200.50.
- **184 Node tests PASS**, 0 fail/skip; lint/build exit 0. Còn 3 Fast Refresh warnings và bundle >500 kB hiện hữu. Không chạy PHPUnit vì không đổi backend/harness. Bốn run HTTP kèm browser: 215 status assertions, 16 oracle comparisons; không gọi đây là số browser tests.
- Positive run IDs: Large `fc8e1ee33fad43899a2e4eaf1b0d74b2`, Zero `e6e53f743a364c5fb5992223d47728ac`, NoRevenue `e629c2c506a0413490fdf08828988634`, Populated `9ca3ec66c9e741eeac2d58c050c7a3fd`. Cả bốn exit 0, business 9 bảng trước/sau browser khớp, authenticated sessions 0; process/port cleanup, schema/user/connections 0/0/0; dev/source/env fingerprints giữ nguyên. Run Large thử trước `c7b5dae9b1724b8cb4784f64b76ef4a9` hết lease khi gián đoạn, result FAIL/cleanup PASS; không tính positive gate.
- **NOT VERIFIED:** zoom 200% (IAB shortcut không đổi zoom đo được), đầu ra screen reader thực (Narrator integrity cao hơn helper, home AX/native foreground không khả dụng). Owner đã xác nhận trên chat “Đã tắt Narrator”; ghi nhận cleanup Narrator theo xác nhận thủ công, không phải bằng chứng screen reader hoặc nghiệm thu gate. Tab QA mới đã đóng/reset viewport; tab error cũ bind để đóng bị URL policy chặn data: document, không bypass. Không gộp các giới hạn UI này vào cleanup harness PASS hoặc WCAG acceptance.
- FIX-AC01/02/03/05/07 đạt; FIX-AC04 PARTIAL; FIX-AC06 đạt phần dữ liệu/tài nguyên harness, còn cleanup UI nêu trên. QA-01B B12/B14/B16/B17 PASS, B18 phần nhãn/semantics PASS nhưng screen reader NOT VERIFIED, B19 NOT VERIFIED. B-AC01/02/03/07/08 PASS; B-AC04/05/06 PARTIAL.
- Hồ sơ và ma trận đầy đủ ở [bàn giao UC47](UC47_ACCEPTANCE_HANDOVER.md). Evidence ignored `.agent-workspace/tv3/uc47-qa-01b-fix-01/` và các thư mục run QA-01A. Scope đúng sidebar + hai docs, giữ nguyên thay đổi baseline ngoài scope; không commit/push. Đề xuất tiếp theo: hoàn tất zoom/screen reader và cleanup UI trên môi trường native phù hợp với scope riêng; **dừng, chưa chuyển UC47-QA-01C**.

### UC47 — kết quả UI thủ công (UC47-QA-01B-NATIVE-01)

- Ngày 2026-11-18, owner xác nhận trên chat: “Tôi đã kiểm tra zoom 200% và bàn phím, đều ổn. Screen reader (bỏ cái này đi )chưa kiểm tra.” Đây là checkpoint hiện hành cho các case còn thiếu; các mục PARTIAL ở trên giữ làm lịch sử.
- [x] B19 — zoom 200%: **PASS theo xác nhận thủ công của owner**; không có ảnh/browser/viewport/scenario cụ thể kèm theo, không suy rộng ra mọi fixture.
- [x] B16/B17 — bàn phím: **PASS theo xác nhận thủ công của owner**, bổ sung evidence focus/keyboard FIX trước; không phải lượt automation mới.
- Screen reader thực: **NOT VERIFIED; owner loại khỏi phạm vi nghiệm thu lần này**. Không đánh PASS, không yêu cầu kiểm tiếp trong task này, không tuyên bố WCAG compliance. B18 nhãn/semantics đã kiểm trước vẫn PASS.
- Phần UI thủ công đã hoàn tất theo phạm vi điều chỉnh và được owner chấp nhận. B-AC04 bổ sung zoom PASS; B-AC05 giữ focus/nhãn/keyboard PASS với ngoại lệ screen reader. B-AC06/cleanup UI cũ vẫn PARTIAL; chưa có xác nhận đóng tab error/reset UI, chưa tuyên bố TECHNICAL PASS toàn gate hoặc đóng UC47.
- Native run trước `cf640cbf69464ee7a2554904ab9f987c` đã dừng, `passed: false` vì session còn đăng nhập khi stop; cleanup không lỗi, schema/user/connections còn 0. Không tính run này là PASS.
- Chỉ cập nhật checklist và [hồ sơ UC47](UC47_ACCEPTANCE_HANDOVER.md), không điều khiển máy tính, không bật harness hoặc chạy thêm QA. Kiểm phạm vi/whitespace cho tài liệu; không chạy lại test suite vì không đổi code. **Dừng, chưa chuyển UC47-QA-01C**, không commit/push.

### UC47 — fault/race/closure (UC47-QA-01C)

- Ngày 2026-11-18, **QA-01C hoàn tất theo phạm vi đã thống nhất**: phần tự động PASS; owner báo “Tôi đã kiểm tra C04–C08 đều đạt, cleanup UI đã xong.” Đây là task cuối theo kế hoạch ban đầu; owner yêu cầu cải thiện UI trước nghiệm thu cuối, **chưa đóng UC47**.
- [x] C01: backend mặc định exit 0, **1487 passed / 102 skipped / 7298 assertions** (SQLite); runner MySQL riêng exit 0, **66 passed / 185 assertions**. Skip mặc định không tính là bằng chứng MySQL.
- [x] C02: case safe 500/middleware đạt ở tầng feature test với service fault giả lập, không lộ thông tin nội bộ; không ghi thành browser 500 đã kiểm.
- [x] C03: toàn Node **184 passed**, 0 fail/skip; có HTTP/network/timeout/cancellation/malformed response và stale-auth pipeline, không phải browser lifecycle. Lint exit 0, 0 errors/3 Fast Refresh warnings hiện hữu; build exit 0, còn cảnh báo chunk >500 kB.
- [x] C04 — PASS theo xác nhận thủ công owner: Offline/Tải lại → Online/Thử lại, lỗi phù hợp và phục hồi dữ liệu.
- [x] C05 — PASS theo xác nhận thủ công owner: Slow 3G, loading, khóa nút Tải lại, ẩn KPI cũ trong lúc tải, kết thúc hoạt động lại.
- [x] C06 — PASS theo xác nhận thủ công owner: rời trang/quay lại khi request pending, không lỗi hủy/hiển thị sai; không có log Network mới gửi kèm.
- [x] C07 — PASS theo xác nhận thủ công owner: logout khi request pending, không hiện dashboard cũ; đăng nhập lại được; không có log Network mới gửi kèm.
- [x] C08 — PASS theo xác nhận thủ công owner: logout tab thứ hai cùng phiên, Tải lại tab dashboard xử lý hết phiên và bỏ dữ liệu cũ.
- C09 **PARTIAL**: runner MySQL run `b7fa18d6d73c41a7a65657d11b4011a9` passed true, cleanup không lỗi, schema/user/connections 0/0/0; dev/source/env fingerprints khớp. Baseline 713 file không đổi trước sửa docs. Không start browser server/session mới. Còn owner xác nhận đóng tab QA/error cũ và trả zoom 100%, Network Online/No throttling, tắt blocking/overrides nếu có dùng.
- Screen reader giữ NOT VERIFIED, owner đã loại khỏi phạm vi. Zoom/bàn phím đã nghiệm thu ở checkpoint trước, không yêu cầu kiểm lại. C06/C07 cần xác nhận request thực sự pending; không suy thành response trả ngược thứ tự đã kiểm.
- [x] C09 — owner đã xác nhận cleanup UI xong; kết hợp cleanup runner đạt ở trên, khép phần cleanup UI còn thiếu của QA-01B. Mô tả PARTIAL/chờ xác nhận phía trên là lịch sử trước phản hồi mới này.
- Chỉ sửa hai docs, không điều khiển máy tính hoặc sửa code/Auth/env/schema. Evidence tại [hồ sơ UC47 — QA-01C](UC47_ACCEPTANCE_HANDOVER.md#qa-01c--kiểm-lỗi-request-đang-chờ-và-tổng-hợp-nghiệm-thu), logs ignored `.agent-workspace/tv3/uc47-qa-01c/` giữ trạng thái lịch sử của lần chạy tự động. Không chạy lại QA khi ghi nhận phản hồi. **QA-01C hoàn tất; nghiệm thu cuối UC47 chờ cải thiện UI theo Mini Plan mới**, không commit/push.

### UC47 — cải thiện UI Tổng quan (UC47-UI-01)

- Ngày 2026-11-18, **UC47-UI-01 ACCEPTED**: owner xác nhận UI01–UI06 đều đạt và chấp nhận kết quả trên chat. Phần tự động PASS, UI PASS theo xác nhận thủ công. **UC47 đã nghiệm thu/đóng trong phạm vi thống nhất**, không commit/push hoặc điều khiển máy tính.
- [x] Source: sáu KPI có icon Lucide, card/theme/hover nhẹ; biểu đồ cột sáu trạng thái và đường 12 tháng, chọn tháng bằng button, hai bảng details có số liệu đầy đủ. Hover/focus/reduced motion và responsive dùng CSS riêng prefix uc47.
- [x] Source/route: link card doanh thu và chart tới `/admin/invoices`; không tạo link hỏng tới users/properties/bookings chưa có route, không tạo query filter chưa hỗ trợ. Giữ money decimal/BigInt ratios, request/auth/session/loading/error/empty và contract hiện có.
- [x] Node **184 passed**, 0 fail/skip; lint **exit 0, 0 errors/3 Fast Refresh warnings hiện hữu**; build **exit 0**, còn warning chunk >500 kB. Render tĩnh React/Vite SSR **4 fixture đạt** (Populated/Zero/NoRevenue/Large); không coi là browser/HTTP hoặc interactive/layout evidence. Không chạy lại backend/MySQL vì chỉ thay frontend trình bày và docs, không truy cập DB.
- [x] UI01 PASS theo xác nhận thủ công owner: sáu KPI/icon, hover đẹp/nhẹ, số tiền không cắt.
- [x] UI02 PASS theo xác nhận thủ công owner: sáu cột/trạng thái/số đơn, mở bảng và đối chiếu KPI.
- [x] UI03 PASS theo xác nhận thủ công owner: chọn tháng bằng click/Tab/Enter/Space, tháng/tiền/điểm nhấn khớp bảng 12 tháng.
- [x] UI04 PASS theo xác nhận thủ công owner: hai link Xem hóa đơn điều hướng đúng và quay lại được.
- [x] UI05 PASS theo xác nhận thủ công owner: desktop/tablet/mobile/zoom 200%, không tràn/chồng; focus và details keyboard rõ.
- [x] UI06 PASS theo xác nhận thủ công owner: reduced motion nếu có, loading/error/retry, zero nếu có dữ liệu; smoke logout/login.
- UI07 PASS: chỉ sửa `AdminDashboardPage.jsx`, `AdminDashboardCharts.jsx`, tạo `AdminDashboardCharts.css` và hai docs; không sửa helper/service/tests vì dùng lại phép tỷ lệ đã có test. Không đổi API/Auth/router/sidebar/env/schema/dependencies. Owner đã nghiệm thu; ghi nhận cuối chỉ cập nhật hai docs và kiểm scope/whitespace.
- Checklist chi tiết ở [hồ sơ UC47](UC47_ACCEPTANCE_HANDOVER.md#uc47-ui-01--cải-thiện-giao-diện-tổng-quan); logs ignored `.agent-workspace/tv3/uc47-ui-01/` giữ trạng thái lần chạy trước xác nhận owner. QA-01C đã hoàn tất, cleanup UI được owner xác nhận, UI-01 đã nghiệm thu. Screen reader vẫn NOT VERIFIED/được miễn; không tuyên bố WCAG compliance. Các checkpoint PARTIAL/chưa đóng trước đây là lịch sử, trạng thái hiện hành theo mục này và tổng kết cuối hồ sơ. Không chạy lại suite cho cập nhật docs; không commit/push, không tự chuyển task.

## 3. Công việc ngoài số đếm UC

| Hạng mục | Luồng chính | Nghiệm thu | Còn thiếu |
| --- | --- | --- | --- |
| FAQ Management | [ ] | [ ] | Có migration/model nền; còn Admin CRUD API/UI, validation và kiểm quyền. |
| Chat History | [ ] | [ ] | Có migration/model nền; còn session/message list/detail API/UI, ownership, phân trang và retention. |

## 4. Checkpoint nền đã nghiệm thu

- [x] Invoice read/create: Repository/Validator/typed DTO/Mapper, Symfony adapters và Frontend domain API/service/mapper.
- [x] Review: Backend refactor/audit và Frontend REV-FE-01 đã nghiệm thu.
- [x] Auth/Profile và Public/Host Catalog trong phạm vi được giao đã nghiệm thu, bàn giao TV1 duy trì.
- [x] CLEANUP-ARCH-01 và HANDOVER-BASELINE-01 đã nghiệm thu; owner đã merge đợt refactor vào dev.

Phạm vi, checkpoint IDs, evidence E1–E19 và exclusions nằm trong [bàn giao baseline](BASELINE_HANDOVER.md).

## 5. Phần còn thiếu và dependency

| Phạm vi | Trạng thái / việc còn thiếu |
| --- | --- |
| UC36 ↔ TV2 | HOÃN tích hợp: TV2 còn phát triển và chưa đưa nhánh Booking về kiến trúc mới. Không lấy/merge code hiện tại; kiểm lại SHA/contract khi owner yêu cầu tiếp. UC36-INT-PREP-01 đã khảo sát, nghiệm thu riêng còn PENDING; PREP-02/INT-01 chưa thực hiện. |
| UC15/34/35/37/56/57/58 | QA/nghiệm thu chức năng Invoice và Booking thật còn mở; hồi quy UC36 không tự đóng các UC này. |
| UC16/38/60 | Nghiệm thu các màn hình Review, điểm vào Booking thật và race MySQL còn mở; giữ evidence REV-FE-01. |
| UC39/47/48 | Chốt KPI, trạng thái Invoice tính doanh thu, kỳ/timezone và refund trước khi viết query. |
| UC44 | Phụ thuộc contract/workflow Booking TV2; TV3 phụ trách giám sát Admin. |
| UC17/61, FAQ, History | Chốt provider/retention/quyền context; chatbot V1 chỉ tư vấn, không mutation Booking/thanh toán. |
| Hồ sơ nền | SETUP-01 và VAL-STRUCT-01 còn thiếu nghiệm thu riêng; MAP-01 còn PARTIAL về hồ sơ. Không mở lại refactor đã nghiệm thu hoặc tự đánh PASS. |
| Bàn giao TV1/TV2 | Catalog còn lại (gồm UC21) thuộc TV1; Booking/Operations thuộc TV2. Writers Booking/Maintenance, DELETE Room và uniqueness Room ở DB giữ theo bàn giao baseline. |
| Môi trường kiểm chứng | UC36 dùng MySQL Docker/schema test riêng; giữ DB stayhub, env, datadir XAMPP và backup. Suite SQLite-only giữ nguyên guard, không dùng SQLite làm evidence MySQL. |

Không lưu kế hoạch đang làm hoặc nhật ký chạy lệnh trong checklist. Việc phát triển tiếp cần scope/approval riêng theo quy trình dự án.
