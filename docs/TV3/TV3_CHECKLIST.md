# StayHub TV3 — Checklist

Cập nhật: 2026-11-17 (Asia/Bangkok). Phạm vi theo [phân công TV3](../stayhub-team-work-assignment.md): 18 UC, FAQ Management và Chat History.
Baseline hiện hành: `feature/tv3-uc36-payment-status`, HEAD `6bd6838` cộng working tree đã nghiệm thu; không dùng HEAD riêng thay code hiện hữu. [Kiến trúc](../../ARCHITECTURE.md) quy định cách triển khai.

## 1. Quy ước trạng thái

- `[x]` ở cột triển khai: đã có luồng chính; không đồng nghĩa nghiệm thu toàn chức năng.
- `[x]` ở cột nghiệm thu: owner đã nghiệm thu chức năng trong phạm vi ghi rõ. `[ ]` là chưa hoàn thành hoặc chưa đủ bằng chứng nghiệm thu.
- `NOT VERIFIED`: chưa có bằng chứng kiểm chứng cho phần đang nói tới. Kết quả test trong hồ sơ là lịch sử, không phải vừa chạy lại.
- Checklist chỉ lưu kết quả và việc còn thiếu. Mini Plan/Task Result trình bày trên chat.

## 2. Checklist 18 UC

| UC | Luồng chính | Nghiệm thu chức năng | Đã có / còn thiếu |
| --- | --- | --- | --- |
| UC15 — Xem hóa đơn cá nhân | [x] | [ ] | Customer list/detail API/UI; refactor ACCEPTED. Còn QA/nghiệm thu chức năng Invoice. |
| UC16 — Tạo Review | [x] | [ ] | API/modal và refactor ACCEPTED; fixture/browser đã kiểm. Còn điểm vào Booking thật TV2 và QA tích hợp. |
| UC17 — Chat với chatbot | [ ] | [ ] | Còn provider, API/UI gửi câu hỏi, lưu message và xử lý lỗi. |
| UC34 — Tạo Invoice từ Booking | [x] | [ ] | API/UI, snapshot, transaction, uniqueness đã có; refactor ACCEPTED. Còn nghiệm thu chức năng với Booking thật. |
| UC35 — Chi tiết Invoice Host | [x] | [ ] | API/UI, items và ownership đã có; refactor ACCEPTED. Còn nghiệm thu chức năng Invoice riêng. |
| UC36 — Cập nhật trạng thái thanh toán | [x] | [x] TV3 | API/UI và QA đã nghiệm thu; F01/F02 CLOSED. Tích hợp Booking TV2 hoãn riêng; xem [hồ sơ UC36](UC36_TV2_HANDOVER.md). |
| UC37 — Quản lý Invoice | [x] | [ ] | Host list/lọc/tìm kiếm/phân trang đã có; refactor ACCEPTED. Còn QA/nghiệm thu chức năng quản lý Invoice. |
| UC38 — Host xem Review | [x] | [ ] | API list/summary và UI có evidence browser/XAMPP; refactor ACCEPTED. Còn nghiệm thu chức năng. |
| UC39 — Dashboard Host | [ ] | [ ] | Có khung UI; còn chốt KPI/kỳ/timezone/refund và triển khai query/API/UI. |
| UC44 — Admin xem toàn bộ Booking | [ ] | [ ] | Còn contract Booking TV2, query/API/UI, lọc/phân trang và kiểm quyền. |
| UC45 — Admin xem toàn bộ Invoice | [ ] | [ ] | Còn Admin query/API/UI, lọc/phân trang và kiểm quyền. |
| UC47 — Dashboard Admin | [ ] | [ ] | Có khung UI; còn KPI, query/API/UI và kiểm thử. |
| UC48 — Thống kê hệ thống | [ ] | [ ] | Còn kỳ/chỉ tiêu/dimension, query/API/UI và đối chiếu số liệu độc lập. |
| UC56 — Tạo InvoiceItem | [x] | [ ] | ROOM/SERVICE cùng transaction tạo Invoice; refactor ACCEPTED. Còn nghiệm thu cùng UC34. |
| UC57 — Tính subtotal | [x] | [ ] | Calculator decimal từ snapshot đã tích hợp; refactor ACCEPTED. Còn đối chiếu AC chức năng với Booking thật. |
| UC58 — Tính tổng thanh toán | [x] | [ ] | Calculator có discount/extra_fee; create hiện dùng 0.00; refactor ACCEPTED. Còn nghiệm thu AC chức năng. |
| UC60 — Kiểm tra điều kiện Review | [x] | [ ] | COMPLETED/ownership/one-review đã có; refactor ACCEPTED. Còn race MySQL và workflow Booking thật. |
| UC61 — Ngữ cảnh nội bộ chatbot | [ ] | [ ] | Còn allowlist, quyền truy cập và context service. |

**Tổng:** 11/18 UC có luồng chính; 1/18 UC được nghiệm thu chức năng phạm vi TV3 (UC36); 10 UC còn gate chức năng dù refactor đã nghiệm thu; 7 UC chưa có luồng hoàn chỉnh.
UC56/57/58/60 nằm trong workflow cha, không cần API/UI riêng chỉ để đủ số lớp.

### UC36 — phần TV3 đã hoàn thành

- [x] UC36-INV-02: contract/quyền/transition/paid_at/no-op được nghiệm thu.
- [x] UC36-INV-03A/03B: Backend + Frontend xác nhận thanh toán và test mở rộng đã nghiệm thu.
- [x] UC36-QA-01: MySQL Docker transaction/outer commit/rollback/concurrency đã nghiệm thu.
- [x] UC36-QA-02A/02B: browser/session/CSRF/pending/recovery/cancellation đã nghiệm thu theo phạm vi kiểm.
- [x] UC36-FIX-01: sửa Host paid_at và hướng dẫn 419; F01/F02 CLOSED.
- [x] UC36-QA-03/04: hồi quy và bổ sung T04/T10 đã nghiệm thu; FK/timeout override chỉ trong harness được duyệt.
- [x] UC36-CLOSE-01: tổng hợp nghiệm thu và chuẩn bị bàn giao đã nghiệm thu.
- [x] UC36 hoàn thành trong phạm vi TV3 theo quyết định owner; Booking production TV2 chưa kiểm chứng và được hoãn riêng.

Contract tại [workflow](INVOICE_PAYMENT_WORKFLOW.md); 12 gate, evidence/giới hạn và dependency tại [hồ sơ UC36](UC36_TV2_HANDOVER.md).

## 3. Công việc ngoài số đếm UC

| Hạng mục | Luồng chính | Nghiệm thu | Còn thiếu |
| --- | --- | --- | --- |
| FAQ Management | [ ] | [ ] | Có migration/model nền; còn Admin CRUD API/UI, validation và kiểm quyền. |
| Chat History | [ ] | [ ] | Có migration/model nền; còn session/message list/detail API/UI, ownership, phân trang và retention. |

## 4. Checkpoint nền đã nghiệm thu

- [x] Invoice read/create: Repository/Validator/typed DTO/Mapper, Symfony adapters và Frontend domain API/service/mapper.
- [x] Review: Backend refactor/audit và Frontend REV-FE-01 đã nghiệm thu.
- [x] Auth/Profile và Public/Host Catalog trong phạm vi được giao đã nghiệm thu, bàn giao TV1 duy trì.
- [x] CLEANUP-ARCH-01 và HANDOVER-BASELINE-01 đã nghiệm thu; owner đã merge đợt refactor vào dev.

Phạm vi, checkpoint IDs, evidence E1–E19 và exclusions nằm trong [bàn giao baseline](BASELINE_HANDOVER.md).

## 5. Phần còn thiếu và dependency

| Phạm vi | Trạng thái / việc còn thiếu |
| --- | --- |
| UC36 ↔ TV2 | HOÃN tích hợp: TV2 còn phát triển và chưa đưa nhánh Booking về kiến trúc mới. Không lấy/merge code hiện tại; kiểm lại SHA/contract khi owner yêu cầu tiếp. UC36-INT-PREP-01 đã khảo sát, nghiệm thu riêng còn PENDING; PREP-02/INT-01 chưa thực hiện. |
| UC15/34/35/37/56/57/58 | QA/nghiệm thu chức năng Invoice và Booking thật còn mở; hồi quy UC36 không tự đóng các UC này. |
| UC16/38/60 | Nghiệm thu các màn hình Review, điểm vào Booking thật và race MySQL còn mở; giữ evidence REV-FE-01. |
| UC39/47/48 | Chốt KPI, trạng thái Invoice tính doanh thu, kỳ/timezone và refund trước khi viết query. |
| UC44 | Phụ thuộc contract/workflow Booking TV2; TV3 phụ trách giám sát Admin. |
| UC17/61, FAQ, History | Chốt provider/retention/quyền context; chatbot V1 chỉ tư vấn, không mutation Booking/thanh toán. |
| Hồ sơ nền | SETUP-01 và VAL-STRUCT-01 còn thiếu nghiệm thu riêng; MAP-01 còn PARTIAL về hồ sơ. Không mở lại refactor đã nghiệm thu hoặc tự đánh PASS. |
| Bàn giao TV1/TV2 | Catalog còn lại (gồm UC21) thuộc TV1; Booking/Operations thuộc TV2. Writers Booking/Maintenance, DELETE Room và uniqueness Room ở DB giữ theo bàn giao baseline. |
| Môi trường kiểm chứng | UC36 dùng MySQL Docker/schema test riêng; giữ DB stayhub, env, datadir XAMPP và backup. Suite SQLite-only giữ nguyên guard, không dùng SQLite làm evidence MySQL. |

Không lưu kế hoạch đang làm hoặc nhật ký chạy lệnh trong checklist. Việc phát triển tiếp cần scope/approval riêng theo quy trình dự án.
