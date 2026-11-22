<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\User;
use App\Services\Chat\ChatTurnPersistenceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Tv3AcceptanceMySqlEnvironment;

final class ChatTurnPersistenceMySqlTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_22_000001_create_chat_turns_table.php';
    private const QUESTION = 'Tôi nên chuẩn bị gì trước chuyến đi?';
    private const ANSWER = 'Bạn nên kiểm tra giờ nhận phòng.';
    private static array $legacyMessages = [];

    private array $manifest;
    private ChatTurnPersistenceService $service;
    private CarbonImmutable $start;
    private User $actor;

    public function createApplication()
    {
        if (getenv('STAYHUB_TV3_AC_OPT_IN') === false) {
            $this->markTestSkipped('ChatData requires the explicit owned-schema runner.');
        }

        $this->manifest = Tv3AcceptanceMySqlEnvironment::beforeBoot(dirname(__DIR__, 3));
        $app = parent::createApplication();
        Tv3AcceptanceMySqlEnvironment::assertApplication($app, $this->manifest);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->start = CarbonImmutable::parse('2026-11-22T03:00:00Z');
        Date::setTestNow($this->start);

        $schema = $this->manifest['database'];
        if (! isset(self::$legacyMessages[$schema])) {
            if (DB::connection()->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('ChatData refuses to adopt a nonempty schema.');
            }

            $prior = array_filter(
                glob(database_path('migrations/*.php')),
                static fn (string $path): bool => basename($path) < basename(self::MIGRATION),
            );
            $this->artisan('migrate', ['--realpath' => true, '--path' => array_values($prior)])->assertExitCode(0);
            $legacy = User::factory()->customer()->create()->chatSessions()->create(['title' => 'Synthetic legacy session']);
            self::$legacyMessages[$schema] = $legacy->messages()->create([
                'sender' => 'USER',
                'message' => 'Synthetic legacy message',
            ])->only(['id', 'chat_session_id', 'sender', 'message']);
            $this->artisan('migrate', ['--path' => [self::MIGRATION]])->assertExitCode(0);
        }

        $this->service = $this->app->make(ChatTurnPersistenceService::class);
        $this->actor = User::factory()->customer()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_chat_migration_preserves_legacy_rows_and_scopes_unique_request_ids(): void
    {
        $legacy = self::$legacyMessages[$this->manifest['database']];
        $this->assertDatabaseHas('chat_messages', $legacy);
        $this->assertDatabaseHas('chat_sessions', ['id' => $legacy['chat_session_id'], 'title' => 'Synthetic legacy session']);

        $schema = DB::connection()->getSchemaBuilder();
        $this->assertTrue($schema->hasIndex('chat_turns', ['user_id', 'request_id'], 'unique'));
        $this->assertTrue($schema->hasIndex('chat_turns', ['created_at']));

        $id = (string) Str::uuid();
        $this->assertSame('claimed', $this->claim($id)['status']);
        $other = User::factory()->customer()->create();
        $this->assertSame('claimed', $this->service->claim($other->id, $id, null, self::QUESTION)['status']);
        $this->assertSame(2, ChatTurn::query()->where('request_id', $id)->count());

        $attributes = $this->turn($id)->getAttributes();
        unset($attributes['id']);
        try {
            DB::table('chat_turns')->insert($attributes);
            $this->fail('The database accepted a duplicate per-actor UUID.');
        } catch (QueryException $error) {
            $this->assertSame(1062, $error->errorInfo[1]);
        }
    }

    public function test_chat_success_commits_exactly_one_pair_and_replays_it(): void
    {
        $id = (string) Str::uuid();
        $claim = $this->claim(strtoupper($id), question: ' '.self::QUESTION.' ');
        $this->assertSame('claimed', $claim['status']);
        $this->assertSame('in_progress', $this->claim($id)['status']);
        $result = $this->complete($id, $claim['token']);
        $this->assertSame('committed', $result['status']);

        $reader = DB::build(config('database.connections.mysql'));
        try {
            $this->assertNotSame(DB::connection()->getPdo(), $reader->getPdo());
            $this->assertSame($this->manifest['database'], $reader->selectOne('SELECT DATABASE() AS db')->db);
            $this->assertSame($this->actor->id, $reader->table('chat_sessions')->find($result['session_id'])->user_id);
            $messages = $reader->table('chat_messages')->where('chat_session_id', $result['session_id'])->orderBy('id')->get();
            $this->assertSame([$result['user_message_id'], $result['assistant_message_id']], $messages->pluck('id')->all());
            $this->assertSame(['USER', 'ASSISTANT'], $messages->pluck('sender')->all());
            $this->assertSame([self::QUESTION, self::ANSWER], $messages->pluck('message')->all());
        } finally {
            $reader->disconnect();
        }

        $this->assertSame([...$result, 'status' => 'replayed'], $this->claim($id));
        $this->assertSame(1, $this->turn($id)->attempts);
        $this->assertSame('stale', $this->complete($id, $claim['token'])['status']);
        $this->assertSame(2, ChatMessage::query()->where('chat_session_id', $result['session_id'])->count());
        $this->assertArrayNotHasKey('message', $this->turn($id)->getAttributes());

        $nextId = (string) Str::uuid();
        $next = $this->claim($nextId, $result['session_id']);
        $this->assertSame('claimed', $next['status']);
        $this->assertSame($result['session_id'], $this->complete($nextId, $next['token'], $result['session_id'])['session_id']);
        $this->assertSame(4, ChatMessage::query()->where('chat_session_id', $result['session_id'])->count());
        $this->assertSame(1, $this->actor->chatSessions()->count());
    }

    public function test_chat_claim_rejects_missing_or_foreign_sessions_and_conflicting_payloads(): void
    {
        $id = (string) Str::uuid();
        $other = User::factory()->customer()->create();
        $foreign = $other->chatSessions()->create();
        $ownerless = ChatSession::query()->create(['title' => 'Synthetic ownerless session']);
        foreach ([$foreign->id, $ownerless->id, PHP_INT_MAX] as $sessionId) {
            $this->assertSame(['status' => 'not_found'], $this->claim($id, $sessionId));
        }
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $id]);

        $owned = $this->actor->chatSessions()->create();
        $claim = $this->claim($id, $owned->id);
        $snapshot = $this->turn($id)->getAttributes();
        $this->assertSame('conflict', $this->claim($id, $owned->id, 'Câu hỏi khác')['status']);
        $this->assertSame('conflict', $this->claim($id)['status']);
        $this->assertSame($snapshot, $this->turn($id)->getAttributes());

        $owned->update(['user_id' => $other->id]);
        $this->assertSame('not_found', $this->complete($id, $claim['token'], $owned->id)['status']);
        $this->assertSame(0, $owned->messages()->count());
    }

    public function test_chat_pair_rolls_back_and_an_expired_attempt_cannot_commit(): void
    {
        $id = (string) Str::uuid();
        $first = $this->claim($id);
        $messagesBefore = ChatMessage::query()->count();
        try {
            $this->service->complete($this->actor->id, $id, $first['token'], null, self::QUESTION, str_repeat('x', 65536));
            $this->fail('The assistant message exceeded the MySQL TEXT column capacity.');
        } catch (QueryException $error) {
            $this->assertSame(1406, $error->errorInfo[1]);
        }

        $this->assertSame(0, $this->actor->chatSessions()->count());
        $this->assertSame($messagesBefore, ChatMessage::query()->count());
        $this->assertSame(ChatTurn::PROCESSING, $this->turn($id)->status);
        $this->assertNull($this->turn($id)->user_message_id);

        Date::setTestNow($this->start->addSeconds(29));
        $this->assertSame('in_progress', $this->claim($id)['status']);
        Date::setTestNow($this->start->addSeconds(30));
        $this->assertSame('stale', $this->complete($id, $first['token'])['status']);
        $second = $this->claim($id);
        $this->assertSame('claimed', $second['status']);
        $this->assertNotSame($first['token'], $second['token']);
        $this->assertSame('stale', $this->complete($id, $first['token'])['status']);
        $this->assertFalse($this->service->fail($this->actor->id, $id, $first['token']));
        $this->assertSame('committed', $this->complete($id, $second['token'])['status']);
        $this->assertSame(2, $this->turn($id)->attempts);
    }

    public function test_chat_retries_stop_at_three_and_replay_expires_after_one_day(): void
    {
        $id = (string) Str::uuid();
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $claim = $this->claim($id);
            $this->assertSame('claimed', $claim['status']);
            $this->assertTrue($this->service->fail($this->actor->id, $id, $claim['token']));
            $this->assertDatabaseHas('chat_turns', ['request_id' => $id, 'attempts' => $attempt, 'status' => ChatTurn::FAILED]);
        }
        $this->assertSame('attempts_exhausted', $this->claim($id)['status']);
        $this->assertSame(0, $this->actor->chatSessions()->count());

        $successId = (string) Str::uuid();
        $success = $this->claim($successId);
        $this->assertSame('committed', $this->complete($successId, $success['token'])['status']);

        $lateId = (string) Str::uuid();
        $late = $this->claim($lateId);
        $this->assertTrue($this->service->fail($this->actor->id, $lateId, $late['token']));
        Date::setTestNow($this->start->addDay()->subSecond());
        $this->assertSame('attempts_exhausted', $this->claim($id)['status']);
        $this->assertSame('replayed', $this->claim($successId)['status']);
        $lastSecond = $this->claim($lateId);
        $this->assertSame('claimed', $lastSecond['status']);

        Date::setTestNow($this->start->addDay());
        $this->assertSame('expired', $this->claim($id)['status']);
        $this->assertSame('expired', $this->claim($successId)['status']);
        $this->assertSame('stale', $this->complete($lateId, $lastSecond['token'])['status']);
        $this->assertSame(3, $this->turn($id)->attempts);
    }

    public function test_chat_foreign_keys_preserve_tombstones_until_user_deletion(): void
    {
        $id = (string) Str::uuid();
        $session = $this->actor->chatSessions()->create();
        $claim = $this->claim($id, $session->id);
        $result = $this->complete($id, $claim['token'], $session->id);
        ChatMessage::query()->whereKey($result['assistant_message_id'])->delete();
        $this->assertNull($this->turn($id)->assistant_message_id);
        $this->assertSame('result_unavailable', $this->claim($id, $session->id)['status']);
        $this->assertSame(1, $session->messages()->count());

        $session->delete();
        $this->assertNull($this->turn($id)->chat_session_id);
        $this->assertNull($this->turn($id)->user_message_id);
        $this->assertSame('result_unavailable', $this->claim($id, $session->id)['status']);
        $this->assertSame(0, $this->actor->chatSessions()->count());
        $this->assertSame(ChatTurn::SUCCEEDED, $this->turn($id)->status);

        $pendingId = (string) Str::uuid();
        $this->assertSame('claimed', $this->claim($pendingId)['status']);
        $remainingId = (string) Str::uuid();
        $remainingClaim = $this->claim($remainingId);
        $remaining = $this->complete($remainingId, $remainingClaim['token']);
        $this->assertSame('committed', $remaining['status']);
        $this->actor->delete();
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $id]);
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $pendingId]);
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $remainingId]);
        $this->assertDatabaseMissing('chat_sessions', ['id' => $remaining['session_id']]);
        $this->assertDatabaseMissing('chat_messages', ['id' => $remaining['user_message_id']]);
        $this->assertDatabaseMissing('chat_messages', ['id' => $remaining['assistant_message_id']]);
    }

    public function test_chat_metadata_can_be_pruned_without_deleting_messages(): void
    {
        $id = (string) Str::uuid();
        $claim = $this->claim($id);
        $result = $this->complete($id, $claim['token']);
        $this->assertSame('committed', $result['status']);

        Date::setTestNow($this->start->addSecond());
        $recentId = (string) Str::uuid();
        $this->assertSame('claimed', $this->claim($recentId)['status']);

        Date::setTestNow($this->start->addDays(7));
        $cutoff = Date::now()->subDays(ChatTurnPersistenceService::RETENTION_DAYS);
        $this->assertSame(1, ChatTurn::query()->where('user_id', $this->actor->id)
            ->where('created_at', '<=', $cutoff)->delete());
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $id]);
        $this->assertDatabaseHas('chat_turns', ['request_id' => $recentId]);
        $this->assertDatabaseHas('chat_sessions', ['id' => $result['session_id']]);
        $this->assertDatabaseHas('chat_messages', ['id' => $result['user_message_id']]);
        $this->assertDatabaseHas('chat_messages', ['id' => $result['assistant_message_id']]);
    }


    private function claim(string $id, ?int $sessionId = null, string $question = self::QUESTION): array
    {
        return $this->service->claim($this->actor->id, $id, $sessionId, $question);
    }

    private function complete(string $id, string $token, ?int $sessionId = null): array
    {
        return $this->service->complete($this->actor->id, $id, $token, $sessionId, self::QUESTION, self::ANSWER);
    }

    private function turn(string $id): ChatTurn
    {
        return ChatTurn::query()->where('user_id', $this->actor->id)->where('request_id', $id)->firstOrFail();
    }
}
