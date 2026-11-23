<?php

namespace Tests\Unit;

use App\DTOs\Dashboard\Responses\HostDashboardSnapshotResponse;
use App\Exceptions\AppException;
use App\Models\User;
use App\Repositories\Dashboard\HostDashboardRepository;
use App\Services\Dashboard\HostDashboardQueryService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostDashboardSnapshotContractTest extends TestCase
{
    private function facts(array $rows = []): object
    {
        return (object) ['properties_count' => '2', 'rooms_count' => '3', 'pending_bookings_count' => '2',
            'in_progress_maintenances_count' => '2', 'recent_json' => json_encode($rows, JSON_THROW_ON_ERROR)];
    }

    private function row(string $id): array
    {
        return ['id' => $id, 'booking_code' => 'BK-'.$id, 'status' => 'PENDING',
            'created_at_epoch' => '1795237200', 'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-02',
            'property_id' => '1', 'property_name' => '<script>text</script>', 'room_id' => '2', 'room_name' => 'Phòng',
            'customer_email' => 'private@example.test', 'note' => 'private'];
    }

    public static function denied(): array
    {
        return [['ADMIN', 'ACTIVE', '1'], ['CUSTOMER', 'ACTIVE', '2'], ['HOST', 'BLOCKED', '3'],
            ['HOST', 'INACTIVE', '4'], ['HOST', 'ACTIVE', null]];
    }

    #[DataProvider('denied')]
    public function test_denied_actor_never_reaches_repository(string $role, string $status, ?string $id): void
    {
        $repo = $this->createMock(HostDashboardRepository::class);
        $repo->expects($this->never())->method('snapshot');
        $actor = new User(['role' => $role, 'status' => $status]);
        $actor->id = $id;
        try {
            (new HostDashboardQueryService($repo))->getSnapshot($actor);
            $this->fail('Unauthorized actor accepted.');
        } catch (AppException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('HOST_DASHBOARD_FORBIDDEN', $e->errorCode);
        }
    }

    public function test_service_passes_server_identity_and_single_utc_cutoff(): void
    {
        CarbonImmutable::setTestNow('2026-11-21T05:00:00.123456Z');
        try {
            $repo = $this->createMock(HostDashboardRepository::class);
            $repo->expects($this->once())->method('snapshot')->with('42', $this->callback(
                fn ($time) => $time->format('Y-m-d H:i:s.uP') === '2026-11-21 05:00:00.123456+00:00'
            ))->willReturn($this->facts());
            $actor = new User(['role' => 'HOST', 'status' => 'ACTIVE']);
            $actor->id = 42;
            $data = (new HostDashboardQueryService($repo))->getSnapshot($actor)->jsonSerialize();
            $this->assertSame('2026-11-21T05:00:00.123456Z', $data['generated_at']);
            $this->assertSame('Asia/Ho_Chi_Minh', $data['report_timezone']);
        } finally { CarbonImmutable::setTestNow(); }
    }

    public function test_projection_restores_order_and_preserves_large_ids_without_pii(): void
    {
        $dto = new HostDashboardSnapshotResponse(new DateTimeImmutable('2026-11-21T05:00:00Z'),
            $this->facts([$this->row('9007199254740992'), $this->row('18446744073709551615'), $this->row('9')]));
        $data = $dto->jsonSerialize();
        $this->assertSame(['18446744073709551615', '9007199254740992', '9'], array_column($data['recent_bookings'], 'id'));
        $this->assertSame(['scope', 'snapshot', 'recent_bookings', 'generated_at', 'report_timezone'], array_keys($data));
        $this->assertSame(['id', 'booking_code', 'status', 'created_at', 'check_in_date', 'check_out_date', 'property', 'room'], array_keys($data['recent_bookings'][0]));
        $this->assertSame(['id', 'name'], array_keys($data['recent_bookings'][0]['property']));
        $this->assertSame(['id', 'name'], array_keys($data['recent_bookings'][0]['room']));
        $this->assertSame(3, $data['snapshot']['rooms_count']);
        $this->assertStringNotContainsString('private', json_encode($dto, JSON_THROW_ON_ERROR));
        $this->assertSame('<script>text</script>', $data['recent_bookings'][0]['property']['name']);
    }

    public static function invalidFacts(): array
    {
        return [['properties_count', -1], ['rooms_count', '01'], ['pending_bookings_count', 1.0],
            ['rooms_count', '9007199254740992'], ['in_progress_maintenances_count', null],
            ['recent_json', '{}'], ['recent_json', 'null'], ['recent_json', '[[]]'], ['recent_json', '[']];
    }

    #[DataProvider('invalidFacts')]
    public function test_malformed_aggregate_fails_instead_of_becoming_empty(string $key, mixed $value): void
    {
        $facts = $this->facts(); $facts->$key = $value;
        $this->expectException($key === 'recent_json' && $value === '[' ? \JsonException::class : \UnexpectedValueException::class);
        new HostDashboardSnapshotResponse(new DateTimeImmutable('2026-11-21T05:00:00Z'), $facts);
    }

    public static function invalidRows(): array
    {
        return [['id', 1], ['id', '01'], ['status', 'UNKNOWN'], ['created_at_epoch', '1795237201'],
            ['created_at_epoch', '1.5'], ['check_in_date', '2026-02-30'], ['property_id', null], ['room_name', []]];
    }

    #[DataProvider('invalidRows')]
    public function test_invalid_recent_values_are_rejected(string $key, mixed $value): void
    {
        $row = $this->row('1'); $row[$key] = $value;
        $this->expectException(\UnexpectedValueException::class);
        new HostDashboardSnapshotResponse(new DateTimeImmutable('2026-11-21T05:00:00Z'), $this->facts([$row]));
    }

    public function test_duplicate_and_over_limit_recent_sets_are_rejected(): void
    {
        foreach ([[$this->row('1'), $this->row('1')], array_map(fn ($i) => $this->row((string) $i), range(1, 6))] as $rows) {
            try {
                new HostDashboardSnapshotResponse(new DateTimeImmutable('2026-11-21T05:00:00Z'), $this->facts($rows));
                $this->fail('Invalid recent set accepted.');
            } catch (\UnexpectedValueException) { $this->addToAssertionCount(1); }
        }
    }
}
