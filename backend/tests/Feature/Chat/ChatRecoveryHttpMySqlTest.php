<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\ChatTurn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class ChatRecoveryHttpMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private array $workers = [];
    private int $time;
    private User $actor;
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        DB::rollBack(); // Workers and the observer must see real commits.
        $this->time = time();
        $this->actor = User::factory()->customer()->create();
        $this->payload = ['request_id' => (string) Str::uuid(), 'session_id' => null, 'message' => 'Câu hỏi tổng hợp về lưu trú.'];
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->workers as [$process, $input]) {
                $input->close();
                if ($process->isRunning()) {
                    $process->stop(0.5);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_chat_recovery_concurrent_duplicate_only_calls_provider_once(): void
    {
        $this->spawn('first', 'hold');
        $this->awaitProvider('first');
        $this->spawn('duplicate', 'normal');

        $busy = $this->finish('duplicate', 'response');
        $this->assertSame(409, $busy['status']);
        $this->assertSame('CHAT_IN_PROGRESS', $busy['body']['code']);
        $this->assertSame(0, $this->providerCalls('duplicate'));
        $this->assertSame(1, $this->turn()->attempts);
        $this->assertSame(0, $this->actor->chatSessions()->count());

        $this->release('first');
        $winner = $this->finish('first', 'response');
        $this->assertSame(201, $winner['status']);
        $this->assertSame(1, $this->providerCalls('first'));
        $this->assertPair($winner['body']['data']);
    }

    public function test_chat_recovery_replays_after_committed_response_is_dropped(): void
    {
        $this->spawn('dropped', 'drop');
        $this->assertSame(201, $this->finish('dropped', 'dropped')['status']);
        $row = $this->turn();
        $this->assertSame(ChatTurn::SUCCEEDED, $row->status);
        $this->assertSame(1, $this->providerCalls('dropped'));

        $this->spawn('retry', 'normal');
        $result = $this->finish('retry', 'response');
        $this->assertSame(200, $result['status']);
        $this->assertSame($row->chat_session_id, $result['body']['data']['session_id']);
        $this->assertSame($row->user_message_id, $result['body']['data']['user_message']['id']);
        $this->assertSame($row->assistant_message_id, $result['body']['data']['assistant_message']['id']);
        $this->assertSame(0, $this->providerCalls('retry'));
        $this->assertSame(1, $this->turn()->attempts);
        $this->assertPair($result['body']['data']);
    }

    public function test_chat_recovery_late_worker_reads_winner_after_lease_takeover(): void
    {
        $this->spawn('late', 'hold');
        $this->awaitProvider('late');
        $this->spawn('winner', 'normal', 31);
        $winner = $this->finish('winner', 'response');
        $this->assertSame(201, $winner['status']);

        $this->release('late', 31);
        $late = $this->finish('late', 'response');
        $this->assertSame(200, $late['status']);
        $this->assertSame($winner['body'], $late['body']);
        $this->assertSame('Phản hồi tổng hợp đã hoàn tất.', $late['body']['data']['assistant_message']['message']);
        $this->assertSame(1, $this->providerCalls('late'));
        $this->assertSame(1, $this->providerCalls('winner'));
        $this->assertSame(2, $this->turn()->attempts);
        $this->assertPair($winner['body']['data']);
    }

    public function test_chat_recovery_expired_worker_without_success_returns_retry_required(): void
    {
        $this->spawn('expired', 'hold');
        $this->awaitProvider('expired');
        $this->release('expired', 31);
        $expired = $this->finish('expired', 'response');
        $this->assertSame(409, $expired['status']);
        $this->assertSame('CHAT_RETRY_REQUIRED', $expired['body']['code']);
        $this->assertSame(0, $this->actor->chatSessions()->count());

        $this->spawn('takeover', 'normal', 31);
        $retry = $this->finish('takeover', 'response');
        $this->assertSame(201, $retry['status']);
        $this->assertSame(2, $this->turn()->attempts);
        $this->assertPair($retry['body']['data']);
    }

    public function test_chat_recovery_insufficient_slot_budget_does_not_call_provider(): void
    {
        $this->spawn('budget', 'budget');
        $result = $this->finish('budget', 'response');
        $this->assertSame(409, $result['status']);
        $this->assertSame('CHAT_RETRY_REQUIRED', $result['body']['code']);
        $this->assertSame(0, $this->providerCalls('budget'));
        $this->assertSame(ChatTurn::FAILED, $this->turn()->status);
        $this->assertSame(0, $this->actor->chatSessions()->count());
    }

    private function spawn(string $label, string $mode, int $offset = 0): void
    {
        $input = new InputStream;
        $input->write(json_encode([
            'actor_id' => $this->actor->id, 'payload' => $this->payload,
            'time' => $this->time + $offset, 'mode' => $mode,
        ], JSON_THROW_ON_ERROR)."\n");
        if ($mode !== 'hold') {
            $input->close();
        }

        $process = new Process([PHP_BINARY, base_path('tests/Support/ChatRecoveryHttpWorker.php')], base_path(), input: $input, timeout: 15);
        $this->workers[$label] = [$process, $input];
        $process->start();
        $directory = getenv('STAYHUB_CHAT_RECOVERY_ARTIFACTS');
        $this->assertIsString($directory);
        $this->assertSame(realpath(dirname(getenv('STAYHUB_TV3_AC_MANIFEST')).'/lifecycle'), realpath($directory));
        $this->assertIsInt($process->getPid());
        $written = file_put_contents($directory.'/'.$this->name().'-'.$label.'-spawn.json',
            json_encode(['pid' => $process->getPid()], JSON_THROW_ON_ERROR));
        $this->assertNotFalse($written);
    }

    private function release(string $label, int $advance = 0): void
    {
        $input = $this->workers[$label][1];
        $input->write(json_encode(['advance' => $advance], JSON_THROW_ON_ERROR)."\n");
        $input->close();
    }

    private function awaitProvider(string $label): void
    {
        $process = $this->workers[$label][0];
        $deadline = microtime(true) + 8;
        do {
            if ($this->providerCalls($label) > 0) {
                return;
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->fail('Worker did not reach the fake provider. '.$process->getOutput().$process->getErrorOutput());
    }

    private function finish(string $label, string $event): array
    {
        $process = $this->workers[$label][0];
        $exit = $process->wait();
        $this->assertSame(0, $exit, $process->getErrorOutput());
        foreach ($this->events($label) as $message) {
            if (($message['event'] ?? null) === $event) {
                return $message;
            }
        }
        $this->fail('Missing '.$event.' event. '.$process->getOutput());
    }

    private function providerCalls(string $label): int
    {
        return count(array_filter($this->events($label), static fn ($event) => ($event['event'] ?? null) === 'provider'));
    }

    private function events(string $label): array
    {
        $lines = preg_split('/\R/', trim($this->workers[$label][0]->getOutput()), flags: PREG_SPLIT_NO_EMPTY);

        return array_map(static fn (string $line) => json_decode($line, true), $lines);
    }

    private function turn(): ChatTurn
    {
        return ChatTurn::query()->where('user_id', $this->actor->id)
            ->where('request_id', $this->payload['request_id'])->firstOrFail();
    }

    private function assertPair(array $data): void
    {
        $this->assertSame(1, $this->actor->chatSessions()->count());
        $messages = ChatMessage::query()->where('chat_session_id', $data['session_id'])->orderBy('id')->get();
        $this->assertSame([$data['user_message']['id'], $data['assistant_message']['id']], $messages->pluck('id')->all());
        $this->assertSame(['USER', 'ASSISTANT'], $messages->pluck('sender')->all());
    }
}
