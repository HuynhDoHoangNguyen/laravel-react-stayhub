# Ma trận nghiệm thu 10 UC TV3

Cập nhật: 2026-11-20 (Asia/Bangkok). **TV3-AC-CLOSE-01 ACCEPTED trên chat; 10 UC CLOSED phạm vi TV3**. Cộng UC36/45/47 đã đóng trước: **13/18 UC**; còn UC17/39/44/48/61, FAQ Management và Chat History. Không phải end-to-end toàn hệ thống.

## 1. Phạm vi và cách đọc evidence

- Baseline closure: `feature/tv3-uc48-system-statistics`, HEAD `3931bf6d41c2f23e26ce73ba73a28bd3f9f008c5` cộng accepted working tree. Trạng thái tổng ở [checklist](TV3_CHECKLIST.md); refactor ở [baseline](BASELINE_HANDOVER.md).
- API/MySQL/frontend là kết quả lịch sử; UI01–16/CUI01–18/RUI01–20/RCUI01–20 **PASS theo owner**, không phải agent browser observation.
- Audit DB delta/request journal/session/process/ports/schema/user/dev/source của đúng lượt manual **PARTIAL/NOT VERIFIED độc lập**, owner đã chấp nhận. Không dựng lại evidence hoặc yêu cầu owner gửi Run ID lần nữa để đóng hồ sơ này.
- MySQL Docker8.4.13/PHP XAMPP8.2.12/loopback3307 là runtime đã ghi lúc chạy; SQLite, Node mocked transport, HTTP cookie-jar, Vite transform/SSR và manual browser không thay thế nhau.
- UI harness dùng fixture DB QA thật; Review create có synthetic parent/selector/callback, chưa chứng minh CustomerBookingDetailPage production. UNKNOWN là synthetic500 sau production201 đã commit, không phải lỗi DB/rollback hay mạng tự nhiên.
- Không sửa verdict FAIL/negative/skipped hoặc timestamp cũ; artifact acceptance PENDING phản ánh thời điểm trước owner review. Không cộng counts của suites/reruns trùng nhau.

## 2. Ma trận đóng 10 UC

| UC / nhóm | Trạng thái phạm vi TV3 | AC / evidence đã nghiệm thu | Giới hạn giữ nguyên | Integration chưa đóng |
| --- | --- | --- | --- | --- |
| UC15 — Xem hóa đơn cá nhân / A | **CLOSED TV3** — TV3-AC-CLOSE-01 | IR01–IR06; INV-READ-QA/UI-01A/01B; evidence ledger | UI01–UI16 PASS theo owner; audit manual runtime chưa đối chiếu độc lập | **T01 BLOCKED TV2** |
| UC35 — Chi tiết Invoice Host / A | **CLOSED TV3** — TV3-AC-CLOSE-01 | IR01/IR02/IR04–IR06; response/items/owner/detail UI; evidence ledger | API/HTTP harness có artifacts; UI detail PASS theo owner, giữ giới hạn evidence ledger | **T01 BLOCKED TV2** |
| UC37 — Quản lý Invoice / A | **CLOSED TV3** — TV3-AC-CLOSE-01 | IR01–IR06; filter/search/page/reset và UI; evidence ledger | Read DB bất biến; UI PASS theo owner, audit manual runtime chưa đối chiếu độc lập | **T01 BLOCKED TV2** |
| UC34 — Tạo Invoice từ Booking / B | **CLOSED TV3** — TV3-AC-CLOSE-01 | IC01–IC07; QA/RACE/UI-01A/UI-01B; evidence ledger | Booking fixture hợp lệ; CUI01–18 PASS theo owner, audit manual runtime còn gap | **T02 BLOCKED TV2** |
| UC56 — Tạo InvoiceItem / B | **CLOSED TV3** — TV3-AC-CLOSE-01 | IC02/IC03/IC06/IC07 qua workflow UC34; evidence ledger | Snapshot/atomic items/rollback/commit/race có MySQL evidence; UI owner-reported | **T02 BLOCKED TV2** |
| UC57 — Tính subtotal / B | **CLOSED TV3** — TV3-AC-CLOSE-01 | IC02/IC04/IC06/IC07 qua workflow UC34; evidence ledger | Literal oracle/decimal/rounding/range/create/read-back; UI theo owner | **T02 BLOCKED TV2** |
| UC58 — Tính tổng thanh toán / B | **CLOSED TV3** — TV3-AC-CLOSE-01 | IC02/IC04/IC06/IC07 qua workflow UC34; evidence ledger | API fee/discount0.00; nonzero chỉ calculator coverage, không thêm input UI; audit gap giữ nguyên | **T02 BLOCKED TV2** |
| UC38 — Host xem Review / C | **CLOSED TV3** — TV3-AC-CLOSE-01 | RV04-read/RV05/RV07; REV-READ-QA/UI-01A/01B; evidence ledger | Summary/filter/scope có API/MySQL; RUI01–20 PASS theo owner, audit manual PARTIAL | **T03 BLOCKED TV2** |
| UC16 — Tạo Review / C | **CLOSED TV3** — TV3-AC-CLOSE-01 | RV01–RV04/RV06/RV07; QA/RACE/UI-01A/UI-01B; evidence ledger | RCUI01–20 PASS theo owner; synthetic QA parent, không chứng minh CustomerBookingDetailPage | **T03 BLOCKED TV2** |
| UC60 — Kiểm tra điều kiện Review / C | **CLOSED TV3** — TV3-AC-CLOSE-01 | RV01–RV04/RV06 qua workflow UC16; evidence ledger | Server owner/COMPLETED/raw validation/duplicate/race; UI owner-reported, audit manual PARTIAL | **T03 BLOCKED TV2** |

UC56/57/58 qua UC34; UC60 qua UC16. Không thêm API/trang riêng chỉ để đủ số UC.

## 3. Cổng chung bắt buộc

| AC | Kết quả cần quan sát | Bằng chứng cần bổ sung | Trạng thái |
| --- | --- | --- | --- |
| G01 | Chốt source, actor, endpoint, engine, dữ liệu và oracle của lượt kiểm; không có mock tạo mutation trên dữ liệu thật | Manifest baseline; fixture/oracle do run sở hữu; ghi rõ synthetic context nếu có | PASS đối chiếu phạm vi TV3: baseline/actor/endpoint/engine/fixture/oracle từ API/harness artifacts; source hiện tại khớp các production/env hashes đã ghi; evidence ledger |
| G02 | Auth/session/CSRF, role, account, ownership, thứ tự lỗi và JSON theo contract hiện hành; lỗi an toàn | Feature/HTTP cases; session thật cho UI; đối chiếu controller/validator/consumer, không áp status mới hàng loạt lên legacy | PASS API/MySQL và HTTP harness theo contract; UI PASS theo owner; synthetic parent và manual runtime gap giữ nguyên, evidence ledger |
| G03 | MySQL QA cô lập; dev/env/source không bị sửa ngoài scope; fixture/session/process dọn sạch | Target guard trước mutation; fingerprints và cleanup cuối run. Không reset/seed lại dev hoặc dùng credentials dev cho QA | PASS các run API/harness có artifacts; PARTIAL/NOT VERIFIED độc lập đối với DB/session/process/cleanup đúng lượt UI manual; owner đã chấp nhận giới hạn, không đổi thành PASS độc lập |
| G04 | Backend và frontend cần thiết đều đủ evidence; owner chấp nhận đúng scope | Kết quả test thực tế, Node/lint/build theo task; checklist UI thủ công, findings và xác nhận owner. Refactor acceptance tách khỏi UC closure | ACCEPTED phạm vi TV3: các task đã nghiệm thu trên chat, UI owner-reported và frontend checks có logs; closure có giới hạn evidence ledger; T01–T03 chưa đóng |

Backend giữ Controller → Validator/typed DTO → Service/QueryService → Repository/Model → Mapper/Response DTO. Frontend giữ Page/Component → Domain Service → Domain API → shared Axios; mapper thuần. Không biến nghiệm thu thành đợt refactor mới; nếu phát hiện lỗi cần task FIX với phạm vi được duyệt riêng.

## 4. Nhóm A — đọc Invoice (UC15/35/37)

Điểm vào: `GET /api/customer/invoices`, `GET /api/customer/invoices/{invoice}`, `GET /api/host/invoices`, `GET /api/host/invoices/{invoice}`; UI `/customer/invoices`, `/customer/invoices/:invoiceId`, `/host/invoices`, `/host/invoices/:invoiceId`.

| AC | Tiêu chí quan sát được | Phương thức dự kiến | Trạng thái |
| --- | --- | --- | --- |
| IR01 | Guest/role sai/account không hợp lệ bị chặn; Host/Customer chỉ thấy Invoice đúng scope; missing và ID không hợp lệ giữ contract, không rò dữ liệu | Feature/HTTP với hai Host, hai Customer; ID hợp lệ/sai/không tồn tại | PASS API/MySQL và HTTP harness read; quyền/account/owner theo contract; UI theo owner và giới hạn evidence ledger |
| IR02 | Header, items, tiền decimal string, ngày nullable và trường omitted đúng response hiện hành; list không giả thành detail | Oracle DB độc lập → response → mapper/UI; list/detail đối chiếu cùng Invoice | PASS API/MySQL projection/items/null/decimal; detail UI PASS theo owner UI01–UI16, không có audit manual độc lập |
| IR03 | Filter/search/order/pagination, input lỗi và kết hợp filter đúng legacy; page/meta/links khớp; reset không giữ trang sai | Fixture nhiều hơn một trang, dữ liệu tên/mã trùng một phần, empty và truy vấn không hợp lệ | PASS API/MySQL; reset/page UI PASS theo owner UI-01B, artifact runtime chưa đối chiếu độc lập |
| IR04 | Đọc không thay đổi dữ liệu nghiệp vụ; không lọt Invoice owner khác khi đổi filter hoặc ID | Fingerprint business tables trước/sau và negative ownership cases | PASS API/MySQL INV-READ-QA-01 |
| IR05 | Loading/empty/error/retry, 401/403/404/422 theo endpoint; request cũ không ghi đè context mới sau đổi filter/trang/logout | Node service/mapper và owner thao tác UI với request chậm/lỗi; ghi giới hạn nếu chưa tái hiện response ngược thứ tự | Node PASS; UI checklist PASS theo owner UI-01B, không có Network trace/runtime artifact độc lập |
| IR06 | UI tiếng Việt/VND; chi tiết items và số tiền dài đọc được trên mobile/tablet/desktop; keyboard/focus phù hợp | Owner checklist ba viewport và bàn phím, so oracle invoice | PASS theo owner UI01–UI16; không phải agent browser observation, artifact runtime chưa đối chiếu độc lập |

Không cần TV2 để bắt đầu nhóm A bằng fixture DB QA. T01 vẫn mở sau khi nhóm A được chấp nhận trong phạm vi TV3.

## 5. Nhóm B — tạo và tính Invoice (UC34/56/57/58)

Điểm vào: `POST /api/host/bookings/{booking}/invoice`; UI đã có `/host/bookings/:bookingId/create-invoice` và `/host/invoices/create`. Dùng ID fixture DB QA thuộc Host đăng nhập, không dùng ID mock để gọi mutation thật.

| AC | Tiêu chí quan sát được | Phương thức dự kiến | Trạng thái |
| --- | --- | --- | --- |
| IC01 | Chỉ Host sở hữu Property của Booking COMPLETED được tạo; sai role/owner/status/missing/snapshot lỗi bị từ chối theo contract và không lưu | HTTP + service/DB cases, giữ thứ tự lỗi hiện hành | PASS API/MySQL INV-CREATE-QA-01 trong transaction ENV |
| IC02 | Tiền phòng từ số đêm/giá snapshot Booking; dịch vụ từ BookingService snapshot, không lấy giá catalog mới. Kiểm tính nhất quán `quantity × unit_price` với `amount` đã lưu | Sửa giá catalog trong fixture QA sau khi tạo snapshots; oracle độc lập và negative snapshot lệch; đối chiếu persisted items/header | PASS backend/MySQL INV-CREATE-QA-01 |
| IC03 | Invoice + ROOM/SERVICE items lưu atomically, đúng số lượng/nội dung; lỗi giữa chừng và outer rollback không để header/items/orphan hoặc success log sai | Fault injection tại test boundary; actual commit/rollback và quan sát DB/log trên MySQL | PASS backend/MySQL: functional QA và LIFE01–LIFE05 của INV-CREATE-RACE-01, evidence ledger |
| IC04 | `room_total = nights × price`, `service_total = SUM(amount)`, `subtotal = room_total + service_total`, `total = subtotal - discount + extra_fee`; decimal/rounding/range đúng, không float hoặc cộng phí hai lần | Unit oracle độc lập cho calculator và MySQL create/read. Create API hiện dùng discount/extra_fee `0.00`; nonzero là calculator coverage, không tuyên bố API hỗ trợ nhập phí | PASS calculator/API backend INV-CREATE-QA-01; UI thuộc IC07 |
| IC05 | Một Invoice/Booking, duplicate trả conflict theo contract; constraint unique có hiệu lực; lỗi DB khác không bị đổi thành duplicate | HTTP tạo lặp, kiểm count và unique trên schema QA; test phân loại ngoại lệ | PASS sequential/real constraints/error classification tại QA-01; concurrent 201/409 tại RACE01/02, evidence ledger |
| IC06 | Hai transaction/session đồng thời không tạo đôi; success chỉ sau outer commit; rollback không lưu hoặc log thành công | Harness MySQL hai worker có barrier/timeout, kiểm kết quả từng worker và số bản ghi; SQLite không thay thế race | PASS backend/MySQL RACE01–RACE05 và LIFE02/03, evidence ledger; success log đợi outer commit |
| IC07 | UI gửi một lần, đúng ID/actor; hiển thị thành công và detail đọc lại khớp DB; field/domain errors rõ; kết quả mutation chưa rõ không tự replay | Node/owner UI trên fixture QA, double submit, mạng chậm/lỗi và reload/read recovery | PASS frontend tự động/CUI01–18 theo owner tại01B; giới hạn runtime audit evidence ledger |

UC56/57/58 chỉ đóng cùng evidence workflow UC34 tương ứng. Không thêm input phí/giảm giá, endpoint hoặc sửa Booking pricing để đáp ứng ma trận.

## 6. Nhóm C — Review (UC38 và UC16/60)

Điểm vào TV3: `GET /api/host/reviews`, `POST /api/customer/bookings/{booking}/review`, `GET /api/customer/reviews`; đọc public `GET /api/public/properties/{property}/reviews` làm regression liên quan. UI `/host/reviews`, `/customer/reviews`, modal tại CustomerBookingDetail.

| AC | Tiêu chí quan sát được | Phương thức dự kiến | Trạng thái |
| --- | --- | --- | --- |
| RV01 | Chỉ Customer của Booking COMPLETED tạo review; rating nguyên1–5, comment đúng giới hạn; property lấy từ Booking/Room, không tin client | HTTP/domain + literal persisted oracle MySQL | PASS API REV-CREATE-QA-01 evidence ledger; không suy thành UI/Booking production |
| RV02 | Guest/role/owner/status sai, duplicate hoặc input lỗi không ghi dữ liệu; Host không sửa Review Customer qua luồng tạo này | Negative API + source/review delta audit | PASS API tạo tại QA-01; không tạo/kiểm endpoint edit/delete mới |
| RV03 | Unique một review/Booking, duplicate khi đồng thời; DB lỗi khác không bị nhận nhầm duplicate | MySQL constraint và hai session/worker | PASS API/MySQL: sequential409/real1062 classification và1048 safe500 ở QA-01; hai session/lock201–409/unique/timeout tại RACE-01 RCR05–RCR10, evidence ledger |
| RV04 | Insert lỗi/outer rollback không giữ review hoặc committed success log; projection/null/no-query đúng audience | Read mapper + fault/commit tests đúng engine | PASS read projection/mapper evidence ledger và write fault/outer rollback/durable commit/afterCommit RCR01–RCR04 evidence ledger; không suy sang manual runtime |
| RV05 | Host chỉ thấy review Property mình; filter/page đúng; summary và rating breakdown đúng toàn tập theo contract, không tính chỉ trang hiện tại | MySQL fixture hai Host/nhiều trang/zero; oracle độc lập từng scope/filter và UI UC38 | PASS API/MySQL evidence ledger; UI PASS theo owner tại01B, giới hạn runtime evidence ledger |
| RV06 | Modal/rating/comment/field errors/loading/success/duplicate/recovery đúng; không submit từ mock Booking; refresh đọc lại review đã lưu | Node và owner UI với entry hợp lệ. Nếu dùng synthetic QA entry phải duyệt riêng, ghi rõ không phải Booking production | PASS frontend/RCUI01–RCUI20 theo owner; REV-CREATE-UI-01B ACCEPTED trên chat; synthetic QA entry, audit manual runtime PARTIAL evidence ledger; T03 BLOCKED TV2 |
| RV07 | UI đọc/summary/cards đúng dữ liệu, responsive/keyboard/states và actor isolation | Owner UI + Node, tách nguồn evidence | PASS API/read projection và UI owner: RUI01–20 evidence ledger, RCUI01–20/read-back evidence ledger; giữ audit manual gap, không phải agent browser observation |

UC38 đã nghiệm thu độc lập bằng fixture. UC16/60 đã nghiệm thu API/domain và UI qua synthetic QA entry được duyệt; RV06 PASS theo owner với audit gap, T03 vẫn BLOCKED TV2.

## 7. Evidence ledger lịch sử

| Task / ngày | Run MySQL positive | Kết quả lịch sử | AC được chứng minh |
| --- | --- | --- | --- |
| INV-READ-QA-01 /2026-11-19 | `853ded1388824d459fdf64dea408eb86` |144passed/918assertions/exit0 | IR01–IR04, guarded target/no-write;65read cases+ENV |
| INV-CREATE-QA-01 /2026-11-19 | `46e0266b6d8a45f7a8c4d43bcb83d2a2` |213passed/1399assertions/exit0 | IC01/02/04, sequential/constraint/error classification/savepoint; không dùng riêng để chứng minh durable commit |
| INV-CREATE-RACE-01 /2026-11-19 | `46a37608f9c342fa9b8e0484c8a30d45` |83passed/533assertions/exit0 | LIFE01–05/RACE01–05:IC03/IC05/IC06 observer/commit/rollback/lock/log |
| REV-READ-QA-01 /2026-11-19 | `62abd51cdfb14b96bf75e43632d78c0c` |139passed/1759assertions/exit0 | RV04-read/RV05;60Review read cases+ENV |
| REV-CREATE-QA-01 /2026-11-20 | `40bb385af9c34212b7f5559fb9db42df` |138passed/889assertions/exit0 | RC01–RC08/RV01/02 và phầnRV03/04;59Review cases+ENV |
| REV-CREATE-RACE-01 /2026-11-20 | `aae82d687da7448284716dd6f60015d8` |85passed/516assertions/exit0 | RCR01–RCR10/RV03/04:durable lifecycle/unique/lock/timeout/afterCommit |

Raw6runs `.agent-workspace/tv3/ac-env-01/<run-id>/`; task summaries ở `.agent-workspace/tv3/<task-lowercase>/verification-summary.json`. CLOSE-01 đọc manifest/result/cleanup/protected-before-after/development-before-after/tests logs: cả6positive PASS, cùng HEAD, guarded source/dev bất biến trong run, cleanup schema/user/connections0/0/0. Tại CLOSE-01, so 380 production/config/routes/schema/frontend/env/architecture paths mỗi run với filesystem lúc đó: 0 mismatch. Đây là kiểm hash/metadata tại CLOSE-01, không tái chạy runtime hoặc khẳng định DB hiện tại đã được kiểm lần nữa.

Acceptance PENDING trong artifact summary cũ là snapshot lúc báo cáo trước owner review; acceptance cuối theo chat và hồ sơ hiện hành này. Không sửa timestamp/result/log/evidence lịch sử hoặc đổi skippedSQLite thành MySQL PASS. Không cộng tổng test nhiều suite/rerun trùng nhau thành một suite mới.

ENV-01 đã ACCEPTED: positive `c4f3c7b182794e6ead037575bc56594f` 79/216 exit0; fail-after-write `92fc8095bee64bc69da4129c4d37e163` 1failed/78passed/216 exit1; after-schema `d5afe8dc8e0344f586d299cf41c2feb7` và collision `f2722582610e40fea0cb52801f605691` expected exit1. Bốn run cleanup0/0/0,767 protected hashes khớp. Account collision chỉ unit; hard kill/Docker outage/KILL fallback chưa kiểm. SQLite ENV regression1984passed/128skipped/8333assertions không phải QA10UC.

Các task API/RACE/UI-01A/01B và SCOPE/ENV/CLOSE đã được owner nghiệm thu trên chat. CLOSE01–CLOSE05 PASS đối chiếu tài liệu; checkpoint CLOSE không chạy QA mới,801paths ngoài2docs giữ nguyên. Closure artifacts: `.agent-workspace/tv3/ac-close-01/`.

| UI01B | Evidence frontend local | Kết quả manual hiện hành |
| --- | --- | --- |
| INV-READ-UI-01B | `.agent-workspace/tv3/inv-read-ui-01b/` | UI01–UI16 PASS theo owner; cleanup/runner success theo owner, manual audit chưa đối chiếu |
| INV-CREATE-UI-01B | `.agent-workspace/tv3/inv-create-ui-01b/` | CUI01–CUI18 PASS theo owner; owner yêu cầu ghi theo xác nhận, giữ audit gap |
| REV-READ-UI-01B | `.agent-workspace/tv3/rev-read-ui-01b/` | RUI01–RUI20 PASS theo owner; audit manual PARTIAL |
| REV-CREATE-UI-01B | `.agent-workspace/tv3/rev-create-ui-01b/` | RCUI01–RCUI20 PASS theo owner; Node203/0fail/0skip,lint0errors/3existing warnings,buildexit0/chunk>500kB; manual audit PARTIAL |

Cả bốn lượt UI-01B đã chạy frontend trước khi nhận phản hồi owner: Node 203 passed/0 failed/skipped/cancelled/todo; lint exit 0, 0 errors và 3 Fast Refresh warnings hiện hữu tại badge/button/sidebar; build exit 0, warning chunk >500 kB. Logs/results ở đúng thư mục task trong bảng; không chạy lại khi ghi nhận owner hoặc dọn hồ sơ. Node là logic/transport, không thay manual browser.

## 8. Tái mở QA khi có task được duyệt riêng

Chỉ hướng dẫn, **không chạy trong DOC-CLEANUP-01**. Dùng PowerShell7 tại root, dependencies có sẵn/MySQL Docker thuộc checkout healthy. Runner kiểm container/instance/loopback/target/config/cache/grants/collision trước provision; không tự start container/reset/seed dev. Schema mới `stayhub_tv3_ac_test_<guid32>`, user `tv3ac_<run16>` chỉ quyền exact schema, migrate schema rỗng. Override chỉ trong process, local/debugfalse/database sessions, cookie/origin/CORS/Sanctum riêng, CSRF thật. Không đổi root env/production/guard hoặc dùng tài nguyên dev.

| Nhóm | Runner trong `backend/tests/Support/` | Điểm vào / actor |
| --- | --- | --- |
| Invoice read | RunInvoiceReadBrowser.ps1 | `frontend_url` + `/login`; H_A/C_A theo fixture; bốn list/detail Invoice, không payment/create |
| Invoice create | RunInvoiceCreateBrowser.ps1 | `/login` vai trò Host; `h_a@invoice-create.example.test`; `/host/invoices/create` hoặc UI Booking URL trong fixture |
| Review read | RunReviewReadBrowser.ps1 | `/login?role=host`, `h_a@review-read.example.test`; `/host/reviews`; không mutation Review |
| Review create | RunReviewCreateBrowser.ps1 | `qa_entry` của ready.json; C_A; production modal và `/customer/reviews` trong synthetic parent |

```powershell
$qaRunner = 'backend/tests/Support/RunInvoiceReadBrowser.ps1' # chọn đúng runner trong bảng
$qaSecret = Read-Host 'Mật khẩu QA tạm ít nhất 24 ký tự' -AsSecureString
$qaPrevious = $env:STAYHUB_TV3_AC_BROWSER_PASSWORD
try {
    $env:STAYHUB_TV3_AC_BROWSER_PASSWORD = [Net.NetworkCredential]::new('', $qaSecret).Password
    pwsh -NoProfile -File $qaRunner -Provision -KeepAlive -MaxMinutes 60
} finally {
    if ($null -eq $qaPrevious) { Remove-Item Env:STAYHUB_TV3_AC_BROWSER_PASSWORD -ErrorAction SilentlyContinue }
    else { $env:STAYHUB_TV3_AC_BROWSER_PASSWORD = $qaPrevious }
    $qaSecret.Dispose()
    $qaPrevious = $null
}
```

Chờ ready; terminal2 nhập **đúng evidence directory đang được runner in**, không chọn run theo ngày hoặc dùng URL/ID cũ. Browser profile QA riêng; dùng password tạm vừa nhập, không gửi lên chat/log/HAR. Lấy actor/ID/URL thật từ `fixture.json`; process tắt Booking/Property mocks. Review synthetic isMock toggle chỉ để kiểm nút gửi bị khóa, không tạo từ mock Booking.

```powershell
$qaEvidence = Read-Host 'Absolute evidence directory của chính run đang hoạt động'
$qaReady = Get-Content -LiteralPath (Join-Path $qaEvidence 'ready.json') -Raw | ConvertFrom-Json
$qaReady | Select-Object run_id, frontend_url, backend_url, qa_entry, stop_file
# Chỉ sau logout mọi account/tab QA và hoàn tất checklist:
[IO.File]::WriteAllText($qaReady.stop_file, 'owner completed UI')
```

Giữ runner hoạt động khi kiểm; không Ctrl+C bỏ audit. Logout/đóng QA, trả Network Online/No throttling/tắt overrides/zoom100%/đóng Print Preview. Chờ runner exit; result.passed=true, failure/cleanup_failure=null; business/session journal đúng delta, authenticated sessions0; process exited/ports released/schema-user-connections0/0/0; dev/source/env hashes khớp. Lease hết hạn hoặc session audit lỗi là FAIL dù resource cleanup đạt. Không tự restore dev/source hoặc drop theo prefix. Ghi actor/fixture/case/viewport/expected/actual/request pending/RunID; không lưu secrets.

Invoice/Review create tách `P-*` probes và `UI-*` manual, seal sau logout; chỉ ID allowlist của phase hiện hành được mutation. P/seed/source phải bất biến sau seal. HTTP/harness403 scope guard không thay production authorization. UNKNOWN đổi response sau commit có `X-TV3-QA-Fault: after-commit-response`; đọc GET trước, không tự replay POST.

## 9. Invoice read — UC15/35/37

### Fixture và oracle

Giữ sáu Invoice I01–I06 của task API: hai Host, hai Customer, ba trạng thái, snapshots, tiền có phần lẻ, null dates, zero/items rỗng, và I05 `9999999999.99`. Bổ sung E01–E12, đều PAID thuộc H_A/C_A, mã `UI-PAGE-E01`…`UI-PAGE-E12`, tổng lần lượt `10001.00`…`10012.00`. Created/paid UTC từ 2026-11-06 tới 2026-11-17 lúc 01:00:00.

| Actor | Tổng | PAID | UNPAID | REFUNDED | Cỡ trang UI | Danh sách đầy đủ |
| --- | --- | --- | --- | --- | --- | --- |
| H_A | 16 | 14 | 1 | 1 | 10 | Trang 1: E12…E03; trang 2: E02/E01/I04/I06/I02/I01 |
| C_A | 15 | 13 | 1 | 1 | 9 | Trang 1: E12…E04; trang 2: E03/E02/E01/I04/I03/I01 |
| H_B | 2 | 0 | 1 | 1 | 10 | I05/I03 |
| C_B | 3 | 1 | 1 | 1 | 9 | I05/I06/I02 |
| H_EMPTY / C_EMPTY | 0 | 0 | 0 | 0 | Theo role | Empty state |

Search `UI-PAGE` có 12 dòng, thứ tự E12…E01: Host hai trang 10+2; Customer 9+3. PAID H_A: E12…E01/I04/I02 (10+4); PAID C_A: E12…E01/I04 (9+4). UNPAID H_A/C_A chỉ I01; REFUNDED H_A chỉ I06, C_A chỉ I03. Search `no-match-ever` rỗng.

I01: subtotal 213.25, discount 10.00, extra_fee 5.25, total 208.50; ROOM 200.50, SERVICE 12.75. I04 tổng 0.00/items []; I05 tiền lớn. Đây là stored read fixtures, không chứng minh workflow create UC34 hoặc Booking production đã tích hợp.

Tài khoản ACTIVE: `h_a`, `h_b`, `h_empty`, `c_a`, `c_b`, `c_empty`, `admin` tại domain `invoice-read.example.test`. Hai tài khoản `c_blocked`, `c_inactive` dùng kiểm login bị từ chối. Mọi account dùng password tạm caller cấp cho đúng run. ID thực tế và full projection oracle nằm trong `fixture.json` của run; không dùng ID từ run đã cleanup.

Oracle độc lập dùng fixture literals, thứ tự/membership tường minh và expected metadata. Không gọi production create/calculator/query/mapper để tạo expected. So sánh strict kiểu/value, giữ thứ tự list, bỏ qua thứ tự key object. Unit negative controls từ chối sai tiền, ID type, order, metadata và envelope.

UI hiện format VND với 0 chữ số thập phân: I01 208.50 hiển thị khoảng `209 ₫`, I05 hiển thị `10.000.000.000 ₫` theo formatter hiện tại; khoảng trắng do Intl có thể là NBSP. Đối chiếu decimal DB/API riêng; không dùng số đã làm tròn để tính lại tổng. Nếu trình bày gây hiểu sai, ghi finding để review, không tự đổi formatter.

### Checklist UI — PASS theo owner

| ID | UC | Thao tác và expected | Trạng thái hiện hành |
| --- | --- | --- | --- |
| UI01 | 15/35/37 | Guest/direct URL; login đúng role; reload giữ actor; sai khu vực bị chặn | PASS theo owner; runtime artifact chưa đối chiếu |
| UI02 | 15/35/37 | List đúng owner; foreign/missing detail không lộ dữ liệu | PASS theo owner; runtime artifact chưa đối chiếu |
| UI03 | 15/37 | Card/table đúng code/snapshot/status/tiền/ngày; H_EMPTY/C_EMPTY đúng empty state | PASS theo owner; runtime artifact chưa đối chiếu |
| UI04 | 15/35 | List → detail → list; I01 header/ROOM/SERVICE/discount/fee/tổng đúng oracle | PASS theo owner; runtime artifact chưa đối chiếu |
| UI05 | 15/35 | I04 zero/items rỗng, paid_at null của I01/I06 và I05 tiền lớn render rõ | PASS theo owner; runtime artifact chưa đối chiếu |
| UI06 | 15/37 | Status/search/empty đúng oracle, Host tìm customer name và Customer tìm property name | PASS theo owner; runtime artifact chưa đối chiếu |
| UI07 | 15/37 | PAID và search UI-PAGE qua trang 2; request giữ status/search/per_page, đúng IDs và counts | PASS theo owner; runtime artifact chưa đối chiếu |
| UI08 | 15/37 | Trang 2 → đổi filter/search/xóa/reset về trang 1; dùng đúng controls từng trang, không giả định Host có nút reset riêng | PASS theo owner; runtime artifact chưa đối chiếu |
| UI09 | 15/35/37 | Slow network/loading; đổi nhanh filter/ID, response cũ không đè context mới | PASS theo owner; runtime artifact chưa đối chiếu |
| UI10 | 15/35/37 | Offline/lỗi → thông báo → online/retry hoặc reload phục hồi; ghi rõ cách tạo lỗi | PASS theo owner; runtime artifact chưa đối chiếu |
| UI11 | 15/35/37 | Logout/rời trang khi request thực sự pending; actor khác không thấy dữ liệu cũ | PASS theo owner; runtime artifact chưa đối chiếu |
| UI12 | 15/35/37 | Logout tab thứ hai cùng phiên; request tiếp theo của tab Invoice xử lý hết phiên | PASS theo owner; runtime artifact chưa đối chiếu |
| UI13 | 15/35/37 | 1440×900, 768×1024, 375×812: không mất nội dung/đè tiền/tràn trang ngoài ý muốn | PASS theo owner; runtime artifact chưa đối chiếu |
| UI14 | 15/35/37 | Tab/Shift+Tab/Enter/Space, focus/nhãn controls, zoom 200% | PASS theo owner; runtime artifact chưa đối chiếu |
| UI15 | 15 | Print Preview đúng Invoice, ẩn điều hướng; hủy preview, không gửi lệnh in | PASS theo owner; runtime artifact chưa đối chiếu |
| UI16 | Chung | Logout/tab/Network/zoom cleanup thủ công; đối chiếu runner process/port/schema/user cleanup | PASS cleanup UI/runner success theo owner; artifact chưa đối chiếu |

Kết quả toàn bộ checklist theo xác nhận owner, audit đúng lượt manual chưa đối chiếu độc lập; không dùng HTTP01A thay browser01B.

### Evidence harness01A — lịch sử đã nghiệm thu

Các dòng readiness/chưa manual bên dưới là kết quả tại 01A; kết quả UI hiện hành nằm ở checklist owner phía trên.

| AC | Kết quả và evidence |
| --- | --- |
| A01 | PASS: explicit provision/password-for-KeepAlive; guard env/target/ports/origins/session/effective config; thiếu cờ/password bị chặn trước connection |
| A02 | PASS: fixture commit thật, H_A16/C_A15, status/search qua hai trang đúng oracle; không đổi fixture API gốc |
| A03 | PASS: HTTP login/cookie/database session/me/logout thật; bốn controls thiếu/sai XSRF login/logout trả 419; valid login/logout đạt |
| A04 | PASS: mỗi positive run 167 status assertions/58 full oracle comparisons, đúng role/account/owner/list/detail/filter/page; business 19 bảng bất biến và authenticated sessions sau logout 0 |
| A05 | PASS: Vite phục vụ bốn Invoice JSX source, backend QA đúng target; không browser/UI observation |
| A06 | PASS: failures sau Prepare/Ready cleanup đúng; positive KeepAlive→stop.request audit/cleanup đạt, không thao tác UI |
| A07 | PASS: process exited, ports released, schema/user/connections 0/0/0; dev/source/env fingerprints giữ nguyên, không restore tự động |
| A08 | PASS: 39 unit/54 assertions; hồi quy SQLite 2023 passed/193 skipped/8387 assertions exit 0; PHP syntax/PowerShell parser/scope/docs checks đạt |

| Run | ID | Kết quả |
| --- | --- | --- |
| Positive tự kết thúc | `de5b48f1239b46d681b75cab458b3387` | Exit 0, 167 status/58 oracle; cleanup PASS |
| Negative Prepare | `689f2a9da3974781ae8825b2b8fd8339` | Exit 1 đúng INV_READ_UI_EXPECTED_FAILURE_AFTER_PREPARE; cleanup PASS |
| Negative Ready | `8aa65d0a0c5747309f9aaed20789483b` | Exit 1 đúng INV_READ_UI_EXPECTED_FAILURE_AFTER_READY; cleanup PASS |
| Positive KeepAlive/stop tự động | `9777e0a7a2aa4bb182599017976474a9` | Exit 0, 167 status/58 oracle; stop.request, audit sessions/business và cleanup PASS; không UI action |

Intermediate runs `8ac0535829f3447fa2d554ad496413b1`, `294575843b104effa5afa27e67c953d4` FAIL do JSON tiếng Việt qua process stdin chưa dùng UTF-8; `68ec9d31fbf34e15863fdbbde1353dc6` FAIL do biểu thức mảng PowerShell tách ID thành suffix thứ ba. Đã sửa trong harness mới, không sửa production; cả ba cleanup sạch. Giữ nguyên intermediate evidence, không tính vào positive gate.

Evidence local ignored `.agent-workspace/tv3/inv-read-ui-01a/`: mỗi run có manifest/fixture/probes/oracle/business/session/process/port/cleanup/result và source/dev hashes; task root có baseline, regression, missing-provision/password controls, scope/run audits, secret-scan và verification-summary. Chỉ fixture oracle synthetic được lưu; không lưu raw dev dump, cookies hoặc credentials.

## 10. Invoice create — UC34/56/57/58

### Fixture và oracle

H_A/H_B/C_A/C_B/ADMIN đều ACTIVE; emails tương ứng `h_a`, `h_b`, `c_a`, `c_b`, `admin` tại `invoice-create.example.test`. Dùng password tạm của chính run. Booking nguồn nền ngoài P/UI không được phép tạo Invoice. Catalog phòng900.00/dịch vụ888.88 khác snapshot; service của FOREIGN thuộc Property H_B đúng FK/ngữ nghĩa.

Mỗi recipe có hai bản P/UI; ID/URL thực tế lấy từ `fixture.json`, không tái dùng ID run đã cleanup.

| Kind | Oracle / điều kiện |
| --- | --- |
| VND-SERVICES | COMPLETED/H_A/C_A;3×1200000.00=3600000.00;2×150000.00=300000.00;1×250000.00=250000.00;subtotal/total4150000.00;1 ROOM/2 SERVICE |
| ROOM-ONLY | 2×100000.00=200000.00, đúng1 ROOM |
| ZERO | 2 đêm giá0.00, total0.00, vẫn1 ROOM |
| ROUND | ROOM200.50; SERVICE12.75/12.53;total225.78, quantity1.25×unit10.02 round12.53 |
| MAX | 1 đêm9999999999.99, đúng1 ROOM |
| DUPLICATE | Seed trực tiếp Invoice/items đầy đủ mãQA-P-DUPLICATE/QA-UI-DUPLICATE; POST phải409, rows cũ bất biến |
| NOT-COMPLETED | CONFIRMED, chưa Invoice, POST422 |
| FOREIGN | COMPLETED thuộcH_B; H_A POST403 |
| DOUBLE | Booking riêng cho pending/double submit; cùng oracle VND-SERVICES |
| UNKNOWN | Booking riêng cho lỗi response sau commit; cùng oracle VND-SERVICES |
| MISSING | ID999999999 không tồn tại; POST404 |

Create API không nhận giá/discount/fee/items từ client; các Invoice mới UNPAID, paid_atnull, discount/extra_fee0.00. Hai Invoice DUPLICATE là setup data trực tiếp, không tính là production create evidence. Oracle literal độc lập, không dùng calculator/mapper production sinh expected. API projection dùng ID/code/time đã kiểm từ DB, fields nghiệp vụ theo literals; raw JSON truyền nguyên vẹn để giữ ISO date/null/decimal.

UI VND hiện dùng0 chữ số thập phân: ROUND225.78→khoảng226₫; ROOM200.50→201₫ và hai service→13₫/13₫. Không cộng các số đã làm tròn để tính lại total. MAX hiển thị khoảng10.000.000.000₫, DB/API vẫn9999999999.99. Khoảng trắng Intl có thể NBSP; không sửa formatter trong task QA.

### Checklist CUI — PASS theo owner

| ID | Thao tác / expected | Kết quả |
| --- | --- | --- |
| CUI01 | Host QA mở nhập ID; thông tin/rules/trạng thái ban đầu rõ | PASS theo owner |
| CUI02 | ID rỗng khóa nút;0/01/1e2/chữ bị validation cục bộ từ chối, không POST. Local400 không phải backend422 | PASS theo owner |
| CUI03 | URL có Booking ID khóa input, POST đúng UI Booking trong URL | PASS theo owner |
| CUI04 | VND-SERVICES: success, mã/khách/phòng và Chưa thanh toán đúng | PASS theo owner |
| CUI05 | UC56:1 ROOM/2 SERVICE, quantity/unit_price/amount và null description đúng | PASS theo owner |
| CUI06 | UC57/58: subtotal/total4.150.000₫, không cộng phí hai lần | PASS theo owner |
| CUI07 | ROOM-ONLY/ZERO/ROUND/MAX đúng oracle/formatter, không coi0 là thiếu dữ liệu | PASS theo owner |
| CUI08 | Network chậm: loading/aria-busy/nút khóa; double click chỉ1 POST. Không dùng sequential409 của01A thay double-click UI | PASS theo owner |
| CUI09 | Đổi ID/rời trang khi pending: kết quả không gán nhầm Booking/URL, không tự POST lại; dùng ID thật ghi trong response | PASS theo owner |
| CUI10 | DUPLICATE409 rõ, count/header/items cũ không đổi | PASS theo owner |
| CUI11 | NOT-COMPLETED422, FOREIGN403, MISSING404; không success hoặc output mới | PASS theo owner |
| CUI12 | Hết phiên sau mở trang: xử lý theo Auth hiện hữu; nếu request401 thì không hiển thị success/replay. Ghi phân biệt route guard chuyển trang và401 thực quan sát | PASS theo owner |
| CUI13 | Offline trước submit: lỗi mạng rõ, online lại không tự replay | PASS theo owner |
| CUI14 | UNKNOWN safe500 sau commit: không success giả/autoPOST lại; Host list/detail tìm đúng1 Invoice đã lưu, phục hồi bằng GET | PASS theo owner |
| CUI15 | Reload, Host list/detail và C_A Customer detail đọc lại đúng Invoice/tiền/items đã tạo | PASS theo owner |
| CUI16 | Lập hóa đơn khác, breadcrumb, print preview/đóng preview đúng, không phát sinh POST ngoài ý định tạo | PASS theo owner |
| CUI17 | Mobile/tablet/desktop, zoom200%, Tab/Enter/focus/alert, bảng tiền không tràn/chồng; không tuyên bố screen reader/WCAG nếu chưa kiểm | PASS theo owner |
| CUI18 | Logout các phiên QA, đóng tab, Network Online/No throttling/tắt overrides; stop runner và kiểm result/cleanup | PASS theo owner |

Kết quả toàn bộ checklist theo xác nhận owner, audit đúng lượt manual chưa đối chiếu độc lập; không dùng HTTP01A thay browser01B.

### Evidence harness01A — lịch sử đã nghiệm thu

Các dòng readiness/chưa manual bên dưới là kết quả tại 01A; kết quả UI hiện hành nằm ở checklist owner phía trên.

| Gate01A | Evidence / kết quả |
| --- | --- |
| A01 target/session | PASS: guard reuse, opt-in scenario riêng, database session/cookie/CSRF thật;401 guest,419 thiếu/sai XSRF,403 role/owner,404 missing,409 duplicate,422 status |
| A02 fixture split | PASS:20 Booking P/UI cộng1 source nền;2 Invoice DUPLICATE setup; probe tạo7 Invoice/15 items, UI chưa được tiêu thụ tại readiness |
| A03 oracle | PASS:59 HTTP status checks/20 strict POST/HostGET/CustomerGET oracles; zero/MAX/ROUND/VND và body spoof không thay tiền; raw ISO/null/decimal giữ nguyên |
| A04 mutation | PASS:34 unit/67 assertions;22 corruption controls; source rows, seed/P checkpoint immutable, header/items trọn vẹn, allowedIDs/no orphan |
| A05 unknown | PASS:kernel201/commit→harness500 có attribution/CORS; GET tìm đúng một Invoice, không replay UNKNOWN POST |
| A06 handoff | PASS:KeepAlive sau seal từ chối P POST403, HTTP tự động tạo UI-ROOM-ONLY201 và UI-UNKNOWN500 rồi GET phục hồi; cuối audit đúng2 Invoice/4 items mới, logout/session users rỗng |
| A07 cleanup | PASS:process/port released, schema/user/connections0/0/0 và dev/source/env fingerprints khớp, kể cả controls Prepare/Ready |
| A08 regression/scope | PASS:full backend2084 passed/245 skipped/8485 assertions, SQLite process cô lập;3 PHP syntax/PS parser, đúng7 file, ngoài scope giữ nguyên |

Positive final KeepAlive `a72192c054de4d42b90b152a9f23333b`:59 probes/20 oracle, thêm HTTP selftest sau readiness như A06; runnerexit0. Đây vẫn là HTTP tự động, không phải owner/browser UI. Positive autostop `8b5b0bc1a5bb4ccca0f3c5dd57aea8d1`:59/20,exit0 trước bổ sung seal; final run mới là evidence cho đầy đủ code hiện tại. Positive trước đó `3452a619b5fe4f0e9980655a1bbfdb37` giữ lịch sử trước hardening CORS/seal.

Negative controls: `2f1e07454a2a4539b1529fc691d7f538` dừng sau prepare, `58c58a2fc2d74d04af2df71fe17f9f8d` dừng sau server ready; đều runnerexit1 đúng marker, cleanup đạt, không gọi suite này positivePASS. Lượt `4c8e1bee2a6549fe98d631b123d36a1e` lỗi retained XSRF header, `c7a470cf58a340239d0d0ffe52ce3d62`/`f8a2dd0b72d74219a3402ed8ab3540c4` lỗi JSON timestamp round-trip của PowerShell; đã sửa harness giữ raw JSON, không nới production contract/date oracle. Lượt handoff `cfa5b446873544bbb0c271cc05369232` wrapper dùng tên biến trùng PID read-only, chưa logout; runner audit từ chối session còn đăng nhập đúng thiết kế. Wrapper được sửa và logout trong finally; final run đạt. Giữ nguyên các lượt FAIL, không đổi thành PASS; tất cả cleanup resource đều đạt.

Raw evidence `.agent-workspace/tv3/inv-create-ui-01a/<run-id>/`; tổng hợp tại task root: baseline/backups, handoff-final-selftest, backend-regression/result, run-audit, ac-evidence, secret-scan, final-scope-check và verification-summary. Không giữ service QA hoạt động sau01A;01B dùng run mới.

## 11. Review read — UC38

### Fixture và oracle

8 actors tại domain `review-read.example.test`: `h_a`, `h_b`, `h_empty`, `c_a`, `c_b`, `admin`, `h_blocked`, `h_inactive`. Password tạm dùng chung chỉ trong run. Host blocked/inactive bị login403; active Customer/Admin không có quyền GET Host Review hợp lệ. ID thật lấy `fixture.json`, không dùng ID của run đã cleanup.

4 Property/Room: P_A1/P_A2/P_A0 của H_A, P_B của H_B;16 Booking COMPLETED và16 Review. Không tạo Invoice.

| Scope | Danh sách | Summary total / average | Sao5/4/3/2/1 |
| --- | --- | --- | --- |
| H_A | R13…R01 | 13 /3.8 | 6/3/1/1/2 |
| P_A1 (HTTP) | R08…R01 | 8 /3.6 | 3/2/1/1/1 |
| P_A2 (HTTP) | R13…R09 | 5 /4 | 3/1/0/0/1 |
| H_B | B03/B02/B01 | 3 /1.3 | 0/0/0/1/2 |
| H_EMPTY hoặc P_A0 (HTTP) | rỗng | 0 /null | 0/0/0/0/0 |

H_A page1 size10: R13/R12/R11/R10/R09/R08/R07/R06/R05/R04; page2 R03/R02/R01. R12/R13 cùng timestamp, IDR13 cao hơn; R01 created_at=null. UI card R01 không có dòng ngày; không tự biến null thành ngày hiện tại.

| Điều kiện H_A | Kết quả theo thứ tự |
| --- | --- |
| 1 sao | R13/R08 |
| 2 sao | R07 |
| 3 sao | R06 |
| 4 sao | R12/R05/R04 |
| 5 sao | R11/R10/R09/R03/R02/R01 |
| QA-COMMENT | R10/R02 |
| Trần Bình hoặc tran binh | R11/R04 |
| biển | R03 |
| 100% | R05 |
| A_B | R06 |
| `<script>` | R08 |
| 5 sao + QA-COMMENT | R10/R02 |
| no-match-ever | rỗng |

Summary H_A vẫn13/3.8/6–3–1–1–2 khi lọc/search/page; count danh sách thay đổi riêng. H_B có từ khóa/tên trùng để phát hiện OR search vượt owner scope. R08 comment `<script>alert("QA")</script> 0` phải là văn bản khi kiểm browser, không chạy script. API truyền chuỗi đúng không chứng minh browser XSS đã kiểm.

Kỳ vọng projection gồm10 trường API Host. Oracle dùng fixture literals và membership tường minh, không dùng production query/mapper để sinh expected. Kiểm strict kiểu/value/null/order/summary/meta/links; negative controls chứng minh từ chối corruption và audit dữ liệu/session.

### Checklist RUI — PASS theo owner

| ID | Thao tác / kỳ vọng | Kết quả01B |
| --- | --- | --- |
| RUI01 | Đăng nhập H_A, mở `/host/reviews`; Network GET QA backend thành công, không dùng mock; không có B01–B03 | PASS theo owner |
| RUI02 | Summary13, điểm3.8, phân bố sao6/3/1/1/2; nhãn “Toàn hệ thống” hiện hữu chỉ phản ánh scope Host, không phải toàn bộ DB | PASS theo owner |
| RUI03 | Cards hiển thị tên khách, Property/Room, mã READ-Rxx, sao/comment và ngày theo locale; đối chiếu fixture | PASS theo owner |
| RUI04 | Page1 đúng10, page2 đúng3, thứ tự R13/R12 tie; chuyển trước/sau và nút biên hoạt động | PASS theo owner |
| RUI05 | Chọn lần lượt1–5 sao: membership đúng bảng; page reset1, summary giữ toàn Host | PASS theo owner |
| RUI06 | Click thanh sao trong summary: bộ lọc/list tương ứng, trạng thái chọn khớp | PASS theo owner |
| RUI07 | Search QA-COMMENT rồi Enter → R10/R02; chưa submit không áp dụng search mới | PASS theo owner |
| RUI08 | Search Trần Bình/tran binh/biển → đúng bảng; Unicode hiển thị đúng, không lộ review H_B | PASS theo owner |
| RUI09 | Kết hợp5 sao + QA-COMMENT → R10/R02, tổng phù hợp2 nhưng summary13 | PASS theo owner |
| RUI10 | Xóa search/chip sao, Xóa toàn bộ bộ lọc: dữ liệu khôi phục/page1, input/chip nhất quán | PASS theo owner |
| RUI11 | Search no-match-ever: “Không tìm thấy đánh giá phù hợp”, không nhầm Host chưa có review | PASS theo owner |
| RUI12 | Logout H_A, login H_EMPTY: “Chưa có đánh giá nào từ khách lưu trú”, summary0/—, không NaN hoặc chia0 | PASS theo owner |
| RUI13 | Logout/login H_B: chỉ B03/B02/B01, summary3/1.3; không còn dữ liệu H_A | PASS theo owner |
| RUI14 | H_A page2 R01 không có ngày giả; R08 chuỗi script hiển thị như văn bản, không alert/script execution | PASS theo owner |
| RUI15 | Làm mới dữ liệu: giữ filter/page hợp lệ, không nhân đôi cards, kết quả vẫn đúng | PASS theo owner |
| RUI16 | Slow3G: quan sát skeleton/overlay/nút refresh; đổi filter/rời trang khi pending rồi quay lại, không unhandled cancellation hoặc dữ liệu của scope khác | PASS theo owner |
| RUI17 | Offline rồi refresh: error phù hợp; Online/Thử lại phục hồi. Không dùng response giả để ghi là lỗi500 thật | PASS theo owner |
| RUI18 | Hai tab cùng profile: logout tab2, tab1 refresh xử lý401/đăng nhập lại; Customer/Admin route guard và GET403 được phân biệt | PASS theo owner |
| RUI19 | Desktop/tablet/mobile khoảng1440/768/390px, zoom200%, Tab/Enter/Space cho nút/form; không tràn/chồng/che nội dung; chưa tuyên bố WCAG compliance | PASS theo owner |
| RUI20 | Logout mọi QA session, trả DevTools/zoom, stop.request; audit business/dev/source/session/process/ports/schema/user đạt đúng run | PASS theo owner |

Kết quả toàn bộ checklist theo xác nhận owner, audit đúng lượt manual chưa đối chiếu độc lập; không dùng HTTP01A thay browser01B.

### Evidence harness01A — lịch sử đã nghiệm thu

Các dòng readiness/chưa manual bên dưới là kết quả tại 01A; kết quả UI hiện hành nằm ở checklist owner phía trên.

| AC | Kết quả / evidence |
| --- | --- |
| RUA01 | PASS: missing-Provision exit1 trước connection; 157 focused unit/192 assertions gồm73 ENV,39 browser guard,45 Review controls; scenario/opt-in/config/target sai bị từ chối |
| RUA02 | PASS: fixture16 Booking/Review committed,8 actors, cookie/database session/CSRF/CORS thật; password tạm không lưu vào evidence |
| RUA03 | PASS: 91 HTTP statuses và25 strict oracle comparisons trong mỗi positive run; list/projection/null/order/summary/search/rating/page và3 owned property scopes HTTP |
| RUA04 | PASS: guest401, wrong role403, invalid query422 trước role, disabled login403, foreign/missing property404; scope H_A/H_B không leak |
| RUA05 | PASS: business fingerprints giữ nguyên toàn bộ base tables trừ sessions; logout không còn authenticated sessions; QA mutation403 riêng không phải production mutation test |
| RUA06 | PASS: positive autostop vàKeepAlive/stop.request; failure injection Prepare/Ready exit1 đúng marker; mọi run cleanup0/0/0, processes exited/cổng released; dev/source/env hashes khớp |
| RUA07 | PASS tài liệu: hướng dẫn mở/stop QA và checklist RUI01–20 đã có; mọi browser/manual assertion NOT VERIFIED |

- Autostop `972fd254411e4980b73ebf97e36b53f2`: passed=true,91 statuses/25 oracles,0 authenticated sessions.
- Handoff tự động `4a7f2c2a7eb243c988b1961c590c4270`: passed=true,91/25, ready observed, wrapper tạo stop.request, post-stop business/session audit đạt. Không có người kiểm UI ở run này.
- Negative Prepare `636c636856f644b4a47d4529bb45ce2f`, Ready `fbc06796282043d49a366b1f21bae687`: expected exit1/đúng marker, cleanup_failure=null. passed=false là đúng cho failure controls, không đổi thành positive PASS.
- Bốn run phát triển FAIL được giữ: `169ccb864d8a43d289e2b3a5be0bf39d` do fixture ISO bị PowerShell chuyển ngày; `9fd7605ae19748ddb696a43ecf8aff2a` và`79c336e31c9548a3addd1ea603215c33` do combined-search oracle thiếuR02 (không có property filter nên đúng làR10/R02); `38535947e21b4c45abbc8714e2083ae7` do helper phản hồi QA trước Laravel request binding gây500. Đã sửa harness/oracle/diagnostics, không sửa production hoặc nới kỳ vọng nghiệp vụ; mọi run lỗi cleanup0/0/0 không lỗi cleanup.

## 12. Review create — UC16/60

### Fixture và oracle

- 7 actors: H_A/H_B, C_A/C_B, ADMIN, C_BLOCKED/C_INACTIVE;2 Property/Room. Actor mặc định C_A, ACTIVE. Account bị khóa/ngừng hoạt động là fixture bổ sung; chưa claim kiểm account UI trong01A.
- Tái sử dụng10 Booking nền, tạo thêm28 Booking:14 recipes × hai họ `P` và `UI`; tổng38 Booking. Có4 Review cũ:2 nền và DUPLICATE của mỗi họ.
- Recipes mỗi họ: MAIN, MIN, MAX, CROSS_HOST, DUPLICATE, FOREIGN, DOUBLE, UNKNOWN, HTML, PENDING, CONFIRMED, CHECKED_IN, CANCELLED, REJECTED.
- MAIN/MIN/MAX/CROSS_HOST/DOUBLE/UNKNOWN/HTML là7 Booking mới được phép tạo. CROSS_HOST thuộc C_A nhưng Property Host B. FOREIGN thuộc C_B, dùng C_A để kiểm404; không tạo Review cho FOREIGN trong checklist.
- MIN:1 sao/comment `abcde`. MAX:5 sao/1000 ký tự `Đ`. HTML:5 sao/comment `<script>alert("QA")</script> Phòng sạch.`. MAIN probe gửi comment có khoảng trắng và spoof customer_id/property_id; expected là payload đã trim, danh tính/Property từ DB. UI có thể nhập nội dung khác hợp lệ; journal phải đối chiếu đúng nội dung thực gửi.
- Probes tạo7 Review, đọc lại qua GET Customer; cũ4+probe7=11 Review toàn schema trước seal. C_A thấy10, C_B thấy1. UI còn nguyên, gồm14 Booking/1Review DUPLICATE. Nếu01B tạo đủ7 positive UI thì thêm đúng7 dòng, không tính gửi lại UNKNOWN/DOUBLE là dòng mới.
- Audit giữ nguyên bảng nguồn ngoài reviews/sessions và toàn bộ Review cũ. Mỗi dòng mới phải thuộc allowlist, đúng actor/Property, rating/comment khớp chính request201 được journal ghi, có timestamps hợp lệ; kiểm exact response10 fields bằng fixture/request/DB, không gọi production mapper làm expected. Probe positive còn so với literal payload độc lập trong runner.
- Journal là append-only; request lỗi không được hợp thức hóa dòng mới. Checkpoint seal chỉ sau logout. Audit UI so với checkpoint, không dùng lại kết quả probes làm bằng chứng UI.

UNKNOWN: harness đổi phản hồi production201 thành synthetic500 **sau khi commit**; giữ CORS/session headers và gắn `X-TV3-QA-Fault: after-commit-response`. Marker ghi rõ fault tổng hợp. GET tìm đúng một Review; POST lại nhận409, không tạo dòng thứ hai. Đây là kiểm fault của harness, chưa phải quan sát mạng lỗi tự nhiên/browser recovery.

### Checklist RCUI — PASS theo owner

| ID | Cách kiểm01B | Tiêu chí đạt | Kết quả01B |
| --- | --- | --- | --- |
| RCUI01 | Mở QA, login C_A | Banner synthetic QA rõ; phiên đúng Customer; không dùng mock | PASS theo owner |
| RCUI02 | UI-MAIN mở modal | Đúng Property/Room; modal production; không lấy nhầm Booking | PASS theo owner |
| RCUI03 | Chọn1–5 sao, hover và phím mũi tên | Nhãn/sao đúng, tương tác bàn phím rõ | PASS theo owner |
| RCUI04 | Tab/Shift+Tab, Escape, đóng/Hủy trước submit | Focus/đóng hoạt động; không phát POST hoặc tạo Review | PASS theo owner |
| RCUI05 | Comment trắng,4ký tự,1001ký tự | Chặn gửi/hiển thị đếm và validation phù hợp; DB không đổi | PASS theo owner |
| RCUI06 | UI-MAIN nhập comment có khoảng trắng/Unicode rồi gửi |201, đóng modal; đọc lại đúng comment đã trim, actor/Property đúng | PASS theo owner |
| RCUI07 | UI-MIN:1sao/abcde | Tạo đúng biên tối thiểu; đọc lại khớp | PASS theo owner |
| RCUI08 | UI-MAX:5sao/1000ký tự Đ | Tạo đúng biên tối đa; không cắt dữ liệu | PASS theo owner |
| RCUI09 | UI-CROSS_HOST với C_A | Tạo vào Property Host B; không tin dữ liệu owner từ UI | PASS theo owner |
| RCUI10 | UI-DUPLICATE gửi payload hợp lệ |409, thông báo/đọc lại; Review4sao cũ nguyên vẹn | PASS theo owner |
| RCUI11 | Lần lượt5 fixture status khác COMPLETED |422, không tạo Review; chỉ kiểm server eligibility từ QA parent | PASS theo owner |
| RCUI12 | C_A gửi UI-FOREIGN |404 và thông báo; không lộ/tạo Review của C_B | PASS theo owner |
| RCUI13 | UI-DOUBLE, mạng chậm và click/Enter liên tiếp | Pending rõ, tối đa1 POST thành công/1Review; không coi là race MySQL mới | PASS theo owner |
| RCUI14 | UI-UNKNOWN gửi, nhận fault500; đọc lại; nếu gửi lại nhận409 | Tồn tại đúng1Review, hiểu kết quả đã commit; không báo lần đầu rollback | PASS theo owner |
| RCUI15 | Logout → thử gửi UI-HTML; Host thử gửi; rồi restore C_A |401/403 được xử lý, không ghi; phân biệt modal với QA parent/Auth UI | PASS theo owner |
| RCUI16 | UI-HTML thử offline trước khi gửi, rồi khôi phục mạng | Lỗi mạng/pending phục hồi; lần offline không tự tạo Review; chưa gửi thành công trướcRCUI17 | PASS theo owner |
| RCUI17 | UI-HTML gửi comment recipe giống HTML | Hiển thị văn bản ở modal/read-back/card; không chạy script; chỉ browser01B mới kết luận XSS rendering | PASS theo owner |
| RCUI18 | Đánh giá của tôi, refresh/phân trang/đọc lại | Nội dung mới đúng; Customer khác không thấy; loading/error states khi có điều kiện | PASS theo owner |
| RCUI19 | Mobile/tablet/desktop/zoom200%; tùy chọn isMock=true trên fixture | Không tràn/cắt/chồng; mock banner rõ/nút gửi khóa/không POST; không tuyên bố WCAG compliance | PASS theo owner |
| RCUI20 | Logout, đóng QA, tạo stop.request và chờ runner | Không authenticated session; audit UI đúng delta; processes/ports/schema/user/connection cleanup đạt | PASS theo owner; audit runtime chưa đối chiếu độc lập |

Kết quả toàn bộ checklist theo xác nhận owner, audit đúng lượt manual chưa đối chiếu độc lập; không dùng HTTP01A thay browser01B.

### Evidence harness01A — lịch sử đã nghiệm thu

Các dòng readiness/chưa manual bên dưới là kết quả tại 01A; kết quả UI hiện hành nằm ở checklist owner phía trên.

| AC | Trạng thái | Evidence |
| --- | --- | --- |
| RCUA01 | PASS |153 focused tests/211 assertions;41 fixture/audit tests; thiếu-Provision từ chối trước DB; strict guard helper nguyên trạng |
| RCUA02 | PASS | Cookie/database session/CSRF thật,5 actors login/logout, `/api/me`, session cookie riêng; real loopback origins |
| RCUA03 | PASS |7 production inserts;13 exact POST/GET oracles gồm MIN/MAX/trim/spoof/CROSS_HOST/HTML |
| RCUA04 | PASS |68 HTTP statuses:401/403/404/409/419/422,5non-COMPLETED, thiếu/saiCSRF, scope guard và Customer isolation |
| RCUA05 | PASS | UNKNOWN production201→delivered500 có marker, GET đọc lại, replay409/count1 |
| RCUA06 | PASS | Seal sau logout; P không dùng UI; sau seal POST P bị403; KeepAlive→stop tự động, UI delta0/sessions0 |
| RCUA07 | PASS | HTML entry/module JSX đã transform và production source được Vite phục vụ; chưa mở browser/kiểm UI |
| RCUA08 | PASS | All7 runs cleanup0/0/0; processes thoát/ports nhả; dev/source/env fingerprints giữ nguyên; scope đúng7 file |

- Final KeepAlive tự động **e5a19ce8faed4d5e85ecbb9c4bb4bff7**:68 statuses/13 oracles, checkpoint7Review; sau handoff UI delta0, sessions0; exit0/cleanup đạt. Entry form dùng role enum uppercase theo Auth contract. Không có người kiểm UI ở lượt này.
- Auto-stop **086c9f3577cd4048825f584556850586** và handoff tự động trước **bbd5ab62d06043e29ca25fbde19aca81** cũng68/13 PASS; trước self-review sửa role casing ở form QA, không dùng thay cho final entry evidence.
- Negative Prepare **ecc34edd536446a4b4afd1aa3e0a3aca**, Ready **82ae4edf79fe4e40b12e79d516972e0a**: exit1 đúng fault marker, cleanup đạt.
- Hai development FAIL được giữ: **44d03c615867490dacde7de530f4cd51** ở thư mục artifact Invoice kế thừa lúc đầu, **40642c5cc5784cc18be6f8ce354b916e** ở thư mục Review. Lỗi virtual module/Windows normalized path được sửa trong runner mới; cả hai cleanup đạt. Không sửa production để làm test đạt.
- Full Backend SQLite memory **2170 passed/376 skipped/8604 assertions/exit0**; skipped guards không phải MySQL evidence. Focused **153/211/exit0**. PHP/PowerShell syntax, scope và whitespace được kiểm riêng. Không Node/lint/build production trong01A; dành01B.
- MySQL Docker8.4.13/PHP8.2.12. Artifacts `.agent-workspace/tv3/rev-create-ui-01a/`: raw runs, baseline/backups, focused/full-suite logs, keepalive controls, run-audit và final verification summary. Không lưu secrets.

## 13. T01–T03 — integration chưa đóng

| ID | Trạng thái | Điều kiện còn thiếu |
| --- | --- | --- |
| T01 | **BLOCKED TV2** | Booking API/workflow production tích hợp, đúng owner/customer/room/property và Invoice liên kết thật |
| T02 | **BLOCKED TV2** | Checkout → COMPLETED; snapshot phòng/đêm/dịch vụ nhất quán và writer transaction/lock → Invoice; chưa có checkout production → Invoice evidence |
| T03 | **BLOCKED TV2** | GET Customer Booking/điểm mở Review production đúng owner/status/ID/review; COMPLETED từ Backend, không mock hoặc suy ngày |

Khi được giao tích hợp phải kiểm lại PR HEAD/contract/kiến trúc và test evidence. Không xác nhận findings PR#10 đã sửa; không nhận/merge PR TV2 trong cleanup. TV3 vẫn chịu trách nhiệm lỗi module mình.

UC36/45/47 và UC48 CONTRACT/ENV/QUERY giữ nghiệm thu cũ. Bước tiếp theo đề xuất Mini Plan UC48-CORE-01, chưa triển khai. Bốn guide UI đã gom vào ma trận này ở TV3-DOC-CLEANUP-01; backup/hash/mapping ở `.agent-workspace/tv3/doc-cleanup-01/`. Chỉ kiểm tài liệu, không QA mới.
