# Điều chỉnh tích hợp TV1 / TV2

**Ngày:** 01/11/2026  
**Nhánh:** `feature/member-2-booking`  
**Phạm vi:** Xử lý ba vấn đề tích hợp phát hiện sau khi ghép `origin/dev`, đồng thời giữ nguyên hợp đồng hiện có của module Booking và frontend.

## 1. Tương thích factory

Các thay đổi model của TV1 đã bỏ `HasFactory` ở một số model mà test và seeder TV2 cần tạo dữ liệu. Đã bổ sung lại `HasFactory` cho `Property`, `Room`, `RoomType`, `Amenity` và `Maintenance`. `UserFactory` được bổ sung lại các giá trị role/status mặc định cùng các state `customer()`, `host()`, `admin()` và `blocked()`.

Những thay đổi này giúp test và seeder tiếp tục dùng factory mà không thay đổi schema cơ sở dữ liệu, quan hệ hoặc casts nghiệp vụ của các model.

## 2. Middleware trạng thái tài khoản và vai trò

Các route booking hiện chạy middleware theo thứ tự:

```text
auth:sanctum -> account.active -> role
```

`EnsureAccountIsActive` chịu trách nhiệm kiểm tra trạng thái tài khoản và vô hiệu hóa session khi tài khoản bị khóa hoặc chưa kích hoạt. `CheckRole` chỉ kiểm tra vai trò để tránh lặp logic kiểm tra trạng thái. Feature test kiểm tra tài khoản bị khóa bị từ chối; test rà soát route cũng xác nhận middleware được gắn đúng thứ tự mà không phụ thuộc vào tổng số route cố định.

## 3. API Host Properties

Đã thay closure đặt trực tiếp trong `routes/api/booking.php` bằng route do TV1 quản lý tại `routes/api/properties.php`, cùng các lớp:

```text
HostPropertyController -> HostPropertyQueryService -> HostPropertyResource
```

Giữ nguyên `GET /api/host/properties` để frontend không phải đổi. Query chỉ lấy cơ sở thuộc Host đang đăng nhập và sắp xếp theo tên; resource chỉ trả `id` và `name` trong cấu trúc `{success, message, data}` hiện có. Test kiểm tra truy cập khi chưa đăng nhập, Customer bị từ chối và Host chỉ nhận dữ liệu cơ sở của mình.

## Kết quả kiểm tra

- `php artisan test --compact`: **490 test passed, 0 failed (2.545 assertions)**.
- `php artisan route:list --path=api/host/properties -v`: route trỏ tới `HostPropertyController@index`; middleware theo thứ tự `auth:sanctum`, `EnsureAccountIsActive`, `CheckRole:HOST`.
- `php artisan migrate:fresh --seed`: đã chạy thành công ngày 01/11/2026 sau khi ghép migration TV1. Các điều chỉnh này không thêm migration hay thay đổi schema.
- `npm run lint`: thành công, còn 3 warning Fast Refresh tại `badge.jsx`, `button.jsx` và `sidebar.jsx`.
- `npm run build`: thành công; Vite cảnh báo một JavaScript chunk lớn hơn 500 kB.

## Việc còn lại

- Chưa kiểm tra end-to-end bằng trình duyệt.
- Ba warning lint và cảnh báo bundle lớn chưa được xử lý trong phạm vi điều chỉnh tích hợp này.
- Không tạo commit hoặc push. Merge vẫn chưa commit; `frontend/.env` không bị sửa trong task này.
