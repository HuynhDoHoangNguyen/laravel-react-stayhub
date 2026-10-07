<?php
// TEMP-M1-STUB

namespace Database\Seeders;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoM2Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Admin
        User::firstOrCreate(
            ['email' => 'admin@stayhub.com'],
            [
                'name' => 'Admin System',
                'password' => Hash::make('12345678'),
                'phone' => '0900000001',
                'address' => 'Hà Nội, Việt Nam',
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
            ]
        );

        // 2. Customers
        $customer1 = User::firstOrCreate(
            ['email' => 'customer1@stayhub.com'],
            [
                'name' => 'Nguyễn Văn Customer 1',
                'password' => Hash::make('12345678'),
                'phone' => '0911111111',
                'address' => 'TP. Hồ Chí Minh',
                'role' => UserRole::CUSTOMER,
                'status' => UserStatus::ACTIVE,
            ]
        );

        $customer2 = User::firstOrCreate(
            ['email' => 'customer2@stayhub.com'],
            [
                'name' => 'Trần Thị Customer 2',
                'password' => Hash::make('12345678'),
                'phone' => '0922222222',
                'address' => 'Đà Nẵng',
                'role' => UserRole::CUSTOMER,
                'status' => UserStatus::ACTIVE,
            ]
        );

        $customer3 = User::firstOrCreate(
            ['email' => 'customer3@stayhub.com'],
            [
                'name' => 'Lê Văn Customer 3',
                'password' => Hash::make('12345678'),
                'phone' => '0933333333',
                'address' => 'Cần Thơ',
                'role' => UserRole::CUSTOMER,
                'status' => UserStatus::ACTIVE,
            ]
        );

        // 3. Amenities
        $amenityWifi = Amenity::firstOrCreate(['name' => 'Wi-Fi miễn phí'], ['icon' => 'wifi', 'description' => 'Tốc độ cao', 'status' => true]);
        $amenityAC = Amenity::firstOrCreate(['name' => 'Máy lạnh'], ['icon' => 'air-conditioner', 'description' => 'Inverter tiết kiệm điện', 'status' => true]);
        $amenityTV = Amenity::firstOrCreate(['name' => 'Smart TV'], ['icon' => 'tv', 'description' => 'Có Netflix', 'status' => true]);
        $amenityPool = Amenity::firstOrCreate(['name' => 'Hồ bơi'], ['icon' => 'pool', 'description' => 'Hồ bơi vô cực', 'status' => true]);
        $amenityParking = Amenity::firstOrCreate(['name' => 'Bãi đỗ xe'], ['icon' => 'parking', 'description' => 'Rộng rãi an toàn', 'status' => true]);

        $allAmenities = [$amenityWifi->id, $amenityAC->id, $amenityTV->id, $amenityPool->id, $amenityParking->id];

        // 4. Host 1 & Properties
        $host1 = User::firstOrCreate(
            ['email' => 'host1@stayhub.com'],
            [
                'name' => 'Phạm Host One',
                'password' => Hash::make('12345678'),
                'phone' => '0988888881',
                'address' => 'Quận 1, TP. Hồ Chí Minh',
                'role' => UserRole::HOST,
                'status' => UserStatus::ACTIVE,
            ]
        );

        // Host 1 Property 1
        $prop1 = Property::create([
            'host_id' => $host1->id,
            'name' => 'Khách sạn StayHub Central',
            'type' => PropertyType::HOTEL,
            'address' => '123 Đường Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh',
            'description' => 'Khách sạn hiện đại ngay trung tâm Sài Gòn',
            'phone' => '02838111111',
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $rt1_1 = RoomType::create(['property_id' => $prop1->id, 'name' => 'Standard Room', 'description' => 'Phòng tiêu chuẩn gọn gàng']);
        $rt1_2 = RoomType::create(['property_id' => $prop1->id, 'name' => 'Deluxe Suite', 'description' => 'Phòng cao cấp view phố']);

        $room101 = Room::create([
            'property_id' => $prop1->id,
            'room_type_id' => $rt1_1->id,
            'room_number' => '101',
            'name' => 'Phòng Standard 101',
            'description' => 'Phòng 1 giường đôi',
            'capacity' => 2,
            'price_per_night' => 500000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $room101->amenities()->sync([$amenityWifi->id, $amenityAC->id, $amenityTV->id]);

        $room102 = Room::create([
            'property_id' => $prop1->id,
            'room_type_id' => $rt1_1->id,
            'room_number' => '102',
            'name' => 'Phòng Standard 102',
            'description' => 'Phòng 2 giường đơn',
            'capacity' => 2,
            'price_per_night' => 550000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $room102->amenities()->sync([$amenityWifi->id, $amenityAC->id]);

        $room201 = Room::create([
            'property_id' => $prop1->id,
            'room_type_id' => $rt1_2->id,
            'room_number' => '201',
            'name' => 'Phòng Deluxe 201',
            'description' => 'Phòng suite rộng rãi',
            'capacity' => 3,
            'price_per_night' => 850000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $room201->amenities()->sync($allAmenities);

        $room202 = Room::create([
            'property_id' => $prop1->id,
            'room_type_id' => $rt1_2->id,
            'room_number' => '202',
            'name' => 'Phòng Deluxe 202',
            'description' => 'Phòng gia đình view đẹp',
            'capacity' => 4,
            'price_per_night' => 1000000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $room202->amenities()->sync($allAmenities);

        // Host 1 Property 2
        $prop2 = Property::create([
            'host_id' => $host1->id,
            'name' => 'Homestay Dalat Cloud',
            'type' => PropertyType::HOMESTAY,
            'address' => '45 Đường Phường 10, Thành phố Đà Lạt',
            'description' => 'Homestay yên bình ngắm mây Đà Lạt',
            'phone' => '02633222222',
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $rt2_1 = RoomType::create(['property_id' => $prop2->id, 'name' => 'Wood Cabin', 'description' => 'Cabin gỗ ấm cúng']);
        $rt2_2 = RoomType::create(['property_id' => $prop2->id, 'name' => 'Family Villa Suite', 'description' => 'Căn biệt thự nhỏ cho gia đình']);

        $roomC01 = Room::create([
            'property_id' => $prop2->id,
            'room_type_id' => $rt2_1->id,
            'room_number' => 'C01',
            'name' => 'Cabin Gỗ C01',
            'description' => 'Cabin gỗ ấm áp',
            'capacity' => 2,
            'price_per_night' => 400000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $roomC01->amenities()->sync([$amenityWifi->id, $amenityParking->id]);

        $roomF01 = Room::create([
            'property_id' => $prop2->id,
            'room_type_id' => $rt2_2->id,
            'room_number' => 'F01',
            'name' => 'Family Suite F01',
            'description' => 'Suite gia đình 2 phòng ngủ',
            'capacity' => 5,
            'price_per_night' => 1200000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $roomF01->amenities()->sync([$amenityWifi->id, $amenityAC->id, $amenityTV->id, $amenityParking->id]);

        // 5. Host 2 & Property
        $host2 = User::firstOrCreate(
            ['email' => 'host2@stayhub.com'],
            [
                'name' => 'Vũ Host Two',
                'password' => Hash::make('12345678'),
                'phone' => '0988888882',
                'address' => 'Nha Trang, Khánh Hòa',
                'role' => UserRole::HOST,
                'status' => UserStatus::ACTIVE,
            ]
        );

        $prop3 = Property::create([
            'host_id' => $host2->id,
            'name' => 'Ocean Resort Nha Trang',
            'type' => PropertyType::HOTEL,
            'address' => '88 Đường Trần Phú, Nha Trang',
            'description' => 'Resort biển sang trọng cao cấp',
            'phone' => '02583888888',
            'check_in_time' => '15:00:00',
            'check_out_time' => '12:00:00',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $rt3_1 = RoomType::create(['property_id' => $prop3->id, 'name' => 'Sea View Superior', 'description' => 'Phòng ngắm biển']);
        $rt3_2 = RoomType::create(['property_id' => $prop3->id, 'name' => 'Presidential Suite', 'description' => 'Phòng Tổng Thống sang trọng']);

        $room301 = Room::create([
            'property_id' => $prop3->id,
            'room_type_id' => $rt3_1->id,
            'room_number' => '301',
            'name' => 'Superior Sea View 301',
            'description' => 'View trực diện biển',
            'capacity' => 2,
            'price_per_night' => 1500000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $room301->amenities()->sync($allAmenities);

        $room302 = Room::create([
            'property_id' => $prop3->id,
            'room_type_id' => $rt3_1->id,
            'room_number' => '302',
            'name' => 'Superior Sea View 302',
            'description' => 'View biển góc rộng',
            'capacity' => 3,
            'price_per_night' => 1800000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $room302->amenities()->sync($allAmenities);

        $room401 = Room::create([
            'property_id' => $prop3->id,
            'room_type_id' => $rt3_2->id,
            'room_number' => '401',
            'name' => 'Presidential Suite 401',
            'description' => 'Căn biệt thự trên cao đầy đủ tiện nghi xa hoa',
            'capacity' => 4,
            'price_per_night' => 4500000,
            'status' => RoomStatus::ACTIVE,
        ]);
        $room401->amenities()->sync($allAmenities);
    }
}
