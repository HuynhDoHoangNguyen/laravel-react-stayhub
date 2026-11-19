# UC7 — Lọc phòng

Ngày kiểm chứng: 18/11/2026. Nhánh: `feature/tv1-uc07-room-filters`.
HEAD trước và sau triển khai: `1c4a92865381559f8cc23c19750519f1195b3d30`.

## Kết quả và phạm vi

Đã triển khai backend, frontend và kiểm thử UC7 trên trang `/search` có sẵn: lọc từng phòng theo giá mỗi đêm, sức chứa, loại phòng, tiện nghi và điểm đánh giá của cơ sở. Giữ thanh tìm kiếm mẫu cũ với điểm đến, ngày nhận/trả phòng và số khách; ngày lưu trú vẫn hiển thị cảnh báo chưa hỗ trợ Availability. Sidebar có slider giá, preset và vùng cuộn độc lập. Kết quả, đánh giá, danh mục lựa chọn, tổng số và phân trang lấy từ API/database thật; luồng mới không có mock fallback.

UC7 không xác nhận phòng trống theo ngày. Không triển khai UC6, UC8, Booking hoặc UC21. Card dẫn tới chi tiết cơ sở UC5 đang có. Không commit, push, merge; không chạy migrate/seed/reset hoặc ghi dữ liệu MySQL development.

Đã đối chiếu `AGENTS.md`, `ARCHITECTURE.md`, `docs/TV3/BASELINE_HANDOVER.md`, tài liệu use case/phân công và code Catalog hiện hữu. Yêu cầu triển khai UC7 đã được duyệt trong chat; giữ nguyên baseline Auth, Invoice, Review, session, quyền, shared Axios, schema và các luồng Catalog cũ.

**Kiểm thử riêng UC7 và frontend đạt; toàn bộ backend chưa xanh do ba lỗi nền đã tái hiện trên HEAD trước UC7.** Chi tiết ở phần kiểm chứng, không coi các lỗi này là đã xử lý.

## Kiến trúc và file thay đổi

Backend: `PublicRoomController → PublicRoomFilterValidator / PublicRoomFilterQuery → PublicRoomQueryService → CatalogRepository → CatalogMapper / Response DTO → ApiResponse / PageResponse`.

Frontend: `SearchResultsPage / component → roomSearchService → publicRoomApi → shared Axios`; `roomSearchMapper` kiểm tra/projection payload. Loader quản lý abort và chặn phản hồi cũ ghi đè request mới.

| Nhóm | File |
| --- | --- |
| Controller và routes | `backend/app/Http/Controllers/Catalog/PublicRoomController.php`, `backend/routes/api/catalog.php` |
| Validation và input | `backend/app/Validators/Catalog/PublicRoomFilterValidator.php`, `backend/app/DTOs/Catalog/Requests/PublicRoomFilterQuery.php` |
| Query và mapping | `backend/app/Services/Catalog/PublicRoomQueryService.php`, `backend/app/Repositories/Catalog/CatalogRepository.php`, `backend/app/Mappers/Catalog/CatalogMapper.php` |
| Output DTO | `backend/app/DTOs/Catalog/Responses/PublicRoomListItemResponse.php`, `backend/app/DTOs/Catalog/Responses/PublicRoomFilterOptionsResponse.php` |
| Backend tests | `backend/tests/Unit/Catalog/PublicRoomFilterValidatorTest.php`, `backend/tests/Feature/Catalog/PublicRoomFilterTest.php` |
| Frontend transport/mapping | `frontend/src/api/publicRoomApi.js`, `frontend/src/services/roomSearchService.js`, `frontend/src/mappers/roomSearchMapper.js` |
| UI | `frontend/src/pages/public/SearchResultsPage.jsx`, `frontend/src/components/search/FilterSidebar.jsx`, `ResultsHeader.jsx`, `ResultsGrid.jsx`, `SearchRoomCard.jsx` trong cùng thư mục search |
| Frontend tests | `frontend/tests/roomSearch.test.js` |
| Báo cáo | `docs/TV1/UC07-REPORT.md`, `docs/TV1/README.md` |

Tổng cộng 22 file. Repository/mapper chỉ bổ sung luồng UC7; không thay đổi phương thức cũ. Hai route mới là GET public, không tăng số route cần đăng nhập.

## Contract API

### GET `/api/public/rooms`

Guest được truy cập. Response thành công theo envelope `{data: [...], links: {...}, meta: {...}}` của `PageResponse` hiện hành.

| Tham số | Quy tắc |
| --- | --- |
| `min_price`, `max_price` | Tùy chọn; chuỗi decimal không âm từ `0` đến `9999999999.99`, tối đa hai chữ số sau dấu chấm; so sánh bao gồm hai biên. Nếu có cả hai thì min ≤ max. Không dùng float để so sánh input. |
| `guest_count` | Tùy chọn; số nguyên canonical từ 1 đến 2147483647; `room.capacity >= guest_count`. Đây là số khách trong một phòng. |
| `min_rating` | Tùy chọn; chuỗi điểm từ 1 đến 5, tối đa một chữ số thập phân. So sánh với AVG review của Property, trước khi làm tròn hiển thị. Cơ sở chưa có review không đạt bộ lọc sao. |
| `room_type_ids[]` | Tùy chọn; tối đa 50 ID nguyên dương canonical, không trùng, mảng liên tiếp. Phòng thuộc một trong các loại đã chọn (OR). |
| `amenity_ids[]` | Tùy chọn; tối đa 50 ID nguyên dương canonical, không trùng, mảng liên tiếp. Phòng phải có đủ tất cả tiện nghi đang hoạt động đã chọn (AND). |
| `sort` | `price_asc` mặc định hoặc `price_desc`; luôn thêm `rooms.id ASC` khi bằng giá. |
| `page` | Mặc định 1, số nguyên canonical từ 1 đến 2147483647. |
| `per_page` | Mặc định 12, từ 1 đến 24. |

Giá bỏ trống bằng cách không gửi key. Gửi key với giá trị rỗng/null, boolean, số khoa học, âm, quá hai chữ số thập phân hoặc khoảng giá ngược đều lỗi 422. Integer không nhận `0`, `01`, float hoặc overflow. Giá 0 hợp lệ. ID hợp lệ nhưng không tồn tại/không công khai trả danh sách rỗng; không tiết lộ tài nguyên riêng qua lỗi exists.

Các tham số ngoài contract (ngày, thành phố, keyword, `minRating` kiểu camelCase, loại cơ sở...) trả 422, không bị âm thầm bỏ qua. Dùng `min_rating` cho bộ lọc đánh giá mới. Lỗi validation dùng envelope/errors hiện hữu. Không thay đổi contract endpoint UC4/UC5 hoặc service tìm kiếm cũ.

Ví dụ:

```text
GET /api/public/rooms?min_price=0&max_price=1500000.00&guest_count=3&min_rating=4.5&room_type_ids[]=1&room_type_ids[]=2&amenity_ids[]=1&amenity_ids[]=2&sort=price_asc&page=1&per_page=12
```

Mọi điều kiện áp dụng trên **cùng một room**. Không ghép giá của phòng A, sức chứa của phòng B và tiện nghi của phòng C trong cùng cơ sở để tạo kết quả giả.

Ứng viên công khai phải đồng thời có Property ACTIVE, Room ACTIVE, RoomType thuộc đúng Property, capacity trong miền hợp lệ và giá không âm. Đang bảo trì theo Room.status không được hiển thị. Việc lọc Maintenance theo ngày/Booking còn thuộc Availability, không được suy ra từ UC7.

Mỗi room trả các trường public kế thừa DTO UC5: `id`, `room_number`, `name`, `description`, `capacity`, `price_per_night` (chuỗi hai chữ số thập phân), `room_type`, `images`, `amenities`; bổ sung `property: {id,name,type,address,rating,review_count}`, `currency: "VND"`, `price_unit: "night"`. `rating` là AVG làm tròn một chữ số để hiển thị, null nếu chưa có review; `review_count` là số đánh giá thật. Bộ lọc dùng AVG chưa làm tròn: ví dụ 4.45 có thể hiển thị 4.5 nhưng không đạt ngưỡng 4.5. Không trả host/user/contact nội bộ, pivot hoặc model thô. Ảnh qua `PublicImageUrl` hiện hữu; ảnh không hợp lệ được loại bỏ. Chỉ trả tiện nghi active.

Review hiện gắn Property, không có room rating hay trường phân hạng khách sạn. UI ghi rõ đây là đánh giá khách hàng của cơ sở, không dùng sao để gán hạng sao cho phòng. Giữ semantics Review public đang có, không thay đổi code hoặc workflow TV3. Query gom AVG theo property và bind ngưỡng với CAST numeric để SQLite/MySQL có cùng cách so sánh.

Room/property/type/image/amenity được eager-load theo batch. Mapping không phát sinh lazy query; số query không tăng theo số phòng trong page. Trang ngoài phạm vi trả data rỗng kèm meta thật, không ép về trang đầu trên server.

### GET `/api/public/rooms/filter-options`

Guest được truy cập. Không nhận query parameter. Response:

```json
{
  "data": {
    "room_types": [{"id": 1, "name": "Suite", "property": {"id": 2, "name": "Tên cơ sở"}}],
    "amenities": [{"id": 1, "name": "Wifi"}]
  }
}
```

Đây là danh mục toàn cục của các phòng ứng viên public, không phải facet của trang kết quả hiện tại. Loại phòng có nhãn cơ sở vì RoomType thuộc một Property. Tiện nghi chỉ gồm active và gắn ít nhất một phòng public. Không tạo lựa chọn/counter giả. Database chưa có dữ liệu thì trả hai mảng rỗng.

## Hành vi giao diện

- `/search` lấy API thật; thanh tìm kiếm gốc có điểm đến/ngày/số khách được khôi phục. Bộ lọc có nút Áp dụng/Xóa, sắp xếp giá tăng/giảm, lưới/danh sách, phân trang trước/sau dựa trên meta server.
- Sidebar theo template: nhóm có thể thu gọn, slider giá tối đa với bước 50.000 VND, hai ô min/max, preset dưới 1 triệu/dưới 2 triệu/không giới hạn. Ô nhập vẫn giữ decimal string và hỗ trợ toàn miền giá của API, không giới hạn ở thang kéo mặc định 20 triệu. Khi chưa chọn giá tối đa thì vẫn không gửi giới hạn giá.
- Sidebar có chiều cao giới hạn theo viewport, phần nội dung `overflow-y-auto` và `overscroll-contain`; header và nút áp dụng nằm ngoài vùng cuộn. Cuộn bằng wheel ở cuối sidebar không kéo theo trang. Trên desktop sidebar sticky dưới header; mobile vẫn có vùng cuộn và footer riêng.
- Chip đánh giá Tất cả/3+/4+/4.5+/5 áp dụng qua `min_rating`, có aria-pressed, giữ trên URL/refresh/Back, reset page khi đổi. Card hiển thị điểm và số đánh giá thật của cơ sở, không gán điểm cho cơ sở chưa có review.
- URL dùng tên snake_case của contract và repeated `room_type_ids[]`/`amenity_ids[]`. Axios serialize array bằng bracket để PHP nhận đúng mảng. Giữ nguyên chuỗi decimal; thay tiêu chí/sort/page-size sẽ xóa page cũ. Refresh/Back phục hồi state từ URL.
- Có loading, empty, lỗi mạng/contract, retry kết quả và retry danh mục riêng, lỗi 422 tại trường có liên kết accessibility. Xóa kết quả/tổng cũ khi request mới bắt đầu; abort và revision guard chống stale response.
- URL từ tìm kiếm Home có ngày/thành phố/tiêu chí chưa hỗ trợ sẽ dừng hiển thị kết quả và giải thích rõ. Chỉ khi người dùng bấm **Chuyển sang lọc phòng cơ bản** mới xóa tham số không hỗ trợ; giữ tiêu chí UC7 hợp lệ. Không khẳng định các kết quả đã kiểm tra ngày lưu trú.
- Card hiển thị dữ liệu thật, sức chứa, loại phòng/cơ sở, tiện nghi và giá tham khảo/đêm. Dùng `CatalogImage` với placeholder khi thiếu/hỏng ảnh, không tự gán ảnh thay thế ngẫu nhiên. Ảnh picsum hiện thấy là URL có sẵn trong dữ liệu development, không phải fallback UC7.
- Card dẫn tới `/properties/{id}` hiện hữu; chưa có nút đặt phòng hay route chi tiết phòng mới. Không tự tính giá toàn kỳ lưu trú.

## Kiểm chứng thực tế

Môi trường: PHP 8.3.36, Node 22.22.2; dependencies hiện có, không cài/thay package. Các lệnh backend đặt `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL=''`, `DB_SOCKET=''`, `CACHE_STORE=array`, `SESSION_DRIVER=array`. Test UC7 kiểm tra điều kiện an toàn trước application boot/RefreshDatabase và từ chối config cache.

| Gate | Kết quả đã chạy |
| --- | --- |
| `php artisan test --compact --filter='PublicRoomFilterValidatorTest\|PublicRoomFilterTest'` | Exit 0: **51 passed, 159 assertions** |
| `php artisan test --compact` | Exit 1: **1643 passed, 91 skipped, 3 failed; 7606 assertions**. Không ghi full backend PASS. |
| Đối chiếu ba test lỗi trên snapshot HEAD trước UC7 | Tái hiện đúng **3 failures, 86 assertions**; snapshot riêng và config probe không chứa secret, không thay working tree/source thực tế. |
| `node --experimental-test-module-mocks --test tests/*.test.js` | Exit 0: **194 passed**, gồm 13 test UC7 |
| `npm.cmd run lint` | Exit 0, không có error; 3 warning fast-refresh cũ tại badge/button/sidebar |
| `npm.cmd run build` | Exit 0; còn warning bundle >500 kB của app |
| Chrome headless, API/MySQL development chỉ đọc | Đạt kiểm tra thực tế mô tả dưới đây |
| Git diff/branch/HEAD | 22 file trong phạm vi UC7; `git diff --check` đạt, nhánh/HEAD giữ nguyên, không stage/commit. Cuối lượt thấy thêm thay đổi bên ngoài tại `docs/StayNest-Frontend-template/package-lock.json`; giữ nguyên, không đưa vào phạm vi sửa UC7. |

Backend UC7 kiểm tra defaults/typed DTO, dữ liệu sai định dạng, biên giá/giá 0, sức chứa, OR loại phòng, AND tiện nghi trên cùng phòng, trạng thái/ownership, options active, ID không tồn tại, sort ổn định, phân trang/out-of-range, whitelist, ảnh an toàn, eager loading và SQL chỉ SELECT/không đổi Room hoặc Booking. Test bổ sung kiểm tra rating 4.5/5, không có review, kết hợp rating với giá/sức chứa, biên AVG chưa làm tròn và input rating sai định dạng.

Frontend tests đi qua API factory và service thật với transport adapter kiểm soát: query array thực tế qua Axios URI, decimal string/URL state, unsupported intent, payload sai, no mock fallback, lỗi HTTP/timeout/network, signal cancellation, race/late success/late failure và recovery.

Browser kiểm tra dữ liệu MySQL hiện có: `/search` trả 11 phòng; giá 700000–1500000 và 3 khách còn 3 phòng. Đã kiểm refresh, lỗi min > max tại input, empty result, `per_page=1` sang trang 2 và Back, chuyển explicit từ URL có city/ngày, chọn loại phòng/tiện nghi qua query array, desktop 1440px và mobile 390px không tràn ngang. Chặn kết nối API bằng Chrome DevTools cho thấy cả danh sách và options báo lỗi, không sinh mock; bỏ chặn và bấm hai nút thử lại đều khôi phục dữ liệu. Không có uncaught browser exception. Có ảnh kiểm chứng lưu ở workspace kiểm thử cục bộ; không đưa dữ liệu nhạy cảm hoặc profile trình duyệt vào Git.

Kiểm tra bổ sung giao diện: search bar gốc xuất hiện và submit giữ ngữ cảnh chưa hỗ trợ; wheel thay đổi `scrollTop` sidebar trong khi `window.scrollY` giữ nguyên, kể cả khi cuộn thêm ở đáy sidebar; nút áp dụng vẫn trong viewport sau khi sidebar sticky. Vùng cuộn riêng đã kiểm cả desktop và mobile; ảnh mobile chụp thêm phần kết quả sau khi cuộn trang. Slider và preset cập nhật ô giá. Chip 4+ ghi `min_rating=4` lên URL, reset page và khôi phục lựa chọn sau reload; API MySQL hiện trả tổng 7 phòng thuộc cơ sở đủ điểm. Test fixture riêng kiểm chứng cơ sở chưa có review và ngưỡng 4.45/4.5, không tạo fixture trên MySQL.

### Ba lỗi nền còn lại

1. `RootEnvironmentTest::test_root_loader_keeps_backend_base_path_and_process_overrides`: test dùng sự có mặt của `MYSQL_PORT` để xác nhận root env đã load, nhưng root env local hiện không có biến này.
2. `RootEnvironmentTest::test_composer_hooks_copy_only_the_missing_root_environment`: test yêu cầu không còn `backend/.env`; file legacy local đang tồn tại, dù ứng dụng đọc root `.env`.
3. `AuthenticationTest::test_every_existing_protected_route_checks_account_status_after_auth`: phép đếm hard-code mong 32 route nhưng baseline sau tích hợp dev có 35. Các assertion middleware đều chạy qua; lỗi nằm tại expected count. UC7 thêm hai GET public nên không tăng con số này.

Đối chiếu dùng `git archive HEAD` vào thư mục kiểm thử riêng, dependencies chia sẻ chỉ đọc, project classes/routes/tests từ HEAD. Probe env không có MYSQL_PORT và file backend env trống tái hiện đúng hai điều kiện local trên; không sao chép secret. Cả ba lỗi đã xuất hiện ở HEAD không có UC7. Không sửa test Auth hay di chuyển/sửa env ngoài phạm vi chỉ để làm xanh gate UC7. Các skipped của full suite không được coi là đã kiểm chứng MySQL integration.

## Cách thử

1. Dùng root `.env` và MySQL đã cấu hình của repository. Mở terminal backend chạy `php artisan serve --host=localhost`; terminal frontend chạy `npm.cmd run dev -- --host localhost`. Không chạy migrate:fresh/seed để thử UC7.
2. Truy cập `http://localhost:5173/search`. Không cần đăng nhập. Loại phòng/tiện nghi chỉ xuất hiện khi có dữ liệu public tương ứng trong DB.
3. Kéo thanh giá hoặc dùng preset; nhập min `700000`, max `1500000`, số khách `3`, chọn mức sao nếu cần và bấm Áp dụng. Chọn thêm loại phòng/tiện nghi rồi áp dụng để kiểm điều kiện kết hợp. Kết quả cụ thể phụ thuộc dữ liệu local. Điểm sao lấy từ đánh giá của cơ sở.
4. Đưa chuột vào nội dung sidebar rồi cuộn tới cuối: trang giữ vị trí, sidebar cuộn riêng và nút Áp dụng vẫn nhìn thấy. Có thể Tab vào vùng cuộn hoặc thu gọn từng nhóm.
5. Đổi sort; mở `/search?per_page=1` để thử phân trang; refresh/Back kiểm state URL. Dùng max nhỏ hơn min hoặc số khách `0` để xem lỗi 422.
6. Thử thanh tìm kiếm gốc hoặc `/search?city=Hue&startDate=2026-12-01` để xác nhận cảnh báo chưa hỗ trợ ngày/địa điểm và nút chuyển explicit. Thử ngắt kết nối API để kiểm lỗi/retry, không xuất hiện mock rooms.
7. Bấm Xem cơ sở lưu trú để mở UC5. Không coi điều này là đã có chi tiết phòng UC8 hay kiểm tra lịch UC6.

## Phụ thuộc và giới hạn bàn giao

UC7 hoạt động độc lập trên catalog public hiện tại. Khi TV2 hoàn thiện Search/Availability, cần thống nhất contract ngày, địa điểm và các điều kiện Booking/Maintenance trước khi nối; không tự bỏ unsupported parameters hoặc tái định nghĩa hai API này. Chưa nghiệm thu concurrency/booking availability, MySQL writes hoặc hiệu năng dữ liệu quy mô lớn trong UC7. Không tự triển khai UC kế tiếp.
