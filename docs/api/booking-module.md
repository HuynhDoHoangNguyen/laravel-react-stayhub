# StayHub - Booking Module API Documentation

Tài liệu đặc tả toàn bộ API của Module Booking (B1 - B5) do **Thành viên 2** phụ trách. Được xây dựng dựa trên mã nguồn thực tế (`routes/api/booking.php`, Controllers, Form Requests, Resources, Services, Exceptions và Tests).

---

## 1. Quy ước chung

### 1.1 Prefix & Authentication
- **Public**: `/api/public/*` - Không yêu cầu đăng nhập.
- **Customer**: `/api/customer/*` - Middleware: `auth:sanctum`, `role:CUSTOMER`.
- **Host**: `/api/host/*` - Middleware: `auth:sanctum`, `role:HOST`.
- Header xác thực: `Authorization: Bearer {token}` kèm `Accept: application/json`.

### 1.2 Nguyên tắc tài chính & Dữ liệu
- Toàn bộ giá trị tiền tệ (`price_per_night`, `room_total`, `unit_price`, `amount`, `services_total`) và số lượng (`quantity`, `number_of_nights`) được backend tính toán và chuẩn hóa định dạng chuỗi thập phân 2 chữ số (ví dụ `"500000.00"`).
- Backend là **Single Source of Truth**: hoàn toàn bỏ qua mọi giá trị giá cả hoặc tổng tiền do client gửi lên.
- Phân trang dùng cấu trúc `meta`:
  ```json
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
  ```

### 1.3 Cấu trúc phản hồi lỗi chuẩn
- **401 Unauthorized**:
  - Khi chưa gửi Sanctum token hoặc token không hợp lệ (Laravel framework mặc định):
    ```json
    {
      "message": "Unauthenticated."
    }
    ```
  - Khi qua middleware CheckRole:
    ```json
    {
      "success": false,
      "message": "Unauthenticated",
      "errors": []
    }
    ```
- **403 Forbidden** (Sai vai trò hoặc tài khoản bị khóa):
  ```json
  {
    "success": false,
    "message": "Bạn không có quyền truy cập.",
    "errors": []
  }
  ```
  *(Trường hợp tài khoản bị khóa: `"message": "Tài khoản của bạn đã bị khóa."`)*
- **404 Not Found** (Phòng ngừa IDOR / Dữ liệu không tồn tại):
  - Khi Customer truy cập booking của khách khác hoặc Host truy cập booking không thuộc cơ sở mình quản lý (`BookingPolicy`):
    ```json
    {
      "message": "Không tìm thấy thông tin đặt phòng."
    }
    ```
  - Khi Host truy cập service không thuộc sở hữu (`PropertyServiceManagementService`):
    ```json
    {
      "message": "Dịch vụ không tồn tại."
    }
    ```
  - Khi kiểm tra route lồng mismatch giữa booking và bookingService (`BookingServiceController`):
    ```json
    {
      "message": "Dịch vụ trong booking không tồn tại."
    }
    ```
- **409 Conflict** (`RoomNotAvailableException`):
  Khi phòng không khả dụng do trùng booking khác đang giữ phòng hoặc trùng bảo trì:
  ```json
  {
    "success": false,
    "message": "Phòng không khả dụng trong thời gian yêu cầu.",
    "errors": {
      "reasons": [
        "BOOKING_OVERLAPPING_BOOKING"
      ]
    }
  }
  ```
  *(Các mã lý do: `ROOM_NOT_ACTIVE`, `PROPERTY_NOT_ACTIVE`, `CAPACITY_EXCEEDED`, `MAINTENANCE_OVERLAP`, `BOOKING_OVERLAP`).*
- **422 Unprocessable Entity - Lỗi nghiệp vụ**:
  - Chuyển trạng thái sai quy trình (`InvalidBookingTransitionException`):
    ```json
    {
      "success": false,
      "message": "Không thể chuyển trạng thái đặt phòng từ Chờ xác nhận sang Đã nhận phòng.",
      "errors": []
    }
    ```
  - Hủy phòng không hợp lệ / quá hạn (`BookingCancellationNotAllowedException`):
    ```json
    {
      "success": false,
      "message": "Đã qua thời hạn hủy phòng cho phép.",
      "errors": []
    }
    ```
  - Dịch vụ không hợp lệ (`ServiceNotAllowedException`):
    ```json
    {
      "success": false,
      "message": "Chỉ được thêm dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).",
      "errors": []
    }
    ```
- **422 Unprocessable Entity - Lỗi Form Request Validation**:
  ```json
  {
    "message": "The check in date field must be a date after or equal to today. (and 1 more error)",
    "errors": {
      "check_in_date": [
        "The check in date field must be a date after or equal to today."
      ],
      "check_out_date": [
        "The check out date field must be a date after check in date."
      ]
    }
  }
  ```

---

## 2. Danh sách Endpoint chi tiết

### 2.1 Public APIs

#### 1. Tìm kiếm phòng trống (Search Rooms)
- **Method**: `GET`
- **URL**: `/api/public/rooms/search`
- **Role**: Public (Khách vãng lai, không cần đăng nhập)
- **Query Parameters**:
  - `check_in_date` (string, bắt buộc): Định dạng `Y-m-d`, phải `>= today`.
  - `check_out_date` (string, bắt buộc): Định dạng `Y-m-d`, phải `> check_in_date`.
  - `guest_count` (integer, bắt buộc): Số khách, tối thiểu `1`.
  - `property_id` (integer, tùy chọn): ID cơ sở lưu trú.
  - `keyword` (string, tùy chọn): Tìm theo tên hoặc địa chỉ cơ sở lưu trú.
  - `room_type_id` (integer, tùy chọn): ID loại phòng.
  - `min_price` (numeric, tùy chọn): Giá thấp nhất mỗi đêm `>= 0`.
  - `max_price` (numeric, tùy chọn): Giá cao nhất mỗi đêm `>= min_price`.
  - `amenity_ids` (array, tùy chọn): Mảng ID tiện nghi (phòng phải có **tất cả** tiện nghi chỉ định).
  - `sort` (string, tùy chọn): `price_asc` (mặc định), `price_desc`, `newest`.
  - `per_page` (integer, tùy chọn): Số dòng mỗi trang (`1` đến `50`, mặc định `15`).
- **Quy tắc nghiệp vụ**:
  - Phòng hợp lệ phải thỏa mãn: Room `status = ACTIVE`, Property `status = ACTIVE`, sức chứa `capacity >= guest_count`.
  - Không trùng khoảng bảo trì (bỏ qua bảo trì đã `CANCELLED` hoặc `COMPLETED`).
  - Không trùng booking đang giữ phòng (`PENDING`, `CONFIRMED`, `CHECKED_IN`) theo quy tắc: `existing_in < requested_out AND existing_out > requested_in`.
- **Response thành công (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Tìm kiếm phòng thành công.",
    "data": [
      {
        "id": 1,
        "property_id": 1,
        "room_type_id": 1,
        "room_number": "101",
        "name": "Phòng Standard 101",
        "description": "Phòng 1 giường đôi",
        "capacity": 2,
        "price_per_night": "500000.00",
        "status": "ACTIVE",
        "property": {
          "id": 1,
          "name": "Khách sạn StayHub Central",
          "type": "HOTEL",
          "address": "123 Đường Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh",
          "phone": "02838111111",
          "status": "ACTIVE"
        },
        "room_type": {
          "id": 1,
          "name": "Standard Room",
          "description": "Phòng tiêu chuẩn gọn gàng"
        },
        "amenities": [
          {
            "id": 1,
            "name": "Wi-Fi miễn phí",
            "icon": "wifi"
          }
        ],
        "images": []
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
  ```

#### 2. Kiểm tra phòng trống cụ thể (Room Availability)
- **Method**: `GET`
- **URL**: `/api/public/rooms/{room}/availability`
- **Role**: Public
- **Query Parameters**:
  - `check_in_date` (string, bắt buộc): `Y-m-d`, `>= today`.
  - `check_out_date` (string, bắt buộc): `Y-m-d`, `> check_in_date`.
  - `guest_count` (integer, bắt buộc): Số khách `>= 1`.
- **Response thành công (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Kiểm tra phòng trống thành công.",
    "data": {
      "available": true,
      "reasons": [],
      "blocked_ranges": [
        {
          "start_date": "2026-10-20",
          "end_date": "2026-10-22",
          "type": "BOOKING"
        }
      ]
    }
  }
  ```
- **Response lỗi (404 Not Found)**: Nếu không tìm thấy phòng.

#### 3. Báo giá phòng trước khi đặt (Preview Booking)
- **Method**: `POST`
- **URL**: `/api/public/bookings/preview`
- **Role**: Public (Giới hạn `throttle:60,1`)
- **Request Body**:
  ```json
  {
    "room_id": 1,
    "check_in_date": "2026-10-25",
    "check_out_date": "2026-10-28",
    "guest_count": 2
  }
  ```
- **Quy tắc nghiệp vụ**:
  - Backend đọc `price_per_night` trực tiếp từ bảng `rooms`.
  - Số đêm tính toán: `number_of_nights = diffInDays(check_in_date, check_out_date)`.
  - `room_total = price_per_night * number_of_nights`.
- **Response thành công (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Báo giá phòng thành công.",
    "data": {
      "room_id": 1,
      "check_in_date": "2026-10-25",
      "check_out_date": "2026-10-28",
      "guest_count": 2,
      "number_of_nights": 3,
      "price_per_night": "500000.00",
      "room_total": "1500000.00",
      "availability": {
        "available": true,
        "reasons": []
      }
    }
  }
  ```

---

### 2.2 Customer APIs

#### 4. Tạo đơn đặt phòng (Create Booking)
- **Method**: `POST`
- **URL**: `/api/customer/bookings`
- **Role**: CUSTOMER (`auth:sanctum`, `role:CUSTOMER`)
- **Request Body**:
  ```json
  {
    "room_id": 1,
    "check_in_date": "2026-10-25",
    "check_out_date": "2026-10-28",
    "guest_count": 2,
    "note": "Cần phòng tầng yên tĩnh"
  }
  ```
- **Quy tắc nghiệp vụ**:
  - Thực thi trong `DB::transaction`.
  - Khóa bi quan dòng phòng (`Room::lockForUpdate()`) để chống tranh chấp (race condition).
  - Kiểm tra lại availability trong transaction. Nếu phòng vừa bị đặt hoặc có lịch bảo trì đè lên, ném `RoomNotAvailableException` -> trả về **409 Conflict**.
  - Tự động sinh `booking_code` tiền tố `BK` kèm 8-10 ký tự ngẫu nhiên duy nhất (tự động thử lại nếu trùng).
  - Snapshot `price_per_night`, `number_of_nights`, `room_total` vào booking. Khởi tạo trạng thái ban đầu là `PENDING`.
- **Response thành công (201 Created)**:
  ```json
  {
    "success": true,
    "message": "Đặt phòng thành công.",
    "data": {
      "id": 15,
      "booking_code": "BK7A8B9C10",
      "customer_id": 2,
      "room_id": 1,
      "check_in_date": "2026-10-25",
      "check_out_date": "2026-10-28",
      "guest_count": 2,
      "number_of_nights": 3,
      "price_per_night": "500000.00",
      "room_total": "1500000.00",
      "status": "PENDING",
      "status_label": "Chờ xác nhận",
      "note": "Cần phòng tầng yên tĩnh",
      "room": {
        "id": 1,
        "room_number": "101",
        "name": "Phòng Standard 101",
        "capacity": 2,
        "property": {
          "id": 1,
          "name": "Khách sạn StayHub Central",
          "address": "123 Đường Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh",
          "phone": "02838111111"
        }
      },
      "booking_services": [],
      "created_at": "2026-10-11T14:30:00.000000Z",
      "updated_at": "2026-10-11T14:30:00.000000Z"
    }
  }
  ```
- **Response lỗi**:
  - `401 Unauthorized` (chưa đăng nhập).
  - `403 Forbidden` (đăng nhập bằng Host hoặc Admin).
  - `409 Conflict` (phòng vừa bị đặt trùng khoảng ngày).
  - `422 Unprocessable Entity` (validation ngày/số khách không hợp lệ).

#### 5. Danh sách lịch sử đặt phòng (Customer Booking History)
- **Method**: `GET`
- **URL**: `/api/customer/bookings`
- **Role**: CUSTOMER
- **Query Parameters**:
  - `status` (string, tùy chọn): Lọc theo `PENDING`, `CONFIRMED`, `CHECKED_IN`, `COMPLETED`, `CANCELLED`, `REJECTED`.
  - `per_page` (integer, tùy chọn): `1` đến `50`, mặc định `15`.
- **Response thành công (200 OK)**: Danh sách booking kèm `meta` phân trang.

#### 6. Chi tiết đặt phòng (Customer Booking Detail)
- **Method**: `GET`
- **URL**: `/api/customer/bookings/{booking}`
- **Role**: CUSTOMER
- **Quy tắc bảo mật**:
  - Được kiểm tra qua `BookingPolicy@view`.
  - **Booking của người khác trả về 404 Not Found** (không trả 403 để tránh lộ thông tin sự tồn tại của booking - IDOR protection).
- **Response thành công (200 OK)**:
  Trả về thông tin booking, thông tin cơ sở lưu trú, danh sách dịch vụ và trường `services_total` (tổng tiền dịch vụ).

#### 7. Hủy đặt phòng (Cancel Booking)
- **Method**: `POST`
- **URL**: `/api/customer/bookings/{booking}/cancel`
- **Role**: CUSTOMER
- **Quy tắc nghiệp vụ**:
  - Booking của người khác -> trả về **404 Not Found**.
  - Booking ở trạng thái `PENDING`: Được hủy tự do.
  - Booking ở trạng thái `CONFIRMED`: Chỉ được hủy nếu hôm nay `<= check_in_date - config('booking.cancel_before_days')` (mặc định trước 1 ngày). Nếu cận ngày, trả về **422** với thông điệp: `"Đã qua thời hạn hủy phòng cho phép."`.
  - Booking ở trạng thái `CHECKED_IN`, `COMPLETED`, `CANCELLED`, `REJECTED`: Không được hủy, trả về **422**.
- **Response thành công (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Hủy đặt phòng thành công.",
    "data": {
      "id": 15,
      "booking_code": "BK7A8B9C10",
      "status": "CANCELLED",
      "status_label": "Đã hủy"
    }
  }
  ```

#### 8. Xem danh sách dịch vụ đã dùng trong booking (Customer Booking Services)
- **Method**: `GET`
- **URL**: `/api/customer/bookings/{booking}/services`
- **Role**: CUSTOMER
- **Quy tắc bảo mật**: Booking của người khác trả về **404 Not Found**.
- **Response thành công (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Danh sách dịch vụ của đặt phòng.",
    "data": [
      {
        "id": 1,
        "booking_id": 15,
        "service_id": 2,
        "service_name": "Giặt ủi nhanh",
        "unit": "kg",
        "quantity": "2.00",
        "unit_price": "30000.00",
        "amount": "60000.00",
        "created_at": "2026-10-11T14:35:00.000000Z"
      }
    ]
  }
  ```

---

### 2.3 Host APIs - Cơ sở lưu trú (Host Properties - Stub)

#### 9. Danh sách cơ sở lưu trú của Host (Host Properties Stub)
- **Ghi chú**: Endpoint tạm (`TEMP-M1-STUB`) hỗ trợ Host chọn cơ sở lưu trú khi tạo dịch vụ và lọc booking, sẽ do Thành viên 1 thay thế hoàn chỉnh.
- **Method**: `GET`
- **URL**: `/api/host/properties`
- **Role**: HOST (`auth:sanctum`, `role:HOST`)
- **Response thành công (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Danh sách cơ sở lưu trú của host.",
    "data": [
      {
        "id": 1,
        "name": "Khách sạn StayHub Central"
      },
      {
        "id": 2,
        "name": "Homestay Dalat Cloud"
      }
    ]
  }
  ```
- **Response lỗi**: `401 Unauthorized` (chưa đăng nhập), `403 Forbidden` (sai vai trò).

---

### 2.4 Host APIs - Quản lý Dịch vụ Cơ sở (Property Services)

#### 10. Danh sách dịch vụ của Host (List Property Services)
- **Method**: `GET`
- **URL**: `/api/host/services`
- **Role**: HOST (`auth:sanctum`, `role:HOST`)
- **Query Parameters**: `property_id`, `status` (`true`/`false`), `keyword`, `per_page`.
- **Response thành công (200 OK)**: Danh sách dịch vụ kèm `meta` phân trang.

#### 10. Tạo dịch vụ mới (Create Property Service)
- **Method**: `POST`
- **URL**: `/api/host/services`
- **Role**: HOST
- **Request Body**:
  ```json
  {
    "property_id": 1,
    "name": "Bữa sáng Buffet",
    "description": "Phục vụ từ 6:30 đến 9:30",
    "unit": "người",
    "price": 120000,
    "status": true
  }
  ```
- **Quy tắc**: `property_id` phải thuộc sở hữu của Host (nếu không sở hữu -> trả về **404 Not Found**).
- **Response thành công (201 Created)**: Dữ liệu dịch vụ vừa tạo.

#### 11. Cập nhật dịch vụ (Update Property Service)
- **Method**: `PUT`
- **URL**: `/api/host/services/{service}`
- **Role**: HOST
- **Request Body**:
  ```json
  {
    "name": "Bữa sáng Buffet Cao Cấp",
    "description": "Có thêm món Âu",
    "unit": "người",
    "price": 150000
  }
  ```
- **Quy tắc**:
  - Không thể sửa dịch vụ của Host khác -> trả về **404 Not Found**.
  - Đổi giá dịch vụ tại đây **không ảnh hưởng** đến các hóa đơn/booking cũ đã snapshot giá trước đó.
- **Response thành công (200 OK)**.

#### 12. Bật / Tắt trạng thái dịch vụ (Toggle Service Status)
- **Method**: `PATCH`
- **URL**: `/api/host/services/{service}/status`
- **Role**: HOST
- **Request Body**:
  ```json
  {
    "status": false
  }
  ```
- **Quy tắc**: Danh mục dịch vụ cơ sở **chỉ bật/tắt, không có endpoint xóa cứng** để đảm bảo tính toàn vẹn dữ liệu kế toán.
- **Response thành công (200 OK)**.

---

### 2.4 Host APIs - Vận hành Quy trình Booking (Booking Workflow)

Ma trận chuyển đổi trạng thái hợp lệ của Booking:
```text
PENDING     ──> CONFIRMED
PENDING     ──> REJECTED
PENDING     ──> CANCELLED (Customer hủy)
CONFIRMED   ──> CHECKED_IN
CONFIRMED   ──> CANCELLED (Customer hủy trước hạn)
CHECKED_IN  ──> COMPLETED
```
Mọi bước chuyển trạng thái nằm ngoài ma trận trên đều trả về lỗi **422 Unprocessable Entity** kèm thông báo lỗi chuyển trạng thái tương ứng.

#### 13. Danh sách booking của cơ sở lưu trú (Host Bookings)
- **Method**: `GET`
- **URL**: `/api/host/bookings`
- **Role**: HOST
- **Query Parameters**: `status`, `property_id`, `start_date`, `end_date`, `keyword` (mã booking, tên hoặc email khách), `per_page`.
- **Response thành công (200 OK)**: Danh sách booking của Host kèm `meta` phân trang.

#### 14. Chi tiết booking (Host Booking Detail)
- **Method**: `GET`
- **URL**: `/api/host/bookings/{booking}`
- **Role**: HOST
- **Quy tắc**: Booking không thuộc cơ sở của Host -> trả về **404 Not Found**.
- **Response thành công (200 OK)**: Chứa thông tin khách hàng (`customer`), phòng (`room`), dịch vụ (`booking_services`) và tổng tiền dịch vụ (`services_total`).

#### 15. Xác nhận đơn đặt phòng (Confirm Booking)
- **Method**: `POST`
- **URL**: `/api/host/bookings/{booking}/confirm`
- **Role**: HOST
- **Quy tắc**:
  - Chỉ cho phép khi booking ở trạng thái `PENDING`.
  - Chạy trong `DB::transaction` với `lockForUpdate`.
  - Tái kiểm tra phòng trống (loại trừ chính booking hiện tại). Nếu phòng trùng lịch khác phát sinh -> ném lỗi **409 Conflict**.
  - Nếu trạng thái hiện tại khác `PENDING` -> trả về **422 Unprocessable Entity** (`InvalidBookingTransitionException`).
- **Response thành công (200 OK)**: Booking với `status = CONFIRMED`.

#### 16. Từ chối đơn đặt phòng (Reject Booking)
- **Method**: `POST`
- **URL**: `/api/host/bookings/{booking}/reject`
- **Role**: HOST
- **Request Body** (tùy chọn):
  ```json
  {
    "reason": "Phòng cần sửa chữa đột xuất"
  }
  ```
- **Quy tắc**:
  - Chỉ cho phép khi booking ở trạng thái `PENDING`.
  - Lý do từ chối (nếu có) được ghi nối tiếp vào trường `note`.
  - Trạng thái chuyển thành `REJECTED`. Phòng được giải phóng ngay lập tức trên hệ thống tìm kiếm.
- **Response thành công (200 OK)**: Booking với `status = REJECTED`.

#### 17. Xác nhận nhận phòng (Check-In)
- **Method**: `POST`
- **URL**: `/api/host/bookings/{booking}/check-in`
- **Role**: HOST
- **Quy tắc**: Chỉ cho phép khi booking ở trạng thái `CONFIRMED`. Trạng thái chuyển thành `CHECKED_IN`.
- **Response thành công (200 OK)**: Booking với `status = CHECKED_IN`.

#### 18. Xác nhận trả phòng (Check-Out)
- **Method**: `POST`
- **URL**: `/api/host/bookings/{booking}/check-out`
- **Role**: HOST
- **Quy tắc**:
  - Chỉ cho phép khi booking ở trạng thái `CHECKED_IN`.
  - Trạng thái chuyển thành `COMPLETED`. Giải phóng phòng.
  - **LƯU Ý QUAN TRỌNG**: Check-out chỉ hoàn tất vòng đời booking, **KHÔNG tạo hóa đơn (Invoice)** tại đây (nghiệp vụ tạo hóa đơn do Thành viên 3 phụ trách).
- **Response thành công (200 OK)**: Booking với `status = COMPLETED`.

---

### 2.5 Host APIs - Quản lý Dịch vụ trong Booking (Booking Services)

> **Quy tắc nghiệp vụ cốt lõi**:
> - Dịch vụ chỉ được phép **thêm / sửa / xóa** khi booking ở trạng thái **`CHECKED_IN`** (cấu hình `booking.service_allowed_statuses`). Nếu thực hiện khi booking ở trạng thái khác (ví dụ `PENDING`, `CONFIRMED`, `COMPLETED`), hệ thống sẽ từ chối và trả về **422 Unprocessable Entity**.
> - Route lồng `{booking}/services/{bookingService}`: bắt buộc kiểm tra `bookingService.booking_id === booking.id`. Nếu không trùng khớp -> trả về **404 Not Found**.

#### 19. Danh sách dịch vụ trong booking (Host List Booking Services)
- **Method**: `GET`
- **URL**: `/api/host/bookings/{booking}/services`
- **Role**: HOST
- **Response thành công (200 OK)**.

#### 20. Thêm dịch vụ vào booking (Add Service to Booking)
- **Method**: `POST`
- **URL**: `/api/host/bookings/{booking}/services`
- **Role**: HOST
- **Request Body**:
  ```json
  {
    "service_id": 2,
    "quantity": 3
  }
  ```
- **Quy tắc nghiệp vụ**:
  - `quantity`: Số thập phân trong khoảng `0.01` đến `9999.99`.
  - Booking phải có `status = CHECKED_IN`.
  - Dịch vụ phải thuộc cùng `property_id` với phòng của booking (sai cơ sở -> trả **422** `"Dịch vụ không thuộc cơ sở lưu trú của phòng này."`).
  - Dịch vụ phải đang được bật (`status = true`) (dịch vụ tạm ngưng -> trả **422** `"Dịch vụ này hiện đang tạm ngưng cung cấp."`).
  - Snapshot cố định `service_name`, `unit`, `unit_price` từ dòng `services` vào `booking_services`.
  - `amount = quantity * unit_price` do backend tính toán chính xác.
  - Nếu dịch vụ đã từng được thêm vào booking này trước đó: tự động cộng dồn số lượng `quantity = existing_quantity + new_quantity` và tính lại `amount` dựa trên giá snapshot cũ.
- **Response thành công (201 Created)**:
  ```json
  {
    "success": true,
    "message": "Thêm dịch vụ vào đặt phòng thành công.",
    "data": {
      "id": 1,
      "booking_id": 15,
      "service_id": 2,
      "service_name": "Giặt ủi nhanh",
      "unit": "kg",
      "quantity": "3.00",
      "unit_price": "30000.00",
      "amount": "90000.00",
      "created_at": "2026-10-11T14:40:00.000000Z"
    }
  }
  ```

#### 21. Cập nhật số lượng dịch vụ (Update Booking Service Quantity)
- **Method**: `PUT`
- **URL**: `/api/host/bookings/{booking}/services/{bookingService}`
- **Role**: HOST
- **Request Body**:
  ```json
  {
    "quantity": 5
  }
  ```
- **Quy tắc nghiệp vụ**:
  - Kiểm tra IDOR route lồng: `bookingService.booking_id === booking.id` (nếu sai -> trả về **404 Not Found**).
  - Booking phải đang ở trạng thái `CHECKED_IN`.
  - Giữ nguyên `unit_price` đã snapshot ban đầu, cập nhật lại `quantity` và `amount = quantity * unit_price`.
- **Response thành công (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Cập nhật số lượng dịch vụ thành công.",
    "data": {
      "id": 1,
      "booking_id": 15,
      "service_id": 2,
      "service_name": "Giặt ủi nhanh",
      "unit": "kg",
      "quantity": "5.00",
      "unit_price": "30000.00",
      "amount": "150000.00",
      "created_at": "2026-10-11T14:40:00.000000Z"
    }
  }
  ```

#### 22. Xóa dịch vụ khỏi booking (Remove Booking Service)
- **Method**: `DELETE`
- **URL**: `/api/host/bookings/{booking}/services/{bookingService}`
- **Role**: HOST
- **Quy tắc nghiệp vụ**:
  - Kiểm tra IDOR route lồng: `bookingService.booking_id === booking.id` (nếu sai -> trả về **404 Not Found**).
  - Booking phải đang ở trạng thái `CHECKED_IN`.
- **Response thành công (204 No Content)**: Body rỗng.

---

## 3. Frontend Member 2

Frontend dùng các route giao diện dưới đây; các API tương ứng vẫn giữ theo endpoint ở mục 2.

| Route giao diện | Trang | Service/API chính |
|---|---|---|
| `/search` | `pages/public/SearchResultPage.jsx` | `searchService.js` |
| `/booking/checkout` | `pages/customer/BookingCheckoutPage.jsx` | `bookingService.js` (`previewBooking`, `createBooking`) |
| `/customer/bookings` | `pages/customer/MyBookingsPage.jsx` | `bookingService.js` (`getCustomerBookings`) |
| `/customer/bookings/:bookingId` | `pages/customer/BookingDetailPage.jsx` | `bookingService.js` (`getCustomerBookingDetail`, `cancelBooking`) |
| `/host/bookings` | `pages/host/HostBookingListPage.jsx` | `hostBookingService.js`, `propertyServiceCatalogService.js` |
| `/host/bookings/:bookingId` | `pages/host/HostBookingDetailPage.jsx` | `hostBookingService.js` |
| `/host/bookings/:bookingId/services` | `pages/host/HostBookingServicesPage.jsx` | `bookingServiceUsageService.js`, `hostBookingService.js`, `propertyServiceCatalogService.js` |
| `/host/properties/:propertyId/services` | `pages/host/HostServiceListPage.jsx` | `propertyServiceCatalogService.js` |

`/host/services` là lối vào tương thích tạm thời: gọi `GET /api/host/properties`, rồi chuyển đến trang dịch vụ của cơ sở đầu tiên. Route này có comment `TEMP-M2-WIRING`; route chuẩn là route có `:propertyId` ở bảng trên.

`AvailabilityChecker.jsx` là component kiểm tra khả dụng dùng `availabilityService.js` khi được gắn vào trang phòng.

Hiện router chưa có trang chi tiết phòng nên `AvailabilityChecker` chưa được mount vào luồng giao diện. Luồng này cần nối khi có route/trang phòng được bàn giao.

Frontend hiển thị giá bằng VND, coi dữ liệu giá/tổng tiền từ backend là nguồn quyết định, và khóa nút mutation trong lúc gửi. Backend trả lỗi 401/403/404/409/422 theo các contract tại mục 1 và 2; trang booking dịch các mã lý do 409 sang tiếng Việt.
