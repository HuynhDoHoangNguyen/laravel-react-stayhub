<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\AdminPropertyQueryRequest;
use App\DTOs\Catalog\Requests\AdminPropertyDetailQuery;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Catalog\AdminPropertyController;
use App\Mappers\Catalog\AdminPropertyMapper;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Catalog\AdminPropertyQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdminPropertyTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionCookie = null;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC43 requires an isolated SQLite in-memory environment before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC43 tests cannot run with cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('UC43 database isolation must hold before RefreshDatabase.');
        }
        $app['config']->set('sanctum.stateful', ['localhost:5173']);

        return $app;
    }

    private function browser(string $method = 'GET', string $uri = '/api/admin/properties', array $body = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withCredentials();
        if ($this->sessionCookie !== null) {
            $this->withCookie(config('session.cookie'), $this->sessionCookie);
        }
        $response = $this->json($method, $uri, $body, ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
        if ($cookie = $response->getCookie(config('session.cookie'))) {
            $this->sessionCookie = $cookie->getValue();
        }

        return $response;
    }

    private function login(?User $user = null): User
    {
        $user ??= User::factory()->admin()->create();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        return $user;
    }

    private function listing(array $query = []): TestResponse
    {
        return $this->browser('GET', '/api/admin/properties'.($query ? '?'.http_build_query($query) : ''));
    }

    private function detail(int|string $propertyId, array $query = [], array $body = []): TestResponse
    {
        return $this->browser('GET', '/api/admin/properties/'.$propertyId.($query ? '?'.http_build_query($query) : ''), $body);
    }

    public function test_route_is_unique_and_protected_by_all_three_middleware(): void
    {
        $router = app('router');
        $route = $router->getRoutes()->match(Request::create('/api/admin/properties', 'GET'));
        $this->assertSame(AdminPropertyController::class.'@index', $route->getActionName());
        $this->assertSame('admin.properties.index', $route->getName());
        foreach (['auth:sanctum', 'account.active', 'role:ADMIN'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $routes = array_filter($router->getRoutes()->getRoutes(), static fn ($route) => $route->uri() === 'api/admin/properties');
        $this->assertCount(1, $routes);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_guest_is_unauthorized_before_query_validation(): void
    {
        $this->listing(['page' => 'invalid'])->assertUnauthorized();
    }

    public static function otherRoles(): array { return [['HOST'], ['CUSTOMER']]; }

    #[DataProvider('otherRoles')]
    public function test_other_roles_are_denied_before_query_validation(string $role): void
    {
        $this->login(User::factory()->create(['role' => UserRole::from($role)]));
        $this->listing(['page' => 'invalid'])->assertForbidden();
    }

    public static function inactiveStatuses(): array { return [['BLOCKED'], ['INACTIVE']]; }

    #[DataProvider('inactiveStatuses')]
    public function test_existing_session_is_denied_after_admin_account_status_changes(string $status): void
    {
        $admin = $this->login();
        $admin->update(['status' => UserStatus::from($status)]);
        $this->listing(['page' => 'invalid'])->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_all_property_statuses_and_owners_are_visible_with_exact_projection(): void
    {
        $this->freezeSecond();
        $this->login();
        $owner = User::factory()->host()->create();
        $blockedOwner = User::factory()->host()->create(['status' => UserStatus::BLOCKED]);
        $inactiveOwner = User::factory()->host()->create(['status' => UserStatus::INACTIVE]);
        $properties = collect(PropertyStatus::cases())->map(fn ($status, $index) => Property::factory()->create([
            'host_id' => [$owner->id, $blockedOwner->id, $inactiveOwner->id, $owner->id][$index],
            'type' => $index % 2 === 0 ? PropertyType::HOTEL : PropertyType::HOMESTAY,
            'status' => $status,
        ]));
        $response = $this->listing()->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 4)->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)->assertJsonPath('meta.per_page', 10);
        $this->assertSame($properties->pluck('id')->reverse()->values()->all(), array_column($response->json('data'), 'id'));
        foreach ($response->json('data') as $row) {
            $this->assertSame(['id', 'name', 'type', 'address', 'status', 'rooms_count', 'created_at', 'host'], array_keys($row));
            $this->assertSame(['id', 'name', 'email'], array_keys($row['host']));
            $this->assertSame(0, $row['rooms_count']);
            $this->assertSame(now()->toISOString(), $row['created_at']);
        }
        $first = $properties->first();
        $this->assertSame(['id' => $first->id, 'name' => $first->name, 'type' => 'HOTEL',
            'address' => $first->address, 'status' => 'ACTIVE', 'rooms_count' => 0,
            'created_at' => now()->toISOString(), 'host' => ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email]], $response->json('data.3'));
        $this->assertSame(['data', 'links', 'meta'], array_keys($response->json()));
        $this->assertSame(['first', 'last', 'prev', 'next'], array_keys($response->json('links')));
    }

    public static function propertyStatuses(): array { return array_map(static fn ($status) => [$status->value], PropertyStatus::cases()); }

    #[DataProvider('propertyStatuses')]
    public function test_each_status_filter_preserves_all_owner_scope(string $status): void
    {
        $this->login();
        $owner = User::factory()->host()->create();
        foreach (PropertyStatus::cases() as $case) {
            Property::factory()->create(['host_id' => $owner->id, 'status' => $case]);
        }
        Property::factory()->create(['host_id' => User::factory()->host()->create(['status' => UserStatus::BLOCKED])->id, 'status' => $status]);
        $response = $this->listing(['status' => $status])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->assertSame([$status, $status], array_column($response->json('data'), 'status'));
    }

    public function test_room_counts_include_all_room_statuses_and_exclude_other_properties(): void
    {
        $this->login();
        $property = Property::factory()->create();
        $other = Property::factory()->create();
        foreach ([$property, $other] as $target) {
            $type = RoomType::factory()->create(['property_id' => $target->id]);
            foreach ($target->is($property) ? RoomStatus::cases() : [RoomStatus::ACTIVE] as $status) {
                Room::factory()->create(['property_id' => $target->id, 'room_type_id' => $type->id, 'status' => $status]);
            }
        }
        $rows = collect($this->listing()->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(count(RoomStatus::cases()), $rows[$property->id]['rooms_count']);
        $this->assertSame(1, $rows[$other->id]['rooms_count']);
    }

    public function test_pagination_is_stable_and_preserves_filters(): void
    {
        $this->freezeSecond();
        $this->login();
        $properties = Property::factory()->count(12)->create(['host_id' => User::factory()->host()->create()->id, 'status' => PropertyStatus::PENDING]);
        Property::factory()->create(['status' => PropertyStatus::ACTIVE]);
        $properties[0]->forceFill(['created_at' => now()->addDay()])->save();
        $first = $this->listing(['status' => 'PENDING', 'per_page' => 10])->assertOk()
            ->assertJsonCount(10, 'data')->assertJsonPath('data.0.id', $properties[0]->id)
            ->assertJsonPath('meta.total', 12)->assertJsonPath('meta.last_page', 2);
        $second = $this->listing(['status' => 'PENDING', 'page' => 2])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$properties[2]->id, $properties[1]->id], array_column($second->json('data'), 'id'));
        parse_str(parse_url($first->json('links.next'), PHP_URL_QUERY), $next);
        $this->assertEquals(['status' => 'PENDING', 'per_page' => '10', 'page' => '2'], $next);
        $this->listing(['status' => 'PENDING', 'page' => 3])->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 12)->assertJsonPath('meta.current_page', 3);
        foreach ([20, 50] as $size) {
            $this->listing(['per_page' => $size])->assertOk()->assertJsonCount(13, 'data')->assertJsonPath('meta.per_page', $size);
        }
    }

    public function test_empty_list_filter_miss_and_nullable_creation_date(): void
    {
        $this->login();
        $this->listing()->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        Property::factory()->create(['created_at' => null]);
        $this->listing()->assertOk()->assertJsonPath('data.0.created_at', null);
        $this->listing(['status' => 'PENDING'])->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public static function invalidQueries(): array
    {
        return [
            [['status' => 'DELETED'], 'status'], [['status' => ''], 'status'], [['status' => ['ACTIVE']], 'status'],
            [['page' => '0'], 'page'], [['page' => '-1'], 'page'], [['page' => '1.5'], 'page'],
            [['page' => '01'], 'page'], [['page' => '1e2'], 'page'], [['page' => '2147483648'], 'page'],
            [['page' => ['1']], 'page'], [['page' => ''], 'page'],
            [['per_page' => '11'], 'per_page'], [['per_page' => '010'], 'per_page'], [['per_page' => ['10']], 'per_page'],
            [['q' => ['text']], 'q'], [['q' => str_repeat('á', 101)], 'q'],
            [['type' => 'APARTMENT'], 'type'], [['type' => ''], 'type'], [['type' => ['HOTEL']], 'type'],
            [['host_id' => '0'], 'host_id'], [['host_id' => '-1'], 'host_id'], [['host_id' => '01'], 'host_id'],
            [['host_id' => '1.5'], 'host_id'], [['host_id' => '1e2'], 'host_id'], [['host_id' => ['1']], 'host_id'],
            [['host_id' => ''], 'host_id'], [['host_id' => '9007199254740992'], 'host_id'],
            [['sort' => 'name'], 'sort'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_returns_field_errors(array $query, string $field): void
    {
        $this->login();
        $this->listing($query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_get_body_is_rejected_and_monitoring_does_not_write_business_data(): void
    {
        $this->login();
        $property = Property::factory()->create();
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);
        $tables = ['users', 'properties', 'rooms', 'room_types', 'bookings', 'invoices'];
        $before = [];
        foreach ($tables as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $this->browser('GET', '/api/admin/properties', ['status' => 'BLOCKED'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->listing()->assertOk();
        $this->listing(['status' => 'PENDING'])->assertOk();
        $this->listing(['q' => $property->name, 'type' => $property->type->value, 'host_id' => $property->host_id])->assertOk();
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->browser($method, '/api/admin/properties')->assertStatus(405);
        }
    }

    public function test_eager_loading_and_mapping_have_constant_query_count(): void
    {
        $admin = $this->login();
        Property::factory()->create();
        $service = $this->app->make(AdminPropertyQueryService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $single = $service->listing($admin, new AdminPropertyQueryRequest())->toJson();
        $oneQueries = count(DB::getQueryLog());
        $this->assertCount(1, json_decode($single, true)['data']);
        Property::factory()->count(9)->create();
        DB::flushQueryLog();
        $many = $service->listing($admin, new AdminPropertyQueryRequest())->toJson();
        $manyQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(10, json_decode($many, true)['data']);
        $this->assertSame(3, $oneQueries);
        $this->assertSame($oneQueries, $manyQueries);
    }

    public static function searchFields(): array
    {
        return [['name'], ['address'], ['host_name'], ['host_email']];
    }

    private function propertyMatching(string $field, string $term): Property
    {
        $owner = User::factory()->host()->create([
            'name' => $field === 'host_name' ? 'Owner '.$term.' end' : 'Unrelated owner',
            'email' => $field === 'host_email' ? $term.'@example.test' : fake()->unique()->safeEmail(),
        ]);

        return Property::factory()->create([
            'host_id' => $owner->id,
            'name' => $field === 'name' ? 'Property '.$term.' end' : 'Unrelated property',
            'address' => $field === 'address' ? 'Address '.$term.' end' : 'Unrelated address',
        ]);
    }

    #[DataProvider('searchFields')]
    public function test_search_matches_each_supported_field_and_trims_input(string $field): void
    {
        $this->login();
        $target = $this->propertyMatching($field, 'needle');
        Property::factory()->create(['name' => 'Other property', 'address' => 'Other address']);
        $this->listing(['q' => '  needle  '])->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
    }

    public static function literalSearches(): array
    {
        $cases = [];
        foreach (['%', '_', '!', "' OR 1=1 --", '\\'] as $term) {
            foreach (['name', 'address', 'host_name', 'host_email'] as $field) $cases[] = [$field, $term];
        }

        return $cases;
    }

    #[DataProvider('literalSearches')]
    public function test_search_special_characters_remain_literal_in_all_fields(string $field, string $term): void
    {
        $this->login();
        $target = $this->propertyMatching($field, $term);
        Property::factory()->create(['name' => 'Other property', 'address' => 'Other address']);
        $this->listing(['q' => $term])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
    }

    public function test_search_or_branches_cannot_escape_type_status_or_host_filters(): void
    {
        $this->login();
        $owner = User::factory()->host()->create(['name' => 'needle owner', 'status' => UserStatus::BLOCKED]);
        $other = User::factory()->host()->create(['email' => 'needle@example.test']);
        $base = ['host_id' => $owner->id, 'type' => PropertyType::HOMESTAY, 'status' => PropertyStatus::PENDING];
        $target = Property::factory()->create($base);
        Property::factory()->create(array_replace($base, ['type' => PropertyType::HOTEL, 'name' => 'needle property']));
        Property::factory()->create(array_replace($base, ['status' => PropertyStatus::ACTIVE, 'address' => 'needle address']));
        Property::factory()->create(array_replace($base, ['host_id' => $other->id]));
        $this->listing(['q' => 'needle', 'type' => 'HOMESTAY', 'status' => 'PENDING', 'host_id' => $owner->id])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
        $this->listing(['q' => 'not-found', 'host_id' => $owner->id])->assertOk()->assertJsonPath('data', []);
    }

    public function test_type_and_host_filters_work_alone_and_unknown_host_returns_empty(): void
    {
        $this->login();
        $owner = User::factory()->host()->create();
        $hotel = Property::factory()->create(['host_id' => $owner->id, 'type' => PropertyType::HOTEL]);
        $homestay = Property::factory()->create(['host_id' => $owner->id, 'type' => PropertyType::HOMESTAY]);
        Property::factory()->create(['type' => PropertyType::HOTEL]);
        $this->listing(['type' => 'HOTEL'])->assertOk()->assertJsonCount(2, 'data');
        $this->listing(['type' => 'HOMESTAY'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $homestay->id);
        $this->listing(['host_id' => $owner->id])->assertOk()->assertJsonCount(2, 'data');
        $this->listing(['host_id' => $owner->id, 'type' => 'HOTEL'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $hotel->id);
        $this->listing(['host_id' => User::factory()->host()->create()->id])->assertOk()->assertJsonPath('data', []);
        $this->listing(['host_id' => '9007199254740991'])->assertOk()->assertJsonPath('data', []);
    }

    public function test_search_pagination_links_keep_all_filters_and_order(): void
    {
        $this->freezeSecond();
        $this->login();
        $owner = User::factory()->host()->create(['name' => 'pagination needle']);
        $properties = Property::factory()->count(12)->create([
            'host_id' => $owner->id, 'type' => PropertyType::HOMESTAY, 'status' => PropertyStatus::PENDING,
        ]);
        Property::factory()->create(['type' => PropertyType::HOMESTAY, 'status' => PropertyStatus::PENDING]);
        $query = ['q' => 'needle', 'type' => 'HOMESTAY', 'status' => 'PENDING', 'host_id' => (string) $owner->id, 'per_page' => '10'];
        $first = $this->listing($query)->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 12);
        parse_str(parse_url($first->json('links.next'), PHP_URL_QUERY), $next);
        $this->assertSame($query + ['page' => '2'], $next);
        $second = $this->listing($next)->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$properties[1]->id, $properties[0]->id], array_column($second->json('data'), 'id'));
    }

    public function test_empty_and_max_length_search_keep_default_behavior_and_typed_input(): void
    {
        $admin = $this->login();
        $target = Property::factory()->create(['name' => str_repeat('á', 100), 'type' => PropertyType::HOTEL]);
        foreach (['', '   ', str_repeat('á', 100)] as $term) {
            $this->listing(['q' => $term])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
        }
        $input = new AdminPropertyQueryRequest(search: str_repeat('á', 100), type: PropertyType::HOTEL, hostId: $target->host_id);
        $result = $this->app->make(AdminPropertyQueryService::class)->listing($admin, $input);
        $this->assertSame([$target->id], $result->getCollection()->pluck('id')->all());
    }

    public function test_filtered_search_does_not_add_per_row_queries(): void
    {
        $admin = $this->login();
        $owner = User::factory()->host()->create(['name' => 'needle owner']);
        Property::factory()->count(10)->create(['host_id' => $owner->id, 'type' => PropertyType::HOTEL]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = $this->app->make(AdminPropertyQueryService::class)->listing($admin,
            new AdminPropertyQueryRequest(search: 'needle', type: PropertyType::HOTEL, hostId: $owner->id));
        $this->assertCount(10, json_decode($result->toJson(), true)['data']);
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public static function deniedActors(): array
    {
        return [['HOST', 'ACTIVE'], ['CUSTOMER', 'ACTIVE'], ['ADMIN', 'BLOCKED'], ['ADMIN', 'INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_service_denies_unauthorized_actor_before_any_query(string $role, string $status): void
    {
        $actor = User::factory()->make(['role' => UserRole::from($role), 'status' => UserStatus::from($status)]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->app->make(AdminPropertyQueryService::class)->listing($actor, new AdminPropertyQueryRequest());
            $this->fail('Unauthorized actor was accepted.');
        } catch (AuthorizationException) {
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_mapper_requires_loaded_facts_instead_of_faking_zero_or_lazy_loading(): void
    {
        $property = Property::factory()->create();
        $mapper = $this->app->make(AdminPropertyMapper::class);
        foreach ([Property::find($property->id), Property::with('host')->find($property->id)] as $incomplete) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $mapper->toListItem($incomplete);
                $this->fail('Incomplete projection was accepted.');
            } catch (LogicException) {
                $this->assertSame([], DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
        }
    }

    public function test_detail_route_is_unique_and_protected_by_all_three_middleware(): void
    {
        $routes = app('router')->getRoutes();
        $route = $routes->match(Request::create('/api/admin/properties/123', 'GET'));
        $this->assertSame(AdminPropertyController::class.'@show', $route->getActionName());
        $this->assertSame('admin.properties.show', $route->getName());
        foreach (['auth:sanctum', 'account.active', 'role:ADMIN'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $this->assertCount(1, array_filter($routes->getRoutes(), static fn ($route) => $route->uri() === 'api/admin/properties/{property}'));
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_detail_guest_is_denied_before_id_validation(): void
    {
        $this->detail('invalid')->assertUnauthorized();
    }

    #[DataProvider('otherRoles')]
    public function test_detail_other_roles_are_denied_before_id_validation(string $role): void
    {
        $this->login(User::factory()->create(['role' => UserRole::from($role)]));
        $this->detail('invalid')->assertForbidden();
    }

    #[DataProvider('inactiveStatuses')]
    public function test_detail_rejects_an_existing_admin_session_after_account_status_changes(string $status): void
    {
        $admin = $this->login();
        $property = Property::factory()->create();
        $admin->update(['status' => UserStatus::from($status)]);
        $this->detail($property->id)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    #[DataProvider('propertyStatuses')]
    public function test_detail_all_statuses_have_exact_projection_even_with_a_blocked_owner(string $status): void
    {
        $this->freezeSecond();
        $this->login();
        $owner = User::factory()->host()->create(['status' => UserStatus::BLOCKED]);
        $property = Property::factory()->create(['host_id' => $owner->id, 'type' => PropertyType::HOMESTAY,
            'status' => PropertyStatus::from($status), 'phone' => '02363551111',
            'description' => "Dòng một\n<script>example</script>", 'check_in_time' => '15:30:00', 'check_out_time' => '11:45:00']);
        $response = $this->detail($property->id)->assertOk();
        $this->assertSame(['data' => ['id' => $property->id, 'name' => $property->name, 'type' => 'HOMESTAY',
            'address' => $property->address, 'status' => $status, 'rooms_count' => 0, 'created_at' => now()->toISOString(),
            'host' => ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email],
            'description' => "Dòng một\n<script>example</script>", 'phone' => '02363551111',
            'check_in_time' => '15:30:00', 'check_out_time' => '11:45:00', 'room_types_count' => 0,
            'updated_at' => now()->toISOString()]], $response->json());
    }

    public function test_detail_preserves_nullable_and_empty_values_without_affecting_host_visibility(): void
    {
        $this->login();
        $owner = User::factory()->host()->create(['status' => UserStatus::INACTIVE]);
        $property = Property::factory()->create(['host_id' => $owner->id, 'description' => null, 'phone' => null,
            'created_at' => null, 'updated_at' => null, 'check_in_time' => '00:00:00', 'check_out_time' => '23:59:59']);
        $this->detail($property->id)->assertOk()->assertJsonPath('data.description', null)->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.created_at', null)->assertJsonPath('data.updated_at', null)
            ->assertJsonPath('data.check_in_time', '00:00:00')->assertJsonPath('data.check_out_time', '23:59:59');
        $property->update(['description' => '', 'phone' => '0']);
        $this->detail($property->id)->assertOk()->assertJsonPath('data.description', '')->assertJsonPath('data.phone', '0');
    }

    public function test_detail_counts_are_scoped_fresh_and_do_not_lazy_load(): void
    {
        $admin = $this->login();
        $property = Property::factory()->create();
        $other = Property::factory()->create();
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        foreach (RoomStatus::cases() as $status) {
            Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id, 'status' => $status]);
        }
        $otherType = RoomType::factory()->create(['property_id' => $other->id]);
        Room::factory()->create(['property_id' => $other->id, 'room_type_id' => $otherType->id]);
        $service = $this->app->make(AdminPropertyQueryService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = $service->detail($admin, new AdminPropertyDetailQuery($property->id))->jsonSerialize();
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(count(RoomStatus::cases()), $result['rooms_count']);
        $this->assertSame(1, $result['room_types_count']);
        $newType = RoomType::factory()->create(['property_id' => $property->id]);
        Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $newType->id]);
        $this->detail($property->id)->assertOk()->assertJsonPath('data.rooms_count', count(RoomStatus::cases()) + 1)
            ->assertJsonPath('data.room_types_count', 2);
    }

    public static function invalidDetailIds(): array
    {
        return [['0'], ['-1'], ['01'], ['1.5'], ['1e2'], ['invalid'], ['9007199254740992'], ['999999999999999999999999999'], ['%201%20']];
    }

    #[DataProvider('invalidDetailIds')]
    public function test_detail_rejects_invalid_id_before_lookup(string $id): void
    {
        $this->login();
        $this->detail($id)->assertUnprocessable()->assertJsonValidationErrors('property');
    }

    public function test_detail_missing_property_is_404_but_query_and_body_are_rejected_first(): void
    {
        $this->login();
        $this->detail('9007199254740991')->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        $this->detail('9007199254740991', ['q' => 'ignored'])->assertUnprocessable()->assertJsonValidationErrors('q');
        $property = Property::factory()->create();
        $this->detail($property->id, [], ['status' => 'BLOCKED'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $property->delete();
        $this->detail($property->id)->assertNotFound();
    }

    #[DataProvider('deniedActors')]
    public function test_detail_service_enforces_permissions_before_any_query(string $role, string $status): void
    {
        $actor = User::factory()->make(['role' => UserRole::from($role), 'status' => UserStatus::from($status)]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->app->make(AdminPropertyQueryService::class)->detail($actor, new AdminPropertyDetailQuery(1));
            $this->fail('Unauthorized actor was accepted.');
        } catch (AuthorizationException) {
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_detail_requires_loaded_projection_and_never_lazy_loads_missing_counts(): void
    {
        $property = Property::factory()->create();
        $mapper = $this->app->make(AdminPropertyMapper::class);
        foreach ([Property::find($property->id), Property::with('host')->withCount('rooms')->find($property->id)] as $incomplete) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $mapper->toDetail($incomplete);
                $this->fail('Incomplete detail projection was accepted.');
            } catch (LogicException) {
                $this->assertSame([], DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
        }
    }

    public function test_detail_does_not_write_business_data_or_register_mutations(): void
    {
        $this->login();
        $property = Property::factory()->create();
        $before = [];
        foreach (['users', 'properties', 'rooms', 'room_types', 'bookings', 'invoices'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->detail($property->id)->assertOk();
        $this->detail($property->id, ['status' => 'ACTIVE'])->assertUnprocessable();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->browser($method, '/api/admin/properties/'.$property->id)->assertStatus(405);
        }
        foreach ($before as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }
}
