# UC52 — Kiểm tra sức chứa

Ngày kiểm chứng: 18/11/2026, Asia/Saigon.

## 1. Mục tiêu, baseline và phạm vi

Triển khai validator nội bộ để kiểm tra số khách của một phòng, dùng lại được trong Availability/Booking của TV2. Kế hoạch đã được người dùng duyệt trước khi viết code.

- Nhánh: `feature/tv1-capacity-check`.
- HEAD baseline: `96a3216bcadda4a62dd84febacb390d149a139df`.
- Working tree ban đầu sạch. Task không tạo/chuyển nhánh, commit, push hoặc merge.
- Đã có `Room.capacity`, `Booking.guest_count`, Catalog validation cho tạo/sửa phòng. Cả hai cột dùng signed INT; Catalog hiện giới hạn capacity tối đa 2147483647.
- Chưa có caller `AvailabilityService` hoặc Booking API được đăng ký trên nhánh này. Không tạo caller giả hoặc sửa nghiệp vụ TV2.

Đã đọc/đối chiếu `AGENTS.md`, `ARCHITECTURE.md`, `README.md`, `docs/TV3/BASELINE_HANDOVER.md`, hướng dẫn frontend, ERD, use case, phân công, audit TV1, migration/seeder và Model/Validator/Service/Route/Test liên quan trong giai đoạn lập kế hoạch và triển khai.

## 2. File tạo/sửa

| File | Thao tác | Trách nhiệm |
| --- | --- | --- |
| [RoomCapacityValidator.php](../../backend/app/Validators/Catalog/RoomCapacityValidator.php) | Tạo | Kiểm tra kiểu/phạm vi và so sánh số khách với sức chứa |
| [RoomCapacityValidatorTest.php](../../backend/tests/Unit/Catalog/RoomCapacityValidatorTest.php) | Tạo | Unit tests cho biên, đầu vào sai và tính độc lập |
| [UC52-REPORT.md](UC52-REPORT.md) | Tạo | Contract, bằng chứng kiểm thử và bàn giao TV2 |
| [README.md](README.md) | Sửa | Thêm mục lục UC52 |

Validator nằm trong root `app/Validators` theo domain Catalog. Đây là quy tắc thuần nên không cần thêm Controller, Request/Response DTO, Repository, Mapper, route hoặc frontend. Không đổi schema, Model, dependency, shared config hoặc các luồng đã nghiệm thu.

## 3. Contract nội bộ đã triển khai

```php
RoomCapacityValidator::canAccommodate(mixed $capacity, mixed $guestCount): bool
```

Đây là instance method, dùng qua đối tượng hoặc constructor DI. `mixed` cho phép kiểm tra kiểu thực trước mọi conversion, tránh PHP ép numeric string/float/bool từ caller không bật strict types.

| Điều kiện | Kết quả |
| --- | --- |
| Hai giá trị đều là PHP int từ 1 đến 2147483647, guestCount <= capacity | `true` |
| Hai giá trị hợp lệ, guestCount > capacity | `false` |
| Capacity sai kiểu hoặc ngoài phạm vi | `InvalidArgumentException`: `Sức chứa phòng phải là số nguyên từ 1 đến 2147483647.` |
| Guest count sai kiểu hoặc ngoài phạm vi | `InvalidArgumentException`: `Số khách phải là số nguyên từ 1 đến 2147483647.` |
| Thiếu đối số bắt buộc khi gọi phương thức | PHP ném `ArgumentCountError`; không có giá trị mặc định |

Capacity được kiểm tra trước guest count. Dữ liệu sai không được biến thành `false` hoặc `true`; không trim, cast hoặc làm tròn. Các message cố định không đưa giá trị đầu vào vào lỗi/log. Phép so sánh không cộng/trừ số khách nên không phát sinh overflow trung gian.

Ví dụ sử dụng với dữ liệu đã chuẩn hóa; đây là ví dụ gọi helper, không phải endpoint mới:

```php
use App\Validators\Catalog\RoomCapacityValidator;

$validator = new RoomCapacityValidator();
$validator->canAccommodate(4, 1); // true
$validator->canAccommodate(4, 4); // true
$validator->canAccommodate(4, 5); // false
$validator->canAccommodate(4, '2'); // InvalidArgumentException
```

Trong Service/QueryService của TV2, inject `RoomCapacityValidator` qua constructor và gọi với `$room->capacity` lấy từ server cùng guest count đã validate/normalize. Không lấy capacity từ client hoặc tự cast guest count không hợp lệ trước khi kiểm tra.

## 4. Bàn giao và ranh giới TV2

- Contract trên đã được triển khai theo kế hoạch do người dùng duyệt; chưa có bằng chứng TV2 đã nghiệm thu hoặc tích hợp caller production.
- `guestCount` là tổng số khách cho **một phòng** đang xét. Cách tính người lớn/trẻ em, độ tuổi và phân bổ nhóm nhiều phòng thuộc contract TV2 cần thống nhất; helper không nhận các trường đó.
- HTTP validator của caller chịu trách nhiệm dữ liệu raw và field errors. Query string HTTP vốn là chuỗi; chỉ đổi sang int sau khi validate canonical/range theo contract HTTP của TV2. Lỗi domain không tự gắn status HTTP mới.
- `false` là kết quả không đủ sức chứa với đầu vào hợp lệ. TV2 quyết định lọc phòng hoặc trả lỗi nghiệp vụ theo endpoint của mình. `InvalidArgumentException` phản ánh dữ liệu không đáp ứng contract, không đồng nghĩa phòng hết chỗ.
- `true` chỉ xác nhận sức chứa. Caller vẫn kiểm Property/Room status, quyền truy cập, ngày hợp lệ, booking giữ phòng và bảo trì.
- Khi tạo Booking, TV2 lấy lại dữ liệu Room hiện hành và kiểm tra lại trong transaction/chiến lược lock đã thống nhất, tránh dùng kết quả tìm kiếm cũ sau khi Host đổi capacity.
- Không thay đổi capacity hoặc guest_count của Room/Booking; không sửa lịch sử/snapshot Booking, Auth/session/authorization hoặc transaction hiện có.

## 5. Kiểm thử thực tế

Unit test dùng `PHPUnit\Framework\TestCase`, không Laravel application/TestCase, không Eloquent, fixture DB hoặc mock transport. Caller trong test cố ý không bật strict types để chứng minh scalar sai không bị ép kiểu ngầm.

Các nhóm kiểm thử:

- 11 trường hợp có expected value độc lập: sức chứa nhỏ nhất, dưới/đúng/vượt sức chứa, số khách vượt nhiều và các biên signed INT.
- 31 dữ liệu sai cho từng tham số trên PHP 64-bit: null, 0/âm, bool, float, NaN/Infinity, chuỗi số và ký pháp khác, giá trị vượt giới hạn, array/object. Trường hợp PHP_INT_MAX vượt bound chỉ áp dụng khi runtime hỗ trợ.
- Đầu vào sai vẫn ném ngoại lệ khi phép so sánh có thể trả false; thứ tự lỗi khi cả hai sai; thiếu đối số; gọi lặp cùng instance không giữ kết quả cũ.

| Kiểm tra | Kết quả |
| --- | --- |
| `php -l` hai file PHP mới | PASS, không lỗi cú pháp |
| `php artisan test --filter=RoomCapacityValidatorTest --compact` | PASS: 78 tests / 143 assertions |
| PHP CLI `php -n`, chỉ require file validator, không Composer/Laravel | PASS: đúng capacity true, vượt false, numeric string bị từ chối, Laravel không được load |
| Full backend `php artisan test --compact` | PASS, exit 0: 1243 tests / 6811 assertions, 213.81 giây, SQLite in-memory |
| `git diff --check` và kiểm tra scope/tài liệu | PASS: đúng 4 file đã duyệt, UTF-8, không trailing whitespace, liên kết tài liệu hợp lệ |
| Frontend lint/build | Không áp dụng: không thay đổi frontend |
| Availability/Booking production, browser/MySQL concurrency | Chưa tích hợp/NOT VERIFIED trong task UC52 |

Lệnh Artisan dùng `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, DB_URL rỗng, cache/session array; từ chối chạy khi có config cache. Full suite chỉ dùng DB test cô lập. Không migrate/seed/reset hoặc sửa `.env`/database dev.

## 6. Acceptance criteria và kết luận

| AC | Trạng thái | Bằng chứng |
| --- | --- | --- |
| AC1 — Đúng sức chứa true, vượt false | PASS | Unit cases độc lập gồm đúng biên và max INT |
| AC2 — Từ chối đầu vào sai, không ép kiểu | PASS | Data providers cho cả hai tham số, caller weak typing |
| AC3 — Validator thuần, không tác động dữ liệu/quyền/session | PASS | Unit không boot Laravel, PHP CLI chỉ require validator, kiểm tra source |
| AC4 — Unit và hồi quy PASS | PASS | Unit 78/143; full backend 1243/6811 |
| AC5 — Có contract bàn giao, ghi rõ dependency TV2 | PASS về tài liệu | Mục 3–4; tích hợp/nghiệm thu TV2 chưa xác nhận |

**TECHNICAL PASS cho lõi UC52 và contract bàn giao** theo phạm vi đã duyệt. Không có deviation về scope. HEAD và Git index giữ nguyên; 3 file mới cùng thay đổi mục lục còn ở working tree để review, chưa commit/push/merge. Owner acceptance sau triển khai và tích hợp TV2 được theo dõi riêng; không gọi đây là luồng đặt phòng đã hoàn thành.

Task tiếp theo đề xuất: TV2 xác nhận contract UC52 và tích hợp vào Availability/Booking khi caller production được cung cấp. Dừng sau báo cáo UC52, không tự triển khai UC khác.
