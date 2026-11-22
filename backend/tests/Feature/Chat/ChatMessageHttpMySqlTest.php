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
    private const FAQ_QUESTION = 'Làm thế nào để đặt phòng?';
    private const FAQ_ANSWER = 'Chọn cơ sở, phòng và ngày lưu trú, sau đó đăng nhập để gửi yêu cầu đặt phòng.';
    private const FAQ_MISSING = 'Chưa tìm thấy câu trả lời trong FAQ công khai.';
    private const CATALOG_QUESTION = 'Catalog: Tìm phòng cho 4 người dưới 1 triệu';
    private const CATALOG_EMPTY = 'Chưa tìm thấy phòng phù hợp. Bạn có thể đổi tiêu chí trên trang tìm kiếm.';
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent';
    private const AVAILABILITY_INTENT = ['intent' => 'check_availability', 'room' => 'ROOM', 'check_in' => 'DATE_A', 'check_out' => 'DATE_B', 'guests' => 'GUESTS'];

    /** @var list<string> */
    private array $questions = [];
    /** @var list<int> */
    private array $faqIds = [];
    /** @var list<int> */
    private array $catalogHostIds = [];
    /** @var list<int> */
    private array $amenityIds = [];
    /** @var list<int> */
    private array $availabilityBookingIds = [];

    private const PROPERTY_INTENT = ['intent' => 'check_availability', 'property' => 'PROPERTY', 'check_in' => 'WEEKEND', 'check_out' => 'WEEKEND', 'guests' => 'GUESTS'];
    private ?ChatProviderException $failure = null;
    private bool $outboundBlocked = false;
    private bool $availabilityHttpFake = false;

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
            if ($this->faqIds !== []) {
                DB::table('faqs')->whereIn('id', $this->faqIds)->delete();
            }
            if ($this->availabilityBookingIds !== []) {
                DB::table('bookings')->whereIn('id', $this->availabilityBookingIds)->delete();
            }
            if ($this->catalogHostIds !== []) {
                DB::table('users')->whereIn('id', $this->catalogHostIds)->delete();
            }
            if ($this->amenityIds !== []) {
                DB::table('amenities')->whereIn('id', $this->amenityIds)->delete();
            }
            if ($this->outboundBlocked) {
                if ($this->availabilityHttpFake) {
                    foreach (Http::recorded() as [$request]) {
                        $this->assertSame(self::GEMINI_URL, $request->url());
                    }
                } else {
                    Http::assertNothingSent();
                }
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

    public function test_customer_chat_persists_public_faq_without_ai_or_provider_capacity(): void
    {
        config()->set(['chatbot.enabled' => false, 'chatbot.allow_external_questions' => false]);
        $actor = User::factory()->customer()->create();
        $this->insertFaq();
        $input = [...$this->input(), 'message' => 'FAQ: LÀM THẾ NÀO ĐỂ  ĐẶT PHÒNG?!'];
        $before = $this->chatCounts();
        $slots = [Cache::store('database')->lock('customer-chat:provider-slot:0', 30),
            Cache::store('database')->lock('customer-chat:provider-slot:1', 30)];
        try {
            $this->assertTrue($slots[0]->get());
            $this->assertTrue($slots[1]->get());
            $response = $this->send($input, $actor)->assertCreated();
        } finally {
            foreach ($slots as $slot) {
                $slot->release();
            }
        }

        $result = $response->json('data');
        $this->assertSame($input['request_id'], $result['request_id']);
        $this->assertDatabaseHas('chat_sessions', ['id' => $result['session_id'], 'user_id' => $actor->id]);
        $this->assertDatabaseHas('chat_turns', [
            'request_id' => $input['request_id'], 'user_id' => $actor->id, 'chat_session_id' => $result['session_id'],
            'status' => ChatTurn::SUCCEEDED, 'attempts' => 1, 'user_message_id' => $result['user_message']['id'],
            'assistant_message_id' => $result['assistant_message']['id'],
        ]);
        foreach (['user_message' => ['USER', $input['message']], 'assistant_message' => ['ASSISTANT', self::FAQ_ANSWER]] as $key => [$sender, $message]) {
            $response->assertJsonPath("data.$key.sender", $sender)->assertJsonPath("data.$key.message", $message);
            $this->assertDatabaseHas('chat_messages', [
                'id' => $result[$key]['id'], 'chat_session_id' => $result['session_id'], 'sender' => $sender, 'message' => $message,
            ]);
        }
        $this->assertSame([$before[0] + 1, $before[1] + 1, $before[2] + 2], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_replays_saved_faq_without_querying_current_faq(): void
    {
        $actor = User::factory()->customer()->create();
        $id = $this->insertFaq();
        $input = [...$this->input(), 'message' => self::FAQ_QUESTION];
        $first = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', self::FAQ_ANSWER)->json();
        DB::table('faqs')->where('id', $id)->update(['status' => false]);
        $stored = $this->turn($input)->getAttributes();
        $before = $this->chatCounts();

        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($first);
            $this->send([...$input, 'message' => 'FAQ: Câu hỏi khác'], $actor)
                ->assertConflict()->assertJsonPath('code', 'CHAT_REQUEST_CONFLICT');
            $other = User::factory()->customer()->create();
            $this->send([...$this->input($first['data']['session_id']), 'message' => self::FAQ_QUESTION], $other)
                ->assertNotFound()->assertJsonPath('code', 'CHAT_SESSION_NOT_FOUND');
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertStringNotContainsString('faqs', strtolower(implode("\n", $queries)));
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame($stored, $this->turn($input)->getAttributes());

        $next = [...$this->input($first['data']['session_id']), 'message' => self::FAQ_QUESTION];
        $this->send($next, $actor)->assertCreated()->assertJsonPath('data.session_id', $first['data']['session_id'])
            ->assertJsonPath('data.assistant_message.message', self::FAQ_MISSING);
        $this->assertSame([$before[0] + 1, $before[1], $before[2] + 2], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_saves_safe_fallbacks_for_missing_or_unapproved_faq_rows(): void
    {
        $actor = User::factory()->customer()->create();
        $input = [...$this->input(), 'message' => self::FAQ_QUESTION];
        $first = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', self::FAQ_MISSING)->json('data');
        $sessionId = $first['session_id'];
        $id = $this->insertFaq('Nội dung chưa duyệt.');
        $this->send([...$this->input($sessionId), 'message' => self::FAQ_QUESTION], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', self::FAQ_MISSING);

        DB::table('faqs')->where('id', $id)->update(['answer' => self::FAQ_ANSWER]);
        $this->insertFaq();
        $this->send([...$this->input($sessionId), 'message' => self::FAQ_QUESTION], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', self::FAQ_MISSING);
        $this->assertSame(3, ChatMessage::query()->where('chat_session_id', $sessionId)
            ->where('sender', 'ASSISTANT')->where('message', self::FAQ_MISSING)->count());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_rejects_unsupported_explicit_faq_without_claiming_or_calling_provider(): void
    {
        $actor = User::factory()->customer()->create();
        $question = 'Có được mang thú cưng không?';
        $this->insertFaq('Nội dung chưa duyệt.', $question);
        $input = [...$this->input(), 'message' => 'FAQ: '.$question];
        $before = $this->chatCounts();

        $this->send($input, $actor)->assertUnprocessable()
            ->assertJsonPath('errors.message.0', 'FAQ công khai hiện chỉ hỗ trợ câu hỏi “Làm thế nào để đặt phòng?”.');
        $this->assertSame($before, $this->chatCounts());
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_saves_public_catalog_with_exact_budget_and_guest_filters(): void
    {
        config()->set(['chatbot.enabled' => false, 'chatbot.allow_external_questions' => false]);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $selected = $this->catalogProperty($host, 'Cơ sở Gió QA');
        $other = $this->catalogProperty($host, 'Cơ sở Biển QA');
        $hidden = $this->catalogProperty($host, 'Cơ sở Ẩn QA', 'INACTIVE');
        $this->catalogRoom($selected, 'Phòng Bằng Biên', '1000000.00');
        $this->catalogRoom($selected, 'Phòng Dưới Biên A', '999999.00');
        $this->catalogRoom($selected, 'Phòng Dưới Biên B', '999999.00');
        $this->catalogRoom($selected, 'Phòng Trên Biên', '1000001.00');
        $this->catalogRoom($selected, 'Phòng Ba Khách', '100000.00', capacity: 3);
        $this->catalogRoom($selected, 'Phòng Ẩn', '100000.00', status: 'INACTIVE');
        $this->catalogRoom($selected, 'Phòng Sai Loại', '100000.00', typeId: $other['type']);
        $this->catalogRoom($other, 'Phòng Rẻ Nhất', '700000.00');
        $this->catalogRoom($other, 'Phòng Rẻ Thứ Hai', '800000.00');
        $this->catalogRoom($hidden, 'Phòng Trong Cơ Sở Ẩn', '100000.00');

        $before = $this->chatCounts();
        $suffix = ' tại #'.$selected['id'];
        $input = [...$this->input(), 'message' => self::CATALOG_QUESTION.$suffix];
        $expected = "Phòng đang niêm yết phù hợp:\n"
            ."• Phòng Dưới Biên A — Cơ sở Gió QA (#{$selected['id']})\n"
            ."• Phòng Dưới Biên B — Cơ sở Gió QA (#{$selected['id']})\n"
            .'Bạn có thể kiểm tra ngày lưu trú trên trang tìm kiếm.';
        $first = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.user_message.message', $input['message'])
            ->assertJsonPath('data.assistant_message.message', $expected)->json('data');
        $this->assertSame($input['request_id'], $first['request_id']);
        $this->assertDatabaseHas('chat_sessions', ['id' => $first['session_id'], 'user_id' => $actor->id]);
        $this->assertDatabaseHas('chat_turns', [
            'request_id' => $input['request_id'], 'status' => ChatTurn::SUCCEEDED, 'attempts' => 1,
            'user_message_id' => $first['user_message']['id'], 'assistant_message_id' => $first['assistant_message']['id'],
        ]);
        foreach (['user_message' => ['USER', $input['message']], 'assistant_message' => ['ASSISTANT', $expected]] as $key => [$sender, $message]) {
            $this->assertDatabaseHas('chat_messages', [
                'id' => $first[$key]['id'], 'chat_session_id' => $first['session_id'], 'sender' => $sender, 'message' => $message,
            ]);
        }
        $this->assertSame([$before[0] + 1, $before[1] + 1, $before[2] + 2], $this->chatCounts());

        $session = $first['session_id'];
        $inclusive = str_replace('dưới', 'tối đa', $input['message']);
        $this->send([...$this->input($session), 'message' => $inclusive], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', str_replace(
                'Bạn có thể',
                "• Phòng Bằng Biên — Cơ sở Gió QA (#{$selected['id']})\nBạn có thể",
                $expected,
            ));
        $this->send([...$this->input($session), 'message' => self::CATALOG_QUESTION], $actor)->assertCreated()
            ->assertJsonPath(
                'data.assistant_message.message',
                "Phòng đang niêm yết phù hợp:\n"
                    ."• Phòng Rẻ Nhất — Cơ sở Biển QA (#{$other['id']})\n"
                    ."• Phòng Rẻ Thứ Hai — Cơ sở Biển QA (#{$other['id']})\n"
                    ."• Phòng Dưới Biên A — Cơ sở Gió QA (#{$selected['id']})\n"
                    .'Bạn có thể kiểm tra ngày lưu trú trên trang tìm kiếm.',
            );

        foreach ([
            self::CATALOG_QUESTION.' tại #'.$hidden['id'],
            self::CATALOG_QUESTION.' tại #9007199254740991',
            'Catalog: Tìm phòng cho 4 người dưới 1 đồng'.$suffix,
        ] as $question) {
            $empty = $this->send([...$this->input($session), 'message' => $question], $actor)->assertCreated()
                ->assertJsonPath('data.assistant_message.message', self::CATALOG_EMPTY)->json('data');
            $this->assertDatabaseHas('chat_messages', [
                'id' => $empty['assistant_message']['id'], 'chat_session_id' => $session, 'message' => self::CATALOG_EMPTY,
            ]);
        }
        $this->assertSame([$before[0] + 6, $before[1] + 1, $before[2] + 12], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_replays_the_catalog_snapshot_without_reading_current_rooms(): void
    {
        $actor = User::factory()->customer()->create();
        $property = $this->catalogProperty($this->catalogHost(), 'Cơ sở Sao QA');
        $room = $this->catalogRoom($property, 'Tên Phòng Cũ', '999999.00');
        $input = [...$this->input(), 'message' => 'CATALOG: TIM PHONG CHO 4 KHACH DUOI 1000K TAI #'.$property['id'].'?'];
        $first = $this->send($input, $actor)->assertCreated()->json();
        $this->assertStringContainsString('Tên Phòng Cũ', $first['data']['assistant_message']['message']);
        $session = $first['data']['session_id'];
        DB::table('rooms')->where('id', $room)->update(['name' => 'Tên Phòng Mới']);
        $before = $this->chatCounts();

        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($first);
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:rooms|properties|room_types)\x60/i', implode("\n", $queries));
        $this->assertSame($before, $this->chatCounts());

        $next = $this->send([...$this->input($session), 'message' => $input['message']], $actor)->assertCreated();
        $this->assertStringContainsString('Tên Phòng Mới', $next->json('data.assistant_message.message'));
        $this->assertSame([$before[0] + 1, $before[1], $before[2] + 2], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_rejects_catalog_outside_the_command_and_keeps_error_precedence(): void
    {
        $actor = User::factory()->customer()->create();
        $before = $this->chatCounts();
        foreach ([
            'Catalog:',
            self::CATALOG_QUESTION.' tại Cơ sở không có ngoặc kép',
            self::CATALOG_QUESTION.' từ 2026-12-01',
            self::CATALOG_QUESTION.' email customer@example.test',
            self::CATALOG_QUESTION.' tại #9007199254740992',
            'Catalog: Tìm phòng cho 100 người dưới 1 triệu',
            'Catalog: Tìm phòng cho 4 người',
        ] as $question) {
            $input = [...$this->input(), 'message' => $question];
            $this->send($input, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        }
        $this->assertSame($before, $this->chatCounts());

        $valid = [...$this->input(), 'message' => self::CATALOG_QUESTION.' tại #9007199254740991'];
        $created = $this->send($valid, $actor)->assertCreated()->json('data');
        $this->send([...$valid, 'message' => 'Catalog:'], $actor)->assertConflict()
            ->assertJsonPath('code', 'CHAT_REQUEST_CONFLICT');
        $other = User::factory()->customer()->create();
        $this->send([...$this->input($created['session_id']), 'message' => 'Catalog:'], $other)->assertNotFound()
            ->assertJsonPath('code', 'CHAT_SESSION_NOT_FOUND');
        $this->assertSame([$before[0] + 1, $before[1] + 1, $before[2] + 2], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_resolves_one_public_catalog_name_before_filtering_rooms(): void
    {
        config()->set(['chatbot.enabled' => false, 'chatbot.allow_external_questions' => false]);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $selected = $this->catalogProperty($host, 'Sóc QA');
        $hidden = $this->catalogProperty($host, 'SOC QA', 'BLOCKED');
        $other = $this->catalogProperty($host, 'Sóc QA Riverside');
        $this->catalogRoom($selected, 'Phòng Công Khai', '999999.00');
        $this->catalogRoom($selected, 'Phòng Đúng Biên', '1000000.00');
        $this->catalogRoom($hidden, 'Phòng Cơ Sở Bị Ẩn', '800000.00');
        $this->catalogRoom($other, 'Phòng Cơ Sở Gần Tên', '700000.00');

        $input = [...$this->input(), 'message' => self::CATALOG_QUESTION.' tại " SOC QA "'];
        $before = $this->chatCounts();
        $reply = "Phòng đang niêm yết phù hợp:\n"
            ."• Phòng Công Khai — Sóc QA (#{$selected['id']})\n"
            .'Bạn có thể kiểm tra ngày lưu trú trên trang tìm kiếm.';
        $result = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.user_message.message', $input['message'])
            ->assertJsonPath('data.assistant_message.message', $reply)->json('data');
        $this->assertDatabaseHas('chat_messages', [
            'id' => $result['assistant_message']['id'], 'chat_session_id' => $result['session_id'], 'message' => $reply,
        ]);
        $this->assertSame([$before[0] + 1, $before[1] + 1, $before[2] + 2], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_saves_name_clarification_and_lets_customer_choose_a_code(): void
    {
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $first = $this->catalogProperty($host, 'Sunrise QA', address: 'Đường Mây');
        $second = $this->catalogProperty($host, 'Súnrise QA', address: 'Đường Gió');
        $this->catalogProperty($host, 'SUNRISE QA', 'INACTIVE', 'Đường Riêng');
        $this->catalogRoom($first, 'Phòng Mây', '800000.00');
        $this->catalogRoom($second, 'Phòng Gió', '900000.00');

        $question = self::CATALOG_QUESTION.' tại "SUNRISE QA"';
        $input = [...$this->input(), 'message' => $question];
        $header = "Có nhiều cơ sở công khai trùng tên:\n";
        $options = "#{$first['id']} – Sunrise QA, Đường Mây\n#{$second['id']} – Súnrise QA, Đường Gió\n";
        $footer = "Bạn gửi lại số khách và ngân sách kèm mã đã chọn, ví dụ tại #{$first['id']}.";
        DB::enableQueryLog();
        try {
            $result = $this->send($input, $actor)->assertCreated()
                ->assertJsonPath('data.assistant_message.message', $header.$options.$footer)->json();
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:rooms|room_types)\x60/i', implode("\n", $queries));
        $session = $result['data']['session_id'];
        $this->assertDatabaseHas('chat_messages', [
            'id' => $result['data']['assistant_message']['id'], 'chat_session_id' => $session, 'message' => $header.$options.$footer,
        ]);
        $this->send([...$this->input($session), 'message' => self::CATALOG_QUESTION.' tại #'.$second['id']], $actor)
            ->assertCreated()->assertJsonPath('data.session_id', $session)->assertJsonPath(
                'data.assistant_message.message',
                "Phòng đang niêm yết phù hợp:\n• Phòng Gió — Súnrise QA (#{$second['id']})\n"
                    .'Bạn có thể kiểm tra ngày lưu trú trên trang tìm kiếm.',
            );

        $third = $this->catalogProperty($host, 'Sunrise QA', address: 'Đường Sao');
        $this->send([...$this->input($session), 'message' => $question], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', $header.$options."#{$third['id']} – Sunrise QA, Đường Sao\n".$footer);
        $this->catalogProperty($host, 'Sunrise QA', address: 'Đường Trăng');
        $many = 'Có nhiều cơ sở công khai trùng tên. Hãy dùng trang tìm kiếm để xác định cơ sở, rồi gửi lại câu hỏi kèm mã trên trang chi tiết.';
        $latest = $this->send([...$this->input($session), 'message' => $question], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', $many)->json('data');
        $this->assertDatabaseHas('chat_messages', [
            'id' => $latest['assistant_message']['id'], 'chat_session_id' => $session, 'message' => $many,
        ]);
        DB::table('properties')->where('id', $second['id'])->update(['name' => 'Tên mới QA']);

        $beforeReplay = $this->chatCounts();
        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($result);
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:properties|rooms|room_types)\x60/i', implode("\n", $queries));
        $this->assertSame($beforeReplay, $this->chatCounts());
        $fresh = $this->send([...$this->input($session), 'message' => $question], $actor)->assertCreated();
        $updated = $fresh->json('data.assistant_message.message');
        $this->assertStringNotContainsString('Đường Gió', $updated);
        $this->assertStringContainsString('Đường Trăng', $updated);
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_never_saves_unmatched_or_unsafe_catalog_names(): void
    {
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $this->catalogProperty($host, 'Cơ sở ẩn QA', 'INACTIVE');
        $this->catalogProperty($host, 'hotline 0901 234 567');
        $before = $this->chatCounts();
        $prefix = self::CATALOG_QUESTION.' tại ';
        $missing = [...$this->input(), 'message' => $prefix.'"Cơ sở không tồn tại QA"'];
        $denied = $this->send($missing, $actor)->assertUnprocessable()->assertJsonValidationErrors('message')->json();
        $hidden = [...$this->input(), 'message' => $prefix.'"Cơ sở ẩn QA"'];
        $this->send($hidden, $actor)->assertUnprocessable()->assertExactJson($denied);

        foreach ([' ', 'hotline 0901 234 567', 'a@example.test', 'https://example.test', 'OTP 1234', "Sunrise\nQA", str_repeat('ắ', 256)] as $name) {
            $input = [...$this->input(), 'message' => $prefix.'"'.$name.'"'];
            $this->send($input, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        }
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_queries_public_amenities_by_resolved_ids_and_limits_public_rooms(): void
    {
        config()->set(['chatbot.enabled' => false, 'chatbot.allow_external_questions' => false]);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $property = $this->catalogProperty($host, 'Cơ sở Mây QA');
        $other = $this->catalogProperty($host, 'Cơ sở Biển QA');
        $hidden = $this->catalogProperty($host, 'Cơ sở Ẩn QA', 'BLOCKED');
        $otherRoom = $this->catalogRoom($other, 'Phòng Cơ Sở Khác', '800000.00');
        $hiddenRoom = $this->catalogRoom($hidden, 'Phòng Cơ Sở Ẩn', '800000.00');
        $inactive = $this->catalogRoom($property, 'Phòng Ẩn', '800000.00', status: 'INACTIVE');
        $roomIds = [];
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $roomIds[] = $this->catalogRoom($property, 'Phòng '.$letter, '800000.00');
        }
        [$first, $second, $third] = $roomIds;
        $this->catalogAmenity('Hồ bơi', [...$roomIds, $otherRoom, $hiddenRoom, $inactive]);
        // MySQL's name collation equates these labels, while the command resolves them separately.
        $this->catalogAmenity('Ho boi', [$otherRoom, $second]);
        $this->catalogAmenity('Điều hòa', [$first]);
        $this->catalogAmenity('Ghế đọc sách QA', [$second]);
        $this->catalogAmenity('Wi Fi', [$first]);
        $this->catalogAmenity('Wi-Fi', [$third]);
        $suffix = ' không tại #'.$property['id'].'?';
        $question = 'TIỆN ÍCH: Có HỒ  BƠI'.$suffix;
        $before = $this->chatCounts();
        $expected = "Một số phòng công khai tại Cơ sở Mây QA có Hồ bơi được niêm yết:\n• Phòng A\n• Phòng B\n• Phòng C";
        $result = $this->send([...$this->input(), 'message' => $question], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', $expected)->json('data');
        $this->assertDatabaseHas('chat_messages', ['id' => $result['assistant_message']['id'], 'message' => $expected]);
        $this->assertSame([$before[0] + 1, $before[1] + 1, $before[2] + 2], $this->chatCounts());
        $session = $result['session_id'];
        foreach ([
            'Có Ho boi' => "Một số phòng công khai tại Cơ sở Mây QA có Ho boi được niêm yết:\n• Phòng B",
            'Phòng "PHÒNG A" có điều hòa' => 'Phòng Phòng A tại Cơ sở Mây QA có Điều hòa được niêm yết.',
            'Có Ghế đọc sách QA' => "Một số phòng công khai tại Cơ sở Mây QA có Ghế đọc sách QA được niêm yết:\n• Phòng B",
            'Có Wi‑Fi' => "Một số phòng công khai tại Cơ sở Mây QA có Wi-Fi được niêm yết:\n• Phòng A\n• Phòng C",
            'Có wifi' => "Một số phòng công khai tại Cơ sở Mây QA có Wi-Fi được niêm yết:\n• Phòng A\n• Phòng C",
        ] as $command => $reply) {
            $this->send([...$this->input($session), 'message' => 'Tiện ích: '.$command.$suffix], $actor)->assertCreated()
                ->assertJsonPath('data.assistant_message.message', $reply);
        }
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_replays_amenity_snapshot_and_reads_current_links_for_new_turns(): void
    {
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $property = $this->catalogProperty($host, 'Cơ sở Sao QA');
        $other = $this->catalogProperty($host, 'Cơ sở Trăng QA');
        $room = $this->catalogRoom($property, 'Deluxe', '800000.00');
        $otherRoom = $this->catalogRoom($other, 'Phòng Khác', '800000.00');
        $amenity = $this->catalogAmenity('Bãi đỗ xe', [$room, $otherRoom]);
        $question = 'Tiện ích: Phòng "Deluxe" có bãi đỗ xe không tại #'.$property['id'].'?';
        $input = [...$this->input(), 'message' => $question];
        $original = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Phòng Deluxe tại Cơ sở Sao QA có Bãi đỗ xe được niêm yết.')->json();
        $session = $original['data']['session_id'];
        DB::table('room_amenities')->where('room_id', $room)->where('amenity_id', $amenity)->delete();
        $beforeReplay = $this->chatCounts();
        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($original);
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:properties|rooms|room_types|amenities|room_amenities)\x60/i', implode("\n", $queries));
        $this->assertSame($beforeReplay, $this->chatCounts());
        $this->send([...$this->input($session), 'message' => $question], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Chưa thấy thông tin Bãi đỗ xe trong danh mục công khai.');

        DB::table('amenities')->where('id', $amenity)->update(['status' => false]);
        $before = $this->chatCounts();
        $this->send([...$this->input($session), 'message' => $question], $actor)
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_handles_ambiguous_rooms_and_rejects_unpublished_or_invalid_amenity_input(): void
    {
        config()->set('chatbot.rate_limit_per_minute', 20);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $property = $this->catalogProperty($host, 'Cơ sở Gió QA');
        $hidden = $this->catalogProperty($host, 'Cơ sở Ẩn QA', 'BLOCKED');
        $other = $this->catalogProperty($host, 'Cơ sở Khác QA');
        $room = $this->catalogRoom($property, 'Deluxe', '800000.00');
        $this->catalogRoom($property, 'Déluxe', '800000.00');
        $inactive = $this->catalogRoom($property, 'Phòng Ẩn', '800000.00', status: 'INACTIVE');
        $foreignRoom = $this->catalogRoom($other, 'Phòng Cơ Sở Khác', '800000.00');
        $this->catalogAmenity('Hồ bơi', [$room, $inactive, $foreignRoom]);
        $this->catalogAmenity('Phòng gym', [$room]);
        $this->catalogAmenity('PHÒNG  GYM', [$room]);
        $this->catalogAmenity('Bồn tắm', [$inactive]);
        $this->catalogAmenity('Ban công', [$room], enabled: false);
        $suffix = ' không tại #'.$property['id'].'?';
        $question = 'Tiện ích: Phòng "DELUXE" có hồ bơi'.$suffix;
        $result = $this->send([...$this->input(), 'message' => $question], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Có nhiều phòng công khai trùng tên tại cơ sở này. Bạn hãy xem trang chi tiết phòng để đối chiếu tiện nghi.')->json('data');
        $session = $result['session_id'];
        $hiddenResult = $this->send([...$this->input($session), 'message' => 'Tiện ích: Có hồ bơi không tại #'.$hidden['id'].'?'], $actor)->assertCreated()->json('data');
        $missingResult = $this->send([...$this->input($session), 'message' => 'Tiện ích: Có hồ bơi không tại #9007199254740991?'], $actor)->assertCreated()->json('data');
        $this->assertSame($hiddenResult['assistant_message']['message'], $missingResult['assistant_message']['message']);
        $before = $this->chatCounts();
        foreach ([
            'Tiện ích: Có phòng gym'.$suffix,
            'Tiện ích: Có bồn tắm'.$suffix,
            'Tiện ích: Có ban công'.$suffix,
            'Tiện ích: Có tiện ích không tồn tại'.$suffix,
            'Tiện ích: Phòng "Phòng Ẩn" có hồ bơi'.$suffix,
            'Tiện ích: Phòng "Phòng Cơ Sở Khác" có hồ bơi'.$suffix,
            'Tiện ích: Phòng "Deluxe" có hồ bơi không tại #'.$hidden['id'].'?',
            'Tiện ích: Có hồ bơi không ngày 2026-12-20 tại #'.$property['id'].'?',
            'Tiện ích: Có hồ bơi email a@example.test'.$suffix,
            'Tiện ích: Phòng "OTP 1234" có hồ bơi'.$suffix,
            'Tiện ích: Có hồ bơi không?',
        ] as $invalid) {
            $input = [...$this->input($session), 'message' => $invalid];
            $this->send($input, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        }
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_saves_exact_public_service_prices_and_safe_fallbacks(): void
    {
        config()->set(['chatbot.enabled' => false, 'chatbot.allow_external_questions' => false]);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $property = $this->catalogProperty($host, 'Cơ sở Sao QA')['id'];
        $hidden = $this->catalogProperty($host, 'Cơ sở Ẩn QA', 'BLOCKED')['id'];
        $other = $this->catalogProperty($host, 'Cơ sở Khác QA')['id'];
        $this->catalogService($property, 'Giặt ủi', 'kg', '45000.50');
        $this->catalogService($property, 'Đưa đón sân bay', 'lượt', '0.00');
        $this->catalogService($hidden, 'Giặt ủi', 'kg', '99999.00');
        $this->catalogService($other, 'Giặt ủi', 'kg', '99999.00', enabled: false);
        $transfer = $this->catalogService($other, 'Đưa đón sân bay', 'suất', '123.00');
        $missing = 'Chưa tìm thấy thông tin dịch vụ công khai phù hợp.';
        $before = $this->chatCounts();
        $session = null;

        foreach ([
            ["Dịch vụ: Giá giặt ủi là bao nhiêu tại #$property?", 'Tại Cơ sở Sao QA, dịch vụ Giặt ủi có đơn giá niêm yết 45.000,50 đ/kg.'],
            ["Dịch vụ: Có ĐƯA ĐÓN SÂN BAY không tại #$property?", 'Tại Cơ sở Sao QA, dịch vụ Đưa đón sân bay có đơn giá niêm yết 0 đ/lượt.'],
            ["Dịch vụ: Có giặt ủi không tại #$hidden?", $missing],
            ["Dịch vụ: Có giặt ủi không tại #$other?", $missing],
            ['Dịch vụ: Có giặt ủi không tại #9007199254740991?', $missing],
            ["Dịch vụ: Có đưa đón sân bay không tại #$other?", $missing],
        ] as [$question, $answer]) {
            $input = [...$this->input($session), 'message' => $question];
            $session = $this->send($input, $actor)->assertCreated()
                ->assertJsonPath('data.user_message.message', $question)
                ->assertJsonPath('data.assistant_message.message', $answer)
                ->json('data.session_id');
            $this->assertSame(ChatTurn::SUCCEEDED, $this->turn($input)->status);
        }

        DB::table('services')->where('id', $transfer)->update(['unit' => 'lượt', 'price' => '-1.00']);
        $this->send([...$this->input($session), 'message' => "Dịch vụ: Có đưa đón sân bay không tại #$other?"], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', $missing);
        $this->catalogService($property, 'GIAT UI', 'kg', '99000.00');
        $this->send([...$this->input($session), 'message' => "Dịch vụ: Có giặt ủi không tại #$property?"], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', 'Có nhiều dịch vụ công khai trùng tên tại cơ sở này. Chưa thể xác định một đơn giá niêm yết duy nhất.');
        $this->assertSame([$before[0] + 8, $before[1] + 1, $before[2] + 16], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_replays_the_saved_service_price_and_reads_current_price_and_status_for_new_turns(): void
    {
        $actor = User::factory()->customer()->create();
        $property = $this->catalogProperty($this->catalogHost(), 'Cơ sở Sao QA')['id'];
        $service = $this->catalogService($property, 'Giặt ủi', 'kg', '45000.50');
        $input = [...$this->input(), 'message' => "Dịch vụ: Giá giặt ủi là bao nhiêu tại #$property?"];
        $original = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Tại Cơ sở Sao QA, dịch vụ Giặt ủi có đơn giá niêm yết 45.000,50 đ/kg.')->json();
        $session = $original['data']['session_id'];
        DB::table('services')->where('id', $service)->update(['price' => '52000.05']);
        $before = $this->chatCounts();

        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($original);
            $this->send([...$input, 'message' => 'Dịch vụ:'], $actor)
                ->assertConflict()->assertJsonPath('code', 'CHAT_REQUEST_CONFLICT');
            $other = User::factory()->customer()->create();
            $this->send([...$this->input($session), 'message' => 'Dịch vụ:'], $other)
                ->assertNotFound()->assertJsonPath('code', 'CHAT_SESSION_NOT_FOUND');
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:properties|services)\x60/i', implode("\n", $queries));
        $this->assertSame($before, $this->chatCounts());

        $this->send([...$this->input($session), 'message' => $input['message']], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', 'Tại Cơ sở Sao QA, dịch vụ Giặt ủi có đơn giá niêm yết 52.000,05 đ/kg.');
        DB::table('services')->where('id', $service)->update(['status' => false]);
        $this->send([...$this->input($session), 'message' => $input['message']], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', 'Chưa tìm thấy thông tin dịch vụ công khai phù hợp.');
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_rejects_invalid_explicit_service_commands_before_claiming(): void
    {
        config()->set(['chatbot.enabled' => false, 'chatbot.rate_limit_per_minute' => 20]);
        $actor = User::factory()->customer()->create();
        $before = $this->chatCounts();
        foreach ([
            'Dịch vụ: Có giặt ủi không?',
            'Dịch vụ: Có ăn sáng không tại #71?',
            'Dịch vụ: Có giặt ủi và đưa đón sân bay không tại #71?',
            'Dịch vụ: Đặt giặt ủi tại #71',
            'Dịch vụ: Có giặt ủi không tại #71? email a@example.test',
            'Dịch vụ: Giá giặt ủi không tại #71?',
            'Dịch vụ: Có giặt ủi không tại #0?',
            'Dịch vụ: Có giặt ủi không tại #9007199254740992?',
        ] as $question) {
            $input = [...$this->input(), 'message' => $question];
            $this->send($input, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        }
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_saves_public_availability_using_the_utc_day_and_both_date_formats(): void
    {
        $start = Carbon::parse('2026-11-22T18:30:00Z'); // Already 23 November in Vietnam.
        $this->travelTo($start);
        config()->set(['chatbot.enabled' => false, 'chatbot.allow_external_questions' => false]);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $property = $this->catalogProperty($host, 'Cơ sở Mây QA');
        $hidden = $this->catalogProperty($host, 'Cơ sở Ẩn QA', 'BLOCKED');
        $room = $this->catalogRoom($property, 'Phòng Gió QA', '900000.00', capacity: 2);
        $hiddenRoom = $this->catalogRoom($hidden, 'Phòng Ẩn QA', '900000.00');
        $inactiveRoom = $this->catalogRoom($property, 'Phòng Ngừng QA', '900000.00', status: 'INACTIVE');
        $wrongTypeRoom = $this->catalogRoom($property, 'Phòng Sai Loại QA', '900000.00', typeId: $hidden['type']);
        $expected = 'Phòng Gió QA khả dụng tại thời điểm kiểm tra, từ 22/11/2026 đến 24/11/2026 cho 2 người.';
        $missing = 'Chưa tìm thấy thông tin công khai phù hợp cho mã phòng này.';
        $unavailable = 'Phòng chưa khả dụng tại thời điểm kiểm tra cho khoảng ngày và số khách đã nhập.';
        $before = $this->chatCounts();
        $input = [...$this->input(), 'message' => "Phòng trống: Còn phòng #$room từ 22/11/2026 đến 24/11/2026 cho 2 người không?"];
        $original = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.user_message.message', $input['message'])
            ->assertJsonPath('data.assistant_message.message', $expected)
            ->json();
        $session = $original['data']['session_id'];
        $iso = "Phòng trống: Còn phòng #$room từ 2026-11-22 đến 2026-11-24 cho 2 người không?";
        $this->send([...$this->input($session), 'message' => $iso], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', $expected);

        foreach ([$hiddenRoom, $inactiveRoom, $wrongTypeRoom, 9007199254740991] as $roomId) {
            $question = "Phòng trống: Còn phòng #$roomId từ 22/11/2026 đến 24/11/2026 cho 2 người không?";
            $this->send([...$this->input($session), 'message' => $question], $actor)
                ->assertCreated()->assertJsonPath('data.assistant_message.message', $missing);
        }
        $this->send([...$this->input($session), 'message' => str_replace('2 người', '3 người', $iso)], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', $unavailable);

        $this->travelTo($start->copy()->addHours(6));
        $this->send($input, $actor)->assertOk()->assertExactJson($original);
        $this->send([...$this->input($session), 'message' => $iso], $actor)
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertSame([$before[0] + 7, $before[1] + 1, $before[2] + 14], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_replays_availability_without_reading_the_schedule_and_new_turns_see_changes(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22T12:00:00Z'));
        $actor = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $property = $this->catalogProperty($this->catalogHost(), 'Cơ sở Mây QA');
        $room = $this->catalogRoom($property, 'Phòng Gió QA', '900000.00');
        $question = "Phòng trống: Còn phòng #$room từ 20/12/2026 đến 22/12/2026 cho 2 người không?";
        $input = [...$this->input(), 'message' => $question];
        $available = 'Phòng Gió QA khả dụng tại thời điểm kiểm tra, từ 20/12/2026 đến 22/12/2026 cho 2 người.';
        $unavailable = 'Phòng chưa khả dụng tại thời điểm kiểm tra cho khoảng ngày và số khách đã nhập.';
        $original = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', $available)->json();
        $session = $original['data']['session_id'];
        $booking = DB::table('bookings')->insertGetId([
            'booking_code' => 'BK-PRIVATE-'.Str::uuid(), 'customer_id' => $other->id, 'room_id' => $room,
            'check_in_date' => '2026-12-20', 'check_out_date' => '2026-12-22', 'guest_count' => 2,
            'number_of_nights' => 2, 'price_per_night' => '900000.00', 'room_total' => '1800000.00',
            'status' => 'PENDING', 'note' => 'Ghi chú booking riêng',
        ]);
        $this->availabilityBookingIds[] = $booking;
        $before = $this->chatCounts();

        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($original);
            $this->send([...$input, 'message' => 'Phòng trống:'], $actor)
                ->assertConflict()->assertJsonPath('code', 'CHAT_REQUEST_CONFLICT');
            $this->send([...$this->input($session), 'message' => 'Phòng trống:'], $other)
                ->assertNotFound()->assertJsonPath('code', 'CHAT_SESSION_NOT_FOUND');
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:rooms|room_types|properties|bookings|maintenances)\x60/i', implode("\n", $queries));
        $this->assertSame($before, $this->chatCounts());

        $bookingBefore = DB::table('bookings')->find($booking);
        $this->send([...$this->input($session), 'message' => $question], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', $unavailable)
            ->assertJsonMissingPath('data.reasons')->assertDontSee('Ghi chú booking riêng');
        $edge = "Phòng trống: Còn phòng #$room từ 22/12/2026 đến 24/12/2026 cho 2 người không?";
        $this->send([...$this->input($session), 'message' => $edge], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Phòng Gió QA khả dụng tại thời điểm kiểm tra, từ 22/12/2026 đến 24/12/2026 cho 2 người.');
        $this->assertEquals($bookingBefore, DB::table('bookings')->find($booking));

        DB::table('bookings')->where('id', $booking)->update(['status' => 'CANCELLED']);
        $this->send([...$this->input($session), 'message' => $question], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', $available);
        $maintenance = DB::table('maintenances')->insertGetId([
            'room_id' => $room, 'start_date' => '2026-12-20', 'end_date' => '2026-12-22',
            'status' => 'SCHEDULED', 'reason' => 'Lý do bảo trì riêng',
        ]);
        $maintenanceBefore = DB::table('maintenances')->find($maintenance);
        $this->send([...$this->input($session), 'message' => $question], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', $unavailable)
            ->assertJsonMissingPath('data.reasons')->assertDontSee('Lý do bảo trì riêng');
        $this->assertEquals($maintenanceBefore, DB::table('maintenances')->find($maintenance));
        $this->assertSame([$before[0] + 4, $before[1], $before[2] + 8], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_customer_chat_rejects_invalid_availability_commands_before_claiming(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22T12:00:00Z'));
        config()->set(['chatbot.enabled' => false, 'chatbot.rate_limit_per_minute' => 20]);
        $actor = User::factory()->customer()->create();
        $before = $this->chatCounts();
        foreach ([
            'Phòng trống: Còn phòng #81 không?',
            'Phòng trống: Còn phòng #81 từ 21/11/2026 đến 23/11/2026 cho 2 người không?',
            'Phòng trống: Còn phòng #81 từ 31/02/2027 đến 02/03/2027 cho 2 người không?',
            'Phòng trống: Còn phòng #81 từ 20/12/2026 đến 20/12/2026 cho 2 người không?',
            'Phòng trống: Còn phòng #81 từ 20/12/2026 đến 22/12/2026 cho 0 người không?',
            'Phòng trống: Còn phòng #9007199254740992 từ 20/12/2026 đến 22/12/2026 cho 2 người không?',
            'Phòng trống: Còn phòng #81 từ 20/12/2026 đến 22/12/2026 cho 2 người không? email a@example.test',
            'Phòng trống: Đặt phòng #81 từ 20/12/2026 đến 22/12/2026 cho 2 người',
        ] as $question) {
            $input = [...$this->input(), 'message' => $question];
            $this->send($input, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        }
        config()->set('sanctum.middleware.validate_csrf_token', EnforceChatCsrfForTests::class);
        $this->withSession(['_token' => Str::random(40)]);
        $this->send([...$this->input(), 'message' => 'Phòng trống:'], $actor)->assertStatus(419);
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_natural_availability_uses_a_masked_prompt_and_replays_the_database_result(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22T18:30:00Z'));
        $this->fakeAvailabilityIntents(self::AVAILABILITY_INTENT, self::AVAILABILITY_INTENT, self::AVAILABILITY_INTENT, self::AVAILABILITY_INTENT);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $property = $this->catalogProperty($host, 'Cơ sở QA');
        $room = $this->catalogRoom($property, 'Phòng Gió QA', '900000.00', capacity: 2);
        $hidden = $this->catalogRoom($property, 'Phòng Riêng QA', '900000.00', status: 'INACTIVE');
        $question = "Phòng #$room còn trống cho 2 người từ ngày 22/11/2026 đến 24/11/2026 không?";
        $reordered = "Từ 2026-11-22 tới 2026-11-24, phòng #$room còn chỗ cho 2 khách không?";
        $available = 'Phòng Gió QA khả dụng tại thời điểm kiểm tra, từ 22/11/2026 đến 24/11/2026 cho 2 người.';
        $input = [...$this->input(), 'message' => $question];
        $before = $this->chatCounts();
        $original = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.user_message.message', $question)
            ->assertJsonPath('data.assistant_message.message', $available)->json();
        $session = $original['data']['session_id'];
        $this->send([...$this->input($session), 'message' => $reordered], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', $available);
        $prompts = Http::recorded()->map(static fn (array $call): string => $call[0]->data()['contents'][0]['parts'][0]['text'])->all();
        $this->assertSame([
            'phong [ROOM] con trong cho [GUESTS] nguoi tu ngay [DATE_A] den [DATE_B] khong?',
            'tu [DATE_A] toi [DATE_B] phong [ROOM] con cho cho [GUESTS] khach khong?',
        ], $prompts);

        DB::table('maintenances')->insert([
            'room_id' => $room, 'start_date' => '2026-11-22', 'end_date' => '2026-11-24',
            'reason' => 'Lý do riêng', 'status' => 'SCHEDULED',
        ]);
        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($original);
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:rooms|room_types|properties|bookings|maintenances)\x60/i', implode("\n", $queries));
        Http::assertSentCount(2);
        $this->send([...$this->input($session), 'message' => $question], $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Phòng chưa khả dụng tại thời điểm kiểm tra cho khoảng ngày và số khách đã nhập.')
            ->assertDontSee('Lý do riêng');
        $this->send([...$this->input($session), 'message' => str_replace("#$room ", "#$hidden ", $question)], $actor)
            ->assertCreated()->assertJsonPath('data.assistant_message.message', 'Chưa tìm thấy thông tin công khai phù hợp cho mã phòng này.')
            ->assertDontSee('Phòng Riêng QA');
        $this->assertSame([$before[0] + 4, $before[1] + 1, $before[2] + 8], $this->chatCounts());
        $this->assertSame([], $this->questions);
        Http::assertSentCount(4);
    }

    public function test_natural_availability_blocks_private_or_incomplete_requests_and_uses_local_mode_when_ai_is_disabled(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22T18:30:00Z'));
        $actor = User::factory()->customer()->create();
        $before = $this->chatCounts();
        $question = 'Phòng #1 còn trống cho 2 người từ 22/11/2026 đến 24/11/2026 không?';
        foreach ([
            $question.' Email user@example.test',
            $question.' Số điện thoại 0901234567',
            $question.' Mã booking BK123',
            'Phòng #1 còn trống từ 22/11/2026 tới 24/11/2026 không?',
            'Phòng #1 còn trống cho 2 người từ 24/11/2026 đến 22/11/2026 không?',
            'Cơ sở #1 còn phòng trống cho 2 người từ 22/11/2026 đến 24/11/2026 không?',
        ] as $invalid) {
            $input = [...$this->input(), 'message' => $invalid];
            $this->send($input, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        }
        config()->set('chatbot.allow_external_questions', false);
        $response = $this->send([...$this->input(), 'message' => $question], $actor)
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertStringContainsString('Phòng trống: Còn phòng #81', $response->json('errors.message.0'));
        $this->assertSame($before, $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_natural_availability_rejects_invented_references_and_can_retry_the_same_turn(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22T18:30:00Z'));
        $this->fakeAvailabilityIntents([...self::AVAILABILITY_INTENT, 'room' => 'ROOM_B'], self::AVAILABILITY_INTENT);
        $actor = User::factory()->customer()->create();
        $property = $this->catalogProperty($this->catalogHost(), 'Cơ sở QA');
        $room = $this->catalogRoom($property, 'Phòng Gió QA', '900000.00');
        $input = [...$this->input(), 'message' => "Phòng #$room còn trống cho 2 người từ 22/11/2026 đến 24/11/2026 không?"];
        $before = $this->chatCounts();
        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertServiceUnavailable()->assertJsonPath('code', ChatProviderException::UNAVAILABLE);
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertDoesNotMatchRegularExpression('/\x60(?:rooms|room_types|properties|bookings|maintenances)\x60/i', implode("\n", $queries));
        $this->assertSame([$before[0] + 1, $before[1], $before[2]], $this->chatCounts());
        $this->assertSame(ChatTurn::FAILED, $this->turn($input)->status);
        Http::assertSentCount(1);
        $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Phòng Gió QA khả dụng tại thời điểm kiểm tra, từ 22/11/2026 đến 24/11/2026 cho 2 người.');
        $this->assertSame(2, $this->turn($input)->attempts);
        $this->assertSame([], $this->questions);
        Http::assertSentCount(2);
    }

    public function test_property_availability_filters_public_rooms_before_limit_and_reads_current_week_for_new_turns(): void
    {
        $this->travelTo(Carbon::parse('2026-11-27T23:30:00Z'));
        $this->fakeAvailabilityIntents(self::PROPERTY_INTENT, self::PROPERTY_INTENT);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $property = $this->catalogProperty($host, 'Khách sạn Sông Hàn');
        $other = $this->catalogProperty($host, 'Cơ sở Khác QA');
        $this->catalogRoom($other, 'Phòng Cơ Sở Khác', '900000.00');
        $this->catalogRoom($property, 'Phòng Ẩn', '900000.00', status: 'INACTIVE');
        $this->catalogRoom($property, 'Phòng Sai Loại', '900000.00', typeId: $other['type']);
        $this->catalogRoom($property, 'Phòng Giá Lỗi', '-1.00');
        $this->catalogRoom($property, 'Phòng Một Khách', '900000.00', capacity: 1);
        $maintenance = $this->catalogRoom($property, 'Phòng Bảo Trì', '900000.00');
        $booked = $this->catalogRoom($property, 'Phòng Đã Giữ', '900000.00');
        $available = [];
        foreach (['Mây', 'Gió', 'Trăng', 'Sao'] as $name) {
            $available[] = $this->catalogRoom($property, 'Phòng '.$name, '900000.00');
        }
        DB::table('maintenances')->insert([
            ['room_id' => $maintenance, 'start_date' => '2026-11-28', 'end_date' => '2026-11-30', 'status' => 'SCHEDULED', 'reason' => 'Riêng'],
            ['room_id' => $available[0], 'start_date' => '2026-11-30', 'end_date' => '2026-12-01', 'status' => 'SCHEDULED', 'reason' => 'Cận biên'],
        ]);
        $this->availabilityBookingIds[] = DB::table('bookings')->insertGetId([
            'booking_code' => 'BK-PRIVATE-'.Str::uuid(), 'customer_id' => $actor->id, 'room_id' => $booked,
            'check_in_date' => '2026-11-28', 'check_out_date' => '2026-11-30', 'guest_count' => 2,
            'number_of_nights' => 2, 'price_per_night' => '900000.00', 'room_total' => '1800000.00',
            'status' => 'CONFIRMED', 'note' => 'Ghi chú riêng',
        ]);
        $propertyId = $property['id'];
        $expected = "Khách sạn Sông Hàn (#$propertyId) có phòng khả dụng tại thời điểm kiểm tra từ 28/11/2026 đến 30/11/2026 cho 2 người:"
            ."\n• Phòng Mây (#$available[0])\n• Phòng Gió (#$available[1])\n• Phòng Trăng (#$available[2])";
        $input = [...$this->input(), 'message' => 'Khách sạn Sông Hàn còn phòng cho hai người cuối tuần này không?'];
        $before = $this->chatCounts();
        $original = $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', $expected)->json();

        $this->travelTo(Carbon::parse('2026-11-28T23:00:00Z'));
        DB::enableQueryLog();
        try {
            $this->send($input, $actor)->assertOk()->assertExactJson($original);
            $queries = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNotEmpty($queries);
        $this->assertDoesNotMatchRegularExpression('/\x60(?:rooms|room_types|properties|bookings|maintenances)\x60/i', implode("\n", $queries));
        Http::assertSentCount(1);

        $this->travelTo(Carbon::parse('2026-12-04T16:00:00Z'));
        $this->send([...$this->input($original['data']['session_id']), 'message' => $input['message']], $actor)
            ->assertCreated()
            ->assertJsonPath('data.assistant_message.message', "Khách sạn Sông Hàn (#$propertyId) có phòng khả dụng tại thời điểm kiểm tra từ 05/12/2026 đến 07/12/2026 cho 2 người:"
                ."\n• Phòng Bảo Trì (#$maintenance)\n• Phòng Đã Giữ (#$booked)\n• Phòng Mây (#$available[0])");
        $prompts = Http::recorded()->map(static fn (array $call): string => $call[0]->data()['contents'][0]['parts'][0]['text'])->all();
        $this->assertSame(array_fill(0, 2, '[PROPERTY] con phong cho [GUESTS] nguoi [WEEKEND] khong?'), $prompts);
        $this->assertSame([$before[0] + 2, $before[1] + 1, $before[2] + 4], $this->chatCounts());
        $this->assertSame([], $this->questions);
    }

    public function test_property_availability_clarifies_duplicate_names_and_uses_exact_active_property_code(): void
    {
        // UTC is still Saturday even though Vietnam has crossed into Sunday.
        $this->travelTo(Carbon::parse('2026-11-28T23:30:00Z'));
        $this->fakeAvailabilityIntents(self::PROPERTY_INTENT);
        $actor = User::factory()->customer()->create();
        $host = $this->catalogHost();
        $first = $this->catalogProperty($host, 'Sông Hàn QA');
        $second = $this->catalogProperty($host, 'Song Han QA');
        $hidden = $this->catalogProperty($host, 'Cơ sở Ẩn QA', 'INACTIVE');
        $firstRoom = $this->catalogRoom($first, 'Phòng Mây', '900000.00');
        $this->catalogRoom($second, 'Phòng Gió', '900000.00');
        $this->catalogRoom($hidden, 'Phòng Riêng', '900000.00');
        $suffix = ' còn phòng cho 2 người cuối tuần này không?';
        $duplicate = [...$this->input(), 'message' => 'Sông Hàn QA'.$suffix];
        $original = $this->send($duplicate, $actor)->assertCreated()->json();
        $reply = $original['data']['assistant_message']['message'];
        $this->assertStringContainsString('Sông Hàn QA (#'.$first['id'].')', $reply);
        $this->assertStringContainsString('Song Han QA (#'.$second['id'].')', $reply);
        $this->assertStringNotContainsString('Phòng Mây', $reply);
        $this->send($duplicate, $actor)->assertOk()->assertExactJson($original);

        foreach (['Sông Hàn'.$suffix, 'Cơ sở Ẩn QA'.$suffix, 'Còn phòng cho 2 người cuối tuần này tại cơ sở #'.$hidden['id'].' không?',
            'Email user@example.test'.$suffix, 'Số điện thoại 0901 234 567'.$suffix, 'Sông Hàn QA'.$suffix.' thêm từ 28/11/2026 đến 30/11/2026'] as $invalid) {
            $input = [...$this->input(), 'message' => $invalid];
            $this->send($input, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
            $this->assertDatabaseMissing('chat_turns', ['request_id' => $input['request_id']]);
        }
        Http::assertNothingSent();
        $question = 'Còn phòng cho 2 người cuối tuần này tại cơ sở #'.$first['id'].' không?';
        $selected = [...$this->input($original['data']['session_id']), 'message' => $question];
        $selectedResult = $this->send($selected, $actor)
            ->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Sông Hàn QA (#'.$first['id'].') có phòng khả dụng tại thời điểm kiểm tra từ 28/11/2026 đến 30/11/2026 cho 2 người:'."\n• Phòng Mây (#$firstRoom)")->json();
        $this->travelTo(Carbon::parse('2026-11-29T00:00:00Z'));
        $this->send($selected, $actor)->assertOk()->assertExactJson($selectedResult);
        Http::assertSentCount(1);
        $this->assertSame('con phong cho [GUESTS] nguoi [WEEKEND] tai [PROPERTY] khong?', Http::recorded()[0][0]->data()['contents'][0]['parts'][0]['text']);
        $this->assertSame([], $this->questions);
    }

    public function test_property_availability_requires_explicit_dates_on_sunday_and_rejects_fabricated_slots(): void
    {
        $this->travelTo(Carbon::parse('2026-11-29T00:00:00Z'));
        $explicit = [...self::PROPERTY_INTENT, 'check_in' => 'DATE_A', 'check_out' => 'DATE_B'];
        $this->fakeAvailabilityIntents([...$explicit, 'property' => 'PROPERTY_2'], $explicit, $explicit);
        $actor = User::factory()->customer()->create();
        $this->catalogProperty($this->catalogHost(), "Mây & Gió (QA)");
        $relative = [...$this->input(), 'message' => 'Mây & Gió (QA) còn phòng cho hai khách cuối tuần này không?'];
        $response = $this->send($relative, $actor)->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertStringContainsString('đã qua theo UTC', $response->json('errors.message.0'));
        $this->assertDatabaseMissing('chat_turns', ['request_id' => $relative['request_id']]);
        Http::assertNothingSent();

        $input = [...$this->input(), 'message' => 'Còn phòng cho hai khách tại Mây & Gió (QA) từ 29/11/2026 đến 01/12/2026 không?'];
        $this->send($input, $actor)->assertServiceUnavailable()->assertJsonPath('code', ChatProviderException::UNAVAILABLE);
        $this->assertSame(ChatTurn::FAILED, $this->turn($input)->status);
        $this->send($input, $actor)->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Chưa tìm thấy phòng công khai khả dụng từ 29/11/2026 đến 01/12/2026 cho 2 người.');
        $this->assertSame(2, $this->turn($input)->attempts);
        $this->assertSame('con phong cho [GUESTS] khach tai [PROPERTY] tu [DATE_A] den [DATE_B] khong?', Http::recorded()[1][0]->data()['contents'][0]['parts'][0]['text']);

        $this->send([...$this->input(), 'message' => 'Mây & Gió (QA) có phòng cho hai khách từ 29/11/2026 đến 01/12/2026 không?'], $actor)
            ->assertCreated()
            ->assertJsonPath('data.assistant_message.message', 'Chưa tìm thấy phòng công khai khả dụng từ 29/11/2026 đến 01/12/2026 cho 2 người.');
        $this->assertSame('[PROPERTY] co phong cho [GUESTS] khach tu [DATE_A] den [DATE_B] khong?', Http::recorded()[2][0]->data()['contents'][0]['parts'][0]['text']);
        $this->assertSame([], $this->questions);
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

    private function insertFaq(string $answer = self::FAQ_ANSWER, string $question = self::FAQ_QUESTION): int
    {
        $id = DB::table('faqs')->insertGetId(['question' => $question, 'answer' => $answer, 'status' => true]);
        $this->faqIds[] = $id;

        return $id;
    }

    private function catalogHost(): int
    {
        $id = User::factory()->host()->create()->id;
        $this->catalogHostIds[] = $id;

        return $id;
    }

    /** @param list<int> $roomIds */
    private function catalogAmenity(string $name, array $roomIds, bool $enabled = true): int
    {
        $id = DB::table('amenities')->insertGetId(['name' => $name, 'status' => $enabled]);
        $this->amenityIds[] = $id;
        DB::table('room_amenities')->insert(array_map(
            static fn (int $roomId): array => ['room_id' => $roomId, 'amenity_id' => $id],
            $roomIds,
        ));

        return $id;
    }

    /** @return array{id: int, type: int} */
    private function catalogProperty(int $hostId, string $name, string $status = 'ACTIVE', string $address = 'Địa chỉ QA'): array
    {
        $id = DB::table('properties')->insertGetId([
            'host_id' => $hostId, 'name' => $name, 'type' => 'HOTEL', 'address' => $address, 'status' => $status,
        ]);
        $type = DB::table('room_types')->insertGetId(['property_id' => $id, 'name' => 'Phòng QA']);

        return ['id' => $id, 'type' => $type];
    }

    /** @param array{id: int, type: int} $property */
    private function catalogRoom(
        array $property,
        string $name,
        string $price,
        int $capacity = 4,
        string $status = 'ACTIVE',
        ?int $typeId = null,
    ): int {
        return DB::table('rooms')->insertGetId([
            'property_id' => $property['id'], 'room_type_id' => $typeId ?? $property['type'],
            'room_number' => $name, 'name' => $name, 'capacity' => $capacity, 'price_per_night' => $price, 'status' => $status,
        ]);
    }

    private function catalogService(int $propertyId, string $name, string $unit, string $price, bool $enabled = true): int
    {
        return DB::table('services')->insertGetId([
            'property_id' => $propertyId, 'name' => $name, 'unit' => $unit, 'price' => $price, 'status' => $enabled,
        ]);
    }

    private function fakeAvailabilityIntents(array ...$intents): void
    {
        $this->availabilityHttpFake = true;
        config()->set(['chatbot.api_key' => 'fixture-key', 'chatbot.model' => 'gemini-3.5-flash-lite']);
        $sequence = Http::sequence();
        foreach ($intents as $intent) {
            $sequence->push(['candidates' => [[
                'finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($intent, JSON_THROW_ON_ERROR)]]],
            ]]]);
        }
        Http::fake([self::GEMINI_URL => $sequence]);
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
