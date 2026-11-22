# UC22 — Quản lý tiện nghi phòng

> **UC22-FINAL, 22/11/2026** — Đã nghiệm thu luồng xem, gán, gỡ và gán lại tiện nghi trên phòng thử bằng tài khoản Host. Kiểm gán/gỡ đồng thời trên MySQL/MariaDB và mất phản hồi với Laravel thật còn mở. Xem [kết luận FINAL](#uc22-final--chốt-nghiệm-thu-quản-lý-tiện-nghi-phòng).

## UC22-01 — Xem và gán tiện nghi

### 1. Kết quả

Ngày 22/11/2026, triển khai trên nhánh `feature/tv1-uc21-room-images`, baseline sạch tại `4af8f3a`. Chủ sở hữu yêu cầu tiếp tục trên nhánh này.

- Backend có API danh sách và gán tiện nghi, dùng danh mục và bảng liên kết hiện có.
- Frontend có nút **Gán tiện nghi** tại chi tiết phòng Host. Panel hiển thị danh sách đã gán, tải lựa chọn khi mở, cập nhật khi lưu và yêu cầu tải lại nếu kết quả gửi chưa rõ.
- **Kiểm tra kỹ thuật đạt.** Owner xác nhận gán và refresh trên phòng thử, lựa chọn đã gán được loại khỏi ô chọn, cùng thao tác mobile và Tab/Enter.

### 2. Phạm vi file

| Thao tác | File |
| --- | --- |
| Sửa | `backend/routes/api/catalog.php`, `backend/app/Validators/Catalog/CatalogValidator.php`, `backend/app/Repositories/Catalog/CatalogRepository.php`, `backend/app/Mappers/Catalog/CatalogMapper.php` |
| Tạo | `backend/app/Http/Controllers/Catalog/HostRoomAmenityController.php`, `backend/app/Services/Catalog/HostRoomAmenityService.php` |
| Tạo | `backend/app/DTOs/Catalog/Requests/AssignHostRoomAmenityRequest.php`, `backend/app/DTOs/Catalog/Responses/HostRoomAmenitiesResponse.php` |
| Tạo | `backend/tests/Feature/Catalog/HostRoomAmenitiesTest.php` |
| Tạo | `frontend/src/api/hostRoomAmenityApi.js`, `frontend/src/services/hostRoomAmenityService.js`, `frontend/src/mappers/hostRoomAmenityMapper.js`, `frontend/tests/hostRoomAmenityService.test.js` |
| Tạo, sửa | Tạo `frontend/src/components/catalog/HostRoomAmenityPanel.jsx`, sửa `frontend/src/pages/host/HostRoomDetailPage.jsx` |
| Tạo, sửa | Tạo `docs/TV1/UC22-REPORT.md`, sửa `docs/TV1/README.md` |

Các file trên khớp phạm vi đã duyệt. Schema, seeders và các luồng Booking/Availability giữ nguyên.

### 3. Kiểm thử

| Lệnh hoặc môi trường | Kết quả |
| --- | --- |
| `php artisan test --filter='HostRoomAmenitiesTest\|HostRoomDetailTest\|PublicRoomFilterTest'` | Exit 0. 30 PASS, 232 assertions, SQLite in-memory |
| `php artisan test` | Exit 0. 2759 PASS, 485 skipped, 11899 assertions, cấu hình test SQLite |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0. 442 PASS, 0 fail, 0 skipped, gồm 5 test UC22 |
| `npm run lint` | Exit 0. 0 lỗi, 3 cảnh báo Fast Refresh có sẵn |
| `npm run build` | Exit 0. 3404 modules, cảnh báo bundle lớn hơn 500 kB |
| `git diff --check` và kiểm file untracked | Exit 0, đúng phạm vi |
| Browser Host thật | Owner báo “Đạt các mục trên” cho gán, refresh, danh sách loại mục đã gán, mobile và Tab/Enter |
| Chrome headless trên bản build với API giả lập | Exit 0. Tải lại sau lỗi GET, một PUT khi bấm lặp, phục hồi sau 503 mô phỏng, danh sách hết lựa chọn, focus và 375 px. Công cụ thử ban đầu chọn trùng tên nút của khu Hình ảnh, lần chạy đạt đã giới hạn thao tác trong khu Tiện nghi |

### 4. Contract và nghiệp vụ

| Endpoint | Contract |
| --- | --- |
| `GET /api/host/rooms/{room}/amenities` | `200` với `success`, `message` và `data.{assigned,available}` |
| `PUT /api/host/rooms/{room}/amenities/{amenity}` | `200` cùng cấu trúc GET sau khi gán, khóa theo phòng để giữ gán lặp idempotent |

- Mỗi mục gồm `id`, `name`, `status` boolean. `assigned` giữ cả tiện nghi cũ đã ngưng. `available` gồm tiện nghi đang hoạt động chưa có liên kết với phòng, kể cả khi chưa từng được gán ở phòng công khai.
- Hai route dùng Sanctum và middleware account-active. Validator kiểm role Host, ID chuẩn và tham số dư. Service kiểm phòng thuộc Host trước khi đọc danh mục hoặc ghi liên kết. Guest trả 401, sai quyền trả 403, phòng thiếu hoặc ngoài phạm vi trả 404. Tiện nghi thiếu hoặc ngưng trả 422 với lỗi `amenity_id`.
- Service xác minh lại chủ sở hữu khi khóa Property và Room, đọc trạng thái dưới khóa Amenity, rồi dùng khóa chính kép của pivot và `syncWithoutDetaching`. Gán lặp tiện nghi còn hoạt động giữ đúng một dòng. Log chỉ ghi lần tạo liên kết sau commit.
- Test gán xác nhận phòng khác và dòng danh mục giữ nguyên. Phản hồi Host Room Detail giữ contract cũ. Bộ lọc UC7 dùng liên kết mới khi phòng công khai hợp lệ và tiện nghi hoạt động.

### 5. Tiêu chí nghiệm thu

| ID | Kết quả và bằng chứng |
| --- | --- |
| UC22-01-AC01 | PASS. Test API về role, sở hữu và lựa chọn chưa từng gán. Owner xác nhận chọn được tiện nghi trên phòng của mình |
| UC22-01-AC02 | PASS. Test gán lặp, dữ liệu sau GET và cách ly phòng. Owner xác nhận refresh và mục đã gán được loại khỏi lựa chọn |
| UC22-01-AC03 | PASS. Test 422 cho tiện nghi ngưng hoặc thiếu. API giữ nguyên liên kết cũ và trả trạng thái ngưng |
| UC22-01-AC04 | PASS. Owner xác nhận mobile và Tab/Enter. Hồi quy UC7, Node, lint, build và diff đạt |

### 6. Phạm vi còn mở

Chưa có kiểm thử gán đồng thời bằng hai kết nối MySQL/MariaDB. Test SQLite chứng minh gán lặp và dữ liệu qua API. Owner browser đã kiểm luồng gán bình thường. Ca mất phản hồi được kiểm trên Chrome với API giả lập, chưa kiểm với Laravel qua phiên Host thật. Luồng gỡ liên kết thuộc UC22-02.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) dùng để giữ lát triển khai gán gồm Backend và Frontend, đúng phạm vi đã duyệt.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) dùng để tổ chức route, validator, DTO, service và mapper.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) dùng để đối chiếu khóa pivot, thứ tự khóa khi gán và giới hạn SQLite.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) dùng để tích hợp panel, trạng thái tải/lỗi, focus và khôi phục sau khi mất phản hồi.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để gắn kết quả test và browser với từng tiêu chí.

### 8. Bàn giao

Task kế tiếp đề xuất là **UC22-02 — Gỡ tiện nghi khỏi phòng**, gồm tiện nghi đã ngưng hoạt động nhưng vẫn còn liên kết.

```text
feat(catalog): allow hosts to assign room amenities
```

---

## UC22-02 — Gỡ tiện nghi khỏi phòng

### 1. Kết quả

Ngày 22/11/2026, triển khai trên nhánh `feature/tv1-uc21-room-images` tại `4af8f3a`, dùng diff UC22-01 đang có làm baseline. Backend thêm DELETE cho một liên kết phòng–tiện nghi. Frontend thêm **Gỡ**, **Hủy** và **Xác nhận gỡ** ngay tại tiện nghi đã gán. Kiểm thử browser trên phòng thử đạt các mục đã hỏi, gồm cả tiện nghi đã ngưng.

### 2. Phạm vi file

| Thao tác | File |
| --- | --- |
| Sửa | `backend/routes/api/catalog.php`, `backend/app/Http/Controllers/Catalog/HostRoomAmenityController.php` |
| Sửa | `backend/app/Validators/Catalog/CatalogValidator.php`, `backend/app/Services/Catalog/HostRoomAmenityService.php`, `backend/app/Repositories/Catalog/CatalogRepository.php` |
| Đổi tên DTO nội bộ | `backend/app/DTOs/Catalog/Requests/AssignHostRoomAmenityRequest.php` → `backend/app/DTOs/Catalog/Requests/HostRoomAmenityTarget.php` |
| Sửa test | `backend/tests/Feature/Catalog/HostRoomAmenitiesTest.php`, `frontend/tests/hostRoomAmenityService.test.js` |
| Sửa | `frontend/src/api/hostRoomAmenityApi.js`, `frontend/src/services/hostRoomAmenityService.js`, `frontend/src/components/catalog/HostRoomAmenityPanel.jsx` |
| Sửa | `docs/TV1/UC22-REPORT.md`, `docs/TV1/README.md` |

### 3. Kiểm thử

| Lệnh hoặc môi trường | Kết quả |
| --- | --- |
| `php artisan test --filter='HostRoomAmenitiesTest\|HostRoomDetailTest\|PublicRoomFilterTest'` | Exit 0. 32 PASS, 274 assertions, SQLite in-memory |
| `php artisan test` | Exit 0. 2761 PASS, 485 skipped, 11950 assertions, cấu hình test SQLite |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0. 445 PASS, 0 fail, 0 skipped |
| `npm run lint` | Exit 0. 0 lỗi, 3 cảnh báo Fast Refresh có sẵn |
| `npm run build` | Exit 0. 3404 modules, cảnh báo bundle lớn hơn 500 kB |
| `git diff --check` và kiểm file untracked | Exit 0. Diff của riêng UC22-02 khớp phạm vi duyệt |
| Chrome headless với API giả lập | Exit 0. Hủy gửi 0 DELETE, bấm lặp chỉ gửi 1 DELETE, focus sau Hủy/lưu, 375 px, khôi phục sau 503 và GET lỗi rồi thành công, refresh, gán lại |
| Browser Host thật | Owner xác nhận Hủy/focus, gỡ/refresh, gán lại, mobile/Tab/Enter và gỡ mục đã ngưng |

### 4. Contract và nghiệp vụ

- `DELETE /api/host/rooms/{room}/amenities/{amenity}` trả `200` và `data.{assigned,available}`. Với ID hợp lệ và phòng thuộc Host, liên kết đã vắng trả cùng phản hồi hiện tại, kể cả khi tiện nghi không còn trong danh mục.
- Cùng validation cặp ID và từ chối payload/query dư như PUT. Hai thao tác gán/gỡ khóa Property rồi Room và xác minh lại ownership trước khi thay đổi. Gỡ dùng `detach` cho đúng cặp ID. Log chỉ ghi sau commit khi có liên kết được gỡ.
- Tiện nghi đang hoạt động trở lại danh sách có thể gán. Tiện nghi ngưng hoạt động rời danh sách phòng và vẫn vắng khỏi lựa chọn. Danh mục, dữ liệu phòng và liên kết phòng khác giữ nguyên.
- Panel dùng chung bộ chặn thao tác đang chạy cho gán/gỡ. Sau lỗi có kết quả chưa rõ, cả hai thao tác tiếp tục bị chặn đến khi GET thành công. Thao tác Hủy trả focus về nút Gỡ, gỡ thành công trả focus về tiêu đề Tiện nghi.

### 5. Tiêu chí nghiệm thu

| ID | Kết quả và bằng chứng |
| --- | --- |
| UC22-02-AC01 | PASS kỹ thuật. API gỡ được mục hoạt động và đã ngưng. GET và Host Room Detail trả đúng dữ liệu sau gỡ. Owner xác nhận trên phòng thử với cả hai trạng thái. |
| UC22-02-AC02 | PASS. Kiểm quyền, gỡ lặp, danh mục và liên kết của phòng khác |
| UC22-02-AC03 | PASS trên Chrome fixture. Kiểm Hủy, focus, chặn gửi trùng và tải lại sau 503 |
| UC22-02-AC04 | PASS. Hồi quy gán lại và Public Room Filter. Node, lint, build và diff đạt |

### 6. Phần còn mở

Kiểm gán/gỡ đồng thời trên MySQL/MariaDB cần lượt runtime riêng. Ca mất phản hồi đã được mô phỏng trên Chrome, còn hành vi qua Laravel thật ở tình huống này chưa được xác minh.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) dùng để giữ baseline UC22-01 và phạm vi đã duyệt.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md) dùng để mở rộng API theo validator, DTO, service và repository hiện có.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md) dùng để kiểm thứ tự khóa, phạm vi detach và bằng chứng SQLite.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md) dùng cho bước xác nhận, focus, chặn trùng và tải lại.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng đối chiếu từng AC với test API, fixture browser và phản hồi owner.

### 8. Bàn giao

Bước tiếp theo đề xuất **UC22-FINAL** để chốt bằng chứng toàn UC. Vì UC22-01 và UC22-02 cùng nằm trong working tree, có thể gộp thành một commit.

```text
feat(catalog): let hosts assign and remove room amenities
```

---

## UC22-FINAL — Chốt nghiệm thu quản lý tiện nghi phòng

### 1. Kết quả

Ngày 22/11/2026, rà trên nhánh `feature/tv1-uc21-room-images` tại `4af8f3a`. Working tree chứa UC22-01 và UC22-02. Mã nguồn và test UC22 khớp hash của lượt kiểm UC22-02.

**TECHNICAL PASS** cho phạm vi FINAL. **Owner acceptance PASS** cho luồng UI trên phòng thử đã xác nhận ở UC22-01 và UC22-02, gồm cả gỡ tiện nghi đã ngưng.

### 2. Phạm vi FINAL

| File sửa | Thay đổi |
| --- | --- |
| `docs/TV1/UC22-REPORT.md` | Kết luận, phạm vi nghiệm thu và bằng chứng |
| `docs/TV1/README.md` | Đồng bộ tiến độ UC22 |

Diff gộp của UC22 có 17 file. FINAL chỉ thay đổi hai file tài liệu trong diff đó. File báo cáo đang untracked, thuộc phần tạo mới của UC22-01.

### 3. Kiểm chứng

| Bằng chứng | Kết quả và phạm vi |
| --- | --- |
| UC22-02 Laravel tập trung và full suite | Lượt trước exit 0. Tập trung 32 PASS, 274 assertions. Toàn bộ 2.761 PASS, 485 skipped, 11.950 assertions, cấu hình SQLite. Đã đối chiếu hai log trong FINAL |
| UC22-02 Node, lint, build | Lượt trước exit 0. Node 445 PASS. Lint 0 lỗi, 3 cảnh báo Fast Refresh có sẵn. Build 3.404 modules, cảnh báo bundle lớn hơn 500 kB |
| UC22-02 Chrome với API giả lập | Đạt Hủy không gửi DELETE, chặn bấm trùng, focus, 375 px và khôi phục sau 503 |
| Owner browser UC22-01 và UC22-02 | Hai xác nhận trên Host thật. Gán/gỡ/gán lại, refresh, Hủy và focus, mobile/Tab/Enter, gỡ mục đã ngưng |
| FINAL | Đối chiếu hash source và tests, route/contract, Git scope, `git diff --check`, khoảng trắng và liên kết trong tài liệu |

FINAL dùng kết quả các lượt trên sau khi đối chiếu hash. Các lệnh test, lint và build thuộc lượt UC22-02. Các kiểm tra Git và tài liệu thuộc lượt FINAL.

### 4. Nghiệp vụ được nghiệm thu

| Hành vi | Bằng chứng |
| --- | --- |
| Xem tiện nghi đã gán và có thể gán | GET từ trang chi tiết Host. Test danh sách rỗng, tiện nghi đã ngưng và mục hoạt động chưa xuất hiện ở phòng công khai |
| Gán và gán lại | PUT chỉ nhận mục đang hoạt động. Test gửi lặp giữ một liên kết và Host xác nhận dữ liệu còn sau refresh |
| Gỡ | DELETE gỡ mục hoạt động hoặc đã ngưng trên đúng phòng. Gỡ lặp trả trạng thái hiện tại. Test xác nhận danh mục và các phòng khác giữ nguyên |
| Phân quyền | Cả ba route dùng Sanctum và account-active. Kiểm role Host và quyền sở hữu ở Backend. Feature tests phủ guest, sai role và phòng ngoài quyền |
| Hồi quy UC7 | Feature tests xác nhận gán bổ sung kết quả lọc và gỡ chỉ loại phòng tương ứng khỏi kết quả |
| Tương tác UI | Owner xác nhận Hủy, focus, mobile và Tab/Enter. Chrome fixture bổ sung bằng chứng chặn gửi trùng và phục hồi |

Ba API dùng phản hồi `data.{assigned,available}`. Gán và gỡ chỉ tác động liên kết phòng–tiện nghi. Quyền quản lý danh mục chung thuộc Admin ở UC46.

### 5. Tiêu chí FINAL

| ID | Kết quả |
| --- | --- |
| FINAL-AC01 | PASS. Diff đúng phạm vi UC22, đã rà ba route và quyền sở hữu |
| FINAL-AC02 | PASS. Test UC22-02 trên cùng source phủ gán/gỡ lặp, giữ liên kết phòng khác và hồi quy UC7 |
| FINAL-AC03 | PASS theo xác nhận owner ở UC22-01/02, kể cả gỡ tiện nghi đã ngưng |
| FINAL-AC04 | PASS. Báo cáo và mục lục thống nhất phạm vi nghiệm thu và các mục còn mở |

### 6. Giới hạn bằng chứng

- Gán/gỡ đồng thời với nhiều kết nối trên MySQL/MariaDB chưa được xác minh. Kiểm khóa và tính nhất quán đồng thời cần schema thử riêng.
- Mất phản hồi và tải lại đã được kiểm với API giả lập. Ca tương ứng qua Laravel với phiên Host thật còn mở.

### 7. Skills áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md) dùng để đối chiếu baseline, ranh giới UC22/UC46 và phạm vi hai file.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md) dùng để đối chiếu hash, log, kiểm tra tài liệu và phạm vi owner chấp nhận.

### 8. Bàn giao

Đề xuất một commit gộp UC22-01, UC22-02 và UC22-FINAL. Bước tiếp theo trong lộ trình TV1 là lập plan **UC23 — Quản lý lịch bảo trì**.

```text
feat(catalog): let hosts assign and remove room amenities
```
