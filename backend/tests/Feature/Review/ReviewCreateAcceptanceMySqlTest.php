<?php

namespace Tests\Feature\Review;

use App\Models\Review;
use App\Repositories\Review\ReviewRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\ReviewCreateAcceptanceFixtures as Fixtures;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class ReviewCreateAcceptanceMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private Fixtures $fixture;
    private array $sourceBefore;
    private array $reviewsBefore;
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse(Fixtures::STAMP, 'UTC'));
        $this->fixture = (new Fixtures())->create();
        $this->captureFixtureBaseline();
        // Outer test transaction never commits: success must not be reported as committed here.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $this->app->instance(LoggerInterface::class, $logger);
    }

    private function captureFixtureBaseline(): void
    {
        $this->sourceBefore = $this->fixture->sourceFingerprint();
        $this->reviewsBefore = $this->fixture->reviewRows();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->sourceBefore)) {
                $this->assertSame(1, DB::transactionLevel(), 'Service must return to the test outer transaction.');
                $this->assertSame($this->sourceBefore, $this->fixture->sourceFingerprint(), 'No source/auth/invoice/catalog table may change.');
                $expected = [...$this->reviewsBefore, ...$this->created];
                usort($expected, static fn ($a, $b) => $a['id'] <=> $b['id']);
                $this->assertSame($expected, $this->fixture->reviewRows(), 'Only explicitly expected complete Review rows may be added.');
            }
        } finally {
            CarbonImmutable::setTestNow();
            parent::tearDown();
        }
    }

    private function url(string $label = 'SUCCESS_A'): string
    {
        return '/api/customer/bookings/'.$this->fixture->bookings[$label]['id'].'/review';
    }

    private function valid(): array
    {
        return ['rating' => 5, 'comment' => 'Phòng sạch sẽ.'];
    }

    private function createExpected(string $label, string $actor, array $payload, int $rating, string $comment): array
    {
        $response = $this->actingAs($this->fixture->actors[$actor])->postJson($this->url($label), $payload)
            ->assertCreated()->assertHeader('Content-Type', 'application/json');
        $id = $response->json('data.id');
        $this->assertIsInt($id); $this->assertGreaterThan(0, $id);
        $expected = $this->fixture->expectedCustomer($label, $id, $rating, $comment);
        $response->assertExactJson(['data' => $expected]);
        $stored = $this->fixture->expectedStored($label, $id, $rating, $comment);
        $this->assertSame($stored, (array) DB::table('reviews')->where('id', $id)->first());
        $this->assertSame(1, DB::table('reviews')->where('booking_id', $stored['booking_id'])->count());
        $this->created[] = $stored;
        return $expected;
    }

    public static function acceptedInputs(): array
    {
        return ['minimum' => [1, 'abcde', 1, 'abcde'],
            'maximum-unicode' => [5, str_repeat('Đ', 1000), 5, str_repeat('Đ', 1000)],
            'trim' => [3, '  Phòng sạch sẽ.  ', 3, 'Phòng sạch sẽ.'],
            'signed-integer-string' => ['+4', 'Rating string contract.', 4, 'Rating string contract.'],
            'integer-string' => ['4', 'Rating integer string.', 4, 'Rating integer string.'],
            'whole-json-number' => [4.0, 'Whole number contract.', 4, 'Whole number contract.'],
            'html-is-data' => [2, '<script>alert("QA")</script>', 2, '<script>alert("QA")</script>']];
    }

    #[DataProvider('acceptedInputs')]
    public function test_review_create_acceptance_literal_projection_and_persisted_oracle(mixed $rawRating, string $rawComment, int $rating, string $comment): void
    {
        $expected = $this->createExpected('SUCCESS_A', 'C_A', ['rating' => $rawRating, 'comment' => $rawComment], $rating, $comment);
        $list = $this->getJson('/api/customer/reviews?per_page=1')->assertOk();
        $this->assertSame(['data', 'links', 'meta'], array_keys($list->json()));
        $this->assertSame([$expected], $list->json('data'));
        $list->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->actingAs($this->fixture->actors['C_B'])->getJson('/api/customer/reviews')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.booking_code', 'CREATE-FOREIGN_B');
    }

    public function test_server_owns_identities_and_timestamps_despite_client_spoofing(): void
    {
        $expected = $this->createExpected('SUCCESS_A', 'C_A', [...$this->valid(),
            'id' => 999999999, 'booking_id' => $this->fixture->bookings['FOREIGN_B']['id'],
            'customer_id' => $this->fixture->actors['C_B']->id, 'property_id' => $this->fixture->properties['P_B'],
            'created_at' => '1999-01-01', 'updated_at' => '1999-01-01', 'status' => 'APPROVED', 'secret' => 'ignored'], 5, 'Phòng sạch sẽ.');
        $this->assertNotSame(999999999, $expected['id']);
        $this->assertArrayNotHasKey('customer_id', $expected);
    }

    public function test_customer_b_can_create_their_own_review_but_customer_a_cannot_see_it(): void
    {
        $this->createExpected('SUCCESS_B', 'C_B', $this->valid(), 5, 'Phòng sạch sẽ.');
        $this->getJson('/api/customer/reviews')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->actingAs($this->fixture->actors['C_A'])->getJson('/api/customer/reviews')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.booking_code', 'CREATE-DUPLICATE_A');
    }

    public function test_cross_host_booking_uses_its_room_property_and_correct_host_summary(): void
    {
        $created = $this->createExpected('CROSS_HOST', 'C_A', $this->valid(), 5, 'Phòng sạch sẽ.');
        $hostB = $this->actingAs($this->fixture->actors['H_B'])->getJson('/api/host/reviews')->assertOk();
        $this->assertSame([$created['id'], $this->reviewsBefore[1]['id']], array_column($hostB->json('data'), 'id'));
        $this->assertSame(['total_reviews' => 2, 'average_rating' => 4,
            'rating_breakdown' => [5 => 1, 4 => 0, 3 => 1, 2 => 0, 1 => 0]], $hostB->json('summary'));
        $hostA = $this->actingAs($this->fixture->actors['H_A'])->getJson('/api/host/reviews')->assertOk();
        $this->assertSame([$this->reviewsBefore[0]['id']], array_column($hostA->json('data'), 'id'));
        $this->assertSame(['total_reviews' => 1, 'average_rating' => 4,
            'rating_breakdown' => [5 => 0, 4 => 1, 3 => 0, 2 => 0, 1 => 0]], $hostA->json('summary'));
    }

    public static function invalidPayloads(): array
    {
        $valid = ['rating' => 5, 'comment' => 'Valid comment.'];
        $cases = [];
        foreach (['missing' => null, 'null' => null, 'empty' => '', 'zero' => 0, 'six' => 6, 'negative' => -1,
            'fraction' => 2.5, 'fraction-string' => '2.5', 'text' => 'five', 'array' => [5], 'false' => false] as $label => $rating) {
            $payload = [...$valid, 'rating' => $rating];
            if ($label === 'missing') unset($payload['rating']);
            $cases['rating-'.$label] = [$payload, 'rating'];
        }
        foreach (['missing' => null, 'null' => null, 'empty' => '', 'spaces' => '   ', 'short' => 'abcd',
            'unicode-short' => 'Đẹp!', 'long' => str_repeat('Đ', 1001), 'number' => 12345, 'array' => ['hello'], 'boolean' => true] as $label => $comment) {
            $payload = [...$valid, 'comment' => $comment];
            if ($label === 'missing') unset($payload['comment']);
            $cases['comment-'.$label] = [$payload, 'comment'];
        }
        return $cases;
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_422_before_role_booking_or_duplicate(array $payload, string $field): void
    {
        foreach (['C_A', 'H_A', 'ADMIN'] as $actor) {
            $this->actingAs($this->fixture->actors[$actor])->postJson($this->url('DUPLICATE_A'), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors([$field]);
        }
    }

    public function test_boolean_true_reaches_domain_rejection_without_becoming_rating_one(): void
    {
        $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), ['rating' => true, 'comment' => 'Valid boolean test.'])
            ->assertUnprocessable()->assertExactJson(['message' => 'Điểm đánh giá phải là số nguyên.']);
    }

    public static function deniedActors(): array
    {
        return [[null, 401, 'Unauthenticated.'], ['H_A', 403, 'Bạn không có quyền thực hiện chức năng này.'],
            ['H_B', 403, 'Bạn không có quyền thực hiện chức năng này.'], ['ADMIN', 403, 'Bạn không có quyền thực hiện chức năng này.']];
    }

    #[DataProvider('deniedActors')]
    public function test_guest_host_and_admin_cannot_create_or_modify_customer_review(?string $actor, int $status, string $message): void
    {
        if ($actor !== null) $this->actingAs($this->fixture->actors[$actor]);
        $this->postJson($this->url('DUPLICATE_A'), $this->valid())->assertStatus($status)->assertExactJson(['message' => $message]);
    }

    public function test_guest_authentication_precedes_even_invalid_body_and_route(): void
    {
        $this->postJson('/api/customer/bookings/bad-id/review', ['rating' => 6, 'comment' => 'x'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public static function blockedAccounts(): array
    {
        return [['C_BLOCKED', 'ACCOUNT_BLOCKED'], ['C_INACTIVE', 'ACCOUNT_INACTIVE']];
    }

    #[DataProvider('blockedAccounts')]
    public function test_account_state_precedes_validation_role_and_booking(string $actor, string $code): void
    {
        $this->actingAs($this->fixture->actors[$actor])->postJson('/api/customer/bookings/bad-id/review', ['rating' => 6])
            ->assertForbidden()->assertJsonPath('code', $code);
    }

    public static function nonCompleted(): array
    {
        return array_map(static fn ($status) => [$status], ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'REJECTED']);
    }

    #[DataProvider('nonCompleted')]
    public function test_all_non_completed_statuses_are_ineligible_even_with_past_checkout(string $status): void
    {
        $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url($status), $this->valid())->assertUnprocessable()
            ->assertExactJson(['message' => "Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: '{$status}'."]);
    }

    public static function invalidRouteIds(): array
    {
        return [['bad-id'], ['0'], ['-1'], ['01'], ['1.0'], ['1e0'], ['+1'], ['999999999']];
    }

    #[DataProvider('invalidRouteIds')]
    public function test_invalid_or_missing_booking_ids_preserve_privacy(string $id): void
    {
        $this->actingAs($this->fixture->actors['C_A'])->postJson('/api/customer/bookings/'.rawurlencode($id).'/review', $this->valid())
            ->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy đơn đặt phòng.']);
    }

    public function test_other_customer_and_missing_booking_have_same_404_without_private_details(): void
    {
        $this->actingAs($this->fixture->actors['C_A']);
        $foreign = $this->postJson($this->url('FOREIGN_B'), $this->valid())->assertNotFound();
        $missing = $this->postJson('/api/customer/bookings/999999999/review', $this->valid())->assertNotFound();
        $this->assertSame(['message' => 'Không tìm thấy đơn đặt phòng.'], $foreign->json());
        $this->assertSame($foreign->json(), $missing->json());
    }

    public function test_http_route_role_owner_status_and_duplicate_precedence(): void
    {
        $this->actingAs($this->fixture->actors['H_A']);
        $this->postJson('/api/customer/bookings/bad-id/review', ['rating' => 6, 'comment' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['rating', 'comment']);
        $this->postJson('/api/customer/bookings/bad-id/review', $this->valid())->assertNotFound();
        $this->postJson('/api/customer/bookings/999999999/review', $this->valid())->assertForbidden();
        // Deliberately arrange a historical inconsistent state, with all FKs still valid.
        DB::table('bookings')->where('id', $this->fixture->bookings['DUPLICATE_A']['id'])->update(['status' => 'PENDING']);
        $this->captureFixtureBaseline();
        $this->actingAs($this->fixture->actors['C_A']);
        $this->postJson($this->url('DUPLICATE_A'), ['rating' => 6])->assertUnprocessable()->assertJsonValidationErrors(['rating']);
        $this->postJson($this->url('DUPLICATE_A'), $this->valid())->assertUnprocessable()
            ->assertExactJson(['message' => "Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'PENDING'."]);
        $this->actingAs($this->fixture->actors['C_B'])->postJson($this->url('DUPLICATE_A'), $this->valid())->assertNotFound();
    }

    public function test_preexisting_duplicate_and_sequential_retry_preserve_original_review(): void
    {
        $this->actingAs($this->fixture->actors['C_A']);
        $id = $this->fixture->bookings['DUPLICATE_A']['id'];
        $this->postJson($this->url('DUPLICATE_A'), $this->valid())->assertConflict()
            ->assertExactJson(['message' => "Đơn đặt phòng #{$id} đã có đánh giá. Mỗi đơn đặt phòng chỉ được đánh giá một lần."]);
        $created = $this->createExpected('SUCCESS_A', 'C_A', $this->valid(), 5, 'Phòng sạch sẽ.');
        $before = $this->fixture->reviewRows();
        $this->postJson($this->url(), ['rating' => 1, 'comment' => 'Do not overwrite.'])->assertConflict();
        $this->assertSame($before, $this->fixture->reviewRows());
        $this->assertSame(5, DB::table('reviews')->where('id', $created['id'])->value('rating'));
    }

    public function test_form_content_negotiation_keeps_redirect_while_json_keeps_field_errors(): void
    {
        $this->actingAs($this->fixture->actors['C_A'])->from('/customer/bookings/qa')->post($this->url(), ['rating' => 6, 'comment' => 'x'])
            ->assertRedirect('/customer/bookings/qa')->assertSessionHasErrors(['rating', 'comment']);
        $this->postJson($this->url(), ['rating' => 6, 'comment' => 'x'])->assertUnprocessable()->assertExactJson([
            'message' => 'Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao. (and 1 more error)',
            'errors' => ['rating' => ['Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.'],
                'comment' => ['Nội dung đánh giá phải có tối thiểu 5 ký tự.']]]);
    }

    private function assertSafe500($response): void
    {
        $response->assertStatus(500)->assertExactJson(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR']);
    }

    public function test_real_not_null_sql_failure_is_safe_500_not_duplicate_and_recovers_after_hook_removal(): void
    {
        $dispatcher = Review::getEventDispatcher(); $driver = null;
        $real = $this->app->make(ReviewRepository::class);
        $repository = $this->getMockBuilder(ReviewRepository::class)->onlyMethods(['createReview'])->getMock();
        $repository->method('createReview')->willReturnCallback(function (array $attributes) use ($real, &$driver) {
            try { return $real->createReview($attributes); }
            catch (QueryException $error) { $driver = $error->errorInfo[1] ?? null; throw $error; }
        });
        $this->app->instance(ReviewRepository::class, $repository);
        Review::setEventDispatcher(clone $dispatcher);
        try {
            Review::creating(static function (Review $review) { $review->comment = null; });
            $this->assertSafe500($this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->valid()));
            $this->assertSame(1048, $driver);
            $this->assertSame($this->reviewsBefore, $this->fixture->reviewRows());
        } finally { Review::setEventDispatcher($dispatcher); $this->app->instance(ReviewRepository::class, $real); }
        $this->createExpected('SUCCESS_A', 'C_A', $this->valid(), 5, 'Phòng sạch sẽ.');
    }

    public function test_exception_after_actual_insert_rolls_back_the_new_row(): void
    {
        $dispatcher = Review::getEventDispatcher(); $insertObserved = false;
        Review::setEventDispatcher(clone $dispatcher);
        try {
            Review::created(function (Review $review) use (&$insertObserved) {
                $insertObserved = DB::table('reviews')->where('id', $review->id)->exists();
                throw new RuntimeException('PRIVATE_REVIEW_AFTER_INSERT_FAULT');
            });
            $this->assertSafe500($this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->valid()));
            $this->assertTrue($insertObserved, 'Fault must happen after a real persisted SQL insert, before transaction completion.');
            $this->assertSame($this->reviewsBefore, $this->fixture->reviewRows());
        } finally { Review::setEventDispatcher($dispatcher); }
    }

    public function test_real_unique_constraint_is_translated_in_one_session_not_a_race(): void
    {
        $dispatcher = Review::getEventDispatcher(); $driver = null;
        $real = $this->app->make(ReviewRepository::class);
        $repository = $this->getMockBuilder(ReviewRepository::class)->onlyMethods(['createReview'])->getMock();
        $repository->method('createReview')->willReturnCallback(function (array $attributes) use ($real, &$driver) {
            try { return $real->createReview($attributes); }
            catch (QueryException $error) { $driver = $error->errorInfo[1] ?? null; throw $error; }
        });
        $this->app->instance(ReviewRepository::class, $repository);
        Review::setEventDispatcher(clone $dispatcher);
        try {
            // Insert the competitor after every precheck; MySQL performs the actual conflicting INSERT next.
            Review::creating(static function (Review $review) { DB::table('reviews')->insert($review->getAttributes()); });
            $id = $this->fixture->bookings['SUCCESS_A']['id'];
            $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->valid())->assertConflict()
                ->assertExactJson(['message' => "Đơn đặt phòng #{$id} đã có đánh giá trong hệ thống (vi phạm ràng buộc duy nhất)."]);
            $this->assertSame(1062, $driver);
            $this->assertSame($this->reviewsBefore, $this->fixture->reviewRows(), 'Controlled competitor is in the same rolled-back service transaction.');
        } finally { Review::setEventDispatcher($dispatcher); $this->app->instance(ReviewRepository::class, $real); }
    }
}
