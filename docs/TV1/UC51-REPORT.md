# UC51 — Kiểm tra lịch bảo trì

Ngày kiểm chứng: 18/11/2026, Asia/Saigon.

## 1. Mục tiêu và baseline

Triển khai query nội bộ kiểm tra lịch bảo trì cản khoảng lưu trú của một phòng, theo Mini Plan đã được người dùng duyệt. Chính sách được duyệt gồm IN_PROGRESS chặn đến khi hoàn tất/hủy và khoảng ngày [start,end).

- Nhánh: `feature/tv1-uc51-maintenance-check`.
- HEAD baseline: `3914bbfd3d37677e4692850429b9c179c9a011ed`.
- Working tree ban đầu sạch; UC52 đã có trên baseline. Sau lần ngắt giữ nguyên phần code/test UC51 đã viết và tiếp tục kiểm chứng/báo cáo.
- Model/Enum Maintenance và query/guard đổi trạng thái phòng UC20 đã có. Chưa có caller AvailabilityService/Booking production được đăng ký trên nhánh này.
- Lõi UC51 là backend read/query, không có màn hình/endpoint riêng. Không triển khai CRUD Maintenance UC23 hoặc thay workflow UC20/TV2.

Đã đối chiếu AGENTS, ARCHITECTURE, README, BASELINE_HANDOVER, hướng dẫn frontend, ERD/use case/phân công, audit TV1, UC20-ROOM-STATUS-RULES, schema/seeder, Model/Enum, Repository/Service/Validator, routes và tests liên quan khi lập kế hoạch và triển khai.

## 2. File tạo/sửa và kiến trúc

| File | Thao tác | Trách nhiệm |
| --- | --- | --- |
| [CheckRoomMaintenanceQuery.php](../../backend/app/DTOs/Catalog/Requests/CheckRoomMaintenanceQuery.php) | Tạo | Typed input sau validation: roomId/checkInDate/checkOutDate |
| [RoomMaintenanceValidator.php](../../backend/app/Validators/Catalog/RoomMaintenanceValidator.php) | Tạo | Kiểm input và quy tắc chặn trên facts đã được scope |
| [RoomMaintenanceQueryService.php](../../backend/app/Services/Catalog/RoomMaintenanceQueryService.php) | Tạo | Điều phối input, phòng tồn tại và kết quả boolean |
| [CatalogRepository.php](../../backend/app/Repositories/Catalog/CatalogRepository.php) | Sửa | Thêm 2 query chỉ đọc; giữ nguyên các phương thức hiện có |
| [RoomMaintenanceValidatorTest.php](../../backend/tests/Unit/Catalog/RoomMaintenanceValidatorTest.php) | Tạo | Unit tests cho input, ngày biên, trạng thái và dữ liệu bất thường |
| [RoomMaintenanceQueryTest.php](../../backend/tests/Feature/Catalog/RoomMaintenanceQueryTest.php) | Tạo | QueryService/Repository thật, fixtures SQLite và kiểm tra chỉ đọc |
| [UC51-REPORT.md](UC51-REPORT.md) | Tạo | Contract, kiểm chứng và bàn giao TV2 |
| [README.md](README.md) | Sửa | Thêm mục lục UC51 |

Luồng: caller nội bộ → QueryService → Validator/typed DTO → CatalogRepository → facts thô → Validator → boolean. Constructor DI dùng concrete classes, Laravel tự resolve. Không thêm Controller, Response DTO hoặc Mapper vì kết quả chỉ là boolean, không có HTTP projection.

Hai phương thức mới của Repository:

- `roomExistsForMaintenanceCheck(int $roomId): bool`: SELECT tồn tại Room theo khóa chính.
- `getRoomMaintenanceFacts(int $roomId): Collection`: SELECT đúng room_id, chỉ SCHEDULED/IN_PROGRESS; lấy id/status/start_date/end_date, thứ tự id. `toBase()` tránh date casts/Carbon tự chuẩn hóa ngày legacy sai; không lấy reason hoặc quan hệ.

Không SQL-filter theo ngày trước khi kiểm tra facts, vì lịch đang xử lý hoặc lịch SCHEDULED có ngày sai vẫn phải chặn. Dùng FK index room_id đã có; không thêm index/migration hoặc kết luận tối ưu bulk Search trên dữ liệu lớn.

## 3. Contract nội bộ đã triển khai

Instance method, dùng qua đối tượng hoặc constructor DI:

```php
public function hasBlockingMaintenance(
    mixed $roomId,
    mixed $checkInDate,
    mixed $checkOutDate
): bool;
```

| Điều kiện | Hành vi |
| --- | --- |
| roomId không phải PHP int dương | InvalidArgumentException: Mã phòng phải là số nguyên dương. |
| Ngày sai kiểu/canonical/calendar/MySQL DATE range | InvalidArgumentException, phân biệt ngày nhận/trả phòng |
| checkInDate >= checkOutDate | InvalidArgumentException: Ngày trả phòng phải sau ngày nhận phòng. |
| Input hợp lệ, Room không tồn tại | ModelNotFoundException, model Room và ID được yêu cầu |
| Có ít nhất một Maintenance cản lưu trú | true |
| Room tồn tại, không có Maintenance cản | false |
| Lỗi Repository/database | Ngoại lệ được truyền lên; không đổi thành false |

Thứ tự: ID → ngày nhận → ngày trả → thứ tự khoảng ngày → Room tồn tại → facts bảo trì. Input sai không phát sinh query. Thiếu đối số bắt buộc là lỗi gọi hàm của PHP (ArgumentCountError), không có giá trị mặc định.

Ngày là chuỗi ASCII YYYY-MM-DD hợp lệ từ 1000-01-01 đến 9999-12-31. Không trim/cast/tự sửa ngày, không nhận datetime object, timestamp hoặc ngày có giờ/timezone. Chỉ truyền giá trị đã kiểm tra vào typed DTO. HTTP normalization và field errors thuộc boundary của caller TV2; task này không định nghĩa HTTP status mới.

Helper không áp điều kiện ngày đặt phòng phải từ hôm nay; việc đó thuộc Booking/Search. DATE được so sánh theo ngày lịch đã chuẩn hóa, không dùng now(), timezone máy hoặc CURDATE().

## 4. Quy tắc giao khoảng và trạng thái

Khoảng lưu trú là **[check-in,check-out)**. Với SCHEDULED có ngày hợp lệ:

```text
maintenance.start_date < check_out_date
AND maintenance.end_date > check_in_date
```

| Maintenance của đúng phòng | Kết quả |
| --- | --- |
| SCHEDULED hợp lệ, giao khoảng lưu trú | Chặn |
| SCHEDULED hợp lệ, nằm trước/sau hoặc chỉ chạm ngày biên | Không chặn |
| SCHEDULED có ngày sai, zero-date, ngoài range, start >= end | Chặn, tránh kết luận không có lịch cản từ dữ liệu chưa xử lý |
| IN_PROGRESS | Chặn bất kể ngày, kể cả đã quá end_date hoặc nằm ngoài khoảng yêu cầu |
| COMPLETED/CANCELLED | Không chặn, kể cả dữ liệu ngày bất thường |

IN_PROGRESS chỉ hết chặn qua workflow hoàn tất/hủy của owner. UC51 không tự thay trạng thái hoặc sửa dữ liệu lịch quá hạn. Đây là chính sách đã được người dùng duyệt cho UC51, dựa trên cách xử lý thận trọng của baseline UC20; chưa thay thế xác nhận contract tích hợp của TV2.

Ví dụ lịch SCHEDULED [2026-12-10,2026-12-12) không cản lượt nhận phòng ngày 12/12 hoặc lượt trả phòng ngày 10/12. Quy tắc liền kề này không làm IN_PROGRESS tự hết chặn.

## 5. Bàn giao TV2 và giới hạn tính nhất quán

Ví dụ gọi helper; không phải endpoint mới hoặc caller production đã tích hợp:

```php
use App\Services\Catalog\RoomMaintenanceQueryService;

$service = app(RoomMaintenanceQueryService::class);
$blocked = $service->hasBlockingMaintenance($roomId, '2026-12-10', '2026-12-15');
```

Trong Service/QueryService production, inject RoomMaintenanceQueryService qua constructor. Caller phải xác nhận quyền/scope Room trước khi gọi; service này không nhận actor và không công bố dữ liệu Maintenance ra HTTP. Đã tồn tại Room không đồng nghĩa Room/Property đang public hoặc ACTIVE.

`false` chỉ nghĩa không có Maintenance cản theo dữ liệu đã đọc. Caller vẫn kiểm Room/Property status, quyền, sức chứa UC52, ngày nghiệp vụ và Booking bị trùng. Không được coi false là Availability hoàn chỉnh hoặc cam kết đặt phòng.

UC51 dùng hai SELECT thông thường, không tạo transaction, không lock và không cache kết quả. Dữ liệu có thể thay đổi giữa các lần đọc hoặc sau khi hàm trả về. Chỉ gọi hàm trong transaction cũng **chưa chứng minh current-read** nếu transaction InnoDB REPEATABLE READ đã có snapshot cũ. Khi tích hợp Booking/Maintenance writers, TV1/TV2 phải chốt giao thức lock/current-read/recheck trên cùng connection. Đây là dependency tích hợp riêng, không được đóng gate concurrency bằng test SQLite.

API kiểm tra một phòng mỗi lần; không tự gọi trong vòng lặp lớn để thay bulk Search. Contract/performance cho truy vấn nhiều phòng cần task tích hợp TV2 phù hợp. Không sửa các phương thức lockRoomMaintenanceFacts, UC20 changeStatus hoặc UC52 trong task này.

## 6. Kiểm thử thực tế

| Kiểm tra | Kết quả |
| --- | --- |
| PHP lint 6 file PHP tạo/sửa | PASS, không lỗi cú pháp |
| `php artisan test --compact --filter='RoomMaintenanceValidatorTest\|RoomMaintenanceQueryTest'` | PASS, exit 0: 134 tests / 233 assertions, 1.72 giây |
| `php artisan test --compact --filter='HostRoomStatusTest\|RoomCapacityValidatorTest'` | PASS, exit 0: 255 tests / 648 assertions, 15.14 giây |
| Full backend `php artisan test --compact` | PASS, exit 0: 1377 tests / 7044 assertions, 194.56 giây, SQLite in-memory |
| Git diff/scope và liên kết tài liệu | PASS: đúng 8 file đã duyệt, PHP lint/UTF-8/whitespace/link; các phương thức Repository cũ giữ nguyên |
| Frontend lint/build | Không áp dụng, không thay đổi frontend |
| Browser/Booking production/MySQL locking | NOT VERIFIED trong task này |

Hai lượt focused tests trên đã hoàn tất trước khi bị ngắt; giữ nguyên code đã kiểm chứng. Không dùng lượt full suite bị ngắt/chưa thu được kết quả làm bằng chứng PASS. Sau khi lượt cũ đã kết thúc, chạy lại full suite và thu exit 0 cùng kết quả 1377/7044. Log và result.json của lượt chạy lại nằm trong `.agent-workspace/uc51-verification-03b4e64fbbe441a4a3ca2f07ded70a97/`, được Git ignore và không phải dependency của source.

Unit tests dùng PHPUnit TestCase thuần, không boot Laravel/DB. Feature tests dùng schema SQLite :memory:, container DI và Repository thật; chỉ test truyền lỗi Repository dùng dependency mock được ghi rõ trong tên test. Fixture được tạo trực tiếp phục vụ read coverage; không dùng Seeder hoặc mô phỏng đây là workflow tạo/sửa bảo trì UC23.

Các nhóm đã kiểm tra: strict ID/date, leap year/century, MySQL DATE bounds, ngày đảo/rỗng, mọi hình thái overlap/adjacency, trạng thái hoàn tất/hủy/đang xử lý quá hạn, lịch SCHEDULED sai ngày, nhiều lịch, cùng Property/khác Property, thiếu Room, input sai trước query, projection không có reason/date cast, gọi lặp đọc lại fixture thay đổi, chỉ SELECT và nội dung/transaction level giữ nguyên, không phụ thuộc hôm nay/timezone.

Ngày legacy bất thường được chèn vào **SQLite fixture** để kiểm tra logic không chuẩn hóa sai; không phải bằng chứng MySQL strict mode chấp nhận các giá trị đó. Không nới SQL mode hoặc sửa dữ liệu dev.

Guard trước Laravel boot/RefreshDatabase xác nhận APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL rỗng, cache/session array và không có config cache; sau boot kiểm lại config hiệu lực. Không chạy migrate/seed/reset trên MySQL dev, không sửa .env hoặc dependency.

## 7. Acceptance criteria và kết luận

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| AC1 — Đúng overlap và ngày biên | PASS | Unit và Feature query thực tế |
| AC2 — Đúng chính sách trạng thái đã duyệt | PASS | IN_PROGRESS quá hạn/ngoài khoảng vẫn chặn; terminal bỏ qua; malformed SCHEDULED chặn |
| AC3 — Input sai/Room không tồn tại có lỗi rõ | PASS | Unit strict validation; Feature zero query/missing Room/Repository failure |
| AC4 — Đúng Room, chỉ đọc dữ liệu | PASS | Same/different Property fixtures, SQL chỉ SELECT, snapshots/transaction level giữ nguyên |
| AC5 — Tests và hồi quy PASS | PASS | UC51 134/233; UC20/UC52 255/648; full backend 1377/7044 |
| AC6 — Contract và giới hạn tích hợp rõ | PASS về tài liệu | Mục 3–5, chưa có caller TV2 hoặc bằng chứng MySQL concurrency |

**TECHNICAL PASS cho lõi UC51 và contract bàn giao theo phạm vi đã duyệt.** AC1–AC6 có bằng chứng tương ứng; không có deviation về scope. HEAD và Git index giữ nguyên; đúng 8 file mới/sửa còn ở working tree để review, chưa commit/push/merge. Không thay đổi frontend, schema, `.env`, dữ liệu dev hoặc nghiệp vụ UC khác.

Owner acceptance sau triển khai và xác nhận/tích hợp của TV2 được theo dõi riêng. Task tiếp theo đề xuất: TV2 xác nhận contract UC51/UC52 và phối hợp tích hợp Availability/Booking trên caller thật. Dừng sau báo cáo, không tự triển khai UC khác hoặc commit/push/merge.
