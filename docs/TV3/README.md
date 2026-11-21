# Tài liệu TV3

**14/18 UC CLOSED phạm vi TV3**, không phải end-to-end toàn hệ thống. Đọc checklist để biết tiến độ, ma trận để truy evidence/fixture/UI; contract riêng ở hồ sơ tương ứng.

**UC48 CLOSED TV3:** owner đã nghiệm thu UC48-CLOSE-01 trên chat ngày 2026-11-20, đủ 8/8 task và hai task bổ sung ADMIN-CSS-NAMING-01/ADMIN-VND-DISPLAY-01. Còn UC17/39/44/61; FAQ Management và Chat History ngoài số đếm 18 UC. Chưa triển khai UC tiếp theo.

| File giữ lại | Nội dung |
| --- | --- |
| [TV3_CHECKLIST.md](TV3_CHECKLIST.md) | 18UC, FAQ/History, trạng thái hiện hành, việc còn lại và T01–T03 |
| [TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md) | Nghiệm thu10UC; AC, run ledger,74UIchecks,fixture/oracle và hướng dẫn QA chung |
| [BASELINE_HANDOVER.md](BASELINE_HANDOVER.md) | Refactor đã nghiệm thu, ranh giới owner/tương thích, evidenceindex lịch sử |
| [INVOICE_PAYMENT_WORKFLOW.md](INVOICE_PAYMENT_WORKFLOW.md) | Contract UC36 đang dùng; giữ nguyên |
| [UC36_TV2_HANDOVER.md](UC36_TV2_HANDOVER.md) | UC36 CLOSED TV3; gates/evidence/PREP/tích hợp TV2 hoãn |
| [UC45_ACCEPTANCE_HANDOVER.md](UC45_ACCEPTANCE_HANDOVER.md) | UC45 đã nghiệm thu; giữ nguyên |
| [UC47_ACCEPTANCE_HANDOVER.md](UC47_ACCEPTANCE_HANDOVER.md) | DashboardAdmin đã nghiệm thu, contract/evidence và ngoại lệ screenreader |
| [UC48_STATISTICS_CONTRACT.md](UC48_STATISTICS_CONTRACT.md) | Contract V1 đã triển khai API/UI; có amendment VND 0 phần lẻ. Các trạng thái mục tiêu ban đầu giữ như lịch sử |
| [UC48_DATA_QA.md](UC48_DATA_QA.md) | Oracle/fixtures, MySQL và hồi quy QA-DATA đã nghiệm thu; không thay browser evidence |
| [UC48_UI_QA.md](UC48_UI_QA.md) | Manual M01–M12 trên dev được owner chấp nhận với giới hạn; audit PARTIAL/runner FAIL và cleanup PASS giữ nguyên |
| [UC48_ACCEPTANCE_HANDOVER.md](UC48_ACCEPTANCE_HANDOVER.md) | UC48 CLOSED TV3; implementation/contract, AC, evidence ledger, owner acceptance và giới hạn được bảo toàn |

Bảng trên có 11 hồ sơ cộng README này: **12 file**. Checklist lưu trạng thái hiện hành và kết quả task; Mini Plan/Task Result cũng được báo trên chat theo scope đã duyệt. Khi hồ sơ lịch sử còn ghi PENDING/chưa triển khai, tra trạng thái mới tại checklist và handover, không sửa ngược verdict gốc.

## Evidence và bảo toàn

- UI01–16/CUI01–18/RUI01–20/RCUI01–20 PASS theo owner; audit đúng lượt manual PARTIAL/NOTVERIFIED độc lập đã được chấp nhận. Không dùng run01A/SQLite/Node/SSR thay browsermanual.
- T01–T03 vẫn **BLOCKED TV2**. Refactor/fixture/synthetic Review entry không chứng minh Bookingproduction hoặc integrationPASS.
- Artifacts local/ignored không đi theo Git; giữ ngày/engine/verdict/nguồn gốc, không coi log cũ là QA mới. Backup dọn hồ sơ: `.agent-workspace/tv3/doc-cleanup-01/`.
- UC48: không dùng manual dev như manual trên schema QA riêng. Naming/VND đã được owner nghiệm thu nhưng chưa có browser visual mới; API/raw decimal vẫn scale 2, UI làm tròn half-up đến đồng và không có phần lẻ. CLOSE chỉ kiểm tài liệu, không chạy lại suites hoặc mở QA.
- Đọc [AGENTS.md](../../AGENTS.md), [kiến trúc](../../ARCHITECTURE.md), [phân công](../stayhub-team-work-assignment.md) và [skills](../../.agents/skills). Giữ accepted tracked/untracked/env/data; không tự commit/push/merge.

## Hồ sơ đã gom

| File không còn độc lập | Nơi tra cứu hiện hành |
| --- | --- |
| INVOICE_READ_UI_ACCEPTANCE.md | [Ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#9-invoice-read--uc153537), nhóm Invoice read: fixture/oracle, UI01–UI16, evidence01A/01B |
| INVOICE_CREATE_UI_ACCEPTANCE.md | [Ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#10-invoice-create--uc34565758), nhóm Invoice create: fixture/oracle, CUI01–CUI18, mutation/recovery/evidence |
| REVIEW_READ_UI_ACCEPTANCE.md | [Ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#11-review-read--uc38), nhóm Review read: summaryoracle, RUI01–RUI20, evidence |
| REVIEW_CREATE_UI_ACCEPTANCE.md | [Ma trận](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#12-review-create--uc1660), nhóm Review create: syntheticentry, RCUI01–RCUI20, UNKNOWN/evidence |

Các đợt trước: BASELINE_PR_CHECKLIST đã gom vào BASELINE_HANDOVER; UC36_BROWSER_QA/UC36_INTEGRATION_PREPARATION đã gom vào UC36_TV2_HANDOVER. Không khôi phục thành hồ sơ lặp.
