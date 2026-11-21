<?php

namespace Tests\Feature\Review;

use App\Mappers\Review\ReviewMapper;
use App\Models\Review;
use App\Services\Review\ReviewQueryService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ReviewReadAcceptanceFixtures;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class ReviewReadAcceptanceMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private ReviewReadAcceptanceFixtures $fixture;
    private array $before;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = (new ReviewReadAcceptanceFixtures())->create();
        $this->before = $this->fixture->fingerprint();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->before)) $this->assertSame($this->before, $this->fixture->fingerprint(), 'UC38 read requests must never mutate stored data.');
        } finally {
            parent::tearDown();
        }
    }

    private function url(array $query = []): string
    {
        return '/api/host/reviews'.($query === [] ? '' : '?'.http_build_query($query));
    }

    private function assertPage(string $actor, array $query, array $labels, int $total, string $summaryScope): array
    {
        $response = $this->actingAs($this->fixture->actors[$actor])->getJson($this->url($query))->assertOk();
        $body = $response->json();
        $this->assertSame(['data', 'links', 'meta', 'summary'], array_keys($body));
        $expectedRows = array_map(fn ($label) => $this->fixture->rows[$label], $labels);
        $this->assertSame($expectedRows, $body['data']);
        $this->assertSame(ReviewReadAcceptanceFixtures::summary($summaryScope), $body['summary']);
        $page = (int) ($query['page'] ?? 1);
        $size = (int) ($query['per_page'] ?? 10);
        $last = max(1, (int) ceil($total / $size));
        foreach (['current_page' => $page, 'per_page' => $size, 'total' => $total, 'last_page' => $last,
            'from' => $labels === [] ? null : ($page - 1) * $size + 1,
            'to' => $labels === [] ? null : ($page - 1) * $size + count($labels)] as $field => $expected) {
            $this->assertSame($expected, $body['meta'][$field], 'Pagination '.$field);
        }
        $this->assertSame(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'], array_keys($body['meta']));
        $expectedPath = rtrim(config('app.url'), '/').'/api/host/reviews';
        $this->assertSame($expectedPath, $body['meta']['path']);
        $this->assertSame(['first', 'last', 'prev', 'next'], array_keys($body['links']));
        $prefix = $expectedPath.'?page=';
        $this->assertSame(['first' => $prefix.'1', 'last' => $prefix.$last,
            'prev' => $page > 1 ? $prefix.($page - 1) : null,
            'next' => $page < $last ? $prefix.($page + 1) : null], $body['links']);
        $this->assertIsArray($body['meta']['links']);
        foreach ($body['meta']['links'] as $link) {
            if ($link['label'] === '...') {
                $this->assertSame(['url' => null, 'label' => '...', 'active' => false], $link);
                continue;
            }
            $this->assertSame(['url', 'label', 'page', 'active'], array_keys($link));
            $this->assertIsBool($link['active']);
            $this->assertIsString($link['label']);
            $this->assertSame($link['page'] === null ? null : $prefix.$link['page'], $link['url']);
            $this->assertSame(ctype_digit($link['label']) && (int) $link['label'] === $page, $link['active']);
        }
        return $body;
    }

    public function test_review_read_acceptance_membership_projection_and_whole_scope_summary(): void
    {
        $all = ReviewReadAcceptanceFixtures::order('H_A');
        $this->assertPage('H_A', [], array_slice($all, 0, 10), 13, 'H_A');
        $last = $this->assertPage('H_A', ['page' => 2], ['R03', 'R02', 'R01'], 13, 'H_A');
        $this->assertNull($last['data'][2]['created_at']);
        $this->assertPage('H_B', [], ['B03', 'B02', 'B01'], 3, 'H_B');
        $this->assertPage('H_EMPTY', [], [], 0, 'H_EMPTY');
    }

    public static function propertyScopes(): array
    {
        return [['P_A1'], ['P_A2'], ['P_A0']];
    }

    #[DataProvider('propertyScopes')]
    public function test_owned_property_membership_and_independent_summary(string $scope): void
    {
        $labels = ReviewReadAcceptanceFixtures::order($scope);
        $this->assertPage('H_A', ['property_id' => $this->fixture->properties[$scope]], $labels, count($labels), $scope);
    }

    public static function ratingLists(): array
    {
        return [[1, ['R13', 'R08']], [2, ['R07']], [3, ['R06']], [4, ['R12', 'R05', 'R04']],
            [5, ['R11', 'R10', 'R09', 'R03', 'R02', 'R01']]];
    }

    #[DataProvider('ratingLists')]
    public function test_rating_changes_only_list_not_host_summary(int $rating, array $labels): void
    {
        $this->assertPage('H_A', ['rating' => $rating], $labels, count($labels), 'H_A');
    }

    public static function searches(): array
    {
        return [
            ['QA-COMMENT', ['R10', 'R02']], ['  QA-COMMENT  ', ['R10', 'R02']],
            ['Trần Bình', ['R11', 'R04']], ['tran binh', ['R11', 'R04']], ['biển', ['R03']], ['bien', ['R03']],
            ['100%', ['R05']], ['A_B', ['R06']], ['<script>', ['R08']], ['no-match', []],
            ['%', ['R13', 'R12', 'R11', 'R10', 'R09', 'R08', 'R07', 'R06', 'R05', 'R04', 'R03', 'R02', 'R01']],
            ['_', ['R13', 'R12', 'R11', 'R10', 'R09', 'R08', 'R07', 'R06', 'R05', 'R04', 'R03', 'R02', 'R01']],
            ['0', ['R13', 'R12', 'R11', 'R10', 'R09', 'R08', 'R07', 'R06', 'R05', 'R04', 'R03', 'R02', 'R01']],
            ['   ', ['R13', 'R12', 'R11', 'R10', 'R09', 'R08', 'R07', 'R06', 'R05', 'R04', 'R03', 'R02', 'R01']],
        ];
    }

    #[DataProvider('searches')]
    public function test_mysql_search_and_legacy_wildcards_are_scoped_and_do_not_filter_summary(string $search, array $labels): void
    {
        $this->assertPage('H_A', ['search' => $search, 'per_page' => 50], $labels, count($labels), 'H_A');
    }

    public function test_combined_filters_and_empty_filtered_list_keep_property_summary(): void
    {
        $property = $this->fixture->properties['P_A2'];
        $this->assertPage('H_A', ['property_id' => $property, 'rating' => 5, 'search' => 'QA-COMMENT', 'per_page' => 1], ['R10'], 1, 'P_A2');
        $this->assertPage('H_A', ['property_id' => $property, 'rating' => 2], [], 0, 'P_A2');
        $this->assertPage('H_A', ['property_id' => $property, 'search' => 'no-match'], [], 0, 'P_A2');
    }

    public static function pages(): array
    {
        return [[1, 1, ['R13']], [2, 1, ['R12']], [1, 3, ['R13', 'R12', 'R11']],
            [5, 3, ['R01']], [6, 3, []], [1, 50, ['R13', 'R12', 'R11', 'R10', 'R09', 'R08', 'R07', 'R06', 'R05', 'R04', 'R03', 'R02', 'R01']]];
    }

    #[DataProvider('pages')]
    public function test_pagination_boundaries_null_last_and_equal_timestamp_id_tiebreak(int $page, int $size, array $labels): void
    {
        $this->assertPage('H_A', ['page' => $page, 'per_page' => $size], $labels, 13, 'H_A');
    }

    public static function invalidQueries(): array
    {
        return [['page', 0], ['page', -1], ['page', 'abc'], ['page', '1.5'], ['page', ['1']],
            ['per_page', 0], ['per_page', 51], ['per_page', 'abc'], ['per_page', '1.5'], ['per_page', ['10']],
            ['rating', 0], ['rating', 6], ['rating', 'abc'], ['rating', '1.5'], ['rating', ['5']], ['rating', ''],
            ['property_id', 0], ['property_id', -1], ['property_id', 'abc'], ['property_id', '1.5'], ['property_id', ['1']],
            ['search', ['bad']], ['search', 123], ['search', str_repeat('a', 256)]];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_returns_field_validation_before_role_or_property_checks(string $field, mixed $value): void
    {
        $query = [$field => $value];
        foreach (['H_A', 'C_A', 'ADMIN'] as $actor) {
            // Numeric GET query scalars are strings on the wire; test raw non-string search separately.
            if ($field === 'search' && is_int($value)) {
                $this->actingAs($this->fixture->actors[$actor])->json('GET', '/api/host/reviews', $query)
                    ->assertUnprocessable()->assertJsonValidationErrors([$field]);
            } else {
                $this->actingAs($this->fixture->actors[$actor])->getJson($this->url($query))
                    ->assertUnprocessable()->assertJsonValidationErrors([$field]);
            }
        }
    }

    public function test_guest_precedes_query_validation_and_wrong_role_precedes_property_lookup(): void
    {
        $this->getJson('/api/host/reviews?rating=6')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        foreach (['C_A', 'ADMIN'] as $actor) {
            $this->actingAs($this->fixture->actors[$actor])->getJson($this->url(['property_id' => $this->fixture->properties['P_B']]))
                ->assertForbidden()->assertJsonPath('message', 'Bạn không có quyền truy cập chức năng này.');
        }
    }

    public static function disabledActors(): array
    {
        return [['H_BLOCKED', 'ACCOUNT_BLOCKED'], ['H_INACTIVE', 'ACCOUNT_INACTIVE']];
    }

    #[DataProvider('disabledActors')]
    public function test_account_middleware_precedes_validation(string $actor, string $code): void
    {
        $this->actingAs($this->fixture->actors[$actor])->getJson('/api/host/reviews?rating=6')
            ->assertForbidden()->assertJsonPath('code', $code);
    }

    public function test_missing_and_foreign_property_share_404_and_invalid_query_precedes_both(): void
    {
        foreach ([$this->fixture->properties['P_B'], 999999999] as $property) {
            $this->actingAs($this->fixture->actors['H_A'])->getJson($this->url(['property_id' => $property]))
                ->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
            $this->getJson($this->url(['property_id' => $property, 'rating' => 6]))->assertUnprocessable()->assertJsonValidationErrors(['rating']);
        }
    }

    public function test_mapper_queries_nothing_for_loaded_and_absent_relations_without_breaking_foreign_keys(): void
    {
        $mapper = app(ReviewMapper::class);
        $review = Review::with(['booking.room', 'property', 'customer'])->findOrFail($this->fixture->reviewIds['R01']);
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $this->assertSame($this->fixture->rows['R01'], $mapper->toHostResponse($review)->jsonSerialize());
            $review->unsetRelations();
            $this->assertSame([...$this->fixture->rows['R01'], 'booking_code' => null, 'property_name' => null,
                'room_name' => null, 'customer_name' => 'Khách lưu trú'], $mapper->toHostResponse($review)->jsonSerialize());
            $this->assertSame([], DB::connection()->getQueryLog());
        } finally {
            DB::connection()->disableQueryLog();
        }
    }

    public function test_unexpected_read_failure_returns_safe_500_without_mutation(): void
    {
        $query = $this->createMock(ReviewQueryService::class);
        $query->expects($this->once())->method('getHostReviewSummary')->willThrowException(new RuntimeException('QA failure; private SQL must not leak.'));
        $this->app->instance(ReviewQueryService::class, $query);
        $this->actingAs($this->fixture->actors['H_A'])->getJson('/api/host/reviews')->assertStatus(500)
            ->assertExactJson(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR']);
    }
}
