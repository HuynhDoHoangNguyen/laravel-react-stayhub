<?php

namespace Tests\Feature\Chat;

use App\Exceptions\Chat\ChatProviderException;
use App\Models\User;
use App\Services\Chat\ChatProvider;
use App\Services\Chat\ChatProviderSlots;
use App\Services\Chat\SendGuestChatMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class GuestChatMessageHttpMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private const QUESTION = 'Hôm nay tôi buồn quá, bạn gợi ý tôi nên đi đâu đi?';
    private const PROMPT = 'Gợi ý hoạt động nhẹ nhàng gần nhà, bằng tiếng Việt.';

    private array $prompts = [];
    private ?ChatProviderException $error = null;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'sanctum.stateful' => ['localhost:5173'],
            'chatbot.enabled' => true,
            'chatbot.allow_external_questions' => true,
            'chatbot.guest.enabled' => true,
            'chatbot.guest.allow_provider' => true,
            'chatbot.max_concurrent' => 2,
            'chatbot.timeout_ms' => 12000,
        ]);
        Http::preventStrayRequests();
        Http::fake([]);
        $this->app->instance(ChatProvider::class, new class($this->answer(...)) implements ChatProvider {
            public function __construct(private readonly \Closure $answer) {}

            public function answer(string $approvedQuestion): string
            {
                return ($this->answer)($approvedQuestion);
            }
        });
    }

    public function test_guest_chat_projects_only_the_safe_topic_and_keeps_no_history(): void
    {
        $id = (string) Str::uuid();
        $this->send(self::QUESTION, $id)->assertOk()->assertExactJson(['data' => [
            'message' => 'Bạn có thể đi dạo hoặc ghé một quán cà phê yên tĩnh.', 'rooms' => [], 'action' => null,
        ]]);
        $this->send(self::QUESTION, $id)->assertConflict()->assertJsonPath('code', 'CHAT_GUEST_DUPLICATE');
        $this->assertSame([self::PROMPT], $this->prompts);
        foreach (['chat_sessions', 'chat_messages', 'chat_turns'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $keys = DB::table('cache')->pluck('key')->implode('|');
        $this->assertStringNotContainsString($id, $keys);
        $this->assertStringNotContainsString(self::QUESTION, DB::table('cache')->pluck('value')->implode('|'));
        Http::assertNothingSent();
    }

    public function test_guest_chat_routes_personal_questions_without_a_provider_call(): void
    {
        $this->send('Phòng tôi đã đặt là khi nào?')->assertOk()->assertJsonPath('data.action', 'bookings');
        $this->send('Gợi ý tôi đi đâu và hóa đơn nào tôi chưa thanh toán?')->assertOk()->assertJsonPath('data.action', 'invoices');
        $this->send('Tôi buồn quá, số điện thoại 0901234567')->assertOk()->assertJsonPath('data.action', null)
            ->assertJsonPath('data.message', 'Bạn hãy bỏ thông tin cá nhân hoặc mã bảo mật rồi hỏi lại nhé.');
        $this->send('Xin chào')->assertOk()->assertJsonPath('data.rooms', []);
        config()->set('chatbot.guest.allow_provider', false);
        $this->send(self::QUESTION)->assertOk()->assertJsonPath('data.action', null);
        $this->assertSame([], $this->prompts);
    }

    public function test_guest_chat_lists_only_public_rooms_with_real_property_ids(): void
    {
        $host = User::factory()->create();
        foreach ([[71, 'ACTIVE'], [72, 'INACTIVE']] as [$id, $status]) {
            DB::table('properties')->insert(['id' => $id, 'host_id' => $host->id, 'name' => 'Khách sạn '.$id,
                'status' => $status, 'type' => 'HOTEL', 'address' => 'QA']);
            DB::table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'Phòng đôi']);
        }
        foreach ([[81, 71, 'ACTIVE'], [82, 71, 'ACTIVE'], [83, 71, 'ACTIVE'], [84, 71, 'ACTIVE'], [85, 71, 'INACTIVE'], [86, 72, 'ACTIVE']] as [$id, $property, $status]) {
            DB::table('rooms')->insert(['id' => $id, 'property_id' => $property, 'room_type_id' => $property,
                'room_number' => (string) $id, 'name' => 'Phòng '.$id, 'capacity' => 2,
                'price_per_night' => in_array($id, [85, 86], true) ? '400000.00' : '650000.00', 'status' => $status]);
        }

        $this->send('Tìm phòng cho 2 người dưới 800 nghìn')->assertOk()->assertJsonPath('data.rooms', array_map(
            static fn (int $id): array => ['property_id' => 71, 'property_name' => 'Khách sạn 71', 'room_id' => $id, 'room_name' => 'Phòng '.$id],
            [81, 82, 83],
        ));
        $this->send('Tìm phòng cho 20 người')->assertOk()->assertJsonPath('data.action', 'search');
        $this->send('Tìm khách sạn tại Đà Lạt cho 2 người')->assertOk()->assertJsonPath('data.action', 'search');
        $this->send('2 người dưới 800 nghìn')->assertOk()->assertJsonPath('data.rooms.0.room_id', 81);
        $this->assertSame([], $this->prompts);
    }

    public function test_guest_chat_limits_session_ip_and_route_and_recovers_after_expiry(): void
    {
        $service = $this->app->make(SendGuestChatMessageService::class);
        for ($hit = 0; $hit < 5; $hit++) {
            $this->assertSame('answered', $service->send('session-a', '192.0.2.11', (string) Str::uuid(), 'Xin chào')['status']);
        }
        $limited = $service->send('session-a', '192.0.2.12', (string) Str::uuid(), 'Xin chào');
        $this->assertSame('limited', $limited['status']);
        $this->assertGreaterThan(0, $limited['retry_after']);
        for ($hit = 0; $hit < 10; $hit++) {
            $this->assertSame('answered', $service->send('other-'.$hit, '192.0.2.11', (string) Str::uuid(), 'Xin chào')['status']);
        }
        $this->assertSame('limited', $service->send('new-session', '192.0.2.11', (string) Str::uuid(), 'Xin chào')['status']);
        for ($hit = 0; $hit < 15; $hit++) {
            $this->assertSame('answered', $service->send('more-'.$hit, '198.51.100.'.$hit, (string) Str::uuid(), 'Xin chào')['status']);
        }
        $this->send(self::QUESTION)->assertTooManyRequests()->assertHeader('Access-Control-Expose-Headers', 'Retry-After')->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->send(self::QUESTION)->assertOk();
        $this->assertSame([self::PROMPT], $this->prompts);
    }

    public function test_guest_chat_shares_the_provider_slot_and_maps_timeout(): void
    {
        $pool = $this->app->make(ChatProviderSlots::class);
        $guest = $pool->acquire(public: true);
        $customer = $pool->acquire();
        $this->assertNotNull($guest);
        $this->assertNotNull($customer);
        try {
            $this->assertNull($pool->acquire());
            $this->send(self::QUESTION)->assertServiceUnavailable()->assertJsonPath('code', 'CHAT_BUSY');
            $this->assertSame([], $this->prompts);
        } finally {
            $guest->release();
            $customer->release();
        }
        $this->error = new ChatProviderException(ChatProviderException::TIMEOUT);
        $this->send(self::QUESTION)->assertStatus(504)->assertJsonPath('code', 'CHAT_PROVIDER_TIMEOUT');
        $this->error = new ChatProviderException(ChatProviderException::RATE_LIMITED, 7);
        $this->send(self::QUESTION)->assertServiceUnavailable()->assertHeader('Retry-After', '7');
        $this->assertSame([self::PROMPT, self::PROMPT], $this->prompts);
        Http::assertNothingSent();
    }

    public function test_guest_chat_validates_input_feature_flag_and_session(): void
    {
        config()->set('chatbot.guest.enabled', false);
        $this->send(self::QUESTION)->assertNotFound();
        config()->set('chatbot.guest.enabled', true);
        $this->send(str_repeat('á', 501))->assertUnprocessable();
        $this->send('   ')->assertUnprocessable();
        $this->postJson('/api/public/chat/messages', ['message' => self::QUESTION, 'request_id' => (string) Str::uuid()])->assertForbidden();
        $this->assertSame([], $this->prompts);
    }

    private function send(string $message, ?string $id = null): TestResponse
    {
        return $this->withCredentials()->withCookie(config('session.cookie'), app('session')->driver()->getId())
            ->postJson('/api/public/chat/messages', ['message' => $message, 'request_id' => $id ?? (string) Str::uuid()], [
            'Origin' => 'http://localhost:5173',
        ]);
    }

    private function answer(string $prompt): string
    {
        $this->prompts[] = $prompt;
        if ($this->error !== null) throw $this->error;

        return 'Bạn có thể đi dạo hoặc ghé một quán cà phê yên tĩnh.';
    }
}
