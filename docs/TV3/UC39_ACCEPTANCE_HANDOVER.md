# UC39 — Dashboard Host: contract và bàn giao

**CLOSED TV3: owner nghiệm thu CLOSE-01 ngày 2026-11-21**, đủ 10 task kể cả CORE-PREP. Tổng TV3 hiện **15/18 UC CLOSED**. Owner đã kiểm UI thấy ổn và không yêu cầu bổ sung; technical QA-UI vẫn PARTIAL với giới hạn zoom/race được ghi rõ.

Baseline CLOSE: `feature/tv3-uc39-host-dashboard`, HEAD `8532d02285bc2e3b466cb230b10c090c55974365` cộng accepted tracked/untracked; HEAD riêng chưa chứa hết implementation. CONTRACT bắt đầu tại `ac0ab8277c4253010b348beeffd1f86a0e34c76d`; CORE/UI tiếp tục trên baseline sau khi owner merge PR17.
Hồ sơ hợp nhất contract, DATA/UI QA và checklist cũ mục 19–28. [Checklist](TV3_CHECKLIST.md) chỉ giữ tiến độ; [kiến trúc](../../ARCHITECTURE.md) và [phân công](../stayhub-team-work-assignment.md) xác định trách nhiệm TV3.

## Contract hiện hành

V1 chỉ đọc tại `/host`, API `GET /api/host/dashboard`, route `host.dashboard.show`. Không property/host filter, export, occupancy, realtime, comparison, drill-down mới hoặc refund ledger; không tạo Invoice hay đổi Booking/payment. Schema/models hiện hữu không đổi.

### Dữ liệu và ownership

Đối chiếu tĩnh [migration](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php), [Property](../../backend/app/Models/Property.php), [Room](../../backend/app/Models/Room.php), [Booking](../../backend/app/Models/Booking.php), [Invoice](../../backend/app/Models/Invoice.php), [Maintenance](../../backend/app/Models/Maintenance.php) và seeders liên quan. Chưa kiểm schema đã áp dụng/dữ liệu runtime MySQL.

| Tập dữ liệu | Scope bắt buộc |
| --- | --- |
| Property | `properties.host_id = authenticated actor.id` |
| Room | Room thuộc Property trong scope |
| Booking/recent | Booking → Room → Property trong scope |
| Invoice/revenue/warning | Invoice → Booking → Room → Property trong scope |
| Maintenance | Maintenance → Room → Property trong scope |

Actor luôn truyền riêng từ session, không lấy `host_id` từ client. HOST ACTIVE mới được xem; ADMIN/CUSTOMER không có quyền gọi endpoint Host. QueryService kiểm lại role/status khi gọi trực tiếp, từ chối trước reporting Repository; middleware vẫn thực hiện kiểm session/account hiện hành.

Scope gồm Property ACTIVE/INACTIVE/BLOCKED/PENDING và Room ACTIVE/MAINTENANCE/INACTIVE. Không lọc theo điều kiện public catalog hoặc trạng thái tài khoản Customer. Mọi count/list/warning đều dùng scope Host; không trả cảnh báo toàn hệ thống hoặc lỗi chứa dữ liệu Host khác.

Ownership là **quan hệ hiện tại**, không phải chủ sở hữu lúc phát sinh Invoice. Invoice snapshot tên cơ sở/phòng nhưng không snapshot host_id; chuyển chủ có thể chuyển scope reporting ở lần đọc sau. Không suy ownership lịch sử từ tên snapshot.

Các model liên quan hiện không dùng SoftDeletes. Chỉ báo cáo bản ghi còn tồn tại qua chuỗi quan hệ. Migration có cascade trong Catalog/Maintenance, Booking.room_id restrict và Invoice.booking_id unique/restrict; không tự xóa dữ liệu hoặc giả định mọi cascade đều thực hiện được khi có Booking. Không thay FK/index/schema để phục vụ reporting.

### KPI và semantics snapshot

Chụp một `generated_at` UTC sau kiểm quyền, dùng cho cả response. Snapshot là trạng thái hiện tại trong read view, không phải lịch sử tại cuối kỳ. Để loại dữ liệu có ngày tạo tương lai, các bản ghi Property/Room/Booking/Maintenance tham gia snapshot/recent phải có `created_at <= generated_at`, kể cả các ancestor Property/Room trong scope của chúng. Điều kiện scope ancestor này cũng áp dụng cho revenue/warning; Invoice không bị lọc theo ngày tạo thay cho paid_at.

| Field | Định nghĩa | Nhãn UI/phạm vi thời gian |
| --- | --- | --- |
| `scope.properties_count` | COUNT Property trong scope/cutoff | Số cơ sở thuộc phạm vi hiện tại |
| `snapshot.rooms_count` | COUNT Room trong scope/cutoff, mọi trạng thái | Tổng số phòng — hiện tại |
| `snapshot.pending_bookings_count` | COUNT Booking hiện PENDING trong scope/cutoff, không lọc kỳ revenue | Đơn chờ xác nhận hiện tại |
| `snapshot.in_progress_maintenances_count` | COUNT bản ghi Maintenance hiện IN_PROGRESS trong scope/cutoff | Lịch bảo trì đang xử lý — hiện tại |
| `summary.revenue_total` | Revenue theo quy tắc revenue/kỳ | Doanh thu đã thanh toán — trong kỳ |

Maintenance đếm lịch, không đếm distinct Room; hai lịch IN_PROGRESS của cùng phòng đếm 2. Không dùng Room.status, không lọc start_date/end_date và không tự đổi trạng thái. Lịch quá hạn nhưng còn IN_PROGRESS vẫn tính; SCHEDULED/COMPLETED/CANCELLED không tính. Đây không phải chỉ tiêu phòng bị chặn theo lịch Availability. Nếu cần semantics ngày hiệu lực, phải duyệt contract mới với owner TV1/TV2.

Recent lấy 5 Booking mới nhất theo `created_at DESC, id DESC`, hiện mọi trạng thái, cùng scope/cutoff, độc lập kỳ revenue. Không pagination hoặc client limit/sort. `created_at` quyết định thứ tự, không phải ngày lưu trú. Trả tối đa 5 dù backlog có thể lớn hơn. Trạng thái hợp lệ theo [BookingStatus](../../backend/app/Enums/BookingStatus.php): PENDING, CONFIRMED, CHECKED_IN, COMPLETED, CANCELLED, REJECTED.

### Revenue và cảnh báo

- Nguồn duy nhất là header `invoices.total_amount` hiện lưu, payment_status PAID, paid_at không null, thuộc scope và thỏa kỳ/cutoff trong quy tắc kỳ/cutoff. Không gate thêm Booking COMPLETED; không yêu cầu Booking tạo trong cùng kỳ thanh toán.
- Không lấy doanh thu từ Booking chưa có Invoice, catalog hoặc booking_services; không cộng lại items, discount hay extra_fee. Mỗi Invoice đóng góp đúng một lần, không nhân tiền qua join items/services.
- UNPAID/REFUNDED bị loại dù còn paid_at. PAID thiếu paid_at bị loại; warning `paid_invoices_missing_paid_at_scope_count` đếm trong toàn scope Host/cutoff ancestor, độc lập kỳ và không gán tháng bằng created_at Invoice.
- PAID có paid_at sau generated_at bị loại, không cộng vào warning thiếu ngày. PAID chuyển REFUNDED có thể làm giảm doanh thu kỳ thanh toán cũ ở lần đọc sau. Không tạo dòng refund âm tại ngày hoàn tiền: chưa có refund ledger/ngày/số tiền refund.
- SQL giữ DECIMAL, serialize canonical decimal string không âm scale 2; không float/double/Number/parseFloat. Tổng nhiều Invoice có thể lớn hơn một cột DECIMAL(12,2). Header không đổi chỉ vì thêm nhiều items/services.
- Tổng chính xác mọi `revenue_by_month[].revenue_total` phải bằng `summary.revenue_total`. Backend kiểm invariants, frontend kiểm contract bằng scaled integer/BigInt; việc này không phải frontend tự quyết định tổng tài chính.

### Kỳ, timezone, cutoff và nhất quán

| Quy tắc | Contract |
| --- | --- |
| Input | `from_month`, `to_month` đúng YYYY-MM, năm 0001–9999 |
| Mặc định | Thiếu cả hai → tháng hiện tại và 11 tháng trước, tính theo Asia/Ho_Chi_Minh từ cùng generated_at |
| Cặp/độ dài | Có một phải có cả hai; from <= to; 1–12 tháng inclusive |
| Biên | Đầu ngày đầu tháng from inclusive; đầu ngày đầu tháng sau to exclusive |
| Điều kiện paid_at | `start <= paid_at < end AND paid_at <= generated_at` |
| Bucket | Đủ mọi tháng tăng dần, zero-fill; tháng tương lai hợp lệ nhưng phần sau cutoff bằng 0 |
| Output thời gian | Biên kỳ/generated_at là ISO UTC kết thúc Z; month là nhãn tháng địa phương |

Chuyển biên địa phương sang instant UTC và so sánh theo ngữ cảnh TIMESTAMP connection đã xác minh; không phụ thuộc timezone browser, named timezone tables hoặc thay timezone môi trường dùng chung. Có test năm nhuận, cực trị năm và biên sau tháng 12/9999; dữ liệu ngoài khả năng TIMESTAMP DB không gây overflow tính kỳ.

Toàn response cần cùng read view cho scope/snapshot/revenue/warning/recent, tránh ownership hoặc số liệu lệch giữa các query khi có cập nhật đồng thời. Query tasks phải chọn thiết kế cụ thể chứng minh được (ưu tiên một statement phù hợp; nếu nhiều query thật sự cần consistent snapshot thì giải thích ranh giới read-only transaction). Không thêm write transaction/lock chỉ để theo khuôn. generated_at là cutoff ngày nguồn; không có as_of do client truyền và không tái dựng trạng thái quá khứ.

### HTTP contract

`GET /api/host/dashboard?from_month=2026-10&to_month=2026-11`

Route name hiện hành `host.dashboard.show`; middleware `auth:sanctum` → `account.active`; QueryService kiểm HOST ACTIVE trước validation/query reporting. Giữ session/cookie Sanctum hiện hành.

Chỉ đọc URL query gốc: không trim/coerce dữ liệu sai, không dùng GET body làm nguồn input. Chỉ hai keys trên; reject unknown keys (kể cả host_id/property_id/timezone), duplicate decoded keys, array/nested keys, chuỗi rỗng/bare key, null-like text hoặc sai format. Không nhận key đã bị PHP chuẩn hóa thành hợp lệ. Thiếu cả hai là mặc định; có key nhưng rỗng không phải thiếu.

| Lỗi | Status/body |
| --- | --- |
| Guest | 401; giữ contract Sanctum/session hiện hành |
| Account BLOCKED/INACTIVE | 403; giữ code/message ACCOUNT_BLOCKED/ACCOUNT_INACTIVE |
| Sai role/direct service không đủ quyền | 403, `HOST_DASHBOARD_FORBIDDEN`, message tiếng Việt an toàn |
| Query sai | 422, `HOST_DASHBOARD_INVALID_QUERY`, message và errors theo field |
| Unexpected | 500, `INTERNAL_SERVER_ERROR`, message an toàn; không SQL/stack trace/secrets |

403 quyền đứng trước 422 kể cả query sai. Field errors: from_month sai/thiếu → from_month; to_month sai/thiếu, đảo kỳ hoặc quá 12 tháng → to_month; unknown key → chính decoded key đó; array của key đã biết → field gốc. Chỉ kiểm liên trường khi cả hai field hợp lệ. Không phản chiếu giá trị input nhạy cảm vào lỗi. Không có resource filter nên không thiết kế 404 riêng hoặc business conflict 409 cho GET này. Nếu thêm property filter sau, phải chốt privacy response giống nhau cho ID không tồn tại và ID Host khác, cùng tests isolation trước khi code.

Success 200, đúng một envelope `{data: ...}`, không pagination/meta phụ. Projection bắt buộc:

```text
data: {
  scope: {properties_count: integer},
  period: {from_month, to_month, start_at, end_at_exclusive},
  report_timezone: "Asia/Ho_Chi_Minh",
  generated_at: ISO UTC,
  snapshot: {rooms_count: integer, pending_bookings_count: integer,
             in_progress_maintenances_count: integer},
  summary: {revenue_total: decimal string scale 2},
  revenue_by_month: [{month: YYYY-MM, revenue_total: decimal string scale 2}],
  recent_bookings: [{id, booking_code, status, created_at,
                     check_in_date, check_out_date,
                     property: {id, name}, room: {id, name}}],
  warnings: {paid_invoices_missing_paid_at_scope_count: integer}
}
```

IDs là canonical decimal strings dương; counts là JSON integers không âm <= 9007199254740991, overflow phải fail rõ thay vì làm tròn. Money dạng `"0.00"`, `"19999999999.98"`, không dấu phân cách/ký hiệu tiền tệ. created_at là ISO UTC; ngày lưu trú YYYY-MM-DD, không tự đổi timezone ngày DATE. Chuỗi code/name chỉ là text. Không trả Customer ID/name/email/phone, note, payment details, Invoice items hoặc Model/relationship thừa. Property/Room names trong recent là giá trị hiện tại, không phải snapshot tên tài chính. Không thêm tổng giá Booking vào recent.

Host không Property: properties_count/snapshot/warning 0, summary `"0.00"`, đủ buckets 0, recent `[]`. Có Property nhưng không Room/Booking/Invoice cũng là 200 hợp lệ, không lỗi. Doanh thu 0 không suy ra không Booking; warning >0 vẫn hiển thị khi revenue 0.

### Backend/Frontend và UI behavior

- Route/Middleware → Controller → raw Validator/typed Request DTO → QueryService → concrete scoped Repository → typed Response DTO/projection → Common HTTP response. DI theo [kiến trúc](../../ARCHITECTURE.md). Validator không query; service truyền facts nếu cần; mapper thuần/allowlist, không authorization/lazy loading. Dùng Symfony Validator/ObjectMapper qua adapters hiện có theo nhu cầu; aggregate DTO có thể dựng trực tiếp, không thêm lớp rỗng.
- `/host` → page/hook → domain service → domain API → shared Axios; projection tại frontend/src/mappers. Không gọi API rải rác hoặc âm thầm fallback/mock production. Không đổi router/layout/AuthContext/Axios trong scope hiện tại.
- Bốn thẻ có nhãn thời gian riêng theo semantics snapshot; revenue chart có bảng dữ liệu tương đương, kỳ đã áp dụng/timezone/generated_at; recent hiển thị code/status/cơ sở/phòng/ngày tạo/ngày lưu trú. Giữ lối vào Invoice/Review hiện có; không thêm mutation từ Dashboard.
- Hai input tháng có label, Áp dụng, Đặt lại về mặc định server, Tải lại/Thử lại. Draft inputs không thay nhãn kỳ kết quả đã trả. Snapshot/recent độc lập kỳ nhưng được tải lại cùng response, nhãn không ngụ ý bị lọc kỳ.
- Loading/refresh không hiển thị dữ liệu sai request; error có retry, field errors rõ cho 422; 401 qua Auth pipeline hiện có, 403 ngừng hiển thị dữ liệu không còn quyền. Không biến HTTP/network/timeout/500/malformed payload thành empty. Nếu 404/409 bất ngờ xuất hiện thì giữ status để trình bày lỗi an toàn, không coi là kết quả rỗng.
- Empty riêng: không Property có lối vào Properties hiện có; chưa Booking; không revenue trong kỳ; warning thiếu ngày ghi rõ toàn phạm vi Host, không phân bổ kỳ. Raw `"0.49"` không phải empty dù text VND là 0 ₫.
- AbortController + request identity + Auth-version guards: thay kỳ/reload/rời route/logout/account switch phải invalidate ngay; transport bỏ qua abort vẫn không được ghi kết quả cũ. Không render cache của Host A khi chuyển Host B. Property-change race chỉ bổ sung nếu filter được duyệt sau.
- Tiếng Việt, responsive mobile/tablet/desktop, keyboard/focus/labels, zoom 200%, tiền dài không tràn. CSS namespace `host-dashboard`, không uc39-*; không dangerouslySetInnerHTML với dữ liệu API.
- [formatVnd](../../frontend/src/utils/formatVnd.js) giữ raw scale 2, format Intl.NumberFormat vi-VN và half-up bằng BigInt: `"300.76"` → 301 ₫; `"19999999999.98"` → 20.000.000.000 ₫. Format mỗi giá trị gốc độc lập; không cộng text đã làm tròn, không tính lại tổng tài chính frontend. Chart ratios không lấy từ text VND đã làm tròn.

## Implementation và nghiệm thu

| Lớp | Source |
| --- | --- |
| HTTP | [Route](../../backend/routes/api/dashboard.php), [Controller](../../backend/app/Http/Controllers/Dashboard/HostDashboardController.php), [ErrorCode](../../backend/app/Exceptions/ErrorCode.php), [Exception Handler](../../backend/app/Exceptions/GlobalExceptionHandler.php) |
| Validation/input | [Raw DTO](../../backend/app/DTOs/Dashboard/Requests/HostDashboardQueryInputRequest.php), [Validator](../../backend/app/Validators/Dashboard/HostDashboardValidator.php), [Typed DTO](../../backend/app/DTOs/Dashboard/Requests/HostDashboardQueryRequest.php) |
| Query/persistence | [QueryService](../../backend/app/Services/Dashboard/HostDashboardQueryService.php), [Repository](../../backend/app/Repositories/Dashboard/HostDashboardRepository.php), [Period](../../backend/app/DTOs/Dashboard/HostDashboardPeriod.php) |
| Response | [Overview DTO](../../backend/app/DTOs/Dashboard/Responses/HostDashboardResponse.php), [Snapshot DTO](../../backend/app/DTOs/Dashboard/Responses/HostDashboardSnapshotResponse.php) |
| Frontend domain | [API](../../frontend/src/api/hostDashboardApi.js) → [Service](../../frontend/src/services/hostDashboardService.js) → [Mapper](../../frontend/src/mappers/hostDashboardMapper.js); shared Axios giữ nguyên |
| UI | [Page](../../frontend/src/pages/host/HostDashboardPage.jsx), [CSS](../../frontend/src/pages/host/HostDashboardPage.css), [Chart](../../frontend/src/components/dashboard/HostDashboardRevenueChart.jsx), [VND helper](../../frontend/src/utils/formatVnd.js) |
| Entrypoint có sẵn | [Router](../../frontend/src/routes/AppRouter.jsx), [Host layout](../../frontend/src/layouts/HostLayout.jsx); CLOSE chỉ đọc |
| QA | [MySQL runner](../../backend/tests/Support/RunUc39MySqlTests.ps1), [Browser runner](../../backend/tests/Support/RunUc39Browser.ps1), [DATA tests](../../backend/tests/Feature/Dashboard/Uc39DashboardDataMySqlTest.php), [HTTP tests](../../backend/tests/Feature/Dashboard/Uc39HostDashboardHttpMySqlTest.php), [Node tests](../../frontend/tests/hostDashboard.test.js) |

Các task dưới đây đều được owner nghiệm thu trên chat ngày **2026-11-21**. Các task foundation không được coi là đã có full API/UI tại checkpoint của chúng.

| Task UC39 | Kết quả / phạm vi |
| --- | --- |
| CONTRACT-01 | Contract tài liệu; không phải test chức năng |
| ENV-01 | Guards, MySQL QA cô lập và cleanup |
| QUERY-SNAPSHOT-01 | Snapshot query và DTO |
| QUERY-REVENUE-01 | Period, revenue/overview cùng read view |
| CORE-PREP-01 | Raw/typed DTO, validator, permission helper query-free |
| CORE-01 | HTTP endpoint, domain frontend và UI tối thiểu |
| UI-01 | Filter/chart/table; automated/SSR/static PASS |
| QA-DATA-01 | MySQL oracle, kernel HTTP, mapper bridge/no-write |
| QA-UI-01 | Real HTTP/browser QA; technical PARTIAL, owner chấp nhận giới hạn |
| CLOSE-01 | Technical PASS hồ sơ, owner nghiệm thu đóng UC39 TV3 |

## Oracle và DATA QA

Oracle contract ban đầu là ví dụ thiết kế, không phải run QA: cutoff `2026-11-21T05:00:00Z`, kỳ 10–11/2026, biên UTC `[2026-09-30T17:00:00Z,2026-11-30T17:00:00Z)`. Hai Property/3 Room nhiều trạng thái, pending=2, maintenance=2 cùng Room, recent `[104,103,102,105,106]`. Bản ghi đúng cutoff được gồm; sau một giây bị loại. Pending cũ vẫn là backlog.
Invoice oracle: PAID 100.25 đầu kỳ + 200.50 đầu tháng 11 + 0.01 đúng cutoff = **300.76**. Future PAID999.99/REFUNDED500/UNPAID400 loại; PAID-null300 tăng warning1. Refund200.50 → summary100.26, tháng11 còn0.01; dữ liệu HostB không đổiA. Hai header9999999999.99 →19999999999.98, items/services không nhân tổng.

DATA fixture đã chạy dùng cùng cutoff cố định; mỗi scenario có 2 Property ACTIVE/BLOCKED và 2 Room ACTIVE/INACTIVE. Đây là reporting snapshots trực tiếp, không chứng minh workflow cho phép mọi tổ hợp Booking/Invoice.

| Scenario | Host | October | November | Total | Warning | Pending |
| --- | --- | --- | --- | --- | --- | --- |
| Normal | 1 | 100.25 | 200.51 | 300.76 | 1 | 2 |
| Zero | 2 | 0.00 | 0.00 | 0.00 | 0 | 0 |
| BookingOnly | 3 | 0.00 | 0.00 | 0.00 | 0 | 2 |
| RevenueOnly | 4 | 100.25 | 200.50 | 300.75 | 0 | 0 |
| MissingDate | 5 | 0.00 | 0.00 | 0.00 | 1 | 0 |
| Large | 6 | 0.00 | 19999999999.98 | 19999999999.98 | 0 | 0 |
| Fractional | 7 | 0.00 | 0.49 | 0.49 | 0 | 0 |

Normal: 2 IN_PROGRESS histories cùng Room, mọi created_at ties → recent `[1007,1006,1005,1004,1003]`. Host8 có revenue9999.99 riêng, Host9 không Property, Host10 hai Property/0 Room. RefundI1001 → A200.51; chuyển Property101 sang Host8 sau khi foreign Invoice chỉnh1.00 → A revenue/warning/recent rỗng, B201.51/warning1.
11 DATA cases gồm 7 scenario, empty/noRoom, foreign/refund/current-owner, period/default/+07 và future Room ancestor. Suite Data gồm 9 files: ENV76 + Snapshot30 + Revenue36 + Validator75 + CORE41 + DATA11 =269 cases. Một reporting SELECT, query log chỉ SELECT, 9 QA-table hashes khớp; 7 actual kernel HTTP JSON qua production frontend mapper deep-equal literal projection.
Boundary controls: cuối tháng10 tại16:59:59Z gồm,17:00:00Z loại; Feb2024 UTC `[2024-01-31T17:00:00Z,2024-02-29T17:00:00Z)`; default12/2025–11/2026,13 tháng422, futureDec buckets0. Các foundation suites giữ extreme year/large IDs, isolation, multiplication, status/date và PII controls; không claim concurrent-writer stress benchmark.

## HTTP và browser QA

Positive run **932b01b9df824a88bd7fede5b6d37fff**, PHP8.2.12/MySQL8.4.13: frontendlocalhost:57437 → backend57436; schema `stayhub_uc39_test_932b01b9df824a88bd7fede5b6d37fff`, account `uc39_932b01b9df824a88`, server UUID `b4736273-e223-11f1-a722-aaf8fde9dbf9`. Source probe/manifest/target/grants đúng trước fixtures; runtime months điều chỉnh theo lúc chạy, khác DATA fixed-cutoff context.
178 HTTP status probes, **60 oracle comparisons trên 10 HOST** (mỗi Host6), tất cả matched. Cookie jar riêng từng actor; guest/logout401, missing/wrong XSRF419, CUSTOMER/ADMIN login200 nhưng dashboard403 HOST_DASHBOARD_FORBIDDEN và `/api/me` vẫn200; blocked/inactive HOST login403. Sau logout không còn authenticated session.

| ID | Kết quả | Quan sát trên positive run |
| --- | --- | --- |
| B01 | PASS | Guest `/host` về login với role=host và returnTo; login Host A vào Dashboard thật |
| B02 | PASS | Default 2025-12..2026-11, scope2/rooms2/pending2/maintenance2; raw300.76 hiển thị301 ₫, Oct100 ₫/Nov201 ₫; warning1, recent1007..1003, không PII |
| B03 | PASS | Native month input bằng keyboard; draft không đổi kỳ đang hiển thị. Apply Oct..Nov2026 đúng nhãn/tổng/bảng; Reset bỏ cả hai tháng về server default |
| B04 | PASS | Thiếu tháng, đảo kỳ Nov..Oct, Nov2025..Nov2026 quá12tháng đều báo lỗi; focus tới field lỗi, data/kỳ cũ không đổi |
| B05 | PASS | Dec2026 tương lai: bảng0/empty revenue, snapshot/warning vẫn hiện tại; không lọc recent theo kỳ |
| B06 | PASS | Pause owned backend, Tải lại báo lỗi kết nối, không giữ metrics cũ; resume/Thử lại thành công với đúng Dec2026 đã áp dụng |
| B07 | PASS có giới hạn | Apply rồi Reset nhanh cuối cùng default12tháng. Chưa tạo deferred success/error hoặc transport cố ý bỏ qua abort trong browser; không claim chứng minh toàn race matrix |
| B08 | PASS | Logout Host A rồi Host B: B chỉ thấy DATA-8001/Cơ sở801 và10.000 ₫; không còn DATA-100x. Refresh giữ đúng session/actor/default |
| B09 | PASS | Zero: doanh thu0 và empty; booking-only: pending2/recent3002,3001 dù revenue0; revenue-only:301 ₫/pending0 |
| B10 | PASS | Missing-date: revenue0, warning1; Large raw19999999999.98 hiển thị20.000.000.000 ₫; Fractional0.49 hiển thị0 ₫ nhưng **không** hiện empty revenue |
| B11 | PASS | Empty Host: scope0/rooms0 và thông báo chưa có cơ sở/đơn; no-rooms Host: scope2/rooms0, không dùng cùng thông báo chưa có cơ sở |
| B12 | PASS | Link Quản lý hóa đơn → `/host/invoices`, Back → Dashboard; Xem đánh giá → `/host/reviews`, Back → Dashboard. Chỉ kiểm điều hướng, không nghiệm thu workflow của các trang đích |
| B13 | PASS | 1440×1000,768×1024,390×844: screenshot/DOM không page overflow (scrollWidth1425/753/375), card/form/chart xếp lại; large total trong mobile content bounds |
| B14 | PASS cơ bản | Apply Tab sang Reset có outline; field errors có focus; chart có bảng tháng/caption/headers, region nhận keyboard focus. Chưa screen reader audit |
| B15 | NOT VERIFIED | 200% browser zoom: Control+Equal không đổi devicePixelRatio/viewport trên IAB; không dùng CSS transform hoặc viewport nhỏ thay bằng zoom evidence |
| B16 | PASS | Fresh mobile reload mở menu tài khoản và logout thật, về login/không còn dữ liệu bảo vệ; reset viewport trước kết thúc |

Tooling checkpoints không phải product defects: month.fill bị native/React reset nên dùng segmented keyboard; press Tab trên paragraph/heading không focusable lỗi; menu helper click quá sớm ở mobile chưa tìm được logout. Fresh reload/mở menu bằng AX đã logout thành công. Không sửa shared menu. Screenshots nằm trong tool trace; không hứa artifact ảnh đã lưu Git/runtime.
Chưa browser malformed/500 injection, screen reader audit hoặc controlled delayed success/error với transport bỏ qua abort; Node deferred tests/SSR là evidence riêng. Không dùng viewport nhỏ/CSS transform thay zoom200% evidence.
HTTP/browser audit18 bảng nghiệp vụ khớp, authenticated sessions0, whole-dev/business hashes và counts khớp. Backend23328 pause/resume18812, Vite6492 đã dừng, ports57436/57437 released, private credentials xóa. Final independent metadata cả7 runs:0 schema/user/connection, cleanup_failure=null. Không claim hardkill/Docker outage recovery.

## Ledger và hồi quy

Các kết quả dưới đây là lịch sử, không chạy mới trong CLOSE/cleanup02A. Artifacts local/ignored dưới `.agent-workspace/tv3/`; SQLite memory đã xác minh PRAGMA không file backing. Skips opt-in không là MySQL PASS; Node mocks/SSR/build không thay browser. Lint0 errors/3 existing Fast Refresh warnings ở badge/button/sidebar; build bundle>500kB warning giữ nguyên.

| Task | Kết quả lịch sử (passed/assertions, skip ghi riêng) |
| --- | --- |
| CONTRACT | C39-C01–C07 PASS tài liệu, không code/QA |
| ENV | Pure71/87; MySQL final76/123, checkpoint trước drift75/120; SQLite2523/10927/414skip. Opt-out5 smoke skipped không phải functional PASS |
| SNAPSHOT | Pure25/47; MySQL106/213, checkpoint đầu106/208; SQLite2548/10974/419skip |
| REVENUE | Pure56/103 (31 revenue+25 snapshot); MySQL142/338, checkpoint142/333; SQLite2579/11030/424skip |
| CORE-PREP | Pure131/499 (75 mới+56 accepted); SQLite2654/11426/424skip; không MySQL mới |
| CORE | MySQL258/968; SQLite2654/11435/465skip; Node370 gồm56 CORE mới; actual HTTP JSON→mapper bridge PASS |
| UI | Node final423, checkpoint422; post-PR17 baseline419, thêm4 UI cases. Actual JSX→SSR/lint/build PASS; không browser tại checkpoint này |
| QA-DATA | MySQL269/1164/0skip; SQLite2685/11533/476skip; Node423; 7 mapper bridges |
| QA-UI | 178 HTTP probes/60 comparisons/10HOST; new guards38/40; SQLite2723/11573/476skip; Node423; lint/buildexit0 |
| CLOSE | Technical PASS tài liệu scope3;177 links/anchors,1008 protected files unchanged; owner accepted |

Artifact roots: ENV `uc39-env-01`, Snapshot `uc39-query-snapshot-01`, Revenue `uc39-query-revenue-01`, PREP `uc39-core-prep-01`, CORE `uc39-core-01`, UI `uc39-ui-01`, DATA `uc39-qa-data-01`, browser `uc39-qa-ui-01/runs`. Summary DATA76db85e717bd4d028d82af99355e6ece, QAUI30ff1c0032e04a458772cf553b4e778c, UI902ce07c800d4065a6690b9c9f514822, CLOSE78cf45f49c684fa2a3941ef224c4021a; acceptance707b0a00afc44f63816b8980ff01bb59. Không cộng counts qua các checkpoint.

### Positive và negative runs foundation

**ENV** — mã run và kết quả gốc:

| Kiểm tra | Kết quả | Run / giới hạn |
| --- | --- | --- |
| `pwsh -NoProfile -File backend/tests/Support/RunUc39MySqlTests.ps1 -Provision` | Exit 0, cuối 76 passed/123 assertions/0 skipped | MySQL Docker; run `ddeeb636e49148e7b2d434d6862ecad6` |
| Positive trước bổ sung drift test | Exit 0, 75 passed/120 assertions | Run `5c08e53fdb63457fa9a8d377ed033751`; giữ evidence gốc |
| Runner `-Provision -InjectFailureAfter AfterSchema` | Exit 1 đúng `UC39_EXPECTED_FAILURE_AFTER_SCHEMA` | Run `375236f01f8c4e33b21b2f931f413669`; cleanup PASS |
| Runner `-Provision -InjectFailureAfter Collision` | Exit 1 đúng `UC39_EXPECTED_COLLISION_REJECTED`; schema collision owned không đổi | Run `964fa4aa307e47f4b0e5a50533ee3f5e`; cleanup PASS |
| Runner `-Provision -InjectTestFailure` | Exit 1; 1 expected failed/74 passed/120 assertions; marker UC39_EXPECTED_TEST_FAILURE_AFTER_WRITE, probe tiếp theo không leak | Run `df0c8c898bb547998b4d6e023151b8e3`; cleanup PASS |
| Runner không `-Provision` | Exit 1, Explicit -Provision is required; không mở DB | `4271b0394bb946d1a0d6d34001da2489/no-opt-in-result.json` |
| `php artisan test --no-ansi --do-not-cache-result` từ backend | Exit 0, 2523 passed/10927 assertions/414 skipped | SQLite :memory:; `4271b0394bb946d1a0d6d34001da2489/backend-regression-final.txt` |

**SNAPSHOT** — mã run và kết quả gốc:

| Kiểm tra | Kết quả | Run / giới hạn |
| --- | --- | --- |
| `pwsh -NoProfile -File backend/tests/Support/RunUc39MySqlTests.ps1 -Provision -Suite Snapshot` | Cuối 106 passed/213 assertions/0 skipped, exit 0 | Final run `c26f69f9b6234cbe89eab239740f71e8` |
| Positive checkpoint trước refine fixture IDs | 106 passed/213 assertions, exit 0 | `50970e4516d7475eaffc8f66c74eabd6` |
| Positive đầu, trước thêm engine/exact Maintenance cutoff assertions | 106 passed/208 assertions, exit 0 | `87292b5a2e384493a07441563d7db652`; giữ count gốc |
| Runner `-Provision -Suite Snapshot -InjectTestFailure` | Exit 1, 1 expected failed/105 passed/213 assertions | `f1d6d56b89cc497a8d9bc7bb5a4b8fd2`; marker UC39_EXPECTED_TEST_FAILURE_AFTER_WRITE, cleanup đạt, result.passed=false giữ nguyên |
| Runner `-Suite Snapshot` thiếu Provision; invalid suite ArbitraryPath | Cả hai exit 1 như kỳ vọng trước DB | `bb0d24b7051447269588a34eca64f4d0/runner-guard-controls.json` |
| `php artisan test --no-ansi --do-not-cache-result` từ backend | 2548 passed/10974 assertions/419 skipped, exit 0 | SQLite :memory:, actual connection/PRAGMA preflight; `bb0d24b7051447269588a34eca64f4d0/backend-regression.txt` |

**REVENUE** — mã run và kết quả gốc:

| Kiểm tra | Kết quả | Run / giới hạn |
| --- | --- | --- |
| `pwsh -NoProfile -File backend/tests/Support/RunUc39MySqlTests.ps1 -Provision -Suite Revenue` | Final 142 passed/338 assertions/0 skipped, exit 0 | Run `d97bfbf553b74b70b4dcc673607de4f2` |
| Positive checkpoint trước bổ sung UNPAID còn ngày/only-warning/no-Invoice cases | 142 passed/333 assertions, exit 0 | Run `c65e48a4458e41ecb44b661a1913a9f7`; giữ count gốc |
| Runner `-Provision -Suite Revenue -InjectTestFailure` | Exit 1, 1 expected failed/141 passed/338 assertions | `2374ff2ab3a54f15823105769e4b3ae0`; UC39_EXPECTED_TEST_FAILURE_AFTER_WRITE, cleanup đạt, result.passed=false |
| Thiếu Provision / suite ArbitraryPath | Cả hai exit 1 trước DB, đúng guard | `ddebd9c3ff8143fc8b349643b5de1af0/runner-guard-controls.json` |
| `php artisan test --no-ansi --do-not-cache-result` từ backend | 2579 passed/11030 assertions/424 skipped, exit 0 | SQLite :memory: sau kiểm actual connection + PRAGMA database_list; `ddebd9c3ff8143fc8b349643b5de1af0/backend-regression.txt` |

**CORE** — mã run và kết quả gốc:

| Kiểm tra | Kết quả | Run / giới hạn |
| --- | --- | --- |
| `pwsh -NoProfile -File backend/tests/Support/RunUc39MySqlTests.ps1 -Provision -Suite Core` | Final 258 passed/968 assertions/0 skipped, exit 0 | MySQL Docker 8.4; run 386f7559fb44486ea11c0f335d55658e |
| Positive checkpoint trước lưu synthetic HTTP response artifact | 258 passed/968 assertions, exit 0 | f350b22984b4465297da52290dd97c1f |
| Core runner với `-InjectTestFailure` | Exit 1, 1 expected failure/257 passed/968 assertions | 9c0f0321c52a4371b7e138290c765b61; only UC39_EXPECTED_TEST_FAILURE_AFTER_WRITE; result.passed=false |

### DATA và browser run ledger

| Run | Result | Dev fingerprints | Cleanup |
| --- | --- | --- | --- |
| 703fe71ed78b4f7486c9ef3e89879dab | FAIL overall, 269 tests passed/1164 assertions; result.passed=false | Whole-dev khác; 18 business tables khớp | Resources 0/0/0; cleanup_failure giữ mismatch |
| 60a5355e6c604ae4ae30125eb969ac7a | PASS, 269 passed/1164 assertions/0 skipped, exit0 | Whole-dev/business khớp | 0 schema/user/connections |
| d841d4f1c51f4dfb854cf13ed9ab655b | Expected-negative exit1, 1 failed/268 passed/1164 assertions; result.passed=false | Whole-dev/business khớp | 0 schema/user/connections; cleanup_failure=null |

| Run | Verdict | Diễn giải |
| --- | --- | --- |
| ad24a014fc194a64b1271518eca08c89 | EXPECTED FAIL | Inject sau Prepare; exit1, cleanup_failure=null, owned resources0 |
| 610563a519ad4e6581548aeadcfa60c2 | FAIL | Audit fixture dùng id cho room_amenities composite key; setup JSON parse failure, đã sửa harness |
| bfb98b5521514cacbfd855596a17ba7a | FAIL | Oracle date serialization qua PowerShell mất zero microseconds; giữ failure |
| 3ddcbd85cef04d2881f57ae0394e0164 | FAIL | Cùng date mismatch, verify-failure giữ expected/actual; sửa bằng native JSON facts |
| 0cb78c5c57c34659b5a8ab3ba21a578e | FAIL | HTTP status/oracle qua nhưng users business audit đổi do password rehash; thay fixture hasher, không sửa auth production |
| 2d2fa9a467e545b784588575ad281c3d | EXPECTED FAIL | Inject sau Ready; exit1, cleanup_failure=null, processes/ports/resources sạch |
| 932b01b9df824a88bd7fede5b6d37fff | PASS runner | Positive HTTP + browser lease/logout + no-write/cleanup; không đồng nghĩa tất cả browser AC PASS |

CORE1fe8cecfed4743cf851a7aacfa50bbd2: 41 fixture failures thiếu customer_phone,217 passed/734 assertions; whole-dev mismatch nhưng18 business hashes/counts khớp. Quan sát sessions thay đổi rồi owner ngừng dev; không quy toàn lịch sử cho sessions/restore dev. e4d950351c2a4be889e0c963e182f75a: Auth guard cache harness failure,257passed/959assertions. Wrapper cp1252 UnicodeEncodeError sau PHPexit0 được giữ riêng, không giả wrapper PASS.
DATA first run269/1164 assertions PASS nhưng overallFAIL do whole-dev mismatch, business hash khớp. Owner ngừng dev trước hai run sau, whole-dev/business khớp; final3run metadata0/0/0. QAUI chỉ fix composite-key audit/dateJSONmicroseconds/application hasher trong harness, không Auth production; `fixture-native.json` giữ microseconds. Failed/negative `result.passed=false` giữ nguyên dù cleanup PASS. Run sớm chỉ có wholehash không được suy thêm businesshash độc lập.
ENV/Snapshot/Revenue/CORE positive và expected-negative đã kiểm owned target/grants, rollback/no-leak, source/env và cleanup; AfterSchema/Collision/AfterWrite đều đúng marker. Collision resource owned không đổi; missingProvision/invalidSuite reject trướcDB. Controlled failure không chứng minh hardkill recovery.

## AC và giới hạn bàn giao

| Contract AC | Technical status và evidence | Owner |
| --- | --- | --- |
| UC39-AC01 | PASS: unit/kernel HTTP/MySQL quyền và error order; real HTTP session/CSRF | Đã nghiệm thu |
| UC39-AC02 | PASS: nhiều Property, Host A/B, empty/noRoom; oracle và browser không rò chéo | Đã nghiệm thu |
| UC39-AC03 | PASS: status/date controls, Pending backlog, hai Maintenance cùng Room, browser metrics | Đã nghiệm thu |
| UC39-AC04 | PASS: Invoice header/PAID/null/future/refund/large/noInvoice, không nhân tổng | Đã nghiệm thu |
| UC39-AC05 | PASS: raw URL/default/range/timezone/boundaries/leap/extreme/cutoff/zero-fill; browser filter | Đã nghiệm thu |
| UC39-AC06 | PASS: recent limit/order/ties, projection không PII, IDs/counts/decimal types | Đã nghiệm thu |
| UC39-AC07 | PASS trong query scope: một SELECT/InnoDB/read view, sum invariant; không stress benchmark | Đã nghiệm thu |
| UC39-AC08 | PASS: no-write/owned target/cleanup/positive fingerprints; failed checkpoints giữ trong ledger | Đã nghiệm thu |
| UC39-AC09 | PASS: production API/service/mapper, Node malformed/error/empty, real JSON bridge và browser states | Đã nghiệm thu |
| UC39-AC10 | PARTIAL browser: Node deferred ignore-abort PASS; browser rapid Apply/Reset, Back/refresh/actor/logout đã quan sát; controlled delay chưa xác minh | Chấp nhận giới hạn |
| UC39-AC11 | PASS trong matrix B01–B12/B14/B16: states/labels/links/chart/table/VND; không claim browser500 injection | Đã nghiệm thu UI |
| UC39-AC12 | PARTIAL: ba viewport/keyboard/long money và regression PASS; zoom200% NOT VERIFIED | Chấp nhận giới hạn |

Task ACs: C39-C01–07, ENV01–07, SNAP01–07, REV01–08, PREP01–03, CORE01–06, UI01–06, DATA01–06, CLOSE01–06 PASS trong scope/engine đã ghi; UI05 ban đầu chỉ static/render. QAUI01–03/06 PASS, QAUI04/05 PARTIAL. Owner không yêu cầu kiểm bổ sung; acceptance không chuyển AC10/12 thành technical PASS không điều kiện.
T01–T03 vẫn **BLOCKED TV2** theo [checklist](TV3_CHECKLIST.md#5-dependency-tích-hợp-còn-mở). Không claim screen reader/WCAG, stress benchmark hoặc Booking→Invoice/Review production end-to-end. Nếu artifact local không còn khi đổi máy, bàn giao logs hoặc lập task tái QA; không gán audit mới từ bản ghi cũ.

## Hướng dẫn tái kiểm khi được giao riêng

- MySQL từ root: `pwsh -NoProfile -File backend/tests/Support/RunUc39MySqlTests.ps1 -Provision -Suite Data`; negative thêm `-InjectTestFailure`. Suite allowlist Environment/Snapshot/Revenue/Core/Data, không nhận path tùy ý.
- Browser: `pwsh -NoProfile -File backend/tests/Support/RunUc39Browser.ps1 -Provision -KeepAlive -MaxMinutes 30`. Chỉ existing healthy Compose MySQL; không tạo instance/volume. Guard kiểm effective Laravel connection/identity/grants trước migration/fixture; QA account không truy cập dev.
- Runtime local/database sessions, array cache, sync queue, null log; cookie/key/password/ports owned. Không nới API testing guard. Password/key qua child environment/private file ignored, không argv/evidence; xóa khi finally.
- Đối chiếu ready/manifest/source/API/actor/schema/run trước manual. Logout mọi tab trước tạo stop.request đúng run; pause.backend chỉ dừng owned backend, xóa flag để resume cùng environment/port. Lease hữu hạn, finally dừng processes/ports rồi cleanup đúng owned resources và kiểm fingerprints.
- Không kill runner cưỡng bức, reset/seed dev hoặc sửa env. Hướng dẫn trên không là lệnh đã chạy trong cleanup02A; không còn QA server/schema được bàn giao đang chạy.

### Chỉ mục checkpoint bổ sung

Các mã sau được giữ để truy nguồn các checkpoint trong hồ sơ cũ; không gán thêm verdict hoặc coi là lượt kiểm mới. Mã 40 ký tự là commit baseline, mã 32 ký tự là run/evidence. Backup nguyên văn và content-map nằm trong artifacts cleanup 02A.

| Nguồn cũ | Commit/run chưa nêu ở ledger chính |
| --- | --- |
| UC39_ACCEPTANCE_HANDOVER.md | `78cf45f49c684fa2a3941ef224c4021a`, `902ce07c800d4065a6690b9c9f514822` |
| UC39_DATA_QA.md | `76db85e717bd4d028d82af99355e6ece` |
| UC39_HOST_DASHBOARD_CONTRACT.md | `808c581c556975f2a283bbb6345c54bdde7b006c` |
| UC39_UI_QA.md | `30ff1c0032e04a458772cf553b4e778c` |
| TV3_CHECKLIST.md | `0acbd528614b4a5d8421abfb4a1feb3f`, `1fe8cecfed4743cf851a7aacfa50bbd2`, `902ce07c800d4065a6690b9c9f514822`, `a92cad01a88b4ba4aacbd2c64b72a582` |
