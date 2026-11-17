# Bàn giao baseline StayHub — HANDOVER-BASELINE-01

Ngày đối chiếu:2026-11-16, Asia/Bangkok. Source branch refactor/stayhub-layered-architecture, HEAD7dac999b2808ca6fd583985e19d31248cae1b4cc cộng working tree đã nghiệm thu. Target dev đã fetch tại lượt kiểm:951f62efc70127a3719c840ae529f8d311842a49.

**Baseline là source snapshot chưa commit.** Gói không bao gồm lịch sử Git và không đại diện cho PR đã được phép merge. Xem [PR checklist](BASELINE_PR_CHECKLIST.md) và [checklist TV3](TV3_CHECKLIST.md) để phân biệt PACKAGE VERIFIED, nghiệm thu chức năng và approval merge.

## Phạm vi đã nghiệm thu

| Module | Phạm vi nhận baseline |
| --- | --- |
| Invoice |5API đọc/tạo, validation/typed DTO, Repository, transaction/snapshot, header/items mapping và React API/service/mapper |
| Review |4API đọc/tạo, audit Backend, mapper/service Frontend, màn hình đọc và modal tạo |
| Auth/Profile | Login/register, user projection; cập nhật hồ sơ/mật khẩu/avatar; audit/fix và cleanup CredentialRules đã nghiệm thu |
| Public Catalog | UC04–UC05 danh sách/chi tiết Property |
| Host Property | UC18 list/detail/create/update/status và upload/primary/delete ảnh cơ sở |
| Host RoomType | UC19 list/create/update/delete theo ràng buộc tham chiếu đã chốt |
| Host Room |5luồng UC20 list/detail/create/update/status; audit/F01 đã đóng |

10UC TV3 có luồng chính và đã refactor trong phạm vi hiện hữu: **UC15,16,34,35,37,38,56,57,58,60**. QA chức năng/end-to-end và tích hợp Booking thật vẫn được theo dõi riêng; không tự gọi10UC này là nghiệm thu trọn vẹn.

8UC TV3 TODO: **UC17,36,39,44,45,47,48,61**. FAQ Management và Chat History cũng còn TODO. **UC21 chưa triển khai**: mới có gallery đọc và Mini Plan kiểm kê, không có nghiệp vụ upload/chọn chính/xóa ảnh phòng riêng đã được nghiệm thu.

## Trách nhiệm nhận tiếp

| Owner | Việc nhận tiếp |
| --- | --- |
| TV1 | Duy trì baseline Auth/Profile/Public Catalog/UC18/UC19/5luồng UC20; kiểm kê và chuẩn hóa UC21/22/23, Public Room/filter và Admin thuộc phân công còn lại |
| TV1 | Chốt task riêng cho DELETE Room hoặc DB unique(property_id,room_number); hiện uniqueness dựa workflow lock |
| TV2 | Kiểm kê/chuẩn hóa Availability/Search, Booking lifecycle, dịch vụ/pricing, check-in/check-out; cung cấp contract Booking thực tế |
| TV1+TV2 | Phối hợp lock/recheck giữa Room status và Booking/Maintenance writers;14race harness không thay thế integration production |
| TV2+TV3 | Nối điểm vào Invoice/Review từ Booking thật, giữ ownership, snapshots và trạng thái COMPLETED; TV3 tiếp tục QA/chức năng của mình |

API module Booking chưa đăng ký trong api.php của snapshot này; không kết luận các nhánh TV2 khác không có code. Không chuyển trách nhiệm QA Invoice/Review hoàn toàn cho TV2.

## Quy chuẩn áp dụng

Đọc [ARCHITECTURE.md](../../ARCHITECTURE.md) trước khi mở rộng và dùng [skills dự án](../../.agents/skills/) theo task.

Backend: Route/Middleware → Controller → Validator/typed Request DTO → Service/QueryService → Repository/Model → Mapper/Response DTO → HTTP response.

- Controller điều phối; Service giữ workflow/transaction; Repository giữ query/persistence/lock; Model giữ relationships/casts/scopes.
- Validator kiểm dữ liệu raw trước conversion khi contract cần; dùng Symfony constraints/groups theo mẫu hiện hữu. Helper credentials tại app/Validators/Auth/CredentialRules.php.
- Mapper thuần, projection allowlist, không query/ghi dữ liệu hoặc tính lại nghiệp vụ; adapter ObjectMapper tại app/Mappers/EloquentObjectMapper.php.
- Http/Requests, Http/Resources và Mapping rỗng đã được dọn. Các module mới chỉ tạo lớp có trách nhiệm thực tế; không khôi phục class cũ để né sửa consumer.

Frontend: Page/Component/Hook → Domain Service → Domain API → shared Axios, response qua Mapper trước khi tiêu thụ.

Giữ contract hiện hữu: status/error order/messages, nullable/omitted fields, decimal strings, pagination, session/CSRF/role/ownership, pending/cancellation/retry và xử lý mutation chưa rõ kết quả. Không tự động replay mutation khi chưa xác định commit.

Tạo Mini Plan với exact files/AC trước task; test trên schema owned; không seed/migrate/refresh dev, không tự commit/push/merge. Tính năng có UI cần cả Backend/Frontend trong phạm vi tương ứng.

## Thành phần gói bàn giao

Các artifact local trong .agent-workspace/handover-baseline-<GUID32>/, không phải file source đưa vào PR:

| Artifact | Vai trò |
| --- | --- |
| source.zip | Snapshot source hiện hữu, gồm tracked và untracked cần bàn giao |
| manifest.json | Relative path, Git tracking/action, domain, bytes/SHA256; SHA256 ZIP và exclusions |
| deleted-paths.txt | Danh sách14file tracked đã xóa; không đưa lại vào checkout |
| git-state.json | HEAD, fetched dev, merge-base, inventory và các gate PR chưa xác nhận |
| checks/ | Log kiểm tra và đối chiếu đã lọc; không phải runtime dependency |

Source ZIP không chứa .git, env thật, secrets/private keys, vendor/node_modules, build/dist, uploads/datadir hoặc harness local. Không chỉ dùng git archive HEAD hoặc patch tracked vì sẽ bỏ lớp mới untracked.

**Exclusion được công khai:** frontend/.env đã được track trên HEAD và fetched dev, không đổi ở task này. File chỉ có3key cấu hình Vite công khai, không phát hiện token/private-key theo pattern scan; ZIP vẫn loại env thật theo kế hoạch. frontend/.env.example được giữ sau kiểm tra. Không tự git rm hoặc sửa .gitignore ngoài scope; xử lý Git hygiene này là quyết định/task riêng.

Manifest được tạo sau khi source/docs/checklist chốt; hash manifest/ZIP nằm ngoài ZIP để tránh vòng tự hash. Nếu sửa source sau đóng gói thì phải tạo và kiểm gói lại.

## Cách tiếp nhận và đối chiếu

1. Giữ nguyên gói gốc; đối chiếu SHA256 source.zip với manifest.json.
2. Extract vào **thư mục mới**, không đè checkout hoặc thư mục dữ liệu đang dùng. ZIP là snapshot source, không có history/.git; dùng PR khi tích hợp vào repository team.
3. Kiểm từng relative path/size/SHA256 theo manifest, không có file thừa, không có file trong deleted-paths.txt hoặc env thật.
4. Tạo env local riêng từ backend/.env.example và frontend/.env.example; tự cấu hình origin/API/DB/schema được cấp. Không lấy env từ máy gửi.
5. Cài dependency từ lockfiles: composer install trong backend, npm ci trong frontend. Đây là hướng dẫn cho môi trường nhận riêng, task hiện tại không chạy install/update trong working tree.
6. Tạo APP_KEY riêng khi cần, chuẩn bị schema/database cô lập. Chỉ migrate khi đã xác nhận target được phép; không dùng migrate:fresh/refresh/seed hoặc composer setup trên dữ liệu dev cần giữ.
7. Chạy các cổng trong PR checklist bằng đúng engine/guard. Trước khi dùng UI, kiểm cấu hình API và mock flags; Booking production chưa tích hợp trong snapshot này.
8. Khi nhận qua PR, đối chiếu PR diff với manifest/action list và xác nhận merge target dev trên commit cuối.

## Bằng chứng và giới hạn

Kết quả mới ngày2026-11-16: Backend full SQLite1165tests/6668assertions;6targeted MariaDB suites411/1704; Frontend Node154tests, lint/build exit0. Node22 yêu cầu cờ --experimental-test-module-mocks cho tests dùng mock.module; lần chạy thiếu cờ76pass/4fail chỉ là failed invocation đã ghi riêng, không được che đi.

Lint còn3FastRefresh warnings nền; build còn chunk>500kB và plugin timing diagnostic. Không tự thay Frontend ngoài scope.

Targeted MariaDB dùng binary XAMPP10.4.32, datadir server/schema owned riêng tại127.0.0.1:3306; đã dọn schema/server/datadir. Không gọi là full MySQL hoặc instance dev. Dữ liệu dev đối chiếu offline55file fingerprint; SQL dev chưa xác nhận hoạt động. Không repair bảng quyền XAMPP trong task này.

HTTP/browser/14race cũ là evidence kế thừa E14–E18 trên source không đổi về logic, không phải lượt runtime mới. InvoiceService task này chỉ bỏ whitespace; giữ các dependencies đã chốt: DB uniqueness, Booking/Maintenance writers, DELETE Room, QA từ Booking thật. Xem [checklist](TV3_CHECKLIST.md) để tra từng evidence.

## Kết luận bàn giao

Gói kiểm chứng source không phải approval merge. Người dùng chưa cho phép commit/push/merge; chưa tạo PR thật hoặc chạy CI trên commit cuối. TV1/TV2 nhận tiếp phạm vi của mình theo kiến trúc chung. Không triển khai UC21 trong task này.
