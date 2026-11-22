<?php

namespace App\Services\Chat;

use App\DTOs\Catalog\Requests\PublicRoomFilterQuery;
use App\Models\Property;
use App\Models\Room;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\PublicRoomQueryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class PublicCatalogChatService
{
    private const TARGET = '/(?<!\S)(?:tại|tai)\h+(?:#(?<id>[1-9][0-9]*)|"(?<name>[^"\r\n]+)")[?!.]*\s*\z/iu';

    private const INTENT = '/\b(tim phong|khach san|homestay|phong cho|phong nghi|cho nghi|cho nay|co so nay|phong nay|con phong|phong trong|[1-9][0-9]?\s*(nguoi|khach)|ngan sach)\b|\btai\s+(?:#|")/';

    private const BUDGET = '/\b(duoi|toi da|ngan sach)\s+([1-9][0-9]{0,6})\s*(trieu|nghin|ngan|k|vnd|dong)\b/';

    private const SAVED_PREFIX = '\A\h*catalog\h*:\h*';

    private const AMENITY_PREFIX = '\A\h*tiện\h+ích\h*:\h*';

    private const AMENITY_COMMAND = '(?:(?:phòng|phong)\h+"(?<room>[^"\p{C}\p{Zl}\p{Zp}]+)"\h+)?'
        .'(?:có|co)\h+(?<amenity>[^"\p{C}\p{Zl}\p{Zp}]+?)\h+(?:không|khong)\h+(?:tại|tai)\h+#(?<property>[1-9][0-9]*)[?!.]*\z';

    private const SAVED_CRITERIA = '(?:tìm|tim)\h+(?:phòng|phong)\h+cho\h+(?<guests>[1-9][0-9]?)\h+(?:người|nguoi|khách|khach)\h+'
        .'(?<mode>dưới|duoi|(?:tối|toi)\h+(?:đa|da)|(?:ngân|ngan)\h+(?:sách|sach))\h+(?<amount>[1-9][0-9]{0,6})\h*'
        .'(?<unit>triệu|trieu|nghìn|nghin|ngàn|ngan|k|vnd|đồng|dong)'
        .'(?:\h+(?:tại|tai)\h+(?:#(?<property>[1-9][0-9]*)|"(?<name>[^"\p{C}\p{Zl}\p{Zp}]+)"))?[?!.]*\z';

    private const NO_ROOMS = 'Chưa tìm thấy phòng phù hợp. Bạn có thể đổi tiêu chí trên trang tìm kiếm.';

    private const WIFI = '/\bwi(?:\s*-\s*|\s*)fi\b/';

    private const WIFI_ROOM = '/\A\s*(?:phòng|phong)\h+"(?<name>[^"\r\n]+)"/iu';

    private const WIFI_NAMES = ['Wi-Fi', 'Wifi', 'Wi Fi'];

    private const WIFI_UNLISTED = 'Chưa thấy thông tin Wi-Fi trong danh mục công khai.';

    private const ROOM_NOT_FOUND = 'Chưa tìm thấy phòng công khai phù hợp tại cơ sở này.';

    private const DUPLICATE_ROOM = 'Có nhiều phòng công khai trùng tên tại cơ sở này. Bạn hãy xem trang chi tiết phòng để đối chiếu tiện nghi.';

    private const ISO_DATE = '\b[0-9]{4}-[0-9]{2}-[0-9]{2}\b';

    private const SEARCH = 'Bạn có thể kiểm tra khu vực, ngày lưu trú và phòng trống trên trang tìm kiếm.';

    private const NO_PROPERTY = 'Chưa tìm thấy thông tin công khai phù hợp. Bạn có thể kiểm tra lại tên hoặc mã cơ sở.';

    private const MANY_PROPERTIES = 'Có nhiều cơ sở công khai trùng tên. Hãy dùng trang tìm kiếm để xác định cơ sở, rồi gửi lại câu hỏi kèm mã trên trang chi tiết.';

    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly PublicRoomQueryService $rooms,
        private readonly PublicServiceChatService $services,
        private readonly PublicRoomAvailabilityChatService $availability,
    ) {}

    public function privacyText(#[\SensitiveParameter] string $text): string
    {
        if (str_starts_with($text, 'faq:') || ! $this->isCatalogQuestion($text)) {
            return $text;
        }

        // These typed tokens are consumed locally. Check the rest of the input for private data.
        if (preg_match(self::TARGET, $text, $target) && isset($target['id'])
            && $this->propertyId($target['id']) !== null) {
            $text = substr($text, 0, -strlen($target[0]));
        }

        return preg_replace('/'.self::ISO_DATE.'/', ' [ngay] ', $this->availability->privacyText($text));
    }

    public function isSavedListingCommand(#[\SensitiveParameter] string $question): bool
    {
        return preg_match('/'.self::SAVED_PREFIX.'/iu', $question) === 1;
    }

    public function isSavedAmenityCommand(#[\SensitiveParameter] string $question): bool
    {
        return preg_match('/'.self::AMENITY_PREFIX.'/iu', $question) === 1;
    }

    /** @return array{reply: string}|array{error: string} */
    public function prepareSavedAmenity(#[\SensitiveParameter] string $question): array
    {
        $syntax = 'Dùng mẫu “Tiện ích: Có hồ bơi không tại #71?” hoặc “Tiện ích: Phòng "Deluxe" có điều hòa không tại #71?”.';
        if (! preg_match('/'.self::AMENITY_PREFIX.self::AMENITY_COMMAND.'/iu', $question, $parts, PREG_UNMATCHED_AS_NULL)) {
            return ['error' => $syntax];
        }

        $propertyId = $this->propertyId($parts['property']);
        $roomName = isset($parts['room']) ? $this->trimName($parts['room']) : null;
        $name = $this->trimName($parts['amenity']);
        if ($propertyId === null || ! $this->isSafeAmenityCommandName($name)
            || ($roomName !== null && ! $this->isSafeAmenityCommandName($roomName))) {
            return ['error' => $syntax];
        }

        $key = $this->amenityNameKey($name);
        $amenities = $this->catalog->getPublicRoomAmenities()->filter(
            fn ($amenity): bool => $this->amenityNameKey($amenity->name) === $key,
        );
        if ($amenities->isEmpty() || ($key !== 'wifi' && $amenities->count() !== 1)) {
            return ['error' => 'Hãy kiểm tra tên một tiện ích đang được niêm yết trong danh mục công khai.'];
        }

        $property = $this->catalog->findPublicPropertyCandidates($propertyId)->first();
        if ($property === null) {
            return $roomName === null ? ['reply' => self::NO_PROPERTY] : ['error' => self::ROOM_NOT_FOUND];
        }

        $label = $key === 'wifi' ? 'Wi-Fi' : $this->oneLine($amenities->sole()->name);
        $rooms = $this->catalog->findPublicRoomAmenityCandidatesByIds($propertyId, $amenities->modelKeys(), $roomName);
        if ($roomName !== null) {
            if ($rooms->isEmpty()) {
                return ['error' => self::ROOM_NOT_FOUND];
            }
            if ($rooms->count() > 1) {
                return ['reply' => self::DUPLICATE_ROOM];
            }
            if ($rooms->sole()->has_requested_amenity) {
                return ['reply' => sprintf('Phòng %s tại %s có %s được niêm yết.',
                    $this->oneLine($rooms->sole()->name), $this->oneLine($property->name), $label)];
            }
        } elseif ($rooms->isNotEmpty()) {
            $lines = $rooms->map(fn (Room $room): string => '• '.$this->oneLine($room->name))->implode("\n");

            return ['reply' => sprintf("Một số phòng công khai tại %s có %s được niêm yết:\n%s",
                $this->oneLine($property->name), $label, $lines)];
        }

        return ['reply' => 'Chưa thấy thông tin '.$label.' trong danh mục công khai.'];
    }

    private function trimName(#[\SensitiveParameter] string $name): string
    {
        return preg_replace('/\A\h+|\h+\z/u', '', $name);
    }

    private function oneLine(string $name): string
    {
        return trim(preg_replace('/[\p{C}\p{Z}\s]+/u', ' ', $name));
    }

    private function isSafeAmenityCommandName(#[\SensitiveParameter] string $name): bool
    {
        return $this->isSafePropertyName($name)
            && ! preg_match('/'.self::ISO_DATE.'|\b[0-9]{1,2}\/[0-9]{1,2}(?:\/[0-9]{2,4})?\b/u', $name);
    }

    private function amenityNameKey(#[\SensitiveParameter] string $name): string
    {
        $key = Str::lower($this->oneLine($name));

        return preg_match('/\Awi *(?:[-‐‑] *)?fi\z/u', $key) ? 'wifi' : $key;
    }

    /** @return array{status: 'ready', filters: PublicRoomFilterQuery}|array{status: 'clarification', reply: string}|array{status: 'unknown'}|null */
    public function prepareSavedListing(#[\SensitiveParameter] string $question): ?array
    {
        if (! preg_match('/'.self::SAVED_PREFIX.self::SAVED_CRITERIA.'/iu', $question, $criteria, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        $propertyId = isset($criteria['property']) ? $this->propertyId($criteria['property']) : null;
        if (isset($criteria['property']) && $propertyId === null) {
            return null;
        }

        if (isset($criteria['name'])) {
            $name = preg_replace('/\A\h+|\h+\z/u', '', $criteria['name']);
            if (! $this->isSafePropertyName($name)) {
                return null;
            }

            // Verify that the supplied name belongs to the public catalog before storing the question.
            $candidates = $this->catalog->findPublicPropertyCandidates($name);
            if ($candidates->isEmpty()) {
                return ['status' => 'unknown'];
            }
            if ($candidates->count() > 1) {
                return [
                    'status' => 'clarification',
                    'reply' => $candidates->count() > 3 ? self::MANY_PROPERTIES : $this->duplicatePropertyReply($candidates),
                ];
            }

            $propertyId = $candidates->sole()->id;
        }

        return ['status' => 'ready', 'filters' => $this->listingFilters(
            (int) $criteria['guests'],
            $this->normalize($criteria['mode']),
            $criteria['amount'],
            $this->normalize($criteria['unit']),
            $propertyId,
        )];
    }

    private function isSafePropertyName(#[\SensitiveParameter] string $name): bool
    {
        return $name !== '' && mb_strlen($name) <= 255
            && ! preg_match('/@|:\/\/|(?:[a-z0-9-]+\.)+[a-z]{2,63}\b|\A\p{Nd}{4,8}\z|(?:\p{Nd}[\h.()+-]*){7,}/iu', $name)
            && ! preg_match('/\b(mat khau|otp|token|cccd|so the|email|so dien thoai|ma bao mat)\b/', $this->normalize($name));
    }

    public function savedListingReply(PublicRoomFilterQuery $filters): string
    {
        $rooms = $this->listPublicRooms($filters);
        if ($rooms === []) {
            return self::NO_ROOMS;
        }

        $lines = array_map(static fn (array $room): string => sprintf(
            '• %s — %s (#%d)',
            trim(preg_replace('/\s+/u', ' ', $room['room_name'])),
            trim(preg_replace('/\s+/u', ' ', $room['property_name'])),
            $room['property_id'],
        ), $rooms);

        return "Phòng đang niêm yết phù hợp:\n".implode("\n", $lines)
            ."\nBạn có thể kiểm tra ngày lưu trú trên trang tìm kiếm.";
    }

    /** @return array{message: string, action: ?string, rooms: array}|null */
    public function reply(#[\SensitiveParameter] string $question, #[\SensitiveParameter] string $text): ?array
    {
        if (! $this->isCatalogQuestion($text)) {
            return null;
        }

        preg_match(self::TARGET, $question, $target, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        $rawCriteria = $target === [] ? $question : substr($question, 0, $target[0][1]);
        $criteria = $target === [] ? $text : $this->normalize($rawCriteria);

        if ($this->mentionsWifi($criteria)) {
            preg_match(self::WIFI_ROOM, $rawCriteria, $room);
            $wifiCriteria = $room === [] ? $criteria : $this->normalize(substr($rawCriteria, strlen($room[0])));
            if ($this->mentionsWifi($wifiCriteria)) {
                return $this->needsSearch($wifiCriteria)
                    ? $this->answer(self::SEARCH, 'search')
                    : $this->replyWifi($target['id'][0] ?? null, isset($room['name']) ? trim($room['name']) : null);
            }
        }

        if ($this->services->matches($criteria)) {
            $reference = $target['id'][0] ?? null;
            $property = $reference === null ? null : $this->findPublicPropertyByCode($reference);

            return $this->answer($this->services->reply($criteria, $property, $reference !== null));
        }

        if ($this->availability->matches($text)) {
            return $this->availability->reply($text);
        }

        if ($this->needsSearch($criteria)) {
            return $this->answer(self::SEARCH, 'search');
        }
        if ($target === [] && preg_match('/\b(cho nay|co so nay|phong nay|homestay nay|khach san nay)\b/', $criteria)) {
            return $this->answer('Bạn cho mình tên hoặc mã của cơ sở nhé. Ví dụ: tại "Tên cơ sở" hoặc tại #71.');
        }
        if (preg_match('/\btai\s+(?:#|")/', $criteria)) {
            return $this->answer(self::NO_PROPERTY, 'search');
        }

        preg_match('/\b([1-9][0-9]?)\s*(?:nguoi|khach)\b/', $criteria, $guests);
        preg_match(self::BUDGET, $criteria, $budget);
        if (($guests === [] && $budget === [])
            || ($budget === [] && preg_match('/\b(duoi|toi da|ngan sach)\b/', $criteria))) {
            return $this->answer('Bạn có thể cho biết số khách và ngân sách mỗi đêm, hoặc dùng trang tìm kiếm để chọn chỗ nghỉ.', 'search');
        }

        $propertyId = null;
        if ($target !== []) {
            $reference = $target['id'][0] === null ? trim($target['name'][0]) : $this->propertyId($target['id'][0]);
            $candidates = $reference === null || $reference === '' ? collect()
                : $this->catalog->findPublicPropertyCandidates($reference);
            if ($candidates->isEmpty()) {
                return $this->answer(self::NO_PROPERTY, 'search');
            }
            if ($candidates->count() > 3) {
                return $this->answer(self::MANY_PROPERTIES, 'search');
            }
            if ($candidates->count() > 1) {
                return $this->answer($this->duplicatePropertyReply($candidates));
            }
            $propertyId = $candidates->sole()->id;
        }

        $filters = $this->listingFilters(
            isset($guests[1]) ? (int) $guests[1] : null,
            $budget[1] ?? null,
            $budget[2] ?? null,
            $budget[3] ?? null,
            $propertyId,
        );
        $cards = $this->listPublicRooms($filters);

        return $cards === []
            ? $this->answer(self::NO_ROOMS, 'search')
            : $this->answer('Bạn có thể tham khảo các phòng đang niêm yết này. Hãy kiểm tra ngày lưu trú trên trang chi tiết.', rooms: $cards);
    }

    /** @param Collection<int, Property> $candidates */
    private function duplicatePropertyReply(Collection $candidates): string
    {
        $options = $candidates->map(static fn (Property $property): string => sprintf(
            '#%d – %s, %s',
            $property->id,
            preg_replace('/\s+/u', ' ', $property->name),
            preg_replace('/\s+/u', ' ', $property->address),
        ))->implode("\n");

        return "Có nhiều cơ sở công khai trùng tên:\n".$options
            ."\nBạn gửi lại số khách và ngân sách kèm mã đã chọn, ví dụ tại #".$candidates->first()->id.'.';
    }

    private function listingFilters(
        ?int $guestCount,
        ?string $budgetMode,
        ?string $budgetAmount,
        ?string $budgetUnit,
        ?int $propertyId,
    ): PublicRoomFilterQuery {
        $maximum = $budgetAmount === null ? null : (string) ((int) $budgetAmount * match ($budgetUnit) {
            'trieu' => 1_000_000,
            'nghin', 'ngan', 'k' => 1_000,
            default => 1,
        });
        $exclusive = $budgetMode === 'duoi';
        return new PublicRoomFilterQuery(
            maxPrice: $exclusive ? null : $maximum,
            guestCount: $guestCount,
            perPage: 3,
            propertyId: $propertyId,
            maxPriceExclusive: $exclusive ? $maximum : null,
        );
    }

    /** @return list<array{property_id: int, property_name: string, room_id: int, room_name: string}> */
    private function listPublicRooms(PublicRoomFilterQuery $filters): array
    {
        return $this->rooms->listing($filters)->getCollection()->map(static fn ($item): array => [
            'property_id' => $item->property['id'],
            'property_name' => $item->property['name'],
            'room_id' => $item->room->id,
            'room_name' => $item->room->name,
        ])->values()->all();
    }

    private function isCatalogQuestion(string $text): bool
    {
        return preg_match(self::INTENT, $text) === 1 || preg_match(self::BUDGET, $text) === 1
            || $this->mentionsWifi($text) || $this->services->matches($text) || $this->availability->matches($text);
    }

    private function mentionsWifi(string $text): bool
    {
        return preg_match(self::WIFI, $text) === 1;
    }

    private function replyWifi(?string $reference, ?string $roomName): array
    {
        if ($reference === null) {
            return $this->answer('Bạn cho mình mã cơ sở để kiểm tra Wi-Fi nhé. Ví dụ: Có Wi-Fi không tại #71?');
        }

        $property = $this->findPublicPropertyByCode($reference);
        if ($property === null) {
            return $this->answer(self::NO_PROPERTY, 'search');
        }

        $rooms = $this->catalog->findPublicRoomAmenityCandidates($property->id, self::WIFI_NAMES, $roomName);
        if ($roomName !== null) {
            if ($rooms->isEmpty()) {
                return $this->answer(self::ROOM_NOT_FOUND, 'search');
            }
            if ($rooms->count() > 1) {
                return $this->answer(self::DUPLICATE_ROOM, 'search');
            }
            if (! $rooms->first()->has_requested_amenity) {
                return $this->answer(self::WIFI_UNLISTED);
            }
        } elseif ($rooms->isEmpty()) {
            return $this->answer(self::WIFI_UNLISTED);
        }

        $message = $roomName === null
            ? 'Một số phòng công khai tại '.$property->name.' có Wi-Fi được niêm yết.'
            : 'Phòng '.$rooms->first()->name.' tại '.$property->name.' có Wi-Fi được niêm yết.';
        $cards = $rooms->map(static fn (Room $room): array => [
            'property_id' => $property->id,
            'property_name' => $property->name,
            'room_id' => $room->id,
            'room_name' => $room->name,
        ])->all();

        return $this->answer($message, rooms: $cards);
    }

    private function needsSearch(string $text): bool
    {
        return preg_match('/'.self::ISO_DATE.'|\b[0-9]{1,2}\/[0-9]{1,2}\b'
            .'|\btai\s+\w|\b(?:khach san|homestay|phong|cho nghi)\s+o\s+\w'
            .'|\b(?:(?:ngay|dem)\s+\d+|\d+\s+(?:ngay|dem)|ngay mai|hom nay|cuoi tuan|nhan phong|tra phong'
            .'|check[ -]?in|check[ -]?out|phong trong|con trong|con phong|tong (?:gia|tien|chi phi)|gia cuoi cung)\b/', $text) === 1;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text, 'vi'))));
    }

    private function findPublicPropertyByCode(string $reference): ?Property
    {
        $propertyId = $this->propertyId($reference);

        return $propertyId === null ? null : $this->catalog->findPublicPropertyCandidates($propertyId)->first();
    }

    private function propertyId(string $input): ?int
    {
        // Card IDs must also round-trip through the existing React safe-integer contract.
        $id = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9007199254740991]]);

        return $id === false ? null : $id;
    }

    /** @return array{message: string, action: ?string, rooms: array} */
    private function answer(string $message, ?string $action = null, array $rooms = []): array
    {
        return ['message' => $message, 'action' => $action, 'rooms' => $rooms];
    }
}
