<?php

namespace App\Mappers\Booking;

use App\DTOs\Booking\Responses\BookingServiceResponse;
use App\DTOs\Booking\Responses\CustomerBookingResponse;
use App\DTOs\Booking\Responses\HostBookingResponse;
use App\DTOs\Booking\Responses\RoomAvailabilityResponse;
use App\DTOs\Booking\Responses\RoomSearchResponse;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Models\Room;
use App\Support\DecimalMoney;
use DateTimeInterface;

final class BookingMapper
{
    public function toCustomerBooking(Booking $booking): CustomerBookingResponse
    {
        $statusEnum = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);

        $checkInDate = $booking->check_in_date instanceof DateTimeInterface
            ? $booking->check_in_date->format('Y-m-d')
            : (string) $booking->check_in_date;

        $checkOutDate = $booking->check_out_date instanceof DateTimeInterface
            ? $booking->check_out_date->format('Y-m-d')
            : (string) $booking->check_out_date;

        $roomData = null;
        if ($booking->relationLoaded('room') && $booking->room) {
            $room = $booking->room;
            $propertyData = null;
            if ($room->relationLoaded('property') && $room->property) {
                $property = $room->property;
                $propertyData = [
                    'id' => (int) $property->id,
                    'name' => (string) $property->name,
                    'address' => (string) $property->address,
                    'phone' => (string) $property->phone,
                ];
            }

            $roomData = [
                'id' => (int) $room->id,
                'room_number' => (string) $room->room_number,
                'name' => (string) $room->name,
                'capacity' => (int) $room->capacity,
            ];

            if ($propertyData !== null) {
                $roomData['property'] = $propertyData;
            }
        }

        $bookingServicesData = null;
        if ($booking->relationLoaded('bookingServices') && $booking->bookingServices !== null) {
            $bookingServicesData = $this->toBookingServiceCollection($booking->bookingServices);
        }

        $servicesTotal = isset($booking->services_total)
            ? DecimalMoney::formatNullable($booking->services_total)
            : null;

        return new CustomerBookingResponse(
            id: (int) $booking->id,
            bookingCode: (string) $booking->booking_code,
            customerId: (int) $booking->customer_id,
            roomId: (int) $booking->room_id,
            checkInDate: $checkInDate,
            checkOutDate: $checkOutDate,
            guestCount: (int) $booking->guest_count,
            numberOfNights: (int) $booking->number_of_nights,
            pricePerNight: DecimalMoney::formatNullable($booking->price_per_night),
            roomTotal: DecimalMoney::formatNullable($booking->room_total),
            status: $statusEnum?->value ?? (string) $booking->status,
            statusLabel: $statusEnum?->label() ?? (string) $booking->status,
            note: $booking->note !== null ? (string) $booking->note : null,
            servicesTotal: $servicesTotal,
            room: $roomData,
            bookingServices: $bookingServicesData,
            createdAt: $booking->created_at?->toISOString(),
            updatedAt: $booking->updated_at?->toISOString(),
        );
    }

    /**
     * @param iterable<Booking> $bookings
     * @return list<CustomerBookingResponse>
     */
    public function toCustomerBookingCollection(iterable $bookings): array
    {
        $result = [];
        foreach ($bookings as $booking) {
            $result[] = $this->toCustomerBooking($booking);
        }
        return $result;
    }

    public function toHostBooking(Booking $booking): HostBookingResponse
    {
        $statusEnum = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);

        $checkInDate = $booking->check_in_date instanceof DateTimeInterface
            ? $booking->check_in_date->format('Y-m-d')
            : (string) $booking->check_in_date;

        $checkOutDate = $booking->check_out_date instanceof DateTimeInterface
            ? $booking->check_out_date->format('Y-m-d')
            : (string) $booking->check_out_date;

        $customerData = null;
        if ($booking->relationLoaded('customer') && $booking->customer) {
            $customer = $booking->customer;
            $customerData = [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'email' => (string) $customer->email,
                'phone' => (string) $customer->phone,
            ];
        }

        $roomData = null;
        if ($booking->relationLoaded('room') && $booking->room) {
            $room = $booking->room;
            $propertyData = null;
            if ($room->relationLoaded('property') && $room->property) {
                $property = $room->property;
                $propertyData = [
                    'id' => (int) $property->id,
                    'name' => (string) $property->name,
                    'address' => (string) $property->address,
                ];
            }

            $roomData = [
                'id' => (int) $room->id,
                'room_number' => (string) $room->room_number,
                'name' => (string) $room->name,
                'capacity' => (int) $room->capacity,
            ];

            if ($propertyData !== null) {
                $roomData['property'] = $propertyData;
            }
        }

        $bookingServicesData = null;
        if ($booking->relationLoaded('bookingServices') && $booking->bookingServices !== null) {
            $bookingServicesData = $this->toBookingServiceCollection($booking->bookingServices);
        }

        $servicesTotal = isset($booking->services_total)
            ? DecimalMoney::formatNullable($booking->services_total)
            : null;

        return new HostBookingResponse(
            id: (int) $booking->id,
            bookingCode: (string) $booking->booking_code,
            customerId: (int) $booking->customer_id,
            roomId: (int) $booking->room_id,
            checkInDate: $checkInDate,
            checkOutDate: $checkOutDate,
            guestCount: (int) $booking->guest_count,
            numberOfNights: (int) $booking->number_of_nights,
            pricePerNight: DecimalMoney::formatNullable($booking->price_per_night),
            roomTotal: DecimalMoney::formatNullable($booking->room_total),
            status: $statusEnum?->value ?? (string) $booking->status,
            statusLabel: $statusEnum?->label() ?? (string) $booking->status,
            note: $booking->note !== null ? (string) $booking->note : null,
            servicesTotal: $servicesTotal,
            customer: $customerData,
            room: $roomData,
            bookingServices: $bookingServicesData,
            createdAt: $booking->created_at?->toISOString(),
            updatedAt: $booking->updated_at?->toISOString(),
        );
    }

    /**
     * @param iterable<Booking> $bookings
     * @return list<HostBookingResponse>
     */
    public function toHostBookingCollection(iterable $bookings): array
    {
        $result = [];
        foreach ($bookings as $booking) {
            $result[] = $this->toHostBooking($booking);
        }
        return $result;
    }

    public function toBookingService(BookingServiceModel $service): BookingServiceResponse
    {
        return new BookingServiceResponse(
            id: (int) $service->id,
            bookingId: (int) $service->booking_id,
            serviceId: (int) $service->service_id,
            serviceName: (string) $service->service_name,
            unit: (string) $service->unit,
            quantity: DecimalMoney::formatNullable($service->quantity),
            unitPrice: DecimalMoney::formatNullable($service->unit_price),
            amount: DecimalMoney::formatNullable($service->amount),
            createdAt: $service->created_at?->toISOString(),
        );
    }

    /**
     * @param iterable<BookingServiceModel> $services
     * @return list<BookingServiceResponse>
     */
    public function toBookingServiceCollection(iterable $services): array
    {
        $result = [];
        foreach ($services as $service) {
            $result[] = $this->toBookingService($service);
        }
        return $result;
    }

    public function toRoomSearch(Room $room): RoomSearchResponse
    {
        $propertyData = null;
        if ($room->relationLoaded('property') && $room->property) {
            $p = $room->property;
            $propertyData = [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'type' => $p->type instanceof \BackedEnum ? $p->type->value : (string) $p->type,
                'address' => (string) $p->address,
                'phone' => (string) $p->phone,
                'status' => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
            ];
        }

        $roomTypeData = null;
        if ($room->relationLoaded('roomType') && $room->roomType) {
            $rt = $room->roomType;
            $roomTypeData = [
                'id' => (int) $rt->id,
                'name' => (string) $rt->name,
                'description' => $rt->description !== null ? (string) $rt->description : null,
            ];
        }

        $amenitiesData = null;
        if ($room->relationLoaded('amenities') && $room->amenities) {
            $amenitiesData = $room->amenities->map(fn ($amenity) => [
                'id' => (int) $amenity->id,
                'name' => (string) $amenity->name,
                'icon' => (string) $amenity->icon,
            ])->all();
        }

        $imagesData = null;
        if ($room->relationLoaded('images') && $room->images) {
            $imagesData = $room->images->map(fn ($image) => [
                'id' => (int) $image->id,
                'image_path' => (string) $image->image_path,
                'is_primary' => (bool) $image->is_primary,
            ])->all();
        }

        return new RoomSearchResponse(
            id: (int) $room->id,
            propertyId: (int) $room->property_id,
            roomTypeId: (int) $room->room_type_id,
            roomNumber: (string) $room->room_number,
            name: (string) $room->name,
            description: $room->description !== null ? (string) $room->description : null,
            capacity: (int) $room->capacity,
            pricePerNight: DecimalMoney::formatNullable($room->price_per_night),
            status: $room->status instanceof \BackedEnum ? $room->status->value : (string) $room->status,
            property: $propertyData,
            roomType: $roomTypeData,
            amenities: $amenitiesData,
            images: $imagesData,
        );
    }

    /**
     * @param iterable<Room> $rooms
     * @return list<RoomSearchResponse>
     */
    public function toRoomSearchCollection(iterable $rooms): array
    {
        $result = [];
        foreach ($rooms as $room) {
            $result[] = $this->toRoomSearch($room);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $availability
     */
    public function toRoomAvailability(array $availability): RoomAvailabilityResponse
    {
        return new RoomAvailabilityResponse(
            available: (bool) ($availability['available'] ?? false),
            reasons: array_values((array) ($availability['reasons'] ?? [])),
            blockedRanges: array_values((array) ($availability['blocked_ranges'] ?? [])),
        );
    }
}
