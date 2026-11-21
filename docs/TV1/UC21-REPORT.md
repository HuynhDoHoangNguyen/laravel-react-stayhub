# UC21 — Quản lý hình ảnh phòng

Ngày kiểm chứng: 21/11/2026, Asia/Saigon.

## 1. Task và trạng thái

**UC21-01 — Danh sách và upload ảnh phòng** đã được owner duyệt trước triển khai.

- Branch: `feature/tv1-uc21-room-images`; baseline HEAD `363c8fb8e24c66673cf50a59020addffb07ee624`, trùng local `dev`/`origin/dev` lúc bắt đầu. Không fetch hoặc tự chuyển/tạo nhánh trong task.
- Working tree ban đầu sạch. Không phụ thuộc PR/code UC8 và không cherry-pick UC8.
- Backend: thêm GET/POST `/api/host/rooms/{room}/images`.
- Frontend: thay gallery chỉ đọc bằng manager danh sách/upload tại `/host/rooms/:roomId`, dưới Mô tả.
- **Automated checks PASS. Browser/owner acceptance: NOT VERIFIED.** Owner hỏi vị trí chức năng; đã hướng dẫn mở Host → Cơ sở lưu trú → cơ sở sở hữu → phòng → Xem chi tiết. Đây chưa phải kết quả nghiệm thu.
- Chưa hoàn thành toàn UC21: chọn ảnh chính và xóa ảnh thuộc UC21-02; QA tổng hợp thuộc UC21-QA. UC18/19/20 để audit sau theo yêu cầu owner.

## 2. Exact file scope và deviations

| Action | File |
| --- | --- |
| Tạo | `backend/app/DTOs/Catalog/Requests/HostRoomImageQuery.php` |
| Tạo | `backend/app/DTOs/Catalog/Requests/UploadHostRoomImageRequest.php` |
| Tạo | `backend/app/DTOs/Catalog/Responses/HostRoomImageResponse.php` |
| Tạo | `backend/app/Http/Controllers/Catalog/HostRoomImageController.php` |
| Tạo | `backend/app/Services/Catalog/HostRoomImageService.php` |
| Sửa | `backend/app/Validators/Catalog/CatalogValidator.php` |
| Sửa | `backend/app/Repositories/Catalog/CatalogRepository.php` |
| Sửa | `backend/app/Mappers/Catalog/CatalogMapper.php` |
| Sửa | `backend/routes/api/catalog.php` |
| Tạo | `backend/tests/Feature/Catalog/HostRoomImagesTest.php` |
| Tạo | `frontend/src/api/hostRoomImageApi.js` |
| Tạo | `frontend/src/services/hostRoomImageService.js` |
| Tạo | `frontend/src/mappers/hostRoomImageMapper.js` |
| Tạo | `frontend/src/components/catalog/HostRoomImageManager.jsx` |
| Sửa | `frontend/src/pages/host/HostRoomDetailPage.jsx` |
| Tạo | `frontend/tests/hostRoomImageService.test.js` |
| Tạo | `frontend/tests/hostRoomImages.test.js` |
| Tạo | `docs/TV1/UC21-REPORT.md` |
| Sửa | `docs/TV1/README.md` |

Không xóa file. Không tạo DTO/action dành cho UC21-02 trong task này. Không sửa Model, schema/migration/seeder, Auth, shared Axios, router/layout, public UI hoặc Booking/Availability.

Chuẩn bị môi trường: `composer install --no-interaction --prefer-dist --no-scripts` hoàn tất theo lock, không đổi dependency/lock và không chạy scripts/migration. Tạo link ignored `backend/public/storage` → `backend/storage/app/public` bằng `php artisan storage:link` vì link chưa tồn tại. Không sửa cấu hình hoặc dữ liệu dev. Lần chạy sandbox báo Access denied; lần chạy được cấp quyền hoàn tất.

## 3. Kiểm thử thực tế

| Command | Exit / result | Giới hạn |
| --- | --- | --- |
| `php artisan test --filter=HostRoomImagesTest` | 0; 15 passed, 99 assertions | SQLite `:memory:`, Storage fake; không chứng minh MySQL concurrency |
| `php artisan test --compact` | 0; 1893 passed, 91 skipped, 9836 assertions | 112.16 giây; không tính skipped là PASS |
| `node --experimental-test-module-mocks --test tests/hostRoomImageService.test.js tests/hostRoomImages.test.js` | 0; 10 passed | 7 API/service/mapper tests, 3 SSR tests; không phải browser |
| `node --experimental-test-module-mocks --test tests/*.test.js` | 0; 287 passed, 0 skipped | Baseline từ dev chưa có các tests UC8 |
| `npm run lint` | 0; 0 errors | 3 cảnh báo Fast Refresh có sẵn ở `ui/badge.jsx`, `ui/button.jsx`, `ui/sidebar.jsx` |
| `npm run build` | 0 | Cảnh báo bundle lớn hơn 500 kB |
| `git diff --check` | 0 | Kiểm tra lại cùng status/diff trước bàn giao |

Backend tests đặt biến tiến trình `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL`/`DATABASE_URL` rỗng, `CACHE_STORE=array`, `SESSION_DRIVER=array`. Test mới từ chối config cache và DB khác trước khi RefreshDatabase. Không dùng MySQL/dev data.

Trong quá trình làm, các lần test ban đầu phát hiện lỗi fixture tệp tạm/MIME fake và mã AppException mới chưa được global handler hỗ trợ. Đã sửa fixture dùng UploadedFile thật cho multipart và tái sử dụng mã `CATALOG_IMAGE_LIMIT_REACHED` đã có; final focused/full suite đều đạt. Không sửa global handler.

Log Node/full backend nằm ngoài repo trong workspace ghi chép `C:/Users/Tan Phuoc/Documents/ChatGPT/Stayhub-mnm/uc21-01-node.log` và `uc21-01-backend.log`.

## 4. Contract và kiến trúc

GET trả `{success:true,message,data:[{id,url,is_primary}]}`; danh sách sắp primary trước, ID tăng dần. Mapper lọc URL không công khai, không trả `room_id`, raw storage path hoặc timestamps.

POST nhận multipart một trường `image`; trả HTTP 201 với một image DTO. JPEG/PNG/WebP, tối đa 5 MiB, 4096 × 4096 pixel, 20 bản ghi ảnh/phòng (kể cả ảnh legacy bị lọc URL). Backend xác định MIME, tạo UUID dưới `rooms/{roomId}/`, không tin tên file hoặc `is_primary` do client gửi. Query/field ngoài contract bị 422. Không hỗ trợ upload qua URL hoặc nhiều file.

Auth Sanctum và account.active được giữ nguyên. Validator kiểm role Host; Repository scope ownership qua Property. Host khác, phòng không tồn tại và ID không canonical trả privacy 404 với payload hợp lệ; middleware/role/validation giữ thứ tự kiểm tra đã triển khai. Host được quản lý ảnh phòng của mình cả khi phòng/cơ sở không public, tương thích phần quản lý Host hiện có.

Controller → CatalogValidator/typed DTO → HostRoomImageService → CatalogRepository → Eloquent → CatalogMapper/typed DTO. Mapper dùng EloquentObjectMapper/Symfony hiện có và không query. Không thêm Repository abstraction hoặc sửa quản lý ảnh cơ sở.

Upload sở hữu root transaction; dùng cùng thứ tự khóa Property → Room như HostRoomService. Đọc ảnh trong mutation bằng `lockForUpdate` để tránh snapshot cũ khi chờ khóa. Khi upload thành công giữ primary hợp lệ hiện tại; nếu không có thì ảnh mới làm primary, bỏ các cờ primary legacy trùng/không hợp lệ trong cùng phòng. Đây là invariant nội bộ upload, chưa phải chức năng chọn ảnh của UC21-02.

Lỗi trước commit rollback row và bù trừ file đã ghi. Compensation kiểm tra path còn được RoomImage/PropertyImage tham chiếu trước khi xóa; nếu cleanup lỗi thì giữ nguyên ngoại lệ gốc và ghi warning, không tự retry. Chỉ log thành công sau khi root transaction commit.

Frontend đi qua domain API/service/mapper thật, giữ AbortSignal và lỗi HTTP/cancellation. Multipart boundary để Axios/browser tạo. Chống submit trùng bằng ref lock; hủy khi rời phòng và chặn response cũ. Preview dùng object URL được revoke. Timeout/lỗi 5xx/cancel sau POST hoặc response thành công sai contract được xem là outcome unknown; UI khóa upload tới khi tải lại danh sách thành công. Thất bại lấy CSRF không gửi POST. UI dùng normalizeApiError và hiển thị field error `image` khi có.

## 5. AC và evidence

| ID | Status | Evidence |
| --- | --- | --- |
| UC21-01-AC1 — Danh sách thực, empty/error, đúng ownership | Automated PASS | API tests empty/sorted/private URL, guest/role/blocked/cross-owner; SSR states |
| UC21-01-AC2 — Upload và giới hạn | Automated PASS | PNG, multipart WebP, tên sai extension, 5 MiB boundary, 4096 boundary, file/field/query sai, 20/21 ảnh |
| UC21-01-AC3 — Lưu file/DB và primary nội bộ | Automated PASS | UUID/path, storage exists, Host detail đọc được 2 ảnh, primary được giữ và legacy được chuẩn hóa, phòng khác không đổi |
| UC21-01-AC4 — Rollback/compensation | Automated PASS | DB failure sau ghi file, storage failure, cleanup failure giữ ngoại lệ gốc, root guard |
| UC21-01-AC5 — API client, lỗi, UI states | Automated PASS / browser pending | Production service/API/mapper tests và SSR, POST không replay, CSRF failure không submit, outcome unknown khóa fieldset |
| UC21-01-AC6 — Checks/scope | PASS | Full backend/Node, lint/build, diff check; không đổi module TV2/UC8 |
| UC21-01-AC7 — Browser upload/refresh/mobile/session thực | NOT VERIFIED | Owner chưa cung cấp kết quả; chỉ mới hướng dẫn vị trí màn hình |
| UC21-QA — MySQL concurrency | NOT VERIFIED | Không dựng schema/môi trường ngoài scope để tự chứng minh |

## 6. Rủi ro và hướng dẫn owner kiểm

1. Đăng nhập Host, mở `/host/properties`, chọn cơ sở mình sở hữu, tới danh sách phòng → Xem chi tiết.
2. Tại `/host/rooms/{roomId}`, dưới Mô tả có Hình ảnh phòng: chọn JPG/PNG/WebP, kiểm preview rồi bấm Tải ảnh lên.
3. Xác nhận thành công, ảnh xuất hiện và refresh vẫn còn. Thử file sai và kiểm bố cục trên mobile.
4. Kiểm tra đang gửi không thể bấm lặp; nếu kết quả chưa rõ thì kiểm tra danh sách trước khi gửi tiếp. Chuyển phòng không mang ảnh hoặc thông báo phòng cũ sang phòng mới.

Browser thực, session/CSRF thực, MySQL concurrency chưa được nghiệm thu. Link storage local đã tạo nhưng không coi đó là bằng chứng browser đã tải ảnh. Filesystem và DB không có transaction chung; cleanup thất bại có thể để lại file mồ côi, có warning để xử lý sau. Không thêm cơ chế cleanup tự động ngoài scope.

Khi UC8 merge, có thể conflict thêm code ở CatalogValidator/Repository/Mapper và route Catalog; giữ bổ sung của cả hai nhánh, không chọn nguyên một phía. UC21 không thay public contract nên consumer ảnh có sẵn tiếp tục đọc dữ liệu như trước.

## 7. Skills và bước tiếp theo

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): xác nhận nền dev, owner, scope và chia UC21 thành các task.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): validation, DTO/mapper, privacy, thứ tự khóa và bù trừ storage.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): domain API/service/mapper, responsive UI, normalized errors, cancellation và mutation guard.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): test qua production modules, DB test an toàn, tách SSR/SQLite/browser và owner acceptance.

Đề xuất tiếp theo: **UC21-02 — chọn ảnh đại diện và xóa ảnh phòng**, chờ phạm vi/phê duyệt riêng. Không tự commit/push/merge hoặc bắt đầu UC21-02.

## Tái kiểm sau phản hồi owner — 21/11/2026

Owner đổi nhánh khi Codex đang chạy và yêu cầu xác minh code, kèm screenshot upload thành công nhưng hai ảnh mới hiển thị placeholder lỗi.

- Nhánh hiện tại vẫn `feature/tv1-uc21-room-images`, HEAD `363c8fb`; đủ 19/19 file trong manifest. Wiring Controller/Validator/DTO/Service/Repository/Mapper và Host UI/API/service/mapper vẫn có. Các file mới vẫn untracked, thay đổi chưa commit; không kết luận mất code chỉ vì đổi nhánh.
- Chạy lại focused backend: 15 PASS / 99 assertions trên SQLite memory; Node toàn bộ: 287/287 PASS. Build PASS. Không chạy lại full backend vì source không đổi; kết quả 1893 PASS/91 skipped ở mục 3 thuộc lần chạy trước.
- Hai JPEG owner upload thực tế tồn tại trong `storage/app/public/rooms/1`, mỗi file 43301 bytes; junction `public/storage` đúng target và đọc được cả hai qua đường dẫn public.
- Cấu hình hiệu lực: `filesystems.disks.public.url=http://localhost/storage`, `APP_URL=http://localhost`, trong khi `VITE_BACKEND_URL=http://localhost:8000`.
- Read-only HTTP GET một ảnh upload qua `http://localhost:8000/storage/rooms/1/{filename}` trả **200, image/jpeg, 43301 bytes**. Cùng path ở cổng Vite 5173 trả HTML, không phải ảnh. URL storage hiện sinh ra thiếu cổng backend.
- Kết luận: **runtime hiển thị ảnh chưa đạt** do lệch URL môi trường; automated tests với Storage fake không phát hiện được lệch cổng local. Screenshot chứng minh owner đã vào được UI và gửi upload; chưa phải nghiệm thu hiển thị ảnh/refresh/mobile.
- Đề xuất tối thiểu: sửa duy nhất `APP_URL=http://localhost:8000` trong root `.env`, khởi động lại Laravel theo quy định root env rồi kiểm URL response và HTTP ảnh. Không cần upload lại hoặc sửa file/row đã có. Chưa sửa `.env` hoặc restart server trong lượt tái kiểm; chờ owner cho phép theo giới hạn file cấu hình trong AGENTS.md.

### Khắc phục URL local sau owner duyệt

Owner đã cho phép sửa `APP_URL` và restart Laravel. Đã đổi duy nhất root `.env` từ `APP_URL=http://localhost` thành `APP_URL=http://localhost:8000`; giữ các biến khác. `.env` được Git ignore, không đưa vào PR. Đây là ngoại lệ cấu hình local được duyệt riêng, không mở rộng logic UC21.

Đã xác minh chuỗi tiến trình phục vụ đúng repo trước khi dừng và chạy lại `php artisan serve --host=127.0.0.1 --port=8000` bằng tiến trình ẩn. Không dừng Vite/MySQL hoặc thay code upload.

Read-only API public chi tiết cơ sở sau restart trả đúng URL `http://localhost:8000/storage/rooms/1/{filename}`. Cả hai ảnh báo lỗi ban đầu và ảnh thứ ba mới xuất hiện trong lúc kiểm đều tải được HTTP 200, `image/jpeg`, 43301 bytes, khớp kích thước file local. Lần kiểm đầu có guard số ảnh đúng 2 đã dừng khi nhận thấy ảnh thứ ba; lần xác minh lại kiểm toàn bộ ảnh hiện có, không coi thay đổi số lượng do owner upload là lỗi dữ liệu.

**Runtime API/HTTP ảnh PASS; owner browser acceptance vẫn chờ kiểm lại.** Owner chỉ cần refresh trang hoặc bấm Tải lại danh sách để nhận URL mới, không cần upload lại các ảnh cũ. Không thay schema/row/file ảnh, không commit/push. Không chạy lại suite automated vì chỉ đổi môi trường local và báo cáo; bằng chứng ở đây là API/HTTP trực tiếp sau restart.

## UC21 — Resolve conflict khi owner tích hợp dev

Owner yêu cầu kiểm tra và sửa lần merge đang dang dở. HEAD trước xử lý `e2a25db`, incoming MERGE_HEAD `c8a83f86cf36816eff3f79633697297f5442f056`, nhánh `feature/tv1-uc21-room-images`.

Chỉ có một conflict trong `backend/app/Repositories/Catalog/CatalogRepository.php`: UC21 thêm các hàm ảnh phòng, incoming UC8 thêm `findPublicRoom()` cùng vị trí. Đã giữ nguyên cả hai nhóm hàm, nối lại dấu ngoặc và bỏ markers. So với HEAD, Repository chỉ bổ sung nguyên truy vấn UC8 gồm 12 dòng; so với incoming, giữ các hàm UC21. Mapper, validator và routes tự merge đã giữ đủ hai luồng. Không sửa Dashboard/Statistics hoặc các file incoming khác.

Đã stage duy nhất file resolve để xóa unmerged entry; các file incoming đã staged trước task được giữ nguyên. Không chạy merge mới, không commit/push/rebase hoặc đụng DB dev/env trong lượt này.

Kiểm chứng sau resolve: PHP lint Repository PASS; focused `HostRoomImagesTest|PublicRoomDetailTest|HostRoomDetailTest|PublicPropertyTest` PASS 130 tests/572 assertions trên SQLite memory; Node toàn bộ PASS 433/433; lint/build PASS (cảnh báo Fast Refresh và bundle lớn có sẵn); diff/cached diff check PASS, không còn unmerged entry/markers trong source được kiểm tra. Read-only API thật `/api/public/rooms/1` trả phòng và ảnh UC21; ảnh upload có trong response và tải HTTP 200. Đây là API/HTTP evidence, không phải browser acceptance.

Full backend sau resolve: `php artisan test --compact` exit 0, **2738 passed, 476 skipped, 11690 assertions**, 207.64 giây trên SQLite memory. Các bài skipped không được coi là MySQL acceptance PASS. Logs: workspace ghi chép `uc21-merge-backend.log` và `uc21-merge-node.log`.

Trong lúc kiểm tra, owner đã hoàn tất merge commit `9f2a161` (parents `e2a25db`, `c8a83f8`). Kiểm tra cuối xác nhận Repository chứa đủ hai nhóm hàm, không còn MERGE_HEAD/unmerged entries; chỉ còn báo cáo này staged chờ commit riêng. Agent không tạo merge commit.

Skills áp dụng cho lượt merge: [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) để giữ query/ownership và hai nhóm hàm; [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) để đối chiếu stages, regression và phân biệt SQLite/HTTP/browser. Không tự triển khai UC21-02.

## UC21-02 — Chọn ảnh đại diện và xóa ảnh phòng

### 1. Kết quả và acceptance

Task được owner duyệt, triển khai trên `feature/tv1-uc21-room-images`, baseline `9f2a161`. Trước task chỉ có báo cáo merge đã staged; giữ nguyên nội dung này. Backend thêm PATCH primary và DELETE ảnh; frontend thêm nút ngay dưới ảnh, xác nhận/Hủy xóa, thông báo kết quả và cleanup.

Owner xác nhận browser **“Đạt các mục trên”** cho đổi ảnh đại diện rồi refresh, xóa ảnh thường, Hủy xác nhận và mobile. Đây là evidence do owner kiểm, không phải agent tự chạy browser. MySQL concurrency chưa được nghiệm thu.

### 2. File tạo/sửa và deviations

Tạo:
- `backend/app/DTOs/Catalog/Requests/HostRoomImageRequest.php`
- `backend/app/DTOs/Catalog/Responses/DeleteHostRoomImageResponse.php`

Sửa:
- `backend/app/Http/Controllers/Catalog/HostRoomImageController.php`
- `backend/app/Services/Catalog/HostRoomImageService.php`
- `backend/app/Validators/Catalog/CatalogValidator.php`
- `backend/app/Repositories/Catalog/CatalogRepository.php`
- `backend/routes/api/catalog.php`
- `backend/tests/Feature/Catalog/HostRoomImagesTest.php`
- `frontend/src/api/hostRoomImageApi.js`
- `frontend/src/services/hostRoomImageService.js`
- `frontend/src/mappers/hostRoomImageMapper.js`
- `frontend/src/components/catalog/HostRoomImageManager.jsx`
- `frontend/tests/hostRoomImageService.test.js`
- `frontend/tests/hostRoomImages.test.js`
- `docs/TV1/UC21-REPORT.md`
- `docs/TV1/README.md`

Không xóa file, không mở rộng scope. Không sửa public UI/API UC8, nghiệp vụ TV2/TV3, schema, Model, shared Axios, router/layout, `.env` hoặc dữ liệu dev. Không stage thay đổi source task này, không commit/push. Báo cáo merge đã staged trước task vẫn được giữ; nội dung task mới nằm ở working tree.

### 3. Kiểm thử

- Focused backend cuối qua `php vendor/phpunit/phpunit/phpunit --filter=HostRoomImagesTest --testdox`: exit 0, **25 tests / 171 assertions**; SQLite memory và Storage fake.
- Focused Node `node --experimental-test-module-mocks --test tests/hostRoomImageService.test.js tests/hostRoomImages.test.js`: exit 0, **14/14 PASS**. View kiểm qua SSR production, service/API/mapper qua production modules với transport mock.
- Node toàn bộ `node --experimental-test-module-mocks --test tests/*.test.js`: exit 0, **437/437 PASS**, không skipped.
- `npm run lint`: exit 0; 3 cảnh báo Fast Refresh có sẵn tại badge/button/sidebar.
- `npm run build`: exit 0; cảnh báo bundle lớn có sẵn.
- PHP lint service/test và `git diff --check`: PASS.
- Full backend `php artisan test --compact`: exit 0, **2748 passed, 476 skipped, 11780 assertions**, 198.05 giây trên SQLite memory. Không coi skipped là MySQL acceptance PASS. **Automated checks PASS; owner browser PASS cho các mục đã xác nhận; phần kiểm tra còn lại nằm ở mục 6.**
- Một lần artisan focused chạy trước khi sửa fixture đã FAIL vì test cố tạo path URL ngoài trên filesystem Windows; sửa fixture unmanaged thành file local legacy. Focused PHPUnit sau sửa PASS, không bỏ assertion. Không tính lần cũ này là PASS.

Logs ngoài repo: `uc21-02-focused.log`, `uc21-02-node.log`, `uc21-02-backend.log` trong workspace ghi chép Stayhub-mnm.

### 4. Contract và bảo toàn nghiệp vụ

- `PATCH /api/host/rooms/{room}/images/{image}/primary`: HTTP 200, `{success,message,data:[{id,url,is_primary}]}`.
- `DELETE /api/host/rooms/{room}/images/{image}`: HTTP 200, `{success,message,data:{deleted_image_id,images,cleanup_status}}`.
- Không nhận thêm body/query. Sanctum/account.active → role Host → IDs canonical → validation → ownership room/image. Cross-owner/cross-room/missing trả privacy 404; URL không hợp lệ để chọn chính trả 409 bằng mã AppException hiện có.
- Mutation sở hữu root transaction; khóa Property → Room → ảnh, cùng thứ tự với upload. Chọn lại ảnh đang chính thành công. Chọn mới chỉ giữ một primary. Xóa ảnh thường giữ primary hợp lệ; xóa primary chọn ảnh hợp lệ ID nhỏ nhất; xóa cuối trả mảng rỗng. Legacy invalid URLs bị lọc như trước.
- Xóa row và đổi primary atomically. Sau commit mới dọn file UUID trong đúng `rooms/{roomId}`. Giữ file external/unmanaged hoặc còn tham chiếu RoomImage/PropertyImage. `cleanup_status`: deleted/missing/not_managed/shared/failed. Cleanup failed không rollback row đã commit và không khuyến khích replay DELETE.
- Frontend giữ CSRF/cancellation và rejection identity. Chỉ payload action response đúng contract mới cập nhật UI. Timeout/5xx/cancel sau gửi hoặc success sai contract được xem outcome unknown; khóa upload/action tới khi GET kiểm lại thành công. 404 action cũng yêu cầu tải lại. Không tự retry mutation.
- UI dùng chung ref lock chống gửi trùng; đang xác nhận xóa thì khóa upload và action khác. Focus sang xác nhận, Hủy trả focus về nút mở, thành công trả focus về tiêu đề vùng ảnh. Cảnh báo cleanup riêng với thông báo xóa thành công.

### 5. AC

| ID | Trạng thái | Evidence |
| --- | --- | --- |
| AC02-01 — Chọn chính đúng phòng và idempotent | PASS | API tests, public room đọc ảnh mới đầu tiên, owner refresh đạt |
| AC02-02 — Xóa thường/chính/cuối và ảnh thay thế | PASS | API tests; owner xóa thường đạt |
| AC02-03 — Quyền, rollback và cleanup | Automated PASS | Guest/role/blocked/foreign IDs; rollback sau delete giữ row/file; cleanup sau commit, missing/shared/unmanaged/failed |
| AC02-04 — Confirmation, Hủy, khóa mutation và outcome unknown | PASS với giới hạn evidence | Node API/service/mapper + SSR; owner Hủy đạt; hook race/keyboard chưa tự động kiểm browser |
| AC02-05 — Giữ upload UC21-01 và public UC8 | Automated PASS | Regression ảnh phòng gồm upload cũ và public detail; full checks ghi riêng bên dưới |
| AC02-06 — Refresh và mobile | OWNER PASS | Owner xác nhận trong chat, không tự suy ra từ SSR |

### 6. Rủi ro và phần chưa kiểm

Không chạy MySQL concurrency; SQLite không chứng minh row locks. Owner chưa xác nhận riêng keyboard/cancellation race hoặc cleanup-failure UI trên backend thật; các trạng thái này có bằng chứng code/Node/SSR và test backend tách biệt. Cleanup failed có thể để lại file mồ côi, có warning để xử lý; không tự thêm background cleanup. Không thử xóa ảnh thật bằng công cụ agent.

### 7. Skills áp dụng

- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): giữ validation/DTO/service/repository, quyền và transaction/cleanup sau commit.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): API/service/mapper, normalized errors, shared mutation lock và focus.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): test production modules, SQLite an toàn, phân biệt SSR/owner browser và skipped.

### 8. Bước tiếp theo và commit

Đề xuất `UC21-QA — nghiệm thu tổng hợp quản lý ảnh phòng`, đặc biệt keyboard, stale response và MySQL concurrency khi có môi trường test được duyệt. Không tự bắt đầu task mới.

Commit đề xuất: `feat(catalog): add room image primary selection and deletion`. Không đưa `.env`, uploads, logs, vendor/node_modules/dist vào commit.

## UC21-QA — Kiểm tra tổng hợp 01/02 (21/11/2026)

### 1. Kết quả và phạm vi nghiệm thu

QA trên nhánh `feature/tv1-uc21-room-images`, HEAD `9f2a161` cùng working tree UC21-02 đã được duyệt. Không có source mới kể từ checks cuối UC21-02. **Automated và owner browser chức năng PASS trong phạm vi evidence dưới đây; MySQL concurrency NOT VERIFIED.** Không tuyên bố đã nghiệm thu khóa đồng thời MySQL.

Owner đã xác nhận đổi primary rồi refresh, xóa ảnh thường, Hủy và mobile ở UC21-02. Trong lượt QA này owner xác nhận thêm **“Đạt cả hai mục”** cho:
1. Tab/Enter mở xác nhận xóa, Hủy trả focus về nút Xóa.
2. Mạng chậm rồi chuyển phòng không hiện ảnh/thông báo phòng cũ.

Đây là owner-reported browser evidence, không phải browser automation của agent. Không tự xóa/upload dữ liệu thật để QA.

### 2. File thay đổi trong lượt QA

Chỉ cập nhật `docs/TV1/UC21-REPORT.md` và `docs/TV1/README.md`. Giữ nguyên source UC21-02 chưa commit và phần báo cáo merge đã staged trước task. Không thêm/xóa file, thay dependency, environment, router, code TV2/TV3 hoặc public UC8. Không stage/commit/push.

### 3. Commands và evidence mới

| Check | Kết quả | Giới hạn |
| --- | --- | --- |
| `php vendor/phpunit/phpunit/phpunit --filter='HostRoomImagesTest\|PublicRoomDetailTest\|HostRoomDetailTest'` | Exit 0, 71 tests / 338 assertions | SQLite memory, Storage fake |
| `node --experimental-test-module-mocks --test tests/hostRoomImageService.test.js tests/hostRoomImages.test.js tests/publicRoomService.test.js` | Exit 0, 34/34 PASS | Production modules, transport mock và SSR |
| Read-only `GET /api/public/rooms/1` | PASS, 3 ảnh trong response | API runtime thật, không phải browser |
| GET URL upload local trong response | HTTP 200, Content-Type image/* | 1 ảnh upload còn public tại thời điểm kiểm |
| Guest `GET /api/host/rooms/1/images` | HTTP 401 | Không dùng hoặc xuất cookie/session |
| `git diff --check`, `git diff --cached --check`, unmerged entries | PASS; không còn conflict | Không chỉnh index |

Log focused mới: `uc21-qa-backend.log`, `uc21-qa-node.log` trong workspace Stayhub-mnm ngoài repo.

Dẫn lại checks UC21-02 ngay trước QA, không nhận là đã chạy mới: full backend exit 0 **2748 passed / 476 skipped / 11780 assertions**; full Node exit 0 **437/437 PASS**; lint/build PASS với 3 Fast Refresh warnings và bundle warning đã ghi. Source không đổi nên không lặp full suite/lint/build. Skipped không được tính PASS hoặc MySQL evidence.

### 4. Rà soát contract và kiến trúc

Đủ GET/POST/PATCH/DELETE ảnh phòng, Sanctum/account status, Host ownership và image thuộc room. Primary và delete cùng root transaction/lock order với upload. Xóa row/đổi primary trước commit, cleanup sau commit; URL ngoài/unmanaged/shared không bị xóa file. Mapper allowlist, frontend API/service/mapper phân lớp, kiểm malformed response và không tự replay mutation.

Đã rà cancellation guards, keyed room/session consumer, ref lock, URL preview revoke, xử lý kết quả chưa rõ, cập nhật gallery và focus. API/public consumer tiếp tục đọc ảnh theo primary trước. Không thấy defect mới qua các kiểm tra này.

### 5. Checklist tổng hợp

| ID | Mục | Evidence / trạng thái |
| --- | --- | --- |
| QA21-01 | Upload/list, MIME/size/dimensions/20 ảnh | Automated PASS; owner upload trước đó, URL local đã khắc phục |
| QA21-02 | Chọn chính/idempotent, hiển thị consumer và refresh | API tests + owner browser PASS |
| QA21-03 | Xóa thường/chính/cuối, ảnh thay thế | API tests PASS; owner xóa thường PASS |
| QA21-04 | Ownership/role/blocked/invalid IDs/fields | API tests PASS; guest runtime 401 |
| QA21-05 | Rollback và cleanup outcomes | SQLite/Storage fake PASS, không giả là filesystem failure live |
| QA21-06 | Confirmation/Hủy, keyboard và mobile | Owner browser PASS |
| QA21-07 | CSRF, cancellation, outcome unknown, stale response | Node/code review PASS; owner mạng chậm chuyển phòng PASS |
| QA21-08 | Integration consumer UC8/Host và ảnh thực | Focused tests + runtime GET PASS |
| QA21-09 | Đồng thời upload/chọn chính/xóa trên MySQL | NOT VERIFIED |

### 6. Rủi ro còn lại

Harness HostRoomImagesTest hiện từ chối DB khác SQLite memory. Muốn chứng minh MySQL locks cần task test riêng với schema fixture được xác nhận an toàn; không chạy tests hiện tại vào DB dev và không tự bỏ guard. Cleanup failed có thể để file mồ côi; cơ chế cảnh báo được kiểm bằng test, không thêm worker dọn tự động. Lỗi mạng browser được owner kiểm ở chuyển phòng, chưa phải tái hiện đầy đủ mọi trường hợp POST/DELETE timeout trên server thật.

### 7. Skills thực sự áp dụng

- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): đối chiếu AC và baseline, chạy checks, tách evidence cũ/mới và owner browser/SSR/SQLite/runtime.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): review quyền, transaction/cleanup và giới hạn chứng minh MySQL.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): review mutation lock/cancellation/focus và contract frontend.

### 8. Đề xuất bàn giao

Có thể bàn giao UC21-01/02 để review phần chức năng đã kiểm. Nếu bắt buộc nghiệm thu đồng thời MySQL trước merge, tiếp theo là **UC21-QA-DB — fixture và concurrency tests MySQL**; hiện chưa đủ bằng chứng để đóng mục này. Không tự triển khai task tiếp theo hoặc merge.

Vì UC21-02 còn chưa commit, đề xuất commit chung source và QA: `feat(catalog): add room image primary selection and deletion`. Lấy đầy đủ working-tree report thay vì chỉ phần đã staged từ trước; loại `.env`, upload, logs và build artifacts.

## UC21-QA-DB-01 — Owned database harness và smoke

Ngày kiểm chứng 21/11/2026, Asia/Saigon. Owner đã duyệt Mini Plan trước khi provision. Đây là DB-01, chưa phải DB-02 concurrency.

### 1. Kết quả

**TECHNICAL PASS cho DB-01**: runner tạo schema/user riêng, guard xác thực target, smoke migrate/fixture/GET Host/storage trên **MariaDB 10.11.16**, rồi cleanup. Không gọi kết quả này là MySQL 8.4 hoặc concurrency PASS.

Baseline HEAD vẫn `9f2a161`; working tree gồm UC21-02/QA chưa commit và báo cáo merge staged từ trước. Giữ nguyên các thay đổi này. Không sửa `.env`, source nghiệp vụ, shared TestCase hoặc guard SQLite cũ.

### 2. Exact file scope

Tạo 5 file:
- `backend/tests/Support/Uc21MySqlEnvironment.php`: target/config/server/account/grant guard.
- `backend/tests/Support/Uc21MySqlTestCase.php`: opt-in trước boot, storage riêng.
- `backend/tests/Support/RunUc21MySqlTests.ps1`: provision, child process, evidence, cleanup và fingerprint.
- `backend/tests/Feature/Catalog/Uc21RoomImagesDatabaseTest.php`: smoke trên schema mới, InnoDB, fixture committed/observer độc lập, GET production và storage.
- `backend/tests/Unit/Uc21MySqlEnvironmentTest.php`: guard raw input; không boot database.

Sửa `docs/TV1/UC21-REPORT.md`, `docs/TV1/README.md`. Không tạo race worker/concurrency tests trong DB-01. Không sửa migration/seeders, Model, frontend, Auth, source TV3 hoặc database guards dùng chung. Không stage/commit/push.

Runner dùng PHP nhúng trong PowerShell và ghi bản chạy vào thư mục ignored `.agent-workspace/uc21/{run_id}`; đây là artifact runtime, không thêm script production. Child credentials sinh ngẫu nhiên chỉ truyền qua environment, không đặt trong argv/manifest. SQL exceptions không xuất SQL/credentials; output test được redacted trước lưu.

### 3. Verification và counts

| Command | Kết quả |
| --- | --- |
| `php vendor/phpunit/phpunit/phpunit tests/Unit/Uc21MySqlEnvironmentTest.php tests/Feature/Catalog/Uc21RoomImagesDatabaseTest.php` với môi trường SQLite thường | Exit 0; 1 unit PASS, 14 assertions; 1 DB smoke SKIP trước boot vì thiếu opt-in |
| `./backend/tests/Support/RunUc21MySqlTests.ps1 -Provision` | Exit 0; MariaDB smoke 1 test / 9 assertions; cleanup PASS |
| Runner không có `-Provision` | Exit 1 như mong đợi, từ chối trước tạo artifact/kết nối |
| `-Provision -InjectFailureAfterSchema` | Exit 1 như mong đợi, schema/user remaining 0, cleanup errors rỗng |
| `-Provision -InjectTestFailure` | Exit 1 như mong đợi; test cố ý fail sau fixture writes; schema/user/storage được dọn |
| `php artisan test --compact` với SQLite memory | Exit 0; **2749 passed, 477 skipped, 11794 assertions**, 71.95 giây |
| PHP lint 4 file PHP mới, diff/cached diff check | PASS |

Frontend không đổi: không lặp Node/lint/build; kết quả UC21-02/QA trước đó vẫn được phân biệt ở các mục trên. Test MariaDB riêng không bị tính vào số PASS SQLite. Một smoke ban đầu FAIL vì truyền `APP_CONFIG_CACHE=''` khiến Laravel coi backend directory là config cache; đã sửa child environment dùng `false` để bỏ biến, rerun PASS. Cleanup/preservation của lần lỗi cũng được kiểm và đạt.

Evidence final runner:
- Positive: `.agent-workspace/uc21/4e5ca067b8804efd94175b1e1d36cfe9/`.
- Negative after schema: `.agent-workspace/uc21/7ccea54a6eff495b884ac366bb6a6c49/`.
- Negative test failure: `.agent-workspace/uc21/9b58f437dd3740728ee493e880249544/`.
- Full SQLite log ngoài repo: workspace Stayhub-mnm `uc21-db01-full.log`.

### 4. Isolation và contract

Schema chính xác `stayhub_uc21_{guid32}_test`, username `uc21_{guid16}@127.0.0.1`, password 64 hex. Runner chỉ dùng admin hiện có để provision/cleanup; không đoán quyền ứng dụng hoặc tự xin grant global. Kiểm collision trước CREATE, từ chối schema có sẵn. Grant trên literal schema đã escape underscores, không WITH GRANT OPTION.

Test guard kiểm opt-in, APP_ENV testing, driver mysql, host/port, schema/user, URL/socket overrides, cache/session array, queue sync và không config cache. Sau boot kiểm effective config trước mở PDO; trước migration/write kiểm SELECT DATABASE/CURRENT_USER/version/hostname/server_id và SHOW GRANTS để chặn quyền global/foreign-schema. Shared Tests\TestCase vẫn kiểm schema suffix `_test` cùng explicit opt-in; không sửa guard.

Migration hiện có chỉ chạy trên schema test trống. Không dùng migrate:fresh/refresh hoặc seed. Smoke tạo fixture tổng hợp và kiểm row bằng connection ID khác ở autocommit; không tự nhận đó là lock/race test. Storage được override vào `{run_directory}/public`, không dùng Storage fake hoặc storage dev.

Cleanup DB kiểm lại run ownership/server identity và không còn kết nối test trước DROP schema/user. Cleanup storage dùng PowerShell LiteralPath, verify absolute directory thuộc run và từ chối reparse points. Giữ evidence, xóa storage test. Mỗi positive/negative run cuối xác nhận database dev `stayhub`, root `.env` và toàn bộ file public storage dev có fingerprint trước/sau bằng nhau; không restore/ghi đè nếu fingerprint thay đổi.

### 5. AC DB-01

| ID | Kết quả | Evidence |
| --- | --- | --- |
| DB01-01 — Guard từ chối target sai trước write | PASS | Unit 14 assertions, no opt-in smoke skip, runner no-Provision reject |
| DB01-02 — Schema/user/storage độc lập | PASS | Scoped grants, effective target guard, InnoDB smoke/observer/storage |
| DB01-03 — Cleanup khi success và failure | PASS | Positive + after-schema + injected-test-failure; schema/user remaining 0, storage removed khi đã tạo |
| DB01-04 — Dev/env/uploads được giữ | PASS | preservation.json có cả 3 cờ true trong các final runs |
| DB01-05 — Suite thường không mở DB MariaDB mới | PASS | Full SQLite 2749 PASS / 477 skipped; dedicated smoke skip trước boot |

### 6. Giới hạn

Chưa có worker race, barrier, lock waiting hoặc thử cạnh tranh upload/chọn chính/xóa; các mục DB21-03/04/05 trong plan tổng thể còn ở DB-02. Runner audit MariaDB 10.11 local, chưa tương thích runner Docker MySQL 8.4; tên MySql theo Laravel driver. Chưa inject crash/kill admin runner ở giữa SQL hoặc chứng minh cleanup sau mất điện; negative controls là lỗi đồng bộ có chủ đích. Nếu database/storage dev đang bị owner thay đổi trong lúc test, fingerprint có thể fail; runner không tự phục hồi dữ liệu.

### 7. Skills thực sự áp dụng

- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md): xác nhận MariaDB thực, scoped user/schema, privilege/identity checks và preservation.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): negative controls, evidence trước/sau, tách SQLite skip/MariaDB smoke/concurrency chưa kiểm.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): Laravel bootstrap/connection guard, migration và production GET trong smoke.

### 8. Bàn giao và commit

Tiếp theo đề xuất **UC21-QA-DB-02 — workers và concurrency tests**, không tự triển khai. Commit DB-01 đề xuất: `test(catalog): add isolated MariaDB harness for room image QA`. Stage đúng 5 harness/test files và bản báo cáo đầy đủ khi muốn commit riêng DB-01; source UC21-02 vẫn là thay đổi khác chưa commit. Không đưa `.agent-workspace`, credentials, `.env` hoặc logs vào commit.

## UC21-QA-DB-02 — Race workers và concurrency evidence

Ngày kiểm chứng 21/11/2026, Asia/Saigon. Mini Plan đã được owner duyệt trước triển khai. Baseline HEAD `9f2a161` cùng working tree UC21-02/QA/DB-01 chưa commit được giữ nguyên.

### 1. Kết quả

**TECHNICAL PASS cho 8 ca concurrency được kiểm trên MariaDB 10.11.16/InnoDB.** Không có lỗi production cần sửa. Mỗi ca có 2 PHP workers/kết nối khác nhau, bằng chứng lock wait thực từ server, snapshot cũ của B và trạng thái commit/file sau khi cả hai hoàn tất. Không gọi đây là MySQL 8.4 acceptance hoặc cam kết cho mọi lịch xen kẽ có thể có.

### 2. File tạo/sửa

Tạo:
- `backend/tests/Support/Uc21ImageRaceProtocol.php`
- `backend/tests/Support/Uc21ImageRaceWorker.php`
- `backend/tests/Feature/Catalog/Uc21RoomImagesConcurrencyTest.php`
- `backend/tests/Unit/Uc21ImageRaceProtocolTest.php`

Sửa: `backend/tests/Support/RunUc21MySqlTests.ps1`, `docs/TV1/UC21-REPORT.md`, `docs/TV1/README.md`.

`Uc21MySqlTestCase.php` trong scope đọc/sửa dự kiến được tái sử dụng nguyên trạng, không cần đổi. Không đổi source nghiệp vụ, SQLite guards cũ, frontend, migration/seeder, `.env`, database hoặc ảnh dev. Không stage/commit/push, giữ phần báo cáo đã staged từ trước.

### 3. Test commands và counts

| Command | Result |
| --- | --- |
| `./backend/tests/Support/RunUc21MySqlTests.ps1 -Provision -Suite Concurrency` | Exit 0; **8 tests / 163 assertions** trên MariaDB, không skipped |
| Cùng runner `-Suite Smoke` | Exit 0; DB-01 smoke vẫn 1 test / 9 assertions |
| Cùng runner `-Suite Concurrency -SuppressLockObservation` | Exit 1 đúng dự kiến; missing observed barrier không được tính PASS |
| Cùng runner `-Suite Concurrency -InjectWorkerFailure` | Exit 1 đúng dự kiến; worker B thoát lỗi, attempt barrier không tới, worker còn lại được dừng |
| `php vendor/phpunit/phpunit/phpunit tests/Unit/Uc21ImageRaceProtocolTest.php tests/Feature/Catalog/Uc21RoomImagesConcurrencyTest.php` với SQLite thường | Exit 0; unit 8 assertions PASS, 8 race cases SKIP trước boot |
| PHP lint 4 file PHP mới, diff/cached diff checks | PASS |

Full backend thường `php artisan test --compact`: exit 0, **2750 passed, 485 skipped, 11802 assertions**, 143.44 giây trên SQLite memory. 8 race cases mới skip trước boot khi thiếu opt-in; không coi skipped là database evidence. Log ngoài repo: workspace Stayhub-mnm `uc21-db02-full.log`. Frontend không đổi nên không lặp Node/lint/build; dẫn lại evidence UC21-02/QA, không báo là vừa chạy.

Final positive evidence: `.agent-workspace/uc21/e9afea220a9c4b31adc9678f9d69e12d/` (8 tests/163 assertions). Positive trước bổ sung timestamps: `d5568698600a421ba18eb5764ae6f155` (8 tests/115 assertions). Smoke regression: `874cca0190a2485ab10b466fc8b277a5`.

Negative controls: missing observation `e5fe51c8a83e4c52b301164e786ba878`; worker failure `6b928ba837684080a076f7b42b1d2b95`. Hai run này có exit 1, `cleanup_errors=[]`, schema/user remaining 0 và fingerprint database/env/storage dev bằng nhau. Run root chứa case evidence, `workersStopped.json`, `observed.json` khi có, `tests.txt`, `result.json`, `cleanup.json`, `storage-cleanup.json` và `preservation.json`; không chứa password sinh ngẫu nhiên.

### 4. Production path và protocol

Workers bootstrap Laravel qua guard DB-01, cùng scoped test account và run storage. Repository test subclass chỉ đặt barrier quanh các lời gọi `parent` và inject exception sau write ở ca rollback; không sao chép thuật toán upload/primary/delete. Gọi `HostRoomImageService` production với DTO thật.

A dừng sau khi khóa Property/Room nhưng trước thay đổi ảnh. B mở REPEATABLE READ và đọc ảnh không khóa trước khi gọi lock Property; ghi connection ID và số ảnh snapshot cũ. Runner admin chỉ SELECT lock metadata, lọc cả hai thread theo đúng user và schema của run; không cấp PROCESS/global privileges cho test account. Chỉ khi `INNODB_LOCK_WAITS` báo đúng waiting/blocking pair mới ghi `observed.json` và test cho A tiếp tục. Test so sánh thời điểm attempt/observe/release/results; PID, connection ID khác nhau, transaction level sau Service là 0.

Protocol scope thư mục `case-{guid32}`, message names allowlist, publish JSON qua temp/rename, từ chối ghi đè/path traversal và chờ có timeout. Test cuối dừng owned workers trong finally; runner DB-01 kiểm không còn test connections trước cleanup schema/user. Các negative controls không dựa vào việc hết một khoảng sleep rồi giả định có tranh chấp.

### 5. AC và ca kiểm

| ID / ca | Evidence / trạng thái |
| --- | --- |
| DB02-01 — Process/connection độc lập | PASS: PID/CONNECTION_ID khác nhau, cùng guard/manifest/scoped account |
| DB02-02 — Chờ khóa thật và reread | PASS: đủ 8 cặp INNODB_LOCK_WAITS, B đọc snapshot trước khi chờ, release chỉ sau observed |
| DB02-03a — Upload từ 19 ảnh | PASS: A 201, B 409, tổng 20, files khớp rows |
| DB02-03b — Hai primary khác nhau | PASS: 3 ảnh, A/B đều chọn ảnh khác primary ban đầu; primary cuối là đích B |
| DB02-03c — Primary/delete cùng ảnh, hai thứ tự | PASS: chọn rồi xóa 200/200; xóa rồi chọn 200/404; primary cuối hợp lệ |
| DB02-03d — Delete/upload hai thứ tự | PASS: số row/file và primary cuối hợp lệ |
| DB02-04 — Rollback upload/delete | PASS: inject sau insert hoặc sau delete/primary update; 500 expected ở A, B tiếp tục đúng trạng thái đã rollback; files khớp rows |
| DB02-05 — Missing observer/worker error | PASS negative controls: suite FAIL, workersStopped true, cleanup không còn tài nguyên |
| DB02-06 — Dev/env/storage giữ nguyên | PASS: preservation.json có cả 3 cờ true, owned storage removed |

### 6. Giới hạn

Đây là controlled interleavings có lock observer, không phải stress test hoặc chứng minh mọi tình huống crash/mất điện. Rollback dùng exception test sau lời gọi persistence thật; không sửa production để chèn hook. Đã kiểm engine MariaDB 10.11.16 local; chưa chạy Docker MySQL 8.4. Thời gian worker/barrier có giới hạn, máy quá tải có thể làm suite FAIL thay vì bỏ qua/cho PASS. Không mở thêm UI hoặc nghiệp vụ trong task này.

### 7. Skills áp dụng

- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md): isolation, lock wait metadata, snapshot/reread, cleanup và fingerprint.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): worker boot/DI, Service/DTO/Repository production, root transaction và rollback evidence.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): AC mapping, barriers/negative controls, timestamps và phân biệt MariaDB races với SQLite skipped.

### 8. Bàn giao và commit

Đề xuất review diff/bàn giao PR UC21; không tự merge hoặc bắt đầu UC tiếp theo. Commit DB-02 đề xuất: `test(catalog): verify room image concurrency on MariaDB`. Worker/protocol/test mới phụ thuộc harness DB-01 chưa commit; phải đưa DB-01 vào lịch sử trước hoặc commit cùng phạm vi test. Báo cáo có cả nội dung staged từ trước và mới trong working tree; lấy bản đầy đủ. Không đưa artifacts/credentials/.env/uploads/logs vào Git.
