# StayHub

StayHub là hệ thống quản lý và đặt phòng khách sạn / homestay theo mô hình **đa chủ cơ sở**.

Hệ thống hỗ trợ 3 vai trò chính:

- **Admin**: quản trị toàn bộ hệ thống.
- **Host**: chủ khách sạn / homestay, quản lý cơ sở, phòng, dịch vụ, booking và hóa đơn.
- **Customer**: tìm kiếm phòng, đặt phòng, theo dõi booking, xem hóa đơn và đánh giá.

Ngoài các chức năng booking cơ bản, hệ thống còn hỗ trợ:

- quản lý lịch bảo trì phòng;
- kiểm tra phòng trống theo khoảng ngày;
- quản lý dịch vụ phát sinh trong quá trình lưu trú;
- tạo hóa đơn chi tiết;
- dashboard thống kê;
- chatbot đơn giản sử dụng dữ liệu nội bộ.

---

## 1. Công nghệ sử dụng

### Backend

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- Laravel Eloquent ORM
- MySQL
- Composer

### Frontend

- React
- Vite
- React Router DOM
- Axios
- JavaScript / JSX

### Local Development

- XAMPP
- MySQL
- Git

---

## 2. Vai trò trong hệ thống

### Admin

- quản lý Customer;
- quản lý Host;
- khóa / mở tài khoản;
- giám sát Property;
- xem toàn bộ Booking;
- xem toàn bộ Invoice;
- quản lý Amenity;
- xem dashboard và thống kê.

### Host

- quản lý Property;
- quản lý Room Type;
- quản lý Room;
- quản lý Amenity;
- quản lý Maintenance;
- quản lý Service;
- xử lý Booking;
- Confirm / Reject;
- Check-in / Check-out;
- thêm dịch vụ khách đã sử dụng;
- tạo Invoice;
- cập nhật trạng thái thanh toán;
- xem Review;
- xem Dashboard.

### Customer

- đăng ký / đăng nhập;
- cập nhật profile;
- tìm kiếm Property / Room;
- tìm phòng trống theo ngày;
- đặt phòng;
- hủy Booking;
- xem Booking History;
- xem dịch vụ đã sử dụng;
- xem Invoice;
- Review;
- sử dụng Chatbot.

---

## 3. Luồng nghiệp vụ chính

```text
Host tạo Property
        ↓
Host tạo Room
        ↓
Customer tìm phòng
        ↓
Kiểm tra Availability
        ↓
Customer tạo Booking
        ↓
Host xác nhận Booking
        ↓
Check-in
        ↓
Sử dụng dịch vụ
        ↓
Check-out
        ↓
Tạo Invoice
        ↓
Thanh toán
        ↓
Customer Review
```

---

## 4. Cấu trúc repository

```text
stayhub/
├── backend/
├── frontend/
├── docs/
├── ARCHITECTURE.md
└── README.md
```

### `backend/`

Laravel 12 REST API.

### `frontend/`

React + Vite application.

### `docs/`

Tài liệu phân tích và thiết kế:

```text
stayhub-usecase-system.md
stayhub-erd.md
stayhub-screen-mockups.md
```

### `ARCHITECTURE.md`

Mô tả chi tiết kiến trúc hệ thống và nguyên tắc thiết kế.

---

## 5. Yêu cầu môi trường

Cần cài:

```text
PHP 8.2+
Composer
Node.js
npm
Git
XAMPP
MySQL
```

Môi trường hiện tại của project:

```text
PHP 8.2
Laravel 12
Node.js 24
npm 11
MySQL / XAMPP
```

---

## 6. Cách chạy dự án local

### Backend

```bash
cd backend
composer install
```

Tạo `.env`:

```bash
copy .env.example .env
```

Tạo application key:

```bash
php artisan key:generate
```

Cấu hình MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stayhub
DB_USERNAME=root
DB_PASSWORD=
```

Chạy migration:

```bash
php artisan migrate
```

Chạy Laravel:

```bash
php artisan serve --host=localhost
```

Backend:

```text
http://localhost:8000
```

---

### Frontend

Mở terminal khác:

```bash
cd frontend
npm install
```

Tạo file:

```text
frontend/.env
```

Nội dung:

```env
VITE_BACKEND_URL=http://localhost:8000
```

Chạy:

```bash
npm run dev
```

Frontend:

```text
http://localhost:5173
```

---

## 7. Authentication

Project sử dụng **Laravel Sanctum SPA Authentication**.

Flow hiện tại:

```text
GET  /sanctum/csrf-cookie
POST /login
GET  /api/me
POST /logout
```

`GET /api/me` được bảo vệ bởi:

```text
auth:sanctum
```

Chi tiết kiến trúc authentication được mô tả trong `ARCHITECTURE.md`.

---

## 8. Phạm vi phát triển

### Core

```text
Authentication
Role / Authorization
Property
Room Type
Room
Amenity
Maintenance
Availability
Booking
Booking Workflow
Service
Booking Service
Invoice
Invoice Item
Review
Dashboard
```

### Advanced

```text
Chatbot
FAQ
Chat History
```

### Optional

```text
Online Payment
Voucher
Email Notification
Advanced Statistics
Vector Database
Recommendation System
```

---

## 9. Development Roadmap

```text
Phase 1 - Project Initialization
Phase 2 - Database Migration + Model
Phase 3 - Authentication + Role Authorization
Phase 4 - Property / Room Management
Phase 5 - Search + Availability
Phase 6 - Booking Workflow
Phase 7 - Service + Invoice
Phase 8 - Review + Dashboard
Phase 9 - Chatbot
Phase 10 - Testing + Deployment
```

---

## 10. Project Name

```text
StayHub
```

**Hotel & Homestay Booking Management System**
