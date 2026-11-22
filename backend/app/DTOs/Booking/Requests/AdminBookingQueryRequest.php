<?php

declare(strict_types=1);

namespace App\DTOs\Booking\Requests;

use App\Enums\BookingStatus;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Normalized application input. Raw HTTP validation belongs to the API boundary. */
final readonly class AdminBookingQueryRequest
{
    public const MAX_ID = 9007199254740991;
    public const MAX_PAGE = 2147483647;

    public function __construct(
        public ?int $hostId = null,
        public ?int $propertyId = null,
        public ?int $customerId = null,
        public ?string $status = null,
        public ?string $checkInFrom = null,
        public ?string $checkInTo = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {
        foreach ([$hostId, $propertyId, $customerId] as $id) {
            if ($id !== null && ($id < 1 || $id > self::MAX_ID)) {
                throw new InvalidArgumentException('Định danh bộ lọc đặt phòng không hợp lệ.');
            }
        }
        if ($page < 1 || $page > self::MAX_PAGE || $perPage < 1 || $perPage > 50
            || ($status !== null && BookingStatus::tryFrom($status) === null)
            || ($search !== null && ($search === '' || trim($search) !== $search || mb_strlen($search) > 255))) {
            throw new InvalidArgumentException('Bộ lọc đặt phòng Admin không hợp lệ.');
        }
        foreach ([$checkInFrom, $checkInTo] as $date) {
            if ($date !== null && !self::isDate($date)) {
                throw new InvalidArgumentException('Ngày nhận phòng không hợp lệ.');
            }
        }
        if ($checkInFrom !== null && $checkInTo !== null && $checkInFrom > $checkInTo) {
            throw new InvalidArgumentException('Khoảng ngày nhận phòng không hợp lệ.');
        }
    }

    public static function isDate(string $value): bool
    {
        if (preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** Only normalized, supported fields are carried into pagination links. */
    public function toQuery(): array
    {
        return array_filter([
            'host_id' => $this->hostId, 'property_id' => $this->propertyId,
            'customer_id' => $this->customerId, 'status' => $this->status,
            'check_in_from' => $this->checkInFrom, 'check_in_to' => $this->checkInTo,
            'search' => $this->search, 'page' => $this->page, 'per_page' => $this->perPage,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
