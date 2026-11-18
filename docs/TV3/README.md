# Tài liệu TV3

Tiến độ theo UC nằm trong checklist; contract, nghiệm thu và bàn giao nằm ở các hồ sơ chuyên biệt.

| Tài liệu | Nội dung |
| --- | --- |
| [TV3_CHECKLIST.md](TV3_CHECKLIST.md) | 18 UC, FAQ/History, kết quả đã hoàn thành và phần còn thiếu |
| [BASELINE_HANDOVER.md](BASELINE_HANDOVER.md) | Baseline kiến trúc đã nghiệm thu, bàn giao TV1/TV2 và evidence refactor |
| [INVOICE_PAYMENT_WORKFLOW.md](INVOICE_PAYMENT_WORKFLOW.md) | Quy tắc và contract UC36 đang được triển khai trong source |
| [UC36_TV2_HANDOVER.md](UC36_TV2_HANDOVER.md) | Nghiệm thu UC36 phạm vi TV3, evidence và dependency TV2 đang hoãn |
| [UC45_ACCEPTANCE_HANDOVER.md](UC45_ACCEPTANCE_HANDOVER.md) | UC45 đã nghiệm thu phạm vi TV3; contract Admin invoice, kết quả API/UI, baseline, giới hạn và cách tái kiểm |

## Cách sử dụng

- Đọc [AGENTS.md](../../AGENTS.md), [kiến trúc](../../ARCHITECTURE.md), [phân công](../stayhub-team-work-assignment.md) và kiểm tra code/Git hiện tại trước task. AGENTS.local.md là hướng dẫn bổ sung local, không tự đi theo clone.
- Dùng [skills dự án](../../.agents/skills) theo nhu cầu: planning, Laravel, React, database, verification.
- Mini Plan và Task Result trình bày trên chat, chờ duyệt scope trước triển khai. Checklist chỉ ghi kết quả và việc còn thiếu.
- `[x]` triển khai khác `[x]` nghiệm thu chức năng. Refactor ACCEPTED không tự chứng minh end-to-end.
- UC36 đã nghiệm thu phạm vi TV3; tích hợp Booking TV2 hoãn đến khi nhánh phù hợp kiến trúc và có bàn giao được xác nhận.
- Evidence phải ghi ngày/engine/giới hạn. Artifact local ngoài Git cần được cung cấp riêng khi bàn giao; không tự coi log cũ là test vừa chạy.
- Nhánh dùng tiền tố `feature/`. Giữ dữ liệu, env và accepted working tree; không tự commit/push/merge.

## Hồ sơ đã gộp

BASELINE_PR_CHECKLIST được gộp vào BASELINE_HANDOVER; UC36_BROWSER_QA và UC36_INTEGRATION_PREPARATION được gộp vào UC36_TV2_HANDOVER. Các tên cũ không còn là tài liệu độc lập.
