# Hướng dẫn AI phát triển Frontend StayHub

Tài liệu này là chỉ dẫn làm việc dành cho AI khi xây dựng frontend của dự án đặt phòng khách sạn **StayHub**. Đọc toàn bộ tài liệu này trước khi sửa code.

## 1. Mục tiêu

Xây dựng ứng dụng React trong thư mục `frontend/` cho ba nhóm người dùng:

- **Customer**: tìm nơi lưu trú, xem phòng, đặt phòng, theo dõi booking, hóa đơn, review và chatbot.
- **Host**: quản lý cơ sở lưu trú, loại phòng, phòng, tiện nghi, bảo trì, dịch vụ, booking và hóa đơn.
- **Admin**: quản lý người dùng, cơ sở lưu trú, tiện nghi, booking, hóa đơn và thống kê toàn hệ thống.

Template giao diện có sẵn tại `docs/StayNest-Frontend-template/`. Dùng template này làm nguồn tham khảo và tái sử dụng có chọn lọc, không xem nó là đặc tả nghiệp vụ của StayHub.

## 2. Phạm vi thư mục

- Chỉ viết code frontend chạy thật trong `frontend/`.
- Không sửa trực tiếp `docs/StayNest-Frontend-template/`; đây là bản tham khảo cần được giữ nguyên.
- Không sửa `backend/` trừ khi yêu cầu hiện tại nói rõ phải làm cả backend.
- Tài liệu nghiệp vụ nằm trong `docs/`.
- Không commit `node_modules`, file build, khóa bí mật hoặc `.env`.

Nếu cần lấy một component từ template, hãy sao chép phần cần thiết sang `frontend/src/`, đổi tên và điều chỉnh theo StayHub. Không import runtime từ bên trong `docs/`.

## 3. Thứ tự nguồn sự thật

Khi các nguồn mâu thuẫn, ưu tiên theo thứ tự sau:

1. Code backend đang chạy trong `backend/routes/`, controller, request, resource và model.
2. `docs/stayhub-usecase-system.md` cho nghiệp vụ và phân quyền.
3. `docs/stayhub-erd.md` cho thực thể, quan hệ, enum và business rule.
4. `docs/stayhub-screen-mockups.md` cho danh sách màn hình và nội dung cần hiển thị.
5. `docs/StayNest-Frontend-template/` cho bố cục, component, chuyển động và cảm giác thị giác.

Không tự suy ra endpoint chỉ từ tên hàm API của template. Template StayNest dùng backend, mô hình dữ liệu và thuật ngữ khác với StayHub.

## 4. Quy tắc làm việc bắt buộc

Trước mỗi phần việc, AI phải:

1. Đọc file hiện có liên quan trong `frontend/`.
2. Đọc màn hình tương ứng trong `docs/stayhub-screen-mockups.md`.
3. Đọc use case và business rule tương ứng.
4. Kiểm tra route và contract thật ở `backend/` trước khi nối API.
5. Nêu ngắn gọn các giả định nếu backend chưa có contract.
6. Giữ thay đổi nhỏ, đúng phạm vi yêu cầu; không viết lại phần không liên quan.
7. Chạy ít nhất `npm run lint` và `npm run build` trong `frontend/` sau khi sửa.

Không được:

- bịa endpoint, response field, role hoặc trạng thái nghiệp vụ;
- nhét dữ liệu mẫu trực tiếp vào component dùng cho production;
- gọi API trực tiếp rải rác trong page/component;
- lưu token hoặc dữ liệu nhạy cảm trong source code;
- dùng `dangerouslySetInnerHTML` với dữ liệu từ người dùng/API;
- thay đổi toàn bộ kiến trúc chỉ để giống template;
- tuyên bố hoàn tất nếu lint/build còn lỗi do thay đổi vừa tạo ra.

Nếu backend chưa hỗ trợ tính năng đang làm, vẫn có thể dựng UI nhưng phải:

- đặt mock trong vùng riêng như `src/mocks/`;
- ghi rõ mock/TODO và contract dự kiến;
- thiết kế service để sau này thay mock bằng API mà không sửa lại component;
- không làm cho người đọc hiểu nhầm rằng API đã tồn tại.

## 5. Công nghệ và quy ước hiện tại

Frontend hiện dùng:

- React 19 và Vite;
- React Router DOM;
- Axios;
- JavaScript/JSX.

Tôn trọng `frontend/package.json` và code hiện có. Chỉ thêm dependency khi thật sự cần, giải thích lý do, rồi cập nhật cả `package.json` và lockfile. Không sao chép nguyên `package.json` của template sang frontend.

Template còn dùng Tailwind CSS, Radix/shadcn, React Hook Form, Zod, Lucide, Sonner, Framer Motion và Recharts. Có thể đưa từng thư viện vào khi màn hình cần đến, nhưng phải tích hợp đúng cấu hình dự án và tránh thêm cả bộ thư viện chỉ để dùng một component nhỏ.

Quy ước code:

- Component dùng `PascalCase`, hook dùng tiền tố `use`, hàm/biến dùng `camelCase`.
- Page chỉ điều phối dữ liệu và bố cục; logic API nằm trong `src/api/` hoặc service/query layer.
- Component dùng lại đặt trong `src/components/`; component chỉ dành cho một domain đặt trong thư mục domain tương ứng.
- Dùng một Axios instance chung tại `src/api/axios.js`.
- URL backend chỉ lấy từ biến môi trường `VITE_BACKEND_URL`.
- Ưu tiên named constant cho route, role, status; không lặp magic string ở nhiều nơi.
- UI text của sản phẩm dùng tiếng Việt nhất quán, trừ khi yêu cầu khác.
- Tiền tệ hiển thị bằng `Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' })`.
- Ngày giờ phải có quy ước rõ ràng, không tự parse chuỗi ngày theo timezone trình duyệt nếu backend chưa xác định format.

## 6. Kiến trúc frontend mục tiêu

Giữ và mở rộng cấu trúc hiện có theo hướng sau:

```text
frontend/src/
├── api/                 # Axios instance và API theo domain
├── assets/              # Ảnh/font được bundle
├── components/
│   ├── common/          # Button, modal, empty/error/loading state...
│   ├── booking/
│   ├── property/
│   ├── room/
│   └── ...
├── constants/           # Roles, statuses, route names
├── contexts/            # Auth hoặc state dùng toàn app khi cần
├── hooks/               # Custom hooks
├── layouts/             # Public, Customer, Host, Admin
├── pages/
│   ├── public/
│   ├── customer/
│   ├── host/
│   └── admin/
├── routes/              # Router, route guards
├── utils/               # Formatters và helper thuần
├── App.jsx
├── main.jsx
└── index.css
```

Không bắt buộc tạo trước tất cả thư mục. Chỉ tạo khi có code sử dụng.

### Luồng dữ liệu

```text
Page/Component -> hook/service theo domain -> Axios instance -> Laravel API
                                           <- dữ liệu/error đã chuẩn hóa <-
```

- Component không phụ thuộc trực tiếp vào hình dạng lỗi thô của Axios.
- Mỗi màn hình gọi API phải có trạng thái loading, empty, error và success.
- Mutation phải khóa nút khi đang gửi để tránh submit lặp.
- Sau mutation thành công, cập nhật hoặc tải lại đúng dữ liệu liên quan.

## 7. Authentication và phân quyền

Backend hiện được cấu hình theo Laravel Sanctum dạng session/cookie. Giữ `withCredentials: true` và `withXSRFToken: true` trong Axios.

Luồng đăng nhập dự kiến phải dựa trên route backend thật:

1. Khởi tạo CSRF cookie nếu backend yêu cầu.
2. Gửi thông tin đăng nhập.
3. Gọi endpoint người dùng hiện tại, ví dụ `/api/me`, để phục hồi session khi refresh.
4. Điều hướng theo role thực tế từ backend.
5. Khi logout, xóa auth state phía client và chuyển về trang public.

Không coi việc ẩn menu là phân quyền bảo mật. Frontend cần route guard để cải thiện UX, còn backend vẫn phải là nơi thực thi quyền.

Tên role chuẩn của dự án:

```text
CUSTOMER
HOST
ADMIN
```

Chỉ dùng giá trị khác nếu backend thực tế định nghĩa khác. `manager` trong template phải được ánh xạ thành `host`, không đưa nguyên thuật ngữ manager vào nghiệp vụ StayHub.

## 8. Bản đồ chức năng từ template sang StayHub

| Template StayNest | StayHub | Cách xử lý |
|---|---|---|
| Landing, search, hotel detail | Public property search/detail | Tái sử dụng bố cục; đổi hotel thành property/cơ sở lưu trú theo domain |
| Room list, booking card | Room detail, booking checkout | Giữ trải nghiệm chọn ngày/phòng; áp dụng rule availability của StayHub |
| My bookings | Booking của Customer | Đổi status, field và action theo backend StayHub |
| Profile, auth | Profile, authentication | Tái sử dụng UI; nối Sanctum của Laravel |
| `manage/*` manager console | `host/*` Host console | Đổi route, thuật ngữ và quyền manager thành Host |
| Inventory calendar | Room availability/maintenance | Chỉ dùng khi contract backend hỗ trợ; không đồng nhất inventory template với room vật lý |
| Refund/payment flows | Ngoài core V2 | Không ưu tiên nếu chưa có yêu cầu/backend |
| Wishlist | Ngoài scope tài liệu hiện tại | Không triển khai mặc định |
| AI hotel search | Chatbot đọc dữ liệu nội bộ | V1 chỉ tư vấn, không cho chatbot tạo/hủy/sửa booking hoặc thanh toán |

Giữ nhận diện tên **StayHub**. Không để sót tên StayNest, URL demo, API demo, dữ liệu Ấn Độ, Stripe hoặc Cloudinary của template nếu dự án chưa chọn các dịch vụ đó.

## 9. Các màn hình và route đề xuất

Route chính xác có thể điều chỉnh theo router hiện có, nhưng URL phải nhất quán và phản ánh đúng vai trò.

### Public và Customer

```text
/
/login
/register
/properties
/properties/:propertyId
/rooms/:roomId
/booking/checkout
/customer
/customer/bookings
/customer/bookings/:bookingId
/customer/invoices/:invoiceId
/customer/profile
/customer/chat
```

### Host

```text
/host
/host/properties
/host/properties/new
/host/properties/:propertyId
/host/properties/:propertyId/room-types
/host/properties/:propertyId/rooms
/host/properties/:propertyId/services
/host/properties/:propertyId/maintenances
/host/bookings
/host/bookings/:bookingId
/host/invoices
/host/invoices/:invoiceId
```

### Admin

```text
/admin
/admin/users
/admin/hosts
/admin/properties
/admin/amenities
/admin/bookings
/admin/invoices
```

Chi tiết nội dung từng màn hình phải theo `docs/stayhub-screen-mockups.md`, không theo suy đoán từ tên route.

## 10. Business rule không được làm sai

### Booking

Luồng trạng thái chuẩn:

```text
PENDING -> CONFIRMED -> CHECKED_IN -> COMPLETED
PENDING -> REJECTED
PENDING -> CANCELLED
CONFIRMED -> CANCELLED (chỉ khi thỏa điều kiện hủy)
```

Chỉ hiển thị action hợp lệ với trạng thái và role hiện tại. Backend mới là nguồn quyết định cuối cùng; frontend phải hiển thị message backend khi hành động bị từ chối.

### Availability

Một phòng chỉ khả dụng khi:

- phòng đang `ACTIVE`;
- đủ sức chứa;
- không trùng booking ở trạng thái `PENDING`, `CONFIRMED` hoặc `CHECKED_IN`;
- không trùng lịch bảo trì.

Điều kiện giao nhau của khoảng ngày:

```text
existing_check_in < requested_check_out
AND existing_check_out > requested_check_in
```

Frontend không tự coi kết quả đã kiểm tra trước đó là đảm bảo. Phải chấp nhận việc backend kiểm tra lại availability lúc tạo booking.

### Giá và hóa đơn

- Số đêm, giá phòng, giá dịch vụ, subtotal và total do backend xác nhận.
- Frontend có thể hiển thị giá ước tính nhưng không phải nguồn tính tiền cuối cùng.
- Booking service dùng snapshot `service_name`, `unit`, `unit_price` tại thời điểm thêm.
- Dịch vụ thường chỉ được thêm khi booking ở `CHECKED_IN`, trừ khi backend cho phép ở `CONFIRMED`.
- Review chỉ hợp lệ với booking `COMPLETED`, thuộc Customer hiện tại và chưa được review.

## 11. Form, API và xử lý lỗi

Mỗi form phải có:

- label rõ ràng và liên kết đúng với input;
- validation phía client phục vụ UX;
- hiển thị lỗi validation `422` từ Laravel theo từng field;
- trạng thái đang gửi;
- thông báo thành công/thất bại;
- focus hợp lý khi mở dialog hoặc khi có lỗi.

Quy ước xử lý response:

- Không mặc định mọi response có cùng wrapper nếu backend chưa chuẩn hóa.
- Xử lý rõ các mã `401`, `403`, `404`, `409` và `422` khi có liên quan.
- `401`: session hết hạn, làm mới auth state và đưa người dùng về login khi cần.
- `403`: hiển thị không có quyền, không giả thành lỗi không tìm thấy.
- `409`: hiển thị xung đột nghiệp vụ, đặc biệt availability/booking state.
- `422`: ánh xạ `errors` vào field tương ứng.

Không log cookie, password, token hoặc dữ liệu cá nhân nhạy cảm.

## 12. UI/UX và khả năng truy cập

Phong cách có thể dựa trên template: hiện đại, sáng, nhiều khoảng thở, hình ảnh lưu trú nổi bật và responsive. Tuy nhiên, tính dễ dùng quan trọng hơn việc sao chép hiệu ứng.

Yêu cầu tối thiểu:

- mobile-first, dùng tốt ở mobile/tablet/desktop;
- dùng semantic HTML;
- thao tác được bằng bàn phím;
- focus state dễ thấy;
- icon-only button có accessible name;
- ảnh có `alt`, có kích thước hợp lý và fallback;
- màu chữ/nền đủ tương phản;
- animation tôn trọng `prefers-reduced-motion`;
- skeleton/spinner không làm layout nhảy mạnh;
- empty state nói rõ bước tiếp theo;
- dialog cần quản lý focus và đóng bằng Escape.

Không copy hình ảnh từ template nếu giấy phép/nguồn chưa rõ cho môi trường production. Có thể dùng chúng làm placeholder nội bộ trong giai đoạn phát triển và đánh dấu để thay thế.

## 13. Trình tự triển khai khuyến nghị

1. Nền tảng: design tokens/CSS, shared components, error/loading/empty states.
2. Auth: login, current user, logout, auth context và role guards.
3. Public: home, search, property detail, room detail.
4. Customer: checkout, booking list/detail, invoice, review, profile.
5. Host: dashboard, property, room type, room, maintenance và service.
6. Host booking workflow: confirm, reject, check-in, add service, check-out, invoice.
7. Admin: dashboard và các trang quản trị toàn hệ thống.
8. Chatbot: chỉ sau khi dữ liệu nền và contract backend đã ổn định.
9. Hoàn thiện responsive, accessibility, performance và test.

Không dựng hàng loạt trang giả trước khi hoàn thiện luồng dọc quan trọng. Ưu tiên một luồng chạy được từ UI đến API, ví dụ: tìm phòng -> xem chi tiết -> tạo booking -> xem booking.

## 14. Tiêu chí hoàn thành cho mỗi task

Một task chỉ được coi là hoàn thành khi:

- đúng acceptance criteria và đúng vai trò;
- dùng contract backend có thật hoặc mock được ghi rõ;
- có loading, empty, error và success phù hợp;
- responsive ở kích thước mobile và desktop;
- không làm hỏng route/màn hình hiện có;
- không còn tên/endpoint/dữ liệu StayNest bị copy nhầm;
- không có secret hoặc log nhạy cảm;
- `npm run lint` chạy thành công;
- `npm run build` chạy thành công;
- báo cáo ngắn gọn file đã đổi, kiểm thử đã chạy và phần còn thiếu do backend.

## 15. Mẫu yêu cầu dành cho AI

Dùng mẫu sau khi giao một phần việc cụ thể:

```text
Hãy đọc docs/ai-frontend-development-guide.md trước.

Nhiệm vụ: [mô tả màn hình/tính năng]
Vai trò: [Public/Customer/Host/Admin]
Acceptance criteria:
- [...]
- [...]

Hãy kiểm tra code frontend và contract backend liên quan trước khi sửa.
Dùng docs/StayNest-Frontend-template làm tham khảo giao diện, không sửa template và không sao chép API của template.
Sau khi làm xong, chạy lint/build và báo cáo file đã thay đổi, kết quả kiểm tra, giả định hoặc blocker còn lại.
```

## 16. Checklist tự kiểm tra nhanh của AI

Trước khi trả lời hoàn tất, tự hỏi:

- Tôi đang sửa `frontend/`, không phải bản template trong `docs/`?
- Tôi đã đọc đúng mockup/use case liên quan?
- Endpoint và field có tồn tại trong backend?
- Role và booking status có đúng StayHub?
- Có loading/error/empty state và chống submit lặp?
- Giao diện có dùng được trên mobile và bàn phím?
- Có sót chữ StayNest, manager, API demo hoặc dữ liệu mẫu không?
- Lint và build đã pass chưa?

