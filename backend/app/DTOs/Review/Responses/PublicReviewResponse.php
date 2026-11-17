<?php

namespace App\DTOs\Review\Responses;

use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class PublicReviewResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id', transform: 'intval')]
        public int $id,
        #[Map(source: 'rating', transform: 'intval')]
        public int $rating,
        #[Map(source: 'comment', transform: 'strval')]
        public string $comment,
        #[Map(source: 'author')]
        public string $author,
        #[Map(source: 'created_at', transform: [self::class, 'toIsoString'])]
        public ?string $created_at,
    ) {}

    public static function toIsoString(?CarbonInterface $date): ?string
    {
        return $date?->toISOString();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'author' => $this->author,
            'created_at' => $this->created_at,
        ];
    }
}
