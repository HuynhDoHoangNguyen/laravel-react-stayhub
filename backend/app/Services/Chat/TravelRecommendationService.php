<?php

namespace App\Services\Chat;

use App\Models\Property;
use App\Services\Booking\SearchService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;

final class TravelRecommendationService
{
    private const PROPERTY_LIMIT = 2;
    private const CITY_LIMIT = 3;

    public function __construct(private readonly SearchService $search) {}

    public function reply(#[\SensitiveParameter] string $text): ?array
    {
        $criteria = $this->recognize($text);

        return $criteria === null ? null : $this->recommend($criteria);
    }

    /** @return array{guests: int, theme: 'couple'|'november'|'reason'}|null */
    public function recognize(#[\SensitiveParameter] string $text): ?array
    {
        $couple = preg_match('/\b(tuan trang mat|honeymoon|nguoi yeu|cap doi|lang manx?)\b/', $text) === 1;
        $travel = preg_match('/\b(di dau|di (choi|du lich|nghi duong)|du lich|diem den|thanh pho nao)\b/', $text) === 1;
        $nearby = preg_match('/\b(gan nha|di dao|buon qua)\b/', $text) === 1;
        if ((! $travel && ! $couple) || $nearby) {
            return null;
        }

        preg_match('/\b([1-9][0-9]?)\s*(nguoi|khach)\b/', $text, $party);
        $guests = isset($party[1]) ? (int) $party[1] : ($couple ? 2 : 1);
        $november = preg_match('/\bthang\s*11\b/', $text) === 1;
        $theme = $couple ? 'couple' : ($november ? 'november' : 'reason');

        return ['guests' => $guests, 'theme' => $theme];
    }

    /** @param array{guests: int, theme: 'couple'|'november'|'reason'} $criteria */
    public function recommend(array $criteria): array
    {
        ['guests' => $guests, 'theme' => $theme] = $criteria;
        $cities = config('chatbot_destinations');
        $cities = array_filter($cities, static fn (array $city): bool => isset($city[$theme]))
            + $cities;

        $destinations = [];
        foreach ($cities as $city => $reasons) {
            $properties = $this->propertiesIn($city, $guests);
            if ($properties !== []) {
                $destinations[] = [
                    'city' => $city,
                    'reason' => $reasons[$theme] ?? $reasons['reason'],
                    'properties' => $properties,
                ];
            }
            if (count($destinations) === self::CITY_LIMIT) {
                break;
            }
        }

        $intro = match ($theme) {
            'couple' => 'Gợi ý điểm đến cho chuyến đi của hai bạn.',
            'november' => 'Gợi ý cho chuyến đi tháng 11.',
            default => 'Gợi ý điểm đến cho chuyến đi của bạn.',
        };

        return [
            'message' => $destinations === []
                ? 'Mình chưa tìm thấy chỗ ở công khai phù hợp ở các điểm đến gợi ý. Bạn có thể mở trang tìm chỗ nghỉ để xem thêm.'
                : $intro.' Bạn có thể chọn một chỗ ở bên dưới để xem chi tiết và kiểm tra ngày lưu trú.',
            'action' => $destinations === [] ? 'search' : null,
            'rooms' => [],
            'destinations' => $destinations,
        ];
    }

    private function propertiesIn(string $city, int $guests): array
    {
        $properties = [];
        $locality = $this->localityKey($city);
        $page = 1;

        do {
            $results = $this->search->searchProperties([
                'keyword' => $city, 'guest_count' => $guests, 'page' => $page, 'per_page' => 20,
            ]);
            foreach ($results as $property) {
                if ($property->starting_price === null || ! $this->matchesLocality($property, $locality)) {
                    continue;
                }
                $price = BigDecimal::of((string) $property->starting_price);
                if ($price->isNegative()) {
                    continue;
                }

                $properties[] = [
                    'property_id' => $property->id,
                    'property_name' => $property->name,
                    'starting_price' => (string) $price->toScale(2),
                ];
                if (count($properties) === self::PROPERTY_LIMIT) {
                    return $properties;
                }
            }
            $page++;
        } while ($results->hasMorePages());

        return $properties;
    }

    private function matchesLocality(Property $property, string $locality): bool
    {
        foreach (explode(',', $property->address) as $part) {
            if ($this->localityKey($part) === $locality) {
                return true;
            }
        }

        return false;
    }

    private function localityKey(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value), 'vi'));

        return preg_replace('/^(?:thanh pho\s+|tp\.?\s*)/', '', $value);
    }
}
