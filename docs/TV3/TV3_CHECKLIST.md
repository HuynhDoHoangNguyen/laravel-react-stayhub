# StayHub TV3 — Tiến độ và việc còn lại

Cập nhật: 2026-11-22 (Asia/Bangkok). **18/18 UC CLOSED TV3.**
Owner: TV3. [Phân công](../stayhub-team-work-assignment.md) · [Mục lục hồ sơ](README.md).

## 1. Cách đọc trạng thái

- CLOSED TV3: owner đã nghiệm thu đúng phạm vi TV3; không đồng nghĩa tích hợp end-to-end toàn hệ thống.
- Luồng chính `[x]` và nghiệm thu `[x]` là hai cột khác nhau; UC con có thể được nghiệm thu qua UC cha.
- Technical PASS/FAIL/PARTIAL/NOT VERIFIED giữ theo evidence gốc, tách khỏi owner acceptance.
- Contract, test counts, run ledger và giới hạn chi tiết nằm trong hồ sơ được dẫn; checklist không lưu lại toàn bộ Mini Plan/Task Result.

## 2. Checklist 18 UC

| UC | Luồng chính | Nghiệm thu | Phạm vi / hồ sơ |
| --- | --- | --- | --- |
| UC15 — Xem hóa đơn cá nhân | [x] | [x] TV3 | Invoice read; T01; ma trận chung |
| UC16 — Tạo Review | [x] | [x] TV3 | Review create/eligibility; synthetic entry, T03 |
| UC17 — Chat với chatbot | [x] | [x] TV3 | **V1 CLOSED TV3 ngày 2026-11-22.** Tư vấn chung Guest/Customer, gợi ý Catalog công khai, lưu và replay Customer. Owner duyệt D1 đóng UC17 riêng, trước mốc đóng UC61. [Hồ sơ UC17](UC17_V1_CONTRACT.md#8-uc17-close-01) |
| UC34 — Tạo Invoice từ Booking | [x] | [x] TV3 | Create/commit/rollback/race; T02 |
| UC35 — Chi tiết Invoice Host | [x] | [x] TV3 | Detail/items/ownership; T01 |
| UC36 — Cập nhật trạng thái thanh toán | [x] | [x] TV3 | Payment API/UI; F01/F02 đã đóng, tích hợp TV2 hoãn |
| UC37 — Quản lý Invoice | [x] | [x] TV3 | List/filter/search/pagination/no-write; T01 |
| UC38 — Host xem Review | [x] | [x] TV3 | Scope/summary/UI; T03 |
| UC39 — Dashboard Host | [x] | [x] TV3 | CLOSED ngày 2026-11-21; 10 task đã nghiệm thu, giữ giới hạn QA-UI |
| UC44 — Admin xem toàn bộ Booking | [x] | [x] TV3 | **V1 CLOSED TV3 ngày 2026-11-22.** Danh sách chỉ đọc, tìm kiếm, bộ lọc và phân trang qua quyền Admin phía server. [Hồ sơ UC44](UC44_ACCEPTANCE_HANDOVER.md#21-uc44-close-01) |
| UC45 — Admin xem toàn bộ Invoice | [x] | [x] TV3 | CLOSE-01 nghiệm thu ngày 2026-11-18 |
| UC47 — Dashboard Admin | [x] | [x] TV3 | Sáu KPI/charts/UI; screen reader được miễn |
| UC48 — Thống kê hệ thống | [x] | [x] TV3 | CLOSED ngày 2026-11-20; 8 task + CSS/VND, giữ giới hạn manual dev |
| UC56 — Tạo InvoiceItem | [x] | [x] TV3 | Qua UC34; ROOM/SERVICE items nguyên tử, T02 |
| UC57 — Tính subtotal | [x] | [x] TV3 | Qua UC34; snapshots/decimal, T02 |
| UC58 — Tính tổng thanh toán | [x] | [x] TV3 | Qua UC34; API discount/extra_fee=0.00, nonzero chỉ calculator, T02 |
| UC60 — Kiểm tra điều kiện Review | [x] | [x] TV3 | Qua UC16; ownership/COMPLETED/validation/unique/race, T03 |
| UC61 — Ngữ cảnh nội bộ chatbot | [x] | [x] TV3 | **V1 CLOSED TV3 ngày 2026-11-22.** Owner duyệt **UC61-CLOSE-01** sau khi nghiệm thu 5 task ngữ cảnh công khai và 6 task Customer Chat có lưu. **Phần mở rộng hỏi phòng trống tự nhiên đã hoàn tất**, gồm CONTEXT-01, CONTEXT-02 và LIVE-QA-01 (Gemini thật PASS 3/3). [Hồ sơ UC61](UC61_ACCEPTANCE_HANDOVER.md#uc61-close-01) |

UC56/57/58 qua UC34 và UC60 qua UC16. FAQ Management và Chat History được theo dõi riêng ở mục 3.

## 3. FAQ và Chat History

| Phần việc | Trạng thái | Phần còn lại |
| --- | --- | --- |
| FAQ Management | MỞ; ngoài số đếm 18 UC | Contract, CRUD/quyền và API/UI quản lý |
| Chat History | MỞ; ngoài số đếm 18 UC | Lưu/đọc lịch sử, ownership, API/UI và xử lý lỗi |

## 4. Hồ sơ nghiệm thu

| Nhóm | Checkpoint / acceptance | Nơi tra cứu |
| --- | --- | --- |
| Baseline/refactor | Các phạm vi đã nghiệm thu, tương thích liên nhóm | [Baseline](BASELINE_HANDOVER.md) |
| Môi trường chung | TV3-AC-SCOPE-01, TV3-AC-ENV-01 | [Ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md) |
| Invoice read | INV-READ-QA-01/UI-01A/01B; IR01–06, UI01–16 | Ma trận §9 |
| Invoice create | INV-CREATE-QA-01/RACE-01/UI-01A/UI-01B; IC01–07, CUI01–18 | Ma trận §10 |
| Review read | REV-READ-QA-01/UI-01A/UI-01B; RV04-read/RV05/RV07, RUI01–20 | Ma trận §11 |
| Review create | REV-CREATE-QA-01/RACE-01/UI-01A/UI-01B; RV01–04/RV06/RV07, RCUI01–20 | Ma trận §12 |
| Đóng 10 UC | TV3-AC-CLOSE-01 — OWNER ACCEPTED | Ma trận: UC15/16/34/35/37/38/56/57/58/60 |
| Payment | UC36 CLOSED TV3 | [Handover](UC36_TV2_HANDOVER.md), [contract](INVOICE_PAYMENT_WORKFLOW.md) |
| Host Dashboard | UC39 CLOSED TV3; CONTRACT→CLOSE đã nghiệm thu ngày 2026-11-21 | [Handover UC39](UC39_ACCEPTANCE_HANDOVER.md) |
| Admin Booking | CLOSE44-01–06 PASS / OWNER ACCEPTED. UC44 V1 CLOSED TV3 ngày 2026-11-22 | [Hồ sơ CLOSE-01](UC44_ACCEPTANCE_HANDOVER.md#21-uc44-close-01) |
| Admin Invoice | UC45 CLOSED TV3 | [Handover UC45](UC45_ACCEPTANCE_HANDOVER.md) |
| Admin Dashboard | UC47 CLOSED TV3 | [Handover UC47](UC47_ACCEPTANCE_HANDOVER.md) |
| Statistics | UC48 CLOSED TV3; CSS/VND cũng đã nghiệm thu | [Handover UC48](UC48_ACCEPTANCE_HANDOVER.md) |
| Chatbot | UC17-CLOSE-01 — TECHNICAL PASS tài liệu / OWNER ACCEPTED phạm vi V1 và UC17-GUEST-01 | [Hồ sơ UC17](UC17_V1_CONTRACT.md#8-uc17-close-01) |
| Chatbot · FAQ công khai | **UC61-FAQ-01 — TECHNICAL PASS (FAQ-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Guest và Customer ở chế độ Gợi ý chỗ nghỉ đọc đúng FAQ seed “Làm thế nào để đặt phòng?”. MySQL Docker với fake 10 pass / 131 assertions, Edge 7/7 checkpoint (2 FAQ, 5 hồi quy UC17), 0 lượt Gemini. | Artifact local: [MySQL](../../.agent-workspace/tv3/ac-env-01/7f783fbf6f224554b64c11b0f6c8059b/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/6a540e51b38b45feb939998aac0c1364/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/6a540e51b38b45feb939998aac0c1364/cleanup.json) |
| Chatbot · Catalog công khai | **UC61-CATALOG-01 — TECHNICAL PASS (CAT-AC01–06) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Guest và Customer ở chế độ Gợi ý chỗ nghỉ phân giải cơ sở công khai, hỏi lại khi trùng tên, chọn bằng mã và lọc giá “dưới 1 triệu” theo biên loại trừ. MySQL Docker với fake 14 pass / 199 assertions, Edge 7/7 checkpoint (2 Catalog, 5 hồi quy UC17), 0 lượt Gemini. | Artifact local: [MySQL](../../.agent-workspace/tv3/ac-env-01/a541d5c6bdbf418d9fb56c69b417ad6a/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/16ed2eb6b3714849b09e9dd9f75da155/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/16ed2eb6b3714849b09e9dd9f75da155/cleanup.json) |
| Chatbot · Wi-Fi theo phòng công khai | **UC61-AMENITY-01 — TECHNICAL PASS (AM-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Guest và Customer ở chế độ Gợi ý chỗ nghỉ tra Wi-Fi theo mã cơ sở hoặc tên phòng trong cơ sở, với liên kết tiện nghi đang bật. MySQL Docker với fake 18 pass / 248 assertions, Edge 7/7 checkpoint (2 Wi-Fi, 5 hồi quy UC17), 0 lượt Gemini. | Artifact local: [MySQL](../../.agent-workspace/tv3/ac-env-01/ad11edf437684e28a9883d7558d74635/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/66fa63236cde4726ae9e7170cd8da589/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/66fa63236cde4726ae9e7170cd8da589/cleanup.json) |
| Chatbot · Dịch vụ theo cơ sở công khai | **UC61-SERVICE-01 — TECHNICAL PASS (SVC-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. TV2 xác nhận công bố tên, đơn giá niêm yết và đơn vị kg/lượt cho Guest/Customer khi Property ACTIVE và dịch vụ đang bật. Lát đầu hỗ trợ Giặt ủi và Đưa đón sân bay theo mã cơ sở. MySQL Docker với fake 22 pass / 305 assertions, Edge 7/7 checkpoint (2 dịch vụ, 5 hồi quy UC17), 0 lượt Gemini. | Artifact local: [MySQL](../../.agent-workspace/tv3/ac-env-01/0de91660e89540838aa415ef79476d2b/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/05f5eab3a161432bba1de386ce328222/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/05f5eab3a161432bba1de386ce328222/cleanup.json) |
| Chatbot · Availability một phòng công khai | **UC61-AVAILABILITY-01 — TECHNICAL PASS (AVL-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Guest và Customer ở chế độ Gợi ý chỗ nghỉ kiểm tra một phòng theo mã, ngày và số khách qua contract TV2 (UTC, chỉ dùng kết luận available). MySQL Docker với fake 27 pass / 377 assertions, Edge 7/7 checkpoint (2 Availability, 5 hồi quy UC17), 0 lượt Gemini. | Artifact local: [MySQL](../../.agent-workspace/tv3/ac-env-01/fed3a35fdd724ddd9d69a77dd1616f52/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/b938b820186f45879580b1b86cbf5728/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/b938b820186f45879580b1b86cbf5728/cleanup.json) |
| Chatbot · FAQ công khai trong Customer Chat có lưu | **UC61-CUSTOMER-01 — TECHNICAL PASS (CUST-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. FAQ seed “Làm thế nào để đặt phòng?” được trả lời và lưu qua luồng UC17 kể cả khi hai cờ AI tắt. Retry cùng UUID và payload trả lại đúng cặp tin nhắn đã lưu. FAQ ngoài phạm vi có tiền tố `FAQ:` trả `422` và không tạo lượt chat. MySQL Docker với fake: ChatHttp 13 pass / 242 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 9/9 checkpoint (2 FAQ Customer, 7 hồi quy UC17), 0 lượt Gemini. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/0404030c26bb4434adb16a5c5018f4a3/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/df8394efcde24086975fb3583605ab99/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/6367eb951f644f869f5abeed4203ec87/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/f02dd91e3b2e437ba6ec5b64d791c729/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/f02dd91e3b2e437ba6ec5b64d791c729/cleanup.json) |
| Chatbot · Catalog công khai trong Customer Chat có lưu | **UC61-CUSTOMER-CATALOG-01 — TECHNICAL PASS (CCAT-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Lệnh Catalog tra phòng theo số khách, ngân sách mỗi đêm và mã cơ sở tùy chọn. Kết quả văn bản tối đa ba phòng được lưu và replay theo UUID. MySQL Docker với fake: ChatHttp 16 pass / 316 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 9/9 checkpoint (2 Catalog Customer, 7 hồi quy UC17), 0 lượt Gemini. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/020ca7d6033d4afcb1bf3fdde5405890/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/c7daa1b8153c4dd4a144dd99ddfff9f6/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/f532ecea01184c7daff91156afc3c925/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/579c5b6253454c2ab26c548c8a821dcc/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/579c5b6253454c2ab26c548c8a821dcc/cleanup.json) |
| Chatbot · Chọn cơ sở theo tên trong Customer Chat có lưu | **UC61-CUSTOMER-CATALOG-NAME-01 — TECHNICAL PASS (CCNAME-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Tên khớp duy nhất giới hạn truy vấn theo cơ sở, trùng tên lưu hướng dẫn chọn mã, tên không khớp hoặc chỉ thuộc cơ sở ẩn trả 422 trước khi lưu. MySQL Docker với fake: ChatHttp 19 pass / 382 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 9/9 checkpoint (2 ca theo tên, 7 hồi quy UC17), 0 lượt Gemini. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/55764e1c9e3e4545aad574f1d34fbc1a/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/d0ebaf0c6899417ca20ca275b90328c4/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/1d101c4bf150456d96e7f74bcddd34e5/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/19d5bf5d2e7c4105ad0522425793681e/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/19d5bf5d2e7c4105ad0522425793681e/cleanup.json) |
| Chatbot · Tiện ích công khai trong Customer Chat có lưu | **UC61-CUSTOMER-AMENITY-01 — TECHNICAL PASS (CAM-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Tra một tiện ích đang niêm yết theo tên hoặc bí danh Wi-Fi, với mã cơ sở và tên phòng tùy chọn. Kết quả văn bản được lưu và replay theo UUID. MySQL Docker với fake: ChatHttp 22 pass / 465 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 9/9 checkpoint (2 ca tiện ích, 7 hồi quy UC17), 0 lượt Gemini. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/802ef9a2569741fba3fcb31ed7a77f65/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/46fc3c4c2b8a404ea355cb51ffad4d12/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/ea95d28cdb5b4458afe4487a698c0597/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/c4c78a5899604114b4c99164a1745ec8/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/c4c78a5899604114b4c99164a1745ec8/cleanup.json) |
| Chatbot · Đơn giá dịch vụ công khai trong Customer Chat có lưu | **UC61-CUSTOMER-SERVICE-01 — TECHNICAL PASS (CSVC-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Tra Giặt ủi hoặc Đưa đón sân bay theo mã cơ sở, dùng đơn giá niêm yết với đơn vị kg/lượt theo contract TV2. Câu sai cú pháp trả 422 trước khi lưu. Retry cùng UUID trả đúng cặp tin nhắn đã lưu. MySQL Docker với fake: ChatHttp 25 pass / 548 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 9/9 checkpoint (2 ca dịch vụ, 7 hồi quy UC17), 0 lượt gọi ra ngoài. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/cbbd342c394f4079ab61e555fcd1576b/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/1d9a00a462e34927a458ff5c206ddf31/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/fc2a32b1acdf4a20a241e8c37bcf9e1e/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/af1570abe80e4cbe9619a2a6b4edc6ad/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/af1570abe80e4cbe9619a2a6b4edc6ad/cleanup.json) |
| Chatbot · Availability trong Customer Chat có lưu | **UC61-CUSTOMER-AVAILABILITY-01 — TECHNICAL PASS (CAVL-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Tra một phòng công khai theo mã, ngày nhận, ngày trả và số khách theo UTC. Lưu kết quả tại thời điểm tra, cùng UUID và payload trả nguyên cặp tin nhắn. Lệnh sai trả 422 trước khi lưu. MySQL Docker với fake: ChatHttp 28 pass / 635 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 9/9 checkpoint (2 ca Availability, 7 hồi quy UC17), 0 lượt gọi ra ngoài. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/78458fbc4e4041598b9259e4a450bd00/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/cabe46b1f99e4c24bec05c97a468dcee/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/f770c7a53b404225b6942851891d34d7/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/e7517a1608974254bf81af156dcaf59a/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/e7517a1608974254bf81af156dcaf59a/cleanup.json) |
| Chatbot · Đóng ngữ cảnh UC61 V1 | **UC61-CLOSE-01 — TECHNICAL PASS kiểm tài liệu / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Đối chiếu 11 task, 34 lượt chạy và phạm vi nghiệm thu TV3. | [Hồ sơ CLOSE-01](UC61_ACCEPTANCE_HANDOVER.md#uc61-close-01) |
| Chatbot · Hỏi phòng trống tự nhiên theo mã phòng | **UC61-GEMINI-CONTEXT-01 — TECHNICAL PASS với fake (GCTX-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Customer Chat tách ý định từ câu đã che dữ liệu rồi tra một phòng theo mã trên MySQL thật, lưu kết quả và replay cùng UUID. MySQL cô lập: ChatHttp 31 pass / 703 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 9/9 checkpoint, 0 lượt gọi ra ngoài. Ca theo mã phòng đã kiểm chứng Gemini thật tại UC61-GEMINI-LIVE-QA-01. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/4a2da5cdc5974aa28c378336e0e9df6f/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/d4b3b4a6939e4a07bf9ff441abbbdf03/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/4cfa2a5dccf04d01bab882c0dfac8aa8/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/c91cbf8348294ec795746e075dfa7ec0/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/c91cbf8348294ec795746e075dfa7ec0/cleanup.json) |
| Chatbot · Hỏi phòng trống tự nhiên theo cơ sở và cuối tuần UTC | **UC61-GEMINI-CONTEXT-02 — TECHNICAL PASS với fake (GCTX2-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Customer Chat tra tên đầy đủ hoặc mã công khai, làm rõ tên trùng, dùng thứ Bảy đến thứ Hai theo UTC và lưu kết quả để replay cùng UUID. MySQL cô lập: ChatHttp 34 pass / 776 assertions, GuestChat 27 / 377, ChatRecovery 5 / 91. Edge 10/10 checkpoint, 0 lượt gọi ra ngoài. Ca theo tên và mã cơ sở đã kiểm chứng Gemini thật tại UC61-GEMINI-LIVE-QA-01. | Artifact local: [ChatHttp](../../.agent-workspace/tv3/ac-env-01/449e2fe2d5ef42c1a98e4aae14db6b5c/tests.txt), [GuestChat](../../.agent-workspace/tv3/ac-env-01/29b1955de92c4e359ee384fd4eaca428/tests.txt), [ChatRecovery](../../.agent-workspace/tv3/ac-env-01/25a9f7a1f62c438c98124413c42d48fa/tests.txt), [Edge](../../.agent-workspace/tv3/ac-env-01/4493693655924ffd9519c025493e4609/browser.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/4493693655924ffd9519c025493e4609/cleanup.json) |
| Chatbot · Kiểm chứng Gemini thật cho câu hỏi phòng trống tiếng Việt | **UC61-GEMINI-LIVE-QA-01 — TECHNICAL PASS (GQL-AC01–05) / OWNER ACCEPTED ngày 2026-11-22 (Asia/Bangkok)**. Edge dry run trên MySQL 8.4.13 cô lập đạt 5/5, guard chặn 7/7, 0 lượt gọi thật. Lượt live đạt **3/3** cho tên cơ sở và cuối tuần, mã cơ sở và ngày cụ thể, mã phòng và ngày cụ thể. `gemini-3.5-flash-lite` trả `200 / STOP` trong cả 3 lượt, đúng 1 lượt mỗi câu. MySQL ghi 1 phiên, 3 lượt và 6 tin nhắn. Replay cùng UUID trả `200`, thêm 0 lượt Gemini. Cleanup đạt, fingerprint nguồn và dữ liệu dev khớp. Lượt live đầu dừng sau 1 lượt vì Gemini `503`; lượt chạy lại đạt. | Artifact local: [Edge dry run](../../.agent-workspace/tv3/ac-env-01/e90dac764c314e54b742b067e5375e95/browser.json), [guard](../../.agent-workspace/tv3/ac-env-01/e90dac764c314e54b742b067e5375e95/guard-preflight.json), [Edge live](../../.agent-workspace/tv3/ac-env-01/ca90e9b252f341b3936e06e068c28b5f/browser.json), [3 lượt Gemini thật](../../.agent-workspace/tv3/ac-env-01/ca90e9b252f341b3936e06e068c28b5f/gemini-attempts.json), [MySQL](../../.agent-workspace/tv3/ac-env-01/ca90e9b252f341b3936e06e068c28b5f/fixture-after.json), [cleanup](../../.agent-workspace/tv3/ac-env-01/ca90e9b252f341b3936e06e068c28b5f/cleanup.json), [lượt live đầu 503](../../.agent-workspace/tv3/ac-env-01/bff73aaa863347df9d43aaf638e17792/browser.json) |

## 5. Dependency tích hợp còn mở

| ID | Trạng thái | Điều kiện cần để đóng |
| --- | --- | --- |
| T01 | **BLOCKED TV2** | Booking production tích hợp đúng owner/customer/room/property và Invoice liên kết thật |
| T02 | **BLOCKED TV2** | Checkout→COMPLETED, snapshots phòng/đêm/dịch vụ nhất quán, writer transaction/lock→Invoice; cần evidence checkout production |
| T03 | **BLOCKED TV2** | GET Customer Booking/điểm mở Review thật trả đúng owner/status/ID/review; COMPLETED do backend quyết định |

TV3 vẫn chịu trách nhiệm lỗi trong module mình. Booking API/workflow đã có, nhưng việc tồn tại API hoặc PR mergeable không chứng minh T01–T03 PASS.
PR #9 đã được owner close; PR #10 không được suy là đã sửa findings/đủ merge nếu chưa review mới. Review PR18 là công việc riêng, không đổi nghiệm thu TV3.

## 6. Giới hạn evidence cần nhớ

- Invoice/Review: 74 mục UI PASS theo owner; audit đúng lượt manual vẫn PARTIAL/NOT VERIFIED như ma trận. Không dùng harness01A thay browser01B.
- Review dùng synthetic parent; chưa chứng minh CustomerBookingDetail production. Booking fixture không chứng minh checkout thật.
- UC39: MySQL/HTTP/browser trên QA cô lập đã có; controlled delayed browser races PARTIAL, zoom200% NOT VERIFIED. Owner kiểm UI thấy ổn và chấp nhận không bổ sung.
- UC48: M01–M12 owner-reported PASS trên dev; không phải manual schema QA riêng. Runner audit exit1 do dev sessions, cleanup PASS; technical PARTIAL giữ nguyên.
- ADMIN-CSS-NAMING-01/ADMIN-VND-DISPLAY-01 đã nghiệm thu; browser mới sau hai thay đổi vẫn NOT VERIFIED. Không suy thành WCAG/screen reader PASS.
- Các lượt FAIL, negative controls, fingerprints và warnings giữ trong handover. SQLite skips, mocked transport, SSR/build không thay runtime evidence.
- Artifacts local/ignored không đi theo Git; muốn tái kiểm cần scope riêng và đúng source/API/actor/owned target.

## 7. Việc tiếp theo và quy tắc cập nhật

1. **TV3-DOC-CLEANUP-02A và 02B đã được owner nghiệm thu**; acceptance 02B được xác nhận lại trong yêu cầu UC44-START-01, đồng bộ checkpoint ngày 2026-11-21; giữ technical evidence gốc.
2. **UC44 V1 đã nghiệm thu.** Phần rút gọn checklist và hồ sơ UC44 được owner nghiệm thu ngày 2026-11-22. MySQL HTTP **135 pass / 543 assertions**. Edge/Laravel/Sanctum thật **11/11 checkpoint PASS** sau FIX-RESP-01. Hồi quy SQLite **2.855 pass / 493 skip**, Node **434 pass**, lint/build và cleanup đạt. **UI44-06 FAIL ở lượt gốc**, PASS khi chạy lại. L44-01/06/07 giữ PARTIAL lịch sử. [Ledger và bằng chứng](UC44_ACCEPTANCE_HANDOVER.md#21-uc44-close-01)

   **NOT VERIFIED:** orphan DB thực, rời route độc lập ngoài logout, runtime riêng UC45/39/47/48, browser khác, screen reader, cảm ứng thật, cookie/HTTPS ở domain triển khai và phục hồi sau hard-kill/mất điện. Đăng nhập từ deep link hiện về /admin. **T01–T03 BLOCKED TV2**, xem §5.
3. Mỗi task cần Mini Plan/exact scope, owner duyệt rồi mới thực hiện; code/QA/tích hợp TV2 không nằm trong task tài liệu.
4. Checklist chỉ cập nhật trạng thái và liên kết kết quả; contract/evidence đặt trong hồ sơ chức năng, không nối thêm báo cáo nhiều trang.
5. Giữ accepted tracked/untracked, env và dữ liệu dev; không tự commit/push/merge/rebase.
6. **Phần mở rộng UC61 hoàn tất ngày 2026-11-22 (Asia/Bangkok)**. Owner đã nghiệm thu CONTEXT-01, CONTEXT-02 và **UC61-GEMINI-LIVE-QA-01** với Gemini thật PASS 3/3 trên fixture QA công khai. Không còn task chờ trong phạm vi mở rộng đã duyệt. Mốc đóng UC61 V1 được ghi tại hồ sơ nghiệm thu TV3.

## 8. Checkpoint dọn tài liệu

| Task | Trạng thái | Phạm vi / evidence |
| --- | --- | --- |
| TV3-DOC-CLEANUP-01 | Technical result đã báo; owner acceptance riêng chưa ghi nhận | Đợt trước: 6 viết lại/4 gom-xóa/3 giữ; `.agent-workspace/tv3/doc-cleanup-01/` |
| TV3-DOC-CLEANUP-02A | TECHNICAL PASS tài liệu; OWNER ACCEPTED ngày 2026-11-21 | 4 viết lại + 6 gom-xóa; backup/content-map/verification tại đường dẫn dưới |
| TV3-DOC-CLEANUP-02B | TECHNICAL PASS tài liệu; OWNER ACCEPTED theo xác nhận trong UC44-START-01 | 5 hồ sơ biên tập + README/checklist; DCB01–07 PASS kiểm tài liệu, giữ 10 file; artifacts `doc-cleanup-02b/52813b1cc81d4bd3886c3312f411fe2f/` |

Artifacts 02A: `.agent-workspace/tv3/doc-cleanup-02a/2db97ded3e67452a99c8fc0e37826c6e/`.
Baseline 02A: `feature/tv3-uc39-host-dashboard`, HEAD `8532d02285bc2e3b466cb230b10c090c55974365` cộng accepted working tree.
Đợt cleanup01 dùng baseline `3931bf6d41c2f23e26ce73ba73a28bd3f9f008c5`; đó là lịch sử, không thay baseline hiện tại.

| AC 02A | Kết quả kỹ thuật | Evidence |
| --- | --- | --- |
| DCA01 | PASS | Đúng 10 file còn lại; 4 sửa, 6 xóa |
| DCA02 | PASS | Checklist chỉ hiện trạng, không giữ toàn bộ Task Result cũ |
| DCA03 | PASS | Content-map nguồn→section đích và kiểm coverage |
| DCA04 | PASS | UC39/48 CLOSED, 15/18, T01–T03 và acceptance giữ nguyên |
| DCA05 | PASS | Contract/AC/oracle/run verdict/counts đối chiếu trước xóa |
| DCA06 | PASS | Links/anchors/reference audit; không tạo link hỏng mới |
| DCA07 | PASS | Bốn file viết lại theo vai trò, giảm lặp và sửa câu chữ |
| DCA08 | PASS | Hash ngoài scope/Git status/whitespace; không chạy QA hoặc truy cập DB |

Skills áp dụng: [Planning](../../.agents/skills/stayhub-task-planning/SKILL.md) cho scope/baseline; [Verification](../../.agents/skills/stayhub-verification/SKILL.md) cho coverage/evidence/static checks. Technical PASS trên là kết quả kiểm tài liệu, không phải test chức năng chạy mới; owner đã nghiệm thu 02A và 02B, checkpoint 02B được đồng bộ theo xác nhận trong UC44-START-01. DCB01–07 đối chiếu scope/hash, content-map, AC/gate/oracle/ledger, lịch sử/hiện trạng, links/anchors và độ gọn tại verification-summary run 02B; không QA/DB. Còn một link hỏng có sẵn ngoài scope: `docs/TV2/BASELINE_HANDOVER.md:7` → `../TV3/BASELINE_PR_CHECKLIST.md`; hồ sơ thay thế là baseline TV3, không sửa file TV2 trong task này.
