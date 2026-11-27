<?php

namespace Tests\Feature\Chat;

use App\Exceptions\Chat\ChatProviderException;
use App\Models\User;
use App\Services\Chat\ChatProvider;
use App\Services\Chat\ChatProviderSlots;
use App\Services\Chat\SendGuestChatMessageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class GuestChatMessageHttpMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private const QUESTION = 'Hôm nay tôi buồn quá, bạn gợi ý tôi nên đi đâu đi?';
    private const PROMPT = 'Gợi ý hoạt động nhẹ nhàng gần nhà, bằng tiếng Việt.';
    private const FAQ_QUESTION = 'Làm thế nào để đặt phòng?';
    private const FAQ_ANSWER = 'Chọn cơ sở, phòng và ngày lưu trú, sau đó đăng nhập để gửi yêu cầu đặt phòng.';
    private const FAQ_MISSING = 'Chưa tìm thấy câu trả lời trong FAQ công khai.';

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

    public function test_public_faq_answers_guest_and_active_customer_without_storing_chat(): void
    {
        DB::table('faqs')->insert(['question' => self::FAQ_QUESTION, 'answer' => self::FAQ_ANSWER, 'status' => true]);
        $before = DB::table('faqs')->get()->all();
        $id = (string) Str::uuid();

        $this->assertPublicTextReply(self::FAQ_QUESTION, self::FAQ_ANSWER, $id);
        $this->send(self::FAQ_QUESTION, $id)->assertConflict()->assertJsonPath('code', 'CHAT_GUEST_DUPLICATE');
        $this->actingAs(User::factory()->customer()->create(), 'web');
        $this->assertPublicTextReply("fAq: LÀM THẾ NÀO   ĐỂ\tĐẶT PHÒNG?!", self::FAQ_ANSWER);

        $this->assertEquals($before, DB::table('faqs')->get()->all());
        foreach (['chat_sessions', 'chat_messages', 'chat_turns'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], $this->prompts);
        Http::assertNothingSent();
    }

    public function test_public_faq_falls_back_for_unknown_missing_disabled_or_duplicate_rows(): void
    {
        $fixture = ['question' => self::FAQ_QUESTION, 'answer' => self::FAQ_ANSWER, 'status' => false];
        DB::table('faqs')->insert(['question' => 'Nên chuẩn bị hành lý thế nào?', 'answer' => 'Mục chưa duyệt.', 'status' => true]);
        $this->assertPublicTextReply('FAQ: Nên chuẩn bị hành lý thế nào?', self::FAQ_MISSING);
        $this->assertPublicTextReply(self::FAQ_QUESTION, self::FAQ_MISSING);

        $id = DB::table('faqs')->insertGetId($fixture);
        $this->assertPublicTextReply(self::FAQ_QUESTION, self::FAQ_MISSING);
        DB::table('faqs')->where('id', $id)->update(['status' => true]);
        DB::table('faqs')->insert(array_replace($fixture, ['status' => true]));
        $this->assertPublicTextReply(self::FAQ_QUESTION, self::FAQ_MISSING);

        $this->assertSame([], $this->prompts);
        Http::assertNothingSent();
    }

    public function test_public_faq_falls_back_for_unapproved_answer_versions_and_collation_aliases(): void
    {
        $id = DB::table('faqs')->insertGetId(['question' => self::FAQ_QUESTION, 'answer' => '', 'status' => true]);
        foreach (['', 'Nội dung thay đổi chưa được duyệt.', str_repeat('á', 1001)] as $answer) {
            DB::table('faqs')->where('id', $id)->update(['answer' => $answer]);
            $this->assertPublicTextReply(self::FAQ_QUESTION, self::FAQ_MISSING);
        }

        DB::table('faqs')->where('id', $id)->update([
            'question' => 'Lam thế nào để đặt phòng?', 'answer' => self::FAQ_ANSWER,
        ]);
        $this->assertDatabaseHas('faqs', ['question' => self::FAQ_QUESTION]);
        $this->assertPublicTextReply(self::FAQ_QUESTION, self::FAQ_MISSING);
        $this->assertSame([], $this->prompts);
        Http::assertNothingSent();
    }

    public function test_public_faq_keeps_role_and_personal_input_guards(): void
    {
        $this->send('FAQ: Mật khẩu của tôi là gì?')->assertOk()
            ->assertJsonPath('data.message', 'Bạn hãy bỏ thông tin cá nhân hoặc mã bảo mật rồi hỏi lại nhé.');
        $this->send('FAQ: Hóa đơn nào tôi chưa thanh toán?')->assertOk()->assertJsonPath('data.action', 'invoices');
        $this->actingAs(User::factory()->customer()->blocked()->create(), 'web');
        $this->send(self::FAQ_QUESTION)->assertForbidden();
        $this->actingAs(User::factory()->host()->create(), 'web');
        $this->send(self::FAQ_QUESTION)->assertForbidden();
        $this->assertSame([], $this->prompts);
        Http::assertNothingSent();
    }

    public function test_travel_chat_recommends_catalog_properties_to_guests_and_customers_without_ai(): void
    {
        config()->set(['chatbot.enabled' => false, 'chatbot.guest.allow_provider' => false]);
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Chỗ nghỉ ven hồ', address: '12 Trần Hưng Đạo, TP. Đà Lạt, Lâm Đồng');
        $this->catalogProperty($host, 72, 'Chỗ nghỉ ven sông', address: '12 Bạch Đằng, Hải Châu, Đà Nẵng');
        $this->catalogRoom(81, 71, '420000.50');
        $this->catalogRoom(82, 72, '750000.00');

        $questions = [
            'Tháng 11 nên đi đâu?' => 'tháng 11',
            'Ngày đẹp trời nên đi đâu?' => 'chuyến đi của bạn',
            'Toi vs nguoi yeu nen di dau lang manx cho tuan trang mat?' => 'chuyến đi của hai bạn',
        ];

        foreach ([null, User::factory()->create()] as $customer) {
            if ($customer !== null) {
                $this->actingAs($customer, 'web');
                app('session')->driver()->migrate();
            }
            foreach ($questions as $question => $intro) {
                $this->send($question)->assertOk()->assertJsonPath('data.message', fn (string $message) => str_contains($message, $intro))
                    ->assertJsonPath('data.destinations.*.city', ['Đà Lạt', 'Đà Nẵng'])
                    ->assertJsonPath('data.destinations.0.properties', [
                        ['property_id' => 71, 'property_name' => 'Chỗ nghỉ ven hồ', 'starting_price' => '420000.50'],
                    ])
                    ->assertJsonPath('data.destinations.1.properties.0.property_id', 72)
                    ->assertJsonPath('data.rooms', [])
                    ->assertJsonPath('data.action', null);
            }
        }

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_travel_chat_checks_city_room_status_capacity_and_card_limit(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Chỗ nghỉ ven hồ', address: 'Đà Lạt, Lâm Đồng');
        $this->catalogProperty($host, 72, 'Chỗ nghỉ ẩn', 'INACTIVE', 'Đà Lạt, Lâm Đồng');
        $this->catalogProperty($host, 73, 'Đà Lạt Boutique', address: 'TP. Hồ Chí Minh');
        $this->catalogProperty($host, 74, 'Chỗ nghỉ phòng đơn', address: 'Đà Nẵng');
        $this->catalogProperty($host, 75, 'Chỗ nghỉ ngừng mở phòng', address: 'Nha Trang');
        $this->catalogProperty($host, 76, 'Chỗ nghỉ thứ hai', address: 'Đà Lạt, Lâm Đồng');
        $this->catalogProperty($host, 77, 'Chỗ nghỉ thứ ba', address: 'Đà Lạt, Lâm Đồng');
        $this->catalogRoom(81, 71, '590000.00', ['capacity' => 2]);
        $this->catalogRoom(82, 72, '450000.00');
        $this->catalogRoom(83, 73, '450000.00');
        $this->catalogRoom(84, 74, '450000.00', ['capacity' => 1]);
        $this->catalogRoom(85, 75, '450000.00', ['status' => 'INACTIVE']);
        $this->catalogRoom(86, 71, '350000.00', ['capacity' => 1]);
        $this->catalogRoom(87, 76, '650000.00');
        $this->catalogRoom(88, 77, '750000.00');

        $this->send('Tuần trăng mật đi đâu cho lãng mạn?')->assertOk()
            ->assertJsonPath('data.destinations.*.city', ['Đà Lạt'])
            ->assertJsonPath('data.destinations.0.properties.*.property_id', [71, 76])
            ->assertJsonPath('data.destinations.0.properties.*.starting_price', ['590000.00', '650000.00']);
        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_travel_chat_suggests_search_when_catalog_is_empty_and_keeps_personal_guard(): void
    {
        $this->send('Tháng 11 nên đi đâu?')->assertOk()
            ->assertJsonPath('data.destinations', [])
            ->assertJsonPath('data.action', 'search');
        $this->send('Tháng 11 nên đi đâu và hóa đơn nào tôi chưa thanh toán?')->assertOk()
            ->assertJsonPath('data.action', 'invoices')
            ->assertJsonMissingPath('data.destinations');
        $this->assertCatalogDoesNotCallOrStoreChat();
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

    public function test_catalog_chat_treats_under_as_exclusive_without_changing_public_catalog_price_contract(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogProperty($host, 72, 'Cơ sở khác', 'INACTIVE');
        $this->catalogRoom(81, 71, '999999.99');
        $this->catalogRoom(82, 71, '1000000.00');
        $this->catalogRoom(83, 71, '1000000.01');
        $this->catalogRoom(84, 71, '10000.00', ['capacity' => 3]);
        $this->catalogRoom(85, 72, '10000.00');
        $this->catalogRoom(86, 71, '10000.00', ['status' => 'MAINTENANCE']);
        $this->catalogRoom(87, 71, '10000.00', ['room_type_id' => 72]);

        $under = $this->send('Có phòng nào cho 4 người dưới 1 triệu?')->assertOk();
        $under->assertExactJson(['data' => [
            'message' => 'Bạn có thể tham khảo các phòng đang niêm yết này. Hãy kiểm tra ngày lưu trú trên trang chi tiết.',
            'action' => null,
            'rooms' => [['property_id' => 71, 'property_name' => 'Sunrise', 'room_id' => 81, 'room_name' => 'Phòng 81']],
        ]]);
        $this->send('Tìm phòng cho 4 người tối đa 1 triệu tại "SUNRISE"')->assertOk()
            ->assertJsonPath('data.rooms.*.room_id', [81, 82]);
        $this->send('Tìm phòng cho 4 người ngân sách 1 triệu')->assertOk()
            ->assertJsonPath('data.rooms.*.room_id', [81, 82]);
        $this->send('Tìm phòng cho 4 người dưới 1,5 triệu')->assertOk()
            ->assertJsonPath('data.action', 'search')->assertJsonPath('data.rooms', []);
        $this->getJson('/api/public/rooms?guest_count=4&max_price=1000000')->assertOk()
            ->assertJsonPath('data.*.id', [81, 82]);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_catalog_chat_resolves_duplicate_names_before_room_filters_and_requires_a_choice(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogProperty($host, 72, 'Súnrise');
        $this->catalogProperty($host, 73, 'SUNRISE', 'BLOCKED');
        $this->catalogRoom(81, 71, '999999.99');
        $this->catalogRoom(82, 72, '1000000.00');
        $this->catalogRoom(83, 73, '900000.00');
        $query = 'Tìm phòng cho 4 người dưới 1 triệu tại "SUNRISE"';

        $this->send($query)->assertOk()->assertExactJson(['data' => [
            'message' => "Có nhiều cơ sở công khai trùng tên:\n#71 – Sunrise, Địa chỉ QA 71\n#72 – Súnrise, Địa chỉ QA 72\nBạn gửi lại số khách và ngân sách kèm mã đã chọn, ví dụ tại #71.",
            'action' => null,
            'rooms' => [],
        ]]);
        $this->actingAs(User::factory()->customer()->create(), 'web');
        $this->send('Tìm phòng cho 4 người dưới 1 triệu tại #71')->assertOk()
            ->assertJsonPath('data.rooms.*.property_id', [71])->assertJsonPath('data.rooms.*.room_id', [81]);
        $this->catalogProperty($host, 74, 'Sunrise');
        $this->send($query)->assertOk()->assertJsonPath('data.action', null)->assertJsonPath('data.rooms', [])
            ->assertJsonPath('data.message', fn (string $message) => str_contains($message, '#74') && ! str_contains($message, '#73'));
        $this->catalogProperty($host, 75, 'Sunrise');
        $this->send($query)->assertOk()->assertJsonPath('data.action', 'search')->assertJsonPath('data.rooms', []);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_catalog_chat_checks_selected_ids_and_keeps_personal_input_guard(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 1234567, 'Cơ sở đang mở');
        $this->catalogProperty($host, 1234568, 'Cơ sở ẩn', 'INACTIVE');
        $this->catalogRoom(81, 1234567, '800000.00');
        $this->catalogRoom(82, 1234568, '800000.00');
        $prefix = 'Tìm phòng cho 4 người tại #';

        $this->send($prefix.'1234567')->assertOk()->assertJsonPath('data.rooms.*.room_id', [81]);
        $hidden = $this->send($prefix.'1234568')->assertOk()->assertJsonPath('data.action', 'search')->assertJsonPath('data.rooms', [])->json();
        $this->send($prefix.'1234569')->assertOk()->assertExactJson($hidden);
        $this->send('Tìm phòng cho 4 người, số điện thoại 0901234567 tại #1234567')->assertOk()
            ->assertJsonPath('data.message', 'Bạn hãy bỏ thông tin cá nhân hoặc mã bảo mật rồi hỏi lại nhé.');
        DB::table('properties')->where('id', 1234567)->update(['status' => 'BLOCKED']);
        $this->send($prefix.'1234567')->assertOk()->assertExactJson($hidden);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_catalog_chat_asks_for_context_and_sends_dated_queries_to_search(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Nơi ở QA');
        foreach ([81, 82, 83, 84] as $roomId) {
            $this->catalogRoom($roomId, 71, '900000.00');
        }
        $this->send('Chỗ này có phòng cho 4 người không?')->assertOk()->assertJsonPath('data.rooms', [])
            ->assertJsonPath('data.message', fn (string $message) => str_contains($message, 'tên hoặc mã'));
        foreach (['20/12/2026', '2026-12-20'] as $date) {
            $this->send("Tìm phòng cho 4 người từ $date tại #71")->assertOk()
                ->assertJsonPath('data.action', 'search')->assertJsonPath('data.rooms', []);
        }
        $this->send('Còn phòng ngày mai không?')->assertOk()
            ->assertJsonPath('data.action', 'search')->assertJsonPath('data.rooms', []);
        $this->send('Tìm phòng cho 4 người tại #71')->assertOk()->assertJsonPath('data.rooms.*.room_id', [81, 82, 83]);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_wifi_chat_lists_only_public_rooms_with_an_enabled_wifi_link_before_limiting_results(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogProperty($host, 72, 'Cơ sở khác');
        $wifi = DB::table('amenities')->insertGetId(['name' => 'Wi-Fi', 'status' => true]);
        $wifiAlias = DB::table('amenities')->insertGetId(['name' => 'Wifi', 'status' => true]);
        $spacedAlias = DB::table('amenities')->insertGetId(['name' => 'Wi Fi', 'status' => true]);
        $disabled = DB::table('amenities')->insertGetId(['name' => 'Wi-Fi', 'status' => false]);
        $other = DB::table('amenities')->insertGetId(['name' => 'Wi-Fi trả phí', 'status' => true]);

        $this->catalogRoom(81, 71, '700000.00', ['status' => 'INACTIVE']);
        $this->catalogRoom(82, 71, '700000.00', ['room_type_id' => 72]);
        $this->catalogRoom(83, 72, '700000.00');
        foreach ([84, 85, 86, 87, 88, 89, 90] as $roomId) {
            $this->catalogRoom($roomId, 71, '700000.00');
        }

        foreach ([81 => [$wifi], 82 => [$wifi], 83 => [$wifi], 84 => [$disabled], 85 => [$other],
            87 => [$wifi, $wifiAlias], 88 => [$wifiAlias], 89 => [$spacedAlias], 90 => [$wifi]] as $roomId => $amenities) {
            foreach ($amenities as $amenityId) {
                DB::table('room_amenities')->insert(['room_id' => $roomId, 'amenity_id' => $amenityId]);
            }
        }

        $this->send('Có Wi-Fi không tại #71?')->assertOk()->assertExactJson(['data' => [
            'message' => 'Một số phòng công khai tại Sunrise có Wi-Fi được niêm yết.',
            'action' => null,
            'rooms' => [
                ['property_id' => 71, 'property_name' => 'Sunrise', 'room_id' => 87, 'room_name' => 'Phòng 87'],
                ['property_id' => 71, 'property_name' => 'Sunrise', 'room_id' => 88, 'room_name' => 'Phòng 88'],
                ['property_id' => 71, 'property_name' => 'Sunrise', 'room_id' => 89, 'room_name' => 'Phòng 89'],
            ],
        ]]);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_wifi_chat_resolves_a_named_public_room_and_detects_ambiguous_names_before_filtering_wifi(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 1234567, 'Sunrise');
        $this->catalogProperty($host, 72, 'Cơ sở khác');
        $this->catalogRoom(81, 1234567, '700000.00', ['name' => 'Deluxe']);
        $this->catalogRoom(82, 72, '700000.00', ['name' => 'Phòng riêng bên kia']);
        $this->catalogRoom(83, 1234567, '700000.00', ['name' => 'Suite']);
        $enabled = DB::table('amenities')->insertGetId(['name' => 'WIFI', 'status' => true]);
        $disabled = DB::table('amenities')->insertGetId(['name' => 'Wi-Fi', 'status' => false]);
        DB::table('room_amenities')->insert([
            ['room_id' => 81, 'amenity_id' => $enabled],
            ['room_id' => 82, 'amenity_id' => $enabled],
            ['room_id' => 83, 'amenity_id' => $disabled],
        ]);
        $query = 'Phòng "DELUXE" có WiFi không tại #1234567?';

        $this->actingAs(User::factory()->customer()->create(), 'web');
        $this->send($query)->assertOk()->assertExactJson(['data' => [
            'message' => 'Phòng Deluxe tại Sunrise có Wi-Fi được niêm yết.',
            'action' => null,
            'rooms' => [['property_id' => 1234567, 'property_name' => 'Sunrise', 'room_id' => 81, 'room_name' => 'Deluxe']],
        ]]);
        $this->send('Phòng "Suite" có Wi Fi không tại #1234567?')->assertOk()->assertExactJson(['data' => [
            'message' => 'Chưa thấy thông tin Wi-Fi trong danh mục công khai.', 'action' => null, 'rooms' => [],
        ]]);
        $this->send('Phòng "Phòng riêng bên kia" có Wi-Fi không tại #1234567?')->assertOk()
            ->assertJsonPath('data.message', 'Chưa tìm thấy phòng công khai phù hợp tại cơ sở này.')
            ->assertJsonPath('data.rooms', []);
        $this->catalogRoom(84, 1234567, '700000.00', ['name' => 'Déluxe']);
        $this->send($query)->assertOk()->assertJsonPath('data.rooms', [])
            ->assertJsonPath('data.message', fn (string $message) => str_contains($message, 'trùng tên'));

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_wifi_chat_asks_for_a_property_code_and_keeps_visibility_dates_and_personal_guards(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogProperty($host, 72, 'Cơ sở ẩn', 'BLOCKED');
        $this->catalogRoom(81, 71, '700000.00');
        $this->catalogRoom(82, 72, '700000.00');
        $wifi = DB::table('amenities')->insertGetId(['name' => 'Wi-Fi', 'status' => true]);
        DB::table('room_amenities')->insert(['room_id' => 82, 'amenity_id' => $wifi]);

        $this->send('Homestay này có WiFi không?')->assertOk()->assertJsonPath('data.rooms', [])
            ->assertJsonPath('data.message', fn (string $message) => str_contains($message, 'mã cơ sở'));
        $hidden = $this->send('Có Wi-Fi không tại #72?')->assertOk()
            ->assertJsonPath('data.action', 'search')->assertJsonPath('data.rooms', [])->json();
        $this->send('Có Wi-Fi không tại #73?')->assertOk()->assertExactJson($hidden);
        $this->send('Còn phòng có Wi-Fi ngày 20/12/2026 tại #71?')->assertOk()
            ->assertJsonPath('data.action', 'search')->assertJsonPath('data.rooms', []);
        $this->send('Có Wi-Fi không, số điện thoại 0901234567 tại #71?')->assertOk()
            ->assertJsonPath('data.message', 'Bạn hãy bỏ thông tin cá nhân hoặc mã bảo mật rồi hỏi lại nhé.');

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_wifi_chat_reports_missing_public_facts_when_a_link_is_disabled(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogRoom(81, 71, '700000.00');
        $wifi = DB::table('amenities')->insertGetId(['name' => 'Wi-Fi', 'status' => true]);
        DB::table('room_amenities')->insert(['room_id' => 81, 'amenity_id' => $wifi]);

        $this->send('Có Wi‑Fi không tại #71?')->assertOk()->assertJsonPath('data.rooms.*.room_id', [81]);
        DB::table('amenities')->where('id', $wifi)->update(['status' => false]);
        $this->send('Có Wi-Fi không tại #71?')->assertOk()->assertExactJson(['data' => [
            'message' => 'Chưa thấy thông tin Wi-Fi trong danh mục công khai.', 'action' => null, 'rooms' => [],
        ]]);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_service_chat_reads_exact_public_rates_by_property_for_guest_and_customer(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogProperty($host, 1234567, 'Riverside');
        $this->catalogService(71, 'Đưa đón sân bay', 'lượt', '250000.25');
        $this->catalogService(1234567, 'Đưa đón sân bay', 'Lượt', '350000.00');
        $this->catalogService(71, 'Giặt ủi', 'kg', '50000.00');
        $before = DB::table('services')->orderBy('id')->get()->all();

        $this->send('Giá đưa đón sân bay là bao nhiêu?')->assertOk()->assertJsonPath('data.rooms', [])
            ->assertJsonPath('data.message', fn (string $message) => str_contains($message, 'mã cơ sở'));
        $this->assertPublicTextReply('Giá đưa đón sân bay là bao nhiêu tại #71?',
            'Tại Sunrise, dịch vụ Đưa đón sân bay có đơn giá niêm yết 250.000,25 đ/lượt.');

        $this->actingAs(User::factory()->customer()->create(), 'web');
        $this->assertPublicTextReply('Giá đưa đón sân bay tại #1234567?',
            'Tại Riverside, dịch vụ Đưa đón sân bay có đơn giá niêm yết 350.000 đ/lượt.');
        $this->assertPublicTextReply('Có dịch vụ giặt ủi không tại #71?',
            'Tại Sunrise, dịch vụ Giặt ủi có đơn giá niêm yết 50.000 đ/kg.');
        $this->assertEquals($before, DB::table('services')->orderBy('id')->get()->all());
        $this->assertDatabaseCount('booking_services', 0);
        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_service_chat_does_not_disclose_hidden_disabled_or_unrelated_services(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogProperty($host, 72, 'Cơ sở ẩn', 'BLOCKED');
        $this->catalogProperty($host, 73, 'Riverside');
        $this->catalogService(71, 'Giặt ủi', 'kg', '59000.00', false);
        $this->catalogService(72, 'Giặt ủi', 'kg', '60000.00');
        $this->catalogService(73, 'Giặt ủi', 'kg', '61000.00');
        $this->catalogService(71, 'Đưa đón sân bay nội bộ', 'lượt', '260000.00');
        $missing = 'Chưa tìm thấy thông tin dịch vụ công khai phù hợp.';

        foreach ([71, 72, 74] as $propertyId) {
            $this->assertPublicTextReply("Có dịch vụ giặt ủi không tại #$propertyId?", $missing);
        }
        $this->assertPublicTextReply('Giá đưa đón sân bay tại #71?', $missing);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_service_chat_handles_duplicate_names_unapproved_units_and_zero_listed_price(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogService(71, 'Đưa đón sân bay', 'ca', '270000.00');
        $serviceId = $this->catalogService(71, 'Giặt ủi', 'kg', '0.00');
        $laundry = 'Có dịch vụ giặt ủi không tại #71?';
        $missing = 'Chưa tìm thấy thông tin dịch vụ công khai phù hợp.';

        $this->assertPublicTextReply('Giá đưa đón sân bay tại #71?', $missing);
        $this->assertPublicTextReply($laundry, 'Tại Sunrise, dịch vụ Giặt ủi có đơn giá niêm yết 0 đ/kg.');
        $this->catalogService(71, 'GIẶT ỦI', 'kg', '45000.00');
        $this->assertPublicTextReply($laundry,
            'Có nhiều dịch vụ công khai trùng tên tại cơ sở này. Chưa thể xác định một đơn giá niêm yết duy nhất.');
        DB::table('services')->where('id', $serviceId)->update(['status' => false]);
        $this->assertPublicTextReply($laundry, 'Tại Sunrise, dịch vụ Giặt ủi có đơn giá niêm yết 45.000 đ/kg.');

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_service_chat_keeps_personal_input_guards_and_refuses_to_change_a_booking(): void
    {
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogService(71, 'Giặt ủi', 'kg', '50000.00');

        $this->assertPublicTextReply('Thêm dịch vụ giặt ủi tại #71',
            'Mình chỉ có thể tra cứu thông tin dịch vụ niêm yết. Bạn hãy liên hệ cơ sở để yêu cầu dịch vụ.');
        $this->assertPublicTextReply('Có dịch vụ massage không tại #71?', 'Chưa tìm thấy thông tin dịch vụ công khai phù hợp.');
        $this->assertPublicTextReply('Giá giặt ủi và đưa đón sân bay tại #71?',
            'Bạn hỏi từng dịch vụ một để mình kiểm tra đúng thông tin nhé.');
        $this->assertPublicTextReply('Hỏi giá giặt ủi, số điện thoại 0901234567 tại #71?',
            'Bạn hãy bỏ thông tin cá nhân hoặc mã bảo mật rồi hỏi lại nhé.');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_services', 0);
        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_availability_chat_reads_a_single_public_room_for_guest_and_customer_using_utc_dates(): void
    {
        // It is already 23 November in Vietnam, while the Booking validator's UTC day is 22 November.
        $this->travelTo(Carbon::parse('2026-11-22 18:30:00', 'UTC'));
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogRoom(1234567, 71, '900000.00', ['name' => 'Phòng Gió', 'capacity' => 2]);
        $expected = ['data' => [
            'message' => 'Phòng Gió khả dụng tại thời điểm kiểm tra, từ 22/11/2026 đến 24/11/2026 cho 2 người.',
            'action' => null,
            'rooms' => [['property_id' => 71, 'property_name' => 'Sunrise', 'room_id' => 1234567, 'room_name' => 'Phòng Gió']],
        ]];

        $this->send('Còn phòng #1234567 từ 22/11/2026 đến 24/11/2026 cho 2 người không?')->assertOk()->assertExactJson($expected);
        $this->actingAs(User::factory()->customer()->create(), 'web');
        $this->send('Còn phòng #1234567 từ 2026-11-22 đến 2026-11-24 cho 2 người không?')->assertOk()->assertExactJson($expected);
        $this->assertPublicTextReply('Còn phòng #1234567 từ 2026-11-22 đến 2026-11-24 cho 2 người không? Số điện thoại 0901234567',
            'Bạn hãy bỏ thông tin cá nhân hoặc mã bảo mật rồi hỏi lại nhé.');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_availability_chat_uses_half_open_stays_and_excludes_cancelled_bookings(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 12:00:00', 'UTC'));
        $host = User::factory()->host()->create();
        $customer = User::factory()->customer()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogRoom(81, 71, '900000.00');
        $bookingId = DB::table('bookings')->insertGetId([
            'booking_code' => 'BK-PRIVATE-FIXTURE',
            'customer_id' => $customer->id, 'room_id' => 81,
            'check_in_date' => '2026-12-20', 'check_out_date' => '2026-12-22', 'guest_count' => 2,
            'number_of_nights' => 2, 'price_per_night' => '900000.00', 'room_total' => '1800000.00',
            'status' => 'PENDING', 'note' => 'Thông tin fixture riêng',
        ]);
        $query = 'Còn phòng #81 từ 20/12/2026 đến 22/12/2026 cho 2 người không?';

        foreach ([['18/12/2026', '20/12/2026'], ['22/12/2026', '24/12/2026']] as [$in, $out]) {
            $this->send("Còn phòng #81 từ $in đến $out cho 2 người không?")->assertOk()
                ->assertJsonPath('data.rooms.*.room_id', [81]);
        }

        $bookingBefore = DB::table('bookings')->where('id', $bookingId)->first();
        $this->assertAvailabilityUnavailable($query);
        $this->assertEquals($bookingBefore, DB::table('bookings')->where('id', $bookingId)->first());
        DB::table('bookings')->where('id', $bookingId)->update(['status' => 'CONFIRMED']);
        $this->assertAvailabilityUnavailable($query);
        DB::table('bookings')->where('id', $bookingId)->update(['status' => 'CANCELLED']);
        $this->send($query)->assertOk()->assertJsonPath('data.rooms.*.room_id', [81]);

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_availability_chat_uses_maintenance_and_capacity_without_returning_reasons(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 12:00:00', 'UTC'));
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogRoom(81, 71, '900000.00', ['capacity' => 2]);
        $maintenanceId = DB::table('maintenances')->insertGetId([
            'room_id' => 81, 'start_date' => '2026-12-20', 'end_date' => '2026-12-22',
            'status' => 'SCHEDULED', 'reason' => 'Lý do fixture riêng',
        ]);
        $query = 'Còn phòng #81 từ 20/12/2026 đến 22/12/2026 cho 2 người không?';

        $maintenanceBefore = DB::table('maintenances')->where('id', $maintenanceId)->first();
        $this->assertAvailabilityUnavailable($query);
        $this->assertEquals($maintenanceBefore, DB::table('maintenances')->where('id', $maintenanceId)->first());
        $this->send('Còn phòng #81 từ 22/12/2026 đến 24/12/2026 cho 2 người không?')->assertOk()
            ->assertJsonPath('data.rooms.*.room_id', [81]);
        DB::table('maintenances')->where('id', $maintenanceId)->update(['status' => 'IN_PROGRESS']);
        $this->assertAvailabilityUnavailable($query);
        DB::table('maintenances')->where('id', $maintenanceId)->update(['status' => 'COMPLETED']);
        $this->send($query)->assertOk()->assertJsonPath('data.rooms.*.room_id', [81]);
        $this->assertAvailabilityUnavailable('Còn phòng #81 từ 20/12/2026 đến 22/12/2026 cho 3 người không?');

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_availability_chat_uses_the_same_fallback_for_hidden_missing_and_invalid_catalog_rooms(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 12:00:00', 'UTC'));
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogProperty($host, 72, 'Cơ sở ẩn', 'BLOCKED');
        $this->catalogRoom(81, 71, '900000.00', ['status' => 'INACTIVE']);
        $this->catalogRoom(82, 72, '900000.00');
        $this->catalogRoom(83, 71, '900000.00', ['room_type_id' => 72]);
        foreach ([81, 82, 83, 84] as $roomId) {
            $this->send("Còn phòng #$roomId từ 20/12/2026 đến 22/12/2026 cho 2 người không?")->assertOk()->assertExactJson(['data' => [
                'message' => 'Chưa tìm thấy thông tin công khai phù hợp cho mã phòng này.',
                'action' => 'search', 'rooms' => [],
            ]]);
        }

        $this->assertCatalogDoesNotCallOrStoreChat();
    }

    public function test_availability_chat_guides_invalid_or_incomplete_dates_and_guest_counts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 12:00:00', 'UTC'));
        $host = User::factory()->host()->create();
        $this->catalogProperty($host, 71, 'Sunrise');
        $this->catalogRoom(81, 71, '900000.00');
        foreach ([
            'Còn phòng #81 không?',
            'Còn phòng #81 từ 21/11/2026 đến 23/11/2026 cho 2 người không?',
            'Còn phòng #81 từ 31/02/2027 đến 02/03/2027 cho 2 người không?',
            'Còn phòng #81 từ 20/12/2026 đến 20/12/2026 cho 2 người không?',
            'Còn phòng #81 từ 20/12/2026 đến 22/12/2026 cho 0 người không?',
        ] as $question) {
            $this->send($question)->assertOk()->assertExactJson(['data' => [
                'message' => 'Bạn cho mình mã phòng, ngày nhận, ngày trả và số khách hợp lệ nhé. Mẫu: Còn phòng #81 từ DD/MM/YYYY đến DD/MM/YYYY cho 2 người không?',
                'action' => 'search', 'rooms' => [],
            ]]);
        }

        $this->assertCatalogDoesNotCallOrStoreChat();
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

    private function catalogProperty(User $host, int $id, string $name, string $status = 'ACTIVE', ?string $address = null): void
    {
        DB::table('properties')->insert(['id' => $id, 'host_id' => $host->id, 'name' => $name,
            'status' => $status, 'type' => 'HOTEL', 'address' => $address ?? 'Địa chỉ QA '.$id]);
        DB::table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'Phòng đôi']);
    }

    private function catalogRoom(int $id, int $propertyId, string $price, array $extra = []): void
    {
        DB::table('rooms')->insert(array_replace(['id' => $id, 'property_id' => $propertyId, 'room_type_id' => $propertyId,
            'room_number' => (string) $id, 'name' => 'Phòng '.$id, 'capacity' => 4, 'price_per_night' => $price, 'status' => 'ACTIVE'], $extra));
    }

    private function catalogService(int $propertyId, string $name, string $unit, string $price, bool $active = true): int
    {
        return DB::table('services')->insertGetId(['property_id' => $propertyId, 'name' => $name,
            'description' => 'Chi tiết fixture chỉ dành cho quản lý', 'unit' => $unit, 'price' => $price, 'status' => $active]);
    }

    private function assertAvailabilityUnavailable(string $question): void
    {
        $this->send($question)->assertOk()->assertExactJson(['data' => [
            'message' => 'Phòng chưa khả dụng tại thời điểm kiểm tra cho khoảng ngày và số khách đã nhập.',
            'action' => 'search', 'rooms' => [],
        ]]);
    }

    private function assertCatalogDoesNotCallOrStoreChat(): void
    {
        foreach (['chat_sessions', 'chat_messages', 'chat_turns'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], $this->prompts);
        Http::assertNothingSent();
    }

    private function assertPublicTextReply(string $question, string $answer, ?string $id = null): void
    {
        $this->send($question, $id)->assertOk()->assertExactJson(['data' => [
            'message' => $answer, 'action' => null, 'rooms' => [],
        ]]);
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
