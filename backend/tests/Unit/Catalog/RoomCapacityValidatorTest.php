<?php

namespace Tests\Unit\Catalog;

use App\Validators\Catalog\RoomCapacityValidator;
use ArgumentCountError;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

// Intentionally a weakly typed caller: numeric strings, floats and bools must not be coerced.
final class RoomCapacityValidatorTest extends TestCase
{
    #[DataProvider('capacityCases')]
    public function test_checks_capacity_for_one_room(int $capacity, int $guests, bool $expected): void
    {
        $this->assertSame($expected, (new RoomCapacityValidator())->canAccommodate($capacity, $guests));
    }

    public static function capacityCases(): array
    {
        return [
            'smallest valid room and count' => [1, 1, true],
            'one guest below capacity' => [4, 1, true],
            'just below capacity' => [4, 3, true],
            'exact capacity' => [4, 4, true],
            'one above capacity' => [4, 5, false],
            'smallest room exceeds capacity' => [1, 2, false],
            'many above capacity' => [4, 100, false],
            'maximum counts' => [2_147_483_647, 2_147_483_647, true],
            'one guest in maximum capacity' => [2_147_483_647, 1, true],
            'just below maximum capacity' => [2_147_483_647, 2_147_483_646, true],
            'maximum guests above capacity' => [2_147_483_646, 2_147_483_647, false],
        ];
    }

    #[DataProvider('invalidCounts')]
    public function test_rejects_invalid_capacity(mixed $capacity): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sức chứa phòng phải là số nguyên từ 1 đến 2147483647.');

        (new RoomCapacityValidator())->canAccommodate($capacity, 1);
    }

    #[DataProvider('invalidCounts')]
    public function test_rejects_invalid_guest_count(mixed $guests): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Số khách phải là số nguyên từ 1 đến 2147483647.');

        (new RoomCapacityValidator())->canAccommodate(4, $guests);
    }

    public static function invalidCounts(): iterable
    {
        foreach ([
            'null' => null,
            'zero' => 0,
            'negative integer' => -1,
            'minimum PHP integer' => PHP_INT_MIN,
            'true' => true,
            'false' => false,
            'whole float' => 1.0,
            'fractional float' => 1.5,
            'maximum count as float' => 2147483647.0,
            'above signed INT maximum' => 2_147_483_648,
            'NaN' => NAN,
            'positive infinity' => INF,
            'negative infinity' => -INF,
            'huge float' => PHP_FLOAT_MAX,
            'empty string' => '',
            'whitespace' => ' ',
            'canonical numeric string' => '2',
            'zero string' => '0',
            'negative string' => '-1',
            'leading zero' => '02',
            'padded numeric string' => ' 2 ',
            'explicit plus sign' => '+2',
            'decimal string' => '2.0',
            'scientific notation' => '2e0',
            'non numeric string' => 'invalid',
            'maximum count as string' => '2147483647',
            'very large numeric string' => '999999999999999999999999999999999999',
            'empty array' => [],
            'array' => [2],
            'object' => new stdClass(),
        ] as $name => $value) {
            yield $name => [$value];
        }
        if (PHP_INT_MAX > 2_147_483_647) {
            yield 'maximum PHP integer above schema bound' => [PHP_INT_MAX];
        }
    }

    public function test_numeric_string_over_capacity_is_invalid_rather_than_a_false_result(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RoomCapacityValidator())->canAccommodate(1, '2');
    }

    public function test_checks_capacity_first_when_both_inputs_are_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sức chứa phòng phải là số nguyên từ 1 đến 2147483647.');

        (new RoomCapacityValidator())->canAccommodate(null, null);
    }

    public function test_capacity_is_a_required_argument(): void
    {
        $this->expectException(ArgumentCountError::class);

        (new RoomCapacityValidator())->canAccommodate();
    }

    public function test_guest_count_is_a_required_argument(): void
    {
        $this->expectException(ArgumentCountError::class);

        (new RoomCapacityValidator())->canAccommodate(4);
    }

    public function test_repeated_checks_do_not_retain_previous_inputs_or_results(): void
    {
        $validator = new RoomCapacityValidator();

        $this->assertTrue($validator->canAccommodate(4, 4));
        $this->assertFalse($validator->canAccommodate(1, 2));
        $this->assertTrue($validator->canAccommodate(2, 1));
    }
}
