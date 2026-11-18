# UC36 — Nghiệm thu TV3 và dependency TV2

Cập nhật: 2026-11-17 (Asia/Bangkok), theo [phân công TV3](../stayhub-team-work-assignment.md). Baseline `feature/tv3-uc36-payment-status`, HEAD `6bd6838` cộng working tree đã nghiệm thu; không dùng HEAD riêng thay source.
**UC36 hoàn thành và được chốt nghiệm thu trong phạm vi TV3 theo quyết định owner trên chat.** Tích hợp Booking production TV2 được hoãn riêng: TV2 còn phát triển và chưa đưa nhánh về kiến trúc mới; hiện không lấy/merge code nhánh đó.
Hồ sơ này gộp QA và khảo sát tích hợp; [workflow](INVOICE_PAYMENT_WORKFLOW.md) giữ contract, [checklist](TV3_CHECKLIST.md) giữ tiến độ. Không mở rộng nghiệm thu sang các UC Invoice khác.

## 1. Phạm vi nghiệm thu

| Checkpoint | Kết quả / trạng thái |
| --- | --- |
| UC36-INV-02 | Contract quyền/transition/paid_at/no-op/conflict đã ACCEPTED |
| UC36-INV-03A/03B | API/UI payment và test raw input/DTO/ownership/lock/fault/outer commit/regression đã ACCEPTED |
| UC36-QA-01 | MySQL transaction/concurrency/lock timeout/guard đã ACCEPTED |
| UC36-QA-02A/02B | Browser/session/CSRF/pending/focus/responsive/recovery/cancellation đã nghiệm thu theo phạm vi;02A9PASS/2PARTIAL là kết quả lịch sử |
| UC36-FIX-01 | Host paid_at và hướng dẫn419 đã ACCEPTED; F01/F02 CLOSED |
| UC36-QA-03/04 | Rà12gate và bổ sung quan hệ thiếu/Axios timeout thật đã ACCEPTED; T04/T10/G01/G02 VERIFIED trong điều kiện harness được duyệt |
| UC36-CLOSE-01 | Tổng hợp nghiệm thu và chuẩn bị bàn giao đã ACCEPTED |
| UC36 tổng phạm vi TV3 | ACCEPTED; nhóm triển khai/QA đã hoàn tất trong phạm vi TV3. Booking production end-to-end tách sang dependency hoãn |
| UC36-INT-PREP-01 | Khảo sát source TECHNICAL PASS; nghiệm thu riêng PENDING. Không coi đây là runtime integration |

Luồng đã kiểm: Host mở Invoice fixture UNPAID → xác nhận nhận đủ tiền → PAID/server paid_at → Host/Customer đọc lại; no-op/race/rollback/outer commit/lỗi/recovery. Không có gateway/ngân hàng, refund mutation hoặc cập nhật Booking tự động.

| AC tổng | Kết luận hiện hành trong phạm vi TV3 |
| --- | --- |
| UC36-AC01 | PASS: workflow/quyền/paid_at/retry/refund được nghiệm thu ở INV-02 |
| UC36-AC02 | PASS: Backend role/ownership/raw input, kể cả quan hệ thiếu ở QA-04 |
| UC36-AC03 | PASS phạm vi TV3: API/UI, tiền/items/Booking bất biến trên fixture; Booking production TV2 NOT VERIFIED và hoãn riêng |
| UC36-AC04 | PASS: no-op và hai writer chỉ một transition/paid_at/success log; outer rollback/commit đúng |
| UC36-AC05 | PASS trong các case đã kiểm: pending/error/recovery, không replay; timeout/cancellation giữ giới hạn bên dưới |
| UC36-AC06 | PASS: evidence MySQL/Node/browser cùng giới hạn được ghi nhận |
| UC36-AC07 | ACCEPTED phạm vi TV3 theo quyết định owner; không tuyên bố nghiệm thu tích hợp TV2 |

AC03 từng PARTIAL và AC07 từng PENDING khi còn gộp gate tích hợp; cập nhật trên phản ánh quyết định tách phạm vi của owner, không bổ sung một lượt test Booking production. Bàn giao TV2 hiện PREPARED / NOT SENT / TV2 ACK PENDING.

## 2. Contract và điểm tích hợp hiện hữu

### API và điểm vào UI

[Billing routes](../../backend/routes/api/billing.php) đăng ký các API dưới auth:sanctum/account.active; kiểm role/ownership nằm tại luồng Controller/Validator/Service tương ứng.

| API hiện hữu | Contract tích hợp |
| --- | --- |
| POST /api/host/bookings/{booking}/invoice | Tạo Invoice từ Booking đã COMPLETED; client không gửi giá/tổng/paid_at. Frontend hiện gọi không có body;201 với data detail. Duplicate409; sai quyền403; Booking không có404; điều kiện/snapshot sai422 theo controller hiện hữu |
| PATCH /api/host/invoices/{invoice}/payment-status | Body đúng một field payment_status=PAID;200 full detail cho transition/no-op,403/404/409/419/422 theo INV-02. Invoice ID canonical, raw validation và thứ tự lỗi giữ baseline |
| GET /api/host/invoices và /api/host/invoices/{invoice} | Host list/detail scoped theo booking.room.property.host_id |
| GET /api/customer/invoices và /api/customer/invoices/{invoice} | Customer list/detail scoped theo booking.customer_id |

Detail dùng envelope data; tiền decimal strings, paid_at ISO UTC nullable, items có trong detail và bị omission ở list. Payment success PAID phải có paid_at. Không chuẩn hóa lỗi tạo cũ sang code payment mới hoặc bịa field changed. Nguồn: [Host controller](../../backend/app/Http/Controllers/Invoice/HostInvoiceController.php), [response DTO](../../backend/app/DTOs/Invoice/Responses/InvoiceResponse.php), [Frontend API](../../frontend/src/api/invoiceApi.js).

Router React hiện có /host/bookings/:bookingId/create-invoice, /host/invoices/create, /host/invoices/:invoiceId và /customer/invoices/:invoiceId trong [AppRouter](../../frontend/src/routes/AppRouter.jsx). Host creation nhận Booking ID từ route hoặc nhập tay. Customer Booking detail hiện đọc booking.invoice_id hoặc booking.invoice.id để tạo link Invoice. Đây là nhu cầu consumer hiện hữu, **chưa phải Booking API response đã được TV2 xác nhận**.

[Backend API root](../../backend/routes/api.php) hiện include billing/reviews/profile/catalog; chưa đăng ký workflow Booking production. Đường /api/customer/bookings/{id} trong [bookingService](../../frontend/src/services/bookingService.js) là consumer kỳ vọng, không chứng minh endpoint đã hoạt động. Mock chỉ bật khi DEV và VITE_ENABLE_BOOKING_MOCKS=true; không có fallback mock tự động khi API lỗi. Không sửa env để bật mock và gọi đó là tích hợp thật.

### Snapshot Booking mà Invoice đang đọc

Nguồn: [InvoiceService](../../backend/app/Services/Invoice/InvoiceService.php), [InvoiceValidator](../../backend/app/Validators/Invoice/InvoiceValidator.php), [InvoiceMapper](../../backend/app/Mappers/Invoice/InvoiceMapper.php), [BookingService model](../../backend/app/Models/BookingService.php).

| Dữ liệu | Yêu cầu thực tế của consumer Invoice |
| --- | --- |
| Booking | id, customer_id, room_id, status=COMPLETED; number_of_nights dương, price_per_night và room_total hợp lệ; room_total bằng phép tính decimal số đêm × giá snapshot |
| Ownership | room.property tồn tại và property.host_id là Host hiện tại. Không yêu cầu mới Property/Room ACTIVE để trả tiền; current-owner rule giữ baseline |
| Customer | Quan hệ customer tồn tại; name/email/phone không rỗng. Các giá trị hiện tại được snapshot vào Invoice khi tạo |
| Tên phòng/cơ sở | Room.name và Property.name hiện tại phải có; mapper snapshot lúc tạo Invoice, không khẳng định tên đã được Booking lưu bất biến |
| Booking services | bookingServices có service_id, service_name, quantity, unit_price, amount; Model còn có unit. Quantity dương, amount khớp phép tính decimal quantity × unit_price; danh sách rỗng hợp lệ |
| Tiền dịch vụ | Mapper chuyển snapshot đã lưu, Validator kiểm amount; Calculator tính lại từ quantity/unit_price snapshot và cộng tổng. Không đọc lại giá Service catalog; không tin giá client |
| Discount/extra fee | Service tạo hiện đặt cả hai0.00; fixture QA có giá trị khác để kiểm bảo toàn payment không có nghĩa API create hỗ trợ nhập tùy ý |
| Uniqueness/transaction | Tối đa1Invoice/Booking, Booking lock và unique constraint, header/items tạo atomically; Booking/BookingService không được payment sửa |

Tài liệu công thức SUM(booking_services.amount) và code tính từ quantity/unit_price chỉ tương đương sau kiểm nhất quán amount. Không bỏ kiểm này để nhận snapshot TV2 sai; nếu TV2 có rounding/discount theo dòng khác thì cần thống nhất riêng trước thay đổi. Không chuyển giá catalog mới thành giá snapshot của Booking cũ.

## 3. Ma trận 12 gate

Kết quả dưới là bằng chứng kế thừa các lượt triển khai/QA đã nghiệm thu, không phải test vừa chạy ở lượt gộp tài liệu. Các gate đạt trong phạm vi và điều kiện ghi tại từng dòng.

| Gate INV-02 | Evidence được kế thừa | Ranh giới |
| --- | --- | --- |
| T01 transition | Payment MySQL03A/03B/QA-03/04, browser readback | Fixture Booking, không TV2 workflow |
| T02 no-op | Duplicate/no-op và race thật | Một paid_at/update/log; không thêm field changed |
| T03 conflicts | HTTP4state cases và domain unknown-state | Unknown enum chỉ validator fact boundary, không lưu enum sai lên MySQL |
| T04 role/ownership/relations | Actor/precedence/direct-call và QA-04 relation4tests/37assertions | Quan hệ lỗi dựng bằng FK session override được duyệt; FK bật lại lúc request |
| T05 payload | Raw type/value/whitelist/non-object/parser matrix | Contract parser/framework phân biệt422 |
| T06 rollback/outer commit | Commit suite faults/reader độc lập | Mapper/updated-event errors/outer transaction được kiểm |
| T07 concurrency |2worker/PDO, wait/NOWAIT/timeout control, both commit orders/rollback | SQL lock timeout không thay Axios timeout |
| T08 Invoice regression |5API cũ và Host/Customer readback | Không tự nghiệm thu toàn các UC Invoice khác |
| T09 Node |158tests gần nhất QA-04 | Transport fixtures, không browser proof |
| T10 browser |02A/02B/FIX-01/QA-03 + QA-04 XHR timeout trước/sau commit | Timeout5000ms chỉ harness; production vẫn không timeout, cancellation/account switch có giới hạn02B |
| T11 lint/build | QA-03 exit0,3FastRefresh warnings/chunk>500kB | QA-04/CLOSE-01 không chạy lại; source giữhash |
| T12 isolation | Owned target guards,27demo tables hashes/columns,cleanup từng lượt | CLOSE-01 không kết nối DB hoặc provision |

## 4. Bằng chứng kiểm thử theo lượt

Ngày các lượt UC36: 2026-11-17, Asia/Bangkok. Runtime DB: MySQL Docker8.4.13/127.0.0.1:3307, server UUID `b4736273-e223-11f1-a722-aaf8fde9dbf9`; PHP XAMPP8.2.12. Đây là metadata lúc chạy, không xác nhận instance đang chạy ở lượt tài liệu.
Mỗi run dùng schema `stayhub_uc36_test_<guid32>`/user `uc36_<hex>` riêng; không dùng stayhub làm fixture. Browser dùng React/Vite và Laravel/session/CSRF thật, dataset tổng hợp; không phải workflow Booking TV2.

| Run / artifact local | Kết quả gốc / giới hạn |
| --- | --- |
| [INV-03A](E:/StayHub-DB-Recovery/uc36-inv03a-1a9503ea94fa4c2695e3773e556762d7/) | MySQL26tests/119assertions;Node158;lint/build exit0. Chưa browser trong lượt này |
| [INV-03B](E:/StayHub-DB-Recovery/uc36-inv03b-b4bbd84b580b4a2984790615bea59fa4/) | MySQL52/344;Node158;Host/Customer browser smoke/SQL; không full Backend |
| [QA-01](E:/StayHub-DB-Recovery/uc36-qa01-48d6387fcd89406b89e2f7a91d76b5f1/) | MySQL57/559;hai process/PDO thật, NOWAIT3572/processlist/lock timeout1205;hai commit orders, rollback và timeout control. Không Node/browser/lint/build mới |
| [QA-02A](E:/StayHub-DB-Recovery/uc36-qa02a-cd5a27e6042b4eada2718cc5158a7a09/) |11browser cases:9PASS/2PARTIAL; F01 thiếu paid_at Host/F02 hướng dẫn419 lúc đó OPEN. Không PHPUnit/Node/lint/build mới |
| [QA-02B](E:/StayHub-DB-Recovery/uc36-qa02b-5b32be6de5874852b594c8a7f361cae6/) |7browser probes; lost response trước/sau commit,GET503,malformed success,cancellation/account switch. Không gọi drop network là Axios timeout |
| [FIX-01](E:/StayHub-DB-Recovery/uc36-fix01-0a41c56b1d284d56b0a8b56ff1206114/) |Node158,lint/build exit0;T01–T08 browser/SQL PASS; F01/F02 sửa và được nghiệm thu đóng |
| [QA-03](E:/StayHub-DB-Recovery/uc36-qa03-6d4d9358e3414e6e8c6b745ad7108884/) |MySQL57/560;Node158,lint/build exit0;Host/Customer readback/419/lost response và bốn negative target guards.10gate đạt, T04/T10 PARTIAL lúc đó |
| [QA-04](E:/StayHub-DB-Recovery/uc36-qa04-ff1dae9aab5d430f8edfed7de0e5ed2e/) |MySQL57/559;external RelationGapTest4/37;Node158;hai XHR timeouts thật/control;T04/T10 bổ sung VERIFIED. Lint/build kế thừa QA-03 |
| [CLOSE-01](E:/StayHub-DB-Recovery/uc36-close01-76f27db481254bdd950f0f02207607f4/) |Docs/source/evidence verification:73links/3anchors,8artifact dirs,659outside hashes; không DB/runtime mới |
| [INT-PREP-01](E:/StayHub-DB-Recovery/uc36-int-prep01-6addfa71c1654c919e01cad79b397e27/) |Source-only:76links/3anchors,112candidate paths/660outside hashes; refs/index giữ nguyên. Không ghép code,không DB/runtime |

Số assertion57/560 của QA-03 và57/559 của QA-04 là các lượt khác; không cộng dồn hoặc sửa lịch sử. Node là domain/transport tests, không browser proof. Lint còn ba Fast Refresh warnings, build chunk>500kB; đây là warnings lịch sử. Full Backend/SQLite không chạy để đóng UC36; các suite SQLite-only giữ nguyên guards.
Artifact chứa baseline/final-verification, tests/logs, request/SQL/geometry/screenshots khi áp dụng và cleanup. Các đường dẫn local ngoài Git cần được cung cấp riêng khi bàn giao; đọc log không phải chạy lại test hoặc CI.

### Browser, findings và giới hạn quan sát

| Case / nhóm | Kết quả đã ghi nhận |
| --- | --- |
| QA02-A01–A03 |Cancel/Escape idle không PATCH;confirm/reload/readback đúng;double-click/Enter chỉ một PATCH,pending khóa dialog |
| QA02-A04/A09 |PAID/REFUNDED và419 bảo vệ mutation đúng; thiếu paid_at Host/hướng dẫn419 là hai PARTIAL cũ, đã sửa FIX-01 |
| QA02-A05–A08 |409/401/403account/404ownership thật, không write sai/replay;409 recovery đọc REFUNDED. Lượt đầu fixture5 chưa quan sát settled, lặp quan sát bằng fixture11 mới đạt |
| QA02-A10/A11 |Label/focus trap/Escape/focus return;360×800/768×1024/1440×900 không tràn ngang; không screen-reader audit hoặc mọi device/browser |
| QA02-B01/B02 |Drop response sau commit→GETPAID;drop request trước forward→GETUNPAID. Lần confirm mới sau GETUNPAID là thao tác chủ động, không replay |
| QA02-B03/B04 |GET recovery503 giữ khóa/chỉ thử GET lại;response PAID nhưng paid_at=null bị reject success,GET thật phục hồi |
| QA02-B05 GET/PATCH |Rời route khi response held→client_closed;không hiển thị kết quả cũ trên Invoice mới. Client abort không chứng minh server rollback |
| QA02-B06 |Logout A/login B trong cùng app khi PATCH cũ held;response client_closed,B giữ đúng Invoice và không đọc Invoice A. Chưa cross-tab hoặc mọi nhánh fallback identity |
| FIX-01 T01–T08 |Host paid_at sau confirm/reload/recovery;PAIDnull hiển thị gạch;UNPAID/REFUNDED ẩn;419 tiếng Việt/manualGET;409/404/focus/responsive đạt |
| QA-03 readback |Host và Customer cùng Invoice/paid_at;419 phục hồi thủ công;lost response sau commit→GETPAID; không mở rộng sang Booking thật |

UC36-QA02A-F01/F02 hiện **CLOSED / FIX-01 ACCEPTED**. Các PARTIAL cũ giữ nguyên kết quả lượt gốc, không ghi lại thành fullPASS.

- T04/G01: schema test được duyệt FK override chỉ lúc dựng ba fixture thiếu Booking/Room/Property; FK bật lại trước HTTP. Ba404/no-write/no-success-log,control200/một success;restore fixture,FKsession/global1 và0orphan trước cleanup. Unknown enum chỉ validator fact boundary, không persist enum sai lên MySQL.
- T10/G02: harness interceptor override5000ms chỉ ba PATCH mục tiêu, proxy giữ trước forward/sau commit; hai XHR timeout `ECONNABORTED` sau5011/5004ms. GET lần lượt UNPAID/PAID;control372ms. Ba PATCH nhận/hai forward, không automatic replay. Production vẫn không cấu hình timeout; không sửa shared Axios/env/source để đạt test.
- No-op giữ paid_at/updated_at/items/log; success chỉ sau outer commit. Fixtures so money/header/items/Booking trước/sau, không dùng số tiền client hoặc catalog mới.
- Mỗi lượt kiểm target/effective config/UUID và ownership trước write; scoped user không đọc được stayhub,27demo tables/counts/hashes/payment columns giữ nguyên. Chỉ drop schema/user owned khi0connection;fixture processes/browser đã dọn và password metadata loại bỏ. Datadir XAMPP/backup/Docker volume/env được giữ. Đây là bằng chứng bảo toàn/cleanup của lượt gốc, không phải kiểm SQL mới ở lượt tài liệu.

## 5. Dependency TV2 đang hoãn

Tất cả các mục dưới **OPEN / chưa xác nhận bởi TV2**. Owner ở bảng là phân công đề nghị phối hợp, không phải xác nhận TV2 đã nhận việc.

| ID | Nội dung cần xác nhận | Owner phối hợp |
| --- | --- | --- |
| UC36-TV2-D01 | Workflow Booking thật, route/role và điều kiện check-out → COMPLETED, không bắt buộc PAID trước khi TV3 được tạo Invoice UNPAID | TV2 chính; TV3 đối chiếu consumer |
| UC36-TV2-D02 | Ghi và giữ snapshot nights/price/room_total, dịch vụ quantity/unit_price/amount, rounding; quy tắc bất biến khi Invoice đã tạo | TV2 chính, TV3 xác nhận tương thích |
| UC36-TV2-D03 | Dữ liệu Customer/Room/Property đủ cho snapshot và ownership; thay đổi chủ/lifecycle nếu có phải chốt writer/lock tương ứng với TV1 | TV2/TV1 theo domain, TV3 kiểm scope Invoice |
| UC36-TV2-D04 | Booking detail cung cấp liên kết Invoice theo shape thống nhất; Host có điểm vào create/detail thật; không coi frontend expectation là API đã chốt | TV2 consumer Booking + TV3 Invoice, integrator nếu shared router cần đổi |
| UC36-TV2-D05 | Thực thi end-to-end trên MySQL Docker/schema riêng khi workflow TV2 sẵn sàng, lưu evidence rồi xin nghiệm thu chung | TV2+TV3; owner nghiệm thu |

Điểm cần TV2 xác nhận từ source: bookingService gắn hint COMPLETED là “Kỳ nghỉ đã hoàn tất thanh toán”, nhưng use case hiện mô tả COMPLETED → tạo Invoice UNPAID. COMPLETED của Booking không tự chứng minh Invoice PAID. Đây là khác biệt diễn đạt cần thống nhất trước tích hợp; CLOSE-01 không sửa Booking status, copy UI hoặc payment policy của TV2.

### Khảo sát source UC36-INT-PREP-01

Khảo sát ngày2026-11-17, local candidate `feature/member-2-booking` tại `dca536024bfe1c6915818b5088db83721dc78c53`; merge-base `951f62efc70127a3719c840ae529f8d311842a49`; TV3 HEAD/dev `6bd6838a685be8173d3da3e7edba8e5df89d06c7` cộng accepted working tree.
Đọc git show/diff/ls-tree,không fetch/checkout/merge/merge-tree/runtime. Đây là kết quả tại SHA cũ, không phải trạng thái nhánh đang được TV2 phát triển hôm nay hoặc bàn giao chính thức.

Inventory normalizeCRLF để phân loại:262TV3-only cần giữ,105TV2-only và7both-differ (112candidate paths). Không gọi bảy đường dẫn là bảy merge conflicts đã tái hiện hoặc112file được phép nhập. Exact inventory/quyết định đề nghị nằm ở artifact PREP-01: `three-side-inventory.json`, `candidate-decisions.json`, `refs.json`, `baseline.json`, `final-verification.json`.

Bảy đường dẫn both-differ: backend/bootstrap/app.php; backend/routes/api.php; backend/tests/Feature/Auth/AuthenticationTest.php; frontend/src/pages/customer/CustomerCheckoutPage.jsx; frontend/src/pages/public/PropertyDetailPage.jsx; frontend/src/routes/AppRouter.jsx; frontend/src/services/propertyService.js.

- Giữ baseline stateful/account/GlobalExceptionHandler/Catalog routes và UI Catalog; candidate bootstrap/router cũ có thể làm mất chúng nếu ghi đè. Role alias/HostLayout/Booking routes chỉ xem xét sau scope chung được duyệt.
- Candidate có duplicate GET /api/host/properties; cả hai source trả array id/name, không có finding pagination mismatch. Giữ API TV1, tránh nhập stub trùng. Model/factory shared cần owner tương ứng xác nhận.
- TV2 composer/lock,Frontend manifests,phpunit,migrations/seeders và BookingStatus không delta so merge-base. Giữ Symfony dependencies TV3; không downgrade. Binary backend/stayhub là SQLite data,không nhập; .vscode preferences không nhập,context docs chỉ tham khảo.
- Candidate suites dùng RefreshDatabase,không owned MySQL guards; không chạy trên stayhub. Khi được tiếp tục cần runner schema/user riêng,giữ legacy SQLite guards.
- BookingResource/HostBookingResource chưa invoice projection; Customer UI đang kỳ vọng invoice_id hoặc invoice.id,Host chưa action Invoice. Đây là nhu cầu contract,chưa shape được TV2 xác nhận.

### Contract cần TV2 xác nhận khi tiếp tục

Tất cả **PENDING TV2 CONFIRMATION**; owner trong bảng là đầu mối đề nghị, không phải người đã nhận việc. Không gửi câu hỏi qua công cụ trong task này.

| ID | Source/câu hỏi cụ thể | Owner và điều kiện đóng |
| --- | --- | --- |
| UC36-PREP-C01 | TV2 có bàn giao chính thức dca5360 hay commit khác? Nhánh local có còn thay đổi chưa thấy? | TV2 chốt full SHA và tài liệu/test baseline; owner chốt cách đưa đủ working tree TV3 vào nguồn ghép |
| UC36-PREP-C02 | BookingWorkflowService::checkOut chuyển COMPLETED không kiểm Invoice PAID, phù hợp create Invoice UNPAID. Hint COMPLETED trong bookingService nói đã hoàn tất thanh toán có cần đổi? | TV2 xác nhận COMPLETED là kết thúc lưu trú; TV3 xác nhận payment độc lập, không tự đổi lifecycle |
| UC36-PREP-C03 | PricingService dùng float×float/number_format; BookingServiceManagement round quantity2digits; Invoice dùng BCMath round-half-up và so snapshot amount | TV2+TV3 thống nhất decimal precision/rounding/maximum và dữ liệu cũ; runtime edge cases cần đạt, không chỉ đồng ý bằng lời |
| UC36-PREP-C04 | add service khóa Booking trong transaction; updateQuantity/remove kiểm status model đã load rồi write không khóa cùng Booking. Check-out khóa Booking và đổi COMPLETED | TV2 chốt bất biến snapshot sau COMPLETED/Invoice, cách serialize writer update/remove với check-out/create Invoice; race đang là risk source, chưa runtime FAIL |
| UC36-PREP-C05 | BookingResource/HostBookingResource chưa có invoice_id/invoice, Customer UI đã đọc các field này; Host chưa có Invoice action | TV2+TV3 chốt optional/null/omission, fields id/code/payment_status/paid_at nếu cần, quyền/customer projection và load relations; không chốt cả hai shape tùy tiện |
| UC36-PREP-C06 | role alias/renderer/trait envelopes và duplicate GET Host Properties; UI catalog/routes hiện hành phải giữ | TV2+TV1/integrator chốt exact shared files và preserve contract; không thay Auth/shared Axios/config toàn cục |
| UC36-PREP-C07 | TV2 composer/package/migrations không delta so merge-base, fixtures/tests mới có RefreshDatabase, binary backend/stayhub | TV2 xác nhận required files/dependencies/test engine; bỏ binary khỏi candidate, MySQL runner owned schema trước test; không giảm baseline Symfony dependencies |

### 3.1. Rủi ro source cần TV2 xử lý/xác nhận trước runtime

| ID | Quan sát đã thấy | Ảnh hưởng/kiểm chứng cần làm |
| --- | --- | --- |
| UC36-PREP-R01 | Nhận định ban đầu thiếu booking_id đã bị loại khi tái đọc đầy đủ source pinned SHA: create có booking_id=$currentBooking->id | Không còn source finding thiếu FK. Vẫn kiểm create service mới/persisted booking_id trong runtime integration như đối chứng, không coi là bug cần TV2 sửa |
| UC36-PREP-R02 | updateQuantity/remove không re-read status dưới Booking lock cùng checkOut/create Invoice | Có thể ghi dịch vụ qua cửa sổ chuyển trạng thái từ model cũ; cần2writer race, bảo toàn snapshot/Invoice; không sửa TV2 trong PREP |
| UC36-PREP-R03 | Float pricing và BCMath validation khác triển khai | Chưa chứng minh sai tiền; test quantity0.01/0.05/1.005 input theo precision allowed, unit_price0.10/0.15/2.55, half-cent products, gần giới hạn DECIMAL12,2 và nhiều dòng; expected độc lập decimal, reject overflow rõ. Không truyền quantity3digits nếu request contract chỉ cho2digits rồi coi đó là case hợp lệ |
| UC36-PREP-R04 | Ghi đè API/bootstrap/router theo tree TV2 cũ có thể bỏ Catalog/renderer/UC36 working files | Cần combined snapshot và regression chéo; không merge ở working tree bẩn hoặc dùng HEAD-only snapshot |

## 6. Checklist tích hợp khi TV2 sẵn sàng

Toàn bộ case dưới **NOT VERIFIED trên workflow Booking production**, không lấy regression fixtures thay kết quả. Hiện hoãn theo owner; chỉ thực thi sau Mini Plan/approval riêng, target MySQL Docker owned schema/user, giữ stayhub. Không migrate/reset/seed DB dev.

| ID | Đầu vào/thao tác dự kiến | Expected để nghiệm thu | Owner |
| --- | --- | --- | --- |
| UC36-INT-T01 | Customer/Host đi qua workflow Booking thật đến COMPLETED | Trạng thái được server quyết định; snapshot và quan hệ đủ; không bật mock để vượt route thiếu | TV2 |
| UC36-INT-T02 | Booking thật có phòng và dịch vụ; kiểm snapshot, thay catalog giá trong fixture theo plan | Invoice dùng giá snapshot, amount khớp chính xác; tổng độc lập và không cộng phí hai lần | TV2+TV3 |
| UC36-INT-T03 | Host từ Booking thật tạo Invoice |201/detail/UNPAID/null; header/items nguyên tử; lần tạo thứ hai409, không Invoice trùng | TV3, TV2 cung cấp đầu vào |
| UC36-INT-T04 | Host/customer khác, guest, account không active qua điểm vào Booking/Invoice | Quyền/ownership đúng hai module; không lộ Invoice hoặc mutation trái quyền; status/code theo contract module | TV2+TV3 |
| UC36-INT-T05 | Host xác nhận đủ tiền, reload; Customer mở link từ Booking detail thật | PAID/server paid_at và cùng Invoice ID; UI không suy PAID từ COMPLETED, decimal contract giữ nguyên | TV3+TV2 |
| UC36-INT-T06 | Request payment lặp/đồng thời trong fixture Booking thật | Một transition/paid_at, no-op đúng, không đổi Booking/BookingService/items/tổng | TV3 |
| UC36-INT-T07 | Lỗi session/CSRF/mất phản hồi tại điểm vào tích hợp | UI phù hợp, không replay PATCH; GET xác nhận trạng thái. Timeout nếu kiểm phải nêu cấu hình thực tế | TV3+TV2 |
| UC36-INT-T08 | Đối chiếu trước/sau và cleanup | Demo nguyên vẹn; schema/user/process/file owned được dọn; evidence đủ và owner duyệt | TV2+TV3 |

TV2 không phải tái triển khai Invoice payment. TV3 không tự triển khai Booking/check-in/check-out, availability hoặc sửa module TV1 để hoàn tất checklist trên.

PREP-02 và INT-01 chưa thực hiện; các bước ghép/triển khai từng đề xuất không còn là kế hoạch đang chạy. Khi owner yêu cầu tiếp phải kiểm lại Git/SHA/accepted working tree/kiến trúc/contract và lập Mini Plan mới. Không tự tạo nguồn ghép, lấy code TV2 hoặc sửa nghiệp vụ TV1/TV2.
