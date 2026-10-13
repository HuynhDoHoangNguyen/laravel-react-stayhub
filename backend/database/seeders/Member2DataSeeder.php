<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Owner: Member 2. Tables: services, bookings, booking_services.
class Member2DataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $stamp = ['created_at'=>$now,'updated_at'=>$now];
        $services = [
            [1,1,'Giặt ủi','Giặt sấy quần áo trong ngày','kg',50000,true], [2,1,'Ăn sáng','Bữa sáng địa phương','suất',120000,true],
            [3,1,'Thuê xe máy','Xe tay ga theo ngày','ngày',180000,true], [4,1,'Đưa đón sân bay','Đón tiễn sân bay Đà Nẵng','lượt',250000,true],
            [5,1,'Trang trí phòng','Trang trí theo yêu cầu','lần',400000,false],
            [6,2,'Giặt ủi','Giặt sấy tại homestay','kg',45000,true], [7,2,'Ăn sáng','Bữa sáng nhà làm','suất',90000,true],
            [8,2,'Thuê xe máy','Xe máy tham quan Đà Lạt','ngày',160000,true], [9,2,'Đưa đón sân bay','Đón sân bay Liên Khương','lượt',300000,true],
            [10,3,'Giặt ủi','Giặt sấy nhanh','kg',60000,true], [11,3,'Ăn sáng','Buffet sáng','suất',180000,true],
            [12,3,'Thuê xe máy','Thuê xe máy trong ngày','ngày',200000,true], [13,3,'Đưa đón sân bay','Đón sân bay Cam Ranh','lượt',350000,true],
        ];
        foreach ($services as [$id,$propertyId,$name,$description,$unit,$price,$active]) {
            DB::table('services')->insert(['id'=>$id,'property_id'=>$propertyId,'name'=>$name,'description'=>$description,'unit'=>$unit,'price'=>$price,'status'=>$active,...$stamp]);
        }

        $definitions = [
            [1,4,1,2,4,2,BookingStatus::PENDING], [2,5,3,5,6,2,BookingStatus::PENDING], [3,6,10,3,5,2,BookingStatus::PENDING],
            [4,4,2,2,4,2,BookingStatus::CONFIRMED], [5,5,6,4,7,2,BookingStatus::CONFIRMED], [6,6,11,7,9,2,BookingStatus::CONFIRMED],
            [7,4,4,-1,1,2,BookingStatus::CHECKED_IN], [8,5,8,-1,2,3,BookingStatus::CHECKED_IN],
            [9,6,12,-12,-10,2,BookingStatus::COMPLETED], [10,4,1,-10,-8,2,BookingStatus::COMPLETED],
            [11,5,6,-20,-18,2,BookingStatus::COMPLETED], [12,6,10,-30,-27,2,BookingStatus::COMPLETED],
            [13,4,3,-40,-38,2,BookingStatus::COMPLETED],
            [14,5,7,2,4,2,BookingStatus::CANCELLED], [15,6,13,5,7,2,BookingStatus::CANCELLED],
            [16,4,2,8,10,2,BookingStatus::REJECTED],
        ];
        $bookings = [];
        foreach ($definitions as [$id,$customerId,$roomId,$inOffset,$outOffset,$guests,$status]) {
            $in = $today->copy()->addDays($inOffset);
            $out = $today->copy()->addDays($outOffset);
            $nights = $in->diffInDays($out);
            $rate = (int) DB::table('rooms')->where('id',$roomId)->value('price_per_night');
            $bookings[$id] = ['id'=>$id,'booking_code'=>sprintf('BKDEMO%04d',$id),'customer_id'=>$customerId,'room_id'=>$roomId,
                'check_in_date'=>$in->toDateString(),'check_out_date'=>$out->toDateString(),'guest_count'=>$guests,
                'number_of_nights'=>$nights,'price_per_night'=>$rate,'room_total'=>$nights*$rate,'status'=>$status->value,
                'note'=>$status === BookingStatus::REJECTED ? 'Cơ sở không thể phục vụ ngày yêu cầu.' : null,...$stamp];
            DB::table('bookings')->insert($bookings[$id]);
        }

        $lines = [
            [7,1,2,49000], [7,2,2,120000], [8,7,2,90000], [8,8,1,160000],
            [10,2,2,120000], [10,1,1,50000], [11,7,2,90000], [11,6,2,45000],
            [12,11,2,180000], [12,13,1,350000],
        ];
        foreach ($lines as [$bookingId,$serviceId,$quantity,$unitPrice]) {
            $service = DB::table('services')->where('id',$serviceId)->first();
            DB::table('booking_services')->insert(['booking_id'=>$bookingId,'service_id'=>$serviceId,
                'service_name'=>$service->name,'unit'=>$service->unit,'quantity'=>$quantity,'unit_price'=>$unitPrice,
                'amount'=>$quantity*$unitPrice,...$stamp]);
        }
    }
}
