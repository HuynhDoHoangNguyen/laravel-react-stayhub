# UC48 — Bàn giao nghiệm thu thống kê hệ thống V1

Ngày lập: 2026-11-20 (Asia/Bangkok). Owner: TV3.
Baseline triển khai CLOSE-01: `feature/tv3-uc48-system-statistics`, HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng working tree đã được nghiệm thu; HEAD riêng không chứa toàn bộ phần triển khai chưa commit.

**OWNER ACCEPTED UC48-CLOSE-01 trên chat ngày 2026-11-20. UC48 CLOSED TV3; tổng TV3 14/18 UC CLOSED.** Đủ 8/8 task UC48 và hai task CSS/VND đã nghiệm thu. Technical PASS của CLOSE chỉ áp dụng kiểm tra hồ sơ trong scope 3 file; không thay kết luận technical PARTIAL của QA-UI bằng PASS toàn chức năng hoặc end-to-end toàn hệ thống.

## 1. Phạm vi và nguồn tra cứu

- [Contract V1](UC48_STATISTICS_CONTRACT.md): định nghĩa kỳ/chỉ tiêu/API và amendment hiển thị VND. Các câu “chưa triển khai”/“NOT VERIFIED” tại checkpoint CONTRACT là lịch sử; trạng thái hiện hành theo hồ sơ này và [checklist](TV3_CHECKLIST.md).
- [QA dữ liệu](UC48_DATA_QA.md): fixtures, oracle độc lập, MySQL/SQLite và cleanup. “OWNER ACCEPTANCE PENDING” ở báo cáo gốc là trạng thái tại lúc chạy; owner đã nghiệm thu QA-DATA sau đó trên chat.
- [QA giao diện](UC48_UI_QA.md): M01–M12, môi trường, HTTP preflight, manual dev và audit/cleanup; giữ nguyên các snapshot trước nghiệm thu.
- [Kiến trúc](../../ARCHITECTURE.md), [phân công](../stayhub-team-work-assignment.md): reporting thuộc TV3; dữ liệu Booking và workflow thuộc TV2.

V1 là báo cáo chỉ đọc toàn hệ thống cho ADMIN ACTIVE. Bao gồm số Booking, doanh thu Invoice hợp lệ, sáu trạng thái và bảng tháng theo kỳ tùy chọn. Không bao gồm export, biểu đồ mới, financial/refund ledger, sửa Booking, tạo Invoice tự động hoặc tích hợp end-to-end TV2.

## 2. Contract đang được triển khai

| Phần | Hành vi cần giữ |
| --- | --- |
| API | `GET /api/admin/statistics`, route `admin.statistics.show`; một data envelope, không PII |
| Quyền | Sanctum + account.active; service kiểm ADMIN ACTIVE trước validation/query. Guest 401; account/role 403; query sai 422; lỗi ngoài dự kiến 500 an toàn |
| Input | Chỉ `from_month`, `to_month`, canonical YYYY-MM, đi cặp, tăng dần, từ 1 đến 12 tháng. Bỏ cả hai: mặc định 12 tháng kết thúc ở tháng hiện tại |
| Thời gian | Kỳ theo `Asia/Ho_Chi_Minh`; đầu kỳ inclusive/cuối kỳ exclusive; một `generated_at` UTC cho request, nguồn dữ liệu không vượt cutoff |
| Booking | Theo `bookings.created_at`, trạng thái hiện tại PENDING/CONFIRMED/CHECKED_IN/COMPLETED/CANCELLED/REJECTED; không theo ngày lưu trú |
| Doanh thu | SUM header `invoices.total_amount` khi hiện PAID, `paid_at` khác null và trong kỳ/cutoff. Không tái tính giá catalog/items, không join làm nhân tổng |
| Refund | UNPAID/REFUNDED không tính. Chuyển sang REFUNDED làm giảm số đọc kỳ thanh toán cũ; V1 không ghi dòng âm vào ngày refund |
| Warning | PAID thiếu paid_at: loại khỏi revenue, đếm toàn hệ thống trong `paid_invoices_missing_paid_at_global_count`, không phân bổ theo kỳ |
| Projection | Đủ tháng/status kể cả 0; counts integer, tiền raw decimal string scale 2. Một aggregate SELECT cung cấp cùng read view |
| UI | `/admin/statistics`: draft/applied kỳ riêng, Áp dụng/Đặt lại/Tải lại/Thử lại, loading/error/empty/success, bảng tháng/status/warning; chống response cũ khi đổi kỳ/rời trang/đổi auth |

UC47 và UC48 khác semantics kỳ/cutoff; không ép tổng hai màn hình khớp. Amendment VND chỉ đổi text hiển thị của Dashboard/Statistics, không đổi nguồn hoặc phép tính doanh thu UC47.

### Presentation đã được owner nghiệm thu

**ADMIN-CSS-NAMING-01** và **ADMIN-VND-DISPLAY-01** đã được owner nghiệm thu trên chat trước khi duyệt CLOSE-01. Hai task bổ sung không làm tăng số UC.

- Namespace chức năng hiện tại: `admin-statistics`, `admin-dashboard`; control Invoice dùng chung là `invoice-search-input`/`invoice-filter-select`. DOM IDs/ARIA và business statuses không bị đổi bởi naming.
- VND hiển thị không có phần lẻ: helper dùng BigInt, half-up đến đồng và Intl.NumberFormat vi-VN với 0 fractional digits. Raw API/mapper/đối soát vẫn scale 2.
- Ví dụ raw `300.76` → `301 ₫`; `1000000.00` → `1.000.000 ₫`; `19999999999.98` → `20.000.000.000 ₫`. Khoảng trắng có thể là NBSP.
- Format từng dòng và summary độc lập từ raw amount; không cộng số đã làm tròn. Raw `0.49` hiển thị `0 ₫` nhưng không được coi là raw revenue bằng 0.
- Browser visual mới sau hai thay đổi này vẫn **NOT VERIFIED**. Owner acceptance không được suy thành một lượt browser mới; M01–M12 xảy ra trước các thay đổi này.

## 3. Bản đồ triển khai

| Lớp | Điểm vào/code chính |
| --- | --- |
| HTTP | [Route](../../backend/routes/api/dashboard.php), [Controller](../../backend/app/Http/Controllers/Statistics/AdminStatisticsController.php); exception codes/allowlist dùng chung đã được duyệt trong CORE |
| Input/quyền/kỳ | [Validator](../../backend/app/Validators/Statistics/AdminStatisticsValidator.php), [QueryService](../../backend/app/Services/Statistics/AdminStatisticsQueryService.php), [Period](../../backend/app/DTOs/Statistics/AdminStatisticsPeriod.php) |
| Đọc/projection | [Repository](../../backend/app/Repositories/Statistics/AdminStatisticsRepository.php), [Response DTO](../../backend/app/DTOs/Statistics/Responses/AdminStatisticsResponse.php) |
| Frontend domain | [API](../../frontend/src/api/statisticsApi.js) → [service](../../frontend/src/services/statisticsService.js) → [mapper](../../frontend/src/mappers/statisticsMapper.js); shared Axios được giữ nguyên |
| UI/lifecycle | [Hook](../../frontend/src/hooks/useAdminStatistics.js), [page](../../frontend/src/pages/admin/AdminStatisticsPage.jsx), [CSS](../../frontend/src/pages/admin/AdminStatisticsPage.css); [router](../../frontend/src/routes/AppRouter.jsx), [Admin layout](../../frontend/src/layouts/AdminLayout.jsx) |
| VND | [Helper](../../frontend/src/utils/formatVnd.js), [golden tests](../../frontend/tests/formatVnd.test.js) |
| QA | [MySQL runner](../../backend/tests/Support/RunUc48MySqlTests.ps1), [manual runner](../../backend/tests/Support/RunUc48Browser.ps1), [HTTP tests](../../backend/tests/Feature/Statistics/Uc48AdminStatisticsHttpMySqlTest.php), [Node](../../frontend/tests/adminStatistics.test.js) |

CLOSE-01 chỉ đọc các file ứng dụng trên; không sửa source/tests/schema/guard/env hoặc chạy lại môi trường.

## 4. Chuỗi task và nghiệm thu

| Task | Trạng thái owner | Phạm vi bằng chứng |
| --- | --- | --- |
| UC48-CONTRACT-01 | ACCEPTED | Contract V1; không coi oracle mô tả ban đầu là test đã chạy |
| UC48-ENV-01 | ACCEPTED | Hạ tầng schema/user test cô lập, opt-in/guard/cleanup |
| UC48-QUERY-01 | ACCEPTED | Query/period/DTO, MySQL và controls cô lập |
| UC48-CORE-01 | ACCEPTED | API, quyền/error contract và frontend tích hợp tối thiểu |
| UC48-UI-01 | ACCEPTED | Trang Statistics; giới hạn browser tại checkpoint gốc giữ nguyên |
| UC48-QA-DATA-01 | ACCEPTED | Oracle/negative controls/read-only và regression |
| UC48-QA-UI-01 | ACCEPTED WITH DOCUMENTED LIMITATIONS | M01–M12 owner-reported PASS trên dev, technical PARTIAL và runner exit 1 được bảo toàn |
| UC48-CLOSE-01 | ACCEPTED ngày 2026-11-20 | Owner đã nghiệm thu hồ sơ scope 3 file, đóng UC48 phạm vi TV3 với các giới hạn được ghi rõ |

8/8 task UC48 đã nghiệm thu; không còn task trong chuỗi UC48. Mọi tích hợp/runtime bổ sung cần scope riêng; chưa triển khai UC tiếp theo.

## 5. Ledger bằng chứng lịch sử

Các kết quả dưới đây được đối chiếu trong CLOSE, **không phải kiểm thử chạy mới ở CLOSE**. Artifacts local/ignored không đi theo Git; người nhận checkout khác cần yêu cầu bàn giao evidence hoặc chạy lại theo scope được duyệt. Không chép credential/private files.

| Ngày/checkpoint | Engine/loại | Kết quả gốc | Nơi tra cứu local |
| --- | --- | --- | --- |
| 2026-11-19 QUERY | MySQL Docker, run `a7ef53af190e47f39a5b81cfa7a3eae2` | 181 PASS / 647 assertions; cleanup/fingerprints PASS | `.agent-workspace/tv3/uc48-query-01/verification-summary.json`; `uc48-env-01/<run>/result.json`, tests/cleanup |
| QUERY hồi quy | SQLite memory | 1.911 PASS / 8.245 assertions / 122 SKIP, exit 0 | `.agent-workspace/tv3/uc48-query-01/backend-regression.txt` |
| 2026-11-20 CORE | MySQL Docker 8.4.13, run `ae06dd47f4e2461a978ac2b98d3760ff` | 210 PASS / 776 assertions / 0 SKIP, gồm 29 kernel HTTP cases; cleanup/dev/protected PASS | `.agent-workspace/tv3/uc48-core-01/verification-summary.json`; `uc48-env-01/<run>/` |
| CORE hồi quy | SQLite memory; Node transport mock | 2.235 PASS / 9.589 assertions / 405 SKIP; Node 215 PASS; lint/build exit 0 | `.agent-workspace/tv3/uc48-core-01/` |
| 2026-11-20 UI | Node; static/build | 12 focused, 215 toàn bộ PASS; lint/build exit 0; chưa browser tại checkpoint này | `.agent-workspace/tv3/uc48-ui-01/verification-summary.json` |
| 2026-11-20 QA-DATA | MySQL Docker 8.4.13, run `c4a6c62878c34b708e15a0f29a14cfb2` | 214 PASS / 1.379 assertions / 0 SKIP, runner exit 0; cleanup/dev/protected PASS | `.agent-workspace/tv3/uc48-qa-data-01/verification-summary.json`; `uc48-env-01/<run>/` |
| QA-DATA hồi quy | SQLite memory; Node | 2.235 PASS / 9.589 assertions / 409 SKIP; Node 215 PASS; lint/build exit 0 | `.agent-workspace/tv3/uc48-qa-data-01/` |
| 2026-11-20 QA-UI guard | SQLite memory | 50 PASS / 95 assertions, exit 0 | `.agent-workspace/tv3/uc48-qa-ui-01/guard-tests-final.txt` |
| QA-UI hồi quy | SQLite memory; Node | 2.285 PASS / 9.684 assertions / 409 SKIP; Node 215 PASS; lint/build exit 0 | `.agent-workspace/tv3/uc48-qa-ui-01/backend-all.txt`, `node-all.txt`, lint/build logs |
| QA-UI 6 scenarios | HTTP cookie jars + MySQL 8.4.13 + Vite source | 6 PASS, 558 status probes, 72 full-projection oracle comparisons; 6 cleanup PASS. Không render React/browser | `.agent-workspace/tv3/uc48-qa-ui-01/run-summary.json` |
| QA-UI manual | Owner, dev frontend 5173 | M01–M12 Đạt theo owner; không có agent browser observation/HAR để chứng minh QA fixture context | [QA UI mục 7](UC48_UI_QA.md#7-auditcleanup-sau-xác-nhận-owner), checklist mục 14 |
| QA-UI live audit | Run `822d1c28fc794deea2d42eb74e701831` | Runner exit 1 vì dev full fingerprint đổi; cleanup PASS; technical PARTIAL | `.agent-workspace/tv3/uc48-qa-ui-01/closure/final-audit.json`, `audit-followup/audit-findings.json` |
| 2026-11-20 ADMIN-CSS | Node/static equivalence | 215 PASS; 80 mapping entries đảo ngược khôi phục nguyên 8 source files; lint/build exit 0 | `.agent-workspace/tv3/admin-css-naming-01/verification-summary.json`, `source-equivalence.json` |
| 2026-11-20 ADMIN-VND | Node/domain modules | 37 focused, 231 toàn bộ PASS / 0 FAIL/SKIP; lint/build exit 0; combined scope 16 file | `.agent-workspace/tv3/admin-vnd-display-01/verification-summary.json`, `final-scope-check.json` |

Lint các lượt frontend có 0 errors và 3 Fast Refresh warnings có sẵn; build có cảnh báo bundle >500 kB. SQLite SKIP là opt-in/runtime/engine chưa chạy trong suite đó, không phải MySQL PASS. Không đưa số test của review PR13/nhánh TV2 vào ledger UC48.

### Giữ nguyên failure và controls

- QUERY có lượt runner false FAIL do tên test chứa “warning”; lượt cuối PASS được ghi riêng. Injected failure/after-schema/collision là negative controls có cleanup, không cộng vào positive PASS.
- QA-DATA run `770a7c2a12f746939661ea6a4d2b5e57`: 214 test PASS nhưng runner FAIL do regex status bắt nhầm tên test. Sửa runner trong scope đã duyệt, 5 mẫu phân loại PASS; rerun cuối `c4a6...` PASS. Giữ log cả hai lượt.
- QA-UI focused đầu exit 255 do cú pháp test mới; đã sửa rồi focused/full PASS. Injected Ready failure là exit 1 đúng kỳ vọng với cleanup PASS, tách khỏi 6 scenarios.
- Không sửa timestamp/verdict hoặc trạng thái PENDING trong các summary lịch sử để giả như đã nghiệm thu ngay lúc chạy; acceptance mới được ghi tại checklist/hồ sơ hiện hành.

## 6. Audit manual, nguyên nhân và xử lý đã được chấp nhận

Môi trường được cấp là frontend 56979 → backend 56978 trên schema QA riêng. Owner xác nhận đã kiểm tại frontend **5173 (dev)**; source dev trỏ backend 8000/database dev. Điều này giải thích không có QA session writes sau readiness; dev có 11 row events trên sessions trong cửa sổ đã audit. Whole-dump fingerprint có sessions nên runner trả exit 1.

Audit binlog metadata không thấy business writes/DDL vào dev trong cửa sổ `2026-11-20T12:08:39Z`–`12:15:15Z`; không suy actor từ binlog hoặc khẳng định toàn bộ lịch sử dev không đổi. Không có HAR/request log để dựng lại các bước browser.

Owner đã biết giới hạn và nghiệm thu QA-UI trên chat. Cách xử lý: bảo toàn FAIL gốc, giữ manual dev là owner-reported PASS, không restore dev sessions, không nới guard hoặc dựng lại QA ngoài scope. Technical evidence QA-UI vẫn PARTIAL.

Cleanup lịch sử được độc lập kiểm lại lúc `2026-11-20T12:19:05Z`: QA schema/user/connections đều 0; hai owned processes thoát, ports 56978/56979 được giải phóng, private credential file bị xóa; QA business fingerprints 9 bảng và protected source/config không đổi. CLOSE không mở kết nối DB hay kiểm runtime mới, không tuyên bố đây là trạng thái vừa đo lại.

## 7. Đối chiếu acceptance criteria UC48

| AC contract | Evidence và trạng thái kỹ thuật | Owner |
| --- | --- | --- |
| AC01 quyền/error order | PASS ở unit/kernel HTTP/MySQL và HTTP session/CSRF preflight | Đã nghiệm thu task liên quan |
| AC02 input/kỳ | PASS validator/period/HTTP, mặc định và 1–12 tháng | Đã nghiệm thu |
| AC03 Booking | PASS oracle nhiều Property/current status/created_at | Đã nghiệm thu |
| AC04 revenue | PASS PAID/header/paid_at/refund/null/cutoff/large/no multiplication | Đã nghiệm thu |
| AC05 biên thời gian | PASS period/MySQL, timezone/cutoff/zero-fill | Đã nghiệm thu |
| AC06 projection/read view | PASS DTO/invariants và aggregate một SELECT | Đã nghiệm thu |
| AC07 readonly/bảo toàn | QA business/source/cleanup PASS; live dev full fingerprint FAIL do sessions, audit PARTIAL như mục 6 | Chấp nhận giới hạn |
| AC08 frontend contract | PASS Node qua API/service/mapper thật, transport mock; malformed/error/cancellation | Đã nghiệm thu |
| AC09 states/filter/retry | Node/HTTP bổ trợ; M01–M12 owner-reported PASS trên dev; QA fixture browser NOT VERIFIED | Chấp nhận giới hạn |
| AC10 lifecycle/auth | Node coordinator PASS; owner báo Pending/route/auth Đạt trên dev; độc lập browser correlation PARTIAL | Chấp nhận giới hạn |
| AC11 tiếng Việt/VND/responsive | Owner M10–M12 Đạt trên dev trước refactor; naming/VND automated PASS, browser sau refactor NOT VERIFIED | Đã nghiệm thu cả hai task bổ sung |
| AC12 hồi quy/scope | PASS theo từng checkpoint; UC47 raw semantics/API giữ nguyên, VND presentation đổi theo amendment riêng đã duyệt | Đã nghiệm thu các task, gồm CLOSE |

Không kết luận toàn bộ UC48 TECHNICAL PASS không điều kiện hoặc WCAG compliance. Hồ sơ CLOSE đầy đủ có thể đạt technical PASS cho tài liệu trong khi evidence chức năng còn giới hạn đã được owner chấp nhận.

## 8. Dependency và bàn giao tiếp

- T01–T03 vẫn **BLOCKED TV2**, theo [checklist](TV3_CHECKLIST.md): Booking production → Invoice read/create/payment/Review entry chưa có evidence end-to-end được nghiệm thu. Reporting fixtures là snapshots tổng hợp, không chứng minh các workflow này.
- Review PR13 đã chuyển findings cho TV2; CLOSE không review lại, sửa hoặc merge PR. Việc PR mergeable không chứng minh tích hợp TV3 PASS.
- QA runners/mô tả fixture trong DATA/UI là hướng dẫn lịch sử, không phải lệnh đã chạy trong CLOSE. Muốn kiểm runtime lại cần scope riêng, schema/user owned và đối chiếu đúng URL/Request URL/actor/run trước manual.
- Các giới hạn còn giữ: audit context PARTIAL; browser sau naming/VND NOT VERIFIED; artifacts chỉ có local; lint/build warnings cũ. Không có blocker viết hồ sơ CLOSE trong scope được duyệt.

Owner đã nghiệm thu hồ sơ CLOSE trên chat; lượt ghi nhận nghiệm thu cập nhật UC48 CLOSED TV3 và tăng tổng từ 13/18 lên 14/18. Còn UC17/39/44/61 cùng FAQ Management và Chat History ngoài số đếm. Không bắt đầu UC khác trong lượt này.

## 9. CLOSE-01 và skills áp dụng

Exact scope: tạo hồ sơ này; sửa `TV3_CHECKLIST.md` và `README.md` cùng thư mục. Kiểm tài liệu gồm source/contract/evidence reconciliation, nội dung/Markdown links/anchors, whitespace cả file mới, secrets-pattern scan, Git status/hash và scope; kết quả CLOSE01–CLOSE05 tại checklist mục 18.

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục plan/scope 3 file đã duyệt, baseline accepted working tree, phân biệt task cuối và owner closure.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): đối chiếu ledger/logs/AC, nguồn owner acceptance, audit exceptions và kiểm tài liệu/báo cáo 8 phần.

Chỉ hai skill trên được áp dụng trong CLOSE và lượt ghi nhận nghiệm thu. Các skills Laravel/React/Database ở task trước là lịch sử triển khai, không tính là skills đã dùng trong lượt viết hồ sơ này. Không dùng skill-creator/Computer Use, không chạy Backend/Node/lint/build/MySQL/browser mới. Owner đã nghiệm thu hồ sơ cuối; dừng, chưa triển khai UC tiếp theo. Evidence kiểm tài liệu của lượt ghi nhận lưu riêng dưới `.agent-workspace/tv3/uc48-close-01-acceptance/`, không sửa evidence/logs CLOSE gốc.
