# UC41 — Quản lý Host

## 1. UC41-01 — Danh sách Host

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh.
- Branch `feature/tv1-admin-management`, baseline HEAD `7ccc4c1`, working tree ban đầu sạch.
- Scope được developer duyệt: API và trang danh sách Host, tìm kiếm/lọc/phân trang, số cơ sở, tests và báo cáo.
- Trạng thái kỹ thuật: **TECHNICAL PASS**, H01–H07 có evidence dưới đây. Nghiệm thu developer chờ review kết quả; duyệt plan không phải nghiệm thu code.
- Tại thời điểm bàn giao UC41-01, UC41-02 chưa triển khai. Kết quả bổ sung UC41-02 ở mục 9. Không triển khai tạo/sửa/xóa/nâng role Host hoặc khóa/mở tài khoản UC42.

Đã đối chiếu AGENTS.md, ARCHITECTURE.md, README, hướng dẫn frontend, ERD/use case/phân công/screen mockups, audit TV1 và source User/Property/Auth/UC40. UC41 thuộc TV1. Giữ contract Customer `/api/admin/users` và các file UC40, không refactor ngoài scope.

## 2. Backend và API contract

Endpoint mới **GET /api/admin/hosts**, name `admin.hosts.index`. Route registry xác nhận middleware Sanctum stateful, auth:sanctum, account.active và role:ADMIN. QueryService còn kiểm tra actor ADMIN ACTIVE khi gọi trực tiếp. Guest 401, sai role 403, Admin BLOCKED/INACTIVE theo account codes và session invalidation hiện có.

Controller → AdminHostValidator/Query DTO → QueryService → Repository → Mapper/Response DTO → Common PageResponse. Repository luôn scope role HOST, nhóm các OR search bên trong scope; select allowlist và withCount('properties') trong cùng query. Không đọc danh sách Property rồi đếm trong React.

| Query | Contract |
| --- | --- |
| q | Tùy chọn; trim, tối đa 100 ký tự; rỗng tương đương không lọc; chuỗi con name/email/phone |
| status | Bỏ qua hoặc ACTIVE/INACTIVE/BLOCKED; gửi rỗng tới Backend là không hợp lệ |
| page | Mặc định 1; số nguyên canonical 1..2147483647 |
| per_page | Mặc định 10; chỉ 10, 20, 50 |

Tham số ngoài contract (gồm role/sort), GET body và query sai trả 422 theo field. Raw validation trước cast. Search dùng binding, escape %, _ và ! bằng LIKE ESCAPE '!'. Thứ tự created_at DESC, id DESC. Trang vượt phạm vi trả data rỗng cùng meta thật; không ép về trang đầu.

Success `{data, links, meta}`; mỗi row có đúng `id, name, email, phone, properties_count, status, created_at`. Phone string/null, giữ số 0 đầu; timestamp ISO UTC/null theo schema legacy. Không trả role, address, avatar path, password, token hoặc dữ liệu Booking/Invoice/Chat.

`properties_count` là số nguyên không âm, đếm toàn bộ Property tồn tại có host_id đúng Host, gồm ACTIVE/INACTIVE/BLOCKED/PENDING. Host có 0 cơ sở vẫn xuất hiện. Tests so 1 và 10 Host xác nhận cùng **2 SELECT** (count paginator + page aggregate), kể cả mapping/serialization; không có N+1. Mapping dùng EloquentObjectMapper/Symfony hiện có và projection riêng cho Host. Không sửa schema/Model/Seeder hoặc ghi dữ liệu nghiệp vụ.

## 3. Frontend

Trang `/admin/hosts` dưới Admin guard, menu **Host**. Bảng gồm mã Host, họ tên, email, điện thoại, số cơ sở, trạng thái và ngày tạo. Không thêm action chi tiết/khóa/mở chưa triển khai.

Luồng Page → adminHostService → adminHostApi → shared Axios; response qua adminHostMapper. Service chuẩn hóa query, URL search và metadata; giữ rejection/cancellation identity và Auth interceptors. Mapper reject response sai kiểu, thiếu count hoặc count âm/string; bỏ fields ngoài allowlist, giữ null. Không biến response lỗi thành danh sách rỗng.

Có loading, success với tổng thật, empty, field validation, HTTP/network/timeout/contract error và retry. Search bằng nút/Enter, status/page size đổi thì reset page 1. URL giữ filters/page, hỗ trợ reload và Back/Forward. Reset xóa cả draft chưa submit. AbortController/disposed/Auth snapshot và keyed query ngăn response cũ ghi đè khi đổi filter/page/session.

UI tiếng Việt theo theme hiện có; Intl.NumberFormat cho count, Intl.DateTimeFormat vi-VN/Asia/Ho_Chi_Minh cho ngày. Nullable hiển thị Chưa cập nhật. Bảng cuộn trong vùng riêng trên mobile/tablet; label/focus/keyboard và ảnh 3 viewports đã kiểm chứng.

## 4. Danh sách file

18 file đúng plan: **14 tạo, 4 sửa**, không xóa. Paths tính từ repository root.

| Action | File | Vai trò |
| --- | --- | --- |
| Tạo | backend/app/Http/Controllers/UserManagement/AdminHostController.php | HTTP index |
| Tạo | backend/app/Validators/UserManagement/AdminHostValidator.php | Validation và quyền service |
| Tạo | backend/app/DTOs/UserManagement/Requests/AdminHostQueryRequest.php | Typed query |
| Tạo | backend/app/Services/UserManagement/AdminHostQueryService.php | Điều phối đọc |
| Tạo | backend/app/Repositories/UserManagement/AdminHostRepository.php | Host scope/search/count/pagination |
| Tạo | backend/app/Mappers/UserManagement/AdminHostMapper.php | Projection Host |
| Tạo | backend/app/DTOs/UserManagement/Responses/AdminHostListItemResponse.php | Row JSON contract |
| Sửa | backend/routes/api/users.php | Route module Host đã duyệt |
| Tạo | backend/tests/Feature/UserManagement/AdminHostTest.php | 39 cases, gồm property count và N+1 |
| Tạo | frontend/src/api/adminHostApi.js | Shared transport |
| Tạo | frontend/src/services/adminHostService.js | Query/URL/service |
| Tạo | frontend/src/mappers/adminHostMapper.js | Contract mapping |
| Tạo | frontend/src/pages/admin/AdminHostsPage.jsx | UI danh sách |
| Sửa | frontend/src/routes/AppRouter.jsx | Gắn route Admin hosts đã duyệt |
| Sửa | frontend/src/layouts/AdminLayout.jsx | Menu Host đã duyệt |
| Tạo | frontend/tests/adminHost.test.js | 10 Node tests |
| Tạo | docs/TV1/UC41-REPORT.md | Báo cáo này |
| Sửa | docs/TV1/README.md | Mục lục và trạng thái task |

Không sửa UC40, Shared Axios, Auth/context/navigation, backend/routes/api.php, .env, dependencies, Model, Migration/Seeder hoặc nguồn template.

## 5. Kiểm thử thực tế

| Check | Exit | Kết quả và giới hạn |
| --- | --- | --- |
| php artisan test --compact --filter='AdminHostTest\|AdminCustomerTest' | 0 | **99 passed, 449 assertions**: 39 Host cases + 60 Customer regression cases; SQLite :memory:, session-cookie login thật và direct service |
| php artisan test --compact | 0 | **2822 passed, 476 skipped, 12049 assertions**, 172.50 giây; các suite MySQL cần runner/opt-in riêng được skip, không dùng kết quả này làm evidence MySQL |
| node --experimental-test-module-mocks --test tests/adminHost.test.js | 0 | **10 passed**; production API/service/mapper, chỉ mock shared Vite-configured transport |
| node --experimental-test-module-mocks --test tests/*.test.js | 0 | **448 passed**, 0 failed/skipped |
| npm.cmd run lint | 0 | 0 errors; 3 Fast Refresh warnings có sẵn ở ui/badge.jsx, ui/button.jsx, ui/sidebar.jsx |
| npm.cmd run build | 0 | 3405 modules, JS 1361.15 kB/gzip 367.80 kB; warning chunk >500 kB có sẵn |
| php artisan route:list --path=api/admin/hosts -vv | 0 | Endpoint/name/middleware Auth/account/ADMIN đúng |
| git diff --check và scope review | 0 | Không lỗi whitespace, đúng 18 file được duyệt |

Focused tests lần đầu có 2 lỗi assertion: test query-count đọc DTO như array và Node test còn expected error code Customer. Đã sửa tests để kiểm tra JSON serialization và đúng Host error code; chạy lại Host+Customer/Node đạt như bảng. Không đổi production contract để chiều assertion sai. Node ExperimentalWarning module mocks chỉ thuộc test runtime.

## 6. Browser/runtime evidence

Chrome headless/CDP, Vite source thật localhost:15541, Laravel thật localhost:18541, SQLite `browser_test.sqlite` và session database fixture riêng ngoài repository. Guard kiểm tra connection/target trước boot; migrations/factories chỉ chạy trên file mới đó. APP_ENV local để CSRF middleware thực sự hoạt động. Login thật qua form Admin, có quan sát CSRF cookie request và X-XSRF-TOKEN header mà không in/lưu giá trị nhạy cảm.

Fixture: 25 Host, 50 Property (mọi trạng thái), 1 Customer, 1 Admin; Host không có cơ sở và phone null đều có. Dùng returnTo=/admin/profile hợp lệ rồi mở menu Host để không phụ thuộc MySQL Dashboard ngoài scope. Không bypass auth, mock API hoặc sửa shared navigation.

**22 browser checks PASS**, 38 list requests, **0 JavaScript Runtime exception**:

1. Login/menu thật hiển thị 25 Host/10 rows, không lẫn Customer.
2. Count 0 và count 4 (cả bốn trạng thái cơ sở) hiển thị đúng.
3. Không có action detail hoặc mutation giả.
4. Pagination cập nhật URL và server metadata.
5. Back về trang một.
6. Forward trở lại trang hai.
7. Đổi 20 dòng reset page 1.
8. Tìm email trả đúng Host, phone null và count 1.
9. Search + status hiển thị empty thật.
10. BLOCKED filter đúng 8 Host và trạng thái mỗi dòng.
11. Reload giữ filter/kết quả.
12. Tablet 768px không tràn viewport.
13. Bảng tablet có vùng cuộn riêng.
14. Mobile 390px không tràn viewport.
15. Bảng mobile có vùng cuộn riêng.
16. Tab từ search sang status select.
17. Query quá dài có aria-invalid/field error liên kết.
18. Reset xóa draft chưa submit.
19. Offline hiển thị error, không giả thành empty.
20. Online + retry lấy lại dữ liệu thật.
21. Đổi BLOCKED → ACTIVE nhanh dưới latency 600ms giữ đúng 9 Host ACTIVE mới nhất.
22. URL page=0 có lỗi rõ và reset khôi phục.

Ảnh desktop 1440px, tablet 768px, mobile 390px đã được mở kiểm tra trực quan. Đây là test runtime với source thật trên fixture SQLite, không phải nghiệm thu môi trường XAMPP/MySQL.

Đã đóng đúng Chrome fixture, dừng Laravel/Vite tạm và xóa database, browser profile cùng scripts trong thư mục fixture đã xác minh đường dẫn tuyệt đối. Không giữ account fixture hoặc server chạy nền; không thay dữ liệu MySQL phát triển.

## 7. Acceptance criteria

| ID | Trạng thái kỹ thuật | Evidence |
| --- | --- | --- |
| H01 — Admin ACTIVE only | PASS | API Guest/wrong-role/status-change và direct-service denial tests |
| H02 — Host scope/search/filter/page | PASS | Tests đủ role/status/3 search fields/special chars, pagination; browser thực tế |
| H03 — Count đúng, không N+1 | PASS | Tests counts 4/0/2 theo từng Host, 1-vs-10 Host cùng 2 SELECT; browser count 0/4 |
| H04 — Contract/privacy | PASS | Exact row allowlist/null/phone; mapper reject invalid count/body; Auth errors giữ đúng identity |
| H05 — UI/URL/states/responsive | PASS | 22 browser checks, 3 viewports và ảnh đã inspect |
| H06 — Read-only/UC40 regression | PASS | Snapshot users/properties trước-sau bằng nhau; 60 Customer regression cases đạt |
| H07 — Checks/scope | PASS | Full Backend/Node/lint/build; route registry và Git review |

## 8. Dependencies, giới hạn và bước tiếp theo

- Tái sử dụng schema User/Property, Sanctum/account middleware, Common response, EloquentObjectMapper và theme/layout. Không thêm package hoặc sửa nghiệp vụ module khác.
- Chưa kiểm chứng collation/tìm kiếm trên MySQL; không cam kết tìm tiếng Việt không dấu. SQLite/browser không thay thế evidence MySQL. Warning bundle/Fast Refresh ngoài scope vẫn giữ.
- Tại thời điểm bàn giao UC41-01, UC41-02 chưa triển khai và chưa tuyên bố toàn UC41 hoàn thành. Không có blocker kỹ thuật trong phạm vi UC41-01 đã duyệt.
- Không commit/push/merge. Bàn giao kèm commit và description đề xuất để developer tự review/sử dụng.
- Skills áp dụng: stayhub-task-planning cho scope/baseline/AC; stayhub-laravel cho raw validation/authorization/query/count/projection; stayhub-react cho domain layers/URL/cancellation/responsive; stayhub-verification cho automated/browser evidence/AC/report.
- Bước tiếp theo đề xuất: **UC41-02 — Xem chi tiết Host**, cần scope/mini plan và duyệt riêng trước triển khai.

## 9. UC41-02 — Xem chi tiết Host

### 9.1. Baseline, mục tiêu và trạng thái

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh.
- Branch `feature/tv1-admin-management`, HEAD `2950d6c`; working tree ban đầu sạch sau merge dev.
- Developer duyệt mini plan 17 file: API chi tiết Host, dialog từ danh sách, validation/authorization, property count và tests/report.
- **UC41-02 TECHNICAL PASS**, HD01–HD07 có evidence bên dưới. Phạm vi đọc của UC41 (danh sách/chi tiết) đã có Backend/Frontend; nghiệm thu developer chờ review kết quả. Duyệt plan không phải nghiệm thu code.
- Không triển khai sửa/xóa/nâng role hoặc UC42; không mở rộng sang danh sách/giám sát Property của UC43.

Đã dùng baseline kiến trúc và kết quả rà soát sau merge: Controller/Validator/DTO/QueryService/Repository/Mapper/Common; Frontend Page/Dialog → Domain Service → Domain API/shared Axios → mapper. Không thay các lớp UC40 hoặc shared configuration.

### 9.2. Backend, contract và nghiệp vụ

Endpoint mới **GET /api/admin/hosts/{host}**, name `admin.hosts.show`. Route registry xác nhận Sanctum stateful, auth:sanctum, account.active, role:ADMIN. QueryService kiểm tra ADMIN ACTIVE cả khi gọi trực tiếp, trước lookup; tests xác nhận actor bị từ chối không query DB.

Validator kiểm tra raw ID canonical nguyên dương trước cast, tới PHP_INT_MAX. Không nhận query/body GET, kể cả key có null/rỗng. Input sai trả 422 theo field `host`. Auth/account/role trước validation, validation trước lookup. Repository chọn đúng field, scope `role = HOST`, aggregate withCount('properties') trong cùng query.

Response có một envelope data, gồm đúng:

```text
id, name, email, phone, properties_count, address,
gender, date_of_birth, status, created_at, updated_at
```

Phone/address/gender/date_of_birth/timestamps nullable theo schema. Phone giữ string/số 0 đầu. Gender MALE/FEMALE/OTHER/null; ngày sinh YYYY-MM-DD, timestamps ISO UTC/null. Không trả password/token/session, role, email_verified_at, avatar path hay quan hệ khác.

Host mục tiêu được phép ở ACTIVE/INACTIVE/BLOCKED; actor luôn ADMIN ACTIVE. ID không tồn tại, CUSTOMER hoặc ADMIN cùng 404 `{"message":"Không tìm thấy Host."}`. Khi Host đổi role sau lúc tải bảng, detail cũng trả privacy 404 này.

Count lấy mới từ server, không dùng giá trị cũ trên row. Đếm mọi Property đang tồn tại của đúng Host, gồm ACTIVE/INACTIVE/BLOCKED/PENDING; Host chưa có cơ sở trả 0. Test đổi từ list count 0 sang detail count 4, kiểm không tính cơ sở của Host khác; direct-service detail/mapping chỉ có **1 SELECT**. Snapshot User/Property xác nhận thao tác đọc không ghi dữ liệu.

### 9.3. Frontend và dialog lifecycle

Trang Host thêm cột Thao tác/nút **Xem chi tiết** với accessible name theo ID. Dialog Radix dùng dependency có sẵn, tải detail theo ID đang chọn; service kiểm ID nguyên dương an toàn của JavaScript và mapper yêu cầu response ID trùng request. Count bắt buộc là số nguyên không âm; sai kiểu/thiếu field/ngày không hợp lệ là contract error, không hiển thị nhầm tài khoản.

Dialog hiển thị thông tin cá nhân, trạng thái, số cơ sở và ngày tạo/cập nhật. Nullable hiển thị Chưa cập nhật. Ngày sinh format từ chuỗi ngày, không đổi timezone. Loading/error/retry/404 recovery có UI riêng. 404 cho phép **Đóng và tải lại danh sách** để phục hồi khi row đã biến mất/đổi role.

Đóng bằng Đóng/Escape/pointer ngoài; Tab/Shift+Tab nằm trong modal. Trả focus đúng trigger; trigger không còn thì về heading Host. Giữ filters/page/draft chưa gửi khi đóng. AbortController + disposed flag + Auth snapshot loại bỏ response cũ khi đóng/đổi Host/unmount/đổi phiên. Account failure đi qua Auth interceptors hiện có; domain 403 không bị coi là mất session.

Dialog vừa viewport, max-height 90dvh, footer Đóng luôn có thể thao tác và body cuộn nội bộ; email/địa chỉ dài wrap. Không thêm Frontend route/menu hoặc sửa AppRouter/AdminLayout.

### 9.4. File tạo/sửa

17 file đúng plan: **3 tạo, 14 sửa**, không xóa. Paths tính từ repository root.

| Action | File | Vai trò |
| --- | --- | --- |
| Tạo | backend/app/DTOs/UserManagement/Requests/AdminHostDetailQuery.php | Typed detail ID |
| Tạo | backend/app/DTOs/UserManagement/Responses/AdminHostDetailResponse.php | Detail contract/transforms |
| Sửa | backend/app/Http/Controllers/UserManagement/AdminHostController.php | show/envelope/privacy 404 |
| Sửa | backend/app/Validators/UserManagement/AdminHostValidator.php | Raw ID/query/body validation |
| Sửa | backend/app/Services/UserManagement/AdminHostQueryService.php | Authorized detail workflow |
| Sửa | backend/app/Repositories/UserManagement/AdminHostRepository.php | HOST lookup/allowlist/count |
| Sửa | backend/app/Mappers/UserManagement/AdminHostMapper.php | Detail projection |
| Sửa | backend/routes/api/users.php | Detail endpoint |
| Sửa | backend/tests/Feature/UserManagement/AdminHostTest.php | Thêm 24 detail cases, giữ 39 list cases |
| Sửa | frontend/src/api/adminHostApi.js | GET detail + signal |
| Sửa | frontend/src/services/adminHostService.js | ID/response matching/service |
| Sửa | frontend/src/mappers/adminHostMapper.js | Detail validation/projection |
| Tạo | frontend/src/components/admin/users/AdminHostDetailDialog.jsx | Dialog/state/focus/recovery |
| Sửa | frontend/src/pages/admin/AdminHostsPage.jsx | Action/selection/trigger |
| Sửa | frontend/tests/adminHost.test.js | Thêm 5 tests, giữ 10 tests danh sách |
| Sửa | docs/TV1/UC41-REPORT.md | Bổ sung mục này, giữ lịch sử UC41-01 |
| Sửa | docs/TV1/README.md | Trạng thái UC41-01/02 |

Không thay Model/Migration/Seeder, Auth/Sanctum/shared Axios, dependencies, UC40 hoặc Catalog/Booking/Invoice business logic. Không phát sinh file nguồn ngoài scope. Scripts và fixture browser ngoài repository là tài nguyên kiểm chứng tạm.

### 9.5. Kết quả automated checks

| Check | Exit | Kết quả |
| --- | --- | --- |
| php artisan test --compact --filter='AdminHostTest\|AdminCustomerTest' | 0 | **123 passed, 547 assertions**: 63 Host cases và 60 Customer regression cases; SQLite :memory:, session-cookie login thật và direct service |
| php artisan test --compact | 0 | **2912 passed, 485 skipped, 12713 assertions**, 202.85 giây; MySQL suites cần runner/opt-in riêng được skip |
| node --experimental-test-module-mocks --test tests/adminHost.test.js | 0 | **15 passed**, production API/service/mapper với shared transport mock |
| node --experimental-test-module-mocks --test tests/*.test.js | 0 | **475 passed**, 0 failed/skipped |
| npm.cmd run lint | 0 | 0 errors; 3 Fast Refresh warnings có sẵn trong badge/button/sidebar |
| npm.cmd run build | 0 | 3414 modules, JS 1423.81 kB/gzip 378.55 kB; warning chunk lớn có sẵn |
| php artisan route:list --path=api/admin/hosts -vv | 0 | index/show names và Auth/account/ADMIN middleware đúng |
| Git scope/whitespace review | 0 | Đúng 17 file, không whitespace errors; cảnh báo LF→CRLF không phải lỗi |

Focused tests lần đầu có lỗi fixture: thêm aggregate properties_count vào attributes khi tạo User và coi count là nullable trong Node fixture. Đã sửa fixture (count không phải cột DB và luôn là số nguyên), không thay schema hay production contract để chiều tests. Chạy lại focused/full checks đạt như bảng. Node module-mock ExperimentalWarning chỉ thuộc test runtime. PHPUnit chạy ngoài sandbox do giới hạn quyền is_readable vendor đã xác định trước đó; không đổi cấu hình dự án để né sandbox.

### 9.6. Browser thật và evidence

Chrome headless/CDP, Vite source thật localhost:15542 và Laravel source thật localhost:18542. APP_ENV local để CSRF hoạt động; login Admin qua form, returnTo=/admin/profile hợp lệ rồi mở Host. Fixture SQLite mới browser_test.sqlite và session database riêng ngoài repository, guard kiểm target trước boot. Chỉ migration/factory/mutation fixture do lượt này sở hữu, không dùng MySQL phát triển.

Ban đầu có 25 Hosts, 50 Properties mọi trạng thái, 1 Customer, 1 Admin; Host nullable, ngày sinh nhuận và địa chỉ dài có sẵn. Script test thêm một Property PENDING sau khi list tải, đổi một Host sang Customer và khóa Admin để kiểm tra recovery/session; các thao tác này chỉ tác động SQLite fixture, không triển khai API mutation hoặc UC42.

**25 browser checks PASS**, 26 request chi tiết, **0 JavaScript Runtime exception**. CSRF cookie request và X-XSRF-TOKEN login header được quan sát mà không in/lưu giá trị nhạy cảm.

1. Hiển thị đúng Host được chọn và ngày sinh 29/02/2000.
2. Detail lấy count mới 1 sau khi list đã tải count 0.
3. Initial focus ở nút Đóng.
4. Tab giữ focus trong modal.
5. Shift+Tab giữ focus trong modal.
6. Escape trả đúng focus trigger.
7. Đóng giữ filter URL, page 2 và draft chưa gửi.
8. Nút Đóng trả đúng focus trigger.
9. Pointer ngoài đóng dialog.
10. Đóng/mở nhanh dưới latency 600ms giữ Host mới nhất, gồm target INACTIVE.
11. Detail target có 4 cơ sở đếm đủ mọi trạng thái.
12. Đóng request đang chờ không làm dialog mở lại.
13. Offline hiển thị lỗi/retry thay vì dữ liệu cũ.
14. Retry lấy lại đúng Host.
15. Tablet 768px: dialog vừa viewport.
16. Tablet: nội dung dài wrap/cuộn nội bộ.
17. Mobile 390px: dialog vừa viewport.
18. Mobile: nội dung dài wrap/cuộn nội bộ.
19. Nullable personal fields hiển thị Chưa cập nhật.
20. Host đổi role sang Customer trả generic 404.
21. 404 recovery tải lại bảng, giữ filter/page và loại row cũ.
22. Trigger bị mất thì focus về heading Host.
23. HTTP thật cùng privacy 404 cho Admin/Customer/ID thiếu.
24. HTTP thật ID 01 trả 422 với errors.host.
25. Admin BLOCKED bị invalidated session và UI về login, dialog bị gỡ.

Ảnh desktop 1440px, tablet 768px và mobile 390px đã được mở kiểm tra trực quan. Đây là source sản phẩm thật trên runtime fixture SQLite, không phải nghiệm thu XAMPP/MySQL hoặc browser mock API.

Đã đóng đúng Chrome fixture, dừng hai server Laravel/Vite và xóa database SQLite, browser profile, scripts cùng kết quả tạm trong thư mục fixture đã xác minh đường dẫn tuyệt đối. Không giữ account fixture hoặc server chạy nền, không thay dữ liệu MySQL phát triển.

### 9.7. Acceptance criteria, giới hạn và bước tiếp theo

| ID | Trạng thái kỹ thuật | Evidence |
| --- | --- | --- |
| HD01 — Admin ACTIVE only | PASS | HTTP guest/role/status + direct service không query khi denied; browser blocked-session |
| HD02 — HOST scope/privacy 404 | PASS | Missing/Customer/Admin/changed-role tests và browser HTTP comparison |
| HD03 — Contract/nullable/count | PASS | Exact JSON, all statuses/genders/nullables, fresh count 0→4, đúng Host/1 SELECT, Node malformed-response checks |
| HD04 — Đúng Host/loại response cũ | PASS | Service ID matching; browser pending close/rapid reopen/latency |
| HD05 — State/focus/recovery | PASS | Browser giữ URL/page/draft, Escape/Close/outside, fallback và 404 refresh |
| HD06 — States/keyboard/responsive | PASS | Error/retry/nullable, keyboard và 3 viewport screenshots đã inspect |
| HD07 — Read-only/regression/scope | PASS | User/Property snapshots, 123 focused tests, full Backend/Node/lint/build/Git review |

Không có blocker kỹ thuật trong phạm vi đã duyệt. Nghiệm thu developer chờ review; chưa commit/push/merge. Chưa kiểm chứng runtime MySQL, collation của tìm kiếm UC41-01 vẫn là giới hạn đã ghi. Frontend giữ giới hạn ID nguyên dương an toàn của JavaScript; Backend dùng native PHP integer bounds.

Skills áp dụng: stayhub-task-planning cho scope/baseline/AC; stayhub-laravel cho authorization/validation/projection/count/tests; stayhub-react cho dialog/domain layers/cancellation/focus; stayhub-verification cho automated/browser/MySQL evidence và report.

Bước tiếp theo đề xuất: **UC42 — Khóa/mở tài khoản**, cần mini plan và chốt transitions/chính sách trước triển khai. Không tự bắt đầu UC42.
