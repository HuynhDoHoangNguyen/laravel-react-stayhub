# UC48-QA-UI-01 — Môi trường và kiểm thử UI thủ công

Ngày: 2026-11-20 (Asia/Bangkok). Owner TV3 đã duyệt Mini Plan và scope 7 file.
Baseline: `feature/tv3-uc48-system-statistics`, HEAD `6395e602cf91ad6a4bcb2ac0dc9fb9379053b507` cộng working tree đã nghiệm thu.

**OWNER ACCEPTED QA-UI-01 trên chat ngày 2026-11-20, chấp nhận manual M01–M12 trên dev và giới hạn audit đã được giải thích.** Resource cleanup PASS. Technical evidence vẫn PARTIAL: không có manual trên schema QA riêng; runner exit 1 được giữ nguyên. Không đổi lịch sử FAIL thành PASS hoặc tuyên bố browser QA trên fixture đã chạy. QA-UI được owner chấp nhận theo phạm vi này, không yêu cầu dựng lại môi trường; task tiếp theo là lập Mini Plan CLOSE-01, chưa đóng UC48.

## 1. Ranh giới và an toàn dữ liệu

- React/Vite phục vụ source hiện tại, Domain API/shared Axios thật, Laravel routing/middleware/auth/CSRF thật; không mock API hoặc sửa code production.
- Chỉ schema/user `stayhub_uc48_test_<run_id>` do runner tạo, instance/grants được kiểm trước ghi. Guard browser riêng dùng database sessions; guard API testing/array-session không đổi.
- Fixtures là reporting snapshots, không chứng minh workflow TV2. Dữ liệu synthetic được nhận diện bằng tài khoản/tên UC48 QA và môi trường riêng.
- Password/key chỉ trong bộ nhớ tiến trình; lúc handoff password đăng nhập được ghi vào file private tạm trong ignored workspace, xóa khi dừng/hết hạn. Không ghi vào evidence, tài liệu hay commit.
- Runtime local không chạy chế độ unit test; smoke kiểm POST thiếu/sai XSRF bị 419, cookie session lưu trên MySQL và actor đúng qua `/api/me`.
- Source/config được fingerprint; hai file báo cáo QA/checklist được phép cập nhật trong manual handoff và được kiểm scope riêng. Database dev chỉ đọc fingerprint. Sessions được audit tách khỏi business rows; không sửa account khi manual.

## 2. Chạy và dừng môi trường

Từ repository root, PowerShell 7:

```powershell
pwsh -NoProfile -File backend/tests/Support/RunUc48Browser.ps1 -Provision -Scenario Populated -KeepAlive -MaxMinutes 120
```

Không có `-KeepAlive`: runner smoke rồi cleanup ngay. Có `-KeepAlive`: runner ghi `ready.json` và `handoff.json` dưới `.agent-workspace/tv3/uc48-qa-ui-01/runs/<run_id>/`, chờ owner manual tối đa số phút đã chọn. Server bind loopback, ports được cấp riêng, không dùng lại dev server đang chạy. Không tự khởi động/đổi Compose volume hoặc env.

`handoff.json` chứa đường dẫn credentials private, thời hạn UTC và stop file; không chứa password. Đọc credentials private trên máy để lấy `login_url`, email, password. Mở cửa sổ riêng tư để tách cookie khỏi phiên dev; login chọn **Quản trị viên** (`/login?role=admin`), sau đó vào `/admin/statistics`.

Kết thúc: logout mọi tab QA, gửi kết quả checklist cho agent. Agent tạo đúng `stop.request` trong thư mục run để runner audit, dừng servers và cleanup. Owner cũng có thể tạo file đó bằng `New-Item -ItemType File -LiteralPath '<stop_file trong handoff.json>'` trên máy. Không kill runner cưỡng bức vì sẽ bỏ qua finally; không tự drop schema khác.

Hết lease: runner tự cleanup, ghi lease expired; phần chưa kiểm giữ NOT VERIFIED. Schema không được giữ vô thời hạn. Muốn chạy lại, tạo run mới, không nhận lại schema cũ. Task chỉ hoàn tất sau kết quả manual và cleanup có bằng chứng.

## 3. Expected values cho run Populated

Fixture anchor lấy lúc provision, oracle dựa kỳ request và `generated_at` của response thật bằng native calendar/BCMath, không gọi production query/DTO/mapper và không đóng băng Auth/session.

Khi provision trong tháng 11/2026:

| Tình huống | Từ tháng → đến tháng | Booking | Doanh thu decimal | Global warning |
| --- | --- | --- | --- | --- |
| Dữ liệu thông thường | 2026-10 → 2026-11 | 6 | 300.76 | 1 |
| Chỉ Booking | 2026-07 → 2026-07 | 2 | 0.00 | 1 |
| Chỉ doanh thu | 2026-09 → 2026-09 | 0 | 0.75 | 1 |
| Tiền lớn | 2026-08 → 2026-08 | 0 | 19999999999.98 | 1 |
| Rỗng trong kỳ, vẫn cảnh báo toàn hệ thống | 2026-12 → 2026-12 | 0 | 0.00 | 1 |
| Mặc định/Đặt lại | 2025-12 → 2026-11 | 8 | 20000000301.49 | 1 |

Normal: tháng 10 có 3 Booking và revenue `100.25`, tháng 11 có 3 Booking và revenue `200.51`; sáu status mỗi loại 1. Booking-only: PENDING 1, CANCELLED 1, các trạng thái khác 0. Default thêm 2 Booking tháng 7; revenue-only/large có Booking tạo ngoài kỳ nên Booking trong các tháng đó 0.

Tiền hiển thị theo formatter VND của ứng dụng; so giá trị và phần thập phân chính xác, không yêu cầu browser dùng cùng một ký tự khoảng trắng. Số 0 doanh thu không được biến Booking-only thành empty. Cảnh báo phải ghi **toàn hệ thống, không phân bổ theo kỳ**, không đổi theo filter.

Nếu provision tháng khác, dùng `ui_months` trong handoff và oracle theo run, không sao chép nhãn tháng trên. Nếu lease qua ranh giới tháng, default có thể đổi đúng contract; đối chiếu theo generated_at mới.

Scenarios Zero/BookingOnly/RevenueOnly/MissingDate/Large cũng có thể smoke riêng bằng tham số `-Scenario`. Zero thực sự có warning 0; MissingDate có số liệu kỳ 0 và warning 1. Không cần dựng đồng thời nhiều môi trường để kiểm các tháng trong Populated.

## 4. Checklist owner — M01–M12 Đạt theo xác nhận trên chat

Owner đã xác nhận tất cả M01–M12 Đạt và đã logout trên chat ngày 2026-11-20. Đây là bằng chứng owner manual, không phải agent tự quan sát. Liên hệ các thao tác này với run QA riêng chưa được audit xác nhận (mục 7); không dùng Node/build để suy thêm browser PASS.

| ID | Thao tác và expected | Owner xác nhận / giới hạn audit |
| --- | --- | --- |
| M01 | Login ADMIN A đúng khu vực, mở thống kê, reload vẫn đúng tài khoản; Network gọi đúng backend QA | Đạt theo owner; QA context chưa đối chiếu |
| M02 | Normal: 2 summary, 6 status, 2 hàng tháng đúng bảng oracle; timezone/cutoff và nhãn tiếng Việt | Đạt theo owner; QA context chưa đối chiếu |
| M03 | Sửa draft chưa áp dụng: nhãn/kết quả vẫn kỳ cũ; Apply đổi kết quả; Reset về mặc định 12 tháng | Đạt theo owner; QA context chưa đối chiếu |
| M04 | Kiểm Booking-only, revenue-only, large và zero; đủ tháng/status 0, warning đúng phạm vi | Đạt theo owner; QA context chưa đối chiếu |
| M05 | Chọn kỳ đảo ngược hoặc >12 tháng → 422, field error gắn input, focus lỗi; sửa và Apply phục hồi | Đạt theo owner; QA context chưa đối chiếu |
| M06 | DevTools Network throttle: tải chậm có loading; Offline rồi Tải lại → error, Online/Thử lại phục hồi | Đạt theo owner; QA context chưa đối chiếu |
| M07 | Khi Pending đổi kỳ nhanh: chỉ kết quả kỳ cuối được hiển thị; nếu không quan sát được response về sai thứ tự ghi Chưa kiểm | Đạt theo owner; QA context chưa đối chiếu |
| M08 | Khi Pending rời trang/quay lại hoặc logout/login ADMIN B: không hiện lại dữ liệu của context cũ | Đạt theo owner; QA context chưa đối chiếu |
| M09 | Logout ở tab khác rồi request lại: UI phục hồi đăng nhập/route đúng, không giả thành empty; account/role bị chặn đúng luồng phù hợp | Đạt theo owner; QA context chưa đối chiếu |
| M10 | 375/768/1440 px: không tràn trang, bảng cuộn ngang, đọc đủ tiền lớn, nút/input dùng được | Đạt theo owner; QA context chưa đối chiếu |
| M11 | Zoom 200%, Tab/Enter, focus input/table/nút thấy được, labels/error association và cuộn bằng phím | Đạt theo owner; QA context chưa đối chiếu |
| M12 | Reduced motion trong OS/DevTools: hiệu ứng tôn trọng tùy chọn; logout mọi tab rồi yêu cầu dừng | Đạt theo owner; QA context chưa đối chiếu |

Role HOST/CUSTOMER bị frontend guard điều hướng là hành vi phù hợp; không yêu cầu nhìn thấy lỗi domain 403 trên trang ADMIN vốn không được phép vào. HTTP smoke kiểm quyền Backend riêng. Nếu muốn tái hiện session 403 trên page ADMIN mà không đổi production, ghi phần chưa kiểm và trao đổi trước; không tự sửa tài khoản dev.

## 5. Kiểm thử tự động và evidence

Lệnh guard: `php artisan test --no-ansi --do-not-cache-result tests/Unit/Uc48BrowserEnvironmentTest.php` trên SQLite in-memory.
Full Backend SQLite và Node/lint/build chạy trong tiến trình riêng. MySQL QA-DATA trước đó vẫn là evidence lịch sử, không gọi là test UI mới.

Evidence mới ở `.agent-workspace/tv3/uc48-qa-ui-01/`: baseline, guard/full regression logs, các run HTTP/oracle/source/identity/fingerprints/process/port/database cleanup. Không có browser automation hoặc Computer Use.

Kết quả mới trong task:

| Kiểm tra | Kết quả | Giới hạn |
| --- | --- | --- |
| Guard/oracle focused | 50 PASS / 95 assertions, exit 0 | Không browser |
| Full Backend SQLite `:memory:` | 2.285 PASS / 9.684 assertions / 409 SKIP, exit 0 | Skips runtime/engine opt-in, không MySQL PASS |
| HTTP/MySQL 8.4.13/Vite, 6 scenarios | 6 PASS; tổng 558 status probes và 72 full-projection oracle comparisons | Cookie jars/HTTP scripts, không render React |
| Negative controls | Thiếu Provision bị từ chối; injected Ready failure exit 1 đúng kỳ vọng | Cleanup schema/user/connection/process/port PASS, dev/protected hashes không đổi |
| Node | 215 PASS / 0 FAIL / 0 SKIP, exit 0 | Mock transport/coordinator |
| Lint/build | Exit 0; lint 0 errors/3 Fast Refresh warnings có sẵn, build bundle >500 kB | Static/build evidence |
| Scope/syntax/links/whitespace | Đúng 7 file, không đổi ngoài scope; PHP/PowerShell parse và links hợp lệ | Không commit/push/merge |

Focused lần đầu exit 255 do thiếu dấu đóng array trong test mới, chưa chạy test/runtime; đã sửa cú pháp và chạy lại focused/full suite PASS. Negative injected failure được giữ trong evidence và không tính là scenario PASS.

6 run đã hoàn tất: Populated `e94244cd1a1d494c81b0ee923fb84114`, Zero `f6d69fdefd5543f3a0be47c776fceeda`, BookingOnly `a22e0bb8202b4bd487e8bac5337b839f`, RevenueOnly `ca09dd22d674411b95db6e2a66e2dc58`, MissingDate/Large tra `run-summary.json`. Mỗi run có result/cleanup/fingerprints; tất cả schema/user/connection QA còn lại 0. Negative Ready run `23b2bafc38ab4e468355e5e1c4fac16f` cleanup không lỗi. Không dùng test QA-DATA cũ làm bằng chứng mới.

## 6. Môi trường đã bàn giao — hiện đã dừng

- Run `822d1c28fc794deea2d42eb74e701831`, Populated; frontend `http://localhost:56979`, backend `http://localhost:56978`.
- Login `http://localhost:56979/login?role=admin`, email `admin_a@uc48.example.test`; Admin phụ `admin_b@uc48.example.test` dùng cùng password tạm.
- Credentials private: `.agent-workspace/tv3/uc48-qa-ui-01/private/822d1c28fc794deea2d42eb74e701831.json`. Không chép password vào tài liệu/evidence.
- Hết hạn `2026-11-20T14:09:13.8703627Z` = **21:09:13 ngày 20/11/2026, UTC+7**.
- HTTP/source/oracle preflight của live run PASS; effective Vite Axios origin được xác nhận đúng backend QA. Source route trả HTTP 200 không phải bằng chứng render/browser.
- Thông tin URL/thời hạn ở trên là lịch sử handoff. Owner yêu cầu dừng sau manual; runner dừng lúc `2026-11-20T12:15:14.1468136Z` (19:15:14 UTC+7), trước khi hết lease. Hai process đã thoát, hai port không còn listener, schema/user/connection QA và private credential file đã xóa. Các URL này không còn là môi trường đang chạy.

| AC | Trạng thái hiện tại | Còn thiếu |
| --- | --- | --- |
| QAUI01 | PARTIAL | HTTP/session/CSRF/source PASS; owner browser session/Network |
| QAUI02 | PARTIAL | JSON/oracle PASS; owner kiểm số liệu hiển thị |
| QAUI03 | NOT VERIFIED browser | Owner thao tác form/states/retry |
| QAUI04 | NOT VERIFIED browser | Owner pending/route/auth scenarios; Node chỉ bổ trợ |
| QAUI05 | NOT VERIFIED | Owner viewport/zoom/keyboard/reduced motion |
| QAUI06 | PARTIAL | Hồi quy/scope/closed-run cleanup PASS; live audit/cleanup chờ manual |

Ma trận PARTIAL/NOT VERIFIED ở trên là snapshot lúc bàn giao. Trạng thái mới sau manual/cleanup ở mục 7; không sửa ngược bằng chứng ban đầu.

## 7. Audit/cleanup sau xác nhận owner

Owner báo **M01–M12 đều Đạt, đã logout**, yêu cầu dừng run và chưa chuyển CLOSE-01. Agent chỉ tạo stop file thuộc manifest run hiện tại, không kill cưỡng bức hoặc tác động server dev.

| Kiểm tra thực tế | Kết quả |
| --- | --- |
| QA business fingerprints 9 bảng trước/sau manual | PASS, không đổi |
| Authenticated QA sessions sau logout | 0 |
| Owned process 13248/5212 | Đã thoát |
| Ports 56978/56979 | Đã giải phóng; độc lập kiểm lại không có listener |
| Schema/user/connections QA | Đều 0; truy vấn chỉ đọc kiểm lại đúng instance/manifest |
| Private credential file | Đã xóa |
| Protected source/config hashes | Không đổi, gồm env được bảo vệ |
| Dev table row counts | Không đổi |
| Dev full-dump fingerprint | **FAIL: khác baseline**, runner exit 1; giữ nguyên result.json/log gốc |

Chẩn đoán chỉ đọc binlog.000009 (metadata header/Table_map/row events, không lưu row values) phủ khoảng từ lúc provision đến kết thúc audit:

- Khoảng `2026-11-20T12:08:39Z`–`12:15:15Z`: dev có **11 row events, tất cả trên sessions**, không thấy row write/DDL vào bảng nghiệp vụ dev trong cửa sổ đã kiểm. Không suy người thực hiện từ binlog.
- Run QA có 154 row events trong cửa sổ gồm preflight, nhưng **0 row events sau readiness** (`12:09:14Z`–`12:15:15Z`). Không có bằng chứng session writes để đối chiếu manual với schema QA riêng; đây không phải bằng chứng chắc chắn owner dùng URL nào.
- User QA chỉ có grant trên schema owned; không có quyền ghi dev. Các SQL fingerprint/audit dev của runner là chỉ đọc. Không phục hồi hoặc ghi đè dev sessions, không nới guard, không sửa result FAIL thành PASS.

Evidence mới: `.agent-workspace/tv3/uc48-qa-ui-01/closure/` gồm baseline lượt đóng, metadata-only binlog diagnostic và `final-audit.json`; run gốc giữ `browser-business-after.json`, `browser-sessions-after.json`, process/port/DB cleanup, dev/protected before/after và result/log exit 1.

| AC | Trạng thái sau audit | Giới hạn |
| --- | --- | --- |
| QAUI01 | PARTIAL | HTTP/source/session preflight PASS; manual chưa gắn được với đúng QA runtime |
| QAUI02 | OWNER REPORTED PASS | M02/M04 Đạt; JSON/oracle PASS, QA runtime correlation còn thiếu |
| QAUI03 | OWNER REPORTED PASS | M03/M05/M06 Đạt theo owner, cùng giới hạn context |
| QAUI04 | OWNER REPORTED PASS | M07–09 Đạt theo owner, cùng giới hạn context |
| QAUI05 | OWNER REPORTED PASS | M10–12 Đạt theo owner, không có agent browser observation |
| QAUI06 | PARTIAL | Tài nguyên cleanup/source QA PASS; dev full fingerprint FAIL đã giải thích là sessions trong binlog, chưa miễn trừ tiêu chí này |

**Kết luận tại thời điểm audit: technical PARTIAL, không TECHNICAL PASS toàn task.** Sau đó owner xác nhận manual dùng `http://localhost:5173` (dev). Dev Vite/Axios dùng backend `http://localhost:8000`, database `stayhub`; QA được bàn giao là `56979` → `56978` trên schema riêng. Thông tin này giải thích thiếu QA session activity sau handoff và phù hợp 11 dev sessions events làm đổi full fingerprint; không thấy business writes dev trong cửa sổ đã kiểm. Audit follow-up chỉ đọc lưu dưới `.agent-workspace/tv3/uc48-qa-ui-01/audit-followup/`, không sửa code, dev data hoặc dựng lại môi trường.

Owner sau khi biết rõ giới hạn trên đã quyết định nghiệm thu QA-UI-01 và cho phép chuyển bước tiếp theo trên chat ngày 2026-11-20. **OWNER ACCEPTED WITH DOCUMENTED LIMITATIONS**, technical evidence vẫn PARTIAL. Giữ M01–M12 là owner-reported PASS trên dev; không đổi thành manual PASS trên schema QA riêng. Không sửa/miễn trừ ngầm guard, không thay runner result gốc. Còn task CLOSE-01 lập hồ sơ/chốt nghiệm thu UC48 theo scope riêng.

## 8. Skills thực tế

- [stayhub-task-planning](../../.agents/skills/stayhub-task-planning/SKILL.md): approved scope/baseline và handoff boundary.
- [stayhub-react](../../.agents/skills/stayhub-react/SKILL.md): trace source/API, trạng thái UI/lifecycle và manual checklist.
- [stayhub-laravel](../../.agents/skills/stayhub-laravel/SKILL.md): real HTTP/CSRF/session/permission boundary, guard tests và regression.
- [stayhub-database](../../.agents/skills/stayhub-database/SKILL.md): owned schema/identity/grants, oracle và cleanup.
- [stayhub-verification](../../.agents/skills/stayhub-verification/SKILL.md): evidence phân loại, AC và Task Result 8 phần.

Chưa nghiệm thu task, chưa chuyển CLOSE-01.
