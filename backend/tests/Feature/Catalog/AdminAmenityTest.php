<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\AdminAmenityDetailQuery;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Catalog\AdminAmenityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class AdminAmenityTest extends TestCase
{
    use RefreshDatabase;

    private const LIST = '/api/admin/amenities';

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('UC46 feature tests require SQLite in-memory.');
        }
        parent::beforeRefreshingDatabase();
    }

    private function editable(array $row, array $changes): array
    {
        return array_replace(array_intersect_key($row, array_flip(['name', 'icon', 'description', 'status', 'version'])), $changes);
    }

    public function test_guest_is_rejected_before_validation(): void
    {
        $this->getJson(self::LIST.'?page=invalid')->assertUnauthorized();
        $this->getJson(self::LIST.'/invalid')->assertUnauthorized();
        $this->postJson(self::LIST, [])->assertUnauthorized();
        $this->putJson(self::LIST.'/invalid', [])->assertUnauthorized();
    }

    public static function deniedActors(): array
    {
        return [
            ['HOST', 'ACTIVE', null], ['CUSTOMER', 'ACTIVE', null],
            ['ADMIN', 'BLOCKED', 'ACCOUNT_BLOCKED'], ['ADMIN', 'INACTIVE', 'ACCOUNT_INACTIVE'],
        ];
    }

    #[DataProvider('deniedActors')]
    public function test_wrong_role_or_inactive_actor_is_denied_on_all_actions(string $role, string $status, ?string $code): void
    {
        $actor = User::factory()->create(['role' => $role, 'status' => $status]);
        foreach ([['GET', self::LIST], ['GET', self::LIST.'/01'], ['POST', self::LIST], ['PUT', self::LIST.'/01']] as [$method, $uri]) {
            $response = $this->actingAs($actor)->json($method, $uri)->assertForbidden();
            if ($code !== null) {
                $response->assertJsonPath('code', $code);
            }
        }
        $this->expectException(AuthorizationException::class);
        $this->app->make(AdminAmenityService::class)->detail($actor, new AdminAmenityDetailQuery(1));
    }

    public function test_search_status_and_pagination_and_literal_characters(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(self::LIST)->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
        $first = Amenity::factory()->create(['name' => 'A %_! literal', 'status' => false]);
        $second = Amenity::factory()->create(['name' => 'A ordinary', 'status' => true]);
        foreach (['%', '_', '!'] as $query) {
            $this->getJson(self::LIST.'?'.http_build_query(['q' => ' '.$query.' ', 'status' => 'inactive']))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        }
        Amenity::factory()->count(10)->sequence(fn ($sequence) =>
            ['name' => sprintf('B %02d', $sequence->index), 'status' => true])->create();
        $this->getJson(self::LIST.'?status=active')
            ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('meta.total', 11)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('links.next', url(self::LIST).'?status=active&page=2');
        $this->getJson(self::LIST.'?status=active&page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'B 09');
    }

    public function test_create_update_stale_version_and_response_allowlist(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $created = $this->postJson(self::LIST, ['name' => '  Bãi đỗ xe  ', 'icon' => 'bãi-đỗ-xe', 'description' => ' '])
            ->assertCreated()->assertJsonPath('data.name', 'Bãi đỗ xe')->assertJsonPath('data.description', null)
            ->assertJsonPath('data.icon', 'bãi-đỗ-xe')->assertJsonPath('data.status', true)
            ->assertJsonPath('data.rooms_count', 0)->json('data');
        $this->assertSame(['id', 'name', 'icon', 'description', 'status', 'rooms_count', 'created_at', 'updated_at', 'version'],
            array_keys($created));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $created['version']);
        $uri = self::LIST.'/'.$created['id'];
        $this->getJson($uri)->assertOk()->assertExactJson(['data' => $created]);

        $updated = $this->putJson($uri, $this->editable($created, ['description' => "Có mái che.\nMở 24 giờ."]))
            ->assertOk()->assertJsonPath('data.description', "Có mái che.\nMở 24 giờ.")->json('data');
        $this->assertNotSame($created['version'], $updated['version']);
        $this->putJson($uri, $this->editable($created, ['status' => false]))->assertConflict()
            ->assertJsonPath('code', 'ADMIN_AMENITY_STALE');
        $this->putJson($uri, $this->editable($updated, []))->assertOk()->assertExactJson(['data' => $updated]);
        $this->deleteJson($uri)->assertMethodNotAllowed();
    }

    public function test_duplicate_policy_preserves_legacy_pairs_and_allows_same_name_update(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $legacyIcon = '/old/asset.svg';
        $legacyDescription = str_repeat('x', 2001);
        $legacy = Amenity::factory()->create(['name' => 'Tên cũ', 'icon' => $legacyIcon, 'description' => $legacyDescription]);
        Amenity::factory()->create(['name' => 'Tên cũ', 'status' => false]);
        $other = Amenity::factory()->create(['name' => 'Tên khác']);
        $uri = self::LIST.'/'.$legacy->id;
        $input = $this->getJson($uri)->assertOk()->json('data');

        $this->postJson(self::LIST, ['name' => ' Tên cũ '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $otherInput = $this->getJson(self::LIST.'/'.$other->id)->assertOk()->json('data');
        $this->putJson(self::LIST.'/'.$other->id, $this->editable($otherInput, ['name' => 'Tên cũ']))->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->putJson($uri, $this->editable($input, ['icon' => '/different.svg']))->assertUnprocessable()
            ->assertJsonValidationErrors('icon');
        $this->putJson($uri, $this->editable($input, ['status' => false]))->assertOk()
            ->assertJsonPath('data.icon', $legacyIcon)->assertJsonPath('data.description', $legacyDescription)
            ->assertJsonPath('data.status', false);
        $this->assertDatabaseCount('amenities', 3);
    }

    public function test_validation_details_and_unknown_ids(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(self::LIST.'?page=01&status=ACT')->assertUnprocessable()->assertJsonValidationErrors(['page', 'status']);
        $this->getJson(self::LIST.'/01')->assertUnprocessable()->assertJsonValidationErrors('amenity');
        $this->getJson(self::LIST.'/99999?status=active')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson(self::LIST.'/99999')->assertNotFound();
        $this->postJson(self::LIST, ['name' => 'x', 'status' => 'false', 'id' => 42])
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'id']);
        $this->postJson(self::LIST, ['name' => 'x', 'icon' => 'https://example.test/icon', 'description' => str_repeat('á', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['icon', 'description']);
        $this->postJson(self::LIST.'?status=active', ['name' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson(self::LIST.'/99999', ['name' => 'x', 'status' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['icon', 'description', 'status', 'version']);
        $this->assertDatabaseCount('amenities', 0);
    }

    public function test_toggle_keeps_pivots_and_catalog_respects_new_status(): void
    {
        $admin = User::factory()->admin()->create();
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => 'ACTIVE']);
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id, 'room_number' => '01', 'status' => 'ACTIVE']);
        $otherRoom = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id, 'room_number' => '02', 'status' => 'ACTIVE']);
        $amenity = Amenity::factory()->create(['name' => 'Hồ bơi', 'status' => true]);
        $room->amenities()->attach($amenity);
        $otherRoom->amenities()->attach($amenity);
        $uri = self::LIST.'/'.$amenity->id;
        $input = $this->actingAs($admin)->getJson($uri)->assertOk()->assertJsonPath('data.rooms_count', 2)->json('data');
        $disabled = $this->putJson($uri, $this->editable($input, ['status' => false]))->assertOk()
            ->assertJsonPath('data.rooms_count', 2)->json('data');
        $this->getJson('/api/public/rooms/filter-options')->assertOk()->assertJsonCount(0, 'data.amenities');
        $this->getJson('/api/public/rooms/'.$room->id)->assertOk()->assertJsonCount(0, 'data.amenities');
        $hostUri = '/api/host/rooms/'.$room->id.'/amenities';
        $this->actingAs($host)->getJson($hostUri)->assertOk()->assertJsonPath('data.assigned.0.status', false);
        $this->putJson($hostUri.'/'.$amenity->id)->assertUnprocessable()->assertJsonValidationErrors('amenity_id');
        $this->deleteJson($hostUri.'/'.$amenity->id)->assertOk();
        $this->actingAs($admin)->putJson($uri, $this->editable($disabled, ['status' => true]))->assertOk()
            ->assertJsonPath('data.rooms_count', 1);
        $this->getJson('/api/public/rooms/filter-options')->assertOk()->assertJsonPath('data.amenities.0.id', $amenity->id);
        $this->assertDatabaseMissing('room_amenities', ['room_id' => $room->id, 'amenity_id' => $amenity->id]);
        $this->assertDatabaseHas('room_amenities', ['room_id' => $otherRoom->id, 'amenity_id' => $amenity->id]);
    }
}
