# Quy trình làm việc TV3

## Bắt đầu chat

Gửi: “Đọc AGENTS.md, AGENTS.local.md và docs/TV3/TV3_CHECKLIST.md. Tiếp tục task …; kiểm tra code hiện tại, trình bày Mini Plan chi tiết trên chat và chờ tôi duyệt.”

[Local rules](../../AGENTS.local.md) chỉ áp dụng cho task TV3 được yêu cầu. AGENTS.local.md không tự nạp mặc định; AGENTS.md hiện được giữ nguyên theo yêu cầu owner. File AGENTS.md hiện mang nội dung TV3 MASTER PROMPT: skill chung không vô hiệu hóa nó. Nhóm cần thống nhất một task riêng nếu muốn chuyển AGENTS.md thành quy tắc chung.

## Tài liệu và công cụ

| Thành phần | Vai trò |
|---|---|
| [Checklist](TV3_CHECKLIST.md) | Nguồn tiến độ: code có sẵn, khoảng trống, dependency, kiểm chứng, nghiệm thu |
| AGENTS.md / AGENTS.local.md | Instructions: phạm vi, quy trình và điều kiện hoàn thành |
| [.agents/skills](../../.agents/skills) | Cách thực hiện từng loại công việc, dùng chung các thành viên |
| Mini Plan / Task Result | Báo chi tiết trên chat; không tự tạo file riêng |
| Hook | Hành động tự chạy theo sự kiện của công cụ; chưa cài trong task này |
| Harness | Code chạy ứng dụng/test và thu kết quả; browser harness StayHub chưa được xây trong task này |
| Evidence | Log/screenshot/kết quả thực thi cần thiết; đặt riêng theo task/run khi phát sinh |

“Rules” ở đây là quy tắc trong Markdown, không phải cấu hình policy shell `.rules`. React hook như useAuth/useEffect cũng khác hook tự động hóa của agent/Git.

## Năm skill chung

- `$stayhub-task-planning`: xác định scope, owner, dependency, AC và Mini Plan.
- `$stayhub-laravel`: triển khai/review backend theo kiến trúc hiện có.
- `$stayhub-react`: triển khai/review giao diện và hợp đồng HTTP.
- `$stayhub-database`: migration/model/schema, tiền, constraints và dữ liệu test an toàn.
- `$stayhub-verification`: kiểm chứng, đánh giá AC và Task Result.

Codex có thể tự chọn theo description hoặc người dùng gọi rõ tên. Tự chọn không bảo đảm mọi lần đều kích hoạt; instructions vẫn quy định các bước bắt buộc. Nếu skill mới chưa xuất hiện, mở chat/session mới hoặc yêu cầu đọc đúng SKILL.md. Không khai báo tự nạp đã PASS nếu chưa quan sát host thực tế.

## Lưu trữ và dùng chung

Checklist và skills có thể version-control. `.gitignore` hiện bỏ qua cả `AGENTS.local.md`: file local rules vẫn có trên máy nhưng không tự đi theo clone/checkout của đồng đội; cần cung cấp riêng nếu muốn chia sẻ. Evidence dưới `.agent-workspace/tv3/` là local, không có nghĩa đã được lưu cùng Git; trước bàn giao cần bảo đảm người nghiệm thu truy cập được bằng chứng. Không tự tạo thư mục run hoặc RESULT.json rỗng để giả thành đã chạy.

Nhánh dùng `feature/`; task thiết lập là `feature/stayhub-agent-workflow`. Ghi kết quả từng task vào checklist nhưng giữ phần plan/result chi tiết trên chat. Trước khi đóng một UC phải đủ các task con bắt buộc, kể cả tích hợp UI và các gate còn mở.
