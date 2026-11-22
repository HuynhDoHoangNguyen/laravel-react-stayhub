# UC18 — Quản lý cơ sở lưu trú của Host

## 1. UC18-01 — Mục tiêu, baseline và kết quả

- Ngày audit: 21/11/2026 (Asia/Saigon).
- Baseline: `feature/tv1-uc21-room-images`, HEAD `4e0aa6f`; working tree sạch trước task.
- Owner đã duyệt UC18-01: audit code hiện có, chạy checks và chỉ ghi hai file tài liệu. Không tạo/chuyển branch.
- Backend: đã có danh sách, chi tiết, tạo, sửa, đổi trạng thái và quản lý ảnh cơ sở.
- Frontend: đã có các trang Host và component tương ứng, dùng API thật.
- Technical status của **UC18-01: TECHNICAL PASS cho audit/automated QA**, đủ evidence theo scope; findings và gaps bên dưới vẫn mở. **Toàn bộ UC18 chưa được chốt hoàn thành.**
- Owner browser QA ngày 21/11/2026: **PASS do owner báo “Đạt cả bốn mục”** cho tạo/sửa và refresh, ngưng/bật hoạt động, upload/primary/xóa hoặc Hủy, mobile/bàn phím. Đây không phải browser automation của agent; không đồng nghĩa owner đã duyệt F01 hoặc toàn bộ UC18.
- Không sửa production, tests, schema, seeders, môi trường hoặc dữ liệu nghiệp vụ. Không commit/push/merge.

Nguồn đối chiếu: `AGENTS.md`, `ARCHITECTURE.md`, `README.md`, `docs/ai-frontend-development-guide.md`, `docs/stayhub-erd.md`, `docs/stayhub-usecase-system.md` §4.1, `docs/stayhub-team-work-assignment.md` §3, `docs/stayhub-screen-mockups.md` §3.2–3.3; migration `2026_10_08_000000_create_stayhub_schema.php`, `Member1DataSeeder.php`, models/enums và code/test liên quan dưới đây. Seeder chỉ được đọc.

## 2. File thay đổi và bản đồ code đã audit

| File | Hành động |
| --- | --- |
| `docs/TV1/UC18-REPORT.md` | Tạo báo cáo audit, findings, test evidence và checklist |
| `docs/TV1/README.md` | Thêm mục UC18 và trạng thái chưa nghiệm thu |

Không xóa file versioned. Không có deviation về scope ghi source. Log kiểm thử lưu ngoài repository tại `C:/Users/Tan Phuoc/Documents/ChatGPT/Stayhub-mnm/`.

### Backend hiện có

| Trách nhiệm | File thực tế |
| --- | --- |
| Route/middleware | `backend/routes/api/catalog.php` |
| HTTP | `backend/app/Http/Controllers/Catalog/HostPropertyController.php`, `HostPropertyImageController.php` cùng thư mục |
| Validation | `backend/app/Validators/Catalog/CatalogValidator.php` |
| Request DTO | `backend/app/DTOs/Catalog/Requests/{HostPropertyDetailQuery,CreateHostPropertyRequest,UpdateHostPropertyRequest,ChangeHostPropertyStatusRequest,UploadHostPropertyImageRequest,HostPropertyImageRequest}.php` |
| Query/mutation | `backend/app/Services/Catalog/{HostPropertyQueryService,HostPropertyService,HostPropertyImageService}.php` |
| Persistence | `backend/app/Repositories/Catalog/CatalogRepository.php` |
| Mapping | `backend/app/Mappers/Catalog/CatalogMapper.php`, response DTO dưới `backend/app/DTOs/Catalog/Responses/` |
| Schema/model | Migration schema chung; `backend/app/Models/{Property,PropertyImage,RoomImage}.php`; `PropertyStatus`, `PropertyType` |
| Tests | `backend/tests/Feature/Catalog/{HostPropertiesTest,HostPropertyImagesTest,PublicPropertyTest}.php`; legacy `backend/tests/Feature/HostPropertiesTest.php` |

### Frontend hiện có

- `/host/properties`: `frontend/src/pages/host/HostPropertiesPage.jsx`.
- `/host/properties/create`: `frontend/src/pages/host/HostPropertyCreatePage.jsx`.
- `/host/properties/:propertyId`: `frontend/src/pages/host/HostPropertyDetailPage.jsx`.
- `/host/properties/:propertyId/edit`: `frontend/src/pages/host/HostPropertyEditPage.jsx`.
- `frontend/src/components/catalog/HostPropertyStatusPanel.jsx`, `HostPropertyImageManager.jsx`.
- `frontend/src/services/hostPropertyService.js`; `frontend/tests/hostPropertyService.test.js`.
- Route Host và navigation đã nối tại `frontend/src/routes/AppRouter.jsx`, `frontend/src/layouts/HostLayout.jsx`.

## 3. Kiểm thử thực tế

Các lệnh Backend chạy trong `backend/`, Frontend trong `frontend/`. SQLite `:memory:` theo `backend/phpunit.xml`; hai suite Host property còn kiểm cấu hình trước boot và dùng storage fake. Không chạy trên database dev; không chạy runner MariaDB UC21 để suy ra evidence cho UC18.

| Command | Exit / kết quả | Giới hạn |
| --- | --- | --- |
| `php artisan test --filter='HostPropertiesTest\|HostPropertyImagesTest\|PublicPropertyTest'` | 0; **202 passed, 1184 assertions** | Filter bao gồm cả legacy HostPropertiesTest và public regression, không phải 202 tests chỉ riêng UC18 |
| `node --experimental-test-module-mocks --test tests/hostPropertyService.test.js` | 0; **38/38 PASS** | Service production với transport fixtures; có cả UC19 consumer tests |
| `php artisan test` | 0; **2750 passed, 485 skipped, 11802 assertions**, 117.01s | Full SQLite; skipped không phải PASS |
| `node --experimental-test-module-mocks --test tests/*.test.js` | 0; **437/437 PASS**, 0 skipped | Node/helper/service, không phải browser |
| `npm run lint` | 0; 0 errors, 3 warnings | Fast Refresh tại `badge.jsx`, `button.jsx`, `sidebar.jsx`; không sửa các file này |
| `npm run build` | 0; PASS | Vite 8.3.2, 3400 modules; cảnh báo chunk >500 kB |
| `git diff --check` | 0; PASS | File tracked; file báo cáo mới được kiểm whitespace riêng |

Lần gọi focused đầu tiên trong sandbox exit 2 vì PHP child không mở được `vendor/autoload.php`. Chạy lại cùng command ngoài sandbox được phê duyệt và PASS; không sửa dependency hoặc cấu hình để né lỗi.

Logs: `uc18-focused.log`, `uc18-full-backend.log`, `uc18-full-node.log` trong thư mục ngoài repository nêu trên. Không dùng kết quả lịch sử UC21 thay kết quả lượt này.

## 4. Nghiệp vụ, API contract và kiến trúc

| Method/URI (prefix `/api`) | Contract hiện có |
| --- | --- |
| `GET /host/properties` | 200; `success/message/data`, danh sách chỉ `id`, `name`, sort theo tên, scope owner; không phân trang |
| `GET /host/properties/{property}` | 200; chi tiết 10 trường; không tồn tại/khác owner/ID không canonical trả 404 |
| `POST /host/properties` | 201; actor quyết định `host_id`, trạng thái `ACTIVE`; default giờ 14:00/12:00 nếu bỏ qua |
| `PUT /host/properties/{property}` | 200; yêu cầu đủ 7 field thông tin; nullable mô tả/điện thoại; giữ owner, status và ảnh |
| `PATCH /host/properties/{property}/status` | 200; nhận `status`, `expected_status`; chỉ ACTIVE/INACTIVE; stale và PENDING/BLOCKED trả 409 |
| `GET /host/properties/{property}/images` | 200; `id`, public `url`, `is_primary` |
| `POST /host/properties/{property}/images` | 201; một file JPG/PNG/WebP ≤5 MiB, ≤4096×4096; tối đa 20 rows, kể cả legacy URL không hiển thị; vượt giới hạn 409 |
| `PATCH /host/properties/{property}/images/{image}/primary` | 200; không nhận payload; ảnh không có public URL hợp lệ trả 409 |
| `DELETE /host/properties/{property}/images/{image}` | 200; `deleted_image_id`, `images`, `cleanup_status`; cleanup lỗi không đảo ngược row đã commit |

- `auth:sanctum` và `account.active`; role được kiểm trong validator/service, ownership được scope ở repository. Guest 401, sai role 403, validation 422; giữ privacy 404 cho tài nguyên ngoài quyền.
- Service quản lý transaction; status dùng khóa Property và kiểm expected status trước no-op. Logging mutation thông tin/trạng thái dùng afterCommit. Mutation ảnh yêu cầu root transaction, cleanup sau commit; rollback upload có compensation.
- Mapper/DTO kiểm soát projection; tests dùng Symfony mapper thật và kiểm không phát sinh query trong mapping.
- Backend public chỉ hiển thị Property ACTIVE. Không thay trạng thái Booking/Room hoặc giá snapshot.
- Frontend dùng CSRF trước mutation, truyền signal/timeout, ref guard chống gửi trùng, outcomeUnknown để yêu cầu kiểm lại; page scope theo user/resource và cleanup request.
- Service frontend còn gọi shared Axios và chứa mapping nội bộ. `ARCHITECTURE.md` §3.1 cho phép module đang chuyển đổi giữ cách này; đây là hạng mục chuyển đổi có thể lập scope riêng, **không tự coi là vi phạm bắt buộc refactor ngay**. File còn phục vụ UC19.
- Mockup có Delete và cột Type/Status ở danh sách, nhưng use case §4.1 không yêu cầu xóa, list API chỉ id/name. Ghi nhận sai khác tài liệu, không tự thêm DELETE hoặc đổi contract.

### Findings và khoảng thiếu

| ID | Mức độ / bằng chứng | Ảnh hưởng và hướng xử lý đề xuất |
| --- | --- | --- |
| UC18-F01 | **P2 có điều kiện; static code finding**. `CatalogRepository.php:322` / `imagePathReferenced()` chỉ query PropertyImage; `HostPropertyImageService::cleanupDeletedImage()` dùng kết quả đó trước xóa file. `PublicImageUrl::from()` chấp nhận `properties/...` cho cả ảnh phòng; RoomImage không cấm tham chiếu này. Test cleanup shared hiện chỉ tạo PropertyImage thứ hai. | Nếu dữ liệu cũ/import có RoomImage tham chiếu đúng tệp managed của cơ sở, xóa PropertyImage cuối cùng có thể xóa file mà RoomImage còn dùng. Chưa xác nhận dữ liệu dev có tình huống này, chưa chạy repro mới. UC18-02 cần regression qua DELETE production và kiểm tham chiếu cả hai bảng; có helper `roomImagePathReferenced()` từ UC21 để cân nhắc tái sử dụng. |
| UC18-F02 | **P3, code inspection; browser chưa kiểm**. `HostPropertyImageManager.jsx:133–142`: confirm/cancel inline không chủ động đưa focus vào xác nhận hoặc trả focus về trigger; sau thành công gọi parent reload. | Cần kiểm keyboard khi Hủy/xóa và bổ sung quản lý focus nếu chốt cải thiện accessibility. Không khẳng định đã quan sát lỗi trên browser. |
| UC18-F03 | **Gợi ý UI, không chặn nghiệp vụ**. `HostPropertyDetailPage.jsx:75–82` vừa render manager có thumbnails, vừa render gallery chỉ xem bên dưới. | Ảnh xuất hiện hai khu vực; có thể làm gọn khi owner duyệt UI scope. Không tự thay giao diện. |
| UC18-G01 | **Evidence gap còn lại** | Owner đã báo PASS bốn nhóm chức năng/mobile/bàn phím. Agent chưa chạy browser automation hoặc quan sát session/CSRF; mạng chậm/chuyển cơ sở và tài nguyên PENDING/BLOCKED chưa có xác nhận riêng. Node không chứng minh các mục này. |
| UC18-G02 | **Evidence gap** | Chưa kiểm lock/concurrency trên MariaDB/MySQL cho UC18 trong task này. SQLite và kết quả UC21 không thay thế bằng chứng UC18. |

Không tìm thấy lỗi chức năng khác trong các kịch bản automated đã chạy. Tests hiện có PASS không loại trừ F01 vì thiếu fixture shared chéo bảng.

## 5. Acceptance criteria của audit

| ID | Trạng thái | Evidence |
| --- | --- | --- |
| UC18-01-AC01 | PASS | Baseline và bản đồ đủ luồng tại mục 1–2 |
| UC18-01-AC02 | PASS (audit) | Đối chiếu kiến trúc tại mục 4; findings F01–F03 và gaps được phân loại |
| UC18-01-AC03 | PASS cho phạm vi automated | Focused Backend 202/1184 và Node 38, role/ownership/validation/response contract |
| UC18-01-AC04 | PASS cho phạm vi automated | Status matrix, stale conflict, public visibility, upload boundaries, primary/delete/rollback/cleanup; browser/runtime gaps vẫn mở |
| UC18-01-AC05 | PASS | Required checks exit 0; lỗi sandbox lần đầu và cảnh báo nền được ghi riêng tại mục 3 |
| UC18-01-AC06 | PASS về báo cáo | Báo cáo 8 phần; owner browser QA có xác nhận riêng, toàn UC18 còn F01 |

### Checklist browser tại `/host/properties`

Owner đã trả lời **“Đạt cả bốn mục”** cho câu hỏi bốn nhóm: (1) tạo/sửa rồi refresh; (2) ngưng hoạt động/bật lại; (3) upload, primary, xóa hoặc Hủy; (4) mobile/bàn phím. Không suy rộng câu trả lời thành xác nhận các negative cases chưa hỏi chi tiết.

- [ ] Danh sách chỉ có cơ sở của mình; mở chi tiết, refresh và Back đúng.
- [x] Tạo/sửa và refresh: owner-reported PASS. Validation field lỗi chưa xác nhận browser riêng.
- [x] Ngưng hoạt động/bật lại: owner-reported PASS. Public visibility/PENDING/BLOCKED chưa xác nhận browser riêng.
- [x] Upload, chọn ảnh đại diện, xóa hoặc Hủy: owner-reported PASS. Không suy ra đã thử mọi nhánh lỗi.
- [x] Mobile và bàn phím: owner-reported PASS ở mức tổng quát.
- [ ] Focus cụ thể sau Hủy/xóa theo F02; tablet, lỗi mạng/retry và chuyển cơ sở khi mạng chậm chưa có kết quả riêng.

Không yêu cầu tạo fixture trực tiếp trên database dev hoặc xóa ảnh quan trọng để kiểm. Chỉ ghi owner-reported PASS sau khi owner xác nhận đúng các mục đã thử.

## 6. Rủi ro, dependencies và phần chưa kiểm

- F01 cần fix và regression trước đề xuất chốt UC18. Chưa sửa vì UC18-01 chỉ cho phép audit/tài liệu.
- API/list service đang được UC19/UC20 dùng chung. Nếu tách API/mapper phải giữ exports và consumer contract, lập plan riêng.
- Live database/concurrency và browser negative cases còn mở. Owner đã báo đạt bốn nhóm browser trên; chưa tuyên bố UC18 hoàn thành hoặc đủ điều kiện merge vì F01 còn cần xử lý.
- Không thay schema, seeders, shared Axios/Auth/router/layout, TV2 business logic hoặc code UC21.

## 7. Skills thực sự áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): xác nhận baseline/owner, giữ scope audit, tách sửa lỗi khỏi bước nghiệm thu.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): ánh xạ AC tới test evidence, phân biệt SQLite/Node/build/browser và báo cáo technical status riêng owner acceptance.

Không áp dụng skill Laravel/React/Database để triển khai; task không sửa code hoặc schema.

## 8. Bàn giao và bước kế tiếp

Đề xuất **UC18-02 — bảo vệ cleanup ảnh cơ sở khi còn RoomImage tham chiếu**: tái hiện bằng regression test production, sửa scoped reference check và kiểm cleanup status/file/row còn nguyên. Lập Mini Plan exact file scope trước khi triển khai; không ghép refactor lớn hoặc thay UI tùy ý.

Đề xuất commit tài liệu sau owner review: `docs(catalog): audit UC18 property management and QA evidence`.

Dừng sau báo cáo UC18-01; không tự commit, push, merge hoặc chuyển task.

---

## UC18-02 — Bảo vệ cleanup ảnh khi còn phòng tham chiếu

### 1. Mục tiêu và kết quả Backend/Frontend

- Ngày: 21/11/2026; baseline HEAD `4e0aa6f`, branch `feature/tv1-uc21-room-images`. Hai file báo cáo UC18-01 chưa commit được giữ lại.
- Owner đã duyệt Mini Plan UC18-02 với scope bốn file.
- Backend: `imagePathReferenced()` tái sử dụng `roomImagePathReferenced()` hiện có, kiểm cả RoomImage và PropertyImage trước cleanup. Không đổi helper UC21 hoặc workflow service.
- DELETE ảnh cơ sở giữ file và trả `shared` nếu vẫn có ảnh phòng tham chiếu, kể cả phòng thuộc cơ sở của Host khác. Không còn tham chiếu thì vẫn xóa managed file và trả `deleted`.
- Frontend không đổi; cleanup status và response contract giữ nguyên, không cần UI mới.
- Technical status **UC18-02: TECHNICAL PASS**, đủ các AC và required checks trong scope. Owner acceptance implementation UC18-02 chưa có; kết quả browser UC18-01 không thay cho nghiệm thu fix này.

### 2. Exact files và deviations

| File | Hành động / lý do |
| --- | --- |
| `backend/app/Repositories/Catalog/CatalogRepository.php` | Sửa một return để tái sử dụng kiểm tra tham chiếu chéo bảng |
| `backend/tests/Feature/Catalog/HostPropertyImagesTest.php` | Thêm regression API production cho cùng cơ sở, khác Host và đối chứng không còn tham chiếu |
| `docs/TV1/UC18-REPORT.md` | Ghi evidence red/green, checks, AC và giới hạn |
| `docs/TV1/README.md` | Đồng bộ trạng thái UC18 |

Không thêm/xóa file code, không đổi service, model, factory, migrations, seeders, API, `.env` hoặc Frontend. Không có deviation về scope.

### 3. Kiểm thử đã chạy

Lệnh Backend trong `backend/`, SQLite `:memory:` và storage fake; logs ngoài repository tại `C:/Users/Tan Phuoc/Documents/ChatGPT/Stayhub-mnm/`.

| Command / evidence | Exit / kết quả |
| --- | --- |
| `php artisan test --filter='test_delete_preserves_managed_file_referenced_by_room_image\|test_delete_removes_unreferenced_managed_file'` trước sửa | **1; 2 failed, 1 passed, 12 assertions**. Hai nhánh shared nhận actual `deleted` thay expected `shared`; đối chứng PASS. Log `uc18-02-red.log` |
| Cùng regression command sau sửa và hoàn thiện fixture snapshot | **0; 3 passed, 24 assertions**. Log `uc18-02-green.log` |
| `php artisan test --filter='HostPropertyImagesTest\|HostRoomImagesTest\|HostPropertiesTest\|PublicPropertyTest'` | **0; 230 passed, 1379 assertions**. Log `uc18-02-focused.log` |
| `php artisan test` | **0; 2753 passed, 485 skipped, 11826 assertions**, 150.95s. Log `uc18-02-full-backend.log`; skipped không được tính PASS |
| `php -l backend/app/Repositories/Catalog/CatalogRepository.php` và `php -l backend/tests/Feature/Catalog/HostPropertyImagesTest.php` từ root | **0; không lỗi cú pháp** |
| `git diff --check` | **0; PASS**; báo cáo untracked được kiểm whitespace riêng trước bàn giao |

Lần green đầu sau sửa production còn fail assertion dữ liệu phòng vì `getAttributes()` của model vừa insert và model đọc lại có thứ tự key khác nhau. Đổi snapshot trước request thành `$room->fresh()->getAttributes()` để so sánh hai bản đọc từ DB; giữ `assertSame` toàn bộ attributes. Không bỏ assertion hoặc thay expected để né lỗi production.

Node/lint/build không chạy lại: không đổi Frontend; kết quả UC18-01 tại mục 3 phía trên được giữ là evidence lịch sử riêng. Không chạy database dev, reset/seed hoặc QA MariaDB trong task này.

### 4. Contract và kiến trúc

- Regression gọi DELETE route thật, qua middleware, controller, validator, service, repository và cleanup production; không chép thuật toán vào test.
- Fixture RoomType/Room được gán rõ cùng property_id, tránh lệch quan hệ từ factory mặc định.
- Assert row ảnh cơ sở đã xóa, response ID/images/status chính xác, RoomImage vẫn giữ room/path/primary, toàn bộ attributes phòng và nội dung file không đổi.
- Scope quyền của DELETE vẫn theo owner cơ sở được xóa; tham chiếu chéo Host chỉ bảo vệ tệp, không cấp quyền đọc/sửa tài nguyên Host khác.
- Service vẫn commit row trước cleanup; không đổi primary selection, compensation, auth, validation hoặc error handling. API không đổi status/envelope/fields.
- Tái sử dụng helper tên `roomImagePathReferenced` là tradeoff đã duyệt để tránh refactor UC21; chưa đổi tên API nội bộ đó.

### 5. AC checklist

| ID | Trạng thái / evidence |
| --- | --- |
| UC18-02-AC01 | PASS: code cũ có hai FAIL đúng cleanup status, một đối chứng PASS |
| UC18-02-AC02 | PASS: cùng cơ sở và khác Host đều `shared`, file/content/RoomImage/phòng giữ nguyên |
| UC18-02-AC03 | PASS: đối chứng không tham chiếu trả `deleted`, row và file bị xóa |
| UC18-02-AC04 | PASS focused: 230 tests bao gồm ảnh cơ sở/phòng, quyền, rollback, primary và public property |
| UC18-02-AC05 | PASS: full suite exit 0, PHP lint và diff check PASS, status đúng bốn file đã duyệt |

### 6. Rủi ro và phần chưa kiểm

- F01 đã được tái hiện bằng regression và sửa cho tham chiếu tồn tại tại thời điểm kiểm cleanup. Kết luận audit F01 phía trên là lịch sử trước fix.
- Không khẳng định giải quyết race khi tiến trình khác đồng thời tạo tham chiếu mới giữa kiểm tra và xóa file; không có evidence concurrency MariaDB của UC18-02.
- Không chạy browser mới vì không đổi UI; owner browser PASS của UC18-01 giữ phạm vi đã xác nhận.
- F02/F03 và negative browser/runtime gaps không được sửa trong task này. UC18 tổng thể chưa tự chuyển thành accepted.

### 7. Skills thực sự sử dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục đúng scope được duyệt, giữ baseline tài liệu UC18-01 và dependencies UC21.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): sửa tại persistence boundary, dùng route production và fixture quan hệ đúng cho regression.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): ghi red/green và lỗi fixture trung gian, ánh xạ AC và tách evidence SQLite/browser/runtime.

### 8. Bàn giao và bước tiếp theo

Đề xuất tiếp theo: **UC18-FINAL — rà soát các gap còn lại và chốt phạm vi nghiệm thu**, lập plan riêng; không tự gộp UI refactor hoặc DB concurrency vào task này.

Đề xuất commit (kèm tài liệu audit UC18-01 chưa commit):

```text
fix(catalog): preserve property image files referenced by rooms
```

Không tự commit, push hoặc merge. Dừng chờ owner review sau báo cáo.

---

## UC18-UI-01 — Tối ưu giao diện chi tiết cơ sở Host

### 1. Mục tiêu và kết quả

- Ngày 21/11/2026, owner duyệt scope năm file cho trang `/host/properties/:propertyId`. Baseline Git vẫn `4e0aa6f`; giữ nguyên hai file Backend UC18-02 và tài liệu UC18-01/02 chưa commit.
- Header mới có loại cơ sở, badge trạng thái, tên/địa chỉ và nút Chỉnh sửa thông tin nổi bật.
- Tổng quan hai cột trên desktop, một cột trên mobile: thông tin/mô tả và card trạng thái. Navigation theo anchor đến Tổng quan, Hình ảnh, Loại phòng, Danh sách phòng; không dùng tab unmount các component.
- Card nền sáng, viền mảnh/bóng nhẹ, nút có primary/secondary/destructive, hover/disabled/focus theo theme xanh/kem hiện có.
- Bỏ gallery chỉ xem bị trùng, giữ một manager ảnh. Mỗi card chứa thumbnail, badge ảnh đại diện và thao tác; upload có hint/preview và trạng thái lỗi rõ hơn.
- Focus: mở xác nhận xóa đưa focus đến xác nhận; Hủy trả về trigger sau render; sau mutation ảnh và parent reload đưa focus về tiêu đề ảnh. Xác nhận/Hủy/đổi trạng thái cũng có focus tương ứng.
- Automated checks PASS. **Browser/visual acceptance UI mới: NOT VERIFIED**, đang chờ phản hồi riêng; kết quả browser UC18-01 trước redesign không thay thế QA này.

### 2. Exact file scope

| File | Hành động |
| --- | --- |
| `frontend/src/pages/host/HostPropertyDetailPage.jsx` | Sửa header/layout/anchors, bỏ gallery trùng và phục hồi focus sau reload ảnh |
| `frontend/src/components/catalog/HostPropertyStatusPanel.jsx` | Sửa card/buttons/messages và focus xác nhận |
| `frontend/src/components/catalog/HostPropertyImageManager.jsx` | Sửa image cards/upload/confirmation/buttons và focus |
| `docs/TV1/UC18-REPORT.md` | Bổ sung báo cáo UI-01 |
| `docs/TV1/README.md` | Cập nhật trạng thái |

Không tạo/xóa file source, không đổi stylesheet/theme chung, router/layout, domain service/API/mapper hoặc Backend. Diff Backend UC18-02 là baseline có trước task, không phải phát sinh UI-01. Không có deviation scope.

### 3. Kiểm thử

| Lệnh tại `frontend/` hoặc root tương ứng | Exit / kết quả / giới hạn |
| --- | --- |
| `node --experimental-test-module-mocks --test tests/*.test.js` | 0; **437 passed**, 0 fail/skipped; log `C:/Users/Tan Phuoc/Documents/ChatGPT/Stayhub-mnm/uc18-ui01-node.log` |
| `npm run lint` | 0; 0 errors, 3 Fast Refresh warnings sẵn có ở badge/button/sidebar |
| `npm run build` | 0; Vite 8.3.2, 3400 modules; cảnh báo chunk >500 kB |
| `git diff --check` | 0; PASS; file report untracked kiểm whitespace riêng |

Node tests kiểm logic/service/transport hiện có, không chứng minh layout, DOM focus hoặc tương tác browser của ba component mới. Build là bằng chứng compile, không phải browser PASS. Không thêm test phản chiếu class CSS. Backend không chạy lại vì task UI không sửa Backend; kết quả UC18-02 phía trên giữ ngày/phạm vi riêng.

### 4. Contract và kiến trúc

- Giữ nguyên service methods, CSRF, signal/timeout, payload và response mapping. Không thêm request để lấy ảnh/trạng thái cho mục đích trang trí.
- Giữ ref guard chống submit trùng, account/resource key, cancellation/active guard và outcomeUnknown recovery. Không tự gửi lại mutation khi lỗi mạng.
- Parent refresh sau mutation ảnh vẫn theo workflow cũ, bổ sung focus khi render lại; manager không tạo gallery trùng hoặc thêm ảnh demo.
- Loại phòng/phòng dùng component UC19/20 hiện có, chỉ bọc khu vực cho nền/spacing/anchor; không đổi nghiệp vụ của chúng.
- Label, aria-busy, aria-expanded, field hint, focus-visible và reduced-motion transition được bổ sung ở UI trong scope. Trạng thái phân biệt bằng cả chữ và màu.

### 5. AC checklist

| ID | Trạng thái / evidence |
| --- | --- |
| UI01-AC01 | IMPLEMENTED: header có tên, badge và nút chỉnh sửa; build PASS, visual chờ owner |
| UI01-AC02 | IMPLEMENTED: responsive grid, min-w-0/break-words; viewport/overflow chưa kiểm browser |
| UI01-AC03 | IMPLEMENTED: chỉ render một manager ảnh, bỏ gallery dư; browser chờ owner |
| UI01-AC04 | IMPLEMENTED: phân cấp nút và focus flow; DOM keyboard/focus chờ browser |
| UI01-AC05 | PARTIAL EVIDENCE: service tests 437 PASS và giữ nguyên contracts; mutation/error UI mới cần browser |
| UI01-AC06 | Automated checks PASS; owner UI acceptance PENDING |

### 6. Rủi ro và phần chưa kiểm

- Chưa có ảnh chụp/browser automation hoặc xác nhận mới về thiết kế trên desktop/tablet/mobile.
- Cần kiểm riêng focus sau parent reload ảnh, Hủy xóa và Hủy trạng thái với Tab/Enter; kiểm mạng chậm khi đổi resource và error recovery.
- UI F03 (gallery trùng) đã bỏ trong code; F02 được bổ sung focus nhưng chưa đóng browser acceptance. Các gap runtime UC18 trước đó không tự đóng.
- Chưa sửa danh sách cơ sở hoặc form tạo/sửa; đó là UC18-UI-02 được đề xuất riêng.

### 7. Skills đã áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): giữ phạm vi năm file đã duyệt và baseline UC18-02.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): dùng theme/icon hiện có, giữ lifecycle/service contracts, bổ sung presentation và focus tại đúng lớp UI.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): chạy required frontend checks, phân biệt Node/build với browser và owner acceptance.

### 8. Review và commit đề xuất

Owner kiểm tại chi tiết một cơ sở Host: header/Chỉnh sửa; grid desktop/mobile; anchors; chỉ một khu ảnh; primary/upload/delete/Hủy; trạng thái và focus. Chỉ dùng ảnh/cơ sở thử, không xóa dữ liệu quan trọng.

Đề xuất bước tiếp theo: **UC18-UI-02 — đồng bộ giao diện danh sách cơ sở và form tạo/sửa**, lập Mini Plan riêng sau review UI-01.

Commit riêng UI-01 đề xuất:

```text
style(catalog): refine host property detail layout and image actions
```

Working tree còn Backend fix UC18-02 chưa commit. Stage đúng nhóm file nếu tách commit; tài liệu report/README hiện chứa cả lịch sử các task chưa commit. Không tự commit/push/merge.

---

## UC18-UI-02 — Đồng bộ danh sách cơ sở và form tạo/sửa

### 1. Mục tiêu và kết quả Backend/Frontend

- Ngày 21/11/2026; owner yêu cầu làm UI tại `/host/properties` và duyệt UC18-UI-02 sau khi xác nhận UI-01 chỉ sửa trang chi tiết.
- Baseline HEAD `4e0aa6f`, branch `feature/tv1-uc21-room-images`; giữ nguyên các diff UC18-02/UI-01 và tài liệu chưa commit.
- Danh sách có header, nút Thêm cơ sở, số cơ sở từ collection thật, grid card responsive, nút Xem chi tiết trong card; loading skeleton, empty CTA và error/retry rõ hơn.
- Form tạo/sửa có header/card theo theme chung, bố cục tên/địa chỉ toàn hàng và điện thoại/loại cơ sở hai cột khi đủ rộng, khu giờ nhận/trả riêng, nút submit nổi bật, disabled/error/focus states.
- API danh sách chỉ trả id/name: UI không thêm ảnh, loại, trạng thái hoặc số phòng giả và không gọi thêm detail API cho từng card.
- Backend không đổi. **UI-02 TECHNICAL PASS và owner acceptance PASS** trong các mục owner kiểm: card/nút, mở chi tiết, form tạo/sửa lưu được, mobile không tràn, Tab/Enter. Owner trả lời “Đạt các mục trên” ngày 21/11/2026; đây là owner-reported browser QA, không phải agent browser automation. Không suy rộng thành acceptance của toàn UC18 hoặc mọi thao tác UI-01.

### 2. Exact files và scope

| File | Hành động |
| --- | --- |
| `frontend/src/pages/host/HostPropertiesPage.jsx` | Sửa list header/cards/loading/error/empty và CTA |
| `frontend/src/pages/host/HostPropertyCreatePage.jsx` | Sửa form presentation/field grouping và accessibility lỗi |
| `frontend/src/pages/host/HostPropertyEditPage.jsx` | Đồng bộ form, load/error và link quay về |
| `docs/TV1/UC18-REPORT.md` | Thêm report UI-02 |
| `docs/TV1/README.md` | Cập nhật trạng thái |

Không tạo/xóa file code; không chỉnh lại ba file UI-01 trong task này. Không đổi services/API/mapper, router/layout, shared CSS/Axios, Backend, schema, factory, env hoặc dữ liệu. Những file UC18-02/UI-01 xuất hiện trong status là baseline có trước, không phải mở rộng scope UI-02.

### 3. Kiểm thử

| Command | Exit / kết quả / giới hạn |
| --- | --- |
| `node --experimental-test-module-mocks --test tests/*.test.js` trong frontend | 0; **437 passed, 0 failed/skipped**; log `C:/Users/Tan Phuoc/Documents/ChatGPT/Stayhub-mnm/uc18-ui02-node.log` |
| `npm run lint` | 0; 0 errors/3 warnings Fast Refresh cũ tại badge/button/sidebar |
| `npm run build` | 0; Vite 8.3.2, 3400 modules; cảnh báo chunk >500 kB |
| `git diff --check` tại root | 0; PASS; report untracked kiểm whitespace riêng |

Node dùng production logic/service qua transport fixtures, không chứng minh DOM/layout/focus/browser submission. Build chỉ chứng minh compile. Không thêm tests mirror class CSS. Không chạy lại Backend vì không đổi Backend trong UI-02; kết quả UC18-02 giữ evidence riêng, không tuyên bố là run mới.

### 4. Contract và kiến trúc

- Giữ nguyên list/create/update service calls, request payload, nullable values, default giờ và navigation sau thành công.
- FormData giữ nguyên các field name; không thêm input có name vào payload. Fieldset vẫn disabled khi pending/uncertain, ref guard chống duplicate và cancellation/session/resource guards giữ nguyên.
- Tạo thành công vẫn replace đến detail; sửa thành công giữ route tương ứng; uncertain outcome vẫn khóa submit và cho link kiểm lại danh sách/chi tiết.
- Chỉ render dữ liệu thật; list count là số phần tử từ API không phân trang hiện có, không phải statistic tự bịa.
- Dùng icon có sẵn và theme tokens; loading animations có motion-reduce; focus-visible và field errors gắn aria-describedby.

### 5. AC checklist

| ID | Trạng thái / evidence |
| --- | --- |
| UI02-AC01 | PASS: owner xác nhận card/nút và mở chi tiết đúng |
| UI02-AC02 | PASS compile/code review: loading/error/empty giữ workflow, cải thiện presentation; browser negative cases chưa kiểm riêng |
| UI02-AC03 | PASS: đủ fields và owner xác nhận tạo/sửa lưu được; disabled/error guards giữ nguyên |
| UI02-AC04 | PASS trong phạm vi: Node 437 và owner kiểm create/edit/detail; refresh/Back chưa có xác nhận riêng cho redesign |
| UI02-AC05 | PASS owner-reported: mobile không tràn, Tab/Enter dùng được |
| UI02-AC06 | PASS: Node/lint/build/diff exit 0 và owner chấp nhận các mục đã hỏi |

### 6. Giới hạn và rủi ro

- Owner browser QA mới đã nhận PASS cho list/create/edit/mobile/keyboard trong câu hỏi nêu ở mục 1. Lỗi mạng, unknown outcomes, error/retry/empty và refresh/Back chưa có kết quả browser riêng.
- Không có browser screenshot/automation trong lượt này. Agent không trực tiếp quan sát real-session submit; không gán kết quả owner thành agent browser PASS.
- Không tự mở rộng list contract để thêm ảnh/trạng thái hoặc đổi server pagination. Nếu cần enriched cards, lập task API/UI riêng.
- Giữ nguyên UI-01 chưa được owner nghiệm thu; không đóng toàn UC18 hoặc các gap runtime còn lại.

### 7. Skills đã áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục slice được owner duyệt, bảo toàn baseline các task trước.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): UI theo real contract, theme/layout hiện có; giữ lifecycle và mutation guards.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): chạy frontend checks và tách Node/build/browser evidence.

### 8. Review và commit

Đề xuất tiếp theo **UC18-UI-QA — kiểm tổng hợp UI-01/02 trên browser**: list→create→detail→edit, refresh/Back, trạng thái/ảnh, mobile và keyboard; scope QA riêng, không tự triển khai UI khác.

Commit riêng UI-02:

```text
style(catalog): refine host property list and forms
```

Working tree chứa cả UC18-01/02/UI-01 chưa commit; nếu cần tách commit, stage đúng nhóm file/hunks và lưu ý report mới chứa toàn bộ lịch sử. Không tự commit, push hoặc merge.
