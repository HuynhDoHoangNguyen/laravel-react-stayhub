# StayHub TV1 — UC3: Quản lý hồ sơ cá nhân

## 1. Kết quả hiện tại

Hoàn thành backend/frontend UC3 và các kiểm thử tự động khả thi. Phiên bản cuối bao gồm giới tính, ngày sinh, upload avatar từ máy bằng nút camera và email chỉ đọc theo yêu cầu bổ sung của người dùng.

**Cần chạy migration bổ sung trên MySQL để lưu hai trường mới. Phiên này chỉ chạy migration trên SQLite kiểm thử, chưa thay đổi database MySQL. Browser E2E hai origin chưa nghiệm thu.**

| Mục | Kết quả |
| --- | --- |
| Ngày | 28/10/2026, Asia/Saigon |
| Branch | feature/tv1-uc03-profile |
| HEAD nền | b0428442371369bbe3814eb3456e2b4c430e904e |
| Working tree bắt đầu UC3 | Sạch, đã có UC1/UC2 |
| Baseline backend chạy trước UC3 | 301 tests / 1.409 assertions PASS, 18.87 giây |
| Profile tests cuối | **106 tests / 643 assertions PASS**, 9.97 giây |
| Toàn bộ backend cuối | **407 tests / 2.066 assertions PASS**, 58.65 giây |
| Frontend Node cuối | **49 PASS**: 27 tests nền và 22 profile tests |
| Lint | PASS, 0 errors / 3 warnings nền |
| Build | PASS, 3311 modules; warning chunk lớn còn tồn tại |
| Git/MySQL | Không commit/push/merge/chuyển nhánh, không seed/reset hoặc sửa account MySQL |

Lịch sử kiểm chứng của cùng UC3: phiên bản hồ sơ ban đầu 364 backend tests; sau yêu cầu phone đúng 10 chữ số là 384; bản hiện tại 407. Email trước đây được sửa trong hồ sơ, nay bị khóa theo yêu cầu mới. Không dùng các kết quả cũ làm kết quả của bản cuối.

## 2. Phạm vi và quyết định

Customer, Host và Admin ACTIVE xem/sửa chính hồ sơ của mình bằng một form/service chung. Customer/Host có UC3 trong sơ đồ; Prompt cho phép API self-profile cho cả Admin, nên Admin dùng cùng giao diện tự quản lý, không triển khai UC40/41/42 hoặc Admin sửa người khác.

Các quyết định cuối:
- Cho phép sửa name, phone, address, gender, date_of_birth.
- Email đăng nhập chỉ đọc ở UI và bị từ chối tại API, kể cả gửi lại giá trị cũ hoặc null. Không đổi verification hoặc phiên do sửa contact.
- Phone tùy chọn; nếu nhập phải đúng 10 chữ số ASCII, không +84/dấu phân cách/chữ; giữ số 0 đầu. Không tự cắt số dư hoặc chuyển sang số JSON.
- Giới tính MALE/FEMALE/OTHER, nhãn Nam/Nữ/Khác; có thể để trống.
- Ngày sinh tùy chọn, ngày lịch thực YYYY-MM-DD, từ 1900-01-01 đến hôm nay theo Asia/Ho_Chi_Minh. Không áp tuổi tối thiểu.
- Mockup Profile có Password, vì vậy giữ endpoint/form đổi mật khẩu riêng, yêu cầu mật khẩu hiện tại và chính sách UC1.
- Avatar từ chỉ hiển thị ban đầu được mở rộng thành upload theo yêu cầu mới: nút camera tròn ở góc dưới bên phải, chọn file rồi lưu ảnh. Không có crop, ảnh AI hoặc dịch vụ lưu ảnh bên ngoài.

Yêu cầu bổ sung mới cho phép mở rộng User/resource và thêm migration riêng cho hai trường mới, thay giới hạn không đổi schema của prompt UC3 ban đầu. Không sửa migration khởi tạo, seeders, auth/session/Sanctum/CORS config, dependency/lockfiles hoặc nghiệp vụ/snapshot Booking/Invoice/Review.

## 3. Khảo sát và cách triển khai

Đọc AGENTS.md root đầu tiên; không thấy AGENTS con áp dụng khi tìm bằng rg --files --hidden. Prompt UC3 giao TV1 và cho phép tiếp tục sau mini plan. Đã trình bày contract, actor, file/scope và chia nội bộ backend → frontend/auth sync → testing/report trước triển khai; các yêu cầu bổ sung cũng được thông báo trước khi sửa.

Đối chiếu kiến trúc và phần liên quan trong ARCHITECTURE.md, README.md, docs/ai-frontend-development-guide.md, ERD/use-case/team assignment/screen mockups, seed-data, STAYHUB_M2_CONTEXT.md, TV1 README/audit/UC01/UC02 reports. Tài liệu bắt buộc đều có. Code thực tế chưa có profile API lúc bắt đầu UC3; chọn GET/PATCH theo prompt thay PUT dự kiến trong tài liệu cũ.

Đọc User/enums/migrations, auth/registration/routes/resources/tests, bootstrap/CORS/filesystems/PHPUnit guards; React context/AuthFeedback/services/navigation/router/guard/layout và caller hiển thị user. AuthContext thực tế ở frontend/src/context/. Tham khảo bố cục EditProfileDialog của template; không import runtime, endpoint hoặc dependency từ docs.

Storage thực tế có disk local trỏ storage/app/private. Avatar dùng disk này và API đọc ảnh của chính chủ, không cần storage:link hoặc đổi filesystem config. PHP có fileinfo nhưng chưa bật GD; validation/upload không cần GD. PNG fixtures trong tests được tạo bằng bytes/zlib, không dùng UploadedFile::fake()->image() vốn cần GD.

## 4. File tạo/sửa

Tổng 37 file trong working tree của cả UC3: 19 backend, 16 frontend, 2 tài liệu.

| Backend, đường tương đối với backend/ | Trách nhiệm |
| --- | --- |
| app/Http/Controllers/Profile/ProfileController.php | Show/update/password/upload/stream avatar, controller mỏng |
| app/Http/Requests/Profile/UpdateProfileRequest.php | Partial whitelist, phone/date/gender validation, khóa email |
| app/Http/Requests/Profile/ChangePasswordRequest.php | Current/new/confirmation và từ chối payload ngoài scope |
| app/Http/Requests/Profile/UploadAvatarRequest.php | MIME/image/size/dimensions và whitelist file |
| app/Services/Profile/ProfileService.php | Self-update/password trong transaction, hash và kiểm tra current password |
| app/Services/Profile/AvatarService.php | Tên file server tạo, DB/file consistency, cleanup có giới hạn ownership |
| app/Enums/Gender.php | Enum giới tính |
| app/Models/User.php | Fillable/casts giới tính và ngày sinh, giữ các cast/relations cũ |
| app/Http/Resources/Auth/AuthUserResource.php | Whitelist thêm gender/date_of_birth, URL avatar không lộ đường dẫn storage |
| database/migrations/2026_10_28_000001_add_personal_details_to_users_table.php | Thêm string gender/date date_of_birth nullable, không sửa migration cũ |
| routes/api/profile.php | 5 route profile có auth/status và throttle mutation nhạy cảm |
| routes/api.php | Require module profile tối thiểu |
| app/Http/Requests/Auth/CredentialRules.php | Tách chính sách email/password UC1 để dùng lại |
| app/Http/Requests/Auth/RegisterRequest.php | Gọi shared rules, giữ contract đăng ký |
| app/Services/Auth/EmailUniqueConflict.php | Tách nhận diện constraint email của UC1, không map lỗi DB tùy ý |
| app/Services/Auth/RegistrationService.php | Gọi shared conflict helper, giữ whitelist tạo tài khoản |
| tests/Feature/Profile/ProfileTest.php | Safe DB guard, sessions, contact/date/gender/email/password/filesystem tests |
| tests/Feature/Auth/AuthenticationTest.php | Whitelist resource mới và coverage 14 protected routes |
| tests/Feature/Auth/RegistrationTest.php | Gender/DOB không được inject qua registration; defaults vẫn null |

| Frontend, đường tương đối với frontend/ | Trách nhiệm |
| --- | --- |
| src/components/profile/ProfilePage.jsx | Trang chung, state/retry/reconciliation, wiring upload |
| src/components/profile/ProfileForm.jsx | Form Lưu/Hủy, select/date, email readOnly, lỗi/focus |
| src/components/profile/ChangePasswordForm.jsx | Form đổi mật khẩu, clear password sau response |
| src/components/profile/ProfileHeader.jsx | Avatar/fallback, nút camera, chọn file, loading/error |
| src/components/profile/PersonalInfoCard.jsx | Read/edit và hiển thị gender/ngày sinh |
| src/pages/customer/CustomerProfilePage.jsx | Wrapper dùng trang chung |
| src/components/common/AccountDropdown.jsx | Menu profile Host/Admin, hiển thị avatar từ context |
| src/services/profileService.js | Nối shared Axios vào domain service |
| src/services/profile.js | Whitelist/diff/validation/service/editor lifecycle, multipart upload |
| src/services/credentialValidation.js | Email/password rules UC1 tách nhỏ, không thay chính sách |
| src/services/registration.js | Dùng shared validators, giữ registration flow |
| src/services/authSession.js | Commit profile có kiểm tra version/identity |
| src/context/AuthContext.jsx | Capture/check/commit user, vô hiệu restore cũ |
| src/routes/AppRouter.jsx | Nối Host/Admin profile trong guard/layout tương ứng |
| src/utils/authNavigation.js | Thêm đúng các đường self-profile vào allowlist |
| tests/profile.test.js | Service/editor/auth lifecycle/date/upload/whitelist tests |

Tài liệu: báo cáo này và link trong docs/TV1/README.md. Giữ nguyên audit, UC01-REPORT.md và UC02-REPORT.md lịch sử.

## 5. API contract cuối

Tất cả route dưới đây đi qua auth:sanctum rồi account.active. Không nhận ID để chọn người khác; actor từ session phía server.

| Method/URL | Request | Thành công |
| --- | --- | --- |
| GET /api/profile | Không payload chọn user | 200 {user} |
| PATCH /api/profile | Một hoặc nhiều name/phone/address/gender/date_of_birth | 200 {message,user} |
| PATCH /api/profile/password | current_password/password/password_confirmation | 200 {message} |
| POST /api/profile/avatar | Multipart một file avatar | 200 {message,user} |
| GET /api/profile/avatar?v=... | Không nhận path/user ID; v chỉ đổi URL cache | Ảnh hiện tại của chính chủ, hoặc 404 |

AuthUserResource trả id/name/email/phone/address/avatar/gender/date_of_birth/role/status/email_verified_at/created_at/updated_at. Không password, remember_token, relationship, đường dẫn storage riêng hoặc wrapper data.user. Avatar legacy giữ hiển thị như trước; avatar do API này tạo được đổi thành URL endpoint có version. Date trả YYYY-MM-DD, không chuyển múi giờ ngày sinh.

Contact:
- Name nếu gửi phải là chuỗi không rỗng sau trim, tối đa 255.
- Phone/address/gender/date_of_birth nullable; chuỗi trống thành null. Address tối đa 500.
- Field bỏ qua giữ nguyên; payload rỗng 422 errors.profile. Ngoài whitelist, kể cả email/avatar/ID/role/status/password/verification/token/timestamps/relations, đều 422.
- Service dựng whitelist lần nữa, khóa row của actor trong transaction. Contact không đổi email/email_verified_at, password, role/status hoặc dữ liệu snapshot lịch sử.
- Lỗi sau SQL update rollback toàn bộ. Profile không còn có mutation email, do đó lỗi constraint DB không bị gán nhầm thành lỗi trùng email. Chính sách unique/email validation của registration vẫn có regression tests riêng.

Password:
- Current password required string, tối đa 4096; kiểm tra Hash::check trên row đã khóa.
- Password mới tối thiểu 8 ký tự, hoa/thường Unicode, số ASCII, ký tự đặc biệt; không whitespace/control/null; tối đa 72 byte UTF-8; confirmation khớp và khác current.
- Hash::make mọi plaintext trước khi gán, kể cả chuỗi giống hash. Refresh guard user sau khi lưu để Sanctum ghi fingerprint mới, giữ phiên hiện tại.
- Throttle 5 requests/phút; không reset/chia sẻ bộ đếm login/register. Không thêm password history hoặc workflow quản lý mọi thiết bị.

Avatar:
- MIME thực JPEG/PNG/WebP; image validation, tối đa 2048 KB và 4096×4096 pixel. Không SVG/GIF hoặc URL client gửi.
- File nằm profile-avatars/{actor-id}/{server-uuid}.{extension} trong disk local riêng. Extension suy từ file, không dùng tên/path client để lưu.
- Lưu file mới trước, transaction khóa user rồi thay pointer; DB thất bại thì xóa file mới và giữ row/file cũ. Storage write thất bại không cập nhật DB.
- Sau DB success chỉ dọn file cũ có đúng dạng do hệ thống tạo trong thư mục của actor. Không xóa URL legacy, ảnh chia sẻ hay file của user khác. Cleanup lỗi chỉ log thông báo chung; không làm mất avatar mới đã commit.
- GET ảnh kiểm tra session/status/ownership; bỏ qua query path client. Cache-Control private/no-store và X-Content-Type-Options nosniff. Không cần public symlink.
- Upload throttle 10 requests/phút. Đọc ảnh không dùng bộ đếm upload.
- DB và filesystem không phải distributed transaction; cleanup best effort có thể để lại file không dùng nếu storage lỗi khi dọn, nhưng không đổi pointer đã commit hoặc xóa avatar ngoài phạm vi.

Guest 401; BLOCKED/INACTIVE theo đúng account codes UC2. Test status login lại trước từng endpoint vì lần chặn đầu đã invalidated session. Errors field 422, CSRF 419, throttle 429, mạng/5xx được hiển thị an toàn, không SQL/stack trace. Log không chứa email/phone/ngày sinh/password/file payload.

## 6. Frontend và đồng bộ auth

Các đường /customer/profile, /host/profile, /admin/profile dùng đúng layout/role guard; menu tài khoản có Hồ sơ cá nhân. Một ProfilePage và các form/service chung. ReturnTo chỉ mở các đường đã có theo role, không URL ngoài hệ thống hoặc profile theo ID.

Email là input readOnly màu nền nhạt, vẫn đọc/copy được; không có trong editable fields hoặc payload PATCH. Các trường mới có label, select/date input, min/max ngày, field errors và focus. Date input badInput được chặn trước khi lưu để không hiểu ngày nhập dở là yêu cầu xóa ngày sinh. Phone giữ 10 chữ số; không dùng maxLength=10 để tự cắt 11 chữ số thành số khác.

Nút camera tròn ở góc dưới bên phải avatar mở file picker từ máy. Hủy picker không gửi request. Chọn file hợp lệ gửi multipart bằng shared Axios, không tự đặt Content-Type/boundary. Có disabled/loading/error; chọn lại cùng file vẫn hoạt động vì input được reset. Ảnh chỉ đổi theo response server. AvatarImage trong menu tài khoản đọc cached user nên đổi cùng profile.

Editor giữ bản nháp độc lập với restore auth. Lưu chỉ gửi diff hợp lệ; Hủy về lần load/save gần nhất, không mutation. 422 giữ draft. Upload ảnh khi đang sửa contact giữ nguyên draft; Hủy contact sau upload không hoàn tác ảnh đã lưu.

Capture auth version/identity trước mỗi request. Commit chỉ khi đúng phiên/user và không trong login/logout; tăng version để restore hoặc auth errors cũ không ghi đè user vừa cập nhật. Unmount/logout/đổi user vô hiệu response muộn. Password không đi vào contact draft, context, URL/storage/log; form clear sau response/unmount.

Timeout/5xx sau PATCH/upload là kết quả chưa xác nhận, không báo chắc chắn chưa lưu và không auto retry. Contact/avatar yêu cầu load lại để đối chiếu trước mutation tiếp; reload lỗi vẫn giữ chặn này. Reload có ghi rõ bỏ draft. Password chưa xác nhận hướng dẫn thử login lại. Không hoàn tác request server đã xử lý khi người dùng chuyển trang.

## 7. Kiểm thử thực tế

| Command | Kết quả cuối / exit |
| --- | --- |
| Backend: php artisan test --filter=ProfileTest --compact --no-ansi | 106 tests / 643 assertions, 9.97 giây, exit 0 |
| Backend: php artisan test --compact --no-ansi | 407 tests / 2.066 assertions, 58.65 giây, exit 0 |
| Backend: php artisan route:list -v --path=profile --no-ansi | 5 routes đúng auth/status/throttle, exit 0 |
| Frontend: node --test tests/auth.test.js tests/registration.test.js tests/profile.test.js | 49 PASS, exit 0 |
| Frontend: npm.cmd run lint | 0 errors / 3 warnings nền, exit 0 |
| Frontend: npm.cmd run build | PASS, 3311 modules, JS 1.075,66 kB/gzip 302,02 kB, exit 0 |
| Root: git diff --check | Exit 0; chỉ cảnh báo chuyển CRLF/LF của Git |

Ba lint warnings nền: badge.jsx:25, button.jsx:38, sidebar.jsx:536 (Fast Refresh). Build còn warning chunk >500 kB. Node/build chạy ngoài sandbox theo quyền được cấp vì hạn chế spawn EPERM đã xác nhận ở các lượt trước; không cài/thay dependency hoặc cấu hình để né sandbox.

PHP thực thi D:/xampp/php/php.exe. Trước test đặt APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL rỗng, CACHE_STORE/SESSION_DRIVER=array, LOG_CHANNEL=null. Guard kiểm tra env và config cache trước boot, rồi config hiệu lực trước RefreshDatabase. File tests dùng Storage::fake(local), không đụng ảnh thật. Không đọc/in .env/secret hoặc seed/reset/migrate MySQL.

Coverage gồm session login thật của ba role; resource whitelist; self-only; immutable email kể cả value cũ/null; partial/empty/unknown fields; phone/type/length; gender enum/null; ngày lịch, leap day, range và ngày Việt Nam; password/rollback/current session; resource defaults và registration injection; upload/stream own avatar; MIME giả/SVG/size/dimensions/payload ID; cleanup/replacement/ownership; file write fail và SQL fail; account-status guard ở cả năm endpoint.

Node tests chạy service/editor/helper thật với Axios fixture adapter: whitelist, multipart, current auth commit, stale response, cancel/no-change/duplicate submit, draft preservation, date/timezone, avatar validation/upload failure/reconciliation. Đây không phải React DOM/E2E tests. UC1/UC2/Invoice/Review regression đều nằm trong toàn bộ suite cuối; protected route coverage hiện 14 và đúng thứ tự auth → account status.

## 8. Cập nhật MySQL và thử tay

Chưa chạy migration mới trên MySQL. Từ repository, developer chạy đúng migration bổ sung:
~~~powershell
cd backend
php artisan migrate --path=database/migrations/2026_10_28_000001_add_personal_details_to_users_table.php
~~~

Migration up chỉ thêm hai cột nullable, không xóa dữ liệu hay seed lại. Không chạy migrate:fresh/refresh/rollback để thử tính năng này. Không cần storage:link.

Sau đó:
1. Chạy backend/frontend như hiện tại; Ctrl+F5 và login, menu tài khoản → Hồ sơ cá nhân.
2. Chỉnh sửa: email chỉ đọc, thử copy; gender có Nam/Nữ/Khác/không muốn cung cấp, ngày sinh chọn bằng date input. Lưu rồi refresh phải giữ hai trường mới; xóa nullable rồi lưu phải về chưa cung cấp.
3. Thử ngày 31/04, năm không nhuận 29/02, ngày tương lai và ngày nhập dở: báo lỗi, không lưu. Phone 0901234567 hợp lệ; 9/11 số, +84/dấu phân cách/chữ bị chặn. Gửi email qua API phải 422 và không cập nhật phần còn lại.
4. Bấm camera dưới avatar → chọn JPG/PNG/WebP ≤2 MB. Ảnh profile và menu thay sau success; refresh giữ ảnh. Thay lần nữa để kiểm tra fallback và ảnh cũ không còn được dùng. Hủy picker không đổi ảnh.
5. Thử tệp giả ảnh, SVG, file >2 MB hoặc >4096 pixel: báo lỗi, giữ ảnh cũ. Chọn ảnh khi đang sửa tên chưa lưu: draft tên vẫn còn.
6. Kiểm tra Lưu/Hủy, bấm nhanh nhiều lần, timeout/offline, logout/chuyển trang khi đang upload, session hết hạn và account fixture bị khóa. Không dùng account demo/live để có bằng chứng.
7. DevTools kiểm tra cookie/XSRF và multipart; request img đến backend có session/referrer đúng hai origin. Kiểm tra mobile/tablet/desktop, tab/focus và nút camera bằng bàn phím.

**CHƯA KIỂM CHỨNG:** browser interactions/DOM, cookie/CSRF xuyên suốt hai origin, responsive/keyboard thực tế, MySQL migration/concurrency và JPEG/WebP browser decode trên runtime người dùng. Phiên không có browser tool hoặc Playwright/Puppeteer; không cài framework chỉ để thử. PNG upload/stream đã có feature tests; Laravel feature tests thường bypass CSRF. Preflight /api/profile từng kiểm tra 204 không thay cho E2E.

SQLite không chứng minh collation/locking MySQL hoặc điều kiện disk/permissions của máy triển khai. Không thêm optimistic conflict UI cho nhiều tab. Cleanup storage lỗi có thể cần dọn file không dùng từ log vận hành. Việc quản lý mọi phiên sau đổi password vẫn theo Sanctum hiện có; phiên hiện tại được kiểm thử giữ hoạt động.

## 9. Bàn giao

Chỉ UC3, không tự triển khai UC tiếp theo. Không có commit/push/merge/rebase/chuyển branch. Tài liệu audit/UC1/UC2 được giữ nguyên; bước còn lại là developer chạy migration bổ sung và nghiệm thu browser ở mục 8.

Commit gợi ý:
~~~text
feat(profile): add personal details and avatar upload

Add optional gender and birth date through an additive migration.
Lock profile email and add authenticated avatar upload with safe file cleanup.
Keep contact drafts and cached auth synchronized; cover validation and failure paths.
~~~
