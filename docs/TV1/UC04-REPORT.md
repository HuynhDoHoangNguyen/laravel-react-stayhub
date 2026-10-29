# StayHub TV1 — UC4: Xem danh sách cơ sở lưu trú

## 1. Kết luận và snapshot

**Hoàn thành implementation backend/frontend UC4 và automated tests, chung branch với UC5. Chưa nghiệm thu browser E2E, dữ liệu MySQL và ảnh render thực tế.** UC5 có kết luận riêng tại [UC05-REPORT.md](UC05-REPORT.md).

| Mục | Kết quả |
| --- | --- |
| Ngày | 29/10/2026, Asia/Saigon |
| Branch | feature/tv1-uc04-uc05-property-catalog |
| HEAD nền | 951f62efc70127a3719c840ae529f8d311842a49 |
| Working tree trước sửa | Sạch, có UC1/UC2/UC3 |
| Backend baseline chạy lại | 407 tests / 2.066 assertions PASS, 48.79 giây |
| Catalog tests cuối, chung hai UC | 38 tests / 136 assertions PASS, 1.90 giây |
| Backend toàn bộ cuối | **445 tests / 2.202 assertions PASS**, 23.23 giây |
| Frontend Node cuối | **60 PASS**, gồm 49 tests nền và 11 catalog tests |
| Lint/build | PASS; 0 errors/3 warnings nền, warning bundle lớn còn tồn tại |

Chỉ UC4/UC5. Không Git mutation, commit/push/merge, chuyển branch hoặc tự làm UC7/UC8/Host CRUD. Không migrate/seed/reset MySQL, sửa schema/model/enums/config hoặc nghiệp vụ TV2/TV3.

## 2. Khảo sát và mini plan

Đọc AGENTS.md root đầu tiên; chỉ có AGENTS root áp dụng. Prompt giao TV1, cho phép hai UC chung branch và tiếp tục sau mini plan. Đã trình bày DTO/API/scope và chia query/resources → frontend/shared adapter → tests/reports trước sửa.

Đối chiếu ARCHITECTURE.md, README.md, frontend guide, ERD/use-case/team assignment/screen mockups, seed-data, STAYHUB_M2_CONTEXT.md, TV1 README/audit/UC01/UC02/UC03 reports. Đọc models/enums/migrations/seed conventions, routes/bootstrap, Review public query/controller/resource/tests và callers frontend. Tham khảo HotelHeader/pattern StayNest; không import runtime hoặc sửa template.

Tài liệu bắt buộc đều có. docs/api/booking-module.md do M2_CONTEXT nhắc tới không tồn tại. Inventory trước sửa chỉ có public reviews; sau sửa thêm list/detail. Không có Search/Availability/Booking preview/create executable của TV2; không lấy mô tả M2_CONTEXT làm bằng chứng tích hợp.

Schema không có city/is_featured/room area. UI dùng address; featured trên trang chủ là 6 cơ sở ACTIVE đầu theo ID, không ranking giả. Không serialize Property.phone khi chưa có contract công khai rõ, và không lấy contact User Host. Review không có moderation/status; aggregate giữ cùng semantics đọc hiện tại của TV3.

## 3. File inventory toàn branch

Tổng 32 file: 10 backend, 19 frontend, 3 tài liệu. Chi tiết và phần dùng chung cũng phục vụ UC5.

| Backend, từ backend/ | Trách nhiệm |
| --- | --- |
| app/Http/Controllers/Catalog/PublicPropertyController.php | List/detail, Resources và 404 |
| app/Http/Requests/Catalog/PublicPropertyListRequest.php | Pagination/unsupported query validation |
| app/Http/Requests/Catalog/PublicPropertyDetailRequest.php | Detail không nhận availability filters |
| app/Services/Catalog/PublicPropertyQuery.php | ACTIVE, ownership, pagination/eager loading/aggregate |
| app/Services/Catalog/PublicImageUrl.php | Safe public URLs, không private/storage path tùy ý |
| app/Http/Resources/Catalog/PublicPropertyResource.php | Property whitelist và decimal price |
| app/Http/Resources/Catalog/PublicRoomResource.php | Room whitelist của detail |
| routes/api/catalog.php | Hai public routes |
| routes/api.php | Một require tối thiểu |
| tests/Feature/Catalog/PublicPropertyTest.php | Safe fixture tests chung hai UC |

| Frontend, từ frontend/ | Trách nhiệm |
| --- | --- |
| src/services/catalog.js | Factory/adapter/metadata/formatting/URL/stale loader |
| src/services/propertyService.js | Dùng catalog cho featured/list/detail, giữ search cũ |
| src/hooks/useCatalogResource.js | Subscription/load/cancel/retry |
| src/components/catalog/CatalogImage.jsx | Ảnh thật hoặc placeholder có nhãn |
| src/pages/public/PropertiesPage.jsx | Danh sách và URL pagination |
| src/components/landing/CuratedStays.jsx | 6 item từ list thật trên home |
| src/components/landing/PropertyCard.jsx | Card thật, bỏ giá/ảnh/wishlist giả |
| src/components/landing/PropertyCarousel.jsx | Card trang chủ |
| src/layouts/PublicLayout.jsx | Link catalog desktop/mobile/footer |
| src/routes/AppRouter.jsx | Route /properties |
| src/utils/authNavigation.js | Public list allowlist |
| src/pages/public/PropertyDetailPage.jsx | Detail keyed theo ID, reviews và giá tham khảo |
| src/components/hotel/PropertyHeader.jsx | Header public không PII |
| src/components/hotel/PropertyGallery.jsx | Gallery/modal/keyboard/focus/fallback |
| src/components/hotel/PropertyAmenities.jsx | Aggregate amenities đúng nhãn |
| src/components/hotel/RoomsList.jsx | Room list chỉ để xem |
| src/components/hotel/RoomCard.jsx | DTO thật, không quote/selection giả |
| src/pages/customer/CustomerCheckoutPage.jsx | Guard real catalog khi chưa có booking API |
| tests/catalog.test.js | Service/DTO/error/mock/stale helpers thật |

Docs: báo cáo này, UC05-REPORT.md và TV1 README. Giữ nguyên audit/UC1/UC2/UC3, shared Axios, Review/Invoice/Booking services và dependency/lockfiles. BookingPreviewCard cũ giữ file nhưng không được mount từ catalog detail.

## 4. API, DTO và public rules

GET /api/public/properties: không auth/role/account.active. Guest và actor có session đều xem được; test actor BLOCKED sau login vẫn đọc được public catalog.

- page: integer 1..2147483647, mặc định 1; per_page: integer 1..24, mặc định 12.
- Sai hoặc ngoài whitelist, kể cả city/ngày/giá/rating/amenities, trả 422 errors theo field. Không giả vờ đã áp dụng availability.
- Chỉ Property ACTIVE tính trong total/last_page. Order ID ASC ổn định; trang ngoài dữ liệu trả collection rỗng. Database paginate, không load toàn bộ rồi cắt trang trong PHP/React.
- Resource collection {data,links,meta}. Mỗi item gồm id/name/type/address/description/check_in_time/check_out_time/primary_image/starting_price/currency/price_unit/rating/review_count.
- starting_price là decimal string 2 chữ số hoặc null; VND/night, không phải booking quote. Rating null nếu không review. images/rooms/amenities chỉ thêm ở detail; không nhúng reviews vào list.

Public rooms phải ACTIVE và có RoomType thực thuộc cùng Property bằng whereHas/whereColumn. Phòng INACTIVE/MAINTENANCE hoặc type sai ownership không tính vào giá. ACTIVE Property chưa có public room vẫn xuất hiện với null price. Giá 0 được giữ, không thay bằng 2,5 triệu hoặc 0 giả khi thiếu dữ liệu. BigDecimal đã có từ Laravel chỉ dùng chuẩn hóa decimal string, không dùng float tính tiền; float/round của rating không phải tính tiền.

Review count/avg dùng quan hệ property.reviews với semantics hiện tại của TV3, query theo tập, không gọi summary riêng từng item hoặc load customer/comment. Không có cột moderation/status để tự thêm rule. Không review hiển thị Chưa có đánh giá.

Images order is_primary DESC, id ASC; nhiều main chọn ID đầu, không main chọn ảnh ID đầu. Loại URL không hợp lệ/public trước khi chọn ảnh. Chấp nhận HTTP(S) không credential, public disk properties/... hoặc rooms/..., và /storage/properties|rooms/... qua base cấu hình. Chặn Windows/private/profile-avatars/API avatar UC3/traversal/unsafe schemes. Không bịa host. Seed dùng URL picsum.photos; chưa xác minh ảnh còn tải được hoặc storage đã publish. Placeholder icon là trình bày, không giả làm ảnh cơ sở.

DTO whitelist không host_id/contact cá nhân, booking/customer/invoice data hoặc toàn model. Địa chỉ public lấy từ Property, không User. Query eager images và withMin/withCount/withAvg; test so SELECT property_images giữa list nhỏ và page nhiều property chứng minh một query theo tập, không đếm tổng query giòn toàn ứng dụng.

## 5. Frontend UC4

Home → Khám phá tất cả và menu Cơ sở lưu trú → /properties. Có loading/error-retry/empty, card title/address/type/giá/review thật, ảnh fallback và pagination server. Page/per_page ở URL; invalid/unsupported URL có lỗi và link reset. Không biến endpoint lỗi thành empty success.

Adapter snake_case → camelCase dùng chung, giữ meta/links và kiểm tra metadata. Real data không paginate ở client. Card giữ query list trong router state để detail/back quay đúng page. Back/returnTo không mở URL ngoài hệ thống. Keyed view và loader abort/generation bỏ stale success/error sau đổi page/unmount; retry xóa result cũ.

Mock chỉ khi DEV===true và VITE_ENABLE_PROPERTY_MOCKS==='true', UI có nhãn DEV. Production hoặc flag khác gọi API; lỗi 404/5xx/malformed payload không fallback mock. Mock pagination không được coi là server pagination thật. Intl VND chỉ format hiển thị; null/invalid khác 0, không NaN/default price giả.

## 6. Kiểm thử thực tế

| Command | Exit/kết quả cuối |
| --- | --- |
| backend: php artisan test --compact --no-ansi trước sửa | 0; 407 tests / 2.066 assertions, 48.79 giây |
| backend: php artisan test --filter=PublicPropertyTest --compact --no-ansi | 0; 38 tests / 136 assertions, 1.90 giây |
| backend: php artisan test --compact --no-ansi cuối | 0; 445 tests / 2.202 assertions, 23.23 giây |
| backend: php artisan route:list --path=public --no-ansi | 0; list/detail mới và reviews cũ, không search/availability |
| frontend: node --test tests/auth.test.js tests/registration.test.js tests/profile.test.js tests/catalog.test.js | 0; 60 PASS |
| frontend: npm.cmd run lint cuối | 0; 0 errors/3 warnings nền |
| frontend: npm.cmd run build cuối | 0; 3312 modules; JS 1.083,14 kB/gzip 306,25 kB |
| root: git diff --check | 0 |

Lint warnings nền: badge.jsx:25, button.jsx:38, sidebar.jsx:536 (Fast Refresh). Build còn warning chunk >500 kB; một lượt giữa có timing warning, không phải compile error. Node/build chạy ngoài sandbox theo quyền được cấp do giới hạn spawn EPERM đã biết; không đổi/cài dependency.

Guard trước boot/RefreshDatabase: testing, SQLite :memory:, DB_URL rỗng, array cache/session, không config cache; kiểm tra config hiệu lực trước migration fixture. PHP tại D:/xampp/php/php.exe. Không đọc/in .env/secrets hoặc migrate/seed/reset MySQL.

Backend coverage chung: guest/blocked actor public, ACTIVE-only/counts/order, pagination boundaries/422/unknown params, empty/no rooms, decimal/zero/null price, image order/ownership, safe paths, amenity status, private data whitelist, review aggregate và batched images. Auth/profile/Review/Invoice regression vẫn PASS. UC5 cases mô tả riêng trong báo cáo tương ứng.

11 Node catalog tests chạy service/adapter/loader thật với Axios fixture adapter: DTO/metadata, giá, error safe, real/mock guards, ID/back URL, stale success/error/unmount và retry. Không phải DOM/E2E tests.

## 7. Nghiệm thu còn thiếu và dependencies

**CHƯA KIỂM CHỨNG browser, MySQL và ảnh thực.** Không browser tool/Playwright/Puppeteer. GET localhost:8000 không kết nối được; TCP kiểm tra 0 listener port 8000. Không tạo data demo/live để có bằng chứng. Build PASS không chứng minh Network hoặc ảnh render thật.

Trên database fixture test đã được nhóm chuẩn bị, chạy backend/frontend theo README, tắt mock khi nghiệm thu API thật:
1. Guest mở home → Khám phá tất cả hoặc /properties; kiểm tra request list/total/per_page, không Host PII.
2. Trang sau → detail → Về danh sách, browser Back/direct URL/refresh; giữ page.
3. Fixture giá 0/no rooms/images/reviews hiển thị đúng; không giá giả. Ảnh lỗi dùng placeholder.
4. URL page sai hoặc filter ngày/city ngoài scope báo lỗi. Offline có Retry, không hiện mock che lỗi. Chuyển nhanh page với network throttling không stale data.
5. Kiểm tra mobile/tablet/desktop, keyboard/focus và production build không fallback mock. Bước detail/gallery xem UC05 report.

Dependencies quan trọng:
- TV2 chưa có executable Search/Availability/Booking trong branch. SearchResultsPage/filter engine cũ được giữ nguyên; không kết luận search thật đã hoàn thành. Không làm UC7/UC8/UC51/52.
- **TV3 PublicReviewController vẫn chỉ Property::find, chưa ACTIVE-only.** Catalog bảo vệ list/detail, không bảo vệ thay endpoint reviews. UI chỉ mount sau public detail không thay được kiểm tra server; TV3 cần xử lý riêng.
- Chưa xác minh MySQL, nguồn ảnh seed hoặc static public storage. Nền UC3 có migration personal details nhưng không có bằng chứng migrate MySQL; không tự chạy trong lượt này.

Không có blocker code/test còn biết trong UC4; nghiệm thu browser/data còn mở. Không kết luận booking/review toàn hệ thống đã E2E PASS.

## 8. Gợi ý bàn giao

Không commit/push/merge/chuyển branch, không tự bắt đầu UC khác.

Commit gợi ý:
~~~text
feat(catalog): implement public property list and details (UC4, UC5)

Add ACTIVE-only catalog APIs with pagination and safe public room DTOs.
Connect home, listing and details to real data with guarded DEV mocks.
Cover privacy, prices, images and stale responses; document TV2/TV3 boundaries.
~~~

PR title vào dev: feat(catalog): hoàn thiện danh sách và chi tiết cơ sở công khai (UC4, UC5).

PR body: Bổ sung list/detail public ACTIVE-only, phân trang server và whitelist phòng/ảnh/tiện nghi/giá tham khảo. Kết nối home, /properties và detail với adapter chung, back URL và stale-response guards. Không triển khai search/availability/booking; real checkout bị chặn đến khi TV2 có API. Local validation: 445 backend tests, 60 Node tests, lint/build PASS. Browser/MySQL/ảnh seed chưa nghiệm thu; public review status còn dependency TV3.
