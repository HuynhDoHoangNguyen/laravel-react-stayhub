# UC23 — Quản lý lịch bảo trì phòng

## UC23-01 — Xem danh sách lịch bảo trì theo phòng

### 1. Kết quả

Ngày 22/11/2026, tạo nhánh `feature/tv1-uc23-maintenance-management` từ `origin/dev` tại `c8a83f8`. Nhánh triển khai độc lập trong lúc PR UC18–UC22 đang chờ merge.

- Backend thêm API đọc lịch bảo trì của một phòng trong phạm vi sở hữu của Host, 15 lịch mỗi trang.
- Frontend thêm khu **Lịch bảo trì** trong chi tiết phòng Host. Mỗi lịch hiển thị ngày, lý do và trạng thái. Panel có phân trang, thử tải lại và hủy request khi chuyển phòng.
- **Kiểm tra kỹ thuật đạt.** Browser QA với phiên Host thật trên môi trường thử đạt. Nghiệm thu của owner đang chờ review.

### 2. Phạm vi file

| Thao tác | File |
| --- | --- |
| Sửa | `backend/routes/api/catalog.php`, `backend/app/Validators/Catalog/CatalogValidator.php`, `backend/app/Repositories/Catalog/CatalogRepository.php`, `backend/app/Mappers/Catalog/CatalogMapper.php` |
| Tạo | `backend/app/Http/Controllers/Catalog/HostRoomMaintenanceController.php`, `backend/app/Services/Catalog/HostRoomMaintenanceQueryService.php` |
| Tạo | `backend/app/DTOs/Catalog/Requests/HostRoomMaintenanceListQuery.php`, `backend/app/DTOs/Catalog/Responses/HostRoomMaintenanceResponse.php` |
| Tạo, sửa | Tạo `backend/tests/Feature/Catalog/HostRoomMaintenanceListTest.php`, sửa `backend/tests/Feature/Auth/AuthenticationTest.php` |
| Tạo | `frontend/src/api/hostRoomMaintenanceApi.js`, `frontend/src/services/hostRoomMaintenanceService.js`, `frontend/src/mappers/hostRoomMaintenanceMapper.js`, `frontend/tests/hostRoomMaintenanceService.test.js` |
| Tạo, sửa | Tạo `frontend/src/components/catalog/HostRoomMaintenancePanel.jsx`, sửa `frontend/src/pages/host/HostRoomDetailPage.jsx` |
| Tạo, sửa | Tạo `docs/TV1/UC23-REPORT.md`, sửa `docs/TV1/README.md` |

Diff gồm 18 file đã duyệt. Thao tác đọc dùng schema, model và kết nối hiện có.

### 3. Kiểm thử

| Lệnh hoặc môi trường | Kết quả |
| --- | --- |
| `php artisan test --filter='HostRoomMaintenanceListTest\|HostRoomDetailTest\|HostRoomStatusTest\|RoomMaintenanceQueryTest\|RoomMaintenanceValidatorTest\|AuthenticationTest'` | Exit 0. 408 PASS, 2238 assertions, SQLite in-memory. Gồm 5 feature test mới |
| `php artisan test` | Exit 0. 2728 PASS, 476 skipped, 11653 assertions, cấu hình SQLite in-memory |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0. 427 PASS, 0 fail, 0 skipped. Gồm 4 test mới |
| `npm run lint` | Exit 0. 0 lỗi, 3 cảnh báo Fast Refresh có sẵn |
| `npm run build` | Exit 0. 3400 modules, cảnh báo bundle lớn hơn 500 kB |
| `git diff --check` và kiểm file untracked | Exit 0. Đúng phạm vi đã duyệt |
| Chrome headless trên bundle với API giả lập | Exit 0. 4 trạng thái, ngày không hợp lệ, retry 503, trang 15/2 mục, Tab/Enter và focus, không tràn ở 375/320 px, hủy request phòng cũ khi đổi phòng |
| Chrome 157 headless với Laravel và MariaDB 10.11.16/InnoDB | Exit 0. 9 nhóm kiểm đạt với phiên đăng nhập thật của hai Host và một Customer trên schema thử |
| `php artisan test --filter=HostRoomMaintenanceListTest --colors=never` | Exit 0. Chạy lại 5 PASS, 63 assertions, SQLite in-memory |
| `node --experimental-test-module-mocks --test tests/hostRoomMaintenanceService.test.js` | Exit 0. Chạy lại 4 PASS |

Ở lượt Chrome fixture ban đầu, trình duyệt chặn request để cấp response giả lập. Lượt browser QA bổ sung chạy bản build React hiện tại qua proxy cùng origin và API Laravel thật. Dữ liệu thử gồm hai Host, một Customer, phòng có 17 lịch với bốn trạng thái, phòng rỗng và phòng thuộc Host khác. Laravel dùng cookie, CSRF và session lưu trên MariaDB. Kết quả quan sát được:

- Host vào từ danh sách cơ sở, chọn phòng và xem **Lịch bảo trì**. Đủ 15/2 lịch, đúng thứ tự ngày và ID khi cùng ngày, định dạng ngày, lý do và bốn trạng thái. Phòng rỗng hiển thị đúng thông báo. Refresh giữ phiên và tải lại trang đầu.
- Tab/Enter chuyển trang qua nút đang khả dụng và focus quay về tiêu đề. Không tràn ngang ở 375 và 320 px, kể cả với lý do có chuỗi dài. Giả lập mất kết nối khi chuyển trang, khôi phục mạng rồi bấm **Thử tải lại lịch** đã nhận dữ liệu từ Laravel.
- Guest nhận `401`, POST đăng nhập thiếu CSRF nhận `419`, Customer nhận `403`. Hai Host đọc được phòng của mình và nhận `404` khi đọc lịch phòng của Host còn lại. Mở phòng ngoài quyền trên UI báo không tìm thấy. `page=0` trả `422`.

Bản QA dùng instance MariaDB, schema thử và hồ sơ Chrome riêng. Đã dọn ứng dụng, database, bản build và hồ sơ tạm sau lượt kiểm. Các bằng chứng áp dụng cho thao tác xem lịch.

### 4. Contract và nghiệp vụ

- `GET /api/host/rooms/{room}/maintenances?page=1` trả `200`, `data`, `meta` và `links`. Mỗi mục trả `id`, `start_date`, `end_date`, `reason`, `status`. Page mặc định là 1. Mỗi trang có tối đa 15 mục. Danh sách sắp theo `start_date DESC, id DESC`. Trang hợp lệ ngoài phạm vi trả danh sách rỗng.
- Cả `SCHEDULED`, `IN_PROGRESS`, `COMPLETED` và `CANCELLED` được đưa vào lịch sử theo phòng. Ngày chuẩn được hiển thị dạng ngày/tháng/năm. Nếu dữ liệu cũ chứa ngày lỗi, panel hiển thị “Ngày chưa hợp lệ” và dữ liệu lưu giữ nguyên.
- Sanctum và account-active kiểm tra phiên/trạng thái. Validator và QueryService kiểm vai trò Host. Repository giới hạn theo phòng và chủ cơ sở. Guest trả `401`, sai vai trò hoặc tài khoản ngưng trả `403`, phòng không tồn tại hoặc ngoài quyền sở hữu trả `404`. `page` không hợp lệ và tham số ngoài contract trả `422`.
- Panel dùng domain API và Axios chung. Lấy dữ liệu theo trang hiện tại, dọn request khi đổi trang hoặc rời phòng và giữ focus ở tiêu đề khi chuyển trang hoặc thử tải lại.

### 5. Tiêu chí nghiệm thu

| ID | Kết quả và bằng chứng |
| --- | --- |
| UC23-01-AC01 | PASS. Feature tests và browser session thật kiểm role, ownership và cách ly lịch giữa hai Host |
| UC23-01-AC02 | PASS. Browser qua API thật xác nhận phân trang 15/2, thứ tự ngày/ID, 4 trạng thái, ngày, lý do và phòng rỗng |
| UC23-01-AC03 | PASS. Browser qua API thật xác nhận 375/320 px, Tab/Enter, focus, retry, refresh và đổi sang phòng rỗng. Hủy request đang chờ đã được kiểm ở lượt Chrome fixture. Owner acceptance đang chờ review |
| UC23-01-AC04 | PASS. Laravel tập trung và full suite, Node, lint, build và diff |

### 6. Phạm vi còn mở

Nghiệm thu của owner đang chờ review kết quả browser QA. UC23-02 cần thống nhất với TV2 cách xử lý booking giữ phòng, lịch giao nhau, thứ tự khóa và đọc lại sau khóa trước khi triển khai ghi. Kiểm cạnh tranh MySQL/MariaDB thuộc luồng mutation. Khi tích hợp `dev` chứa PR UC18–UC22, kiểm lại vị trí khu Lịch bảo trì bên cạnh Hình ảnh và Tiện nghi vì hai nhánh cùng sửa trang chi tiết phòng và route Catalog.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) dùng để xác minh nền `dev`, tách API đọc khỏi workflow ghi và giữ phạm vi đã duyệt.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) dùng để tổ chức route, validator, DTO, QueryService, Repository và phân trang theo middleware/contract hiện có.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) dùng để tích hợp panel, lỗi/rỗng/tải, phân trang, focus và hủy request.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để đối chiếu AC với Feature/Node/Chrome và tách browser fixture, browser dùng API thật và nghiệm thu owner.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) dùng cho lượt browser QA để xác minh schema và engine trước khi nạp fixture, dùng session DB thật và dọn tài nguyên thử sau lượt chạy.

### 8. Bàn giao

Commit đề xuất trên nhánh mới:

```text
feat(catalog): show room maintenance history for hosts
```

Task kế tiếp đề xuất **UC23-02 — Tạo lịch bảo trì**, bắt đầu bằng chốt contract xung đột và thứ tự khóa với TV2.

---

## UC23-02A — Contract tạo lịch bảo trì và phối hợp TV2

### 1. Kết quả và phạm vi

Ngày 22/11/2026, rà trên nhánh `feature/tv1-uc23-maintenance-management` tại `c8a83f8`, cùng diff UC23-01 trong working tree. **Tài liệu đề xuất đã hoàn thành theo Mini Plan TV1 duyệt. Xác nhận từ TV2 đang chờ.** Phạm vi sửa gồm duy nhất `docs/TV1/UC23-REPORT.md`.

UC51 trả lời lịch bảo trì nào chặn một khoảng lưu trú. UC23-02B sẽ tạo lịch mới sau khi kiểm cả Booking đang giữ phòng và lịch bảo trì đang chặn. Dùng cùng quy ước giao ngày và bộ kiểm facts bảo trì của UC51. Việc đọc dữ liệu dưới khóa, kiểm quyền và ghi lịch thuộc giao dịch của caller. [Contract và giới hạn UC51](UC51-REPORT.md)

### 2. Quy tắc ngày và xung đột đề xuất

Ngày là chuỗi `YYYY-MM-DD` hợp lệ. `end_date` phải sau `start_date`. Đề xuất lịch mới bắt đầu từ ngày hiện tại trở đi. Ngày hiện tại `D` lấy một lần theo timezone ứng dụng ở bước kiểm nghiệp vụ dưới khóa. Khoảng ngày là `[start_date, end_date)`.

```text
existing_start < new_end AND existing_end > new_start
```

Chỉ kiểm bản ghi của phòng được yêu cầu. Với dữ liệu hợp lệ, khoảng `[10/12, 12/12)` và `[12/12, 14/12)` liền kề theo cả hai chiều. Phòng đang có khách lưu trú quá hạn áp dụng quy tắc riêng trong bảng.

| Fact của cùng phòng | Kết quả đề xuất khi tạo lịch |
| --- | --- |
| Booking `PENDING` hoặc `CONFIRMED`, ngày hợp lệ | Chặn nếu khoảng lưu trú giao lịch mới |
| Booking `CHECKED_IN`, ngày hợp lệ và `check_out_date > D` | Chặn nếu giao khoảng. Có thể lên lịch từ checkout dự kiến. Khi bắt đầu bảo trì, UC23-03 kiểm lại tình trạng lưu trú thực tế |
| Booking `CHECKED_IN` với `check_out_date <= D` | Chặn cho đến khi khách hoàn tất checkout qua workflow TV2 |
| Booking `PENDING`, `CONFIRMED` hoặc `CHECKED_IN` có khoảng ngày sai | Chặn. Xử lý dữ liệu qua workflow đúng quyền |
| Booking `COMPLETED`, `CANCELLED`, `REJECTED` | Bỏ qua |
| Maintenance `SCHEDULED` có khoảng ngày hợp lệ | Chặn nếu giao khoảng |
| Maintenance `IN_PROGRESS` hoặc `SCHEDULED` có khoảng ngày sai | Chặn theo UC51. `IN_PROGRESS` vẫn chặn sau ngày kết thúc dự kiến |
| Maintenance `COMPLETED` hoặc `CANCELLED` | Bỏ qua |

Khoảng ngày sai gồm ngày lịch sai, zero-date, giá trị ngoài miền DATE hoặc bắt đầu không trước kết thúc. UC20 kiểm khi thay đổi trạng thái vận hành toàn phòng và chặn mọi `CHECKED_IN`. Bảng trên áp dụng cho lịch theo khoảng ngày. Quy tắc cho phép lên lịch sau checkout dự kiến và cách xử lý check-in muộn cần TV2 xác nhận. [BookingStatus](../../backend/app/Enums/BookingStatus.php) · [Quy tắc UC20](UC20-ROOM-STATUS-RULES.md)

### 3. Đường ghi hiện tại và thứ tự khóa đề xuất

| Luồng | Hiện tại tại baseline | Đề xuất tích hợp |
| --- | --- | --- |
| UC20 đổi trạng thái phòng | Khóa Property → Room, rồi đọc Booking/Maintenance dưới khóa khi cần | Dùng làm thứ tự tham chiếu cho UC23 |
| TV2 tạo Booking | Khóa Room, sau đó gọi Availability và ghi `PENDING` | Dùng thứ tự Property → Room. Sau khóa, kiểm lại các facts quyết định giữ phòng và ghi trên cùng connection |
| TV2 xác nhận Booking | Khóa Booking, kiểm quyền, kiểm trạng thái, rồi khóa Room và kiểm Availability | Xác định ID phòng/cơ sở, khóa Property → Room → Booking, xác minh lại quan hệ, quyền và trạng thái. Kiểm Availability dưới khóa, bỏ qua Booking đang xác nhận theo rule hiện có |
| TV2 check-in | Khóa Booking rồi kiểm chuyển trạng thái, chưa khóa Room hoặc kiểm lịch bảo trì | Dùng thứ tự Property → Room → Booking. TV2 chốt thời điểm check-in hợp lệ, phép kiểm lịch khi check-in muộn và phản hồi khi xung đột |
| UC23 tạo lịch bảo trì | Chưa có đường ghi | Khóa Property → Room, đọc Booking trước Maintenance bằng locking reads, rồi ghi lịch nếu đủ điều kiện |

Thứ tự dùng chung cho các thao tác cạnh tranh là **Property → Room → Booking → Maintenance** khi cần các dòng đó. Room là khóa chung của những lần ghi cho cùng một phòng. Để tránh đảo thứ tự, tra ID phục vụ định tuyến khóa có thể thực hiện trước, còn quyền sở hữu, quan hệ và trạng thái phải được xác minh lại sau khi lấy khóa. Nếu tài nguyên đã đổi sang phòng/cơ sở khác, kết thúc lần thử và xử lý theo contract thống nhất, tránh lấy khóa parent mới sau khóa child. Đánh giá riêng các đường ghi khác nếu chúng có thể lấy cả khóa Booking và Room. [UC20 HostRoomService](../../backend/app/Services/Catalog/HostRoomService.php#L27) · [TV2 BookingService](../../backend/app/Services/Booking/BookingService.php#L76) · [TV2 BookingWorkflowService](../../backend/app/Services/Booking/BookingWorkflowService.php#L29)

Hai vấn đề cần giải quyết trước khi bật thao tác tạo lịch:

- **Vòng chờ khóa.** Với thứ tự TV2 xác nhận hiện tại, xác nhận có thể giữ Booking và chờ Room, trong khi UC23 giữ Room và chờ Booking. Đây là rủi ro từ đường code. Cần quan sát trên InnoDB khi kiểm chứng tích hợp.
- **Snapshot cũ.** `manageHost` ở luồng xác nhận có thể đọc Room/Property trước khi khóa Room. Với `REPEATABLE READ`, truy vấn thường sau đó có thể dùng lại snapshot đã tạo. Lần kiểm quyết định ghi cần current locking reads trên cùng connection sau khi lấy khóa Room. Truyền facts đã khóa vào bộ kiểm UC51, thay vì gọi helper đang dùng SELECT thường. Khóa Property độc quyền có thể làm thao tác ở các phòng khác nhau cùng cơ sở chờ nhau, cần đo trong QA thực tế.

[BookingPolicy](../../backend/app/Policies/BookingPolicy.php#L32) · [Truy vấn khóa Catalog](../../backend/app/Repositories/Catalog/CatalogRepository.php#L107) · [InnoDB consistent reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-consistent-read.html) · [InnoDB locking reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)

### 4. Contract HTTP dự kiến cho UC23-02B

`POST /api/host/rooms/{room}/maintenances` nhận `start_date`, `end_date` và `reason` tùy chọn. Server lấy phòng từ URL, xác minh quyền Host và đặt trạng thái `SCHEDULED`. Trả `201` sau commit. Lỗi ngày/đầu vào trả `422`. Phòng không tìm thấy hoặc ngoài quyền sở hữu trả `404`.

Khi trùng Booking hoặc lịch bảo trì, trả `409` với thông báo tiếng Việt và rollback. Nếu cả hai cùng chặn, ưu tiên thông báo Booking. Phản hồi chỉ mô tả nguyên nhân, giữ thông tin khách riêng tư. Lỗi kỹ thuật như timeout khóa vẫn đi qua cơ chế xử lý lỗi kỹ thuật, tách khỏi xung đột nghiệp vụ. Form thêm lịch ở panel UC23-01 sẽ tải lại danh sách sau khi tạo thành công.

### 5. Quyết định cần TV2 xác nhận

| ID | Nội dung xác nhận | Trạng thái |
| --- | --- | --- |
| TV2-01 | Chặn theo khoảng cho booking giữ phòng hợp lệ. Chặn `CHECKED_IN` quá hạn và booking giữ phòng có ngày sai. Chốt rule check-in muộn và kiểm lại trước khi bắt đầu bảo trì | Chờ TV2 |
| TV2-02 | Chốt thứ tự khóa và current read cho tạo, xác nhận, check-in. Kiểm quyền và quan hệ trên dữ liệu đã khóa. Xác nhận các writer liên quan có tuân cùng thứ tự khi dùng nhiều khóa | Chờ TV2 |
| TV2-03 | Đồng bộ kiểm một phòng, tìm nhiều phòng và kiểm trước ghi Booking. Query TV2 hiện xét giao ngày, còn UC51 chặn cả `IN_PROGRESS` quá hạn và `SCHEDULED` ngày lỗi. Query tìm nhiều phòng cần giữ xử lý theo tập hợp | Chờ TV2 |

[AvailabilityService](../../backend/app/Services/Booking/AvailabilityService.php#L36) · [RoomAvailabilityRepository](../../backend/app/Repositories/Booking/RoomAvailabilityRepository.php#L74) · [Bộ kiểm facts UC51](../../backend/app/Validators/Catalog/RoomMaintenanceValidator.php#L36)

### 6. Tiêu chí kiểm chứng và trạng thái bàn giao

| ID | Kỳ vọng | Trạng thái |
| --- | --- | --- |
| UC23-02A-AC01 | Ma trận ghi rõ giao ngày, hai biên liền kề, giữ phòng, quá hạn và ngày lỗi | Đã ghi và đối chiếu UC20/UC51/BookingStatus |
| UC23-02A-AC02 | Sơ đồ khóa mô tả create, confirm, check-in và UC23, cùng rủi ro snapshot và vòng chờ | Đã ghi và đối chiếu source |
| UC23-02A-AC03 | Quy tắc và phạm vi tích hợp được TV2 xác nhận | Chờ TV2, điều kiện trước khi bật POST |
| UC23-02A-AC04 | Phạm vi tài liệu, đường dẫn nguồn và whitespace được kiểm | Đạt sau kiểm tra cuối |

UC23-02B cần test quyền, ngày liền kề, từng nhóm trạng thái, hủy ghi khi `409` và cập nhật panel. UC23-02C cần hai kết nối MySQL/MariaDB trên schema thử để kiểm các ca sau:

- Đảo cả hai thứ tự commit của Booking create và Maintenance create với khoảng giao nhau. Chỉ một bản ghi được commit, bên còn lại trả xung đột theo contract.
- Hai yêu cầu tạo lịch trùng cho một phòng. Một yêu cầu tạo thành công, yêu cầu sau thấy lịch vừa commit và trả `409`.
- Booking `PENDING` đã tồn tại và trùng lịch mới. Khi xác nhận Booking và tạo lịch chạy cùng lúc, lịch bị chặn. Xác nhận vẫn qua các kiểm tra Booking hiện hành và hai giao dịch hoàn tất trong thời gian giới hạn.
- Tạo snapshot cũ trước khi chờ khóa, rồi kiểm lại dưới khóa và thấy dữ liệu vừa commit. Thêm ca đối chứng giao dịch đầu rollback để phép kiểm sau không giữ xung đột giả.

Lượt này kiểm source, phạm vi Git, liên kết và tài liệu. Bằng chứng runtime thuộc các task triển khai và QA đồng thời tiếp theo.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) dùng để giới hạn scope tài liệu và tách quyết định cần TV2 xác nhận.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) dùng để đối chiếu các điểm vào Booking, policy, thứ tự transaction và contract HTTP.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) dùng để phân tích khóa theo Room, snapshot và kịch bản hai kết nối.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để đối chiếu tiêu chí với source và tách kết quả review khỏi kiểm chứng runtime.

### 8. Bàn giao

Bước tiếp theo là TV2 xác nhận `TV2-01` đến `TV2-03` và thống nhất scope tích hợp trước khi mở POST tạo lịch. Commit đề xuất cho phần tài liệu:

```text
docs(catalog): define maintenance and booking conflict contract
```

---

## UC23-02B — Lõi transaction tạo lịch phía TV1

### 1. Kết quả

Ngày 22/11/2026, triển khai trên `feature/tv1-uc23-maintenance-management` tại `c8a83f8`, tiếp nối diff UC23-01 và tài liệu UC23-02A. Owner đã gửi đề xuất cho TV2 và yêu cầu triển khai phần TV1 dựa trên contract đó.

Backend có service nội bộ để kiểm quyền, validation, xung đột lịch và persist `SCHEDULED`. Phần này được kiểm qua service thật trên SQLite. POST và form Host thuộc bước nối tiếp sau khi các đường ghi TV2 có cùng thứ tự khóa và đã được kiểm chứng. Quyết định này giữ cổng triển khai runtime vì `BookingWorkflowService::confirm()` hiện vẫn khóa Booking trước Room.

### 2. Exact file scope

| Thao tác | File |
| --- | --- |
| Tạo | `backend/app/DTOs/Catalog/Requests/CreateHostRoomMaintenanceRequest.php` |
| Tạo | `backend/app/Validators/Catalog/HostRoomMaintenanceValidator.php`, `backend/app/Services/Catalog/HostRoomMaintenanceService.php` |
| Sửa | `backend/app/Validators/Catalog/RoomMaintenanceValidator.php`, `backend/app/Repositories/Catalog/CatalogRepository.php` |
| Tạo | `backend/tests/Feature/Catalog/HostRoomMaintenanceServiceTest.php` |
| Sửa | `docs/TV1/UC23-REPORT.md` |

### 3. Kiểm thử

| Lệnh hoặc kiểm tra | Kết quả |
| --- | --- |
| `php artisan test --filter='HostRoomMaintenanceServiceTest\|HostRoomMaintenanceListTest\|RoomMaintenanceQueryTest\|RoomMaintenanceValidatorTest'` | Exit 0. 171 PASS, 405 assertions, SQLite in-memory |
| `php artisan test` | Exit 0. 2760 PASS, 476 skipped, 11762 assertions, cấu hình SQLite in-memory |
| `php -l` trên sáu file PHP thuộc scope, `git diff --check`, whitespace và liên kết | Exit 0. Kiểm Git diff và hash trước/sau để xác nhận phạm vi |

Đây là bằng chứng hành vi qua service và repository thật. SQLite xác nhận ghi dữ liệu, phân quyền domain và rollback. Thứ tự lock được đối chiếu source. Chờ QA hai kết nối trên InnoDB để chứng minh hành vi chờ khóa/current read trong runtime.

### 4. Contract nội bộ

- `HostRoomMaintenanceService::create(actor, input)` kiểm vai trò Host và tìm phòng theo chủ cơ sở, rồi xác minh lại dưới khóa Property → Room. Phòng không thuộc Host hoặc không tồn tại trả `null` để HTTP boundary sau này trả `404`.
- Ngày dùng quy tắc canonical của UC51. Service kiểm ngày bắt đầu từ hôm nay theo timezone ứng dụng, ngày kết thúc sau ngày bắt đầu. Lý do là chuỗi tùy chọn trong miền lưu được của cột TEXT, tối đa 65.535 byte.
- Kiểm Booking giữ phòng trước rồi Maintenance. Hai khoảng chỉ chạm biên được phép. Booking `CHECKED_IN` tới/quá ngày trả phòng hoặc booking giữ phòng có ngày sai sẽ chặn. Lịch `IN_PROGRESS` và lịch `SCHEDULED` có ngày sai chặn theo UC51.
- Nếu cả hai loại vật cản tồn tại, lỗi Booking được ưu tiên. Service dùng hai mã Catalog 409 có sẵn. Khi đủ điều kiện, ghi một lịch `SCHEDULED` và trả DTO đã dùng ở UC23-01. Log sau commit dùng ID phòng/lịch. Câu lệnh ghi lưu chuỗi DATE chuẩn để UC51 đọc đúng cả trên SQLite.

### 5. Tiêu chí và trạng thái

| ID | Kết quả |
| --- | --- |
| UC23-02B-CORE-AC01 | PASS. Service cho Host sở hữu phòng, từ chối role sai, phòng khác chủ và phòng thiếu |
| UC23-02B-CORE-AC02 | PASS. Ma trận giao/kề ngày, checkout quá hạn, ngày sai, trạng thái terminal, ưu tiên lỗi Booking, cách ly phòng |
| UC23-02B-CORE-AC03 | PASS. Lịch mới lưu DATE chuẩn và UC51 thấy ngay. Lỗi sau insert rollback. Kiểm ngày theo timezone ứng dụng |
| UC23-02B-INTEGRATION | Chờ contract và thay đổi liên quan từ TV2, nối POST/UI và QA cạnh tranh InnoDB |

### 6. Phụ thuộc

TV2 vẫn cần xác nhận rule check-in muộn, thứ tự khóa và cách đồng bộ Availability với UC51. Chỉ sau đó mới nối entry point Host vào service. API GET và panel xem lịch đã có từ UC23-01.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) dùng để giới hạn phần lõi có thể làm trong lúc TV2 xem đề xuất.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) dùng để tách DTO, validator, transaction và repository theo kiến trúc Catalog.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) dùng để giữ thứ tự khóa và DATE gốc, phân biệt SQLite với InnoDB.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để kiểm ma trận nghiệp vụ, rollback và phạm vi.

### 8. Bàn giao

Bước kế tiếp là rà phản hồi hoặc diff của TV2 trước khi nối POST và form Host. Commit đề xuất cho lõi:

```text
feat(catalog): add transactional room maintenance creation core
```

## UC23-02C1 — Kiểm thử InnoDB phía TV1

### 1. Kết quả

Ngày 22/11/2026, bốn ca kiểm đạt trên MariaDB 10.11.16/InnoDB. Hai tiến trình PHP gọi cùng service tạo lịch bảo trì và dùng hai kết nối có ID khác nhau. Process list xác nhận câu `SELECT ... FOR UPDATE` của request thứ hai tại Property trong lúc request thứ nhất còn giữ khóa. Đây là lượt QA Backend. Nghiệm thu của owner đang chờ review.

### 2. Phạm vi

| Thao tác | File |
| --- | --- |
| Tạo | [backend/tests/Feature/Catalog/HostRoomMaintenanceConcurrencyMySqlTest.php](../../backend/tests/Feature/Catalog/HostRoomMaintenanceConcurrencyMySqlTest.php) |
| Tạo | [backend/tests/Support/HostRoomMaintenanceRaceWorker.php](../../backend/tests/Support/HostRoomMaintenanceRaceWorker.php) |
| Sửa | `docs/TV1/UC23-REPORT.md` |

### 3. Kiểm thử

| Lệnh | Kết quả |
| --- | --- |
| `php artisan test --filter=HostRoomMaintenanceConcurrencyMySqlTest --testdox` | Exit 0. 4 PASS, 54 assertions, MariaDB 10.11.16/InnoDB |
| `php artisan test --filter='HostRoomMaintenanceServiceTest\|HostRoomMaintenanceListTest\|RoomMaintenanceQueryTest\|RoomMaintenanceValidatorTest'` | Exit 0. 171 PASS, 405 assertions, SQLite in-memory |
| `php artisan test --colors=never` | Exit 0. 2.760 PASS, 480 skipped, 11.762 assertions. Cấu hình SQLite mặc định bỏ qua bốn ca InnoDB theo opt-in |
| `php -l` cho hai file PHP mới, `git diff --check` và kiểm whitespace, links, phạm vi theo hash | Exit 0 |

### 4. Điều kiện chạy lại

Chạy lệnh InnoDB từ `backend/` với schema riêng `stayhub_uc23_<32 ký tự hex>_test`, tài khoản `uc23_<16 ký tự hex>` chỉ có quyền trên schema đó và kết nối TCP loopback. Đặt `STAYHUB_UC23_TEST_DATABASE` trùng `DB_DATABASE` và `STAYHUB_UC23_TEST_SERVER_HOSTNAME` trùng `@@hostname`. Môi trường tiến trình dùng `APP_ENV=testing`, `ALLOW_EXTERNAL_TEST_DB=true`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, cổng và thông tin tài khoản đã cấp, `CACHE_STORE=array`, `SESSION_DRIVER=array`. Đặt `DB_URL`, `DATABASE_URL` và `DB_SOCKET` rỗng. Bộ test tự chạy migration cho schema thử rỗng và xác minh bốn bảng liên quan dùng InnoDB. Dọn schema thử cùng fixture sau khi chạy.

Docker chưa chạy vào thời điểm QA. Lượt này dùng binary MariaDB của XAMPP với instance tạm, port riêng và thư mục dữ liệu mới. Instance và dữ liệu tạm đã được dọn sau khi test.

### 5. Tiêu chí và bằng chứng

| ID | Kết quả |
| --- | --- |
| C1-01 | PASS. Hai kết nối khác ID. Request thứ hai vào câu khóa trong khi request đầu chưa commit. Sau commit chỉ còn một lịch và request còn lại nhận `409 CATALOG_ROOM_MAINTENANCE_CONFLICT` |
| C1-02 | PASS. Hai khoảng liền kề cùng commit, lưu đúng ngày và trạng thái `SCHEDULED` |
| C1-03 | PASS. Request thứ hai tạo snapshot `REPEATABLE READ` trước khi chờ. Sau khi request đầu commit, query khóa phát hiện xung đột dù plain read vẫn thấy snapshot ban đầu |
| C1-04 | PASS. Request đầu rollback, request chờ tạo được lịch. DB chỉ còn đúng bản ghi của request thứ hai |
| Guard DB thử | PASS. Worker từ chối cấu hình trỏ schema `stayhub` ngay trước khi boot ứng dụng |

### 6. Phụ thuộc

Bằng chứng InnoDB hiện bao phủ hai request bảo trì. Các ca cạnh tranh với Booking thật, thứ tự khóa của TV2 và Availability vẫn thuộc C2.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) xác định phạm vi C1 có thể triển khai trong lúc chờ tích hợp TV2.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) giúp gọi service thật và quan sát kết quả khi transaction ngoài commit hoặc rollback.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) hướng dẫn kiểm schema, engine, khóa và snapshot trên hai kết nối.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) đối chiếu từng AC với dữ liệu đã commit và tách kết quả MariaDB khỏi SQLite.

### 8. Bàn giao

Đề xuất UC23-02C2 khi có bản tích hợp TV2 để kiểm cạnh tranh Booking ↔ Maintenance. Commit đề xuất cho C1:

```text
test(catalog): verify maintenance creation concurrency on InnoDB
```

## UC23-02C2A — Kiểm thử cạnh tranh tạo Booking và Maintenance

### 1. Kết quả

Ngày 22/11/2026, chạy trên nhánh `feature/tv1-uc23-maintenance-management`, HEAD `c8a83f8` cùng diff UC23 hiện có. Đã hoàn thành lượt QA Backend. **FAIL tích hợp** khi Booking có snapshot `REPEATABLE READ` từ trước lúc chờ khóa. Hai lượt trên schema MariaDB/InnoDB riêng đều lưu đồng thời một Booking `PENDING` và một Maintenance `SCHEDULED` cho cùng phòng và khoảng `[2026-12-12, 2026-12-14)`. Owner acceptance đang chờ review. POST và form Host chờ nghiệm thu tích hợp.

### 2. Phạm vi

| Thao tác | File |
| --- | --- |
| Tạo | [backend/tests/Feature/Catalog/BookingMaintenanceConcurrencyMySqlTest.php](../../backend/tests/Feature/Catalog/BookingMaintenanceConcurrencyMySqlTest.php) |
| Sửa | [backend/tests/Support/HostRoomMaintenanceRaceWorker.php](../../backend/tests/Support/HostRoomMaintenanceRaceWorker.php) |
| Sửa | `docs/TV1/UC23-REPORT.md` |

### 3. Kiểm thử

| Lệnh | Kết quả |
| --- | --- |
| `php artisan test '--filter=BookingMaintenanceConcurrencyMySqlTest\|HostRoomMaintenanceConcurrencyMySqlTest' --testdox --colors=never` | Exit 1. MariaDB 10.11.16/InnoDB, 7 PASS, 1 FAIL, 92 assertions. C1 đạt cả 4 ca |
| `php artisan test '--filter=older booking snapshot' --testdox --colors=never` | Exit 1. Schema InnoDB mới, 1 FAIL, 12 assertions. Kết quả hai bản ghi giao ngày lặp lại |
| `php artisan test '--filter=CustomerBookingTest\|BookingCreationValidationTest\|BookingInputIntegrityTest\|HostBookingWorkflowTest\|AvailabilityServiceTest\|RoomAvailabilityApiTest\|HostRoomMaintenanceServiceTest\|HostRoomMaintenanceListTest\|RoomMaintenanceQueryTest\|RoomMaintenanceValidatorTest' --colors=never` | Exit 0. 262 PASS, 761 assertions, SQLite in-memory |
| `php artisan test --colors=never` | Exit 0. 2.760 PASS, 484 skipped, 11.762 assertions. Cấu hình mặc định bỏ qua các ca InnoDB theo opt-in |
| `php -l` trên hai file PHP, `git diff --check`, whitespace và liên kết báo cáo | Exit 0 |

Hai lượt InnoDB dùng instance MariaDB tạm từ bộ XAMPP, cổng loopback riêng và schema thử. Đã dọn instance và dữ liệu của từng lượt. Điều kiện cấu hình chạy lại dùng chung với C1.

### 4. Contract và bằng chứng

| Tình huống | Quan sát trên InnoDB |
| --- | --- |
| Booking commit trước | Booking `PENDING` tồn tại. Tạo bảo trì giao ngày nhận `409 CATALOG_ROOM_BOOKING_CONFLICT` |
| Maintenance commit trước, Booking bắt đầu với snapshot mới | Maintenance `SCHEDULED` tồn tại. Booking nhận `409`, lý do `MAINTENANCE_OVERLAP` |
| Maintenance commit khi Booking đã đọc snapshot cũ | Booking trả thành công ở `PENDING`. Truy vấn riêng sau commit thấy cả hai bản ghi giao ngày. Kỳ vọng là `409 MAINTENANCE_OVERLAP` và chỉ còn lịch bảo trì |
| Maintenance rollback trong lúc Booking chờ | Chỉ Booking `PENDING` commit |

Worker đặt `REPEATABLE READ` và dùng transaction ngoài để tạo snapshot trước khi gọi service Booking ở ca thứ ba. Test quan sát câu `SELECT ... FOR UPDATE` trên hai kết nối khác ID trước khi cho request giữ khóa kết thúc. Sau khi service chạy, plain read trong transaction Booking vẫn thấy 0 lịch. Kết quả phù hợp với luồng TV2 hiện khóa Room rồi dùng plain read để kiểm Maintenance. Test assertion giữ nguyên kết quả theo contract khi đối chiếu ca này. [BookingService](../../backend/app/Services/Booking/BookingService.php#L76) · [Truy vấn Availability](../../backend/app/Repositories/Booking/RoomAvailabilityRepository.php#L74)

### 5. Tiêu chí nghiệm thu

| ID | Trạng thái và bằng chứng |
| --- | --- |
| C2A-01 | Đạt. Hai kết nối khác ID và đã quan sát câu truy vấn khóa trong khi request đầu còn giữ transaction |
| C2A-02 | Đạt. Hai thứ tự ghi trong các ca đối chứng cho đúng status, lý do xung đột và bản ghi đã commit |
| C2A-03 | Đã kiểm. Ca rollback đạt. Ca snapshot cũ **FAIL contract** và lặp lại trên schema thứ hai |
| C2A-04 | C1 và hồi quy liên quan đã chạy. Đối chiếu hash trước/sau và `git diff --check` xác nhận phạm vi |

### 6. Phụ thuộc còn lại

TV2 cần phối hợp phần đọc dữ liệu hiện tại dưới khóa trong Availability khi ghi Booking. Với `confirm()`, thứ tự Booking → Room vẫn là phát hiện từ source. C2B sẽ kiểm xác nhận Booking đang `PENDING` đồng thời với tạo bảo trì trên bản tích hợp. C2C sẽ kiểm quy tắc check-in và Availability theo contract thống nhất. [BookingWorkflowService](../../backend/app/Services/Booking/BookingWorkflowService.php#L29)

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) xác định scope test và bàn giao dependency TV2.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) dùng service, DTO và kết quả lỗi thật trong các ca chạy.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) kiểm engine, khóa, snapshot và dữ liệu sau commit qua kết nối độc lập.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) chạy lại ca lỗi và tách trạng thái InnoDB khỏi hồi quy SQLite.

### 8. Bàn giao

Đề xuất C2B sau khi có bản tích hợp TV2. Commit test có thể dùng để bàn giao ca tái hiện. Quyết định merge C2 chờ ca snapshot đạt trên bản tích hợp.

```text
test(catalog): cover booking and maintenance write races
```

## UC23-03A — Đề xuất contract sửa, bắt đầu, hoàn tất và hủy lịch

### 1. Kết quả và phạm vi

Ngày 22/11/2026, đối chiếu workflow TV1 trên nhánh `feature/tv1-uc23-maintenance-management`. Schema đã có đủ bốn trạng thái. Panel chi tiết phòng đang dùng GET để xem lịch. Phân công TV1 liệt kê thêm PUT và DELETE. Phần này ghi đề xuất cho các thao tác ghi và các quyết định còn chờ review. Phạm vi thay đổi là tài liệu UC23. [Phân công TV1](../stayhub-team-work-assignment.md#35-api-chính) · [Schema](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php#L107) · [Panel hiện tại](../../frontend/src/components/catalog/HostRoomMaintenancePanel.jsx)

### 2. Ma trận nghiệp vụ đề xuất

| Thao tác | Trạng thái nguồn | Kết quả | Điều kiện chính |
| --- | --- | --- | --- |
| Sửa ngày hoặc lý do | `SCHEDULED` | Giữ `SCHEDULED` | Giữ phòng theo bản ghi. Ngày theo `[start_date, end_date)`, `start_date >= D` và `end_date > start_date`. Kiểm lại Booking và các lịch khác, bỏ chính lịch đang sửa khỏi tập so sánh |
| Bắt đầu | `SCHEDULED` | `IN_PROGRESS` | Đề xuất chỉ bắt đầu khi `start_date <= D < end_date`. Nếu lịch trước ngày bắt đầu hoặc đã tới ngày kết thúc, Host sửa khoảng ngày trước. Kiểm lại khách đang ở và các xung đột theo mục 3 |
| Hoàn tất | `IN_PROGRESS` | `COMPLETED` | Host xác nhận công việc đã xong. Cho phép hoàn tất lịch quá hạn. Kể từ commit, UC51 bỏ lịch này khỏi tập chặn |
| Hủy | `SCHEDULED` | `CANCELLED` | Host xác nhận hủy. Lưu trạng thái và giữ bản ghi trong lịch sử, kể cả lịch `SCHEDULED` đã quá hạn |
| Hủy công việc đang làm | `IN_PROGRESS` | Ứng viên `CANCELLED` | Chờ owner quyết định. Nếu được duyệt, UI cần xác nhận công việc đã dừng và phòng đã an toàn để sử dụng |
| Sửa, bắt đầu hoặc mở lại lịch đã đóng | `COMPLETED`, `CANCELLED` | Lỗi `409` | Trạng thái kết thúc là trạng thái cuối. Riêng lần gửi lại cùng thao tác đích theo mục 4 dùng kết quả hiện có |

`D` là ngày ứng dụng, chốt một lần trong transaction sau khi lấy các khóa cần thiết. Lịch cũ có ngày sai cần chỉnh về khoảng hợp lệ trước khi bắt đầu. Hoàn tất và hủy thao tác trên trạng thái để vẫn xử lý được dữ liệu cũ theo quyền. Các thao tác UC23 ghi lịch bảo trì. Trạng thái vận hành toàn phòng thuộc UC20. [Quy tắc UC20](UC20-ROOM-STATUS-RULES.md) · [Quy tắc UC51](UC51-REPORT.md)

### 3. Nhất quán với Booking và thứ tự khóa

- Sửa lịch dùng lại quy tắc xung đột của UC23-02B. Khóa `Property → Room → Booking → Maintenance`, rồi kiểm ngày và quyền sở hữu theo dữ liệu đã khóa. Tra ID để định tuyến khóa có thể thực hiện trước. Khi lấy nhóm Maintenance, giữ thứ tự ID và loại lịch đích khỏi tập dùng để kiểm xung đột. Sau đó mới ghi. Hai khoảng liền kề vẫn được phép.
- Bắt đầu cũng kiểm Booking và Maintenance dưới cùng thứ tự khóa. Chặn nếu phòng có khách `CHECKED_IN` thực tế, dù checkout dự kiến đã qua. Lịch bảo trì khác giao ngày hoặc đang `IN_PROGRESS` cũng chặn. Xử lý lịch khác có ngày sai theo UC51.
- UC51 xem `IN_PROGRESS` là trạng thái chặn phòng trên mọi khoảng lưu trú, kể cả sau ngày kết thúc dự kiến. Vì vậy một Booking tương lai giữ phòng có thể chịu ảnh hưởng dù nằm ngoài lịch dự kiến. **Đề xuất thận trọng** là chặn bắt đầu nếu còn `PENDING` hoặc `CONFIRMED` có checkout sau `D`, hoặc Booking giữ phòng có ngày sai. Nếu cần cho phép bắt đầu trước Booking tương lai, phải chốt cách TV2 bảo toàn cam kết với Booking đã nhận và kiểm phòng thực tế trước khi cho khách vào.
- Hoàn tất và hủy dùng `Property → Room → Maintenance` vì chỉ đổi trạng thái lịch đã có. Xác minh lại quyền, quan hệ và trạng thái trong transaction. Sau commit, Availability vẫn xét các điều kiện khác của phòng, gồm Booking, lịch khác và trạng thái UC20.
- Hai bên cần dùng cùng giao thức lấy khóa và đọc facts trên cùng connection. C2A đã tái hiện Booking đọc snapshot cũ và ghi đồng thời với Maintenance. C2B/C2C cung cấp bằng chứng tích hợp cho các thao tác sửa ngày hoặc bắt đầu làm tăng phạm vi chặn phòng. [Repository Catalog](../../backend/app/Repositories/Catalog/CatalogRepository.php#L108) · [Service tạo lịch](../../backend/app/Services/Catalog/HostRoomMaintenanceService.php#L27)

### 4. Contract HTTP và UI đề xuất

| Endpoint | Request | Kết quả thành công |
| --- | --- | --- |
| `PUT /api/host/maintenances/{maintenance}` | `start_date`, `end_date`, `reason` là chuỗi hoặc `null` | `200`, lịch đã lưu |
| `PATCH /api/host/maintenances/{maintenance}/status` | `status` nhận `IN_PROGRESS` hoặc `COMPLETED` | `200`, lịch ở trạng thái đích |
| `DELETE /api/host/maintenances/{maintenance}` | Theo ID và phiên Host | `200`, cùng bản ghi với `status=CANCELLED` để panel cập nhật lịch sử |

Đề xuất trả `data` theo DTO hiện có gồm `id`, `start_date`, `end_date`, `reason`, `status`. PUT chỉ nhận trường chỉnh sửa trong bảng. Ngày dùng định dạng `YYYY-MM-DD`, lý do theo giới hạn lưu trữ hiện có. Lỗi đầu vào trả `422`, guest `401`, sai vai trò hoặc tài khoản ngưng `403`, ngoài quyền sở hữu hoặc không tìm thấy `404`. Dùng hai mã xung đột Booking/Maintenance hiện có cho `409`. Đề xuất `CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT` nếu trạng thái nguồn cản thao tác hoặc lịch lưu chưa đủ điều kiện bắt đầu theo ngày. Khi trạng thái đã bằng chính đích của thao tác gửi lại, trả `200` với bản ghi hiện có và tránh ghi hoặc log thành công lần hai. [DTO trả về](../../backend/app/DTOs/Catalog/Responses/HostRoomMaintenanceResponse.php) · [HTTP response Catalog](../../backend/app/Common/ApiResponse.php)

Điểm vào đề xuất là panel chi tiết phòng Host. Thẻ lịch chỉ hiện action phù hợp trạng thái, có xác nhận cho hủy, chống gửi trùng khi đang lưu và tải lại danh sách sau thành công hoặc xung đột trạng thái. Mockup mô tả thêm một trang tổng hợp. Owner cần xác nhận panel theo phòng đã đủ cho V1 hay cần thêm trang tổng hợp. [Mockup Maintenance](../stayhub-screen-mockups.md#51-maintenance-list) · [Hướng dẫn Frontend](../ai-frontend-development-guide.md)

### 5. Ma trận nghiệm thu cho các task triển khai

| ID | Bằng chứng cần có |
| --- | --- |
| 03-EDIT | Sửa lịch của chính Host, refresh thấy dữ liệu đã lưu. Kiểm xung đột bỏ qua ID của lịch đang sửa. Giao lịch khác hoặc Booking giữ phòng trả `409`. Hai biên liền kề được phép. Ngày sai trả `422` |
| 03-START | Bắt đầu trong khoảng hợp lệ. Chặn khách đang ở, Booking tương lai theo rule đã duyệt, lịch khác đang làm, ngày ngoài khoảng và chuyển trạng thái sai. So sánh Availability trước/sau commit |
| 03-END | Hoàn tất lịch đang làm kể cả quá hạn. Hủy đúng trạng thái đã duyệt, giữ bản ghi và kiểm UC51 bỏ qua lịch đã đóng. Gửi lặp cho cùng kết quả lịch |
| 03-ACCESS | Session thật, Host khác không thấy thông tin hoặc sửa được lịch. Bàn phím, mobile, loading, lỗi `409`/`422`, refresh và điều hướng sang phòng khác |
| 03-DB | Hai kết nối InnoDB kiểm cạnh tranh sửa/bắt đầu với Booking và cạnh tranh giữa các action trên cùng lịch. Đọc trạng thái hiện tại sau khóa, kết quả commit/rollback đúng |

Các tiêu chí trên dành cho 03B–03D. Tiêu chí của 03A là có ma trận trạng thái, API, thứ tự khóa, case QA và các điểm chờ chủ sở hữu duyệt.

### 6. Quyết định còn mở

| ID | Cần xác nhận | Chủ sở hữu |
| --- | --- | --- |
| 03A-01 | Chấp nhận cửa sổ bắt đầu `start_date <= D < end_date` và quy tắc chặn khi còn Booking tương lai. Đồng bộ với quy tắc check-in muộn và Availability | Owner TV1 và TV2 |
| 03A-02 | Có cho `IN_PROGRESS → CANCELLED` khi công việc phải dừng hay chỉ kết thúc qua `COMPLETED` | Owner UC23 |
| 03A-03 | Chấp nhận panel theo phòng cho V1, hoặc đưa trang tổng hợp vào một plan riêng | Owner UC23 |

### 7. Skills và kiểm tài liệu

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) xác định phạm vi độc lập và các quyết định cần chốt.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) đối chiếu thứ tự khóa, cách xét Booking và bản ghi được đọc trước khi ghi.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) lập tiêu chí nghiệm thu cho nghiệp vụ, HTTP, UI và InnoDB.

Kiểm tài liệu bằng đối chiếu schema, controller, DTO, UC20/UC51, cùng kiểm Git diff, phạm vi file, khoảng trắng và liên kết. Các ca runtime ở mục 5 thuộc lượt triển khai.

### 8. Bàn giao

Tài liệu 03A sẵn sàng để owner review. Trong lúc chờ TV2, có thể thực hiện lượt browser QA độc lập cho GET và panel xem lịch UC23-01. Thao tác ghi dùng các điều kiện tích hợp tương ứng trước khi mở cho Host. Commit đề xuất cho phần tài liệu:

```text
docs(catalog): propose room maintenance lifecycle contract
```

## UC23-02C2B — Kiểm cạnh tranh xác nhận Booking và tạo bảo trì

### 1. Kết quả

Ngày 22/11/2026, chạy trên `feature/tv1-uc23-maintenance-management` tại HEAD `c8a83f8` cùng diff UC23 hiện có. Hai kết nối MariaDB/InnoDB gọi hai service thật với cùng một phòng, một Booking `PENDING` và khoảng ngày giao nhau. **FAIL tích hợp.** Khi phía bảo trì lấy khóa Room trước, hai luồng rơi vào deadlock. Đây là lượt QA Backend. Nghiệm thu owner đang chờ review.

### 2. Phạm vi

| Thao tác | File |
| --- | --- |
| Sửa | [BookingMaintenanceConcurrencyMySqlTest.php](../../backend/tests/Feature/Catalog/BookingMaintenanceConcurrencyMySqlTest.php) |
| Sửa | [HostRoomMaintenanceRaceWorker.php](../../backend/tests/Support/HostRoomMaintenanceRaceWorker.php) |
| Sửa | `docs/TV1/UC23-REPORT.md` |

### 3. Kiểm thử

| Lệnh | Kết quả |
| --- | --- |
| `php artisan test '--filter=BookingMaintenanceConcurrencyMySqlTest\|HostRoomMaintenanceConcurrencyMySqlTest' --testdox --colors=never` | Exit 1. 8 PASS, 2 FAIL, 118 assertions trên MariaDB 10.11.16/InnoDB. C1 đạt 4/4, C2A đạt 3/4, C2B đạt 1/2 |
| `php artisan test '--filter=maintenance locks first' --testdox --colors=never` | Exit 1 trên schema riêng thứ hai. 1 FAIL, 14 assertions. Lặp lại deadlock `1213` với cùng thứ tự khóa và dữ liệu sau commit |
| `php artisan test '--filter=CustomerBookingTest\|BookingCreationValidationTest\|BookingInputIntegrityTest\|HostBookingWorkflowTest\|AvailabilityServiceTest\|RoomAvailabilityApiTest\|HostRoomMaintenanceServiceTest\|HostRoomMaintenanceListTest\|RoomMaintenanceQueryTest\|RoomMaintenanceValidatorTest' --colors=never` | Exit 0. 262 PASS, 761 assertions, SQLite in-memory |
| `php artisan test --colors=never` | Exit 0. 2.760 PASS, 486 skipped, 11.762 assertions. Cấu hình mặc định bỏ qua các ca InnoDB theo opt-in |
| PHP lint hai file test, `git diff --check`, phạm vi file, khoảng trắng và liên kết | Exit 0 |

Hai lượt InnoDB dùng instance MariaDB tạm từ XAMPP, mỗi lượt có cổng loopback, thư mục dữ liệu và schema thử riêng. Dữ liệu thử và instance đã được dọn.

### 4. Bằng chứng nghiệp vụ và khóa

| Thứ tự | Kết quả quan sát |
| --- | --- |
| Xác nhận Booking lấy khóa trước | Maintenance đợi khóa đang giữ. Sau commit, DB có một Booking `CONFIRMED`, Maintenance nhận `409 CATALOG_ROOM_BOOKING_CONFLICT` và không có lịch bảo trì được lưu |
| Maintenance giữ Room trước lần đọc Booking dưới khóa | `confirm()` đã giữ Booking và chờ Room. Maintenance đang giữ Property và Room rồi yêu cầu Booking. InnoDB trả `1213` với SQLSTATE `40001` cho `confirm()`. Maintenance đọc Booking còn `PENDING` và trả `409 CATALOG_ROOM_BOOKING_CONFLICT`. DB còn một Booking `PENDING` và không có lịch bảo trì |

Hai service thực hiện một lần mỗi luồng trong cả hai ca. Test quan sát request chờ qua process list và đếm số lần bắt đầu transaction của từng service. Với ca Maintenance lấy khóa trước, test dừng đúng trước truy vấn khóa Booking để chờ `confirm()` đã vào câu khóa. Thứ tự quan sát được là `Booking → Room` ở `confirm()` và `Property → Room → Booking` ở Maintenance. Không ghi nhận lock timeout `1205` trong hai lượt. [Booking workflow](../../backend/app/Services/Booking/BookingWorkflowService.php#L29) · [Catalog workflow](../../backend/app/Services/Catalog/HostRoomMaintenanceService.php#L39)

### 5. Tiêu chí nghiệm thu

| ID | Trạng thái |
| --- | --- |
| C2B-01 | FAIL trên bản hiện tại. C2A tiếp tục tái hiện Booking đọc snapshot cũ. Chờ kiểm lại trên bản tích hợp |
| C2B-02 | Đã chạy cả hai thứ tự. Ca `confirm()` đi trước đạt. Ca Maintenance đi trước FAIL do deadlock và Booking còn `PENDING` |
| C2B-03 | Đã quan sát chờ khóa và một lần thử cho mỗi luồng. FAIL yêu cầu không deadlock |
| C2B-04 | C1, hồi quy SQLite và kiểm phạm vi đạt. Chờ chạy lại toàn bộ trên bản tích hợp TV2 |

### 6. Phụ thuộc

Hai ca lỗi cần bàn giao TV2 gồm snapshot cũ ở tạo Booking và thứ tự khóa đảo ở `confirm()`. Sau khi có bản tích hợp, chạy lại C1/C2A/C2B. C2C kiểm check-in và Availability theo contract đã thống nhất. Nghiệm thu hai bước này trước khi nối POST và form Host.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) chốt QA có thể chạy ngay và phạm vi ba file.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) dùng để thiết kế điểm chờ khóa, kiểm engine và phân biệt `1213` với `1205`.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) đối chiếu kết quả service, số lần thử và bản ghi sau commit, rồi chạy lại ca lỗi trên schema riêng.

### 8. Bàn giao

Commit đề xuất cho test tái hiện C2B. Kết quả xác nhận lỗi trên baseline, chưa đủ điều kiện merge tích hợp.

```text
test(catalog): reproduce booking confirmation and maintenance deadlock
```
