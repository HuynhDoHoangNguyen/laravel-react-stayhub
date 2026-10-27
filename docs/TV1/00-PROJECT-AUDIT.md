# StayHub TV1 — Prompt 00: Project Audit

## 1. Snapshot, phạm vi và giới hạn

| Mục | Kết quả |
| --- | --- |
| Ngày khảo sát | **28/10/2026**, múi giờ Asia/Saigon |
| Repository | `D:\University\HK1_2627\MaNguonMo\project\laravel-react-stayhub` |
| Branch | `feature/member-1-catalog` |
| HEAD | `648dc1a10d528d9d646499c4a76c15b54d7d4474` |
| Working tree đầu lượt | Sạch, `git status --short` không có output |
| Nhiệm vụ | Đọc project, đánh giá đủ 20 UC TV1, kiểm chứng baseline và viết tài liệu |
| File được tạo | `docs/TV1/README.md`, `docs/TV1/00-PROJECT-AUDIT.md` |
| Thay đổi nghiệp vụ | Không sửa backend/frontend, cấu hình, dependency, lockfile, migration, seeder hay template |
| Dữ liệu | Không đọc `.env`, không truy vấn MySQL local/demo; không chạy migrate/seed/reset trên database được lưu giữ |
| Kiểm chứng | Route registration; toàn bộ test hiện có trên SQLite `:memory:`; frontend lint/build |
| Chưa xác minh | Schema/dữ liệu MySQL đang chạy, cookie/CORS thực tế trong trình duyệt, HTTP end-to-end, responsive/a11y qua trình duyệt và concurrency MySQL |

Các truy vấn Git chỉ đọc được dùng để xác định snapshot; không tạo/chuyển branch, stash, reset, commit, push, merge hoặc rebase. Test feature tạo schema và dữ liệu giả **chỉ trong SQLite bộ nhớ**, không phải chạy lại seeder demo. Build tạo output trong `frontend/dist/`, được ignore bởi `frontend/.gitignore`.

### 1.1 Chỉ dẫn áp dụng

Yêu cầu được giao qua `D:\Download\StayHub_TV1_Prompt_00_Audit.md`. File root `AGENTS.md` mang vai trò TV3; prompt audit hiện tại giao rõ vai trò TV1 và chỉ cho phép viết tài liệu trong `docs/TV1`. Vì vậy giữ quy tắc kiến trúc/bảo mật/an toàn, áp dụng phân công TV1, không sửa `AGENTS.md` và không chuyển sang làm TV3.

`AGENTS.md` yêu cầu dừng sau mini plan, nhưng prompt audit yêu cầu thực hiện khảo sát đến khi có báo cáo và dành điểm dừng review cho các nhiệm vụ triển khai sau. Đã trình bày hiểu biết/mini plan trước khi viết tài liệu và tiếp tục audit theo yêu cầu cụ thể này. `STAYHUB_M2_CONTEXT.md` là dữ kiện lịch sử TV2, không đổi vai trò; dòng cấm Git trong file đó được prompt hiện tại cho phép ngoại lệ đối với Git chỉ đọc.

Đây là audit rộng hơn một bước lập trình 30–90 phút. Không triển khai gộp 20 UC. Roadmap bên dưới chia các UC lớn thành bước nhỏ để developer giao riêng sau này.

### 1.2 Tài liệu và code đã khảo sát

Đọc `AGENTS.md` ở root trước các tài liệu dự án; tìm `AGENTS.md` dưới thư mục con không thấy file bổ sung. Đối chiếu các tài liệu theo thứ tự yêu cầu:

| Tài liệu | Kết quả |
| --- | --- |
| `ARCHITECTURE.md` | Có; mô tả mục tiêu phân lớp và nhiều thông tin giai đoạn khởi tạo đã cũ |
| `README.md` | Có; tổng quan, cách chạy, luồng Sanctum và roadmap |
| `docs/ai-frontend-development-guide.md` | Có; service/Axios, mock riêng, tiếng Việt, VND, UI states và contract |
| `docs/stayhub-erd.md` | Có; dùng phần data dictionary và business rules; đoạn ERD bị thiếu/hỏng cấu trúc, xem mục 8 |
| `docs/stayhub-usecase-system.md` | Có; khảo sát phạm vi actor/UC và các quy tắc liên quan |
| `docs/stayhub-team-work-assignment.md` | Có; đặc biệt mục 2, 3, 6, 7, 8–10 |
| `docs/stayhub-screen-mockups.md` | Có; auth/profile/public, Host catalog và Admin management |
| `docs/seed-data.md` | Có; chỉ đối chiếu mô tả, không thực thi hướng dẫn khởi tạo database |
| `STAYHUB_M2_CONTEXT.md` | Có; nhiều thành phần được mô tả không hiện diện trên HEAD này |
| `docs/api/booking-module.md` | **Thiếu**; tìm trong repository không thấy bản thay thế |
| Các API contract tài liệu khác | Không thấy thư mục contract `docs/api`; dùng routes/controllers/requests/resources thật để khảo sát |
| Báo cáo `docs/TV1` có trước | **Chưa có**; hai file của audit là tài liệu mới |

Đọc schema migration, tám model TV1 và enum liên quan, các model liên kết Booking/BookingService/Invoice/Review, UserFactory, luồng DatabaseSeeder và nội dung liên quan trong Member1/2/3 seeders. Đối chiếu routes, bootstrap, auth/Sanctum/CORS config, controllers/services/validators/requests/resources Invoice/Review, cấu hình và test hiện có. Đọc router/layouts, auth context/hooks, shared Axios, các services, public/profile/search/checkout và component liên quan. Không đọc secret hoặc log runtime.

Tham khảo bố cục có chọn lọc từ `docs/StayNest-Frontend-template/src/components/search/FilterSidebar.jsx`, `manager/RoomFormDialog.jsx`, `profile/PersonalInfoCard.jsx`: nhóm bộ lọc, dialog form và lưới thông tin hồ sơ. Template có `basePrice/totalCount`, amenity chuỗi và thuật ngữ riêng; không dùng chúng làm contract StayHub, không sửa/import runtime từ template.

### 1.3 Quy ước trạng thái

- **Đã có code**: đọc thấy implementation, chưa mặc định đã chạy end-to-end.
- **Đã chạy kiểm chứng**: có lệnh, exit code và kết quả trong mục 9.
- **Mới có tài liệu**: được mô tả nhưng không có implementation tương ứng tại HEAD.
- **Stub/mock**: marker tạm hoặc nguồn dữ liệu giả được xác định cụ thể.
- **Chưa có**: không thấy thành phần trong phạm vi code/routes đã kiểm tra.
- **Chưa xác minh**: không có điều kiện kiểm chứng; không suy diễn là lỗi hoặc dữ liệu bị thiếu.

## 2. Kiến trúc và dữ liệu thực tế

### 2.1 Stack và cấu trúc

Monorepo gồm Laravel API trong `backend/`, React SPA trong `frontend/`, tài liệu/template trong `docs/`. Backend `resources/` vẫn là skeleton riêng; frontend chính đi từ `main.jsx` → `BrowserRouter` → `App` → `AuthProvider` → `AppRouter`.

| Thành phần | Khai báo/lock đã đọc | Runtime dùng kiểm chứng |
| --- | --- | --- |
| PHP | `backend/composer.json`: `^8.2` | PHP **8.3.36**, XAMPP |
| Laravel | `backend/composer.lock`: **12.69.3** | Artisan route/test chạy được |
| Sanctum | lock: **4.3.3** | Có route CSRF và `statefulApi()` |
| PHPUnit | lock: **11.5.56** | Chạy qua `php artisan test` |
| React | package khai báo `^19.2.8`; lock: **19.3.0** | Build production thành công |
| React Router DOM / Axios | lock: **7.18.4 / 1.20.0** | Có route guards và shared client |
| Vite / ESLint | lock: **8.3.2 / 10.11.0** | Build/lint thực tế ở mục 9 |
| Node / npm | Không lấy số phiên bản README làm runtime | **22.22.2 / 10.9.7** |
| Database | Migration hướng MySQL; PHPUnit SQLite | Có `pdo_mysql`, `pdo_sqlite`, `bcmath`; MySQL chưa truy vấn |

Lockfile là phiên bản khóa, không thay thế kiểm tra dependency thực tế đã cài cho mọi package. `vendor/autoload.php` và frontend `node_modules` có sẵn, không cài dependency. Frontend scripts có `dev/build/lint/preview`, **không có script test**; không thấy bộ test frontend riêng. Backend có 4 file Unit và 7 file Feature, tập trung Invoice/Review. Không có `backend/tests/TestCase.php`, nhưng các Feature test import trực tiếp `Illuminate\Foundation\Testing\TestCase`, nên việc thiếu file đó không chặn baseline hiện tại.

### 2.2 Luồng đang có và khoảng trống

Auth thực tế: `LoginPage` → `useAuth/AuthContext` → Axios → `GET /sanctum/csrf-cookie` → `POST /login` closure → web session. Khi mount, context gọi `GET /api/me`; logout gọi `POST /logout`. Có regenerate session lúc login, logout/invalidate/regenerate CSRF token lúc logout. Nghiệp vụ auth vẫn trong route closure; chưa có AuthService/Form Request/Resource riêng.

Catalog thực tế: `Home/CuratedStays`, `SearchResultsPage`, `PropertyDetailPage` → `propertyService` → shared Axios hoặc mock được bật rõ trong development. Các API catalog mà service gọi **chưa được đăng ký**. Không có Property/Room CRUD service/controller/policy. Vì thế chưa có luồng catalog thật từ UI đến database.

Invoice/Review đã có phân lớp Controller → Service/Domain Validator → Eloquent và API Resource; Review có Form Requests. Các module này có kiểm tra role/ownership tại controller/validator/query, không phải mọi API đã có middleware role/status hoặc Policy. `AppServiceProvider` chưa thêm binding/gate/module wiring. Không thấy Policy và middleware tùy biến TV1 trong `backend/app`.

Luồng dữ liệu cần giữ khi tích hợp: `User(HOST) → Property → RoomType/Room → Booking → BookingService → Invoice/Review`. Tên quan hệ TV1 hiện được TV3 dùng thật, xem mục 7. Có model và enum Booking, nhưng không có Booking API/AvailabilityService/SearchService trên branch này.

### 2.3 Schema theo migration, chưa phải xác nhận MySQL live

`0001_01_01_000000_create_users_table.php` tạo users/email unique, password reset và sessions. `2026_10_08_000000_create_stayhub_schema.php` bổ sung user fields và tạo các bảng nghiệp vụ còn lại. Có **18 bảng nghiệp vụ** nếu tính users và pivot `room_amenities`, tương ứng **17 model**; số bảng không bằng số model. Không sửa migration khởi tạo đã dùng chung; thay đổi schema tương lai cần migration bổ sung riêng.

| Dữ liệu TV1 | Schema/model hiện có | Khoảng trống cần xử lý ở UC tương ứng |
| --- | --- | --- |
| `users` | Email unique; phone/address/avatar; role/customer default; status/active default; password hashed cast; role/status enum cast | Không có register/profile/admin mutation HTTP; whitelist dữ liệu và status guard chưa có |
| `properties` + `property_images` | host FK; type/status enums; check-in/out time; nhiều ảnh/is_primary | Không có ownership policy, public scope, CRUD/upload hoặc invariant ảnh chính |
| `room_types` | property FK, name/description; **không có status** | Chưa có quy tắc xóa/ngưng dùng; không tự thêm cột/enum |
| `rooms` + `room_images` | property FK, room_type FK, capacity integer, price DECIMAL(12,2), status; ảnh/is_primary | Hai FK riêng không bảo đảm RoomType cùng Property; chưa có validation/CRUD/upload; chưa có unique `(property_id, room_number)` |
| `amenities` + `room_amenities` | status boolean; pivot khóa chính kép `(room_id, amenity_id)` | Chưa có Admin catalog/Host assignment API hoặc kiểm tra amenity đang bật |
| `maintenances` | room FK, start/end date, status enum, date casts | Chưa có validation ngày, query overlap, quản lý lịch, transaction/chống xung đột booking |

Schema/enum khớp nhau ở các trạng thái TV1: `UserStatus=ACTIVE/INACTIVE/BLOCKED`; `PropertyStatus=ACTIVE/INACTIVE/BLOCKED/PENDING`; `RoomStatus=ACTIVE/MAINTENANCE/INACTIVE`; `MaintenanceStatus=SCHEDULED/IN_PROGRESS/COMPLETED/CANCELLED`. Sáu enum nền TV1 vẫn có marker stub. Không suy ra workflow duyệt Property chỉ vì có `PENDING`.

Booking lưu snapshot `price_per_night`, `room_total`, số đêm; BookingService lưu snapshot dịch vụ. FK Booking → Room và Customer dùng `restrictOnDelete`; FK Property/RoomType → Room dùng cascade. Không có SoftDeletes trong model TV1 đã đọc. Các FK giúp tránh một số mất lịch sử nhưng chưa thay thế quy tắc xóa/ngưng dùng ở application; thay đổi property/room_type của phòng có thể ảnh hưởng ownership suy từ quan hệ booking nếu không khóa trường này khi đã có lịch sử. Bằng chứng: migration dòng 35–114, 134–151 và `Room::$fillable`, `InvoiceQueryService::getHostInvoices`.

## 3. Danh mục bằng chứng

Các mã E bên dưới được dùng trong ma trận để giữ bảng ngắn. Đường dẫn và vị trí tương ứng thuộc HEAD ở mục 1. Nhận định “chưa có endpoint/page” dựa cả inventory `rg --files` và route list chạy thực tế, không dựa vào tài liệu cũ.

| Mã | File/vị trí và bằng chứng |
| --- | --- |
| E01 | [backend/routes/web.php](../../backend/routes/web.php), dòng 8 `POST /login`, dòng 14 `Auth::attempt`, dòng 20 session regenerate, dòng 28–32 logout/invalidate/regenerateToken; không status/rate limit |
| E02 | [backend/routes/api.php](../../backend/routes/api.php), dòng 6 `/test`, 13–17 `/me`, cuối file chỉ require billing/reviews; [bootstrap/app.php](../../backend/bootstrap/app.php) `withRouting/statefulApi`, không role/status alias; route list mục 9 |
| E03 | [routes/api/billing.php](../../backend/routes/api/billing.php) dòng 13; [routes/api/reviews.php](../../backend/routes/api/reviews.php) dòng 15–27: routes Invoice/Review, auth Sanctum |
| E04 | [app/Models/User.php](../../backend/app/Models/User.php) dòng 24 fillable role/status, 40 hidden, 50 casts hashed/enum, 63–89 relationships |
| E05 | [migration schema](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php) dòng 14–114 TV1, 134–151 booking/FK/snapshot; [migration users](../../backend/database/migrations/0001_01_01_000000_create_users_table.php) `up` |
| E06 | [app/Models/Property.php](../../backend/app/Models/Property.php) `casts`, `host`, `propertyImages/images`, `roomTypes`, `rooms`, `services`, `reviews` |
| E07 | [app/Models/Room.php](../../backend/app/Models/Room.php) dòng 25 fillable, 41 casts, 53–103 property/roomType/images/bookings/maintenances/amenities |
| E08 | [app/Models/RoomType.php](../../backend/app/Models/RoomType.php) `property/rooms`; [RoomImage.php](../../backend/app/Models/RoomImage.php), [PropertyImage.php](../../backend/app/Models/PropertyImage.php) `fillable/casts` |
| E09 | [app/Models/Maintenance.php](../../backend/app/Models/Maintenance.php) `casts/room`; [Amenity.php](../../backend/app/Models/Amenity.php) `casts/rooms`; chỉ dữ liệu/relationships |
| E10 | [app/Enums](../../backend/app/Enums): sáu enum có `TEMP-M1-STUB`; [Member1DataSeeder.php](../../backend/database/seeders/Member1DataSeeder.php) dòng 18 marker, `run`; [DatabaseSeeder.php](../../backend/database/seeders/DatabaseSeeder.php) `run` |
| E11 | [frontend/src/routes/AppRouter.jsx](../../frontend/src/routes/AppRouter.jsx), dòng 35–81 route tree; [ProtectedRoute.jsx](../../frontend/src/routes/ProtectedRoute.jsx) nhánh loading, guest và allowedRoles |
| E12 | [context/AuthContext.jsx](../../frontend/src/context/AuthContext.jsx) `restoreSession`, mount effect, `login`, `logout`; [hooks/useCustomerAuth.js](../../frontend/src/hooks/useCustomerAuth.js) wrapper |
| E13 | [api/axios.js](../../frontend/src/api/axios.js) `axios.create`; [backend/config/cors.php](../../backend/config/cors.php) paths/origin/credentials; [sanctum.php](../../backend/config/sanctum.php) stateful/guard/middleware |
| E14 | [pages/public/LoginPage.jsx](../../frontend/src/pages/public/LoginPage.jsx) dòng 34 signup mode, `handleSubmit` dòng 88, notice dòng 231 trở đi, button disabled dòng 350 |
| E15 | [services/propertyService.js](../../frontend/src/services/propertyService.js): `getFeaturedProperties` dòng 12, `searchProperties` dòng 41, real search dòng 117, `getPropertyById` dòng 149; mock guard và 404 unavailable |
| E16 | [pages/public/SearchResultsPage.jsx](../../frontend/src/pages/public/SearchResultsPage.jsx) search URL parsing, fetch effect dòng 147–203, pagination dòng 205; [FilterSidebar.jsx](../../frontend/src/components/search/FilterSidebar.jsx) filter groups; [lib/filterConstants.js](../../frontend/src/lib/filterConstants.js) enum-like constants |
| E17 | [pages/public/PropertyDetailPage.jsx](../../frontend/src/pages/public/PropertyDetailPage.jsx) load effect, `selectedRoom`, loading/error/not-found; [RoomsList.jsx](../../frontend/src/components/hotel/RoomsList.jsx), [RoomCard.jsx](../../frontend/src/components/hotel/RoomCard.jsx), [BookingPreviewCard.jsx](../../frontend/src/components/hotel/BookingPreviewCard.jsx) dự toán dòng 35–41 và chuyển checkout |
| E18 | [pages/customer/CustomerProfilePage.jsx](../../frontend/src/pages/customer/CustomerProfilePage.jsx) `useAuth`; [PersonalInfoCard.jsx](../../frontend/src/components/profile/PersonalInfoCard.jsx) hiển thị thông tin; chưa có edit form |
| E19 | [layouts/HostLayout.jsx](../../frontend/src/layouts/HostLayout.jsx) `NAV`; [layouts/AdminLayout.jsx](../../frontend/src/layouts/AdminLayout.jsx) `ADMIN_NAV`; [AdminDashboardPage.jsx](../../frontend/src/pages/admin/AdminDashboardPage.jsx) chỉ heading |
| E20 | [InvoiceQueryService.php](../../backend/app/Services/Invoice/InvoiceQueryService.php) `getHostInvoices/getHostInvoiceDetail/getCustomerInvoices`; [InvoiceCreationValidator.php](../../backend/app/Validators/Invoice/InvoiceCreationValidator.php) `validateHostAuthorization`; [ReviewCreationValidator.php](../../backend/app/Validators/Review/ReviewCreationValidator.php) `validateCustomerAuthorization` |
| E21 | [PublicReviewController.php](../../backend/app/Http/Controllers/Review/PublicReviewController.php) `index` dùng `Property::find` không lọc status; [ReviewQueryService.php](../../backend/app/Services/Review/ReviewQueryService.php) public/host queries; [PublicReviewResource.php](../../backend/app/Http/Resources/Review/PublicReviewResource.php) `toArray/anonymizeName` |
| E22 | [backend/phpunit.xml](../../backend/phpunit.xml) dòng 19–36 SQLite/memory; [backend/tests/Feature](../../backend/tests/Feature) 7 file import framework TestCase, setUp kiểm tra SQLite; [backend/tests/Unit](../../backend/tests/Unit) 4 file |
| E23 | [frontend/package.json](../../frontend/package.json) scripts/dependencies; [eslint.config.js](../../frontend/eslint.config.js); [vite.config.js](../../frontend/vite.config.js); kết quả lint/build mục 9 |
| E24 | [pages/customer/CustomerCheckoutPage.jsx](../../frontend/src/pages/customer/CustomerCheckoutPage.jsx) `loadProperty`, selectedRoom, `roomTotal`; [services/bookingService.js](../../frontend/src/services/bookingService.js) `ENDPOINTS/getMyBookings/getBookingDetail`; [components/hotel/ReviewsSection.jsx](../../frontend/src/components/hotel/ReviewsSection.jsx) `propertySource` guard |

## 4. Ma trận đủ 20 use case TV1

**T0** trong cột tests nghĩa là chưa thấy test trực tiếp cho UC TV1 đó trong 11 file test đã khảo sát; baseline 167 test Invoice/Review PASS không phải nghiệm thu UC này. Có test ownership/role/PII của TV3 dùng model TV1, được ghi riêng. “Chưa có contract” nghĩa là chưa có route backend executable tương ứng; API trong tài liệu chỉ là dự kiến.

| UC | Backend | Frontend | API contract | Tests đã có/đã chạy | Phần thiếu | Dependency/blocker | Bằng chứng |
| --- | --- | --- | --- | --- | --- | --- | --- |
| UC1 Đăng ký | User/schema/hash/email unique có; chưa có register action | `?mode=signup` là notice, vẫn là form login; không tạo tài khoản | Chưa có `POST /register` | T0 | Validation, role/status server mặc định, service, HTTP và form thực | Chốt đăng ký Customer; không suy ra Host self-register từ toggle UI | E01, E02, E04, E05, E14 |
| UC2 Login/logout | Có closures login/logout, `/api/me`, Sanctum; thiếu status guard/throttle/lớp domain auth | Có login, restore, logout, role guard | Contract thật ở mục 5; chưa kiểm chứng cookie browser | T0; tests TV3 xác nhận 401/403 trên API của họ | Login ACTIVE-only, chặn session sau khóa, rate limit, auth service/request/resource, tests | Là nền cho UC có auth; giữ response đang được E12 dùng | E01–E04, E11–E14, E22 |
| UC3 Hồ sơ | Có `/api/me` đọc toàn bộ User sau hidden; chưa có update | Customer profile chỉ đọc; chưa có Host profile page/edit | GET `/api/me`; chưa có PUT `/api/profile` | T0 | Update whitelist, email unique bỏ qua self, password/avatar theo scope, field errors | UC2; role/status không được lấy từ payload | E04, E12, E18 |
| UC4 Danh sách cơ sở | Property/Image model có; chưa có public list query/API | Home/CuratedStays và search có shell, mock tùy cờ | Service gọi GET `/api/public/properties`, backend chưa có | T0 | Active-only query/resource, pagination, adapter DTO/list UI | Có thể dùng dữ liệu nền có sẵn; không bắt buộc chờ Host CRUD | E02, E06, E15, E16 |
| UC5 Chi tiết cơ sở | Model/relations có; public detail chưa có; public reviews thuộc TV3 đã có | `/properties/:propertyId` có gallery/amenities/rooms/reviews, phụ thuộc API/mock | GET `/api/public/properties/{id}` chưa có; `/reviews` có thật | T0; PublicReviewApiTest PASS cho phần TV3 | Detail resource/active-only/ảnh/phòng; chuẩn hóa DTO và lỗi | UC4/shared catalog contract; tích hợp Review hiện có, không viết lại | E06–E09, E15, E17, E21, E24 |
| UC7 Lọc phòng | Chưa có Search/Availability/filter API | Search đang lọc property theo giá/rating/amenity/property type; thiếu room type và kiểm chứng capacity | UI gọi `/api/public/properties/search` chưa có; `/api/public/rooms/search` TV2 mô tả cũng chưa có | T0 | Chốt room vs property results; room type/capacity; truyền ngày/guest; pagination/DTO | Contract TV1/TV2 chưa thống nhất; code TV2 cần tích hợp trước | E02, E15, E16; STAYHUB_M2_CONTEXT mục endpoints |
| UC8 Chi tiết phòng | Room/Image/Amenity model có; chưa có public room endpoint | Có room cards trong PropertyDetail; chưa có page `/rooms/:id` | GET `/api/public/rooms/{room}` chưa có; availability/preview TV2 cũng chưa có | T0 | Room detail API/page, active Property+Room, ngày/guest/preview thật | UC5/shared DTO; availability/preview do TV2 | E07–E09, E11, E17, E24 |
| UC18 Quản lý cơ sở | Chỉ model/schema; chưa service/request/policy/controller | Menu `/host/properties` có nhưng route/page chưa có | Host properties CRUD/status/ảnh mới ở tài liệu | T0 | CRUD, ownership, phân quyền status, ảnh upload/main, UI states | UC2; chốt Host được dùng status nào; storage contract | E02, E05, E06, E08, E11, E19 |
| UC19 Loại phòng | RoomType quan hệ có; không status | Chưa có Host room-type UI | Chưa có route thực thi | T0 | CRUD ownership, chặn xóa loại đang được room dùng, form/list | UC18/property ownership; chốt xóa/ngưng dùng vì schema không status | E02, E05, E08, E11 |
| UC20 Phòng | Room casts/relations có; chưa CRUD/validation | Chưa có Host room list/form | Chưa có route thực thi | T0 | Type cùng Property, capacity/giá/status, bảo toàn lịch sử/ownership | UC18, UC19, UC2; callers TV2/TV3 dùng room.property | E05, E07, E11, E20 |
| UC21 Ảnh phòng | RoomImage có; chưa upload/delete/main transaction | Chưa có quản lý ảnh Host | Chưa có route thực thi | T0 | Upload validation/storage, ownership, main image invariant, UI | UC20; không có cột thứ tự ảnh, không tự bịa ordering contract | E05, E07, E08, E11 |
| UC22 Gán/gỡ tiện nghi | belongsToMany/pivot PK có; chưa assignment action | Chưa có Host assignment UI | Chưa có route thực thi | T0 | Gán/gỡ amenity tồn tại/hợp lệ, ownership, idempotency, UI | UC20; amenity hợp lệ có sẵn hoặc UC46; không giao Host catalog CRUD | E05, E07, E09, E11 |
| UC23 Lịch bảo trì | Model/enum/schema có; chưa quản lý/query overlap | Chưa có maintenance UI | Chưa có route thực thi | T0 | Dates/status/ownership, overlap, booking conflict và khóa đồng thời | UC20, UC51; phối hợp transaction TV2, hiện thiếu code caller | E02, E05, E09, E10, E11 |
| UC40 Quản lý Customer | User role/status có; chưa Admin user query/API | Chưa có Admin user page | GET `/api/admin/users` chỉ ở tài liệu | T0 | Admin-only list/detail/search/pagination và UI; không gộp khóa tài khoản | UC2; chốt phạm vi read/edit từ đặc tả | E02, E04, E11, E19 |
| UC41 Quản lý Host | User↔Property có; chưa Admin Host query/API | Chưa có Admin hosts page | Chưa có route thực thi | T0 | Admin-only Host list/detail/property counts; xác minh quyền tạo/sửa | UC2; không tự triển khai nâng role hoặc Host signup | E02, E04, E06, E11, E19 |
| UC42 Khóa/mở tài khoản | Enum/schema có; chưa mutation lẫn status middleware | Chưa có block/unblock UI | PATCH `/api/admin/users/{user}/status` chỉ ở tài liệu | T0 | Admin mutation, trạng thái đích hợp lệ, chặn session đang có, UI/tests | UC2 status guard; chốt tác động INACTIVE và chống tự khóa/quyền Admin | E01, E02, E04, E05, E10, E11 |
| UC43 Giám sát cơ sở | Property/host/rooms relations có; chưa Admin monitoring | Chưa có Admin property page | GET `/api/admin/properties` chỉ ở tài liệu | T0 | Read/filter/pagination/detail toàn hệ thống; chốt moderation actions | UC2 + dữ liệu catalog; enum PENDING/BLOCKED chưa là workflow duyệt | E02, E05, E06, E11, E19 |
| UC46 Danh mục tiện nghi | Amenity boolean status/pivot có; chưa Admin CRUD | Chưa có Admin amenity page | Admin amenity GET/POST/PUT chỉ ở tài liệu | T0 | Admin-only catalog, validation/status, UI; không xóa mất liên kết | UC2; phối hợp UC22/public metadata | E02, E05, E09, E11 |
| UC51 Kiểm tra bảo trì | Chưa có hàm/query thực thi; chỉ model dates/status | Không cần page riêng | **Chưa có signature/caller/return/error contract**; AvailabilityService TV2 chưa có | T0 | Query strict overlap, status chặn, boundary/error tests, integration seam | Chốt half-open date/status/conflict với TV2 trước tích hợp | E02, E09; phân công mục 3.2; STAYHUB_M2_CONTEXT rules |
| UC52 Kiểm tra sức chứa | Có capacity integer cast, chưa validator kiểm tra khách | Không cần page riêng | **Chưa có signature/caller/return/error contract** | T0 | guest_count nguyên dương, ≤ capacity, invalid input/error tests | TV2 Availability/Booking contract chưa tích hợp; không thay toàn bộ availability bằng capacity | E07; phân công mục 3.2; STAYHUB_M2_CONTEXT rules |

Không UC nào trong bảng được kết luận hoàn tất chỉ dựa trên model, mock hay baseline TV3. UC2 và phần đọc UC3 có code tích hợp với auth endpoints; cần bổ sung nghiệp vụ và test chuyên biệt.

## 5. Routes và contract hiện có

### 5.1 Backend được Artisan xác nhận

`route:list --except-vendor -v` có **13 routes ứng dụng**; bản đầy đủ có **17 routes** gồm 4 route hạ tầng. Chỉ liệt kê quyền đã thấy trong code, không suy từ prefix.

| Method | Endpoint thật | Quyền/bộ kiểm tra hiện có | Response/giới hạn |
| --- | --- | --- | --- |
| POST | `/login` | web session + CSRF của web group; email/password; chưa throttle/status | 200 `{message,user}`; 422 validation/sai credentials |
| POST | `/logout` | web + CSRF; chưa gắn auth guard riêng | 200 `{message}`; logout/invalidate/regenerateToken |
| GET | `/api/me` | `auth:sanctum`; chưa kiểm tra status | 200 `{user}`; User hidden password/remember_token; guest 401 JSON khi Accept JSON |
| GET | `/api/test` | Public | `{success:true,message}` |
| GET | `/api/public/properties/{property}/reviews` | Public, query Form Request; chỉ kiểm tra Property tồn tại | Resource collection `data/links/meta` + `summary`; 404/422; chưa lọc Property status |
| POST | `/api/customer/bookings/{booking}/review` | Sanctum + CUSTOMER trong controller/domain validator + booking ownership | 201 Resource `data`; 401/403/404/409/422 |
| GET | `/api/customer/reviews` | Sanctum + CUSTOMER, query giới hạn customer_id | Collection `data/links/meta` |
| GET | `/api/host/reviews` | Sanctum + HOST + property ownership trong query/request flow | Collection + summary; foreign property filter trả 404 |
| GET | `/api/host/invoices` | Sanctum + HOST trong controller, query qua booking.room.property.host_id | Collection `data/links/meta`; status/search/per_page |
| POST | `/api/host/bookings/{booking}/invoice` | Sanctum + HOST/ownership trong domain validator | 201 Resource `data`; sai định dạng booking ID trả 422; 401/403/404/409/422 |
| GET | `/api/host/invoices/{invoice}` | Sanctum + HOST, query giới hạn ownership | Resource `data`; sai/foreign ID 404 |
| GET | `/api/customer/invoices` | Sanctum + CUSTOMER, query booking.customer_id | Collection `data/links/meta` |
| GET | `/api/customer/invoices/{invoice}` | Sanctum + CUSTOMER + ownership query | Resource `data`; foreign ID 404 |

Bằng chứng: E01–E03, E20–E22; `CustomerInvoiceController::index/show`, `CustomerReviewController::store/index`, `HostReviewController::index`, Form Requests và Resources tương ứng. Các quyền TV3 đã có test PASS; chưa có status guard chung để loại BLOCKED/INACTIVE.

Bốn routes hạ tầng trong bản đầy đủ: `GET /sanctum/csrf-cookie` (Sanctum), `GET /up` (health), `GET /storage/{path}` và `PUT /storage/{path}` (Laravel filesystem). Hai storage routes do framework đăng ký; chưa kiểm thử URL ký/chính sách truy cập/storage live và không coi đây là API upload ảnh TV1.

Chưa có routes register/profile/catalog/admin/Host CRUD/Search/Availability/Booking vận hành. Endpoint lồng có từ `bookings` trong Invoice/Review **không chứng minh Booking module đã được tích hợp**. Không có payment-status mutation trong các billing routes hiện tại; chỉ ghi nhận làm dependency TV3, không triển khai.

### 5.2 Frontend route tree

| Vùng/quyền client | Routes đăng ký | Hiện trạng liên quan audit |
| --- | --- | --- |
| Public | `/`, `/search`, `/properties/:propertyId`, `/login`, `/api-test` | Home/search/detail dùng propertyService; login thật, signup notice; api-test dùng URL sai |
| Development public | `/login-test` | Chỉ đăng ký khi `import.meta.env.DEV` |
| CUSTOMER | `/customer`, `/customer/bookings`, `/customer/bookings/:bookingId`, `/customer/checkout`, `/customer/profile`, `/customer/invoices`, `/customer/invoices/:invoiceId`, `/customer/reviews` | ProtectedRoute role; profile đọc thật, booking có DEV mock; checkout chưa tạo Booking thật |
| HOST | `/host`, `/host/invoices`, `/host/invoices/:invoiceId`, `/host/bookings/:bookingId/create-invoice`, `/host/invoices/create`, `/host/reviews` | ProtectedRoute role; chưa route Host quản lý catalog |
| ADMIN | `/admin` | ProtectedRoute role; index chỉ heading, chưa trang management TV1 |

Không có `/register`, `/properties` list page riêng, `/rooms/:id`, các trang Host property/room-type/room/ảnh/tiện nghi/bảo trì, hoặc Admin users/hosts/properties/amenities. UC4 có thể dùng Home/list hiện có tùy thiết kế chốt, không bắt buộc tạo một route `/properties` chỉ để giống mockup. `HostLayout::NAV` vẫn trỏ `/host/properties` và `/host/bookings` chưa có route đích; Admin menu không đồng nghĩa các trang đã hiện diện. E11/E19.

ProtectedRoute kiểm tra loading/auth/role và giữ returnTo; **không kiểm tra account status**. AuthContext quản lý session tập trung, `useCustomerAuth` là wrapper nên không cần xây auth context thứ hai. Logout xóa client state cả khi request server thất bại: khi lỗi mạng chưa bảo đảm session backend đã hủy; cần UX/test retry trong UC2. Context gọi Axios trực tiếp cho auth, chưa có `authService`; chuyển lớp cần giữ đúng response và caller.

### 5.3 Contract frontend đang chờ backend

| Caller | Request đang viết | Khác biệt/thiếu cần giải quyết |
| --- | --- | --- |
| `propertyService.getFeaturedProperties` | GET `/api/public/properties` | Route chưa có; service trả `unavailable` cho 404, không giả success từ mock |
| `propertyService.searchProperties` | GET `/api/public/properties/search`; params `city,minPrice,maxPrice,minRating,sortBy,amenities,propertyTypes,keyword` | Route chưa có; page truyền thêm dates/guests/rooms nhưng function destructuring **bỏ qua** các field đó |
| `propertyService.getPropertyById` | GET `/api/public/properties/{id}` | Route chưa có; UI kỳ vọng DTO `rooms`, `price`, `photos`, `roomTypeName`… chưa có Resource contract tương ứng |
| `SearchResultsPage` | Query URL `startDate,endDate,roomsCount,adults,children`; client pagination size 10 | Chưa ánh xạ `check_in_date/check_out_date/guest_count` của mô tả TV2; chưa dùng server metadata |
| `FilterSidebar/filterConstants` | Amenity slug `wifi/pool/...`; PropertyType `Resort/Hotel/Villa` | Schema amenity dùng ID; PropertyType chỉ `HOTEL/HOMESTAY`. Property type khác room type; chưa có room-type filter |
| `BookingPreviewCard/RoomCard` | Tính dự toán trong UI từ `room.price`; chuyển checkout | Không gọi availability/preview thật, không xác nhận giá cuối cùng/phòng trống; UC8 cần dùng API TV2 khi có |
| `bookingService.getMyBookings/getBookingDetail` | `/api/customer/bookings[...]`; DEV mock có cờ riêng | Route backend chưa có; không tự viết lại Booking TV2 trong audit/UC catalog |

Mock property chỉ bật khi **DEV và `VITE_ENABLE_PROPERTY_MOCKS === 'true'`**; booking tương tự với `VITE_ENABLE_BOOKING_MOCKS`. Production gọi API thật và không fallback mock khi lỗi. Chưa đọc giá trị các cờ local; không khẳng định chúng đang bật. `ReviewsSection` nhận `propertySource` và chặn dùng mock property ID để gọi review thật. Điểm này cần giữ khi thay adapter (E15, E24).

Các UI đã có utility responsive, tiếng Việt và `Intl.NumberFormat('vi-VN', VND)` ở component tiền. Search/detail/profile có nhánh loading/error/empty hoặc not-found; login có pending/disabled và thông báo. Chưa kiểm chứng hiển thị trình duyệt; chưa coi tất cả 401/403/404/409/422 đã hiển thị đúng: catalog service chủ yếu đặc biệt hóa 404, login lấy lỗi email/message chung, chưa có form TV1 mutation để kiểm chứng field errors/success. Không thấy `dangerouslySetInnerHTML` hoặc import runtime từ `docs/` trong frontend source qua tìm kiếm.

Wrapper thực tế không đồng nhất: auth `{message,user}`/`{user}`, test `{success,message}`, business Resources `{data}` và collections `{data,links,meta}`; public/host reviews có summary. Không ép đổi sang một wrapper mới chỉ để khớp tài liệu phân công. Các status 401/403/404/409/422 của Invoice/Review đã có test; chưa kiểm chứng các mã đó cho UC TV1 còn thiếu.

## 6. Phát hiện ưu tiên và cách xử lý đề xuất

P1 = ưu tiên trước khi mở chức năng cho người dùng; P2 = thiếu chức năng/contract hoặc cần chốt trước UC; P3 = chất lượng/tài liệu. Đây là kết quả audit, **không sửa trong lượt này**.

| ID / mức | Phát hiện và bằng chứng | Tác động | Hướng xử lý trong nhiệm vụ sau |
| --- | --- | --- | --- |
| F01 / P1 | E01 login chỉ `Auth::attempt(email,password)`; E02/E03 không status middleware; E04 enum status chỉ là cast; E20 chỉ kiểm role/ownership | Code không chặn BLOCKED/INACTIVE lúc login và các request của session đã có. Tài khoản bị khóa vẫn có thể tới API phù hợp role/ownership; kết luận từ code, chưa thử bằng tài khoản live | UC2 xây status guard chung, tests login/restore/API sau khóa; UC42 bổ sung mutation. Phối hợp TV3 giữ role/ownership hiện có |
| F02 / P1 | E01/E02 không throttle middleware hoặc RateLimiter logic cho login; route verbose xác nhận chỉ web group | Chưa có cơ chế app chống thử mật khẩu hàng loạt được thấy trong repo; reverse proxy/deployment chưa xác minh | Rate limit theo contract đã chốt, test vượt ngưỡng và reset; UI thông báo 429 trong UC2 |
| F03 / P2 | E02, E11, E15–E17: public UI gọi routes catalog chưa có; E19 menu Host có route đích thiếu | Không chạy được luồng public catalog thật; menu quản lý dẫn đến trang không khớp route | UC4/5/8 và UC18–23 hoàn thiện từng vertical slice, giữ trạng thái unavailable rõ |
| F04 / P2 | E15 search bỏ ngày/guest; E16 lọc property thay vì room, hằng filter khác enum/schema | Có giao diện tìm theo ngày nhưng chưa kiểm chứng availability/capacity; nếu giữ contract này sẽ lệch Search TV2 | Chốt UC7 với TV2, tích hợp implementation/contract có thật, dùng một Search/Availability; không tạo engine thứ hai |
| F05 / P1 trước CRUD | E05 rooms có 2 FK độc lập, capacity integer không domain validator; E07 chỉ fillable/casts | Chưa có invariant RoomType cùng Property/capacity dương ở application. Chưa phải exploit HTTP vì API CRUD chưa có | UC20 scoped exists/type ownership, capacity/price/status validation; UC52 kiểm tra guest boundary. Không sửa migration dùng chung |
| F06 / P1 trước CRUD | E05 FK cascade/restrict; E20 ownership hóa đơn đi qua room.property; Room cho phép property_id/room_type_id fillable | Xóa hoặc chuyển cha không được thiết kế có thể mất liên kết/dẫn tới đổi quyền xem lịch sử; chưa có đường mutation hiện tại | Chốt ngưng dùng thay xóa dữ liệu có lịch sử; hạn chế reparent Room và cross-Host Property; test rollback/ownership/history trong UC18–20 |
| F07 / P1 trước maintenance | E09 chỉ model; không query/validator/locking/caller Availability trong inventory | Chưa ngăn lịch bảo trì giao nhau với booking giữ phòng hoặc 2 mutation đồng thời | UC51 + UC23 phối hợp khóa Room với TV2; tests overlap/adjacent/status/conflict, kiểm thử concurrency trên MySQL tách biệt |
| F08 / P2 | E21 PublicReviewController chỉ `Property::find` trước public reviews | Khi catalog ACTIVE-only được thêm, review endpoint vẫn có thể trả review của Property INACTIVE/BLOCKED/PENDING theo code hiện tại | Chốt chính sách public với TV3, tạo thay đổi riêng thuộc TV3 khi được giao; public Resource đã ẩn email/phone/booking code và có tests PASS |
| F09 / P2 | E04 `$fillable` chứa role/status; chưa register/profile action. E14 toggle role chỉ là UI, request login không gửi role | Chưa chứng minh được mass-assignment nâng quyền qua HTTP; nhưng tạo register/profile bằng `$request->all()` sau này sẽ gây rủi ro | UC1/3 chỉ nhận validated whitelist, backend đặt role/status; tests payload role=ADMIN/HOST, status và customer_id. Không gán mọi trường fillable cho request |
| F10 / P2 | E12 logout catch bỏ lỗi rồi clear local user | UI đã về guest không có nghĩa server session đã hủy nếu request lỗi mạng | UC2 kiểm thử lỗi logout/retry/thông báo phù hợp; xác minh cookie/session thật trong browser |
| F11 / P2 | E13 CORS có localhost:5173 + credentials; mặc định SANCTUM_STATEFUL_DOMAINS không liệt kê rõ 5173 | Chưa kết luận cấu hình local lỗi vì không đọc env; browser SPA flow chưa được chứng minh bởi feature tests actingAs | Kiểm tra tên biến `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `VITE_BACKEND_URL` trong nhiệm vụ cấu hình được phép; không in giá trị nhạy cảm |
| F12 / P3 | E23 lint 3 warning Fast Refresh; build chunk ~1,050.66 kB trước gzip | Baseline pass nhưng còn cảnh báo chất lượng/kích thước; không phải lỗi mới của audit | Chia task tối ưu riêng khi được giao; không thay UI primitives hay routing trong audit |
| F13 / P3 | `ApiTestPage.jsx` dòng 10 gọi `/test`, E02 route thật `/api/test` | Trang kiểm tra kết nối có thể báo lỗi dù Laravel hoạt động | Sửa endpoint trong task hạ tầng frontend được giao riêng |
| F14 / P2 | E17 giá dự toán frontend; E24 checkout cũng nhân số đêm với giá property data, không gọi preview | Chưa có authoritative quote hoặc mutation booking; tiền dự toán không được coi là tổng cuối cùng | UC8/UC7 tích hợp preview TV2; phép tính cuối và snapshot ở backend; không tự triển khai Booking |
| F15 / P2 | E08/E09 chỉ model; chưa có upload, primary-image constraint, amenity assignment authorization | Chưa có kiểm chứng an toàn upload/ownership/main image; không thể nghiệm thu UC21/22 bằng relationships | UC18/21 upload validation và storage contract; UC22 gán/gỡ room-owned; Admin CRUD chỉ ở UC46 |

Password đã có hashed cast và User hidden password/remember_token (E04); email unique ở migration users. Session regeneration/invalidation đã có (E01). Không đánh đồng các điểm đã có này với auth hoàn chỉnh. Không thấy signup/profile HTTP mutation nên chưa có bằng chứng endpoint tự cấp ADMIN/HOST; UC1/3 phải giữ whitelist. Production debug/stack trace policy phụ thuộc cấu hình chưa xác minh; không kết luận đã an toàn chỉ vì bootstrap chưa custom exception handler.

### 6.1 UC51/UC52: contract nội bộ chưa hiện diện

**Hiện trạng:** không có signature, implementation, caller `AvailabilityService`, định dạng return hoặc exception hiện hành để bảo toàn trên HEAD này. `Maintenance::casts/room`, `Room::capacity/maintenances` và `BookingStatus::holdsRoom/holdingValues` là nền dữ liệu, không phải hàm UC51/52. Không có test trực tiếp tương ứng.

**Đề xuất để TV1/TV2 chốt, chưa phải API/hàm đã tồn tại:**

| UC | Hướng interface nội bộ | Quy tắc và kiểm thử cần có |
| --- | --- | --- |
| UC51 | Query nhận Room/roomId + check-in/check-out đã chuẩn hóa; kết quả boolean có lịch chặn hoặc collection xung đột, chọn một contract duy nhất với TV2 | `start_date < requested_check_out AND end_date > requested_check_in`; hai khoảng liền kề không giao; loại COMPLETED/CANCELLED theo ngữ cảnh TV2, chốt SCHEDULED/IN_PROGRESS; ngày sai/room không tồn tại phải có lỗi rõ |
| UC52 | Validator nhận capacity/Room + guest_count; chọn boolean hay exception domain theo caller TV2, không tự thêm endpoint | Guest nguyên dương, 1 và đúng capacity hợp lệ; 0/âm/phân số/chuỗi không hợp lệ theo HTTP contract; capacity+1 bị từ chối; đủ capacity không thay thế status/booking/maintenance checks |

UC23 phải kiểm tra cả lịch bảo trì với các booking giữ phòng (`PENDING/CONFIRMED/CHECKED_IN`) và cạnh tranh giữa tạo Booking/tạo maintenance. Chỉ query trước rồi insert chưa đủ; cần cùng giao thức transaction/lock Room ở hai luồng TV1/TV2, thứ tự khóa nhất quán. SQLite baseline không chứng minh được `lockForUpdate` trên MySQL. Chốt date interval trước, không tự quy ước ngày bảo trì kết thúc là inclusive để khác availability đang mô tả half-open.

## 7. Ma trận stub và dependencies

### 7.1 Marker tìm thấy thật

Tìm `TEMP-M1-STUB`, `TEMP-M2-WIRING`, `TEMP-M3-STUB`, `TODO` trong app/bootstrap/routes/database/tests/frontend source; không dựa vào danh sách cũ trong M2_CONTEXT.

| Thành phần | Marker/trạng thái thật | Caller/contract phụ thuộc và cách bảo toàn |
| --- | --- | --- |
| `app/Enums/UserRole.php`, `UserStatus.php` | TEMP-M1-STUB dòng 2 | User casts; Invoice/Review controllers/validators, tests và Member1 seeder; giữ namespace, enum values, kiểu enum khi cast |
| `app/Enums/PropertyStatus.php`, `PropertyType.php` | TEMP-M1-STUB dòng 2 | Property casts; seeders/tests TV3; ACTIVE/INACTIVE/BLOCKED/PENDING và HOTEL/HOMESTAY hiện đã có schema tương ứng |
| `app/Enums/RoomStatus.php` | TEMP-M1-STUB dòng 2 | Room cast và tests/seeders; giữ ACTIVE/MAINTENANCE/INACTIVE |
| `app/Enums/MaintenanceStatus.php` | TEMP-M1-STUB dòng 2 | Maintenance cast/Member1 seeder; TV2 caller chỉ ở tài liệu, chưa có code |
| `database/seeders/Member1DataSeeder.php` | TEMP-M1-STUB dòng 18 | `DatabaseSeeder::run` gọi trước Member2/3; các seeder sau dùng ID/quan hệ nền cố định; không thay/seed lại trong audit |
| `database/seeders/Member3DataSeeder.php` | TEMP-M3-STUB dòng 11 | Demo Invoice/Review/FAQ/chat; quyền TV3, không thay |
| `TEMP-M2-WIRING` | Không tìm thấy trong code khảo sát | Không có role alias/booking route wiring để thay tại HEAD này |
| `TODO` liên quan TV1 | Không có match trong các nguồn được tìm | Thiếu marker không đồng nghĩa UC đã hoàn tất; dùng inventory/routes làm bằng chứng |

### 7.2 Thành phần tài liệu nói là stub nhưng HEAD không khớp

Tám model TV1 **có file nhưng không có TEMP-M1-STUB marker**. Không gọi chúng là stub chỉ vì M2_CONTEXT nói vậy; đã có fillable/casts/relationships dùng thật. Chưa có CheckRole, ApiResponse trait, Amenity/Property/Room/RoomType/Maintenance factories được liệt kê trong M2_CONTEXT; chỉ có `UserFactory`. `routes/api/booking.php`, SearchService, AvailabilityService, BookingPolicy và các controllers nghiệp vụ TV2 không hiện diện. Không có phần wiring này không có nghĩa dữ liệu MySQL thiếu bảng.

### 7.3 Contract liên kết cần giữ

| Bên sử dụng | Dependency TV1 đang có / đang thiếu | Bằng chứng và ảnh hưởng |
| --- | --- | --- |
| TV3 Invoice creation | User role/id; Booking.customer/room; Room.property; Property.host_id/name; customer contact | `InvoiceCreationValidator::validateHostAuthorization/validateSnapshots`, `InvoiceService::createInvoice`; thay quan hệ/ownership sẽ làm hỏng kiểm tra/quyền/snapshot |
| TV3 Invoice query | `booking.room.property.host_id`, `booking.customer_id` | `InvoiceQueryService::getHostInvoices/getHostInvoiceDetail/getCustomerInvoices`; không đổi room parent tùy tiện |
| TV3 Review create | UserRole CUSTOMER; Booking COMPLETED/customer_id; `booking.room.property_id` | `ReviewCreationValidator::validateCustomerAuthorization/validatePropertyAssociation`; tests PASS |
| TV3 Review query | `Review.property/customer/booking.room`; Property.host_id | `ReviewQueryService::getHostReviews/getHostReviewSummary`, PublicReviewController; cần thống nhất public status |
| TV2 Search/Availability/Booking | Tài liệu yêu cầu Room ACTIVE + Property ACTIVE + capacity + maintenance overlap + booking overlap | M2_CONTEXT và phân công mục 4; **chưa có caller executable/contract file**. Yêu cầu developer cung cấp branch/dependency đã tích hợp; chưa xác định commit cần merge từ code hiện tại |
| Seeders TV2/TV3 | IDs Room/Property/User/RoomType/Amenity và lịch tương đối của Member1 | DatabaseSeeder thứ tự Member1 → Member2 → Member3; không tái seed để kiểm chứng audit |
| Frontend catalog/booking | DTO room/property và auth session dùng chung; DEV mock flags | E12/E15/E17/E24; không tự đổi snake_case/camelCase hoặc wrapper mà thiếu adapter/caller test |

Không thay stub hàng loạt, không đổi enum/namespace/relationship hoặc xóa dữ liệu nền. Việc tích hợp branch/commit là nhiệm vụ developer chuẩn bị, không được audit tự merge hoặc viết lại TV2.

## 8. Mâu thuẫn cần chốt và lựa chọn kỹ thuật

### 8.1 Cần developer/nhóm thống nhất trước phần triển khai bị ảnh hưởng

| Chủ đề | Nguồn khác nhau | Quyết định cần chốt |
| --- | --- | --- |
| Vai trò/điểm dừng | AGENTS TV3/feature-invoice; prompt hiện tại TV1/audit đến báo cáo | Audit áp dụng prompt cụ thể; các UC tiếp theo review mini plan theo AGENTS, không mặc định được sửa mọi file TV1 |
| UC7 / Search ownership | Phân công mục 3 giao UC7 TV1, mục 4 giao Search/Availability và filter UI TV2; UI hiện gọi property search | Chủ sở hữu endpoint search và adapter filter/DTO; dùng lại một engine TV2 sau tích hợp; chốt room-type/capacity/amenity semantics |
| Tài khoản | Phân công liệt kê ACTIVE/BLOCKED; migration/UserStatus có thêm INACTIVE | Ý nghĩa INACTIVE, điều kiện login/restore, các transition khóa/mở và quyền Admin; không xóa enum value |
| Property moderation | Phân công chỉ ACTIVE/INACTIVE; ERD và schema/enum có BLOCKED/PENDING | Host được set gì, Admin có duyệt/chặn không và trường nào bất biến; UC43 read-only trước nếu chưa có workflow |
| RoomType ngưng dùng | UC19 nói xóa/ngưng sử dụng; schema/model/ERD không có status | Chặn xóa khi đang được room dùng hay migration bổ sung được duyệt; không tự thêm status |
| Host account lifecycle | Login UI có context Host/signup notice; use case không đặc tả rõ self-register Host hoặc Admin tạo Host | UC1 mặc định Customer theo prompt; UC41 cần đặc tả rõ tạo/sửa/nâng quyền trước mutation |
| Maintenance | Công thức overlap strict đã có trong tài liệu; trạng thái chặn/booking conflict/đồng thời chưa có code | Chốt interval/status, create/update conflict policy và lock contract với TV2 |
| Public reviews của cơ sở tắt | TV1 public active-only; TV3 endpoint reviews chỉ `Property::find` | Có còn công khai review hay trả 404 khi Property không ACTIVE; yêu cầu TV3 thay đổi riêng nếu cần |
| Check-out/invoice | ARCHITECTURE mục 8.5 mô tả gộp; Use case mô tả check-out rồi tạo; M2_CONTEXT nói check-out không tạo Invoice; TV3 hiện đòi COMPLETED | Giữ current InvoiceService contract khi audit; xác minh workflow TV2 thực tế sau tích hợp, không sửa theo suy đoán |
| Invoice status ngoài TV1 | Phân công enum gợi ý CANCELLED; schema/InvoiceQueryService có REFUNDED | Bàn giao cho TV3/integrator; không đổi trạng thái để vừa tài liệu |

### 8.2 Có thể giải quyết bằng đối chiếu kỹ thuật, không cần chặn audit

- Branch thực tế là `feature/member-1-catalog`, không phải `feature/invoice` trong AGENTS. Dùng HEAD/Git hiện tại.
- ARCHITECTURE phần 4.2/7.1 nói mới có User và schema nền; HEAD đã có 17 model, migration nghiệp vụ và module Invoice/Review. Đây là tài liệu cũ, không phải thiếu code theo tài liệu.
- M2_CONTEXT nói 79 rồi 83 tests/18 bảng live ở lần kiểm tra cũ; audit mới có **167 tests/664 assertions** trên SQLite. Không dùng số cũ, không suy ra đã có Search/Booking API ở branch này.
- 18 bảng nghiệp vụ và 17 model không mâu thuẫn: pivot room_amenities không có model riêng. Hạ tầng Laravel thêm các bảng khác, không gọi tổng DB là đúng 18.
- Không có Unit directory là trạng thái cũ trong AGENTS: `backend/tests/Unit` hiện có 4 file. Không có Tests\TestCase riêng vẫn chạy được vì Feature test kế thừa framework TestCase.
- `docs/stayhub-erd.md` đoạn Mermaid mở đầu có field không nằm trong entity và phần Invoice/data dictionary thiếu đoạn; không đủ làm schema source. Dùng migration/model, báo lại việc sửa tài liệu trong task riêng.
- DTO frontend `price/photos/roomTypeName`, hằng property types và amenity slugs chưa là API contract. Có thể dùng adapter sau khi backend Resource được chốt; không đổi schema để khớp mock/template.
- Bố cục template là tham khảo; Room vật lý StayHub không có `totalCount` như template. Dùng page/component hiện có phù hợp theme; không thêm inventory model của template.
- Runtime Node/npm/PHP khác README không tự là lỗi; các lệnh kiểm chứng thực tế chạy được. Không nâng dependency chỉ để khớp mô tả.

## 9. Baseline đã chạy

### 9.1 An toàn trước test

Đọc `backend/phpunit.xml`: APP_ENV testing, DB_CONNECTION sqlite, DB_DATABASE `:memory:`, DB_URL rỗng, session/cache array. Không có cached `backend/bootstrap/cache/config.php`, không có `.env.testing`. Kiểm tra test source không override sang MySQL hoặc dùng connection local riêng; Feature setUp kiểm tra env trước boot và driver/database sau boot. Framework RefreshDatabase có thể migrate trong `parent::setUp`, nên không chỉ dựa vào guard sau boot: đã cố định env trước khi chạy và xác nhận không có config cache. Có `pdo_sqlite`, dependency đã cài; không đọc/in env local.

Các lệnh Artisan bên dưới chạy với env tạm trong process PowerShell:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:LOG_CHANNEL='null'
```

Không lưu các giá trị này vào file cấu hình. Không chạy `composer test` vì script đó có bước config:clear ngoài nhu cầu audit; chạy trực tiếp Artisan. Không chạy seeder demo. `php artisan test` dùng RefreshDatabase trong database tạm riêng để kiểm thử, không làm mới MySQL local.

### 9.2 Commands, exit code và kết quả

| Thư mục | Command | Exit code | Kết quả thực tế |
| --- | --- | --- | --- |
| root | `git status --short`, `git branch --show-current`, `git rev-parse --show-toplevel`, `git rev-parse HEAD` | 0 | Snapshot mục 1; đầu lượt sạch |
| backend | `php artisan route:list -v --except-vendor --no-ansi` | 0 | 13 routes ứng dụng; middleware như mục 5 |
| backend | `php artisan route:list --no-ansi` | 0 | 17 routes gồm Sanctum/health/storage |
| backend | `php artisan test --no-ansi` | **0** | **167 passed, 664 assertions, 82.39 giây**; 4 Unit + 7 Feature classes |
| frontend | `npm.cmd run lint` | **0** | **0 errors, 3 warnings** `react-refresh/only-export-components` |
| frontend, sandbox | `npm.cmd run build` | **1** | Build config lỗi `spawn EPERM`, kèm lỗi nạp native Tailwind dependency; chưa kết luận source lỗi |
| frontend, ngoài sandbox sau cấp quyền | `npm.cmd run build` | **0** | **Build PASS**, 3299 modules transformed, 46.79 giây; output dist được ignore |

Lint warnings: `frontend/src/components/ui/badge.jsx:25`, `button.jsx:38`, `sidebar.jsx:536`. Build warning: JS chunk `index-*.js` **1,050.66 kB** (gzip **293.00 kB**) vượt ngưỡng 500 kB; có diagnostic thời gian plugin. Không sửa warning, không cài lại native dependency; lần chạy ngoài sandbox chứng minh build được trong môi trường được cấp quyền.

Bốn Unit classes: InvoiceCalculationServiceTest, InvoiceCalculationValidatorTest, InvoiceCreationValidatorTest, ReviewCreationValidatorTest. Bảy Feature classes: HostInvoiceApiTest, InvoiceCreationTest, InvoiceQueryApiTest, CustomerReviewApiTest, HostReviewApiTest, PublicReviewApiTest, ReviewServiceTest. Chúng kiểm tra tính tiền, workflow invoice/review, role/ownership, privacy, pagination và rollback; **không có bộ test auth/catalog/maintenance/capacity TV1**.

MySQL schema/counts, demo login, CSRF browser round-trip, responsive/a11y, endpoint catalog, race-condition MySQL: **CHƯA CHẠY** do audit không dùng dữ liệu local, chưa có implementation tương ứng hoặc chưa mở browser. Không kết luận database thiếu bảng. Backend test PASS không xác nhận tính đúng MySQL ENUM/FK locking, CORS hoặc session cookie production.

## 10. Roadmap 20 UC — đề xuất cho prompt sau

Developer chuẩn bị từng branch từ nền đã có dependencies; không tự chuyển branch hoặc merge. `backend/routes/api.php`, `backend/bootstrap/app.php`, `frontend/src/routes/AppRouter.jsx` và shared Axios là file tích hợp dùng chung: prompt triển khai cần nêu rõ quyền sửa nếu cần. Schema khởi tạo, seeder, auth config và model module khác không tự mở rộng phạm vi.

Trong cột file, `BE=backend/`, `FE=frontend/src/`; đường dẫn mới là **dự kiến**, cần chốt danh sách cụ thể trong mini plan từng UC. Mỗi UC có UI cần API/service/page/validation/authorization, loading/empty/error/success, chống submit lặp và lint/build; baseline test mới phải bao phủ success/validation/quyền. Các UC lớn chia bước 30–90 phút trên cùng branch; không báo hoàn thành khi thiếu UI bắt buộc.

Ưu tiên **UC2 trước** để bảo vệ session và callers TV3 đã có, rồi UC1/UC3. Có thể làm public catalog độc lập trên schema/data đã có. UC51/52 nên chốt/tích hợp cùng TV2 trước khi nghiệm thu UC7/UC23; không đợi toàn bộ Host CRUD mới đọc public catalog.

| Thứ tự / UC | Branch gợi ý | Dependency bắt buộc | Phạm vi file dự kiến | Acceptance criteria sơ bộ |
| --- | --- | --- | --- | --- |
| 1 / UC2 | `feature/tv1-uc02-auth` | User/schema hiện có; xác nhận tích hợp TV3 | BE Auth Controller/Request/Service/Resource, role/status middleware, routes/web + auth module, bootstrap wiring được phép, tests; FE authService/context/LoginPage/ProtectedRoute | Login ACTIVE-only, session regenerate/restore/logout, rate limit, chặn tài khoản bị khóa sau login; giữ response hoặc adapter caller; guest/wrong role/status tests; browser CSRF/session. Chia auth refactor và status/rate-limit/UX trong cùng UC |
| 2 / UC1 | `feature/tv1-uc01-register` | UC2 nền auth; chốt Customer-only | BE Auth Request/Service/controller/routes/tests; FE LoginPage hoặc Register component + authService | Email unique/hash, không nhận role/status do client, đăng ký thật, 422 từng field, chống submit lặp, success/login policy được chốt |
| 3 / UC3 | `feature/tv1-uc03-profile` | UC2; scope profile của từng role | BE Profile Request/Service/controller/resource/route/tests; FE profileService/CustomerProfilePage/edit components | Cập nhật đúng self, email unique bỏ qua self, không sửa role/status; quyền/field errors/pending/success; avatar/password chỉ khi scope được giao |
| 4 / UC4 | `feature/tv1-uc04-property-list` | Property/Image schema/data; không cần UC18 CRUD | BE PublicPropertyController/Query/Resource/catalog route/tests; FE propertyService/CuratedStays/list components | Chỉ ACTIVE, ảnh/giá theo DTO đã chốt, pagination/empty/error thật, DEV mock được phân biệt; contract Resource được tài liệu hóa |
| 5 / UC5 | `feature/tv1-uc05-property-detail` | UC4/shared DTO; Review TV3 đang có | BE detail query/resource/route/tests; FE PropertyDetailPage/gallery/amenities/rooms adapter | Property không public → 404 theo contract; không lộ room inactive; gallery/room data thật; reviews dùng module TV3 và xử lý propertySource |
| 6 / UC52 | `feature/tv1-uc52-capacity-check` | Room.capacity; thống nhất input/error với TV2 | BE Validators/Property hoặc Room helper được chốt, Unit tests; tài liệu contract | Nguyên dương, đúng capacity/biên, từ chối vượt/0/âm/phân số; không tự kết luận available; không cần UI/HTTP riêng |
| 7 / UC51 | `feature/tv1-uc51-maintenance-check` | Maintenance dates/status; contract TV2 | BE Query/Service Maintenance theo cấu trúc chốt, tests; tài liệu integration | Strict overlap, adjacent không chặn, status terminal bỏ qua theo rule đã chốt, invalid date; TV2 có thể gọi mà không cần page |
| 8 / UC7 | `feature/tv1-uc07-room-filters` | UC51/52; **Search/Availability TV2 thực sự được tích hợp**, contract endpoint/DTO | FE searchService/property adapter/SearchResultsPage/FilterSidebar/constants; BE chỉ seam TV1 hoặc phần filter được phân công, tests | Một Search/Availability; truyền ngày/guest, room type, giá, amenity IDs; ACTIVE-only; server pagination; không dùng mock/template semantics làm API |
| 9 / UC8 | `feature/tv1-uc08-room-detail` | UC5; Room/Image/Amenity; TV2 availability/preview | BE PublicRoom query/resource/catalog route/tests; FE roomService/RoomDetailPage/router/preview adapter | Room+Property public, 404 đúng, ảnh/amenity/capacity/giá thật; preview backend, availability lỗi rõ; không tự tạo Booking |
| 10 / UC18 | `feature/tv1-uc18-property-management` | UC2 + ownership; status/ảnh contract | BE Property service/Requests/Policy/controllers/resources/routes/tests; FE HostPropertiesPage/form/propertyService/upload | Host chỉ quản lý own, không nhận host_id tùy ý/status vượt quyền; bảo toàn history; ảnh thuộc Property và main image nhất quán; list/form thật. Chia CRUD, ảnh, tích hợp UI/tests |
| 11 / UC19 | `feature/tv1-uc19-room-types` | UC18; quyết định ngưng dùng/xóa | BE RoomType service/Requests/Policy/routes/tests; FE roomTypeService/list/form | Type thuộc Property own; chống cross-host; chặn xóa đang được room dùng theo rule chốt; không tự thêm status |
| 12 / UC20 | `feature/tv1-uc20-rooms` | UC18/19/2; snapshot/history contract TV2/3 | BE Room service/Requests/Policy/resources/routes/tests; FE roomService/HostRoomsPage/form | Type cùng Property, capacity nguyên dương, decimal giá hợp lệ, status hợp lệ; không đổi ownership/lịch sử trái phép. Chia data/authorization và UI/integration |
| 13 / UC21 | `feature/tv1-uc21-room-images` | UC20; storage contract | BE RoomImage service/upload Request/policy/actions/routes/tests; FE gallery/upload manager | MIME/size/file validation, room ownership, lỗi upload rõ, không nhiều main image khi cạnh tranh; xóa file/DB nhất quán theo chính sách |
| 14 / UC46 | `feature/tv1-uc46-amenity-catalog` | UC2 ADMIN; schema/pivot hiện có | BE Amenity service/Requests/admin controller/resources/routes/tests; FE amenityService/AdminAmenitiesPage/form | ADMIN-only danh mục chung, bật/tắt/validation, giữ liên kết Room; Host bị từ chối CRUD; UI states đầy đủ |
| 15 / UC22 | `feature/tv1-uc22-room-amenities` | UC20; UC46 hoặc danh mục hợp lệ có sẵn | BE room-amenity assignment Request/service/policy/routes/tests; FE room amenity component/service | Host chỉ gán/gỡ own room, amenity tồn tại/được phép, duplicate/idempotency, không có quyền CRUD danh mục chung |
| 16 / UC23 | `feature/tv1-uc23-maintenance-management` | UC20/51; TV2 booking/locking integration; rule xung đột chốt | BE Maintenance service/Requests/Policy/controller/routes/tests; FE maintenanceService/list/form | Ngày/status/ownership hợp lệ, adjacent/status terminal, booking holds conflicts, chung lock Room; UI field/conflict states. Chia query/transaction và UI; concurrency test trên DB test riêng |
| 17 / UC40 | `feature/tv1-uc40-customers` | UC2 ADMIN, User schema | BE AdminCustomer query/Request/controller/resource/routes/tests; FE adminUserService/AdminCustomersPage | Admin list/detail/filter/pagination chỉ Customer; tránh dữ liệu nhạy cảm; không gộp status mutation UC42 |
| 18 / UC41 | `feature/tv1-uc41-hosts` | UC2; quyết định quyền tạo/sửa Host; Property data | BE AdminHost query/controller/resource/Request khi cần/routes/tests; FE adminHostService/AdminHostsPage | Admin-only Host list/detail/property counts; quyền mutation đúng đặc tả được chốt, không tự nâng role tùy payload |
| 19 / UC42 | `feature/tv1-uc42-account-status` | UC2 status middleware; quản lý user mục tiêu | BE AdminAccountStatus Request/service/controller/routes/tests; FE status actions/adminUserService | Chỉ ADMIN khóa/mở theo transition chốt; session đã login bị chặn ngay request sau; INACTIVE/self/admin policy rõ; confirmation/pending/error/success |
| 20 / UC43 | `feature/tv1-uc43-property-monitoring` | UC2 ADMIN; Property/host/room data; moderation rule nếu mutation | BE AdminProperty query/Request/controller/resource/routes/tests; FE AdminPropertiesPage/service | Admin nhìn toàn hệ thống với filter/pagination/detail; Host/Customer bị từ chối; read-only trừ moderation được giao rõ; không tự thêm duyệt |

Thứ tự UC40/41/42 có thể kéo lên ngay sau UC2 nếu ưu tiên vận hành account; UC42 vẫn cần nền status guard của UC2, không cần chờ hoàn tất catalog. UC22 không bị bắt buộc chờ UC46 nếu có danh mục hợp lệ. Không xác định được commit TV2 cần tích hợp chỉ từ M2_CONTEXT; developer cung cấp branch nền chứa code thật rồi audit lại contract trước UC7/UC23.

## 11. Bàn giao và việc kế tiếp

**Prompt 00 hoàn tất phần khảo sát/tài liệu**, không phải hoàn tất 20 UC. Hai file được tạo trong `docs/TV1`; backend/frontend source giữ nguyên. Baseline: route list chạy được, backend 167 tests/664 assertions PASS, frontend lint PASS với 3 warnings và build PASS ngoài sandbox với warning chunk lớn. Lỗi sandbox được tách khỏi lỗi chức năng/baseline source.

Kiểm tra cuối: `git status --short` chỉ có `?? docs/TV1/`; danh sách untracked chỉ gồm hai báo cáo; `git diff --check` exit 0, tracked diff rỗng; branch/HEAD giữ nguyên. Kiểm tra riêng file mới không có trailing whitespace và các đường dẫn Markdown đều tồn tại. Ma trận mục 4 và roadmap mục 10 đều có đủ 20 UC, mỗi UC xuất hiện một lần trong mỗi bảng.

Dependencies/blockers còn lại: status/rate-limit nền auth; contract UC7 và code TV2 chưa tích hợp; quyết định workflow account/Property/RoomType/maintenance; public reviews status phối hợp TV3; browser và MySQL live chưa kiểm chứng. Không thay stub, không khởi tạo database, không tự sửa lỗi vừa tìm thấy.

Đề xuất prompt triển khai đầu tiên: **UC2 — hoàn thiện login/logout, account-status guard và bảo vệ các session hiện có** trên branch developer chuẩn bị; đọc lại audit/code, nêu file scope và mini plan để review. Sau báo cáo này dừng, không tự làm UC1/UC2 hoặc tạo branch.
