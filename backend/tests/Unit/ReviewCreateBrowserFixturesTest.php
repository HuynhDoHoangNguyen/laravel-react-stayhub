<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\ReviewCreateBrowserFixtures as F;

final class ReviewCreateBrowserFixturesTest extends TestCase
{
    public function test_review_create_browser_literal_payloads_and_boundaries(): void
    {
        $this->assertSame(['rating' => 1, 'comment' => 'abcde'], F::payload('MIN'));
        $this->assertSame(1000, mb_strlen(F::payload('MAX')['comment']));
        $this->assertSame(5, F::payload('MAX')['rating']);
        $this->assertSame('<script>alert("QA")</script> Phòng sạch.', F::payload('HTML')['comment']);
        $this->assertCount(14, F::kinds());
    }

    public function test_unknown_recipe_is_rejected_without_database_boot(): void
    {
        $this->expectException(RuntimeException::class); F::payload('invented');
    }

    public static function scenarios(): array
    {
        return ['correct' => ['ReviewCreate', '1', true], 'wrong' => ['ReviewRead', '1', false],
            'missing-opt-in' => ['ReviewCreate', false, false], 'disabled' => ['ReviewCreate', '0', false]];
    }

    #[DataProvider('scenarios')]
    public function test_scenario_guard_fails_closed(string $scenario, mixed $optIn, bool $allowed): void
    {
        if (!$allowed) $this->expectException(RuntimeException::class);
        F::assertScenario(['scenario' => $scenario], $optIn);
        if ($allowed) $this->addToAssertionCount(1);
    }

    public static function routes(): array
    {
        return ['read' => ['GET', '/api/customer/reviews', true], 'login' => ['POST', '/login', true],
            'logout' => ['POST', '/logout', true], 'owned-probe' => ['POST', '/api/customer/bookings/7/review', true],
            'ui-not-probe' => ['POST', '/api/customer/bookings/8/review', false], 'missing' => ['POST', '/api/customer/bookings/999/review', true],
            'unknown' => ['POST', '/api/customer/bookings/1000/review', false], 'register' => ['POST', '/register', false],
            'invoice' => ['POST', '/api/host/bookings/7/invoice', false], 'delete' => ['DELETE', '/api/customer/bookings/7/review', false],
            'leading-zero' => ['POST', '/api/customer/bookings/07/review', false]];
    }

    #[DataProvider('routes')]
    public function test_request_allowlist_separates_probe_and_ui(string $method, string $path, bool $allowed): void
    {
        $bookings = [['id' => 7, 'family' => 'P'], ['id' => 8, 'family' => 'UI']];
        $this->assertSame($allowed, F::allowsRequest($method, $path, $bookings, 999, 'P'));
        $this->assertTrue(F::allowsRequest('POST', '/api/customer/bookings/8/review', $bookings, 999, 'UI'));
        $this->assertFalse(F::allowsRequest('POST', '/api/customer/bookings/7/review', $bookings, 999, 'UI'));
    }

    private function sample(): array
    {
        $spec = ['id' => 7, 'family' => 'P', 'mutable' => true, 'customer_id' => 2, 'property_id' => 3,
            'booking_code' => 'P-MAIN', 'property_name' => 'Cơ sở QA', 'room_name' => 'Phòng QA'];
        $row = ['id' => 10, 'booking_id' => 7, 'customer_id' => 2, 'property_id' => 3, 'rating' => 5,
            'comment' => 'Phòng sạch sẽ', 'created_at' => '2026-11-20 03:04:05', 'updated_at' => '2026-11-20 03:04:05'];
        $a = ['booking_id' => 7, 'actor_id' => 2, 'status' => 201, 'review_id' => 10, 'rating' => 5, 'comment' => 'Phòng sạch sẽ'];
        $before = ['source' => ['bookings' => 'stable', 'users' => 'hashed-only'], 'reviews' => [], 'attempts' => [], 'session_users' => []];
        return [$before, [...$before, 'reviews' => [$row], 'attempts' => [$a]], ['P-MAIN' => $spec]];
    }

    public function test_review_delta_matches_submitted_payload_and_preserves_sources(): void
    {
        [$before, $after, $specs] = $this->sample();
        $after['session_users'] = [['user_id' => 2, 'count' => 1]];
        $this->assertSame(['matched' => true, 'new_reviews' => 1, 'created' => [7 => 10], 'source_unchanged' => true], F::verifyDelta($before, $after, $specs, [7]));
        $this->assertSame(0, F::verifyDelta($after, $after, $specs, [])['new_reviews']);
    }

    public static function corruptions(): array
    {
        return array_combine($keys = ['source', 'unauthorized', 'immutable', 'unknown-booking', 'duplicate-id', 'duplicate-booking',
            'customer', 'property', 'rating', 'comment', 'invalid-time', 'updated-time', 'missing-journal', 'duplicate-journal',
            'journal-actor', 'journal-rating', 'journal-comment', 'success-no-row', 'old-changed', 'old-deleted', 'journal-rewritten', 'success-old-row'],
            array_map(static fn ($k) => [$k], $keys));
    }

    #[DataProvider('corruptions')]
    public function test_audit_rejects_unexpected_delta(string $kind): void
    {
        [$b, $a, $s] = $this->sample(); $allowed = [7];
        switch ($kind) {
            case 'source': $a['source']['bookings'] = 'changed'; break;
            case 'unauthorized': $allowed = []; break;
            case 'immutable': $s['P-MAIN']['mutable'] = false; break;
            case 'unknown-booking': $a['reviews'][0]['booking_id'] = 99; break;
            case 'duplicate-id': $a['reviews'][] = $a['reviews'][0]; break;
            case 'duplicate-booking': $a['reviews'][] = [...$a['reviews'][0], 'id' => 11]; break;
            case 'customer': $a['reviews'][0]['customer_id'] = 99; break;
            case 'property': $a['reviews'][0]['property_id'] = 99; break;
            case 'rating': $a['reviews'][0]['rating'] = 1; break;
            case 'comment': $a['reviews'][0]['comment'] = 'Changed'; break;
            case 'invalid-time': $a['reviews'][0]['created_at'] = null; break;
            case 'updated-time': $a['reviews'][0]['updated_at'] = '2026-11-20 04:00:00'; break;
            case 'missing-journal': $a['attempts'] = []; break;
            case 'duplicate-journal': $a['attempts'][] = $a['attempts'][0]; break;
            case 'journal-actor': $a['attempts'][0]['actor_id'] = 99; break;
            case 'journal-rating': $a['attempts'][0]['rating'] = 0; break;
            case 'journal-comment': $a['attempts'][0]['comment'] = 'abcd'; break;
            case 'success-no-row': $a['reviews'] = []; break;
            case 'old-changed': $b = $a; $a['reviews'][0]['comment'] = 'Changed'; break;
            case 'old-deleted': $b = $a; $a['reviews'] = []; break;
            case 'journal-rewritten': $b = $a; $a['attempts'][0]['comment'] = 'Changed'; break;
            case 'success-old-row': $b = $a; $a['attempts'][] = $a['attempts'][0]; break;
        }
        $this->expectException(RuntimeException::class); F::verifyDelta($b, $a, $s, $allowed);
    }

    public function test_customer_response_matches_literal_and_audited_row_without_mapper(): void
    {
        [$b, $a, $s] = $this->sample();
        $data = ['id' => 10, 'booking_id' => 7, 'booking_code' => 'P-MAIN', 'property_id' => 3, 'property_name' => 'Cơ sở QA',
            'room_name' => 'Phòng QA', 'rating' => 5, 'comment' => 'Phòng sạch sẽ', 'created_at' => '2026-11-20T03:04:05.000000Z', 'updated_at' => '2026-11-20T03:04:05.000000Z'];
        F::verifyResponse(['data' => $data], ['bookings' => $s], $a, 7, ['rating' => 5, 'comment' => 'Phòng sạch sẽ']);
        $this->addToAssertionCount(1);
        $data['property_id'] = 99;
        $this->expectException(RuntimeException::class); F::verifyResponse(['data' => $data], ['bookings' => $s], $a, 7);
    }
}
