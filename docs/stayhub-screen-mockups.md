# StayHub - Screen Mockups / Danh sách màn hình

## 1. Mục tiêu

Tài liệu này liệt kê các màn hình React cần thiết cho hệ thống StayHub.

Hệ thống chia thành:

- Public
- Customer
- Host
- Admin

---

# 2. Public / Customer Screens

## 2.1 Home Page

Route gợi ý:

```text
/
```

Mockup:

```text
┌──────────────────────────────────────────────────────┐
│ StayHub     Home   Properties     Login   Register   │
├──────────────────────────────────────────────────────┤
│                                                      │
│            Find your perfect stay                    │
│                                                      │
│ ┌──────────┬──────────┬───────────┬───────────────┐  │
│ │Check-in  │Check-out │ Guests    │ Search        │  │
│ └──────────┴──────────┴───────────┴───────────────┘  │
│                                                      │
├──────────────────────────────────────────────────────┤
│ Featured Properties                                  │
│                                                      │
│ [Image]          [Image]          [Image]            │
│ Sea View         Lakeside         Green Garden       │
│ ★4.8             ★4.5             ★4.7               │
│                                                      │
│                                               [💬]   │
└──────────────────────────────────────────────────────┘
```

Chức năng:

- tìm theo ngày;
- số khách;
- xem property nổi bật;
- mở chatbot.

---

## 2.2 Property Search Result

Route:

```text
/search
```

```text
┌───────────────────────────────────────────────────────────┐
│ Check-in: 10/10 | Check-out: 12/10 | 2 Guests | Search   │
├───────────────┬───────────────────────────────────────────┤
│ FILTER        │ RESULTS                                   │
│               │                                           │
│ Price         │ [Image] Deluxe Room                       │
│ Room Type     │ Capacity: 4                               │
│ Amenities     │ WiFi • TV • AC                            │
│ Capacity      │ 800,000/night                             │
│               │ [View Detail]                             │
│               │                                           │
│               │ [Image] Family Room                       │
│               │ 1,200,000/night                           │
└───────────────┴───────────────────────────────────────────┘
```

---

## 2.3 Property Detail

Route:

```text
/properties/:id
```

Hiển thị:

- tên;
- địa chỉ;
- rating;
- gallery;
- description;
- tiện nghi;
- check-in/out time;
- danh sách room phù hợp.

---

## 2.4 Room Detail

Route:

```text
/rooms/:id
```

```text
┌─────────────────────────────────────────────────────┐
│ Deluxe Room                                         │
├──────────────────────────────┬──────────────────────┤
│                              │ Check-in             │
│       IMAGE GALLERY          │ [10/10/2026]         │
│                              │                      │
│                              │ Check-out            │
│                              │ [13/10/2026]         │
│                              │                      │
│                              │ Guests [4]           │
├──────────────────────────────┤                      │
│ 800,000 / night              │ 3 nights             │
│ Capacity: 4                  │ Total: 2,400,000     │
│ WiFi • TV • AC               │                      │
│ Description...               │ [ BOOK NOW ]         │
└──────────────────────────────┴──────────────────────┘
```

---

## 2.5 Booking Checkout

Route:

```text
/bookings/create
```

```text
┌───────────────────────────────────────────────┐
│ Booking Summary                               │
├───────────────────────────────────────────────┤
│ Sea View Homestay                             │
│ Deluxe Room                                   │
│                                               │
│ Check-in:  10/10/2026                         │
│ Check-out: 13/10/2026                         │
│ Guests: 2                                     │
│                                               │
│ 3 nights × 800,000                            │
│ Room total: 2,400,000                         │
│                                               │
├───────────────────────────────────────────────┤
│ Customer                                      │
│ Full name [____________]                      │
│ Phone     [____________]                      │
│ Note      [____________]                      │
│                                               │
│ [ CONFIRM BOOKING ]                           │
└───────────────────────────────────────────────┘
```

---

## 2.6 My Bookings

Route:

```text
/my-bookings
```

Các tab:

```text
All
Pending
Confirmed
Checked-in
Completed
Cancelled
```

Card:

```text
BK001
Deluxe Room
10/10 - 13/10
2,400,000
CONFIRMED

[View Detail]
```

---

## 2.7 Booking Detail

Route:

```text
/my-bookings/:id
```

```text
Booking #BK001

PENDING → CONFIRMED → CHECKED_IN → COMPLETED

Room
Check-in
Check-out
Guests
Room Total

Services used:
Breakfast ×2      200,000
Laundry ×3kg      150,000

[View Invoice]
[Cancel Booking]
```

---

## 2.8 Invoice Detail

Route:

```text
/my-invoices/:id
```

```text
┌─────────────────────────────────────────────────────┐
│ INVOICE #INV001                                     │
├─────────────────────────────────────────────────────┤
│ Customer: Nguyen Van A                              │
│ Property: Sea View Homestay                         │
│ Room: Deluxe Room                                   │
│                                                     │
├─────────────────────────────────────────────────────┤
│ Item                    Qty    Price       Amount    │
│ Deluxe Room              3     800,000   2,400,000  │
│ Breakfast                2     100,000     200,000  │
│ Laundry                  3      50,000     150,000  │
├─────────────────────────────────────────────────────┤
│ Subtotal                              2,750,000      │
│ Discount                                      0      │
│ Extra Fee                                     0      │
│ TOTAL                                  2,750,000      │
│                                                     │
│ Payment Status: PAID                               │
└─────────────────────────────────────────────────────┘
```

---

## 2.9 Review Screen

Route:

```text
/my-bookings/:id/review
```

```text
Rate your stay

☆ ☆ ☆ ☆ ☆

Comment:
[                                  ]

[Submit Review]
```

---

## 2.10 Profile

Route:

```text
/profile
```

Fields:

```text
Avatar
Full name
Email
Phone
Address
Password
```

---

## 2.11 Chatbot

Có thể là floating widget trên toàn bộ Customer layout.

```text
┌───────────────────────────────┐
│ StayHub Assistant          X  │
├───────────────────────────────┤
│ Bot: Xin chào!                │
│                               │
│ User: Có phòng cho 4 người    │
│ dưới 1 triệu không?           │
│                               │
│ Bot: Có 3 phòng phù hợp...    │
├───────────────────────────────┤
│ Type message...           ➤   │
└───────────────────────────────┘
```

---

# 3. Host Screens

Host layout:

```text
Dashboard
Properties
Room Types
Rooms
Amenities
Services
Maintenance
Bookings
Invoices
Reviews
Profile
```

---

## 3.1 Host Dashboard

Route:

```text
/host/dashboard
```

```text
┌─────────────┬─────────────┬─────────────┬─────────────┐
│ Rooms       │ Pending     │ Revenue     │ Maintenance │
│ 20          │ 5           │ 25,000,000  │ 2           │
└─────────────┴─────────────┴─────────────┴─────────────┘

Revenue Chart

Recent Bookings
```

---

## 3.2 Property List

Route:

```text
/host/properties
```

```text
Name                Type       Status      Actions
Sea View Homestay   HOMESTAY   ACTIVE      Edit Delete
Lakeside Hotel      HOTEL      ACTIVE      Edit Delete

[+ Add Property]
```

---

## 3.3 Property Form

Route:

```text
/host/properties/create
/host/properties/:id/edit
```

Fields:

```text
Name
Type
Address
Phone
Description
Check-in time
Check-out time
Images
Status
```

---

## 3.4 Room Type Management

Route:

```text
/host/room-types
```

Fields:

```text
Property
Type Name
Description
```

---

## 3.5 Room Management

Route:

```text
/host/rooms
```

```text
Room  Type      Capacity  Price       Status     Actions
101   Deluxe    2         800,000     ACTIVE     Edit
102   Family    4         1,200,000   ACTIVE     Edit
103   Standard  2         600,000     INACTIVE   Edit
```

---

## 3.6 Room Form

Route:

```text
/host/rooms/create
/host/rooms/:id/edit
```

Fields:

```text
Property
Room Type
Room Number
Name
Capacity
Price Per Night
Description
Amenities
Images
Status
```

---

# 4. Service Management

## 4.1 Service List

Route:

```text
/host/services
```

```text
┌────────────────────────────────────────────────────────┐
│ Services                                [+ Add Service] │
├────────────────────────────────────────────────────────┤
│ Name             Unit       Price       Status  Actions │
│ Breakfast        suất       100,000     Active  Edit    │
│ Laundry          kg          50,000     Active  Edit    │
│ Airport Pickup   lượt       300,000     Active  Edit    │
└────────────────────────────────────────────────────────┘
```

---

## 4.2 Service Form

Route:

```text
/host/services/create
/host/services/:id/edit
```

Fields:

```text
Property
Service Name
Description
Unit
Price
Status
```

---

# 5. Maintenance Screens

## 5.1 Maintenance List

Route:

```text
/host/maintenances
```

```text
Room | Start Date | End Date | Reason | Status | Actions
```

---

## 5.2 Maintenance Form

Fields:

```text
Room
Start Date
End Date
Reason
Status
```

---

# 6. Booking Management - Host

## 6.1 Booking List

Route:

```text
/host/bookings
```

```text
Code   Customer   Room   Check-in   Check-out   Total      Status
BK001  Nguyen A   101    10/10      13/10       2,400,000  PENDING
```

Actions:

```text
View
Confirm
Reject
```

---

## 6.2 Booking Detail - Host

Route:

```text
/host/bookings/:id
```

```text
Booking #BK001

Customer
Room
Check-in / Check-out
Guests
Room Total

Status: CHECKED_IN

[Confirm]
[Reject]
[Check-in]
[Check-out]
```

Chỉ hiển thị action phù hợp trạng thái.

---

# 7. Add Services to Booking

Đây là màn hình mới quan trọng.

Route:

```text
/host/bookings/:id/services
```

Mockup:

```text
┌──────────────────────────────────────────────────────┐
│ Booking #BK001 - Add Services                        │
├──────────────────────────────────────────────────────┤
│ Customer: Nguyen Van A                               │
│ Room: Deluxe 101                                     │
│                                                      │
├──────────────────────────────────────────────────────┤
│ Add Service                                          │
│                                                      │
│ Service       [Breakfast ▼]                          │
│ Quantity      [2]                                    │
│ Unit Price    100,000                                │
│ Amount        200,000                                │
│                                                      │
│ [ADD SERVICE]                                        │
├──────────────────────────────────────────────────────┤
│ Services Used                                        │
│                                                      │
│ Breakfast    2 suất × 100,000     200,000   Delete   │
│ Laundry      3 kg   × 50,000      150,000   Delete   │
└──────────────────────────────────────────────────────┘
```

---

# 8. Invoice Management - Host

## 8.1 Invoice List

Route:

```text
/host/invoices
```

```text
Invoice   Booking   Customer   Total       Payment    Actions
INV001    BK001     Nguyen A   2,750,000   UNPAID     View
```

---

## 8.2 Create Invoice

Có thể được tạo khi Host nhấn `Check-out`.

Route:

```text
/host/bookings/:id/create-invoice
```

Mockup:

```text
┌──────────────────────────────────────────────────────────┐
│ Create Invoice - Booking #BK001                          │
├──────────────────────────────────────────────────────────┤
│ Room                                                     │
│ Deluxe Room    3 nights × 800,000       2,400,000        │
│                                                          │
│ Services                                                 │
│ Breakfast       2 × 100,000                200,000        │
│ Laundry         3 × 50,000                 150,000        │
├──────────────────────────────────────────────────────────┤
│ Subtotal                                  2,750,000       │
│ Discount          [0____________]                         │
│ Extra Fee         [0____________]                         │
│                                                          │
│ TOTAL                                     2,750,000       │
│                                                          │
│ [CREATE INVOICE]                                         │
└──────────────────────────────────────────────────────────┘
```

---

## 8.3 Invoice Detail - Host

Route:

```text
/host/invoices/:id
```

```text
INVOICE #INV001

Customer Information
Property
Room
Booking Code

Items
------------------------------------------------
ROOM      Deluxe Room     3 x 800,000   2,400,000
SERVICE   Breakfast       2 x 100,000     200,000
SERVICE   Laundry         3 x 50,000      150,000

Subtotal:     2,750,000
Discount:             0
Extra Fee:            0
Total:        2,750,000

Payment: UNPAID

[MARK AS PAID]
```

---

# 9. Admin Screens

Admin sidebar:

```text
Dashboard
Users
Hosts
Properties
Amenities
Bookings
Invoices
Statistics
```

---

## 9.1 Admin Dashboard

Route:

```text
/admin/dashboard
```

```text
Users       500
Hosts        30
Properties   45
Rooms       320
Bookings    1,200
Revenue     xxx

Booking Status Chart
Revenue Chart
```

---

## 9.2 User Management

Route:

```text
/admin/users
```

Columns:

```text
Name
Email
Role
Status
Created At
Actions
```

---

## 9.3 Host Management

Route:

```text
/admin/hosts
```

Columns:

```text
Host
Properties
Status
Created At
Actions
```

Actions:

```text
View
Block
Unblock
```

---

## 9.4 Property Management

Route:

```text
/admin/properties
```

Columns:

```text
Property
Host
Type
Rooms
Status
Actions
```

---

## 9.5 Amenity Management

Route:

```text
/admin/amenities
```

Admin quản lý danh mục tiện nghi chung.

---

## 9.6 Global Booking Management

Route:

```text
/admin/bookings
```

Admin xem toàn hệ thống.

Có filter:

```text
Host
Property
Customer
Status
Date
```

---

## 9.7 Global Invoice Management

Route:

```text
/admin/invoices
```

Admin có thể:

- xem tất cả hóa đơn;
- filter theo Host;
- filter theo property;
- filter theo payment status;
- xem tổng doanh thu.

---

# 10. Tổng số màn hình nên làm

## Public / Customer

```text
1. Home
2. Search Result
3. Property Detail
4. Room Detail
5. Login
6. Register
7. Booking Checkout
8. My Bookings
9. Booking Detail
10. Invoice Detail
11. Review
12. Profile
13. Chatbot
```

## Host

```text
14. Dashboard
15. Property List
16. Property Form
17. Room Type List
18. Room Type Form
19. Room List
20. Room Form
21. Service List
22. Service Form
23. Maintenance List
24. Maintenance Form
25. Booking List
26. Booking Detail
27. Booking Services
28. Invoice List
29. Create Invoice
30. Invoice Detail
31. Review List
```

## Admin

```text
32. Dashboard
33. User Management
34. Host Management
35. Property Management
36. Amenity Management
37. Global Booking List
38. Global Invoice List
39. Statistics
```

Không nhất thiết mỗi item phải là một page hoàn toàn riêng. Một số `Form` có thể dùng chung component Create/Edit.

---

# 11. Thứ tự nên implement UI

Ưu tiên:

```text
1. Login / Register
2. Host Property
3. Host Room Type
4. Host Room
5. Customer Search
6. Room Detail
7. Booking
8. Host Booking Process
9. Maintenance
10. Services
11. Booking Services
12. Invoice
13. Review
14. Dashboard
15. Chatbot
```

Đi theo thứ tự này giúp hệ thống luôn có một luồng nghiệp vụ chạy được.
