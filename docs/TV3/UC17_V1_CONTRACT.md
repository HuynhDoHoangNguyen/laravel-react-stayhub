# UC17 V1 — Đặc tả chat với chatbot

- **Task** UC17-CONTRACT-01 · **Owner** TV3 · **Ngày** 2026-11-22 (Asia/Bangkok).
- **Trạng thái hiện tại** UC17 V1 **CLOSED TV3 / OWNER ACCEPTED** ngày 2026-11-22. Owner chốt D1 đóng V1 riêng, nghiệm thu UC17-GUEST-01 và giữ UC61 mở. [UC17-CLOSE-01](#8-uc17-close-01)
- **Baseline khi bắt đầu** nhánh `feature/tv3-uc17-chatbot`, HEAD `e94bb024bc39f6aed6d8b490f9f5e2bd8c6e48b4`, commit đóng UC44 V1. Khi đó checklist ghi 16/18 UC CLOSED TV3 và T01–T03 BLOCKED TV2. [Checklist TV3](TV3_CHECKLIST.md)

## 1. Quyết định và giới hạn phạm vi

| ID | Trạng thái | Đề xuất và nội dung cần chốt | Ảnh hưởng |
| --- | --- | --- | --- |
| D1 | **Đã duyệt tại CLOSE-01** | Owner đóng riêng UC17 V1 với tư vấn chung qua Gemini, widget Guest/Customer, gợi ý Catalog công khai, lưu và replay Customer. Catalog hỗ trợ số khách và ngân sách. Câu hỏi cần khu vực, ngày ở hoặc dữ liệu tài khoản được dẫn tới trang tra cứu phù hợp. | UC61 giữ mở để triển khai ngữ cảnh nội bộ. Quan hệ `include` trong tài liệu Use Case là yêu cầu của luồng đầy đủ. FAQ Management và Chat History có phạm vi riêng. |
| D2 | **Đã duyệt** | CUSTOMER ACTIVE dùng phiên web SPA Sanctum khớp actor. Widget đặt trong CustomerLayout trên các trang /customer. | Owner đã duyệt import route và điểm gắn widget. |
| D3 | **Đã duyệt** | Lượt gửi có khóa theo tài khoản và request_id. Ghi cặp USER/ASSISTANT nguyên tử khi thành công. Retry/replay trong 24 giờ, tối đa 3 lần xử lý, quyền xử lý 30 giây mỗi lần. Metadata đủ điều kiện dọn sau 7 ngày. | DATA-01 bổ sung persistence và FK. Tác vụ dọn định kỳ cần scope riêng trước khi bật tính năng. |
| D4 | **Đã chốt model, cấu hình và nghiệm thu QA live** | Laravel dùng Gemini 3.5 Flash-Lite. Cấu hình server gồm timeout tổng 12 giây, 500 token đầu ra, 10 request/phút mỗi Customer và tối đa 2 lượt gọi đồng thời. | Phê duyệt live áp dụng cho các lượt thử với câu tổng hợp đã duyệt. Chính sách lưu dữ liệu phía Gemini, xử lý câu hỏi tự do và quyền mở cho người dùng thực tế còn cần phạm vi riêng. |

[Use Case hệ thống](../stayhub-usecase-system.md) · [Kiến trúc](../../ARCHITECTURE.md) · [Mockup](../stayhub-screen-mockups.md) · [Phân công](../stayhub-team-work-assignment.md)

## 2. Baseline và phạm vi

- `chat_sessions` có owner nullable, title nullable và timestamps. `chat_messages` có FK bắt buộc tới session với cascade, sender `USER` hoặc `ASSISTANT`, message `TEXT`, created_at bắt buộc và updated_at nullable. Audit đọc metadata MySQL Docker 8.4.13, xác nhận hai bảng InnoDB và hai migration đã áp dụng. Hai model Eloquent đã có. Dữ liệu chèn trực tiếp từ seeder là dữ liệu mẫu. [ERD](../stayhub-erd.md) · [Migration gốc](../../backend/database/migrations/2026_10_08_000000_create_stayhub_schema.php) · [Migration updated_at](../../backend/database/migrations/2026_10_13_064530_add_updated_at_to_chat_messages_table.php) · [ChatSession](../../backend/app/Models/ChatSession.php) · [ChatMessage](../../backend/app/Models/ChatMessage.php) · [Seeder](../../backend/database/seeders/Member3DataSeeder.php)
- Audit route registry Laravel tìm thấy `GET /api/me` với Auth và active-account middleware. Bộ lọc route chat trả về không có route khớp. React có vùng CUSTOMER với CustomerLayout và Outlet. Shared Axios hiện gửi cookie/XSRF. Repo đã audit chưa có chat entry, Chat API, workflow hay provider adapter của StayHub. [Route registry](../../backend/routes/api.php) · [AppRouter](../../frontend/src/routes/AppRouter.jsx) · [CustomerLayout](../../frontend/src/layouts/CustomerLayout.jsx) · [Axios](../../frontend/src/api/axios.js)
- App chỉ ghi dữ liệu phục vụ chat. Provider trả nội dung tư vấn và không có quyền thao tác Booking, thêm dịch vụ, thanh toán hay cập nhật nghiệp vụ. UC17 lưu cặp message để hoàn thành lượt hỏi–đáp và phục hồi response. UC61 sở hữu truy xuất ngữ cảnh nội bộ. Liệt kê, đọc lại, tiếp tục phiên cũ qua History hoặc xóa lịch sử thuộc Chat History. Streaming, chọn model từ UI và fallback provider để ngoài đề xuất V1.

## 3. HTTP và quyền theo đề xuất

`POST /api/customer/chat/messages` · route name `customer.chat.messages.store`.

1. Dùng phiên first-party và cookie/XSRF. Giữ middleware `auth:sanctum` → `account.active` → `role:CUSTOMER`. Giới hạn route theo tài khoản sau khi xác thực, 10 request/phút, gồm retry và replay. Route kiểm một phiên `web` xác thực cùng actor; bearer-only không đủ quyền. Middleware CSRF có thể trả 419 trước kiểm quyền nghiệp vụ.
2. Form Request kiểm kiểu JSON và định dạng trước khi ép kiểu. Service resolve session bằng ID và owner của actor. Session không tồn tại, owner null hoặc thuộc tài khoản khác đều trả cùng 404 trước khi gọi provider hoặc ghi chat. Dùng ranh giới ghi của Service để kiểm lại resource khi commit.
3. `message` là chuỗi 1–2.000 ký tự sau trim. `request_id` là UUID bắt buộc, do client sinh cho mỗi lượt. `session_id` có thể bỏ hoặc là null ở lượt đầu, hoặc là số nguyên dương hợp lệ của phiên đang mở. Bỏ field và null cùng được chuẩn hóa thành null. Owner và sender do server quyết định.
4. Success dùng envelope `data` qua Common ApiResponse. Field trả về giới hạn ở ID phiên, UUID lượt, role, ID, nội dung và thời điểm tạo message theo UTC. Lỗi Auth/framework/validation dùng shape hiện hành. Lỗi domain chat dùng `message` và `code`.

[Customer API hiện hành](../../backend/routes/api/booking.php) · [Sanctum](../../backend/config/sanctum.php) · [ApiResponse](../../backend/app/Common/ApiResponse.php)

### Request mẫu

```json
{
  "session_id": null,
  "request_id": "d3a70b4c-41f3-4b08-8f79-df5f3b44d620",
  "message": "Tôi nên kiểm tra gì trước khi đặt phòng?"
}
```

### Response mẫu

HTTP 201 ở lần tạo thành công. HTTP 200 khi phát lại kết quả, cùng shape và giá trị đã commit.

```json
{
  "data": {
    "session_id": 42,
    "request_id": "d3a70b4c-41f3-4b08-8f79-df5f3b44d620",
    "user_message": {
      "id": 101,
      "sender": "USER",
      "message": "Tôi nên kiểm tra gì trước khi đặt phòng?",
      "created_at": "2026-11-22T03:00:00Z"
    },
    "assistant_message": {
      "id": 102,
      "sender": "ASSISTANT",
      "message": "Bạn nên xem sức chứa, giờ nhận phòng và chính sách hủy.",
      "created_at": "2026-11-22T03:00:02Z"
    }
  }
}
```

### Lỗi và hành vi UI

| HTTP hoặc kết quả | Trường hợp | Hành vi UI |
| --- | --- | --- |
| 401 | Thiếu phiên xác thực phù hợp | Dùng luồng Auth hiện hành, bỏ kết quả thuộc phiên cũ. |
| 403 | Sai role hoặc tài khoản inactive/blocked | Hiện lỗi quyền hoặc chuyển trạng thái Auth theo mã lỗi tài khoản hiện hành. |
| 404 | Session vắng hoặc ngoài scope | Báo phiên không còn truy cập được. Người dùng có thể bắt đầu lượt mới với UUID mới. |
| 419 | CSRF hết hạn hoặc sai | Cho thử lại có chủ đích sau khi làm mới CSRF, giữ payload và UUID nếu phiên vẫn là của cùng actor. |
| 422 | Payload sai hoặc nội dung không đạt quy tắc gửi dữ liệu đã duyệt | Hiện lỗi tại field, giữ draft. Nội dung đã sửa dùng UUID mới. |
| 409 `CHAT_IN_PROGRESS` | Cùng ID và payload đang được xử lý | Giữ draft và UUID. Báo đang xử lý, cho thử lại theo thời gian được phép. |
| 409 `CHAT_REQUEST_CONFLICT` | Cùng ID với payload khác | Báo xung đột và giữ nội dung soạn. Lượt mới có UUID mới. |
| 409 `CHAT_RETRY_REQUIRED` | Lượt mất quyền xử lý hoặc không còn đủ thời gian cho lời gọi provider và hiện có thể thử lại | Giữ payload và UUID. Người dùng chủ động thử lại. |
| 429 | Giới hạn route chat | Giữ payload và khóa Thử lại cho tới hết `Retry-After` hợp lệ. |
| 409 CHAT_REQUEST_EXPIRED / CHAT_ATTEMPTS_EXHAUSTED / CHAT_RESULT_UNAVAILABLE | Lượt hết hạn, hết số lần thử hoặc kết quả không còn | Giữ nội dung soạn và cho bắt đầu lượt mới với UUID mới. |
| 503 CHAT_BUSY | Hết slot gọi provider đồng thời | Giữ draft và UUID để thử lại. |
| 503 `CHAT_PROVIDER_UNAVAILABLE` / `CHAT_PROVIDER_RATE_LIMITED` | Provider lỗi, trả response không dùng được hoặc giới hạn upstream | Giữ draft và UUID. Cho retry chủ động. Tôn trọng `Retry-After` hợp lệ nếu có. |
| 504 `CHAT_PROVIDER_TIMEOUT` | Provider quá thời gian | Giữ draft và UUID. Cho retry chủ động. |
| 500 / mất mạng / mất response | Client chưa biết kết quả commit | Giữ payload gốc và UUID để khôi phục lượt. |

```json
{
  "message": "Trợ lý phản hồi quá thời gian. Vui lòng thử lại.",
  "code": "CHAT_PROVIDER_TIMEOUT"
}
```

## 4. Lưu lượt gửi, đồng thời và retry

DATA-01 bổ sung bảng `chat_turns` bổ sung với unique `(user_id, request_id)`, dấu vân tay của session ID **được yêu cầu** cùng câu hỏi chuẩn hóa, trạng thái, định danh lần thực thi, mốc hết hạn xử lý và tham chiếu đến session/cặp message đã commit. Session và message hiện có tiếp tục dùng được với dữ liệu trước migration. Bản ghi kỹ thuật giữ lượng dữ liệu tối thiểu, không sao chép nội dung tin nhắn. D3 xác nhận các giới hạn sau.

- Retry/replay trong 24 giờ tính từ lần nhận đầu. Tối đa 3 lần được cấp quyền xử lý, gồm lần đầu. Replay, poll và request bị từ chối giữ nguyên bộ đếm. Quyền của mỗi lần xử lý hết sau 30 giây. Khi một lượt tiếp quản thành công, định danh thực thi cũ mất quyền ghi.
- Từ mốc 24 giờ đến lúc dọn, lượt cũ bị từ chối. Metadata đủ điều kiện dọn theo lô từ mốc 7 ngày tính theo thời điểm tạo lượt. DATA-01 chuẩn bị index cho việc dọn. Tác vụ dọn định kỳ cần scope riêng trước khi bật tính năng. Xóa metadata giữ nguyên phiên và tin nhắn trong dữ liệu chat, đồng thời kết thúc khả năng nhận diện UUID cũ.
- Xóa user cascade lượt kỹ thuật, phiên và tin nhắn. Xóa phiên hoặc message kết quả đặt FK kết quả liên quan về null. Lượt đã thành công thiếu kết quả trả trạng thái kết quả không còn khả dụng trong thời hạn replay và giữ nguyên dữ liệu còn lại.
- Fingerprint dùng HMAC phía server trên dữ liệu đã chuẩn hóa. Cần giữ khóa ổn định trong thời hạn replay.

| Tình huống | Trạng thái và ghi dữ liệu | Retry |
| --- | --- | --- |
| Lượt được nhận | Giữ chỗ `PROCESSING` trong transaction ngắn. Gọi provider sau khi kết thúc transaction. | Cùng payload trong thời gian xử lý nhận 409. |
| Provider trả nội dung hợp lệ | Transaction khóa lượt, xác nhận định danh thực thi còn hiệu lực, kiểm lại session và ownership, tạo session nếu cần, ghi USER và ASSISTANT, rồi lưu kết quả `SUCCEEDED`. | Cùng ID/payload nhận 200 với cặp đã lưu. Provider chỉ được gọi lại nếu chưa có kết quả thành công đã commit và hợp lệ để retry. |
| Provider lỗi hoặc timeout đã xác nhận | Chuyển lượt còn hiệu lực sang `FAILED`. Giữ metadata kỹ thuật, chưa tạo session hoặc message cho lần thất bại. | Dùng cùng ID/payload để tiếp quản theo quy tắc D3. |
| Lỗi giữa hai insert hoặc mất worker | Transaction ghi cặp rollback khi lỗi. Lượt có thể ở `FAILED` nếu cập nhật được, hoặc `PROCESSING` đến khi hết hạn. | Khi quá hạn, lần tiếp quản có định danh thực thi mới. Lần cũ đến muộn không còn quyền commit. API đọc lại lượt và trả `200` nếu đã có kết quả, `CHAT_IN_PROGRESS` nếu lượt đang xử lý, hoặc `CHAT_RETRY_REQUIRED` nếu lượt có thể tiếp quản. |
| Mất response sau commit | `SUCCEEDED` và cặp message vẫn tồn tại. | Replay cùng payload gốc, gồm `session_id: null` nếu là lượt đầu. Trả lại session và message ID cũ. |
| Trùng UUID với payload khác | Giữ lượt gốc, trả 409. | Nội dung chỉnh sửa dùng UUID mới. |

Idempotency bảo vệ kết quả lưu trong thời hạn replay đã chốt. Nếu timeout hoặc worker chết sau khi provider đã nhận request, lần tiếp quản có thể phát sinh một lượt gọi provider mới. Kiểm thử phải phân biệt số lượt gọi provider và số cặp message đã commit.

## 5. Provider và hành vi React

- Laravel dùng adapter Gemini riêng qua HTTP Client. Mỗi lần xử lý gọi một lần, timeout kết nối tối đa 3 giây và tổng thời gian gọi mặc định 12 giây. Adapter chỉ gửi câu hỏi hiện tại đã được duyệt và chỉ dẫn tư vấn cố định, giới hạn một candidate và 500 token đầu ra. Secret được nạp từ env phía server và gửi qua header xác thực.
- Candidate có finishReason STOP và text hợp lệ được dùng làm câu trả lời. Response bị lọc, chạm giới hạn token, sai cấu trúc hoặc rỗng được coi là không khả dụng. Adapter phân biệt timeout xác định được, upstream 429 và các lỗi không khả dụng. Retry-After hợp lệ được chuyển thành số giây. CHAT-01 chuyển timeout thành HTTP 504, lỗi provider còn lại thành 503. Adapter giữ một request cho mỗi lần xử lý, còn replay và retry thuộc workflow đã chốt.
- Route giới hạn 10 request/phút theo tài khoản. Tối đa 2 lượt gọi đồng thời trên toàn ứng dụng bằng khóa trên database cache. Replay có thể đọc kết quả khi đã hết slot. Lượt mới chỉ dùng một attempt sau khi lấy slot. Ngay trước lời gọi provider, service kiểm phần thời gian còn lại của slot đủ cho timeout đã cấu hình. Cờ SUPPORT_CHAT_ALLOW_EXTERNAL_QUESTIONS mặc định tắt. Chỉ bật trong test bind fake provider hoặc sau khi có quy tắc duyệt câu hỏi và quyền chạy thật. QA-LIVE-01 còn cần quota và dữ liệu test được duyệt.
- Payload dự kiến chỉ gồm chỉ dẫn tư vấn cố định và câu hỏi hiện tại đã qua kiểm tra. Cần chốt cách xử lý mật khẩu, token, dữ liệu thanh toán, định danh cá nhân và nội dung chưa phân loại được trước khi mở free text. Actor ID, dữ liệu tài khoản, Booking, Invoice và transcript nằm ngoài payload. Log chỉ dùng metadata an toàn, loại nội dung câu hỏi, câu trả lời, secret, cookie và upstream body.
- Dùng widget tiếng Việt trong CustomerLayout. Domain service gọi domain API, dùng shared Axios và AbortSignal. Widget có trạng thái ban đầu, đang gửi, thành công, lỗi và retry. Một lượt pending tại một thời điểm trên widget. Giữ draft và ID khi có thể phục hồi. Khi retry thành công sau mất response, gộp kết quả theo request_id hoặc ID message để tránh lặp. Đóng widget hủy chờ và giữ nguyên lượt chưa rõ kết quả. Thử lại giữ nguyên payload, kể cả session_id null của lượt đầu. UI khóa Thử lại theo Retry-After hợp lệ. Muốn bỏ lượt để đổi câu hỏi, người dùng xác nhận sau khi được thông báo lượt cũ có thể đã lưu. Khi logout, hết phiên hoặc đổi tài khoản, hủy chờ và loại draft, UUID, session ID, transcript cùng response cũ khỏi UI. Worker vẫn có thể hoàn tất vào tài khoản đã xác thực lúc nhận request. Transcript đang mở tồn tại trong bộ nhớ UI. Các lượt sau dùng session ID đã nhận để lưu cùng phiên. Provider nhận từng câu hỏi độc lập theo D4.
- Hỗ trợ mở/đóng và gửi bằng bàn phím, nhãn ô soạn, thông báo lỗi và trạng thái đang gửi. Kiểm bố cục trên mobile, tablet, desktop. Render câu trả lời dưới dạng text an toàn.

[Frontend guide](../ai-frontend-development-guide.md) · [Auth interceptor](../../frontend/src/services/authSession.js)

## 6. Ma trận bằng chứng cho triển khai

| Bằng chứng | Oracle quan sát được | Môi trường / task |
| --- | --- | --- |
| HTTP happy path và replay | 201 với shape mẫu, 200 cùng ID ở replay, đúng owner và đúng hai sender, replay thành công không gọi lại provider | Laravel Feature + MySQL cô lập + fake provider. CHAT-01 / QA-API-01 |
| Quyền, CSRF và input | Guest hoặc bearer-only theo D2, HOST, ADMIN, inactive/blocked, CUSTOMER B truy cập session của A, ID vắng, thiếu CSRF, JSON hoặc message sai. Status đúng theo bảng. Request bị từ chối không gọi provider và không ghi chat. | Feature HTTP và browser cho cookie/CSRF. QA-API-01 / QA-UI-01 |
| Payload và phân loại lỗi upstream | Transport fake chỉ nhận trường được duyệt. Lỗi kết nối, timeout, upstream 429 và response rỗng/sai shape có mapping và số lượt gọi đã định. | Fake HTTP với chặn request ngoài fixture. PROVIDER-01 |
| Tính toàn vẹn và phục hồi | Lỗi giữa hai insert rollback. Mất response sau commit phát lại cặp cũ. Hai kết nối cạnh tranh cùng UUID chỉ commit một cặp. Lần thực thi cũ bị từ chối khi đến muộn. | MySQL/InnoDB cô lập, kiểm đúng schema trước khi ghi, fake provider điều khiển độ trễ. DATA-01 / RECOVERY-01 / QA-API-01 |
| React và browser thật | Node kiểm request, mapping lỗi và cancellation. Browser kiểm cookie/CSRF, gửi–nhận, lỗi/retry, logout/đổi tài khoản, bàn phím và viewport. Đối chiếu dữ liệu lưu và dọn fixture/dịch vụ tạm. | Node, lint, build và React/Laravel thật với fake provider. QA-UI-01 |
| Provider thật | Một câu hỏi tổng hợp đã duyệt đi qua widget và adapter thật. UI và dữ liệu lưu khớp. Metadata evidence đã loại secrets. | QA-LIVE-01 sau khi owner cấp quyền, model/key và quota. |
| Hồi quy và nghiệm thu | Kết quả PHPUnit, Node, lint/build và từng lần chạy browser được ghi riêng. UC17 chỉ đổi trạng thái sau khi đạt bằng chứng bắt buộc và có xác nhận owner theo D1. | `php artisan test`, Node, `npm run lint`, `npm run build`, hồ sơ CLOSE-01. |

### Bằng chứng RECOVERY-01

- MySQL Docker cô lập, suite ChatRecovery qua Laravel HTTP kernel và fake provider. Đạt 5 test / 91 assertions cho gửi trùng ở hai tiến trình, bỏ response sau commit, kết quả đến muộn, retry khi hết quyền và kiểm ngân sách slot. Runner xác nhận worker đã thoát và dọn tài nguyên test. [Kết quả MySQL](../../.agent-workspace/tv3/ac-env-01/65325683a8d6473891a3188eeb6fc4f2/tests.txt)
- Edge với Vite và HTTP fixture đạt 4/4 checkpoint. Bao gồm giữ nguyên payload qua Retry-After, đóng/mở widget, xác nhận bỏ lượt, và phản hồi success/401 đến muộn sau logout, đổi tài khoản hoặc hết phiên. Chạy lại bằng node tests/support/uc17RecoveryBrowser.mjs từ frontend. Cookie/CSRF trên browser với Laravel thật thuộc QA-UI-01.
- Hồi quy ChatHttp và ChatData trên MySQL cô lập, PHPUnit Unit, Node, lint và build đạt. Gemini API thật chưa được gọi.

### Bằng chứng QA-API-01

Chạy ngày 2026-11-22 (Asia/Bangkok). Mỗi suite chạy qua runner TV3 trên một schema MySQL Docker test riêng. Các suite HTTP bind fake ChatProvider, dùng key giả và chặn HTTP ngoài fixture. ChatData thực thi lớp persistence trực tiếp. Runner xác nhận danh tính database và dọn schema, tài khoản test cùng các worker thuộc lượt chạy.

| Suite | Kết quả | Bằng chứng |
| --- | --- | --- |
| ChatHttp | 9 test, 194 assertions, exit 0 | [Kết quả](../../.agent-workspace/tv3/ac-env-01/d801ea7022d34d9ea888400587854806/tests.txt) |
| ChatData | 7 test, 94 assertions, exit 0 | [Kết quả](../../.agent-workspace/tv3/ac-env-01/b84726ab2686405e96a0ef70653e2fd3/tests.txt) |
| ChatRecovery | 5 test, 91 assertions, exit 0 | [Kết quả](../../.agent-workspace/tv3/ac-env-01/92a567d1cbb54e8ca71addff9a3109ed/tests.txt) · [Cleanup worker](../../.agent-workspace/tv3/ac-env-01/92a567d1cbb54e8ca71addff9a3109ed/worker-cleanup.json) |

- **QAPI-01–QAPI-03 đạt.** JSON 201/200 khớp projection và hàng MySQL. Owner và sender do server xác định. Quyền và trạng thái tài khoản được kiểm trước validation. CSRF thiếu hoặc sai trả 419 khi bật kiểm trong fixture HTTP. Biên 2.000/2.001 ký tự Unicode và JSON/ID sai kiểu trả đúng kết quả.
- **QAPI-04–QAPI-06 đạt.** Kiểm mã 409 khi lượt hết hạn, hết số lần xử lý hoặc mất kết quả. Kiểm 429, 503, 504, Retry-After và dữ liệu còn lại sau lỗi. Ba suite đạt và tài nguyên test đã dọn. Diff giới hạn trong file test HTTP và đặc tả.
- Phạm vi kiểm chứng là Laravel HTTP Feature và MySQL cô lập. Cookie/XSRF thực trong browser thuộc QA-UI-01. Gemini API thật chưa được gọi.

### Bằng chứng QA-UI-01

Chạy ngày 2026-11-22 (Asia/Bangkok). Suite `ChatBrowser` dùng Edge headless 158.0.4370.33, React/Vite và Laravel HTTP trên hai cổng loopback, cùng MySQL Docker 8.4.13 trên schema/user riêng. Bootstrap QA gắn fake `ChatProvider`, buộc dùng key giả và chặn HTTP outbound. Bảy checkpoint đạt, runner exit 0. [Bằng chứng Edge](../../.agent-workspace/tv3/ac-env-01/0b7eb6317ff049a5a32569d762660af2/browser.json) · [Kết quả runner](../../.agent-workspace/tv3/ac-env-01/0b7eb6317ff049a5a32569d762660af2/result.json)

| Ca | Kết quả lượt cuối |
| --- | --- |
| QUI-01 | Guest bị chuyển đến đăng nhập. HOST và ADMIN nhận 403, request CUSTOMER thiếu CSRF nhận 419. Browser có cookie session HttpOnly và cookie XSRF. Các request bị từ chối giữ nguyên dữ liệu chat và số lần gọi fake. |
| QUI-02 | Hai thao tác gửi trong lúc fake đang xử lý chỉ phát một POST. Hai lượt thành công 201 cùng session, bốn tin nhắn được lưu đúng thứ tự và đúng sender. |
| QUI-03a | Laravel trả 504 khi fake timeout và 503 khi fake báo giới hạn. Widget giữ payload, hiện thông báo tiếng Việt, khóa thử lại trong thời gian `Retry-After` và phục hồi với cùng UUID. |
| QUI-03b | Laravel trả 429 khi đạt giới hạn thật của route. Edge đọc được `Retry-After` qua CORS, widget đếm ngược và giữ UUID khi thử lại. Request 429 không ghi chat hoặc gọi fake. |
| QUI-04 | Sau khi MySQL commit, Edge làm mất phản hồi 201. Thử lại trả 200 cùng ID tin nhắn và giữ nguyên số bản ghi, số lần gọi fake. Giữ lượt bảo toàn UUID. Xác nhận bỏ lượt cho phép câu tiếp theo dùng UUID mới. |
| QUI-05 | Phiên A bị vô hiệu hóa khi XHR còn chờ. Edge quan sát `net::ERR_ABORTED` khi đóng widget. B đăng nhập và gửi lượt riêng, không thấy nội dung hoặc draft A. Chat trả 401 khi phiên B bị vô hiệu hóa và UI chuyển về đăng nhập. |
| QUI-06 | Enter mở dialog, Tab và Shift+Tab giữ focus trong dialog, Escape đóng và trả focus về nút mở. Ở 360, 768 và 1440 px, vùng tin nhắn cuộn được, độ tràn ngang 0 px và các nút nằm trong viewport. |

- Đối chiếu cuối lượt ghi nhận **3 session, 12 lượt, 24 tin nhắn, 14 lần gọi fake và 0 lần thử HTTP outbound**. Edge, Vite, Laravel đã thoát. Profile Edge, schema, user và kết nối test đã dọn. Fingerprint DB dev trước–sau khớp. [Đối chiếu MySQL](../../.agent-workspace/tv3/ac-env-01/0b7eb6317ff049a5a32569d762660af2/fixture-after.json) · [Dọn trình duyệt](../../.agent-workspace/tv3/ac-env-01/0b7eb6317ff049a5a32569d762660af2/browser-cleanup.json) · [Dọn MySQL](../../.agent-workspace/tv3/ac-env-01/0b7eb6317ff049a5a32569d762660af2/cleanup.json)
- Hồi quy `ChatHttp` đạt 9 test / 194 assertions trên MySQL cô lập. Node đạt 439 test. Lint và build exit 0, còn 3 cảnh báo Fast Refresh và cảnh báo bundle trên 500 kB. [Kết quả ChatHttp](../../.agent-workspace/tv3/ac-env-01/ffebbca6d27143bc9ea413589bd62a72/tests.txt)
- Lượt đầu hết thời gian tại bước đăng nhập của harness. Lượt thứ hai đạt 6/7 checkpoint, thiếu sự kiện Enter của Edge và có fingerprint DB dev chênh lệch dù số dòng từng bảng giữ nguyên. Cả hai lượt đã dọn tài nguyên test. Lượt cuối chờ form sẵn sàng, phát đủ sự kiện phím và xác nhận fingerprint khớp. [Lượt đầu](../../.agent-workspace/tv3/ac-env-01/c1a2a33845c446499718795caddd9d52/browser.json) · [Lượt thứ hai](../../.agent-workspace/tv3/ac-env-01/6c85c7dba67849b8bcf5af85b5ec87a6/result.json)
- QA-UI-01 đạt kỹ thuật và đã được owner nghiệm thu. Phạm vi browser là Edge headless với fixture tổng hợp. Kiểm chứng Gemini thật được ghi riêng tại QA-LIVE-01.

### UC17-GUEST-01 — Tư vấn công khai và giao diện lễ tân

Phạm vi mở rộng chạy trên hai trang `/` và `/search` cho khách vãng lai. Khu vực `/customer` dùng cùng lễ tân 2D và khung hội thoại. Tin của trợ lý ở trái có avatar và nhãn, tin của khách ở phải có nhãn riêng. Chuyển động CSS được tắt khi người dùng chọn giảm chuyển động. Nhân vật được tạo bằng ImageGen, xuất thành WebP trong suốt 320 × 320. [Asset lễ tân](../../frontend/src/assets/stayhub-receptionist.webp)

- API `POST /api/public/chat/messages` dùng phiên web first-party và CSRF. Câu tư vấn chỉ có thể gửi chủ đề chuẩn hóa tới provider. Booking/hóa đơn được xử lý trong Laravel và dẫn đến `/customer/bookings` hoặc `/customer/invoices` qua đăng nhập. Widget công khai giữ hội thoại trong bộ nhớ, xóa khi đóng hoặc rời trang, không chuyển vào lịch sử của Customer.
- Luồng Gợi ý chỗ nghỉ trả tối đa ba phòng công khai từ Catalog. Thẻ dùng ID do Laravel trả về để mở `/properties/:propertyId` và `/rooms/:roomId`. Bộ lọc chat hỗ trợ số khách và ngân sách. Câu hỏi cần khu vực hoặc ngày cụ thể được dẫn về `/search`. Danh sách Catalog chưa xác nhận tình trạng phòng trống theo ngày.
- Giới hạn công khai là 500 ký tự, 5 request/phút theo phiên, 15 theo IP, 30 trên toàn route và một lượt provider công khai đồng thời trong trần hai lượt dùng chung. Khóa chống trùng UUID tồn tại 2 phút. Cache lưu metadata có thời hạn với khóa định danh băm. API Customer hiện hành giữ hợp đồng và cơ chế lưu, retry, bỏ lượt.
- Widget công khai mặc định tắt. Để xem trên local, bật `VITE_ENABLE_GUEST_CHAT=true` cho Vite và `SUPPORT_GUEST_CHAT_ENABLED=true` cho Laravel. `SUPPORT_GUEST_CHAT_ALLOW_PROVIDER` mặc định tắt. Khi cờ provider tắt, tư vấn sử dụng nội dung có sẵn và Catalog.

Kiểm thử ngày 2026-11-22 (Asia/Bangkok) trên MySQL Docker 8.4.13. Các lượt Edge chạy React/Vite và Laravel thật trên loopback, gắn fake `ChatProvider` và chặn HTTP outbound.

| Suite | Kết quả | Bằng chứng |
| --- | --- | --- |
| `GuestChatBrowser` | 5/5, exit 0. Mở bằng bàn phím, 360/768/1440 px, giảm chuyển động, link chi tiết, hủy request khi rời trang, login và thẻ Customer | [Edge](../../.agent-workspace/tv3/ac-env-01/86c4008d3dd04e7eb63567cdbc1f2190/browser.json) · [Ảnh 360 px](../../.agent-workspace/tv3/ac-env-01/86c4008d3dd04e7eb63567cdbc1f2190/guest-widget-360.png) |
| `ChatBrowser` | 7/7, exit 0. Hồi quy Customer sau đổi giao diện | [Edge](../../.agent-workspace/tv3/ac-env-01/06517cc77555487ba0df2267964a30e5/browser.json) |
| `GuestChat` | 6 test / 90 assertions, exit 0 | [PHPUnit](../../.agent-workspace/tv3/ac-env-01/ca05ba31fba1441198aa27daf421dfc0/tests.txt) |
| `ChatHttp` | 9 test / 194 assertions, exit 0 | [PHPUnit](../../.agent-workspace/tv3/ac-env-01/f3d5a0c8ee274c57922e59748715a7b0/tests.txt) |
| `ChatRecovery` | 5 test / 91 assertions, exit 0. 9 worker đã thoát | [PHPUnit](../../.agent-workspace/tv3/ac-env-01/9e0873df312a41b3a0887b3ff187baec/tests.txt) |

Browser công khai ghi nhận **2 lượt fake với prompt chuẩn hóa, 0 HTTP outbound**, và không có hàng mới trong `chat_sessions`, `chat_messages`, `chat_turns`. Các runner exit 0, dọn tài nguyên test và xác nhận fingerprint DB dev trước–sau khớp. Node đạt 442 test, lint và build exit 0 với ba cảnh báo Fast Refresh và một cảnh báo bundle trên 500 kB. Task đạt kỹ thuật và được owner nghiệm thu tại CLOSE-01. [Đối chiếu MySQL](../../.agent-workspace/tv3/ac-env-01/86c4008d3dd04e7eb63567cdbc1f2190/fixture-after.json) · [Dọn MySQL](../../.agent-workspace/tv3/ac-env-01/86c4008d3dd04e7eb63567cdbc1f2190/cleanup.json) · [Dọn trình duyệt](../../.agent-workspace/tv3/ac-env-01/86c4008d3dd04e7eb63567cdbc1f2190/browser-cleanup.json)

### UC17-QA-LIVE-01 — Lượt Gemini thật cho Guest và Customer

Owner duyệt hai lần gọi thật, mỗi luồng một lần. Chạy ngày 2026-11-22 (Asia/Bangkok) trên Edge 158 và MySQL Docker 8.4.13 cô lập. Chỉ chạy `gemini-3.5-flash-lite` với câu “Nêu 3 điều nên kiểm tra trước khi đặt phòng, bằng tiếng Việt và tối đa 60 từ.” Bootstrap QA đối chiếu câu nhập và chủ đề đã chuẩn hóa của Guest rồi chuyển đúng câu mẫu cho adapter thật. Cơ chế này thuộc bootstrap test. Cấu hình ngoài lượt QA giữ nguyên.

| Luồng | Gemini | Laravel | Độ dài theo bộ kiểm tra |
| --- | --- | --- | --- |
| Guest | 1 lượt, HTTP 200, `STOP`, 1.620 ms | HTTP 200 | 84 từ, vượt 60 |
| Customer | 1 lượt, HTTP 200, `STOP`, 1.349 ms | HTTP 201 | 71 từ, vượt 60 |

**Kết quả kỹ thuật FAIL, runner exit 1.** Hai request đã đến Gemini và không retry. Bộ kiểm tra đếm các chuỗi chữ cái Unicode và dừng từng ca ở assertion quá 60 từ, trước khi xác nhận nội dung hiển thị trong widget hoặc gửi replay Customer. Hai kiểm tra đó đạt với fake, còn **NOT VERIFIED** trong lượt live. Audit MySQL live xác nhận 1 session và 1 lượt `SUCCEEDED` với đúng 2 tin nhắn `USER`/`ASSISTANT`, thuộc Customer test. Guest không tạo bản ghi lịch sử. [Metadata hai lượt](../../.agent-workspace/tv3/ac-env-01/99e8560e4a8f40c8933adef7ddcc2e61/gemini-attempts.json) · [Kết quả Edge live](../../.agent-workspace/tv3/ac-env-01/99e8560e4a8f40c8933adef7ddcc2e61/browser.json) · [Audit MySQL](../../.agent-workspace/tv3/ac-env-01/99e8560e4a8f40c8933adef7ddcc2e61/fixture-after.json)

Preflight cuối đạt 2/2 trên Edge với fake và 0 HTTP outbound thật. Guard chặn sai URL, sai payload, sai phương thức và gửi trùng ở từng luồng. Hai preflight trước cũng đạt kiểm tra browser, nhưng runner báo bảng `cache` của DB dev thay đổi trong thời gian chạy. Cả hai đã dọn tài nguyên test. Ở preflight cuối và lượt live, fingerprint DB dev trước–sau khớp. Lượt live đã dọn Edge, Vite, Laravel, profile, schema, user và kết nối test. Artifact chỉ chứa metadata và digest đã lọc của nội dung, không chứa key, cookie hoặc phản hồi thô. [Guard preflight](../../.agent-workspace/tv3/ac-env-01/5ea3576b538a48798ac7d9f7f77d87b2/guard-preflight.json) · [Edge fake](../../.agent-workspace/tv3/ac-env-01/5ea3576b538a48798ac7d9f7f77d87b2/browser.json) · [Dọn trình duyệt](../../.agent-workspace/tv3/ac-env-01/99e8560e4a8f40c8933adef7ddcc2e61/browser-cleanup.json) · [Dọn MySQL](../../.agent-workspace/tv3/ac-env-01/99e8560e4a8f40c8933adef7ddcc2e61/cleanup.json)

**Tiêu chí cập nhật theo owner.** Mốc 60 từ trong câu mẫu là mục tiêu về độ gọn. Bài kiểm tra chỉ ghi số từ vào metadata và tiếp tục kiểm tra hiển thị, dữ liệu lưu và replay. Điều kiện nội dung đối với câu mẫu là trả lời bằng tiếng Việt, đúng chủ đề và đưa ra ba điều hữu ích cần kiểm tra trước khi đặt phòng. Kiểm tra tự động chỉ xác nhận các dấu hiệu cơ bản về ngôn ngữ và chủ đề. Việc đánh giá độ đúng của lời tư vấn cần quan sát nội dung. Preflight cập nhật đạt **2/2** với câu fake dài **66 từ**, Guest hiển thị mà không tạo lịch sử và Customer hiển thị, lưu, replay `200` mà không tăng lượt gọi. Lượt live cũ giữ nguyên bằng chứng và chưa xác minh được nội dung, hiển thị hoặc replay. Không phát sinh lượt Gemini thật trong lần cập nhật tiêu chí. [Edge fake sau cập nhật](../../.agent-workspace/tv3/ac-env-01/d954f3b0240c4df9a110a33a60233dc6/browser.json) · [Kết quả runner](../../.agent-workspace/tv3/ac-env-01/d954f3b0240c4df9a110a33a60233dc6/result.json)

**Lượt live chạy lại sau khi owner duyệt tiêu chí cập nhật.** Edge và MySQL Docker 8.4.13 cô lập đạt **2/2 checkpoint, runner exit 0**. Cùng câu mẫu cũ đi qua đúng **2 lượt Gemini thật**, mỗi luồng một lượt, không retry. Bài kiểm tra xác nhận hiển thị và lưu/replay trước khi đánh giá dấu hiệu tiếng Việt và các nhóm nội dung tư vấn. Số từ chỉ dùng để ghi nhận.

| Luồng | Gemini | Laravel và dữ liệu | Nội dung quan sát qua bộ kiểm tra |
| --- | --- | --- | --- |
| Guest | 1 lượt, `200 / STOP`, 1.184 ms | `200`, widget hiển thị đúng nội dung API, không tạo lịch sử | 67 từ. Có các nhóm chi phí, đổi/hủy, vị trí, tiện nghi và đánh giá chỗ nghỉ. |
| Customer | 1 lượt, `200 / STOP`, 1.065 ms | `201`, widget hiển thị đúng nội dung API. MySQL có một session, một lượt và một cặp tin nhắn. Replay `200`, cùng ID và nội dung, số lượt Gemini giữ nguyên. | 73 từ. Có các nhóm chi phí, đổi/hủy, tiện nghi và đánh giá chỗ nghỉ. |

Preflight fake đạt 2/2 và 0 lượt Gemini thật. Lượt live dọn Edge, Vite, Laravel, profile và tài nguyên MySQL test. Fingerprint DB dev khớp trước–sau. Artifact chỉ ghi metadata và digest. Kết luận về nội dung áp dụng cho câu mẫu và các nhóm tư vấn đã quan sát qua kiểm tra tự động. Kết quả `FAIL` của lượt live đầu vẫn được lưu ở trên. [Preflight](../../.agent-workspace/tv3/ac-env-01/5f94d01359294ea4b5b109a63da4f87b/browser.json) · [Edge live](../../.agent-workspace/tv3/ac-env-01/0bb30bc8c3884b23b80e40ab2d94ee5e/browser.json) · [Ngân sách Gemini](../../.agent-workspace/tv3/ac-env-01/0bb30bc8c3884b23b80e40ab2d94ee5e/gemini-attempts.json) · [Audit MySQL](../../.agent-workspace/tv3/ac-env-01/0bb30bc8c3884b23b80e40ab2d94ee5e/fixture-after.json) · [Cleanup](../../.agent-workspace/tv3/ac-env-01/0bb30bc8c3884b23b80e40ab2d94ee5e/cleanup.json)

## 7. Phụ thuộc triển khai

TV3 sở hữu domain chat và dữ liệu chat. TV1 cung cấp contract Auth/User hiện hành. Hai điểm ghép CHAT-01 đã được owner duyệt là [backend/routes/api.php](../../backend/routes/api.php) và [frontend/src/layouts/CustomerLayout.jsx](../../frontend/src/layouts/CustomerLayout.jsx). UC61 xác định dependency dữ liệu Catalog/Booking/Services và FAQ khi cần ngữ cảnh nội bộ. T01–T03 vẫn BLOCKED TV2 theo checklist.

Chuỗi thực hiện UC17 V1 gồm CONTRACT-01 → DATA-01 và PROVIDER-01 → CHAT-01 → RECOVERY-01 → QA-API-01 → QA-UI-01 → GUEST-01 → QA-LIVE-01 → CLOSE-01. DATA-01 được duyệt trong phạm vi migration bổ sung và kiểm chứng trên MySQL test cô lập.

## 8. UC17-CLOSE-01

**TECHNICAL PASS kiểm tài liệu. OWNER ACCEPTED UC17 V1 và UC17-GUEST-01 ngày 2026-11-22 (Asia/Bangkok).** Owner chốt D1 đóng UC17 V1 riêng. Checklist TV3 đạt **17/18**, UC61 giữ mở. [Checklist TV3](TV3_CHECKLIST.md#2-checklist-18-uc)

| Phạm vi nghiệm thu | Bằng chứng đã đối chiếu |
| --- | --- |
| Quyền, CSRF, lỗi và persistence | QA-API-01 đạt 21 test / 379 assertions trên MySQL cô lập. Bao gồm quyền, CSRF, validation, commit/rollback, giới hạn gửi và recovery. [QA-API-01](#bằng-chứng-qa-api-01) |
| Widget Customer và chuyển phiên | QA-UI-01 đạt 7/7 trên Edge với React, Laravel và fake provider. Lượt hồi quy sau GUEST-01 cũng đạt 7/7. [QA-UI-01](#bằng-chứng-qa-ui-01) |
| Chat vãng lai và thẻ Catalog | GUEST-01 đạt 5/5 trên Edge với fake. Thẻ dẫn tới khách sạn/phòng công khai, câu hỏi tài khoản dẫn tới trang đăng nhập phù hợp. [UC17-GUEST-01](#uc17-guest-01--tư-vấn-công-khai-và-giao-diện-lễ-tân) |
| Gemini thật ở cả hai luồng | Lượt chạy lại QA-LIVE-01 đạt 2/2 trên Edge với MySQL cô lập, đúng một lượt gọi mỗi luồng. Guest không ghi lịch sử. Customer lưu đúng cặp tin nhắn và replay `200` không gọi thêm Gemini. Giữ kết quả FAIL của lượt live đầu theo tiêu chí độ dài cũ. [QA-LIVE-01](#uc17-qa-live-01--lượt-gemini-thật-cho-guest-và-customer) |

UC61, tích hợp ngữ cảnh đầy đủ và việc mở tính năng cho người dùng thực tế còn ngoài phạm vi V1 đã nghiệm thu. Tác vụ dọn metadata định kỳ theo D3 và chính sách xử lý câu hỏi tự do theo D4 cần phạm vi riêng. CLOSE-01 đối chiếu artifact có sẵn, không chạy thêm QA chức năng, không thay đổi cơ sở dữ liệu và gọi Gemini mới **0 lần**.
