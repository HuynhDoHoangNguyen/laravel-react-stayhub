# UC20 — Quy tắc trạng thái phòng và phụ thuộc Booking/Maintenance

## 1. Phạm vi và trạng thái

- Task CAT-ROOM-05A, kiểm chứng 2026-11-15, nhánh refactor/stayhub-layered-architecture, HEAD 7dac999 và working tree đã nghiệm thu CAT-ROOM-01–04.
- **05A đã ACCEPTED trên chat. CAT-ROOM-05B IMPLEMENTED / TECHNICAL PASS, chờ nghiệm thu.** Ma trận giữ nguyên đặc tả đã được duyệt.
- Phạm vi lịch sử 05A chỉ gồm hai tài liệu. API/UI status được triển khai sau đó trong 05B theo Mini Plan 16 file đã duyệt; không thay schema. UC20 chưa đóng audit.
- UC20/UC23/UC51 thuộc TV1; Availability/Booking workflow thuộc TV2. Quy ước khóa phải được các writer liên quan bảo toàn khi tích hợp.

Nguồn: [kiến trúc](../../ARCHITECTURE.md), [phân công](../stayhub-team-work-assignment.md), [use case](../stayhub-usecase-system.md), [ERD](../stayhub-erd.md) và [checklist/evidence](../TV3/TV3_CHECKLIST.md).

## 2. Nguồn sự thật đã đối chiếu

| Thành phần | Hiện trạng |
| --- | --- |
| [RoomStatus](../../backend/app/Enums/RoomStatus.php) | ACTIVE, INACTIVE, MAINTENANCE |
| [BookingStatus](../../backend/app/Enums/BookingStatus.php) | PENDING, CONFIRMED, CHECKED_IN giữ phòng; COMPLETED, CANCELLED, REJECTED không giữ phòng |
| [MaintenanceStatus](../../backend/app/Enums/MaintenanceStatus.php) | SCHEDULED, IN_PROGRESS, COMPLETED, CANCELLED |
| [Models](../../backend/app/Models) | Booking/Maintenance tham chiếu Room, ngày dạng DATE; Room có casts và quan hệ |
| [Migration](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php) | ENUM như trên; Booking có index room_id/status/check_in_date/check_out_date; Maintenance có FK index room_id |
| [Catalog routes](../../backend/routes/api/catalog.php) | List/detail/create/update và PATCH /api/host/rooms/{room}/status đã có sau 05B |
| [API registry](../../backend/routes/api.php) | Chưa tích hợp route module Booking/Maintenance |
| [CatalogRepository](../../backend/app/Repositories/Catalog/CatalogRepository.php) | Public Room lọc ACTIVE và RoomType cùng Property, không phải availability theo ngày |
| [HostRoomService](../../backend/app/Services/Catalog/HostRoomService.php) | Create ACTIVE, update giữ status/property_id; changeStatus dùng parent/Room locks và B/M guards sau 05B |
| [Host Room detail UI](../../frontend/src/pages/host/HostRoomDetailPage.jsx) | Hiển thị/sửa thông tin và form trạng thái sau 05B, hai form loại trừ nhau |

XAMPP thực tế: MariaDB **10.4.32**, database stayhub, rooms/bookings/maintenances đều **InnoDB**, isolation **REPEATABLE-READ**. App timezone hiệu lực **UTC**, DB session timezone **SYSTEM**; không thay config/timezone dự án.

Đối chiếu metadata/data dev bằng transaction READ ONLY:
- 13 Room: ACTIVE 11, MAINTENANCE 1, INACTIVE 1.
- 16 Booking: PENDING 3, CONFIRMED 3, CHECKED_IN 2, COMPLETED 5, CANCELLED 2, REJECTED 1.
- 4 Maintenance: mỗi trạng thái một record.
- Truy vấn tổng hợp không thấy khoảng ngày đảo/rỗng, toàn-zero hoặc thành phần ngày zero.
- Tại ngày UTC 2026-11-15 có **2 CHECKED_IN đã tới/quá checkout** và **1 IN_PROGRESS đã tới/quá end_date**. Owner cần rà workflow, không tự coi là COMPLETED hoặc sửa trong 05A.
- Fingerprint trước/sau khớp ở 10 bảng: properties, room_types, rooms, bookings, booking_services, invoices, invoice_items, maintenances, room_images, room_amenities. Báo cáo không chứa dữ liệu cá nhân.

Không thêm index/migration. Query plan và nhu cầu index Maintenance được đánh giá riêng theo quy mô khi triển khai.

## 3. Ngày và dữ liệu bất thường

1. Lấy D một lần theo config('app.timezone') cho thao tác; hiện UTC. Bind D vào query, không thay bằng ngày máy người dùng hoặc DB CURDATE().
2. Khoảng ngày là **[start,end)**: checkout/end bằng D đã kết thúc; start bằng D đã bắt đầu.
3. Khoảng sai gồm start >= end, ngày toàn-zero/thành phần zero hoặc ngày lịch không hợp lệ. Record đang giữ phòng/chưa hoàn tất có ngày sai phải chặn theo B/M.
4. Ngày sai ở record terminal không làm record đó chiếm phòng lại; vẫn ghi nhận dữ liệu bất thường cho owner xử lý.
5. CHECKED_IN/IN_PROGRESS không tự hết hiệu lực theo ngày; cần workflow owner hoàn tất/hủy đúng quyền.
6. Không thêm scheduler, tự checkout, tự hoàn tất Maintenance hoặc sửa dữ liệu cũ.

Strict SQL mode thông thường từ chối zero-date. Probe legacy chỉ nới connection của schema fixture owned để lưu zero component/ngày lịch sai, sau đó khôi phục. Không nới chế độ database dev.

## 4. Điều kiện B và M

### B — Booking cản ngừng hoạt động/chuyển bảo trì

Chỉ xét Booking có room_id của Room đang thao tác. B = có ít nhất một:
- CHECKED_IN, bất kể ngày; hoặc
- PENDING/CONFIRMED có khoảng ngày sai; hoặc
- PENDING/CONFIRMED hợp lệ và check_out_date > D.

PENDING/CONFIRMED tương lai vẫn chặn vì trạng thái toàn phòng có thể ảnh hưởng cam kết đã nhận. COMPLETED/CANCELLED/REJECTED không chặn. PENDING/CONFIRMED hợp lệ có checkout <= D không chặn theo đặc tả này; không tự thay trạng thái Booking.

### M — Maintenance cản mở lại/rời bảo trì

Chỉ xét Maintenance có room_id của Room đang thao tác. M = có ít nhất một:
- IN_PROGRESS, bất kể ngày; hoặc
- SCHEDULED có khoảng ngày sai; hoặc
- SCHEDULED hợp lệ có start_date <= D AND D < end_date.

COMPLETED/CANCELLED không chặn. SCHEDULED hợp lệ tương lai hoặc đã kết thúc không chặn.

Nhiều record dùng EXISTS/any: một record cản là đủ. Record Room khác, kể cả cùng Property, không tham gia quyết định.

## 5. Ma trận trạng thái

| Từ → Đến | ACTIVE | INACTIVE | MAINTENANCE |
| --- | --- | --- | --- |
| ACTIVE | No-op 200 | Cho phép khi không B | Cho phép khi không B |
| INACTIVE | Cho phép khi không M | No-op 200 | Cho phép khi không B |
| MAINTENANCE | Cho phép khi không M | Cho phép khi không B và không M | No-op 200 |

- No-op chỉ sau auth/account/role, canonical ID, input và ownership hợp lệ. Không chạy B/M, không save/đổi updated_at/log success.
- Nếu cùng vướng B/M ở transition cần cả hai, ưu tiên thông báo B.
- MAINTENANCE là khóa vận hành thủ công toàn phòng, không bắt buộc có và không tự tạo Maintenance record.
- Đổi ra khỏi MAINTENANCE không tự hoàn thành/hủy lịch. Lịch tương lai giữ nguyên; Availability vẫn phải xét overlap khi tích hợp.
- ACTIVE không đồng nghĩa còn trống. Không thêm status BOOKED.
- Host quản lý Room ở cả bốn trạng thái Property như baseline. Room ACTIVE không vượt qua gate Public riêng của Property.
- Giữ Property, giá/sức chứa, ảnh/tiện nghi, Room khác và mọi snapshot nghiệp vụ.

## 6. Contract bàn giao CAT-ROOM-05B

Contract dưới đây đã được triển khai/kiểm chứng trong 05B; evidence lịch sử 05A ở mục 8 và evidence triển khai ở mục 10:

~~~http
PATCH /api/host/rooms/{room}/status
Content-Type: application/json
Accept: application/json

{"status":"INACTIVE"}
~~~

- Chỉ nhận status là chuỗi enum chính xác ACTIVE/INACTIVE/MAINTENANCE; không ép bool/số hoặc tên viết thường. Query/file/trường thừa trả 422.
- Sanctum session/cookie, account.active, HOST và Room → Property → host_id thuộc actor.
- Thứ tự: middleware → role/canonical ID → payload → scope Room/Property → no-op → B/M → persistence/mapping/commit.
- Canonical ID sai, Room thiếu hoặc khác chủ dùng chung privacy 404.

| HTTP | Contract |
| --- | --- |
| 200 | success=true, message="Cập nhật trạng thái phòng thành công.", data là tám trường HostRoomResponse |
| 401/403/419 | Giữ Auth/account/role/CSRF baseline |
| 404 | {"message":"Không tìm thấy phòng."} |
| 422 | Laravel field errors, không lộ dữ liệu nghiệp vụ riêng tư |
| 409 do B | {"message":"Phòng còn booking đang giữ chỗ hoặc đang lưu trú."} |
| 409 do M | {"message":"Phòng còn lịch bảo trì đang có hiệu lực hoặc chưa hoàn tất."} |

Data tái sử dụng [HostRoomResponse](../../backend/app/DTOs/Catalog/Responses/HostRoomResponse.php) qua CatalogMapper/Symfony: id, room_number, name, description, capacity, price_per_night (decimal string), status, room_type ({id,name} hoặc null theo baseline). No-op cùng envelope 200 và status hiện tại.

Bất biến:
- Booking status/date/guest_count/price_per_night/room_total và BookingService snapshot giữ nguyên.
- Invoice/InvoiceItem, Maintenance, ảnh/tiện nghi, Property/RoomType và Room khác giữ nguyên.
- Mapping trước commit, thất bại rollback. Chỉ log thay đổi thật sau outer commit, gồm ID an toàn.

05A không kiểm chứng HTTP 200/409 của status, policy mới, no-op save/log của Service hoặc lỗi mapping của endpoint mới; các cổng này thuộc 05B.

## 7. Khóa và tích hợp

1. Dùng cùng connection/transaction cho lookup, locks, fact reads và mutation.
2. Lookup Property của Room theo ownership; khóa **Property → Room** và xác minh Room vẫn thuộc Property.
3. Đọc B/M hiện tại bằng locking/current reads sau parent locks. Plain consistent read có thể tiếp tục thấy snapshot cũ dù đã chờ khóa.
4. Writer Booking/Maintenance phải khóa cùng Property → Room trước khi kiểm trạng thái/availability và ghi fact. Khác thứ tự cần thống nhất với owner trước tích hợp.
5. Chỉ ghi Room khi guards qua, map trước commit và log success sau outer commit.
6. FK/khóa Room không thay thế business recheck. Direct SQL hoặc writer bỏ recheck vẫn có thể insert Booking trên Room INACTIVE sau khi chờ FK lock.
7. Probe 05A chứng minh kỹ thuật trong harness tạm, không chứng minh mọi writer production tuân thủ. Route/workflow liên quan chưa tích hợp, nên race toàn workflow TV1/TV2 giữ **NOT VERIFIED**.

## 8. Bằng chứng kiểm chứng 2026-11-15

### Predicate cases

D cố định 2026-11-15 từ clock UTC 23:30; cùng instant ở Asia/Bangkok sang 2026-11-16. Bound DATE vẫn giữ kết quả khi session DB chuyển +07:00 trong fixture.

Mỗi trạng thái được kiểm với 12 case ngày:
- past: 2026-11-13 → 2026-11-14.
- ends_today: 2026-11-14 → 2026-11-15.
- starts_today: 2026-11-15 → 2026-11-16.
- future: 2026-11-16 → 2026-11-17.
- zero_length và reversed.
- zero_start / zero_end: một đầu 0000-00-00.
- zero_month: 2026-00-01 → 2026-00-02.
- zero_day: 2026-11-00 → 2026-11-14.
- zero_year: 0000-11-01 → 0000-11-02.
- invalid_calendar: 2026-02-30 → 2026-03-01.

**120/120 PASS:** Booking 6 × 12 = 72; Maintenance 4 × 12 = 48. Có strict zero-date rejection trước legacy mode riêng, bảng expected/actual theo từng status/date trong artifact JSON.

### Matrix oracle

Expected viết thành bảng literal độc lập với query; thứ tự cột [không B/M, chỉ B, chỉ M, cả B/M].

| Transition | Expected outcome |
| --- | --- |
| ACTIVE → ACTIVE | [200,200,200,200] |
| ACTIVE → INACTIVE | [200,409,200,409] |
| ACTIVE → MAINTENANCE | [200,409,200,409] |
| INACTIVE → ACTIVE | [200,200,409,409] |
| INACTIVE → INACTIVE | [200,200,200,200] |
| INACTIVE → MAINTENANCE | [200,409,200,409] |
| MAINTENANCE → ACTIVE | [200,200,409,409] |
| MAINTENANCE → INACTIVE | [200,409,409,409] |
| MAINTENANCE → MAINTENANCE | [200,200,200,200] |

**36/36 PASS.** 200/409 ở đây là outcome harness, không phải HTTP endpoint chưa tồn tại. Probe SQL transaction xác minh no-op/reject không đổi row; mutation chỉ đổi status/updated_at; snapshot/relations/Room khác bất biến; rollback phục hồi fixture.

### Bốn probe hai connection

Mỗi case quan sát lock wait thật trong information_schema.INNODB_LOCK_WAITS:

| Case | Quan sát |
| --- | --- |
| Booking commit → status checker | Plain snapshot trước/sau chờ B=false; current locking read B=true, harness chặn |
| Maintenance commit → activation checker | Plain snapshot trước/sau chờ M=false; current locking read M=true, harness chặn |
| Status commit → cooperating Booking writer | Snapshot ACTIVE; sau khóa đọc INACTIVE và không insert |
| Negative control: status commit → writer bỏ recheck | FK insert có chờ khóa nhưng sau đó vẫn lưu Booking giữ phòng trên Room INACTIVE |

Bốn probe đạt expected. Negative control cố ý lưu trên schema owned và xác nhận lại trước DROP schema; không mutation trên dữ liệu dev hoặc tuyên bố đã kiểm race endpoint production.

### Regression và giới hạn

| Cổng | Kết quả |
| --- | --- |
| Catalog bằng PHP XAMPP, SQLite in-memory | 348 tests / 2.046 assertions PASS |
| Backend full suite, SQLite in-memory | 979 tests / 6.110 assertions PASS |
| Frontend Node | 150 PASS |
| Lint/build | Exit 0; 3 warning Fast Refresh nền và warning chunk lớn |
| Route registry | GET/PUT host Room detail/update, public Property GET tồn tại; PATCH status chưa có |
| Browser/API status | NOT IMPLEMENTED / NOT VERIFIED; 05A không mở browser/dựng status endpoint giả |
| Source baseline | Hash ngoài hai tài liệu giữ nguyên |
| Dev data | Fingerprint 10 bảng trước/sau READ ONLY khớp |
| Cleanup | Schema fixture owned được DROP và xác nhận không còn; không khởi tạo listener 8157/5174/9231 |
| Whitespace nền | InvoiceService.php:91 còn finding cũ, source giữ nguyên |

Metadata harness dùng biến MariaDB @@tx_isolation. Lượt race đầu timeout do escape newline IPC release trong harness; đã sửa harness và chạy lại toàn bộ fixture/probe/races đạt, không sửa source cho lỗi harness.

Artifact local trong TEMP:
- stayhub-cat-room05a-dev-before.json / stayhub-cat-room05a-dev-after.json.
- stayhub-cat-room05a-predicate-cases.json / stayhub-cat-room05a-matrix-cases.json.
- stayhub-cat-room05a-xampp.txt (lượt đầu) / stayhub-cat-room05a-xampp-final.txt (lượt đạt).
- stayhub-cat-room05a-catalog.txt / stayhub-cat-room05a-full.txt / stayhub-cat-room05a-node.txt.
- stayhub-cat-room05a-lint.txt / stayhub-cat-room05a-build.txt.
- stayhub-cat-room05a-host-routes.json / stayhub-cat-room05a-public-routes.json.
- stayhub-cat-room05a-final-check.txt.

Không thêm harness/fixture vào repository.

## 9. Checklist bàn giao

| AC | Kết quả | Bằng chứng |
| --- | --- | --- |
| CATROOM05A-AC1 | PASS | Source/route registry/schema XAMPP, owner và phần chưa tích hợp đã đối chiếu |
| CATROOM05A-AC2 | PASS | Đủ 9 ô matrix, B/M, UTC/[start,end), no-op và 120 case ngày/trạng thái |
| CATROOM05A-AC3 | PASS | Contract 05B, lỗi/quyền/thứ tự và invariant theo Mini Plan |
| CATROOM05A-AC4 | PASS | 36 matrix probes, 4 lock probes, independent expected và negative control; giới hạn ghi rõ |
| CATROOM05A-AC5 | PASS | Regression, đúng 2 tài liệu, baseline/checklist và cleanup |

**Còn mở:** nghiệm thu kết quả 05B; tích hợp/kiểm chứng writer Booking/Maintenance thật; owner rà record quá hạn; audit UC20. 05A đã được nghiệm thu.


## 10. CAT-ROOM-05B — triển khai và kiểm chứng

**2026-11-15 · IMPLEMENTED / TECHNICAL PASS / OWNER ACCEPTANCE PENDING.**

- Đúng 16 file Mini Plan: 3 tạo/13 sửa. AuthenticationTest chỉ đổi route count 31 → 32. Không sửa Model/enum/schema, Backend mapper/Response DTO, AppRouter, Axios chung hoặc workflow Booking/Maintenance.
- [Typed Request](../../backend/app/DTOs/Catalog/Requests/ChangeHostRoomStatusRequest.php) chứa roomId/RoomStatus; Controller giữ giá trị JSON raw trước enum validation, kể cả chuỗi có khoảng trắng không được biến thành enum. JSON/body sai, extra/query/file trả 422.
- Service khóa Property → Room, no-op không save/updated_at/log/query B/M. Khi đổi, lấy D theo app timezone sau locks. Repository current reads raw date/status; Validator dùng checkdate/so sánh ngày để từ chối zero/calendar invalid, không phụ thuộc hàm date riêng MySQL hoặc Eloquent cast.
- Fact reads chỉ chạy khi transition cần; B ưu tiên trước M. RoomType projection dùng current read trên cùng transaction, legacy mismatch vẫn null; Symfony mapper giữ tám trường/decimal/null. Map trước commit, chỉ log thay đổi thật sau outer commit.
- [Form trạng thái](../../frontend/src/components/catalog/HostRoomStatusForm.jsx) có xác nhận, field/global errors, pending ref, cancel/focus và abort/ignore response muộn. Không mở song song với form sửa thông tin.
- PATCH thành công refresh detail, lỗi refresh tách riêng. Unknown outcome giữ lựa chọn/form qua GET detail rồi mới cho người dùng chủ động gửi lại; không auto PATCH.

| Cổng | Kết quả thực tế |
| --- | --- |
| [HostRoomStatusTest](../../backend/tests/Feature/Catalog/HostRoomStatusTest.php) SQLite | 177 tests/505 assertions PASS |
| HostRoomStatusTest XAMPP MariaDB 10.4.32 | 177 tests/505 assertions PASS trên schema owned có guard/SELECT DATABASE, đã dọn |
| Matrix/date production guard | 36 ô/tổ hợp qua endpoint thật và 120 case ngày/trạng thái PASS, gồm session legacy riêng phục hồi sau case |
| No-op/permissions/invariants | Không save/fact query/log/updated_at, auth/account/role/privacy, query/file/extra, decimal/type null và snapshots được kiểm |
| Rollback/log | Persistence/map failure rollback trong FeatureTest; outer rollback/commit/log level 0 trên InnoDB thật |
| Catalog regression SQLite | 525 tests/2.551 assertions PASS |
| Backend full SQLite | 1.156 tests/6.617 assertions PASS |
| Frontend Node | 154 PASS, hostRoomService 21 test (giữ 17 cũ + 4 status) |
| Lint/build | Exit 0, còn 3 Fast Refresh warnings nền và warning chunk lớn |
| Edge/React/API thật | CSRF/session 401/403/404/419, B/M409 với record quá hạn, no-op200, mutation200, focus, list/public projection và viewport1440/768/390 PASS |
| Input UI negative case | Harness thêm option enum sai vào DOM để gửi request thật; API422 và field error UI đạt. Không phải lựa chọn production có sẵn |
| Duplicate/unknown outcome | Giữ PATCH200 thật sau commit: double submit chỉ một PATCH. Thay committed200 bằng502 có kiểm soát: GET đối chiếu giữ form/lựa chọn, không auto replay; gửi lại chủ động no-op200 |
| Edit/late response | PUT sửa thông tin04 thật vẫn đạt; hai form loại trừ; điều hướng qua React Router khi PATCH response bị giữ không đổi Room mới hoặc success cũ |
| Dev/source baseline | Fingerprint 10 bảng dev trước/sau READ ONLY khớp, gồm 2 CHECKED_IN/1 IN_PROGRESS quá hạn giữ nguyên; source ngoài scope khớp |
| Cleanup | Schema PHPUnit/runtime, Edge/storage/process và cổng8157/5174/9231 dọn; PID nền3306/8000/5173 giữ nguyên |

Sáu race có quan sát lock wait thật trên hai connection:
1. Booking fact mới commit trước actual status Service: 409 B dù đã prime snapshot cũ.
2. Maintenance fact mới commit trước actual activate Service: 409 M.
3. Actual status200 trước cooperating Booking writer harness: writer đọc INACTIVE và từ chối.
4. Actual status MAINTENANCE200 trước status INACTIVE: request sau đọc current MAINTENANCE và bị M409 (snapshot trước là ACTIVE).
5. Actual status INACTIVE200 trước actual update thông tin200: update giữ INACTIVE.
6. Negative control writer bỏ recheck: sau status INACTIVE200 vẫn insert Booking qua FK wait. Đây là giới hạn phối hợp đã biết, không phải sửa module TV2.

Phía status trong các race gọi Service production thật; writer Booking/Maintenance là fixture/harness. Race toàn workflow API Booking/Maintenance production vẫn **NOT VERIFIED** đến khi module được tích hợp. Không dùng trạng thái số của harness writer để tuyên bố đã kiểm endpoint chưa có.

Artifacts trong TEMP: stayhub-cat-room05b-focused.txt, stayhub-cat-room05b-mysql-tests.txt, stayhub-cat-room05b-catalog.txt, stayhub-cat-room05b-full.txt, stayhub-cat-room05b-node.txt, stayhub-cat-room05b-lint.txt, stayhub-cat-room05b-build.txt, stayhub-cat-room05b-xampp.txt, stayhub-cat-room05b-dev-before.json, stayhub-cat-room05b-dev-after.json, stayhub-cat-room05b-final-check.txt. Fixture tổng hợp thuộc schema riêng, không phải dữ liệu production. Harness không thêm vào repo.

| AC | Kết quả | Evidence |
| --- | --- | --- |
| CATROOM05B-AC1 | PASS | PATCH exact contract, input, permissions/privacy/error order và CSRF thật |
| CATROOM05B-AC2 | PASS | Matrix/date qua production guard; no-op không write/log/fact query |
| CATROOM05B-AC3 | PASS | Current reads, persistence/map rollback, outer commit/log, sáu race và invariant |
| CATROOM05B-AC4 | PASS | React form, mutual exclusion, focus/pending/errors, GET reconciliation/late response/responsive |
| CATROOM05B-AC5 | PASS | MariaDB, Backend/Frontend regression và baseline01–04/05A |
| CATROOM05B-AC6 | PASS | Đúng16file/1dòngAuth, docs/checklist, source/data fingerprint và cleanup |

05B chờ người dùng nghiệm thu kết quả; chưa tự đóng UC20 hoặc triển khai audit.
