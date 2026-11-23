<?php

namespace App\DTOs\Dashboard\Responses;

use App\Enums\BookingStatus;
use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use UnexpectedValueException;

final readonly class HostDashboardSnapshotResponse implements JsonSerializable
{
    private array $data;

    public function __construct(DateTimeImmutable $generatedAt, object $facts)
    {
        $count = static function (mixed $value): int {
            if ((!is_int($value) && !is_string($value)) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string) $value) !== 1
                || strlen((string) $value) > 16 || (strlen((string) $value) === 16 && strcmp((string) $value, '9007199254740991') > 0)) {
                throw new UnexpectedValueException('Số lượng tổng quan Host không hợp lệ.');
            }
            return (int) $value;
        };
        $id = static function (mixed $value): string {
            if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
                throw new UnexpectedValueException('ID tổng quan Host không hợp lệ.');
            }
            return $value;
        };
        $text = static function (mixed $value): string {
            if (!is_string($value)) { throw new UnexpectedValueException('Dữ liệu tổng quan Host không hợp lệ.'); }
            return $value;
        };
        $date = static function (mixed $value) use ($text): string {
            $value = $text($value);
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
            if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
                throw new UnexpectedValueException('Ngày lưu trú không hợp lệ.');
            }
            return $value;
        };
        if (!is_string($facts->recent_json ?? null)) { throw new UnexpectedValueException('Danh sách Booking không hợp lệ.'); }
        $rows = json_decode($facts->recent_json, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 5) {
            throw new UnexpectedValueException('Danh sách Booking không hợp lệ.');
        }
        $recent = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!$row instanceof \stdClass) { throw new UnexpectedValueException('Booking không hợp lệ.'); }
            $row = (array) $row;
            $bookingId = $id($row['id'] ?? null);
            $epoch = $row['created_at_epoch'] ?? null;
            if (!is_string($epoch) || preg_match('/\A(?:0|[1-9][0-9]{0,10})\z/', $epoch) !== 1
                || !in_array($row['status'] ?? null, array_column(BookingStatus::cases(), 'value'), true)
                || isset($seen[$bookingId])) {
                throw new UnexpectedValueException('Booking không hợp lệ.');
            }
            $createdAt = new DateTimeImmutable('@'.$epoch);
            if ($createdAt > $generatedAt) { throw new UnexpectedValueException('Booking vượt cutoff.'); }
            $seen[$bookingId] = true;
            $recent[] = ['id' => $bookingId, 'booking_code' => $text($row['booking_code'] ?? null),
                'status' => $row['status'], 'created_at' => $createdAt->format('Y-m-d\TH:i:s.u\Z'),
                'check_in_date' => $date($row['check_in_date'] ?? null), 'check_out_date' => $date($row['check_out_date'] ?? null),
                'property' => ['id' => $id($row['property_id'] ?? null), 'name' => $text($row['property_name'] ?? null)],
                'room' => ['id' => $id($row['room_id'] ?? null), 'name' => $text($row['room_name'] ?? null)]];
        }
        usort($recent, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at'])
            ?: (strlen($b['id']) <=> strlen($a['id'])) ?: strcmp($b['id'], $a['id']));
        $this->data = ['scope' => ['properties_count' => $count($facts->properties_count ?? null)],
            'snapshot' => ['rooms_count' => $count($facts->rooms_count ?? null),
                'pending_bookings_count' => $count($facts->pending_bookings_count ?? null),
                'in_progress_maintenances_count' => $count($facts->in_progress_maintenances_count ?? null)],
            'recent_bookings' => $recent,
            'generated_at' => $generatedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'report_timezone' => 'Asia/Ho_Chi_Minh'];
    }

    public function jsonSerialize(): array { return $this->data; }
}
