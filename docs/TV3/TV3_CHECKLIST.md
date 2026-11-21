# StayHub TV3 — Checklist

Cập nhật: 2026-11-20 (Asia/Bangkok). **14/18 UC CLOSED phạm vi TV3; còn 4 UC**, cùng FAQ Management và Chat History ngoài số đếm 18 UC.
Nguồn phân công: [team assignment](../stayhub-team-work-assignment.md). Mục lục hồ sơ: [README](README.md).

## 1. Trạng thái hiện hành

- `[x]` luồng chính khác với nghiệm thu; `CLOSED TV3` chỉ đóng đúng phạm vi owner chấp nhận, không phải end-to-end toàn hệ thống.
- TV3-AC-CLOSE-01 **đã được owner nghiệm thu trên chat**: đóng UC15/16/34/35/37/38/56/57/58/60. UC36/45/47 giữ nghiệm thu trước đó.
- API/MySQL/frontend dùng evidence lịch sử; 74 mục UI PASS theo owner. Audit runtime đúng lượt manual vẫn PARTIAL/NOT VERIFIED độc lập, được owner chấp nhận.
- Baseline dọn hồ sơ: `feature/tv3-uc48-system-statistics`, HEAD `3931bf6d41c2f23e26ce73ba73a28bd3f9f008c5` cộng working tree đã nghiệm thu; giữ cả tracked/untracked.
- UC48 **CLOSED TV3**: owner đã nghiệm thu UC48-CLOSE-01 trên chat ngày 2026-11-20, đủ 8/8 task; hai task ADMIN-CSS-NAMING-01/ADMIN-VND-DISPLAY-01 cũng đã nghiệm thu. Xem [bàn giao UC48](UC48_ACCEPTANCE_HANDOVER.md) và mục 18. Giữ giới hạn QA-UI technical PARTIAL/manual dev, audit runner FAIL và browser sau CSS/VND NOT VERIFIED; đóng phạm vi owner chấp nhận, không phải end-to-end toàn hệ thống.

## 2. Checklist 18 UC

| UC | Luồng chính | Nghiệm thu chức năng | Đã có / còn thiếu |
| --- | --- | --- | --- |
| UC15 — Xem hóa đơn cá nhân | [x] | [x] TV3 | CLOSED TV3; Invoice read, UI theo owner; T01. |
| UC16 — Tạo Review | [x] | [x] TV3 | CLOSED TV3; Review create/eligibility, synthetic QA entry; T03. |
| UC17 — Chat với chatbot | [ ] | [ ] | Còn provider, API/UI hỏi đáp, lưu message và xử lý lỗi. |
| UC34 — Tạo Invoice từ Booking | [x] | [x] TV3 | CLOSED TV3; create/commit/rollback/race và UI theo owner; T02. |
| UC35 — Chi tiết Invoice Host | [x] | [x] TV3 | CLOSED TV3; Host detail/items/ownership; T01. |
| UC36 — Cập nhật trạng thái thanh toán | [x] | [x] TV3 | CLOSED TV3; payment API/UI, F01/F02 đã đóng; tích hợp TV2 hoãn. |
| UC37 — Quản lý Invoice | [x] | [x] TV3 | CLOSED TV3; list/filter/search/page/no-write; T01. |
| UC38 — Host xem Review | [x] | [x] TV3 | CLOSED TV3; Host scope/summary và UI theo owner; T03. |
| UC39 — Dashboard Host | [ ] | [ ] | Có khung UI; còn KPI/kỳ/timezone/refund, query/API/UI. |
| UC44 — Admin xem toàn bộ Booking | [ ] | [ ] | Còn contract Booking TV2, query/API/UI, lọc/phân trang/quyền. |
| UC45 — Admin xem toàn bộ Invoice | [x] | [x] TV3 | CLOSED TV3; UC45-CLOSE-01 đã nghiệm thu ngày 2026-11-18. |
| UC47 — Dashboard Admin | [x] | [x] TV3 | CLOSED TV3; API/sáu KPI/charts/UI; screen reader được miễn. |
| UC48 — Thống kê hệ thống | [x] | [x] TV3 | CLOSED TV3; owner nghiệm thu CLOSE-01 ngày 2026-11-20, đủ 8/8 task và hai task CSS/VND. Giữ QA-UI manual dev/technical PARTIAL, audit runner FAIL, cleanup lịch sử PASS và browser sau CSS/VND NOT VERIFIED. |
| UC56 — Tạo InvoiceItem | [x] | [x] TV3 | CLOSED TV3 qua UC34; ROOM/SERVICE items nguyên tử; T02. |
| UC57 — Tính subtotal | [x] | [x] TV3 | CLOSED TV3 qua UC34; subtotal/snapshots/decimal; T02. |
| UC58 — Tính tổng thanh toán | [x] | [x] TV3 | CLOSED TV3 qua UC34; API fee/discount 0.00, nonzero chỉ calculator; T02. |
| UC60 — Kiểm tra điều kiện Review | [x] | [x] TV3 | CLOSED TV3 qua UC16; owner/COMPLETED/validation/unique/race; T03. |
| UC61 — Ngữ cảnh nội bộ chatbot | [ ] | [ ] | Còn allowlist, quyền truy cập và context service. |

UC56/57/58 qua UC34; UC60 qua UC16, không cần thêm API/UI riêng. Còn mở: **UC17/39/44/61**.

## 3. FAQ và Chat History

| Phần việc | Trạng thái / còn thiếu |
| --- | --- |
| FAQ Management | MỞ; còn contract, CRUD/quyền và API/UI quản lý |
| Chat History | MỞ; còn lưu/đọc lịch sử, ownership, API/UI và xử lý lỗi |

## 4. Hồ sơ nghiệm thu và giới hạn

| Nhóm | Checkpoint owner đã nghiệm thu | Hồ sơ chính |
| --- | --- | --- |
| Môi trường | TV3-AC-SCOPE-01, TV3-AC-ENV-01 | [Ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md) |
| Invoice read | INV-READ-QA-01, INV-READ-UI-01A/01B | Ma trận: IR01–IR06, UI01–UI16 |
| Invoice create | INV-CREATE-QA-01/RACE-01/UI-01A/UI-01B | Ma trận: IC01–IC07, CUI01–CUI18 |
| Review read | REV-READ-QA-01/UI-01A/UI-01B | Ma trận: RV04-read/RV05/RV07, RUI01–RUI20 |
| Review create | REV-CREATE-QA-01/RACE-01/UI-01A/UI-01B | Ma trận: RV01–RV04/RV06/RV07, RCUI01–RCUI20 |
| Đóng 10 UC | TV3-AC-CLOSE-01 — ACCEPTED | Ma trận: closure, evidence ledger và T01–T03 |
| Payment | UC36 — CLOSED TV3 | [Bàn giao UC36](UC36_TV2_HANDOVER.md), [contract](INVOICE_PAYMENT_WORKFLOW.md) |
| Admin Invoice | UC45 — CLOSED TV3 | [Bàn giao UC45](UC45_ACCEPTANCE_HANDOVER.md) |
| Admin Dashboard | UC47 — CLOSED TV3 | [Bàn giao UC47](UC47_ACCEPTANCE_HANDOVER.md) |
| Statistics | UC48 — CLOSED TV3; 8/8 task + CSS/VND ACCEPTED | [Contract](UC48_STATISTICS_CONTRACT.md), [DATA QA](UC48_DATA_QA.md), [UI QA](UC48_UI_QA.md), [bàn giao UC48](UC48_ACCEPTANCE_HANDOVER.md); giữ technical limits |

- Raw artifacts local/ignored không đi theo Git; không sửa verdict, timestamp hoặc acceptance snapshot cũ. Xác nhận cuối theo chat/hồ sơ hiện hành.
- Không lấy SQLite skips, Node mocks, HTTP probes, SSR/Vite transform hoặc run01A cũ thay manual browser/runtime của01B.
- Review UI dùng synthetic parent trên DB fixture thật, chưa chứng minh CustomerBookingDetail production. Booking fixture chưa chứng minh checkout thật.
- UC58 giữ API discount/extra_fee `0.00`; nonzero chỉ calculator. Screen reader/WCAG không được suy thành PASS.
- UC36/45/47 và UC48 CONTRACT/ENV/QUERY không bị mở lại vì dọn tài liệu.

## 5. Dependency tích hợp còn mở

| ID | Trạng thái | Điều kiện còn thiếu |
| --- | --- | --- |
| T01 | **BLOCKED TV2** | Booking API/workflow production tích hợp; đúng owner/customer/room/property và Invoice liên kết thật |
| T02 | **BLOCKED TV2** | Checkout → COMPLETED, snapshots phòng/đêm/dịch vụ nhất quán, writer transaction/lock → Invoice; chưa có checkout production → Invoice evidence |
| T03 | **BLOCKED TV2** | GET Customer Booking/điểm mở Review thật trả đúng owner/status/ID/review; COMPLETED từ Backend, không mock hoặc suy ngày |

TV3 vẫn chịu trách nhiệm lỗi thuộc module mình. PR #9 đã được owner close; không coi PR #10 đã sửa findings/đủ điều kiện merge nếu chưa có review mới.

## 6. Việc tiếp theo và quy tắc cập nhật

1. **UC48-CLOSE-01 đã được owner nghiệm thu; UC48 CLOSED TV3**, kết quả mục 18. Đủ 8/8 task UC48 và hai task bổ sung CSS/VND accepted. QA-UI manual M01–M12 trên dev, runner full fingerprint FAIL do sessions/cleanup PASS và technical PARTIAL giữ nguyên; browser sau CSS/VND NOT VERIFIED. Không còn task trong chuỗi UC48. Chờ owner giao scope tiếp theo, chưa triển khai UC17/39/44/61 hoặc FAQ/Chat History. Các mục 7–17 là checkpoint lịch sử, không phải danh sách việc hiện hành.
2. Mỗi task tiếp theo phải chốt exact scope và được duyệt; tích hợp TV2 là task riêng sau bàn giao/kiểm contract/kiến trúc.
3. Chỉ giữ trạng thái hiện hành tại checklist; fixture/oracle/UI/evidence chung ở ma trận; contract riêng giữ trong hồ sơ chuyên biệt.

**TV3-DOC-CLEANUP-01:** đã gom hồ sơ theo scope 6 viết lại/4 gom rồi xóa/3 giữ nguyên; chỉ kiểm tài liệu, không QA hoặc production. Technical result ở báo cáo task; owner acceptance task này chờ review. Backup/hash/kiểm tra local: `.agent-workspace/tv3/doc-cleanup-01/`.

## 7. UC48-CORE-01 — Task Result 2026-11-20

**TECHNICAL PASS trong scope CORE; OWNER ACCEPTED trên chat ngày 2026-11-20.** Nghiệm thu không thay đổi giới hạn bằng chứng browser bên dưới. Baseline `feature/tv3-uc48-system-statistics`, HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng working tree CONTRACT/ENV/QUERY và skill đã nghiệm thu. Không sửa QUERY, nghiệp vụ TV1/TV2, `/search`, schema, env, shared Axios hoặc AuthContext; không commit/push. UC48 chưa đóng.

- Backend: `GET /api/admin/statistics`, tên `admin.statistics.show`, `auth:sanctum` → `account.active` → QueryService kiểm ADMIN trước validation; một envelope `data`. Controller đọc query URL gốc để không trim/coerce, không nhận GET body, từ chối duplicate/array/unknown keys. Exception allowlist chỉ thêm route mới, giữ nhánh Auth/Catalog hiện có và 500 an toàn.
- Frontend: `/admin/statistics` và nav Admin; API → service → mapper → hook → page tối thiểu. Draft/kỳ request/kỳ kết quả riêng; áp dụng, reset mặc định máy chủ, tải lại, field errors, loading/empty/error, hai tổng, sáu trạng thái, bảng tháng và cảnh báo toàn hệ thống. Tiền giữ decimal/BigInt + Intl; mapper kiểm shape, bucket, timezone/biên lịch sử, tổng tiền/count/status. Abort + request serial + Auth-version guards chặn response cũ.

Scope đúng **8 tạo + 7 sửa**, không mở rộng:

| Hành động | File |
| --- | --- |
| Tạo | `backend/app/Http/Controllers/Statistics/AdminStatisticsController.php` |
| Tạo | `backend/tests/Feature/Statistics/Uc48AdminStatisticsHttpMySqlTest.php` |
| Tạo | `frontend/src/api/statisticsApi.js` |
| Tạo | `frontend/src/services/statisticsService.js` |
| Tạo | `frontend/src/mappers/statisticsMapper.js` |
| Tạo | `frontend/src/hooks/useAdminStatistics.js` |
| Tạo | `frontend/src/pages/admin/AdminStatisticsPage.jsx` |
| Tạo | `frontend/tests/adminStatistics.test.js` |
| Sửa | `backend/routes/api/dashboard.php` |
| Sửa | `backend/app/Exceptions/GlobalExceptionHandler.php` |
| Sửa | `backend/tests/Feature/Auth/AuthenticationTest.php` |
| Sửa | `backend/tests/Support/RunUc48MySqlTests.ps1` — chỉ thêm HTTP suite vào allowlist |
| Sửa | `frontend/src/routes/AppRouter.jsx` |
| Sửa | `frontend/src/layouts/AdminLayout.jsx` |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` |

| Kiểm tra | Kết quả thực tế và giới hạn |
| --- | --- |
| MySQL Docker 8.4.13, runner `-Provision` | **210 PASS / 776 assertions**, gồm 29 HTTP tests; 0 skip. Kernel HTTP dùng `actingAs`, không phải đăng nhập/CSRF qua network/browser. Fixture synthetic, không chứng minh Booking workflow TV2. |
| Oracle qua HTTP | 4 Booking, revenue `300.76`, tháng `100.25`/`200.51`, sáu status `[1,1,0,1,1,0]`, missing-date global 1; dữ liệu nghiệp vụ trước/sau không đổi. |
| Cleanup/dev/env | Schema/user/connections còn 0; owned children exited; development fingerprint và protected files trước/sau runner khớp. |
| `php artisan test --compact`, SQLite in-memory | **2.235 PASS / 9.589 assertions / 405 SKIP**, exit 0. Skips thuộc guard runtime/engine opt-in, không coi là MySQL PASS. |
| Node toàn bộ | **215 PASS / 0 FAIL / 0 SKIP**, gồm 12 test UC48; real domain modules, transport mock và deferred lifecycle coordinator. Không phải React/browser rendering. |
| `npm run lint` | Exit 0, 0 errors; 3 warnings có sẵn ở badge/button/sidebar, ngoài scope. |
| `npm run build` | Exit 0; cảnh báo bundle >500 kB. |
| Scope/whitespace | 15 file so baseline; `git diff --check` PASS; giữ các thay đổi đã nghiệm thu trước task. |

Lần Node đầu có 1 assertion sai về interceptor: interceptor chuyển version cho AuthContext quyết định; đã sửa test mô phỏng đúng trách nhiệm version-check rồi chạy lại toàn bộ PASS. Không sửa Auth để làm test qua.

AC contract: **AC01–AC08 PASS** ở mức unit/kernel HTTP/MySQL/Node tương ứng; AC03–05 tái chạy QUERY đã nghiệm thu. **AC09/AC10 PARTIAL**: code states và Node coordinator đã có bằng chứng, thao tác React/browser thật chưa kiểm. **AC11 NOT VERIFIED** về viewport/zoom/keyboard thực tế; **AC12 PASS** hồi quy tự động và scope. Không suy runtime browser từ build/Node.

Evidence local/ignored: `.agent-workspace/tv3/uc48-core-01/` (baseline, regression, Node, lint/build, final scope); MySQL `.agent-workspace/tv3/uc48-env-01/ae06dd47f4e2461a978ac2b98d3760ff/` (tests/connection/cleanup/dev/protected hashes).

Skills thực sự áp dụng: [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) — baseline/scope; [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) — HTTP/auth/error boundary; [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) — domain layers/UI/lifecycle; [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) — MySQL QA riêng/cleanup/data safety; [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) — AC/evidence/scope/Task Result. Không dùng skill-creator hoặc Computer Use trong CORE.

Còn **4 task**: UI-01, QA-DATA-01, QA-UI-01, CLOSE-01. CORE đã nghiệm thu; chưa đóng toàn bộ UC48.

## 8. UC48-UI-01 — Mini Plan đã duyệt 2026-11-20

**Mini Plan và scope 3 file đã được owner duyệt trên chat.** Baseline: HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng toàn bộ working tree đã nghiệm thu. Ở lượt lập plan chỉ sửa checklist để ghi nhận CORE và kế hoạch; không sửa source UI/API hoặc chạy QA runtime. Kết quả lượt triển khai sau phê duyệt ở mục 9.

Mục tiêu: hoàn thiện trình bày trang `/admin/statistics` tối thiểu đã có: header/kỳ áp dụng, form tháng, 2 summary cards, 6 trạng thái, bảng tháng và cảnh báo. Theo theme Admin hiện tại; giữ nguyên contract và lifecycle CORE. Mobile-first, loading skeleton có text, empty/error/retry rõ, field association/focus, bảng cuộn ngang có hướng dẫn và focus thấy được, số tiền dài không làm tràn trang. Không thêm chart/export/KPI hoặc bộ lọc ngoài contract.

Scope triển khai đã duyệt **3 file (1 tạo, 2 sửa)**:

| Hành động | File | Lý do |
| --- | --- | --- |
| Sửa | `frontend/src/pages/admin/AdminStatisticsPage.jsx` | Hoàn thiện bố cục/semantic UI/các trạng thái, tiếp tục dùng hook/service hiện có |
| Tạo | `frontend/src/pages/admin/AdminStatisticsPage.css` | Style dưới `.uc48-statistics`, dùng design tokens hiện tại; responsive/focus/reduced-motion, không global selector |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` | Ghi Task Result, AC và giới hạn kiểm chứng UI-01 sau khi được triển khai |

Không cần sửa file dùng chung. API/service/mapper/hook/router/layout/Auth/shared Axios/index.css/Backend/schema/env/TV1/TV2 chỉ đọc, ngoài scope ghi. Nếu phát hiện cần sửa logic hoặc shared file: báo phát hiện và xin duyệt scope bổ sung trước khi sửa.

Test khi được duyệt: Node focused UC48 rồi toàn bộ `node --experimental-test-module-mocks --test tests/*.test.js`, `npm run lint`, `npm run build`; kiểm diff/hash/scope/whitespace. Không thêm dependency hoặc test chỉ kiểm class/CSS để thay UI QA. UI-only không cần chạy lại MySQL/provision hoặc backend suite nếu không có thay đổi contract/backend; bằng chứng CORE giữ nguyên timestamp, không gọi là kết quả UI-01.

Owner kiểm browser thật ở QA-UI-01 trên API/schema QA được cấp ở QA-DATA-01: 375/768/1440 px, zoom 200%, bàn phím, kỳ 1/12 tháng/zero/Booking-only/global missing-date/tiền lớn, chậm mạng/offline/422/403/401/retry và đổi kỳ/rời trang/logout khi pending. Không dùng Computer Use hay tạo browser harness. UI-01 bàn giao danh sách kiểm; browser gate chưa quan sát giữ NOT VERIFIED, không đóng AC09–11 chỉ từ Node/build.

| AC UI-01 | Kết quả cần có | Bằng chứng dự kiến | Trạng thái ban đầu |
| --- | --- | --- | --- |
| UI01 | Đúng theme, đủ 2 summary/6 status/bảng 1–12 tháng/kỳ/timezone/cutoff | Review JSX/CSS + owner browser sau | NOT VERIFIED |
| UI02 | Draft không đổi nhãn kỳ kết quả, apply/reset/retry và field errors giữ contract CORE | Diff + Node hồi quy; owner thao tác sau | NOT VERIFIED |
| UI03 | Loading/empty/Booking-only/error/cảnh báo không nhập nhằng | Review branches; owner scenario sau | NOT VERIFIED |
| UI04 | Responsive, đọc đủ tiền, table scroll/focus/labels/reduced-motion | Scoped CSS/semantic review; owner viewport/keyboard/zoom sau | NOT VERIFIED |
| UI05 | Không đổi API/lifecycle/decimal semantics hoặc ảnh hưởng UC47/search | Source hashes + Node/lint/build | NOT VERIFIED |
| UI06 | Đúng 3 file, evidence/AC trung thực và không đóng UC48 sớm | Git diff/checklist/Task Result | NOT VERIFIED |

Thứ tự: recheck baseline → sửa page/style trong scope → review states/semantics/overflow → Node/lint/build → scope/checklist/Task Result 8 phần → dừng. Ước lượng 45–60 phút công sức thông thường. Rủi ro: bảng 9 cột/tiền dài ở mobile và hành vi input month tùy trình duyệt; cần owner browser để kết luận. QA-DATA/QA-UI là task riêng, không tự chạy trong UI-01.

Skills đã áp dụng cho lượt lập plan: [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) — baseline/scope/7 phần Mini Plan; [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) — theme/UI states/semantic/responsive/test boundary; [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) — ghi owner acceptance, bảo toàn evidence lịch sử và tách automated/browser gates. Không áp dụng Laravel/database/skill-creator cho lượt UI planning này.

## 9. UC48-UI-01 — Task Result 2026-11-20

**IMPLEMENTED; AUTOMATED CHECKS PASS; UI VERIFICATION PARTIAL; OWNER ACCEPTED trên chat ngày 2026-11-20.** Nghiệm thu không chuyển các browser gates chưa kiểm thành PASS. Chưa tuyên bố TECHNICAL PASS toàn bộ AC UI hoặc đóng UC48 khi browser gates chưa có bằng chứng.

### 1. Backend/Frontend

Backend không thay đổi. Frontend hoàn thiện header/kỳ báo cáo, form tháng, hai summary cards, sáu status, bảng tháng 9 cột và cảnh báo. Có skeleton chỉ mang tính trình bày kèm text, empty gợi ý chọn kỳ khác, error/retry/401/403/422, focus về field lỗi 422, caption/heading/labels và thông báo tải xong. CSS dùng token/theme Admin, được giới hạn dưới `.uc48-statistics`; breakpoint 640/1024/1280, wrap tiền lớn, bảng cuộn ngang có cột tháng sticky và vùng focus; motion chỉ bật khi `prefers-reduced-motion: no-preference`.

### 2. File thay đổi

| Hành động | File | Kết quả |
| --- | --- | --- |
| Sửa | `frontend/src/pages/admin/AdminStatisticsPage.jsx` | Trình bày/semantic/states; tái sử dụng hook/service/formatter CORE |
| Tạo | `frontend/src/pages/admin/AdminStatisticsPage.css` | Style riêng, responsive, focus, reduced-motion |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` | Ghi plan được duyệt, kết quả và các gate chưa kiểm |

Đúng 3 file; không lệch scope; không thêm dependency. Các thay đổi đã nghiệm thu trước task được giữ nguyên.

### 3. Kiểm thử thực tế

| Lệnh/kiểm tra | Kết quả | Giới hạn |
| --- | --- | --- |
| Node focused `tests/adminStatistics.test.js` | Exit 0; 12 PASS, 0 fail/skip | Domain modules/transport mocks/coordinator của CORE; không kiểm render/focus/layout mới |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0; 215 PASS, 0 fail/skip | Có cảnh báo Node experimental module mocking; không thay browser |
| `npm run lint` | Exit 0; 0 errors, 3 warnings sẵn có | badge/button/sidebar ngoài scope |
| `npm run build` | Exit 0; 3.350 modules transformed | Cảnh báo bundle >500 kB; không xác nhận visual runtime |
| Scope/hash/whitespace/links | PASS; đúng 3 file, ngoài scope không đổi | Static verification |
| Review JSX/CSS | Đã review semantic/draft/result/empty/warning/overflow/reduced-motion | Không phải browser evidence |
| Backend/MySQL/browser | NOT RUN trong UI-01 | UI-only; không provision/seed/reset DB, không browser harness/Computer Use |

Evidence local/ignored: `.agent-workspace/tv3/uc48-ui-01/` — baseline, node-focused.txt, node-all.txt, lint.txt, build.txt, final-scope-check.json, verification-summary.json. Bằng chứng CORE giữ nguyên, không gán lại timestamp/kết quả cho UI-01.

### 4. Nghiệp vụ và contract

Giữ GET/input/output, dữ liệu decimal, cutoff và cảnh báo toàn hệ thống của CORE; không tự tính tổng tiền. Draft không đổi kỳ kết quả trước khi apply; reset gửi mặc định máy chủ; empty chỉ khi cả Booking = 0 và revenue = `0.00`, không giả Booking-only thành empty. Giữ quyền và abort/serial/Auth guards ở lớp hiện có. Hash API/service/mapper/hook/router/layout/Auth/shared Axios/index.css/Backend/TV1/TV2/search không đổi.

### 5. AC và trạng thái

| AC | Trạng thái | Bằng chứng/còn thiếu |
| --- | --- | --- |
| UI01 | PARTIAL | Đủ nội dung/theme theo JSX/CSS/build; visual browser NOT VERIFIED |
| UI02 | PARTIAL | Giữ submit/reset/draft/result/error semantics qua review; Node hồi quy PASS; thao tác/focus React thật NOT VERIFIED |
| UI03 | PARTIAL | Đã review loading/empty/Booking-only/error/warning branches; browser scenarios NOT VERIFIED |
| UI04 | PARTIAL | Có responsive/focus/overflow/reduced-motion trong code; 375/768/1440 px, keyboard/zoom/tiền dài thực tế NOT VERIFIED |
| UI05 | PASS | Hash các domain/shared files không đổi; Node/lint/build PASS; không sửa UC47/search |
| UI06 | PASS | Đúng 3 file, evidence có giới hạn rõ, UC48 vẫn mở |

Contract AC09/10 vẫn PARTIAL, AC11 vẫn NOT VERIFIED về browser; không suy thành PASS từ code hoặc Node/build.

### 6. Dependencies/rủi ro/sai lệch

Không có blocker triển khai trong scope, không phát sinh file dùng chung. Browser QA cần API/session/schema QA từ task QA-DATA riêng; fixture không thay production Booking workflow TV2. Rủi ro chưa quan sát: bảng 9 cột/tiền dài ở mobile, native month input, focus/scroll/zoom và trạng thái pending thực tế. Không sửa warning chung ngoài scope; không commit/push.

Checklist bàn giao owner QA-UI (đều **Chưa kiểm**): 375/768/1440 px; zoom 200%; Tab/Enter/focus field 422/table scroll; kỳ 1/12 tháng/zero/Booking-only/doanh thu-only/global warning/tiền lớn; loading/offline→retry; 401/403/422; đổi kỳ/rời trang/logout khi pending. Chỉ đánh Đạt/Lỗi sau khi quan sát đúng môi trường/API thật.

### 7. Skills đã sử dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục plan/scope đã duyệt, bảo toàn baseline và ranh giới task.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): theme/domain boundaries, UI states/semantic/focus/responsive và frontend verification.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): test evidence, scope/hash, AC PARTIAL/NOT VERIFIED và Task Result 8 phần.

Không áp dụng Laravel/database/skill-creator/Computer Use cho UI-01.

### 8. Task đề xuất và điểm dừng

Owner đã nghiệm thu UI-01. Còn **3 task**: QA-DATA-01, QA-UI-01, CLOSE-01. Đề xuất lập Mini Plan **UC48-QA-DATA-01** khi được giao riêng; lượt hiện tại review PR TV2 theo yêu cầu owner. **Dừng, chưa chuyển QA-DATA-01.**

## 10. UC48-QA-DATA-01 — Task Result 2026-11-20

Mini Plan 7 phần và scope 4 file đã được owner duyệt trên chat. Baseline: `feature/tv3-uc48-system-statistics`, HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng toàn bộ working tree đã nghiệm thu. **TECHNICAL PASS; OWNER ACCEPTED trên chat ngày 2026-11-20.** Chi tiết ledger/evidence: [UC48_DATA_QA.md](UC48_DATA_QA.md); nhãn acceptance PENDING trong báo cáo đó là snapshot trước nghiệm thu, trạng thái hiện hành theo checklist này.

### 1. Backend/Frontend

Thêm 4 test QA gọi endpoint thống kê qua Laravel kernel/actingAs trên MySQL thật: nhiều Host/Property, cohort Booking/Invoice độc lập, current-state/catalog negative controls, doanh thu lớn với nhiều items/services, Booking-only kỳ 12 tháng và warning ngoài kỳ. Tái chạy test ENV/QUERY/CORE. Không sửa production Backend/Frontend; frontend chạy hồi quy tự động. Không chứng minh browser/session thật hoặc workflow TV2 bằng synthetic snapshots.

### 2. File thay đổi

| Hành động | File | Mục đích |
| --- | --- | --- |
| Tạo | `backend/tests/Feature/Statistics/Uc48StatisticsDataAcceptanceMySqlTest.php` | 4 test đối soát endpoint, oracle tường minh và readonly fingerprints |
| Sửa | `backend/tests/Support/RunUc48MySqlTests.ps1` | Đăng ký test mới; sửa status detection tránh bắt nhầm tên test chứa “warning” |
| Tạo | `docs/TV3/UC48_DATA_QA.md` | Ledger, ma trận, bằng chứng/môi trường/giới hạn và bàn giao QA-UI |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` | Ghi plan được duyệt, Task Result và AC |

Đúng 4 file. Phát sinh nhỏ trong runner đã được duyệt: sửa cách phân loại output vì false FAIL; không đổi guard/provision/cleanup hoặc file ngoài scope. Không thêm dependency.

### 3. Kiểm thử thực tế

| Kiểm tra | Kết quả và giới hạn |
| --- | --- |
| MySQL Docker 8.4.13 qua runner `-Provision` | **214 PASS / 1.379 assertions / 0 SKIP**, exit 0; gồm 4 test mới, kernel HTTP/Query/period/validator/guard tests |
| Isolation và cleanup | Dev/protected fingerprints không đổi; schema/user/connection QA còn lại 0, owned children đã thoát |
| Backend full, SQLite `:memory:` | **2.235 PASS / 9.589 assertions / 409 SKIP**, exit 0; skips thuộc runtime/engine opt-in, không phải MySQL evidence |
| Node toàn bộ | **215 PASS / 0 FAIL / 0 SKIP**, exit 0; transport mocks/coordinator, không browser |
| Frontend lint/build | Cả hai exit 0; lint 0 errors/3 warnings có sẵn; build warning bundle >500 kB |
| Static/scope | PHP syntax, PowerShell parse, Markdown links/whitespace và đúng 4 file; ngoài scope không đổi |

Lượt MySQL đầu có 214 test PASS nhưng runner FAIL do regex `WARN` khớp tên test có chữ “warning”; cleanup thành công. Sửa status detection và kiểm 5 mẫu PASS/WARN/SKIP, chạy lại MySQL PASS. Giữ cả hai bộ evidence, không che lần lỗi.

Evidence mới: `.agent-workspace/tv3/uc48-qa-data-01/`; runner giữ quy ước đường dẫn `.agent-workspace/tv3/uc48-env-01/c4a6c62878c34b708e15a0f29a14cfb2/`. Lượt đầu `770a7c2a12f746939661ea6a4d2b5e57`. Không dùng evidence cũ để gọi là test mới.

### 4. Nghiệp vụ và contract

Multi-property ledger trả 6 Booking, revenue `120.12`, tháng `10.01`/`110.11`, sáu status mỗi loại 1, global warning 1. Booking ngoài kỳ vẫn có Invoice thanh toán trong kỳ được tính, không gate Booking COMPLETED. Đổi catalog/items không đổi report; chuyển một Invoice REFUNDED giảm revenue còn `80.08`; đổi Booking sang REJECTED chỉ đổi phân bố trạng thái. Large decimal `19999999999.98` giữ chính xác; kỳ Booking-only có 10 tháng trống được zero-fill. Oracle contract `300.76`, timezone/cutoff/leap/boundaries và HTTP contract đều được tái kiểm. Dữ liệu synthetic phục vụ predicate báo cáo, không mở rộng transition TV2.

### 5. Acceptance criteria

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| DATA01 | PASS | Identity/grants, guard, dev/protected fingerprints, cleanup |
| DATA02 | PASS | Multi-property/cohort test và expected ledger |
| DATA03 | PASS | Current states, headers, decimal lớn, warning |
| DATA04 | PASS | Period/Query/HTTP MySQL tests |
| DATA05 | PASS | GET fingerprint, aggregate một SELECT, projection/invariants |
| DATA06 | PASS | Kernel HTTP auth/error order/validation/safe 500; không phải browser session |
| DATA07 | PASS | Hồi quy, 4-file scope, tài liệu và Task Result |

Không chuyển UC48 AC09–11/browser gates thành PASS. Owner đã nghiệm thu scope QA-DATA trên chat ngày 2026-11-20.

### 6. Dependencies/rủi ro/sai lệch

Không phát hiện blocker dữ liệu UC48 trong ma trận đã chạy. Browser/session/CSRF/visual/responsive vẫn NOT VERIFIED. Không kiểm chứng production TV2 Booking/payment integration, không merge PR #12, không sửa UC47 hoặc schema/env. QA schema đã cleanup; QA-UI cần môi trường riêng được duyệt, không có server/schema chờ sẵn. Không commit/push/merge.

### 7. Skills đã sử dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): approved scope/baseline/task boundary.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): endpoint tests, projection/error order và Backend regression.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md): owned MySQL target, exact decimal, oracle, fingerprint/cleanup.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): evidence theo engine, AC, ghi false FAIL/rerun và Task Result 8 phần.

Không dùng stayhub-react/skill-creator/Computer Use trong task này.

### 8. Task đề xuất và điểm dừng

Owner đã nghiệm thu QA-DATA. Còn **2 task: QA-UI-01 và CLOSE-01** chưa triển khai. Mini Plan QA-UI ở mục 11 chờ duyệt; UC48 vẫn mở. **Dừng, chưa triển khai QA-UI-01.**

## 11. UC48-QA-UI-01 — Mini Plan đã duyệt 2026-11-20

Owner đã duyệt Mini Plan và scope 7 file trên chat. Lượt lập plan trước chỉ ghi nghiệm thu QA-DATA và kế hoạch, chưa runtime. Lượt triển khai sau duyệt chuẩn bị môi trường/manual handoff; kết quả ở mục 12. Baseline HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng working tree đã nghiệm thu.

### 1. Mục tiêu và khoảng thiếu

Kiểm trang `/admin/statistics` trên React hiện tại + Laravel/MySQL thật + session/CSRF thật; owner thao tác trình duyệt. Đóng khoảng thiếu bằng chứng AC09/10/11 khi có quan sát, không tự đóng UC48. QA-DATA đã cleanup, chưa có môi trường browser giữ lại.

### 2. Hướng thực hiện và ranh giới

Tái sử dụng pattern bootstrap thủ công UC47, tạo support riêng UC48, không sửa support UC47 hoặc guard API UC48. Không browser automation/Computer Use/harness phức tạp. Dùng source Vite thật, domain API và shared Axios hiện có. Runtime localhost/ports riêng chỉ process overrides; không sửa root env/config dùng chung. Bootstrap chỉ chuẩn bị fixture, guard, audit, phục vụ HTTP và cleanup, không thêm endpoint production hoặc bỏ auth/CSRF.

### 3. Schema/nghiệp vụ/contract

Schema/user QA owned theo manifest; migrations chỉ trên schema trống mới tạo. Browser guard riêng kiểm local runtime/database sessions, origins/cookie/instance/grants; tái dùng target checks UC48 mà không nới testing/array-session guard của runner API. Không dùng helper fixture testing-only bằng cách bỏ guard; browser fixture tự xác nhận browser context trước ghi. Login tài khoản synthetic có password hash, ADMIN ACTIVE chính/phụ và actor phục vụ kiểm quyền. Password/key ngẫu nhiên, không ghi vào evidence/commit. Fixture scenarios: Populated, Zero, BookingOnly, RevenueOnly, MissingDate, Large. Oracle độc lập dựa ledger, kỳ và generated_at thật; không đóng băng thời gian của Auth/session, không bê expected cutoff cũ sang runtime mới. Tách business fingerprint với session và last_login_at hợp lệ theo pha đăng nhập; không coi thay đổi có chủ đích là report write.

### 4. Verification dự kiến

- Guard unit tests: thiếu opt-in, target sai, config cache/DB_URL, origin/session sai bị từ chối trước ghi; target hợp lệ được chấp nhận. Parser/syntax và cleanup failure-path smoke trên owned resources.
- HTTP thật: csrf-cookie/login/me/statistics/logout, cookie jar, request thiếu/sai XSRF bị 419, guest 401, role/account 403; oracle/read-only audit. Phân biệt với owner browser evidence.
- Owner manual: apply/reset/reload/retry, draft/result, 1/12 tháng và 422; zero/Booking-only/revenue-only/missing-date/large; loading/offline→retry; hết phiên/403/đổi kỳ/rời trang/logout hoặc đổi tài khoản khi pending.
- 375/768/1440 px, zoom 200%, Tab/Enter/focus lỗi/table scroll, tiền dài, reduced motion; ghi Đạt/Lỗi/Chưa kiểm theo tình huống. Nếu không tái hiện được response về sai thứ tự bằng network throttling, giữ NOT VERIFIED, không suy từ Node.
- Reuse Node UC48/full suite, lint/build; Backend focused guard và full SQLite suite vì thêm PHP support. MySQL/API QA-DATA là evidence lịch sử, chỉ chạy lại khi thay đổi/failure mới cần.
- Kết thúc manual: dừng owned children/ports, fingerprint dev/env, audit business/session theo pha, xóa đúng schema/user/credentials của run; xác nhận không còn resource. Chưa hoàn tất manual/cleanup thì chưa báo TECHNICAL PASS.

### 5. Scope đề xuất 7 file (6 tạo, 1 sửa)

| Hành động | File | Lý do |
| --- | --- | --- |
| Tạo | `backend/tests/Support/Uc48BrowserEnvironment.php` | Guard riêng cho session/origin/browser QA, tái dùng target validation |
| Tạo | `backend/tests/Support/Uc48BrowserFixtures.php` | Synthetic actors/scenarios, oracle độc lập và audit theo pha |
| Tạo | `backend/tests/Support/Uc48BrowserServer.php` | Bootstrap process-local cho prepare/serve/audit, không sửa production routes |
| Tạo | `backend/tests/Support/RunUc48Browser.ps1` | Provision, smoke HTTP, source check, bounded manual handoff và cleanup |
| Tạo | `backend/tests/Unit/Uc48BrowserEnvironmentTest.php` | Guard positive/negative cases trước ghi |
| Tạo | `docs/TV3/UC48_UI_QA.md` | Hướng dẫn manual, expected values, môi trường và kết quả theo scenario |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` | Plan/result/AC/owner status |

Không cần sửa production/shared router/layout/Auth/Axios/CORS/Sanctum/env/schema hoặc runner API đã nghiệm thu. Chỉ process-local QA configuration được đề xuất. Phát hiện lỗi ngoài scope: ghi lỗi và đề xuất scope sửa, không tự sửa page/hook/CSS hoặc module TV1/TV2. Evidence/ephemeral secrets chỉ dưới ignored workspace, secrets không được đưa vào logs/report.

### 6. AC ban đầu

| AC | Kết quả cần có | Evidence | Trạng thái |
| --- | --- | --- | --- |
| QAUI01 | Đúng source/API/owned target; session/CSRF thật | Guard, HTTP probes, identity/source/audit | NOT VERIFIED |
| QAUI02 | Kỳ/summary/status/month/warning/VND khớp oracle ở các scenarios | Owner + JSON/oracle | NOT VERIFIED |
| QAUI03 | Apply/reset/draft/retry/loading/error/empty đúng | Owner checklist | NOT VERIFIED |
| QAUI04 | Pending/route/auth context không khôi phục dữ liệu cũ | Owner observed steps + Node bổ trợ | NOT VERIFIED |
| QAUI05 | Responsive/keyboard/zoom/overflow/reduced-motion | Owner viewport/checklist | NOT VERIFIED |
| QAUI06 | Cleanup/dev/env nguyên vẹn; scope/hồi quy/báo cáo đủ | Fingerprints, cleanup, logs/diff | NOT VERIFIED |

### 7. Thứ tự/dependencies/rủi ro/skills

Approved scope → baseline → support/guard tests → provision/scenarios/HTTP smoke → gửi URL/tài khoản tạm và manual checklist cho owner → chờ kết quả thực tế → audit/cleanup → report 8 phần → dừng. Handoff có thời hạn rõ; hết hạn phải cleanup và ghi phần chưa kiểm, không coi hết hạn là nghiệm thu. Ước lượng 75–90 phút công sức kỹ thuật, thời gian owner manual tính riêng; nếu phát sinh vượt phạm vi đề xuất chia task trước khi làm thêm. Dependency: Docker/MySQL/PHP/Node/PowerShell 7, ports riêng và owner browser. Không đóng gate từ build hoặc owner duyệt plan.

Skills áp dụng lúc lập plan: [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) — baseline/scope/7 phần; [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) — states/lifecycle/manual UI; [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) — HTTP session/CSRF/runtime boundary; [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) — owned schema/guard/fingerprint/cleanup; [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) — acceptance/evidence/status/checklist. Không dùng skill-creator hoặc Computer Use.

Mini Plan/scope 7 file đã được duyệt. Không dùng việc duyệt plan làm bằng chứng browser PASS; chờ owner kiểm UI trên môi trường QA trước khi kết luận task. Chưa chuyển CLOSE-01.

## 12. UC48-QA-UI-01 — Task Result / manual handoff 2026-11-20

**Snapshot lúc handoff: PREPARED; AUTOMATED CHECKS PASS; OWNER MANUAL/CLEANUP PENDING.** Kết quả mới sau owner manual/cleanup ở mục 13. Chi tiết: [UC48_UI_QA.md](UC48_UI_QA.md).

### 1. Backend/Frontend

Chuẩn bị QA bootstrap riêng, guard local session/origin/owned MySQL, synthetic actors và 6 scenarios với oracle độc lập. Production source React/Vite/API/auth/CSRF được giữ nguyên; không browser automation/Computer Use. Real HTTP scripts kiểm cookie/session/CSRF/role và full response; owner browser vẫn chờ.

### 2. Files

Đúng scope 7 file: tạo `backend/tests/Support/Uc48BrowserEnvironment.php`, `Uc48BrowserFixtures.php`, `Uc48BrowserServer.php`, `RunUc48Browser.ps1`; tạo `backend/tests/Unit/Uc48BrowserEnvironmentTest.php`, `docs/TV3/UC48_UI_QA.md`; sửa checklist này. Không đổi production/shared files, guard API, dependencies, env/schema dev hoặc module TV1/TV2. Hai tài liệu báo cáo được loại khỏi runtime protected-source fingerprint để ghi manual handoff, nhưng vẫn kiểm scope riêng.

### 3. Tests

- Guard/oracle focused: **50 PASS / 95 assertions**, exit 0.
- Full SQLite `:memory:`: **2.285 PASS / 9.684 assertions / 409 SKIP**, exit 0; opt-in/engine skips không phải MySQL evidence.
- 6 HTTP/MySQL 8.4.13 scenarios: **558 status probes / 72 oracle comparisons PASS**; mỗi run cleanup schema/user/connection/process/port PASS, dev/protected fingerprints nguyên vẹn.
- Missing Provision bị từ chối; injected Ready failure exit 1 đúng dự kiến, cleanup PASS. Giữ bằng chứng negative control riêng.
- Node **215 PASS / 0 fail/skip**; lint/build exit 0, 3 warnings cũ và bundle >500 kB.
- PHP/PowerShell syntax, Markdown links, whitespace và 7-file scope PASS. Focused lần đầu parse error trong test mới (exit 255, chưa chạy tests), đã sửa và focused/full suite PASS.

Evidence `.agent-workspace/tv3/uc48-qa-ui-01/`, run summary ghi sáu run hoàn tất và negative control. Live run preflight PASS riêng, không tính là live cleanup PASS.

### 4. Nghiệp vụ/contract

Giữ kỳ HCM/cutoff/created_at/current status/PAID paid_at/header decimal/global warning. Native calendar + BCMath oracle không dùng production query/DTO/mapper. Populated normal kỳ 10–11/2026: 6 Booking, revenue `300.76`, statuses mỗi loại 1; các tháng 7/9/8/12 tương ứng Booking-only/revenue-only/large/zero có expected riêng. Default 12 tháng: 8 Booking, revenue `20000000301.49`, warning 1. Snapshot fixtures không chứng minh workflow TV2.

### 5. AC

| AC | Trạng thái | Bằng chứng/còn thiếu |
| --- | --- | --- |
| QAUI01 | PARTIAL | HTTP/session/CSRF/source/effective API origin PASS; owner browser chờ |
| QAUI02 | PARTIAL | Oracle/JSON PASS; visual data/VND chờ owner |
| QAUI03 | NOT VERIFIED browser | M03/M05/M06 thao tác/state/retry |
| QAUI04 | NOT VERIFIED browser | M07–09 pending/route/auth, Node chỉ bổ trợ |
| QAUI05 | NOT VERIFIED | M10–12 viewport/zoom/keyboard/motion |
| QAUI06 | PARTIAL | Scope/regression/smoke cleanup PASS; live audit/cleanup chờ |

### 6. Dependencies/rủi ro/handoff

Live Populated run `822d1c28fc794deea2d42eb74e701831`: frontend `http://localhost:56979`, backend `http://localhost:56978`; login `/login?role=admin`, actor `admin_a@uc48.example.test`, password ở private ignored file theo handoff. Lease đến **21:09:13 ngày 20/11/2026 UTC+7**, sau đó tự cleanup. Owner dùng cửa sổ riêng tư, làm M01–M12 trong tài liệu và gửi Đạt/Lỗi/Chưa kiểm, logout rồi yêu cầu dừng. Không ghi secrets vào evidence/checklist. Live resources giữ có chủ đích, chưa cleanup; nếu hết hạn phần chưa quan sát vẫn NOT VERIFIED. Không commit/push/merge; không đổi PR #12 hoặc nghiệp vụ TV2.

### 7. Skills đã sử dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): approved 7-file scope, baseline và manual handoff boundary.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): source/API trace, UI states/lifecycle và checklist manual.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): CSRF/session/permission HTTP probes, guard tests và Backend regression.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md): owned target, oracle exact decimal, fingerprint/cleanup.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): evidence theo engine/runtime, AC PARTIAL/NOT VERIFIED và report 8 phần.

### 8. Bước tiếp theo và điểm dừng

Chờ owner kiểm UI và gửi kết quả, sau đó tiếp tục chính QA-UI-01 để audit/cleanup và báo cáo nghiệm thu. Chưa đề nghị đóng UC48 khi browser gates chưa có bằng chứng. **Chưa chuyển CLOSE-01.**

## 13. UC48-QA-UI-01 — Owner manual và audit/cleanup 2026-11-20

**OWNER REPORTED UI PASS; RESOURCE CLEANUP PASS; TECHNICAL STATUS PARTIAL — AUDIT EXCEPTION.** Owner xác nhận M01–M12 Đạt và đã logout, không tự coi đây là nghiệm thu sai lệch audit hoặc toàn task. Chi tiết mới ở [UI QA mục 7](UC48_UI_QA.md#7-auditcleanup-sau-xác-nhận-owner).

### 1. Backend/Frontend outcomes

Đã ghi nhận owner manual M01–M12 Đạt. Đã gửi stop request đúng owned run `822d1c28fc794deea2d42eb74e701831`, runner audit và dừng môi trường; không mở run mới, sửa production hoặc chạy thêm task. UI evidence là xác nhận của owner, không phải agent browser observation.

### 2. Files changed

Lượt đóng chỉ sửa `docs/TV3/UC48_UI_QA.md` và `docs/TV3/TV3_CHECKLIST.md` trong scope 7 file đã duyệt. Năm support/test files giữ nguyên; baseline uncommitted khác được bảo toàn. Evidence/chẩn đoán chỉ đọc lưu dưới ignored `.agent-workspace/tv3/uc48-qa-ui-01/closure/`; không ghi row values/secrets.

### 3. Checks executed

- Runner đã thoát **exit 1**: `Development fingerprint changed; do not restore or overwrite development data.` Không giấu hoặc ghi đè result.json gốc.
- Resource cleanup PASS: schema/user/connection QA đều 0; process 13248/5212 thoát; port 56978/56979 được giải phóng; credentials private file đã xóa. Đã kiểm lại SQL identity/absence và listener sau runner.
- QA business fingerprints 9 bảng và protected source/config/env không đổi; authenticated QA sessions 0.
- Dev counts không đổi nhưng full-dump SHA256 khác. Read-only binlog metadata trong cửa sổ provision→stop: **11 dev row events đều là sessions, 0 business write events**; không thấy DDL tác động nghiệp vụ dev.
- QA binlog có 154 preflight/window row events nhưng **0 sau readiness**; chưa correlate manual với schema QA được bàn giao.
- Không chạy lại Backend/Node/lint/build: lượt này chỉ stop/audit và sửa docs. Kết quả trước vẫn giữ ngày/evidence gốc: guard 50/95 assertions; SQLite 2.285 PASS/9.684 assertions/409 skip; 6 HTTP scenarios 558 probes/72 oracle comparisons; Node 215 PASS; lint/build exit 0 với warnings cũ.
- Kiểm tài liệu/scope/links/whitespace sau cập nhật; không đổi ngoài hai file tài liệu trong lượt này.

### 4. Business/contract fidelity

Không thay contract UC48/UC47 hoặc nghiệp vụ TV2; QA business rows không đổi qua manual window. Binlog dev chỉ cho thấy session activity; không gán người thực hiện hay kết luận owner dùng URL nào. User QA không có quyền ghi dev; không reset/seed/restore hoặc chỉnh dev sessions. UI results được ghi đúng nguồn owner report; môi trường manual còn giới hạn đối chiếu.

### 5. Acceptance criteria

| AC | Trạng thái | Evidence/giới hạn |
| --- | --- | --- |
| QAUI01 | PARTIAL | Preflight/source/API/session PASS; chưa correlate manual QA runtime |
| QAUI02 | OWNER REPORTED PASS | M02/M04 và oracle; context còn thiếu |
| QAUI03 | OWNER REPORTED PASS | M03/M05/M06; context còn thiếu |
| QAUI04 | OWNER REPORTED PASS | M07–09; context còn thiếu |
| QAUI05 | OWNER REPORTED PASS | M10–12, không agent browser observation |
| QAUI06 | PARTIAL | Resource cleanup/source/QA data PASS; dev full fingerprint FAIL, sai lệch sessions chưa được miễn trừ |

### 6. Risks/dependencies/deviations

Không còn môi trường/schema/user/credential QA của live run. Sai lệch dev dump và thiếu session writes QA sau handoff là giới hạn audit, cần đối chiếu URL/backend owner đã kiểm trước khi chốt task. Không tái dựng môi trường hoặc sửa guard để làm PASS trong lượt này. UC48 chưa đóng, không commit/push/merge.

### 7. Skills applied

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục đúng task/scope, baseline và điểm dừng.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): đối chiếu M01–M12 với UI/lifecycle/viewport, ghi đúng nguồn manual.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): session/runtime/source boundary và phân biệt HTTP preflight với browser context.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md): owned resource cleanup, effective instance, hashes và binlog metadata read-only.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): không báo PASS giả khi runner exit 1; AC/evidence/owner acceptance và Task Result 8 phần.

### 8. Next task/stop

Đề xuất tiếp tục **đối chiếu môi trường manual và sai lệch audit trong chính QA-UI-01** khi owner giao tiếp; chưa chuyển CLOSE-01 hoặc đóng UC48. Lượt hiện tại dừng sau báo cáo; không mở lại runtime.

## 14. UC48-QA-UI-01 — Owner acceptance với giới hạn, 2026-11-20

Owner xác nhận manual dùng dev `http://localhost:5173`, đã đọc kết luận audit và quyết định nghiệm thu để sang bước tiếp theo. **OWNER ACCEPTED WITH DOCUMENTED LIMITATIONS.** M01–M12 giữ owner-reported PASS trên dev; không tuyên bố manual trên schema QA riêng hoặc TECHNICAL PASS tất cả gate. Runner exit 1/log/fingerprint giữ nguyên, không sửa lịch sử. Cleanup resources PASS; source/config/QA business không đổi. Audit follow-up xác nhận dev Vite → backend 8000 → `stayhub`, đối chiếu binlog sessions; không phát hiện dev business writes trong cửa sổ đã kiểm.

Lượt ghi nhận nghiệm thu chỉ sửa checklist và `UC48_UI_QA.md`; không sửa source, guard, DB/env, chạy lại suites hoặc dựng lại runtime. Skills áp dụng: [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) — trạng thái/task boundary/scope CLOSE; [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) — phân biệt owner acceptance và technical evidence, bảo toàn audit exception. Không đóng toàn bộ UC48 trong lượt này.

## 15. UC48-CLOSE-01 — Mini Plan đã duyệt, checkpoint 2026-11-20

Owner đã duyệt Mini Plan/scope 3 file, ban đầu yêu cầu chưa triển khai trong lúc review PR13. Sau đó owner giao triển khai và tiếp tục sau lượt ngắt. Phạm vi vẫn giữ nguyên bảng dưới; bổ sung vào hồ sơ trạng thái nghiệm thu CSS/VND và presentation hiện tại, không mở rộng file. Kết quả thực hiện ở mục 18.

### 1. Mục tiêu/hiện trạng

Tổng hợp contract, implementation, automated evidence, owner acceptance và giới hạn tích hợp/audit thành hồ sơ bàn giao UC48 V1. 7 task trước đã được owner nghiệm thu; CLOSE là task cuối. QA-UI accepted có giới hạn, không làm lại test để đổi lịch sử. UC48 vẫn mở chờ CLOSE và chấp thuận hồ sơ cuối.

### 2. Hướng thực hiện/Backend–Frontend

Docs-only: đối chiếu source/routes/contracts hiện tại, lập ledger bằng chứng Backend/Frontend theo từng engine và nguồn manual, cập nhật index/checklist. Không thay code, schema, env hoặc scope TV1/TV2; không merge dev/PR. Hồ sơ đóng phạm vi TV3 khác với tích hợp toàn hệ thống.

### 3. Contract và quyết định giữ nguyên

Giữ toàn bộ UC48 V1: ADMIN ACTIVE, kỳ HCM 1–12 tháng, Booking created_at/current status, PAID Invoice paid_at/header decimal, cutoff, zero-fill/global warning. UC47 raw semantics/API không đổi; amendment VND đã duyệt chỉ đổi text Dashboard/Statistics thành 0 phần lẻ, half-up bằng BigInt. Namespace CSS hiện tại theo chức năng; hai task bổ sung đã được owner nghiệm thu nhưng browser mới vẫn NOT VERIFIED. Ghi rõ manual dev, QA fixture/API tự động riêng, runner audit FAIL vì dev sessions và owner chấp nhận giới hạn; không gọi QA fixture browser PASS hoặc tự nới guard. Tài liệu contract là baseline lịch sử, không sửa lại lời văn để giả như có test trước khi thực hiện.

### 4. Verification

Đối chiếu ledger với log/artifacts đã có, không tự chạy lại test hoặc provision. Kiểm ngày/run/engine/count/verdict, nguồn owner acceptance, đường dẫn/link/heading/whitespace, Git baseline/hash/scope và secrets scan nội dung tài liệu được viết. Giữ artifact ignored là local, không cam kết đi theo Git. Không lấy Node/build thay manual hoặc MySQL. Chỉ khi phát hiện thiếu bằng chứng mới nêu rõ, không tự mở runtime ngoài scope.

### 5. Exact scope đã duyệt 3 file

| Hành động | File | Mục đích |
| --- | --- | --- |
| Tạo | `docs/TV3/UC48_ACCEPTANCE_HANDOVER.md` | Hồ sơ contract/implementation/AC/ledger/owner acceptance/limitations/handoff |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` | Ghi kết quả CLOSE, 7 task đã accepted và trạng thái chờ nghiệm thu hồ sơ cuối |
| Sửa | `docs/TV3/README.md` | Bổ sung DATA/UI/acceptance hồ sơ UC48, cập nhật mô tả/index/file count đang cũ |

Không cần sửa file dùng chung của ứng dụng. DATA_QA/UI_QA/contract/source/tests chỉ đọc; dẫn chiếu trạng thái hiện hành tại checklist/handover. Không dọn/xóa artifacts hay gom/xóa hồ sơ ngoài scope.

### 6. AC ban đầu

| AC | Kết quả cần có | Evidence | Trạng thái |
| --- | --- | --- | --- |
| CLOSE01 | Đủ phạm vi implementation và contract V1 | Source/route + handover | NOT VERIFIED |
| CLOSE02 | Ledger Backend/MySQL/SQLite/Node/build/manual chính xác | Logs, dates/runs/counts/verdicts | NOT VERIFIED |
| CLOSE03 | Ghi owner acceptance/technical PARTIAL/audit FAIL trung thực | Chat/checklist/UI audit references | NOT VERIFIED |
| CLOSE04 | Dependencies TV2/dev integration và việc còn lại rõ | Handover boundaries | NOT VERIFIED |
| CLOSE05 | Đúng 3 file, links/index/whitespace/secrets/scope đạt | Static docs checks/diff | NOT VERIFIED |

### 7. Thứ tự/dependencies/rủi ro/skills

Approved scope → baseline → đối chiếu ledger/AC/source → viết handover → cập nhật README/checklist → kiểm static/scope → Task Result 8 phần → dừng chờ owner nghiệm thu hồ sơ cuối. Ước lượng 30–45 phút công sức thông thường. Rủi ro: evidence local không đi theo Git, index cũ, nhầm manual dev thành QA fixture; phải ghi đúng giới hạn. Không tự tăng số UC CLOSED hoặc tuyên bố UC48 CLOSED trước chấp thuận cuối của owner. Khi hồ sơ CLOSE được nghiệm thu mới ghi chốt UC48 và cập nhật số tổng tương ứng.

Skills thực tế áp dụng cho Mini Plan: [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) — baseline/scope/7 phần; [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) — evidence/acceptance/docs verification/report. Không áp dụng skill sửa code/database/runtime trong lượt docs planning này.

Checkpoint plan đã được duyệt; CLOSE chỉ triển khai sau lệnh giao việc riêng của owner. Trạng thái hiện hành/kết quả ở mục 18, không coi phê duyệt plan là nghiệm thu hồ sơ cuối.

## 16. ADMIN-CSS-NAMING-01 — Task Result 2026-11-20

Owner duyệt hai Mini Plan và scope hợp nhất 16 file, thực hiện naming trước VND. **IMPLEMENTED / AUTOMATED CHECKS PASS; OWNER ACCEPTED trên chat ngày 2026-11-20**, sau báo cáo và trước phê duyệt CLOSE. Visual smoke mới chưa kiểm; NAME04 technical PARTIAL giữ nguyên. Nghiệm thu UC48 QA-UI trước đó giữ nguyên phạm vi/lịch sử, không gán lại cho thay đổi này.

### 1. Backend/Frontend outcomes

Frontend chuyển task-number prefixes sang `admin-dashboard`/`admin-statistics` và tên elements/modifiers theo chức năng. Đổi cả class động, CSS variable, animation và selectors/media queries; control Invoice shared đổi từ `host-*` sang `invoice-*` ở cả Admin/Host. Backend không đổi.

### 2. Files changed

Đúng 9 file sửa: `frontend/src/pages/admin/AdminStatisticsPage.jsx`, `AdminStatisticsPage.css`, `AdminDashboardPage.jsx`, `AdminInvoiceListPage.jsx`; `frontend/src/components/dashboard/AdminDashboardCharts.jsx`, `AdminDashboardCharts.css`; `frontend/src/components/invoice/Invoice.css`; `frontend/src/pages/host/HostInvoiceListPage.jsx`; checklist này. Invoice.css là shared file đã được owner duyệt, mọi consumer của hai control được sửa đồng bộ. Không thêm file/dependency hoặc sửa ngoài scope.

### 3. Tests executed

Node toàn bộ **215 PASS / 0 FAIL / 0 SKIP**, exit 0; lint exit 0 (0 errors, 3 Fast Refresh warnings có sẵn); build exit 0 (bundle >500 kB). Static inverse mapping 80 entries khôi phục nguyên bản 8 source files; chỉ đổi tên, không đổi JSX structure, CSS declarations/specificity, breakpoints hoặc logic. Quét source không còn UC prefixes/hai control names cũ. Scope/hash/whitespace kiểm theo baseline. Không chạy Backend/MySQL/browser hoặc dựng QA.

Evidence mới: `.agent-workspace/tv3/admin-css-naming-01/` — baseline, source backups, rename-map, source-equivalence, Node/lint/build và scope checks. Không coi Node/build là visual browser PASS.

### 4. Business/contract fidelity

API, raw decimal, quyền, lifecycle, DOM IDs/ARIA, component/export/route và hành vi được bảo toàn bằng đối chiếu source sau khi đảo ánh xạ. Namespace riêng vẫn giữ CSS theo feature. Booking status class động giữ cùng enum suffix, không thay business statuses. Không ảnh hưởng nghiệp vụ TV1/TV2.

### 5. Acceptance criteria

| AC | Trạng thái | Evidence |
| --- | --- | --- |
| NAME01 | PASS | Không còn UC prefixes trong class/variable/keyframe source được rà |
| NAME02 | PASS | Mapping bijective và inverse source equivalence, gồm dynamic prefix |
| NAME03 | PASS | Invoice shared selectors + Admin/Host consumers cùng tên mới |
| NAME04 | PARTIAL | CSS/JSX/logic equivalence PASS; visual responsive/focus/animation mới NOT VERIFIED |
| NAME05 | PASS | Node/lint/build/scope/whitespace |

### 6. Risks/dependencies/deviations

Không lệch scope hoặc backend dependency. Cần owner kiểm nhanh Dashboard/Statistics và filter Invoice Admin/Host sau đổi tên, desktop/mobile/focus/animation; không tự dùng Computer Use hoặc dựng browser harness. Existing warnings giữ nguyên. Không commit/push/merge.

### 7. Skills used

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): approved 9-file slice, baseline và thứ tự hai task.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): semantic namespace, shared consumers/dynamic classes và bảo toàn presentation.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): inverse equivalence, test evidence/scope, visual limit và report 8 phần.

### 8. Next task / stop

Trong lượt triển khai đã tiếp tục **ADMIN-VND-DISPLAY-01** theo phê duyệt cùng lượt. Owner sau đó nghiệm thu cả hai task; không dùng acceptance này để tự đóng UC48. Trạng thái CLOSE hiện hành ở mục 18.

## 17. ADMIN-VND-DISPLAY-01 — Task Result 2026-11-20

Owner đã duyệt Mini Plan, quy tắc half-up đến đồng và scope 8 file (hợp với naming là 16 file khác nhau). Thực hiện sau khi naming hoàn tất kiểm thử/scope. **IMPLEMENTED / AUTOMATED CHECKS PASS; OWNER ACCEPTED trên chat ngày 2026-11-20**, cùng task naming. Chưa chạy visual browser mới, không dựng lại môi trường QA.

### 1. Backend/Frontend outcomes

Formatter dùng chung `utils/formatVnd.js` nhận nonnegative canonical decimal string scale 2, làm tròn half-up bằng BigInt và format vi-VN 0 fractional digits. Dashboard/Statistics giữ public formatter exports, dùng helper mới cho KPI, bảng tháng, chart labels và bảng dữ liệu biểu đồ. API/raw decimal, mapper, financial calculations, chart ratios và empty-state predicates không đổi. Backend không sửa.

### 2. Files changed

| Hành động | File | Mục đích |
| --- | --- | --- |
| Tạo | `frontend/src/utils/formatVnd.js` | Pure whole-dong presentation, exact BigInt rounding |
| Tạo | `frontend/tests/formatVnd.test.js` | Golden values, thresholds/carry, large integers, malformed inputs |
| Sửa | `frontend/src/services/adminDashboardService.js` | Delegate formatter, giữ service/API/ratios |
| Sửa | `frontend/src/services/statisticsService.js` | Delegate formatter, giữ raw data/lifecycle |
| Sửa | `frontend/tests/adminDashboard.test.js` | Expected text mới, giữ raw contract assertions |
| Sửa | `frontend/tests/adminStatistics.test.js` | Expected text mới, raw decimal không đổi |
| Sửa | `docs/TV3/UC48_STATISTICS_CONTRACT.md` | Ghi presentation amendment được owner duyệt; dữ liệu/đối soát scale 2 giữ nguyên |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` | Ghi task result/AC/giới hạn |

Đúng 8 file cho task; naming files giữ nguyên so baseline sau task 1. Không đổi formatter Invoice/Booking/Catalog đã không có fractional digits, không mở rộng sửa module TV2 hoặc shared Axios.

### 3. Tests executed

Focused `node --experimental-test-module-mocks --test tests/formatVnd.test.js tests/adminDashboard.test.js tests/adminStatistics.test.js`: **37 PASS / 0 FAIL / 0 SKIP**, exit 0. Full Node cùng flag: **231 PASS / 0 FAIL / 0 SKIP**, exit 0. Lint exit 0, 0 errors/3 Fast Refresh warnings có sẵn; build exit 0, bundle >500 kB. Unit golden values kiểm `.00/.01/.49/.50/.99`, carry 999→1000, zero, large values >Number.MAX_SAFE_INTEGER và malformed/nonstring/signed/newline input. Service tests kiểm actual public formatter và raw mapped/source values giữ decimal.

Scope/hash/whitespace/links checks theo baseline riêng và baseline chung; không chạy Backend/MySQL/browser vì chỉ presentation. Evidence mới `.agent-workspace/tv3/admin-vnd-display-01/`, không dùng log trước để gọi là kết quả mới.

### 4. Business/contract fidelity

`1000000.00` → `1.000.000 ₫`; `300.76` → `301 ₫`; `100.25` → `100 ₫`; `0.00` → `0 ₫`; `19999999999.98` → `20.000.000.000 ₫`; `9007199254740993.50` → `9.007.199.254.740.994 ₫`. Không dùng Number/parseFloat trong formatter mới. Rounding chỉ áp text từng giá trị; không dùng text đã làm tròn để cộng tổng, tính chart hoặc đổi raw response. Summary/month display có thể lệch tổng các số đã làm tròn; raw invariants/API scale 2 vẫn chính xác. `0.49` hiển thị 0 đồng nhưng empty-state vẫn dựa raw amount. API/schema và dữ liệu nghiệp vụ không đổi; contract amendment chỉ presentation.

### 5. Acceptance criteria

| AC | Trạng thái | Evidence |
| --- | --- | --- |
| VND01 | PASS automated/source | Các formatter consumers dùng chung output không có fractional digits |
| VND02 | PASS | Golden values half-up/carry và số rất lớn |
| VND03 | PASS | Raw mapper/source decimal assertions giữ nguyên, không sửa API/backend |
| VND04 | PASS automated/source | Dashboard KPI/charts/data table và Statistics summary/month dùng public wrappers chung |
| VND05 | PASS | Focused/full Node, lint/build, scope/docs checks |

Visual browser output sau thay đổi vẫn NOT VERIFIED; không biến automated/source evidence thành browser evidence. Owner đã nghiệm thu hai task trên chat; không có xác nhận một lượt browser mới sau thay đổi để ghi thêm PASS.

### 6. Risks/dependencies/deviations

Không lệch scope/đổi nghiệp vụ hoặc dependencies. Đã cập nhật quy tắc contract theo yêu cầu owner thay vì làm tròn dữ liệu API. Cần owner kiểm nhanh Dashboard/Statistics (0, tiền lớn, hàng tháng và biểu đồ/data table), Invoice Admin/Host sau naming, desktop/mobile/focus. Không dùng lại manual UC48 trước đó để chứng minh hai thay đổi mới đã được kiểm browser. Không commit/push/merge, không provision hay thay dev/env.

### 7. Skills used

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tuần tự task 2 sau task 1, exact scope/baseline và giữ CLOSE-01 ngoài lượt triển khai.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): presentation helper, stable service wrappers, đồng nhất consumers và giữ raw contract.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): independent golden tests, financial boundary cases, scope/contract docs và report 8 phần.

### 8. Next task / stop

Owner đã nghiệm thu **hai task ADMIN-CSS-NAMING-01 và ADMIN-VND-DISPLAY-01** và duyệt Mini Plan/scope CLOSE riêng. Kết quả triển khai CLOSE ở mục 18; chưa đóng UC48 trước nghiệm thu cuối.

## 18. UC48-CLOSE-01 — Task Result 2026-11-20

**TECHNICAL PASS trong scope hồ sơ CLOSE01–CLOSE05; OWNER ACCEPTED UC48-CLOSE-01 trên chat ngày 2026-11-20. UC48 CLOSED TV3.** Tổng TV3 cập nhật **14/18 UC CLOSED**, đủ 8/8 task UC48 accepted. Không thay technical PARTIAL của QA-UI hoặc các verdict lịch sử. Lượt ghi nhận nghiệm thu đồng bộ đúng 3 hồ sơ checklist/README/handover; không sửa source/tests/env/data.

### 1. Backend/Frontend outcomes

Đã tổng hợp [bàn giao UC48 V1](UC48_ACCEPTANCE_HANDOVER.md): source/API/UI hiện có, contract, 12 AC, ledger theo engine/ngày/run, owner acceptance và các giới hạn. Ghi nhận nghiệm thu CSS/VND, namespace chức năng và VND 0 phần lẻ/raw scale 2. Backend/Frontend source không sửa; CLOSE là task tài liệu, không cần thêm UI.

### 2. Files changed

| Hành động | File | Kết quả |
| --- | --- | --- |
| Tạo | `docs/TV3/UC48_ACCEPTANCE_HANDOVER.md` | Hồ sơ implementation/contract/AC/evidence/limits/dependencies |
| Sửa | `docs/TV3/TV3_CHECKLIST.md` | Acceptance CSS/VND, approved plan, current status và Task Result CLOSE |
| Sửa | `docs/TV3/README.md` | Index 11 hồ sơ + README = 12 file, mô tả UC48 đúng hiện trạng |

Không sửa DATA_QA/UI_QA/contract/source/tests/shared application files; các trạng thái cũ trong hồ sơ lịch sử được giải thích bằng dẫn chiếu, không viết lại verdict. Giữ accepted tracked/untracked từ baseline.

### 3. Checks executed

Đối chiếu source route/service/repository/frontend/formatter với contract; ledger với summary và log/run gốc, tách QUERY 2026-11-19 và các checkpoint 2026-11-20: 15 kiểm tra evidence/source PASS. Kiểm 108 liên kết Markdown, 6 anchors, mục lục/file count, headings, whitespace cả file mới, secrets-pattern scan, hash/scope và Git status: PASS; script kiểm tài liệu và `git diff --check` exit 0. Evidence kiểm tài liệu mới ở `.agent-workspace/tv3/uc48-close-01/verification-summary.json` và `checks.json`.

Không chạy mới `php artisan test`, Node, lint/build, MySQL hoặc browser vì scope chỉ tài liệu. Số test trong handover là evidence lịch sử: QA-DATA MySQL 214 PASS/1.379 assertions; QA-UI SQLite 2.285 PASS/9.684 assertions/409 SKIP và 6 HTTP scenarios; VND Node 231 PASS. Không lấy test PR13 làm test UC48. Không provision, seed/reset DB hoặc mở runtime.

### 4. Business/contract fidelity

ADMIN ACTIVE; kỳ HCM 1–12 tháng/cutoff; Booking created_at/current status; revenue PAID/paid_at/header; decimal scale 2, zero-fill/global warning giữ nguyên. Naming/VND chỉ presentation đã được nghiệm thu; UC47 raw contract không đổi. Giữ manual M01–M12 trên dev là owner-reported PASS, audit runner exit 1 do dev sessions, technical PARTIAL và cleanup PASS lịch sử; không đổi thành QA fixture browser PASS.

### 5. Acceptance criteria

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| CLOSE01 | PASS tài liệu | Handover mục 1–3: contract và source map API/UI hiện hữu |
| CLOSE02 | PASS tài liệu | Mục 5: engine/ngày/run/count/verdict, logs gốc và failures được giữ |
| CLOSE03 | PASS tài liệu | Mục 4/6/7: owner acceptance khác technical evidence; CSS/VND visual NOT VERIFIED |
| CLOSE04 | PASS tài liệu | Mục 8: T01–T03 BLOCKED TV2, không suy fixture thành production integration |
| CLOSE05 | PASS tài liệu | Đúng 3 file; links/anchors/index/whitespace/secrets/scope kiểm đạt |

### 6. Risks/dependencies/deviations

Không lệch scope. Raw artifacts ignored chỉ có local, không đi theo Git; lint/build warnings cũ và browser sau CSS/VND chưa kiểm vẫn ghi rõ. T01–T03 chưa đóng vì chưa có integration evidence được nghiệm thu; CLOSE không phụ thuộc merge PR13. Không mở lại task QA-UI đã được owner chấp nhận giới hạn và không tự ghi UC48 CLOSED. Không commit/push/merge hoặc thay env/data.

### 7. Skills used

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục plan 3 file đã duyệt sau lượt ngắt, baseline/owner/task boundary và không tự mở task mới.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): evidence ledger/AC, owner acceptance với limits, đối chiếu log/source và docs/scope checks, báo cáo 8 phần.

Không tính các skills Laravel/React/Database ở task triển khai trước vào skills đã áp dụng trong CLOSE; không sửa skill hoặc dùng Computer Use.

### 8. Next task / stop

**Owner đã nghiệm thu UC48-CLOSE-01 và hồ sơ bàn giao cuối; UC48 CLOSED TV3, tổng 14/18.** Còn UC17/39/44/61, FAQ Management và Chat History ngoài số đếm 18 UC. Chờ owner chọn/giao scope tiếp theo; dừng sau báo cáo, chưa triển khai UC/task khác. Kiểm tài liệu/hash/scope của lượt ghi nhận nghiệm thu lưu riêng tại `.agent-workspace/tv3/uc48-close-01-acceptance/`; không ghi đè evidence CLOSE trước nghiệm thu.
