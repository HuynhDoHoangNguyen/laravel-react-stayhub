# UC20 — Quản lý phòng

> **UC20-FINAL, 22/11/2026** — Nghiệm thu danh sách, thêm, chi tiết, sửa và đổi trạng thái phòng thử, kèm UI Host đã kiểm. Các ca MariaDB đồng thời, collation và lỗi runtime ngoài checklist vẫn mở. Xem mục [UC20-FINAL](#uc20-final--rà-soát-và-chốt-phạm-vi-nghiệm-thu).

## 1. UC20-01 — Mục tiêu và kết quả

- Task được duyệt: audit/QA code hiện có, chỉ tạo báo cáo này và cập nhật README TV1. Không sửa production hoặc tests.
- Baseline: branch `feature/tv1-uc21-room-images`, HEAD `1d6dcdb`, working tree sạch. Khởi chạy kiểm thử ngày 21/11/2026; tiếp tục và bàn giao ngày 22/11/2026 (Asia/Saigon).
- Backend có list/create/detail/update/status; Frontend có danh sách/thêm trong chi tiết cơ sở và detail/edit/status tại `/host/rooms/:roomId`, tích hợp ảnh UC21.
- Automated output: focused Backend 321 PASS; full Backend 2753 PASS/485 skipped; focused Node 21 và full Node 437 PASS. Giới hạn thu hồi exit code PHP ghi riêng tại mục 3.
- Owner browser QA: **PASS do owner báo “Đạt các mục trên”** cho thêm phòng thử, mở chi tiết, sửa tên/giá/sức chứa/loại phòng, trùng số phòng, đổi trạng thái phòng thử không có booking/bảo trì, refresh, mobile và Tab/Enter. Không phải agent browser automation.
- Trạng thái: audit code/evidence và owner QA chức năng đã thực hiện; chưa CLOSED toàn UC20. Rủi ro thứ tự khóa liên module và runtime gaps vẫn mở.

Nguồn đối chiếu: AGENTS.md, ARCHITECTURE.md, README.md, hướng dẫn frontend, use case §4.3, phân công TV1/TV2, ERD rooms/room_types, mockup Room Management/Form; migration schema chung, model/enums, catalog và các consumer Booking. Seeder chỉ đọc, không chạy lại.

## 2. Scope file và bản đồ implementation

| File ghi | Hành động |
| --- | --- |
| `docs/TV1/UC20-REPORT.md` | Tạo báo cáo audit/contract/evidence/gaps |
| `docs/TV1/README.md` | Thêm trạng thái UC20 |

Không sửa/xóa source, cấu hình, schema, fixtures hoặc dữ liệu nghiệp vụ; không chuyển nhánh, stage/commit/push/merge.

| Lớp đã audit | Thành phần |
| --- | --- |
| Route/HTTP | `backend/routes/api/catalog.php`; `backend/app/Http/Controllers/Catalog/HostRoomController.php` |
| Validation/DTO | `backend/app/Validators/Catalog/CatalogValidator.php`; HostRoomListQuery, HostRoomDetailQuery, CreateHostRoomRequest, UpdateHostRoomRequest, ChangeHostRoomStatusRequest dưới `backend/app/DTOs/Catalog/Requests/` |
| Workflow/query | `backend/app/Services/Catalog/HostRoomService.php`, `HostRoomQueryService.php` |
| Persistence/mapping | `backend/app/Repositories/Catalog/CatalogRepository.php`; `backend/app/Mappers/Catalog/CatalogMapper.php`; HostRoomResponse/HostRoomDetailResponse DTO |
| Schema/model | `backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php`; `backend/app/Models/Room.php`, RoomType/Property; RoomStatus |
| Frontend | `frontend/src/pages/host/HostRoomDetailPage.jsx`; `frontend/src/components/catalog/HostRoomList.jsx`, HostRoomCreateForm/HostRoomEditForm/HostRoomStatusForm |
| Domain integration | `frontend/src/services/hostRoomService.js` → `frontend/src/api/hostRoomApi.js` → shared Axios; `frontend/src/mappers/hostRoomMapper.js` |
| Dependencies | UC19 loại phòng, UC21 ảnh; TV2 Booking/Availability chỉ đọc để đối chiếu, UC22/23 không mở rộng |

## 3. Kiểm thử và giới hạn evidence

Backend chạy trong backend, Node/lint/build trong frontend. PHP tests dùng SQLite `:memory:` theo phpunit config và safety guards của suites. Logs tại `C:/Users/Tan Phuoc/Documents/ChatGPT/Stayhub-mnm/` ngoài repo.

| Command | Result / exit |
| --- | --- |
| `php artisan test --filter='HostRoomListTest\|HostRoomCreateTest\|HostRoomDetailTest\|HostRoomUpdateTest\|HostRoomStatusTest'` | Log `uc20-01-focused.log`: **321 passed, 1203 assertions**, 83.27s; không có failed trong summary. Exit code tiến trình cũ không thu hồi được sau phiên công cụ hết hạn |
| `php artisan test` | Log `uc20-01-backend-full.log`: **2753 passed, 485 skipped, 11826 assertions**, 309.21s; exit code tiến trình cũ không thu hồi được, không ghi exit 0 giả |
| `node --experimental-test-module-mocks --test tests/hostRoomService.test.js` | Exit 0; **21/21 PASS**, log `uc20-01-node-focused.log` |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0; **437/437 PASS**, 0 skipped; log `uc20-01-node-full.log` |
| `npm run build` | Exit 0; Vite 8.3.2, 3400 modules; bundle >500 kB warning |
| `npm run lint` | Chạy lại ngày 22/11: exit 0, 0 errors, 3 Fast Refresh warnings cũ ở badge/button/sidebar |
| `git diff --check` | Exit 0; report untracked kiểm whitespace và skill link targets riêng, PASS |

Không chạy lại full PHP suite chỉ để thay bằng chứng summary đã hoàn tất thành số liệu mới; ghi rõ giới hạn exit metadata. Skipped không được tính PASS. Node là logic/service/transport fixtures, build là compile; không thay evidence browser. Không reset/seed hoặc chạy MySQL/MariaDB trong audit này.

Backend suites bao phủ role/account/ownership/canonical IDs, payload/query/file rejection, price/capacity boundaries, duplicate scope, room type foreign/missing, mapper/query count, rollback/no-op, dữ liệu liên quan và matrix trạng thái/ngày qua endpoint production. Tests status có ngày giả lập; không dùng ngày test làm lịch thực tế.

## 4. Contract, nghiệp vụ và findings

| Endpoint prefix `/api` | Contract hiện tại |
| --- | --- |
| `GET /host/properties/{property}/rooms` | 200 success/message/data; không query/pagination; sort room_number rồi id |
| `POST /host/properties/{property}/rooms` | 201; type/number/name/capacity/price bắt buộc, description optional nullable; tạo ACTIVE |
| `GET /host/rooms/{room}` | 200; room fields + property id/name/status + public images + assigned amenities |
| `PUT /host/rooms/{room}` | 200; năm field bắt buộc như create, description bỏ qua được normalize null; không đổi Property/status |
| `PATCH /host/rooms/{room}/status` | 200; chỉ JSON status ACTIVE/INACTIVE/MAINTENANCE; raw enum không được trim/coerce thành hợp lệ; conflicts 409 |

- Sanctum/account-active middleware; validator/service authorize Host; repository scope Property owner. Input lỗi 422, role 403, guest 401; privacy/canonical 404 theo precedence tests hiện có.
- Loại phòng phải thuộc Property; số phòng tối đa 50 ký tự, tên 255, mô tả 10000, capacity canonical integer 1..2147483647. Price là chuỗi decimal dương ≤9999999999.99, tối đa hai số lẻ, normalize qua BigDecimal; frontend không tính giá cuối Booking.
- Update chỉ sửa Room hiện tại, không đổi Booking/Invoice snapshot, images/amenities/maintenance; tests kiểm bảo toàn các bảng liên quan. Schema price decimal(12,2); Room cast decimal:2.
- Status no-op không save/log hoặc đọc blocker facts. Khi đổi sang INACTIVE/MAINTENANCE, CHECKED_IN luôn chặn; PENDING/CONFIRMED có ngày sai hoặc checkout > ngày ứng dụng thì chặn. Booking đã kết thúc không tự bị sửa.
- Chuyển ACTIVE hoặc MAINTENANCE→INACTIVE kiểm bảo trì: IN_PROGRESS hoặc SCHEDULED có ngày sai/đang hiệu lực [start,end) thì chặn. SCHEDULED tương lai không tự chặn ACTIVE trong rule hiện có. Không tự thay rule hoặc suy ra cập nhật lịch bảo trì.
- Service transaction, khóa Property→Room và facts liên quan, DTO/mapper projection và afterCommit log. Frontend đúng Service→API→Axios, pure mapper; CSRF/signal/timeout, mutation refs, abort/session/resource keys và unknown-outcome recovery hiện có.
- Type legacy lệch Property được trả null để không lộ type của cơ sở khác; sửa cho chọn type đúng. Không thêm DELETE Room.

| ID | Finding/gap | Hướng xử lý |
| --- | --- | --- |
| UC20-R01 | **Rủi ro concurrency từ static trace, chưa tái hiện deadlock**: `HostRoomService::changeStatus()` khóa Room rồi `lockRoomBookingFacts()` khóa Booking. `BookingWorkflowService::confirm()` TV2 khóa Booking rồi Room. Hai transaction có thứ tự khóa ngược; có khả năng deadlock nếu chạy đồng thời | Lập QA MariaDB cô lập và thống nhất lock ordering/retry với TV2 trước khi thay nghiệp vụ. Không kết luận đã xảy ra lỗi production, không sửa TV2 trong audit |
| UC20-G01 | Chưa có evidence InnoDB locks/races và collation số phòng trên MariaDB; SQLite không chứng minh các mục này | Task DB QA riêng; không dùng evidence DB UC21 thay thế |
| UC20-G02 | Owner đổi trạng thái phòng thử không có blockers; browser 409 booking/maintenance, mất phản hồi, CSRF/session lỗi và chuyển phòng mạng chậm chưa kiểm riêng | Backend matrix/Node PASS có giới hạn, giữ browser negative cases NOT VERIFIED |
| UC20-D01 | Mockup có `/host/rooms`, `/create`, `/:id/edit`; code dùng list/create trong chi tiết cơ sở và edit/status trong `/host/rooms/:roomId` | Giữ routes thực tế; không tự tạo route mới từ mockup |
| UC20-U01 | List/create/edit/status/detail còn styling cũ, khác UC18/19 | Lập UI slice riêng, bảo toàn ảnh UC21 và guards |

Không phát hiện lỗi chức năng mới được tái hiện trong các kịch bản automated và owner browser đã kiểm. R01 là rủi ro cụ thể cần theo dõi, không bị coi đã giải quyết vì SQLite PASS.

## 5. Acceptance criteria và owner checklist

| ID | Trạng thái / evidence |
| --- | --- |
| AC01 | PASS: baseline sạch, mapping code/contract/dependencies tại mục 1–2/4 |
| AC02 | PASS audit: scope owner, type/number/price/capacity được trace và có suites tương ứng |
| AC03 | PASS trong test engine: 321 focused tests, matrix status/date, rollback và related snapshots; concurrency R01/G01 vẫn mở |
| AC04 | Có đủ summaries/checks, PHP exit metadata không thu hồi được; lint/diff final ghi tại mục 3. Không tuyên bố every-command-exit-zero khi thiếu metadata |
| AC05 | PASS về báo cáo và browser checklist; gaps và bước tiếp theo được ghi riêng |

Owner đã xác nhận: thêm phòng thử; detail/sửa tên/giá/capacity/type; trùng số phòng; đổi trạng thái trên phòng thử không có booking/bảo trì; refresh, mobile, Tab/Enter. Không gán câu trả lời này thành xác nhận browser xung đột trên phòng phục vụ khách, thao tác UC21 hoặc mọi nhánh lỗi.

## 6. Dependencies, rủi ro và phần chưa kiểm

- Giữ TV2 Booking/Availability business rules. R01 cần evidence và phối hợp owner module nếu sửa.
- Ảnh UC21 đã tích hợp, tiện nghi đang chỉ hiển thị; mutation gán tiện nghi UC22 và lịch bảo trì UC23 không thuộc audit implement UC20.
- MariaDB/runtime negative cases chưa xác minh; không CLOSED toàn UC20 hoặc đề xuất merge vô điều kiện.
- Không sửa source nên chưa có regression mới; không bịa task fix khi chưa xác nhận vấn đề.

## 7. Skills thực sự áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): giữ scope hai file, xác nhận baseline/owner và dependencies TV2/UC19/21.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): trace AC tới tests, thu hồi logs sau gián đoạn, ghi rõ thiếu exit metadata và tách SQLite/Node/owner browser.

Không sửa source/schema nên không áp dụng skill triển khai Laravel/React/Database trong task này.

## 8. Bàn giao và đề xuất tiếp theo

Đề xuất **UC20-UI-01 — đồng bộ danh sách và form thêm phòng với UC18/19**, lập Mini Plan trước thực thi. R01/G01 giữ backlog DB QA riêng; không tự sửa code TV2 hoặc chuyển sang task khác.

Commit tài liệu đề xuất:

```text
docs(catalog): audit host room management and QA evidence
```

Không tự stage/commit/push/merge. Owner review báo cáo và quyết định mức nghiệm thu riêng với runtime gaps.

---

## UC20-UI-01 — Đồng bộ danh sách và form thêm phòng

### 1. Kết quả

Ngày 22/11/2026, triển khai trên nhánh `feature/tv1-uc21-room-images`, HEAD `1d6dcdb`. Phạm vi là khu **Danh sách phòng** trong trang chi tiết cơ sở Host.

- Danh sách có số lượng từ API, card có trạng thái bằng chữ và màu, thông tin loại phòng/sức chứa, giá theo đêm và nút **Xem chi tiết** ở chân card. Grid một cột trên mobile và hai cột ở màn hình rộng.
- Form tách số/tên phòng, lựa chọn loại phòng, sức chứa/giá và mô tả. Cảnh báo thiếu loại phòng hoặc tải lỗi nằm ngay dưới ô chọn, kèm lối sang khu Loại phòng và nút tải lại phù hợp.
- Automated checks đạt. Nghiệm thu browser của UI mới đang chờ xác nhận riêng.

### 2. Phạm vi file

| File | Thay đổi |
| --- | --- |
| `frontend/src/components/catalog/HostRoomList.jsx` | Header, card phòng, điều hướng chi tiết và các trạng thái danh sách |
| `frontend/src/components/catalog/HostRoomCreateForm.jsx` | Bố cục form, nhãn/lỗi trường, cảnh báo loại phòng và thanh hành động |
| `docs/TV1/UC20-REPORT.md` | Bổ sung kết quả và tiêu chí UI-01 |
| `docs/TV1/README.md` | Cập nhật tiến độ UC20 |

### 3. Kiểm thử

| Lệnh | Kết quả |
| --- | --- |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0, 437 PASS, 0 fail, 0 skipped. Log ngoài repo `uc20-ui01-node.log` |
| `npm run lint` | Exit 0, 0 lỗi và 3 cảnh báo Fast Refresh tại badge/button/sidebar |
| `npm run build` | Exit 0, 3400 modules. Vite báo bundle lớn hơn 500 kB |
| `git diff --check` | Exit 0. Kiểm khoảng trắng báo cáo riêng vì file đang untracked |

Node kiểm logic và service với transport fixture. Bằng chứng Backend dùng lượt audit UC20-01, do diff UI-01 chỉ gồm Frontend và tài liệu.

### 4. Hành vi cần bảo toàn

- Form vẫn gửi cùng tên trường và chuỗi giá decimal qua service hiện có. Có thể đến khu Loại phòng, tạo loại rồi tải lại lựa chọn trong form.
- Giữ điều kiện khóa lưu khi chưa có loại phòng hợp lệ, đang gửi hoặc chưa xác định được kết quả. Chỉ hiển thị nút cho phép gửi lại sau khi người dùng kiểm tra danh sách thành công.
- Giữ abort khi rời màn hình, ref guard chống submit trùng, lỗi 409/422 và liên kết lỗi với input. Focus vào Số phòng khi mở form và trở về nút Thêm phòng sau khi Hủy hoặc tạo thành công.
- Trạng thái phòng và số lượng trên UI lấy trực tiếp từ dữ liệu danh sách. Giá dùng hàm định dạng tiền sẵn có.

### 5. Tiêu chí nghiệm thu

| ID | Evidence và trạng thái |
| --- | --- |
| UI20-01-AC01 | Đã triển khai. Diff và build xác nhận header, card và link chi tiết |
| UI20-01-AC02 | Đã triển khai. Form đủ trường, nhãn, lỗi và trạng thái thiếu loại phòng |
| UI20-01-AC03 | Service regression PASS. Đã đối chiếu điều kiện tạo phòng và khôi phục qua source |
| UI20-01-AC04 | Đã triển khai responsive và focus. Chờ xác nhận trình duyệt |
| UI20-01-AC05 | Node/lint/build/diff PASS. Chờ owner nghiệm thu browser UI mới |

### 6. Phần chưa kiểm

Checklist browser gồm mở/đóng form, tạo phòng thử, lỗi trùng số, thiếu loại phòng và tải lại, tên/mô tả dài, mobile, Tab/Enter và focus sau Hủy hoặc lưu. Bằng chứng browser của UC20-01 thuộc giao diện trước UI-01. Ca mất phản hồi thực tế và rủi ro khóa liên module UC20-R01 vẫn thuộc phần theo dõi runtime.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) giúp giữ phạm vi bốn file đã duyệt và bảo toàn báo cáo audit có trước.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) hướng dẫn tổ chức UI theo dữ liệu thật, theme chung, trạng thái form và ngữ nghĩa bàn phím.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để kiểm diff, chạy các check Frontend và đánh giá tiêu chí theo bằng chứng.

### 8. Bàn giao

Commit đề xuất cho phần giao diện `style(catalog): align host room list and create form with property UI`. Hai file tài liệu đang chứa cả nội dung audit UC20-01. Bước tiếp theo trong lộ trình là UC20-UI-02 cho chi tiết và form sửa/đổi trạng thái.

---

## UC20-UI-02 — Đồng bộ chi tiết phòng, form sửa và đổi trạng thái

### 1. Kết quả

Ngày 22/11/2026, triển khai trên nhánh `feature/tv1-uc21-room-images`, HEAD `1d6dcdb`.

- Header chi tiết phòng hiển thị tên, số phòng, nhãn trạng thái và hai thao tác Sửa thông tin, Đổi trạng thái. Loại phòng, sức chứa và giá mỗi đêm nằm ngay dưới header. Thông tin cơ sở, mô tả, ảnh và tiện nghi có khu riêng.
- Form sửa dùng bố cục của form thêm phòng. Lỗi trường nằm cạnh input. Form đổi trạng thái đặt trạng thái hiện tại cạnh ô chọn trạng thái mới ở màn hình rộng. Nút xác nhận và Hủy nằm cùng hàng khi đủ chỗ.
- Phạm vi triển khai là Frontend và tài liệu, dùng API Backend đã kiểm ở UC20-01. Các kiểm tra kỹ thuật của UI-02 đạt. Owner acceptance trên phiên Host thật đang chờ.

### 2. Phạm vi file

| File sửa | Thay đổi |
| --- | --- |
| `frontend/src/pages/host/HostRoomDetailPage.jsx` | Header, tóm tắt, nút quay lại, phản hồi tải/lỗi/thành công và khu thông tin phòng |
| `frontend/src/components/catalog/HostRoomEditForm.jsx` | Nhóm trường, ô chọn loại phòng, lỗi trường và thanh thao tác |
| `frontend/src/components/catalog/HostRoomStatusForm.jsx` | Bố cục trạng thái hiện tại và mới, phản hồi lỗi và thanh thao tác |
| `docs/TV1/UC20-REPORT.md` | Ghi kết quả và giới hạn nghiệm thu UI-02 |
| `docs/TV1/README.md` | Cập nhật tiến độ UC20 |

Cả năm file thuộc scope đã duyệt. Báo cáo UC20 đang untracked do được tạo từ UC20-01. Working tree có sẵn diff của danh sách và form thêm phòng từ UI-01.

### 3. Kiểm thử

| Lệnh hoặc môi trường | Kết quả |
| --- | --- |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0, 437 PASS, 0 fail, 0 skipped |
| `npm run lint` | Exit 0, 0 lỗi, 3 cảnh báo Fast Refresh có sẵn tại badge, button và sidebar |
| `npm run build` | Exit 0, 3400 modules, cảnh báo JS bundle lớn hơn 500 kB |
| Chrome headless trên bản build, API fixture cục bộ | Exit 0. Điều hướng cơ sở → phòng → cơ sở, retry chi tiết, lỗi và trạng thái rỗng của loại phòng, 422/409, lưu và tải lại, khôi phục sau phản hồi 503, bàn phím và chiều rộng 1440/768/375/320 px |
| `git diff --check` | Exit 0. Báo cáo untracked kiểm khoảng trắng riêng |

Chrome thực thi bundle React và chặn request API tại trình duyệt để cấp dữ liệu fixture. Kết quả kiểm được bố cục, điều hướng, request payload và phản hồi UI. Laravel, Sanctum thật và MySQL chưa được kiểm lại trong UI-02. Dùng bằng chứng Backend từ UC20-01 vì diff UI-02 chỉ gồm Frontend và tài liệu.

### 4. Contract và hành vi

- Lưu thông tin tiếp tục dùng `PUT /api/host/rooms/{room}` với các trường phòng hiện có. Đổi trạng thái dùng `PATCH /api/host/rooms/{room}/status` với trường `status`. Cả hai tải lại chi tiết khi lưu thành công.
- Form sửa chọn lại loại hiện tại sau khi các tùy chọn tải xong. Khi thiếu loại, form dẫn về khu Loại phòng của cơ sở. Giá hiển thị qua formatter hiện có và giữ giá trị decimal khi gửi.
- Giữ cơ chế chống gửi trùng, hủy request khi rời trang, phản hồi lỗi 422 và thông báo 409 do Backend trả về khi đổi trạng thái. Với kết quả chưa xác định, phải kiểm tra chi tiết thành công trước khi có thể cho phép gửi lại.
- Chức năng quản lý ảnh do `HostRoomImageManager` đảm nhiệm. Tiện nghi hiển thị từ chi tiết phòng hiện tại.

### 5. Tiêu chí nghiệm thu

| ID | Trạng thái và bằng chứng |
| --- | --- |
| UI20-02-AC01 | PASS trên Chrome fixture. Hai nút cùng hàng ở desktop, trang không tràn ngang tại 768/375/320 px kể cả tên phòng dài. Mở chi tiết từ danh sách và quay lại được |
| UI20-02-AC02 | PASS trên Chrome fixture. Kiểm mặc định loại phòng, retry, danh sách loại rỗng, lỗi 422 gắn với input, payload PUT và tải lại sau khi lưu |
| UI20-02-AC03 | PASS trên Chrome fixture. Kiểm trạng thái hiện tại/mới, thông báo 409, phục hồi sau 503 và payload PATCH khi thành công |
| UI20-02-AC04 | PASS trên Chrome fixture. Tab/Enter mở form, focus vào ô đầu tiên và trở về đúng nút sau Hủy hoặc lưu |
| UI20-02-AC05 | Node/lint/build/diff PASS. Owner acceptance trên trình duyệt với Host thật đang chờ |

### 6. Phần chưa kiểm

Browser với Host thật, ghi dữ liệu phòng thử và refresh bằng Laravel, xung đột booking/bảo trì thực tế và phục hồi phiên Sanctum chưa có bằng chứng mới. UC20-R01 về thứ tự khóa vẫn mở và cần phối hợp với TV2 nếu xử lý.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) xác nhận scope năm file, baseline và các phần việc có sẵn.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) dùng để đồng bộ bố cục, kiểm liên kết lỗi với trường, focus và guard khi lưu.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để đối chiếu AC với kiểm thử Node, build, Chrome và giới hạn evidence.

### 8. Bàn giao

Commit đề xuất cho UI-02 là `style(catalog): align host room detail and edit forms`. Các hunk tài liệu UC20 đang gồm nội dung của cả ba task. Bước kế tiếp đề xuất là UC20-FINAL, đối chiếu browser trên Host thật và tổng hợp phạm vi nghiệm thu.

---

## UC20-FINAL — Rà soát và chốt phạm vi nghiệm thu

### 1. Mục tiêu và kết quả

Ngày 22/11/2026, rà baseline nhánh `feature/tv1-uc21-room-images` tại `1d6dcdb`. Backend và Frontend đã đủ năm thao tác quản lý phòng thuộc UC20. **TECHNICAL PASS** cho phạm vi FINAL. **Owner acceptance PASS** theo checklist browser cho giao diện mới của UI-01/UI-02. Kết luận nghiệm thu áp dụng cho các luồng đã kiểm ở mục 4.

### 2. Phạm vi file

| File sửa trong FINAL | Nội dung |
| --- | --- |
| `docs/TV1/UC20-REPORT.md` | Bổ sung kết luận, bằng chứng và các mục còn mở |
| `docs/TV1/README.md` | Đồng bộ trạng thái UC20 |

Cả năm file JSX từ UI-01/UI-02 có nội dung trùng baseline FINAL. Báo cáo UC20 đang untracked từ UC20-01. Diff hiện tại của nhánh gồm hai file báo cáo và năm file JSX của các task UC20 đã thực hiện.

### 3. Kiểm thử

| Kiểm tra | Kết quả |
| --- | --- |
| `php artisan test --filter='HostRoomListTest\|HostRoomCreateTest\|HostRoomDetailTest\|HostRoomUpdateTest\|HostRoomStatusTest'` | Exit 0, **321 PASS, 1.203 assertions**, SQLite in-memory. Lượt đầu trong sandbox exit 2 do PHPUnit không đọc được `vendor/autoload.php`. Chạy lại ngoài sandbox với guard kiểm tra cấu hình database của test |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0, 437 PASS, 0 fail, 0 skipped |
| `npm run lint` | Exit 0, 0 lỗi, 3 cảnh báo Fast Refresh có sẵn |
| `npm run build` | Exit 0, 3400 modules, cảnh báo bundle lớn hơn 500 kB |
| `git diff --check` | Exit 0. Kiểm riêng khoảng trắng, anchor và link của báo cáo untracked |
| Browser Host thật | Owner xác nhận “Đạt các mục trên” cho checklist UI mới trong FINAL |

Lượt Backend tập trung có exit code mới. Full PHP suite là bằng chứng lịch sử của UC20-01 tại mục 3 của báo cáo. Chrome với API giả lập của UI-02 là bằng chứng lịch sử riêng, bổ sung ca 422, 409 và phục hồi sau 503.

### 4. Hành vi được nghiệm thu và contract

| Chức năng | Bằng chứng |
| --- | --- |
| Danh sách và chi tiết | API theo quyền sở hữu Host, danh sách theo cơ sở và chi tiết theo phòng. Suites List/Detail và owner xác nhận vào từ Danh sách phòng, refresh và quay về cơ sở |
| Thêm và sửa | Suites Create/Update kiểm kiểu dữ liệu, giới hạn giá/sức chứa, loại phòng cùng cơ sở và 409 trùng số. Owner kiểm thêm/sửa trên UI mới, kiểm trùng số đã được ghi ở UC20-01 |
| Đổi trạng thái | Suite Status kiểm enum, no-op, booking/bảo trì và rollback. Owner đổi trạng thái trên phòng thử, Chrome fixture kiểm hiển thị 409 và khôi phục sau 503 |
| Mobile và bàn phím | Owner xác nhận mobile, Tab/Enter trong checklist FINAL. Chrome fixture UI-02 bổ sung bằng chứng focus vào trường đầu và trả về nút tương ứng |

Hai API GET/POST theo cơ sở và ba API GET/PUT/PATCH theo phòng giữ contract hiện hành. Kiểm quyền ở Laravel bằng Sanctum, account-active, quyền Host và query theo chủ sở hữu. Frontend tiếp tục qua domain service và Axios. Loại phòng lấy từ UC19. Trang chi tiết vẫn nhúng trình quản lý ảnh UC21.

### 5. Tiêu chí nghiệm thu

| ID | Kết quả |
| --- | --- |
| FINAL-AC01 | PASS. Đối chiếu đủ route, contract, quyền Host và diff thuộc phạm vi UC20 |
| FINAL-AC02 | PASS. Có kết quả mới của Backend tập trung, Node, lint, build và diff |
| FINAL-AC03 | PASS theo owner, giới hạn ở checklist trên phòng thử của UI-01/UI-02 |
| FINAL-AC04 | PASS. Báo cáo và README ghi cùng phạm vi nghiệm thu và các mục còn mở |

### 6. Rủi ro và phần chưa xác minh

| ID | Trạng thái |
| --- | --- |
| UC20-R01 | Rủi ro thứ tự khóa. Đổi trạng thái UC20 khóa Room rồi Booking, xác nhận đặt phòng TV2 khóa Booking rồi Room. Chưa tái hiện deadlock trên InnoDB |
| UC20-G01 | Chưa xác minh cạnh tranh ghi và collation số phòng trên MariaDB |
| UC20-G02 | Chưa xác minh browser với booking/bảo trì thật gây 409, mất phản hồi, lỗi CSRF/session hoặc chuyển phòng khi mạng chậm |

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) dùng để giữ baseline, ranh giới hai file tài liệu và dependencies UC19/UC21/TV2.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để đối chiếu kết quả kiểm thử với AC, ghi giới hạn test SQLite và phạm vi owner xác nhận.

### 8. Bàn giao

Đề xuất **UC20-QA-DB** để kiểm MariaDB/InnoDB và collation trên schema thử, phối hợp TV2 cho ca thứ tự khóa. Commit gộp UC20-01, UI-01, UI-02 và FINAL được đề xuất vì cả bốn task đang dùng chung hai file báo cáo.

```text
style(catalog): align host room management UI and document acceptance
```
