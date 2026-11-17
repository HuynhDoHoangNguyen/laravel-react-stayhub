# StayHub TV2 — checklist tiến độ và nghiệm thu

Phạm vi: Availability, Search, Booking, Booking Workflow, Services và các lát giao diện U1–U3. Đối chiếu 2026-11-17 trên `feature/member-2-booking`, HEAD `65b6e00`. Commit được xác nhận bằng `git log --oneline -n 40`; các số kiểm thử tổng hợp là lượt chạy hiện tại, không phải số test riêng từng lát.

| Luồng/lát | Phạm vi | Commit | Test/build hiện có | Trạng thái kỹ thuật | Thử thủ công trên trình duyệt | Nhóm trưởng nghiệm thu |
| --- | --- | --- | --- | --- | --- | --- |
| Availability + Search | Query khả dụng, ngày nửa mở, maintenance, booking giữ phòng; API Search | `266a1d8` | Full backend hiện tại PASS 1.278/7.320; frontend 172 PASS. Số riêng lát không ghi nhận. | Refactor đã commit | Chưa ghi nhận | Chưa ghi nhận |
| Checkout source | `getBookableProperty` cho dữ liệu room-search; giữ `getPropertyById` hiện tại | `b70a91d` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Đã commit; quyết định Catalog còn mở | Chưa ghi nhận trong mục nghiệm thu UI | Chưa ghi nhận |
| Booking preview + create | Preview, pricing snapshot, tạo booking và transaction | `ba70c47` | Full backend hiện tại PASS 1.278/7.320; frontend 172 PASS | Refactor đã commit | Chưa ghi nhận | Chưa ghi nhận |
| Customer đọc/hủy | List, detail, cancel và ownership | `c8ccf5d` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Refactor đã commit | Chưa ghi nhận | Chưa ghi nhận |
| Host workflow | Host list/detail, confirm/reject/check-in/check-out | `b7221bf` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Refactor đã commit | Chưa ghi nhận | Chưa ghi nhận |
| Services | Service catalog theo property và dịch vụ gắn booking | `8d66132` | Full backend/frontend hiện tại PASS; số riêng lát không ghi nhận | Refactor đã commit | Chưa ghi nhận | Chưa ghi nhận |
| U1 giao diện | Search + checkout | `6b7a676` | Frontend 172 PASS; lint/build được chạy ở lượt U3 trước đây, không chạy lại trong lượt tài liệu | Đã commit; browser acceptance chưa ghi nhận | Chưa ghi nhận | Chưa ghi nhận |
| U2 giao diện | Customer bookings/detail, hộp hủy cục bộ | `58d3f81` | Frontend 172 PASS; lint/build được chạy ở lượt U3 trước đây, không chạy lại trong lượt tài liệu | Đã commit; browser acceptance chưa ghi nhận | Chưa ghi nhận | Chưa ghi nhận |
| U3 giao diện | Host bookings/workflow/services và danh mục | `65b6e00` | Frontend 172 PASS; lượt U3 báo lint 0 lỗi/3 warning nền, build PASS với cảnh báo bundle lớn | Đã commit; browser acceptance chưa ghi nhận | Chưa ghi nhận | Chưa ghi nhận |

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

[Handover baseline](BASELINE_HANDOVER.md), [PR checklist](BASELINE_PR_CHECKLIST.md), [handover TV2 hiện có](../handover-member2.md), [booking API](../api/booking-module.md), [ARCHITECTURE.md](../../ARCHITECTURE.md).
