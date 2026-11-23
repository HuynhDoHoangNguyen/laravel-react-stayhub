# UC48 — Admin Statistics: contract và bàn giao

**CLOSED TV3: owner nghiệm thu CLOSE-01 ngày 2026-11-20**, đủ8 task và hai task CSS/VND. Tại lúc đóng UC48 tổng14/18; sau UC39 hiện **15/18**. Technical QA-UI vẫn PARTIAL/manual dev, runner audit FAIL và cleanup PASS; không phải end-to-end toàn hệ thống.

Baseline CLOSE: `feature/tv3-uc48-system-statistics`, HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng accepted working tree. CONTRACT bắt đầu tại `feature/tv3-uc47-admin-dashboard`, HEAD `5ee09a0f80c2a3d1e90a82581a61c61767fd89bf`; lần fetch kế hoạch dev ở `0a26cf150988afa4b7d2af35760c9f92fdbcdaf3`, UC47 chưa tích hợp là checkpoint lịch sử, không cam kết remote hiện tại.
Hồ sơ hợp nhất contract, DATA/UI QA và checklist cũ mục7–18. [Checklist](TV3_CHECKLIST.md) giữ hiện trạng; [kiến trúc](../../ARCHITECTURE.md)/[phân công](../stayhub-team-work-assignment.md) xác định ranh giới TV3.

## Contract hiện hành

ADMIN ACTIVE xem toàn hệ thống, chỉ đọc tại `/admin/statistics`. V1 thống kê Booking theo ngày tạo/trạng thái hiện tại và doanh thu Invoice theo ngày thanh toán; không dựng lại sáu KPI UC47. Không Host/property/status filter, export, comparison, realtime, drill-down, occupancy, financial ledger hoặc mutation nghiệp vụ. UC47 giữ API/semantics toàn thời gian và chart12tháng cố định; không ép tổng UC47/48 khớp.

### Kỳ báo cáo và thời gian

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

### Định nghĩa chỉ tiêu và nguồn dữ liệu

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

### HTTP contract

`GET /api/admin/statistics?from_month=2026-10&to_month=2026-11`

- Route hiện hành `admin.statistics.show`, middleware `auth:sanctum` rồi `account.active`.
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

Field errors: thiếu/sai `from_month` → `from_month`; thiếu/sai `to_month`, thứ tự sai hoặc kỳ quá dài → `to_month`; key ngoài contract → chính key đó. Chỉ kiểm liên trường khi cả hai tháng hợp lệ. Sai role kèm query sai vẫn 403. Các mã lỗi đã được nối vào HTTP boundary trong CORE.

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

### Frontend và presentation amendment

Page→Hook→Service→API/shared Axios, mapper thuần. Draft/applied kỳ riêng, Apply/Reset server default/Tải lại/Thử lại; nhãn dựa response, warning ghi toàn hệ thống/không phân bổ theo kỳ. Loading/empty/error/success phân biệt;422 field errors/focus,401 Auth pipeline,403 ngừng dữ liệu; network/timeout/500/malformed không thành empty. Abort/request identity/Auth version loại stale success/error khi đổi kỳ/rời trang/logout/đổi account, kể cả transport bỏ qua abort. Tiếng Việt, responsive, table/labels/keyboard/reduced-motion.
ADMIN-CSS-NAMING-01 đổi namespace `admin-dashboard`/`admin-statistics`, control Invoice chung `invoice-search-input`/`invoice-filter-select`; giữ DOM IDs/ARIA/enums, structure, declaration/specificity/breakpoints/logic.80 inverse mapping entries khôi phục8 source files. NAME01–03/05 PASS, NAME04 PARTIAL: visual mới chưa kiểm.
ADMIN-VND-DISPLAY-01 dùng [formatVnd](../../frontend/src/utils/formatVnd.js): BigInt half-up/Intl vi-VN không phần lẻ; raw API/mapper/chart ratios/empty predicates giữ scale2.300.76→301₫,100.25→100₫,0.49→0₫ nhưng không empty,19999999999.98→20.000.000.000₫,9007199254740993.50→9.007.199.254.740.994₫. Format từng giá trị gốc độc lập, không cộng text đã làm tròn. VND01–05 automated PASS; formatter Invoice/Booking/Catalog ngoài scope giữ nguyên. Browser mới sau CSS/VND **NOT VERIFIED** dù owner đã nghiệm thu cả hai task.

## Implementation và nghiệm thu

| Lớp | Điểm vào/code chính |
| --- | --- |
| HTTP | [Route](../../backend/routes/api/dashboard.php), [Controller](../../backend/app/Http/Controllers/Statistics/AdminStatisticsController.php); exception codes/allowlist dùng chung đã được duyệt trong CORE |
| Input/quyền/kỳ | [Validator](../../backend/app/Validators/Statistics/AdminStatisticsValidator.php), [QueryService](../../backend/app/Services/Statistics/AdminStatisticsQueryService.php), [Period](../../backend/app/DTOs/Statistics/AdminStatisticsPeriod.php) |
| Đọc/projection | [Repository](../../backend/app/Repositories/Statistics/AdminStatisticsRepository.php), [Response DTO](../../backend/app/DTOs/Statistics/Responses/AdminStatisticsResponse.php) |
| Frontend domain | [API](../../frontend/src/api/statisticsApi.js) → [service](../../frontend/src/services/statisticsService.js) → [mapper](../../frontend/src/mappers/statisticsMapper.js); shared Axios được giữ nguyên |
| UI/lifecycle | [Hook](../../frontend/src/hooks/useAdminStatistics.js), [page](../../frontend/src/pages/admin/AdminStatisticsPage.jsx), [CSS](../../frontend/src/pages/admin/AdminStatisticsPage.css); [router](../../frontend/src/routes/AppRouter.jsx), [Admin layout](../../frontend/src/layouts/AdminLayout.jsx) |
| VND | [Helper](../../frontend/src/utils/formatVnd.js), [golden tests](../../frontend/tests/formatVnd.test.js) |
| QA | [MySQL runner](../../backend/tests/Support/RunUc48MySqlTests.ps1), [manual runner](../../backend/tests/Support/RunUc48Browser.ps1), [HTTP tests](../../backend/tests/Feature/Statistics/Uc48AdminStatisticsHttpMySqlTest.php), [Node](../../frontend/tests/adminStatistics.test.js) |

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

ADMIN-CSS-NAMING-01 và ADMIN-VND-DISPLAY-01 cũng được owner nghiệm thu ngày2026-11-20, không tăng số UC. CLOSE technical PASS chỉ áp dụng hồ sơ scope3; không thay technical PARTIAL của QA-UI.

## Oracle và DATA QA

Các oracle dưới đây là phép tính độc lập, không dùng production Repository/QueryService/DTO để tạo expected. Fixtures là reporting snapshots, không khẳng định workflow cho phép mọi tổ hợp Booking/Invoice.
Oracle contract: cutoff `2026-11-19T05:00:00Z`, kỳ10–11/2026, biênUTC `[2026-09-30T17:00:00Z,2026-11-30T17:00:00Z)`. Booking đầu kỳCOMPLETED/cuốiOctCANCELLED/đầuNovPENDING/đúngcutoffCONFIRMED gồm; sau cutoff1giây/trước kỳ1giây loại.4Booking, tháng[2,2], status summary[1,1,0,1,1,0], Oct[0,0,0,1,1,0], Nov[1,1,0,0,0,0]. PAID100.25+200.50+0.01=300.76; future999.99/refunded500/unpaid400 loại; PAID-null300 warning1. RefundI2→100.26/Nov0.01; Booking không đổi.
Boundary examples: Oct16:59:59Z gồm/17:00:00Z loại; Feb2024 UTC `[2024-01-31T17:00:00Z,2024-02-29T17:00:00Z)`; default12/2025–11/2026,13 tháng422,futureDec buckets0 nhưng warning độc lập. Booking tạoOct/lưu trúNov/Invoice trảDec nằm cohort riêng khi cutoff cho phép. Zero đủ tháng/status0; chỉ PAID-null thì warning1; hai header9999999999.99→19999999999.98 không nhân items.

DATA ledger đã chạy cùng cutoff cố định, hai Property thuộc hai Host khác nhau (có Host BLOCKED/Property BLOCKED/Room MAINTENANCE), Customer INACTIVE; sáu Booking tạo10/11, B7 tạo9 nhưng Invoice trả11:

| Booking | Tháng tạo địa phương | Trạng thái Booking | Invoice hiện tại | paid_at UTC | total_amount | Doanh thu được tính |
| --- | --- | --- | --- | --- | --- | --- |
| B1 | 2026-10 | PENDING | PAID | 2026-10-10 00:00:00 | 10.01 | Tháng 10: 10.01 |
| B2 | 2026-10 | CONFIRMED | UNPAID | 2026-10-10 00:00:00 | 20.02 | Không |
| B3 | 2026-10 | CHECKED_IN | REFUNDED | 2026-10-10 00:00:00 | 30.03 | Không |
| B4 | 2026-11 | COMPLETED | PAID | 2026-11-10 00:00:00 | 40.04 | Tháng 11: 40.04 |
| B5 | 2026-11 | CANCELLED | PAID | null | 50.05 | Không; cảnh báo +1 |
| B6 | 2026-11 | REJECTED | PAID | 2026-11-19 05:00:01 | 60.06 | Không; sau cutoff |
| B7 | 2026-09 | PENDING | PAID | 2026-11-10 00:00:00 | 70.07 | Tháng 11: 70.07 |

Expected/observed:6Booking, revenue120.12, months Booking[3,3]/revenue[10.01,110.11], sáu status[1,1,1,1,1,1], warning1. Catalog price999999.99/items amount0.01 không đổi report. B4 Invoice REFUNDED+B1 Booking REJECTED→6Booking/revenue80.08, months[10.01,70.07], status[0,1,1,1,1,2], warning1; không refund âm.
4 DATA tests mới kiểm cohort/current state/header/large/revenue-only/booking-only/globalwarning, kết hợp Query/HTTP controls biên/default/+07/extreme years/permission/rawquery/safe500.9 QA-table fingerprints khớp, aggregate một SELECT; một tháng0 revenue không có nghĩa không Booking.
MySQL8.4.13, schema `stayhub_uc48_test_c4a6c62878c34b708e15a0f29a14cfb2`, QA user chỉ grant schema owned, không đọc dev; session UTC, Query controls +07/-05/+00 rồi phục hồi.214PASS/1379assertions/0skip, dev/protected hashes khớp, cleanup0schema/user/connection. Kernel actingAs không chứng minh real login/session/browser.

## HTTP và manual UI

HTTP fixtures lấy runtime anchor/native calendar/BCMath, không đóng băng Auth/session hoặc gọi query production làm oracle. Populated khi provision tháng11/2026:

| Tình huống | Từ tháng → đến tháng | Booking | Doanh thu decimal | Global warning |
| --- | --- | --- | --- | --- |
| Dữ liệu thông thường | 2026-10 → 2026-11 | 6 | 300.76 | 1 |
| Chỉ Booking | 2026-07 → 2026-07 | 2 | 0.00 | 1 |
| Chỉ doanh thu | 2026-09 → 2026-09 | 0 | 0.75 | 1 |
| Tiền lớn | 2026-08 → 2026-08 | 0 | 19999999999.98 | 1 |
| Rỗng trong kỳ, vẫn cảnh báo toàn hệ thống | 2026-12 → 2026-12 | 0 | 0.00 | 1 |
| Mặc định/Đặt lại | 2025-12 → 2026-11 | 8 | 20000000301.49 | 1 |

Normal:Oct3Booking/100.25,Nov3Booking/200.51,sáu status mỗi loại1. Booking-only PENDING1/CANCELLED1; default thêm2BookingJuly. Revenue-only/large có Booking tạo ngoài kỳ. Dùng ui_months/response generated_at nếu provision khác tháng hoặc lease qua biên tháng; không sao chép nhãn11/2026. Zero warning0, MissingDate warning1. Bảng này là raw oracle, không ảnh browser hoặc proof manual QA.

Owner báo tất cả M01–M12 Đạt và đã logout ngày2026-11-20; đây là owner-reported manual, không agent browser observation/HAR. Sau audit, owner xác nhận dùng **localhost:5173 (dev)**→backend8000/database stayhub, không phải QA56979→56978. Mỗi ca giữ PASS theo owner trên dev, không nâng thành manual PASS schema QA riêng.

| ID | Thao tác và expected | Owner xác nhận / giới hạn audit |
| --- | --- | --- |
| M01 | Login ADMIN A đúng khu vực, mở thống kê, reload vẫn đúng tài khoản; Network gọi đúng backend QA | Đạt theo owner; QA context chưa đối chiếu |
| M02 | Normal: 2 summary, 6 status, 2 hàng tháng đúng bảng oracle; timezone/cutoff và nhãn tiếng Việt | Đạt theo owner; QA context chưa đối chiếu |
| M03 | Sửa draft chưa áp dụng: nhãn/kết quả vẫn kỳ cũ; Apply đổi kết quả; Reset về mặc định 12 tháng | Đạt theo owner; QA context chưa đối chiếu |
| M04 | Kiểm Booking-only, revenue-only, large và zero; đủ tháng/status 0, warning đúng phạm vi | Đạt theo owner; QA context chưa đối chiếu |
| M05 | Chọn kỳ đảo ngược hoặc >12 tháng → 422, field error gắn input, focus lỗi; sửa và Apply phục hồi | Đạt theo owner; QA context chưa đối chiếu |
| M06 | DevTools Network throttle: tải chậm có loading; Offline rồi Tải lại → error, Online/Thử lại phục hồi | Đạt theo owner; QA context chưa đối chiếu |
| M07 | Khi Pending đổi kỳ nhanh: chỉ kết quả kỳ cuối được hiển thị; nếu không quan sát được response về sai thứ tự ghi Chưa kiểm | Đạt theo owner; QA context chưa đối chiếu |
| M08 | Khi Pending rời trang/quay lại hoặc logout/login ADMIN B: không hiện lại dữ liệu của context cũ | Đạt theo owner; QA context chưa đối chiếu |
| M09 | Logout ở tab khác rồi request lại: UI phục hồi đăng nhập/route đúng, không giả thành empty; account/role bị chặn đúng luồng phù hợp | Đạt theo owner; QA context chưa đối chiếu |
| M10 | 375/768/1440 px: không tràn trang, bảng cuộn ngang, đọc đủ tiền lớn, nút/input dùng được | Đạt theo owner; QA context chưa đối chiếu |
| M11 | Zoom 200%, Tab/Enter, focus input/table/nút thấy được, labels/error association và cuộn bằng phím | Đạt theo owner; QA context chưa đối chiếu |
| M12 | Reduced motion trong OS/DevTools: hiệu ứng tôn trọng tùy chọn; logout mọi tab rồi yêu cầu dừng | Đạt theo owner; QA context chưa đối chiếu |

Frontend guard điều hướng HOST/CUSTOMER là đúng; backend403 được kiểm qua HTTP riêng, không sửa dev account để ép page ADMIN. M01–M12 xảy ra trước CSS/VND; không dùng làm visual mới sau hai thay đổi.

### Audit sau manual và cleanup

Live run `822d1c28fc794deea2d42eb74e701831` readiness12:09:14Z, lease đến2026-11-20T14:09:13.8703627Z; owner yêu cầu dừng, runner kết thúc12:15:14.1468136Z. HTTP/source/oracle preflight PASS không chứng minh React render/manual. Dev table counts giữ nguyên nhưng full-dump fingerprint **FAIL**, runner exit1; result/log gốc không đổi.
Metadata-only binlog.000009 phủ2026-11-20T12:08:39Z–12:15:15Z: dev11 row events đều sessions, không thấy business writes/DDL trong cửa sổ đó. Không suy actor hoặc toàn bộ lịch sử dev. QA154 row events gồm preflight, **0 sau readiness** đến12:15:15Z; không có session activity để gắn manual với QA riêng. QA user không quyền ghi dev; fingerprint/audit SQL chỉ đọc. Owner xác nhận dùng dev phù hợp các quan sát này; không restore dev sessions, nới guard hoặc sửa FAIL thành PASS.
Cleanup lịch sử được kiểm độc lập12:19:05Z:9 QA business hashes/protected source/env khớp, authenticated sessions0, process13248/5212 thoát, ports56978/56979 released, schema/user/connection0, private credentials xóa. Evidence ở `.agent-workspace/tv3/uc48-qa-ui-01/closure/`, `audit-followup/` và run gốc; không lưu binlog row values.
Snapshot handoff:QAUI01/02/06 PARTIAL,03–05 NOT VERIFIED. Sau audit:QAUI01/06 PARTIAL,02–05 OWNER REPORTED PASS trên dev. Owner đã biết giới hạn và nghiệm thu; technical QA-UI vẫn **PARTIAL**. Cleanup PASS không xóa FAIL dev fingerprint hoặc thiếu manual QA correlation.

## Ledger và hồi quy

Đây là evidence lịch sử, không phải suites chạy mới trong CLOSE/cleanup02A. Artifacts local/ignored không theo Git; không chép credentials. Lint0 errors/3 existing Fast Refresh warnings; build bundle>500kB warning. SQLite skipped là opt-in/runtime/engine chưa chạy trong suite đó, không là MySQL PASS; Node/mock/SSR không thay browser.

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
| QA-UI manual | Owner, dev frontend 5173 | M01–M12 Đạt theo owner; không có agent browser observation/HAR để chứng minh QA fixture context | [Audit manual và cleanup](#audit-sau-manual-và-cleanup) |
| QA-UI live audit | Run `822d1c28fc794deea2d42eb74e701831` | Runner exit 1 vì dev full fingerprint đổi; cleanup PASS; technical PARTIAL | `.agent-workspace/tv3/uc48-qa-ui-01/closure/final-audit.json`, `audit-followup/audit-findings.json` |
| 2026-11-20 ADMIN-CSS | Node/static equivalence | 215 PASS; 80 mapping entries đảo ngược khôi phục nguyên 8 source files; lint/build exit 0 | `.agent-workspace/tv3/admin-css-naming-01/verification-summary.json`, `source-equivalence.json` |
| 2026-11-20 ADMIN-VND | Node/domain modules | 37 focused, 231 toàn bộ PASS / 0 FAIL/SKIP; lint/build exit 0; combined scope 16 file | `.agent-workspace/tv3/admin-vnd-display-01/verification-summary.json`, `final-scope-check.json` |

### FAIL và negative controls

- QUERY runner falseFAIL do tên test có “warning”; final positive a7ef53af190e47f39a5b81cfa7a3eae2 PASS181/647 ghi riêng. Injected failure/AfterSchema/Collision đúng marker và cleanup, không cộng positive PASS.
- DATA770a7c2a12f746939661ea6a4d2b5e57:214tests PASS nhưng regexWARN falseFAIL; sửa parser trong scope,5 classification controls PASS, không nới guard. Final c4a6c62878c34b708e15a0f29a14cfb2 PASS; giữ logs/result cả hai.
- QAUI focused đầu exit255 do thiếu đóng array, chưa chạy tests/runtime; sửa rồi guard50/95 và full suite PASS. InjectReady23b2bafc38ab4e468355e5e1c4fac16f expected exit1, cleanup/dev/protected khớp; missingProvision reject trước DB.
- Sáu HTTP scenarios:Populated e94244cd1a1d494c81b0ee923fb84114,Zero f6d69fdefd5543f3a0be47c776fceeda,BookingOnly a22e0bb8202b4bd487e8bac5337b839f,RevenueOnly ca09dd22d674411b95db6e2a66e2dc58;MissingDate/Large tra run-summary.json. Tổng558status probes/72full-projection comparisons,6cleanup PASS; không render React. Live822d1c28fc794deea2d42eb74e701831 vẫn overallFAIL.
- CSS scope9files/80 inverse mappings,Node215;VND scope8files/37focused/full231,combined scope16. Cả hai accepted nhưng browser sau refactor NOT VERIFIED; không đổi raw API, financial calculations, chart ratios hoặc status semantics.

## AC và giới hạn bàn giao

| AC contract | Evidence và trạng thái kỹ thuật | Owner |
| --- | --- | --- |
| AC01 quyền/error order | PASS ở unit/kernel HTTP/MySQL và HTTP session/CSRF preflight | Đã nghiệm thu task liên quan |
| AC02 input/kỳ | PASS validator/period/HTTP, mặc định và 1–12 tháng | Đã nghiệm thu |
| AC03 Booking | PASS oracle nhiều Property/current status/created_at | Đã nghiệm thu |
| AC04 revenue | PASS PAID/header/paid_at/refund/null/cutoff/large/no multiplication | Đã nghiệm thu |
| AC05 biên thời gian | PASS period/MySQL, timezone/cutoff/zero-fill | Đã nghiệm thu |
| AC06 projection/read view | PASS DTO/invariants và aggregate một SELECT | Đã nghiệm thu |
| AC07 readonly/bảo toàn | QA business/source/cleanup PASS; live dev full fingerprint FAIL do sessions, audit PARTIAL như phần HTTP/manual UI | Chấp nhận giới hạn |
| AC08 frontend contract | PASS Node qua API/service/mapper thật, transport mock; malformed/error/cancellation | Đã nghiệm thu |
| AC09 states/filter/retry | Node/HTTP bổ trợ; M01–M12 owner-reported PASS trên dev; QA fixture browser NOT VERIFIED | Chấp nhận giới hạn |
| AC10 lifecycle/auth | Node coordinator PASS; owner báo Pending/route/auth Đạt trên dev; độc lập browser correlation PARTIAL | Chấp nhận giới hạn |
| AC11 tiếng Việt/VND/responsive | Owner M10–M12 Đạt trên dev trước refactor; naming/VND automated PASS, browser sau refactor NOT VERIFIED | Đã nghiệm thu cả hai task bổ sung |
| AC12 hồi quy/scope | PASS theo từng checkpoint; UC47 raw semantics/API giữ nguyên, VND presentation đổi theo amendment riêng đã duyệt | Đã nghiệm thu các task, gồm CLOSE |

Contract IDs là UC48-AC01–UC48-AC12. DATA01–07 technical PASS trong MySQL/kernel/regression scope. CORE AC01–08 PASS engine đã ghi, AC09/10 browser PARTIAL và AC11 NOT VERIFIED tại checkpoint trước manual; UI implementation acceptance không xóa các giới hạn đó. Owner chấp nhận QA-UI với documented limitations; không kết luận technical PASS toàn UC48 hoặc WCAG compliance.
T01–T03 vẫn **BLOCKED TV2**, fixture không chứng minh Checkout→Invoice/Review production. Review/mergeability PR12/PR13 không là TV3 integration PASS. Không claim stress benchmark, hardkill/Docker outage recovery hoặc browser visual mới sau CSS/VND. UC47 raw semantics được giữ nguyên; presentation amendment không ép khớp KPI.

## Hướng dẫn tái kiểm khi được giao riêng

- MySQL từ root: `pwsh -NoProfile -File backend/tests/Support/RunUc48MySqlTests.ps1 -Provision`; negative flags theo allowlist của runner.
- Browser: `pwsh -NoProfile -File backend/tests/Support/RunUc48Browser.ps1 -Provision -Scenario Populated -KeepAlive -MaxMinutes 120`; không KeepAlive thì smoke/cleanup ngay. Có Zero/BookingOnly/RevenueOnly/MissingDate/Large.
- Browser guard local/database-session tách API testing/array-session. Existing MySQL, owned target/instance/account/grants kiểm trước migration/fixtures; QA user không ghi dev. Server loopback/ports riêng, Vite Axios origin đúng manifest; không tạo instance/volume hoặc reset/seed dev/env.
- ready/handoff gồm URL,ui_months,lease,stop file/private credential path; password/key chỉ memory/private file ignored, xóa khi kết thúc. Dùng phiên browser riêng, roleadmin, đối chiếu source/API/actor/schema/run trước manual.
- Logout mọi tab rồi stop.request đúng run; hết lease cleanup và phần chưa kiểm NOT VERIFIED. Không kill runner/drop foreign schema. URL live lịch sử đã dừng, không là môi trường đang chạy.
- CLOSE artifacts `uc48-close-01/`, acceptance `uc48-close-01-acceptance/` dưới ignored workspace. Không sửa timestamp/verdict/PENDING snapshot để giả nghiệm thu từ lúc chạy; acceptance hiện hành ghi riêng ở đây.

### Chỉ mục checkpoint bổ sung

Các mã sau được giữ để truy nguồn các checkpoint trong hồ sơ cũ; không gán thêm verdict hoặc coi là lượt kiểm mới. Mã 40 ký tự là commit baseline, mã 32 ký tự là run/evidence. Backup nguyên văn và content-map nằm trong artifacts cleanup 02A.

| Nguồn cũ | Commit/run chưa nêu ở ledger chính |
| --- | --- |
| UC48_ACCEPTANCE_HANDOVER.md | `770a7c2a12f746939661ea6a4d2b5e57` |
| UC48_UI_QA.md | `23b2bafc38ab4e468355e5e1c4fac16f` |
| TV3_CHECKLIST.md | `3931bf6d41c2f23e26ce73ba73a28bd3f9f008c5`, `770a7c2a12f746939661ea6a4d2b5e57` |
