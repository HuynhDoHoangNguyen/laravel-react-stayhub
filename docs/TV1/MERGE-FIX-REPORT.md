# MERGE-FIX — Tích hợp dev vào nhánh quản lý Admin

Mục 1–9 lưu lịch sử MERGE-FIX-01. Hai lỗi ghi nhận tại mục 7 đã được xử lý trong MERGE-FIX-02, xem mục 10; giữ nguyên kết quả và giới hạn của từng lượt kiểm chứng.

## 1. MERGE-FIX-01 — Hợp nhất route/contract

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh.
- Nhánh `feature/tv1-admin-management`, baseline HEAD `057c539`, MERGE_HEAD `5447d8b`.
- Baseline là merge đang dở do developer khởi tạo: 3 unmerged files, 207 file đã staged tự merge. Không reset/abort/chạy lại merge.
- Mini plan 14 file được duyệt trước implementation, gồm quyền stage riêng các file task sau kiểm thử; không commit hoặc merge --continue.
- Kỹ thuật MERGE-FIX-01: **TECHNICAL PASS** theo evidence dưới đây; owner acceptance chờ review. Hai lỗi MERGE-FIX-02 còn mở. Khi kiểm tra cuối, repository đã có merge commit `ba369b2`; agent không chạy commit/merge --continue, xem mục 9.

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

201 file incoming ngoài scope đã chụp SHA256 working tree và index entries trước sửa, đối chiếu cả trước và sau staging đều giữ nguyên. Chỉ stage 14 file task đã duyệt để resolve index, không stage toàn repository, không sửa migrations/seeders, .env, business Service/Repository của dev hoặc UC42.

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
| Stage, marker/whitespace/links và cleanup cuối | **PASS**: chỉ stage scope đã duyệt, không còn unmerged entries/markers; scoped whitespace và links sạch | Phát hiện merge commit ba369b2 ở lượt cuối, không có lệnh commit/merge --continue từ agent; Chrome đóng qua CDP, PHP/Vite dừng chủ động (Ctrl+C exit 1), thư mục QA đã xóa sau khi xác minh absolute path |

Route tests kiểm Customer/Host/detail/list tổng hợp/approve/status đúng Controller và đủ ba middleware; PATCH chỉ một route dù đảo thứ tự module. Test registry đầy đủ giữ Booking, Commission, Agreement, Chatbot. Test từ dev được đổi URL list và payload status/expected_status, self target 404 theo UC42; không xóa test để tránh failure.

Browser dùng Admin login form thật với returnTo=/admin/user-management; menu thật chuyển Customer → Host → tổng hợp. Kiểm all-role list, hai danh sách tách biệt, pending Host tab/keyword contract và approve POST cho Host ACTIVE. Kiểm Customer/Host đọc đúng detail API và khóa/mở qua UC42, INACTIVE/Admin không có action, expected_status thực, real 409, mất response PATCH 200 sau commit rồi GET reconcile không replay; role filter/keyword giữ nguyên. Kiểm dialog ở 1440/768/390, Tab/Escape/focus sau refresh; đã xem screenshots desktop/mobile và thấy dialog/footer/nút không tràn viewport.

Lịch sử QA: lượt browser đầu dừng do helper chọn tab bằng text gộp badge số; sửa runner tạm. Lượt kế tiếp phát hiện lỗi focus thật trong tích hợp AdminUsersPage (loading bật muộn sau effect), đã sửa Page rồi chạy lại full Node/lint/build và browser; 26 checks cuối PASS. Full backend không chạy lại sau sửa frontend một dòng vì backend không thay đổi.

QA database được tạo bằng exclusive file trong thư mục riêng, bootstrap kiểm exact SQLite path và config cache trước migration, không dùng DB development. Fault injection chỉ chặn response PATCH, cho OPTIONS đi qua, đối chiếu DB fixture đã ghi trước khi gọi đó là lost response after commit. Không có browser mock giả làm Laravel. MySQL concurrency/locking chưa được kiểm chứng trong task này.

## 6. Acceptance criteria

| ID | Tiêu chí | Trạng thái/evidence |
| --- | --- | --- |
| M01 | Hết conflict markers và unmerged entries | PASS: scoped stage, git ls-files -u và diff-filter=U rỗng, không còn markers |
| M02 | Không trùng route/phụ thuộc thứ tự module | PASS: AdminRouteContractTest hai thứ tự + registry đầy đủ |
| M03 | Customer-only và list tổng hợp đúng contract | PASS: Customer/Host/Admin HTTP tests, Node gọi service thực và browser menu/filter |
| M04 | UI khóa/mở qua UC42 | PASS: bỏ legacy PATCH route, shared dialog; Customer/Host browser mutations, 409/recovery |
| M05 | Giữ module dev/menu/returnTo | PASS: registry Booking/Commission/Agreement/Chatbot, AppRouter build, Node navigation và browser |
| M06 | Checks và báo cáo có evidence | PASS automated/browser, scope/fingerprints/links/whitespace, staging và cleanup |

## 7. Còn mở — MERGE-FIX-02

Hai lỗi đã thống nhất tách riêng **chưa sửa** trong MERGE-FIX-01:

1. AdminUserService::approveHost vẫn có thể đặt Host BLOCKED/INACTIVE thành ACTIVE. Cần thống nhất duyệt Host độc lập với trạng thái tài khoản và bổ sung regression trước khi chốt merge. Browser task này chỉ kiểm approval của Host ACTIVE.
2. AdminUserRepository::paginateUsers gọi select sau withCount làm mất properties_count/bookings_count. SQL compilation đã xác nhận ở audit; cần sửa và test fixture có dữ liệu đếm >0 trong MERGE-FIX-02. Không dùng test hiện tại PASS để khẳng định số đếm đã đúng.

Agent không tự commit/push hoặc kết thúc merge. TECHNICAL PASS của task hợp nhất contract không xác nhận hai lỗi trên đã được xử lý; chúng vẫn tồn tại trong merge commit hiện tại. Task kế tiếp đề xuất: MERGE-FIX-02 — sửa duyệt Host/count theo mini plan được duyệt riêng.

## 8. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): baseline merge dở, scope 14 file, tách quyết định nghiệp vụ tiếp theo.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): canonical HTTP contract, middleware, route order tests và bảo toàn workflow.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): tách domain API/Service, shared dialog UC42, Auth/cancellation và focus.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): AC evidence, full checks, browser thật, fingerprints và phân biệt SQLite/MySQL.

## 9. Git tại thời điểm bàn giao

Sau khi stage scope đã duyệt và trong lúc cập nhật kết quả cleanup, repository được commit merge từ bên ngoài luồng lệnh của agent: `ba369b2 Merge branch 'dev' into feature/tv1-admin-management`, parents `057c539` và `5447d8b`; reflog ghi commit (merge). Không suy đoán công cụ hoặc người thực hiện. Agent không gọi git commit, merge --continue, push hoặc rebase.

Source đã kiểm thử nằm trong merge commit này; diff còn lại so với HEAD chỉ là cập nhật kết quả cuối của báo cáo này và docs/TV1/README.md, đã staged theo scope. Không còn unmerged entries hoặc unstaged code. 201 file ngoài scope vẫn giữ đúng SHA256 working tree và index entries của baseline. Không chạy lại tests cho thay đổi Git state/báo cáo vì source không thay đổi sau lượt checks cuối.

## 10. MERGE-FIX-02 — Sửa duyệt Host và số đếm

### 10.1 Baseline, mục tiêu và kết quả

- Ngày kiểm chứng: 22/11/2026, Asia/Ho_Chi_Minh; branch `feature/tv1-admin-management`, HEAD `ba369b2`.
- Baseline có hai báo cáo MERGE-FIX-01/README đang staged; giữ nguyên index của phần việc đó. Task mới chưa tự stage/commit/push.
- Developer đã duyệt mini plan 11 file (1 tạo, 10 sửa), gồm nghiệp vụ độc lập giữa approval và account status, hồi quy counts và chỉnh nội dung UI. Không thay đổi schema, route hoặc workflow UC42.
- Trạng thái: **TECHNICAL PASS**, F01–F05 có evidence dưới đây. Chờ developer review, không coi duyệt plan là nghiệm thu code.
- Hai lỗi chính ở mục 7 được sửa: approval không tự mở khóa/kích hoạt tài khoản; counts không bị select ghi đè. MySQL concurrency vẫn NOT VERIFIED.

### 10.2 Backend và tính toàn vẹn dữ liệu

`AdminUserService::approveHost` giữ signature và URL hiện có. Trong transaction, Repository đọc lại actor và target bằng primary key có khóa theo ID tăng dần, cùng thứ tự với UC42. Validator yêu cầu actor ADMIN ACTIVE kể cả caller gọi Service trực tiếp. Sau đó kiểm target tồn tại/role HOST, giữ lỗi 404 missing và 422 user_id cho sai role.

Repository khóa các bản ghi `host_agreement_acceptances` đang REGISTRATION_PENDING theo ID, chỉ cập nhật những bản ghi đó sang REGISTRATION_APPROVED cùng updated_at. Không gọi save User trong approval; status, updated_at và toàn bộ hồ sơ/tokens của User được giữ nguyên. Hash nội dung hợp đồng, accepted_at, lịch sử CHECKBOX_CLICKWRAP và dữ liệu Host khác không thay đổi. Nếu có nhiều bản pending thì duyệt hết các bản của target theo hành vi hiện có; không ghi lại các bản đã được duyệt.

| Trước duyệt | Sau duyệt |
| --- | --- |
| ACTIVE + pending | ACTIVE + approved |
| BLOCKED + pending | BLOCKED + approved |
| INACTIVE + pending | INACTIVE + approved |
| Không còn pending, kể cả Host legacy | No-op đọc, không UPDATE/touch timestamp/log thành công trùng |

Success log chỉ đăng ký nếu thực sự có acceptance đổi method, chờ outer commit thực tế; context chỉ admin_id/host_id scalar. Rollback hoặc exception sau write không có success log. Unexpected exception tự nổi lên, không log/rethrow. Approval không phục hồi session hoặc đổi điều kiện account.active; BLOCKED/INACTIVE sau duyệt vẫn bị chặn nghiệp vụ. Account status chỉ thay qua endpoint UC42 đã đăng ký.

Sửa `paginateUsers`: select('users.*') trước withCount để properties_count/bookings_count được giữ trong SELECT. Counts theo từng chủ tài khoản, gồm mọi status và giữ số 0 thực; không sửa dữ liệu Property/Booking. Query summary approved_hosts vẫn biểu thị số Host được duyệt độc lập status, không đổi thành số Host ACTIVE.

Repository tải sẵn counts và is_pending_approval cho cả list/approval result. Mapper bỏ query fallback và yêu cầu loaded facts, tránh âm thầm biến missing count thành 0 hoặc lazy-query theo mỗi dòng. Các field/envelope HTTP giữ nguyên; message approval đổi thành “Đã phê duyệt Đối tác thành công.”. Nhánh save cũ của Repository được trả projection có facts cho compatibility với method legacy chưa đăng ký route; không mở lại legacy PATCH hoặc sửa workflow UC42.

### 10.3 Frontend

Trang tổng hợp đổi badge “Đã duyệt mở khóa” thành “Đã duyệt”, KPI “Đối tác đã mở khóa” thành “Đối tác đã duyệt”, mô tả pending thành chờ phê duyệt. Thông báo không khẳng định mọi tài khoản đã duyệt có quyền kinh doanh; giải thích quyền còn phụ thuộc trạng thái tài khoản. Giữ cột account status và nút UC42 riêng. Host BLOCKED vẫn hiển thị Đã khóa/Mở khóa, Host INACTIVE vẫn không có thao tác status.

Không sửa shared dialog/API/Service UC42. Sau approve trang reload theo cơ chế hiện có để hiển thị approval/count/status thực từ API. Node test bổ sung kiểm client giữ status ACTIVE/BLOCKED/INACTIVE độc lập với approval và count 4/0 được bảo toàn.

### 10.4 File thay đổi

Đúng **11 file: 1 tạo + 10 sửa**, không xóa. Paths tính từ repository root. Hai báo cáo có thay đổi staged baseline trước task được giữ lại, phần bổ sung MERGE-FIX-02 chưa tự stage.

| Action | File | Mục đích |
| --- | --- | --- |
| Sửa | backend/app/Services/Admin/AdminUserService.php | Approval độc lập status, recheck actor, transaction/no-op/log |
| Sửa | backend/app/Repositories/Admin/AdminUserRepository.php | Ordered locks, approval write, counts/projection |
| Sửa | backend/app/Validators/Admin/AdminUserValidator.php | Chỉ ADMIN ACTIVE |
| Sửa | backend/app/Http/Controllers/Admin/AdminUserController.php | Message approval trung lập |
| Sửa | backend/app/Mappers/Admin/AdminUserMapper.php | Loaded facts, không query/default count giả |
| Sửa | backend/tests/Feature/Admin/AdminUserManagementTest.php | HTTP approval/status, counts theo owner và query count |
| Tạo | backend/tests/Feature/Admin/AdminHostApprovalRegressionTest.php | 17 Service/transaction/mapper regression cases |
| Sửa | frontend/src/pages/admin/AdminUsersPage.jsx | Nội dung phân biệt approval/account status |
| Sửa | frontend/tests/adminUsers.test.js | Approval response status/count contract |
| Sửa | docs/TV1/MERGE-FIX-REPORT.md | Bổ sung lịch sử và evidence MERGE-FIX-02 |
| Sửa | docs/TV1/README.md | Trạng thái hiện tại |

Không sửa migrations/seeders/Model/Auth/.env/.env.example/route của task khác. Cấu hình chatbot được user yêu cầu ở lượt trước giữ nguyên, không đưa secrets hoặc backup env vào Git.

### 10.5 Kiểm thử thực tế

| Check | Kết quả | Giới hạn |
| --- | --- | --- |
| Regression trước sửa production | **4 failed**, exit 1 | Count trả 0 thay vì 4; BLOCKED/INACTIVE bị đổi User status/timestamp; ACTIVE lệch thông điệp log mới |
| Approval/count regression sau sửa ban đầu | **23 passed, 176 assertions**, 3.30s, exit 0 | Sau đó bổ sung một case nhiều acceptances |
| Focused approval/count + route/Agreement/UC42 | **132 passed, 697 assertions**, 11.74s, exit 0 | Gồm 17 regression mới và 3 HTTP/count tests mới |
| php artisan test --compact | **3.262 passed, 575 skipped, 14.349 assertions**, 194.71s, exit 0 | SQLite test isolation; skipped không phải đã chạy |
| Full Node | **545 passed**, 12.69s, exit 0 | Node module thật, mocked transport cho contract |
| npm run lint | **PASS**, exit 0; 0 errors, 3 warnings | Fast Refresh warnings có sẵn tại badge/button/sidebar |
| npm run build | **PASS**, exit 0; 3.442 modules, 3.47s, JS 1.569,85 kB/gzip 413,34 kB | Chunk >500 kB warning có sẵn |
| php -l cho 7 file PHP | **PASS**, exit 0 | Không có lỗi cú pháp |
| Browser React/Laravel thật | **28 checks PASS**, exit 0; 20 Admin requests, 3 approval POST, 1 status PATCH, 0 uncaught JS exceptions | SQLite fixture file riêng, session database và CSRF bật |
| Scope/whitespace/links/Git/cleanup | **PASS**: đúng 11 file, whitespace/links sạch, HEAD ba369b2 | Giữ hai báo cáo staged baseline, code mới chưa stage; Chrome đóng qua CDP, PHP/Vite dừng chủ động (Ctrl+C exit 1), fixture/scripts/screenshots/profile QA được xóa sau khi xác minh absolute path |
| MySQL/MariaDB concurrency | **NOT VERIFIED** | Không lấy SQLite/query order làm bằng chứng locking engine thật |

Lượt test đầu trước sửa có fixture sai field published_at thay vì effective_at, khiến ba cases dừng ở NOT NULL; sửa đúng fixture schema rồi chạy lại mới xác nhận regression nghiệp vụ. Không tính lỗi fixture là bằng chứng approval lỗi. Node/build trong sandbox bị spawn EPERM/native dependency load; đã chạy lại ngoài sandbox, full Node/build PASS. Không sửa dependencies/config chỉ để vượt lỗi môi trường.

17 regression cases kiểm User snapshot ba trạng thái, accepted history nhiều phiên bản, repeat/no-pending noop, wrong/stale/missing actor, sai role/target thiếu, log sau outer commit, outer rollback, exception sau acceptance write, mapper query-free và lookup order. DatabaseMigrations có guard kiểm APP_ENV/SQLite :memory:/DB_URL/cache config trước bootstrap/migrations để quan sát real outer commit; không dùng transaction wrapper của test để giả định afterCommit.

HTTP counts fixture có 4 Property ở mọi trạng thái và 6 Booking ở mọi trạng thái của owner mục tiêu, thêm dữ liệu owner khác và owner zero. API phải trả đúng integer 4/1/0 Property và 6/1/0 Booking. Luồng list + summary + mapping + JSON có **8 SELECT** với cả 1 và 10 Host: paginator 2 + sáu KPI aggregates, không tăng theo số dòng. HTTP approval BLOCKED/INACTIVE kiểm exact User snapshot và account-status rejection sau approval.

Browser dùng form Admin login thật tại Vite localhost:5180 và Laravel localhost:8104, database SQLite QA file mới, không trỏ DB development. Có ba Host pending ở ACTIVE/BLOCKED/INACTIVE; mỗi lần approve đều đối chiếu digest toàn User persisted trước/sau, pending về 0, status giữ nguyên, UI badge và account status hiển thị riêng, message trung lập. Counter UI hiển thị Host 4/1/0 cơ sở, Customer 6/1 booking, vẫn đúng sau refresh. Active KPI không tăng chỉ vì approval. Sau đó Host approved BLOCKED cần explicit dialog UC42 để mở; status PATCH mới đổi ACTIVE, còn Host INACTIVE giữ nguyên.

Dialog shared UC42 được kiểm vừa viewport 1440/768/390 và đã xem screenshots desktop/mobile, footer/nút trong viewport. Provider chatbot được tắt trong bootstrap QA tạm để không gọi dịch vụ ngoài task; .env thật không đổi. Không ghi secrets vào evidence. Browser fixture/scripts/Chrome profile đã đóng và dọn. Kiểm build cuối không chứa giá trị API key; .env và backup vẫn được Git ignore.

### 10.6 Acceptance criteria

| ID | Tiêu chí | Trạng thái/evidence |
| --- | --- | --- |
| F01 | Duyệt không đổi User status/hồ sơ/timestamp | PASS: exact raw snapshots ACTIVE/BLOCKED/INACTIVE và browser persisted digest |
| F02 | Quyền, transaction, no-op, after-commit đúng | PASS: direct Service actor recheck, pending/noop/history, outer commit/rollback và lỗi sau write |
| F03 | Counts đúng, không N+1 | PASS: HTTP 4/1/0 Property, 6/1/0 Booking; 8 SELECT với 1/10 Host, mapper query-free |
| F04 | UI phân biệt duyệt với khóa/mở | PASS: nhãn/message trung lập, 28 browser checks, explicit UC42 unblock |
| F05 | Regression/checks/báo cáo đầy đủ | PASS: automated/browser, syntax 7 PHP files, exact scope 11 files, whitespace/links và QA cleanup |

### 10.7 Dependencies, skills và bước tiếp

- Hai lỗi chính MERGE-FIX-02 đã được sửa trong working tree; chờ developer review và tự quyết định commit/push. Agent không commit/push/stage phần code mới, không ghi đè hai báo cáo đã staged từ baseline.
- Khóa thứ tự giống UC42 được triển khai và kiểm query order; vẫn cần QA MySQL fixture riêng nếu muốn nghiệm thu race giữa approval và status mutation trên engine thực.
- Áp dụng [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) cho transaction/auth/response, [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) cho semantics UI, [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) cho lock/persistence và test isolation, [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) để phân loại evidence và đối chiếu AC. Tiếp tục mini plan được duyệt theo [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md).
- Task tiếp theo đề xuất: UC42-03 — nghiệm thu phần Host còn lại và đối chiếu các runtime gates; cần scope riêng được duyệt, không tự triển khai tiếp.
