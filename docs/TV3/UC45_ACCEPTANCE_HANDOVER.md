# UC45 — Hồ sơ đối chiếu nghiệm thu và bàn giao

Biên tập: 2026-11-21 (Asia/Bangkok); tổng hợp/owner nghiệm thu gốc: 2026-11-18. Owner: TV3. Task: **UC45-CLOSE-01**.

**UC45 CLOSED TV3 — TECHNICAL PASS / OWNER ACCEPTED.** Các task thành phần đã nghiệm thu; UI thủ công theo owner, giữ giới hạn §8. Bản biên tập không bổ sung runtime evidence.

## 1. Baseline và phạm vi

- Nhánh `feature/tv3-uc45-admin-invoices`, HEAD `482db4619063f301954b437bc9714f795dcb538f` **cộng working tree chưa commit**. Checkout HEAD riêng sẽ thiếu implementation UC45 và cấu hình root đã nghiệm thu.
- Nguồn yêu cầu: [Use Case](../stayhub-usecase-system.md), [phân công TV3](../stayhub-team-work-assignment.md), scope các task đã duyệt và code thực tế; tuân thủ [kiến trúc](../../ARCHITECTURE.md).
- Bao gồm Admin xem danh sách toàn bộ hóa đơn, phân trang, tìm kiếm, trạng thái, Host/cơ sở, quyền truy cập, trạng thái UI và phục hồi request.
- Không bao gồm Admin tạo/sửa hóa đơn, thay trạng thái thanh toán, màn hình Admin invoice detail, export, thống kê doanh thu UC47/UC48 hoặc tích hợp workflow Booking TV2. Các chức năng này không được suy ra từ việc nghiệm thu danh sách UC45.
- Chỉ đọc dữ liệu nghiệp vụ; việc ghi session phục vụ authentication không phải mutation hóa đơn.

## 2. Luồng implementation

Backend: [billing routes](../../backend/routes/api/billing.php) → Sanctum/account.active → [AdminInvoiceController](../../backend/app/Http/Controllers/Invoice/AdminInvoiceController.php) → [AdminInvoiceValidator](../../backend/app/Validators/Invoice/AdminInvoiceValidator.php)/raw DTO → [InvoiceMapper](../../backend/app/Mappers/Invoice/InvoiceMapper.php)/typed DTO → [InvoiceQueryService](../../backend/app/Services/Invoice/InvoiceQueryService.php) → [InvoiceRepository](../../backend/app/Repositories/Invoice/InvoiceRepository.php)/Eloquent → mapper/response DTO → `ApiResponse::page`.

Controller kiểm quyền trước validation; QueryService kiểm lại role ADMIN khi được gọi trực tiếp. Repository chỉ query; DTO/mapper giới hạn projection. Không thêm Repository trừu tượng, schema hoặc writer UC45.

Frontend: route `/admin/invoices` trong [AppRouter](../../frontend/src/routes/AppRouter.jsx) → [AdminInvoiceListPage](../../frontend/src/pages/admin/AdminInvoiceListPage.jsx) → [invoiceService](../../frontend/src/services/invoiceService.js) → [invoiceApi](../../frontend/src/api/invoiceApi.js) → shared Axios. [invoiceMapper](../../frontend/src/mappers/invoiceMapper.js) chuẩn hóa dữ liệu; page dùng AbortController, serial và snapshot Auth để bỏ kết quả hết hiệu lực.

## 3. Contract hiện hành

| Điểm vào | Contract |
| --- | --- |
| `GET /api/admin/invoices` | `host_id`, `property_id`, `status`, `search`, `page`, `per_page` |
| `GET /api/admin/invoices/filter-options` | Bắt buộc `type=hosts` hoặc `type=properties`; hỗ trợ `search`, `page`, `per_page`; `host_id` chỉ hợp lệ cho `properties` |
| Authentication | Sanctum session/cookie, tài khoản active, role ADMIN ở backend; route guard frontend không thay thế kiểm quyền |
| Phân trang | Mặc định page 1, per_page 10; API nhận per_page 1–50; UI cung cấp 10/20/50; page tối đa 2147483647 |
| Định danh | Số nguyên dương dạng chuẩn, tối đa 9007199254740991; raw input được kiểm trước chuyển kiểu |
| Trạng thái | `UNPAID`, `PAID`, `REFUNDED`; UI “Tất cả” bỏ query status, không gửi `ALL` |
| Tìm kiếm | Trim, tối đa 255 ký tự; tìm OR trên invoice_code/customer_name/property_name snapshot; `%`, `_`, `!` được xử lý như ký tự literal |
| Kết hợp | Host AND cơ sở AND trạng thái AND nhóm search; Host/cơ sở không khớp trả trang rỗng |
| Thứ tự | Danh sách: created_at DESC rồi id DESC; options: tên ASC rồi id ASC |
| Options | Chỉ Host/cơ sở có hóa đơn liên quan; tên và quan hệ sở hữu hiện tại. Options không bị thu hẹp bởi status/search của danh sách hóa đơn |
| Lỗi | Guest 401; sai role/account 403; input không hợp lệ 422; lỗi nội bộ trả thông báo an toàn. Các test API kiểm cả thứ tự quyền trước validation |

Response phân trang có `data`, `links`, `meta`; không bọc thêm `data.data`. Mỗi hóa đơn có đúng projection:

```text
id, invoice_code, booking_id, customer_name, property_name, room_name,
total_amount, payment_status, paid_at, created_at
```

`total_amount` là decimal string; thời gian theo mapper ISO/null. Host option gồm `id,name`; cơ sở gồm `id,name,host_id`. Không đưa email/phone khách hàng, items hoặc quan hệ Eloquent thừa vào Admin list.

Thông tin hiển thị khách/cơ sở/phòng dùng snapshot Invoice; lọc ownership dựa quan hệ hiện tại Booking → Room → Property. Đổi Host bỏ cơ sở và về trang 1. UI dùng tiếng Việt, formatter VND và timezone Asia/Ho_Chi_Minh; chỉ trình bày tổng tiền từ API, không tính lại nghiệp vụ. Reload giữ phiên nhưng chưa lưu bộ lọc trong URL.

## 4. Ma trận nghiệm thu UC45

| AC | Yêu cầu | Bằng chứng | Kết quả |
| --- | --- | --- | --- |
| UC45-AC01 | Xác thực, role và account; quyền trước validation | API acceptance matrix guest/Host/Customer/blocked/inactive | PASS tự động |
| UC45-AC02 | Toàn bộ danh sách, projection, snapshot, decimal/date và sort | Query/list/API MySQL; UI B01/B12 | PASS tự động + owner |
| UC45-AC03 | Phân trang, giới hạn input, không mất/trùng dòng | API dataset cố định và B02/B03 | PASS tự động + owner |
| UC45-AC04 | Tìm kiếm/trạng thái, kết hợp AND/OR và empty | API expected IDs; B04–B06/B10 | PASS tự động + owner |
| UC45-AC05 | Options Host/cơ sở, ownership, scope và reset | API options/acceptance; B07–B11 | PASS tự động + owner |
| UC45-AC06 | Session, CSRF, reload, logout | HTTP cookie jar và browser A/ENV-ROOT-01 owner xác nhận | PASS HTTP + owner |
| UC45-AC07 | Loading, lỗi mạng và thử lại đúng vùng | C01–C06 | PASS owner kiểm tra thủ công |
| UC45-AC08 | Không áp dụng response cũ sau đổi lọc/rời trang/logout | C07–C10; owner xác nhận đã thấy Pending trước thao tác | PASS owner kiểm tra thủ công |
| UC45-AC09 | Responsive và bảng cuộn trong vùng chứa | B13, owner xác nhận toàn B01–B13 PASS | PASS owner kiểm tra thủ công |
| UC45-AC10 | Không mutation nghiệp vụ; hồi quy Host/Customer và cleanup | Business/dev fingerprints, privacy404 API; runner cleanup; owner cleanup | PASS tự động + owner |
| UC45-AC11 | Cấu hình chung và bảo vệ secrets trong phạm vi test | ENV-ROOT-01A/B, process overrides, HTTP env và bundle canary | PASS tự động + owner |

AC truy vết scope đã duyệt, không thêm yêu cầu; UI owner-reported tách khỏi API/service tests.

## 5. Hồ sơ kiểm thử đã có — không phải vừa chạy lại tại CLOSE-01

| Gate | Kết quả và môi trường | Nguồn evidence local ignored |
| --- | --- | --- |
| QA API | 227 tests, 1726 assertions PASS; MySQL Docker; Laravel HTTP kernel/actingAs, chưa chứng minh UI login | `.agent-workspace/tv3/uc45-qa-api-01/final-verification.json`; runtime `uc45-qa-env-01/13eed76a5e544910bcd3d923e9c7a2e6/` |
| Hồi quy sau gom env | Cùng allowlist 227 tests/1726 assertions PASS, cleanup PASS | `.agent-workspace/tv3/uc45-qa-env-01/905c253d1c1d466dacdbfa7583bd2b5c/` |
| Unit/config, Node, lint/build | 366 Unit/863 assertions; 175 Node PASS; lint 0 lỗi/3 warning hiện có; build PASS, warning chunk lớn | `.agent-workspace/tv3/env-root-01a/20261118-103846/final-verification.json` |
| Browser A / env | HTTP CSRF 204, thiếu CSRF 419, login/read/logout 200, guest sau logout 401; UI login/reload/logout/cleanup owner xác nhận | `uc45-qa-browser-01/resume-verification.json` là kết quả agent còn thiếu UI ở thời điểm đó; ENV-ROOT-01 và xác nhận owner trên chat bổ sung phần UI |
| Browser B | 16 đối chiếu HTTP PASS; B01–B13 và cleanup owner xác nhận PASS | `.agent-workspace/tv3/uc45-qa-browser-01b/20261118-110558/` |
| Browser C | 30 Node hỗ trợ PASS; HTTP session/CSRF/cleanup PASS; C01–C10, Pending và reset DevTools owner xác nhận PASS | `.agent-workspace/tv3/uc45-qa-browser-01c/20261118-120345/` |
| Root env HTTP/bundle | 6 probe env 403/404, public @fs control 200; private canary và 5 giá trị private được kiểm không xuất hiện trong output client | `.agent-workspace/tv3/env-root-01b/`; xem summary ENV-ROOT-01 và probe JSON trong run |

Các file kết quả lịch sử có thể còn trạng thái pending tại thời điểm tạo; xác nhận owner trên chat và checkpoint B/C/ENV sau đó là bằng chứng nghiệm thu bổ sung. Không sửa log lịch sử thành kết quả quan sát tự động.

## 6. Đối chiếu baseline tại CLOSE-01

Các từ “hiện tại/khớp” trong checkpoint này chỉ filesystem ngày CLOSE-01 (2026-11-18), không phải source tại lần đọc hồ sơ.

- 331 file core thuộc backend/app, backend/routes, backend/database và frontend/src khớp SHA256 baseline QA-API tại CLOSE-01.
- Thay đổi loader/config root sau QA-API đã được bao phủ bởi lần hồi quy MySQL/API sau ENV-ROOT-01; protected-after của lần đó khớp source tại CLOSE-01 ngoại trừ checklist tiến độ.
- Baseline đầy đủ của browser C (gồm file untracked và root env) khớp trước CLOSE-01 ngoại trừ checklist được cập nhật nghiệm thu.
- Runner API đổi trong QA-API theo scope đã ghi; không giả định nó phải khớp snapshot trước khi triển khai chính task QA-API.
- CLOSE-01 chỉ sửa ba file tài liệu theo scope; không chạy database, runner, lint/build hoặc toàn bộ test suite lại vì không có thay đổi implementation cần hồi quy. Kiểm tra mới là hash, liên kết, nội dung và Git diff/scope.

Chi tiết hash và kết quả kiểm tra mới lưu trong `.agent-workspace/tv3/uc45-close-01/<run-id>/`; không đưa secrets hoặc bản env vào hồ sơ tracked.

## 7. Cách tái kiểm tra khi bàn giao

Từ root, dùng PowerShell 7, PHP 8.2+/pdo_mysql, Node/dependencies đã cài và Docker Desktop. Cần container Compose MySQL 8.4 **hiện có, healthy**; runner từ chối nếu chưa có. Không chạy migrate/seed/reset trên database dev.

```powershell
# Chỉ xem trạng thái; không tạo lại volume/container.
docker compose ps
pwsh -File backend/tests/Support/RunUc45MySqlTests.ps1 -Provision

# Browser QA: chỉ mật khẩu fixture tạm, không dùng mật khẩu dev.
$env:STAYHUB_UC45_BROWSER_PASSWORD = Read-Host 'Mật khẩu QA tạm >=24 ký tự' -MaskInput
try {
    pwsh -File backend/tests/Support/RunUc45Browser.ps1 -Provision -MaxMinutes 90
} finally {
    Remove-Item Env:STAYHUB_UC45_BROWSER_PASSWORD -ErrorAction SilentlyContinue
}
```

Browser runner in URL riêng và đường dẫn run. Mở Frontend `/login`, dùng `admin@uc45.example.test` và mật khẩu fixture vừa nhập; mở `/admin/invoices`. Fixture có 60 hóa đơn, 12 Host, 24 cơ sở. Sau QA, tắt Request blocking, đặt No throttling; terminal thứ hai tạo `stop.request` trong đúng run:

```powershell
$qaRun = 'Đường dẫn sau UC45 browser ready: của run đang kiểm tra'
New-Item -ItemType File -Path (Join-Path $qaRun 'stop.request') -Force
```

Chờ thông báo cleanup verified và kiểm `result.json`, `cleanup.json`, `process-cleanup.json`. Không chỉnh bất kỳ file tracked/root env nào trong lúc runner chạy, kể cả checklist. Hướng dẫn B/C và case matrix chi tiết nằm trong các thư mục evidence tương ứng.

Frontend logic test, nếu cần chạy lại sau thay đổi source: `node --experimental-test-module-mocks --test frontend/tests/*.test.js` từ root; lint/build chạy từ frontend. Không dùng kết quả mock transport để kết luận race/UI browser.

## 8. Giới hạn và lưu ý reviewer

- QA runtime dùng MySQL Docker/schema riêng, không phải kiểm thử trực tiếp dữ liệu dev, production hay toàn bộ workflow Booking TV2.
- Không có bằng chứng agent tự động thao tác toàn bộ UI. Owner đã xác nhận A/B/C thủ công; C07–C10 có điều kiện Pending được xác nhận.
- DevTools blocking chứng minh lỗi kết nối/phục hồi, không phải lỗi HTTP 500. Safe500/projection/error contract dựa test API hiện có; không tuyên bố đã UI-test mọi mã lỗi HTTP.
- Không có kiểm thử tải lớn/performance hoặc kiểm toán bảo mật toàn hệ thống. Không tuyên bố secrets tuyệt đối không thể lộ ngoài các probe đã thực hiện.
- Cảnh báo lint/build nêu trên còn tồn tại. Bộ lọc chưa lưu URL; chưa có reset dữ liệu hoặc đổi payment status bởi UC45.
- Các lần lỗi setup đã có chạy lại: fixture trùng code ở QA-API; fingerprint do sửa checklist trong lúc browser B chạy; Docker daemon chưa chạy trước C. Những lần cuối liên quan đã PASS, giữ nguyên lịch sử failure.
- Artifact local ignored không có trong clone. Khi bàn giao, cung cấp riêng log đã lọc và hướng dẫn nếu reviewer cần tái kiểm; không gửi env, backup secrets, cookie/token, mật khẩu hoặc HAR chưa lọc.

## 9. Quyết định nghiệm thu

Owner nghiệm thu UC45-CLOSE-01 và đóng **UC45 phạm vi TV3** trên chat ngày 2026-11-18, theo phạm vi/giới hạn hồ sơ này. [Checklist](TV3_CHECKLIST.md) giữ trạng thái hiện hành; nghiệm thu không xác nhận commit/push/merge hoặc mở PR.
