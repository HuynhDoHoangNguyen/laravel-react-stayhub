# StayHub TV1 — UC5: Xem chi tiết cơ sở lưu trú

## 1. Kết luận riêng UC5

**Hoàn thành API/frontend UC5 và automated tests, cùng branch UC4. Chưa nghiệm thu browser, MySQL và ảnh render thật.** Có gallery, public rooms/amenities, review integration và giá tham khảo; không đồng nghĩa đã làm đặt phòng hoặc room detail UC8.

| Snapshot | Kết quả |
| --- | --- |
| Ngày | 29/10/2026, Asia/Saigon |
| Branch | feature/tv1-uc04-uc05-property-catalog |
| HEAD nền | 951f62efc70127a3719c840ae529f8d311842a49 |
| Trước sửa | Working tree sạch, nền UC1/UC2/UC3 |
| Baseline chạy lại | 407 backend tests / 2.066 assertions |
| Kết quả cuối chung hai UC | **445 backend tests / 2.202 assertions, 60 Node tests PASS** |
| Catalog suite riêng | 38 tests / 136 assertions PASS |
| Lint/build | PASS, warnings nền còn tồn tại |

Không Git mutation, chuyển branch, commit/push/merge hoặc thay dữ liệu MySQL. Phần code/test hoàn thành tách biệt với nghiệm thu browser/data còn mở.

## 2. Khảo sát, mini plan và files

Đọc AGENTS root đầu tiên, không AGENTS con; Prompt giao TV1 và cho phép hai UC chung branch, tiếp tục sau mini plan. Đã trình bày contract/actor/scope và chia backend → frontend → testing/reports. Tài liệu khảo sát và inventory đủ 32 file tại [UC04-REPORT.md](UC04-REPORT.md), mục 2–3.

Đối chiếu schema Property/Images/Room/RoomType/RoomImage/Amenity/pivot/Review, User/Booking, enums/migrations/seed URLs; public Review query/controller/resource/tests; detail/gallery/room/amenity/booking preview/review, service/mock, shared Axios/router/layout và checkout caller. Tham khảo bố cục HotelHeader StayNest, không import runtime hoặc bịa field template. Tài liệu bắt buộc có đủ; docs/api/booking-module.md do M2_CONTEXT nhắc tới bị thiếu. Route inventory không xác nhận Search/Availability/Booking TV2 đã tích hợp.

Files chính UC5:
- backend/app/Http/Controllers/Catalog/PublicPropertyController.php; Requests/Catalog/PublicPropertyDetailRequest.php.
- backend/app/Services/Catalog/PublicPropertyQuery.php và PublicImageUrl.php.
- backend/app/Http/Resources/Catalog/PublicPropertyResource.php và PublicRoomResource.php.
- backend/routes/api/catalog.php, require tối thiểu trong api.php, tests/Feature/Catalog/PublicPropertyTest.php.
- frontend/src/pages/public/PropertyDetailPage.jsx; components/hotel/PropertyHeader.jsx, PropertyGallery.jsx, PropertyAmenities.jsx, RoomsList.jsx, RoomCard.jsx.
- frontend/src/components/catalog/CatalogImage.jsx, hooks/useCatalogResource.js, services/catalog.js và propertyService.js, tests/catalog.test.js.
- frontend/src/pages/customer/CustomerCheckoutPage.jsx: guard source tối thiểu, không viết Booking service.
- Docs UC04/UC05 và hai link trong README. Giữ audit/UC1/UC2/UC3 lịch sử.

Không sửa schema/models/enums/seeders/auth/profile/shared Axios/dependencies hoặc Review/Invoice/Booking business logic. BookingPreviewCard cũ giữ file nhưng không mount từ detail. ReviewsSection giữ implementation TV3.

## 3. Detail API và public rules

GET /api/public/properties/{property}, không auth/role/account.active:
- Positive canonical integer ID. Route constrain chữ số, controller kiểm tra canonical/range. Missing và INACTIVE/BLOCKED/PENDING có cùng JSON 404 cho ID hợp lệ; malformed/overflow/noncanonical cũng 404 an toàn, không search placeholder.
- Không nhận query filters: query ngoài scope trả 422 theo field, không giả vờ đã lọc phòng trống.
- Success Resource {data}; không data.user hoặc toàn model.

Property fields chung theo UC04 report: id/name/type/address/description/check-in-out/primary_image/starting_price/currency/price_unit/rating/review_count. Detail thêm images, rooms, amenities. Schema không có city hoặc area; không tạo chúng và không lấy contact User Host.

Room whitelist: id/room_number/name/description/capacity/price_per_night, room_type chỉ id/name, images URL public và amenities chỉ id/name. Không host/property/customer IDs hoặc bookings/invoices/maintenance/admin data.

Chỉ Property ACTIVE; Room ACTIVE có RoomType thực thuộc đúng Property. Type lệch/thiếu không expose, không sửa DB legacy. Ảnh query qua đúng property_id/room_id. Amenity phải đang bật, liên kết phòng public. Property amenities là distinct aggregate từ các phòng, không hứa mọi phòng đều có tất cả tiện nghi.

ACTIVE Property không có public room vẫn 200, arrays rỗng hợp lệ, starting_price null. Không review thì rating null/review_count 0. MIN chỉ room public hợp lệ, có bao gồm giá 0; giữ decimal string VND/night. Không tính số đêm/quote hoặc xác nhận phòng trống theo ngày/khách.

Ảnh main trước rồi ID; main trùng/không main có thứ tự ổn định. URL chỉ HTTP(S)/public folders đã kiểm tra và base cấu hình; không private/User avatar UC3/Windows/traversal/credentials/unsafe schemes. Không ảnh/lỗi ảnh dùng icon placeholder có nhãn, không ảnh hotel giả. Nguồn seed remote chưa được xác minh render.

## 4. Frontend, gallery và stale state

Route /properties/:propertyId giữ nguyên. View keyed theo ID bắt đầu loading trống, loader abort/generation bỏ response muộn. A→B không giữ gallery/room/review A. Không có room selection vì chỉ catalog read-only. Missing/nonpublic có màn hình không tìm thấy và link list, không redirect loop. Lỗi mạng khác 404, có Retry.

Adapter dùng chung map starting_price→price, rating→averageRating, type→propertyType, images→photos, room_type.name→roomTypeName, amenity objects→names. Decimal chỉ format VND để hiển thị. Back link nhận query list hợp lệ, không mở external location.

Gallery dùng Radix Dialog dependency đã có, không đổi UI kit/cài thư viện. Có previous/next, thumbnails, modal, Escape/arrows, focus trap/restore opener, alt/fallback và giới hạn modal height trên mobile. Đây là code/build evidence, chưa phải browser interaction evidence.

Description/room names/comment hiển thị dạng React text, không dangerouslySetInnerHTML. Room cards có giá mỗi đêm, sức chứa/amenity thật; không giả diện tích/tổng giá theo ngày hoặc link room-detail UC8. Amenities ghi rõ có thể khác nhau từng phòng. Header không hiển thị liên hệ cá nhân Host.

DEV mock phải cả hai guard, có nhãn và giữ source=mock. Không gọi API review bằng mock ID. Bỏ override backendPropertyId cũ để URL query không lật mock identity thành API thật.

## 5. Review và checkout boundaries

ReviewsSection chỉ mount khi detail đã xác nhận public, dùng property ID đã load và propertySource. Keyed view unmount reviews cũ khi đổi ID hoặc mới 404. Giữ service/DTO/pagination/filter TV3. Aggregate theo quan hệ reviews, cùng semantics hiện có, không tự thêm moderation rule.

**Dependency TV3 còn mở:** PublicReviewController vẫn chỉ Property::find, chưa ACTIVE-only. Endpoint /api/public/properties/{property}/reviews vẫn có thể trả review cho non-ACTIVE Property. UI guard không thay server authorization, kể cả property đổi status sau khi detail đã load. Không sửa logic TV3 trong scope này hoặc tuyên bố đã bảo vệ endpoint reviews.

TV2 chưa có executable Search/Availability/Booking preview/create trên branch. Detail chỉ reference price, thông báo chưa thể đặt phòng và nút disabled, không chuyển checkout như đã xác nhận availability. Checkout caller chặn source=api để real catalog không đi vào luồng checkout/mock pricing hiện có. DEV mock cũ không bị trình bày thành API booking thật. Không sửa bookingService, không làm UC7/UC8/UC51/52/Host CRUD hoặc pricing engine.

## 6. Kiểm thử thực tế

| Command | Exit/kết quả cuối |
| --- | --- |
| backend: php artisan test --filter=PublicPropertyTest --compact --no-ansi | 0; 38 tests / 136 assertions, 1.90 giây |
| backend: php artisan test --compact --no-ansi | 0; 445 tests / 2.202 assertions, 23.23 giây |
| backend: php artisan route:list --path=public --no-ansi | 0; list/detail/reviews, không Search/Availability placeholder |
| frontend: node --test tests/auth.test.js tests/registration.test.js tests/profile.test.js tests/catalog.test.js | 0; 60 PASS |
| frontend: npm.cmd run lint | 0; 0 errors/3 warnings nền |
| frontend: npm.cmd run build | 0; 3312 modules, JS 1.083,14 kB/gzip 306,25 kB |
| root: git diff --check | 0 |

Warnings nền Fast Refresh tại ui/badge.jsx:25, button.jsx:38, sidebar.jsx:536; build chunk >500 kB. Node/build chạy ngoài sandbox theo quyền được cấp do spawn EPERM đã biết, không sửa dependencies/config.

Safe tests: testing/SQLite :memory:, DB_URL rỗng, array cache/session, không config cache. Guard trước boot/RefreshDatabase và kiểm tra config hiệu lực trước migrations fixture. Không đọc/in .env/secret hoặc migrate/seed/reset MySQL.

UC5 coverage: ACTIVE detail; missing/nonpublic/malformed IDs; rooms/type ownership; ảnh order/ownership/fallback; amenities status; no-room/image/review; privacy whitelist; decimal/0/null; public access của blocked actor; không availability filter. UC4 và auth/profile/Review/Invoice regression đều PASS trong suite cuối.

Node tests là service/DTO/metadata/mock/source/generation/back helpers thật, không phải DOM/E2E. Có stale success/error/unmount/retry. Build không thay bằng chứng gallery/reviews/checkout trên browser.

## 7. Nghiệm thu còn thiếu và hướng dẫn thử

**CHƯA KIỂM CHỨNG browser/runtime/MySQL/ảnh seed.** Không browser tool/Playwright/Puppeteer; port 8000 có 0 listener khi thử HTTP. Không tạo fixture live/demo hoặc sửa seeder để có bằng chứng.

Trên môi trường fixture test đã chuẩn bị:
1. Chạy backend/frontend, tắt mock khi nghiệm thu API thật. List → detail → back giữ page; direct URL/refresh hoạt động.
2. Kiểm tra title/address/type/description/check-in-out; main/thumbnails/modal/keyboard. Block nguồn ảnh trong DevTools để thử fallback.
3. ACTIVE không room/image/review vẫn 200 và UI rõ; nonpublic/missing/malformed không có review cũ. Wrong-owner room type, INACTIVE/MAINTENANCE và amenity tắt không hiện.
4. Giá 0 hiện VND 0; không room hiện chưa có giá. Review public API thật có pagination/filter; mock detail không gọi review thật. Status gap của TV3 cần xử lý phối hợp riêng.
5. Chuyển nhanh A→B, offline/retry, mobile/tablet/desktop, modal focus/Escape/arrows. Không stale data hoặc production fallback mock.
6. Booking disabled; direct real checkout báo chưa khả dụng, không tạo booking/quote giả. Không đổi data demo chỉ để thử.

Giới hạn: schema thiếu city; chưa chứng minh public storage/remote image vận hành. SQLite không thay MySQL precision/collation/concurrency hoặc cookie hai origin. Migration personal details UC3 là việc triển khai riêng của developer; không chạy ở lượt này.

## 8. Bàn giao và điểm dừng

Không có blocker code/test còn biết trong UC5; acceptance implementation/automated có bằng chứng. Browser/data còn mở và public review status là dependency TV3 riêng. Dừng sau hai UC, không triển khai UC khác.

Commit và PR title/body cho cả UC4/UC5 vào dev nằm tại [UC04-REPORT.md](UC04-REPORT.md), mục 8. Không phát hành PR, commit/push/merge hoặc chuyển branch trong phiên.
