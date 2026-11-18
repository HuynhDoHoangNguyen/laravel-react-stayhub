# UC47 — hồ sơ kiểm chứng và bàn giao

Cập nhật: 2026-11-18. Baseline: `feature/tv3-uc47-admin-dashboard`, HEAD `5001a61` cộng working tree ENV/FIX/CORE/CHART đã được owner nghiệm thu. Không dùng riêng HEAD làm baseline source.

## Trạng thái nghiệm thu

- ENV-01, ENV-FIX-01, CORE-01 và CHART-01: owner đã nghiệm thu trên chat.
- **QA-01A: TECHNICAL PASS**, owner đã nghiệm thu trên chat. Đây là browser harness và HTTP session/CSRF/oracle/cleanup, chưa phải browser UI acceptance.
- **QA-01B: hoàn tất theo phạm vi điều chỉnh** — hai finding accessibility đã sửa/tái kiểm; zoom 200%, bàn phím và cleanup UI được owner xác nhận đạt. Screen reader thực **NOT VERIFIED, được owner loại khỏi phạm vi**, không ghi PASS.
- **QA-01C: hoàn tất theo phạm vi đã thống nhất** — phần tự động PASS; owner xác nhận C04–C08 đều đạt và cleanup UI xong.
- **UI-01: ACCEPTED** — tự động PASS, owner xác nhận UI01–UI06 đều đạt và chấp nhận kết quả trên chat ngày 2026-11-18.
- **UC47: ĐÃ NGHIỆM THU / ĐÓNG trong phạm vi TV3 đã thống nhất**, gồm ngoại lệ screen reader. Các checkpoint PARTIAL/chờ xác nhận/chưa đóng phía dưới giữ làm lịch sử; tổng kết cuối là trạng thái hiện hành. Chưa commit/push.

## Contract dashboard hiện hành

GET `/api/admin/dashboard`, ADMIN ACTIVE, cookie/session Sanctum. Response `data` gồm summary (users/hosts/properties/rooms/bookings counts và revenue_total), excluded_paid_invoices_count, generated_at UTC, booking_status_counts đủ sáu trạng thái, revenue_by_month đủ 12 tháng, report_timezone Asia/Ho_Chi_Minh.

KPI toàn thời gian, mọi trạng thái tài nguyên hiện tồn tại. Doanh thu từ PAID có paid_at; loại UNPAID/REFUNDED/PAID thiếu ngày. Biểu đồ theo paid_at ở tháng hiện tại và 11 tháng trước, cutoff generated_at. Tổng 12 tháng không thay KPI toàn thời gian; refund hiện tại có thể đổi tổng lịch sử. Không mutation nghiệp vụ, không live payment hoặc workflow Booking TV2 trong QA này.

## QA-01A — harness và ranh giới

Tạo 5 file backend QA: Uc47BrowserEnvironment.php, Uc47BrowserServer.php, Uc47BrowserFixtures.php, RunUc47Browser.ps1 và Unit/Uc47BrowserEnvironmentTest.php. Không sửa production/Auth/config/schema/frontend hoặc guard/runner API UC47/UC36/UC45.

- Guard browser riêng: explicit opt-in, local runtime, debug false, database session; tái sử dụng hàm guard target/config/identity/grants thuần của UC47 bằng bản sao chuẩn hóa. Ứng dụng thật vẫn local, runningUnitTests false; không đổi semantics guard API.
- Schema `stayhub_uc47_test_<guid>` và account `uc47_<run-prefix>` riêng; loopback MySQL Docker 8.4.13; APP_KEY/password ngẫu nhiên. Session cookie theo run, session table ở schema QA, CORS/Sanctum chỉ origin frontend QA. Process environment overrides, root env giữ nguyên.
- Chỉ migrate schema QA rỗng; không seed chung/reset dev. Backend server wrapper chỉ đưa request qua normal Laravel pipeline; prepare/audit/verify là CLI modes, không có QA HTTP route/backdoor.
- PHP/Vite bind loopback với ports riêng. Readiness kiểm HTTP frontend và module AdminDashboardPage hiện có; chưa chứng minh React render.
- Probe dùng cookie jar riêng mỗi actor, không actingAs/token/session injection. Session/user binding kiểm trực tiếp ở DB nhưng chỉ lưu actor/count metadata; không raw session/cookie/XSRF.

## Fixtures và oracle

Tất cả tài khoản dùng hash của hasher hiện hành và đã kiểm password hash; snapshot tài chính được đối chiếu sau insert. Oracle độc lập với Dashboard Repository/QueryService/Mapper, native DateTimeImmutable và integer cents có kiểm overflow trên PHP 64-bit.

| Scenario | Accounts/Host | Property/Room/Booking | Doanh thu | PAID thiếu ngày |
| --- | --- | --- | --- | --- |
| Populated | 8 / 3 | 4 / 4 / 10 | 300.75 | 1 |
| Zero | 2 / 0 | 0 / 0 / 0 | 0.00 | 0 |
| NoRevenue | 8 / 3 | 4 / 4 / 10 | 0.00 | 0 |
| Large | 8 / 3 | 4 / 4 / 10 | 19999999999.98 | 1 |

Populated/NoRevenue/Large có 2 Admin active, 3 Host (một Host inactive không có cơ sở), Customer, Admin blocked/inactive. Booking counts theo PENDING/CONFIRMED/CHECKED_IN/COMPLETED/CANCELLED/REJECTED là 1/1/1/5/1/1. Chỉ năm Booking COMPLETED có Invoice; ROOM item một đêm, subtotal/total/Booking snapshots khớp, discount/extra_fee bằng 0. PAID thiếu ngày là negative fixture có chủ đích.

Populated PAID 100.25 ở tháng trước, 200.50 ở đầu tháng hiện tại; UNPAID/REFUNDED không tính. Large thay hai PAID bằng 9999999999.99 mỗi hóa đơn. NoRevenue toàn UNPAID; Zero chỉ có Admin. Oracle tính lại kỳ theo generated_at từng response, không hardcode tháng của run trước.

## Bằng chứng thực chạy QA-01A

### Unit và hồi quy

- Focused `php artisan test tests/Unit/Uc47BrowserEnvironmentTest.php --no-ansi --do-not-cache-result`: **49 passed, 105 assertions**, exit 0.
- Full `php artisan test --no-ansi --do-not-cache-result`: **1487 passed, 102 skipped, 0 failed, 7298 assertions**, exit 0. PHPUnit mặc định SQLite in-memory; MySQL-only suites skip đúng guard, không dùng skipped làm MySQL evidence.
- PHP syntax, PowerShell parser, Git whitespace/scope PASS. Không chạy lại Node/lint/build vì QA-01A không sửa frontend.

### HTTP thật, không browser UI

| Scenario / control | Run ID | Kết quả |
| --- | --- | --- |
| Populated | ed372fa1d5094409b043cf83aa92fe13 | exit 0; 65 status assertions, 4 oracle comparisons |
| Zero | 7ea30f09d70f4f7f9b10ab31e23e35bb | exit 0; 20 status assertions, 4 oracle comparisons |
| NoRevenue | 5be0f7bb622b4a828d1bc384ad07d30d | exit 0; 65 status assertions, 4 oracle comparisons |
| Large | 50bc4661875c470f9b653354d0389ec1 | exit 0; 65 status assertions, 4 oracle comparisons |
| Fail after Prepare | aae45561e7434fe9a76902d436b9468c | exit 1, đúng UC47_EXPECTED_FAILURE_AFTER_PREPARE |
| Fail after Ready | d4cc1f7b39124d46bab68a1717881fc5 | exit 1, đúng UC47_EXPECTED_FAILURE_AFTER_READY |

Tổng positive: **215 HTTP status assertions**, gồm 85 HTTP 200, 26 HTTP 204, 69 HTTP 401, 27 HTTP 403 và **8 HTTP 419**; **16 full dashboard/oracle comparisons**. Đây là số assertion của runner, không phải số PHPUnit tests.

Đã kiểm: guest 401; CSRF cookie 204; missing/wrong XSRF với credentials hợp lệ bị 419; login thật; đúng actor/role; run-owned session cookie; /api/me và repeated dashboard giữ phiên; session DB gắn actor; Host/Customer domain 403 vẫn giữ phiên; blocked/inactive trả ACCOUNT_BLOCKED/ACCOUNT_INACTIVE; logout rồi /me/dashboard 401. Credentialed CORS header khớp origin QA.

Bốn scenario: hash 9 bảng nghiệp vụ trước–sau probes khớp, authenticated sessions sau logout bằng 0. Snapshot tài chính và oracle đạt. Không có UI observation.

### Cleanup và bảo toàn

Cả sáu run cuối: source/dev/env fingerprints trước–sau khớp; schema/user/connection QA còn 0; mọi owned process exited; ports không còn listener. Negative controls vẫn cleanup sạch sau prepare/ready, không gọi là positive suite PASS. Evidence mảng rỗng được lưu thành `[]` (run fail trước khi start servers).

Kiểm tra SQL cuối task cho toàn prefix UC47: 0 schemas, 0 users, 0 connections. Evidence không phát hiện DB password/APP_KEY/login password QA. Raw credentials/cookies/CSRF/session không được ghi; source/config không tự restore.

Evidence local ignored: `.agent-workspace/tv3/uc47-qa-01a/` gồm `final-runs.json`, `verification-summary.json`, `backend.txt`, baseline hashes và từng run (manifest, fixture/spec, oracle, HTTP statuses, session metadata, business/dev/source fingerprints, process/port/DB cleanup). Các run thử trước bản cuối được giữ local, không dùng thay ma trận sáu run ở trên.

## Ma trận AC QA-01A

| AC | Trạng thái / evidence |
| --- | --- |
| A01 | PASS: negative unit guard/config/manifest/cache, identity/grants runtime trước migration/fixture; thiếu -Provision dừng trước connection. |
| A02 | PASS: four scenarios, persisted financial snapshots/hash verification, independent golden oracle và overflow tests. |
| A03 | PASS: runtime local/runningUnitTests false, 8 real CSRF 419 controls. |
| A04 | PASS: real HTTP login/database session/me/repeated dashboard/logout, không actingAs. |
| A05 | PASS: role/account error codes và 16 exact oracle comparisons trên 4 scenarios. |
| A06 | PASS: 9 business-table snapshots khớp; session metadata tách riêng, authenticated sessions sau logout bằng 0. |
| A07 | PASS: positive và failure Prepare/Ready cleanup process/ports/schema/user/connections; final prefix SQL counts 0. |
| A08 | PASS: focused/full backend/syntax/whitespace; đúng 7 file scope, baseline ngoài scope giữ nguyên, docs cập nhật sau runner stop. |

## Chạy lại harness khi được giao

Từ root, PowerShell 7, dependencies sẵn và Compose MySQL hiện có healthy:

```powershell
pwsh -NoProfile -File backend/tests/Support/RunUc47Browser.ps1 -Provision -Scenario Populated
# Scenario: Populated | Zero | NoRevenue | Large
# Negative controls phải exit 1 và cleanup đạt:
pwsh -NoProfile -File backend/tests/Support/RunUc47Browser.ps1 -Provision -InjectFailureAfter Prepare
pwsh -NoProfile -File backend/tests/Support/RunUc47Browser.ps1 -Provision -InjectFailureAfter Ready
```

Mặc định probe xong tự cleanup. `-KeepAlive -MaxMinutes 30` dành cho task browser được duyệt; có thể cung cấp password QA tạm qua STAYHUB_UC47_BROWSER_PASSWORD trong process environment (ít nhất 24 ký tự), không ghi env/file/log. Stop file của run nằm trong ready.json; hết lease hoặc lỗi sẽ cleanup. KeepAlive với stop file đã kiểm ở QA-01B; lease-expiry chưa kiểm chứng.

Không sửa source/root env/checklist trong lúc runner còn giữ fingerprint. Không chạy full backend suite với browser overrides; chạy regression mặc định riêng sau runner. Nếu Docker/ports/config cache không đúng, dừng và báo; không auto-start container hoặc xóa cache/volume.

## Phần còn lại

QA-01B còn thiếu các case nêu dưới; QA-01C chưa thực hiện (fault/race/closure). Cookie jar/CORS header của QA-01A không thay browser evidence. Chưa kiểm lease expiry, port collision hoặc Docker mất kết nối. Không thêm proxy, fix production hoặc tự chuyển task.

## QA-01B — browser UI/session/responsive/accessibility

Ngày 2026-11-18, **PARTIAL; không phải TECHNICAL PASS**. Run `685296bf324342bf900c24bee3fff55e`, Populated, React/Vite và Laravel thật trên loopback, database session ở schema MySQL QA riêng. Dùng ChatGPT in-app browser trong context của task, ban đầu không có tab; không điều khiển cửa sổ Brave của người dùng. Login/logout thực hiện qua UI, không inject session/token.

Scope thay đổi đúng ba file: runner `backend/tests/Support/RunUc47Browser.ps1` thêm audit sau KeepAlive, hồ sơ này và `TV3_CHECKLIST.md`. Audit lưu hash business/session metadata, từ chối thành công nếu dữ liệu nghiệp vụ đổi hoặc còn authenticated session. Không sửa source production/frontend/shared primitives để xử lý finding.

### Ma trận browser thực chạy trước FIX (lịch sử; xem checkpoint mới bên dưới)

| Case | Kết quả / evidence quan sát |
| --- | --- |
| B01 | PASS: guest vào /admin chuyển Admin login, giữ returnTo. |
| B02 | PASS: Admin A login UI, hiển thị đúng dashboard. |
| B03 | PASS: reload trình duyệt khôi phục session/dashboard. |
| B04 | PASS: click và Enter trên Tải lại thấy disabled/loading rồi dữ liệu đúng. |
| B05 | PASS: logout qua menu tài khoản; Back về Admin login, không KPI. |
| B06 | PASS: Admin B hiển thị đúng danh tính và dữ liệu; logout qua sidebar về login. |
| B07 | PASS: Host/Customer login đúng khu vực, truy cập trực tiếp /admin bị Khu vực giới hạn, không KPI. |
| B08 | PASS: credentials Host ở Admin login báo sai khu vực, không vào dashboard. Không suy ra HTTP status từ thông báo UI. |
| B09 | PASS: blocked/inactive báo đúng lỗi tài khoản, vẫn ở login. |
| B10 | PASS: Populated KPI 8/3/4/4/10, doanh thu 300.75 VND, cảnh báo loại 1 invoice. |
| B11 | PASS: sáu status counts 1/1/1/5/1/1; 12 tháng 12/2025–11/2026, tháng 10 là 100.25 và tháng 11 là 200.50, còn lại 0. |
| B12 | NOT VERIFIED: browser Zero/NoRevenue/Large chưa chạy. HTTP oracle QA-01A không thay browser evidence. |
| B13 | PASS: Populated tại 1440×900, 768×1024, 375×812; screenshot đọc được, card/chart không chồng; scrollWidth = innerWidth ở cả ba viewport. |
| B14 | NOT VERIFIED: Large trên mobile/tablet chưa chạy. |
| B15 | PASS: thao tác menu tài khoản và mở/đóng mobile sidebar; native Escape đóng sidebar. |
| B16 | PARTIAL: Tab tới chuông/menu/Tải lại có focus visible; Space mở menu; Enter tải lại. Shift+Tab chưa quan sát. |
| B17 | FAIL: menu tài khoản trả focus về trigger sau Escape; mobile sidebar đóng nhưng focus về BODY, không về trigger (tái hiện). |
| B18 | PASS WITH FINDING: kiểm AX/DOM có H1/H2, dt/dd và nhãn/count/tiền dạng text; thanh trang trí aria-hidden. Nhãn sidebar còn tiếng Anh; chưa dùng screen reader thật. |
| B19 | NOT VERIFIED: chưa chứng minh zoom 200%; Control+plus trong IAB không đổi zoom đo được. |

### Findings và giới hạn công cụ

- **F-B01, P2:** mở mobile sidebar, nhấn native Escape, chờ dialog ẩn: activeElement là BODY, focusVisible false; không trả focus về nút mở. Evidence `sidebar-escape-focus.json`. Finding thuộc shared UI, chưa sửa trong task QA.
- **F-B02, P3:** accessible title/description/toggle còn `Sidebar`, `Displays the mobile sidebar.`, `Toggle Sidebar`, chưa Việt hóa. Không tuyên bố đạt WCAG hoặc screen reader chỉ từ AX inspection.
- Khi chuyển sang chọn cửa sổ Brave để kiểm zoom native, Computer Use dừng vì không xác minh URL hiện tại trên Windows đủ tin cậy để áp dụng policy. Đã dừng mọi UI action, không thử công cụ UI khác. Brave chưa được điều khiển; các case còn lại giữ NOT VERIFIED. Không thể đóng tab/reset viewport qua CUA sau tool stop; tab IAB tạm của task không đánh dấu handoff/deliverable, viewport cuối 1440×900.

### Audit, kiểm thử và cleanup

Runner exit **0**, `result.json` passed true chỉ chứng minh harness/audit/cleanup; không đổi browser verdict PARTIAL thành PASS. Audit sau KeepAlive đã thực chạy: hash **9 bảng nghiệp vụ không đổi**, authenticated sessions **0**. Stop file kết thúc run; owned PHP/Vite processes exited, ports 65462/65463 hết listener; schema/user/connections QA **0/0/0**. Dev/source/env fingerprints trước–sau khớp. Không reset/seed dev, không thay env hoặc log secrets.

PowerShell parser PASS; Git whitespace và hash/scope đối chiếu baseline PASS. Không chạy lại PHPUnit/Node/lint/build vì chỉ đổi runner PowerShell và docs; các số suite QA-01A ở trên là evidence lịch sử, không phải run mới QA-01B. Không thực hiện fault/race QA-01C.

| AC QA-01B | Trạng thái / evidence |
| --- | --- |
| B-AC01 | PASS: UI login/role/account denial, B01–B02/B07–B09. |
| B-AC02 | PASS: session restore/reload/logout/Back/Admin B identity, B03–B06. Không suy thành fault/race coverage. |
| B-AC03 | PARTIAL: Populated KPI/charts đúng; thiếu browser Zero/NoRevenue/Large, B10–B12. |
| B-AC04 | PARTIAL: ba viewport Populated đạt; thiếu Large responsive và zoom 200%, B13–B14/B19. |
| B-AC05 | FAIL/PARTIAL: focus sidebar lỗi; English labels; Shift+Tab/screen reader thật chưa kiểm, B15–B18. |
| B-AC06 | PARTIAL: business/session/process/port/DB/dev/source/env audit PASS; tab close/viewport reset chưa quan sát do tool stop. |
| B-AC07 | PASS: ma trận/evidence/findings và giới hạn được cập nhật, không đóng toàn UC47. |
| B-AC08 | PASS: parser/runtime audit/whitespace, chỉ ba file được duyệt; không commit/push hoặc chuyển QA-01C. |

Evidence local ignored `.agent-workspace/tv3/uc47-qa-01b/`: `browser-result.json`, baseline hashes, desktop/tablet/mobile JPEG, DOM snapshots theo actor và keyboard/focus JSON. Harness audit ở `.agent-workspace/tv3/uc47-qa-01a/685296bf324342bf900c24bee3fff55e/` gồm result, business/session snapshots, fingerprint và cleanup. Đề xuất tiếp theo: Mini Plan xử lý findings và hoàn tất phần QA-01B còn thiếu, chờ scope/approval riêng; chưa chuyển QA-01C.

## QA-01B-FIX-01 — checkpoint sau sửa accessibility

Ngày 2026-11-18. **PARTIAL**, chưa TECHNICAL PASS toàn task/gate; owner đã duyệt plan, chưa nghiệm thu kết quả. Source cuối trên cùng branch/HEAD và working-tree baseline; chỉ sửa `frontend/src/components/ui/sidebar.jsx` cùng hai tài liệu UC47/checklist. Không đổi backend, fixture, oracle, runner, Sheet primitive, Auth, router, API, schema hoặc dependencies.

### Kết quả sửa và hồi quy

- **F-B01: sửa và tái kiểm PASS.** Provider giữ ref trigger và opener; mobile Sheet trả focus qua onCloseAutoFocus khi phần tử còn connected, hiển thị và không disabled; loại BODY/HTML khỏi đích trả focus. Forwarded ref được chuyển tiếp. Không query trigger toàn cục hoặc timer. Logout/unmount không focus vào phần tử đã tháo.
- **F-B02: sửa và tái kiểm PASS.** Dialog name `Điều hướng chính`, description `Các mục điều hướng và thao tác tài khoản.`, trigger/rail `Đóng/mở thanh điều hướng`.
- Admin mobile: Enter/Space/click mở sidebar; Tab từ logout vòng về logo, Shift+Tab quay lại logout, focus visible; Escape đóng dialog rồi focus về trigger, focus-visible true. Click overlay cũng trả focus về trigger (focus-visible false sau thao tác chuột là trạng thái bình thường). Escape trong test cuối gửi tới dialog focus scope; một số lệnh gửi native Escape/qua child locator không ổn định, không lấy lần gọi lệnh đơn lẻ làm evidence. Kết quả cuối kiểm DOM sau animation dialog đã unmount.
- Host mobile: Escape/overlay trả focus về trigger. Admin/Host desktop collapse → expand đúng. Menu tài khoản Escape vẫn trả focus về trigger; browser reload giữ dashboard; Enter Tải lại vẫn thành công. Admin và Host logout khi sidebar mở về login, không còn dialog hoặc focus vào trigger cũ. Role/account denial không bị sửa; evidence B01–B09 trước FIX tiếp tục được giữ, không gọi là đã chạy lại toàn bộ browser role matrix.

### Browser scenarios trên source cuối

| Scenario | Run ID | Browser kết quả |
| --- | --- | --- |
| Large | fc8e1ee33fad43899a2e4eaf1b0d74b2 | KPI 8/3/4/4/10; 19.999.999.999,98 VND; hai tháng cuối 9.999.999.999,99, sáu status đúng. Desktop/tablet/mobile 1440×900, 768×1024, 375×812 ảnh full-page và DOM không tràn ngang; tiền dài wrap đầy đủ tại tablet. |
| Zero | e6e53f743a364c5fb5992223d47728ac | KPI 2/0/0/0/0; tiền 0,00; sáu status/12 tháng 0; empty Booking và Revenue đúng. Login UI/logout/guest redirect đạt. |
| NoRevenue | e629c2c506a0413490fdf08828988634 | KPI 8/3/4/4/10; booking chart có dữ liệu 1/1/1/5/1/1; revenue/12 tháng 0; chỉ revenue empty state, không nhầm Booking rỗng. Login UI/logout đạt. |
| Populated | 9ca3ec66c9e741eeac2d58c050c7a3fd | Smoke KPI/charts 300,75, 100,25/200,50 hai tháng cuối; ba viewport không tràn ngang; Enter reload, logout và guest /admin redirect đạt. |

Các run dùng IAB context của task, Vite source/Laravel/MySQL QA thật, login UI session/CSRF; synthetic fixtures không đại diện workflow Booking production. Mỗi run harness còn chạy HTTP probes/oracle: **215 status assertions, 16 oracle comparisons** tổng bốn run cuối; đây là HTTP evidence riêng, không phải số browser/PHPUnit tests. Tháng kỳ được đối chiếu theo oracle/run timestamp, không cố định cho lần chạy sau.

Run Large thử trước `c7b5dae9b1724b8cb4784f64b76ef4a9` hết lease trong gián đoạn: result passed false, failure lease expired, cleanup_failure null; process/port/schema/user/connections đã cleanup. Giữ evidence lịch sử, không cộng vào positive matrix hoặc nói audit sau browser đạt cho run này. Sau đó bổ sung guard loại BODY/HTML và chạy lại trên source cuối như bảng trên.

### Giới hạn còn mở

- **Zoom 200% NOT VERIFIED:** IAB chỉ cung cấp viewport/visibility capability; phím Ctrl+plus không đổi zoom đo được. Lần đo ổn định trước/sau cùng width 1280, DPR 1.375, visualViewport.scale 1. Không suy DPR hệ điều hành hoặc resize viewport thành browser zoom 200%; Ctrl+0 đã gửi nhưng không có chỉ báo zoom UI để xác nhận mức zoom. Viewport override đã reset sau QA.
- **Screen reader thực NOT VERIFIED:** đã thử khởi động Windows Narrator. Cửa sổ Narrator báo integrity cao hơn helper; cửa sổ home không đọc được accessibility, các thao tác native không có foreground process/geometry đáng tin cậy. Không vượt quyền Windows; không có bằng chứng đọc heading/KPI/dialog bằng đầu ra screen reader. Việc Narrator hiện diện không thay acceptance. Owner đã xác nhận trên chat: “Đã tắt Narrator”. Cleanup Narrator được ghi nhận theo xác nhận thủ công của owner, không phải quan sát tự động; không thay bằng chứng screen reader thực hoặc nghiệm thu QA.
- Tab QA mới đã đóng sau logout và reset viewport. Tab tạm cũ từ run hết lease còn error page; bind để đóng bị URL policy chặn data: error document. Không đọc/bypass trang bị chặn; tab không đánh dấu handoff/deliverable. Việc đóng tab cũ chưa kiểm chứng, không ảnh hưởng DB/session QA đã được dọn.

### Kiểm thử, audit và AC hiện tại

- Frontend source cuối: `node --experimental-test-module-mocks --test tests/*.test.js`: **184 passed, 0 failed/skipped**, exit 0; đây là hồi quy logic/transport, không phải test DOM focus. `npm run lint`: exit 0, 0 errors, 3 Fast Refresh warnings hiện hữu ở badge/button/sidebar. `npm run build`: exit 0, còn warning chunk >500 kB (và thông tin plugin timing). Không chạy PHPUnit vì không đổi backend/harness.
- Bốn positive run: exit 0, hash **9 bảng nghiệp vụ trước/sau browser khớp**, authenticated sessions **0**; owned processes exited, ports released, schema/user/connections **0/0/0**; dev/source/env fingerprints khớp. Không seed/reset dev hoặc log secrets. Docs cập nhật sau mọi runner dừng.

| AC FIX | Trạng thái / evidence |
| --- | --- |
| FIX-AC01 | PASS: focus Admin/Host Escape/overlay, keyboard và logout/unmount; admin-final-keyboard.json, host-keyboard.json. |
| FIX-AC02 | PASS: nhãn Việt source và dialog DOM/AX quan sát. |
| FIX-AC03 | PASS: Zero/NoRevenue/Large browser và Large responsive, ảnh/DOM/metrics theo bảng run. |
| FIX-AC04 | PARTIAL: Shift+Tab/focus đã đạt; zoom 200% và screen reader thực NOT VERIFIED do môi trường. |
| FIX-AC05 | PASS: shared Admin/Host desktop/menu/session smoke; 184 Node tests, lint/build exit 0. |
| FIX-AC06 | PASS cho business/dev/source/env/session và tài nguyên harness; Narrator đã được owner xác nhận tắt; đóng tab error cũ chưa kiểm chứng, không gộp thành cleanup UI PASS. |
| FIX-AC07 | PASS hồ sơ: cập nhật evidence và trạng thái từng case, giữ gate PARTIAL, không đóng UC47. |

Ma trận QA-01B mới: B12/B14/B16/B17 **PASS**; B18 nhãn/semantics **PASS**, screen reader thực **NOT VERIFIED**; B19 **NOT VERIFIED**. B01–B11/B13/B15 giữ evidence trước và các smoke mới nêu rõ ở trên. B-AC01/02/03/07/08 PASS; B-AC04/05/06 PARTIAL vì zoom/screen reader và cleanup UI chưa đầy đủ. Không còn finding F-B01/F-B02 tái hiện trong các bước đã quan sát; không tuyên bố đạt WCAG.

Evidence local ignored `.agent-workspace/tv3/uc47-qa-01b-fix-01/`: baseline, frontend logs/result, final-runs.json, admin-final-keyboard.json, host-keyboard.json, Large/Populated metrics, scenario DOM và JPEG, zoom-attempt/stable-attempt JSON. Audit từng run trong thư mục QA-01A theo ID ở trên. Đề xuất tại thời điểm FIX: hoàn tất zoom/screen reader và cleanup UI còn thiếu trong môi trường native phù hợp. Phạm vi hiện hành theo checkpoint thủ công bên dưới; **chưa chuyển QA-01C**, không commit/push.

## QA-01B-NATIVE-01 — kết quả kiểm tra UI thủ công của owner

Ngày 2026-11-18. Theo kế hoạch kiểm tra thủ công đã được duyệt, owner báo trên chat: “Tôi đã kiểm tra zoom 200% và bàn phím, đều ổn. Screen reader (bỏ cái này đi )chưa kiểm tra.” Agent chỉ cập nhật hai tài liệu, không điều khiển máy tính, không khởi động harness hoặc chạy thêm QA trong lần cập nhật này. Các checkpoint phía trên giữ nguyên làm lịch sử; trạng thái hai case còn thiếu được cập nhật như sau:

| Case | Kết quả hiện hành | Nguồn bằng chứng / giới hạn |
| --- | --- | --- |
| B19 — zoom 200% | PASS — owner xác nhận thủ công | Owner báo đã kiểm tra và thấy ổn. Không có ảnh, phiên bản browser, viewport hoặc scenario cụ thể được gửi kèm; không suy rộng thành kiểm lại mọi fixture. |
| B16/B17 — bàn phím | PASS — owner xác nhận thủ công, bổ sung evidence FIX trước | Owner báo kiểm tra bàn phím ổn. Không ghi thành một lượt automation hoặc tái chạy toàn bộ ma trận focus. |
| B18 — screen reader thực | NOT VERIFIED — loại khỏi phạm vi nghiệm thu theo yêu cầu owner | Chưa nghe/kiểm đầu ra screen reader; không ghi PASS, không yêu cầu kiểm tiếp trong task này. Evidence nhãn/semantics trước đó vẫn giữ nguyên; không tuyên bố WCAG compliance. |

Owner chấp nhận kết quả hai case zoom/bàn phím; phần UI thủ công được giao đã hoàn tất theo phạm vi điều chỉnh. B-AC04 được bổ sung B19 PASS theo xác nhận owner; B-AC05 giữ kết quả sửa focus/nhãn và keyboard PASS, screen reader được miễn trong lần nghiệm thu này. FIX-AC04 tương ứng đạt phần zoom/bàn phím với ngoại lệ screen reader đã nêu. B-AC06/cleanup UI cũ vẫn PARTIAL: chưa có xác nhận đóng tab error cũ/reset UI. Không đóng toàn gate QA-01B hoặc UC47 bằng kết quả thủ công này.

Lượt native trước `cf640cbf69464ee7a2554904ab9f987c` đã dừng, `passed: false` vì còn authenticated browser session tại thời điểm stop; `cleanup_failure: null`, schema/user/connections còn 0. Đây không phải run PASS và không phải nguồn chứng minh kết quả thủ công mới. Không dùng URL của run đã dừng để tiếp tục kiểm tra.

Kiểm tra cho cập nhật hồ sơ: kiểm phạm vi hai file và `git diff --check`; không chạy lại PHPUnit/Node/lint/build vì không thay đổi code. Dừng sau báo cáo, **chưa thực hiện UC47-QA-01C**.

## QA-01C — kiểm lỗi, request đang chờ và tổng hợp nghiệm thu

Ngày 2026-11-18, plan đã được owner duyệt. **QA-01C hoàn tất theo phạm vi đã thống nhất: phần tự động PASS; owner xác nhận C04–C08 đều đạt, cleanup UI đã xong.** Các case UI là xác nhận thủ công trên chat, không phải automation. Đây là task cuối trong kế hoạch UC47 ban đầu; owner yêu cầu cải thiện UI trước nghiệm thu cuối nên **chưa đóng UC47**. Chỉ cập nhật hai tài liệu UC47/checklist, không sửa code production/test/harness, không điều khiển máy tính, không khởi động browser harness. Giữ nguyên baseline nhánh `feature/tv3-uc47-admin-dashboard`, HEAD `5001a61` và các thay đổi đã nghiệm thu.

### Kết quả tự động

| Lệnh / môi trường | Kết quả thực chạy |
| --- | --- |
| Backend: `php artisan test --no-ansi --do-not-cache-result` | exit 0; 1487 passed, 102 skipped, 7298 assertions. SQLite in-memory theo phpunit.xml; các suite MySQL có guard skip, không tính skip là bằng chứng MySQL. |
| Root: `pwsh -NoProfile -File backend/tests/Support/RunUc47MySqlTests.ps1 -Provision` | exit 0; 66 passed, 185 assertions. MySQL Docker qua runner schema/user riêng; run `b7fa18d6d73c41a7a65657d11b4011a9`. |
| Frontend: `node --experimental-test-module-mocks --test tests/*.test.js` | exit 0; 184 passed, 0 failed/skipped/cancelled. Transport mocked adapter, không phải browser hoặc HTTP integration. |
| Frontend: `npm run lint` | exit 0; 0 errors, 3 cảnh báo Fast Refresh hiện hữu ở badge/button/sidebar. |
| Frontend: `npm run build` | exit 0; còn cảnh báo chunk lớn hơn 500 kB hiện hữu. |

MySQL đã chạy case phân quyền guest/role/account, summary/chart/decimal, readonly và ranh giới thời gian; case `test_unexpected_errors_are_safe_and_route_has_expected_middleware` trả 500 chuẩn với service fault giả lập, không lộ `PRIVATE_DASHBOARD_FAILURE`. Không ghi thành browser đã quan sát lỗi server 500. Node đã chạy các case network/timeout/HTTP/cancellation, malformed response, không replay và thông báo lỗi từ phiên cũ. Không ghi service test thành React lifecycle hoặc response đảo thứ tự đã kiểm thực tế.

Runner MySQL: `passed: true`, failure/cleanup_failure null; schema/user/connections còn **0/0/0**, connections trước drop 0. Dev fingerprint trước–sau khớp, protected source/config không drift. Đối chiếu baseline task sau các lệnh và trước sửa docs: 713 file không đổi. Runner này không khởi động PHP/Vite server, không có browser session mới cần logout/cleanup. Không migrate/seed/reset dev, không thay env và không log secrets.

### Ma trận C01–C09

| ID | Trạng thái hiện hành | Bằng chứng / việc còn lại |
| --- | --- | --- |
| C01 | PASS | Suite dashboard MySQL và hồi quy mặc định đạt; contract giữ global counts, PAID có paid_at, sáu trạng thái, 12 tháng theo giờ Việt Nam và readonly. |
| C02 | PASS ở tầng feature test | Case safe 500 đạt với fault service giả lập; không có evidence browser 500 mới. |
| C03 | PASS ở tầng service/API mapper | Node tests lỗi HTTP/network/timeout/response/cancellation đạt; không thay evidence UI. |
| C04 | PASS — owner xác nhận thủ công | Offline → Tải lại có lỗi phù hợp; Online → Thử lại phục hồi dashboard. |
| C05 | PASS — owner xác nhận thủ công | Slow 3G → Tải lại: có loading, nút Tải lại bị khóa, KPI cũ không hiển thị như kết quả mới; kết thúc request hoạt động lại. |
| C06 | PASS — owner xác nhận thủ công | Case rời trang khi request pending đạt theo báo cáo C04–C08 của owner; không có log Network mới gửi kèm. |
| C07 | PASS — owner xác nhận thủ công | Case logout khi request pending đạt theo báo cáo C04–C08 của owner; không có log Network mới gửi kèm. |
| C08 | PASS — owner xác nhận thủ công | Hai tab cùng phiên; logout tab thứ hai, Tải lại tab dashboard xử lý hết phiên theo xác nhận owner. |
| C09 | PASS trong phạm vi đã thống nhất | Cleanup runner/bảo toàn dữ liệu đạt; owner xác nhận cleanup UI đã xong, bổ sung và khép phần cleanup UI còn thiếu của QA-01B. |

### Checklist UI gửi owner

Dùng dashboard đang chạy và tài khoản Admin hiện có; không cần browser harness mới. Chỉ thao tác đọc dashboard, chuyển trang và session. Không tạo/sửa booking, invoice hoặc dữ liệu nghiệp vụ. Mở DevTools → Network; giữ DevTools mở khi kiểm. Mỗi case trở về Online/No throttling để chuẩn bị case kế tiếp.

1. **C04:** Khi dashboard đang hiển thị, chọn Offline, bấm Tải lại, ghi nhận thông báo lỗi. Trả Online/No throttling, bấm Thử lại; xác nhận KPI/biểu đồ hiện lại.
2. **C05:** Chọn Slow 3G rồi Tải lại; kiểm loading, nút bị khóa, không còn KPI cũ trong lúc tải. Chờ hoàn tất rồi kiểm nút hoạt động lại. Chỉ tính khi quan sát được trạng thái đang tải.
3. **C06:** Chọn Slow 3G, Tải lại; khi Network cho thấy GET `/api/admin/dashboard` còn pending, chuyển sang mục quản trị khác rồi quay lại. Trả No throttling; kiểm không có thông báo hủy request hoặc dữ liệu hiển thị sai.
4. **C07:** Chọn Slow 3G, Tải lại; khi request còn pending, đăng xuất. Trả No throttling và chờ; xác nhận dashboard không xuất hiện lại, rồi đăng nhập lại. Nếu request đã xong trước thao tác, báo chưa kiểm được race.
5. **C08:** Mở hai tab cùng browser/profile, cùng phiên đăng nhập. Logout ở tab thứ hai; quay tab dashboard bấm Tải lại. Xác nhận dữ liệu dashboard cũ biến mất và luồng đăng nhập/phiên hết hạn phù hợp xuất hiện. Đăng nhập lại khi kết thúc.
6. **C09 UI:** Đóng các tab QA/error cũ nếu còn; trả zoom 100%, Network Online/No throttling, tắt request blocking/Local Overrides nếu có dùng. Narrator đã xác nhận tắt trước đó; screen reader vẫn NOT VERIFIED và được loại khỏi phạm vi, không yêu cầu kiểm lại.

Owner báo C04/C05/C06/C07/C08: đạt, lỗi hoặc chưa kiểm; kèm nội dung lỗi nếu có, xác nhận C06/C07 thực sự pending và cleanup UI đã xong hay chưa. Không yêu cầu ảnh/video nếu không cần làm rõ lỗi. Các quan sát này không chứng minh đã cưỡng bức hai response trả ngược thứ tự.

Evidence ignored `.agent-workspace/tv3/uc47-qa-01c/`: baseline.json, backend.txt, node.txt, lint.txt, build.txt, mysql-runner.txt, result.json; result.json ghi trạng thái tại lần chạy tự động trước khi nhận phản hồi UI, xác nhận owner hiện hành được ghi tại đây. MySQL audit/test log tại `.agent-workspace/tv3/uc47-env-01/b7fa18d6d73c41a7a65657d11b4011a9/`. Owner đã báo “Tôi đã kiểm tra C04–C08 đều đạt, cleanup UI đã xong.” QA-01C hoàn tất theo phạm vi điều chỉnh, screen reader không kiểm và được miễn; không tuyên bố WCAG hoặc response đảo thứ tự đã kiểm. Không chạy lại QA trong lần ghi nhận này. Nghiệm thu cuối UC47 chờ Mini Plan cải thiện UI được duyệt, triển khai và kiểm lại phần ảnh hưởng; không commit/push.

## UC47-UI-01 — cải thiện giao diện Tổng quan

Ngày 2026-11-18, **ACCEPTED**: triển khai/kiểm tra tự động đạt; owner báo “Tôi đã kiểm tra UI01–UI06 đều đạt. Chấp nhận kết quả UC47-UI-01.” UI PASS theo xác nhận thủ công của owner, không phải browser automation. UC47 được nghiệm thu/đóng trong phạm vi thống nhất theo yêu cầu owner; không điều khiển máy tính, không mở browser/harness, không commit/push.

### Thay đổi trong phạm vi

- `frontend/src/pages/admin/AdminDashboardPage.jsx`: sáu icon Lucide cho KPI, card bo góc/nền màu nhẹ, hover nâng card/viền/shadow, nút tải lại và timestamp có icon. Card doanh thu có link tới `/admin/invoices`; các KPI chưa có trang đích không tạo link giả. Giữ nguyên request lifecycle, cancellation, auth gate và dữ liệu.
- `frontend/src/components/dashboard/AdminDashboardCharts.jsx`: sáu cột đứng theo trạng thái booking; đường thẳng nối 12 điểm doanh thu, lưới và điểm tháng đang chọn. Nút tháng dùng được bằng bàn phím, có aria-pressed và nhãn đầy đủ tháng/năm/số tiền; mặc định chọn tháng mới nhất, fallback tháng mới nhất khi kỳ thay đổi. Hai bảng số liệu native details/summary, có caption và row/column headers. Link hóa đơn mở danh sách chung, không giả định bộ lọc URL.
- Tạo `frontend/src/components/dashboard/AdminDashboardCharts.css`: style riêng prefix uc47, theme tokens hiện có; responsive một/hai cột, số dài wrap, hover cho card/cột/điểm/tháng/dòng bảng, focus-visible và chỉ transition/transform khi không yêu cầu reduced motion. Không dùng animation tự chạy, không thêm dependency.
- Cập nhật hồ sơ này và `docs/TV3/TV3_CHECKLIST.md`. Không sửa service/API/mapper/router/Auth/backend/env/schema hoặc shared sidebar. Tái sử dụng `dashboardBarWidths` BigInt hiện có; chỉ tỷ lệ hữu hạn đổi thành tọa độ SVG, tiền gốc giữ decimal string và formatVND hiện có. Không tạo helper mới nên không bổ sung test tính toán trùng logic.

### Kiểm tra tự động thực chạy

| Kiểm tra | Kết quả / giới hạn |
| --- | --- |
| `node --experimental-test-module-mocks --test tests/*.test.js` tại frontend | exit 0; 184 passed, 0 failed/skipped/cancelled. Test service/contract/tỷ lệ/decimal hiện có; không phải tương tác UI. |
| `npm run lint` tại frontend, source cuối | exit 0; 0 errors, 3 cảnh báo Fast Refresh hiện hữu ở badge/button/sidebar. |
| `npm run build` tại frontend, source cuối | exit 0; cảnh báo chunk >500 kB còn tồn tại; không coi là tối ưu bundle trong task này. |
| Render tĩnh React qua Vite SSR, source cuối | 4/4 fixture Populated/Zero/NoRevenue/Large đạt: sáu cột, 12 điểm/nút tháng, một tháng được chọn, hai bảng details, link thật, không NaN/Infinity, empty message đúng và tiền dài giữ chính xác. Đây là fixture render in-memory, không có DB/API/browser, không kiểm click/hover/layout thực. |
| Backend/MySQL | Không chạy lại: không thay backend/contract. Kết quả QA-01C ở trên là evidence lịch sử, không phải test mới UI-01. Không truy cập/sửa DB trong task UI-01. |

Không có renderer/browser tự động cho UI mới. Kiểm tra render đã phát hiện cảnh báo React về title nhiều children; đã sửa title thành một string và chạy lại 4 fixture sạch cảnh báo. Source ngoài phạm vi giữ baseline; git diff --check kiểm sau cập nhật. Evidence ignored `.agent-workspace/tv3/uc47-ui-01/`: baseline.json, node.txt, lint.txt, build.txt, render-check.mjs/render-check.txt và result.json.

### AC và checklist UI owner tự kiểm

| AC | Trạng thái hiện tại | Bước owner kiểm tại `/admin` |
| --- | --- | --- |
| UI01 — sáu KPI/icon và hover | PASS — owner xác nhận thủ công | Kiểm nhãn/số liệu, icon, hover card/viền/bóng nhẹ và tiền không bị cắt. |
| UI02 — cột trạng thái | PASS — owner xác nhận thủ công | Sáu cột có tên/số; mở Xem số liệu, đối chiếu sáu hàng và tổng số đơn với KPI. |
| UI03 — đường doanh thu | PASS — owner xác nhận thủ công | Chọn vài nút tháng bằng chuột và Tab/Enter/Space; tháng/tiền/điểm nhấn đổi đồng bộ, đối chiếu bảng 12 tháng. Tooltip điểm chỉ bổ sung, không là cách duy nhất xem tiền. |
| UI04 — điều hướng | PASS — owner xác nhận thủ công | Hai link Xem hóa đơn (card và chart) mở `/admin/invoices`; quay lại dashboard bình thường. Không thêm route hoặc filter giả. |
| UI05 — responsive/zoom/focus | PASS — owner xác nhận thủ công | Desktop/tablet/mobile và zoom 200%: không tràn trang/cắt số/chồng chữ; Tab focus rõ, Enter/Space mở/đóng bảng số liệu; hover không gây dịch bố cục. |
| UI06 — motion/states/session | PASS — owner xác nhận thủ công | Nếu máy bật Reduce motion, card không nâng chuyển động. Tải lại có loading/khóa nút; Offline → Thử lại phục hồi. Dữ liệu zero nếu có vẫn hiện thông báo rỗng và số 0. Smoke đăng xuất/đăng nhập, dashboard không hiện lại sau logout. |
| UI07 — phạm vi/hồ sơ | PASS — scope/whitespace, owner đã nghiệm thu | Không thay business/API/session; owner đã nghiệm thu UI-01 và UC47 trong phạm vi thống nhất. Screen reader tiếp tục được miễn, không đánh PASS. |

Owner đã xác nhận UI01–UI06 đều đạt và chấp nhận UC47-UI-01. Không có ảnh, viewport hoặc log tương tác mới gửi kèm; không suy rộng thành browser đã chạy lại toàn bộ fixture Zero/Large, giữ evidence SSR riêng. Không chạy lại QA khi ghi nhận nghiệm thu; không tự chuyển task.


## Tổng kết nghiệm thu UC47 — 2026-11-18

**ACCEPTED / CLOSED trong phạm vi TV3 đã thống nhất**, theo yêu cầu owner sau khi kiểm UI01–UI06 đều đạt. Không đồng nghĩa đã commit, push, merge hoặc triển khai production.

- Backend: GET `/api/admin/dashboard`, ADMIN ACTIVE; sáu KPI toàn hệ thống, sáu trạng thái booking và doanh thu 12 tháng theo Asia/Ho_Chi_Minh. Doanh thu từ PAID có paid_at; loại UNPAID/REFUNDED và cảnh báo PAID thiếu ngày; decimal chính xác, readonly.
- Frontend: API thật, loading/error/retry/empty, session/cancellation guards; icon KPI, cột trạng thái, đường doanh thu/chọn tháng, bảng số liệu và link hóa đơn thật. Responsive/zoom/keyboard/focus/hover và state/session smoke được owner xác nhận UI01–UI06 đạt.
- QA-01A: HTTP session/CSRF/roles/oracle và cleanup đã kiểm; QA-01B/FIX: hai finding focus/nhãn đã sửa và tái kiểm; QA-01C: C01–C03 tự động đạt, C04–C08 và cleanup UI owner xác nhận đạt. Phân biệt HTTP, feature tests, mocked transport, SSR và manual UI.
- Kết quả tự động gần nhất theo phần ảnh hưởng: QA-01C backend SQLite 1487 passed, 102 skipped, 7298 assertions; MySQL riêng 66 passed/185 assertions, cleanup schema/user/connections 0/0/0 và dev/source/env fingerprints khớp. UI-01 Node 184 passed, lint/build exit 0, SSR 4 fixture đạt. Đây là kết quả các lượt đã ghi ở trên, không phải vừa chạy lại khi đóng hồ sơ.
- Ngoại lệ/giới hạn: screen reader thực NOT VERIFIED và được owner miễn; không tuyên bố WCAG compliance hoặc đã cưỡng bức response đảo thứ tự. Không suy rộng ra tích hợp mutation Booking TV2, Docker outage/port collision hoặc performance/load testing. Link KPI chỉ tới route đang hoạt động; không triển khai UC44/UC48 hoặc trang quản lý TV1 còn thiếu.
- Ba Fast Refresh warnings và cảnh báo bundle >500 kB còn hiện hữu. Không còn finding bắt buộc mở trong phạm vi nghiệm thu đã thống nhất; các run lỗi/lease/session cũ giữ nguyên verdict lịch sử, không đổi thành PASS.
- Lần ghi nhận cuối chỉ sửa hai docs; kiểm Git scope/whitespace, không sửa source/env/database, không điều khiển máy tính hoặc chạy lại suite. Evidence ignored giữ nguyên dữ liệu tại thời điểm chạy; xác nhận owner và trạng thái đóng được ghi trong hồ sơ/checklist này. Chưa commit/push, dừng sau báo cáo.
