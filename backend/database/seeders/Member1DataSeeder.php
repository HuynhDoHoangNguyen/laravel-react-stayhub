<?php

namespace Database\Seeders;

use App\Enums\MaintenanceStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Owner: Member 1. Tables: users, properties, property_images, room_types, rooms,
// room_images, amenities, room_amenities, maintenances.
// TEMP-M1-STUB: replace this demo data with Member 1's production seeder.
class Member1DataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $stamp = ['created_at' => $now, 'updated_at' => $now];
        $password = Hash::make('12345678');

        $users = [
            [1, 'Quản trị viên StayHub', 'admin@stayhub.com', '0900000001', 'Hà Nội', UserRole::ADMIN, UserStatus::ACTIVE],
            [2, 'Phạm Minh Anh', 'host1@stayhub.com', '0988888881', 'Quận 1, TP. Hồ Chí Minh', UserRole::HOST, UserStatus::ACTIVE],
            [3, 'Vũ Hoàng Nam', 'host2@stayhub.com', '0988888882', 'Nha Trang, Khánh Hòa', UserRole::HOST, UserStatus::ACTIVE],
            [4, 'Nguyễn Văn An', 'customer1@stayhub.com', '0911111111', 'Hải Châu, Đà Nẵng', UserRole::CUSTOMER, UserStatus::ACTIVE],
            [5, 'Trần Thị Bình', 'customer2@stayhub.com', '0922222222', 'Ba Đình, Hà Nội', UserRole::CUSTOMER, UserStatus::ACTIVE],
            [6, 'Lê Minh Châu', 'customer3@stayhub.com', '0933333333', 'Ninh Kiều, Cần Thơ', UserRole::CUSTOMER, UserStatus::ACTIVE],
            [7, 'Đặng Thu Hà', 'customer4@stayhub.com', '0944444444', 'Sơn Trà, Đà Nẵng', UserRole::CUSTOMER, UserStatus::BLOCKED],
            [8, 'Phan Quốc Huy', 'customer5@stayhub.com', '0955555555', 'Đà Lạt, Lâm Đồng', UserRole::CUSTOMER, UserStatus::INACTIVE],
        ];
        foreach ($users as [$id, $name, $email, $phone, $address, $role, $status]) {
            DB::table('users')->insert(['id' => $id, 'name' => $name, 'email' => $email, 'password' => $password,
                'phone' => $phone, 'address' => $address, 'avatar' => null, 'role' => $role->value,
                'status' => $status->value, ...$stamp]);
        }

        $properties = [
            [1, 2, 'Khách sạn Sông Hàn', PropertyType::HOTEL, '12 Bạch Đằng, Hải Châu, Đà Nẵng', PropertyStatus::ACTIVE, '02363551111'],
            [2, 2, 'Mây Đà Lạt Homestay', PropertyType::HOMESTAY, '18 Trần Hưng Đạo, Đà Lạt, Lâm Đồng', PropertyStatus::ACTIVE, '02633552222'],
            [3, 3, 'Biển Xanh Nha Trang', PropertyType::HOTEL, '45 Trần Phú, Lộc Thọ, Nha Trang', PropertyStatus::ACTIVE, '02583553333'],
            [4, 3, 'Nhà Vườn Hà Nội', PropertyType::HOMESTAY, '26 Âu Cơ, Tây Hồ, Hà Nội', PropertyStatus::PENDING, '02433554444'],
        ];
        foreach ($properties as [$id, $host, $name, $type, $address, $status, $phone]) {
            DB::table('properties')->insert(['id' => $id, 'host_id' => $host, 'name' => $name, 'type' => $type->value,
                'address' => $address, 'description' => "Cơ sở lưu trú thân thiện tại {$address}.", 'phone' => $phone,
                'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => $status->value, ...$stamp]);
            foreach ([1, 2] as $index) {
                DB::table('property_images')->insert(['property_id' => $id, 'image_path' => "https://picsum.photos/seed/stayhub-property-{$id}-{$index}/800/600",
                    'is_primary' => $index === 1, ...$stamp]);
            }
        }

        $types = [
            [1, 1, 'Phòng Tiêu Chuẩn'], [2, 1, 'Phòng Hướng Sông'], [3, 1, 'Phòng Gia Đình'],
            [4, 2, 'Phòng Gỗ Thông'], [5, 2, 'Căn Hộ Gia Đình'],
            [6, 3, 'Phòng Hướng Biển'], [7, 3, 'Phòng Suite'], [8, 4, 'Phòng Vườn'],
        ];
        foreach ($types as [$id, $propertyId, $name]) {
            DB::table('room_types')->insert(['id' => $id, 'property_id' => $propertyId, 'name' => $name,
                'description' => "{$name} tiện nghi, phù hợp nghỉ dưỡng.", ...$stamp]);
        }

        $rooms = [
            [1,1,1,'101','Phòng Tiêu Chuẩn 101',2,650000,RoomStatus::ACTIVE], [2,1,1,'102','Phòng Tiêu Chuẩn 102',2,700000,RoomStatus::ACTIVE],
            [3,1,2,'201','Phòng Hướng Sông 201',3,1100000,RoomStatus::ACTIVE], [4,1,2,'202','Phòng Hướng Sông 202',4,1450000,RoomStatus::ACTIVE],
            [5,1,3,'301','Phòng Gia Đình 301',6,2200000,RoomStatus::MAINTENANCE],
            [6,2,4,'A01','Phòng Gỗ Thông A01',2,550000,RoomStatus::ACTIVE], [7,2,4,'A02','Phòng Gỗ Thông A02',3,750000,RoomStatus::ACTIVE],
            [8,2,5,'B01','Căn Hộ Gia Đình B01',5,1800000,RoomStatus::ACTIVE], [9,2,5,'B02','Căn Hộ Gia Đình B02',4,1600000,RoomStatus::INACTIVE],
            [10,3,6,'301','Phòng Hướng Biển 301',2,1250000,RoomStatus::ACTIVE], [11,3,6,'302','Phòng Hướng Biển 302',3,1650000,RoomStatus::ACTIVE],
            [12,3,7,'401','Suite Biển 401',4,2500000,RoomStatus::ACTIVE], [13,3,7,'402','Suite Biển 402',6,2400000,RoomStatus::ACTIVE],
        ];
        foreach ($rooms as [$id, $propertyId, $typeId, $number, $name, $capacity, $price, $status]) {
            DB::table('rooms')->insert(['id' => $id, 'property_id' => $propertyId, 'room_type_id' => $typeId,
                'room_number' => $number, 'name' => $name, 'description' => "{$name}, đầy đủ tiện nghi.",
                'capacity' => $capacity, 'price_per_night' => $price, 'status' => $status->value, ...$stamp]);
            foreach ([1, 2] as $index) {
                DB::table('room_images')->insert(['room_id' => $id, 'image_path' => "https://picsum.photos/seed/stayhub-room-{$id}-{$index}/800/600",
                    'is_primary' => $index === 1, ...$stamp]);
            }
        }

        $amenities = ['Wi-Fi', 'Điều hòa', 'TV', 'Bếp', 'Hồ bơi', 'Bãi đỗ xe', 'Ăn sáng', 'Ban công', 'Máy giặt', 'Tủ lạnh', 'View biển', 'Bồn tắm'];
        foreach ($amenities as $offset => $name) {
            $id = $offset + 1;
            DB::table('amenities')->insert(['id' => $id, 'name' => $name, 'icon' => strtolower(str_replace(' ', '-', $name)),
                'description' => "Tiện nghi {$name} tại cơ sở.", 'status' => $id !== 12, ...$stamp]);
        }
        foreach ($rooms as [$roomId]) {
            $amenityIds = array_map(fn ($i) => (($roomId + $i - 2) % 12) + 1, range(1, 4));
            DB::table('room_amenities')->insert(array_map(fn ($amenityId) => ['room_id' => $roomId, 'amenity_id' => $amenityId], $amenityIds));
        }

        $maintenance = [
            [1,5,$today->copy()->addDays(10),$today->copy()->addDays(12),'Bảo dưỡng điều hòa định kỳ',MaintenanceStatus::SCHEDULED],
            [2,5,$today->copy()->subDay(),$today->copy()->addDays(2),'Thay mới hệ thống nước nóng',MaintenanceStatus::IN_PROGRESS],
            [3,9,$today->copy()->subDays(20),$today->copy()->subDays(18),'Sơn sửa phòng sau mùa cao điểm',MaintenanceStatus::COMPLETED],
            [4,4,$today->copy()->addDays(20),$today->copy()->addDays(21),'Khách đổi lịch bảo trì',MaintenanceStatus::CANCELLED],
        ];
        foreach ($maintenance as [$id,$roomId,$start,$end,$reason,$status]) {
            DB::table('maintenances')->insert(['id'=>$id,'room_id'=>$roomId,'start_date'=>$start->toDateString(),'end_date'=>$end->toDateString(),
                'reason'=>$reason,'status'=>$status->value,...$stamp]);
        }
    }
}
