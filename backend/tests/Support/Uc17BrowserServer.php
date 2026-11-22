<?php

declare(strict_types=1);

use App\Exceptions\Chat\ChatProviderException;
use App\Services\Chat\ChatProvider;
use App\Services\Chat\GeminiChatProvider;
use GuzzleHttp\Psr7\Request as OutboundRequest;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Tv3AcceptanceMySqlEnvironment;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';

$manifest = Tv3AcceptanceMySqlEnvironment::beforeBoot($backend);
$isOrigin = static fn (mixed $url): bool => is_string($url)
    && preg_match('/\Ahttp:\/\/127\.0\.0\.1:[1-9][0-9]{0,4}\z/', $url) === 1
    && (int) parse_url($url, PHP_URL_PORT) <= 65535;
$frontend = $manifest['frontend_url'] ?? null;
$origin = $manifest['backend_url'] ?? null;
if (!in_array($manifest['suite'] ?? null, ['ChatBrowser', 'GuestChatBrowser', 'ChatLiveDryRun', 'ChatLive', 'ChatContextLiveDryRun', 'ChatContextLive'], true) || !$isOrigin($frontend) || !$isOrigin($origin) || $frontend === $origin) {
    throw new RuntimeException('UC17 browser requires an owned loopback manifest.');
}
$guestBrowser = $manifest['suite'] === 'GuestChatBrowser';
$liveMode = in_array($manifest['suite'], ['ChatLiveDryRun', 'ChatLive'], true);
$contextMode = in_array($manifest['suite'], ['ChatContextLiveDryRun', 'ChatContextLive'], true);
$directory = dirname(realpath(getenv('STAYHUB_TV3_AC_MANIFEST')));
$providerLog = $directory.'/fake-provider.jsonl';
$http = PHP_SAPI === 'cli-server';
$liveNetwork = $http && in_array($manifest['suite'], ['ChatLive', 'ChatContextLive'], true);
if ($http && (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || 'http://'.($_SERVER['HTTP_HOST'] ?? '') !== $origin)) {
    http_response_code(403);
    exit;
}

$app = require $backend.'/bootstrap/app.php';
$app->make(HttpKernel::class)->bootstrap();
$identity = Tv3AcceptanceMySqlEnvironment::assertApplication($app, $manifest);
$config = $app['config'];
$validKey = $liveNetwork
    ? getenv('STAYHUB_UC17_LIVE_AUTHORIZED') === '1' && is_string($config->get('chatbot.api_key'))
        && preg_match('/\A[!-~]{20,200}\z/', $config->get('chatbot.api_key')) === 1
        && $config->get('chatbot.api_key') !== 'fake-uc17-browser-key'
    : $config->get('chatbot.api_key') === 'fake-uc17-browser-key';
if (!$validKey
    || $config->get('chatbot.enabled') || $config->get('chatbot.allow_external_questions')
    || ($http && $app->runningInConsole())) {
    throw new RuntimeException('UC17 browser refuses external provider configuration or console-mode HTTP.');
}

$config->set([
    'app.url' => $origin,
    'session.driver' => 'database',
    'session.connection' => 'mysql',
    'session.table' => 'sessions',
    'session.cookie' => 'uc17_'.$manifest['run_id'].'_session',
    'session.domain' => null,
    'session.path' => '/',
    'session.secure' => false,
    'session.http_only' => true,
    'session.same_site' => 'lax',
    'cache.limiter' => 'database',
    'cache.stores.database.connection' => 'mysql',
    'cache.stores.database.lock_connection' => 'mysql',
    'cors.allowed_origins' => [$frontend],
    'cors.allowed_origins_patterns' => [],
    'cors.supports_credentials' => true,
    'sanctum.stateful' => [substr($frontend, strlen('http://'))],
]);

final class Uc17LiveGuard
{
    public const SAMPLE = 'Nêu 3 điều nên kiểm tra trước khi đặt phòng, bằng tiếng Việt và tối đa 60 từ.';
    public const GUEST_TOPIC = 'Gợi ý điều cần kiểm tra trước khi đặt phòng, bằng tiếng Việt.';
    public const URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent';
    private const INSTRUCTION = 'Bạn là trợ lý tư vấn lưu trú của StayHub. Trả lời ngắn gọn bằng tiếng Việt. '
        .'Chỉ tư vấn chung. Với giá, phòng trống hoặc dữ liệu StayHub hiện hành, hướng người dùng đến chức năng tra cứu. '
        .'Không khẳng định đã đặt phòng, sửa hoặc hủy đặt phòng hay thực hiện thanh toán. '
        .'Không yêu cầu mật khẩu, mã xác thực, thông tin thanh toán hoặc dữ liệu cá nhân.';

    private ?int $startedAt = null;

    public function __construct(
        private readonly string $directory,
        private readonly string $flow,
        private readonly ?array $approvedPayload = null,
    ) {}

    public static function payload(): array
    {
        return [
            'systemInstruction' => ['parts' => [['text' => self::INSTRUCTION]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => self::SAMPLE]]]],
            'generationConfig' => ['candidateCount' => 1, 'maxOutputTokens' => 500],
        ];
    }

    public function authorize(RequestInterface $request): RequestInterface
    {
        if (!in_array($this->flow, ['guest', 'customer', 'name', 'code', 'room', 'invalid-intent'], true) || $request->getMethod() !== 'POST'
            || (string) $request->getUri() !== self::URL
            || json_decode((string) $request->getBody(), true) !== ($this->approvedPayload ?? self::payload())) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        // Exclusive creation reserves one attempt per flow across independent HTTP requests.
        $marker = @fopen($this->directory.'/attempt-'.$this->flow, 'x');
        if ($marker === false) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }
        fclose($marker);
        $this->startedAt = hrtime(true);

        return $request;
    }

    public function record(ResponseInterface $response): ResponseInterface
    {
        if ($this->startedAt === null) {
            throw new RuntimeException('UC17 received a provider response outside its budget guard.');
        }
        $body = json_decode((string) $response->getBody(), true);
        $finish = $body['candidates'][0]['finishReason'] ?? null;
        $finish = is_string($finish) && preg_match('/\A[A-Z_]{1,40}\z/', $finish) === 1 ? $finish : null;
        $metadata = ['status' => $response->getStatusCode(), 'finish_reason' => $finish,
            'elapsed_ms' => (int) ((hrtime(true) - $this->startedAt) / 1_000_000)];
        if (file_put_contents($this->directory.'/response-'.$this->flow.'.json', json_encode($metadata, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('UC17 could not record provider metadata.');
        }

        return $response;
    }
}

/** @return array<string, array{question: string, prompt: string, intent: array}> */
function uc61Cases(bool $dryRun): array
{
    $dates = ['check_in' => 'DATE_A', 'check_out' => 'DATE_B', 'guests' => 'GUESTS'];
    $property = ['intent' => 'check_availability', 'property' => 'PROPERTY', ...$dates];
    $weekend = [...$property, 'check_in' => 'WEEKEND', 'check_out' => 'WEEKEND'];
    $cases = [
        'name' => [
            'question' => 'Cơ sở Mây QA còn phòng cho hai người cuối tuần này không?',
            'prompt' => '[PROPERTY] con phong cho [GUESTS] nguoi [WEEKEND] khong?',
            'intent' => $weekend,
        ],
        'code' => [
            'question' => 'Còn phòng cho 2 người tại cơ sở #171 từ 01/12/2026 đến 03/12/2026 không?',
            'prompt' => 'con phong cho [GUESTS] nguoi tai [PROPERTY] tu [DATE_A] den [DATE_B] khong?',
            'intent' => $property,
        ],
        'room' => [
            'question' => 'Phòng #181 còn trống cho 2 người từ 28/11/2026 đến 30/11/2026 không?',
            'prompt' => 'phong [ROOM] con trong cho [GUESTS] nguoi tu [DATE_A] den [DATE_B] khong?',
            'intent' => ['intent' => 'check_availability', 'room' => 'ROOM', ...$dates],
        ],
    ];
    if ($dryRun) {
        $cases['invalid-intent'] = [
            'question' => 'Cơ sở Mây QA có phòng cho hai người cuối tuần này không?',
            'prompt' => '[PROPERTY] co phong cho [GUESTS] nguoi [WEEKEND] khong?',
            'intent' => [...$weekend, 'property' => 'OTHER_PROPERTY'],
        ];
    }

    return $cases;
}

function uc61Payload(string $prompt, bool $byProperty): array
{
    $place = $byProperty ? 'property' : 'room';
    $location = $byProperty
        ? 'cơ sở [PROPERTY], số khách và kỳ lưu trú. Với [WEEKEND], dùng WEEKEND cho cả ngày nhận và ngày trả. '
        : 'mã phòng, ngày nhận, ngày trả và số khách. ';
    $instruction = 'Phân loại câu hỏi tra phòng trống. Chỉ trả bộ tham chiếu có sẵn trong câu cho '
        .$location.'Ghi tên tham chiếu không kèm dấu ngoặc vuông. '
        .'Nếu câu không phải tra phòng trống hoặc thiếu tham chiếu, '
        .'chọn unsupported và dùng null cho tham chiếu chưa xác định.';
    $reference = ['type' => 'STRING', 'nullable' => true];

    return [
        'systemInstruction' => ['parts' => [['text' => $instruction]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'candidateCount' => 1,
            'maxOutputTokens' => 500,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'intent' => ['type' => 'STRING', 'enum' => ['check_availability', 'unsupported']],
                    $place => $reference,
                    'check_in' => $reference,
                    'check_out' => $reference,
                    'guests' => $reference,
                ],
                'required' => ['intent', $place, 'check_in', 'check_out', 'guests'],
            ],
        ],
    ];
}

final class Uc61QaCalendar
{
    public function handle(Request $request, Closure $next): Response
    {
        $sunday = config('chatbot.api_key') === 'fake-uc17-browser-key'
            && $request->json('message') === 'Cơ sở Mây QA còn phòng cho 2 người cuối tuần này không?';
        Date::setTestNow(Date::parse($sunday ? '2026-11-29T00:00:00Z' : '2026-11-27T12:00:00Z'));
        try {
            return $next($request);
        } finally {
            Date::setTestNow();
        }
    }
}

if ($http && $contextMode) {
    $incoming = Request::capture();
    $question = $incoming->json('message');
    $cases = uc61Cases(!$liveNetwork);
    $flow = '';
    if ($incoming->isMethod('POST') && $incoming->getPathInfo() === '/api/customer/chat/messages') {
        foreach ($cases as $case => $fixture) {
            if ($question === $fixture['question']) {
                $flow = $case;
                break;
            }
        }
    }
    $selected = $cases[$flow] ?? null;
    $payload = $selected === null ? null : uc61Payload($selected['prompt'], $flow !== 'room');
    $guard = new Uc17LiveGuard($directory, $flow, $payload);
    Http::globalRequestMiddleware(static function (RequestInterface $request) use ($guard, $directory): RequestInterface {
        try {
            return $guard->authorize($request);
        } catch (ChatProviderException $error) {
            file_put_contents($directory.'/outbound-blocked.txt', "blocked\n", FILE_APPEND | LOCK_EX);
            throw $error;
        }
    });
    Http::globalResponseMiddleware($guard->record(...));
    if (!$liveNetwork) {
        Http::preventStrayRequests();
        if ($selected !== null) {
            $answer = json_encode($selected['intent'], JSON_THROW_ON_ERROR);
            Http::fake([Uc17LiveGuard::URL => Http::response(['candidates' => [[
                'finishReason' => 'STOP', 'content' => ['parts' => [['text' => $answer]]],
            ]]])]);
        }
    }

    $config->set(['chatbot.enabled' => true, 'chatbot.allow_external_questions' => true,
        'chatbot.guest.enabled' => false, 'chatbot.guest.allow_provider' => false]);
    $app['router']->getRoutes()->getByName('customer.chat.messages.store')->middleware(Uc61QaCalendar::class);
    $app->handleRequest($incoming);
    exit;
}

if ($http && $liveMode) {
    $incoming = Request::capture();
    $flow = match ($incoming->getPathInfo()) {
        '/api/public/chat/messages' => 'guest',
        '/api/customer/chat/messages' => 'customer',
        default => '',
    };
    $guard = new Uc17LiveGuard($directory, $incoming->isMethod('POST') ? $flow : '');
    Http::globalRequestMiddleware($guard->authorize(...));
    Http::globalResponseMiddleware($guard->record(...));
    if (!$liveNetwork) {
        Http::preventStrayRequests();
        $answer = "1. Kiểm tra tổng giá phòng, thuế, phụ phí và các dịch vụ đã bao gồm để biết số tiền cần chuẩn bị.\n"
            ."2. Đọc chính sách đổi ngày, hủy phòng và hoàn tiền, đặc biệt nếu lịch trình có thể thay đổi.\n"
            .'3. Xác nhận giờ nhận và trả phòng, vị trí chỗ nghỉ và các tiện nghi quan trọng với chuyến đi của bạn trước khi đặt.';
        Http::fake([Uc17LiveGuard::URL => Http::response(['candidates' => [[
            'finishReason' => 'STOP', 'content' => ['parts' => [['text' => $answer]]],
        ]]])]);
    }
    $app->instance(ChatProvider::class, new class($app->make(GeminiChatProvider::class), $incoming, $flow) implements ChatProvider {
        public function __construct(private readonly GeminiChatProvider $adapter, private readonly Request $request, private readonly string $flow) {}

        public function answer(#[\SensitiveParameter] string $approvedQuestion): string
        {
            $expected = match ($this->flow) {
                'guest' => Uc17LiveGuard::GUEST_TOPIC,
                'customer' => Uc17LiveGuard::SAMPLE,
                default => null,
            };
            if ($approvedQuestion !== $expected || $this->request->json('message') !== Uc17LiveGuard::SAMPLE) {
                throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
            }

            // The guest workflow uses a normalized topic. Only this QA bootstrap maps it back to the approved synthetic sample.
            return $this->adapter->answer(Uc17LiveGuard::SAMPLE);
        }
    });
    $config->set(['chatbot.enabled' => true, 'chatbot.allow_external_questions' => true,
        'chatbot.guest.enabled' => true, 'chatbot.guest.allow_provider' => true]);
    $app->handleRequest($incoming);
    exit;
}

if ($http) {
    Http::preventStrayRequests();
    Http::globalRequestMiddleware(static function () use ($directory): never {
        file_put_contents($directory.'/outbound-blocked.txt', "blocked\n", FILE_APPEND | LOCK_EX);
        throw new RuntimeException('UC17 browser refuses outbound HTTP.');
    });
    $fake = new class($providerLog, $directory.'/release-first-answer') implements ChatProvider
    {
        public function __construct(private readonly string $log, private readonly string $release) {}

        public function answer(#[\SensitiveParameter] string $approvedQuestion): string
        {
            $answers = [
                'QA17 hỏi đầu' => 'QA17 trả lời đầu',
                'QA17 hỏi tiếp' => 'QA17 trả lời tiếp',
                'QA17 timeout' => 'QA17 đã phục hồi timeout',
                'QA17 upstream' => 'QA17 đã phục hồi giới hạn',
                'QA17 mất phản hồi' => 'QA17 trả lời đã lưu',
                'QA17 bỏ lượt' => 'QA17 trả lời lượt đã bỏ',
                'QA17 câu mới' => 'QA17 trả lời câu mới',
                'QA17 sau khi bỏ' => 'QA17 trả lời sau khi bỏ',
                'QA17 phản hồi A' => 'QA17 dữ liệu riêng A',
                'QA17 câu của B' => 'QA17 trả lời riêng B',
                'QA17 giới hạn ứng dụng' => 'QA17 trả lời sau giới hạn',
                'QA17 nội dung dài' => str_repeat('Dàikhôngdấucách', 160),
                'Gợi ý hoạt động nhẹ nhàng gần nhà, bằng tiếng Việt.' => 'Bạn có thể đi dạo hoặc ghé một quán cà phê yên tĩnh.',
            ];
            if (!array_key_exists($approvedQuestion, $answers)) {
                throw new RuntimeException('UC17 fake received an unexpected fixture question.');
            }
            $entry = json_encode($approvedQuestion, JSON_THROW_ON_ERROR);
            $calls = file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($calls === false || file_put_contents($this->log, $entry."\n", FILE_APPEND | LOCK_EX) === false) {
                throw new RuntimeException('Cannot record UC17 fake calls.');
            }
            if (!in_array($entry, $calls, true)) {
                if ($approvedQuestion === 'QA17 timeout') {
                    throw new ChatProviderException(ChatProviderException::TIMEOUT);
                }
                if ($approvedQuestion === 'QA17 upstream') {
                    throw new ChatProviderException(ChatProviderException::RATE_LIMITED, 4);
                }
                if ($approvedQuestion === 'QA17 hỏi đầu') {
                    $deadline = hrtime(true) + 10_000_000_000;
                    while (!is_file($this->release)) {
                        if (hrtime(true) >= $deadline) {
                            throw new RuntimeException('UC17 browser did not release the first fake answer.');
                        }
                        usleep(30_000);
                    }
                }
            }

            return $answers[$approvedQuestion];
        }
    };
    $app->instance(ChatProvider::class, $fake);
    if ($app->make(ChatProvider::class) !== $fake) {
        throw new RuntimeException('UC17 browser fake binding failed.');
    }
    $config->set(['chatbot.enabled' => true, 'chatbot.allow_external_questions' => true,
        'chatbot.guest.enabled' => $guestBrowser, 'chatbot.guest.allow_provider' => $guestBrowser]);
    $app->handleRequest(Request::capture());
    exit;
}

$db = $app['db']->connection();
$mode = $argv[1] ?? '';
if ($mode === 'guard-preflight' && $manifest['suite'] === 'ChatContextLiveDryRun') {
    $guardDirectory = $directory.'/guard-preflight';
    if (!mkdir($guardDirectory)) {
        throw new RuntimeException('UC61 guard preflight requires fresh evidence.');
    }
    $cases = uc61Cases(false);
    $payload = uc61Payload($cases['name']['prompt'], true);
    $valid = new OutboundRequest('POST', Uc17LiveGuard::URL, [], json_encode($payload, JSON_THROW_ON_ERROR));
    $badPayload = $payload;
    $badPayload['contents'][0]['parts'][0]['text'] = $cases['name']['question'];
    $rejected = [];
    $reject = static function (string $label, Uc17LiveGuard $guard, RequestInterface $request) use (&$rejected): void {
        try {
            $guard->authorize($request);
            throw new RuntimeException('UC61 guard accepted a disallowed request.');
        } catch (ChatProviderException) {
            $rejected[] = $label;
        }
    };
    $guard = new Uc17LiveGuard($guardDirectory, 'name', $payload);
    $reject('wrong_destination', $guard, $valid->withUri(new \GuzzleHttp\Psr7\Uri('https://example.test/')));
    $reject('wrong_method', $guard, $valid->withMethod('GET'));
    $reject('raw_payload', $guard, new OutboundRequest('POST', Uc17LiveGuard::URL, [], json_encode($badPayload, JSON_THROW_ON_ERROR)));
    $reject('unknown_case', new Uc17LiveGuard($guardDirectory, '', $payload), $valid);
    foreach ($cases as $case => $fixture) {
        $casePayload = uc61Payload($fixture['prompt'], $case !== 'room');
        $request = new OutboundRequest('POST', Uc17LiveGuard::URL, [], json_encode($casePayload, JSON_THROW_ON_ERROR));
        (new Uc17LiveGuard($guardDirectory, $case, $casePayload))->authorize($request);
        $reject('duplicate_'.$case, new Uc17LiveGuard($guardDirectory, $case, $casePayload), $request);
    }
    echo json_encode(['blocked' => $rejected, 'network_attempts' => 0], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'guard-preflight' && $manifest['suite'] === 'ChatLiveDryRun') {
    $guardDirectory = $directory.'/guard-preflight';
    if (!mkdir($guardDirectory)) throw new RuntimeException('UC17 guard preflight requires fresh evidence.');
    $valid = new OutboundRequest('POST', Uc17LiveGuard::URL, [], json_encode(Uc17LiveGuard::payload(), JSON_THROW_ON_ERROR));
    $guestGuard = new Uc17LiveGuard($guardDirectory, 'guest');
    $rejected = [];
    $unexpected = Uc17LiveGuard::payload();
    $unexpected['contents'][0]['parts'][0]['text'] = 'QA: other input';
    foreach ([
        'wrong_destination' => new OutboundRequest('POST', 'https://example.test/', [], (string) $valid->getBody()),
        'wrong_payload' => new OutboundRequest('POST', Uc17LiveGuard::URL, [], json_encode($unexpected, JSON_THROW_ON_ERROR)),
        'wrong_method' => $valid->withMethod('GET'),
    ] as $scenario => $request) {
        try {
            $guestGuard->authorize($request);
            throw new RuntimeException('UC17 guard accepted a disallowed request.');
        } catch (ChatProviderException) {
            $rejected[] = $scenario;
        }
    }
    foreach (['guest', 'customer'] as $flow) {
        (new Uc17LiveGuard($guardDirectory, $flow))->authorize($valid);
        try {
            (new Uc17LiveGuard($guardDirectory, $flow))->authorize($valid);
            throw new RuntimeException('UC17 guard accepted a duplicate request.');
        } catch (ChatProviderException) {
            $rejected[] = 'duplicate_'.$flow;
        }
    }
    echo json_encode(['blocked' => $rejected, 'network_attempts' => 0], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'prepare') {
    $password = getenv('STAYHUB_UC17_BROWSER_PASSWORD');
    if (!is_string($password) || strlen($password) < 32 || $db->getSchemaBuilder()->getTables() !== [] || is_file($providerLog)) {
        throw new RuntimeException('UC17 browser requires an empty owned schema and fresh fixtures.');
    }
    if ($app->make(ConsoleKernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('UC17 browser migration failed.');
    }
    $db->transaction(function () use ($db, $app, $password, $guestBrowser, $contextMode): void {
        $hash = $app['hash']->make($password);
        foreach ([[31, 'customer-a', 'CUSTOMER'], [32, 'customer-b', 'CUSTOMER'], [21, 'host', 'HOST'], [11, 'admin', 'ADMIN']] as [$id, $account, $role]) {
            $db->table('users')->insert([
                'id' => $id, 'role' => $role, 'status' => 'ACTIVE',
                'name' => 'QA17 '.$account, 'email' => $account.'@example.test', 'password' => $hash,
            ]);
        }
        if ($contextMode) {
            foreach ([[171, 'Cơ sở Mây QA'], [173, 'Cơ sở Trùng QA'], [174, 'Cơ sở Trùng QA']] as [$id, $name]) {
                $db->table('properties')->insert(['id' => $id, 'host_id' => 21, 'name' => $name,
                    'status' => 'ACTIVE', 'type' => 'HOTEL', 'address' => 'QA']);
            }
            $db->table('room_types')->insert(['id' => 172, 'property_id' => 171, 'name' => 'Phòng đôi QA']);
            foreach ([[181, 'Phòng Gió QA', 2, 'ACTIVE'], [182, 'Phòng Riêng QA', 2, 'INACTIVE'], [183, 'Phòng Một Khách QA', 1, 'ACTIVE']]
                as [$id, $name, $capacity, $status]) {
                $db->table('rooms')->insert(['id' => $id, 'property_id' => 171, 'room_type_id' => 172,
                    'room_number' => (string) $id, 'name' => $name, 'capacity' => $capacity, 'price_per_night' => '650000.00', 'status' => $status]);
            }
            $db->table('maintenances')->insert(['room_id' => 181, 'start_date' => '2026-12-01', 'end_date' => '2026-12-03',
                'status' => 'SCHEDULED', 'reason' => 'Lịch QA tổng hợp']);
        }
        if ($guestBrowser) {
            $db->table('properties')->insert(['id' => 71, 'host_id' => 21, 'name' => 'Khách sạn Giấc Mơ QA', 'status' => 'ACTIVE', 'type' => 'HOTEL', 'address' => 'QA']);
            $db->table('room_types')->insert(['id' => 72, 'property_id' => 71, 'name' => 'Phòng đôi']);
            foreach ([[81, 'Phòng Bình Minh QA', 'ACTIVE'], [82, 'Phòng Ẩn QA', 'INACTIVE']] as [$id, $name, $status]) {
                $db->table('rooms')->insert(['id' => $id, 'property_id' => 71, 'room_type_id' => 72,
                    'room_number' => (string) $id, 'name' => $name, 'capacity' => 2, 'price_per_night' => '650000.00', 'status' => $status]);
            }
        }
    });
    if (file_put_contents($providerLog, '') === false) {
        throw new RuntimeException('Could not initialize UC17 fake provider evidence.');
    }
} elseif ($mode !== 'audit') {
    throw new RuntimeException('Unknown UC17 browser bootstrap mode.');
}

$messages = $db->table('chat_messages')->orderBy('id')->get(['id', 'chat_session_id', 'sender', 'message']);
if ($liveMode || $contextMode) {
    $messages->each(static function ($row): void {
        $row->body_sha256 = hash('sha256', $row->message);
        unset($row->message);
    });
}
$metadata = [];
if ($liveMode || $contextMode) {
    $flows = $contextMode ? array_keys(uc61Cases($manifest['suite'] === 'ChatContextLiveDryRun')) : ['guest', 'customer'];
    foreach ($flows as $flow) {
        $path = $directory.'/response-'.$flow.'.json';
        $metadata[$flow] = ['attempts' => is_file($directory.'/attempt-'.$flow) ? 1 : 0,
            'provider' => is_file($path) ? json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : null];
    }
}

echo json_encode([
    'identity' => $identity,
    'sessions' => $db->table('chat_sessions')->orderBy('id')->get(['id', 'user_id']),
    'messages' => $messages,
    'turns' => $db->table('chat_turns')->orderBy('id')->get(['request_id', 'user_id', 'status', 'attempts', 'chat_session_id', 'user_message_id', 'assistant_message_id']),
    'fake_calls' => array_map(static fn (string $entry): string => json_decode($entry, true, flags: JSON_THROW_ON_ERROR), file($providerLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
    'outbound_attempts' => is_file($directory.'/outbound-blocked.txt') ? count(file($directory.'/outbound-blocked.txt')) : 0,
    ...($liveMode || $contextMode ? ['gemini' => $metadata] : []),
], JSON_THROW_ON_ERROR);
