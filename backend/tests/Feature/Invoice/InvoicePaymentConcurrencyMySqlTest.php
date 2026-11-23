<?php

namespace Tests\Feature\Invoice;

use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InvoicePaymentMySqlTestCase;

final class InvoicePaymentConcurrencyMySqlTest extends InvoicePaymentMySqlTestCase
{
    private array $workers = [];
    private ?string $raceDirectory = null;

    protected function tearDown(): void
    {
        try {
            if ($this->raceDirectory !== null) file_put_contents($this->raceDirectory.'/release.json', '{}');
            foreach ($this->workers as $worker) {
                if (is_resource($worker['process'])) {
                    if (proc_get_status($worker['process'])['running']) proc_terminate($worker['process']);
                    proc_close($worker['process']);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function holderOrders(): array
    {
        return ['A-then-B' => ['A'], 'B-then-A' => ['B']];
    }

    #[DataProvider('holderOrders')]
    public function test_waiter_rechecks_committed_state_and_returns_identical_200_without_second_update(string $holder): void
    {
        $this->runRace($holder, 'commit');
    }

    public function test_waiter_can_pay_after_holder_rolls_back_and_only_waiter_emits_committed_success(): void
    {
        $this->runRace('A', 'rollback');
    }

    public function test_negative_control_waiter_gets_mysql_lock_timeout_while_holder_keeps_the_lock(): void
    {
        $this->runRace('A', 'timeout');
    }

    public function test_worker_refuses_demo_target_before_booting_or_connecting(): void
    {
        extract($this->fixture());
        $this->makeRaceDirectory();
        $this->startWorker('A', $invoice->id, $host->id, false, 'commit', [
            'DB_DATABASE' => 'stayhub', 'STAYHUB_UC36_TEST_DATABASE' => 'stayhub',
        ]);
        $status = $this->waitUntil(function () {
            $status = proc_get_status($this->workers['A']['process']);
            return !$status['running'] ? $status : null;
        }, 'unsafe worker must exit');
        $this->assertSame(2, $status['exitcode']);
        $this->assertFileDoesNotExist($this->raceDirectory.'/A-ready.json');
        $this->assertFileDoesNotExist($this->raceDirectory.'/A-result.json');
        $this->assertStringContainsString('RuntimeException', file_get_contents($this->raceDirectory.'/A-stderr.txt'));
        proc_close($this->workers['A']['process']);
        unset($this->workers['A']);
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);
    }

    private function runRace(string $holder, string $mode): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        $itemsBefore = $invoice->items()->get()->toArray();
        $bookingBefore = $booking->getRawOriginal();
        $reader = $this->observer();
        $this->makeRaceDirectory();
        $waiter = $holder === 'A' ? 'B' : 'A';
        // For timeout control both requests roll back; for the other cases waiter can proceed.
        $this->startWorker($holder, $invoice->id, $host->id, true, $mode === 'commit' ? 'commit' : 'rollback');
        $firstIdentity = $this->phase($holder, 'ready');
        $written = $this->phase($holder, 'written');
        $this->assertSame('PAID', $written['state']);
        $this->assertGreaterThan(0, $written['transaction_level']);
        $this->assertSame('UNPAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));
        $this->assertSame([], $this->logs($holder), 'Uncommitted holder must not emit success.');

        // Independent connection proves the exact invoice row is locked, without extra monitoring grants.
        $reader->beginTransaction();
        try {
            $reader->select('SELECT id FROM invoices WHERE id = ? FOR UPDATE NOWAIT', [$invoice->id]);
            $this->fail('NOWAIT must fail while holder owns the row lock.');
        } catch (QueryException $exception) {
            $this->assertSame(3572, $exception->errorInfo[1]);
            $this->saveEvidence('nowait', ['driver_code' => $exception->errorInfo[1]]);
        } finally {
            $reader->rollBack();
        }

        $this->startWorker($waiter, $invoice->id, $host->id, false, $mode === 'timeout' ? 'timeout' : 'commit');
        $secondIdentity = $this->phase($waiter, 'ready');
        foreach ([$firstIdentity, $secondIdentity] as $identity) {
            $this->assertSame(getenv('STAYHUB_UC36_TEST_DATABASE'), $identity['db']);
            $this->assertSame(getenv('STAYHUB_UC36_TEST_SERVER_UUID'), $identity['uuid']);
            $this->assertStringStartsWith('8.4.', $identity['version']);
            $this->assertNotSame(getmypid(), $identity['pid']);
        }
        $this->assertNotSame($firstIdentity['pid'], $secondIdentity['pid']);
        $this->assertNotSame($firstIdentity['connection_id'], $secondIdentity['connection_id']);
        $this->phase($waiter, 'locking');
        // SHOW FULL PROCESSLIST exposes this user's own connections; no PROCESS/global grant required.
        $active = $this->waitUntil(function () use ($reader, $secondIdentity) {
            foreach ($reader->select('SHOW FULL PROCESSLIST') as $process) {
                $sql = strtolower((string) $process->Info);
                if ((int) $process->Id === (int) $secondIdentity['connection_id']
                    && str_contains($sql, 'from `invoices`') && str_contains($sql, 'for update')) {
                    return ['connection_id' => (int) $process->Id, 'command' => $process->Command, 'state' => $process->State];
                }
            }
            return null;
        }, 'waiter locking SELECT must be active while holder is unreleased');
        $this->saveEvidence('waiter-active', $active);
        $this->assertFileDoesNotExist($this->raceDirectory.'/'.$waiter.'-result.json');
        $this->assertSame('UNPAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));

        if ($mode === 'timeout') {
            $timedOut = $this->phase($waiter, 'result');
            $this->assertSame(500, $timedOut['status']);
            $this->assertSame(0, $timedOut['successful_update_sql']);
            $this->assertContains(1205, array_column($timedOut['reported'], 'driver_code'));
            $this->assertSame([], $this->logs($waiter));
            $this->assertSame($before, $invoice->fresh()->getRawOriginal());
            $this->assertFileDoesNotExist($this->raceDirectory.'/release.json');
        }
        file_put_contents($this->raceDirectory.'/release.json', '{}');
        $first = $this->phase($holder, 'result');
        $second = $this->phase($waiter, 'result');
        $this->finishWorker($holder);
        $this->finishWorker($waiter);
        foreach ([$first, $second] as $result) {
            $this->assertSame(0, $result['transaction_level']);
            $this->assertSame(1, $result['locking_attempts'], 'One request must not silently retry the mutation.');
            if ($result['status'] === 500) {
                $this->assertSame(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
                    'code' => 'INTERNAL_SERVER_ERROR'], $result['body']);
            }
        }
        $this->assertSame(1, $first['successful_update_sql'], 'Holder must have reached actual SQL update.');
        if ($mode === 'timeout') {
            $this->assertSame(500, $first['status']);
            $this->assertSame($before, $invoice->fresh()->getRawOriginal());
            $this->assertSame([], [...$this->logs($holder), ...$this->logs($waiter)]);
        } else {
            $this->assertSame($mode === 'commit' ? 200 : 500, $first['status']);
            $this->assertSame(200, $second['status']);
            $this->assertSame($mode === 'commit' ? 0 : 1, $second['successful_update_sql']);
            if ($mode === 'commit') $this->assertSame($first['body'], $second['body']);
            $winner = $mode === 'commit' ? $holder : $waiter;
            $iso = $winner === 'A' ? '2026-11-17T01:10:00.000000Z' : '2026-11-17T02:00:00.000000Z';
            $this->assertSame('PAID', $second['body']['data']['payment_status']);
            $this->assertSame($iso, $second['body']['data']['paid_at']);
            $this->assertSame('208.50', $second['body']['data']['total_amount']);
            $this->assertCount(2, $second['body']['data']['items']);
            $this->assertSame($iso, $invoice->fresh()->paid_at->toISOString());
            $committed = $reader->table('invoices')->find($invoice->id);
            $this->assertSame('PAID', $committed->payment_status);
            $sqlTime = str_replace('T', ' ', substr($iso, 0, 19));
            $this->assertSame($sqlTime, $committed->paid_at);
            $this->assertSame($sqlTime, $committed->updated_at);
            $logs = [...$this->logs($holder), ...$this->logs($waiter)];
            $this->assertSame([['level' => 'info', 'message' => 'Xác nhận thanh toán hóa đơn thành công',
                'context' => ['invoice_id' => $invoice->id, 'payment_status' => 'PAID'],
                'transaction_level' => 0, 'pdo_transaction' => false]], $logs);
            if ($mode === 'rollback') $this->assertSame([], $this->logs($holder));
            $this->actingAs($host)->getJson('/api/host/invoices/'.$invoice->id)->assertOk()->assertExactJson($second['body']);
            $this->actingAs($customer)->getJson('/api/customer/invoices/'.$invoice->id)->assertOk()->assertExactJson($second['body']);
            $this->assertSame(Arr::except($before, ['payment_status', 'paid_at', 'updated_at']),
                Arr::except($invoice->fresh()->getRawOriginal(), ['payment_status', 'paid_at', 'updated_at']));
        }
        $this->assertSame($itemsBefore, $invoice->items()->get()->toArray());
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->saveEvidence('summary', ['mode' => $mode, 'holder' => $holder, 'waiter' => $waiter,
            'holder_identity' => $firstIdentity, 'waiter_identity' => $secondIdentity,
            'statuses' => [$first['status'], $second['status']],
            'successful_update_sql' => [$first['successful_update_sql'], $second['successful_update_sql']],
            'locking_attempts' => [$first['locking_attempts'], $second['locking_attempts']],
            'success_logs' => count([...$this->logs($holder), ...$this->logs($waiter)]), 'workers_exited' => true]);
    }

    private function makeRaceDirectory(): void
    {
        $root = realpath(getenv('STAYHUB_UC36_RACE_ARTIFACT_DIR') ?: '');
        $repository = realpath(dirname(__DIR__, 4));
        $this->assertNotFalse($root, 'Set an existing, owned external artifact directory.');
        $this->assertFalse(str_starts_with(strtolower($root.DIRECTORY_SEPARATOR), strtolower($repository.DIRECTORY_SEPARATOR)));
        $this->raceDirectory = $root.'/race-'.bin2hex(random_bytes(16));
        $this->assertTrue(mkdir($this->raceDirectory));
    }

    private function startWorker(string $label, int $invoiceId, int $hostId, bool $hold, string $mode, array $overrides = []): void
    {
        $environment = array_merge(getenv(), ['APP_DEBUG' => 'false', 'LOG_CHANNEL' => 'null'], $overrides);
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/Support/InvoicePaymentRaceWorker.php'], [
            0 => ['pipe', 'r'], 1 => ['file', $this->raceDirectory.'/'.$label.'-stdout.txt', 'w'],
            2 => ['file', $this->raceDirectory.'/'.$label.'-stderr.txt', 'w'],
        ], $pipes, dirname(__DIR__, 3), $environment, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        $this->workers[$label] = ['process' => $process];
        fwrite($pipes[0], json_encode(['directory' => $this->raceDirectory, 'label' => $label,
            'invoice_id' => $invoiceId, 'host_id' => $hostId, 'hold' => $hold, 'mode' => $mode,
            'empty_environment' => ['DB_URL' => $_ENV['DB_URL'] ?? getenv('DB_URL'),
                'DB_SOCKET' => $_ENV['DB_SOCKET'] ?? getenv('DB_SOCKET')],
            'clock' => $label === 'A' ? '2026-11-17T01:10:00Z' : '2026-11-17T02:00:00Z'], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
    }

    private function phase(string $label, string $phase): array
    {
        return $this->waitUntil(function () use ($label, $phase) {
            $path = $this->raceDirectory.'/'.$label.'-'.$phase.'.json';
            if (is_file($path)) {
                $data = json_decode(file_get_contents($path), true);
                if (is_array($data)) return $data;
            }
            $status = proc_get_status($this->workers[$label]['process']);
            if (!$status['running']) {
                $this->fail('Worker '.$label.' exited before '.$phase.': '.file_get_contents($this->raceDirectory.'/'.$label.'-stderr.txt'));
            }
            return null;
        }, $label.' must reach '.$phase);
    }

    private function finishWorker(string $label): void
    {
        $status = $this->waitUntil(function () use ($label) {
            $status = proc_get_status($this->workers[$label]['process']);
            return !$status['running'] ? $status : null;
        }, $label.' must exit after response');
        $this->assertSame(0, $status['exitcode']);
        proc_close($this->workers[$label]['process']);
        unset($this->workers[$label]);
    }

    private function waitUntil(callable $read, string $message): mixed
    {
        $deadline = microtime(true) + 8;
        do {
            $result = $read();
            if ($result !== null) return $result;
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Race deadline: '.$message);
    }

    private function logs(string $label): array
    {
        $path = $this->raceDirectory.'/'.$label.'-logs.jsonl';
        return !is_file($path) ? [] : array_map(static fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    private function saveEvidence(string $name, array $data): void
    {
        file_put_contents($this->raceDirectory.'/'.$name.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
