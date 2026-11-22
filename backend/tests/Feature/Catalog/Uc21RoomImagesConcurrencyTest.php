<?php

namespace Tests\Feature\Catalog;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\Uc21ImageRaceProtocol;
use Tests\Support\Uc21MySqlTestCase;

class Uc21RoomImagesConcurrencyTest extends Uc21MySqlTestCase
{
    public static function races(): array
    {
        return [
            'upload limit'=>['upload','upload',19,false,[201,409]],
            'two primary'=>['primary','primary',3,false,[200,200]],
            'primary then delete'=>['primary','delete',2,false,[200,200]],
            'delete then primary'=>['delete','primary',2,false,[200,404]],
            'delete then upload'=>['delete','upload',1,false,[200,201]],
            'upload then delete'=>['upload','delete',1,false,[201,200]],
            'upload rollback'=>['upload','upload',19,true,[500,201]],
            'delete rollback'=>['delete','primary',2,true,[500,200]],
        ];
    }

    #[DataProvider('races')]
    public function test_production_mutations_wait_then_reread_committed_facts(string $a, string $b, int $initial, bool $inject, array $expected): void
    {
        $db = DB::connection();
        if ($db->getSchemaBuilder()->getTables() === []) $this->artisan('migrate', ['--no-interaction'=>true])->assertExitCode(0);
        $host = User::factory()->create(['role'=>'HOST','status'=>'ACTIVE']);
        $property = Property::create(['host_id'=>$host->id,'name'=>'Race fixture','type'=>'HOTEL','address'=>'Fixture','status'=>'ACTIVE']);
        $type = $property->roomTypes()->create(['name'=>'Fixture']);
        $room = $property->rooms()->create(['room_type_id'=>$type->id,'room_number'=>'R1','name'=>'Race room','capacity'=>2,'price_per_night'=>'100.00','status'=>'ACTIVE']);
        for ($i=0; $i<$initial; $i++) {
            $path = 'rooms/'.$room->id.'/'.Str::uuid().'.png';
            Storage::disk('public')->put($path, 'seed fixture');
            $room->images()->create(['image_path'=>$path,'is_primary'=>$i===0]);
        }
        $target = $room->images()->orderBy('id')->first()->id;
        $second = $room->images()->orderByDesc('id')->first()->id;
        if ($a==='primary') $target = $room->images()->orderBy('id')->skip(1)->first()->id;
        $case = 'case-'.bin2hex(random_bytes(16));
        $protocol = new Uc21ImageRaceProtocol(dirname(getenv('STAYHUB_UC21_MANIFEST')), $case, true);
        $protocol->write('jobA', ['host'=>$host->id,'room'=>$room->id,'image'=>$target,'operation'=>$a,'inject'=>$inject]);
        $protocol->write('jobB', ['host'=>$host->id,'room'=>$room->id,'image'=>($a==='primary' && $b==='primary')?$second:$target,'operation'=>$b]);
        $processes=[]; $released=false;
        try {
            foreach (['A','B'] as $label) {
                $process = new Process([PHP_BINARY, 'tests/Support/Uc21ImageRaceWorker.php'], base_path(), ['UC21_CASE'=>$case,'UC21_WORKER_LABEL'=>$label]);
                $process->setTimeout(30); $process->start(); $processes[$label]=$process;
                if ($label==='A') $held=$protocol->wait('heldA');
            }
            $attempt=$protocol->wait('attemptB');
            $this->assertNotSame($held['connection'], $attempt['connection']);
            $this->assertSame($initial, $attempt['snapshot_count']);
            $protocol->write('observe', ['blocking'=>$held['connection'],'waiting'=>$attempt['connection']]);
            $observed=$protocol->wait('observed', 6);
            $this->assertSame($held['connection'], (int)$observed['blocking']);
            $this->assertSame($attempt['connection'], (int)$observed['waiting']);
            $this->assertLessThanOrEqual($observed['observed_at'], $attempt['at']);
            $releasedAt=microtime(true);
            $protocol->write('releaseA', ['after_lock_observed'=>true, 'at'=>$releasedAt]); $released=true;
            foreach ($processes as $process) { $process->wait(); $this->assertSame(0, $process->getExitCode(), 'Worker must finish successfully; consult scoped evidence.'); }
            $results=[$protocol->wait('resultA'), $protocol->wait('resultB')];
            $this->assertSame($expected, array_column($results,'status'));
            $this->assertSame([0,0], array_column($results,'transaction_level'));
            $this->assertNotSame($results[0]['pid'], $results[1]['pid']);
            $this->assertGreaterThanOrEqual($releasedAt, $results[0]['at']);
            $this->assertGreaterThanOrEqual($releasedAt, $results[1]['at']);
            $images=$room->images()->orderBy('id')->get();
            $expectedCount=$initial + (($a==='upload' && !$inject)?1:0) + (($b==='upload' && $expected[1]===201)?1:0)
                - (($a==='delete' && !$inject)?1:0) - ($b==='delete'?1:0);
            $this->assertCount($expectedCount, $images);
            $this->assertSame($images->isEmpty()?0:1, $images->where('is_primary',true)->count());
            $this->assertLessThanOrEqual(20, $images->count());
            $files=Storage::disk('public')->allFiles('rooms/'.$room->id);
            $paths=$images->pluck('image_path')->all(); sort($paths); sort($files);
            $this->assertSame($paths,$files,'No leaked or incorrectly removed owned files.');
            if ($b==='primary' && $expected[1]===200) $this->assertSame(($a==='primary')?$second:$target, $images->firstWhere('is_primary',true)->id);
            if (getenv('STAYHUB_UC21_INJECT_FAILURE')==='1') $this->fail('Injected concurrency failure after workers completed.');
        } finally {
            if (!$released && isset($held)) $protocol->write('releaseA', ['failure_cleanup'=>true]);
            foreach ($processes as $process) if ($process->isRunning()) $process->stop(2);
            $protocol->write('workersStopped', ['all_stopped'=>count(array_filter($processes, fn($p)=>$p->isRunning()))===0]);
        }
    }
}
