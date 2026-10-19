<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Owner: Member 3. Tables: invoices, invoice_items, reviews, faqs, chat_sessions, chat_messages.
// TEMP-M3-STUB: replace this demo data with Member 3's production seeder.
class Member3DataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $stamp = ['created_at'=>$now,'updated_at'=>$now];
        $invoiceSpecs = [
            [1,9,'UNPAID',false], [2,10,'PAID',false], [3,11,'REFUNDED',false], [4,12,'UNPAID',true],
        ];
        foreach ($invoiceSpecs as [$invoiceId,$bookingId,$payment,$surcharge]) {
            $booking = DB::table('bookings')->where('id',$bookingId)->first();
            if ($booking->status !== BookingStatus::COMPLETED->value) {
                continue;
            }
            $customer = DB::table('users')->where('id',$booking->customer_id)->first();
            $room = DB::table('rooms')->where('id',$booking->room_id)->first();
            $property = DB::table('properties')->where('id',$room->property_id)->first();
            $items = [['ROOM',$room->name,$booking->number_of_nights,$booking->price_per_night,$booking->room_total]];
            foreach (DB::table('booking_services')->where('booking_id',$bookingId)->get() as $line) {
                $items[] = ['SERVICE',$line->service_name,$line->quantity,$line->unit_price,$line->amount];
            }
            if ($surcharge) {
                $items[] = ['SURCHARGE','Phụ thu trang trí phòng',1,150000,150000];
            }
            $subtotal = array_sum(array_column($items,4));
            DB::table('invoices')->insert(['id'=>$invoiceId,'invoice_code'=>sprintf('INVDEMO%04d',$invoiceId),'booking_id'=>$bookingId,
                'customer_name'=>$customer->name,'customer_email'=>$customer->email,'customer_phone'=>$customer->phone,
                'property_name'=>$property->name,'room_name'=>$room->name,'subtotal'=>$subtotal,'discount_amount'=>0,
                'extra_fee'=>0,'total_amount'=>$subtotal,'payment_status'=>$payment,
                'paid_at'=>$payment === 'PAID' ? Carbon::now()->subDays(2) : null,...$stamp]);
            foreach ($items as [$type,$name,$quantity,$unitPrice,$amount]) {
                DB::table('invoice_items')->insert(['invoice_id'=>$invoiceId,'item_type'=>$type,'item_name'=>$name,
                    'description'=>null,'quantity'=>$quantity,'unit_price'=>$unitPrice,'amount'=>$amount,...$stamp]);
            }
        }

        foreach ([[1,10,4,1,'Dịch vụ chu đáo, phòng sạch và vị trí thuận tiện.'],[2,11,5,2,'Không gian yên bình, gia đình tôi có kỳ nghỉ tuyệt vời.'],[3,12,6,3,'View biển đẹp, nhân viên nhiệt tình.']] as [$id,$bookingId,$customerId,$propertyId,$comment]) {
            $booking = DB::table('bookings')->where('id',$bookingId)->first();
            if ($booking->status === BookingStatus::COMPLETED->value) {
                DB::table('reviews')->insert(['id'=>$id,'booking_id'=>$bookingId,'customer_id'=>$customerId,
                    'property_id'=>$propertyId,'rating'=>[5,4,2][$id-1],'comment'=>$comment,...$stamp]);
            }
        }

        $faqs = [
            ['Làm thế nào để đặt phòng?','Chọn cơ sở, phòng và ngày lưu trú, sau đó đăng nhập để gửi yêu cầu đặt phòng.'],
            ['Tôi có thể hủy đặt phòng không?','Đơn chờ xác nhận có thể hủy bất cứ lúc nào; đơn đã xác nhận cần hủy trước hạn quy định.'],
            ['Giờ nhận phòng là khi nào?','Giờ nhận phòng tiêu chuẩn là 14:00; vui lòng liên hệ cơ sở nếu cần hỗ trợ.'],
            ['Giờ trả phòng là khi nào?','Giờ trả phòng tiêu chuẩn là 12:00.'],
            ['Tôi thanh toán bằng cách nào?','Thông tin thanh toán sẽ được cơ sở xác nhận khi bạn hoàn tất lưu trú.'],
            ['Có thể đặt thêm dịch vụ không?','Có, khách đang lưu trú có thể nhờ host thêm các dịch vụ đang được cung cấp.'],
            ['Tôi nhận hóa đơn ở đâu?','Hóa đơn được tạo từ chi tiết đặt phòng sau khi hoàn tất lưu trú.'],
            ['Tôi liên hệ hỗ trợ bằng cách nào?','Gửi câu hỏi qua khung chat hoặc liên hệ trực tiếp cơ sở lưu trú.'],
        ];
        foreach ($faqs as $i => [$question,$answer]) {
            DB::table('faqs')->insert(['question'=>$question,'answer'=>$answer,'status'=>$i !== 7,...$stamp]);
        }

        foreach ([[1,4,'Hỏi thông tin đặt phòng'],[2,null,'Tư vấn lưu trú cho khách'] ] as [$id,$userId,$title]) {
            DB::table('chat_sessions')->insert(['id'=>$id,'user_id'=>$userId,'title'=>$title,...$stamp]);
        }
        $messages = [
            [1,'USER','Tôi muốn đặt phòng cho hai người ở Đà Nẵng.'],[1,'ASSISTANT','Bạn dự định nhận và trả phòng vào ngày nào?'],
            [1,'USER','Tôi muốn nhận phòng vào cuối tuần này.'],[1,'ASSISTANT','Bạn có thể xem phòng trống và gửi yêu cầu đặt phòng trên StayHub.'],
            [2,'USER','Xin chào, bên mình có phòng hướng biển không?'],[2,'ASSISTANT','Có, StayHub có các phòng hướng biển tại Nha Trang.'],
            [2,'USER','Giá phòng đã bao gồm ăn sáng chưa?'],[2,'ASSISTANT','Bạn có thể xem chi tiết giá và dịch vụ đi kèm trên trang phòng.'],
        ];
        foreach ($messages as $i => [$sessionId,$sender,$message]) {
            DB::table('chat_messages')->insert(['chat_session_id'=>$sessionId,'sender'=>$sender,'message'=>$message,
                'created_at'=>$now->copy()->addSeconds($i),'updated_at'=>$now->copy()->addSeconds($i)]);
        }
    }
}
