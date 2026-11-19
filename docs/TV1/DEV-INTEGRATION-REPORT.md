# TV1 — DEV-INTEGRATION-01

Ngày kiểm chứng: 17/11/2026, Asia/Saigon.

## Mục tiêu và trạng thái

Giải quyết conflict trong lần tích hợp `dev` đang có, giữ baseline đã nghiệm thu và chuẩn hóa phần Public Catalog frontend còn theo cấu trúc cũ. Đây là task tích hợp/refactor, không triển khai thêm UC.

**TECHNICAL PASS** cho phạm vi source, automated tests, lint và build. Owner review và nghiệm thu browser/MySQL sau tích hợp vẫn chưa được xác nhận ở lượt này.

- Nhánh: `feature/tv1-uc04-uc05-property-catalog`.
- HEAD trước/sau: `907aac61200a519a4cf6110e386048266afaef2a`.
- Incoming `MERGE_HEAD`: `8913c5fcb326f83aac47395f0fba4c4ab7c37d81`.
- `git fetch origin dev` thành công; `origin/dev` trùng incoming commit trên.
- Trước task: đang merge dở, 4 conflict dạng add/add và nhiều thay đổi từ `dev` đã staged. Không khởi tạo lần merge khác hoặc ghi đè toàn bộ checkout.
- Sau task: không còn unmerged entry hoặc conflict marker trong source; lần merge hiện tại **chưa commit**. Không push, đổi nhánh hoặc merge ngược về `dev`.
- Sao lưu trước sửa tại `.agent-workspace/dev-integration-37201aa5aed44a00b01a6df62de21910/`, kèm manifest SHA256. Thư mục này được Git ignore, không chứa `.env`, database hoặc upload.

Đã đối chiếu `AGENTS.md`, `ARCHITECTURE.md`, `docs/TV3/BASELINE_HANDOVER.md`, hướng dẫn frontend, phân công/use case/ERD và code liên quan. Mini Plan đã được người dùng duyệt trước triển khai.

## Cách giải quyết conflict và bảo toàn baseline

Incoming `dev` đã chuẩn hóa Auth/Profile/Public Catalog, Host Property/RoomType/Room và Invoice/Review. Không thực hiện lại các luồng này.

Ở 4 file conflict, đối chiếu stage 2 với stage 3 trước khi giữ nội dung stage 3. Stage 3 được kiểm tra trùng `origin/dev`; bản test incoming bổ sung 204 dòng, không xóa test cũ.

- Controller gọi CatalogValidator, typed Request DTO và QueryService; trả Common response thay Resource cũ.
- QueryService dùng CatalogRepository và CatalogMapper; không mang lại query Eloquent từ bản cũ.
- Route giữ 2 endpoint Public và toàn bộ endpoint Host mới từ `dev`.
- Test giữ các kiểm tra nghiệp vụ sẵn có và bổ sung exact response contract, content negotiation/error order, Symfony mapping không phát sinh query.
- Xóa 4 Request/Resource cũ do nhánh UC4–UC5 thêm vào nhưng không tồn tại trên baseline mới. Không còn consumer/import trỏ tới các lớp này.

Sau dọn, `git diff --quiet origin/dev -- backend` trả exit 0: toàn bộ backend tracked khớp `dev`, kể cả migrations, dependency lock và cấu hình đã được nhập từ lần merge trước task.

## Thay đổi trực tiếp của task

Các thay đổi đã staged từ lần tích hợp trước khi task bắt đầu được giữ nguyên. Bảng dưới chỉ liệt kê các file được xử lý trực tiếp trong task này.

| File | Thao tác và lý do |
| --- | --- |
| `backend/app/Http/Controllers/Catalog/PublicPropertyController.php` | Resolve theo baseline `dev`, giữ Controller → Validator/DTO → QueryService |
| `backend/app/Services/Catalog/PublicPropertyQuery.php` | Resolve theo baseline `dev`, giữ Repository/Mapper |
| `backend/routes/api/catalog.php` | Resolve, giữ cả route Public và Host |
| `backend/tests/Feature/Catalog/PublicPropertyTest.php` | Resolve, giữ bộ test incoming đầy đủ |
| `backend/app/Http/Requests/Catalog/PublicPropertyListRequest.php` | Xóa lớp cũ đã được CatalogValidator/DTO thay thế |
| `backend/app/Http/Requests/Catalog/PublicPropertyDetailRequest.php` | Xóa lớp cũ đã được CatalogValidator/DTO thay thế |
| `backend/app/Http/Resources/Catalog/PublicPropertyResource.php` | Xóa lớp cũ đã được CatalogMapper/Response DTO thay thế |
| `backend/app/Http/Resources/Catalog/PublicRoomResource.php` | Xóa lớp cũ đã được CatalogMapper/Response DTO thay thế |
| `frontend/src/api/publicPropertyApi.js` | Tạo transport cho các thao tác hiện có qua shared Axios |
| `frontend/src/mappers/catalogMapper.js` | Tạo mapper thuần cho list/detail và projection Search hiện có |
| `frontend/src/services/catalog.js` | Sửa factory nhận domain API, dùng mapper; giữ helper và lifecycle loader |
| `frontend/src/services/propertyService.js` | Sửa wiring API/mapper và thêm factory có DI để kiểm tra production entry point |
| `frontend/tests/catalog.test.js` | Cập nhật wiring để kiểm tra qua domain API và mapper thật |
| `frontend/tests/publicPropertyService.test.js` | Tạo 6 test tích hợp service/API/mapper, chỉ mock shared transport |
| `docs/TV1/DEV-INTEGRATION-REPORT.md` | Tạo báo cáo này |

Frontend hiện đi theo Page/Component → propertyService/catalog service → publicPropertyApi → shared Axios; response qua catalogMapper. Không sửa Page/Component, router, Auth context hoặc shared Axios trong task này.

Mapper giữ decimal string, giá `0.00`, giá/rating null, collection rỗng, metadata và links. Search giữ shape array hoặc `{data: array}` đang dùng; response thành công sai cấu trúc bị từ chối thay vì coi là danh sách rỗng. API giữ rejection identity và AbortSignal; service vẫn có normalization lỗi Catalog như trước. Không tự retry request.

## Contract và giới hạn phạm vi

- `GET /api/public/properties`: chỉ page/per_page, không chuyển bộ lọc tìm kiếm thành danh sách không lọc.
- `GET /api/public/properties/{property}`: giữ data envelope, phòng/ảnh/tiện nghi hợp lệ và privacy 404.
- Property public chỉ ACTIVE; Room phải ACTIVE và RoomType cùng Property. Giá là giá tham khảo, không phải báo giá Booking.
- Giữ whitelist public, thứ tự ảnh, query batching, validation, quyền Host/ownership, session/CSRF và transaction của baseline.
- DEV mock yêu cầu đồng thời `DEV === true` và flag chuỗi `'true'`; lỗi API không fallback sang mock. Giữ source identity để guard Review/Booking.
- `/api/public/properties/search` vẫn là điểm gọi hiện có chờ contract/backend Search; route chưa được đăng ký trên snapshot này. Refactor chỉ chuyển transport sang API layer, không triển khai Search/Availability hoặc đổi sang endpoint list. 404 vẫn trả trạng thái unavailable.
- UC21 upload/chọn chính/xóa ảnh phòng chưa triển khai. Gallery đọc không được tính là UC21 hoàn thành.
- Auth/Profile và Host Catalog đã có baseline mới; không tạo thêm lớp chỉ để đủ sơ đồ. Invoice/Review và các dependencies của TV3 được giữ nguyên.

## Kiểm chứng thực tế

| Kiểm tra | Kết quả mới trong lượt này |
| --- | --- |
| `git fetch origin dev` | Exit 0; ref mới nhất trùng MERGE_HEAD |
| `composer install --no-interaction --prefer-dist --no-scripts` | Exit 0; cài 5 package thiếu theo lock, không update package hoặc sửa lock |
| PHP lint 4 file conflict | PASS, không lỗi cú pháp |
| `php artisan test --filter=PublicPropertyTest` | PASS: 44 tests / 201 assertions |
| `php artisan test --compact` | PASS: 1165 tests / 6668 assertions, 51.16 giây |
| Node Catalog và production property service | PASS: 17 tests |
| `node --experimental-test-module-mocks --test tests/*.test.js` | PASS: 160 tests, 0 fail/skip |
| `npm run lint` | Exit 0; 0 lỗi, 3 Fast Refresh warnings có sẵn ở badge/button/sidebar |
| `npm run build` | Exit 0; 3339 modules, còn warning chunk >500 kB và plugin timing |
| `php artisan route:list --path=api/public` | 3 route: list, detail, reviews |
| `php artisan route:list --path=api/host` | 22 route Host của Catalog/Invoice/Review |
| `git diff --check`, `git diff --cached --check` | PASS |
| `git diff --name-only --diff-filter=U` và scan conflict marker | Không còn conflict |
| Backend so với `origin/dev` | Không có diff |
| Browser/live MySQL/concurrency | NOT VERIFIED trong lượt này |

Backend tests dùng `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` rỗng, cache/session array và từ chối config cache trước full suite. Không migrate/seed/reset database dev; không sửa `.env`, dữ liệu người dùng hoặc upload.

Lần chạy ban đầu của fetch/Composer bị giới hạn quyền ghi/mạng trong sandbox; Node test bị `spawn EPERM`; build trong sandbox gặp `spawn EPERM` cùng lỗi load native Tailwind. Chạy lại bằng quyền được cấp đều thành công. Không sửa code hoặc dependency version để né các giới hạn này. Composer dùng `--no-scripts`, không chạy script setup/migrate.

## Acceptance criteria

| ID | Trạng thái | Bằng chứng |
| --- | --- | --- |
| AC1 — Đối chiếu đúng dev | PASS | Fetch thành công, origin/dev = MERGE_HEAD |
| AC2 — Resolve không mất baseline | PASS | 4 file khớp incoming, full backend khớp dev và 1165 test qua |
| AC3 — Dọn cấu trúc cũ | PASS | 4 lớp không dùng đã xóa, không còn import |
| AC4 — Frontend có API/mapper đúng trách nhiệm | PASS | Production exports chạy qua các lớp thật, 17 test Catalog/service qua |
| AC5 — Giữ contract và guard | PASS | Exact backend contracts, pagination/null/decimal, HTTP/cancel identity và DEV gate được kiểm tra |
| AC6 — Kiểm tra hồi quy | PASS | Full backend, full Node, lint/build và diff checks qua |
| AC7 — Git và dữ liệu an toàn | PASS | Không còn unmerged entry; không commit/push/merge mới, không tác động database dev |

Không dùng kết quả SQLite/Node/build để tuyên bố browser E2E hoặc MySQL locking đã được nghiệm thu. Task tiếp theo đề xuất: kiểm thử thủ công các màn hình hiện có với API thật sau tích hợp, bằng tài khoản/dữ liệu test được cấp; cần phạm vi riêng trước khi triển khai UC khác.
