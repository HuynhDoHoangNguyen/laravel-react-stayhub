# MERGE-FIX — Tích hợp dev vào nhánh quản lý Admin

## 1. MERGE-FIX-01 — Hợp nhất route/contract

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh.
- Nhánh `feature/tv1-admin-management`, HEAD `057c539`, MERGE_HEAD `5447d8b`.
- Baseline là merge đang dở do developer khởi tạo: 3 unmerged files, 207 file đã staged tự merge. Không reset/abort/chạy lại merge.
- Mini plan 14 file được duyệt trước implementation, gồm quyền stage riêng các file task sau kiểm thử; không commit hoặc merge --continue.
- Kỹ thuật MERGE-FIX-01: **TECHNICAL PASS** theo evidence dưới đây; owner acceptance chờ review. **Chưa chốt merge** vì các lỗi đã tách cho MERGE-FIX-02 còn mở.

Đã áp dụng kiến trúc hiện hành và đối chiếu AGENTS.md, tài liệu/luồng UC40–UC42, các route/Controller/Service/Repository/Mapper/validators/tests hiện tại và module quản lý tổng hợp/duyệt Host từ dev. Hai nhánh cùng đăng ký GET users và PATCH status với contract không tương thích; các thay đổi ngoài task được giữ nguyên.

## 2. Route và contract sau hợp nhất

| Method | API | Controller/contract |
| --- | --- | --- |
| GET | /api/admin/users | AdminCustomerController@index; CUSTOMER-only, q/status/page/per_page; data/links/meta |
| GET | /api/admin/users/{user} | AdminCustomerController@show; detail UC40 |
| GET | /api/admin/hosts | AdminHostController@index; HOST-only UC41 |
| GET | /api/admin/hosts/{host} | AdminHostController@show; detail UC41 |
| GET | /api/admin/user-management | AdminUserController@index; mọi role, keyword/role/status/host_approval/page/per_page; success/data/summary/meta |
| POST | /api/admin/users/{id}/approve-host | AdminUserController@approveHost; workflow duyệt từ dev giữ nguyên trong task này |
| PATCH | /api/admin/users/{user}/status | AccountStatusController@update; duy nhất một route, expected_status bắt buộc, data/id/status/updated_at/changed |

Hai module users.php và admin_users.php có auth:sanctum/account.active/role:ADMIN; Controller vẫn có defense riêng. admin_users.php không đăng ký legacy PATCH updateStatus. Method cũ của AdminUserController/Service không bị refactor ngoài scope, không còn được đăng ký làm API đổi trạng thái.

api.php giữ require của cả hai module cùng Agreement/Commission/Booking/Chatbot từ dev. Route tests tải cả hai thứ tự module để chứng minh Controller và middleware không đổi; kiểm cả registry ứng dụng đầy đủ. Không dựa vào thứ tự require để chọn implementation.

AppRouter có đúng một route mỗi màn hình: /admin/users → Customer hiện tại, /admin/hosts → Host hiện tại, /admin/user-management → màn hình tổng hợp từ dev. Giữ Admin Booking và Commission cùng các route Customer/Host có sẵn. Menu thêm Người dùng & duyệt Host. Whitelist returnTo bổ sung user-management/hosts/bookings, giữ kiểm role và chống URL ngoài phạm vi.

## 3. Frontend và workflow mutation

adminUserService.js khôi phục phần Customer từ HEAD, giữ nguyên named exports/normalization/mapper/transport. Không chắp hai contract khác nhau vào cùng service.

Module mới adminUserManagementService → adminUserManagementApi → shared Axios phục vụ list tổng hợp và approveHost. API list gửi keyword/role/host_approval như contract dev; approval giữ CSRF trước POST, signal và timeout. Service reject response thiếu cấu trúc nền thay vì fallback thành danh sách rỗng; giữ error/cancellation identity và kiểm ID approval trước transport.

AdminUsersPage bỏ mutation status legacy. Nút khóa/mở mở AccountStatusDialog hiện có: Customer đọc getAdminCustomer, Host đọc getAdminHost; fresh status quyết định expected_status và intent được giữ cố định. Dialog dùng accountStatusService/Api UC42 với pending ref, CSRF, AbortSignal, Auth snapshot, privacy/conflict/validation và GET reconcile sau unknown outcome. Không bổ sung một writer status thứ hai. INACTIVE/Admin không có status action.

List/approval callbacks kiểm Auth snapshot và mounted state, approval có ref guard trước render. Thành công status reload danh sách, giữ role/status/keyword/page và draft. Focus về action mới cùng ID hoặc heading khi dòng biến mất; loading được đặt ngay khi bắt đầu refresh sau dialog để không focus vào DOM sắp bị thay. Không đổi schema/Auth/DTO/Service UC42 hoặc workflow duyệt Host trong backend.

## 4. File thuộc task

14 file theo plan, **4 tạo + 10 sửa/resolve** so với trạng thái merge dở. File Customer service được resolve về nội dung HEAD nên có thể không còn xuất hiện trong diff cuối so với HEAD. Không xóa file nguồn.

| Action | File | Vai trò |
| --- | --- | --- |
| Resolve | backend/routes/api.php | Giữ các module của cả hai nhánh |
| Sửa | backend/routes/api/admin_users.php | List riêng, approval riêng, bỏ PATCH trùng |
| Sửa | backend/tests/Feature/Admin/AdminUserManagementTest.php | URL list mới và contract UC42 |
| Tạo | backend/tests/Feature/Admin/AdminRouteContractTest.php | Hai thứ tự đăng ký, middleware, canonical Controller và module registry |
| Resolve | frontend/src/routes/AppRouter.jsx | Một route mỗi page, giữ màn hình dev |
| Resolve | frontend/src/services/adminUserService.js | Khôi phục contract Customer |
| Tạo | frontend/src/api/adminUserManagementApi.js | Transport list/approval riêng |
| Tạo | frontend/src/services/adminUserManagementService.js | Domain service tổng hợp |
| Sửa | frontend/src/pages/admin/AdminUsersPage.jsx | Service riêng, dialog UC42, guards và focus |
| Sửa | frontend/src/layouts/AdminLayout.jsx | Menu riêng cho tổng hợp/duyệt Host |
| Sửa | frontend/src/utils/authNavigation.js | ReturnTo cho các route Admin tích hợp |
| Sửa | frontend/tests/adminUsers.test.js | Gọi module thật qua transport mock và kiểm điều hướng |
| Tạo | docs/TV1/MERGE-FIX-REPORT.md | Báo cáo này |
| Sửa | docs/TV1/README.md | Mục lục/trạng thái |

201 file incoming ngoài scope đã chụp SHA256 working tree và index entries trước sửa, đối chiếu sau implementation đều giữ nguyên. Không stage toàn repository, không sửa migrations/seeders, .env, business Service/Repository của dev hoặc UC42. Git và cleanup cuối được ghi bên dưới sau khi thực hiện.

## 5. Kiểm thử thực tế

| Check | Kết quả | Evidence/giới hạn |
| --- | --- | --- |
| php artisan test --filter='AdminRouteContractTest\|AdminUserManagementTest\|AdminCustomerTest\|AdminHostTest\|AccountStatus\|HostPartnerAgreementTest' --compact | **235 passed, 1.110 assertions**, 16.26s, exit 0 | SQLite test data cô lập; kể cả route, UC40/41/42 và workflow Agreement hiện có |
| php artisan test --compact | **3.242 passed, 575 skipped, 14.215 assertions**, 143.14s, exit 0 | Toàn backend sau merge; skipped không được tính là đã kiểm chứng |
| Node focused: adminUsers/adminUser/adminHost/accountStatus | **47 passed**, exit 0 | Module Service/API thực, transport mock |
| Full Node sau sửa focus: node --experimental-test-module-mocks --test --test-reporter=spec tests/*.test.js | **544 passed**, 19.21s, exit 0 | Không dùng raw Axios viết riêng để giả làm test service |
| npm run lint | **PASS**, 0 errors/3 warnings, exit 0 | Cảnh báo Fast Refresh có sẵn tại badge/button/sidebar |
| npm run build | **PASS**, exit 0; 3.442 modules, 4.35s; JS 1.569,52 kB/gzip 413,28 kB | Chunk warning >500 kB có sẵn; scope này không tối ưu bundle |
| php -l cho 4 file PHP task | **PASS**, exit 0 | Routes, test sửa và test mới |
| Browser Chrome + React/Vite + Laravel thật | **26 checks PASS**, exit 0; 60 Admin requests, 5 PATCH, 0 uncaught JS exceptions | SQLite file QA riêng, session database, CSRF bật với APP_ENV local |
| Bảo toàn ngoài scope | **PASS**: 201 SHA256 và index entries không đổi | Đối chiếu cả working tree và index, không chỉ danh sách filename |
| Stage, marker/whitespace/links và cleanup cuối | Đang hoàn tất | Sẽ cập nhật trước bàn giao |

Route tests kiểm Customer/Host/detail/list tổng hợp/approve/status đúng Controller và đủ ba middleware; PATCH chỉ một route dù đảo thứ tự module. Test registry đầy đủ giữ Booking, Commission, Agreement, Chatbot. Test từ dev được đổi URL list và payload status/expected_status, self target 404 theo UC42; không xóa test để tránh failure.

Browser dùng Admin login form thật với returnTo=/admin/user-management; menu thật chuyển Customer → Host → tổng hợp. Kiểm all-role list, hai danh sách tách biệt, pending Host tab/keyword contract và approve POST cho Host ACTIVE. Kiểm Customer/Host đọc đúng detail API và khóa/mở qua UC42, INACTIVE/Admin không có action, expected_status thực, real 409, mất response PATCH 200 sau commit rồi GET reconcile không replay; role filter/keyword giữ nguyên. Kiểm dialog ở 1440/768/390, Tab/Escape/focus sau refresh; đã xem screenshots desktop/mobile và thấy dialog/footer/nút không tràn viewport.

Lịch sử QA: lượt browser đầu dừng do helper chọn tab bằng text gộp badge số; sửa runner tạm. Lượt kế tiếp phát hiện lỗi focus thật trong tích hợp AdminUsersPage (loading bật muộn sau effect), đã sửa Page rồi chạy lại full Node/lint/build và browser; 26 checks cuối PASS. Full backend không chạy lại sau sửa frontend một dòng vì backend không thay đổi.

QA database được tạo bằng exclusive file trong thư mục riêng, bootstrap kiểm exact SQLite path và config cache trước migration, không dùng DB development. Fault injection chỉ chặn response PATCH, cho OPTIONS đi qua, đối chiếu DB fixture đã ghi trước khi gọi đó là lost response after commit. Không có browser mock giả làm Laravel. MySQL concurrency/locking chưa được kiểm chứng trong task này.

## 6. Acceptance criteria

| ID | Tiêu chí | Trạng thái/evidence |
| --- | --- | --- |
| M01 | Hết conflict markers và unmerged entries | Marker đã gỡ; chờ stage cuối xác nhận index |
| M02 | Không trùng route/phụ thuộc thứ tự module | PASS: AdminRouteContractTest hai thứ tự + registry đầy đủ |
| M03 | Customer-only và list tổng hợp đúng contract | PASS: Customer/Host/Admin HTTP tests, Node gọi service thực và browser menu/filter |
| M04 | UI khóa/mở qua UC42 | PASS: bỏ legacy PATCH route, shared dialog; Customer/Host browser mutations, 409/recovery |
| M05 | Giữ module dev/menu/returnTo | PASS: registry Booking/Commission/Agreement/Chatbot, AppRouter build, Node navigation và browser |
| M06 | Checks và báo cáo có evidence | PASS automated/browser; chờ Git/cleanup cuối |

## 7. Còn mở — MERGE-FIX-02

Hai lỗi đã thống nhất tách riêng **chưa sửa** trong MERGE-FIX-01:

1. AdminUserService::approveHost vẫn có thể đặt Host BLOCKED/INACTIVE thành ACTIVE. Cần thống nhất duyệt Host độc lập với trạng thái tài khoản và bổ sung regression trước khi chốt merge. Browser task này chỉ kiểm approval của Host ACTIVE.
2. AdminUserRepository::paginateUsers gọi select sau withCount làm mất properties_count/bookings_count. SQL compilation đã xác nhận ở audit; cần sửa và test fixture có dữ liệu đếm >0 trong MERGE-FIX-02. Không dùng test hiện tại PASS để khẳng định số đếm đã đúng.

Không có quyền tự commit/push hoặc kết thúc merge. TECHNICAL PASS của task hợp nhất contract không đồng nghĩa toàn bộ merge đã sẵn sàng bàn giao. Task kế tiếp đề xuất: MERGE-FIX-02 — sửa duyệt Host/count theo mini plan được duyệt riêng.

## 8. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): baseline merge dở, scope 14 file, tách quyết định nghiệp vụ tiếp theo.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): canonical HTTP contract, middleware, route order tests và bảo toàn workflow.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): tách domain API/Service, shared dialog UC42, Auth/cancellation và focus.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): AC evidence, full checks, browser thật, fingerprints và phân biệt SQLite/MySQL.
