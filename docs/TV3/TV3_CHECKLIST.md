# StayHub TV3 — Tiến độ và việc còn lại

Cập nhật: 2026-11-21 (Asia/Bangkok). **15/18 UC CLOSED TV3; còn 3 UC.**
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
| UC17 — Chat với chatbot | [ ] | [ ] | Còn provider, API/UI hỏi đáp, lưu message và xử lý lỗi |
| UC34 — Tạo Invoice từ Booking | [x] | [x] TV3 | Create/commit/rollback/race; T02 |
| UC35 — Chi tiết Invoice Host | [x] | [x] TV3 | Detail/items/ownership; T01 |
| UC36 — Cập nhật trạng thái thanh toán | [x] | [x] TV3 | Payment API/UI; F01/F02 đã đóng, tích hợp TV2 hoãn |
| UC37 — Quản lý Invoice | [x] | [x] TV3 | List/filter/search/pagination/no-write; T01 |
| UC38 — Host xem Review | [x] | [x] TV3 | Scope/summary/UI; T03 |
| UC39 — Dashboard Host | [x] | [x] TV3 | CLOSED ngày 2026-11-21; 10 task đã nghiệm thu, giữ giới hạn QA-UI |
| UC44 — Admin xem toàn bộ Booking | [ ] | [ ] | Còn contract TV2, query/API/UI, filter/pagination/authorization |
| UC45 — Admin xem toàn bộ Invoice | [x] | [x] TV3 | CLOSE-01 nghiệm thu ngày 2026-11-18 |
| UC47 — Dashboard Admin | [x] | [x] TV3 | Sáu KPI/charts/UI; screen reader được miễn |
| UC48 — Thống kê hệ thống | [x] | [x] TV3 | CLOSED ngày 2026-11-20; 8 task + CSS/VND, giữ giới hạn manual dev |
| UC56 — Tạo InvoiceItem | [x] | [x] TV3 | Qua UC34; ROOM/SERVICE items nguyên tử, T02 |
| UC57 — Tính subtotal | [x] | [x] TV3 | Qua UC34; snapshots/decimal, T02 |
| UC58 — Tính tổng thanh toán | [x] | [x] TV3 | Qua UC34; API discount/extra_fee=0.00, nonzero chỉ calculator, T02 |
| UC60 — Kiểm tra điều kiện Review | [x] | [x] TV3 | Qua UC16; ownership/COMPLETED/validation/unique/race, T03 |
| UC61 — Ngữ cảnh nội bộ chatbot | [ ] | [ ] | Còn allowlist, authorization và context service |

UC56/57/58 qua UC34; UC60 qua UC16, không cần API/UI riêng. Còn mở: **UC17/44/61**.

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
| Admin Invoice | UC45 CLOSED TV3 | [Handover UC45](UC45_ACCEPTANCE_HANDOVER.md) |
| Admin Dashboard | UC47 CLOSED TV3 | [Handover UC47](UC47_ACCEPTANCE_HANDOVER.md) |
| Statistics | UC48 CLOSED TV3; CSS/VND cũng đã nghiệm thu | [Handover UC48](UC48_ACCEPTANCE_HANDOVER.md) |

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

1. **TV3-DOC-CLEANUP-02A đã được owner nghiệm thu ngày 2026-11-21**; **02B** đã được duyệt và thực hiện theo scope 7 file; chờ nghiệm thu kết quả tài liệu.
2. UC44-CONTRACT-01 là task chức năng được đề xuất sau dọn tài liệu; chưa được triển khai. UC17/61/FAQ/Chat History cũng chưa mở scope mới.
3. Mỗi task cần Mini Plan/exact scope, owner duyệt rồi mới thực hiện; code/QA/tích hợp TV2 không nằm trong task tài liệu.
4. Checklist chỉ cập nhật trạng thái và liên kết kết quả; contract/evidence đặt trong hồ sơ chức năng, không nối thêm báo cáo nhiều trang.
5. Giữ accepted tracked/untracked, env và dữ liệu dev; không tự commit/push/merge/rebase.

## 8. Checkpoint dọn tài liệu

| Task | Trạng thái | Phạm vi / evidence |
| --- | --- | --- |
| TV3-DOC-CLEANUP-01 | Technical result đã báo; owner acceptance riêng chưa ghi nhận | Đợt trước: 6 viết lại/4 gom-xóa/3 giữ; `.agent-workspace/tv3/doc-cleanup-01/` |
| TV3-DOC-CLEANUP-02A | TECHNICAL PASS tài liệu; OWNER ACCEPTED ngày 2026-11-21 | 4 viết lại + 6 gom-xóa; backup/content-map/verification tại đường dẫn dưới |
| TV3-DOC-CLEANUP-02B | TECHNICAL PASS tài liệu; chờ owner nghiệm thu | 5 hồ sơ biên tập + README/checklist; DCB01–07 PASS kiểm tài liệu, giữ 10 file; artifacts `doc-cleanup-02b/52813b1cc81d4bd3886c3312f411fe2f/` |

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

Skills áp dụng: [Planning](../../.agents/skills/stayhub-task-planning/SKILL.md) cho scope/baseline; [Verification](../../.agents/skills/stayhub-verification/SKILL.md) cho coverage/evidence/static checks. Technical PASS trên là kết quả kiểm tài liệu, không phải test chức năng chạy mới; owner đã nghiệm thu 02A; 02B chờ nghiệm thu. DCB01–07 đối chiếu scope/hash, content-map, AC/gate/oracle/ledger, lịch sử/hiện trạng, links/anchors và độ gọn tại verification-summary run 02B; không QA/DB. Còn một link hỏng có sẵn ngoài scope: `docs/TV2/BASELINE_HANDOVER.md:7` → `../TV3/BASELINE_PR_CHECKLIST.md`; hồ sơ thay thế là baseline TV3, không sửa file TV2 trong task này.
