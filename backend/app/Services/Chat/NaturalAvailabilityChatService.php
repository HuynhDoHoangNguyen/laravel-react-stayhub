<?php

namespace App\Services\Chat;

use App\DTOs\Booking\Requests\CheckAvailabilityQuery;
use App\Exceptions\Chat\ChatProviderException;
use App\Models\Property;
use App\Models\Room;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Booking\SearchService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class NaturalAvailabilityChatService
{
    public const LOCAL_GUIDE = 'Để tra phòng trực tiếp, dùng mẫu “Phòng trống: Còn phòng #81 từ DD/MM/YYYY đến DD/MM/YYYY cho 2 người không?”.';

    private const GUIDE = 'Bạn hãy gửi mã của một phòng, ngày nhận, ngày trả và số khách trong cùng một câu, theo mẫu “Phòng #81 còn trống cho 2 người từ DD/MM/YYYY đến DD/MM/YYYY không?”.';

    private const PROPERTY_GUIDE = 'Bạn hãy gửi đúng tên đầy đủ hoặc mã công khai của cơ sở, số khách và kỳ lưu trú. Ví dụ: “Còn phòng cho hai người cuối tuần này tại cơ sở #71 không?”.';

    private const PROPERTY_VOCABULARY = ['tai', '[PROPERTY]', '[WEEKEND]'];

    private const GUEST_NUMBERS = [
        'mot' => '1', 'hai' => '2', 'ba' => '3', 'bon' => '4', 'nam' => '5',
        'sau' => '6', 'bay' => '7', 'tam' => '8', 'chin' => '9', 'muoi' => '10',
    ];

    private const VOCABULARY = [
        'ban', 'bao', 'cho', 'co', 'con', 'den', 'du', 'dung', 'duoc', 'giup', 'hay', 'kiem', 'kha', 'khach', 'khong',
        'ma', 'minh', 'ngay', 'nguoi', 'nhe', 'nhan', 'o', 'phong', 'tu', 'ra', 'sang', 'thay', 'thi', 'toi',
        'tra', 'trong', 'vao', 'voi', 'xin', '[ROOM]', '[DATE_A]', '[DATE_B]', '[GUESTS]',
    ];

    private const DATE_PATTERN = '(?<![0-9/-])(?:[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}/[0-9]{2}/[0-9]{4})(?![0-9/-])';

    private const FACTS_PATTERN = '~(?<room>#[0-9]+)'
        .'|(?<date>'.self::DATE_PATTERN.')'
        .'|(?<guests>\b(?:[0-9]+|mot|hai|ba|bon|nam|sau|bay|tam|chin|muoi))(?= (?:nguoi|khach)\b)'
        .'|(?<weekend>\bcuoi tuan nay\b)~';

    private const EXPECTED_INTENT = [
        'check_in' => 'DATE_A',
        'check_out' => 'DATE_B',
        'guests' => 'GUESTS',
        'intent' => 'check_availability',
        'room' => 'ROOM',
    ];

    public function __construct(
        private readonly GeminiChatProvider $provider,
        private readonly PublicRoomAvailabilityChatService $availability,
        private readonly CatalogRepository $catalog,
        private readonly SearchService $search,
    ) {}

    /** @return array{prompt: string, location: int|Property, query: CheckAvailabilityQuery, weekend: bool}|array{clarification: string}|null */
    public function prepare(#[\SensitiveParameter] string $question): ?array
    {
        $text = $this->normalize($question);
        $byProperty = ! preg_match('/\bphong\s*#/', $text);
        $asksAvailability = preg_match('/\b(?:con phong|phong trong|con trong|con cho|kha dung)\b|\bphong\s*#/', $text)
            || (preg_match('/\bco phong\b/', $text)
                && preg_match('~\b(?:tai|cuoi tuan nay)\b|'.self::DATE_PATTERN.'~', $text));
        if (! preg_match('/\bphong\b/', $text) || ! $asksAvailability) {
            return null;
        }

        $guide = $byProperty ? self::PROPERTY_GUIDE : self::GUIDE;
        $allowedCharacters = $byProperty ? "~[^\p{L}\p{M}0-9#\/ .?!,\t&'’()+-]~u" : '/[^\p{L}\p{M}0-9#\/ .?!,\t-]/u';
        if (preg_match($allowedCharacters, $question) !== 0) {
            throw ValidationException::withMessages(['message' => $guide]);
        }

        $reference = null;
        if ($byProperty) {
            [$reference, $text] = $this->maskProperty($question);
        }

        $rooms = $dates = $guests = [];
        $weekends = 0;
        $masked = preg_replace_callback(self::FACTS_PATTERN, static function (array $match) use (&$rooms, &$dates, &$guests, &$weekends): string {
            if (($match['room'] ?? '') !== '') {
                $rooms[] = substr($match['room'], 1);

                return '[ROOM]';
            }
            if (($match['date'] ?? '') !== '') {
                $dates[] = $match['date'];

                return count($dates) === 1 ? '[DATE_A]' : '[DATE_B]';
            }
            if (($match['weekend'] ?? '') !== '') {
                $weekends++;

                return '[WEEKEND]';
            }

            $guests[] = $match['guests'];

            return '[GUESTS]';
        }, $text);

        $words = preg_split('/[ ,.?!]+/', $masked, flags: PREG_SPLIT_NO_EMPTY);
        $vocabulary = $byProperty ? [...self::VOCABULARY, ...self::PROPERTY_VOCABULARY] : self::VOCABULARY;
        $validDates = ($weekends === 0 && count($dates) === 2)
            || ($byProperty && $weekends === 1 && $dates === []);
        $roomId = count($rooms) === 1 && $rooms[0][0] !== '0' ? $this->availability->roomId($rooms[0]) : null;
        $validLocation = $byProperty ? $reference !== null && $rooms === [] : $roomId !== null && str_contains($masked, 'phong [ROOM]');
        if (! $validDates || ! $validLocation || count($guests) !== 1 || array_diff($words, $vocabulary) !== []
            || (! $byProperty && isset(self::GUEST_NUMBERS[$guests[0]]))) {
            throw ValidationException::withMessages(['message' => $guide]);
        }

        if ($weekends === 1) {
            $today = Date::now('UTC')->toImmutable();
            if ($today->dayOfWeek === CarbonInterface::SUNDAY) {
                throw ValidationException::withMessages([
                    'message' => 'Thứ Bảy của cuối tuần này đã qua theo UTC. Bạn hãy ghi rõ ngày nhận và ngày trả phòng.',
                ]);
            }
            $saturday = $today->startOfWeek(CarbonInterface::MONDAY)->addDays(5);
            $dates = [$saturday->toDateString(), $saturday->addDays(2)->toDateString()];
        }

        try {
            $query = $this->availability->validateDatesAndGuests($dates[0], $dates[1], self::GUEST_NUMBERS[$guests[0]] ?? $guests[0]);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['message' => $guide]);
        }

        $location = $roomId;
        if ($byProperty) {
            $properties = $this->catalog->findPublicPropertyCandidates($reference);
            if ($properties->isEmpty()) {
                throw ValidationException::withMessages([
                    'message' => 'Chưa tìm thấy cơ sở công khai phù hợp. Bạn hãy kiểm tra tên đầy đủ hoặc dùng mã công khai tại cơ sở #<mã>.',
                ]);
            }
            if ($properties->count() > 1) {
                $options = $properties->take(3)->map(fn (Property $property): string => $this->propertyLabel($property))->implode("\n");

                return ['clarification' => "Có nhiều cơ sở công khai trùng tên. Một số mã để chọn:\n".$options
                    ."\nBạn gửi lại kỳ lưu trú và số khách kèm mã đã chọn, ví dụ tại cơ sở #".$properties->first()->id.'.'];
            }
            $location = $properties->sole();
        }

        // The external prompt is assembled solely from allowlisted words and placeholders.
        return ['prompt' => implode(' ', $words).'?', 'location' => $location, 'query' => $query, 'weekend' => $weekends === 1];
    }

    /** @return array{int|string|null, string} */
    private function maskProperty(#[\SensitiveParameter] string $question): array
    {
        $question = trim($question);
        if (preg_match('/\btại\h+(?<code>cơ\h+sở\h+#([1-9][0-9]*))\b/iu', $question, $match, PREG_OFFSET_CAPTURE)) {
            [$code, $offset] = $match['code'];

            return [$this->availability->roomId($match[2][0]), $this->maskName($question, $code, $offset)];
        }

        $afterAt = '/\btại\h+(?<name>.+?)(?=\h+(?:không[?!. ]*\z|cuối\h+tuần\h+này\b|từ\h+(?:ngày\h+|[0-9]))|[?!, ]*\z)/iu';
        $leading = '/\A(?<name>.+?)\h+(?=(?:còn|có)\h+phòng\b)/iu';
        if (! preg_match($leading, $question, $match, PREG_OFFSET_CAPTURE)
            && ! preg_match($afterAt, $question, $match, PREG_OFFSET_CAPTURE)) {
            return [null, ''];
        }

        [$name, $offset] = $match['name'];
        if (mb_strlen($name) > 255 || ! preg_match("~\A[\p{L}\p{M}0-9][\p{L}\p{M}0-9 .,&'’()+-]*\z~u", $name)
            || preg_match('/(?:[a-z0-9-]+\.)+[a-z]{2,63}\b|\A[0-9]{4,8}\z|(?:[0-9][ .()+-]*){7,}/iu', $name)
            || preg_match('/\b(?:mat khau|otp|token|cccd|so the|email|so dien thoai|ma bao mat)\b/', $this->normalize($name))) {
            return [null, ''];
        }

        return [$name, $this->maskName($question, $name, $offset)];
    }

    private function maskName(#[\SensitiveParameter] string $question, #[\SensitiveParameter] string $name, int $offset): string
    {
        $before = $this->normalize(substr($question, 0, $offset));
        $after = $this->normalize(substr($question, $offset + strlen($name)));

        return trim($before.' [PROPERTY] '.$after);
    }

    private function normalize(#[\SensitiveParameter] string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text, 'vi'))));
    }

    public function reply(#[\SensitiveParameter] string $prompt, int|Property $location, CheckAvailabilityQuery $query, bool $weekend): string
    {
        $byProperty = $location instanceof Property;
        $expected = self::EXPECTED_INTENT;
        if ($byProperty) {
            unset($expected['room']);
            $expected['property'] = 'PROPERTY';
            if ($weekend) {
                $expected['check_in'] = $expected['check_out'] = 'WEEKEND';
            }
        }

        $intent = $this->provider->availabilityIntent($prompt, $byProperty);
        ksort($intent);
        if ($intent !== $expected) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        if (! $byProperty) {
            return $this->availability->savedReply($location, $query);
        }

        $rooms = $this->search->publicAvailableRooms($location->id, $query);
        $period = sprintf(
            'từ %s đến %s cho %d người',
            Date::parse($query->checkInDate, 'UTC')->format('d/m/Y'),
            Date::parse($query->checkOutDate, 'UTC')->format('d/m/Y'),
            $query->guestCount,
        );
        if ($rooms->isEmpty()) {
            return 'Chưa tìm thấy phòng công khai khả dụng '.$period.'.';
        }

        $options = $rooms->map(static fn (Room $room): string => sprintf(
            '• %s (#%d)', trim(preg_replace('/\s+/u', ' ', $room->name)), $room->id,
        ))->implode("\n");

        return sprintf("%s có phòng khả dụng tại thời điểm kiểm tra %s:\n%s", $this->propertyLabel($location), $period, $options);
    }

    private function propertyLabel(Property $property): string
    {
        return sprintf('%s (#%d)', trim(preg_replace('/\s+/u', ' ', $property->name)), $property->id);
    }
}
