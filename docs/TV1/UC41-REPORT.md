# UC41 — Quản lý Host

## 1. UC41-01 — Danh sách Host

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh.
- Branch `feature/tv1-admin-management`, baseline HEAD `7ccc4c1`, working tree ban đầu sạch.
- Scope được developer duyệt: API và trang danh sách Host, tìm kiếm/lọc/phân trang, số cơ sở, tests và báo cáo.
- Trạng thái kỹ thuật: **TECHNICAL PASS**, H01–H07 có evidence dưới đây. Nghiệm thu developer chờ review kết quả; duyệt plan không phải nghiệm thu code.
- UC41-02 xem chi tiết chưa triển khai. Không triển khai tạo/sửa/xóa/nâng role Host hoặc khóa/mở tài khoản UC42.

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
- UC41-02 chưa triển khai; chưa tuyên bố toàn UC41 hoàn thành. Không có blocker kỹ thuật trong phạm vi UC41-01 đã duyệt.
- Không commit/push/merge. Bàn giao kèm commit và description đề xuất để developer tự review/sử dụng.
- Skills áp dụng: stayhub-task-planning cho scope/baseline/AC; stayhub-laravel cho raw validation/authorization/query/count/projection; stayhub-react cho domain layers/URL/cancellation/responsive; stayhub-verification cho automated/browser evidence/AC/report.
- Bước tiếp theo đề xuất: **UC41-02 — Xem chi tiết Host**, cần scope/mini plan và duyệt riêng trước triển khai.
