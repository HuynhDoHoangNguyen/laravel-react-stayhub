# StayHub — Báo cáo TV1

Thư mục này lưu kết quả khảo sát và báo cáo triển khai của **TV1 — Identity & Property Catalog** theo mục 3 của [phân công nhóm](../stayhub-team-work-assignment.md).

## Mục lục

| Tài liệu | Nội dung | Trạng thái |
| --- | --- | --- |
| [00-PROJECT-AUDIT.md](00-PROJECT-AUDIT.md) | Audit ngày 28/10/2026: kiến trúc thực tế, 20 UC, API/UI, bảo mật, dependencies và roadmap | Đã khảo sát và kiểm chứng baseline; chưa triển khai UC |
| [UC02-REPORT.md](UC02-REPORT.md) | UC2: Sanctum session, login/logout, account-status guard, rate limit và React auth flow | Code và automated tests hoàn thành; còn nghiệm thu trình duyệt |

## Quy ước

- Nhận định luôn gắn với branch/HEAD, file và dòng hoặc tên hàm. Số dòng tham chiếu thuộc snapshot audit, có thể thay đổi sau này.
- Phân biệt **đã có code**, **đã chạy kiểm chứng**, **mới có tài liệu**, **stub/mock**, **chưa có**, **chưa xác minh**. Có model hoặc test của module khác không đồng nghĩa UC đã hoàn thành.
- Kết quả lệnh phải ghi command, exit code và giới hạn. Không ghi PASS cho test chưa chạy; không dùng số test lịch sử làm baseline hiện tại.
- Mỗi UC được giao riêng, trên branch do developer chuẩn bị, theo tên gợi ý `feature/tv1-ucXX-ten-chuc-nang`. Không tự tạo/chuyển branch hoặc tích hợp dependency.
- Báo cáo triển khai sau này dùng `UCXX-REPORT.md`: mục tiêu, trạng thái, branch/HEAD, tài liệu đã đọc, phạm vi, file đổi, backend/frontend, contract, business rules, test thực tế, hướng dẫn thử, dependencies và phần còn thiếu.
- UC có UI cần đủ backend/frontend và kiểm thử tương ứng. UC51/UC52 nghiệm thu qua hàm/query, contract tích hợp và test, không bắt buộc UI/endpoint riêng.
- CRUD lớn chia bước 30–90 phút trong cùng UC/branch. Đọc lại `AGENTS.md`, audit và code; trình bày mini plan và dừng review trước triển khai, trừ khi prompt mới cho phép tiếp tục rõ ràng.
- Không đưa secret, dữ liệu cá nhân, log nhạy cảm hay toàn bộ mã nguồn vào báo cáo. Giữ các báo cáo cũ khi bổ sung kết quả mới.

Audit không cho phép tự bắt đầu UC kế tiếp. Các đường dẫn/contract ghi **đề xuất** chưa phải API đã tồn tại.
