# UC48 — Contract thống kê hệ thống V1

Ngày: 2026-11-19 (Asia/Bangkok). Owner: TV3. Task: **UC48-CONTRACT-01**.

Owner đã duyệt task và các khuyến nghị V1 của Mini Plan trên chat. Tài liệu này cụ thể hóa contract để triển khai sau; chưa có API/UI UC48 hoạt động, chưa chạy QA và chưa nghiệm thu chức năng UC48. Việc duyệt triển khai tài liệu không đồng nghĩa owner đã nghiệm thu bản tài liệu được tạo.

## 1. Nguồn và baseline

- [Kiến trúc](../../ARCHITECTURE.md), [hướng dẫn frontend](../ai-frontend-development-guide.md), [use case](../stayhub-usecase-system.md), [phân công TV3](../stayhub-team-work-assignment.md), [ERD](../stayhub-erd.md).
- [UC47 đã nghiệm thu](UC47_ACCEPTANCE_HANDOVER.md), [UC45](UC45_ACCEPTANCE_HANDOVER.md), [giới hạn tích hợp UC36/TV2](UC36_TV2_HANDOVER.md).
- Baseline khi bắt đầu task: `feature/tv3-uc47-admin-dashboard`, HEAD `5ee09a0f80c2a3d1e90a82581a61c61767fd89bf`, working tree sạch.
- Lần fetch trong task lập kế hoạch trước: `dev`/`origin/dev` ở `0a26cf150988afa4b7d2af35760c9f92fdbcdaf3`; UC47 chưa là ancestor của dev, dev thiếu route/query dashboard và trang Admin còn là khung. Đây là kết quả lần kiểm tra đó, không phải cam kết trạng thái remote luôn giữ nguyên.
- UC47 được nghiệm thu trên nhánh riêng khác với tích hợp vào dev. Task tích hợp phải được duyệt riêng; CONTRACT-01 không merge/cherry-pick/rebase hoặc sửa dev.

## 2. Phạm vi V1 và khác biệt với UC47

Actor là ADMIN ACTIVE, xem thống kê toàn hệ thống trong kỳ tháng được chọn. Hai nhóm chỉ tiêu:

1. Số Booking được tạo trong kỳ, phân bố theo sáu trạng thái hiện tại.
2. Doanh thu từ Invoice hiện tại PAID có ngày thanh toán hợp lệ, phân bố theo tháng thanh toán.

UC47 giữ nguyên sáu KPI toàn thời gian và biểu đồ doanh thu 12 tháng cố định. UC48 có kỳ báo cáo riêng, không thay đổi API, semantics, DTO hoặc mapper UC47. Không dựng lại các KPI users/hosts/properties/rooms.

V1 chỉ có dimension tháng, không có filter Host/property/status, export Excel/PDF, comparison, realtime, drill-down, công suất phòng, số lượt lưu trú, sổ dòng tiền hay báo cáo lịch sử bất biến. Không tạo/sửa Booking, Invoice, payment status hoặc dữ liệu nghiệp vụ khác.

## 3. Kỳ báo cáo và thời gian

| Quy tắc | Contract |
| --- | --- |
| Query | `from_month`, `to_month`, chuỗi đúng `YYYY-MM` |
| Định dạng | Năm bốn chữ số `0001`–`9999`, tháng `01`–`12`; không trim hoặc ép array/object/số thành chuỗi hợp lệ |
| Kỳ mặc định | Nếu thiếu cả hai: tháng hiện tại và 11 tháng trước, theo `Asia/Ho_Chi_Minh` |
| Cặp tham số | Nếu có một thì bắt buộc có cả hai; chuỗi rỗng/null không được coi là thiếu |
| Thứ tự/độ dài | `from_month <= to_month`; từ 1 đến 12 tháng, tính cả hai đầu |
| Timezone | Cố định `Asia/Ho_Chi_Minh`, client không truyền timezone |
| Mốc bắt đầu | 00:00:00 ngày đầu `from_month`, bao gồm |
| Mốc kết thúc | 00:00:00 ngày đầu tháng sau `to_month`, loại trừ |
| Cutoff | Một `generated_at` UTC được chụp cho cả request, sau kiểm quyền; nguồn ngày phải `<= generated_at` |
| Tháng tương lai | Được phép chọn trong giới hạn 12 tháng; phần sau cutoff không có số liệu, vẫn trả bucket 0 |

Điều kiện chung cho thời điểm nguồn `t`: `start <= t < end AND t <= generated_at`. Các tháng trong response tăng dần, đủ mọi tháng của kỳ kể cả tháng không có dữ liệu. Kỳ và mặc định cùng được tính từ một mốc thời gian bất biến cho request.

Backend chuyển biên giờ Việt Nam sang các instant UTC và truy vấn đúng ngữ cảnh TIMESTAMP của connection. Không so chuỗi UTC với giờ connection một cách ngầm định, không phụ thuộc timezone trình duyệt, không mutate timezone của môi trường dùng chung. JSON biên kỳ dùng ISO UTC; `from_month`/`to_month` vẫn là nhãn tháng địa phương.

Cutoff chỉ giới hạn ngày nguồn; không tái dựng trạng thái dữ liệu tại một thời điểm quá khứ. Không có tham số `as_of` do client điều khiển. Ngày nguồn ngoài khả năng lưu của DB không có bản ghi, không được gây overflow khi tính biên kỳ (gồm tháng 12 năm 9999).

## 4. Định nghĩa chỉ tiêu và nguồn dữ liệu

| Chỉ tiêu | Nguồn và điều kiện | Kiểu output |
| --- | --- | --- |
| `bookings_count` | COUNT bản ghi `bookings` hiện tồn tại, áp kỳ/cutoff vào `created_at`, mọi trạng thái | Integer không âm |
| `booking_status_counts` | Cùng tập Booking trên, đếm theo `status` hiện tại | Sáu hàng `{status,count}` |
| `revenue_total` | SUM `invoices.total_amount` với `payment_status = PAID`, `paid_at IS NOT NULL`, áp kỳ/cutoff vào `paid_at` | Decimal string scale 2 |
| `monthly[].bookings_count` | Booking theo tháng địa phương của `created_at` | Integer không âm |
| `monthly[].booking_status_counts` | Phân bố trạng thái hiện tại của Booking được tạo trong tháng đó | Sáu hàng `{status,count}` |
| `monthly[].revenue_total` | Doanh thu theo tháng địa phương của `paid_at` | Decimal string scale 2 |
| `warnings.paid_invoices_missing_paid_at_global_count` | COUNT Invoice hiện tồn tại, hiện PAID nhưng `paid_at IS NULL`, trên toàn hệ thống | Integer không âm |

Thứ tự trạng thái cố định: `PENDING`, `CONFIRMED`, `CHECKED_IN`, `COMPLETED`, `CANCELLED`, `REJECTED`. Luôn trả đủ sáu hàng, kể cả count 0.

### Booking

- Ngày tạo Booking không đồng nhất với `check_in_date`, `check_out_date`, ngày check-out thực tế hoặc ngày thanh toán Invoice.
- Không lọc theo trạng thái tài khoản Customer/Host, Property hoặc Room. Đây là thống kê bản ghi hiện tồn tại toàn hệ thống.
- Booking đổi trạng thái sau kỳ báo cáo sẽ đổi phân bố trạng thái khi xem lại kỳ đó; ngày tạo vẫn quyết định bucket.
- Chưa có lịch sử transition để trả “trạng thái tại cuối kỳ”; không tự suy từ `updated_at` hoặc ngày lưu trú.

### Doanh thu

- Chỉ đọc tổng cuối đã lưu trên Invoice; không lấy giá catalog hiện tại, không cộng lại items/services, discount hoặc extra fee.
- Không gate thêm Booking COMPLETED khi tổng hợp: predicate là trạng thái/ngày Invoice theo contract. Booking và Invoice là hai tập tổng hợp độc lập, không yêu cầu Booking tạo trong cùng kỳ với Invoice thanh toán.
- UNPAID và REFUNDED không tính, kể cả còn `paid_at`. PAID thiếu `paid_at` bị loại và đưa vào cảnh báo toàn hệ thống.
- Cảnh báo thiếu ngày không thể gán vào kỳ: không lọc bằng `Invoice.created_at`, không đưa count này vào summary/tháng. UI phải ghi rõ “toàn hệ thống, không phân bổ theo kỳ”.
- Khi PAID chuyển REFUNDED, doanh thu kỳ thanh toán cũ giảm ở lần đọc sau. V1 không tạo dòng âm tại ngày refund; schema chưa có refund ledger, thời điểm hay số tiền refund để làm việc đó.
- Invoice PAID có ngày thanh toán tương lai bị loại bởi cutoff; không gộp với cảnh báo thiếu ngày. KPI revenue toàn thời gian UC47 không có cùng semantics cutoff với tổng kỳ UC48; không sửa UC47 để ép khớp.
- Không dùng float/double hoặc chuyển SUM tiền thành Number. SQL aggregate giữ DECIMAL, Backend serialize chuỗi hai chữ số, tổng nhiều hóa đơn được phép lớn hơn giới hạn một cột `DECIMAL(12,2)`.
- Header Invoice phải được đếm một lần; tránh join items/services làm nhân tổng. Backend giữ một read view nhất quán cho summary/tháng/cảnh báo, chẳng hạn một SQL statement tổng hợp; mapper không query.

Các đẳng thức bắt buộc: summary Booking bằng tổng tháng và bằng tổng sáu status; từng tháng cũng bằng tổng status tháng. Tổng doanh thu tháng bằng summary doanh thu bằng phép cộng decimal chính xác.

## 5. HTTP contract mục tiêu — chưa triển khai

`GET /api/admin/statistics?from_month=2026-10&to_month=2026-11`

- Route dự kiến `admin.statistics.show`, middleware `auth:sanctum` rồi `account.active`.
- Kiểm role ADMIN trước validation query; QueryService kiểm lại ADMIN ACTIVE khi gọi trực tiếp và từ chối trước query repository.
- Chỉ chấp nhận hai query keys được công bố; key khác, kiểu mảng, chuỗi sai định dạng, cặp thiếu, thứ tự sai hoặc kỳ >12 tháng trả 422. GET không nhận body làm nguồn input.
- Success 200 dùng đúng một envelope `{data: ...}`, không pagination, không PII, Model hoặc relationship thừa.

| Lỗi | Contract |
| --- | --- |
| 401 | Chưa xác thực, giữ contract Sanctum/session hiện hành |
| 403 account | Giữ mã/message account hiện hành (`ACCOUNT_BLOCKED`, `ACCOUNT_INACTIVE`) |
| 403 role/domain | `ADMIN_STATISTICS_FORBIDDEN`, thông báo tiếng Việt an toàn |
| 422 | `ADMIN_STATISTICS_INVALID_QUERY`, `message` và `errors` theo field |
| 500 | Thông báo an toàn, không stack trace/SQL/credentials |

Field errors: thiếu/sai `from_month` → `from_month`; thiếu/sai `to_month`, thứ tự sai hoặc kỳ quá dài → `to_month`; key ngoài contract → chính key đó. Chỉ kiểm liên trường khi cả hai tháng hợp lệ. Sai role kèm query sai vẫn 403. Các mã mới chỉ là contract; việc đăng ký ErrorCode/exception allowlist thuộc task code được duyệt sau.

Projection `data`:

```text
period: {from_month, to_month, start_at, end_at_exclusive}
report_timezone: "Asia/Ho_Chi_Minh"
generated_at: ISO UTC
summary: {bookings_count, revenue_total}
booking_status_counts: [{status, count}]                 // đủ 6
monthly: [{month, bookings_count, revenue_total,
           booking_status_counts: [{status, count}]}]   // đủ 1–12 tháng
warnings: {paid_invoices_missing_paid_at_global_count}
```

`start_at`, `end_at_exclusive`, `generated_at` là timestamp ISO UTC kết thúc `Z`. Counts là JSON integer trong miền an toàn của consumer JavaScript; không âm/NaN/Infinity hoặc silently làm tròn count vượt miền an toàn. Tiền giữ chuỗi decimal không ký hiệu tiền tệ, không dấu phân cách, ví dụ `"19999999999.98"`. Response sai shape phải bị frontend nhận diện là lỗi contract, không biến thành kết quả rỗng.

## 6. Trách nhiệm Backend/Frontend và tái sử dụng

Backend: Route/Middleware → Controller → raw Validator/typed Request DTO → QueryService → Repository → typed Response DTO/projection → Common HTTP response. Validator không query. Với aggregate facts, dựng response DTO trực tiếp khi không cần Model projection; không tạo thêm mapper/lớp rỗng để đủ tên kiến trúc.

Frontend mục tiêu: `/admin/statistics` trong Admin layout → page/component → `statisticsService.js` → domain API → shared Axios; response qua mapper thuần riêng. Tái sử dụng mẫu request lifecycle và format tiền chính xác của UC47 khi phù hợp, không sửa contract UC47 hoặc shared Axios. Mapper UC47 cố định 12 tháng nên không dùng trực tiếp cho kỳ tùy chọn.

UI tiếng Việt: hai input tháng có label, nút “Áp dụng”, “Đặt lại” về kỳ mặc định, “Tải lại”/“Thử lại”; hiển thị kỳ đã áp dụng, timezone và thời điểm cập nhật. Input đang sửa không đổi nhãn kỳ của kết quả đã trả. Hiển thị số Booking, sáu trạng thái và bảng tháng có doanh thu; chưa yêu cầu biểu đồ mới chỉ để lặp dashboard.

UI có loading, error, success và empty đúng dữ liệu; revenue 0 không đồng nghĩa không có Booking. Cảnh báo thiếu ngày ghi rõ phạm vi toàn hệ thống. Dùng Intl.NumberFormat và decimal parts/BigInt để trình bày VND chính xác, không tự tính tổng tài chính. Dùng AbortController, request identity và Auth-version guards khi đổi kỳ, rời trang hoặc logout. Không để response cũ thay kết quả mới; 401/403/422 có cách phục hồi phù hợp, không giả thành empty.

**Cập nhật presentation được owner duyệt ngày 2026-11-20 — ADMIN-VND-DISPLAY-01:** UI Dashboard/Statistics hiển thị VND không có phần thập phân, làm tròn đến đồng gần nhất, phần lẻ từ `.50` trở lên làm tròn lên. Dùng integer/BigInt và `Intl.NumberFormat('vi-VN')`, không ép decimal lớn sang Number/parseFloat. Ví dụ API `"1000000.00"` → `1.000.000 ₫`, `"300.76"` → `301 ₫`, `"19999999999.98"` → `20.000.000.000 ₫`. Quy tắc này chỉ áp dụng text hiển thị; API/schema/mapper/raw amounts và phép tính/đối soát vẫn giữ decimal scale 2. Mỗi dòng và summary được format độc lập từ giá trị gốc, không cộng lại số đã làm tròn; tổng các số hiển thị có thể lệch do làm tròn. Empty state vẫn xét raw data (ví dụ revenue `"0.49"` hiển thị `0 ₫` nhưng không phải doanh thu raw bằng 0). Oracle và evidence lịch sử trong tài liệu vẫn là giá trị decimal gốc; không thay số liệu đã nghiệm thu hoặc chart ratios bằng số đã format.

Router, Admin navigation, route loader và shared exception files chỉ sửa trong scope riêng đã duyệt. CONTRACT-01 không tạo route/page hoặc thay module TV1/TV2.

## 7. Oracle minh họa độc lập — dữ liệu giả định, không phải QA đã chạy

Giả sử request kỳ `2026-10`–`2026-11`, cutoff `2026-11-19T05:00:00Z` (12:00 giờ Việt Nam).

- `start_at = 2026-09-30T17:00:00Z`.
- `end_at_exclusive = 2026-11-30T17:00:00Z`.
- Tháng 11 bắt đầu tại `2026-10-31T17:00:00Z`.

### Fixture Booking giả định

| ID | created_at UTC | Status hiện tại | Kết quả |
| --- | --- | --- | --- |
| B1 | 2026-09-30T17:00:00Z | COMPLETED | Tháng 10, gồm biên đầu |
| B2 | 2026-10-31T16:59:59Z | CANCELLED | Tháng 10 |
| B3 | 2026-10-31T17:00:00Z | PENDING | Tháng 11 |
| B4 | 2026-11-19T05:00:00Z | CONFIRMED | Tháng 11, gồm đúng cutoff |
| B5 | 2026-11-19T05:00:01Z | PENDING | Loại, sau cutoff |
| B6 | 2026-09-30T16:59:59Z | COMPLETED | Loại, trước kỳ |

Expected: summary `bookings_count = 4`; tháng 10 và 11 mỗi tháng 2. Sáu status summary theo thứ tự contract là `[1,1,0,1,1,0]`; tháng 10 `[0,0,0,1,1,0]`, tháng 11 `[1,1,0,0,0,0]`.

### Fixture Invoice giả định

| ID | paid_at UTC | Status | total_amount | Kết quả |
| --- | --- | --- | --- | --- |
| I1 | 2026-09-30T17:00:00Z | PAID | 100.25 | Tháng 10 |
| I2 | 2026-10-31T17:00:00Z | PAID | 200.50 | Tháng 11 |
| I3 | 2026-11-19T05:00:00Z | PAID | 0.01 | Tháng 11 |
| I4 | 2026-11-19T05:00:01Z | PAID | 999.99 | Loại, sau cutoff |
| I5 | 2026-10-15T00:00:00Z | REFUNDED | 500.00 | Loại |
| I6 | null | PAID | 300.00 | Loại, cảnh báo toàn hệ thống +1 |
| I7 | null | UNPAID | 400.00 | Loại, không tăng cảnh báo |

Expected độc lập: tháng 10 `"100.25"`, tháng 11 `"200.51"`, summary `"300.76"`, cảnh báo toàn hệ thống `1`. Đổi I2 sang REFUNDED: tháng 11 còn `"0.01"`, summary `"100.26"`, không thêm dòng refund ở tháng khác. Booking counts không đổi.

Các ví dụ bổ sung cho test sau:

- Zero: không có Booking/Invoice → summary counts 0, revenue `"0.00"`, đủ tháng và sáu status 0; cảnh báo 0. Chỉ có I6 → số liệu kỳ vẫn 0 nhưng cảnh báo 1.
- Large: hai Invoice PAID hợp lệ mỗi hóa đơn `"9999999999.99"` → tổng `"19999999999.98"`; thêm nhiều items không đổi tổng. Không dùng phép cộng float để tính oracle.
- Biên cuối riêng: kỳ chỉ tháng 10; bản ghi tại `2026-10-31T16:59:59Z` được tính, tại `2026-10-31T17:00:00Z` bị loại nếu cutoff đã qua cả hai.
- Năm nhuận: kỳ `2024-02` có biên UTC `[2024-01-31T17:00:00Z, 2024-02-29T17:00:00Z)`. Thời điểm ngày 29/02 địa phương trước biên cuối được tính.
- Với cutoff trên: mặc định là `2025-12`–`2026-11`; `2025-11`–`2026-11` là 13 tháng → 422. Kỳ chỉ `2026-12` hợp lệ nhưng mọi bucket 0 do cutoff; cảnh báo toàn hệ thống vẫn độc lập.
- Booking tạo tháng 10, lưu trú tháng 11 và Invoice trả tháng 12: Booking thuộc tháng 10, doanh thu thuộc tháng 12 khi cutoff cho phép. Không bắt hai tập dữ liệu dùng cùng cohort.

## 8. Acceptance criteria và bằng chứng triển khai dự kiến

Tất cả kiểm thử chức năng dưới đây **NOT VERIFIED**; đây là kế hoạch, không phải kết quả test.

| AC | Hành vi cần chứng minh | Bằng chứng dự kiến |
| --- | --- | --- |
| UC48-AC01 | Guest/role/account bị chặn; quyền trước validation; direct service không query khi bị từ chối | Laravel feature + unit, HTTP session riêng khi được duyệt |
| UC48-AC02 | Raw input, cặp tháng, mặc định, 1–12 tháng, thứ tự và unknown keys đúng contract | Unit validator + feature 422/200 |
| UC48-AC03 | Booking theo created_at, sáu status hiện tại, không theo lưu trú | MySQL fixture và oracle độc lập |
| UC48-AC04 | PAID/paid_at, REFUNDED/null/cutoff, decimal và không nhân tổng | MySQL + unit projection/Node decimal |
| UC48-AC05 | Timezone, biên đầu/cuối, cutoff, năm nhuận, zero-fill và tháng tương lai | Unit period + MySQL với ngữ cảnh connection được xác nhận |
| UC48-AC06 | Summary/month/status khớp, cùng read view, đúng một envelope và không PII | Feature projection + MySQL/oracle |
| UC48-AC07 | Readonly; dữ liệu nghiệp vụ và dev/env được bảo toàn | Schema QA riêng, fingerprint/cleanup; phân biệt session writes với business writes |
| UC48-AC08 | API/service/mapper, malformed response và giữ lỗi/cancellation | Node tests qua domain modules thật, transport mock có ghi rõ |
| UC48-AC09 | Loading/empty/error/retry, áp dụng/reset kỳ, cảnh báo đúng phạm vi | Owner kiểm UI trên API thật; Node chỉ bổ trợ |
| UC48-AC10 | Response cũ/session/rời trang không hiện lại dữ liệu hết hiệu lực | Node lifecycle phù hợp + owner kiểm Pending, ghi rõ tình huống quan sát |
| UC48-AC11 | UI tiếng Việt/VND, mobile/tablet/desktop, keyboard, zoom 200% | Owner checklist Đạt/Lỗi/Chưa kiểm; không suy thành WCAG compliance |
| UC48-AC12 | UC47/API cũ được bảo toàn, đúng scope và không lỗi mới | Diff, hồi quy backend/Node/lint/build theo task được duyệt |

Khi triển khai: focused unit/feature và `php artisan test`; frontend chạy `node --experimental-test-module-mocks --test tests/*.test.js`, `npm run lint`, `npm run build`. MySQL dùng schema/user QA riêng có guard target/opt-in và cleanup, không seed/reset/migrate dev. Kết quả SQLite, MySQL, mocked transport, HTTP thật và owner UI phải báo riêng. Không tự dựng browser harness phức tạp hoặc dùng Computer Use.

Checklist UI gửi owner sau triển khai, mỗi dòng báo **Đạt / Lỗi / Chưa kiểm**:

1. Chọn kỳ, áp dụng/đặt lại và đối chiếu summary/bảng tháng/trạng thái với oracle.
2. Kỳ rỗng, chỉ có Booking, và PAID thiếu ngày hiển thị đúng số 0/cảnh báo toàn hệ thống.
3. Tải chậm có loading; offline → lỗi → online → thử lại phục hồi.
4. Đổi kỳ khi Pending, rời trang, logout/hết phiên: không hiện kết quả cũ sau thao tác.
5. Mobile/tablet/desktop, zoom 200% và bàn phím: đọc đủ số tiền, label/focus rõ, không tràn trang.

## 9. Dependency và điều kiện dừng

- Tích hợp UC47 vào dev là task riêng cần duyệt và kiểm lại baseline; không giấu merge trong feature UC48. Chưa kết luận không conflict chỉ từ việc không có file sửa chung ở hai phía.
- TV2 sở hữu workflow Booking. Truy vấn fixture chứng minh phép tổng hợp, không chứng minh toàn bộ workflow production đã tích hợp hoặc dữ liệu thực đầy đủ.
- Nếu cần trạng thái cuối kỳ, doanh thu bất biến, refund theo ngày/số tiền hay dimension mới: dừng phần bị ảnh hưởng để chốt contract/schema với owner, không tự thêm ledger hoặc sửa nghiệp vụ TV1/TV2.
- Nếu phải sửa shared files hoặc vượt task 30–90 phút: báo scope/phân task trước khi triển khai.
- CONTRACT-01 chỉ tạo tài liệu này và cập nhật checklist; không code, QA runtime, DB/env, tích hợp branch, commit hoặc push. UC48 vẫn chưa triển khai/chưa nghiệm thu chức năng.
