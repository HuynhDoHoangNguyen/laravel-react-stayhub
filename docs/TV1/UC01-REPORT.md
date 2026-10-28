# StayHub TV1 — UC1: Đăng ký

## 1. Kết quả và snapshot

**Hoàn thành implementation backend/frontend UC1 và các kiểm thử tự động khả thi. Chưa nghiệm thu browser hai origin hoặc concurrency MySQL thực tế.**

| Mục | Kết quả |
| --- | --- |
| Ngày | 28/10/2026, Asia/Saigon |
| Branch | `feature/tv1-uc01-register`, developer chuẩn bị trước |
| HEAD nền | `8b6b329283ad7ca17fbad3189bc41eeb63832d73` |
| Working tree đầu lượt | Sạch; nền UC2, các bổ sung đúng vai trò/nút Admin và tài liệu đã có trong HEAD |
| Baseline chạy lại | **216 backend tests / 957 assertions PASS**, không dùng số 198/865 cũ trong prompt làm kết quả mới |
| UC1 backend sau bổ sung CORS/validation | **85 tests / 452 assertions PASS** trong toàn bộ suite |
| Backend regression cuối | **301 tests / 1.409 assertions PASS** |
| Frontend Node tests | **27 PASS**: 11 auth tests cũ + 16 registration tests |
| Frontend lint/build | PASS; 0 lint errors, 3 warnings nền; warning chunk lớn khi build |
| Dữ liệu | Chỉ fixture SQLite `:memory:`; không đọc/ghi database MySQL demo/live, không migrate/seed/reset dữ liệu lưu giữ |

Chỉ triển khai UC1. Không triển khai UC3/profile, đăng ký Host/Admin, email verification, remember-me, token/JWT hoặc hệ thống auth thứ hai. Không tạo/chuyển branch, stash/reset, commit/push/merge/rebase. Nội dung lịch sử audit và UC02-REPORT giữ nguyên.

## 2. Tài liệu và kiểm tra nền

Đọc `AGENTS.md` root đầu tiên; không thấy AGENTS con bổ sung. Prompt hiện tại giao TV1, giữ quy tắc kiến trúc/bảo mật/dữ liệu và cho phép tiếp tục sau mini plan. Đã trình bày hiểu biết, contract, phạm vi file và ba bước nội bộ: backend → frontend → test/báo cáo. Đây là các bước nhỏ của cùng UC1, không chuyển UC khác.

Đối chiếu theo thứ tự: `ARCHITECTURE.md`, `README.md`, `docs/ai-frontend-development-guide.md`, `docs/stayhub-erd.md`, `docs/stayhub-usecase-system.md`, `docs/stayhub-team-work-assignment.md`, `docs/stayhub-screen-mockups.md`, `docs/seed-data.md`, `STAYHUB_M2_CONTEXT.md`, `docs/TV1/README.md`, audit và UC02-REPORT. Tài liệu UC2 phần bổ sung có 216 tests, phù hợp baseline code hiện tại; số 198 trong phần cũ được coi là lịch sử.

Đọc User/enum, migration users/schema, auth routes/bootstrap, toàn bộ lớp auth UC2 và AuthenticationTest, cấu hình PHPUnit, frontend auth services/session/context/hooks/feedback/navigation/guard/router/callers. Tham khảo bố cục `docs/StayNest-Frontend-template/src/components/auth/SignUpForm.jsx`; không import runtime, endpoint hay logic auto-login/manager của template. Dùng input React có sẵn, không thêm thư viện form/test.

`backend/config/hashing.php` không tồn tại trong app; Laravel dùng cấu hình framework `vendor/laravel/framework/config/hashing.php` (bcrypt mặc định). Không tự tạo config thay thế hoặc đổi hasher. Đọc cách hashed cast thực thi tại `HasAttributes::castAttributeAsHashedString` để tránh nhận nhầm plaintext có hình dạng hash. `docs/api/booking-module.md` vẫn thiếu; không cần contract Booking cho UC1, không tưởng tượng nội dung.

## 3. File tạo/sửa

### Backend

| File | Trạng thái / trách nhiệm |
| --- | --- |
| `backend/app/Http/Requests/Auth/RegisterRequest.php` | Mới: trim/validation/whitelist HTTP, password confirmation và byte limit |
| `backend/app/Services/Auth/RegistrationService.php` | Mới: tạo Customer/Active, hash đầu vào, transaction, xử lý email unique đúng constraint |
| `backend/app/Http/Middleware/ThrottleRegistration.php` | Mới: limiter riêng theo IP, tính cả validation/conflict |
| `backend/app/Http/Middleware/EnsureRegistrationGuest.php` | Mới: 409 JSON cho session đã xác thực |
| `backend/tests/Feature/Auth/RegistrationTest.php` | Mới: guard DB trước boot, register/session/defaults/validation/limiter/DB errors |
| `backend/app/Http/Controllers/Auth/AuthController.php` | Thêm method `register`, controller chỉ điều phối và trả 201 |
| `backend/routes/web.php` | Nối POST `/register` qua hai middleware trên trong web group hiện có |
| `backend/config/cors.php` | Thêm path `register` sau khi người dùng cho phép sửa cấu hình |

### Frontend

| File | Trạng thái / trách nhiệm |
| --- | --- |
| `frontend/src/components/auth/RegisterForm.jsx` | Mới: form bốn field, loading/error/countdown, focus, chống double-submit và response cũ |
| `frontend/src/services/registration.js` | Mới: register qua client được truyền từ shared Axios; whitelist, normalize lỗi đăng ký và submission lifecycle |
| `frontend/tests/registration.test.js` | Mới: service/error/interceptor/caller async tests bằng Node có sẵn |
| `frontend/src/services/authService.js` | Thêm `register()` dùng shared client và helper trên |
| `frontend/src/pages/public/LoginPage.jsx` | Thay signup notice bằng RegisterForm; success chuyển login/prefill email, giữ login/role/returnTo của UC2 |

### Tài liệu và phạm vi giữ nguyên

Tạo `docs/TV1/UC01-REPORT.md`; thêm link vào `docs/TV1/README.md`. Tổng **15 file UC1**, bao gồm CORS được người dùng cho phép ở lượt khắc phục sau. Không sửa User, enum, migration, seeder, bootstrap, API routes/protected coverage, config auth/session, shared Axios, AuthContext, AuthUserResource, LoginRequest/AuthService, domain Invoice/Review/Booking/catalog, dependency hoặc lockfile. Không sửa audit hay UC02-REPORT để ghi đè lịch sử.

## 4. Backend contract

### 4.1 Request và response

```http
POST /register
Accept: application/json
Content-Type: application/json
```

Đi qua web session và CSRF, tương tự `/login`; client gọi `/sanctum/csrf-cookie` trước. Không có `/api/register`, không tắt CSRF. Session ẩn danh phục vụ CSRF không phải session đã xác thực.

Request chỉ dùng bốn field:

| Field | Quy tắc |
| --- | --- |
| `name` | Required string; trim; tối đa 255 ký tự theo migration |
| `email` | Required string; trim; tối đa 254 ký tự, phần trước @ tối đa 64; định dạng ASCII dot-atom, domain có dấu chấm và đuôi 2–63 chữ cái; unique users.email theo DB collation hiện có |
| `password` | Required string; ít nhất 8 ký tự, có chữ hoa/thường, số ASCII và ký tự đặc biệt; tối đa 72 ký tự và **72 byte UTF-8**; không có khoảng trắng/ký tự điều khiển; confirmation khớp |
| `password_confirmation` | Required string; không lưu database |

Không yêu cầu phone trong UC1; không tự thêm field profile. Không lower-case email ở input/query/database. Byte limit tránh bcrypt cắt ngầm mật khẩu đa byte; không thay thuật toán hay cấu hình hasher. Validation không dùng dịch vụ mạng/password breach checker.

Thành công:

```http
201 Created
```

```json
{"message":"Đăng ký thành công. Vui lòng đăng nhập để tiếp tục."}
```

Không trả user/profile/hash/token; không gọi login, không gắn identity vào session. `/api/me` sau đăng ký vẫn 401. Customer có thể đăng nhập qua UC2 sau đó.

Validation trả 422 `{message,errors}` với thông báo field tiếng Việt. Email trùng có `errors.email[0] = "Địa chỉ email này đã được sử dụng."`. Các lỗi kỹ thuật khác không được biến thành lỗi trùng email.

### 4.2 Giá trị server và password

Controller lấy `safe()->only(['name','email','password'])`; service tiếp tục xây explicit create array. Backend gán cố định `role=CUSTOMER`, `status=ACTIVE`, `email_verified_at=null`. Không nhận `id`, role/status, timestamps, remember_token, phone/address/avatar hay relationships do client gửi. Tests thử payload ADMIN/HOST, BLOCKED/INACTIVE và các trường bảo vệ vẫn tạo đúng Customer/Active.

Service dùng `Hash::make` với hasher hiện có để coi **mọi chuỗi từ client là plaintext**, kể cả chuỗi trông giống bcrypt hash. User hashed cast nhận hash vừa tạo và không băm lại. Điều này tránh việc hashed cast lưu nguyên một password có hình dạng hash, khiến password thực tế khác chuỗi người dùng nhập. Có test kiểm tra cả password thường và hash-shaped literal bằng Hash::check/login thật; không hash hai lần và không in giá trị hash.

Tạo User trong transaction. Log nghiệp vụ chỉ có thông báo đăng ký thành công sau commit, không chứa payload/password/email/token/cookie. Không thêm bảng profile hoặc thay email unique constraint.

### 4.3 Guest-only và limiter

`ThrottleRegistration` chạy trước `EnsureRegistrationGuest` và Form Request, sau web/CSRF:

- Khóa limiter `auth:register:` + SHA-256(IP); tối đa **5 POST / 60 giây** từ request đầu, theo IP.
- Tính cả success, lỗi validation và conflict đã tới route; request thứ 6 trả 429 trước khi tạo user.
- 429 có message chung tiếng Việt, header `Retry-After` và body số `retry_after`. Không phụ thuộc CORS expose header.
- GET csrf-cookie không tính vào limiter. CSRF của web group chạy trước route limiter; không thay thứ tự bảo vệ để tính request sai CSRF.
- Không reset counter khi đăng ký thành công; không dùng/chia sẻ/reset limiter login UC2. Tests kiểm tra độc lập theo cả hai chiều và dùng clock travel, không sleep.

Session web đã login gọi register: **409 JSON**, không redirect HTML:

```json
{
  "message":"Bạn đang đăng nhập. Vui lòng đăng xuất trước khi đăng ký tài khoản mới.",
  "code":"ALREADY_AUTHENTICATED"
}
```

Không tạo tài khoản hoặc thay identity/session hiện có. Kiểm tra áp dụng cho CUSTOMER/HOST/ADMIN. Nếu account vừa BLOCKED nhưng session chưa bị xử lý, register vẫn từ chối; protected `/api/me` kế tiếp tiếp tục áp dụng status guard UC2 như cũ.

### 4.4 Unique email và race

Form Request dùng unique query để phản hồi sớm; DB unique constraint vẫn là lớp bảo vệ cuối. RegistrationService chỉ ánh xạ `UniqueConstraintViolationException` có constraint email đã nhận diện:

- MySQL/MariaDB driver code 1062 và index `users_email_unique` (có/không có tiền tố bảng).
- SQLite driver code 19/2067 với lỗi chính xác `UNIQUE constraint failed: users.email`.

Các unique constraint khác như PRIMARY/users.id và QueryException khác được ném tiếp cho Laravel handler; không nuốt mọi lỗi thành trùng email. Có kiểm chứng SQLite unique thật bằng gọi service sau khi đã có email; có fault injection cho chi tiết MySQL email/PRIMARY và lỗi DB khác. **Đây không phải kiểm thử hai request đồng thời trên MySQL**; chưa tuyên bố concurrency PASS.

## 5. Frontend flow

Mở `/login?mode=signup` hoặc bấm Đăng ký trên LoginPage. Form thật có họ tên/email/password/confirmation, label/id/autoComplete, lỗi từng field, password hint/show toggle, pending/disabled và countdown. Bố cục/theme giữ nguyên, phần form có thể cuộn trong panel hiện có.

Signup luôn ghi rõ **Đăng ký Khách hàng**, ẩn bộ chọn quyền. Bấm Đăng ký từ Host đưa URL về context customer; trực tiếp mở URL có `role=host` hoặc `role=admin` cùng `mode=signup` vẫn là Customer signup. Chỉ login có ba ngữ cảnh UC2. Không có thao tác public tạo Host/Admin.

`AuthContext` không thêm register hoặc set user. `authService.register` dùng shared Axios qua helper `registerCustomer`; helper chỉ gửi bốn field, lấy CSRF cookie, timeout 10 giây, không tự POST lại và không POST login. Không thêm Axios instance/auth context hoặc lưu credential vào storage/URL/route state.

Sau 201: xóa cả hai password, unmount form đăng ký, chuyển về login Customer; email chỉ điền trong state bộ nhớ và giữ notice thành công sau chuyển. `returnTo` vẫn qua allowlist UC2 khi người dùng tự login sau đó, không tự điều hướng tới input URL.

| Trạng thái | Hành vi |
| --- | --- |
| Form mới | Bốn field trống; chưa có mutation/success giả |
| Client required/confirmation lỗi | Hiển thị field errors, focus field đầu; không gửi request |
| Đang gửi | Khóa input/submit, pending label; submission controller chỉ cho một request |
| 422 | Giữ name/email, xóa password/confirmation, errors theo field; focus sau khi input được mở khóa |
| 409 | Thông báo đã đăng nhập, cho về login; không tự invalidate session |
| 429 | Countdown Retry-After/body; disable submit; không reset limiter login |
| 419 | Thông báo phiên bảo mật hết hạn; thử lại sẽ lấy CSRF cookie mới |
| Timeout/offline/5xx sau POST | Ghi rõ **chưa nhận xác nhận, tài khoản có thể đã được tạo**; cho về login, không khẳng định tạo thất bại |
| Lỗi trước khi gửi POST | Chưa gửi payload đăng ký; thông báo kết nối từ auth normalizer |
| Rời signup khi request chưa xong | Layout cleanup vô hiệu hóa request version; response cũ không đưa success/error vào màn hình login mới hoặc tác động auth state mới |

Không log Axios config/payload, không hiển thị SQL/stack trace từ error response. Chỉ đọc fields/message đã chuẩn hóa. Kiểm tra `authSession` interceptor giữ nguyên: register 409/422/429 không phải account 403 hay protected 401 nên không xóa user. Có Node tests xác nhận subscriber không bị kích hoạt.

Rời form không đảo ngược request đã tới server: một account có thể được tạo sau khi người dùng chuyển về login. Không tự retry POST; DB unique bảo vệ email khi người dùng gửi lại. Đây là lý do thông báo timeout dùng kết quả chưa xác định thay vì báo chắc chắn thất bại.

## 6. Kiểm thử và bằng chứng

### 6.1 An toàn trước test

Đọc PHPUnit và setup UC2; không có cached `backend/bootstrap/cache/config.php` hoặc `.env.testing`. Mọi lệnh Artisan dùng env process tạm:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:LOG_CHANNEL='null'
```

RegistrationTest kiểm tra các env và config cache **trước boot**, rồi kiểm tra config hiệu lực trước RefreshDatabase. Không chạy guard sau migrate mới kết luận an toàn. Dữ liệu đăng ký/login/status là fixture trong SQLite bộ nhớ; không dùng tài khoản demo/live làm bằng chứng.

### 6.2 Commands / exit codes

| Thư mục | Command | Exit | Kết quả thực tế |
| --- | --- | --- | --- |
| root | Git status/branch/rev-parse trước sửa | 0 | Đúng nhánh UC1, HEAD như mục 1, working tree sạch |
| backend | `php artisan test --compact --no-ansi` trước sửa | 0 | 216 tests / 957 assertions, 28.62 giây |
| backend | `php artisan test --filter=RegistrationTest --compact --no-ansi` cuối | 0 | **39 tests / 221 assertions**, 2.66 giây |
| backend | `php artisan test --compact --no-ansi` cuối | 0 | **255 tests / 1.178 assertions**, 17.69 giây |
| backend | `php artisan route:list -v --path=register --no-ansi` | 0 | POST register → AuthController@register; middleware web → ThrottleRegistration → EnsureRegistrationGuest |
| frontend | `node --test tests/auth.test.js tests/registration.test.js` ngoài sandbox | 0 | **23 tests PASS**; chạy lại sau tự review cuối |
| frontend | `npm.cmd run lint` | 0 | **0 errors / 3 warnings** nền |
| frontend | `npm.cmd run build` ngoài sandbox | 0 | **PASS**, 3305 modules; JS 1,061.55 kB, gzip 296.66 kB; warning chunk >500 kB |
| root | `git diff --check` | 0 | Không whitespace error ở tracked diff; file mới được kiểm tra riêng |

Build/Node test chạy ngoài sandbox vì các lượt trước đã xác nhận Vite/test runner cần tạo tiến trình mà sandbox trả EPERM; dùng quyền được cấp, không cài lại dependency hoặc sửa config để né lỗi. Ba lint warnings cũ: `components/ui/badge.jsx:25`, `button.jsx:38`, `sidebar.jsx:536`, rule Fast Refresh. Không sửa cảnh báo ngoài scope.

### 6.3 Coverage và giới hạn

Backend mới kiểm tra: guest success 201 chỉ message; đúng một Customer/Active, email_verified_at null; Hash::check và không hash hai lần; hash-shaped password không bypass policy; `/api/me` sau register 401 rồi login/restore UC2 thành công; trim/case preservation/boundary; thiếu/sai/quá dài/name-email-password-confirmation, Unicode byte limit và null byte; duplicate email; payload quyền/trường bảo vệ; session thật đã login bị 409 giữ identity; status guard không bị bỏ qua; 5 requests/lần 6/retry_after/clock/IP isolation/CSRF GET; login/register limiter độc lập; SQLite unique thật và lỗi DB mô phỏng được phân loại đúng.

Tests session bắt đầu qua POST login của UC2, mang cookie và reset auth guards giữa requests; không dùng actingAs để kết luận register guest-only hoạt động. Các tests UC2, role/ownership Invoice/Review cũ đều PASS trong toàn bộ suite cuối.

Node tests mới chạy **helper/service/submission logic thật** với Axios fixture adapter: CSRF/order/whitelist/no auto-login; field mapping không giữ credential config; 409/422/429 không invalidation; header/body Retry-After; 419; lỗi trước/sau POST; unknown outcome; chống double-submit; bỏ late success/error sau cancel và không hoàn tất nhầm request mới; retry do người dùng. Đây không phải React DOM hoặc browser tests.

## 7. Browser verification và hướng dẫn thử tay

**CHƯA KIỂM CHỨNG** browser cookie/CSRF giữa hai origin, form interaction, keyboard/responsive thực tế và race MySQL. Phiên không có browser tool hoặc Playwright/Puppeteer cài sẵn; không cài framework, không tạo tài khoản trên demo/live chỉ để có bằng chứng. Laravel feature tests thường bypass CSRF, không thay cho browser verification. UC2 vẫn giữ giới hạn nghiệm thu browser trong báo cáo cũ.

Developer thử bằng môi trường DB test tách biệt và fixture được phép:

1. Chạy frontend/backend hai origin như cấu hình local; đối chiếu `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `VITE_BACKEND_URL` và CORS. Không công khai secret hoặc đổi cấu hình production để thử.
2. Khi guest, vào `/login?mode=signup`; thử cả đường Host → Đăng ký và URL `role=host&mode=signup`: phải luôn thấy đăng ký Khách hàng, không chọn quyền.
3. Dùng email fixture mới, nhập bốn field hợp lệ. Network phải có csrf-cookie trước POST `/register`, cookie/XSRF header; chỉ có một POST khi bấm nhanh nhiều lần.
4. Sau 201 phải trở về login, có notice và email điền sẵn, password trống. Không có POST login tự động; `/api/me` vẫn 401 trước khi tự login.
5. Tự login bằng mật khẩu vừa đặt qua context Customer → refresh giữ session → logout. Kiểm tra payload không chứa password ở URL/storage/route state.
6. Thử duplicate/field thiếu/password ngắn/confirmation sai: 422 từng field và focus phù hợp. Thử 5 POST lỗi rồi lần 6 có 429; hết Retry-After mới thử lại.
7. Với session fixture đang login, POST register phải 409 JSON và không thay identity. Không sửa account demo/live để thử status; fixture status denial phải giữ luật UC2.
8. Giả lập offline/timeout sau POST: UI thông báo chưa xác nhận, có thể về login; không tự lặp POST. Chuyển signup → login khi request pending: response muộn không đổi lại màn hình/auth state mới.
9. Kiểm tra mobile/tablet/desktop, cuộn panel, labels, tab/focus, show password, pending/disabled và thông báo. Không lấy lint/build làm bằng chứng responsive.

## Bổ sung: khắc phục CORS đăng ký (28/10/2026)

- Người dùng đã cho phép sửa cấu hình CORS sau khi báo lỗi đăng ký chưa nhận được xác nhận. Quyền này mở rộng giới hạn không sửa config của prompt UC1 cho thay đổi dưới đây.
- Nguyên nhân: `backend/config/cors.php` chưa có `register` trong `paths`. OPTIONS thực tế từ `http://localhost:5173` trước sửa trả 200 và thiếu `Access-Control-Allow-Origin`.
- Thay đổi: thêm duy nhất `register` vào danh sách paths; giữ nguyên origin, credentials, cấu hình session/CSRF và dữ liệu MySQL.
- Thêm hai test trong `RegistrationTest.php`: preflight cho phép POST/header XSRF/cookie, không tiêu hao limiter; origin ngoài danh sách không được cấp quyền truy cập. Response POST thành công cũng phải có header CORS.
- Kiểm thử cuối: `php artisan test --compact --no-ansi` với SQLite in-memory: **257 tests PASS / 1.247 assertions**, exit 0, 29.44 giây. Lần chạy đầu phát hiện assertion quá chặt về header origin không tin cậy; đã sửa để kiểm tra không phản chiếu origin đó và không trả wildcard, phù hợp thư viện CORS dùng origin cố định.
- OPTIONS thực tế sau sửa: **204**, Allow-Origin `http://localhost:5173`, Allow-Credentials `true`, Allow-Methods `POST`. Không tạo tài khoản trên MySQL để thử; browser đăng ký xuyên suốt vẫn cần người dùng xác nhận. Không thay đổi frontend nên không chạy lại lint/build cho bản sửa này.

## Bổ sung: siết validation email và mật khẩu (28/10/2026)

- Theo yêu cầu người dùng, thay chính sách đăng ký ban đầu chỉ kiểm tra email rộng/mật khẩu tối thiểu 8 ký tự bằng quy tắc cụ thể trong bảng contract bên trên. Giữ nguyên LoginRequest của UC2 để tài khoản cũ tiếp tục đăng nhập; không sửa dữ liệu đã tồn tại.
- Backend `RegisterRequest.php` dùng `email:filter` kết hợp regex cho địa chỉ ASCII thông dụng: cho phép dấu cộng, dấu nháy đơn trong phần tên, tên miền con và email tối đa 254 ký tự; từ chối domain một nhãn, domain literal/IP, quoted local-part, Unicode chưa chuyển sang ASCII, đuôi số/quá ngắn, dấu chấm liên tiếp và nhãn domain sai/quá dài. Không dùng DNS hoặc gửi thư, vì vậy không xác nhận hộp thư tồn tại hay phát hiện được mọi lỗi gõ như tên miền sai nhưng vẫn đúng cú pháp.
- Mật khẩu hỗ trợ chữ hoa/thường Unicode; bắt buộc số 0–9 và ký tự đặc biệt, không khoảng trắng/control/format/null; giữ giới hạn bcrypt 72 byte, xác nhận và hashing trước đây. Không tự trim mật khẩu.
- Frontend `registration.js` bổ sung `validateRegistration`, được gọi trực tiếp từ `RegisterForm.jsx` trước submission. Form hiển thị lỗi từng field, focus field đầu tiên và mô tả chính sách mật khẩu mới. Giữ `noValidate` để dùng thông báo tiếng Việt nhất quán; validation thực thi bằng helper, không dựa vào riêng type/minLength của HTML.
- File thay đổi trong lượt này: `backend/app/Http/Requests/Auth/RegisterRequest.php`, `backend/tests/Feature/Auth/RegistrationTest.php`, `frontend/src/services/registration.js`, `frontend/src/components/auth/RegisterForm.jsx`, `frontend/tests/registration.test.js`, báo cáo này.
- Backend bổ sung 44 ca: email sai, alias/domain con/trim/giới hạn 254, từng nhóm mật khẩu thiếu, whitespace/control, giới hạn byte Unicode, đăng ký rồi đăng nhập với mật khẩu hợp lệ. API trả 422 cho dữ liệu sai và không tạo user, kể cả khi không qua frontend. Node bổ sung 4 tests kiểm tra helper thật với các nhóm trường hợp tương ứng, confirmation và fields trống; đây không phải DOM/browser tests.
- Kết quả cuối: `php artisan test --compact --no-ansi` **301 PASS / 1.409 assertions**, exit 0, 45.28 giây, SQLite `:memory:` có guard trước boot. `node --test tests/auth.test.js tests/registration.test.js` **27 PASS**, exit 0 ngoài sandbox. `npm.cmd run lint` exit 0, 0 errors/3 warnings nền; `npm.cmd run build` exit 0 ngoài sandbox, 3305 modules, cảnh báo chunk >500 kB. Node/build trong sandbox bị spawn EPERM, chạy lại ngoài sandbox theo quyền được cấp. `git diff --check` exit 0.
- Lượt test đầu phát hiện khác biệt validator RFC ở email biên với nhãn 63 ký tự; đã dùng filter cú pháp cùng regex giới hạn rõ ràng và test biên đã PASS trong toàn bộ suite cuối. Không sửa vendor/dependency.
- Không tạo user, migrate, seed hay sửa account MySQL để kiểm thử. Browser interaction thực tế vẫn cần người dùng kiểm tra lại; không tuyên bố browser E2E PASS.

## 8. Hạn chế, dependencies và điểm dừng

- SQLite khác MySQL về collation/case email, VARCHAR/ENUM và concurrency. Giữ nguyên email case/DB unique; không tuyên bố MySQL case/race PASS từ SQLite hoặc hai lần register tuần tự.
- Chỉ nhận diện các email unique constraint đã biết từ migration; lỗi constraint không khớp tiếp tục được xử lý như lỗi DB khác, không che thành email trùng.
- Chưa nghiệm thu browser hai origin; không tự nâng UC1/UC2 thành E2E PASS. Có thể cần cấu hình local đúng theo tên biến ở trên; không sửa `.env`, chỉ bổ sung path CORS sau khi được người dùng cho phép.
- Phone/profile để UC3; tài khoản mới không có phone/address/avatar được gửi từ client. Không tự mở thêm API profile/Host/Admin registration.
- Limiter dùng cache Laravel và IP theo cấu hình hiện có, không thêm hạ tầng hoặc workflow chống spam ngoài yêu cầu 5 POST/60 giây.
- Không có thay đổi schema, dữ liệu MySQL, dependency/lockfile hoặc nghiệp vụ module khác. Branch/HEAD giữ nguyên; không commit/push/merge và không tự làm UC3.

Commit đề xuất để developer tự dùng:

```text
feat(auth): implement customer registration for UC1

Add guest-only CSRF registration with Customer/Active defaults and an IP limiter.
Handle email unique conflicts and safely hash all submitted password strings.
Replace the signup placeholder with a real form and return to login after success.
Add registration tests and document remaining browser/MySQL verification.
```
