<?php

namespace Tests\Unit;

use App\Exceptions\Chat\ChatProviderException;
use App\Services\Chat\GeminiChatProvider;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GeminiChatProviderTest extends TestCase
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent';
    private const KEY = 'fake-gemini-key';
    private const QUESTION = 'Tôi nên kiểm tra gì trước chuyến đi?';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('chatbot', [
            'enabled' => true,
            'api_key' => self::KEY,
            'model' => 'gemini-3.5-flash-lite',
            'timeout_ms' => 12000,
            'max_output_tokens' => 500,
            'rate_limit_per_minute' => 10,
            'max_concurrent' => 2,
        ]);
    }

    public function test_it_sends_one_request_and_extracts_only_visible_text(): void
    {
        $options = [];
        Http::globalMiddleware(static function (callable $handler) use (&$options): callable {
            return static function ($request, array $requestOptions) use ($handler, &$options) {
                $options = $requestOptions;

                return $handler($request, $requestOptions);
            };
        });
        Http::fake([self::URL => Http::response(self::candidate([
            ['thought' => true, 'text' => 'Internal thought'],
            ['text' => '  Kiểm tra giờ nhận phòng'],
            ['text' => ' và chính sách hủy.  '],
        ]))]);

        $this->assertSame(
            'Kiểm tra giờ nhận phòng và chính sách hủy.',
            $this->provider()->answer(' '.self::QUESTION.' '),
        );
        Http::assertSent(static function (Request $request): bool {
            $payload = $request->data();
            self::assertSame(self::URL, $request->url());
            self::assertSame('POST', $request->method());
            self::assertTrue($request->hasHeader('x-goog-api-key', self::KEY));
            self::assertSame(['systemInstruction', 'contents', 'generationConfig'], array_keys($payload));
            self::assertSame(['parts'], array_keys($payload['systemInstruction']));
            self::assertCount(1, $payload['systemInstruction']['parts']);
            self::assertSame(['text'], array_keys($payload['systemInstruction']['parts'][0]));
            self::assertStringContainsString('Chỉ tư vấn chung', $payload['systemInstruction']['parts'][0]['text']);
            self::assertSame([['role' => 'user', 'parts' => [['text' => self::QUESTION]]]], $payload['contents']);
            self::assertSame(['candidateCount' => 1, 'maxOutputTokens' => 500], $payload['generationConfig']);
            self::assertStringNotContainsString(self::KEY, $request->body());

            return true;
        });
        Http::assertSentCount(1);
        $this->assertEquals(12, $options['timeout'] ?? null);
        $this->assertEquals(3, $options['connect_timeout'] ?? null);
        $this->assertFalse($options['allow_redirects'] ?? true);
    }

    #[DataProvider('disabledOrInvalidConfiguration')]
    public function test_it_does_not_send_when_disabled_or_invalid(string $name, mixed $value): void
    {
        config()->set('chatbot.'.$name, $value);
        Http::fake([self::URL => Http::response(self::candidate([['text' => 'Unused response']]))]);

        $this->assertFailure(ChatProviderException::UNAVAILABLE);
        Http::assertNothingSent();
    }

    public static function disabledOrInvalidConfiguration(): array
    {
        return [
            'disabled' => ['enabled', false],
            'missing key' => ['api_key', null],
            'header injection' => ['api_key', "value\r\nInjected: value"],
            'missing model' => ['model', ''],
            'malformed model' => ['model', '../../other'],
            'invalid timeout' => ['timeout_ms', 0],
            'invalid output limit' => ['max_output_tokens', false],
        ];
    }

    public function test_a_blank_question_never_reaches_the_transport(): void
    {
        try {
            $this->provider()->answer('   ');
            $this->fail('Expected the adapter to reject an empty question.');
        } catch (InvalidArgumentException) {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('invalidResponses')]
    public function test_it_rejects_unusable_responses(mixed $payload): void
    {
        Http::fake([self::URL => Http::response($payload)]);

        $this->assertFailure(ChatProviderException::UNAVAILABLE);
        Http::assertSentCount(1);
    }

    public static function invalidResponses(): array
    {
        return [
            'malformed JSON' => ['{invalid upstream body'],
            'scalar JSON' => ['"unexpected"'],
            'missing candidate' => [['candidates' => []]],
            'non-list candidates' => [['candidates' => ['unexpected' => true]]],
            'prompt blocked' => [['promptFeedback' => ['blockReason' => 'SAFETY']]],
            'response filtered' => [self::candidate([['text' => 'Do not use']], 'SAFETY')],
            'truncated output' => [self::candidate([['text' => 'Do not use']], 'MAX_TOKENS')],
            'blank text' => [self::candidate([['text' => '   ']])],
            'only thought content' => [self::candidate([['thought' => true, 'text' => 'Do not use']])],
            'part without text' => [self::candidate([['functionCall' => ['name' => 'unexpected']]])],
        ];
    }

    #[DataProvider('rateLimitResponses')]
    public function test_rate_limit_returns_only_safe_retry_metadata(array $headers, ?int $expected): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-22T03:00:00Z'));
        try {
            Http::fake([self::URL => Http::response(['secret' => 'upstream-private-fixture'], 429, $headers)]);

            $this->assertFailure(ChatProviderException::RATE_LIMITED, $expected);
            Http::assertSentCount(1);
        } finally {
            $this->travelBack();
        }
    }

    public static function rateLimitResponses(): array
    {
        return [
            'seconds' => [['Retry-After' => '030'], 30],
            'zero' => [['Retry-After' => '0'], 0],
            'HTTP date' => [['Retry-After' => 'Sun, 22 Nov 2026 03:01:30 GMT'], 90],
            'absent' => [[], null],
            'invalid' => [['Retry-After' => '-3'], null],
            'overflow' => [['Retry-After' => '999999999999999999999999999999'], null],
        ];
    }

    #[DataProvider('otherUpstreamStatuses')]
    public function test_it_does_not_retry_or_follow_upstream_errors(int $status): void
    {
        Http::fake([self::URL => Http::response('upstream-private-fixture', $status, [
            'Location' => 'https://example.invalid/',
        ])]);

        $this->assertFailure(ChatProviderException::UNAVAILABLE);
        Http::assertSentCount(1);
    }

    public static function otherUpstreamStatuses(): array
    {
        return ['redirect' => [302], 'invalid key' => [403], 'upstream unavailable' => [503]];
    }

    #[DataProvider('transportFailures')]
    public function test_transport_failures_are_classified_without_retries(int $errno, string $errorCode): void
    {
        $attempts = 0;
        Http::fake([self::URL => static function (Request $request) use ($errno, &$attempts) {
            $attempts++;

            return Create::rejectionFor(new ConnectException(
                'upstream-private-fixture '.self::KEY.' '.self::QUESTION,
                $request->toPsrRequest(),
                null,
                ['errno' => $errno],
            ));
        }]);

        $this->assertFailure($errorCode);
        $this->assertSame(1, $attempts);
    }

    public static function transportFailures(): array
    {
        return [
            'cURL timeout' => [28, ChatProviderException::TIMEOUT],
            'cannot resolve host' => [6, ChatProviderException::UNAVAILABLE],
        ];
    }

    private static function candidate(array $parts, string $finishReason = 'STOP'): array
    {
        return ['candidates' => [['finishReason' => $finishReason, 'content' => ['parts' => $parts]]]];
    }

    private function provider(): GeminiChatProvider
    {
        return $this->app->make(GeminiChatProvider::class);
    }

    private function assertFailure(string $errorCode, ?int $retryAfterSeconds = null): void
    {
        try {
            $this->provider()->answer(self::QUESTION);
            $this->fail('Expected a chat provider exception.');
        } catch (ChatProviderException $error) {
            $this->assertSame($errorCode, $error->errorCode);
            $this->assertSame($retryAfterSeconds, $error->retryAfterSeconds);
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString(self::KEY, $error->getMessage());
            $this->assertStringNotContainsString(self::QUESTION, $error->getMessage());
            $this->assertStringNotContainsString('upstream-private-fixture', $error->getMessage());
        }
    }
}
