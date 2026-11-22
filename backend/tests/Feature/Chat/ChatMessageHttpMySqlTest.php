<?php

namespace Tests\Feature\Chat;

use App\Enums\UserStatus;
use App\Exceptions\Chat\ChatProviderException;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\User;
use App\Services\Chat\ChatProvider;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class ChatMessageHttpMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private const URL = '/api/customer/chat/messages';
    private const ORIGIN = 'http://localhost:5173';
    private const QUESTION = 'Tôi nên kiểm tra gì trước chuyến đi?';
    private const ANSWER = 'Bạn nên xem giờ nhận phòng và chính sách hủy.';

    /** @var list<string> */
    private array $questions = [];
    private ?ChatProviderException $failure = null;
    private bool $outboundBlocked = false;

    protected function setUp(): void
    {
        parent::setUp();
        DB::rollBack(); // End the helper's empty outer transaction before testing real commits.

        config()->set([
            'sanctum.stateful' => ['localhost:5173'],
            'chatbot.enabled' => true,
            'chatbot.allow_external_questions' => true,
            'chatbot.rate_limit_per_minute' => 10,
            'chatbot.max_concurrent' => 2,
            'chatbot.timeout_ms' => 12000,
            'cache.stores.database.connection' => 'mysql',
            'cache.stores.database.lock_connection' => 'mysql',
            'cache.stores.database.table' => 'cache',
            'cache.stores.database.lock_table' => 'cache_locks',
        ]);
        Http::preventStrayRequests();
        Http::fake([]);
        $this->outboundBlocked = true;
        $this->app->instance(ChatProvider::class, new class($this->answer(...)) implements ChatProvider {
            public function __construct(private readonly \Closure $answer) {}

            public function answer(string $approvedQuestion): string
            {
                return ($this->answer)($approvedQuestion);
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            if ($this->outboundBlocked) {
                Http::assertNothingSent();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_customer_chat_persists_and_replays_exactly_two_messages(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22T03:00:00Z'));
        $actor = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $input = $this->input();
        $withoutSession = $input;
        unset($withoutSession['session_id']);
        $response = $this->send([
            ...$withoutSession, 'user_id' => $other->id, 'sender' => 'ASSISTANT',
        ], $actor)->assertCreated();

        $stored = $this->turn($input);
        $time = '2026-11-22T03:00:00.000000Z';
        $first = [
            'request_id' => $input['request_id'],
            'session_id' => $stored->chat_session_id,
            'user_message' => [
                'id' => $stored->user_message_id, 'sender' => 'USER',
                'message' => self::QUESTION, 'created_at' => $time,
            ],
            'assistant_message' => [
                'id' => $stored->assistant_message_id, 'sender' => 'ASSISTANT',
                'message' => self::ANSWER, 'created_at' => $time,
            ],
        ];
        $response->assertExactJson(['data' => $first]);
        $this->assertSame(0, $other->chatSessions()->count());
        foreach (['user_message', 'assistant_message'] as $key) {
            $message = $first[$key];
            $this->assertDatabaseHas('chat_messages', [
                'id' => $message['id'], 'chat_session_id' => $first['session_id'],
                'sender' => $message['sender'], 'message' => $message['message'],
                'created_at' => '2026-11-22 03:00:00',
            ]);
        }

        $this->assertSame($input['request_id'], $first['request_id']);
        $this->assertSame(self::QUESTION, $first['user_message']['message']);
        $this->assertSame(self::ANSWER, $first['assistant_message']['message']);
        $this->assertSame('USER', $first['user_message']['sender']);
        $this->assertSame('ASSISTANT', $first['assistant_message']['sender']);
        $this->assertStringEndsWith('Z', $first['user_message']['created_at']);
        $this->assertDatabaseHas('chat_sessions', ['id' => $first['session_id'], 'user_id' => $actor->id]);
        $this->assertSame(2, ChatMessage::query()->where('chat_session_id', $first['session_id'])->count());

        $this->send($input, $actor)->assertOk()->assertExactJson(['data' => $first]);
        $this->assertSame($stored->getAttributes(), $this->turn($input)->getAttributes());
        $this->send([...$input, 'message' => 'Câu hỏi khác'], $actor)->assertConflict()
            ->assertJsonPath('code', 'CHAT_REQUEST_CONFLICT');
        $this->assertSame([self::QUESTION], $this->questions);
        $this->assertSame(2, ChatMessage::query()->where('chat_session_id', $first['session_id'])->count());

        $next = $this->input($first['session_id']);
        $this->send($next, $actor)->assertCreated()->assertJsonPath('data.session_id', $first['session_id']);
        $this->assertSame(4, ChatMessage::query()->where('chat_session_id', $first['session_id'])->count());
        $this->assertSame([self::QUESTION, self::QUESTION], $this->questions);
    }

    public function test_customer_chat_rejects_requests_outside_the_web_session_or_owner_scope(): void
    {
        $input = $this->input();
        $before = $this->chatCounts();
        $this->send([...$input, 'message' => []])->assertUnauthorized();

        $actor = User::factory()->customer()->create();
        Auth::forgetGuards();
        $this->postJson(self::URL, $input, [
            'Authorization' => 'Bearer '.$actor->createToken('chat-test')->plainTextToken,
        ])->assertUnauthorized();

        $this->send([...$input, 'message' => []], User::factory()->host()->create())->assertForbidden();
        $this->send($input, User::factory()->admin()->create())->assertForbidden();
        $this->send($input, User::factory()->customer()->blocked()->create())->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_BLOCKED');
        $inactive = User::factory()->customer()->create(['status' => UserStatus::INACTIVE]);
        $this->send([...$input, 'request_id' => []], $inactive)->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_INACTIVE');

        $this->assertSame($before, $this->chatCounts());
        $foreign = ChatSession::query()->create(['user_id' => User::factory()->customer()->create()->id]);
        $before = $this->chatCounts();
        $denied = $this->send([...$input, 'session_id' => $foreign->id], $actor)->assertNotFound()
            ->assertJsonPath('code', 'CHAT_SESSION_NOT_FOUND');
        $this->send([...$input, 'session_id' => PHP_INT_MAX], $actor)->assertNotFound()->assertExactJson($denied->json());
        $this->send([...$input, 'session_id' => ''], $actor)->assertUnprocessable()->assertJsonValidationErrors('session_id');
        $this->send([...$input, 'session_id' => (string) $foreign->id], $actor)->assertUnprocessable()
            ->assertJsonValidationErrors('session_id');
        $this->send([...$input, 'message' => '   ', 'request_id' => 'bad'], $actor)->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'request_id']);
        $this->send([...$input, 'message' => str_repeat('x', 2001)], $actor)->assertUnprocessable()
            ->assertJsonValidationErrors('message');
        $this->assertSame([], $this->questions);
        $this->assertSame($before, $this->chatCounts());
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        $this->assertSame(0, $actor->chatSessions()->count());
    }

    public function test_customer_chat_retry_after_timeout_and_provider_rate_limit_preserves_the_request(): void
    {
        $actor = User::factory()->customer()->create();
        $input = $this->input();
        $this->failure = new ChatProviderException(ChatProviderException::TIMEOUT);
        $this->send($input, $actor)->assertStatus(504)->assertJsonPath('code', ChatProviderException::TIMEOUT);
        $this->assertDatabaseHas('chat_turns', ['request_id' => $input['request_id'], 'attempts' => 1, 'status' => ChatTurn::FAILED]);
        $this->assertSame(0, $actor->chatSessions()->count());

        $this->failure = new ChatProviderException(ChatProviderException::RATE_LIMITED, 7);
        $this->send($input, $actor)->assertStatus(503)->assertHeader('Retry-After', '7')
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After')
            ->assertJsonPath('code', ChatProviderException::RATE_LIMITED);
        $this->assertSame(0, $actor->chatSessions()->count());

        $this->failure = null;
        $this->send($input, $actor)->assertCreated();
        $this->assertDatabaseHas('chat_turns', ['request_id' => $input['request_id'], 'attempts' => 3, 'status' => ChatTurn::SUCCEEDED]);
        $this->assertSame([self::QUESTION, self::QUESTION, self::QUESTION], $this->questions);
        $this->assertSame(1, $actor->chatSessions()->count());
    }

    public function test_customer_chat_limits_requests_per_actor_and_keeps_replay_available_when_slots_are_busy(): void
    {
        $actor = User::factory()->customer()->create();
        $input = $this->input();
        $this->send($input, $actor)->assertCreated();

        $busy = $this->input();
        $slots = [Cache::store('database')->lock('customer-chat:provider-slot:0', 30),
            Cache::store('database')->lock('customer-chat:provider-slot:1', 30)];
        $this->assertTrue($slots[0]->get());
        $this->assertTrue($slots[1]->get());
        try {
            $this->send($busy, $actor)->assertStatus(503)->assertJsonPath('code', 'CHAT_BUSY');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $busy['request_id']]);
            $this->send($input, $actor)->assertOk();
            $this->assertSame([self::QUESTION], $this->questions);
        } finally {
            foreach ($slots as $slot) {
                $slot->release();
            }
        }

        $this->send($busy, $actor)->assertCreated();
        for ($request = 5; $request <= 10; $request++) {
            $this->send($input, $actor)->assertOk();
        }
        $limited = $this->input();
        $before = $this->chatCounts();
        $this->send($limited, $actor)->assertTooManyRequests()->assertHeader('Retry-After')
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $limited['request_id']]);
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([self::QUESTION, self::QUESTION], $this->questions);

        $other = User::factory()->customer()->create();
        $this->send($this->input(), $other)->assertCreated();
        $this->assertCount(3, $this->questions);
    }

    public function test_customer_chat_blocks_external_input_before_claim_when_gate_is_closed(): void
    {
        config()->set('chatbot.allow_external_questions', false);

        $actor = User::factory()->customer()->create();
        $input = $this->input();
        $this->send($input, $actor)->assertStatus(503)->assertJsonPath('code', ChatProviderException::UNAVAILABLE);
        $this->assertSame([], $this->questions);
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        $this->assertSame(0, $actor->chatSessions()->count());
        Http::assertNothingSent();
    }

    public function test_customer_chat_rejects_missing_and_mismatched_csrf_before_workflow(): void
    {
        config()->set('sanctum.middleware.validate_csrf_token', EnforceChatCsrfForTests::class);

        $actor = User::factory()->customer()->create();
        $input = $this->input();
        $token = Str::random(40);
        $before = $this->chatCounts();

        $this->withSession(['_token' => $token]);
        $this->send($input, $actor)->assertStatus(419);
        $this->withSession(['_token' => $token]);
        $this->send($input, $actor, ['X-CSRF-TOKEN' => 'wrong'])->assertStatus(419);
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);

        $this->withSession(['_token' => $token]);
        $this->send($input, $actor, ['X-CSRF-TOKEN' => $token])->assertCreated();
        $this->assertSame([self::QUESTION], $this->questions);
    }

    public function test_customer_chat_validates_unicode_length_and_raw_json_types(): void
    {
        $actor = User::factory()->customer()->create();
        $input = $this->input();
        $before = $this->chatCounts();
        $max = str_repeat('ắ', 2000);

        $missingId = $input;
        unset($missingId['request_id']);
        $invalid = [
            [$missingId, 'request_id'],
            [[...$input, 'request_id' => 123], 'request_id'],
            [[...$input, 'session_id' => 1.25], 'session_id'],
            [[...$input, 'session_id' => 0], 'session_id'],
            [[...$input, 'session_id' => -1], 'session_id'],
            [[...$input, 'session_id' => true], 'session_id'],
            [[...$input, 'message' => $max.'ắ'], 'message'],
        ];
        foreach ($invalid as [$payload, $field]) {
            $this->send($payload, $actor)->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->useWebActor($actor);
        $this->call('POST', self::URL, server: [
            'HTTP_ORIGIN' => self::ORIGIN, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], content: '{"message":')->assertUnprocessable()->assertJsonValidationErrors(['request_id', 'message']);
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);

        $this->send([...$input, 'message' => ' '.$max.' '], $actor)->assertCreated()
            ->assertJsonPath('data.user_message.message', $max);
        $this->assertSame([$max], $this->questions);
    }

    public function test_customer_chat_exposes_expired_and_deleted_results_without_writing_messages(): void
    {
        $start = Carbon::parse('2026-11-22T03:00:00Z');
        $this->travelTo($start);
        $actor = User::factory()->customer()->create();
        $expiring = $this->input();
        $this->send($expiring, $actor)->assertCreated();
        $this->travelTo($start->copy()->addHours(24));

        $this->assertTerminalConflict($expiring, $actor, 'CHAT_REQUEST_EXPIRED');

        $deleted = $this->input();
        $result = $this->send($deleted, $actor)->assertCreated()->json('data');
        ChatMessage::query()->whereKey($result['assistant_message']['id'])->delete();
        $this->assertTerminalConflict($deleted, $actor, 'CHAT_RESULT_UNAVAILABLE');
    }

    public function test_customer_chat_reports_provider_unavailable_and_caps_attempts(): void
    {
        $actor = User::factory()->customer()->create();
        $input = $this->input();
        $before = $this->chatCounts();
        $this->failure = new ChatProviderException(ChatProviderException::UNAVAILABLE);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->send($input, $actor)->assertStatus(503)->assertHeaderMissing('Retry-After')
                ->assertExactJson([
                    'message' => 'Trợ lý chưa thể trả lời lúc này. Vui lòng thử lại sau.',
                    'code' => 'CHAT_PROVIDER_UNAVAILABLE',
                ]);
            $this->assertSame($attempt, $this->turn($input)->attempts);
            $this->assertSame(ChatTurn::FAILED, $this->turn($input)->status);
        }
        $this->assertSame([$before[0] + 1, $before[1], $before[2]], $this->chatCounts());
        $this->assertSame(array_fill(0, 3, self::QUESTION), $this->questions);
        $this->failure = null;
        $this->assertTerminalConflict($input, $actor, 'CHAT_ATTEMPTS_EXHAUSTED');
    }

    private function assertTerminalConflict(array $input, User $actor, string $code): void
    {
        $before = $this->chatCounts();
        $turn = $this->turn($input)->getAttributes();
        $calls = $this->questions;

        $this->send($input, $actor)->assertConflict()->assertJsonPath('code', $code);
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame($turn, $this->turn($input)->getAttributes());
        $this->assertSame($calls, $this->questions);
    }

    private function turn(array $input): ChatTurn
    {
        return ChatTurn::query()->where('request_id', $input['request_id'])->firstOrFail();
    }

    private function chatCounts(): array
    {
        return [ChatTurn::query()->count(), ChatSession::query()->count(), ChatMessage::query()->count()];
    }

    private function input(?int $sessionId = null): array
    {
        return ['request_id' => (string) Str::uuid(), 'session_id' => $sessionId, 'message' => self::QUESTION];
    }

    private function send(array $input, ?User $actor = null, array $headers = []): TestResponse
    {
        $this->useWebActor($actor);

        return $this->postJson(self::URL, $input, ['Origin' => self::ORIGIN, ...$headers]);
    }

    private function useWebActor(?User $actor): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        if ($actor !== null) {
            $this->actingAs($actor, 'web');
        }

    }

    private function answer(string $question): string
    {
        $this->assertSame(0, DB::transactionLevel());
        $this->questions[] = $question;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return self::ANSWER;
    }
}

final class EnforceChatCsrfForTests extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
