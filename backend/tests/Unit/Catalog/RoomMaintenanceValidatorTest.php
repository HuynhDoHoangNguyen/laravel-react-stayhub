<?php

namespace Tests\Unit\Catalog;

use App\DTOs\Catalog\Requests\CheckRoomMaintenanceQuery;
use App\Validators\Catalog\RoomMaintenanceValidator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

// A weakly typed caller must not coerce IDs or dates before validation.
final class RoomMaintenanceValidatorTest extends TestCase
{
    public function test_returns_a_typed_query_without_changing_validated_values(): void
    {
        $query = (new RoomMaintenanceValidator())->validateQuery(7, '2028-02-29', '2028-03-01');

        $this->assertInstanceOf(CheckRoomMaintenanceQuery::class, $query);
        $this->assertSame(7, $query->roomId);
        $this->assertSame('2028-02-29', $query->checkInDate);
        $this->assertSame('2028-03-01', $query->checkOutDate);
    }

    public function test_accepts_mysql_date_boundaries_and_positive_php_integer_ids(): void
    {
        $query = (new RoomMaintenanceValidator())->validateQuery(PHP_INT_MAX, '1000-01-01', '9999-12-31');

        $this->assertSame(PHP_INT_MAX, $query->roomId);
        $this->assertSame('1000-01-01', $query->checkInDate);
        $this->assertSame('9999-12-31', $query->checkOutDate);
    }

    #[DataProvider('invalidRoomIds')]
    public function test_rejects_invalid_room_ids_before_date_validation(mixed $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mã phòng phải là số nguyên dương.');

        (new RoomMaintenanceValidator())->validateQuery($id, null, null);
    }

    public static function invalidRoomIds(): array
    {
        return [
            'null' => [null], 'zero' => [0], 'negative' => [-1], 'numeric string' => ['1'],
            'leading zero' => ['01'], 'huge string' => ['99999999999999999999999999'],
            'true' => [true], 'false' => [false], 'whole float' => [1.0], 'fraction' => [1.5],
            'NaN' => [NAN], 'infinity' => [INF], 'empty' => [''], 'array' => [[1]], 'object' => [new stdClass()],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_rejects_invalid_check_in_dates(mixed $date): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ngày nhận phòng phải có định dạng YYYY-MM-DD hợp lệ, từ năm 1000 đến 9999.');

        (new RoomMaintenanceValidator())->validateQuery(1, $date, '9999-12-31');
    }

    #[DataProvider('invalidDates')]
    public function test_rejects_invalid_check_out_dates(mixed $date): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ngày trả phòng phải có định dạng YYYY-MM-DD hợp lệ, từ năm 1000 đến 9999.');

        (new RoomMaintenanceValidator())->validateQuery(1, '1000-01-01', $date);
    }

    public static function invalidDates(): array
    {
        return [
            'null' => [null], 'true' => [true], 'integer' => [20261210], 'float' => [20261210.0],
            'array' => [['2026-12-10']], 'date object' => [new DateTimeImmutable('2026-12-10')],
            'empty' => [''], 'whitespace' => [' '], 'padded' => [' 2026-12-10 '],
            'unpadded month' => ['2026-2-10'], 'unpadded day' => ['2026-12-1'],
            'zero date' => ['0000-00-00'], 'zero month' => ['2026-00-10'], 'zero day' => ['2026-12-00'],
            'invalid month' => ['2026-13-01'], 'invalid day' => ['2026-04-31'],
            'non leap year' => ['2026-02-29'], 'non leap century' => ['1900-02-29'],
            'below mysql minimum' => ['0999-12-31'], 'above mysql maximum' => ['10000-01-01'],
            'time included' => ['2026-12-10T00:00:00Z'], 'relative date' => ['tomorrow'],
            'alternate order' => ['10/12/2026'], 'trailing newline' => ["2026-12-10\n"],
        ];
    }

    #[DataProvider('invalidIntervals')]
    public function test_check_out_must_follow_check_in(string $start, string $end): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ngày trả phòng phải sau ngày nhận phòng.');

        (new RoomMaintenanceValidator())->validateQuery(1, $start, $end);
    }

    public static function invalidIntervals(): array
    {
        return [['2026-12-10', '2026-12-10'], ['2026-12-11', '2026-12-10']];
    }

    /** Expected values are independent examples for the stay [Dec 10, Dec 15). */
    public static function scheduledIntervals(): array
    {
        return [
            'before' => ['2026-12-01', '2026-12-03', false],
            'ends on check in' => ['2026-12-08', '2026-12-10', false],
            'starts on check out' => ['2026-12-15', '2026-12-18', false],
            'after' => ['2026-12-20', '2026-12-22', false],
            'overlaps beginning' => ['2026-12-09', '2026-12-11', true],
            'overlaps end' => ['2026-12-14', '2026-12-16', true],
            'inside stay' => ['2026-12-11', '2026-12-12', true],
            'contains stay' => ['2026-12-01', '2026-12-20', true],
            'identical' => ['2026-12-10', '2026-12-15', true],
            'same beginning' => ['2026-12-10', '2026-12-11', true],
            'same end' => ['2026-12-14', '2026-12-15', true],
        ];
    }

    #[DataProvider('scheduledIntervals')]
    public function test_scheduled_intervals_use_half_open_overlap(string $start, string $end, bool $expected): void
    {
        $validator = new RoomMaintenanceValidator();
        $query = $validator->validateQuery(1, '2026-12-10', '2026-12-15');

        $this->assertSame($expected, $validator->hasBlockingFacts([$this->fact('SCHEDULED', $start, $end)], $query));
    }

    public static function stateCases(): array
    {
        return [
            'overdue in progress' => ['IN_PROGRESS', '2026-01-01', '2026-01-02', true],
            'future in progress' => ['IN_PROGRESS', '2027-01-01', '2027-01-02', true],
            'adjacent in progress' => ['IN_PROGRESS', '2026-12-15', '2026-12-16', true],
            'malformed in progress' => ['IN_PROGRESS', '0000-00-00', '0000-00-00', true],
            'completed overlap' => ['COMPLETED', '2026-12-10', '2026-12-15', false],
            'cancelled overlap' => ['CANCELLED', '2026-12-10', '2026-12-15', false],
            'completed malformed' => ['COMPLETED', '2026-02-31', '2026-01-01', false],
            'cancelled malformed' => ['CANCELLED', null, null, false],
            'scheduled zero date' => ['SCHEDULED', '0000-00-00', '0000-00-00', true],
            'scheduled invalid calendar day' => ['SCHEDULED', '2026-02-31', '2026-03-02', true],
            'scheduled unpadded' => ['SCHEDULED', '2026-2-1', '2026-02-02', true],
            'scheduled null start' => ['SCHEDULED', null, '2026-12-01', true],
            'scheduled null end' => ['SCHEDULED', '2026-12-01', null, true],
            'scheduled equal dates' => ['SCHEDULED', '2026-12-01', '2026-12-01', true],
            'scheduled reversed dates' => ['SCHEDULED', '2026-12-02', '2026-12-01', true],
        ];
    }

    #[DataProvider('stateCases')]
    public function test_unresolved_work_blocks_and_terminal_work_does_not(string $status, mixed $start, mixed $end, bool $expected): void
    {
        $validator = new RoomMaintenanceValidator();
        $query = $validator->validateQuery(1, '2026-12-10', '2026-12-15');

        $this->assertSame($expected, $validator->hasBlockingFacts([$this->fact($status, $start, $end)], $query));
    }

    public function test_empty_and_multiple_facts_use_any_blocker_without_retaining_state(): void
    {
        $validator = new RoomMaintenanceValidator();
        $query = $validator->validateQuery(1, '2026-12-10', '2026-12-15');
        $clear = $this->fact('SCHEDULED', '2026-12-15', '2026-12-20');
        $blocker = $this->fact('SCHEDULED', '2026-12-12', '2026-12-13');

        $this->assertFalse($validator->hasBlockingFacts([], $query));
        $this->assertTrue($validator->hasBlockingFacts([$clear, $blocker], $query));
        $this->assertTrue($validator->hasBlockingFacts([$blocker, $clear], $query));
        $this->assertFalse($validator->hasBlockingFacts([$clear], $query));
    }

    public function test_past_intervals_and_leap_centuries_are_calendar_dates_without_clock_rules(): void
    {
        $validator = new RoomMaintenanceValidator();
        $query = $validator->validateQuery(1, '2000-02-29', '2000-03-01');

        $this->assertTrue($validator->hasBlockingFacts([$this->fact('SCHEDULED', '2000-02-28', '2000-03-02')], $query));
    }

    private function fact(string $status, mixed $start, mixed $end): object
    {
        return (object) ['status' => $status, 'start_date' => $start, 'end_date' => $end];
    }
}
