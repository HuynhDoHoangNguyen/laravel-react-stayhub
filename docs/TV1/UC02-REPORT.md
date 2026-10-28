# StayHub TV1 — UC2: Đăng nhập / Đăng xuất

> Các mục 1–8 ghi nhận lần triển khai UC2 ban đầu. Mục 9 bổ sung đăng nhập đúng chế độ; mục 10 bổ sung nút Quản trị viên trên giao diện theo yêu cầu tiếp theo của người dùng.

## 1. Kết quả và snapshot

**Đã hoàn thành implementation backend/frontend UC2 và kiểm thử tự động khả thi. Chưa nghiệm thu end-to-end bằng trình duyệt.** Không triển khai register, profile mutation, account-status mutation UC42 hoặc UC khác.

| Mục | Giá trị |
| --- | --- |
| Ngày | 28/10/2026, Asia/Saigon |
| Branch | `feature/tv1-uc02-auth` do developer chuẩn bị |
| HEAD nền | `648dc1a10d528d9d646499c4a76c15b54d7d4474` |
| Trước sửa | Tracked working tree sạch; `docs/TV1/` chứa hai tài liệu audit untracked có từ lượt trước |
| Phạm vi cho phép | Auth, middleware/wiring tối thiểu, React auth/callers, test và báo cáo UC2 |
| Backend baseline chạy lại trước sửa | 167 tests / 664 assertions PASS |
| Backend sau sửa | **198 tests / 865 assertions PASS**, gồm **31 tests / 201 assertions** mới của UC2 |
| Frontend | **11 tests PASS** bằng Node test runner có sẵn; lint **0 errors / 3 warnings** nền; production build PASS |
| Browser | **CHƯA KIỂM CHỨNG** cookie/CSRF hai origin, UI tương tác và responsive thực tế |

Đọc `AGENTS.md` root trước, tìm không thấy AGENTS con. Prompt UC2 hiện tại giao vai trò TV1, cho phép sửa wiring auth và tiếp tục sau mini plan; đã trình bày hiểu biết/contract/file scope/mini plan rồi thực hiện. Ban đầu đã dừng khi branch còn là `feature/member-1-catalog`; chỉ sửa sau khi developer chuyển đúng nhánh. Không đổi branch/HEAD, commit, push, merge hoặc rebase.

Các bước nội bộ: backend auth/status/rate limit → frontend session/UX → test/regression/báo cáo. Đây là nhiều bước 30–90 phút của cùng UC2, không gộp thêm UC.

## 2. Tài liệu, contract và dependencies đã đọc

Đối chiếu tài liệu theo thứ tự: `ARCHITECTURE.md`, `README.md`, `docs/ai-frontend-development-guide.md`, `docs/stayhub-erd.md`, `docs/stayhub-usecase-system.md`, `docs/stayhub-team-work-assignment.md`, `docs/stayhub-screen-mockups.md`, `docs/seed-data.md`, `STAYHUB_M2_CONTEXT.md`, `docs/TV1/README.md`, `docs/TV1/00-PROJECT-AUDIT.md`. Dùng các phần auth, session, phân quyền, response, UI states và ownership liên quan; giữ audit lịch sử nguyên vẹn.

Đọc code thật: routes web/api/billing/reviews; bootstrap; User/UserRole/UserStatus; users/schema migrations và seeder wiring liên quan; config auth/Sanctum/CORS/session/database; `.env.example` để đối chiếu tên biến; phpunit/test setup và tests Invoice/Review. Frontend gồm shared Axios, context/hooks, router/ProtectedRoute, LoginPage/LoginTestPage, layouts và AccountDropdown; đối chiếu callers Invoice/Review/profile để giữ user fields và role/ownership. Không đọc `.env` hoặc log runtime, không in secret.

`docs/api/booking-module.md` vẫn thiếu theo inventory/audit; không cần để triển khai UC2. Không dùng M2_CONTEXT để giả định Search/Booking đã tích hợp. Không sửa template, enum/model/schema hoặc logic TV2/TV3. Không thêm dependency/framework test.

## 3. File tạo/sửa

### Backend — tạo mới

| File | Trách nhiệm |
| --- | --- |
| `backend/app/Http/Controllers/Auth/AuthController.php` | Điều phối login/me/logout và giữ wrapper hiện có |
| `backend/app/Http/Requests/Auth/LoginRequest.php` | Validation và whitelist email/password; trim email |
| `backend/app/Http/Resources/Auth/AuthUserResource.php` | Whitelist dữ liệu user, serialization không thêm wrapper `data` |
| `backend/app/Services/Auth/AuthService.php` | Xác minh credential/status, RateLimiter, session lifecycle và log nghiệp vụ không chứa PII |
| `backend/app/Services/Auth/AccountStatusException.php` | 403 account code và thông báo tiếng Việt dùng chung |
| `backend/app/Http/Middleware/EnsureAccountIsActive.php` | Đọc status mới từ DB sau Sanctum, chặn/hủy phiên không ACTIVE |
| `backend/tests/Feature/Auth/AuthenticationTest.php` | Isolated environment guard, session/credential/rate-limit/status/coverage tests |

### Backend — sửa tối thiểu

| File | Thay đổi |
| --- | --- |
| `backend/routes/web.php` | Thay closures bằng AuthController, giữ POST login/logout và web/CSRF |
| `backend/routes/api.php` | `/api/me` dùng AuthController + `auth:sanctum`, `account.active` |
| `backend/routes/api/billing.php` | Chỉ thêm `account.active` vào group sau Sanctum |
| `backend/routes/api/reviews.php` | Chỉ thêm `account.active` vào authenticated group; public reviews giữ nguyên |
| `backend/bootstrap/app.php` | Đăng ký alias `account.active`; giữ `statefulApi()` |

### Frontend — tạo mới

| File | Trách nhiệm |
| --- | --- |
| `frontend/src/services/authService.js` | Shared Axios → csrf/login/me/logout; timeout và lỗi auth chuẩn hóa |
| `frontend/src/services/authSession.js` | Phân biệt account code, chuẩn hóa lỗi, quản lý interceptor và request version |
| `frontend/src/utils/authNavigation.js` | Allowlist returnTo theo route/role thực tế, dashboard fallback |
| `frontend/src/components/common/AuthFeedback.jsx` | Notice thành công/account error, lỗi kết nối và nút retry dùng chung |
| `frontend/tests/auth.test.js` | Node tests cho redirect security, errors, interceptor/callback cleanup |

### Frontend — sửa

- `frontend/src/context/AuthContext.jsx`: dùng authService, một auth state duy nhất; chống response cũ/submit trùng; phân biệt guest, account denial và network error; logout trả boolean xác nhận.
- `frontend/src/App.jsx`: đặt AuthFeedback trong AuthProvider hiện có.
- `frontend/src/routes/ProtectedRoute.jsx`: chặn render protected khi restore/logout pending hoặc xác minh session lỗi; retry; giữ role-denied UI.
- `frontend/src/pages/public/LoginPage.jsx`: label/field errors, pending lock, 403/422/429/419/network messages, Retry-After countdown, returnTo theo role thật; signup vẫn chỉ notice, submit signup bị tắt.
- `frontend/src/pages/public/LoginTestPage.jsx`: chuyển caller development qua context chung, bỏ log Axios error có thể chứa credential payload; không duy trì user state riêng.
- `frontend/src/components/common/AccountDropdown.jsx`: chỉ navigate sau logout được xác nhận.
- `frontend/src/layouts/PublicLayout.jsx`, `frontend/src/layouts/CustomerLayout.jsx`: disable nút logout mobile khi đang gửi. Host/Admin đã dùng context logout và disable, không cần sửa.

### Tài liệu

Tạo `docs/TV1/UC02-REPORT.md`; thêm đúng một link vào `docs/TV1/README.md`. `00-PROJECT-AUDIT.md` không thay đổi nội dung lịch sử. Tổng cộng **27 file UC2** (25 backend/frontend + 2 tài liệu); audit cũ untracked không tính là file tạo mới của UC2.

Shared Axios, AppRouter, config auth/Sanctum/CORS/session, User model, enums, migrations/seeders, package/composer/lockfiles, catalog/booking services và mock guards được giữ nguyên.

## 4. Backend contract và nghiệp vụ

### 4.1 Endpoints giữ nguyên

| Endpoint | Success | Error liên quan |
| --- | --- | --- |
| GET `/sanctum/csrf-cookie` | Sanctum cấp CSRF cookie như trước | Framework/session config, chưa nghiệm thu browser |
| POST `/login` | 200 `{message,user}` | 422 field/credential; 403 account; 429 rate limit; web CSRF 419 |
| GET `/api/me` | 200 `{user}` | 401 guest; 403 non-ACTIVE rồi hủy phiên web hiện tại |
| POST `/logout` | 200 `{message}` | Vẫn qua CSRF; không gắn status guard hoặc yêu cầu ACTIVE |

`user` chỉ có `id,name,email,phone,address,avatar,role,status,email_verified_at,created_at,updated_at`, phục vụ các caller đang có. Không `password`, `remember_token`, relationships hoặc `data.user`. Dùng Resource `resolve($request)` bên trong wrapper hiện hành; không đổi schema JSON Invoice/Review.

LoginRequest chỉ chuyển validated email/password vào service. Client gửi role/status/id/remember không được dùng để cấp quyền hoặc thay dữ liệu; không phát triển remember-me. Email được trim cho input; **chỉ lower-case cho limiter key**, không lower email query hoặc thay chính sách case-sensitive DB. Password không trim/hash lại thủ công; dùng Laravel SessionGuard/provider và hashed cast hiện có.

SessionGuard `attemptWhen` chỉ chạy callback account status **sau khi password hợp lệ**; account bị chặn không được login. Credential sai hoặc email không tồn tại cùng 422:

```json
{"message":"Email hoặc mật khẩu không chính xác.","errors":{"email":["Email hoặc mật khẩu không chính xác."]}}
```

Credential đúng nhưng account không ACTIVE trả 403 `{message,code}`:

| Status | Code | Message |
| --- | --- | --- |
| BLOCKED | `ACCOUNT_BLOCKED` | Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên. |
| INACTIVE | `ACCOUNT_INACTIVE` | Tài khoản của bạn chưa được kích hoạt. Vui lòng liên hệ quản trị viên. |

Trước khi trả account denial, service logout/invalidate/regenerateToken phiên hiện tại, kể cả khi trình duyệt trước đó đăng nhập user khác. Login ACTIVE thành công regenerate session ID. Logout invalidate và regenerate CSRF token; không đổi thành GET. Không tạo/sửa status tài khoản qua API UC2.

### 4.2 Status guard coverage

Alias `account.active` đứng sau `auth:sanctum` ở **9 protected routes** hiện có:

1. GET `/api/me`.
2. GET `/api/host/invoices`.
3. POST `/api/host/bookings/{booking}/invoice`.
4. GET `/api/host/invoices/{invoice}`.
5. GET `/api/customer/invoices`.
6. GET `/api/customer/invoices/{invoice}`.
7. POST `/api/customer/bookings/{booking}/review`.
8. GET `/api/customer/reviews`.
9. GET `/api/host/reviews`.

Middleware gọi `fresh()` từ User đã xác thực để lấy trạng thái có hiệu lực từ DB; không tin model/status cache của guard. Khi không ACTIVE, dọn session web hiện tại và trả account 403; request kế tiếp dùng phiên đã dọn trả 401. Nếu user không còn tồn tại, dọn phiên và trả 401. Không tuyên bố xóa mọi session DB: các phiên khác bị kiểm tra khi request kế tiếp đến.

Public `/api/test`, public reviews và csrf-cookie không bị status guard. Logout là ngoại lệ có chủ đích để account bị khóa vẫn dọn phiên được. Role/ownership trong controllers/domain validators/queries Invoice/Review giữ nguyên; không tạo middleware role toàn hệ thống mới. Route coverage test kiểm tra mọi `auth:sanctum` route đều có alias theo thứ tự; regression tests module cũ PASS.

### 4.3 Rate limit

- Laravel RateLimiter, khóa `auth:login:` + SHA-256 của `lower(trim(email))|IP`; không dùng client role, không đưa email/IP rõ vào cache key.
- Tối đa **5 lần thất bại** trong cửa sổ **60 giây tính từ lần thất bại đầu**; request thứ 6 bị từ chối trước khi kiểm credential, kể cả nhập đúng.
- Wrong credential và account denial đều `hit`; login thành công `clear` đúng khóa; key email/IP khác độc lập. Validation payload không hợp lệ bị Form Request trả 422 trước service.
- 429 có message chung, header `Retry-After` và body `retry_after` số giây. Body cho frontend dùng khi CORS không expose header; không thay config CORS.
- Tests dùng frozen clock/travel, không sleep; chứng minh hết cửa sổ phục hồi và login thành công reset counter.

Service chỉ log sự kiện nghiệp vụ thành công/dọn phiên, không log credential/email/IP/token/cookie, không catch-log-rethrow. Ngoại lệ domain tự render 403; validation dùng Laravel handler. Không đổi exception handling của Invoice/Review.

## 5. Frontend states và xử lý lỗi

`Page/Hook → AuthContext → authService → shared Axios → Laravel`. Không thêm Axios instance/AuthContext, không lưu token/password vào storage. Shared Axios vẫn `withCredentials/withXSRFToken`. Login và logout đều khởi tạo CSRF cookie; request auth có timeout 10 giây, retry thủ công rõ ràng.

| Tình huống | Hành vi |
| --- | --- |
| Initial restore / retry | Loading che protected Outlet; gọi `/api/me`; không render nội dung được bảo vệ trước khi xác nhận |
| Restore 401 | Guest, không coi là lỗi kết nối; route guard chuyển login khi cần |
| Account 403 từ restore hoặc domain request | Nhận đúng `ACCOUNT_BLOCKED/ACCOUNT_INACTIVE`, xóa cached user, notice tiếng Việt, guard chuyển login |
| 403 role/ownership | Không invalidation auth; domain page giữ ý nghĩa lỗi quyền của nó |
| Restore lỗi mạng/5xx | Hiển thị lỗi/retry; không tuyên bố server logout; giữ cached identity nếu đã biết nhưng ProtectedRoute che dữ liệu |
| Login 422 | Message và errors email/password theo field; input có label/htmlFor/id, aria-invalid/describedby; focus field lỗi |
| Login 403 | Account notice + lỗi login; không cấp quyền từ role toggle |
| Login 429 | Countdown từ Retry-After/body; disable submit đến hết thời gian; backend vẫn là nguồn quyết định |
| Login 419 / mạng / 5xx | Thông báo riêng tiếng Việt; thử lại sẽ lấy CSRF cookie mới; không log Axios config chứa password |
| Login đang gửi | Ref lock + disabled; chỉ gửi email/password; xóa password sau attempt; không tự đăng ký khi signup notice |
| Logout đang gửi | Gộp submit trùng vào một promise, disable callers; protected Outlet tạm ẩn |
| Logout 200 | Clear user, success notice từ server; AccountDropdown mới navigate về public |
| Logout 401 | Đồng bộ guest theo contract; không giả báo server xác nhận logout 200 |
| Logout lỗi mạng/5xx/419 | Trả false, giữ kiến thức về user, hiện “Chưa thể xác nhận đăng xuất” và retry; protected data bị che; không navigate như thành công |

Interceptor chỉ phát sự kiện account denial hoặc 401 của protected API/logout, không gọi restore/logout tự động nên không tạo vòng lặp. Cả request/response interceptors được eject khi provider cleanup. Mỗi request giữ version lúc gửi; context bỏ response của thao tác cũ sau login/logout/restore mới. StrictMode mount đầu được hủy trước restore; không duy trì context riêng ở LoginTestPage.

`returnTo` dùng allowlist public/role routes **đã có trong router**. Giữ query/hash hợp lệ, từ chối URL ngoài, protocol-relative, backslash/control characters, encoding bất thường, đường khác role hoặc route chưa có; fallback `/customer`, `/host`, `/admin`. Không suy role từ query string, giữ theme/layout và các mock guards catalog/booking.

## 6. Kiểm thử thực tế

### 6.1 An toàn database

Đọc `phpunit.xml`, test setup, scripts; không có `backend/bootstrap/cache/config.php` hoặc `.env.testing`. Các lệnh Artisan dùng env tạm dưới đây, không ghi file cấu hình:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:LOG_CHANNEL='null'
```

AuthenticationTest kiểm tra các env này và không có config cache **trước application boot**; kiểm tra config hiệu lực ngay sau boot, trước RefreshDatabase chạy migration. Không dựa vào guard sau migrate. Chỉ tạo users và đổi status **fixture trong SQLite bộ nhớ**, không truy cập/chỉnh account demo/live. Không chạy lệnh migrate/reset/seed MySQL; không sửa migration hoặc dữ liệu lưu giữ.

### 6.2 Commands và kết quả

| Thư mục / môi trường | Command | Exit | Kết quả |
| --- | --- | --- | --- |
| root | Git status/branch/rev-parse trước sửa | 0 | Đúng nhánh UC2; HEAD nền như mục 1, chỉ audit untracked |
| backend, trước sửa | `php artisan test --compact --no-ansi` | 0 | **167 passed / 664 assertions**, 4.08 giây |
| backend, test UC2 | `php artisan test --filter=AuthenticationTest --compact --no-ansi` | 0 | **31 passed / 201 assertions**, 8.92 giây |
| backend, regression | `php artisan test --compact --no-ansi` | 0 | **198 passed / 865 assertions**, 11.97 giây |
| backend | `php artisan route:list -v --except-vendor --no-ansi` | 0 | 13 routes ứng dụng; 9 protected có status guard, public/logout giữ scope |
| frontend, ngoài sandbox | `node --test tests/auth.test.js` | 0 | **11 tests passed**, không cài dependency; rerun sau sửa navigation PASS |
| frontend | `npm.cmd run lint` | 0 | **0 errors, 3 warnings** nền |
| frontend, ngoài sandbox | `npm.cmd run build` | 0 | **Build PASS**, 3303 modules; JS 1,056.09 kB, gzip 295.02 kB; warning chunk >500 kB |
| root | `git diff --check` | 0 | Không whitespace error ở tracked diff; file mới được kiểm tra riêng |

Node test và Vite build lần đầu trong sandbox exit 1 do `spawn EPERM`; build còn diagnostic native Tailwind load. Đã chạy lại ngoài sandbox với cấp quyền, PASS; không sửa dependency/lockfile để né lỗi môi trường. Lint lần đầu bắt được regex/control-character và ref-cleanup cảnh báo mới; đã sửa, lần cuối chỉ còn 3 cảnh báo có từ baseline tại `ui/badge.jsx:25`, `button.jsx:38`, `sidebar.jsx:536`.

### 6.3 Coverage và giới hạn bằng chứng

Backend mới bao phủ: ACTIVE login/restore cả ba role; response whitelist; wrong/unknown email cùng lỗi; missing/invalid fields; không nhận role/status/id/remember; regenerate ID; logout invalidate/CSRF token; guest 401; BLOCKED/INACTIVE chỉ lộ status sau credential đúng và không còn session hợp lệ; rejected login dọn phiên trước; 5 failures/lần 6/Retry-After/clock recovery/reset counter; normalized email/IP isolation; account denial count.

Các test session bắt đầu bằng **POST `/login`**, gửi tiếp session cookie qua request helper, reset auth guards giữa requests; không dùng `actingAs` để chứng minh login session. Sau fixture status change, test `/api/me`, Customer Invoice API và Customer Review API trả account 403, rồi request sau 401. Có test replay cookie cũ sau logout vẫn 401, logout account vừa đổi status, public endpoints vẫn public khi session đã bị khóa, role-denied không xóa session và route coverage. Toàn bộ ownership/role/rollback/PII tests Invoice/Review cũ vẫn PASS.

Frontend Node tests chạy mã helper/interceptor thật với Axios adapter fixture: returnTo allow/deny, account-code classification, 419/429/422/network/server messages, giữ rejection cho caller, không clear auth do role 403, request version, cleanup/remount không nhân listener. **Không phải React DOM/browser interaction tests**; pending/redirect/UX state còn cần thử trình duyệt.

## 7. Browser verification và hướng dẫn thử tay

**CHƯA KIỂM CHỨNG:** phiên này không có browser tool; project không có Playwright/Puppeteer test runtime cài sẵn và chưa có browser fixture accounts được chuẩn bị. Không cài thêm công cụ, không khởi chạy test trên database demo/live để thay bằng chứng. Laravel feature tests thường bypass CSRF, vì vậy session-cookie assertions phía backend không chứng minh browser thực thi CSRF/CORS đúng giữa hai origin.

Developer cần dùng môi trường test tách biệt với tài khoản fixture được phép và kiểm tra:

1. Chạy backend và frontend ở hai origin thật theo cấu hình dự án. Đối chiếu tên biến `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `VITE_BACKEND_URL` và config CORS; không công khai giá trị secret. CORS hiện cho frontend localhost:5173, nhưng Sanctum default không liệt kê rõ port này; phải xác minh config hiệu lực local, chưa kết luận env đang sai.
2. Login ACTIVE bằng từng role; Network phải thấy csrf-cookie trước POST login, XSRF header và session cookie; response đúng wrapper. Refresh protected URL vẫn giữ user từ `/api/me`.
3. Thử returnTo hợp role rồi khác role/URL ngoài; kiểm tra fallback dashboard, không chuyển ra domain ngoài hoặc vòng lặp login.
4. Thử sai credential, email không tồn tại và thiếu field; lỗi chung/field đúng tiếng Việt. Thử 5 lỗi, lần 6 có 429/countdown; không click submit trùng; chờ hết Retry-After hoặc dùng đồng hồ test riêng để thử lại.
5. Dùng **fixture test** đang login rồi đổi fixture status sang BLOCKED/INACTIVE bằng cơ chế test được cho phép. Request protected kế tiếp phải 403 account code, UI xóa identity và giữ notice; sau đó 401. Không thực hiện trên account demo/live để thử.
6. Login fixture ACTIVE rồi chuyển non-ACTIVE, bấm logout trước khi gọi protected API: logout vẫn dọn được session. Sau logout, refresh protected URL không còn session hợp lệ.
7. Mô phỏng offline/5xx khi restore/logout: protected data bị che, có retry, không báo logout success hoặc navigate như đã thành công. Khôi phục mạng rồi retry logout hoặc kiểm tra lại phiên. Thử CSRF hết hạn 419 và retry lấy cookie mới.
8. Kiểm tra mobile/tablet/desktop, labels, keyboard/focus và các trạng thái loading/field error/notice. Xác minh các menu logout dùng chung, role-denied vẫn giữ session.

Không lấy việc build/lint PASS làm bằng chứng responsive hoặc end-to-end PASS.

## 8. Hạn chế, nghiệm thu và điểm dừng

- Implementation/tests UC2 hoàn thành theo bằng chứng tự động; còn nghiệm thu browser hai origin và config hiệu lực. Không đánh dấu đã hoàn thành browser E2E.
- MySQL live/demo không bị đọc/ghi. Session store/MySQL production, proxy/IP deployment và cookie flags thực tế chưa được kiểm chứng ở lượt này; giữ config hiện có.
- Status guard áp dụng cho toàn bộ protected routes **hiện có**. Khi TV2/UC khác thêm route auth mới, integrator phải gắn `account.active` sau auth và cập nhật coverage test.
- Chỉ hủy session hiện tại; không triển khai quản lý mọi session hay đổi status UC42. Từng session khác bị kiểm tra ở request kế tiếp.
- Ba warning Fast Refresh và warning bundle lớn là phần nền chưa xử lý; không mở rộng scope tối ưu UI/dependency.
- Public review status policy, Search/Availability, register/profile/CRUD giữ trạng thái audit; không sửa module khác.

Kiểm tra Git cuối giữ nguyên branch/HEAD; không có thay đổi migration/seeder/config/dependency/lockfile/catalog/booking. Tài liệu audit có sẵn được giữ nguyên; README chỉ thêm link UC2. Không tự commit/push/merge hoặc làm UC1.

Commit đề xuất để developer tự dùng:

```text
feat(auth): complete UC2 session authentication and account guards

Extract auth endpoints, enforce ACTIVE accounts and throttle failed logins.
Centralize React auth errors, safe redirects and confirmed logout handling.
Add session/status/rate-limit tests and document remaining browser verification.
```

Dừng sau báo cáo; nhiệm vụ tiếp theo chỉ bắt đầu khi developer giao prompt riêng.

## 9. Bổ sung: kiểm tra vai trò theo chế độ đăng nhập

### 9.1 Yêu cầu và phạm vi

Ngày 28/10/2026, người dùng đã kiểm thử bằng trình duyệt và yêu cầu: chọn Khách hàng chỉ nhận CUSTOMER; chọn Đối tác chỉ nhận HOST, nhập tài khoản khác loại phải báo lỗi. Đã xác nhận yêu cầu này trước khi sửa. Đây là cập nhật quyết định UI/nghiệp vụ UC2, thay cho việc chỉ dùng role toggle làm ngữ cảnh giao diện ở lần triển khai ban đầu.

Branch `feature/tv1-uc02-auth`, HEAD nền mới `82d30b4f77fbff2af2dd3a994a102101de5296b4`; working tree sạch trước lượt sửa. Đã đọc lại AGENTS và đối chiếu code/tài liệu auth, trình bày contract và mini plan trước khi thực hiện theo quyền tiếp tục UC2 đã có. Không tạo/chuyển branch hoặc commit.

Chỉ sửa 6 file:

- `backend/app/Http/Requests/Auth/LoginRequest.php`.
- `backend/app/Services/Auth/AuthService.php`.
- `backend/tests/Feature/Auth/AuthenticationTest.php`.
- `frontend/src/services/authService.js`.
- `frontend/src/pages/public/LoginPage.jsx`.
- `docs/TV1/UC02-REPORT.md`.

Không chạy migration/seeder, sửa dữ liệu MySQL, `.env`, enum/schema hoặc logic Invoice/Review.

### 9.2 Contract cập nhật

Frontend gửi `expected_role` trong POST `/login`, lấy từ chế độ của form:

| Chế độ / URL | `expected_role` | Tài khoản được đăng nhập |
| --- | --- | --- |
| Khách hàng: `/login` hoặc `/login?role=customer` | CUSTOMER | Chỉ CUSTOMER ACTIVE |
| Đối tác: `/login?role=host` | HOST | Chỉ HOST ACTIVE |
| Quản trị: `/login?role=admin` | ADMIN | Chỉ ADMIN ACTIVE |

`LoginRequest` kiểm tra giá trị theo `UserRole` hiện có, từ chối rỗng/null/kiểu dữ liệu sai/giá trị lạ với 422 `errors.expected_role` tiếng Việt. Trường này là điều kiện so khớp, **không phải quyền được cấp**; raw `role/status/id` từ client vẫn không thay đổi dữ liệu tài khoản.

AuthService bỏ `expected_role` khỏi credential query trước khi gọi SessionGuard. Sau khi mật khẩu hợp lệ, kiểm tra ACTIVE như trước, rồi mới so vai trò thật với vai trò yêu cầu. Sai mật khẩu vẫn trả lỗi chung; không tiết lộ role mismatch trước khi xác minh mật khẩu. BLOCKED/INACTIVE vẫn ưu tiên account code 403 như contract ban đầu.

Nếu credential đúng nhưng sai chế độ: 422, `errors.email[0]` là “Tài khoản này không thuộc khu vực … Vui lòng chọn đúng khu vực đăng nhập.” Không tạo phiên mới và dọn phiên web cũ nếu có; request `/api/me` sau đó là 401. Lần sai chế độ tính vào limiter email+IP hiện hành; đổi chế độ không xóa/bỏ qua giới hạn 5 lần/60 giây.

Giữ `expected_role` tùy chọn cho callers API/dev portal cũ không gửi ngữ cảnh; các caller đó vẫn đăng nhập theo role thật của tài khoản và chịu các kiểm tra ACTIVE/role/ownership hiện hành. Form production luôn gửi chế độ đã chọn. Endpoint/response success `{message,user}` và các protected routes không đổi.

Frontend lấy chế độ trực tiếp từ URL để không giữ nhầm state khi chuyển giữa các ngữ cảnh; khóa role toggle trong lúc gửi, xóa lỗi cũ khi đổi chế độ và hiển thị lỗi mismatch tại ô email. Role và destination sau success vẫn từ user do server trả. Form Admin dùng query `role=admin` trên LoginPage hiện có, không hiện signup/toggle Customer/Host. Truy cập `/admin` khi guest vẫn qua ProtectedRoute tới ngữ cảnh Admin này; không thêm route hoặc endpoint mới.

### 9.3 Kiểm chứng bổ sung

Môi trường test được cố định như mục 6: APP_ENV testing, SQLite `:memory:`, DB_URL rỗng, cache/session array, không config cache. Guard an toàn chạy trước RefreshDatabase. Tài khoản/trạng thái chỉ được tạo trong fixture SQLite, không sử dụng dữ liệu demo/live để chạy test.

| Command | Exit code | Kết quả lượt bổ sung |
| --- | --- | --- |
| `php artisan test --filter=AuthenticationTest --compact --no-ansi` | 0 | 49 cases / 292 assertions trước lần bổ sung assertion thông báo validation tiếng Việt cuối cùng |
| `php artisan test --compact --no-ansi` | 0 | **216 tests / 957 assertions PASS**, 31.27 giây, bao gồm assertion tiếng Việt cuối cùng |
| `node --test tests/auth.test.js` ngoài sandbox | 0 | **11 tests PASS**, kiểm tra hồi quy frontend helpers/interceptors |
| `npm.cmd run lint` | 0 | **0 errors / 3 warnings** nền tại UI badge/button/sidebar |
| `npm.cmd run build` ngoài sandbox | 0 | **PASS**, 3303 modules; JS 1,056.41 kB (gzip 295.12 kB), cảnh báo chunk lớn có sẵn |
| `git diff --check` | 0 | Không có whitespace error; Git có thông báo chuyển LF → CRLF theo cấu hình working copy |

Build trong sandbox gặp `spawn EPERM`; chạy lại ngoài sandbox với quyền được cấp thành công, không sửa dependencies/config để né lỗi. Test backend mới bao phủ 3 role đúng ngữ cảnh, 6 tổ hợp sai ngữ cảnh, không tạo session/không sửa role, không lộ mismatch khi password sai, hủy phiên cũ, validation context, limiter khi đổi context và giữ account-status denial. Tổng Auth suite tăng từ 31 lên **49 cases**; toàn bộ regression Invoice/Review vẫn PASS.

Chưa có browser tool để tự xác nhận giao diện/cookie bằng trình duyệt trong lượt bổ sung. Người dùng đã báo thử được luồng cũ; việc đó chưa được coi là nghiệm thu hành vi mới. Không gọi Node helper tests là React DOM/E2E tests.

### 9.4 Thử tay sau cập nhật

1. Đăng xuất phiên hiện tại, tải lại trang login. Nếu đã có session hợp lệ, LoginPage vẫn đưa về dashboard của tài khoản đang đăng nhập; cần logout trước khi thử một lần đăng nhập mới.
2. Chọn Khách hàng, nhập tài khoản Host ACTIVE: phải thấy lỗi khu vực Khách hàng ở ô email và vẫn ở form login.
3. Chọn Host, nhập tài khoản Customer ACTIVE: phải thấy lỗi khu vực Đối tác, không vào dashboard.
4. Chọn đúng chế độ/tài khoản: đăng nhập thành công; thử với mật khẩu sai phải nhận lỗi credential chung.
5. Dùng `/login?role=admin` với tài khoản Admin; tài khoản Admin không đăng nhập qua form Customer/Host.
6. Nếu vừa thử sai nhiều lần, chờ hết Retry-After trước khi thử tiếp. Không cần seed hoặc migrate lại database.

Đề xuất commit cho developer tự dùng: `fix(auth): enforce selected login context`. Dừng sau cập nhật UC2, không làm UC khác.

## 10. Bổ sung nút Quản trị viên trên form login

Ngày 28/10/2026, theo yêu cầu người dùng, thêm nút **Quản trị viên** ngay cạnh Khách hàng và Đối tác. Cả ba nút xuất hiện ở mọi chế độ, có trạng thái được chọn và `aria-pressed`; dùng grid ba cột, ẩn icon trên màn hình nhỏ để dành chỗ cho nhãn. Trong lúc xác thực, các nút bị khóa như trước.

Nút Quản trị viên cập nhật query `role=admin`, hiển thị form Admin và gửi `expected_role=ADMIN` qua luồng auth đã có. Chuyển từ signup notice sang Admin sẽ bỏ `mode=signup`. Có thể bấm hai nút còn lại để quay về Customer/Host; lỗi field cũ được xóa khi chuyển chế độ. Kiểm tra role/status, API và database không thay đổi.

Lượt bổ sung này chỉ sửa `frontend/src/pages/public/LoginPage.jsx` và báo cáo này; giữ nguyên các thay đổi UC2 chưa commit từ lượt trước. Không thêm dependency, test mô phỏng implementation, route, migration hoặc seeder.

Kiểm chứng thực tế:

| Command | Exit code | Kết quả |
| --- | --- | --- |
| `npm.cmd run lint` | 0 | 0 errors / 3 warnings Fast Refresh có sẵn |
| `npm.cmd run build` ngoài sandbox | 0 | PASS; 3303 modules, JS 1,055.89 kB (gzip 295.09 kB), warning chunk lớn có sẵn |
| `git diff --check` | 0 | Không có whitespace error; thông báo LF/CRLF của working copy không phải lỗi |

Backend không thay đổi ở lượt thêm nút, không chạy lại backend tests. Chưa tự kiểm chứng bằng browser. Thử tay: logout phiên cũ → mở `/login` → bấm Quản trị viên → thấy tiêu đề Admin và nút Admin được chọn → đăng nhập tài khoản Admin; thử Customer/Host trong chế độ Admin vẫn phải nhận lỗi mismatch do backend mục 9.
