<?php

namespace App\Services\Chat;

use App\Models\Property;
use App\Repositories\Booking\ServiceRepository;
use App\Repositories\Catalog\CatalogRepository;
use Illuminate\Support\Str;

final class PublicServiceChatService
{
    private const SERVICE_NAMES = ['giat ui' => 'Giặt ủi', 'dua don san bay' => 'Đưa đón sân bay'];

    private const SERVICE_PATTERN = '/\b(giat ui|dua don san bay)\b/';

    private const MISSING = 'Chưa tìm thấy thông tin dịch vụ công khai phù hợp.';

    private const SAVED_PREFIX = '/\A\h*Dịch vụ\h*:\h*/iu';

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly CatalogRepository $catalog,
    ) {}

    public function isSavedCommand(#[\SensitiveParameter] string $question): bool
    {
        return preg_match(self::SAVED_PREFIX, $question) === 1;
    }

    /** @return array{propertyId: int, name: string}|null */
    public function parseSavedCommand(#[\SensitiveParameter] string $question): ?array
    {
        $command = preg_replace(self::SAVED_PREFIX, '', $question, 1, $prefixCount);
        if ($prefixCount !== 1 || ! preg_match('/\A(Có|Giá)\h+(Giặt ủi|Đưa đón sân bay)\h+(không|là bao nhiêu)\h+tại\h+#([1-9][0-9]{0,15})\h*\??\h*\z/iu', $command, $parts)) {
            return null;
        }

        $ending = Str::lower($parts[1]) === 'có' ? 'không' : 'là bao nhiêu';
        $propertyId = filter_var($parts[4], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 9007199254740991],
        ]);
        if (Str::lower($parts[3]) !== $ending || $propertyId === false) {
            return null;
        }

        return ['propertyId' => $propertyId, 'name' => self::SERVICE_NAMES[Str::lower(Str::ascii($parts[2], 'vi'))]];
    }

    public function savedReply(int $propertyId, string $name): string
    {
        $property = $this->catalog->findPublicPropertyCandidates($propertyId)->first();

        return $property === null ? self::MISSING : $this->publishedReply($property, $name);
    }

    public function matches(#[\SensitiveParameter] string $criteria): bool
    {
        return preg_match(self::SERVICE_PATTERN, $criteria) === 1 || preg_match('/\bdich vu\b/', $criteria) === 1;
    }

    public function reply(#[\SensitiveParameter] string $criteria, ?Property $property, bool $hasCode): string
    {
        if (preg_match('/\b(them|dat|huy|sua|cap nhat)\s+(dich vu|giat ui|dua don san bay)\b/', $criteria)) {
            return 'Mình chỉ có thể tra cứu thông tin dịch vụ niêm yết. Bạn hãy liên hệ cơ sở để yêu cầu dịch vụ.';
        }

        preg_match_all(self::SERVICE_PATTERN, $criteria, $mentions);
        $names = array_values(array_unique($mentions[0]));
        if ($names === []) {
            return self::MISSING;
        }
        if (count($names) > 1) {
            return 'Bạn hỏi từng dịch vụ một để mình kiểm tra đúng thông tin nhé.';
        }
        if (! $hasCode) {
            return 'Bạn cho mình mã cơ sở để kiểm tra dịch vụ nhé. Ví dụ: Giá đưa đón sân bay là bao nhiêu tại #71?';
        }
        if ($property === null) {
            return self::MISSING;
        }

        return $this->publishedReply($property, self::SERVICE_NAMES[$names[0]]);
    }

    private function publishedReply(Property $property, string $name): string
    {
        $matches = $this->services->findPublishedForProperty($property->id, $name);
        if ($matches->isEmpty()) {
            return self::MISSING;
        }
        if ($matches->count() > 1) {
            return 'Có nhiều dịch vụ công khai trùng tên tại cơ sở này. Chưa thể xác định một đơn giá niêm yết duy nhất.';
        }

        $service = $matches->sole();
        $unit = Str::lower(trim($service->unit));
        if (! in_array($unit, ['kg', 'lượt'], true)
            || ! preg_match('/\A([0-9]{1,10})\.([0-9]{2})\z/', $service->price, $price)) {
            return self::MISSING;
        }

        // Keep both components as strings so the listed price is displayed exactly.
        $amount = preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', '.', $price[1]);
        if ($price[2] !== '00') {
            $amount .= ','.$price[2];
        }

        return sprintf('Tại %s, dịch vụ %s có đơn giá niêm yết %s đ/%s.', $property->name, $name, $amount, $unit);
    }
}
