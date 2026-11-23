<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPropertyListApiTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/admin/properties';

    public function test_guest_and_disallowed_accounts_are_rejected_before_validation(): void
    {
        $url = self::PATH.'?page=0';
        $this->getJson($url)->assertUnauthorized();

        foreach ([UserRole::HOST, UserRole::CUSTOMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson($url)->assertForbidden();
        }

        foreach ([UserStatus::BLOCKED, UserStatus::INACTIVE] as $status) {
            $this->actingAs(User::factory()->admin()->create(['status' => $status]))
                ->getJson($url)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status->value);
        }
    }

    public function test_rejects_invalid_query_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $query = http_build_query(['q' => str_repeat('ệ', 101), 'status' => 'pending', 'page' => '01', 'per_page' => '51']);
        $this->getJson(self::PATH.'?'.$query)->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'status', 'page', 'per_page']);

        $this->getJson(self::PATH.'?q[]=x&status[]=ACTIVE&page=2147483648&per_page[]=10')
            ->assertUnprocessable()->assertJsonValidationErrors(['q', 'status', 'page', 'per_page']);
        $this->getJson(self::PATH.'?page=&per_page=')->assertUnprocessable()->assertJsonValidationErrors(['page', 'per_page']);
    }

    public function test_returns_only_list_fields_with_stable_pagination_across_all_statuses(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $host = User::factory()->host()->create(['name' => 'Chủ cơ sở demo']);
        $properties = collect(PropertyStatus::cases())->map(fn (PropertyStatus $status) => Property::factory()->create([
            'host_id' => $host->id, 'name' => 'Cơ sở '.$status->value, 'address' => '12 Bạch Đằng',
            'status' => $status, 'type' => PropertyType::HOTEL, 'created_at' => '2026-11-20 00:00:00',
        ]));
        $newest = Property::factory()->create(['created_at' => '2026-11-21 00:00:00']);
        Property::factory()->count(15)->create(['created_at' => '2026-11-19 12:00:00']);
        // An older, higher ID must stay below rows with a more recent creation time.
        $oldest = Property::factory()->create(['created_at' => '2026-11-19 00:00:00']);

        $first = $this->getJson(self::PATH.'?per_page=10')->assertOk()->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.total', 21)
            ->assertJsonPath('data.0.id', $newest->id);
        $this->assertSame(['data', 'links', 'meta'], array_keys($first->json()));
        $this->assertSame([
            'id' => $properties[3]->id, 'name' => 'Cơ sở PENDING', 'type' => 'HOTEL',
            'address' => '12 Bạch Đằng', 'status' => 'PENDING', 'rooms_count' => 0,
            'created_at' => $properties[3]->created_at->toISOString(),
            'host' => ['id' => $host->id, 'name' => 'Chủ cơ sở demo', 'email' => $host->email],
        ], $first->json('data.1'));
        $first->assertJsonPath('data.2.status', 'BLOCKED')->assertJsonPath('data.3.status', 'INACTIVE')
            ->assertJsonPath('data.4.status', 'ACTIVE');

        $second = $this->getJson($first->json('links.next'))->assertOk()->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(10, 'data');
        $this->getJson($second->json('links.next'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $oldest->id);
        $this->getJson(self::PATH.'?per_page=10&page=4')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.from', null)->assertJsonPath('meta.to', null);
    }

    public function test_name_and_address_search_are_literal_and_combine_with_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $active = Property::factory()->create(['name' => 'Sông Hàn !%_', 'address' => 'Đà Nẵng', 'status' => PropertyStatus::ACTIVE]);
        $pending = Property::factory()->create(['name' => 'Nhà Vườn', 'address' => '12 Bạch Đằng !%_', 'status' => PropertyStatus::PENDING]);
        Property::factory()->create(['name' => 'Sông Hàn khác', 'address' => '14 Bạch Đằng', 'status' => PropertyStatus::ACTIVE]);

        $this->getJson(self::PATH.'?'.http_build_query(['q' => ' Sông Hàn ']))
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 10);
        $byAddress = $this->getJson(self::PATH.'?'.http_build_query(['q' => 'Bạch Đằng', 'status' => 'PENDING', 'per_page' => 10]))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $pending->id);
        parse_str((string) parse_url($byAddress->json('links.first'), PHP_URL_QUERY), $link);
        $this->assertEquals(['q' => 'Bạch Đằng', 'status' => 'PENDING', 'per_page' => '10', 'page' => '1'], $link);
        $this->getJson(self::PATH.'?'.http_build_query(['q' => '!%_', 'status' => 'ACTIVE']))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $active->id);
        $this->getJson(self::PATH.'?'.http_build_query(['q' => 'Sông Hàn', 'status' => 'PENDING']))
            ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
    }
}
