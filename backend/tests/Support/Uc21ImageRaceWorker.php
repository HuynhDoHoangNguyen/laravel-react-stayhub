<?php
declare(strict_types=1);

use App\DTOs\Catalog\Requests\HostRoomImageQuery;
use App\DTOs\Catalog\Requests\HostRoomImageRequest;
use App\DTOs\Catalog\Requests\UploadHostRoomImageRequest;
use App\Exceptions\AppException;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\HostRoomImageService;
use Illuminate\Http\UploadedFile;
use Tests\Support\Uc21ImageRaceProtocol;
use Tests\Support\Uc21MySqlEnvironment;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$backend = dirname(__DIR__, 2);
$manifest = Uc21MySqlEnvironment::manifest($backend);
$label = getenv('UC21_WORKER_LABEL');
if (!in_array($label, ['A', 'B'], true)) throw new RuntimeException('Invalid worker label.');
$protocol = new Uc21ImageRaceProtocol(dirname(getenv('STAYHUB_UC21_MANIFEST')), getenv('UC21_CASE'));
$job = $protocol->wait('job'.$label);
$app = require $backend.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Uc21MySqlEnvironment::assertApplication($app, $manifest);
$storage = dirname(getenv('STAYHUB_UC21_MANIFEST')).'/public';
$app['config']->set('filesystems.disks.public.root', $storage);
$app['config']->set('filesystems.disks.public.url', 'http://uc21-fixture.invalid/storage');
$app['filesystem']->forgetDisk('public');
$db = $app['db']->connection();
$db->statement('SET SESSION innodb_lock_wait_timeout=20');
$db->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection = (int) $db->selectOne('SELECT CONNECTION_ID() AS id')->id;
if ($label==='B' && getenv('UC21_FAIL_WORKER')==='1') {
    $protocol->write('resultB', ['status'=>599,'connection'=>$connection,'injected_worker_failure'=>true]);
    $db->disconnect(); exit(1);
}
$repository = new class($protocol, $label, $connection, $job) extends CatalogRepository {
    public function __construct(private Uc21ImageRaceProtocol $protocol, private string $label, private int $connection, private array $job) {}
    public function lockHostProperty(int $hostId, int $propertyId): ?Property
    {
        if ($this->label === 'B') {
            // Establish an older consistent snapshot before waiting; production must reread locked facts.
            $count = $this->getRoomImages($this->job['room'])->count();
            $this->protocol->write('attemptB', ['connection'=>$this->connection, 'snapshot_count'=>$count, 'at'=>microtime(true)]);
        }
        return parent::lockHostProperty($hostId, $propertyId);
    }
    public function lockPropertyRoom(Property $property, int $roomId): ?Room
    {
        $room = parent::lockPropertyRoom($property, $roomId);
        if ($this->label === 'A') {
            $this->protocol->write('heldA', ['connection'=>$this->connection, 'pid'=>getmypid(), 'at'=>microtime(true)]);
            $this->protocol->wait('releaseA');
        }
        return $room;
    }
    public function createRoomImage(array $attributes): RoomImage
    {
        $image = parent::createRoomImage($attributes);
        if ($this->job['inject'] ?? false) throw new RuntimeException('Injected UC21 rollback');
        return $image;
    }
    public function setRoomImagePrimary(int $roomId, ?int $imageId): void
    {
        parent::setRoomImagePrimary($roomId, $imageId);
        if (($this->job['inject'] ?? false) && $this->job['operation'] !== 'upload') throw new RuntimeException('Injected UC21 rollback');
    }
};
$app->instance(CatalogRepository::class, $repository);
$status = 200; $result = null;
try {
    $actor = User::findOrFail($job['host']);
    $service = $app->make(HostRoomImageService::class);
    if ($job['operation'] === 'upload') {
        $file = UploadedFile::fake()->createWithContent('fixture.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQSTkBAAHQAUHAZ3dkAAAAAElFTkSuQmCC'));
        $result = $service->upload($actor, new HostRoomImageQuery($job['room']), new UploadHostRoomImageRequest($file));
        $status = 201;
    } elseif (in_array($job['operation'], ['primary', 'delete'], true)) {
        $input = new HostRoomImageRequest($job['room'], $job['image']);
        $result = $job['operation'] === 'primary' ? $service->makePrimary($actor, $input) : $service->delete($actor, $input);
        if ($result === null) $status = 404;
    } else throw new RuntimeException('Invalid operation.');
} catch (AppException $failure) { $status = $failure->status; }
catch (Throwable $failure) {
    if (($job['inject'] ?? false) && $failure->getMessage() === 'Injected UC21 rollback') $status = 500;
    else { $protocol->write('result'.$label, ['status'=>599, 'exception'=>get_class($failure)]); exit(1); }
}
$protocol->write('result'.$label, ['status'=>$status, 'connection'=>$connection, 'pid'=>getmypid(), 'transaction_level'=>$db->transactionLevel(), 'at'=>microtime(true), 'result'=>$result]);
$db->disconnect();
