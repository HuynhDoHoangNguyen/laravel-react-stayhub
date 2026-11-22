# UC42 — Khóa / mở tài khoản

Các mục 1–8 ghi kết quả UC42-01 tại thời điểm bàn giao nền tảng. API và giao diện Customer bổ sung trong UC42-02 được ghi riêng từ mục 9; không ghi đè evidence cũ.

## 1. UC42-01 — Nền tảng nghiệp vụ

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh.
- Branch `feature/tv1-admin-management`, baseline HEAD `c3c9dc3`, working tree ban đầu sạch.
- Developer đã duyệt mini plan UC42-01: DTO, Validator, Service, Repository, exception, tests và báo cáo; task nền tảng thuộc TV1 theo phân công.
- Trạng thái kỹ thuật: **TECHNICAL PASS**, AC01–AC06 có evidence dưới đây. Nghiệm thu developer chờ review kết quả; duyệt plan không phải nghiệm thu code.
- UC42-01 chưa công khai API/UI. Toàn UC42 chưa hoàn tất; UC42-02/03 cần scope và nghiệm thu riêng.

Đã đối chiếu AGENTS.md, ARCHITECTURE.md, README, hướng dẫn frontend, ERD/use case/phân công, migrations/seeders/factories User và các luồng Auth/Profile/UC40/UC41 hiện có. Code/schema có `INACTIVE` dù phần enum tóm tắt trong phân công chỉ liệt kê ACTIVE/BLOCKED. Plan được duyệt giữ nguyên INACTIVE và loại trừ toàn bộ tài khoản Admin.

## 2. Backend và contract tích hợp

Entry point nền tảng:

```php
AccountStatusValidator::update(string $user, array $body, array $query = []): UpdateAccountStatusRequest
AccountStatusService::update(User $actor, UpdateAccountStatusRequest $input): AccountStatusResponse
```

Luồng Validator → typed Request DTO → Service → Repository → derived Response DTO. Actor được caller xác thực truyền riêng; không lấy actor/role từ input. Concrete classes dùng constructor DI tự resolve, không thêm binding hoặc interface dùng một lần.

Validator nhận route ID canonical `1..PHP_INT_MAX`, kiểm trước cast; body chỉ có `status` và `expected_status`, đều bắt buộc và chỉ nhận chuỗi ACTIVE/BLOCKED. Từ chối body field lạ, kể cả role/actor_id/user/name, và mọi query parameter. Route identity không bị body ghi đè. Validator không query DB. Service kiểm lại bounds/status allowlist khi caller dựng DTO trực tiếp.

Response DTO serialize đúng bốn fields:

```json
{"id":12,"status":"BLOCKED","updated_at":"2026-11-22T03:00:00.000000Z","changed":true}
```

`id` integer, `status` string, `changed` boolean; `updated_at` UTC ISO hoặc null với no-op trên bản ghi legacy. Khi có ghi, Repository đọc lại timestamp đã lưu để response giữ đúng độ chính xác của DB. DTO được dựng trực tiếp từ kết quả command theo kiến trúc; không cần lớp Mapper hoặc Symfony projection riêng.

Không đăng ký `PATCH /api/admin/users/{user}/status` trong task này. Endpoint là contract dự kiến cho UC42-02. UC42-02 phải thêm middleware, Controller, HTTP envelope/error translation và kiểm thử HTTP; chưa có evidence 403/404/409/422 của endpoint mới.

## 3. Quy tắc và transaction

| Điều kiện | Hành vi nền tảng |
| --- | --- |
| Actor tồn tại trong DB, ADMIN ACTIVE | Cho phép tiếp tục |
| Actor không được lưu/đã xóa/sai role/BLOCKED/INACTIVE | AuthorizationException |
| Target CUSTOMER/HOST, ACTIVE → BLOCKED | Ghi trạng thái khóa |
| Target CUSTOMER/HOST, BLOCKED → ACTIVE | Ghi trạng thái mở |
| Target INACTIVE hoặc status hiện tại khác expected_status | AccountStatusConflictException, không ghi |
| Expected khớp và status yêu cầu bằng status hiện tại | changed=false, không ghi/touch timestamp/log success |
| Target Admin, chính actor, hoặc không tồn tại | ModelNotFoundException cùng model/message, không kèm ID |

Service chỉ dùng ID từ actor model đã được lưu, đọc lại actor và target trong transaction; thuộc tính role/status cache không quyết định quyền. Repository deduplicate IDs rồi khóa từng primary key theo thứ tự số tăng dần bằng `lockForUpdate`. Quyền actor được kiểm trước scope target và transition sau khi đọc có khóa. Conflict được xét trước no-op, tránh biến request cũ thành thành công giả.

Write allowlist chỉ gồm `users.status`, `users.updated_at`. Không fill payload vào User, không cập nhật hồ sơ/role/password/remember_token, không cascade trạng thái Property/Room/Booking/Invoice. Unexpected exception tự nổi lên và transaction rollback; không bắt rồi log/rethrow.

Chỉ đăng ký success log khi `changed=true`, dùng `DB::afterCommit` chờ outer commit thực tế. Context chụp scalar `actor_id`, `user_id`, `from_status`, `to_status`; không giữ model thay đổi được hoặc dữ liệu cá nhân. No-op, từ chối, conflict và rollback không có log thành công.

Auth hiện có kiểm tra status khi login và đọc fresh User ở middleware account.active. Tài khoản BLOCKED bị từ chối ở request được bảo vệ kế tiếp khi đang BLOCKED; việc khóa không lập tức xóa mọi session. Phiên đã invalidated không được khôi phục bởi mở khóa. UC42-01 không thay đổi Auth/session và chưa chạy browser integration cho mutation mới.

## 4. File thay đổi

Đúng scope **9 files: 8 tạo, 1 sửa**, không xóa. Paths tính từ repository root.

| Action | File | Vai trò |
| --- | --- | --- |
| Tạo | backend/app/DTOs/UserManagement/Requests/UpdateAccountStatusRequest.php | Typed input |
| Tạo | backend/app/DTOs/UserManagement/Responses/AccountStatusResponse.php | Command result và JSON allowlist |
| Tạo | backend/app/Validators/UserManagement/AccountStatusValidator.php | Raw input, direct command, quyền và transition |
| Tạo | backend/app/Services/UserManagement/AccountStatusService.php | Transaction, workflow, response và afterCommit |
| Tạo | backend/app/Repositories/UserManagement/AccountStatusRepository.php | Ordered locks, projection và write allowlist |
| Tạo | backend/app/Exceptions/UserManagement/AccountStatusConflictException.php | Xung đột nghiệp vụ; chưa render HTTP |
| Tạo | backend/tests/Feature/UserManagement/AccountStatusTest.php | Service/Validator/persistence tests |
| Tạo | docs/TV1/UC42-REPORT.md | Báo cáo này |
| Sửa | docs/TV1/README.md | Mục lục và trạng thái UC42 |

Không sửa routes/Controller/Model/schema/seeders/Auth/shared Axios/frontend hoặc nghiệp vụ TV2/TV3.

## 5. Kiểm thử thực tế

Tests dùng SQLite `:memory:`, cấu hình testing/DB/cache/session được xác minh trước bootstrap/migration, từ chối config cache/DB URL override. `DatabaseMigrations` chỉ chạy trên DB in-memory cô lập; guard được gọi trước migration. Dùng transaction manager thực, không có transaction bọc mỗi test, để quan sát afterCommit tại outer commit thực tế. Không migrate/seed/reset dữ liệu development.

| Check | Kết quả | Giới hạn |
| --- | --- | --- |
| php artisan test --filter=AccountStatusTest trong sandbox | Không khởi chạy tests: cannot open vendor/autoload.php, exit 1 | Giới hạn đọc file của sandbox; đã chạy lại ngoài sandbox với DB guard |
| Focused run đầu ngoài sandbox | 4 failed, 61 passed, 223 assertions, exit 1 | Test lấy snapshot từ factory model thiếu nullable DB defaults/thứ tự cột; sửa lấy fresh persisted snapshot; production code không thay đổi vì lỗi này |
| Focused run sau sửa snapshot | **65 passed, 243 assertions**, 10.38s, exit 0 | SQLite, Service/Validator entry points |
| php artisan test --compact | **2.977 passed, 485 skipped, 12.956 assertions**, 254.20s, exit 0 | Full backend; skipped không phải ca đã chạy; không chứng minh runtime MySQL |
| php -l cho 7 file PHP mới | **PASS**, exit 0 | Không có lỗi cú pháp |
| Git diff/whitespace/scope và links báo cáo | **PASS**: đúng 9 files, tracked/new whitespace sạch, links tồn tại | Bao gồm file mới chưa track bằng no-index diff; core.autocrlf=false chỉ áp dụng lệnh check để tránh cảnh báo chuyển LF/CRLF |
| Frontend Node/lint/build/browser | Không chạy | Không có thay đổi frontend hoặc UI trong task kỹ thuật nền tảng |
| MySQL/MariaDB concurrency | **NOT VERIFIED** | SQLite kiểm tra thứ tự SELECT, không chứng minh FOR UPDATE hoặc tranh chấp đồng thời |

65 cases bao phủ bốn transitions Customer/Host, bốn no-op, actor wrong role/status và stale authority, actor deleted/unsaved, Admin/self/missing privacy, INACTIVE, expected-status conflict trước no-op, real outer commit/rollback, lỗi sau write và exception identity, thứ tự lookup khi target ID nhỏ hơn actor, timestamp null legacy, response types/allowlist, raw validation/query-free và direct DTO bounds.

Fixture thực có Property/RoomType/Room/Booking/Invoice/InvoiceItem. Sau khóa rồi mở cả Host và Customer, snapshots sáu bảng nghiệp vụ giữ nguyên. Tests không gọi API giả để tuyên bố có endpoint.

## 6. Acceptance criteria UC42-01

| ID | Tiêu chí | Trạng thái và evidence |
| --- | --- | --- |
| AC01 | Chỉ Admin ACTIVE được đổi trạng thái Customer/Host | PASS focused: direct service, stale/unsaved/deleted actor; fresh authority |
| AC02 | Đúng transition, expected status, no-op | PASS focused: 4 transitions, 4 no-op, stale expected bị từ chối trước no-op |
| AC03 | Bảo vệ INACTIVE/Admin/self | PASS focused: INACTIVE không ghi; Admin/self/missing cùng exception không chứa ID |
| AC04 | Transaction, rollback, log sau commit | PASS focused: real outer commit, outer rollback, lỗi sau write; không log trước commit hoặc sau rollback |
| AC05 | Input/output allowlist và không ghi ngoài scope | PASS focused: canonical ID/status/unknown fields; exact JSON types; snapshots users và sáu bảng nghiệp vụ |
| AC06 | Tests, diff và báo cáo có kết quả thực tế | PASS: focused 65/243, full 2.977 passed/485 skipped, syntax 7 files, scope 9 files và whitespace/links |

Trạng thái PASS ở đây chỉ áp dụng các tests đã chạy, không phải nghiệm thu toàn UC42 hoặc runtime MySQL.

## 7. Dependencies, giới hạn và bước tiếp

- Không có blockers ở scope nền tảng. UC42-01 TECHNICAL PASS; owner acceptance chờ developer review. Chưa nghiệm thu toàn UC42.
- Quyền HTTP, middleware order, CSRF, error envelopes và session behavior khi sử dụng mutation mới cần kiểm trong UC42-02/03. Không dựa vào exception mới để tự có HTTP 409; Controller phải dịch đúng exception nghiệp vụ, để lỗi ngoài dự kiến cho Laravel xử lý.
- UC42-02: API cùng giao diện khóa/mở Customer, dialog xác nhận, chống gửi trùng, refresh và reconcile sau timeout/mất phản hồi trước khi cân nhắc gửi lại. Không tự bắt đầu khi chưa có plan/scope được duyệt.
- UC42-03: tích hợp Host, kiểm thử session/recovery và nghiệm thu toàn UC42. MySQL locking/concurrency cần runner/fixture riêng được phép.
- Không commit/push/merge/rebase. Khi bàn giao chỉ đề xuất commit title và description.

## 8. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục đúng scope UC42-01 đã duyệt và ranh giới task nền tảng.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): phân lớp, raw/typed validation, ordered locks, transaction, derived DTO và log sau outer commit.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): AC evidence, kết quả lệnh thực tế, test isolation và tách giới hạn SQLite/MySQL/API/UI.

## 9. UC42-02 — API và giao diện khóa/mở Customer

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh.
- Branch `feature/tv1-admin-management`, HEAD `c3c9dc3`. Baseline có 9 file UC42-01 chưa commit đã bàn giao; không reset hoặc ghi đè phần nền tảng.
- Mini plan UC42-02 được developer duyệt trước khi sửa code. Scope gồm endpoint dùng chung và UI Customer; Host UI thuộc UC42-03.
- Trạng thái kỹ thuật: **TECHNICAL PASS**, C01–C06 có evidence dưới đây. Owner acceptance chờ developer review; chưa nghiệm thu toàn UC42.

### 9.1 Backend và HTTP contract

Thêm **PATCH /api/admin/users/{user}/status**, route name `admin.users.status.update`, middleware `auth:sanctum`, `account.active`, `role:ADMIN` cùng Sanctum stateful/CSRF hiện có.

Body chỉ nhận `status` và `expected_status`, mỗi field ACTIVE/BLOCKED. Route ID canonical, unknown body/query và enum được Validator UC42-01 kiểm tra trước cast. Controller lấy actor từ request, chuyển input vào typed DTO, gọi Service và trả `ApiResponse::success`. Nền tảng UC42-01 được tái sử dụng nguyên trạng.

| Trường hợp | HTTP và body |
| --- | --- |
| Transition hoặc no-op hợp lệ | 200, `{data:{id,status,updated_at,changed}}` |
| Chưa có session | 401 theo Laravel |
| Actor sai role | 403 theo role middleware |
| Actor BLOCKED/INACTIVE | 403, ACCOUNT_BLOCKED/ACCOUNT_INACTIVE và invalidation phiên hiện tại |
| Target Admin/self/missing | Cùng 404 `{message:"Không tìm thấy tài khoản."}` |
| Target INACTIVE hoặc expected status cũ | 409, ACCOUNT_STATUS_CONFLICT và thông báo đọc lại thông tin |
| CSRF thiếu/sai | 419 theo middleware, không bypass ở browser QA |
| ID/body/query không hợp lệ | 422 cùng field errors |
| Lỗi persistence ngoài dự kiến | Laravel xử lý 500; không dịch thành conflict; rollback write |

Với request stateful, CSRF có thể từ chối trước bước authentication/validation. Controller chỉ catch AccountStatusConflictException và ModelNotFoundException dự kiến; không sửa GlobalExceptionHandler hoặc bootstrap/Auth. Không dùng mã ACCOUNT_BLOCKED để mô tả target bị khóa trong lỗi nghiệp vụ, tránh invalidation nhầm phiên Admin.

Endpoint hỗ trợ cả CUSTOMER/HOST theo nền tảng đã duyệt. Cả bốn transitions có HTTP tests; task này chưa thêm thao tác trên trang Host.

### 9.2 Frontend và phục hồi

Trang Customer giữ route/filter/pagination/detail hiện có. Cột thao tác bổ sung Khóa tài khoản khi ACTIVE, Mở khóa khi BLOCKED; INACTIVE không có nút mutation. Page → AccountStatusDialog → accountStatusService → accountStatusApi → shared Axios. Customer detail được đọc qua adminUserService hiện có.

Dialog tải fresh detail trước xác nhận; intent khóa/mở cố định theo thao tác đã chọn, expected_status lấy từ detail mới. Nếu trạng thái mới đã là đích hoặc INACTIVE thì không cho xác nhận. Có loading, pending, errors, field errors, success và recovery. Dialog Radix trap focus, hỗ trợ Escape/outside khi chưa gửi; pending ref chặn duplicate trước khi React render và ngăn đóng trong lúc gửi. AbortController/disposed/Auth snapshot loại response cũ khi unmount hoặc thay phiên; abort phía client không được xem là rollback.

Domain Service kiểm canonical JS safe integer và payload allowlist trước transport; API lấy CSRF trước đúng một PATCH, timeout 10 giây, truyền signal. Mapper kiểm ID đúng request, trạng thái đúng đích, changed boolean phù hợp transition/no-op, timestamp UTC/null hợp lệ rồi chỉ trả bốn fields được phép.

Lỗi CSRF trước PATCH không được đánh dấu kết quả mutation chưa rõ. PATCH timeout/network/cancel/5xx hoặc response thành công sai contract có outcomeUnknown, không tự replay. Dialog hiển thị yêu cầu đọc lại; khi GET thành công mới cho xác nhận mới nếu vẫn cần. GET thấy trạng thái đã là đích chỉ thông báo trạng thái hiện tại, không khẳng định request trước đã thành công và không tự đảo intent. 409 cũng yêu cầu đọc lại; 404/403/401 không cho gửi tiếp. 419 có thông báo tiếng Việt và cần đọc lại trước xác nhận mới. Error status/identity của transport và Auth interceptors được giữ; domain 403/409 không làm Admin logout.

Kết quả mutation đã xác định được thông báo ở Page, reload danh sách và giữ URL/filter/page/draft chưa submit. Sau refresh, focus về nút thao tác mới cùng ID nếu dòng còn; fallback về heading khi dòng biến mất theo filter hoặc load lỗi. Dialog chi tiết Customer giữ nguyên. Component nhận loadAccount/accountLabel/fallbackFocusId để tích hợp Host ở task sau, không thêm Host UI trong task này.

### 9.3 File thay đổi và baseline

Đúng scope UC42-02 **11 files: 7 tạo, 4 sửa** so với baseline UC42-01. Hai task dùng chung hai file báo cáo nên toàn working tree hiện có 18 file khác HEAD. Không thay đổi bảy file PHP nền tảng UC42-01; fingerprints SHA256 được đối chiếu trước/sau.

| Action | File | Vai trò |
| --- | --- | --- |
| Tạo | backend/app/Http/Controllers/UserManagement/AccountStatusController.php | HTTP boundary và lỗi dự kiến |
| Tạo | backend/tests/Feature/UserManagement/AccountStatusApiTest.php | 30 HTTP/session/rollback cases |
| Sửa | backend/routes/api/users.php | PATCH route và middleware |
| Tạo | frontend/src/api/accountStatusApi.js | CSRF/PATCH/signal/timeout và outcome classification |
| Tạo | frontend/src/services/accountStatusService.js | Input, workflow và error view |
| Tạo | frontend/src/mappers/accountStatusMapper.js | Strict command response projection |
| Tạo | frontend/src/components/admin/users/AccountStatusDialog.jsx | Xác nhận, duplicate guard và recovery |
| Tạo | frontend/tests/accountStatus.test.js | 10 Node tests, transport/contract/Auth negative cases |
| Sửa | frontend/src/pages/admin/AdminCustomersPage.jsx | Actions, dialog, success, list refresh và focus |
| Sửa | docs/TV1/UC42-REPORT.md | Bổ sung lịch sử UC42-02 |
| Sửa | docs/TV1/README.md | Trạng thái UC42 |

Không sửa schema/seeders/Model/Auth/shared Axios/router/layout hoặc các domain TV2/TV3. Tách tests HTTP riêng để giữ nguyên 65 tests Service của UC42-01.

### 9.4 Kiểm thử UC42-02

| Check | Kết quả | Giới hạn/evidence |
| --- | --- | --- |
| php artisan test --filter=AccountStatus --compact | **95 passed, 393 assertions**, 6.26s, exit 0 | 30 HTTP + 65 nền tảng; SQLite in-memory |
| php artisan test --compact | **3.007 passed, 485 skipped, 13.115 assertions**, 298.59s, exit 0 | Full backend, skipped không được tính là đã kiểm chứng |
| node --experimental-test-module-mocks --test tests/*.test.js; rerun bản cuối với --test-reporter=dot | **485 passed**, exit 0 ở cả hai lượt | 10 tests mới, mocked transport chạy module thật |
| npm run lint | **PASS**, exit 0; bản cuối 0 errors/3 warnings | 3 cảnh báo Fast Refresh có sẵn ở badge/button/sidebar |
| npm run build | **PASS**, exit 0; 3.418 modules, 10.32s, JS 1.432,78 kB/gzip 380,76 kB | Chunk warning >500 kB có sẵn; không mở rộng scope tối ưu bundle |
| php -l Controller/test/routes | **PASS**, exit 0 | 3 file PHP task này |
| Browser React/Laravel thật | **30 checks PASS**, exit 0; 8 PATCH, 66 Admin requests, 0 uncaught JS exceptions | Chrome headless, fixture SQLite riêng, session database và CSRF bật; chi tiết bên dưới |
| Scope/whitespace/fingerprints | **PASS**: 11 task files/18 tổng, bảy fingerprints nền tảng không đổi | Bao gồm file mới chưa track; không coi UC42-01 chưa commit là thay đổi mới của task này |
| Links/cleanup và Git cuối | **PASS**: links tồn tại, scope/whitespace sạch, HEAD giữ c3c9dc3; QA đã dọn | Chrome đóng qua CDP; PHP/Vite dừng chủ động (exit 1 do Ctrl+C), xóa đúng thư mục SQLite/profile/scripts/screenshots thuộc QA sau khi xác minh absolute path |
| MySQL concurrency | **NOT VERIFIED** | Không dùng SQLite để khẳng định FOR UPDATE thực tế |

HTTP tests kiểm route/middleware, guest/role gates, actor BLOCKED/INACTIVE, exact response/write allowlist, no-op nullable timestamp, privacy 404, INACTIVE/stale conflict không invalidation Admin, 13 validation cases, blocked login, hai session database tách biệt và không phục hồi phiên sau unblock, unexpected 500 rollback. Test bootstrap xác minh :memory: trước RefreshDatabase, từ chối config cache/DB URL override. Session database chỉ nằm trong fixture in-memory; các HTTP tests thông thường vẫn có bypass CSRF của framework, nên 419 được kiểm độc lập trong browser thật.

Node tests kiểm shared transport, CSRF trước đúng một PATCH, payload/signal/timeout, input rejected trước transport, HTTP error identity/fields, outcomeUnknown chỉ sau mutation attempt, malformed success, no-op projection, unblock, cancellation và Auth invalidation phân biệt account/domain codes. Full Node/lint/build được chạy lại sau tinh chỉnh focus và thông báo 419; bản cuối đều PASS.

Browser QA dùng React/Vite thật tại localhost:5178, Laravel thật tại localhost:8102, database SQLite file mới trong thư mục QA riêng; bootstrap kiểm exact DB path, không dùng config cache, session database và CSRF bật với APP_ENV=local. Admin đăng nhập bằng form thật rồi vào trang Customer. Context Customer thứ hai dùng cookie jar tách biệt và gọi API từ browser bằng script sau khi Auth restore hoàn tất; đây là context API phụ trợ, không được tính là nghiệm thu màn hình Customer.

30 checks bao phủ fresh detail trước confirm; dialog vừa viewport 1440/768/390 và không tràn toàn trang; Tab trap/Escape/focus về action sau refresh; pending double-click chỉ khởi tạo một PATCH và Escape không đóng; DB status thực sau khóa/mở; filter/page/draft chưa submit được giữ; focus fallback khi row rời filter; existing Customer session bị từ chối, blocked login, unblock không khôi phục phiên và đăng nhập mới được; INACTIVE không có action; real 409 và GET recovery giữ intent; real 419 khi bỏ X-XSRF-TOKEN và thông báo Việt; privacy 404 qua API thật; không có uncaught JS exception. Đã xem screenshot desktop/mobile, chữ/nút/footer nằm trong dialog, mobile xếp nút thành hai dòng.

Hai ca fault injection chặn **response PATCH 200 sau khi server đã commit**: (1) làm mất phản hồi, (2) thay body thành success sai contract. Preflight OPTIONS được cho đi qua. Cả hai xác nhận DB đã BLOCKED, UI báo chưa rõ kết quả, không tự replay PATCH, nút đọc lại gửi GET rồi hiển thị trạng thái hiện tại và bỏ confirm. Không dùng response giả để chứng minh server đã ghi; trạng thái được đối chiếu bằng CLI trên fixture thuộc QA. Timeout/5xx, hủy request và CSRF GET failure được kiểm ở Node; không gộp thành browser runtime đã chạy.

Lịch sử sửa test/runner: lần PHPUnit đầu dừng vì helper tên patch trùng phương thức Laravel; đổi thành patchStatus, không thay production vì lỗi này. Node đầu 9 passed/1 failed vì predicate assert.rejects trả array thay boolean; đã sửa assertion rồi full 485 PASS. Các lượt browser QA đầu dừng ở helper serialize DOM CDP, chờ Auth restore, chọn sai trang fixture hoặc chặn nhầm preflight OPTIONS; runner tạm được sửa rồi cả 30 checks PASS. Không tính các lượt dừng là nghiệm thu hoàn tất và không khẳng định mất response sau commit trước khi kiểm được DB thực.

### 9.5 Acceptance criteria UC42-02

| ID | Tiêu chí | Trạng thái/evidence |
| --- | --- | --- |
| C01 | API đúng quyền/contract/lỗi | PASS automated: 30 HTTP cases; middleware và privacy/conflict/validation/rollback |
| C02 | UI Customer khóa/mở với xác nhận | PASS browser: form Admin thật, fresh detail, cả hai chiều ghi DB đúng và INACTIVE không có action |
| C03 | Chống gửi trùng và không tự replay unknown outcome | PASS Node + browser: double-click một PATCH, hai ca mất/sai response sau commit, GET reconcile trước thao tác mới |
| C04 | Giữ filter/page/draft, focus và UI states | PASS browser: page 2/filter/draft, action/heading focus, Tab/Escape và 3 viewports |
| C05 | CSRF/session và write scope | PASS HTTP/session/write tests + browser: 419 không ghi, hai cookie sessions và login sau unblock |
| C06 | Automated/browser/scope đạt và báo cáo đủ | PASS: 95 focused, 3.007 backend passed/485 skipped, 485 Node, lint/build, 30 browser và scope/fingerprints |

### 9.6 Dependencies, giới hạn và skills

- UC42-01 là dependency đã có trong working tree; được giữ nguyên phần code/test nền tảng. Chưa tự commit/push/merge/rebase.
- Chưa kiểm MySQL/MariaDB concurrency hoặc global session revocation; mutation giữ hành vi session đã duyệt. Chưa nghiệm thu Host UI hoặc toàn UC42.
- Browser fixture/scripts/Chrome profile là tài nguyên QA tạm bên workspace phụ; cleanup được ghi ở check cuối. Không sửa .env hoặc DB development.
- Đã áp dụng [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) để chốt baseline/scope, [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) cho HTTP boundary và contract, [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) cho mutation/recovery/focus, [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) để đối chiếu AC với evidence và giới hạn môi trường.
- Task kế tiếp đề xuất: UC42-03 — tích hợp khóa/mở Host và nghiệm thu phần còn lại; cần plan/scope riêng được duyệt.
