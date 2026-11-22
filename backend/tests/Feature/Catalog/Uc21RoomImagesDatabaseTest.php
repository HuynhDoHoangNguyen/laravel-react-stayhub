<?php

namespace Tests\Feature\Catalog;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Uc21MySqlEnvironment;
use Tests\Support\Uc21MySqlTestCase;

class Uc21RoomImagesDatabaseTest extends Uc21MySqlTestCase
{
    public function test_owned_schema_migrations_committed_fixtures_and_storage(): void
    {
        $db = DB::connection();
        $this->assertSame([], $db->getSchemaBuilder()->getTables());
        Uc21MySqlEnvironment::assertApplication($this->app, $this->manifest);
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
        $engine = $db->selectOne("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_images'");
        $this->assertSame('InnoDB', $engine->engine);
        $host = User::factory()->create(['role' => 'HOST', 'status' => 'ACTIVE']);
        $property = Property::create(['host_id' => $host->id, 'name' => 'UC21 isolated fixture', 'type' => 'HOTEL', 'address' => 'Fixture', 'status' => 'ACTIVE']);
        $type = $property->roomTypes()->create(['name' => 'Fixture']);
        $room = $property->rooms()->create(['room_type_id' => $type->id, 'room_number' => 'QA01', 'name' => 'Fixture', 'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE']);
        $this->assertSame(0, $db->transactionLevel());
        config(['database.connections.uc21_observer' => config('database.connections.mysql')]);
        $observer = DB::connection('uc21_observer');
        $this->assertNotSame($db->selectOne('SELECT CONNECTION_ID() AS id')->id, $observer->selectOne('SELECT CONNECTION_ID() AS id')->id);
        $this->assertSame(1, $observer->table('rooms')->where('id', $room->id)->count());
        $this->actingAs($host)->getJson('/api/host/rooms/'.$room->id.'/images')->assertOk()->assertJsonPath('data', []);
        Storage::disk('public')->put('rooms/smoke.txt', 'owned fixture');
        $this->assertSame('owned fixture', Storage::disk('public')->get('rooms/smoke.txt'));
        DB::purge('uc21_observer');
        if (getenv('STAYHUB_UC21_INJECT_FAILURE') === '1') $this->fail('Injected UC21 test failure after owned fixture writes.');
    }
}
