<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Chat\ChatProvider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Support\Tv3AcceptanceMySqlEnvironment;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';

$manifest = Tv3AcceptanceMySqlEnvironment::beforeBoot($backend);
if (($manifest['suite'] ?? null) !== 'ChatRecovery' || PHP_SAPI !== 'cli') {
    throw new RuntimeException('Chat recovery worker requires its own suite and a CLI process.');
}

$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
$now = CarbonImmutable::createFromTimestampUTC($input['time']);
$mode = $input['mode'];
if (! in_array($mode, ['hold', 'normal', 'drop', 'budget'], true)) {
    throw new RuntimeException('Unexpected recovery worker mode.');
}

$app = require $backend.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
Tv3AcceptanceMySqlEnvironment::assertApplication($app, $manifest);

config()->set([
    'sanctum.stateful' => ['localhost:5173'],
    'chatbot.enabled' => true,
    'chatbot.allow_external_questions' => true,
    'chatbot.rate_limit_per_minute' => 10,
    'chatbot.max_concurrent' => 2,
    'chatbot.timeout_ms' => $mode === 'budget' ? 29000 : 12000,
    'cache.stores.database.connection' => 'mysql',
    'cache.stores.database.lock_connection' => 'mysql',
    'cache.stores.database.lock_table' => 'cache_locks',
    'cache.stores.database.table' => 'cache',
]);
Date::setTestNow($now);

Http::preventStrayRequests();
Http::fake([]);
$emit = static function (array $event): void {
    fwrite(STDOUT, json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
    fflush(STDOUT);
};
$app->instance(ChatProvider::class, new class($mode, $now, $emit) implements ChatProvider {
    public function __construct(
        private readonly string $mode,
        private readonly CarbonImmutable $now,
        private readonly Closure $emit,
    ) {}

    public function answer(string $approvedQuestion): string
    {
        ($this->emit)(['event' => 'provider']);
        if ($this->mode === 'hold') {
            $signal = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
            Date::setTestNow($this->now->addSeconds($signal['advance']));

            return 'Phản hồi được giữ trong worker A.';
        }

        return 'Phản hồi tổng hợp đã hoàn tất.';
    }
});

$request = Request::create('/api/customer/chat/messages', 'POST', server: [
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_ORIGIN' => 'http://localhost:5173',
    'CONTENT_TYPE' => 'application/json',
], content: json_encode($input['payload'], JSON_THROW_ON_ERROR));
$app->instance('request', $request);
Auth::guard('web')->setUser(User::query()->findOrFail($input['actor_id']));
Auth::shouldUse('web');
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
Http::assertNothingSent();

if ($mode === 'drop') {
    $emit(['event' => 'dropped', 'status' => $response->getStatusCode()]);
} else {
    $emit(['event' => 'response', 'status' => $response->getStatusCode(),
        'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)]);
}
