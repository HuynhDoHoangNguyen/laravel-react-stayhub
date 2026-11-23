# Dữ liệu demo StayHub

Chạy bộ seeder trên cơ sở dữ liệu đã migrate bằng `php artisan migrate:fresh --seed`. Bộ dữ liệu dùng ngày tương đối theo `Carbon::today()`, nên các booking tương lai, đang lưu trú và quá khứ vẫn có ý nghĩa ở ngày chạy. Chạy riêng `php artisan db:seed` lần thứ hai không được hỗ trợ vì dữ liệu dùng ID và mã cố định.

## Tài khoản

Mật khẩu chung cho tất cả tài khoản: `12345678`.

| Email | Vai trò | Trạng thái | Mật khẩu |
|---|---|---|---|
| admin@stayhub.com | ADMIN | ACTIVE | 12345678 |
| host1@stayhub.com | HOST | ACTIVE | 12345678 |
| host2@stayhub.com | HOST | ACTIVE | 12345678 |
| customer1@stayhub.com | CUSTOMER | ACTIVE | 12345678 |
| customer2@stayhub.com | CUSTOMER | ACTIVE | 12345678 |
| customer3@stayhub.com | CUSTOMER | ACTIVE | 12345678 |
| customer4@stayhub.com | CUSTOMER | BLOCKED | 12345678 |
| customer5@stayhub.com | CUSTOMER | INACTIVE | 12345678 |

## Số bản ghi

| Bảng | Số dòng |
|---|---:|
| users | 8 |
| properties | 4 |
| property_images | 8 |
| room_types | 8 |
| rooms | 13 |
| room_images | 26 |
| amenities | 12 |
| room_amenities | 52 |
| maintenances | 4 |
| services | 13 |
| bookings | 16 |
| booking_services | 10 |
| invoices | 4 |
| invoice_items | 11 |
| reviews | 3 |
| faqs | 8 |
| chat_sessions | 2 |
| chat_messages | 8 |

## Kịch bản demo

- **Tìm phòng và đặt:** đăng nhập `customer1@stayhub.com`; dùng phòng 101 hoặc 102 tại Khách sạn Sông Hàn. Các booking pending/confirmed chiếm các ngày tương lai khác nhau.
- **Host xác nhận:** đăng nhập `host1@stayhub.com`, mở một booking PENDING của cơ sở Sông Hàn và xác nhận.
- **Check-in và thêm dịch vụ:** dùng booking CHECKED_IN phòng 202, customer1; host1 có thể thêm giặt ủi và ăn sáng. Phòng 202 có lịch nhận/trả từ hôm qua đến ngày mai.
- **Check-out:** hoàn tất booking CHECKED_IN qua host workflow; checkout chỉ đổi trạng thái thành COMPLETED, không tự tạo invoice.
- **Hủy:** dùng booking PENDING của customer2 hoặc customer4; booking CONFIRMED cũng nằm đủ xa ngày nhận phòng để thử quy tắc hạn hủy.
- **Tạo hóa đơn:** trong 5 booking COMPLETED, bốn booking đầu theo dữ liệu có invoice; booking ở phòng 201 (booking code `BKDEMO0013`) chưa có invoice để thử nút tạo hóa đơn.
- **Review:** các booking code `BKDEMO0010`, `BKDEMO0011`, `BKDEMO0012` đã có review lần lượt 5, 4 và 2 sao. `BKDEMO0013` và `BKDEMO0009` chưa có review.
- **Tài khoản bị chặn:** `customer4@stayhub.com` có trạng thái BLOCKED; `customer5@stayhub.com` có trạng thái INACTIVE.

## Seeder theo thành viên

`DatabaseSeeder` gọi `Member1DataSeeder`, `Member2DataSeeder`, `Member3DataSeeder` theo thứ tự. Khi nhận code chính thức của Member 1, thay phần TEMP-M1-STUB trong `database/seeders/Member1DataSeeder.php`. Khi nhận code Member 3, thay phần TEMP-M3-STUB trong `database/seeders/Member3DataSeeder.php`. Seeder Member 2 sở hữu services, bookings và booking_services.

Ảnh dùng URL `https://picsum.photos/seed/<slug>/800/600`; cần kết nối Internet để tải ảnh. Ngày demo được tính từ ngày chạy seeder. Migration bổ sung `chat_messages.updated_at` là migration riêng; migration khởi tạo được giữ nguyên.

## Quy ước hóa đơn

`subtotal` bằng tổng `invoice_items.amount`; `total_amount = subtotal - discount_amount + extra_fee`. Dòng `SURCHARGE` trong demo được cộng trực tiếp vào subtotal, vì vậy `extra_fee` vẫn bằng 0 để không tính phụ phí hai lần. Quy ước cho dòng `DISCOUNT`: lưu `amount` âm và cộng vào subtotal; khi đã có dòng này thì `discount_amount` bằng 0. Nếu dùng trường `discount_amount` thì không thêm dòng `DISCOUNT` để tránh trừ hai lần.
