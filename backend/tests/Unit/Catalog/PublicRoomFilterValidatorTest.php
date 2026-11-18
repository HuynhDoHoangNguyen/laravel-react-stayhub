<?php

namespace Tests\Unit\Catalog;

use App\Validators\Catalog\PublicRoomFilterValidator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicRoomFilterValidatorTest extends TestCase
{
    private function validator(): PublicRoomFilterValidator
    {
        return new PublicRoomFilterValidator(new Factory(new Translator(new ArrayLoader(), 'vi')));
    }

    public function test_defaults_and_typed_projection_preserve_decimal_strings(): void
    {
        $default = $this->validator()->validate([]);
        $this->assertSame(12, $default->perPage);
        $this->assertSame('price_asc', $default->sort);
        $input = $this->validator()->validate(['min_price' => '0.00', 'max_price' => '9999999999.99',
            'guest_count' => '2147483647', 'room_type_ids' => ['2', '3'], 'amenity_ids' => ['4'], 'page' => '2', 'per_page' => '24', 'min_rating' => '4.5']);
        $this->assertSame('0.00', $input->minPrice);
        $this->assertSame('9999999999.99', $input->maxPrice);
        $this->assertSame(2147483647, $input->guestCount);
        $this->assertSame([2, 3], $input->roomTypeIds);
        $this->assertSame([4], $input->amenityIds);
        $this->assertSame(2, $input->page);
        $this->assertSame('4.5', $input->minRating);
    }

    public static function invalidQueries(): array
    {
        return [
            [['min_price' => '-1'], 'min_price'], [['max_price' => '1.001'], 'max_price'],
            [['min_price' => '01'], 'min_price'], [['max_price' => '10000000000'], 'max_price'],
            [['min_price' => true], 'min_price'], [['min_price' => 1.5], 'min_price'],
            [['min_price' => '1e2'], 'min_price'], [['max_price' => ''], 'max_price'],
            [['min_price' => '10.01', 'max_price' => '10.00'], 'max_price'],
            [['guest_count' => '0'], 'guest_count'], [['guest_count' => '01'], 'guest_count'],
            [['guest_count' => true], 'guest_count'], [['guest_count' => 1.0], 'guest_count'],
            [['guest_count' => '2147483648'], 'guest_count'],
            [['page' => '0'], 'page'], [['page' => '1.5'], 'page'], [['page' => '2147483648'], 'page'],
            [['per_page' => '25'], 'per_page'], [['per_page' => ''], 'per_page'],
            [['sort' => 'RATING_DESC'], 'sort'], [['sort' => 'price_asc; DROP TABLE rooms'], 'sort'],
            [['room_type_ids' => '1,2'], 'room_type_ids'], [['room_type_ids' => [2 => '1']], 'room_type_ids'],
            [['room_type_ids' => ['1', '1']], 'room_type_ids.0'], [['amenity_ids' => ['0']], 'amenity_ids.0'],
            [['amenity_ids' => ['abc']], 'amenity_ids.0'], [['amenity_ids' => [[1]]], 'amenity_ids.0'],
            [['amenity_ids' => range(1, 51)], 'amenity_ids'],
            [['check_in_date' => '2026-12-01'], 'check_in_date'], [['startDate' => '2026-12-01'], 'startDate'],
            [['city' => 'Hue'], 'city'], [['minRating' => '4'], 'minRating'],
            [['min_rating' => '0'], 'min_rating'], [['min_rating' => '5.1'], 'min_rating'],
            [['min_rating' => '4.50'], 'min_rating'], [['min_rating' => ''], 'min_rating'],
            [['min_rating' => true], 'min_rating'], [['min_rating' => 4.5], 'min_rating'],
            [['min_rating' => ['4']], 'min_rating'], [['min_rating' => '4e0'], 'min_rating'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_or_unsupported_input_returns_field_errors(array $query, string $field): void
    {
        try {
            $this->validator()->validate($query);
            $this->fail('Expected validation error.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    public function test_equal_price_bounds_and_empty_id_arrays_are_valid(): void
    {
        $query = $this->validator()->validate(['min_price' => '0', 'max_price' => '0.00', 'room_type_ids' => [], 'amenity_ids' => []]);
        $this->assertSame([], $query->amenityIds);
        $this->assertSame('0', $query->minPrice);
    }
}
