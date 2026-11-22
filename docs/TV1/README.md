# StayHub — Báo cáo TV1

Thư mục này lưu kết quả khảo sát và báo cáo triển khai của **TV1 — Identity & Property Catalog** theo mục 3 của [phân công nhóm](../stayhub-team-work-assignment.md).

## Mục lục

| Tài liệu | Nội dung | Trạng thái |
| --- | --- | --- |
| [00-PROJECT-AUDIT.md](00-PROJECT-AUDIT.md) | Audit ngày 28/10/2026: kiến trúc thực tế, 20 UC, API/UI, bảo mật, dependencies và roadmap | Đã khảo sát và kiểm chứng baseline; chưa triển khai UC |
| [UC02-REPORT.md](UC02-REPORT.md) | UC2: Sanctum session, login/logout, account-status guard, rate limit và React auth flow | Code và automated tests hoàn thành; còn nghiệm thu trình duyệt |
| [UC01-REPORT.md](UC01-REPORT.md) | UC1: đăng ký Customer, guest-only, validation, limiter và form đăng ký thật | Backend/frontend và automated tests hoàn thành; còn nghiệm thu browser/MySQL race |
| [UC03-REPORT.md](UC03-REPORT.md) | UC3: hồ sơ cá nhân, cập nhật contact, đổi mật khẩu và đồng bộ auth state | Backend/frontend và automated tests hoàn thành; còn nghiệm thu trình duyệt |
| [UC04-REPORT.md](UC04-REPORT.md) | UC4: danh sách cơ sở public, phân trang server và card dữ liệu thật | Code/tests hoàn thành cùng UC5; còn nghiệm thu browser/MySQL |
| [UC05-REPORT.md](UC05-REPORT.md) | UC5: chi tiết public, phòng/ảnh/tiện nghi và review integration | Code/tests hoàn thành cùng UC4; còn nghiệm thu browser/MySQL và dependency Review TV3 |
| [UC07-REPORT.md](UC07-REPORT.md) | UC7: lọc phòng theo giá, sức chứa, loại phòng/tiện nghi và đánh giá cơ sở; search bar, sidebar cuộn riêng, API thật | UC7/frontend/browser đạt; full backend còn 3 lỗi nền tái hiện ở HEAD trước UC7 |
| [UC18-REPORT.md](UC18-REPORT.md) | UC18-01 audit; UC18-02 cleanup; UI-01 chi tiết, UI-02 danh sách/form Host | Audit/fix và UI checks PASS; owner chấp nhận UI-02 list/form/mobile/keyboard; UI-01 browser và runtime gaps còn mở, chưa chốt toàn UC18 |
| [UC19-REPORT.md](UC19-REPORT.md) | UC19-01 audit/QA; UI-01; FINAL chốt phạm vi | Nghiệm thu CRUD/UI đã kiểm, technical checks và owner browser PASS; concurrency/collation MariaDB và browser negative cases còn NOT VERIFIED, chưa CLOSED toàn runtime |
| [UC20-REPORT.md](UC20-REPORT.md) | UC20-01 audit/QA, UI-01 danh sách/form thêm, UI-02 chi tiết/form sửa và đổi trạng thái, FINAL | Nghiệm thu luồng quản lý phòng và UI đã kiểm với Host thật. Kiểm tra kỹ thuật đạt. Thứ tự khóa TV2, collation MariaDB và các ca lỗi runtime còn mở |
| [UC21-REPORT.md](UC21-REPORT.md) | UC21-01/02, QA và QA-DB-01/02 | Chức năng/owner browser PASS; MariaDB 10.11 smoke, 8 concurrency cases và cleanup PASS; chờ review bàn giao |
| [UC22-REPORT.md](UC22-REPORT.md) | UC22-01 xem/gán, UC22-02 gỡ và UC22-FINAL | Đã nghiệm thu xem/gán/gỡ/gán lại, kể cả gỡ tiện nghi đã ngưng. Gán/gỡ đồng thời trên MySQL/MariaDB và mất phản hồi với Laravel thật còn mở |
| [UC51-REPORT.md](UC51-REPORT.md) | UC51: query kiểm tra lịch bảo trì theo khoảng lưu trú, contract bàn giao TV2 | Lõi query và automated tests PASS; chưa tích hợp Availability/Booking TV2 |
| [UC52-REPORT.md](UC52-REPORT.md) | UC52: validator kiểm tra sức chứa một phòng, contract bàn giao TV2 và unit tests | Lõi validator và automated tests PASS; chưa tích hợp Availability/Booking TV2 |
| [UC40-REPORT.md](UC40-REPORT.md) | UC40-01/02: Admin xem danh sách và chi tiết Customer, tìm kiếm/lọc/phân trang, dialog và URL state | Hai task TECHNICAL PASS: backend/Node/lint/build và browser SQLite fixture; chờ developer review, chưa kiểm chứng runtime MySQL |
| [UC41-REPORT.md](UC41-REPORT.md) | UC41-01: Admin tra cứu Host, lọc/phân trang và đếm cơ sở mọi trạng thái, URL state | UC41-01 TECHNICAL PASS: automated checks và browser SQLite fixture; UC41-02 chưa triển khai, chờ developer review |

## Quy ước

- Nhận định luôn gắn với branch/HEAD, file và dòng hoặc tên hàm. Số dòng tham chiếu thuộc snapshot audit, có thể thay đổi sau này.
- Phân biệt **đã có code**, **đã chạy kiểm chứng**, **mới có tài liệu**, **stub/mock**, **chưa có**, **chưa xác minh**. Có model hoặc test của module khác không đồng nghĩa UC đã hoàn thành.
- Kết quả lệnh phải ghi command, exit code và giới hạn. Không ghi PASS cho test chưa chạy; không dùng số test lịch sử làm baseline hiện tại.
- Mỗi UC được giao riêng, trên branch do developer chuẩn bị, theo tên gợi ý `feature/tv1-ucXX-ten-chuc-nang`. Không tự tạo/chuyển branch hoặc tích hợp dependency.
- Ngoại lệ theo yêu cầu ngày 29/10/2026: UC4 và UC5 triển khai chung trên `feature/tv1-uc04-uc05-property-catalog`, có hai báo cáo nghiệm thu riêng.
- Báo cáo triển khai sau này dùng `UCXX-REPORT.md`: mục tiêu, trạng thái, branch/HEAD, tài liệu đã đọc, phạm vi, file đổi, backend/frontend, contract, business rules, test thực tế, hướng dẫn thử, dependencies và phần còn thiếu.
- UC có UI cần đủ backend/frontend và kiểm thử tương ứng. UC51/UC52 nghiệm thu qua hàm/query, contract tích hợp và test, không bắt buộc UI/endpoint riêng.
- CRUD lớn chia bước 30–90 phút trong cùng UC/branch. Đọc lại `AGENTS.md`, audit và code; trình bày mini plan và dừng review trước triển khai, trừ khi prompt mới cho phép tiếp tục rõ ràng.
- Không đưa secret, dữ liệu cá nhân, log nhạy cảm hay toàn bộ mã nguồn vào báo cáo. Giữ các báo cáo cũ khi bổ sung kết quả mới.

Audit không cho phép tự bắt đầu UC kế tiếp. Các đường dẫn/contract ghi **đề xuất** chưa phải API đã tồn tại.
