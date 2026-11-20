# UC47 — Nghiệm thu và bàn giao

Cập nhật hồ sơ: 2026-11-20; kết quả nghiệm thu gốc ngày2026-11-18. Baseline gốc `feature/tv3-uc47-admin-dashboard`, HEAD `5001a61` cộng working tree đã nghiệm thu; không dùng gói HEAD-only thay baseline.
**UC47 ACCEPTED / CLOSED phạm vi TV3 đã thống nhất.** ENV/FIX/CORE/CHART/QA-01A/QA-01B theo phạm vi điều chỉnh/QA-01C/UI-01 đều đã được owner nghiệm thu. Screen reader thực **NOT VERIFIED, được owner loại khỏi phạm vi**, không PASS/WCAG compliance. Không xác nhận commit/push/merge/deploy từ nghiệm thu.

## Contract dashboard hiện hành

GET `/api/admin/dashboard`, ADMIN ACTIVE, cookie/session Sanctum. Response `data` gồm summary (users/hosts/properties/rooms/bookings counts và revenue_total), excluded_paid_invoices_count, generated_at UTC, booking_status_counts đủ sáu trạng thái, revenue_by_month đủ 12 tháng, report_timezone Asia/Ho_Chi_Minh.

KPI toàn thời gian, mọi trạng thái tài nguyên hiện tồn tại. Doanh thu từ PAID có paid_at; loại UNPAID/REFUNDED/PAID thiếu ngày. Biểu đồ theo paid_at ở tháng hiện tại và 11 tháng trước, cutoff generated_at. Tổng 12 tháng không thay KPI toàn thời gian; refund hiện tại có thể đổi tổng lịch sử. Không mutation nghiệp vụ, không live payment hoặc workflow Booking TV2 trong QA này.

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

## Evidence ledger — kết quả lịch sử

| Checkpoint | Kết quả / nguồn |
| --- | --- |
| QA-01A | Unit49/105; fullSQLite1487passed/102skipped/7298assertions;4positive HTTP scenarios215status assertions/16oracle comparisons; hai expected negatives Prepare/Ready exit1 |
| QA-01B/FIX-01 | F-B01 focus trap/Escape/overlay và F-B02 nhãn tiếng Việt đã sửa/tái kiểm; Populated/Zero/NoRevenue/Large, desktop/mobile/keyboard evidence tách owner phần zoom. Node184/lint/buildexit0 |
| NATIVE-01 | Owner xác nhận zoom200%/keyboard đạt; screen reader miễn. Run `cf640cbf69464ee7a2554904ab9f987c` passed=false do còn session khi stop, cleanup0/0/0/null; không phải manual PASS mới |
| QA-01C | SQLite1487passed/102skipped/7298assertions; MySQL66passed/185assertions run `b7fa18d6d73c41a7a65657d11b4011a9`; Node184/lint/buildexit0; C04–C08 và cleanup UI theo owner |
| UI-01 | Node184passed/0failed/skipped; lint0errors/3FastRefresh warnings; buildexit0/chunk>500kB; SSR4/4fixtures. UI01–UI06 PASS theo owner, không có ảnh/Network/viewport mới gửi kèm |

Runtime QA: MySQL Docker8.4.13/loopback3307, PHP XAMPP8.2.12; schema `stayhub_uc47_test_<guid>`, user `uc47_<run-prefix>` mới, database session/cookie/CSRF/CORS thật, migrate schema rỗng không seed dev. QA-01A chỉ HTTP cookie jar/CLI, readiness/source transform không chứng minh React render.

| Scenario / run và verdict lịch sử |
| --- |
| Populated — ed372fa1d5094409b043cf83aa92fe13 — exit 0; 65 status assertions, 4 oracle comparisons |
| Zero — 7ea30f09d70f4f7f9b10ab31e23e35bb — exit 0; 20 status assertions, 4 oracle comparisons |
| NoRevenue — 5be0f7bb622b4a828d1bc384ad07d30d — exit 0; 65 status assertions, 4 oracle comparisons |
| Large — 50bc4661875c470f9b653354d0389ec1 — exit 0; 65 status assertions, 4 oracle comparisons |
| Fail after Prepare — aae45561e7434fe9a76902d436b9468c — exit 1, đúng UC47_EXPECTED_FAILURE_AFTER_PREPARE |
| Fail after Ready — d4cc1f7b39124d46bab68a1717881fc5 — exit 1, đúng UC47_EXPECTED_FAILURE_AFTER_READY |
| Large — fc8e1ee33fad43899a2e4eaf1b0d74b2 — KPI 8/3/4/4/10; 19.999.999.999,98 VND; hai tháng cuối 9.999.999.999,99, sáu status đúng. Desktop/tablet/mobile 1440×900, 768×1024, 375×812 ảnh full-page và DOM không tràn ngang; tiền dài wrap đầy đủ tại tablet. |
| Zero — e6e53f743a364c5fb5992223d47728ac — KPI 2/0/0/0/0; tiền 0,00; sáu status/12 tháng 0; empty Booking và Revenue đúng. Login UI/logout/guest redirect đạt. |
| NoRevenue — e629c2c506a0413490fdf08828988634 — KPI 8/3/4/4/10; booking chart có dữ liệu 1/1/1/5/1/1; revenue/12 tháng 0; chỉ revenue empty state, không nhầm Booking rỗng. Login UI/logout đạt. |
| Populated — 9ca3ec66c9e741eeac2d58c050c7a3fd — Smoke KPI/charts 300,75, 100,25/200,50 hai tháng cuối; ba viewport không tràn ngang; Enter reload, logout và guest /admin redirect đạt. |
| Root: `pwsh -NoProfile -File backend/tests/Support/RunUc47MySqlTests.ps1 -Provision` — exit 0; 66 passed, 185 assertions. MySQL Docker qua runner schema/user riêng; run `b7fa18d6d73c41a7a65657d11b4011a9`. |

Run IDs và verdict gốc được giữ; negative/intermediate không đổi thành PASS.
Positive runs đã ghi business9tables/dev/source/env hashes khớp,authenticated sessions0/processes exited/ports released/schema-user-connections0/0/0. Negative controls cleanup đạt không làm test thành positive. Lease-expiry/hardkill/Dockeroutage/portcollision/performance/load và mọi identity fallback chưa được chứng minh.


### QA-01B — cases lịch sử trước cập nhật owner

| Case | Kết quả gốc / giới hạn |
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

### QA-01B — bổ sung hiện hành từ owner

| Case | Kết quả | Nguồn / giới hạn |
| --- | --- | --- |
| B19 — zoom 200% | PASS — owner xác nhận thủ công | Owner báo đã kiểm tra và thấy ổn. Không có ảnh, phiên bản browser, viewport hoặc scenario cụ thể được gửi kèm; không suy rộng thành kiểm lại mọi fixture. |
| B16/B17 — bàn phím | PASS — owner xác nhận thủ công, bổ sung evidence FIX trước | Owner báo kiểm tra bàn phím ổn. Không ghi thành một lượt automation hoặc tái chạy toàn bộ ma trận focus. |
| B18 — screen reader thực | NOT VERIFIED — loại khỏi phạm vi nghiệm thu theo yêu cầu owner | Chưa nghe/kiểm đầu ra screen reader; không ghi PASS, không yêu cầu kiểm tiếp trong task này. Evidence nhãn/semantics trước đó vẫn giữ nguyên; không tuyên bố WCAG compliance. |

Trạng thái hiện hành áp dụng bổ sung FIX/NATIVE/QA-01C bên dưới; PARTIAL/NOT VERIFIED gốc không đổi thành runtime PASS.

## AC và UI hiện hành

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

| AC | Trạng thái hiện tại | Bước owner kiểm tại `/admin` |
| --- | --- | --- |
| UI01 — sáu KPI/icon và hover | PASS — owner xác nhận thủ công | Kiểm nhãn/số liệu, icon, hover card/viền/bóng nhẹ và tiền không bị cắt. |
| UI02 — cột trạng thái | PASS — owner xác nhận thủ công | Sáu cột có tên/số; mở Xem số liệu, đối chiếu sáu hàng và tổng số đơn với KPI. |
| UI03 — đường doanh thu | PASS — owner xác nhận thủ công | Chọn vài nút tháng bằng chuột và Tab/Enter/Space; tháng/tiền/điểm nhấn đổi đồng bộ, đối chiếu bảng 12 tháng. Tooltip điểm chỉ bổ sung, không là cách duy nhất xem tiền. |
| UI04 — điều hướng | PASS — owner xác nhận thủ công | Hai link Xem hóa đơn (card và chart) mở `/admin/invoices`; quay lại dashboard bình thường. Không thêm route hoặc filter giả. |
| UI05 — responsive/zoom/focus | PASS — owner xác nhận thủ công | Desktop/tablet/mobile và zoom 200%: không tràn trang/cắt số/chồng chữ; Tab focus rõ, Enter/Space mở/đóng bảng số liệu; hover không gây dịch bố cục. |
| UI06 — motion/states/session | PASS — owner xác nhận thủ công | Nếu máy bật Reduce motion, card không nâng chuyển động. Tải lại có loading/khóa nút; Offline → Thử lại phục hồi. Dữ liệu zero nếu có vẫn hiện thông báo rỗng và số 0. Smoke đăng xuất/đăng nhập, dashboard không hiện lại sau logout. |
| UI07 — phạm vi/hồ sơ | PASS — scope/whitespace, owner đã nghiệm thu | Không thay business/API/session; owner đã nghiệm thu UI-01 và UC47 trong phạm vi thống nhất. Screen reader tiếp tục được miễn, không đánh PASS. |

QA-01B B12/B14/B16/B17 đã tái kiểm sau FIX; B18 semantics/nhãn PASS nhưng screen reader NOT VERIFIED. B19zoom200%/bàn phím PASS theo owner; QA-01C owner xác nhận C04–C08/cleanup UI, khép cleanup PARTIAL cũ. Run PARTIAL/FAIL gốc không bị sửa verdict. Không chứng minh đã cưỡng bức hai response đảo thứ tự hoặc browser500 từ feature fault test.

## UI và contract cần bảo toàn

- AdminDashboardPage: sáu KPI/icon/card/hover/timestamp; hai link Invoice thực tới `/admin/invoices`, không giả link/filter cho UC chưa triển khai.
- AdminDashboardCharts và CSS prefixuc47: sáu cột,12điểm/tháng, keyboard/aria-pressed, hai bảng native details/headers; responsive/focus-visible/reducedmotion. Tỷ lệ từ BigInt hiện có, tiền gốc decimal string/formatVND, không float tiền hoặc thêm dependency.
- HTTP/feature500 là fault giả lập; Node là mocked transport; SSR là in-memory render, không DB/API/browser. Manual owner không suy thành mọi fixture Zero/Large đã browser-recheck.
- Ba warnings FastRefresh và bundle>500kB lịch sử còn ghi nhận; không còn finding bắt buộc mở trong phạm vi đã thống nhất. Không mở rộng sang UC44/UC48 hay mutation Booking production TV2.

### Run provenance bổ sung — lịch sử

Ngày 2026-11-18, **PARTIAL; không phải TECHNICAL PASS**. Run `685296bf324342bf900c24bee3fff55e`, Populated, React/Vite và Laravel thật trên loopback, database session ở schema MySQL QA riêng. Dùng ChatGPT in-app browser trong context của task, ban đầu không có tab; không điều khiển cửa sổ Brave của người dùng. Login/logout thực hiện qua UI, không inject session/token.

Evidence local ignored `.agent-workspace/tv3/uc47-qa-01b/`: `browser-result.json`, baseline hashes, desktop/tablet/mobile JPEG, DOM snapshots theo actor và keyboard/focus JSON. Harness audit ở `.agent-workspace/tv3/uc47-qa-01a/685296bf324342bf900c24bee3fff55e/` gồm result, business/session snapshots, fingerprint và cleanup. Đề xuất tiếp theo: Mini Plan xử lý findings và hoàn tất phần QA-01B còn thiếu, chờ scope/approval riêng; chưa chuyển QA-01C.

Run Large thử trước `c7b5dae9b1724b8cb4784f64b76ef4a9` hết lease trong gián đoạn: result passed false, failure lease expired, cleanup_failure null; process/port/schema/user/connections đã cleanup. Giữ evidence lịch sử, không cộng vào positive matrix hoặc nói audit sau browser đạt cho run này. Sau đó bổ sung guard loại BODY/HTML và chạy lại trên source cuối như bảng trên.

## Nguồn evidence và tái kiểm khi được giao

Local ignored `.agent-workspace/tv3/uc47-qa-01a/` (final-runs/manifest/fixture/oracle/HTTP/session/hash/cleanup), `uc47-qa-01b/`, `uc47-qa-01b-fix-01/` (keyboard/DOM/JPEG/zoom-attempt), `uc47-qa-01c/` (backend/Node/lint/build/result), `uc47-ui-01/` (render-check/result). Raw MySQL QA-01C ở `uc47-env-01/b7fa18d6d73c41a7a65657d11b4011a9/` dưới cùng `.agent-workspace/tv3/`. Artifacts không đi theo Git; acceptance snapshot cũ không thay xác nhận cuối trên chat.

## Chạy lại harness khi được giao

Chỉ chạy theo task mới được duyệt, PowerShell 7 tại root, dependencies có sẵn/MySQL Docker thuộc checkout healthy:

```powershell
pwsh -NoProfile -File backend/tests/Support/RunUc47Browser.ps1 -Provision -Scenario Populated
# Các scenario: Populated, Zero, NoRevenue, Large.
# Negative controls phải exit 1 đúng marker và cleanup đạt:
pwsh -NoProfile -File backend/tests/Support/RunUc47Browser.ps1 -Provision -InjectFailureAfter Prepare
pwsh -NoProfile -File backend/tests/Support/RunUc47Browser.ps1 -Provision -InjectFailureAfter Ready
```

Mặc định probe xong tự cleanup. `-KeepAlive -MaxMinutes 30` cho task browser được duyệt; password tạm qua process environment `STAYHUB_UC47_BROWSER_PASSWORD` ít nhất 24 ký tự, không ghi env/file/log. Đọc `ready.json` của chính run để lấy frontend URL và `stop_file`; dùng profile QA riêng, logout trước khi tạo stop file rồi chờ audit/cleanup. KeepAlive/stop đã kiểm, lease-expiry chưa kiểm. Không dùng URL/ID của run đã cleanup.

Không sửa source/env/docs lúc runner còn giữ fingerprint; không chạy full suite với browser overrides. Nếu Docker/ports/cache sai thì dừng/báo, không tự start/recreate container/xóa cache/volume. Kiểm result, session/business/dev/source hashes, process/port và schema/user/connections cleanup của đúng run. Không ghi cookie/token/password.

QA-01C/UI tái kiểm cần plan riêng theo C01–C09/UI01–UI07, ghi đúng nguồn quan sát. Lần dọn hồ sơ này chỉ đọc/gom tài liệu, không chạy QA/CI/remote/DB. Trạng thái tổng tại [checklist](TV3_CHECKLIST.md).
