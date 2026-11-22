<?php

namespace App\Services\Chat;

use App\DTOs\Booking\Requests\CheckAvailabilityQuery;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Booking\AvailabilityService;
use App\Validators\Booking\RoomSearchValidator;
use Illuminate\Validation\ValidationException;

final class PublicRoomAvailabilityChatService
{
    private const PREFIX = '\A(?:(?:con|kiem tra)\s+)?phong\s+#';

    private const CODE = '(?<room>[1-9][0-9]*)';

    private const DATE = '(?:[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}/[0-9]{2}/[0-9]{4})';

    private const GUIDE = 'Bạn cho mình mã phòng, ngày nhận, ngày trả và số khách hợp lệ nhé. Mẫu: Còn phòng #81 từ DD/MM/YYYY đến DD/MM/YYYY cho 2 người không?';

    private const SAVED_PREFIX = '\A\h*Phòng\h+trống\h*:\h*';

    private const SAVED_GUIDE = 'Dùng mẫu “Phòng trống: Còn phòng #81 từ DD/MM/YYYY đến DD/MM/YYYY cho 2 người không?” với mã phòng, ngày nhận, ngày trả và số khách hợp lệ.';

    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly RoomSearchValidator $validator,
        private readonly AvailabilityService $availability,
    ) {}

    public function matches(#[\SensitiveParameter] string $text): bool
    {
        return preg_match('/'.self::PREFIX.'/', $text) === 1;
    }

    public function privacyText(#[\SensitiveParameter] string $text): string
    {
        if (! preg_match('/'.self::PREFIX.self::CODE.'(?=\s|[?!.]*\z)/', $text, $matches, PREG_OFFSET_CAPTURE)
            || $this->roomId($matches['room'][0]) === null) {
            return $text;
        }

        [$code, $offset] = $matches['room'];

        return substr_replace($text, '[ma-phong]', $offset, strlen($code));
    }

    public function isSavedCommand(#[\SensitiveParameter] string $question): bool
    {
        return preg_match('~'.self::SAVED_PREFIX.'~iu', $question) === 1;
    }

    /** @return array{roomId: int, query: CheckAvailabilityQuery} */
    public function validateSavedCommand(#[\SensitiveParameter] string $question): array
    {
        $pattern = '~'.self::SAVED_PREFIX.'Còn\h+phòng\h+#'.self::CODE
            .'\h+từ\h+(?<checkIn>'.self::DATE.')\h+đến\h+(?<checkOut>'.self::DATE.')'
            .'\h+cho\h+(?<guests>[0-9]+)\h+(?:người|khách)\h+không\h*\??\h*\z~iu';

        return $this->prepare($question, $pattern)
            ?? throw ValidationException::withMessages(['message' => self::SAVED_GUIDE]);
    }

    public function savedReply(int $roomId, CheckAvailabilityQuery $query): string
    {
        return $this->publicReply($roomId, $query)['message'];
    }

    public function validateDatesAndGuests(string $checkIn, string $checkOut, string $guests): CheckAvailabilityQuery
    {
        return $this->validator->validateCheckAvailability([
            'check_in_date' => $this->isoDate($checkIn),
            'check_out_date' => $this->isoDate($checkOut),
            'guest_count' => $guests,
        ]);
    }

    /** @return array{message: string, action: ?string, rooms: array} */
    public function reply(#[\SensitiveParameter] string $text): array
    {
        $pattern = '~'.self::PREFIX.self::CODE.'\s+tu\s+(?<checkIn>'.self::DATE.')'
            .'\s+den\s+(?<checkOut>'.self::DATE.')\s+cho\s+(?<guests>[0-9]+)\s+(?:nguoi|khach)'
            .'(?:\s+khong)?\s*[?!.]*\z~';
        $prepared = $this->prepare($text, $pattern);
        if ($prepared === null) {
            return $this->answer(self::GUIDE, 'search');
        }

        return $this->publicReply($prepared['roomId'], $prepared['query']);
    }

    /** @return array{roomId: int, query: CheckAvailabilityQuery}|null */
    private function prepare(#[\SensitiveParameter] string $text, string $pattern): ?array
    {
        if (! preg_match($pattern, $text, $input) || ($roomId = $this->roomId($input['room'])) === null) {
            return null;
        }

        try {
            $query = $this->validateDatesAndGuests($input['checkIn'], $input['checkOut'], $input['guests']);
        } catch (ValidationException) {
            return null;
        }

        return ['roomId' => $roomId, 'query' => $query];
    }

    /** @return array{message: string, action: ?string, rooms: array} */
    private function publicReply(int $roomId, CheckAvailabilityQuery $query): array
    {
        $room = $this->catalog->findPublicRoomForAvailability($roomId);
        if ($room === null || $room->property === null) {
            return $this->answer('Chưa tìm thấy thông tin công khai phù hợp cho mã phòng này.', 'search');
        }

        if (! $this->availability->checkRoom($room, $query)['available']) {
            return $this->answer('Phòng chưa khả dụng tại thời điểm kiểm tra cho khoảng ngày và số khách đã nhập.', 'search');
        }

        return $this->answer(
            sprintf(
                '%s khả dụng tại thời điểm kiểm tra, từ %s đến %s cho %d người.',
                $room->name,
                implode('/', array_reverse(explode('-', $query->checkInDate))),
                implode('/', array_reverse(explode('-', $query->checkOutDate))),
                $query->guestCount,
            ),
            rooms: [[
                'property_id' => $room->property->id,
                'property_name' => $room->property->name,
                'room_id' => $room->id,
                'room_name' => $room->name,
            ]],
        );
    }

    private function isoDate(string $date): string
    {
        return str_contains($date, '/') ? implode('-', array_reverse(explode('/', $date))) : $date;
    }

    public function roomId(string $value): ?int
    {
        // Match the safe-integer range already accepted by React room cards.
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9007199254740991]]);

        return $id === false ? null : $id;
    }

    private function answer(string $message, ?string $action = null, array $rooms = []): array
    {
        return ['message' => $message, 'action' => $action, 'rooms' => $rooms];
    }
}
