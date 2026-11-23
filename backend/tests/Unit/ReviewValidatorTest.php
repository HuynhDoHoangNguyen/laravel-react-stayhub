<?php

namespace Tests\Unit;

use App\Validators\Review\ReviewValidator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReviewValidatorTest extends TestCase
{
    public function test_create_validation_preserves_raw_rating_and_unicode_comment_bounds(): void
    {
        $validator = $this->validator();
        foreach (['+1', 1.0, true, '5'] as $rating) {
            foreach ([str_repeat('á', 5), str_repeat('á', 1000)] as $comment) {
                $input = ['rating' => $rating, 'comment' => $comment];
                $this->assertSame($input, $validator->validateCreateInput($input + ['customer_id' => 99]));
            }
        }

        try {
            $validator->validateCreateInput(['rating' => '1e0', 'comment' => str_repeat('á', 1001)]);
            $this->fail('Expected validation failure for invalid rating and comment.');
        } catch (ValidationException $error) {
            $this->assertSame([
                'rating' => ['Điểm đánh giá phải là số nguyên.'],
                'comment' => ['Nội dung đánh giá không được vượt quá 1000 ký tự (quy định V1).'],
            ], $error->errors());
        }
    }

    public function test_create_booking_id_preserves_existing_route_predicate(): void
    {
        $validator = $this->validator();
        $this->assertSame(42, $validator->validateCreateBookingId('42'));
        foreach (['042', '+42', '4.2e1', ' 42 ', '0', '-1', 'ABC', (string) PHP_INT_MAX.'0', []] as $id) {
            $this->assertNull($validator->validateCreateBookingId($id));
        }
    }

    public function test_host_validation_preserves_raw_presence_and_nullable_search(): void
    {
        $validator = $this->validator();
        $this->assertSame([], $validator->validateHostQuery(['ignored' => ['x']]));
        foreach ([null, '', '  ', '0', str_repeat('á', 255)] as $search) {
            $input = ['page' => '+2', 'per_page' => true, 'property_id' => '3', 'rating' => 5.0, 'search' => $search];
            $before = $input;
            $this->assertSame(['page' => '+2', 'per_page' => true, 'rating' => 5.0, 'property_id' => '3', 'search' => $search],
                $validator->validateHostQuery($input));
            $this->assertSame($before, $input);
        }
    }

    public function test_host_rejects_raw_invalid_values_and_preserves_messages(): void
    {
        $validator = $this->validator();
        foreach (['page', 'per_page', 'rating', 'property_id'] as $field) {
            foreach ([null, [], '01', '1.0', '1e0', false, 1.5, 0, -1] as $value) {
                try {
                    $validator->validateHostQuery([$field => $value]);
                    $this->fail('Expected raw Host validation failure.');
                } catch (ValidationException $error) {
                    $this->assertArrayHasKey($field, $error->errors());
                }
            }
        }
        try {
            $validator->validateHostQuery(['property_id' => 0, 'search' => str_repeat('á', 256)]);
            $this->fail('Expected Host validation errors.');
        } catch (ValidationException $error) {
            $this->assertSame([
                'property_id' => ['Mã cơ sở lưu trú (property_id) không hợp lệ.'],
                'search' => ['Từ khóa tìm kiếm không được vượt quá 255 ký tự.'],
            ], $error->errors());
        }
    }

    public function test_host_authorization_uses_session_actor_role_without_mutation(): void
    {
        $validator = $this->validator();
        $host = new \App\Models\User(['role' => 'HOST']);
        $before = $host->getAttributes();
        $validator->authorizeHost($host);
        $this->assertSame($before, $host->getAttributes());
        foreach ([null, new \App\Models\User(['role' => 'CUSTOMER']), new \App\Models\User(['role' => 'ADMIN'])] as $actor) {
            try {
                $validator->authorizeHost($actor);
                $this->fail('Expected Host role denial.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $error) {
                $this->assertSame('Bạn không có quyền truy cập chức năng này.', $error->getMessage());
            }
        }
    }

    public function test_customer_read_role_denial_keeps_message_and_does_not_mutate_actor(): void
    {
        $validator = $this->validator();
        $customer = new \App\Models\User(['role' => 'CUSTOMER']);
        $before = $customer->getAttributes();
        $validator->authorizeCustomer($customer);
        $this->assertSame($before, $customer->getAttributes());
        foreach ([null, new \App\Models\User(['role' => 'HOST']), new \App\Models\User(['role' => 'ADMIN'])] as $actor) {
            try {
                $validator->authorizeCustomer($actor);
                $this->fail('Expected Customer role denial.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $error) {
                $this->assertSame('Bạn không có quyền thực hiện chức năng này.', $error->getMessage());
            }
        }
    }

    private function validator(): ReviewValidator
    {
        return new ReviewValidator(new Factory(new Translator(new ArrayLoader(), 'en')));
    }

    public static function acceptedQueries(): array
    {
        return [
            'missing' => [[], []],
            // Without HTTP ConvertEmptyStringsToNull, Laravel skips non-implicit rules on ''.
            'raw empty strings before HTTP middleware' => [
                ['page' => '', 'per_page' => '', 'rating' => '', 'sort_by' => ''],
                ['page' => '', 'per_page' => '', 'rating' => '', 'sort_by' => ''],
            ],
            'unknown fields ignored' => [['unknown' => ['value']], []],
            'strings remain raw' => [['page' => '+2', 'per_page' => '50', 'rating' => '5', 'sort_by' => 'rating_asc'],
                ['page' => '+2', 'per_page' => '50', 'rating' => '5', 'sort_by' => 'rating_asc']],
            'boolean integer compatibility' => [['page' => true, 'per_page' => true, 'rating' => true],
                ['page' => true, 'per_page' => true, 'rating' => true]],
            'integral float compatibility' => [['page' => 2.0], ['page' => 2.0]],
        ];
    }

    #[DataProvider('acceptedQueries')]
    public function test_validation_preserves_raw_values_and_presence(array $input, array $expected): void
    {
        $before = $input;
        $this->assertSame($expected, $this->validator()->validatePublicQuery($input));
        $this->assertSame($before, $input);
    }

    public static function rejectedQueries(): array
    {
        $cases = [];
        foreach (['page', 'per_page', 'rating'] as $field) {
            foreach ([null, [], ['1'], '01', '1.0', '1e0', false, 1.5, 0, -1] as $i => $value) {
                $cases[$field.'-'.$i] = [[$field => $value], $field];
            }
        }
        foreach ([null, [], ['latest'], 1, 'LATEST', 'other'] as $i => $value) {
            $cases['sort-'.$i] = [['sort_by' => $value], 'sort_by'];
        }
        $cases['per-page-bound'] = [['per_page' => 51], 'per_page'];
        $cases['rating-bound'] = [['rating' => 6], 'rating'];

        return $cases;
    }

    #[DataProvider('rejectedQueries')]
    public function test_raw_invalid_input_is_rejected_before_cast(array $input, string $field): void
    {
        try {
            $this->validator()->validatePublicQuery($input);
            $this->fail('Expected field validation failure.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    public function test_custom_error_messages_and_order_are_preserved(): void
    {
        try {
            $this->validator()->validatePublicQuery(['page' => 0, 'per_page' => 51, 'rating' => 6, 'sort_by' => 'wrong']);
            $this->fail('Expected field validation failure.');
        } catch (ValidationException $error) {
            $this->assertSame([
                'page' => ['Số trang (page) phải lớn hơn hoặc bằng 1.'],
                'per_page' => ['Số phần tử trên mỗi trang (per_page) tối đa là 50.'],
                'rating' => ['Bộ lọc số sao (rating) phải nằm trong khoảng từ 1 đến 5.'],
                'sort_by' => ['Kiểu sắp xếp (sort_by) không hợp lệ. Chỉ chấp nhận: latest, rating_desc, rating_asc.'],
            ], $error->errors());
        }
    }

    public function test_property_id_uses_existing_raw_predicate(): void
    {
        $validator = $this->validator();
        foreach ([1, '1', 42, '42'] as $id) {
            $this->assertSame((int) $id, $validator->validatePublicPropertyId($id));
        }
        foreach ([null, '', [], 'abc', '01', '+1', '1e0', '1.0', ' 1', 0, -1, '9999999999999999999999999'] as $id) {
            $this->assertNull($validator->validatePublicPropertyId($id));
        }
    }
}
