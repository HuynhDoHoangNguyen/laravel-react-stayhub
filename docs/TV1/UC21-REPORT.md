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
