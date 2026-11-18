# UC36 — Host xác nhận thanh toán Invoice V1

Contract được duyệt tại UC36-INV-02 ngày 2026-11-17, đã triển khai ở INV-03A/03B và nghiệm thu phạm vi TV3 sau QA/FIX/CLOSE. Tích hợp Booking TV2 hoãn riêng theo owner.
Tài liệu này giữ quy tắc nghiệp vụ; [hồ sơ nghiệm thu](UC36_TV2_HANDOVER.md) lưu kết quả 12 gate và giới hạn. [Checklist](TV3_CHECKLIST.md) là nguồn tiến độ.

## 1. Nguồn và baseline

- [Phân công TV3](../stayhub-team-work-assignment.md), [kiến trúc](../../ARCHITECTURE.md), [use case](../stayhub-usecase-system.md), [ERD](../stayhub-erd.md), [mockup](../stayhub-screen-mockups.md).
- Baseline: `feature/tv3-uc36-payment-status`, HEAD `6bd6838` cộng accepted working tree; gồm cả file mới chưa commit.
- [Billing routes](../../backend/routes/api/billing.php), [Host controller](../../backend/app/Http/Controllers/Invoice/HostInvoiceController.php), [payment validator](../../backend/app/Validators/Invoice/InvoicePaymentValidator.php), [payment service](../../backend/app/Services/Invoice/InvoicePaymentService.php), [repository](../../backend/app/Repositories/Invoice/InvoiceRepository.php).
- [Raw request DTO](../../backend/app/DTOs/Invoice/Requests/InvoicePaymentInputRequest.php), [typed request DTO](../../backend/app/DTOs/Invoice/Requests/UpdateInvoicePaymentStatusRequest.php), [mapper](../../backend/app/Mappers/Invoice/InvoiceMapper.php), [response DTO](../../backend/app/DTOs/Invoice/Responses/InvoiceResponse.php), [error handler](../../backend/app/Exceptions/GlobalExceptionHandler.php).
- [Frontend API](../../frontend/src/api/invoiceApi.js), [service](../../frontend/src/services/invoiceService.js), [mapper](../../frontend/src/mappers/invoiceMapper.js), [Host detail](../../frontend/src/pages/host/HostInvoiceDetailPage.jsx), [dialog](../../frontend/src/components/invoice/InvoicePaymentDialog.jsx), [detail component](../../frontend/src/components/invoice/InvoiceDetail.jsx).
- [Migration](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php) và [Invoice model](../../backend/app/Models/Invoice.php): payment_status enum UNPAID/PAID/REFUNDED, paid_at nullable; app timezone UTC, DTO ISO UTC. SQL đọc tại INV-02 đã đối chiếu schema Docker; không phải kiểm SQL mới ở lượt dọn tài liệu.
- Create Invoice từ Booking COMPLETED mặc định UNPAID/null. Billing có năm API đọc/tạo và PATCH payment; Host/Customer đọc lại qua contract Invoice hiện hữu.
- PHP XAMPP; MySQL Docker 8.4.13 tại 127.0.0.1:3307 trong các lượt QA. [Compose](../../docker-compose.yaml) đọc .env root; Laravel nhận DB variables qua process environment, giữ backend/.env. Không ghi mật khẩu vào tài liệu/log.

## 2. Phạm vi và quyền

Host xác nhận đã nhận **đủ tiền ngoài hệ thống**. Thao tác không chuyển tiền, không tích hợp payment gateway và không xác minh giao dịch ngân hàng.

- Actor lấy từ session Sanctum, không nhận actor/host/customer ID từ payload.
- Giữ middleware `auth:sanctum`, `account.active` và CSRF hiện hữu; không dùng route guard Frontend thay quyền Backend.
- Chỉ HOST ACTIVE được thực hiện; ADMIN/CUSTOMER không dùng mutation Host.
- Scope theo quan hệ hiện hữu `Invoice -> Booking -> Room -> Property.host_id`; không dùng snapshot property_name để phân quyền. Invoice không thuộc Host hoặc không tìm thấy quan hệ hợp lệ trả privacy 404.
- Không thêm điều kiện Property/Room ACTIVE hoặc đổi Booking lifecycle trong UC36. Invoice lịch sử vẫn có thể được thanh toán; nguồn Invoice hợp lệ đã được kiểm khi create.
- Không đổi amounts, invoice_items, snapshot hoặc Booking/BookingService. Không tính lại tiền từ catalog hoặc client.
- Không thêm migration, refund, chuyển ngược, thanh toán một phần hoặc chỉnh ngày thanh toán. KPI doanh thu thuộc RPT-01, không suy từ UC36.

## 3. Ma trận trạng thái và dữ liệu

Mọi request hợp lệ chỉ yêu cầu đích PAID. Kiểm tra sau khi đã scope/lock và đọc lại trạng thái trong transaction:

| Trạng thái hiện tại | paid_at | Kết quả | Ghi dữ liệu |
| --- | --- | --- | --- |
| UNPAID | null | 200, chuyển PAID | Ghi payment_status, paid_at theo server clock và updated_at |
| UNPAID | khác null | 409 INVOICE_PAYMENT_INCONSISTENT | Không ghi |
| PAID | khác null | 200, no-op idempotent | Không save/touch; giữ paid_at và updated_at |
| PAID | null | 409 INVOICE_PAYMENT_INCONSISTENT | Không ghi |
| REFUNDED | null hoặc khác null | 409 INVOICE_PAYMENT_CONFLICT | Không ghi |
| Giá trị ngoài miền trạng thái nếu gặp dữ liệu legacy | bất kỳ | 409 INVOICE_PAYMENT_INCONSISTENT | Không ghi |

`paid_at` biểu thị lúc Host xác nhận trên StayHub, không phải ngày chuyển tiền do client cung cấp. Dùng server clock, serialize ISO UTC theo DTO hiện hữu. Không đổi timezone toàn ứng dụng hoặc session DB; kiểm round-trip thời gian trong MySQL tests. Không tự chữa dữ liệu bất nhất.

Lần chuyển đầu tiên giữ nguyên subtotal, discount_amount, extra_fee, total_amount, các snapshot và tất cả items. paid_at không được thay bởi retry/no-op. No-op không đăng ký success log mới.

## 4. API contract hiện hữu

```http
PATCH /api/host/invoices/{invoice}/payment-status
Accept: application/json
Content-Type: application/json

{"payment_status":"PAID"}
```

Tên route: `host.invoices.payment-status.update`, đã đăng ký trong billing routes.

### Input và thứ tự lỗi

1. Middleware session/account/CSRF chạy theo baseline; lỗi middleware được giữ nguyên, không bảo đảm 401 ưu tiên hơn 419 trong mọi browser request.
2. Kiểm role HOST trước xử lý input domain.
3. Kiểm route ID bằng quy tắc canonical positive ID hiện hữu; ID sai định dạng trả 404.
4. Kiểm raw JSON payload bằng Symfony Validator trước conversion sang typed Request DTO. Body là object và chỉ có `payment_status`, giá trị string đúng `PAID`. Không trim/cast giá trị sai thành hợp lệ. Đọc body raw trước normalization khi cần phân biệt object/array và whitespace; không dựa duy nhất vào input đã qua TrimStrings/ConvertEmptyStringsToNull, không sửa bootstrap/shared middleware để đạt contract này.
5. Service thực hiện scoped lookup/lock trong transaction; thiếu/sai owner trả cùng 404.
6. Kiểm ma trận trạng thái; cập nhật hoặc no-op.

Thiếu payment_status, null, array, number, boolean, chuỗi rỗng/khác PAID/lowercase hoặc field ngoài whitelist trả 422. Ví dụ `paid_at`, `total_amount`, `host_id` không được chấp nhận. Dữ liệu sai và ID canonical nhưng chưa lookup: payload 422 ưu tiên hơn lookup 404, không phụ thuộc việc Invoice có tồn tại. JSON sai cú pháp giữ lỗi parser/framework, không biến thành success hoặc sửa shared middleware.

### Thành công

Chuyển lần đầu và no-op cùng trả **200**, một envelope `{"data": InvoiceDetail}`; dùng lại projection InvoiceResponse và items. Không tạo response partial hoặc thêm trường `changed` chỉ để phục vụ UI.

- ID/booking_id là integer; invoice_code và snapshot giữ nguyên.
- subtotal/discount_amount/extra_fee/total_amount là decimal strings hai chữ số thập phân.
- payment_status là PAID; paid_at là ISO UTC khác null; created_at giữ nguyên serialization hiện hữu.
- items luôn được load trong detail (kể cả mảng rỗng), giữ contract InvoiceItemResponse, decimal strings và nullable description.
- Không lộ internal relations hoặc trường ngoài allowlist. Không thay contract năm API Invoice cũ.

### Lỗi

| HTTP | Trường hợp | Body/code |
| --- | --- | --- |
| 401 | Chưa đăng nhập/hết session | Giữ authentication response hiện hữu |
| 403 | Tài khoản BLOCKED/INACTIVE | Giữ AccountStatusException và xử lý logout/session hiện hữu |
| 403 | Actor ACTIVE nhưng không phải HOST | `INVOICE_PAYMENT_FORBIDDEN`, message `Bạn không có quyền cập nhật trạng thái thanh toán.` |
| 404 | ID không hợp lệ/không tồn tại/không thuộc Host | `INVOICE_NOT_FOUND`, message `Không tìm thấy hóa đơn.` |
| 409 | REFUNDED | `INVOICE_PAYMENT_CONFLICT`, message `Không thể xác nhận thanh toán cho hóa đơn đã hoàn tiền.` |
| 409 | Trạng thái/paid_at bất nhất | `INVOICE_PAYMENT_INCONSISTENT`, message `Dữ liệu trạng thái thanh toán không nhất quán. Vui lòng liên hệ quản trị viên.` |
| 419 | CSRF không hợp lệ | Giữ framework response; không tự replay PATCH |
| 422 | Input không hợp lệ | Laravel validation shape: message/errors theo field, không thêm code bắt buộc |
| 500 | Lỗi ngoài dự kiến | `INTERNAL_SERVER_ERROR`, message an toàn theo handler hiện hữu |

AppException dùng shape `{"message":"...","code":"..."}`, không có errors khi rỗng. Validation: message `Dữ liệu không hợp lệ.`; thiếu field dùng lỗi `Trạng thái thanh toán là trường bắt buộc.`; field có mặt nhưng sai kiểu/giá trị dùng `Trạng thái thanh toán phải là PAID.`; mỗi field ngoài whitelist dùng `Trường này không được phép.`. Body không phải object dùng errors key `payload` với `Dữ liệu gửi lên phải là một đối tượng JSON.`. Các lỗi parser/middleware không bị ép sang envelope này.

Route payment đã được tích hợp vào allowlist GlobalExceptionHandler và ErrorCode trong UC36-INV-03A. Không dùng INVOICE_READ_FORBIDDEN cho mutation, không sửa bootstrap/shared Auth hoặc thay renderer của route cũ.

## 5. Transaction, concurrency và trách nhiệm lớp

- Controller điều phối actor/input/HTTP response. Validator giữ raw validation và domain facts, không query. Typed DTO không giữ HTTP Request hoặc session.
- Service cũng kiểm role/ownership khi được gọi trực tiếp, không chỉ dựa vào Controller; giữ transaction trên cùng connection với Repository. Repository scope theo Host và `lockForUpdate()` Invoice, đọc trạng thái sau khi lock; không quyết định từ snapshot UI hoặc model đã đọc trước transaction.
- Bảo toàn kiểm ownership trong workflow khóa. Contract Host hiện không có chuyển chủ Property; nếu bổ sung writer chuyển chủ phải phối hợp TV1 để chốt lock/recheck tương ứng, không tự sửa module TV1.
- Trong transaction: lookup/lock -> kiểm ma trận -> update nếu cần -> load items và dựng/kiểm serialization DTO. Lỗi persistence hoặc mapping trước commit phải rollback toàn thay đổi của mutation.
- Chỉ chạm Invoice khi có transition thực. Không gọi lại InvoiceCalculationService để tái tính tiền hoặc thay Booking/items.
- Tái sử dụng adapter Symfony Validator/ObjectMapper; mapper không query hoặc quyết định transition. Chỉ tạo DTO/validator có trách nhiệm thực tế. Nếu cần validation group payment, gọi tường minh; không tái dùng invoice-create chỉ để kiểm một update status.
- Log chuyển thành công đăng ký sau outer commit thực tế, chỉ chứa ID kỹ thuật/trạng thái. No-op và rollback không có success log; không log credentials, cookie, token hoặc thông tin tài chính cá nhân. Không dùng log-and-throw.
- Hai request PAID đồng thời trên cùng Invoice: request sau chờ lock, đọc PAID đã commit và trả 200 no-op. Chỉ một lần ghi, một paid_at, một success event. Outer rollback giữ nguyên UNPAID/null và không phát success event.
- Không tự retry toàn mutation khi timeout/network hoặc khi chưa biết đã commit. Lỗi lock/deadlock ngoài dự kiến không bị ngụy trang thành 409 nghiệp vụ; trả lỗi an toàn theo framework/handler, UI phục hồi bằng GET.

## 6. Hành vi Frontend

Host detail hiển thị trạng thái từ server, VND bằng Intl.NumberFormat; không tính lại tổng hoặc suy quyền chỉ từ nút.

| Tình huống | Hành vi |
| --- | --- |
| Đang tải detail/không có detail hợp lệ | Không cho xác nhận; hiển thị loading/error hiện hữu |
| UNPAID | Nút `Đánh dấu đã thanh toán`; mở xác nhận nhận đủ số tiền Backend trả |
| Pending PATCH | Khóa submit đồng bộ để chặn double-click, hiển thị đang xử lý |
| 200 transition/no-op | Dùng data qua mapper detail, thông báo `Đã xác nhận trạng thái thanh toán.`, ẩn action; không khẳng định thao tác hiện tại vừa thu tiền |
| PAID | Hiển thị đã thanh toán và paid_at; không có action chuyển ngược |
| REFUNDED | Hiển thị trạng thái; không có action xác nhận/refund |
| 401/403 tài khoản | Theo cơ chế Auth/session hiện hữu; không hiển thị success hoặc replay request |
| 403 domain/404/422 | Hiển thị lỗi phù hợp; 404 không giữ nút thao tác trên dữ liệu cũ; giữ field errors khi có |
| 409 | Hiển thị message, đọc lại detail; không tự chữa hoặc gửi lại PATCH |
| 419 | Hướng dẫn khôi phục phiên/CSRF theo baseline; không tự replay PATCH |
| Timeout/network/response success sai shape hoặc 5xx chưa rõ commit | Hiển thị `Chưa xác định được kết quả. Vui lòng kiểm tra lại hóa đơn.`; khóa gửi lại cho đến khi GET xác nhận |
| GET phục hồi trả PAID | Hiển thị trạng thái server, không PATCH lần nữa |
| GET phục hồi trả UNPAID | Cho thao tác xác nhận mới do người dùng chủ động sau khi đọc lại; server vẫn bảo vệ request cũ bằng lock/no-op |
| GET phục hồi thất bại | Giữ trạng thái chưa rõ, chỉ cho thử GET lại; không cung cấp retry PATCH mù |

Đổi route, unmount hoặc session/account phải bỏ qua response cũ bằng request identity/session baseline và cancellation phù hợp. Abort client không chứng minh server đã rollback; lần đọc mới vẫn quyết định trạng thái. Dialog có label, focus/keyboard, nội dung tiếng Việt; kiểm mobile/tablet/desktop. Không thêm router hoặc sửa shared Axios khi không cần và chưa được duyệt.

## 7. Môi trường và bảo toàn dữ liệu

Theo quyết định owner, **toàn bộ bằng chứng DB cho UC36 dùng MySQL Docker**, không dùng SQLite thay thế. PHPUnit là test runner, không đồng nghĩa SQLite. Node tests/lint/build tiếp tục kiểm Frontend.

- Schema test owned: `stayhub_uc36_test_<guid32>` (tên thực tế được ghi mỗi lượt). Không dùng `stayhub`, `mysql`, `test` hoặc schema không xác định owner.
- Tạo schema/user test bằng bước provision được duyệt trong Mini Plan triển khai/QA. User ứng dụng Docker hiện chỉ có quyền trên stayhub; không giả định có thể tạo schema hoặc tự nới quyền user đó. Cấp user test riêng đúng schema; quyền admin chỉ dùng cho provision/cleanup đã duyệt, không đưa vào code/test log.
- Trước boot/migrate/fixture/reset/cleanup: APP_ENV=testing, explicit opt-in test DB, DB_URL/socket không override; đối chiếu driver/host/port/schema hiệu lực, SELECT DATABASE(), version và identity instance. Dùng connection test nhất quán, không fallback sang backend/.env hoặc stayhub khi thiếu biến.
- Fixture Booking/Invoice/User/Property/Room do test sở hữu; không gọi bộ demo seed lên stayhub. Chưa được coi là tích hợp Booking production TV2.
- Concurrency dùng hai connection/process độc lập và barrier/lock probe, kiểm commit từ connection thứ ba khi cần; test tuần tự không thay thế race. Không outer wrapping transaction che mất commit/afterCommit cần quan sát.
- Giữ nguyên guards SQLite-only của suites cũ. Viết suite MySQL riêng; kết quả SQLite cũ/mới nếu chạy chỉ là hồi quy nền, không đóng AC UC36. Không ép full suite hiện hữu sang MySQL demo.
- Trước/sau runtime test ghi schema owned, counts/hash dữ liệu stayhub cần bảo vệ và cleanup. Chỉ drop schema/user fixture có owner, không xóa Docker volume, không reseed demo hoặc sửa datadir XAMPP/backup.

## 8. Đối chiếu kiểm chứng

12 gate UC36-T01–T12 và bằng chứng theo lượt nằm trong [hồ sơ nghiệm thu UC36](UC36_TV2_HANDOVER.md#3-ma-trận-12-gate). Các gate TV3 đã được nghiệm thu trong phạm vi và điều kiện ghi tại đó; không thay bằng kiểm chứng Booking production TV2.

Host PAID hiển thị thời điểm xác nhận; PAID thiếu paid_at hiển thị dấu gạch, UNPAID/REFUNDED không hiển thị thời điểm. Lỗi 419 dùng hướng dẫn tiếng Việt và GET thủ công, không tự replay PATCH (FIX-01 đã nghiệm thu).
