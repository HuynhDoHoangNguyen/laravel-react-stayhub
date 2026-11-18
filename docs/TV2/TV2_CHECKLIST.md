# StayHub TV2 — checklist tiến độ và nghiệm thu

Phạm vi: Availability, Search, Booking, Booking Workflow, Services và các lát giao diện U1–U3. Đối chiếu 2026-11-17 trên `feature/member-2-booking`, HEAD `b7a1981`. Commit được xác nhận bằng `git log`; các số kiểm thử tổng hợp là lượt chạy hiện tại, không phải số test riêng từng lát.

## Use case TV2 đã có triển khai

Đối chiếu tên và số UC theo [stayhub-usecase-system.md](../stayhub-usecase-system.md), ranh giới sở hữu theo [phân công nhóm](../stayhub-team-work-assignment.md), mã thay đổi theo `git log` và phạm vi đã ghi trong [handover TV2](BASELINE_HANDOVER.md). “Đã triển khai” ở bảng nghĩa là có code và commit tương ứng; không tự xác nhận nghiệm thu trình duyệt hay duyệt của nhóm trưởng.

| UC | Tên trong Use Case System | Phần TV2 hiện có | Commit refactor liên quan | Tình trạng |
| --- | --- | --- | --- | --- |
| UC6 | Tìm kiếm phòng trống | Search API/UI, tiêu chí lọc, phân trang | `266a1d8`, UI `6b7a676` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC7 | Lọc phòng | Bộ lọc tìm kiếm | `266a1d8`, UI `6b7a676` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC9 | Đặt phòng | Preview và tạo booking | `ba70c47` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC10 | Xem tổng tiền phòng | Preview giá phòng trước khi tạo booking | `ba70c47` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC11 | Xem lịch sử booking | Customer list/filter/pagination | `c8ccf5d`, UI `58d3f81` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC12 | Xem chi tiết booking | Customer booking detail | `c8ccf5d`, UI `58d3f81` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC13 | Hủy booking | Hủy PENDING và CONFIRMED theo điều kiện hiện hành | `c8ccf5d` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC14 | Xem dịch vụ đã sử dụng | Booking detail tải các service gắn booking | Có trong luồng Customer detail; không gán commit refactor riêng | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC24 | Quản lý dịch vụ | Host list/create/update/ẩn-hiện danh mục theo property | `8d66132`, UI `65b6e00` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC25 | Thêm dịch vụ vào booking | Thêm service vào booking CHECKED_IN | `8d66132` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC26 | Cập nhật số lượng dịch vụ | Sửa quantity dòng booking service | `8d66132` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC27 | Xóa dịch vụ khỏi booking | Xóa dòng booking service | `8d66132` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC28 | Xem booking của cơ sở | Host list/filter/pagination theo property | `b7221bf`, UI `65b6e00` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC29 | Xem chi tiết booking | Host booking detail | `b7221bf`, UI `65b6e00` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC30 | Xác nhận booking | PENDING → CONFIRMED, recheck availability | `b7221bf` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC31 | Từ chối booking | PENDING → REJECTED | `b7221bf` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC32 | Check-in | CONFIRMED → CHECKED_IN | `b7221bf` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC33 | Check-out | CHECKED_IN → COMPLETED | `b7221bf` | Transition đã triển khai; không tạo Invoice theo bất biến hiện hành |
| UC49 | Kiểm tra Availability | Availability service/query | `266a1d8` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC50 | Kiểm tra booking bị trùng | Overlap booking giữ phòng | `266a1d8` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC51 | Kiểm tra lịch bảo trì | Availability loại trừ maintenance | `266a1d8` | Đã triển khai; không đồng nghĩa TV2 sở hữu UC23 quản lý lịch bảo trì |
| UC52 | Kiểm tra sức chứa | Validate guest count/capacity | `266a1d8`, `ba70c47` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC53 | Tính số đêm | Booking pricing/preview | `ba70c47` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC54 | Tính tiền phòng | Preview/create dùng snapshot giá phòng | `ba70c47` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC55 | Tính tiền dịch vụ | Amount theo snapshot dịch vụ và quantity | `8d66132` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |
| UC59 | Kiểm tra điều kiện hủy | Kiểm tra trạng thái và hạn hủy CONFIRMED | `c8ccf5d` | Đã triển khai; nghiệm thu riêng chưa ghi nhận |

**Lưu ý lệch tài liệu:** mục 9 của `stayhub-usecase-system.md` mô tả check-out tiếp tục tạo Invoice. Bất biến đã thống nhất cho code TV2 là check-out chỉ chuyển booking sang COMPLETED, không tạo Invoice; Invoice là điểm nối riêng với TV3. Ghi nhận để nhóm trưởng thống nhất tài liệu, không thay đổi workflow/contract trong cập nhật này.

UC8 (chi tiết phòng) và UC23 (quản lý lịch bảo trì) thuộc Catalog/Maintenance của TV1, không tính là UC TV2. UC34–UC37 và Invoice/Review thuộc TV3; UC49–UC55/UC59 ở đây chỉ ghi phần include/internal do các luồng TV2 sử dụng.

| Luồng/lát | Phạm vi | Commit | Test/build hiện có | Trạng thái kỹ thuật | Nhóm trưởng nghiệm thu |
| --- | --- | --- | --- | --- | --- |
| Availability + Search | Query khả dụng, ngày nửa mở, maintenance, booking giữ phòng; API Search | `266a1d8` | Full backend hiện tại PASS 1.278/7.320; frontend 172 PASS. Số riêng lát không ghi nhận. | Refactor đã commit | Chưa ghi nhận |
| Checkout source | `getBookableProperty` cho dữ liệu room-search; giữ `getPropertyById` hiện tại | `b70a91d` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Đã commit; quyết định Catalog còn mở | Chưa ghi nhận |
| Booking preview + create | Preview, pricing snapshot, tạo booking và transaction | `ba70c47` | Full backend hiện tại PASS 1.278/7.320; frontend 172 PASS | Refactor đã commit | Chưa ghi nhận |
| Customer đọc/hủy | List, detail, cancel và ownership | `c8ccf5d` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Refactor đã commit | Chưa ghi nhận |
| Host workflow | Host list/detail, confirm/reject/check-in/check-out | `b7221bf` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Refactor đã commit | Chưa ghi nhận |
| Services | Service catalog theo property và dịch vụ gắn booking | `8d66132` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Refactor đã commit | Chưa ghi nhận |
| U1 giao diện | Search + checkout | `6b7a676` | Frontend 172 PASS; lint/build được chạy ở lượt U3 trước đây, không chạy lại trong lượt tài liệu | Đã commit; browser acceptance chưa ghi nhận | Chưa ghi nhận |
| U2 giao diện | Customer bookings/detail, hộp hủy cục bộ | `58d3f81` | Frontend 172 PASS; lint/build được chạy ở lượt U3 trước đây, không chạy lại trong lượt tài liệu | Đã commit; browser acceptance chưa ghi nhận | Chưa ghi nhận |
| U3 giao diện | Host bookings/workflow/services và danh mục | `65b6e00` | Frontend 172 PASS; lượt U3 báo lint 0 lỗi/3 warning nền, build PASS với cảnh báo bundle lớn | Đã commit; browser acceptance chưa ghi nhận | Chưa ghi nhận |

## Bằng chứng kiểm thử toàn nhánh

| Lệnh ngày 2026-11-17 | Kết quả |
| --- | --- |
| `php artisan test` (backend) | 1.278 passed, 7.320 assertions; exit 0 |
| `node --experimental-test-module-mocks --test tests/*.test.js` (frontend) | 172 passed, 0 failed; exit 0 |
| `npm run lint` | Không chạy trong lượt đối chiếu tài liệu này |
| `npm run build` | Không chạy trong lượt đối chiếu tài liệu này |

Test full-suite hiện tại không thay thế việc đối chiếu response cũ/mới hoặc thử từng luồng với database/browser thật. MySQL concurrency và E2E browser chưa được xác minh theo [handover TV2](BASELINE_HANDOVER.md).

## Quyết định cần theo dõi

- Nhóm trưởng quyết định hướng xử lý việc `getPropertyById` đang đè delegate Catalog.
- Chốt quy ước `*Request`/`Validators` và canonical path `/search`.
- Giữ bất biến contract Booking ↔ Invoice/Review; nếu cần thay đổi phải thống nhất liên module trước.

## Nguồn tiến độ

[Handover baseline](BASELINE_HANDOVER.md), [PR checklist](BASELINE_PR_CHECKLIST.md), [handover TV2](handover-member2.md), [booking API](../api/booking-module.md), [ARCHITECTURE.md](../../ARCHITECTURE.md).