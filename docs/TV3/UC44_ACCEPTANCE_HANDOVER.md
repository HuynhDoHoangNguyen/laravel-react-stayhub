# UC44 — Admin Booking Monitoring V1

**CLOSED TV3 ngày 2026-11-22 theo phạm vi đã kiểm chứng.** CLOSE44-01–06 TECHNICAL PASS / OWNER ACCEPTED. TV3 đạt **16/18 UC**, còn UC17 và UC61. [Quyết định đóng](#21-uc44-close-01) · [Checklist TV3](TV3_CHECKLIST.md)

Tra cứu: phạm vi và contract §2–8, ma trận runtime §9, điểm tích hợp và ledger §10–11, test theo lượt §12–20, kết luận và giới hạn §21.

## 1. Baseline và nguồn yêu cầu

- Baseline START/CONTRACT-01 là nhánh feature/tv3-uc44-admin-booking-monitoring, HEAD dd38ff84b06df8982b8392785c80580d1c77e897. Ref local origin/dev lúc audit là c8a83f86cf36816eff3f79633697297f5442f056 (PR #19), cùng tree. START và CONTRACT-01 chỉ audit source và tài liệu. Các task sau tiếp tục trên working tree đã nghiệm thu.
- Nguồn yêu cầu gồm [Use Case](../stayhub-usecase-system.md), [mockup §9.6](../stayhub-screen-mockups.md) và [phân công §5](../stayhub-team-work-assignment.md). TV3 sở hữu monitoring, TV2 sở hữu lifecycle/pricing/services, TV1 sở hữu Identity/Catalog. Điểm ghép theo [kiến trúc](../../ARCHITECTURE.md) và [hướng dẫn Frontend](../ai-frontend-development-guide.md).

## 2. Phạm vi V1 và ranh giới triển khai

Admin ACTIVE đọc Booking toàn hệ thống, tìm kiếm, lọc, phân trang và tải tùy chọn bộ lọc tại /admin/bookings. UI tiếng Việt có loading, empty, error, success và retry. V1 chỉ đọc. Mutation, lifecycle, tính lại giá, payment, export, KPI và chi tiết Booking thuộc phạm vi riêng.

Backend đi từ route/middleware → controller/raw validation → typed DTO → QueryService → repository/model → mapper → Common response. Actor truyền riêng và được kiểm trước query. Frontend đi từ page/component → domain service/API → shared Axios. AbortSignal, query identity và Auth snapshot kiểm kết quả cũ; cancellation có trạng thái riêng.

## 3. Decision log và điều kiện chốt contract

**V1 READ DECISIONS APPROVED ngày 2026-11-21** qua CONTRACT-02. Bảng giữ bối cảnh tại thời điểm duyệt. ACK về writer, chuyển chủ và lịch sử thuộc TV1/TV2. Điểm ghép đã triển khai xem §10.

| ID | Nguồn/vấn đề | Quyết định V1 đã duyệt | Owner / trạng thái / phạm vi |
| --- | --- | --- | --- |
| D44-01 | UC44/Actor Admin và mockup §9.6 chỉ yêu cầu global list | List/options read-only; chưa detail/navigation sang detail Host/Customer | TV3 APPROVED; áp dụng toàn V1 |
| D44-02 | Migration/Booking/Room/Property/User và mapper hiện có | Allowlist19 field §4; tên/Host hiện tại, tiền Booking snapshot; không email/phone/note/payment | TV3 APPROVED projection reader; source facts không phải external ACK về lịch sử tên/ownership |
| D44-03 | Host legacy có start/end khác nghĩa; mockup chưa quy định Date | check_in_from/to cùng lọc check_in_date, inclusive; không dùng overlap/check_out/created_at | TV3 APPROVED filter Admin độc lập; giữ nguyên endpoint/semantics Host TV2 |
| D44-04 | FK restrict trong migration, read projection có thể gặp relation null | Default giữ row, field dẫn xuất null; filter theo FK còn xác định được (§5), không bịa tên/repair dữ liệu | TV3 APPROVED read fallback; không xác nhận dev có orphan, không cho phép tắt FK |
| D44-05 | Options Invoice hiện có loại Booking chưa Invoice | EXISTS theo Booking; Host → Property, Customer độc lập; không lọc active/role hiện tại | TV3 APPROVED audience Admin; source facts quan hệ hiện tại, không cam kết lịch sử đổi chủ |
| D44-06 | Common envelope, active middleware, source raw validation legacy | Raw/typed query §5–§6; auth/error/direct-call §7; ADMIN_BOOKING_FORBIDDEN và ADMIN_BOOKING_INVALID_QUERY | TV3 APPROVED contract đích; code mới chưa tồn tại, không đổi Auth toàn cục |
| D44-07 | API registry/error route allowlist/AppRouter dùng chung | Integration map bên dưới; không rehome routes Invoice/Booking hiện có | TV3 APPROVED thiết kế điểm ghép; quyền sửa từng shared file DEFERRED TO CODE PLAN |

## 4. Projection list V1

Tất cả 19 key bên dưới luôn có trong mỗi item; không dùng omission để biểu diễn quan hệ thiếu. Chỉ xuất allowlist này. Các ID là JSON integer dương trong miền JS-safe; nếu dữ liệu vượt miền hoặc status/timestamp/tiền malformed thì fail toàn response với 500 INTERNAL_SERVER_ERROR an toàn, không cắt số, bỏ riêng row lỗi hay giả thành 0/empty success. Kết quả kiểm xem C44-R03 tại §9.

| Field | Kiểu JSON / null | Nguồn và ý nghĩa |
| --- | --- | --- |
| id | integer, không null | bookings.id |
| booking_code | string, không null | bookings.booking_code |
| customer_id | integer, không null | bookings.customer_id; giữ FK ngay cả khi quan hệ thiếu |
| customer_name | string hoặc null | customer.name hiện tại; null khi không có customer |
| room_id | integer, không null | bookings.room_id; giữ FK ngay cả khi quan hệ thiếu |
| room_name, room_number | string hoặc null | room.name/room_number hiện tại; null khi room thiếu |
| property_id | integer hoặc null | room.property_id khi có room; vẫn giữ FK nếu property thiếu |
| property_name | string hoặc null | room.property.name hiện tại; null khi property thiếu |
| host_id | integer hoặc null | room.property.host_id khi có property; vẫn giữ FK nếu host thiếu |
| host_name | string hoặc null | room.property.host.name hiện tại; null khi host thiếu |
| check_in_date, check_out_date | string, không null | DATE YYYY-MM-DD lưu trên Booking; không chuyển timezone |
| guest_count, number_of_nights | integer, không null | Giá trị Booking đã lưu; không tính từ ngày ở frontend |
| price_per_night, room_total | decimal string, không null | Snapshot Booking, hai chữ số thập phân; không đọc giá Room hiện tại |
| status | string, không null | Một trong sáu giá trị enum ở dưới; không suy từ ngày hoặc Invoice |
| created_at | string ISO UTC hoặc null | Booking.created_at; null giữ nguyên nếu storage không có timestamp |

Schema có money DECIMAL(12,2), miền đọc V1 `0.00`–`9999999999.99`; không float, không tính lại snapshot để thay giá trị đọc. Reader không nhân lại số đêm/giá hoặc cộng dịch vụ để validate/thay room_total; bất biến arithmetic của writer không được nghiệm thu qua read task. UI format VND bằng Intl.NumberFormat, nhãn **Tiền phòng**; không gọi room_total là tổng phải thanh toán/doanh thu. Không xuất services_total, total_amount, payment_status, paid_at, invoice_id/invoice, note, updated_at, booking_services hoặc status_label trong V1.

Status: `PENDING` (Chờ xác nhận), `CONFIRMED` (Đã xác nhận), `CHECKED_IN` (Đang lưu trú), `COMPLETED` (Đã hoàn thành lưu trú), `CANCELLED` (Đã hủy), `REJECTED` (Bị từ chối). COMPLETED không đồng nghĩa PAID. UI null hiển thị “—”; không đổi null thành chuỗi rỗng hoặc số 0. Unknown status không giả thành một trong sáu trạng thái hợp lệ.

Tên rỗng/whitespace hoặc chuỗi `0` có trong storage phải được bảo toàn tại mapper; UI không dùng truthy fallback làm mất `0`. Chuyển tên/ownership sau đặt phòng sẽ thay projection/filter theo quan hệ hiện tại, không thay snapshot tiền; cần ghi rõ giới hạn lịch sử này cho người dùng khi phù hợp.

## 5. Query list V1

`GET /api/admin/bookings`, route name `admin.bookings.index`. UI `/admin/bookings` thuộc ADMIN guard; backend quyết định quyền. Endpoint list đã tích hợp tại §15.

| Query | Quy tắc |
| --- | --- |
| host_id, property_id, customer_id | Optional; chuỗi chữ số canonical hoặc integer dương, <= 9007199254740991. Sau trim mới validate, rồi cast; không nhận boolean/array/object/float, 01, +1, 1.0, 1e2, 0, số âm |
| status | Optional; đúng một trong sáu enum, phân biệt hoa/thường; “Tất cả” bỏ query, không gửi ALL |
| check_in_from, check_in_to | Optional string YYYY-MM-DD, ngày calendar hợp lệ trong miền 1000-01-01–9999-12-31; cho phép một đầu; nếu đủ hai đầu thì from <= to, lỗi khoảng gắn check_in_to |
| search | Optional string, trim, tối đa 255 Unicode characters; chuỗi `0` là từ khóa hợp lệ |
| page | Mặc định 1 khi omitted; canonical integer 1–2147483647 |
| per_page | Mặc định 10 khi omitted; canonical integer 1–50; UI 10/20/50 |

Optional filter null/chuỗi rỗng sau trim là không lọc. page/per_page hiện diện nhưng null/rỗng/blank là 422; không clamp/fallback. Unknown keys bị bỏ qua theo allowlist, không nối vào pagination links; không nhận sort tùy ý. Raw scalar/type validation trước conversion; Request DTO typed giữ invariant cho caller nội bộ.

Host lọc qua booking.room.property.host_id; Property qua booking.room.property_id; Customer qua booking.customer_id. Không yêu cầu thực thể liên quan ACTIVE hoặc role hiện tại đúng HOST/CUSTOMER để giữ Booking lịch sử; actor truy cập vẫn bắt buộc ADMIN ACTIVE. ID hợp lệ nhưng không khớp FK hoặc Host/Property không khớp → 200 rỗng, không404. Chốt theo cột FK còn xác định được: customer_id vẫn match khi customer thiếu; property_id vẫn match nếu room tồn tại nhưng property thiếu; host_id vẫn match nếu property tồn tại nhưng host thiếu. Nếu tầng cha cần để xác định FK bị thiếu thì field tương ứng null và không match filter đó. Query phải giữ được semantics này, không whereHas target entity làm mất match FK; default không có filter giữ row. Options chỉ lấy thực thể thật tồn tại theo §6.

Các filter AND với nhóm search; search OR trên booking_code, customer.name, property.name hiện tại. LIKE contains với binding và escape `!` → `!!`, `%` → `!%`, `_` → `!_`, SQL ESCAPE '!'. Không tìm email/phone; relation null không match tên nhưng vẫn có thể match booking_code. Case/accent theo collation MySQL thực tế, chưa cam kết accent-insensitive; phải ghi engine/collation khi QA. Không dùng SQLite để chứng minh collation MySQL.

Date V1: `check_in_date >= check_in_from AND check_in_date <= check_in_to` với từng đầu được truyền; UI labels “Ngày nhận phòng từ”/“Ngày nhận phòng đến”. Không dùng check_out_date, created_at hoặc overlap; không ép ngày theo giờ browser. Timestamp lưu/serialize UTC; UI định dạng vi-VN với timeZone Asia/Ho_Chi_Minh, null hiển thị “—”. Không đổi config timezone toàn cục.

Sort cố định created_at DESC rồi id DESC (timestamp null cuối theo MySQL DESC); options theo §6. Không có bảo đảm snapshot xuyên nhiều request: Booking mới có thể dịch trang offset. UI đổi filter/per_page sẽ reset page 1; page vượt cuối vẫn trả 200/data rỗng, cho quay trang đầu, không âm thầm đổi page.

## 6. Filter options V1

`GET /api/admin/bookings/filter-options`, route name `admin.bookings.filter-options`. Dùng cùng quyền, validation và envelope của list. HP-01 triển khai hosts/properties tại §16. CD-01 bổ sung customers tại §17.

| Query | Quy tắc |
| --- | --- |
| type | Required string, trim rồi kiểm đúng hosts/properties/customers; omitted/null/blank/unknown/type sai → 422 |
| search, page, per_page | Cùng normalization/giới hạn §5; search chỉ trên name của option |
| host_id | Chỉ cho type=properties, optional cùng ID rule; nếu truyền key này cho hosts/customers, kể cả null/rỗng, trả 422 |

Source V1: hosts có properties.rooms.bookings; properties có rooms.bookings, thu hẹp theo host_id nếu truyền; customers có bookings qua customer_id. Dùng EXISTS hoặc equivalent để tránh lặp option; không chỉ lấy thực thể trong trang Booking hiện tại. Không phụ thuộc status/search/date/customer/property của main list; không lọc theo Invoice, active status hoặc role hiện tại. Unknown keys ngoài allowlist bị bỏ qua.

Option hosts/customers có đúng `{id, name}`; properties có đúng `{id, name, host_id}`. ID integer JS-safe, name string bảo toàn giá trị, không dùng email để phân biệt tên trùng; UI hiển thị tên và #ID. Sort name ASC rồi id ASC theo collation thực tế; options thiếu quan hệ không được bịa từ Invoice snapshot. host_id không tồn tại → properties rỗng.

UI: chọn Host xóa Property và reset main page 1; Customer giữ độc lập. Giữ selected option khi nó nằm ngoài trang options hiện tại; clear filters xóa toàn bộ selection và reset query. Options tải/lỗi/rỗng có state riêng, không làm lỗi options trông như main list rỗng. Selected option mất khỏi nguồn sau đổi dữ liệu có thể vẫn giữ selection/#ID đến khi người dùng xóa; kết quả list phản ánh filter hiện tại, không tự thay ID.

## 7. Envelope, quyền và lỗi V1

Success 200 có top-level `data` (array), `links` (first/last/prev/next, string hoặc null) và `meta`; không bọc data.data, không thêm success/message riêng. Theo [Common PageResponse](../../backend/app/Common/PageResponse.php), meta giữ thông tin Laravel paginator; consumer phải kiểm current_page/last_page/per_page/total là integer và from/to nullable, không loại bỏ metadata/links còn lại. Links giữ filter đã chuẩn hóa; host/user names không xuất trong URL nếu không phải query người dùng nhập.

Thứ tự HTTP: Sanctum → account.active → authorize persisted ADMIN → validate raw query → typed DTO → query/map →200. Role check trước validation phải dùng actor đọc lại từ persistence, không tin role từ instance cũ của guard. QueryService nhận actor riêng, đọc lại actor persisted và kiểm ACTIVE/ADMIN trước Booking/options query, kể cả khi gọi trực tiếp. Direct-call actor thiếu/unsaved/đã xóa/sai role →403 ADMIN_BOOKING_FORBIDDEN; actor persisted BLOCKED/INACTIVE →403 ACCOUNT_BLOCKED/ACCOUNT_INACTIVE. Direct-call không nhận HTTP Request/session và không tự dọn session; HTTP giữ account middleware hiện hành. Unit/direct-call và HTTP evidence nằm tại §14/18/19. Tham khảo [active middleware](../../backend/app/Http/Middleware/EnsureAccountIsActive.php), [AuthService](../../backend/app/Services/Auth/AuthService.php), [global handler](../../backend/app/Exceptions/GlobalExceptionHandler.php).

| Case | Status / body đích |
| --- | --- |
| Guest, kể cả query sai | 401; giữ response Unauthenticated theo Sanctum/framework, không yêu cầu code domain |
| BLOCKED/INACTIVE, kể cả query sai | 403; code ACCOUNT_BLOCKED/ACCOUNT_INACTIVE, message hiện hành và session cleanup |
| CUSTOMER/HOST ACTIVE, kể cả query sai | 403; code ADMIN_BOOKING_FORBIDDEN, message “Bạn không có quyền xem danh sách đặt phòng toàn hệ thống.” |
| ADMIN ACTIVE, query sai | 422; code ADMIN_BOOKING_INVALID_QUERY, message “Bộ lọc đặt phòng không hợp lệ.” và errors object với field → array string; thu thập lỗi field, không dựa thứ tự JSON keys |
| Filter không có match/page ngoài cuối | 200, data [], meta phản ánh page yêu cầu/total thật |
| Lỗi ngoài dự kiến/response data không hợp lệ tại server | 500; INTERNAL_SERVER_ERROR và message an toàn, không stack trace/secrets |

Các code ADMIN_BOOKING_* dùng domain AppException. Hai route có trong allowlist handler cho 500 an toàn. Route hoặc method không tồn tại giữ framework semantics. Nếu hạ tầng trả 419/429, frontend dùng hành vi lỗi/session hiện hành.

Frontend giữ status/code/errors/cancellation; 401 theo Auth hiện tại, 403 account khác 403 domain; 422 hiển thị field errors; network/500 có thử GET lại chủ động. Response success malformed phải thành lỗi dữ liệu, không fallback `{data: []}`. Không tự nhận mọi code là session hết hạn hoặc tự logout khi chỉ sai quyền domain.

## 8. Ví dụ contract và oracle QA

Ví dụ và oracle là dữ liệu minh họa. Verdict runtime nằm ở §9 và các lượt test tương ứng.

### Example item và options

```json
{
  "id": 101, "booking_code": "BK-UC44-A", "customer_id": 31, "customer_name": "Khách A",
  "room_id": 71, "room_name": "Phòng A", "room_number": "101",
  "property_id": 51, "property_name": "Cơ sở A", "host_id": 21, "host_name": "Host A",
  "check_in_date": "2026-11-21", "check_out_date": "2026-11-23",
  "guest_count": 2, "number_of_nights": 2, "price_per_night": "500000.00",
  "room_total": "1000000.00", "status": "PENDING", "created_at": "2026-11-20T17:30:00.000000Z"
}
```

Timestamp trên hiển thị 00:30 ngày 21/11 tại Việt Nam; check_in_date vẫn 21/11 với mọi timezone browser. Options độc lập: host `{ "id": 21, "name": "Host A" }`, property `{ "id": 51, "name": "Cơ sở A", "host_id": 21 }`, customer `{ "id": 31, "name": "Khách A" }`.

Ví dụ query: `GET /api/admin/bookings?host_id=21&property_id=51&customer_id=31&status=PENDING&check_in_from=2026-11-21&check_in_to=2026-11-21&page=1&per_page=10`. Trên dataset O01, trả đúng A. `GET /api/admin/bookings/filter-options?type=properties&host_id=21` có option 51 dù A chưa có Invoice.

Ví dụ422 cho `page=01`: `{ "message": "Bộ lọc đặt phòng không hợp lệ.", "code": "ADMIN_BOOKING_INVALID_QUERY", "errors": { "page": ["Tham số page không hợp lệ."] } }`. Message top-level/code đã chốt tại D44-06; field message minh họa phải là tiếng Việt, đúng field/shape ở task code, không yêu cầu cố định nguyên văn mọi message của framework.

### Oracle độc lập

| ID | Dataset/thao tác synthetic dự kiến | Expected theo V1 đã chốt |
| --- | --- | --- |
| O01 | A: id101/H21/P51/C31/PENDING/in21/out23; B: id102/H22/P52/C32/COMPLETED/in20/out22; C: id103/H21/P51/C32/CANCELLED/in21/out22; cả ba created_at bằng nhau | Default IDs 103,102,101; filter H21 AND C31 → 101; H21 AND P52 → [] |
| O02 | Dataset O01; from=to=2026-11-21; B có ngày trả22 | IDs 103,101; B bị loại vì ngày nhận20, không dùng overlap hoặc checkout |
| O03 | Tên khách A là `Khách 50%_!`; một khách đối chứng tên `Khách 500x` | search `50%_!` chỉ match literal A; OR booking_code với tên được nhóm dưới AND filters |
| O04 | A không Invoice; B có Invoice snapshot tên cũ; đổi giá catalog/phòng và tên cơ sở trong schema QA owned | A vẫn hiện/options có P51; snapshot tiền A giữ 1000000.00, tên là tên hiện tại; không dùng snapshot Invoice của B |
| O05 | O01 per_page=1; request page=2; sau đó page=4 | Trang2 ID102, total3/last_page3; trang4 [], current_page4/total3; UI cho quay trang đầu |
| O06 | FK hợp lệ bình thường; relation thiếu chỉ dựng khi QA được duyệt hoặc qua mapper facts synthetic | Default không mất row; customer thiếu vẫn match customer_id, property thiếu vẫn match room.property_id, host thiếu vẫn match property.host_id; tầng cha mất thì FK dẫn xuất null/không match. Options không bịa thực thể. Không tự tắt FK/tạo orphan trong CONTRACT-02 |
| O07 | Còn Booking của Customer/Host blocked hoặc property inactive; actor Admin ACTIVE | Vẫn có row/options; actor blocked bị403 dù dữ liệu có thể được Admin khác xem |
| O08 | Input array/boolean/float, ID vượt JS-safe, page01, per_page51, ALL/lowercase status, 2026-02-30, from>to, search256 ký tự | 422 field tương ứng; không cast/clamp trước validation. Null/blank optional bỏ lọc; null/blank pagination422; search `0` giữ nguyên |
| O09 | type=hosts/customers kèm host_id=null; type thiếu/sai; properties với host_id không tồn tại | Hai case đầu422; case cuối200 rỗng; names trùng phân biệt #ID, không email |
| O10 | Giá snapshot `0.10`, `9999999999.99`, tên `0`, timestamps null/qua nửa đêm Việt Nam; mapper facts malformed/ID quá JS-safe | Dữ liệu hợp lệ giữ type/precision/null/date; malformed fail response500 an toàn, không bỏ row/fake empty; UI không tính lại tiền. Không persist enum/money sai lên MySQL để dựng case |
| O11 | Guest/HOST/CUSTOMER/blocked/inactive/Admin ACTIVE và query sai; direct-call actor unsaved/deleted/stale role/status | HTTP precedence401/403/422; persisted role kiểm trước raw validation. Direct-call thiếu/sai role403 ADMIN_BOOKING_FORBIDDEN, blocked/inactive403 ACCOUNT_*; không Booking/options query hoặc session mutation khi bị từ chối |
| O12 | So dữ liệu trước/sau GET, request khi filter/page/session đổi và response cũ bị delay có kiểm soát | Không mutation nghiệp vụ; response cũ không hiện; cancel không thành lỗi/empty giả; cần HTTP/SQL và browser evidence riêng |

## 9. Acceptance criteria và evidence gates

### AC CONTRACT-01 lúc nghiệm thu bản draft

| ID | Điều kiện | Evidence đối chiếu | Kết quả tài liệu |
| --- | --- | --- | --- |
| C44-C01 | Baseline/nguồn/owner/dependency rõ, historical source khác hiện tại được phân biệt | §1–§3 và refs/source | PASS |
| C44-C02 | Scope/exclusions/list-detail boundary rõ; decision chưa ACK giữ mở | §2–§3 | PASS |
| C44-C03 | Projection có nguồn/type/null/omission/snapshot-current | §4 và example | PASS |
| C44-C04 | Filters/search/date/sort/pagination/options đủ giới hạn và expected | §5–§6, O01–O10 | PASS |
| C44-C05 | Quyền/precedence/envelope/error được đặc tả; code mới ghi là đề xuất | §7, O11 | PASS |
| C44-C06 | AC implementation có ID, oracle và evidence đúng loại | Bảng bên dưới; mọi runtime NOT VERIFIED | PASS |
| C44-C07 | Đúng ba file, links/anchors/whitespace đạt; giữ historical verdict/T01–T03 | Git diff/status, static link/content checks trên chat | PASS |

### Ma trận runtime V1 lúc đóng UC44

| ID | Expected quan sát được | Oracle / evidence bắt buộc | Trạng thái |
| --- | --- | --- | --- |
| C44-R01 | Chỉ persisted ADMIN ACTIVE đọc list/options; precedence, stale actor và direct-call account/role đúng | O11; Q44-02, DATA44-02, UI44-02 (§14/18/19) | PASS: direct-call/stale actor, thứ tự 401/403/422 và cookie/CSRF trên loopback |
| C44-R02 | Toàn hệ thống, đủ sáu status, không giới hạn owner/Invoice/active data | O01/O04/O07; Q44-03, DATA44-03, UI44-03 (§14/18/19) | PASS trong fixtures MySQL: global, đủ sáu trạng thái, dữ liệu inactive và nguồn Booking độc lập Invoice |
| C44-R03 | Đúng19 field nguồn/type/null; không PII ngoài allowlist; giữ FK filter còn biết, malformed fail-safe, tiền/date không tính lại | O04/O06/O10; Q44-06/07, DATA44-03/06, L44-04, UI44-03 (§14/15/18/19) | PASS projection, snapshot, null và malformed. Relation-gap có mapper fixture/SQL shape. Orphan DB thực NOT VERIFIED |
| C44-R04 | AND filters/OR search, literal wildcard/collation và date boundary đúng | O01–O03/O08; Q44-01/04, CD44-03, DATA44-04 (§14/17/18) | PASS AND/OR, LIKE literal, collation utf8mb4_unicode_ci và biên ngày inclusive |
| C44-R05 | Sort tie-break/page/empty/options không duplicate và không phụ thuộc Invoice | O01/O04/O05/O09; Q44-05, DATA44-04/05, UI44-03 (§14/18/19) | PASS thứ tự/page/rỗng và options source Booking, scope, dedup và phân trang |
| C44-R06 | Lỗi đúng status/code/shape,500 không stack trace; malformed success không fake empty | O08/O11; L44-05, HP44-02, DATA44-02/06, UI44-04 (§15/16/18/19) | PASS mã lỗi/field/500 an toàn. Node xác nhận malformed success là lỗi contract |
| C44-R07 | UI Việt/VND, loading/empty/error/success, retry/reset/selected options hoạt động | UI44-03/04, RESP44-03/04 (§19/20) | PASS trong Edge loopback: Việt/VND, loading/empty/error, retry/reset và filters/options |
| C44-R08 | Không stale response khi filter/page/session/unmount; cancellation đúng | O12; HP44-06, CD44-06, UI44-05, RESP44-04 (§16/17/19/20) | PASS cho filter/page/logout với CDP. Rời route độc lập ngoài logout NOT VERIFIED |
| C44-R09 | Responsive/keyboard/focus, label/status/error đọc được | RESP44-01–04 (§20), UI44-06 gốc (§19) | PASS sau sửa ở 360/768/1440, keyboard và zoom thật 200%. Giữ FAIL lịch sử. Screen reader NOT VERIFIED |
| C44-R10 | GET không ghi Booking/Service/Invoice; regression UC45/39/47/48 và cleanup owned đúng | O12; Q44-08, DATA44-06/07, UI44-07 và RESP44-06 (§14/18–20) | PARTIAL tổng hợp: SELECT-only, fingerprints, cleanup và hồi quy tự động PASS. Runtime riêng UC45/39/47/48 NOT VERIFIED |

## 10. Chuỗi task và điểm tích hợp sau contract

LIST-01 tích hợp list qua [module routes](../../backend/routes/api/admin-bookings.php) và [controller](../../backend/app/Http/Controllers/Booking/AdminBookingController.php). Bốn file dùng chung được duyệt cho điểm ghép là [API root](../../backend/routes/api.php), [ErrorCode](../../backend/app/Exceptions/ErrorCode.php), [GlobalExceptionHandler](../../backend/app/Exceptions/GlobalExceptionHandler.php) và [AppRouter](../../frontend/src/routes/AppRouter.jsx). HP-01 thêm options và allowlist route tương ứng. CD-01 mở rộng Customer/Date. Menu Admin có sẵn.

UI dùng [AdminBookingListPage](../../frontend/src/pages/admin/AdminBookingListPage.jsx), [domain service](../../frontend/src/services/adminBookingService.js), [API adapter](../../frontend/src/api/adminBookingApi.js) và [mapper](../../frontend/src/mappers/adminBookingMapper.js). QA dùng [MySQL runner](../../backend/tests/Support/RunUc44MySqlTests.ps1), [browser bootstrap](../../backend/tests/Support/Uc44BrowserServer.php) và [Edge harness](../../frontend/tests/support/uc44Browser.mjs).

## 11. Ledger và giới hạn nghiệm thu

Verdict và giới hạn trong ledger áp dụng tại lượt gốc. Các giới hạn còn mở tại thời điểm đóng xem §21.

| Checkpoint | Technical result | Owner acceptance / giới hạn |
| --- | --- | --- |
| UC44-START-01 | Audit source và Mini Plan trên chat, không code/QA/DB | Owner chấp nhận kế hoạch tổng thể; không phải nghiệm thu UC44 |
| UC44-CONTRACT-01 | TECHNICAL PASS tài liệu; C44-C01–07 PASS, không runtime evidence mới | OWNER ACCEPTED kết quả tài liệu trên chat, checkpoint 2026-11-21; D44-01–07 còn chờ ACK riêng |
| UC44-ENV-01 | TECHNICAL PASS môi trường; E44-01–07 PASS, xem §12 | OWNER ACCEPTED kết quả ENV-01 trên chat ngày 2026-11-21; không đóng UC44 |
| UC44-CONTRACT-02 | TECHNICAL PASS tài liệu; C44-D01–07 PASS, V1 reader đủ cơ sở lập Mini Plan QUERY-01 | OWNER ACCEPTED kết quả CONTRACT-02 trên chat ngày 2026-11-21; không external ACK hoặc quyền sửa code |
| UC44-QUERY-01 | TECHNICAL PASS reader; Q44-01–09 PASS, MySQL và hồi quy tại §14 | OWNER ACCEPTED kết quả QUERY-01 theo yêu cầu nghiệm thu trên chat ngày 2026-11-21; không đóng UC44 |
| UC44-LIST-01 | TECHNICAL PARTIAL; HTTP SQLite, Node, lint/build tại §15 | OWNER ACCEPTED phạm vi đã kiểm chứng trên chat ngày 2026-11-21. Browser và MySQL HTTP chưa kiểm chứng |
| UC44-FILTER-HP-01 | TECHNICAL PASS phạm vi HP-01, evidence §16 | OWNER ACCEPTED phạm vi đã kiểm chứng ngày 2026-11-21. MySQL HTTP và browser Laravel/Sanctum thật giữ NOT VERIFIED |
| UC44-FILTER-CD-01 | TECHNICAL PASS phạm vi CD-01, evidence §17 | OWNER ACCEPTED phạm vi đã kiểm chứng ngày 2026-11-22. MySQL HTTP và browser Laravel/Sanctum thật giữ NOT VERIFIED ở lượt CD-01 |
| UC44-QA-DATA-01 | TECHNICAL PASS MySQL HTTP qua Laravel kernel, evidence §18 | OWNER ACCEPTED phạm vi đã kiểm chứng ngày 2026-11-22 |
| UC44-QA-UI-01 | TECHNICAL FAIL do UI44-06. UI44-01–05 và UI44-07 PASS, evidence §19 | OWNER ACCEPTED UI44-01–05 và UI44-07 ngày 2026-11-22. UI44-06 của lượt này giữ FAIL |
| UC44-FIX-RESP-01 | TECHNICAL PASS RESP44-01–06, evidence §20 | OWNER ACCEPTED RESP44-01–06 ngày 2026-11-22. Sẵn sàng lập Mini Plan CLOSE-01 |
| UC44-CLOSE-01 | TECHNICAL PASS CLOSE44-01–06, evidence §21 | OWNER ACCEPTED ngày 2026-11-22. UC44 V1 CLOSED TV3 theo phạm vi đã kiểm chứng |

CONTRACT-01 kiểm 80 link, 12 anchor, JSON 19 trường, 7 AC tài liệu, 10 AC runtime và 12 oracle. Runtime ở lượt đó giữ NOT VERIFIED.

## 12. UC44-ENV-01 — Môi trường MySQL QA cô lập

Environment dùng MySQL Docker **8.4.13** tại loopback 127.0.0.1:3307, PHP **8.2.12** và collation utf8mb4_unicode_ci. [Environment guard](../../backend/tests/Support/Uc44MySqlEnvironment.php) kiểm opt-in, manifest, effective connection, identity và grants trước migration/probe. [TestCase](../../backend/tests/Support/Uc44MySqlTestCase.php) migrate schema QA rỗng và dùng transaction/rollback. [Guard unit](../../backend/tests/Unit/Uc44MySqlEnvironmentTest.php) và [MySQL smoke](../../backend/tests/Feature/Booking/Uc44EnvironmentMySqlTest.php) kiểm guard, schema, quyền và rollback.

Chạy PowerShell từ root theo mẫu sau. Mỗi lượt có schema stayhub_uc44_<guid32>_test, user riêng và log đã lọc secrets. Negative controls gồm thiếu opt-in, sai suite, AfterSchema, Collision, test failure sau write, và BrowserStartup cho suite Browser.

    pwsh -NoProfile -File backend/tests/Support/RunUc44MySqlTests.ps1 -Provision -Suite Environment

Các suite Query, Data và Browser dùng cùng lệnh với tên suite tương ứng.

| Run ID / check | Kết quả thực tế | Cleanup / preservation |
| --- | --- | --- |
| d1270a5b8bc14a00a65baf944e5d6c1c — lượt đầu | **FAIL**, exit1; 71 unit PASS/4 feature FAIL,87 assertions: guard chung từ chối vì thiếu child ALLOW_EXTERNAL_TEST_DB. Chưa migration/probe write | PASS; 0 schema/user/connection owned, dev/protected files không đổi |
| 80b361ca72fe475cae8dfb247744ae8c — sửa/rerun | **PASS**, exit0; **75 tests/123 assertions**, gồm71 unit và4 MySQL feature, không skips | PASS; owned resources0, dev/protected files không đổi |
| 62998feaba8147e4a8c5a22fe4a67e66 — AfterSchema | Expected negative exit1, marker UC44_EXPECTED_FAILURE_AFTER_SCHEMA | PASS; đã xóa đúng schema của run, user chưa tạo; resources0 |
| d987c8a9066a4335854696cf1900968e — Collision | Expected negative exit1, UC44_EXPECTED_COLLISION_REJECTED; schema definition hash giữ nguyên khi reject name collision | PASS; schema do chính run tạo được dọn, resources0 |
| f305df364542451492a6e74aff0f62c4 — AfterWrite | Expected negative exit1; **1 failed/74 passed,123 assertions**, đúng UC44_EXPECTED_TEST_FAILURE_AFTER_WRITE; test kế tiếp xác nhận probe không leak | PASS; resources0, dev/protected files không đổi |
| no-opt-in / invalid-suite | Hai controlled rejections exit1, đúng marker; không DB opened/provision | Không có resource tạo để cleanup |
| Unit riêng trước MySQL | **71 passed/87 assertions**, exit0; chỉ guard logic, chưa MySQL | Artifacts scratch owned, không DB |
| Full Backend regression | `php artisan test --no-ansi --do-not-cache-result`: **2794 passed/480 skipped,11660 assertions**, exit0,67.84s; PHPUnit SQLite defaults | Skips giữ nguyên ý nghĩa guard; gồm4 UC44 MySQL skips khi không dùng runner; không thay MySQL evidence riêng |
| PHP syntax/PowerShell parser | Bốn file PHP mới php -l PASS, runner PowerShell parser PASS | Static evidence |

Năm lượt provision còn 0 schema/user/connection. Fingerprint 27 bảng dev và 1.011 đường dẫn bảo vệ khớp. Negative control SELECT và INSERT zero-row trên dev bị từ chối với MySQL 1142. Artifacts tại .agent-workspace/tv3/uc44-env-01/<run_id>/ và [báo cáo tổng hợp](../../.agent-workspace/tv3/uc44-env-01/45d72800527844f6987ccf051c9f9f3d/run-summary.json).

| ID | Kết quả | Evidence |
| --- | --- | --- |
| E44-01 | PASS |71 guard unit cases; no-opt-in/invalid-suite controlled rejections; manifest/type/config/identity/grants negatives |
| E44-02 | PASS | Manifest/effective config/Connection identity và SELECT DATABASE()/UUID/account/grants qua runner |
| E44-03 | PASS | Positive MySQL75/123; migrations, InnoDB/DATE/DECIMAL/collation và transaction rollback |
| E44-04 | PASS | Scoped literal grant; dev SELECT/zero-row INSERT denied1142 |
| E44-05 | PASS | Success/AfterSchema/Collision/AfterWrite cleanup; owned resources0, probe không leak |
| E44-06 | PASS |27 bảng dev unchanged;1011 protected-file hashes/run; final task scope/accepted working tree kiểm riêng |
| E44-07 | PASS | Lệnh/counts/engine/expected negatives ghi tách; sanitized artifacts; syntax/parser/regression/static checks |

## 13. UC44-CONTRACT-02 — Quyết định đọc và readiness

Owner nghiệm thu CONTRACT-02 ngày 2026-11-21. Các quyết định tại §3 đủ để bắt đầu reader. Lượt này kiểm tài liệu/source, 64 link nội bộ và JSON minh họa 19 trường. Runtime ở checkpoint này giữ NOT VERIFIED.

| ID | Tiêu chí / evidence đối chiếu | Kết quả |
| --- | --- | --- |
| C44-D01 | §3 đủ 7 decision, verdict/owner/nguồn approval và giới hạn D44-07 | PASS |
| C44-D02 | Ownership matrix phân biệt quyết định TV3/facts source/external ACK; không tạo ACK TV1/TV2 | PASS |
| C44-D03 | §4–§7 thống nhất projection/FK-null filters/options/date/raw input/quyền/lỗi | PASS |
| C44-D04 | 19-field JSON example, O01–O12 và C44-R01–10 khớp V1; runtime vẫn NOT VERIFIED | PASS |
| C44-D05 | Readiness gates tách đủ-plan/chưa-code, shared scope và điều kiện dừng | PASS |
| C44-D06 | CONTRACT-01/ENV-01 acceptance giữ; §12 và checklist dependency/evidence giữ nguyên | PASS |
| C44-D07 | Chỉ 2 files sửa, không file/artifact mới; outside-scope hashes/links/JSON/whitespace/Git đạt | PASS |

## 14. UC44-QUERY-01 — Reader Admin Booking trực tiếp

Reader trực tiếp trả typed item trong paginator. Common PageResponse được kiểm trước khi có HTTP route. Mapper đọc snapshot, validate raw, parse timestamp UTC và bảo toàn null/0/whitespace. Quan hệ thiếu dùng mapper facts synthetic và SQL shape theo FK.

| File | Action / trách nhiệm |
| --- | --- |
| [AdminBookingQueryRequest](../../backend/app/DTOs/Booking/Requests/AdminBookingQueryRequest.php) | CREATE; typed normalized input, bounds/status/date/search invariants và query allowlist; không raw HTTP parser/422 adapter |
| [AdminBookingListItemResponse](../../backend/app/DTOs/Booking/Responses/AdminBookingListItemResponse.php) | CREATE; immutable Response DTO, serialize đúng19 field, date/UTC timestamp transforms |
| [AdminBookingMapper](../../backend/app/Mappers/Booking/AdminBookingMapper.php) | CREATE; kiểm raw stored values trước casts, loaded relations, null/FK fallback; dùng EloquentObjectMapper/Symfony hiện có, không query/tính lại tiền |
| [AdminBookingRepository](../../backend/app/Repositories/Booking/AdminBookingRepository.php) | CREATE; persisted actor lookup; scoped select/eager loads, all-system filters/search/date/order/explicit pagination |
| [AdminBookingQueryService](../../backend/app/Services/Booking/AdminBookingQueryService.php) | CREATE; actor tồn tại/ACTIVE/ADMIN trước Booking query; map paginator, domain403 theo contract |
| [AdminBookingQueryContractTest](../../backend/tests/Unit/AdminBookingQueryContractTest.php) | CREATE; DTO/mapper boundary/malformed/null/maximum IDs-money và zero connection control |
| [Uc44AdminBookingQueryMySqlTest](../../backend/tests/Feature/Booking/Uc44AdminBookingQueryMySqlTest.php) | CREATE; fixture nhỏ tại test, direct-call/query/projection/read-only/MySQL oracle |
| [Runner](../../backend/tests/Support/RunUc44MySqlTests.ps1) | MODIFY; allowlist thêm Query; suite này chạy Environment guards/smoke rồi typed/mapper/query tests; evidence Query riêng, giữ Environment behavior |
| Hồ sơ UC44 này; [checklist](TV3_CHECKLIST.md) | MODIFY; technical status/AC/ledger và checkpoint ngắn |

| Run / command | Kết quả thực tế | Giới hạn / cleanup |
| --- | --- | --- |
| Focused unit lượt đầu | **FAIL**, exit1;46 PASS/1 FAIL,74 assertions: created_at lệch1 giờ vì Eloquent timestamp cast dùng timezone mặc định PHP CLI | Đã sửa mapper parse validated timestamp raw theo UTC trước mapping; không sửa timezone/config global. Metadata lỗi tại unit-initial-result.json, chi tiết quan sát trên chat |
| Focused unit sau sửa | **PASS**, exit0;47 tests/76 assertions | Không DB; unit-tests.txt |
| Query run 467543943ee845b6bd2e88bb7acd7682 | **PASS**, exit0;128 tests/315 assertions | 75 Environment +47 unit mới +6 MySQL query; sau đó bổ sung SQL-shape/stale-actor control và rerun |
| Query run c64831bb3b404a98807d307c840f57a4 | **PASS**, exit0;**129 tests/327 assertions**, không skips |75 Environment +47 unit mới +7 MySQL query; positive cuối trên source hiện tại |
| Environment regression f7230a5ba4da4c54a7b2502d1f77f727 | **PASS**, exit0;75 tests/123 assertions | Hành vi cũ giữ; artifact theo runner Environment hiện hữu tại `.agent-workspace/tv3/uc44-env-01/f7230a5ba4da4c54a7b2502d1f77f727/` |
| Full Backend `php artisan test --no-ansi --do-not-cache-result` | **PASS**, exit0;**2841 passed/487 skipped,11736 assertions**,68.88s | PHPUnit SQLite defaults;7 query MySQL tests mới skip khi không dùng runner, không thay MySQL evidence riêng |
| Syntax/parser/static | PHP files mới syntax PASS, runner PowerShell parser PASS; final scope/links/whitespace kiểm riêng | Không frontend change nên không Node domain tests/lint/build/browser |

Query và Environment regression xác nhận cleanup resources = 0, fingerprint 27 bảng dev và 1.018 đường dẫn bảo vệ khớp mỗi run. List đầy đủ dùng 7 SELECT, mapper riêng dùng 0 query. Actor bị từ chối chỉ qua lookup actor. [Artifact tổng hợp Query](../../.agent-workspace/tv3/uc44-query-01/909b65ec70de484d933925ae35075b91/run-summary.json) giữ lần lệch timezone trước sửa và regression sau sửa.

| ID | Kết quả | Evidence |
| --- | --- | --- |
| Q44-01 | PASS | Typed DTO defaults/bounds/status/normalized search/calendar/date range; raw HTTP validation chưa có |
| Q44-02 | PASS | Direct-call null/unsaved/deleted/wrong role/blocked/inactive/stale actor;403 và không Booking query |
| Q44-03 | PASS | Six-status/multi-owner/inactive-data fixtures, Invoice count0 |
| Q44-04 | PASS | MySQL literal wildcard/OR group dưới AND, combined filters, one/two-sided inclusive check-in dates |
| Q44-05 | PASS | created_at DESC/id DESC tie-break/null-last; explicit page, out-of-range/empty, Common meta/links serialization |
| Q44-06 | PASS | Exact19 fields, decimal/null/text/date/current names, không PII/tính lại snapshot |
| Q44-07 | PASS | Synthetic relation-null/FK và malformed tests; SQL shape cho known-FK filters; no-lazy-load/zero mapper queries |
| Q44-08 | PASS | Reader SELECT-only, fingerprints, guards và cleanup owned ở cả3 runtime runs |
| Q44-09 | PASS |7 CREATE/3 MODIFY source scope; focused/full/regression/syntax/parser/static evidence; artifact placement deviation ghi rõ |

## 15. UC44-LIST-01 — Danh sách Booking cho Admin

LIST-01 tích hợp route list, raw validation, projection Common và trang React với search/status/phân trang/reset/retry. Owner nghiệm thu phần đã kiểm ngày 2026-11-21. Browser và MySQL HTTP ở checkpoint này giữ NOT VERIFIED. L44-01/06/07 giữ PARTIAL lịch sử.

| Kiểm tra | Kết quả |
| --- | --- |
| Route registry | PASS, 1 GET/HEAD `api/admin/bookings` |
| PHPUnit tập trung | PASS, 54 tests/123 assertions (7 HTTP SQLite và 47 unit reader) |
| PHPUnit đầy đủ | PASS, 2848 passed/487 skipped, 11792 assertions, 83.70s, SQLite in-memory defaults |
| Node focused / regression | PASS, 4 / 427 tests, 0 failed ở lượt chạy hoàn chỉnh |
| Frontend lint/build cuối | PASS, lint 0 errors/3 warnings Fast Refresh ở component dùng chung, Vite cảnh báo chunk JS trên 500 kB |

| AC | Kết quả | Evidence |
| --- | --- | --- |
| L44-01 | PARTIAL | API registry, router/menu và build PASS. Browser smoke NOT VERIFIED |
| L44-02 | PASS | HTTP 401/403 trước query validation, BLOCKED/INACTIVE và stale role |
| L44-03 | PASS | HTTP errors 9 keys, range, Unicode dài, page boundaries, search 0, unknown key bị bỏ |
| L44-04 | PASS | HTTP projection 19 keys, decimal/date và page/links. Node nullable fixture và lỗi dữ liệu sai |
| L44-05 | PASS | HTTP 422 theo field, 500 an toàn với debug bật, Node phân biệt malformed và empty |
| L44-06 | PARTIAL | Trang, state và API wiring có trong source/build. Tương tác browser NOT VERIFIED |
| L44-07 | PARTIAL | Node truyền signal/cancellation, source kiểm query và auth snapshot. Browser delayed/session NOT VERIFIED |
| L44-08 | PASS | 9 CREATE, 7 MODIFY, 0 DELETE theo scope. Hồi quy và whitespace check |

## 16. UC44-FILTER-HP-01 — Bộ lọc Host và Property

HP-01 bổ sung options Host/Property từ Booking và lựa chọn phụ thuộc trên UI. Edge dùng tài khoản mô phỏng và HTTP fixture. Lượt cuối kiểm hủy request khi đổi Host/logout, giữ lựa chọn ngoài trang, lỗi options và retry độc lập.

| Kiểm tra | Kết quả |
| --- | --- |
| Route registry | PASS, GET/HEAD list và filter-options |
| PHPUnit tập trung | PASS, 59 tests / 200 assertions, gồm 5 tests options mới, 7 LIST-01 HTTP và 47 unit reader |
| PHPUnit đầy đủ | PASS, 2853 passed, 487 skipped, 11878 assertions, 72.29s, SQLite in-memory |
| Node tập trung / đầy đủ | PASS, 9 / 432 tests. Module mock có cảnh báo experimental |
| Lint / build | PASS, lint 0 lỗi và 3 cảnh báo Fast Refresh ở component dùng chung. Build cảnh báo chunk JS trên 500 kB |
| Edge headless kiểm tương tác | PASS, 12 checkpoints. React/Vite thật qua route Admin, fixture HTTP và tài khoản Admin mô phỏng. Check viewport desktop 1440×900 |
| Git và scope | PASS, 5 CREATE, 13 MODIFY, 0 DELETE. Whitespace/diff kiểm cả tracked lẫn file mới trong scope |

| AC | Kết quả | Evidence |
| --- | --- | --- |
| HP44-01 | PASS | HTTP guest/roles/blocked/inactive/stale, direct-call chỉ lookup actor trước từ chối |
| HP44-02 | PASS | 422 theo type/host_id/search/page/per_page, unknown key không đi vào links, lỗi ngoài dự kiến giữ safe500 |
| HP44-03 | PASS | SQLite fixture dedup, no Invoice, inactive, names/IDs, literal LIKE, hai projection, scope Host và page past-end |
| HP44-04 | PASS | HTTP ghép list, Node transport và browser fixture đổi/bỏ Host, reset, IDs với status/search |
| HP44-05 | PASS | Browser fixture giữ option đã chọn ngoài trang, phân biệt tên trùng bằng ID, lỗi/retry độc lập |
| HP44-06 | PASS phạm vi kiểm soát | Node signal/cancellation, Edge fixture abort request Host trước và pending options khi logout. Session thật theo QA-UI-01 |
| HP44-07 | PASS | Regression và scope đạt. Ghi giới hạn runtime dưới đây |

## 17. UC44-FILTER-CD-01 — Khách đặt phòng và ngày nhận phòng

CD-01 bổ sung Customer options và khoảng ngày nhận phòng inclusive. Customer giữ độc lập khi đổi Host/Property. Edge fixture kiểm ngày nháp, một đầu ngày, range sai, giữ lựa chọn qua trang và hủy request Customer.

| Kiểm tra | Kết quả |
| --- | --- |
| Route registry | PASS, hai route GET/HEAD list và filter-options |
| PHPUnit tập trung | PASS, 61 tests / 239 assertions. 7 filter-options/list CD, 7 HTTP LIST-01 và 47 unit reader |
| PHPUnit đầy đủ | PASS, 2855 passed, 487 skipped, 11917 assertions, 77.95s. SQLite in-memory |
| Node tập trung / đầy đủ | PASS, 11 / 434 tests. Module mock có cảnh báo experimental |
| Lint / build | PASS, lint 0 errors và 3 cảnh báo Fast Refresh ở component dùng chung. Vite cảnh báo chunk trên 500 kB |
| Edge headless | PASS, 15 checkpoint ở 1440×900, timezone Pacific/Honolulu, React/Vite thật với HTTP và Admin mô phỏng |
| Git/scope | PASS, 0 CREATE, 11 MODIFY, 0 DELETE. Diff và whitespace kiểm cả file untracked trong scope |

| AC | Kết quả | Bằng chứng |
| --- | --- | --- |
| CD44-01 | PASS | Route hiện có xử lý customers, HTTP quyền/active trước validation, stale actor bị từ chối khi gọi customers với query sai |
| CD44-02 | PASS | SQLite kiểm source Booking, dedup, role/status liên quan, tên trùng, search 0/literal, projection, links và host_id 422 |
| CD44-03 | PASS | SQLite kiểm biên inclusive theo check_in_date, mỗi đầu, khoảng bỏ trống/không match, range đảo chiều, ngày sai và query ghép/links |
| CD44-04 | PASS | Edge fixture giữ Customer qua trang options và Host/Property, phân biệt tên trùng qua ID, quản lý lỗi/retry/empty riêng |
| CD44-05 | PASS | Node và Edge fixture kiểm DATE không đổi khi browser ở Honolulu, reset trang, lỗi đúng field và reset bộ lọc |
| CD44-06 | PASS phạm vi kiểm soát | Node signal/cancellation, fixture kiểm abort request Customer khi đổi query và khi logout rời trang |
| CD44-07 | PASS | Hồi quy LIST-01/HP-01, lint/build, scope/Git và evidence limits |

## 18. UC44-QA-DATA-01 — HTTP list/options trên MySQL

Data suite gồm Environment + Query + 6 HTTP test qua Laravel kernel với actor MySQL persisted qua actingAs. Connection và cột booking code, tên User/Property cùng collation utf8mb4_unicode_ci. Fingerprint 8 bảng fixture khớp trước/sau 4 GET, SQL chỉ SELECT. Unsafe projection dùng ID ngoài JS-safe trong schema QA với FK bật.

| Run / kiểm tra | Kết quả |
| --- | --- |
| MySQL Data `75bdad802acf4a43ad723e9b0a0571bc` | PASS, exit 0. MySQL Docker 8.4.13, 135 tests / 543 assertions, không skip, 19.80s. |
| AfterSchema `51e390fdbf334ed094f6e39096c6b3d0` | Lỗi kỳ vọng, exit 1, UC44_EXPECTED_FAILURE_AFTER_SCHEMA. Dừng sau tạo schema, trước tạo user và chạy test. Cleanup PASS. |
| Full Backend SQLite | PASS, exit 0, 2855 passed / 493 skipped, 11917 assertions, 86.18s. Sáu ca HTTP MySQL mới skip đúng guard khi chạy SQLite. |
| Static / scope | PHP syntax, PowerShell parser, Git diff check PASS. 1 CREATE, 3 MODIFY, 0 DELETE. |

| AC | Kết quả | Bằng chứng |
| --- | --- | --- |
| DATA44-01 | PASS | Runner kiểm identity/manifest, scoped grants, MySQL connection, Data suite và evidence collation |
| DATA44-02 | PASS | Cả hai route 401/403/422 đúng thứ tự, gồm active, blocked, inactive, stale role. Request bị từ chối không query dữ liệu nghiệp vụ |
| DATA44-03 | PASS | Toàn hệ thống đủ 6 status, không phụ thuộc Invoice, đúng 19 trường, số tiền decimal strings, DATE và UTC/null |
| DATA44-04 | PASS | Filter kết hợp, search OR, literal LIKE, accent/case theo collation quan sát, biên ngày, tie-break và pagination |
| DATA44-05 | PASS | Host/Property/Customer từ Booking, đúng projection, dedup, name/ID sort, host scope, host_id sai ngữ cảnh trả 422 |
| DATA44-06 | PASS | Field errors, safe 500 debug bật, SQL chỉ đọc và fingerprint 8 bảng giữ nguyên |
| DATA44-07 | PASS | Hồi quy Environment/Query/Backend, positive và controlled cleanup, scope/Git/DB dev preservation |

## 19. UC44-QA-UI-01 — Browser Laravel/Sanctum trên MySQL

Edge dùng React/Vite và Laravel/Sanctum thật với session database trong schema QA. Ca failure/retry và giữ phản hồi chậm được điều khiển bằng CDP. Browser chính và BrowserStartup đều dọn 0 schema/user/connection/listener còn lại. PHP/Vite/Edge thoát, profile tạm đã dọn.

| Lượt | Kết quả |
| --- | --- |
| Browser `cc42ca8aba2640298f952bfa97b1e95e` | Exit 1 do hai checkpoint responsive FAIL. 9/11 checkpoint PASS. Edge 158.0.4370.33, PHP 8.2.12, Vite và MySQL Docker 8.4.13. [Browser evidence](../../.agent-workspace/tv3/uc44-qa-ui-01/cc42ca8aba2640298f952bfa97b1e95e/browser.json) |
| BrowserStartup `bf8818e92f3943a786811c8f0614e804` | Exit 1 đúng kỳ vọng `UC44_EXPECTED_FAILURE_AFTER_BROWSER_STARTUP`, sau khi PHP, Vite và Edge sẵn sàng. Cleanup PASS. [Kết quả](../../.agent-workspace/tv3/uc44-qa-ui-01/bf8818e92f3943a786811c8f0614e804/result.json) |
| MySQL Data `c97ca6775e8d4985a1431b31dd419ff1` | Exit 0, 135 PASS / 543 assertions / 0 skip, 13,28 s. Cleanup PASS. [Log](../../.agent-workspace/tv3/uc44-qa-data-01/c97ca6775e8d4985a1431b31dd419ff1/tests.txt) |
| Hồi quy SQLite và React | Full Backend exit 0, 2.855 PASS / 493 skip / 11.917 assertions, 83,50 s. Node exit 0, 434 PASS / 0 skip. Lint exit 0 với 3 Fast Refresh warnings hiện có. Build exit 0 với cảnh báo chunk >500 kB. |
| Kiểm tra tĩnh | PHP lint, PowerShell parser, Node syntax và Git diff check PASS. Git nhắc LF/CRLF ở AppRouter.jsx thuộc baseline. |

| AC | Kết quả | Evidence |
| --- | --- | --- |
| UI44-01 | PASS | React/Vite gọi Laravel thật trên loopback. Guard xác nhận MySQL/schema/user/grants và dùng driver database trên bảng sessions thuộc schema QA. Reload giữ được phiên. |
| UI44-02 | PASS | Hai API trả 401 cho guest và sau logout, 403 cho Host/Customer có phiên. Thiếu CSRF login trả 419. Admin đăng nhập qua UI, gọi /api/me và khôi phục phiên qua reload. Blocked/inactive login bị từ chối. |
| UI44-03 | PASS | Nhãn Việt, VND snapshot, sáu trạng thái, DATE không lệch khi browser ở Honolulu, created_at theo giờ VN/null, trang 2, search/status, Host/Property/Customer, range inclusive và reset. |
| UI44-04 | PASS | 422 thật có field error. Ngày đảo đưa focus về trường ngày cuối và chưa gửi request. Loading, empty, error/retry riêng cho list/options được quan sát qua HTTP thật kết hợp một lỗi mạng CDP mỗi khu vực. |
| UI44-05 | PASS | CDP giữ phản hồi Laravel 200. Đổi search/trang, đổi Host và logout hủy request cũ. Mã Booking cuối theo query mới và logout xóa danh sách. |
| UI44-06 | FAIL | Tab/Enter, focus và liên kết lỗi PASS. Khi tên cơ sở dài xuất hiện trong Booking card, scrollWidth tại 360/768/1440 px lần lượt là 524/1.019/1.541 px. Zoom thật 200% trên Edge cho viewport CSS 720 px, scrollWidth 870 px. Phần nội dung trong card tràn ngang. |
| UI44-07 | PASS | Hồi quy và đúng scope. Lượt browser chính và lỗi startup kiểm 8 bảng fixture và 27 bảng dev trước/sau, còn 0 schema/user/connection thuộc run. PHP, Vite, Edge đã thoát, 0 listener còn lại và profile Edge đã dọn. |

**UI44-06 FAIL ở lượt QA-UI-01 gốc.** Owner nghiệm thu UI44-01–05 và UI44-07 ngày 2026-11-22. Responsive chạy lại riêng tại §20.

## 20. UC44-FIX-RESP-01 — Sửa tràn ngang thẻ Booking

BookingRows dùng cột co được và overflow-wrap: anywhere. Harness đo DOM Range trên cùng tên dài của thẻ UI44-110. Tên giữ đầy đủ và bố cục vẫn có 1/2/3 cột.

| Cấu hình | Chiều rộng cuộn trước → sau | Độ tràn tối đa ngoài thẻ trước → sau | Số cột sau |
| --- | --- | --- | --- |
| 360 px | 524 → 345 px | 196 → 0 px | 1 |
| 768 px | 1.019 → 753 px | 290 → 0 px | 2 |
| 1.440 px | 1.541 → 1.425 px | 148 → 0 px | 3 |
| Edge zoom thật 200%, CSS 720 px | 870 → 712 px | 182 → 0 px | 2 |

Cùng harness trên hai lượt Edge 158.0.4370.33 → React/Vite → Laravel/Sanctum → MySQL Docker 8.4.13. Trước sửa exit 1 với 9/11 checkpoint PASS và hai checkpoint responsive FAIL. Sau sửa exit 0 với 11/11 checkpoint PASS. Tên dài nguyên vẹn và xuống 3–4 dòng. Tab/Enter, focus, liên kết lỗi, các luồng Sanctum, filter/options, phân trang, retry và hủy request đạt. [Evidence trước sửa](../../.agent-workspace/tv3/uc44-qa-ui-01/7b864522ede54978a9bd5ee03e3083b3/browser.json) · [Evidence sau sửa](../../.agent-workspace/tv3/uc44-qa-ui-01/41e4f633a15a4bf28d4c3617415cda53/browser.json)

| AC | Verdict | Evidence |
| --- | --- | --- |
| RESP44-01 | PASS | Harness nâng cấp tái hiện lỗi trên cùng tên cơ sở dài ở UI44-110. Xác nhận đủ tên và tràn thẻ ở cả bốn phép đo baseline. |
| RESP44-02 | PASS | Layout sau sửa nằm trong viewport ở 360/768/1.440 px và zoom Edge thật 200%. Cả bốn phép đo có độ tràn thẻ 0 px. |
| RESP44-03 | PASS | Tên nguyên vẹn xuống dòng. Bố cục 1/2/3 cột và hai cột tại zoom. Giá/ngày, nhãn, Tab/Enter, focus và liên kết lỗi hoạt động. |
| RESP44-04 | PASS | Browser suite sau sửa exit 0, 11/11 checkpoint PASS. |
| RESP44-05 | PASS | Node syntax và Git diff check đạt. Node 434 PASS, 0 skip, exit 0. Lint và build exit 0. Còn 3 cảnh báo Fast Refresh, cảnh báo chunk >500 kB và thông báo LF/CRLF của AppRouter.jsx thuộc baseline. |
| RESP44-06 | PASS | Đúng 4 file, 1.031 đường dẫn ngoài scope khớp. Hai lượt giữ fingerprint 8 bảng fixture và 27 bảng dev. 0 schema/user/connection/listener còn lại, PHP/Vite/Edge đã thoát, profile tạm đã dọn. |

## 21. UC44-CLOSE-01

**CLOSE44-01–06 TECHNICAL PASS / OWNER ACCEPTED ngày 2026-11-22.** UC44 V1 CLOSED TV3 cho reader Admin đã kiểm chứng. Tiến độ TV3 **16/18**, còn UC17 và UC61.

### Bằng chứng bàn giao

| Nguồn | Kết quả gốc và vai trò |
| --- | --- |
| MySQL Docker 8.4.13 — Environment và Query | Environment exit 0, 75 pass / 123 assertions. Query exit 0, 129 pass / 327 assertions. Kết hợp bằng chứng guard, direct-call, query, allowlist và kiểm chỉ đọc. [Environment](../../.agent-workspace/tv3/uc44-env-01/80b361ca72fe475cae8dfb247744ae8c/tests.txt) · [Query](../../.agent-workspace/tv3/uc44-query-01/c64831bb3b404a98807d307c840f57a4/tests.txt) |
| MySQL Docker 8.4.13 — HTTP Data | Exit 0, 135 pass / 543 assertions. Laravel HTTP kernel dùng actor persisted qua actingAs. Bao gồm hai API, thứ tự quyền/validation, collation, projection, error và SQL/fingerprint chỉ đọc. [Data](../../.agent-workspace/tv3/uc44-qa-data-01/75bdad802acf4a43ad723e9b0a0571bc/tests.txt) · [Collation](../../.agent-workspace/tv3/uc44-qa-data-01/75bdad802acf4a43ad723e9b0a0571bc/http-collation.json) |
| Edge 158 — Browser gốc và sau sửa | Laravel/Sanctum thật trên loopback với MySQL owned. Lượt gốc 9/11 PASS, exit 1 do hai checkpoint UI44-06 FAIL. Sau FIX-RESP-01 11/11 PASS, exit 0, độ tràn thẻ 0 px ở 360/768/1.440 và zoom thật 200%. [QA-UI gốc](../../.agent-workspace/tv3/uc44-qa-ui-01/cc42ca8aba2640298f952bfa97b1e95e/browser.json) · [FIX-RESP](../../.agent-workspace/tv3/uc44-qa-ui-01/41e4f633a15a4bf28d4c3617415cda53/browser.json) |
| Hồi quy tự động đã ghi tại §19–20 | Full Backend SQLite exit 0 với 2.855 pass / 493 skip / 11.917 assertions. Node 434 pass / 0 skip, lint và build exit 0. Ba cảnh báo Fast Refresh và cảnh báo bundle >500 kB giữ theo lượt gốc. |
| Cleanup và bảo toàn | Đọc lại artifact của bảy lượt đã chọn, gồm PASS, FAIL responsive và hai lỗi có kiểm soát AfterSchema/BrowserStartup. Cả bảy xác nhận 0 schema/user/connection owned, tiến trình con đã thoát và fingerprint dev/protected khớp. Các lượt browser có 0 listener và profile tạm đã dọn. [AfterSchema](../../.agent-workspace/tv3/uc44-qa-data-01/51e390fdbf334ed094f6e39096c6b3d0/result.json) · [BrowserStartup](../../.agent-workspace/tv3/uc44-qa-ui-01/bf8818e92f3943a786811c8f0614e804/result.json) · [Cleanup browser sau sửa](../../.agent-workspace/tv3/uc44-qa-ui-01/41e4f633a15a4bf28d4c3617415cda53/browser-cleanup.json) |

Artifacts trong .agent-workspace/tv3/ là local/ignored. Các link trỏ đến từng lượt chạy để tra log, manifest, kết quả và cleanup. Tài nguyên QA được tạo lại qua runner khi có scope kiểm chứng mới.

### Giới hạn khi đóng phạm vi V1

- Relation-gap ở C44-R03 có mapper fixture và SQL shape cho FK còn xác định được. Trường hợp DB có orphan thực giữ **NOT VERIFIED**. Contract §9 đã quy định kiểm trên DB chỉ khi được duyệt.
- C44-R08 có bằng chứng hủy request khi filter/đổi trang/logout và unmount lúc logout. Ca browser rời route độc lập khi request chờ giữ **NOT VERIFIED**. Evidence không bao quát mọi thứ tự mạng có thể xảy ra.
- C44-R10 có bằng chứng SELECT-only, fingerprint, cleanup và hồi quy tự động. Kiểm runtime riêng UC45/39/47/48 trên lượt này giữ **NOT VERIFIED**. Bằng chứng tự động và runtime riêng được ghi theo từng nguồn.
- Browser khác, screen reader, cảm ứng thiết bị vật lý, cookie/HTTPS ở domain triển khai và phục hồi sau hard-kill/mất điện giữ **NOT VERIFIED**. Mở deep link lúc chưa login đưa Admin về `/admin` sau đăng nhập, rồi vào Booking qua menu.
- `T01–T03` giữ **BLOCKED TV2** cho các luồng production Booking/Invoice/Review. Owner đã xác nhận đóng reader V1 theo phạm vi đã kiểm chứng ngày 2026-11-22.

### Acceptance Criteria CLOSE-01

| AC | Verdict | Evidence |
| --- | --- | --- |
| CLOSE44-01 | PASS | Ledger §11 liên kết verdict và owner acceptance từng task đến FIX-RESP-01. |
| CLOSE44-02 | PASS | Ma trận §9 đủ C44-R01–10 và ghi rõ giới hạn theo từng dòng. |
| CLOSE44-03 | PASS | Bảy lượt đối chiếu artifact, cleanup/fingerprint đạt. Giữ QA-UI-01 FAIL gốc, FIX-RESP-01 PASS và L44-01/06/07 PARTIAL lịch sử. |
| CLOSE44-04 | PASS | Ghi phạm vi reader, deep link, NOT VERIFIED và T01–T03 BLOCKED TV2. |
| CLOSE44-05 | PASS | Đúng 3 tài liệu, đồng bộ trạng thái 16/18 sau xác nhận đóng. Kiểm link/anchor/whitespace và hash ngoài scope. |
| CLOSE44-06 | PASS / OWNER ACCEPTED | Owner xác nhận đóng UC44 V1 theo phạm vi đã kiểm chứng ngày 2026-11-22. Cả ba tài liệu ghi 16/18, còn UC17 và UC61. |
