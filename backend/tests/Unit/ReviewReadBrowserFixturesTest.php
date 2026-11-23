<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\ReviewReadAcceptanceFixtures;
use Tests\Support\ReviewReadBrowserFixtures as Browser;

final class ReviewReadBrowserFixturesTest extends TestCase
{
    public function test_literal_fixture_has_pagination_tie_order_and_host_summary(): void
    {
        $this->assertSame(['R13', 'R12'], array_slice(ReviewReadAcceptanceFixtures::order('H_A'), 0, 2));
        $this->assertSame(['R03', 'R02', 'R01'], array_slice(ReviewReadAcceptanceFixtures::order('H_A'), 10));
        $this->assertSame(['total_reviews' => 13, 'average_rating' => 3.8,
            'rating_breakdown' => [5 => 6, 4 => 3, 3 => 1, 2 => 1, 1 => 2]], ReviewReadAcceptanceFixtures::summary('H_A'));
        $this->assertSame(1.3, ReviewReadAcceptanceFixtures::summary('H_B')['average_rating']);
        $this->assertNull(ReviewReadAcceptanceFixtures::summary('H_EMPTY')['average_rating']);
    }

    public function test_review_scenario_accepts_only_its_explicit_opt_in(): void
    {
        Browser::assertScenario(['scenario' => 'ReviewRead'], '1');
        $this->addToAssertionCount(1);
    }

    public static function invalidScenarios(): array
    {
        return [[[], '1'], [['scenario' => 'InvoiceRead'], '1'], [['scenario' => 'ReviewRead'], null],
            [['scenario' => 'ReviewRead'], '0'], [['scenario' => 'ReviewRead'], 1]];
    }

    #[DataProvider('invalidScenarios')]
    public function test_missing_or_wrong_scenario_is_rejected_before_database(array $manifest, mixed $optIn): void
    {
        $this->expectException(RuntimeException::class);
        Browser::assertScenario($manifest, $optIn);
    }

    public static function requests(): array
    {
        return [['GET', '/api/host/reviews', true], ['GET', '/sanctum/csrf-cookie', true],
            ['HEAD', '/', true], ['OPTIONS', '/api/host/reviews', true], ['POST', '/login', true], ['POST', '/logout', true],
            ['POST', '/api/customer/bookings/1/review', false], ['POST', '/login/extra', false],
            ['DELETE', '/api/host/reviews/1', false], ['PUT', '/api/host/reviews/1', false], ['PATCH', '/api/me', false]];
    }

    #[DataProvider('requests')]
    public function test_harness_allows_auth_but_refuses_business_mutations(string $method, string $path, bool $allowed): void
    {
        $this->assertSame($allowed, Browser::allowsRequest($method, $path));
    }

    private function sample(): array
    {
        $path = 'http://localhost:12345/api/host/reviews'; $prefix = $path.'?page=';
        $row = ['id' => 3, 'booking_id' => 7, 'booking_code' => 'READ-R01', 'property_id' => 2,
            'property_name' => 'Cơ sở P_A1', 'room_name' => 'Phòng P_A1', 'customer_name' => 'Nguyễn An',
            'rating' => 5, 'comment' => '<script>alert("QA")</script>', 'created_at' => null];
        $summary = ['total_reviews' => 13, 'average_rating' => 3.8,
            'rating_breakdown' => [5 => 6, 4 => 3, 3 => 1, 2 => 1, 1 => 2]];
        $response = ['data' => [$row], 'links' => ['first' => $prefix.'1', 'last' => $prefix.'1', 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'links' => [
                ['url' => null, 'label' => '&laquo; Previous', 'page' => null, 'active' => false],
                ['url' => $prefix.'1', 'label' => '1', 'page' => 1, 'active' => true],
                ['url' => null, 'label' => 'Next &raquo;', 'page' => null, 'active' => false]],
                'path' => $path, 'per_page' => 10, 'to' => 1, 'total' => 1], 'summary' => $summary];
        return [$response, ['data' => [$row], 'summary' => $summary, 'page' => 1, 'size' => 10, 'total' => 1, 'path' => $path]];
    }

    public function test_oracle_accepts_filtered_list_with_whole_host_summary_and_null_date(): void
    {
        [$response, $expected] = $this->sample();
        Browser::verify($response, $expected);
        $this->addToAssertionCount(1);
        $response['data'][0]['created_at'] = $expected['data'][0]['created_at'] = '2026-11-13T01:00:00.000000Z';
        // Exercise a real JSON round trip without converting timestamps or numeric identifiers.
        Browser::verify(json_decode(json_encode($response), true), $expected);
        $this->addToAssertionCount(1);
    }

    public function test_order_oracle_rejects_reversed_rows_even_if_membership_matches(): void
    {
        [$response, $expected] = $this->sample();
        $second = [...$response['data'][0], 'id' => 4, 'booking_id' => 8];
        $expected['data'][] = $second; $expected['total'] = 2;
        $response['data'] = [$second, $response['data'][0]];
        $this->expectException(RuntimeException::class);
        Browser::verify($response, $expected);
    }

    public function test_empty_filtered_page_keeps_host_summary_and_null_bounds(): void
    {
        [$response, $expected] = $this->sample();
        $response['data'] = $expected['data'] = [];
        $response['meta']['from'] = $response['meta']['to'] = null;
        $response['meta']['total'] = $expected['total'] = 0;
        Browser::verify($response, $expected);
        $this->addToAssertionCount(1);
    }

    public static function corruptions(): array
    {
        return array_map(static fn ($kind) => [$kind], ['foreign-row', 'wrong-id-type', 'missing-projection', 'extra-projection',
            'changed-comment', 'lost-null-date', 'filtered-summary', 'wrong-average', 'wrong-breakdown', 'wrong-total',
            'wrong-page', 'wrong-path', 'wrong-from', 'wrong-link', 'active-link', 'extra-envelope', 'extra-meta', 'missing-meta-links']);
    }

    #[DataProvider('corruptions')]
    public function test_oracle_rejects_contract_or_membership_corruption(string $kind): void
    {
        [$response, $expected] = $this->sample();
        switch ($kind) {
            case 'foreign-row': $response['data'][0]['property_id'] = 999; break;
            case 'wrong-id-type': $response['data'][0]['id'] = '3'; break;
            case 'missing-projection': unset($response['data'][0]['booking_code']); break;
            case 'extra-projection': $response['data'][0]['secret'] = 'unexpected'; break;
            case 'changed-comment': $response['data'][0]['comment'] = 'changed'; break;
            case 'lost-null-date': $response['data'][0]['created_at'] = ''; break;
            case 'filtered-summary': $response['summary']['total_reviews'] = 1; break;
            case 'wrong-average': $response['summary']['average_rating'] = 5; break;
            case 'wrong-breakdown': $response['summary']['rating_breakdown'][5] = 1; break;
            case 'wrong-total': $response['meta']['total'] = 2; break;
            case 'wrong-page': $response['meta']['current_page'] = 2; break;
            case 'wrong-path': $response['meta']['path'] = 'http://wrong/api/host/reviews'; break;
            case 'wrong-from': $response['meta']['from'] = 2; break;
            case 'wrong-link': $response['links']['next'] = 'http://wrong'; break;
            case 'active-link': $response['meta']['links'][1]['active'] = false; break;
            case 'extra-envelope': $response['unexpected'] = true; break;
            case 'extra-meta': $response['meta']['unexpected'] = true; break;
            case 'missing-meta-links': $response['meta']['links'] = []; break;
        }
        $this->expectException(RuntimeException::class);
        Browser::verify($response, $expected);
    }

    public function test_audit_allows_only_session_churn_without_losing_business_tables(): void
    {
        Browser::verifyAudit(['business' => ['users' => 'x', 'reviews' => 'y'], 'session_users' => [['user_id' => 1]]],
            ['business' => ['reviews' => 'y', 'users' => 'x'], 'session_users' => []]);
        $this->addToAssertionCount(1);
    }

    public static function auditCorruptions(): array
    {
        return [[['business' => ['users' => 'changed', 'reviews' => 'y'], 'session_users' => []]],
            [['business' => ['users' => 'x'], 'session_users' => []]],
            [['business' => ['users' => 'x', 'reviews' => 'y'], 'session_users' => [['user_id' => 1]]]],
            [['business' => [], 'session_users' => []]], [['business' => ['users' => 'x', 'reviews' => 'y']]]];
    }

    #[DataProvider('auditCorruptions')]
    public function test_audit_rejects_mutation_missing_tables_or_live_sessions(array $after): void
    {
        $this->expectException(RuntimeException::class);
        Browser::verifyAudit(['business' => ['users' => 'x', 'reviews' => 'y']], $after);
    }
}
