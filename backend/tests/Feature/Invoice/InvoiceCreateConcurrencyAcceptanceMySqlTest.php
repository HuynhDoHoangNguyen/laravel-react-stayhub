<?php

namespace Tests\Feature\Invoice;

use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InvoiceCreateLifecycleMySqlTestCase;

final class InvoiceCreateConcurrencyAcceptanceMySqlTest extends InvoiceCreateLifecycleMySqlTestCase
{
    private array $workers = [];

    protected function tearDown(): void
    {
        try {
            if (isset($this->caseDirectory)) file_put_contents($this->caseDirectory.'/release.json', '{}');
            foreach (array_keys($this->workers) as $label) {
                $worker = $this->workers[$label];
                if (is_resource($worker['process'])) {
                    if (proc_get_status($worker['process'])['running']) proc_terminate($worker['process']);
                    proc_close($worker['process']);
                    $this->evidence($label.'-exited', ['pid' => $worker['pid'], 'exited' => true]);
                }
                unset($this->workers[$label]);
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function orders(): array
    {
        return ['A-holds' => ['A'], 'B-holds' => ['B']];
    }

    #[DataProvider('orders')]
    public function test_invoice_create_race_waiter_rechecks_after_holder_commit_and_returns_409(string $holder): void
    {
        $this->runRace($holder, 'commit');
    }

    public function test_waiter_creates_after_holder_rolls_back_with_only_winner_committed_log(): void
    {
        $this->runRace('A', 'rollback');
    }

    public function test_negative_control_waiter_times_out_on_real_booking_lock_without_partial_output(): void
    {
        $this->runRace('A', 'timeout');
    }

    public function test_worker_rejects_development_target_before_ready_or_database_boot(): void
    {
        $reader = $this->observer();
        $this->startWorker('A', false, 'commit', ['DB_DATABASE' => $this->manifest['development_database']]);
        $this->finishWorker('A', 2);
        $this->assertFileDoesNotExist($this->caseDirectory.'/A-ready.json');
        $this->assertFileDoesNotExist($this->caseDirectory.'/A-result.json');
        $this->assertStringContainsString('unowned MySQL target', file_get_contents($this->caseDirectory.'/A-stderr.txt'));
        $this->assertNoOutput($reader);
    }

    private function runRace(string $holder, string $mode): void
    {
        $reader = $this->observer();
        $waiter = $holder === 'A' ? 'B' : 'A';
        $this->startWorker($holder, true, $mode === 'commit' ? 'commit' : 'rollback');
        $firstIdentity = $this->phase($holder, 'ready');
        $written = $this->phase($holder, 'written');
        $this->assertSame(3, $written['items']);
        $this->assertSame('225.78', $written['total']);
        $this->assertGreaterThan(0, $written['transaction_level']);
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->logs($holder));
        $reader->beginTransaction();
        try {
            $reader->select('SELECT id FROM bookings WHERE id = ? FOR UPDATE NOWAIT', [$this->fixture->bookingId]);
            $this->fail('Expected a real lock on the exact Booking row.');
        } catch (QueryException $error) {
            $this->assertSame(3572, $error->errorInfo[1]);
            $this->evidence('nowait', ['driver_code' => $error->errorInfo[1], 'booking_id' => $this->fixture->bookingId]);
        } finally {
            $reader->rollBack();
        }
        $this->startWorker($waiter, false, $mode === 'timeout' ? 'timeout' : 'commit');
        $secondIdentity = $this->phase($waiter, 'ready');
        $parentConnection = $this->app['db']->selectOne('SELECT CONNECTION_ID() AS id')->id;
        foreach ([$firstIdentity, $secondIdentity] as $identity) {
            $this->assertSame($this->manifest['database'], $identity['db']);
            $this->assertSame($this->manifest['server_uuid'], $identity['uuid']);
            $this->assertSame($this->manifest['version'], $identity['version']);
            $this->assertNotSame(getmypid(), $identity['pid']);
            $this->assertNotSame((int) $parentConnection, (int) $identity['connection_id']);
        }
        $this->assertNotSame($firstIdentity['pid'], $secondIdentity['pid']);
        $this->assertNotSame($firstIdentity['connection_id'], $secondIdentity['connection_id']);
        $this->phase($waiter, 'locking');
        $active = $this->waitUntil(function () use ($reader, $secondIdentity) {
            foreach ($reader->select('SHOW FULL PROCESSLIST') as $process) {
                $sql = strtolower((string) $process->Info);
                if ((int) $process->Id === (int) $secondIdentity['connection_id']
                    && str_contains($sql, 'from `bookings`') && str_contains($sql, 'for update')) {
                    return ['connection_id' => (int) $process->Id, 'command' => $process->Command, 'state' => $process->State];
                }
            }
            return null;
        }, 'Waiter must execute locking SQL before holder release.');
        $this->evidence('waiter-active', $active);
        $this->assertFileDoesNotExist($this->caseDirectory.'/'.$waiter.'-result.json');
        $this->assertNoOutput($reader);
        if ($mode === 'timeout') {
            $timedOut = $this->phase($waiter, 'result');
            $this->assertSame(500, $timedOut['status']);
            $this->assertContains(1205, array_column($timedOut['reported'], 'driver_code'));
            $this->assertSame(['invoices' => 0, 'invoice_items' => 0], $timedOut['inserts']);
            $this->assertSame([], $this->logs($waiter));
            $this->assertFileDoesNotExist($this->caseDirectory.'/release.json');
        }
        file_put_contents($this->caseDirectory.'/release.json', '{}');
        $first = $this->phase($holder, 'result');
        $second = $this->phase($waiter, 'result');
        $this->finishWorker($holder);
        $this->finishWorker($waiter);
        foreach ([$first, $second] as $result) {
            $this->assertSame(0, $result['transaction_level']);
            $this->assertFalse($result['pdo_transaction']);
            $this->assertSame(1, $result['locking_attempts'], 'No silent transaction retry.');
            if ($result['status'] === 500) {
                $this->assertSame(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR'], $result['body']);
            }
        }
        $this->assertSame(['invoices' => 1, 'invoice_items' => 3], $first['inserts']);
        if ($mode === 'timeout') {
            $this->assertSame([500, 500], [$first['status'], $second['status']]);
            $this->assertNoOutput($reader);
            $this->assertSame([], [...$this->logs($holder), ...$this->logs($waiter)]);
        } else {
            $this->assertSame($mode === 'commit' ? [201, 409] : [500, 201], [$first['status'], $second['status']]);
            $this->assertSame($mode === 'commit' ? ['invoices' => 0, 'invoice_items' => 0] : ['invoices' => 1, 'invoice_items' => 3], $second['inserts']);
            $id = $this->assertOutput($reader);
            $winner = $mode === 'commit' ? $holder : $waiter;
            $response = $mode === 'commit' ? $first['body'] : $second['body'];
            $this->assertSame($id, $response['data']['id']);
            $this->assertSame('225.78', $response['data']['total_amount']);
            $this->assertCount(3, $response['data']['items']);
            $this->assertOneCommittedLog([...$this->logs($holder), ...$this->logs($waiter)], $id);
            $this->assertSame([], $this->logs($winner === $holder ? $waiter : $holder));
            if ($mode === 'commit') {
                $this->assertSame(['message' => 'Đơn đặt phòng #'.$this->fixture->bookingId.' đã có hóa đơn. Không thể tạo hóa đơn thứ hai.'], $second['body']);
            } else {
                $this->assertNotSame($written['invoice_id'], $id, 'Rolled-back holder row must not be the committed winner.');
            }
            $this->actingAs($this->fixture->actors['H_A'])->getJson('/api/host/invoices/'.$id)->assertOk()->assertExactJson($response);
            $this->actingAs($this->fixture->actors['C_A'])->getJson('/api/customer/invoices/'.$id)->assertOk()->assertExactJson($response);
        }
        $this->evidence('race-summary', ['mode' => $mode, 'holder' => $holder, 'waiter' => $waiter,
            'identities' => [$firstIdentity, $secondIdentity], 'statuses' => [$first['status'], $second['status']],
            'inserts' => [$first['inserts'], $second['inserts']],
            'logs' => [...$this->logs($holder), ...$this->logs($waiter)], 'workers_exited' => true]);
    }

    private function startWorker(string $label, bool $hold, string $mode, array $overrides = []): void
    {
        $environment = array_merge(getenv(), ['APP_DEBUG' => 'false', 'LOG_CHANNEL' => 'null'], $overrides);
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/Support/InvoiceCreateRaceWorker.php'], [
            0 => ['pipe', 'r'], 1 => ['file', $this->caseDirectory.'/'.$label.'-stdout.txt', 'w'],
            2 => ['file', $this->caseDirectory.'/'.$label.'-stderr.txt', 'w'],
        ], $pipes, dirname(__DIR__, 3), $environment, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        $pid = proc_get_status($process)['pid'];
        $this->workers[$label] = ['process' => $process, 'pid' => $pid];
        $this->evidence($label.'-spawn', ['pid' => $pid, 'label' => $label]);
        fwrite($pipes[0], json_encode(['directory' => $this->caseDirectory, 'label' => $label,
            'booking_id' => $this->fixture->bookingId, 'host_id' => $this->fixture->actors['H_A']->id,
            'hold' => $hold, 'mode' => $mode, 'empty_environment' => ['DB_URL' => '', 'DB_SOCKET' => '']], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
    }

    private function phase(string $label, string $phase): array
    {
        return $this->waitUntil(function () use ($label, $phase) {
            $path = $this->caseDirectory.'/'.$label.'-'.$phase.'.json';
            if (is_file($path)) return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!proc_get_status($this->workers[$label]['process'])['running']) {
                $this->fail('Worker exited before '.$phase.': '.file_get_contents($this->caseDirectory.'/'.$label.'-stderr.txt'));
            }
            return null;
        }, $label.' must reach '.$phase);
    }

    private function finishWorker(string $label, int $expectedExit = 0): void
    {
        $status = $this->waitUntil(function () use ($label) {
            $status = proc_get_status($this->workers[$label]['process']);
            return !$status['running'] ? $status : null;
        }, $label.' must exit');
        $this->assertSame($expectedExit, $status['exitcode']);
        proc_close($this->workers[$label]['process']);
        $this->evidence($label.'-exited', ['pid' => $this->workers[$label]['pid'], 'exited' => true, 'exit' => $status['exitcode']]);
        unset($this->workers[$label]);
    }

    private function waitUntil(callable $read, string $message): mixed
    {
        $deadline = microtime(true) + 10;
        do {
            $result = $read();
            if ($result !== null) return $result;
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Race deadline: '.$message);
    }

    private function logs(string $label): array
    {
        $path = $this->caseDirectory.'/'.$label.'-logs.jsonl';
        return !is_file($path) ? [] : array_map(static fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }
}
