# Tài liệu TV3 — Hướng dẫn tra cứu

Cập nhật 2026-11-21 (Asia/Bangkok). **15/18 UC CLOSED TV3**; còn UC17/44/61. FAQ Management và Chat History nằm ngoài số đếm 18 UC. Trạng thái này không phải nghiệm thu end-to-end toàn hệ thống.

## Đọc tài liệu nào?

| Nhu cầu | Hồ sơ chính |
| --- | --- |
| Tiến độ, việc còn lại và dependency | [TV3_CHECKLIST.md](TV3_CHECKLIST.md) |
| Baseline kiến trúc/refactor và tương thích liên nhóm | [BASELINE_HANDOVER.md](BASELINE_HANDOVER.md) |
| Invoice/Review: 10 UC, AC, fixture/oracle, 74 mục UI và ledger | [TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md) |
| Contract thanh toán Invoice hiện hành | [INVOICE_PAYMENT_WORKFLOW.md](INVOICE_PAYMENT_WORKFLOW.md) |
| UC36: nghiệm thu, 12 gate và tích hợp TV2 | [UC36_TV2_HANDOVER.md](UC36_TV2_HANDOVER.md) |
| UC39: Dashboard Host, contract, QA và giới hạn | [UC39_ACCEPTANCE_HANDOVER.md](UC39_ACCEPTANCE_HANDOVER.md) |
| UC45: Admin Invoice Monitoring | [UC45_ACCEPTANCE_HANDOVER.md](UC45_ACCEPTANCE_HANDOVER.md) |
| UC47: Admin Dashboard | [UC47_ACCEPTANCE_HANDOVER.md](UC47_ACCEPTANCE_HANDOVER.md) |
| UC48: Admin Statistics, contract, QA và giới hạn | [UC48_ACCEPTANCE_HANDOVER.md](UC48_ACCEPTANCE_HANDOVER.md) |

Thư mục có **10 file**, gồm README này. Checklist chỉ giữ hiện trạng; contract/AC/evidence riêng nằm tại hồ sơ chức năng. Không ghi lại toàn bộ Mini Plan/Task Result vào checklist.

## Hồ sơ đã hợp nhất

| Đường dẫn cũ trong docs/TV3 | Nơi tra cứu thay thế |
| --- | --- |
| UC39_HOST_DASHBOARD_CONTRACT.md | [UC39: contract và HTTP](UC39_ACCEPTANCE_HANDOVER.md#contract-hiện-hành) |
| UC39_DATA_QA.md | [UC39: oracle và DATA QA](UC39_ACCEPTANCE_HANDOVER.md#oracle-và-data-qa) |
| UC39_UI_QA.md | [UC39: HTTP và browser QA](UC39_ACCEPTANCE_HANDOVER.md#http-và-browser-qa) |
| UC48_STATISTICS_CONTRACT.md | [UC48: contract và HTTP](UC48_ACCEPTANCE_HANDOVER.md#contract-hiện-hành) |
| UC48_DATA_QA.md | [UC48: oracle và DATA QA](UC48_ACCEPTANCE_HANDOVER.md#oracle-và-data-qa) |
| UC48_UI_QA.md | [UC48: HTTP và manual UI](UC48_ACCEPTANCE_HANDOVER.md#http-và-manual-ui) |
| Checklist cũ mục 7–18 / 19–28 | [Ledger UC48](UC48_ACCEPTANCE_HANDOVER.md#ledger-và-hồi-quy) / [ledger UC39](UC39_ACCEPTANCE_HANDOVER.md#ledger-và-hồi-quy) |

Lịch sử gom trước đó: INVOICE_READ_UI_ACCEPTANCE → [ma trận §9](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#9-invoice-read--uc153537); INVOICE_CREATE_UI_ACCEPTANCE → [§10](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#10-invoice-create--uc34565758); REVIEW_READ_UI_ACCEPTANCE → [§11](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#11-review-read--uc38); REVIEW_CREATE_UI_ACCEPTANCE → [§12](TV3_FUNCTIONAL_ACCEPTANCE_MATRIX.md#12-review-create--uc1660). BASELINE_PR_CHECKLIST → baseline; UC36_BROWSER_QA/UC36_INTEGRATION_PREPARATION → handover UC36. Không khôi phục các hồ sơ lặp.

## Quy tắc sử dụng evidence

- Owner acceptance tách khỏi technical verdict; giữ FAIL/PARTIAL/NOT VERIFIED và ngày/engine/run gốc. Không dùng SQLite skips, Node mocks, SSR hoặc build thay browser/MySQL.
- T01–T03 vẫn **BLOCKED TV2**. Reporting fixtures và synthetic Review entry không chứng minh Booking production end-to-end.
- Artifacts dưới `.agent-workspace/tv3/` là local/ignored, không theo Git. Đường dẫn/run lịch sử không phải môi trường đang chạy; tái QA cần task được duyệt riêng.
- Giữ accepted tracked/untracked, env và dữ liệu dev; không tự commit/push/merge. Đọc [AGENTS.md](../../AGENTS.md), [ARCHITECTURE.md](../../ARCHITECTURE.md) và [phân công](../stayhub-team-work-assignment.md).
- **02A OWNER ACCEPTED ngày 2026-11-21**: gom 6 hồ sơ vào UC39/48; backup/content-map/verification tại `.agent-workspace/tv3/doc-cleanup-02a/2db97ded3e67452a99c8fc0e37826c6e/`.
- **02B**: biên tập baseline, ma trận Invoice/Review và handover UC36/45/47; đồng bộ README/checklist. Giữ 10 file, giữ payment contract và handover UC39/48. Evidence kiểm tài liệu tại `.agent-workspace/tv3/doc-cleanup-02b/52813b1cc81d4bd3886c3312f411fe2f/`; kết quả/owner acceptance theo checklist.
- Nhận định về TV1/TV2 và từ “source khớp” trong ledger áp dụng SHA/ngày checkpoint gốc. Khi tích hợp lại phải kiểm source/contract hiện tại; route tồn tại không tự đóng dependency.
