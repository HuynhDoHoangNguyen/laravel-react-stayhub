<?php

declare(strict_types=1);

namespace App\Mappers\Booking;

use App\DTOs\Booking\Requests\AdminBookingQueryRequest;
use App\DTOs\Booking\Responses\AdminBookingFilterOptionResponse;
use App\DTOs\Booking\Responses\AdminBookingListItemResponse;
use App\Enums\BookingStatus;
use App\Mappers\EloquentObjectMapper;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Pure allowlist projection. Missing relations differ from relations not loaded. */
final class AdminBookingMapper
{
    private const FIELDS = ['id', 'booking_code', 'customer_id', 'room_id', 'check_in_date',
        'check_out_date', 'guest_count', 'number_of_nights', 'price_per_night', 'room_total'];

    public function __construct(private readonly EloquentObjectMapper $objectMapper) {}

    public function toListItem(Booking $booking): AdminBookingListItemResponse
    {
        foreach (['id', 'customer_id', 'room_id'] as $field) {
            self::id($this->attribute($booking, $field));
        }
        self::text($this->attribute($booking, 'booking_code'));
        foreach (['check_in_date', 'check_out_date'] as $field) {
            $raw = $this->attribute($booking, $field);
            if (!is_string($raw) || !AdminBookingQueryRequest::isDate($raw)) {
                throw new LogicException('Invalid stored Booking date.');
            }
        }
        foreach (['guest_count', 'number_of_nights'] as $field) {
            $raw = $this->attribute($booking, $field);
            if ((!is_int($raw) && !is_string($raw)) || filter_var($raw, FILTER_VALIDATE_INT) === false) {
                throw new LogicException('Invalid stored Booking integer.');
            }
        }
        foreach (['price_per_night', 'room_total'] as $field) {
            $raw = $this->attribute($booking, $field);
            if ((!is_string($raw) && !is_int($raw))
                || preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', (string) $raw) !== 1) {
                throw new LogicException('Invalid stored Booking money.');
            }
        }
        $status = $this->attribute($booking, 'status');
        if (!is_string($status) || BookingStatus::tryFrom($status) === null) {
            throw new LogicException('Invalid stored Booking status.');
        }
        $createdAt = $this->attribute($booking, 'created_at');
        if ($createdAt !== null && (!is_string($createdAt)
            || preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2}) ([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9](?:\.[0-9]{1,6})?\z/', $createdAt, $parts) !== 1
            || !AdminBookingQueryRequest::isDate($parts[1]))) {
            throw new LogicException('Invalid stored Booking timestamp.');
        }
        $customer = $this->relation($booking, 'customer', User::class);
        $room = $this->relation($booking, 'room', Room::class);
        $property = $room === null ? null : $this->relation($room, 'property', Property::class);
        $host = $property === null ? null : $this->relation($property, 'host', User::class);

        return $this->objectMapper->map($booking, AdminBookingListItemResponse::class, self::FIELDS, [
            'status' => $status,
            // Stored timestamps are UTC; never inherit the CLI machine's default timezone.
            'created_at' => $createdAt === null ? null : new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC')),
            'customer_name' => $customer === null ? null : self::text($this->attribute($customer, 'name')),
            'room_name' => $room === null ? null : self::text($this->attribute($room, 'name')),
            'room_number' => $room === null ? null : self::text($this->attribute($room, 'room_number')),
            'property_id' => $room === null ? null : self::id($this->attribute($room, 'property_id')),
            'property_name' => $property === null ? null : self::text($this->attribute($property, 'name')),
            'host_id' => $property === null ? null : self::id($this->attribute($property, 'host_id')),
            'host_name' => $host === null ? null : self::text($this->attribute($host, 'name')),
        ]);
    }

    public function toFilterOption(User|Property $option): AdminBookingFilterOptionResponse
    {
        return new AdminBookingFilterOptionResponse(
            self::id($this->attribute($option, 'id')),
            self::text($this->attribute($option, 'name')),
            $option instanceof Property ? self::id($this->attribute($option, 'host_id')) : null,
        );
    }

    private function attribute(Model $model, string $field): mixed
    {
        $attributes = $model->getAttributes();
        if (!array_key_exists($field, $attributes)) {
            throw new LogicException('Admin Booking mapping requires loaded attributes.');
        }

        return $attributes[$field];
    }

    private function relation(Model $model, string $name, string $expected): ?Model
    {
        if (!$model->relationLoaded($name)) {
            throw new LogicException('Admin Booking mapping requires eager-loaded relations.');
        }
        $related = $model->getRelation($name);
        if ($related !== null && !$related instanceof $expected) {
            throw new LogicException('Invalid loaded Admin Booking relation.');
        }

        return $related;
    }

    private static function id(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value))
            || preg_match('/\A[1-9][0-9]*\z/', (string) $value) !== 1
            || strlen((string) $value) > 16
            || (strlen((string) $value) === 16 && strcmp((string) $value, (string) AdminBookingQueryRequest::MAX_ID) > 0)) {
            throw new LogicException('Invalid stored Admin Booking identifier.');
        }

        return (int) $value;
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value)) {
            throw new LogicException('Invalid stored Admin Booking text.');
        }

        return $value;
    }
}
