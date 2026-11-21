# UC48-QA-DATA-01 — Đối soát dữ liệu

Ngày thực hiện: 2026-11-20 (Asia/Bangkok). Owner: TV3.
Mini Plan và scope 4 file đã được owner duyệt trên chat.
Baseline: `feature/tv3-uc48-system-statistics`, HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng working tree đã nghiệm thu.
Contract: [UC48 V1](UC48_STATISTICS_CONTRACT.md). Trạng thái nghiệm thu owner: **PENDING**.

## 1. Phạm vi và cách đối soát

- Thêm 4 test tại [Uc48StatisticsDataAcceptanceMySqlTest.php](../../backend/tests/Feature/Statistics/Uc48StatisticsDataAcceptanceMySqlTest.php), gọi `GET /api/admin/statistics` qua Laravel kernel với `actingAs`, Repository và MySQL thật.
- Tái sử dụng guard, isolated TestCase, fixture helpers và test ENV/QUERY/CORE. Không chỉnh production Backend/Frontend, migration, seeder, env hoặc module TV1/TV2.
- Dữ liệu là synthetic reporting snapshots ghi trực tiếp trong transaction schema QA. Chúng không chứng minh production Booking/payment workflows hoặc session/CSRF/browser đã được kiểm thử.
- Kết quả kỳ vọng là ledger tường minh và phép cộng BCMath độc lập; không dùng lại Repository/QueryService để tạo oracle.
- Cutoff test cố định `2026-11-19T05:00:00Z`, chủ đích khác ngày chạy để tái lập kết quả.

## 2. Ledger nhiều Host/Property

Hai Property thuộc hai Host: một Host BLOCKED/Property BLOCKED/Room MAINTENANCE và một Host/Property/Room ACTIVE. Customer INACTIVE. Sáu Booking phân bổ luân phiên giữa hai Property, ngày lưu trú tháng 12, ngày tạo như bảng dưới.

| Booking | Tháng tạo địa phương | Trạng thái Booking | Invoice hiện tại | paid_at UTC | total_amount | Doanh thu được tính |
| --- | --- | --- | --- | --- | --- | --- |
| B1 | 2026-10 | PENDING | PAID | 2026-10-10 00:00:00 | 10.01 | Tháng 10: 10.01 |
| B2 | 2026-10 | CONFIRMED | UNPAID | 2026-10-10 00:00:00 | 20.02 | Không |
| B3 | 2026-10 | CHECKED_IN | REFUNDED | 2026-10-10 00:00:00 | 30.03 | Không |
| B4 | 2026-11 | COMPLETED | PAID | 2026-11-10 00:00:00 | 40.04 | Tháng 11: 40.04 |
| B5 | 2026-11 | CANCELLED | PAID | null | 50.05 | Không; cảnh báo +1 |
| B6 | 2026-11 | REJECTED | PAID | 2026-11-19 05:00:01 | 60.06 | Không; sau cutoff |
| B7 | 2026-09 | PENDING | PAID | 2026-11-10 00:00:00 | 70.07 | Tháng 11: 70.07 |

Đây là dữ liệu để kiểm predicate báo cáo, không phải khẳng định workflow cho phép tạo mọi tổ hợp trạng thái trên.

Expected/observed PASS kỳ `2026-10`–`2026-11`:

- Summary: 6 Booking, revenue `120.12`.
- Tháng: Booking `[3,3]`, revenue `["10.01","110.11"]`.
- Sáu trạng thái theo contract: `[1,1,1,1,1,1]`.
- Global missing-paid-at count: 1.
- Đổi giá catalog thành `999999.99` và amounts của items thành `0.01`: response không đổi. Đây là negative control chứng minh nguồn revenue là header Invoice, không tái tính items.
- B4 Invoice chuyển REFUNDED và B1 Booking chuyển REJECTED: summary vẫn 6 Booking, revenue `80.08`; tháng `["10.01","70.07"]`; status `[0,1,1,1,1,2]`; cảnh báo vẫn 1. Không thêm dòng âm theo ngày refund.

## 3. Các tình huống khác

| Tình huống | Expected | Kết quả |
| --- | --- | --- |
| Oracle contract mục 7 | 4 Booking; `300.76`; tháng `100.25`/`200.51`; warning 1 | PASS — test Query/HTTP có sẵn được chạy lại |
| Chỉ doanh thu, 2 Invoice `9999999999.99`, Booking tạo ngoài kỳ | 0 Booking, `19999999999.98`; thêm nhiều items/services không nhân tổng | PASS — test endpoint mới |
| Chỉ Booking, kỳ 2025-12–2026-11 | 2 Booking ở tháng đầu/cuối, 10 tháng giữa 0, revenue `0.00` | PASS — test endpoint mới |
| PAID thiếu ngày gắn Booking ngoài kỳ | Warning 1 trong kỳ có dữ liệu và kỳ tương lai rỗng | PASS — test endpoint mới |
| Đầu/cuối kỳ, cutoff, năm nhuận, cực trị năm, mặc định, connection timezone | Đúng biên HCM/UTC, cutoff và zero-fill | PASS — bộ period/Query/HTTP chạy lại |
| Guest, role/account, raw query, 500 an toàn | 401/403 trước validation; 422 đúng field; 500 không lộ nội bộ | PASS — bộ HTTP/contract chạy lại |
| Summary/tháng/sáu status | Đẳng thức khớp, tiền decimal string, counts integer | PASS — assertions test mới và projection tests |
| GET readonly/read view | Fingerprint 9 bảng nghiệp vụ không đổi; aggregate một SELECT | PASS — test mới và Query test có sẵn |

## 4. Môi trường và bằng chứng mới

Lệnh từ root:

```powershell
pwsh -NoProfile -File backend/tests/Support/RunUc48MySqlTests.ps1 -Provision
```

MySQL Docker **8.4.13**, schema `stayhub_uc48_test_c4a6c62878c34b708e15a0f29a14cfb2`, user riêng chỉ có quyền schema đó. Probe xác nhận user QA không đọc được database dev. Các test đặt session timezone UTC; Query test kiểm thêm `+07:00`, `-05:00`, `+00:00` và phục hồi context.

- **214 PASS / 1.379 assertions / 0 skip**, test và runner exit 0; gồm 4 test mới và 29 test kernel HTTP cũ.
- Fingerprint dev trước/sau khớp; protected source/config hashes khớp.
- Cleanup xác nhận schema/user/connection còn lại đều 0; owned children đã thoát.
- Không giữ schema/server để bàn giao browser. QA-UI phải có kế hoạch cấp môi trường riêng.

Lượt đầu `770a7c2a12f746939661ea6a4d2b5e57`: 214 test PASS nhưng runner FAIL vì regex `WARN` bắt nhầm từ “warning” trong tên test mới. Cleanup và bảo toàn dữ liệu vẫn thành công. Đã sửa nhận diện status/summary trong đúng runner được duyệt, không đổi database guards. 5 mẫu phân loại PASS gồm test name hợp lệ, WARN, SKIP, skipped summary và passed summary. Lượt cuối trên là bằng chứng hoàn tất; không xóa/ghi đè bằng chứng lần đầu.

Evidence ignored/local:

- `.agent-workspace/tv3/uc48-qa-data-01/`: baseline, runner logs, status checks, Backend/Node/lint/build logs, scope và verification summary.
- `.agent-workspace/tv3/uc48-env-01/c4a6c62878c34b708e15a0f29a14cfb2/`: manifest, connection, tests, dev/protected fingerprints, cleanup, result.
- Đường dẫn ENV là quy ước runner hiện có; evidence này được tạo mới trong QA-DATA, không phải dùng lại kết quả ENV cũ.

## 5. Hồi quy và AC

Frontend: Node **215 PASS / 0 fail/skip**, lint exit 0 (0 errors, 3 Fast Refresh warnings có sẵn ở badge/button/sidebar), build exit 0 (bundle >500 kB). Node module mocking là experimental; không phải browser evidence.

Backend: `php artisan test --compact --no-ansi --do-not-cache-result`, explicit process overrides SQLite `:memory:`, **2.235 PASS / 9.589 assertions / 409 SKIP**, exit 0. Skips là các test opt-in/runtime/engine (gồm 4 test MySQL mới); không coi skipped là MySQL PASS. Không mang MySQL overrides sang suite chung.

Scope cuối: đúng 4 file được duyệt; hashes ngoài scope giữ nguyên, PHP syntax/PowerShell parser/Markdown links/whitespace kiểm tra đạt. Không commit/push/merge.

| AC | Evidence | Trạng thái |
| --- | --- | --- |
| DATA01 | Identity, grants, dev/protected fingerprints, cleanup | PASS |
| DATA02 | Multi-property/cohort endpoint test và oracle | PASS |
| DATA03 | Current-state/header/large-money/warning tests | PASS |
| DATA04 | Period/Query/HTTP MySQL tests | PASS |
| DATA05 | Readonly fingerprints, one SELECT, projection/invariants | PASS |
| DATA06 | HTTP auth/validation/safe error tests | PASS — kernel HTTP, chưa browser session |
| DATA07 | Full regression, scope và báo cáo | PASS |

## 6. Bàn giao QA-UI, chưa thực hiện

Owner kiểm trên React/API/schema QA thật được duyệt ở task riêng:

1. Oracle contract, multi-property ledger, Booking-only, revenue-only, zero và global warning đúng nhãn/phạm vi.
2. Kỳ 1/12 tháng, tiền lớn, apply/reset/retry; draft không đổi nhãn kết quả đã áp dụng.
3. Loading/offline/401/403/422; đổi kỳ/rời trang/logout khi request pending.
4. 375/768/1440 px, zoom 200%, bàn phím/focus và table scroll.

Các mục browser đều **NOT VERIFIED** trong QA-DATA. Không dùng Computer Use hoặc tạo browser harness. Không kết luận production TV2 integration từ synthetic snapshots; không merge PR #12 hoặc đổi semantics UC47.

## 7. Skills đã áp dụng

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): tiếp tục plan/scope được duyệt, baseline và task boundary.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): test production HTTP entry point, projection, permissions/error order và regression.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md): isolated MySQL, exact decimal, oracle, fingerprint và cleanup.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): bằng chứng theo engine, AC, báo cáo trung thực cả lần runner FAIL và mẫu Task Result.

**TECHNICAL PASS cho DATA01–DATA07; OWNER ACCEPTANCE PENDING.** Chưa đóng UC48. Dừng sau Task Result, chưa triển khai QA-UI-01.
