<?php

namespace Database\Seeders;

use App\Models\PartnerAgreement;
use Illuminate\Database\Seeder;

class PartnerAgreementSeeder extends Seeder
{
    public const DEFAULT_TERMS = <<<TEXT
HỢP ĐỒNG ĐỐI TÁC KINH DOANH LƯU TRÚ STAYHUB (PHIÊN BẢN v1.0)

Điều 1. Phạm vi và Đối tượng áp dụng
Hợp đồng này quy định quyền, trách nhiệm và nghĩa vụ tài chính giữa Nền tảng quản lý lưu trú StayHub và Chủ cơ sở lưu trú (sau đây gọi là "Host") khi đăng tải và kinh doanh cơ sở lưu trú trên hệ thống StayHub.

Điều 2. Quy trình thanh toán tiền phòng của Khách hàng
1. Khách hàng (Customer) thanh toán trực tiếp 100% chi phí lưu trú (tiền phòng và dịch vụ) cho Host bằng tiền mặt hoặc chuyển khoản ngoài hệ thống StayHub.
2. StayHub là nền tảng kết nối và quản lý đặt phòng, không thu hộ và không giữ hộ tiền phòng của Host.

Điều 3. Phí hoa hồng nền tảng (Platform Commission)
1. Host đồng ý thanh toán cho StayHub khoản phí hoa hồng dịch vụ nền tảng là 10% (mười phần trăm) trên doanh thu thực tế.
2. Cơ sở tính phí hoa hồng = (Tiền phòng + Tiền dịch vụ phát sinh) - Số tiền giảm giá (nếu có). Phụ phí phát sinh riêng (extra fee) không tính vào cơ sở hoa hồng.
3. Tỷ lệ hoa hồng 10% được cố định cho từng đơn đặt phòng tại thời điểm khách đặt phòng thành công.

Điều 4. Thời hạn nộp hoa hồng và Đối soát
1. Thời hạn nộp hoa hồng: Trong vòng 72 (bảy mươi hai) giờ tính từ thời điểm Host xác nhận hóa đơn của khách đã thanh toán thành công (Invoice trạng thái PAID).
2. Phương thức nộp: Host thực hiện chuyển khoản 100% số tiền hoa hồng vào số tài khoản nhận tiền chính thức của StayHub với cú pháp/mã tham chiếu được cấp cho khoản phí đó (ví dụ: SHF-xxxx).
3. Đối soát: Sau khi Host thông báo đã chuyển khoản trên hệ thống, Ban quản trị StayHub sẽ kiểm tra sao kê ngân hàng và xác nhận hoàn tất nghĩa vụ phí.

Điều 5. Cam kết và Hiệu lực
Host xác nhận đã đọc, hiểu rõ và tự nguyện chấp thuận toàn bộ các điều khoản của Hợp đồng đối tác này trước khi tạo và kinh doanh cơ sở lưu trú trên StayHub.
TEXT;

    public function run(): void
    {
        $hash = hash('sha256', self::DEFAULT_TERMS);

        PartnerAgreement::firstOrCreate(
            ['version' => 'v1.0'],
            [
                'title' => 'Hợp đồng Đối tác Kinh doanh Lưu trú StayHub (Phiên bản v1.0)',
                'terms_body' => self::DEFAULT_TERMS,
                'content_sha256' => $hash,
                'commission_rate_bps' => 1000,
                'effective_at' => '2026-01-01 00:00:00',
                'retired_at' => null,
                'status' => 'PUBLISHED',
            ]
        );
    }
}
