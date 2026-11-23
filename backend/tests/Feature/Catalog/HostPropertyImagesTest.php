<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\UploadHostPropertyImageRequest;
use App\DTOs\Catalog\Requests\HostPropertyImageRequest;
use App\DTOs\Catalog\Responses\HostPropertyImageResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\HostPropertyImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase;
use App\Exceptions\AppException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class HostPropertyImagesTest extends TestCase
{
    use RefreshDatabase;

    // Upload deliberately owns a real root transaction, never the PHPUnit wrapper.
    public function beginDatabaseTransaction() {}

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Host property tests require isolated in-memory testing configuration.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Cached configuration is unsafe for Host property tests.');
        }
        $driver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        $sqlite = $driver === 'sqlite' && $database === ':memory:';
        $mysql = $driver === 'mysql' && is_string($database)
            && $database === getenv('STAYHUB_PROPERTY_IMAGE_TEST_DATABASE')
            && preg_match('/\Astayhub_mgmt06a_test_[a-f0-9]{32}\z/', $database) === 1;
        if (!$sqlite && !$mysql) {
            throw new RuntimeException('Host property tests require an explicitly isolated test database.');
        }
        $app = parent::createApplication();
        $config = $app['config'];
        $connection = $config->get("database.connections.{$driver}");
        if ($config->get('database.default') !== $driver
            || ($connection['database'] ?? null) !== $database
            || !empty($connection['url'])
            || ($mysql && (($connection['host'] ?? null) !== '127.0.0.1' || !empty($connection['unix_socket'])))
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array') {
            throw new RuntimeException('Effective Host property database configuration is unsafe.');
        }
        if ($mysql && $app['db']->connection()->selectOne('SELECT DATABASE() AS selected_database')->selected_database !== $database) {
            throw new RuntimeException('Selected MySQL database is not the owned test schema.');
        }

        return $app;
    }

    private function image(string $kind = 'png'): UploadedFile
    {
        $content = match ($kind) {
            'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQSTkBAAHQAUHAZ3dkAAAAAElFTkSuQmCC',
            'jpg' => '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD8qqKKKAP/2Q==',
            'webp' => 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA',
            'wide' => 'iVBORw0KGgoAAAANSUhEUgAAEAEAAAABCAIAAAA+gZcVAAAAKElEQVR4nO3CQREAAAgDIEMsomGNZY094Jjsqaqqqqqqqqqqqqqq/R8W6QJtfiZhlAAAAABJRU5ErkJggg==',
            'edge' => 'iVBORw0KGgoAAAANSUhEUgAAEAAAAAABCAIAAADRQ/wrAAAAKElEQVR4nO3CQREAAAgDIEMsomGNZY094Jjsqaqqqqqqqqqqqqqq5R8RlgEtDijk5QAAAABJRU5ErkJggg==',
        };
        return UploadedFile::fake()->createWithContent('untrusted-name.'.($kind === 'wide' || $kind === 'edge' ? 'png' : $kind), base64_decode($content));
    }

    private function sizedImage(int $bytes, string $kind = 'png'): UploadedFile
    {
        $fixture = $this->image($kind);
        $content = file_get_contents($fixture->getRealPath());
        return UploadedFile::fake()->createWithContent('sized.png', $content.str_repeat("\0", $bytes - strlen($content)));
    }

    private function actor(string $role = 'HOST', string $status = 'ACTIVE'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function property(User $host, string $status = 'ACTIVE'): Property
    {
        return Property::create(['host_id' => $host->id, 'name' => 'Image fixture', 'type' => 'HOTEL', 'address' => 'Fixture', 'status' => $status])->refresh();
    }

    private function endpoint(int|string $id): string { return '/api/host/properties/'.$id.'/images'; }

    public function test_guest_and_wrong_roles_are_denied_before_input(): void
    {
        $this->getJson($this->endpoint('01'))->assertUnauthorized();
        $this->postJson($this->endpoint('01'), [])->assertUnauthorized();
        foreach (['CUSTOMER', 'ADMIN'] as $role) {
            $actor = $this->actor($role);
            $this->actingAs($actor)->getJson($this->endpoint('01'))->assertForbidden();
            $this->postJson($this->endpoint('01'), [])->assertForbidden();
            $this->postJson($this->endpoint('1'), [])->assertForbidden();
            try {
                $this->app->make(HostPropertyImageService::class)->upload($actor, 1, new UploadHostPropertyImageRequest($this->image()));
                $this->fail('Expected role denial.');
            } catch (AppException $error) { $this->assertSame(403, $error->status); }
        }
        Storage::disk('public')->assertDirectoryEmpty('properties');
    }

    public function test_inactive_accounts_are_denied(): void
    {
        foreach (['BLOCKED' => 'ACCOUNT_BLOCKED', 'INACTIVE' => 'ACCOUNT_INACTIVE'] as $status => $code) {
            $this->actingAs($this->actor('HOST', $status))->getJson($this->endpoint('1'))->assertForbidden()->assertJsonPath('code', $code);
            $this->actingAs($this->actor('HOST', $status))->postJson($this->endpoint('1'), [])->assertForbidden()->assertJsonPath('code', $code);
        }
    }

    public function test_ownership_and_bad_ids_share_privacy_404_without_writes(): void
    {
        $host = $this->actor(); $property = $this->property($this->actor());
        $this->actingAs($host);
        foreach ([$property->id, '999999', '0', '01', '-1', 'abc', str_repeat('9', 40)] as $id) {
            $this->getJson($this->endpoint($id))->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
            $this->postJson($this->endpoint($id), ['image' => $this->image()])->assertNotFound();
        }
        $this->assertNull($this->app->make(HostPropertyImageService::class)->upload($host, $property->id, new UploadHostPropertyImageRequest($this->image())));
        $this->assertNull($this->app->make(HostPropertyImageService::class)->listing($host, $property->id));
        $this->assertDatabaseCount('property_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function formats(): array { return [['png'], ['jpg'], ['webp']]; }

    #[DataProvider('formats')]
    public function test_upload_real_content_maps_exact_response_and_keeps_gallery_contract(string $format): void
    {
        $host = $this->actor(); $property = $this->property($host); $before = $property->getAttributes();
        $this->actingAs($host)->getJson($this->endpoint($property->id))->assertExactJson([
            'success' => true, 'message' => 'Danh sách hình ảnh cơ sở lưu trú.', 'data' => [],
        ]);
        Log::spy();
        $response = $this->postJson($this->endpoint($property->id), ['image' => $this->image($format)])->assertCreated();
        $row = PropertyImage::sole();
        $this->assertMatchesRegularExpression('~\Aproperties/'.$property->id.'/[a-f0-9-]{36}\.'.$format.'\z~', $row->image_path);
        Storage::disk('public')->assertExists($row->image_path);
        $expected = ['id' => $row->id, 'url' => Storage::disk('public')->url($row->image_path), 'is_primary' => true];
        $response->assertExactJson(['success' => true, 'message' => 'Tải ảnh cơ sở lưu trú thành công.', 'data' => $expected]);
        $this->getJson($this->endpoint($property->id))->assertJsonPath('data', [$expected]);
        $this->getJson('/api/host/properties/'.$property->id)->assertJsonPath('data.images', [$expected['url']]);
        $this->getJson('/api/public/properties/'.$property->id)->assertJsonPath('data.images', [$expected['url']]);
        $this->assertSame($before, $property->fresh()->getAttributes());
        $this->assertSame(0, DB::transactionLevel());
        Log::shouldHaveReceived('info')->once()->with('Tải ảnh cơ sở lưu trú thành công.', ['property_id' => $property->id, 'image_id' => $row->id]);
    }

    public static function statuses(): array { return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']]; }

    #[DataProvider('statuses')]
    public function test_upload_in_all_property_statuses_preserves_status_and_primary_order(string $status): void
    {
        $host = $this->actor(); $property = $this->property($host, $status);
        $legacy = PropertyImage::create(['property_id' => $property->id, 'image_path' => 'https://example.com/legacy.jpg']);
        $this->actingAs($host)->postJson($this->endpoint($property->id), ['image' => $this->image()])->assertCreated()->assertJsonPath('data.is_primary', true);
        $this->postJson($this->endpoint($property->id), ['image' => $this->image()])->assertCreated()->assertJsonPath('data.is_primary', false);
        $this->getJson($this->endpoint($property->id))->assertJsonPath('data.0.is_primary', true)->assertJsonPath('data.1.id', $legacy->id);
        $this->assertSame($status, $property->fresh()->status->value);
    }

    public function test_unsafe_legacy_paths_are_not_exposed_but_count_towards_limit(): void
    {
        $host = $this->actor(); $property = $this->property($host);
        for ($i=0; $i<20; $i++) PropertyImage::create(['property_id' => $property->id, 'image_path' => 'private/'.$i.'.jpg']);
        $this->actingAs($host)->getJson($this->endpoint($property->id))->assertJsonPath('data', []);
        $this->postJson($this->endpoint($property->id), ['image' => $this->image()])->assertConflict()
            ->assertExactJson(['message' => 'Cơ sở đã đạt giới hạn 20 ảnh.']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_twentieth_upload_succeeds_and_next_is_rejected(): void
    {
        $host = $this->actor(); $property = $this->property($host);
        for ($i=0; $i<19; $i++) PropertyImage::create(['property_id' => $property->id, 'image_path' => 'https://example.com/'.$i.'.jpg', 'is_primary' => $i===0]);
        $this->actingAs($host)->postJson($this->endpoint($property->id), ['image' => $this->image()])->assertCreated()->assertJsonPath('data.is_primary', false);
        $this->postJson($this->endpoint($property->id), ['image' => $this->image()])->assertConflict();
        $this->assertDatabaseCount('property_images', 20);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_invalid_files_and_extra_fields_never_write(): void
    {
        $host = $this->actor(); $property = $this->property($host); $this->actingAs($host);
        $inputs = [[], ['image' => 'https://example.com/image.png'], ['image' => [$this->image()]],
            ['image' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image')],
            ['image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
            ['image' => $this->image('wide')], ['image' => $this->sizedImage(5 * 1024 * 1024 + 1)]];
        foreach ($inputs as $input) $this->postJson($this->endpoint($property->id), $input)->assertUnprocessable()->assertJsonValidationErrors('image');
        foreach (['host_id','property_id','image_path','is_primary','unknown'] as $field) {
            $this->postJson($this->endpoint($property->id), ['image' => $this->image(), $field => 'injected'])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('property_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_exact_file_size_and_dimension_limits_are_accepted(): void
    {
        $host = $this->actor(); $property = $this->property($host);
        $this->actingAs($host)->postJson($this->endpoint($property->id), ['image' => $this->sizedImage(5 * 1024 * 1024, 'edge')])->assertCreated();
    }

    public function test_nested_transaction_is_rejected_before_file_write(): void
    {
        $host = $this->actor(); $property = $this->property($host);
        DB::beginTransaction();
        try {
            $this->app->make(HostPropertyImageService::class)->upload($host, $property->id, new UploadHostPropertyImageRequest($this->image()));
            $this->fail('Expected nested transaction rejection.');
        } catch (LogicException $error) { $this->assertStringContainsString('root transaction', $error->getMessage()); }
        finally { DB::rollBack(); }
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('property_images', 0);
    }

    public function test_database_failure_after_insert_rolls_back_and_deletes_only_new_file(): void
    {
        $host = $this->actor(); $property = $this->property($host);
        Storage::disk('public')->put('properties/kept.txt', 'existing');
        $real = $this->app->make(CatalogRepository::class); $repo = $this->createMock(CatalogRepository::class);
        $repo->method('lockHostProperty')->willReturnCallback(fn (int $hostId, int $id) => $real->lockHostProperty($hostId,$id));
        $repo->method('getPropertyImages')->willReturnCallback(fn (int $id) => $real->getPropertyImages($id));
        $failure = new RuntimeException('Controlled insert failure.');
        $repo->method('createPropertyImage')->willReturnCallback(function(array $attributes) use($real,$failure) {
            $real->createPropertyImage($attributes);
            $this->assertDatabaseCount('property_images', 1);
            throw $failure;
        });
        $this->app->instance(CatalogRepository::class,$repo); Log::spy();
        try { $this->app->make(HostPropertyImageService::class)->upload($host,$property->id,new UploadHostPropertyImageRequest($this->image())); $this->fail('Expected failure.'); }
        catch(RuntimeException $error) { $this->assertSame($failure,$error); }
        $this->assertDatabaseCount('property_images',0);
        $this->assertSame(['properties/kept.txt'],Storage::disk('public')->allFiles());
        Log::shouldNotHaveReceived('info');
    }

    public function test_storage_failure_does_not_insert_and_cleanup_failure_preserves_original(): void
    {
        $host = $this->actor(); $property = $this->property($host);
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andThrow(new RuntimeException('Cleanup failed.'));
        Storage::shouldReceive('disk')->with('public')->andReturn($disk); Log::spy();
        try { $this->app->make(HostPropertyImageService::class)->upload($host,$property->id,new UploadHostPropertyImageRequest($this->image())); $this->fail('Expected failure.'); }
        catch(RuntimeException $error) { $this->assertSame('Property image storage failed.',$error->getMessage()); }
        $this->assertDatabaseCount('property_images',0);
        Log::shouldNotHaveReceived('info');
        Log::shouldHaveReceived('warning')->once()->with('Không thể dọn ảnh cơ sở sau lỗi upload.', \Mockery::on(fn($context) => $context['property_id']===$property->id && str_starts_with($context['managed_path'],'properties/'.$property->id.'/')));
    }

    public function test_mapping_failure_rolls_back_file_and_row(): void
    {
        $host = $this->actor(); $property = $this->property($host);
        $symfony = $this->createMock(ObjectMapperInterface::class);
        $failure = new RuntimeException('Controlled mapping failure.');
        $symfony->method('map')->willThrowException($failure);
        $this->app->instance(ObjectMapperInterface::class,$symfony); Log::spy();
        try { $this->app->make(HostPropertyImageService::class)->upload($host,$property->id,new UploadHostPropertyImageRequest($this->image())); $this->fail('Expected mapping failure.'); }
        catch(RuntimeException $error) { $this->assertSame($failure,$error); }
        $this->assertDatabaseCount('property_images',0);
        $this->assertSame([],Storage::disk('public')->allFiles());
        Log::shouldNotHaveReceived('info');
    }

    public function test_image_mapping_uses_real_symfony_without_queries_or_model_mutation(): void
    {
        $symfony=$this->app->make(ObjectMapperInterface::class); $this->assertInstanceOf(ObjectMapper::class,$symfony);
        $spy=$this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->once())->method('map')->willReturnCallback(fn(object $source,string $target) => $symfony->map($source,$target));
        $this->app->instance(ObjectMapperInterface::class,$spy);
        $image=new PropertyImage(['image_path'=>'https://example.com/test.jpg','is_primary'=>true]);$image->id=31;$before=$image->getAttributes();
        DB::enableQueryLog();DB::flushQueryLog();
        try {
            $dto=$this->app->make(CatalogMapper::class)->toHostPropertyImage($image);
            $this->assertInstanceOf(HostPropertyImageResponse::class,$dto);
            $this->assertSame(['id'=>31,'url'=>'https://example.com/test.jpg','is_primary'=>true],$dto->jsonSerialize());
            $this->assertSame([] ,DB::getQueryLog());$this->assertSame($before,$image->getAttributes());
        } finally { DB::disableQueryLog(); }
    }
    private function managedImage(Property $property, bool $primary = false, int $suffix = 1): PropertyImage
    {
        $path = 'properties/'.$property->id.'/00000000-0000-4000-8000-'.str_pad((string)$suffix,12,'0',STR_PAD_LEFT).'.png';
        Storage::disk('public')->put($path, 'owned fixture');
        return PropertyImage::create(['property_id'=>$property->id,'image_path'=>$path,'is_primary'=>$primary])->refresh();
    }

    private function primaryUrl(int|string $property, int|string $image): string
    {
        return $this->endpoint($property).'/'.$image.'/primary';
    }

    #[DataProvider('statuses')]
    public function test_primary_selection_normalizes_flags_and_keeps_contract_in_all_statuses(string $status): void
    {
        $host=$this->actor();$property=$this->property($host,$status);$before=$property->getAttributes();
        $first=$this->managedImage($property,true,1);$second=$this->managedImage($property,true,2);
        $other=$this->property($this->actor());$otherImage=$this->managedImage($other,true,3);$otherBefore=$otherImage->getAttributes();
        $expected=[['id'=>$second->id,'url'=>Storage::disk('public')->url($second->image_path),'is_primary'=>true],
            ['id'=>$first->id,'url'=>Storage::disk('public')->url($first->image_path),'is_primary'=>false]];
        $this->actingAs($host)->patchJson($this->primaryUrl($property->id,$second->id))->assertOk()
            ->assertExactJson(['success'=>true,'message'=>'Đã cập nhật ảnh đại diện cơ sở.','data'=>$expected]);
        $this->getJson($this->endpoint($property->id))->assertJsonPath('data',$expected);
        $this->getJson('/api/host/properties/'.$property->id)->assertJsonPath('data.images',array_column($expected,'url'));
        if($status==='ACTIVE') $this->getJson('/api/public/properties/'.$property->id)->assertJsonPath('data.images',array_column($expected,'url'));
        $this->assertSame($before,$property->fresh()->getAttributes());$this->assertSame($otherBefore,$otherImage->fresh()->getAttributes());
        Storage::disk('public')->assertExists($first->image_path);Storage::disk('public')->assertExists($second->image_path);
    }

    public function test_primary_noop_keeps_timestamps_and_does_not_log(): void
    {
        $host=$this->actor();$property=$this->property($host);$image=$this->managedImage($property,true);$before=$image->getAttributes();
        $this->travel(2)->seconds();Log::spy();
        $this->actingAs($host)->patchJson($this->primaryUrl($property->id,$image->id))->assertOk();
        $this->assertSame($before,$image->fresh()->getAttributes());Log::shouldNotHaveReceived('info');
    }

    public function test_image_mutations_preserve_auth_privacy_and_reject_payloads(): void
    {
        $host=$this->actor();$property=$this->property($host);$image=$this->managedImage($property,true);
        $other=$this->property($this->actor());$otherImage=$this->managedImage($other,true);
        foreach(['PATCH','DELETE'] as $method){
            $url=$method==='PATCH'?$this->primaryUrl($property->id,$image->id):$this->endpoint($property->id).'/'.$image->id;
            $this->json($method,$url)->assertUnauthorized();
        }
        foreach(['CUSTOMER','ADMIN'] as $role){
            $actor=$this->actor($role);$this->actingAs($actor)->patchJson($this->primaryUrl('01','abc'))->assertForbidden();
            $this->deleteJson($this->endpoint('01').'/abc')->assertForbidden();
            foreach(['makePrimary','delete'] as $operation){
                try{$this->app->make(HostPropertyImageService::class)->$operation($actor,new HostPropertyImageRequest($property->id,$image->id));$this->fail('Expected denial.');}
                catch(AppException $e){$this->assertSame(403,$e->status);}
            }
        }
        foreach(['BLOCKED'=>'ACCOUNT_BLOCKED','INACTIVE'=>'ACCOUNT_INACTIVE'] as $status=>$code){
            $this->actingAs($this->actor('HOST',$status))->patchJson($this->primaryUrl(1,1))->assertForbidden()->assertJsonPath('code',$code);
            $this->actingAs($this->actor('HOST',$status))->deleteJson($this->endpoint(1).'/1')->assertForbidden()->assertJsonPath('code',$code);
        }
        $this->actingAs($host);
        foreach([[$property->id,$otherImage->id],[$other->id,$otherImage->id],['01',$image->id],[$property->id,'01'],[$property->id,'999999'],['999999',$image->id]] as [$pid,$iid]){
            foreach(['PATCH','DELETE'] as $method){
                $url=$method==='PATCH'?$this->primaryUrl($pid,$iid):$this->endpoint($pid).'/'.$iid;
                $this->json($method,$url)->assertNotFound()->assertExactJson(['message'=>'Không tìm thấy hình ảnh cơ sở lưu trú.']);
            }
        }
        $this->patchJson($this->primaryUrl($property->id,$image->id),['is_primary'=>true])->assertUnprocessable()->assertJsonValidationErrors('is_primary');
        $this->deleteJson($this->endpoint($property->id).'/'.$image->id,['image_path'=>'injected'])->assertUnprocessable()->assertJsonValidationErrors('image_path');
        $this->assertDatabaseCount('property_images',2);Storage::disk('public')->assertExists($image->image_path);
    }

    public function test_private_legacy_image_cannot_be_primary(): void
    {
        $host=$this->actor();$property=$this->property($host);$image=PropertyImage::create(['property_id'=>$property->id,'image_path'=>'private/hidden.png']);
        $this->actingAs($host)->patchJson($this->primaryUrl($property->id,$image->id))->assertConflict()
            ->assertExactJson(['message'=>'Ảnh không có URL công khai hợp lệ để làm ảnh đại diện.']);
        $this->assertFalse($image->fresh()->is_primary);
    }

    #[DataProvider('statuses')]
    public function test_delete_primary_selects_lowest_eligible_then_deletes_last_image(string $status): void
    {
        $host=$this->actor();$property=$this->property($host,$status);
        PropertyImage::create(['property_id'=>$property->id,'image_path'=>'private/hidden.png']);
        $first=$this->managedImage($property,false,1);$second=$this->managedImage($property,true,2);
        $this->actingAs($host)->deleteJson($this->endpoint($property->id).'/'.$second->id)->assertOk()
            ->assertJsonPath('data.deleted_image_id',$second->id)->assertJsonPath('data.cleanup_status','deleted')
            ->assertJsonPath('data.images.0.id',$first->id)->assertJsonPath('data.images.0.is_primary',true);
        Storage::disk('public')->assertMissing($second->image_path);Storage::disk('public')->assertExists($first->image_path);
        $this->deleteJson($this->endpoint($property->id).'/'.$first->id)->assertOk()->assertExactJson([
            'success'=>true,'message'=>'Đã xóa ảnh khỏi cơ sở lưu trú.',
            'data'=>['deleted_image_id'=>$first->id,'images'=>[],'cleanup_status'=>'deleted'],
        ]);
        Storage::disk('public')->assertMissing($first->image_path);
        $this->assertDatabaseHas('property_images',['property_id'=>$property->id,'is_primary'=>false]);
        $this->deleteJson($this->endpoint($property->id).'/'.$first->id)->assertNotFound();
        $this->assertSame($status,$property->fresh()->status->value);
    }

    public function test_delete_regular_image_preserves_primary_and_repairs_legacy_flags(): void
    {
        $host=$this->actor();$property=$this->property($host);
        $lowest=$this->managedImage($property,false,1);$primary=$this->managedImage($property,true,2);
        $extra=$this->managedImage($property,true,3);$regular=$this->managedImage($property,false,4);
        $this->actingAs($host)->deleteJson($this->endpoint($property->id).'/'.$regular->id)->assertOk()->assertJsonPath('data.images.0.id',$primary->id);
        $this->assertFalse($extra->fresh()->is_primary);$this->assertFalse($lowest->fresh()->is_primary);
        PropertyImage::where('property_id',$property->id)->update(['is_primary'=>false]);
        $this->deleteJson($this->endpoint($property->id).'/'.$extra->id)->assertOk()->assertJsonPath('data.images.0.id',$lowest->id);
        $this->assertTrue($lowest->fresh()->is_primary);
    }

    public static function cleanupModes(): array {return [['missing'],['not_managed'],['shared'],['failed']];}

    #[DataProvider('cleanupModes')]
    public function test_delete_reports_cleanup_state_without_deleting_unrelated_resources(string $mode): void
    {
        $host=$this->actor();$property=$this->property($host);$image=$this->managedImage($property,true);
        $path=$image->image_path;
        if($mode==='missing')Storage::disk('public')->delete($path);
        if($mode==='not_managed'){$image->image_path='https://example.com/legacy.jpg';$image->save();}
        if($mode==='shared'){$other=$this->property($this->actor());PropertyImage::create(['property_id'=>$other->id,'image_path'=>$path]);}
        if($mode==='failed'){
            $disk=\Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
            $disk->shouldReceive('exists')->once()->with($path)->andReturn(true);
            $disk->shouldReceive('delete')->once()->with($path)->andReturn(false);
            Storage::shouldReceive('disk')->with('public')->andReturn($disk);Log::spy();
        }
        $this->actingAs($host)->deleteJson($this->endpoint($property->id).'/'.$image->id)->assertOk()->assertJsonPath('data.cleanup_status',$mode);
        $this->assertDatabaseMissing('property_images',['id'=>$image->id]);
        if($mode==='shared'||$mode==='not_managed')Storage::disk('public')->assertExists($path);
        if($mode==='failed')Log::shouldHaveReceived('warning')->once();
    }


    public static function publicPathAliases(): array
    {
        $cases = [];
        foreach ([false, true] as $propertyReference) {
            foreach (['relative', 'storage', 'disk', 'query', 'encoded'] as $form) {
                $cases[($propertyReference ? 'property' : 'room').'-'.$form] = [$propertyReference, $form];
            }
        }
        return $cases;
    }

    #[DataProvider('publicPathAliases')]
    public function test_delete_keeps_public_path_alias_references(bool $propertyReference, string $form): void
    {
        config(['filesystems.disks.public.url' => 'https://assets.stayhub.test:8443/public-media']);
        Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        $host = $this->actor();
        $target = $this->property($host);
        $image = $this->managedImage($target, true);
        $path = $image->image_path;
        $alias = match ($form) {
            'relative' => $path,
            'storage' => '/storage/'.$path,
            'disk' => Storage::disk('public')->url($path),
            'query' => Storage::disk('public')->url($path).'?v=1#preview',
            'encoded' => 'https://assets.stayhub.test:8443/public-media/'.str_replace('/', '%2F', $path),
        };
        $other = Room::factory()->create();
        $reference = $propertyReference
            ? PropertyImage::create(['property_id' => $other->property_id, 'image_path' => $alias, 'is_primary' => true])
            : RoomImage::create(['room_id' => $other->id, 'image_path' => $alias, 'is_primary' => true]);
        $original = $reference->fresh()->getAttributes();
        $this->actingAs($host)->deleteJson($this->endpoint($target->id).'/'.$image->id)->assertOk()
            ->assertJsonPath('data.deleted_image_id', $image->id)->assertJsonPath('data.cleanup_status', 'shared');
        $this->assertDatabaseMissing('property_images', ['id' => $image->id]);
        $this->assertSame($original, $reference->fresh()->getAttributes());
        Storage::disk('public')->assertExists($path);
        $this->assertSame('owned fixture', Storage::disk('public')->get($path));
    }

    public static function unrelatedPublicUrls(): array
    {
        return [
            ['https://external.test/public-media/'],
            ['https://assets.stayhub.test:8443/elsewhere/'],
            ['https://assets.stayhub.test:8444/public-media/'],
        ];
    }

    #[DataProvider('unrelatedPublicUrls')]
    public function test_delete_does_not_treat_unrelated_urls_as_shared(string $base): void
    {
        config(['filesystems.disks.public.url' => 'https://assets.stayhub.test:8443/public-media']);
        Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        $host = $this->actor();
        $target = $this->property($host);
        $image = $this->managedImage($target, true);
        $path = $image->image_path;
        $other = Room::factory()->create();
        $reference = RoomImage::create(['room_id' => $other->id, 'image_path' => $base.$path]);
        $original = $reference->fresh()->getAttributes();
        $this->actingAs($host)->deleteJson($this->endpoint($target->id).'/'.$image->id)->assertOk()
            ->assertJsonPath('data.cleanup_status', 'deleted');
        $this->assertDatabaseMissing('property_images', ['id' => $image->id]);
        $this->assertSame($original, $reference->fresh()->getAttributes());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_reference_lookup_failure_keeps_file_after_row_deletion(): void
    {
        $host = $this->actor();
        $target = $this->property($host);
        $image = $this->managedImage($target, true);
        $path = $image->image_path;
        $repository = \Mockery::mock(CatalogRepository::class)->makePartial();
        $repository->shouldReceive('imagePathReferenced')->once()->andThrow(new RuntimeException('Fixture lookup failure'));
        $this->app->instance(CatalogRepository::class, $repository);
        $this->actingAs($host)->deleteJson($this->endpoint($target->id).'/'.$image->id)->assertOk()
            ->assertJsonPath('data.cleanup_status', 'failed');
        $this->assertDatabaseMissing('property_images', ['id' => $image->id]);
        Storage::disk('public')->assertExists($path);
    }

    public static function sharedRoomOwners(): array
    {
        return ['same property' => [false], 'different host property' => [true]];
    }

    #[DataProvider('sharedRoomOwners')]
    public function test_delete_preserves_managed_file_referenced_by_room_image(bool $differentOwner): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $image = $this->managedImage($property, true);
        $path = $image->image_path;
        $roomProperty = $differentOwner ? $this->property($this->actor()) : $property;
        $roomType = RoomType::factory()->create(['property_id' => $roomProperty->id]);
        $room = Room::factory()->create([
            'property_id' => $roomProperty->id, 'room_type_id' => $roomType->id,
        ]);
        $roomImage = RoomImage::create([
            'room_id' => $room->id, 'image_path' => $path, 'is_primary' => true,
        ]);
        $originalRoom = $room->fresh()->getAttributes();

        $this->actingAs($host)->deleteJson($this->endpoint($property->id).'/'.$image->id)
            ->assertOk()
            ->assertJsonPath('data.deleted_image_id', $image->id)
            ->assertJsonPath('data.cleanup_status', 'shared')
            ->assertJsonPath('data.images', []);

        $this->assertDatabaseMissing('property_images', ['id' => $image->id]);
        $this->assertDatabaseHas('room_images', [
            'id' => $roomImage->id, 'room_id' => $room->id,
            'image_path' => $path, 'is_primary' => true,
        ]);
        $this->assertSame($originalRoom, $room->fresh()->getAttributes());
        Storage::disk('public')->assertExists($path);
        $this->assertSame('owned fixture', Storage::disk('public')->get($path));
    }

    public function test_delete_removes_unreferenced_managed_file(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $image = $this->managedImage($property, true);
        $path = $image->image_path;

        $this->actingAs($host)->deleteJson($this->endpoint($property->id).'/'.$image->id)
            ->assertOk()
            ->assertJsonPath('data.deleted_image_id', $image->id)
            ->assertJsonPath('data.cleanup_status', 'deleted')
            ->assertJsonPath('data.images', []);

        $this->assertDatabaseMissing('property_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public static function imageOperations(): array {return [['makePrimary'],['delete']];}

    #[DataProvider('imageOperations')]
    public function test_mutations_reject_nested_transactions_and_rollback_before_cleanup(string $operation): void
    {
        $host=$this->actor();$property=$this->property($host);$first=$this->managedImage($property,true,1);$target=$this->managedImage($property,false,2);
        $request=new HostPropertyImageRequest($property->id,$target->id);
        DB::beginTransaction();
        try{$this->app->make(HostPropertyImageService::class)->$operation($host,$request);$this->fail('Expected root guard.');}
        catch(LogicException $e){$this->assertStringContainsString('root transaction',$e->getMessage());}finally{DB::rollBack();}
        $before=PropertyImage::orderBy('id')->get()->toArray();
        $real=$this->app->make(CatalogRepository::class);$repo=$this->createMock(CatalogRepository::class);
        $repo->method('lockHostProperty')->willReturnCallback(fn(int $uid,int $pid)=>$real->lockHostProperty($uid,$pid));
        $reads=0;$failure=new RuntimeException('Controlled projection failure after mutation.');
        $repo->method('getPropertyImages')->willReturnCallback(function(int $pid)use($real,&$reads,$failure){if(++$reads>1)throw $failure;return $real->getPropertyImages($pid);});
        $repo->method('setPropertyPrimary')->willReturnCallback(fn(int $pid,?int $iid)=>$real->setPropertyPrimary($pid,$iid));
        $repo->method('deletePropertyImage')->willReturnCallback(fn(PropertyImage $i)=>$real->deletePropertyImage($i));
        $repo->expects($this->never())->method('imagePathReferenced');
        $this->app->instance(CatalogRepository::class,$repo);Log::spy();
        try{$this->app->make(HostPropertyImageService::class)->$operation($host,$request);$this->fail('Expected failure.');}
        catch(RuntimeException $e){$this->assertSame($failure,$e);}
        $this->assertSame($before,PropertyImage::orderBy('id')->get()->toArray());
        Storage::disk('public')->assertExists($first->image_path);Storage::disk('public')->assertExists($target->image_path);Log::shouldNotHaveReceived('info');
    }

    public function test_delete_cleanup_and_logging_run_only_after_real_commit(): void
    {
        $host=$this->actor();$property=$this->property($host);$image=$this->managedImage($property,true);$disk=Storage::disk('public');
        $proxy=\Mockery::mock($disk)->makePartial();
        $proxy->shouldReceive('exists')->once()->with($image->image_path)->andReturn(true);
        $proxy->shouldReceive('delete')->once()->with($image->image_path)->andReturnUsing(function($path)use($disk,$image){
            $this->assertSame(0,DB::transactionLevel());$this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertDatabaseMissing('property_images',['id'=>$image->id]);return $disk->delete($path);
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($proxy);
        Log::shouldReceive('info')->once()->with('Đã xóa ảnh khỏi cơ sở lưu trú.',\Mockery::on(function($context)use($image){$this->assertSame(0,DB::transactionLevel());return $context['image_id']===$image->id;}));
        $this->actingAs($host)->deleteJson($this->endpoint($property->id).'/'.$image->id)->assertOk()->assertJsonPath('data.cleanup_status','deleted');
        $disk->assertMissing($image->image_path);
    }

}
