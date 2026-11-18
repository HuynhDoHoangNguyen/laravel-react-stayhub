# Kiến trúc hệ thống StayHub

Tài liệu này mô tả cấu trúc và quy chuẩn kỹ thuật dùng chung cho TV1, TV2 và TV3.
Cập nhật tài liệu khi cấu trúc hoặc quy chuẩn kiến trúc thay đổi.
Ghi tiến độ, kiểm thử và nghiệm thu vào checklist của phạm vi tương ứng.

## 1. Tổng quan hệ thống

StayHub gồm hai ứng dụng triển khai độc lập trong cùng repository:

- **Frontend** — React 19, Vite, React Router, JavaScript/JSX và Tailwind CSS.
- **Backend** — Laravel 12, PHP 8.2+, Sanctum, Eloquent và PHPUnit.
- **Database** — MySQL hoặc MariaDB trong môi trường XAMPP, dùng InnoDB cho các bảng nghiệp vụ.
- **Chatbot** — tích hợp provider qua Backend khi triển khai tính năng. Backend xác định dữ liệu được phép đưa vào ngữ cảnh.

Backend là một ứng dụng Laravel tổ chức theo domain và phân lớp. Frontend tích hợp qua HTTP/JSON. Các endpoint file có contract stream hoặc multipart tương ứng.

~~~mermaid
flowchart LR
    P[React Page / Component] --> F[Domain Service]
    F --> FM[Frontend Mapper]
    F --> A[Domain API / Shared Axios]
    A --> R[Route / Middleware]
    R --> C[Controller]
    C --> V[Input Validator / Request DTO]
    C --> S[Service / QueryService]
    S --> RP[Repository]
    RP --> M[Eloquent Model]
    M --> DB[(MySQL / InnoDB)]
    S --> RM[Response Mapper / DTO]
    RM --> C
    C --> O[HTTP Response]
    O --> F
~~~

| Thành phần | Trách nhiệm |
| --- | --- |
| React | Điều hướng, nhập liệu, trình bày dữ liệu và trạng thái của request |
| Laravel | Xác thực, phân quyền, kiểm tra đầu vào, quyết định nghiệp vụ và bảo toàn giao dịch |
| Database | Dữ liệu bền vững, khóa, quan hệ, unique constraint và chỉ mục |
| Provider bên ngoài | Xử lý yêu cầu theo dữ liệu và quyền do Backend giới hạn |

### Ranh giới nhóm

| Owner | Domain | Điểm tích hợp |
| --- | --- | --- |
| TV1 | Identity, Profile, Property Catalog | Session, User, Property, Room và điều kiện hiển thị công khai |
| TV2 | Availability, Booking, Operations, dịch vụ phát sinh | Trạng thái Booking, lịch phòng và snapshot giá |
| TV3 | Invoice, Review, Reporting, FAQ và Chat | Billing sau Booking, đánh giá theo quyền và báo cáo theo dữ liệu đã xác nhận |

Chi tiết giao việc nằm trong [tài liệu phân công](docs/stayhub-team-work-assignment.md).
Thống nhất contract với owner khi task phụ thuộc dữ liệu hoặc endpoint của domain khác.

## 2. Cấu trúc repository

~~~text
stayhub/
├── backend/
│   ├── app/                         # Laravel application
│   ├── routes/                      # Web và API
│   ├── database/                    # Migrations, factories và seeders
│   └── tests/                       # Unit và Feature
├── frontend/
│   ├── src/                         # SPA
│   └── tests/                       # Node tests cho logic và HTTP adapter
├── docs/                            # Use case, ERD, hướng dẫn và checklist
├── .agents/skills/                  # Hướng dẫn thực thi theo trách nhiệm
├── ARCHITECTURE.md
└── README.md
~~~

Hai ứng dụng có dependency, lệnh chạy và quy trình build riêng.

### Cấu hình môi trường local

Laravel, Vite và Docker Compose dùng chung `.env` tại repository root; `.env.example` tại root là template duy nhất. Không tạo `.env` trong backend/frontend. Laravel giữ base path tại `backend/` và chuyển environment path về root; Vite giữ root tại `frontend/` và dùng envDir tại root.

Biến môi trường của tiến trình được ưu tiên hơn file, giúp runner QA sử dụng database, session và cấu hình riêng. Không thay đổi guard hoặc bỏ qua giới hạn database QA để chạy test. Configuration cache của Laravel bỏ qua việc đọc `.env`; chỉ xóa/dựng lại cache có chủ đích trong đúng môi trường.

Chỉ các biến `VITE_*` được công khai cho mã frontend; không dùng tiền tố này cho secrets. Compose chỉ nhận các biến được khai báo rõ trong YAML. Giữ nguyên `APP_KEY` và credentials của môi trường hiện có; thay đổi `MYSQL_*` không cập nhật credentials trong volume Docker đã khởi tạo.

Khởi động lại Laravel và Vite sau khi sửa root `.env`; `artisan serve` hiện tại không theo dõi thay đổi file này ở root. Hướng dẫn thiết lập và mapping kết nối host PHP → MySQL Docker nằm trong README. Backup env local nằm tại `.local-env-backups/` được Git ignore; không đưa secrets vào evidence hoặc tài liệu.

Contract tích hợp xác định URL, method, input, output, lỗi, quyền và hướng tương thích.
Khi tài liệu hoặc template mô tả một endpoint, kiểm tra route và consumer thực tế trước khi dựa vào endpoint đó.

## 3. Kiến trúc frontend

### 3.1 Cấu trúc và chiều phụ thuộc

~~~text
frontend/src/
├── main.jsx
├── App.jsx
├── routes/AppRouter.jsx
├── context/                         # Auth và trạng thái toàn ứng dụng
├── layouts/                         # Public, Customer, Host, Admin
├── pages/                           # Màn hình theo route và vai trò
├── components/                      # UI primitives và component theo feature
├── hooks/                           # Logic React tái sử dụng
├── services/                        # Điều phối request, mapping và workflow UI
├── api/                             # Domain API, shared Axios và chuẩn hóa lỗi
├── mappers/                         # Projection và normalization theo domain
├── utils/                           # Helper dùng chung
├── assets/
└── mocks/                           # Fixture DEV có điều kiện kích hoạt tường minh
~~~

~~~text
Route và Layout
  → Page / Component / Hook
    → Domain Service
      → Domain API → Shared Axios → Laravel
      ← Response JSON
      → Mapper → Dữ liệu cho UI
~~~

Các module đã có lớp Domain API giữ URL, method và cấu hình transport tại đó.
Module đang chuyển đổi có thể còn gọi shared Axios từ service. Kiểm tra contract và scope trước khi tách lớp.

### 3.2 Trách nhiệm từng lớp

#### Routes, layouts và context

- AppRouter gắn page vào layout theo vai trò. Layout sở hữu navigation, outlet và khung giao diện.
- Route guard dùng Auth state để điều hướng. Laravel thực thi quyền trên mọi request và resource.
- Context xử lý restore session và trạng thái người dùng. Request cũ phải tôn trọng baseline phiên khi đăng xuất hoặc đổi tài khoản.
- Chỉ thay đổi router, layout hoặc context dùng chung khi task cần và phạm vi file được xác nhận.

#### Pages, components và hooks

- Page giữ state màn hình và điều phối data loading. Component trình bày props, thu thập input và phát sự kiện.
- Hook dùng cho logic React có vòng đời hoặc state. Dùng AbortSignal hoặc request identity để tránh response muộn khi đổi filter, page, resource hoặc session.
- Xử lý loading ban đầu, refresh, empty thật, error và success. Giữ nội dung tiếng Việt, responsive và thao tác bàn phím.
- Mutation cần guard gửi trùng. Với kết quả chưa rõ do timeout hoặc mất phản hồi, dùng trạng thái phục hồi phù hợp để người dùng xác nhận trước khi gửi lại.

#### Domain service, API và mapper

- Service giữ signature dùng bởi UI, chuẩn hóa query/payload, gọi API và map kết quả. Giữ cùng module service cho các thao tác liên quan khi cùng chia sẻ contract.
- Domain API gọi shared Axios với URL, method, query, body và signal. Tôn trọng cơ chế Auth và lỗi của adapter.
- Mapper đặt tại src/mappers, thuần và có thể test bằng fixture độc lập. Mapper bảo toàn null, trường được lược bỏ, decimal string, ngày, links và meta theo contract.
- Kiểm tra response thành công ở điểm tiêu thụ. Dữ liệu rỗng hợp lệ, response sai cấu trúc và lỗi HTTP dẫn đến các trạng thái rõ ràng.
- UI chỉ format tiền bằng Intl.NumberFormat. Nguồn giá, phí và tổng thanh toán là Backend.

#### Shared Axios và lỗi

- Shared client đọc VITE_BACKEND_URL. Dùng origin như http://localhost:8000 để gọi cả route Web và route /api.
- Giữ Accept: application/json, cookie, XSRF và cơ chế phân biệt lỗi session/account với lỗi quyền domain.
- Giữ nguyên rejection identity và nhận diện cancellation nếu caller dựa vào chúng. Chuẩn hóa lỗi khi chuyển sang dữ liệu hiển thị.
- Chỉ gửi lại mutation khi workflow đã xác định cách retry. Các request GET có thể retry bằng trạng thái UI tương ứng.

### 3.3 Tái sử dụng template và mock

Dùng [StayNest template](docs/StayNest-Frontend-template/) làm nguồn tham khảo giao diện.
Chọn layout, card, form, dialog, bảng và design tokens phù hợp với theme StayHub.
Dữ liệu, quyền, route, tên trạng thái và luồng nghiệp vụ lấy từ StayHub.

Mock chỉ chạy khi có điều kiện DEV tường minh và phải được nhận diện ở UI khi cần.
Giữ guard chặn mutation dùng ID của mock. Kiểm thử tích hợp cần truy cập Laravel thật trên schema fixture.
Đối chiếu [hướng dẫn Frontend](docs/ai-frontend-development-guide.md) khi sửa giao diện hoặc dùng lại template.

## 4. Kiến trúc backend

### 4.1 Cấu trúc và trách nhiệm

~~~text
backend/
├── app/
│   ├── Common/                      # ApiResponse, PageResponse, constants
│   ├── DTOs/<Domain>/               # Requests và Responses
│   ├── Enums/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/<Domain>/
│   │   ├── Middleware/
│   │   └── Requests/               # Form Request cho luồng hiện có
│   ├── Mappers/                     # Shared adapter và mapper theo domain
│   ├── Models/
│   ├── Providers/
│   ├── Repositories/<Domain>/
│   ├── Services/<Domain>/
│   └── Validators/
│       ├── AttributeValidator.php
│       ├── Constraints/
│       └── <Domain>/
├── bootstrap/app.php
├── routes/
│   ├── web.php
│   ├── api.php
│   └── api/                         # File route theo domain đã tích hợp
└── tests/
~~~

| Lớp | Trách nhiệm chính | Điểm ghép |
| --- | --- | --- |
| Route | Ánh xạ method/URI, middleware và tên route | Controller |
| Middleware | Session, trạng thái tài khoản, role, throttle và CSRF theo route | Request đã qua kiểm soát truy cập chung |
| Controller | Lấy actor/input, gọi lớp thích hợp và trả HTTP response | Validator, Request DTO, Service, Common |
| Validator | Kiểm tra input hoặc quy tắc domain trên dữ liệu đã được cung cấp | DTO hoặc fact đã lấy qua Repository |
| Service / QueryService | Quyền theo workflow, thứ tự nghiệp vụ, transaction và điều phối | Repository, domain validator, mapper |
| Repository | Query scope, projection, eager load, pagination, lock và persistence | Eloquent Model và database |
| Model | Mapping DB, relationship, casts, fillable, scope và hành vi gắn với entity | Schema và domain |
| Mapper / Response DTO | Dựng output theo allowlist, serialize rõ kiểu và trường | Common response hoặc envelope đang dùng |

Endpoint read và mutation có thể dùng QueryService và Service riêng khi khác trách nhiệm.
Dùng constructor DI cho cộng tác viên bắt buộc. Container có thể tự resolve concrete class.
Chỉ thêm interface hoặc binding khi có nhiều implementation hoặc cần một điểm thay thế rõ ràng.

### 4.2 Validation, authorization và Request DTO

- Một root app/Validators. AttributeValidator bọc Symfony Validator, Constraints chứa constraint dùng chung, thư mục domain chứa quy tắc riêng.
- Kiểm tra input raw trước khi cast nếu cần giữ lỗi canonical ID, kiểu dữ liệu hoặc field-specific errors.
- Typed Request DTO mô tả input đã chuẩn hóa, không giữ Http Request, container hoặc trạng thái session. Actor truyền qua tham số riêng.
- Form Request hiện có tiếp tục xử lý HTTP validation ở các luồng đang dùng. Khi thay lớp, đối chiếu middleware và thứ tự lỗi, gồm cả content negotiation nếu có.
- Kiểm tra ownership tại ranh giới thích hợp, bằng policy hoặc quyền trong Service và query đã scope. Quyền theo resource được kiểm chứng phía Backend.
- Validator dùng fact đã truyền vào, giữ thuần và tránh query/lazy loading. Lấy fact cần khóa hoặc lookup trong Repository thông qua Service.
- Khi gọi Symfony Validator cho candidate trước lưu, truyền group tường minh. Kiểm tra nhóm nào active và tác động lên Model chưa hoặc đã lưu.

Ví dụ về vai trò của DTO đầu vào:

| Bước | Dữ liệu | Việc cần kiểm tra |
| --- | --- | --- |
| Trước validation | Raw route, query hoặc body | Phân biệt kiểu nguyên thủy, trường thiếu và giá trị không hợp lệ |
| Sau validation | Request DTO đã định kiểu | Chỉ chứa giá trị đã kiểm tra và đúng phạm vi workflow |
| Trong Service | Request DTO, actor và facts | Quyền, trạng thái, xung đột và điều kiện thay đổi theo dữ liệu |
| Trước persistence | Candidate model hoặc payload đã dựng | Constraints và validation group áp dụng cho thao tác cụ thể |

Tách raw DTO và typed DTO khi từng bước có ý nghĩa với contract, chẳng hạn cần báo lỗi ID trước khi dữ liệu bị ép kiểu.
Với endpoint đơn giản, dùng input hiện có nếu vẫn biểu đạt rõ ranh giới kiểm tra và workflow.

### 4.3 Mapping và serialization

- [EloquentObjectMapper](backend/app/Mappers/EloquentObjectMapper.php) nằm dưới app/Mappers cùng các mapper domain.
- Service/Repository cung cấp thuộc tính và relation đã tải. Mapper dùng allowlist và giữ casts, xử lý giá trị lồng nhau theo đúng projection cần trả.
- Symfony ObjectMapper phù hợp cho DTO projection khi nguồn và đích xác định. DTO phản hồi chỉ gồm dữ liệu tự định nghĩa như ID đã xóa có thể được dựng trực tiếp.
- Output DTO serialize tường minh, bảo toàn decimal string, thời gian ISO theo contract, nullable, trường bị lược bỏ và collection rỗng.
- Phân biệt projection Public, Customer, Host và Admin. Dữ liệu nội bộ có thể cần ẩn danh như tên tác giả Review trên Public.
- Mapper không gọi DB, kiểm tra quyền, thực hiện transaction hoặc tự tính lại nghiệp vụ.
- AppServiceProvider cấu hình engine Validator và ObjectMapper. Domain dùng lại adapter và cấu hình hiện có.

### 4.4 Service, Repository và log

- Service chọn thứ tự đọc, kiểm tra, khóa và ghi. Repository thực hiện Eloquent trên đúng connection và query scope.
- Lock đúng resource và thống nhất thứ tự lock giữa các workflow cạnh tranh. Đọc lại fact sau khi khóa nếu snapshot cũ có thể dẫn tới kết luận sai.
- Mapper chuẩn bị output cần thiết trước khi mutation commit nếu lỗi mapping phải rollback cùng dữ liệu.
- Với success log của mutation, dùng ranh giới commit thực tế. Log chỉ gồm thông tin cần để điều tra như ID kỹ thuật.
- Laravel báo lỗi ngoài dự kiến. Khi bắt exception, cần một mục đích như phân loại lỗi constraint, bù file hoặc chuyển lỗi nghiệp vụ theo contract.
- Giữ nguyên status, error code, field errors, privacy 404, conflict 409 và HTTP headers theo endpoint.

## 5. Hợp đồng API

### 5.1 URL, operation và response

API nghiệp vụ dùng tiền tố /api. Session và CSRF dùng Web routes ở root.
Tên route phục vụ routing, middleware, exception renderer và các công cụ kiểm chứng.
Dùng route registry và feature test để xác nhận URL/method trước khi đổi consumer.

| Endpoint nền tảng | Mục đích |
| --- | --- |
| GET /api/test | Kiểm tra kết nối Laravel |
| GET /sanctum/csrf-cookie | Khởi tạo CSRF cookie |
| POST /login | Xác thực và tạo session |
| POST /register | Đăng ký theo contract hiện hành |
| GET /api/me | Restore identity từ session ACTIVE |
| POST /logout | Xóa session theo contract Web |
| GET /api/profile | Đọc thông tin user hiện tại |

Common response hiện có cho JSON:

~~~json
{"data":{"id":10}}
~~~

~~~json
{
  "data": [],
  "links": {"first": "/api/resource?page=1", "last": "/api/resource?page=1", "prev": null, "next": null},
  "meta": {"current_page": 1, "last_page": 1, "per_page": 10, "total": 0}
}
~~~

Ví dụ dùng URL minh họa. Endpoint thật có thể trả additional fields như summary.
GET detail và POST create thường có một data envelope. GET list giữ meta và links khi API phân trang.
Catalog có các response success/message/data theo action. Auth và Profile có response user/message theo route đang dùng.
Endpoint trả file giữ MIME, cache và disposition theo contract.

ApiResponse/PageResponse phục vụ các JSON DTO và giữ nhánh tương thích cho Resource collection hiện có.
Giữ HTTP status và metadata khi chuyển từ Resource sang DTO.
DTO có thể phân biệt ba trường hợp sau:

- Field bị lược bỏ khi endpoint chưa load dữ liệu mở rộng.
- Field nullable khi giá trị đã được xác định và không có.
- Danh sách rỗng khi collection thực sự rỗng.

### 5.2 Lỗi và exception boundary

| HTTP | Ý nghĩa phổ biến | Nội dung cần giữ |
| --- | --- | --- |
| 200 | Đọc, cập nhật hoặc xóa có kết quả | Shape và message đã công bố |
| 201 | Tạo thành công | Projection mới và header nếu có |
| 204 | Thành công không có body | Giữ response trống theo contract |
| 401 | Chưa xác thực | Contract session của Laravel |
| 403 | Sai role, không có quyền hoặc tài khoản bị chặn | Mã lỗi và hành vi của Auth UI theo endpoint |
| 404 | Không tìm thấy hoặc privacy scope | Cùng response cho các trường hợp mà contract yêu cầu che giấu |
| 409 | Trùng dữ liệu hoặc workflow bị xung đột | Message/code đủ cho UI phục hồi |
| 419 | Lỗi CSRF | Giữ middleware, cookie và phản hồi theo Web contract |
| 422 | Input không hợp lệ | Thông báo và field errors hiện hành |
| 429 | Rate limit | Status và Retry-After nếu có |
| 500 | Lỗi ngoài dự kiến | Response an toàn ở môi trường production |

~~~json
{
  "message": "Dữ liệu không hợp lệ.",
  "errors": {"name": ["Tên là trường bắt buộc."]}
}
~~~

Laravel giữ framework errors, content negotiation và headers.
GlobalExceptionHandler hiện áp dụng theo allowlist route có tên của Invoice và Review. AppException sử dụng code/status/errors ở các route được hỗ trợ.
Các luồng dùng lỗi message-only hoặc validation mặc định vẫn giữ contract của chúng.
Khi refactor, kiểm tra thứ tự giữa middleware, role, định dạng ID, payload, ownership, trạng thái và kiểm tra trùng.
Cùng một status có thể kích hoạt các hành vi UI khác nhau. Cần đối chiếu error code, body và trạng thái session.

## 6. Xác thực và phân quyền

### 6.1 Sanctum SPA session

Ứng dụng first-party dùng Sanctum với cookie và session.
Frontend chỉ dùng thông tin user để hiển thị và điều hướng, Backend kiểm tra quyền cho request thực tế.

~~~mermaid
sequenceDiagram
    participant B as Browser / React
    participant L as Laravel
    participant D as Session store

    B->>L: GET /sanctum/csrf-cookie
    L-->>B: XSRF-TOKEN cookie
    B->>L: POST /login + credentials + X-XSRF-TOKEN
    L->>D: Tạo session
    L-->>B: Session cookie và user
    B->>L: GET /api/me + cookies
    L->>D: Xác minh session
    L-->>B: User JSON theo allowlist
~~~

Yêu cầu cấu hình:

- Axios gửi credentials và XSRF theo cấu hình dùng chung.
- CORS cho phép đúng origin Frontend và hỗ trợ credentials.
- SANCTUM_STATEFUL_DOMAINS chứa host/port Frontend, ví dụ localhost:5173.
- SESSION_DOMAIN và APP_URL phù hợp với các origin đang chạy.
- Cookie gắn theo host. Dùng cùng cách gọi localhost hoặc IP trong suốt luồng xác thực.

### 6.2 Kiểm tra quyền theo tầng

1. Middleware xác thực session với auth:sanctum trên route yêu cầu đăng nhập.
2. Middleware trạng thái tài khoản kiểm tra user ACTIVE tại các route được bảo vệ.
3. Role hoặc gate xác định nhóm ADMIN, HOST hoặc CUSTOMER.
4. Policy, Service và scoped query xác nhận quyền trên resource cụ thể theo use case.
5. Với mutation cạnh tranh, kiểm tra lại điều kiện cần thiết trong transaction trước khi ghi.

Host A chỉ thấy dữ liệu thuộc Host A trong luồng quản lý của mình.
Customer ID cho luồng sở hữu lấy từ actor đã xác thực.
Các endpoint Public chọn field theo audience và lọc theo visibility; ẩn dữ liệu cá nhân trước khi serialize.
Giữ rate limit theo route và chính sách session hiện hành khi thay đổi lớp validation hoặc service.

## 7. Kiến trúc dữ liệu

### 7.1 Nguồn schema và dữ liệu tham chiếu

Đối chiếu schema đang chạy với migration trước khi đưa ra quyết định persistence.
Dùng [ERD](docs/stayhub-erd.md) để xác định quan hệ mục tiêu và dùng Eloquent casts/relations để hiểu cách ứng dụng đọc dữ liệu.
Migrations, factories và seeders tương ứng nằm trong backend/database.
Luôn xác nhận schema đang chạy mà Laravel thực sự kết nối khi kiểm thử trên MySQL/XAMPP.

| Nhóm | Bảng nghiệp vụ chính |
| --- | --- |
| Identity | users |
| Catalog | properties, property_images, room_types, rooms, room_images, amenities, room_amenities |
| Operations | maintenances, services, bookings, booking_services |
| Billing | invoices, invoice_items |
| Engagement | reviews |
| Chat | faqs, chat_sessions, chat_messages |

### 7.2 Constraints, index và dữ liệu lịch sử

- FK bảo toàn quan hệ. Xem đầy đủ hành vi RESTRICT/CASCADE trước khi xóa.
- Dùng unique/index theo business key và query thực tế. Đối chiếu collation khi xác định tên trùng.
- Xem dữ liệu cũ, nullability, default, cast và consumer trước khi đổi field hoặc constraint.
- Lưu và truyền số tiền ở dạng chính xác theo schema/contract. Giá từ client chỉ là input tham chiếu khi use case cho phép.
- Snapshot tên và giá dịch vụ, giá phòng và số đêm để các cập nhật danh mục sau này không đổi lịch sử.
- Kiểm tra query plan, scope, order ổn định và eager loading với dữ liệu đại diện khi task liên quan hiệu năng.

### 7.3 Transaction và tài nguyên ngoài DB

- Các bước đọc/kiểm tra/ghi của cùng mutation dùng connection và transaction nhất quán.
- Cập nhật nhiều bản ghi liên quan trong một transaction khi nghiệp vụ yêu cầu atomicity.
- Kiểm tra retry, duplicate và read-after-write. Khóa bản ghi theo một thứ tự nhất quán.
- Ghi log hoặc hoàn thành tác vụ phụ cần success sau outer commit thực tế.
- File storage có ranh giới riêng. Xác định lúc tạo file, cập nhật DB, xóa file cũ và bù khi lỗi.
- Test cả rollback và commit, gồm lỗi giữa chừng, dữ liệu còn lại và cleanup đúng tài nguyên.

### 7.4 Test isolation

Tách XAMPP development khỏi schema test. Fixture phải có owner và vòng đời rõ ràng.
Các thao tác migrate/reset trong test chỉ trỏ vào DB được cấp riêng và đã đối chiếu cấu hình hiệu lực.
SQLite in-memory kiểm chứng được nhiều contract và rollback. Kiểm thử cạnh tranh, collation và FK theo engine thực tế dùng MySQL/MariaDB.
Thử nghiệm trình duyệt thật có thể dùng schema fixture riêng và session thật mà vẫn để nguyên schema làm việc.

## 8. Kiến trúc module nghiệp vụ

### 8.1 Identity và Profile

Auth xác thực bằng Sanctum và duy trì contract của Web routes.
Đăng nhập và đăng ký sử dụng AuthValidator, input DTO và service tương ứng.
AuthRepository giữ phần truy vấn và persistence đăng ký.
Đọc user hiện tại, đăng nhập, đọc/cập nhật Profile và upload avatar dùng AuthUserMapper với allowlist response chung khi trả đối tượng user.
Thay đổi mật khẩu có contract riêng. Giữ fingerprint phiên, throttle và thông báo theo luồng hiện hành.
Các mutation Profile hiện dùng Form Request. Mỗi luồng là một phạm vi chuyển đổi riêng.

Avatar là dữ liệu file theo quyền của user hiện tại. URL response có version để client refresh.
Giữ stream, cache headers và kiểm tra đường dẫn managed. Xử lý rollback/cleanup file theo kết quả ghi dữ liệu chính.

### 8.2 Property Catalog

Public Catalog chỉ đưa ra Property và Room đủ điều kiện hiển thị.
Repository lọc ownership và quan hệ giữa Property, Room và RoomType.
Các DTO công khai dùng allowlist. Trang chi tiết dựng ảnh, tiện nghi, phòng, giá tham khảo và tích hợp Review qua contract riêng.

Host Catalog điều phối theo quyền sở hữu Property.
Các thao tác trên ảnh kiểm soát dung lượng, kiểu file, ảnh đại diện và cleanup file cùng workflow persistence.
RoomType giữ định danh theo Property. Khi thêm/sửa tên, kiểm tra xung đột theo collation hiện hành trong phạm vi Property.
Khi xóa RoomType, khóa Property và RoomType rồi kiểm tra mọi Room tham chiếu. Chỉ xóa khi tập tham chiếu rỗng.
Database đang có FK cascade từ RoomType xuống Room. Kiểm tra phạm vi và khóa bảo vệ cả những Room cũ có liên kết lệch Property.
Room management, public room detail độc lập và các luồng Admin cần xuất phát từ contract và schema đã được owner xác nhận.

### 8.3 Availability và Booking

Quy tắc tích hợp do TV2 xác nhận khi triển khai. Availability kết hợp phòng ACTIVE, sức chứa, lịch bảo trì và booking giữ phòng.
Kiểm tra giao nhau theo khoảng nửa mở khi contract dùng check-in bao gồm và check-out loại trừ:

~~~text
existing_check_in < requested_check_out
AND existing_check_out > requested_check_in
~~~

Đối chiếu enum và rule trạng thái nào giữ phòng. Tìm kiếm là ảnh chụp tại thời điểm đọc.
Khi đặt phòng, kiểm tra lại điều kiện trong transaction với chiến lược khóa được TV2 chốt.
Giữ nguyên snapshot giá, số đêm và dữ liệu cần thiết cho billing khi Booking đã được tạo.

Sơ đồ sau là thiết kế workflow. Endpoint hoặc transition cụ thể phải được kiểm tra ở code và contract đã duyệt.

~~~mermaid
stateDiagram-v2
    [*] --> PENDING
    PENDING --> CONFIRMED: Host xác nhận
    PENDING --> REJECTED: Host từ chối
    PENDING --> CANCELLED: Customer hủy theo chính sách
    CONFIRMED --> CHECKED_IN: Check-in
    CONFIRMED --> CANCELLED: Hủy nếu chính sách cho phép
    CHECKED_IN --> COMPLETED: Check-out
    COMPLETED --> [*]
    CANCELLED --> [*]
    REJECTED --> [*]
~~~

Dịch vụ gắn với Booking tham chiếu danh mục theo Property và lưu snapshot tên, đơn vị, giá, số lượng và số tiền.
Client gửi dữ liệu thao tác. Backend xác định giá và tổng từ nguồn đáng tin cậy.
Các thao tác xác nhận, hủy, nhận phòng, trả phòng và dịch vụ dùng quyền và trạng thái phù hợp ở Backend.

### 8.4 Invoice và Reporting

Mỗi Booking có tối đa một Invoice. Invoice header và items được lưu nguyên tử.
Số tiền xuất phát từ Booking và BookingService snapshots. Xử lý số tiền bằng decimal chính xác, giữ scale theo contract.
Mã hóa đơn duy nhất và unique booking_id bảo vệ tính toàn vẹn ở DB. Kiểm tra xung đột trong workflow và phân loại constraint lỗi hợp lệ.

~~~text
room_total    = number_of_nights × price_per_night
service_total = SUM(booking_services.amount)
subtotal      = room_total + service_total
total_amount  = subtotal - discount_amount + extra_fee
~~~

Áp discount và extra fee đúng một lần theo workflow. Frontend hiển thị giá trị Backend đã tính.

Create hỗ trợ ROOM và SERVICE trong workflow hiện tại. Item enum rộng hơn được dùng khi có nghiệp vụ và contract tương ứng.
Invoice mới có status UNPAID và paid_at null. Transition thanh toán, refund và KPI doanh thu phải được chốt ở backlog nghiệp vụ.
Reporting Host lọc các Property thuộc mình. Reporting Admin kiểm tra quyền ADMIN. Các tham số thời gian, timezone và trạng thái hóa đơn tính doanh thu cần thống nhất trước khi triển khai.

### 8.5 Review

Chỉ Customer sở hữu Booking COMPLETED mới có quyền tạo Review. Mỗi Booking có tối đa một Review và rating từ 1 đến 5.
POST bảo toàn thứ tự validation và privacy 404. Unique constraint bảo vệ cả các request đồng thời.
Public trả tên đã ẩn danh. Host chỉ đọc Review theo Property sở hữu. Customer đọc Review của chính mình.
Summary có phạm vi và key riêng của từng endpoint. API tạo và API đọc Customer dùng cùng projection.

### 8.6 FAQ và Chat

FAQ quản lý ở phạm vi Admin theo contract được xác nhận. Chat V1 là luồng tư vấn do Backend điều phối:

~~~text
Chat UI → Chat API → dữ liệu được phép → context
        → provider → kiểm tra response → lưu lịch sử theo quyền → UI
~~~

Kiểm soát quyền truy cập lịch sử theo session, giới hạn context và thời gian gọi provider.
Provider chỉ nhận dữ liệu cần cho tư vấn. Loại thông tin nhạy cảm trước khi dựng context.
Chat không thực hiện đặt phòng, hủy, thanh toán hoặc cập nhật dữ liệu nghiệp vụ.

## 9. Luồng mẫu đã triển khai — Tạo Invoice

~~~mermaid
sequenceDiagram
    participant UI as React
    participant C as HostInvoiceController
    participant V as InvoiceValidator
    participant S as InvoiceService
    participant R as InvoiceRepository
    participant M as InvoiceMapper
    participant DB as MySQL

    UI->>C: POST /api/host/bookings/{booking}/invoice
    C->>V: Kiểm tra route input
    V-->>C: ID hợp lệ
    C->>S: CreateInvoiceRequest + actor
    S->>R: Lock Booking và lấy facts cần thiết
    R->>DB: Scoped read / lock
    S->>V: Quyền, trạng thái, duplicate và snapshot
    S->>S: Tính từ snapshot chính xác
    S->>M: Dựng dữ liệu lưu
    S->>R: Lưu Invoice header và items trong một transaction
    R->>DB: INSERT header và items
    S->>DB: Commit
    S-->>C: Invoice với items đã tải
    C->>M: Map sang InvoiceResponse
    M-->>C: Response DTO
    C-->>UI: 201, data
~~~

- Host phải sở hữu Property của Booking đã hoàn tất. Giữ thứ tự lỗi riêng của mutation.
- Candidate Invoice dùng nhóm invoice-create. ROOM item dùng invoice-create-room. Nhóm validation được gọi tường minh trước insert.
- Repository quản lý persistence. Service sở hữu transaction và success log sau commit.
- Header và items map qua Symfony ObjectMapper với allowlist. API tạo trả cùng hình dạng dữ liệu cơ bản với API đọc chi tiết.
- Frontend dùng invoiceApi, invoiceService và invoiceMapper. Giữ decimal strings, nullable dates và phân biệt list không có items với detail có mảng items rỗng.

### 9.1 Luồng đọc Invoice

- Route list hoặc detail xác thực actor và giữ thứ tự role, input và lookup theo contract đọc.
- QueryService yêu cầu Repository scope theo Host hoặc Customer và trả kết quả đã map. Repository kiểm soát dữ liệu cần eager-load.
- Response list dùng PageResponse với data/meta/links. Response detail dùng ApiResponse với data.
- Các giá trị tiền và ngày đi từ schema qua Eloquent casts, DTO rồi JSON. Frontend giữ chuỗi decimal và chỉ định dạng khi hiển thị.
- Kiểm chứng bằng list/detail của cả hai vai trò, lookup sai owner, phân trang và tính nhất quán với response create.

### 9.2 Áp dụng ví dụ cho slice khác

So sánh điểm vào, quyền, đầu vào, read/write boundary và projection của slice mới.
Nếu response có summary hoặc file stream, giữ envelope và serialization đặc thù của endpoint đó.
Đối chiếu DTO hoặc mapper với fixture độc lập, gồm cả trường nullable và trường không được phép xuất ra.
Khi kiểm tra refactor, chạy lại consumer thực tế để xác nhận đường đi trước và sau cho cùng dữ liệu.

## 10. Triển khai local

~~~text
Browser
  → http://localhost:5173       React/Vite
  → http://localhost:8000       Laravel dev
       → XAMPP MySQL/MariaDB    dữ liệu nghiệp vụ và session theo cấu hình
~~~

Xem [hướng dẫn cài và chạy](README.md).
Cấu hình origin, CORS, cookie và session nhất quán. Khi chạy dev/test đồng thời, dùng cổng và schema test riêng.
Production dùng HTTPS, biến môi trường riêng và APP_DEBUG tắt. Chỉ build nội dung cần thiết. Không đưa env, secret, log, vendor hoặc node_modules vào commit.

## 11. Phát triển, kiểm thử và nghiệm thu

### 11.1 Quy trình theo lát chức năng

1. Kiểm tra repo và ranh giới owner.
2. Đọc kiến trúc, route, schema, contract và consumer thực tế.
3. Trình bày Mini Plan gồm phạm vi file, hành vi giữ nguyên và AC đo được.
4. Triển khai phần đã duyệt. Một tính năng có UI cần kiểm chứng cả Backend và Frontend.
5. So diff, chạy test và báo bằng chứng theo từng cổng.
6. Ghi nghiệm thu vào checklist tương ứng khi có xác nhận.

### 11.2 Cổng kiểm chứng

| Cổng | Mục đích |
| --- | --- |
| Unit | Quy tắc thuần, calculation, validation và projection |
| Feature Laravel | Route, API contract, authentication, role, ownership và transaction test |
| SQLite in-memory | Hồi quy nhanh theo guard của PHPUnit |
| MySQL/XAMPP | Collation, constraint, storage-bound flow và InnoDB concurrency với schema test riêng |
| Node frontend | Service, mapper, API adapter, lỗi và cancellation |
| Lint và build | Chất lượng source và biên dịch Frontend |
| Browser thật | Session/CSRF, trạng thái UI, responsive và tích hợp API thật |

~~~powershell
# Tại backend
php artisan route:list
php artisan test

# Tại frontend
node --experimental-test-module-mocks --test tests/*.test.js
npm run lint
npm run build

# Tại root
git status --short
git diff --check
~~~

Chạy focused test khi phát triển và cổng hồi quy theo phạm vi thay đổi.
Dùng schema XAMPP test riêng khi cần kiểm tra engine và transaction đồng thời. Xác nhận connection và SELECT DATABASE() trước khi ghi dữ liệu.
Fixture, server tạm và quá trình cleanup thuộc lượt test. SQLite và mock adapter có giới hạn riêng cần ghi trong kết quả.
Task tài liệu/skill kiểm chứng nội dung, liên kết, phạm vi và validator tương ứng.
Ghi test, build và runtime riêng. Ghi technical result và owner acceptance riêng.

## 12. Tài liệu liên quan

- [README](README.md) và [dữ liệu seed](docs/seed-data.md)
- [Use case](docs/stayhub-usecase-system.md) và [ERD](docs/stayhub-erd.md)
- [Phân công nhóm](docs/stayhub-team-work-assignment.md)
- [Screen mockups](docs/stayhub-screen-mockups.md) và [Frontend guide](docs/ai-frontend-development-guide.md)
- [Checklist TV3](docs/TV3/TV3_CHECKLIST.md) cho tiến độ và bằng chứng thuộc phạm vi TV3

## 13. ARCH-01 — kiến trúc mục tiêu toàn dự án

Mục này tổng hợp cách áp dụng cấu trúc chung theo từng loại task.
Dùng tên lớp trong [cấu trúc Backend](#4-kiến-trúc-backend) và [cấu trúc Frontend](#3-kiến-trúc-frontend).

### 13.1 Chọn ranh giới theo nhu cầu

| Nhu cầu | Cách áp dụng |
| --- | --- |
| Chuẩn hóa input của endpoint | Giữ raw input nếu validation phụ thuộc kiểu ban đầu. Tạo Request DTO typed sau khi dữ liệu hợp lệ. |
| Lấy facts nhiều bảng hoặc khóa ghi | Dùng concrete Repository có scope và method rõ. Service giữ workflow và transaction. |
| Quy tắc kiểm tra dùng lại | Đặt trong Validator theo domain. Dùng Symfony attributes khi hợp với dữ liệu và group kiểm tra. |
| Chuyển Model thành response theo audience | Dựng Response DTO với mapper thuần và dữ liệu đã load. Dùng adapter ObjectMapper cho projection tường minh. |
| Tạo response chỉ có dữ liệu tổng hợp hoặc ID | Dùng DTO dựng trực tiếp khi source không phải projection của Model. |
| Response và lỗi | Dùng Common cho luồng đã thống nhất envelope. Giữ contract legacy của consumer trong quá trình chuyển đổi. |
| Nhiều màn hình xử lý cùng dữ liệu | Domain service áp mapper ở ranh giới API. Dùng chung pagination/projection và để component xử lý trình bày. |

### 13.2 Các trường hợp cần đối chiếu khi refactor

- **Read** — auth/role, canonical ID, filter, order, pagination, summary, ownership, lỗi API và trường lược bỏ.
- **Create** — raw payload, validation, permission, duplicate, kết quả tạo, rollback và hồi quy bằng thao tác đọc.
- **Update** — whitelist field, dữ liệu cũ, trạng thái, kết quả no-op, constraints và dữ liệu liên quan.
- **Delete** — tình trạng tham chiếu, foreign key, đường dọn file, xử lý retry và độ nhất quán sau commit.
- **Upload hoặc stream** — quyền, giới hạn file, mime/dimension khi có, storage path, lỗi giữa các bước và headers.
- **UI** — phiên đăng nhập, lỗi theo field và domain, hủy request, đổi filter/page, gửi trùng, retry và focus.

### 13.3 Các điểm mở rộng chung

- Giữ endpoint và lớp đang phục vụ caller cũ cho đến khi đã đối chiếu đầy đủ consumer.
- Đối chiếu lower-level entry point cùng HTTP entry point nếu có caller trực tiếp từ module khác.
- Giữ quy tắc hiện hành khi rút lớp dùng chung. Khi có thay đổi nghiệp vụ, tách thành quyết định và phạm vi được chốt.
- Ưu tiên adapter và provider hiện có cho dependencies hạ tầng.
- Đồng bộ tài liệu này khi thay đổi kiến trúc. Xem checklist của owner để theo dõi triển khai và nghiệm thu từng slice.

### 13.4 Nguồn đối chiếu cho task tích hợp

| Thứ cần xác minh | Nguồn đối chiếu |
| --- | --- |
| Route, status và lỗi | Route registry, Controller, middleware, feature test và consumer |
| Payload và field hiển thị | Request validation, Response DTO, mapper và UI thực tế |
| Transaction, unique và FK | Service, Repository, migration và schema trên engine liên quan |
| Quyền theo vai trò | Actor, policy hoặc workflow, scoped query và integration test |

## 14. STRUCT-01 — naming hiện hành và trạng thái chuyển đổi

### 14.1 Quy ước tên

| Vai trò | Tổ chức |
| --- | --- |
| Controller | app/Http/Controllers/<Domain> |
| Request/Response DTO | app/DTOs/<Domain>/Requests hoặc Responses |
| Symfony adapter và domain mapper | app/Mappers cùng thư mục domain |
| Domain validator và constraints | app/Validators/<Domain> và app/Validators/Constraints |
| Workflow và persistence | app/Services/<Domain> và app/Repositories/<Domain> |
| Frontend transport và mapping | src/api, src/services và src/mappers |
| Host/Customer/Admin UI | src/pages và src/components theo vai trò hoặc domain đang dùng |

Dùng hậu tố Request, Response hoặc Query theo vai trò DTO. Tên field bên ngoài theo contract tiêu thụ.
Namespace Controller theo domain và routing hiện hành. Chỉ thêm version prefix khi có quyết định về API versioning.

### 14.2 Ví dụ từ code đang dùng

| Slice | Backend | Frontend |
| --- | --- | --- |
| Invoice đọc/tạo | InvoiceValidator, typed DTO, InvoiceRepository, InvoiceMapper, Common | invoiceService, invoiceApi, invoiceMapper |
| Review đọc/tạo | ReviewValidator, ReviewRepository, ReviewMapper, Response DTO theo audience, Common | reviewService, reviewApi, reviewMapper |
| Auth và Profile | AuthUserMapper chia sẻ projection user. Đăng nhập/đăng ký dùng AuthValidator và typed Request DTO. Profile mutation dùng ProfileValidator, typed Request DTO và ProfileRepository. | authService và profileService theo contract Sanctum đang dùng |
| Public Catalog | CatalogValidator, CatalogRepository và CatalogMapper, DTO theo endpoint | propertyService và lớp catalog theo contract đọc |
| Host Catalog | Validator, QueryService/Service, Repository và DTO cho Property, ảnh và RoomType | hostPropertyService và các component Catalog |
| Booking & Operations | Schema/model là đầu mối tích hợp. Xác nhận route khi code TV2 được hợp nhất. | bookingService có luồng DEV mock được gắn điều kiện |

Các điểm xem nhanh trong source:

- [Laravel route registry](backend/routes/api.php)
- [Symfony engine registration](backend/app/Providers/AppServiceProvider.php)
- [Adapter validation](backend/app/Validators/AttributeValidator.php)
- [Invoice mapping](backend/app/Mappers/Invoice/InvoiceMapper.php)
- [Review mapping](backend/app/Mappers/Review/ReviewMapper.php)
- [Catalog mapping](backend/app/Mappers/Catalog/CatalogMapper.php)
- [Auth mapping](backend/app/Mappers/Auth/AuthUserMapper.php)
- [Frontend router](frontend/src/routes/AppRouter.jsx)
- [Frontend shared Axios](frontend/src/api/axios.js)
