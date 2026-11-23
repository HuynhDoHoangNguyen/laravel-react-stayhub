# UC40 — Quản lý Customer

## 1. UC40-01 — Danh sách Customer

- Ngày kiểm chứng: 22/11/2026, múi giờ Asia/Ho_Chi_Minh.
- Branch: `feature/tv1-admin-management`; baseline HEAD: `c8a83f8`; working tree ban đầu sạch.
- Phạm vi được developer duyệt: Backend và Frontend danh sách Customer, tìm kiếm, lọc trạng thái, phân trang, tests và báo cáo.
- Trạng thái kỹ thuật UC40-01: **TECHNICAL PASS** theo AC01–AC07 ở mục 7.
- Nghiệm thu của developer: chờ review kết quả triển khai; duyệt plan không được coi là nghiệm thu code.
- Tại thời điểm bàn giao UC40-01, UC40 toàn bộ **chưa hoàn thành** vì UC40-02 chưa triển khai. Kết quả UC40-02 bổ sung ở mục 9; khóa/mở tài khoản thuộc UC42.

Đã đối chiếu AGENTS.md, ARCHITECTURE.md, README.md, hướng dẫn frontend, ERD, use case, phân công nhóm, screen mockups, audit TV1, schema/seeders User, Auth/session, DTO/mapper/validator, Common response, route registry và Admin layout/router hiện hữu. UC40 thuộc TV1 theo phân công và theo phạm vi mới được developer giao.

## 2. Kết quả Backend và Frontend

Backend bổ sung một endpoint đọc `GET /api/admin/users`, tên route `admin.customers.index`. Middleware thực tế được xác minh bằng `route:list -vv`: `auth:sanctum`, `account.active`, `role:ADMIN`, cùng Sanctum stateful middleware. QueryService kiểm tra Admin ACTIVE khi gọi trực tiếp, không phụ thuộc riêng middleware.

Luồng: Controller → Validator/typed Query DTO → QueryService → Repository → Eloquent User → EloquentObjectMapper/Response DTO → Common PageResponse. Controller giữ HTTP envelope; Repository cố định CUSTOMER và nhóm các điều kiện OR tìm kiếm bên trong scope đó. Không có thao tác ghi tài khoản, không sửa model/schema/auth/shared Axios.

Frontend bổ sung `/admin/users` dưới Admin guard và đổi nhãn menu thành **Khách hàng**. Trang có tên/email/điện thoại, status, ngày tạo và mã khách hàng; tìm bằng nút hoặc Enter; lọc trạng thái; page size 10/20/50; tổng kết quả; nút trang đầu/trước/sau. Bộ lọc nằm trên URL, hỗ trợ reload và Back/Forward. Đổi filter/page size reset page về 1.

Có loading, success với số kết quả thật, empty, validation, network/HTTP/contract error và retry. Hủy request khi đổi query/unmount; response cũ không cập nhật state sau khi dispose hoặc phiên Auth thay đổi. Bảng cuộn ngang trong vùng riêng trên mobile/tablet; nhãn, focus và keyboard navigation có kiểm chứng. Không có nút chi tiết hoặc khóa/mở giả.

## 3. Contract và business rules

| Query | Contract |
| --- | --- |
| `q` | Tùy chọn; trim; tối đa 100 ký tự; rỗng tương đương không lọc; tìm chuỗi con theo name/email/phone |
| `status` | Bỏ qua để xem tất cả hoặc ACTIVE/INACTIVE/BLOCKED; giá trị rỗng được gửi tới Backend là input không hợp lệ |
| `page` | Mặc định 1; số nguyên dương canonical, tối đa 2147483647 |
| `per_page` | Mặc định 10; chỉ 10, 20 hoặc 50 |

Backend từ chối tham số ngoài contract, gồm role/sort, và body GET có dữ liệu. Không cho client đổi role truy vấn. Tìm kiếm dùng binding và `LIKE ... ESCAPE '!'`, escape `%`, `_`, `!`; dấu nháy và backslash được kiểm tra như dữ liệu. Hành vi hoa/thường và dấu tiếng Việt tuân theo collation của engine, chưa cam kết tìm không dấu.

Thứ tự `created_at DESC, id DESC`. Trang vượt phạm vi trả `data: []` và metadata thật; không ép về trang đầu. Success `{data, links, meta}` qua Common. Mỗi item chỉ có `id`, `name`, `email`, `phone`, `status`, `created_at`. Điện thoại là string hoặc null, giữ số 0 đầu. Ngày tạo là ISO UTC hoặc null cho bản ghi legacy vì cột timestamps nullable; UI hiển thị Chưa cập nhật khi thiếu. Không dùng AuthUserMapper vì projection của session có nhiều field hơn danh sách quản trị cần.

Guest 401; sai role 403; Admin BLOCKED/INACTIVE theo account codes hiện có, session bị invalidated. Validation 422 theo field. Frontend dùng shared Auth failure interceptors; giữ nguyên rejection/cancellation identity ở lớp API/service; phân biệt response sai cấu trúc với empty hợp lệ. Frontend còn phát hiện tham số URL bị lặp và hiển thị lỗi khôi phục thay vì tự chọn một giá trị.

## 4. File tạo/sửa

Các đường dẫn dưới đây tính từ repository root.

| Action | File | Vai trò |
| --- | --- | --- |
| Tạo | `backend/app/Http/Controllers/UserManagement/AdminCustomerController.php` | HTTP index |
| Tạo | `backend/app/Validators/UserManagement/AdminCustomerValidator.php` | Raw validation và quyền service |
| Tạo | `backend/app/DTOs/UserManagement/Requests/AdminCustomerQueryRequest.php` | Typed query |
| Tạo | `backend/app/Services/UserManagement/AdminCustomerQueryService.php` | Điều phối đọc |
| Tạo | `backend/app/Repositories/UserManagement/AdminCustomerRepository.php` | Customer scope/search/pagination |
| Tạo | `backend/app/Mappers/UserManagement/AdminCustomerMapper.php` | Allowlist projection |
| Tạo | `backend/app/DTOs/UserManagement/Responses/AdminCustomerListItemResponse.php` | JSON item contract |
| Tạo | `backend/routes/api/users.php` | Route module |
| Sửa | `backend/routes/api.php` | Require route module đã duyệt |
| Tạo | `backend/tests/Feature/UserManagement/AdminCustomerTest.php` | 37 API/service test cases |
| Tạo | `frontend/src/api/adminUserApi.js` | Shared Axios adapter |
| Tạo | `frontend/src/services/adminUserService.js` | Query/URL normalization và service |
| Tạo | `frontend/src/mappers/adminUserMapper.js` | Response validation và allowlist |
| Tạo | `frontend/src/pages/admin/AdminCustomersPage.jsx` | UI danh sách |
| Sửa | `frontend/src/routes/AppRouter.jsx` | Admin users route đã duyệt |
| Sửa | `frontend/src/layouts/AdminLayout.jsx` | Nhãn Khách hàng |
| Tạo | `frontend/tests/adminUser.test.js` | 10 Node tests |
| Tạo | `docs/TV1/UC40-REPORT.md` | Báo cáo này |
| Sửa | `docs/TV1/README.md` | Thêm mục lục và trạng thái UC40-01 |

Không tạo/xóa file production ngoài danh sách. Harness trình duyệt, SQLite fixture và Chrome profile tạm thời nằm ngoài repository, không phải dependency hay source code bàn giao.

## 5. Kết quả automated checks thực tế

| Lệnh | Exit | Kết quả và giới hạn |
| --- | --- | --- |
| `php artisan test --filter=AdminCustomerTest` | 0 | **37 passed, 173 assertions**; SQLite :memory:, session-cookie requests qua login thật và service trực tiếp |
| `php artisan test` | 0 | **2760 passed, 476 skipped, 11755 assertions**, 161.31 giây; các suite MySQL cần runner/opt-in riêng được skip, không coi là đã kiểm chứng MySQL |
| `node --experimental-test-module-mocks --test tests/adminUser.test.js` | 0 | **10 passed**; dùng production API/service/mapper, chỉ mock shared transport cấu hình bởi Vite |
| `node --experimental-test-module-mocks --test tests/*.test.js` | 0 | **433 passed**, 0 failed/skipped; sau rà soát mapper, chạy lại toàn suite với reporter dot cũng exit 0 |
| `npm.cmd run lint` | 0 | 0 errors; 3 Fast Refresh warnings tại ui/badge.jsx, ui/button.jsx, ui/sidebar.jsx có sẵn, ngoài scope |
| `npm.cmd run build` | 0 | 3400 modules; JS 1344.54 kB, gzip 365.64 kB; warning chunk >500 kB, chưa tối ưu bundle ngoài task |
| `php artisan route:list --path=api/admin/users -vv` | 0 | Đúng endpoint, tên route và middleware Auth/account/ADMIN |
| `git diff --check` | 0 | Không có lỗi whitespace |

Lần PHPUnit đầu trong sandbox dừng trước khi chạy tests: PHP `is_readable(vendor/autoload.php)` bị chặn dù file tồn tại. Đã chạy lại ngoài sandbox và đạt kết quả nêu trên. Lần đầu focused suite có một lỗi assertion so thứ tự key trong query string; đã đổi sang so map không phụ thuộc thứ tự, không thay contract hay production code để chiều test.

Node báo ExperimentalWarning cho module mocking; đây là cơ chế test hiện có, không dùng trong production. Kết quả Node không được gọi là browser/E2E test.

## 6. Kiểm chứng trình duyệt thật

Chrome headless qua CDP, Vite source thật tại localhost:15140, Laravel source thật tại localhost:18040. Không dùng mock API hoặc bypass Auth. Runtime local để middleware CSRF thực sự hoạt động; quan sát request CSRF cookie và header X-XSRF-TOKEN ở login mà không lưu/in giá trị. Hạ tầng tạm thời override connection sang file SQLite `browser_test.sqlite` riêng, kiểm tra target trước boot; migrations và factories chỉ chạy trên file fixture mới đó. Session database cũng nằm trong fixture riêng.

Fixture: 25 Customers (9 ACTIVE, 8 INACTIVE, 8 BLOCKED), 1 Host, 1 Admin. Không dùng account/dữ liệu MySQL phát triển. Đây là cấu hình test runtime tổng hợp dùng source sản phẩm thật, không phải xác nhận môi trường XAMPP/MySQL triển khai.

22 kiểm tra/quan sát đạt, 38 request danh sách được quan sát, **0 Runtime exception**:

1. Đăng nhập Admin qua form thật, list hiển thị 25 Customers/10 rows, không lẫn Host.
2. Trang sau cập nhật URL và dữ liệu server.
3. Back về trang đầu.
4. Forward trở lại trang hai.
5. Đổi 20 dòng reset page về 1.
6. Tìm email thật trả một Customer.
7. Kết hợp search/status cho empty thật.
8. Lọc BLOCKED chỉ có 8 Customers bị khóa.
9. Reload giữ đúng status và kết quả.
10. Tablet 768px không tràn viewport.
11. Bảng tablet nằm trong vùng cuộn riêng.
12. Mobile 390px không tràn viewport.
13. Bảng mobile nằm trong vùng cuộn riêng.
14. Tab từ ô tìm kiếm đến bộ lọc trạng thái.
15. Từ khóa quá dài hiển thị aria-invalid và field error liên kết.
16. Reset xóa cả draft chưa submit và validation error.
17. Offline hiển thị error, không giả thành empty.
18. Online + retry lấy lại dữ liệu thật.
19. URL page=0 hiển thị lỗi và cho reset.
20. Đổi BLOCKED → ACTIVE nhanh khi network latency 600ms giữ kết quả ACTIVE mới nhất (9 rows).
21. Quan sát CSRF cookie request.
22. Quan sát XSRF header trong login request.

Ảnh desktop 1440px, tablet 768px và mobile 390px đã được mở kiểm tra trực quan. Một số lượt harness ban đầu chưa chạy tới UC40 do firewall localhost trong sandbox, giới hạn đường dẫn session file Windows và submit login trước khi Auth restore xong. Đã điều chỉnh riêng harness: server ngoài sandbox, session trong SQLite fixture và chờ Auth/React cập nhật. Không thay source Auth để phục vụ kiểm thử.

Sau kiểm chứng đã đóng đúng Chrome fixture qua CDP, dừng Laravel/Vite tạm và xóa thư mục fixture đã xác minh đường dẫn tuyệt đối (SQLite, browser profile, session và scripts). Không giữ account fixture hoặc server chạy nền. Git status cuối xác nhận đúng 19 file nguồn/tài liệu trong phạm vi đã duyệt; không commit/push.

## 7. Acceptance criteria

| ID | Trạng thái kỹ thuật | Evidence |
| --- | --- | --- |
| AC01 — Admin ACTIVE only | PASS | Guest/wrong-role/status-change API tests, direct QueryService tests, login browser thật |
| AC02 — Customer scope/status | PASS | Fixture đủ ba role/status; OR search không thoát scope; browser BLOCKED/ACTIVE counts |
| AC03 — Search/pagination | PASS | Tests ba trường, special characters, combined status, stable order, page sizes/overflow; browser URL/Back/Forward/reload |
| AC04 — Contract/privacy | PASS | Exact JSON projection, phone leading zero/null, legacy date null, mapper rejects malformed response và bỏ field thừa |
| AC05 — Real API/URL | PASS | Shared adapter tests và browser source thật gọi Laravel fixture, 38 list requests |
| AC06 — UI states/responsive/keyboard | PASS | Browser loading/empty/error/retry/validation, 3 viewports, Tab và ảnh đã inspect |
| AC07 — Checks/scope | PASS | Full backend/Node/lint/build exit 0; route registry và diff review; thay đổi đúng 19 file đã duyệt |

## 8. Dependencies, giới hạn và bước tiếp theo

- Tái sử dụng User/Auth/Common response/mapper engine và UI hiện có. Không thêm package, không đổi database phát triển, không commit/push.
- Điều chỉnh nhỏ đã ghi rõ: giới hạn page tới 2147483647 để tránh overflow; giữ nullable created_at theo schema thực tế. Không mở rộng chức năng task.
- **Chưa kiểm chứng collation/tìm kiếm trên MySQL**. SQLite/runtime browser không thay thế evidence MySQL; chưa có cam kết tìm không dấu.
- Chưa triển khai UC40-02 và UC42; chưa tuyên bố UC40 toàn bộ hoàn thành.
- Bước kế tiếp đề xuất: **UC40-02 — Xem chi tiết Customer**, lập scope/plan riêng trước triển khai.

Skills áp dụng: stayhub-task-planning (scope/AC/file list), stayhub-laravel (layering, raw validation, authorization, allowlist), stayhub-react (Domain API/service/mapper, URL state, cancellation và UI), stayhub-verification (tests/runtime evidence/AC/report). Không dùng skill database để thay schema vì task không sửa schema.

## 9. UC40-02 — Xem chi tiết Customer

### 9.1. Mục tiêu, baseline và trạng thái

Ngày 22/11/2026, developer duyệt plan UC40-02 trên cùng branch `feature/tv1-admin-management`, HEAD `c8a83f8`. Baseline gồm 19 file UC40-01 chưa commit; đã ghi SHA-256 trước khi sửa để phân biệt phần mới và giữ các thay đổi trước đó. Không coi các file có sẵn trong working tree là file được tạo bởi UC40-02.

**UC40-02: TECHNICAL PASS** theo D01–D07. Backend và Frontend của phạm vi đọc UC40 (danh sách + chi tiết) đã triển khai và có evidence. Nghiệm thu developer vẫn chờ review; việc duyệt plan không đồng nghĩa đã nghiệm thu code. Không mở rộng UC40 sang sửa/xóa tài khoản, UC41 hoặc khóa/mở UC42.

### 9.2. Backend, contract và bảo mật

Endpoint mới `GET /api/admin/users/{user}`, route name `admin.customers.show`. Route registry thực tế có Sanctum stateful middleware, `auth:sanctum`, `account.active`, `role:ADMIN`. Raw route ID được kiểm tra số nguyên dương canonical trước cast, tối đa PHP_INT_MAX. Không nhận query hoặc body GET, kể cả key có giá trị rỗng/null. Input không hợp lệ trả 422 theo field (`user` cho ID).

Controller → AdminCustomerValidator/DetailQuery → QueryService → Repository → Mapper/DetailResponse → Common ApiResponse. QueryService kiểm tra actor ADMIN ACTIVE trước khi truy vấn, gồm khi được gọi trực tiếp. Repository chọn đúng các trường chi tiết và dùng cả ID lẫn điều kiện `role = CUSTOMER`, không dùng implicit User binding ngoài scope.

Success có một envelope `data`, gồm đúng:

```text
id, name, email, phone, address, gender, date_of_birth,
status, created_at, updated_at
```

`phone`, `address`, `gender`, `date_of_birth`, `created_at`, `updated_at` nullable theo schema. Gender MALE/FEMALE/OTHER; date_of_birth là YYYY-MM-DD; timestamps ISO UTC. Frontend giữ số 0 đầu của điện thoại và hiển thị ngày sinh bằng tách chuỗi ngày, không đổi timezone. Không trả avatar/path riêng tư, role, email_verified_at, password, remember_token hoặc quan hệ Booking/Invoice/Chat.

Customer mục tiêu có thể ACTIVE/INACTIVE/BLOCKED. Actor phải ACTIVE. User không tồn tại, HOST hoặc ADMIN cùng trả 404 `{"message":"Không tìm thấy khách hàng."}`. Thứ tự lỗi Auth/account/role trước validation; validation trước lookup. Không có ghi dữ liệu nghiệp vụ hoặc success log cho thao tác đọc.

### 9.3. Frontend và vòng đời dialog

Danh sách có thêm cột Thao tác và nút **Xem chi tiết** có accessible name theo ID. Dialog dùng Radix dependency sẵn có, gọi Domain service/API bằng ID đang chọn; Mapper kiểm tra response ID đúng ID request và allowlist output. Response sai ID/kiểu/ngày/trường thiếu là contract error, không dùng dữ liệu của Customer khác.

Dialog hiển thị mã, họ tên, email, điện thoại, giới tính, ngày sinh, trạng thái, ngày tạo/cập nhật và địa chỉ. Có loading, nullable Chưa cập nhật, HTTP/network/contract error, retry. 404 có nút **Đóng và tải lại danh sách** để khôi phục khi Customer đã bị xóa/đổi role sau lúc tải bảng. 401/account codes dùng Auth interceptors có sẵn; không thay Auth/shared Axios.

Đóng bằng Escape/nút Đóng/pointer ngoài dialog; Tab và Shift+Tab giữ focus trong modal. Khi đóng, trả focus về đúng trigger, nếu trigger biến mất thì về heading danh sách. Bộ lọc/page/draft chưa submit giữ nguyên. Cleanup AbortController + disposed flag + Auth snapshot loại bỏ kết quả cũ sau đóng, unmount hoặc đổi phiên. Danh sách keyed theo query/actor nên đổi trang/bộ lọc sẽ đóng dialog theo vòng đời đó.

Dialog max-height 90dvh, footer Đóng luôn hiện, body cuộn nội bộ; địa chỉ/email dài wrap trong chiều rộng dialog. Không thêm route Frontend, package hoặc shared UI primitive.

### 9.4. File UC40-02 tạo/sửa

17 file trong phạm vi được duyệt: **3 tạo, 14 sửa so với working tree baseline UC40-01**. Các đường dẫn tính từ repository root.

| Action | File | Thay đổi |
| --- | --- | --- |
| Tạo | `backend/app/DTOs/UserManagement/Requests/AdminCustomerDetailQuery.php` | ID chi tiết đã chuẩn hóa |
| Tạo | `backend/app/DTOs/UserManagement/Responses/AdminCustomerDetailResponse.php` | Contract chi tiết và enum/date transforms |
| Sửa | `backend/app/Http/Controllers/UserManagement/AdminCustomerController.php` | Action show/envelope/404 |
| Sửa | `backend/app/Validators/UserManagement/AdminCustomerValidator.php` | Raw ID/query/body validation; quyền dùng chung |
| Sửa | `backend/app/Services/UserManagement/AdminCustomerQueryService.php` | Điều phối detail có quyền |
| Sửa | `backend/app/Repositories/UserManagement/AdminCustomerRepository.php` | Lookup CUSTOMER theo ID và projection |
| Sửa | `backend/app/Mappers/UserManagement/AdminCustomerMapper.php` | Mapping chi tiết |
| Sửa | `backend/routes/api/users.php` | GET detail cùng middleware |
| Sửa | `backend/tests/Feature/UserManagement/AdminCustomerTest.php` | Thêm 23 cases chi tiết; giữ 37 cases danh sách |
| Sửa | `frontend/src/api/adminUserApi.js` | GET detail và signal |
| Sửa | `frontend/src/services/adminUserService.js` | Chuẩn hóa ID, detail service, ID matching |
| Sửa | `frontend/src/mappers/adminUserMapper.js` | Kiểm tra/map allowlist chi tiết |
| Tạo | `frontend/src/components/admin/users/AdminCustomerDetailDialog.jsx` | Modal/vòng đời/focus/error recovery |
| Sửa | `frontend/src/pages/admin/AdminCustomersPage.jsx` | Action button, selection, focus fallback |
| Sửa | `frontend/tests/adminUser.test.js` | Thêm 5 tests chi tiết; giữ 10 tests trước |
| Sửa | `docs/TV1/UC40-REPORT.md` | Bổ sung mục này, giữ lịch sử UC40-01 |
| Sửa | `docs/TV1/README.md` | Trạng thái UC40 danh sách/chi tiết |

Không sửa lại `backend/routes/api.php`, Frontend AppRouter/AdminLayout, User Model, schema/Seeder, Auth, Shared Axios hoặc dependencies. Chỉ ba file mới được thêm vào baseline nên working tree tổng cộng 22 file khác HEAD, không phải 22 file mới của UC40-02.

### 9.5. Kiểm thử và kiểm chứng thực tế

| Check | Exit | Kết quả |
| --- | --- | --- |
| `php artisan test --filter=AdminCustomerTest` | 0 | **60 passed, 263 assertions**, SQLite :memory:, session-cookie login thật và direct-service checks |
| `php artisan test --compact` | 0 | **2783 passed, 476 skipped, 11854 assertions**, 97.19 giây; skip các MySQL suite có runner/opt-in riêng, không truy cập MySQL phát triển |
| `node --experimental-test-module-mocks --test tests/adminUser.test.js` | 0 | **15 passed**, production API/service/mapper với shared transport mock |
| `node --experimental-test-module-mocks --test tests/*.test.js` | 0 | **438 passed**, 0 failed/skipped |
| `npm.cmd run lint` | 0 | 0 errors, 3 warnings Fast Refresh có sẵn trong badge/button/sidebar |
| `npm.cmd run build` | 0 | 3401 modules, JS 1350.12 kB/gzip 367.10 kB; warning chunk lớn có sẵn |
| `php artisan route:list --path=api/admin/users -vv` | 0 | Đúng index/show, names và middleware Auth/account/ADMIN |
| `git diff --check` và scope/hash review | 0 | Diff/whitespace đúng phạm vi; phân biệt thay đổi mới với 19 file baseline |

Hai lệnh full-suite ban đầu chưa được thực thi vì redirect log tới đường dẫn temp sandbox không tồn tại ở tiến trình sau; không coi exit code shell đó là PASS tests. Đã chạy lại trực tiếp bằng các lệnh nêu trong bảng và nhận summary thực tế. PHPUnit tiếp tục cần chạy ngoài sandbox do hạn chế is_readable của môi trường đã xác định ở UC40-01. Không đổi source/config để né lỗi môi trường.

Browser thật: Chrome headless/CDP, Vite source localhost:15142 và Laravel source localhost:18042; APP_ENV local để CSRF middleware hoạt động. Fixture SQLite mới `browser_test.sqlite` nằm ngoài repository, có guard kiểm tra target trước boot; session cũng lưu trong database fixture. Chỉ migration/factory/mutation fixture cách ly được chạy. Có 25 Customers, 1 Host, 1 Admin; một Customer nullable, các bản ghi khác có ngày sinh nhuận và địa chỉ dài. Quan sát CSRF cookie và XSRF header login mà không in/lưu giá trị nhạy cảm.

**23 checks đạt**, 26 request chi tiết được quan sát, **0 Runtime exception**:

1. Chi tiết đúng Customer đã chọn và ngày sinh 29/02/2000.
2. Focus mở dialog vào nút Đóng.
3. Tab nằm trong modal.
4. Shift+Tab nằm trong modal.
5. Escape đóng, trả đúng focus trigger.
6. Đóng giữ URL, trang 2 và draft tìm kiếm chưa gửi.
7. Nút Đóng trả đúng focus trigger.
8. Pointer ngoài dialog đóng modal.
9. Đóng/mở nhanh giữa hai Customer khi latency 600ms giữ đúng Customer mới nhất.
10. Đóng request đang chờ không làm dialog mở lại khi response về.
11. Offline hiển thị lỗi, có retry.
12. Online + retry lấy đúng dữ liệu Customer từ Laravel.
13. Tablet 768px: dialog vừa viewport.
14. Tablet: địa chỉ dài không tràn, body cuộn nội bộ.
15. Mobile 390px: dialog vừa viewport.
16. Mobile: nội dung dài không tràn, body cuộn nội bộ.
17. Các thông tin nullable hiển thị Chưa cập nhật.
18. Customer fixture đổi thành Host sau khi tải bảng nhận generic 404.
19. Nút khôi phục 404 đóng dialog/tải lại bảng và giữ filter/page.
20. Trigger bị loại khỏi bảng thì focus về heading.
21. HTTP thật trả cùng 404 cho ID Admin, Host và ID thiếu.
22. HTTP thật trả 422 và errors.user cho ID 01.
23. Admin fixture bị BLOCKED thì request chi tiết invalidates session và UI về login, không giữ dialog.

Ảnh desktop 1440px, tablet 768px và mobile 390px đã mở kiểm tra trực quan. Đây là source sản phẩm thật trên cấu hình runtime/SQLite fixture tổng hợp, không phải bằng chứng triển khai XAMPP/MySQL. Browser không bypass Auth hoặc mock endpoint; thao tác đổi role/khóa Admin chỉ bằng script riêng trên SQLite fixture do lượt kiểm chứng tạo, không triển khai UC42.

Sau kiểm chứng đã đóng Chrome đúng profile/cổng CDP fixture, dừng hai server Laravel/Vite và xóa đúng thư mục test đã kiểm tra đường dẫn tuyệt đối trong workspace phụ. Database SQLite, browser profile và scripts tạm đã được dọn; không giữ account fixture hoặc server chạy nền. Trang Dashboard sau đăng nhập không thuộc phạm vi kiểm chứng này; query Dashboard hiện dùng FROM_UNIXTIME của MySQL, nên không dùng Dashboard trên SQLite fixture làm bằng chứng nghiệm thu UC40.

### 9.6. Acceptance criteria và giới hạn

| ID | Trạng thái kỹ thuật | Evidence |
| --- | --- | --- |
| D01 — Admin ACTIVE only | PASS | Guest/wrong-role/status-change API tests; direct service không query khi bị từ chối; browser blocked-session |
| D02 — Customer scope/privacy 404 | PASS | API tests missing/Host/Admin/changed-role; browser đổi role và HTTP 404 comparison |
| D03 — Contract/nullable | PASS | Exact JSON + nullable tests; Node rejects wrong ID/field/date; browser leap birthday/nullable |
| D04 — Đúng Customer đang chọn | PASS | Service ID matching, browser row→dialog và đổi Customer nhanh |
| D05 — Request lifecycle/state | PASS | Browser pending-close, latest response, giữ URL/page/draft và focus recovery |
| D06 — UI states/accessibility/responsive | PASS | Browser loading/error/retry/404/keyboard/3 viewports, ảnh đã inspect |
| D07 — Regression/scope | PASS | Full Backend/Node/lint/build, route registry, Git/hash review |

Không có blocker kỹ thuật trong phạm vi duyệt. Không sửa nghiệp vụ thành viên khác ngoài phạm vi UserManagement đã được giao. Không commit/push. Chưa xác minh lại runtime MySQL; giới hạn collation tìm kiếm UC40-01 vẫn giữ. Frontend chỉ hỗ trợ ID nguyên dương an toàn của JavaScript (như list mapper hiện có), Backend kiểm tra tới native PHP integer bounds.

Skills áp dụng: stayhub-task-planning (plan/file scope và baseline), stayhub-laravel (query authorization, DTO/projection và tests), stayhub-react (dialog, service/mapper, cancellation và focus), stayhub-verification (tách evidence automated/browser/MySQL, AC và báo cáo).

Bước tiếp theo đề xuất: UC41 — Quản lý Host, cần plan/scope riêng; không tự bắt đầu UC41 hoặc UC42.
