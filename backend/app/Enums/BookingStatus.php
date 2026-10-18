<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CHECKED_IN = 'CHECKED_IN';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case REJECTED = 'REJECTED';

    /**
     * Determine if this status holds the room.
     */
    public function holdsRoom(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED, self::CHECKED_IN], true);
    }

    /**
     * Get all statuses that hold a room.
     *
     * @return array<self>
     */
    public static function holdingValues(): array
    {
        return [self::PENDING, self::CONFIRMED, self::CHECKED_IN];
    }

    /**
     * Get allowed target statuses from current status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::CONFIRMED, self::REJECTED, self::CANCELLED],
            self::CONFIRMED => [self::CHECKED_IN, self::CANCELLED],
            self::CHECKED_IN => [self::COMPLETED],
            self::COMPLETED, self::CANCELLED, self::REJECTED => [],
        };
    }

    /**
     * Check if transition to target status is allowed.
     */
    public function canTransitionTo(BookingStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Get Vietnamese human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ xác nhận',
            self::CONFIRMED => 'Đã xác nhận',
            self::CHECKED_IN => 'Đã nhận phòng',
            self::COMPLETED => 'Đã hoàn thành',
            self::CANCELLED => 'Đã hủy',
            self::REJECTED => 'Từ chối',
        };
    }
}
