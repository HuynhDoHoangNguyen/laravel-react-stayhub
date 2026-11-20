# StayHub TV3 — Checklist

Cập nhật: 2026-11-20 (Asia/Bangkok). **13/18 UC CLOSED phạm vi TV3; còn 5 UC**, cùng FAQ Management và Chat History ngoài số đếm 18 UC.
Nguồn phân công: [team assignment](../stayhub-team-work-assignment.md). Mục lục hồ sơ: [README](README.md).

## 1. Trạng thái hiện hành

- `[x]` luồng chính khác với nghiệm thu; `CLOSED TV3` chỉ đóng đúng phạm vi owner chấp nhận, không phải end-to-end toàn hệ thống.
- TV3-AC-CLOSE-01 **đã được owner nghiệm thu trên chat**: đóng UC15/16/34/35/37/38/56/57/58/60. UC36/45/47 giữ nghiệm thu trước đó.
- API/MySQL/frontend dùng evidence lịch sử; 74 mục UI PASS theo owner. Audit runtime đúng lượt manual vẫn PARTIAL/NOT VERIFIED độc lập, được owner chấp nhận.
- Baseline dọn hồ sơ: `feature/tv3-uc48-system-statistics`, HEAD `3931bf6d41c2f23e26ce73ba73a28bd3f9f008c5` cộng working tree đã nghiệm thu; giữ cả tracked/untracked.

## 2. Checklist 18 UC

| UC | Luồng chính | Nghiệm thu chức năng | Đã có / còn thiếu |
| --- | --- | --- | --- |
| UC15 — Xem hóa đơn cá nhân | [x] | [x] TV3 | CLOSED TV3; Invoice read, UI theo owner; T01. |
| UC16 — Tạo Review | [x] | [x] TV3 | CLOSED TV3; Review create/eligibility, synthetic QA entry; T03. |
| UC17 — Chat với chatbot | [ ] | [ ] | Còn provider, API/UI hỏi đáp, lưu message và xử lý lỗi. |
| UC34 — Tạo Invoice từ Booking | [x] | [x] TV3 | CLOSED TV3; create/commit/rollback/race và UI theo owner; T02. |
| UC35 — Chi tiết Invoice Host | [x] | [x] TV3 | CLOSED TV3; Host detail/items/ownership; T01. |
| UC36 — Cập nhật trạng thái thanh toán | [x] | [x] TV3 | CLOSED TV3; payment API/UI, F01/F02 đã đóng; tích hợp TV2 hoãn. |
| UC37 — Quản lý Invoice | [x] | [x] TV3 | CLOSED TV3; list/filter/search/page/no-write; T01. |
| UC38 — Host xem Review | [x] | [x] TV3 | CLOSED TV3; Host scope/summary và UI theo owner; T03. |
| UC39 — Dashboard Host | [ ] | [ ] | Có khung UI; còn KPI/kỳ/timezone/refund, query/API/UI. |
| UC44 — Admin xem toàn bộ Booking | [ ] | [ ] | Còn contract Booking TV2, query/API/UI, lọc/phân trang/quyền. |
| UC45 — Admin xem toàn bộ Invoice | [x] | [x] TV3 | CLOSED TV3; UC45-CLOSE-01 đã nghiệm thu ngày 2026-11-18. |
| UC47 — Dashboard Admin | [x] | [x] TV3 | CLOSED TV3; API/sáu KPI/charts/UI; screen reader được miễn. |
| UC48 — Thống kê hệ thống | [ ] | [ ] | CONTRACT-01/ENV-01/QUERY-01 đã nghiệm thu; chưa API/UI; CORE-01 chưa bắt đầu. |
| UC56 — Tạo InvoiceItem | [x] | [x] TV3 | CLOSED TV3 qua UC34; ROOM/SERVICE items nguyên tử; T02. |
| UC57 — Tính subtotal | [x] | [x] TV3 | CLOSED TV3 qua UC34; subtotal/snapshots/decimal; T02. |
| UC58 — Tính tổng thanh toán | [x] | [x] TV3 | CLOSED TV3 qua UC34; API fee/discount 0.00, nonzero chỉ calculator; T02. |
| UC60 — Kiểm tra điều kiện Review | [x] | [x] TV3 | CLOSED TV3 qua UC16; owner/COMPLETED/validation/unique/race; T03. |
| UC61 — Ngữ cảnh nội bộ chatbot | [ ] | [ ] | Còn allowlist, quyền truy cập và context service. |

UC56/57/58 qua UC34; UC60 qua UC16, không cần thêm API/UI riêng. Còn mở: **UC17/39/44/48/61**.

## 3. FAQ và Chat History

| Phần việc | Trạng thái / còn thiếu |
| --- | --- |
| FAQ Management | MỞ; còn contract, CRUD/quyền và API/UI quản lý |
| Chat History | MỞ; còn lưu/đọc lịch sử, ownership, API/UI và xử lý lỗi |

## 4. Hồ sơ nghiệm thu và giới hạn

| Nhóm | Checkpoint owner đã nghiệm thu | Hồ sơ chính |
| --- | --- | --- |
| Môi trường | TV3-AC-SCOPE-01, TV3-AC-ENV-01 | [Ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md) |
| Invoice read | INV-READ-QA-01, INV-READ-UI-01A/01B | Ma trận: IR01–IR06, UI01–UI16 |
| Invoice create | INV-CREATE-QA-01/RACE-01/UI-01A/UI-01B | Ma trận: IC01–IC07, CUI01–CUI18 |
| Review read | REV-READ-QA-01/UI-01A/UI-01B | Ma trận: RV04-read/RV05/RV07, RUI01–RUI20 |
| Review create | REV-CREATE-QA-01/RACE-01/UI-01A/UI-01B | Ma trận: RV01–RV04/RV06/RV07, RCUI01–RCUI20 |
| Đóng 10 UC | TV3-AC-CLOSE-01 — ACCEPTED | Ma trận: closure, evidence ledger và T01–T03 |
| Payment | UC36 — CLOSED TV3 | [Bàn giao UC36](UC36_TV2_HANDOVER.md), [contract](INVOICE_PAYMENT_WORKFLOW.md) |
| Admin Invoice | UC45 — CLOSED TV3 | [Bàn giao UC45](UC45_ACCEPTANCE_HANDOVER.md) |
| Admin Dashboard | UC47 — CLOSED TV3 | [Bàn giao UC47](UC47_ACCEPTANCE_HANDOVER.md) |
| Statistics nền tảng | UC48 CONTRACT/ENV/QUERY — ACCEPTED | [Contract UC48](UC48_STATISTICS_CONTRACT.md); chưa đóng UC48 |

- Raw artifacts local/ignored không đi theo Git; không sửa verdict, timestamp hoặc acceptance snapshot cũ. Xác nhận cuối theo chat/hồ sơ hiện hành.
- Không lấy SQLite skips, Node mocks, HTTP probes, SSR/Vite transform hoặc run01A cũ thay manual browser/runtime của01B.
- Review UI dùng synthetic parent trên DB fixture thật, chưa chứng minh CustomerBookingDetail production. Booking fixture chưa chứng minh checkout thật.
- UC58 giữ API discount/extra_fee `0.00`; nonzero chỉ calculator. Screen reader/WCAG không được suy thành PASS.
- UC36/45/47 và UC48 CONTRACT/ENV/QUERY không bị mở lại vì dọn tài liệu.

## 5. Dependency tích hợp còn mở

| ID | Trạng thái | Điều kiện còn thiếu |
| --- | --- | --- |
| T01 | **BLOCKED TV2** | Booking API/workflow production tích hợp; đúng owner/customer/room/property và Invoice liên kết thật |
| T02 | **BLOCKED TV2** | Checkout → COMPLETED, snapshots phòng/đêm/dịch vụ nhất quán, writer transaction/lock → Invoice; chưa có checkout production → Invoice evidence |
| T03 | **BLOCKED TV2** | GET Customer Booking/điểm mở Review thật trả đúng owner/status/ID/review; COMPLETED từ Backend, không mock hoặc suy ngày |

TV3 vẫn chịu trách nhiệm lỗi thuộc module mình. PR #9 đã được owner close; không coi PR #10 đã sửa findings/đủ điều kiện merge nếu chưa có review mới.

## 6. Việc tiếp theo và quy tắc cập nhật

1. Đề xuất Mini Plan **UC48-CORE-01**, chưa tự triển khai. QUERY-01 không đồng nghĩa đã có API/UI UC48.
2. Mỗi task tiếp theo phải chốt exact scope và được duyệt; tích hợp TV2 là task riêng sau bàn giao/kiểm contract/kiến trúc.
3. Chỉ giữ trạng thái hiện hành tại checklist; fixture/oracle/UI/evidence chung ở ma trận; contract riêng giữ trong hồ sơ chuyên biệt.

**TV3-DOC-CLEANUP-01:** đã gom hồ sơ theo scope 6 viết lại/4 gom rồi xóa/3 giữ nguyên; chỉ kiểm tài liệu, không QA hoặc production. Technical result ở báo cáo task; owner acceptance task này chờ review. Backup/hash/kiểm tra local: `.agent-workspace/tv3/doc-cleanup-01/`.
