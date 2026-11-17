<?php

namespace App\Mappers\Review;

use App\DTOs\Review\Requests\CreateReviewRequest;
use App\DTOs\Review\Requests\PublicReviewFilterRequest;
use App\DTOs\Review\Responses\PublicReviewResponse;
use App\Models\Review;
use App\Models\Booking;
use App\Models\User;
use App\DTOs\Review\Requests\CustomerReviewFilterRequest;
use App\DTOs\Review\Responses\CustomerReviewResponse;
use App\DTOs\Review\Requests\HostReviewFilterRequest;
use App\DTOs\Review\Responses\HostReviewResponse;
use App\Mappers\EloquentObjectMapper;
use LogicException;

class ReviewMapper
{
    public function __construct(private readonly EloquentObjectMapper $objectMapper) {}

    /** Input has already passed ReviewValidator; do not normalize unvalidated values. */
    public function toPublicFilterRequest(int $propertyId, array $input): PublicReviewFilterRequest
    {
        return new PublicReviewFilterRequest(
            propertyId: $propertyId,
            page: (int) ($input['page'] ?? 1),
            perPage: (int) ($input['per_page'] ?? 10),
            rating: array_key_exists('rating', $input) ? (int) $input['rating'] : null,
            sortBy: $input['sort_by'] ?? 'latest',
        );
    }

    public function toPublicResponse(Review $review): PublicReviewResponse
    {
        if (!$review->relationLoaded('customer')) {
            throw new LogicException('Public review mapping requires an eager-loaded customer relation.');
        }

        return $this->mapResponse($review, PublicReviewResponse::class, ['id', 'rating', 'comment'], [
            'author' => $this->anonymizeName($review->getRelation('customer')?->name),
        ], ['created_at']);
    }

    /** Raw values have already passed Host query validation. */
    public function toHostFilterRequest(array $input): HostReviewFilterRequest
    {
        return new HostReviewFilterRequest(
            page: (int) ($input['page'] ?? 1),
            perPage: (int) ($input['per_page'] ?? 10),
            propertyId: array_key_exists('property_id', $input) ? (int) $input['property_id'] : null,
            rating: array_key_exists('rating', $input) ? (int) $input['rating'] : null,
            search: $input['search'] ?? null,
        );
    }

    public function toHostResponse(Review $review): HostReviewResponse
    {
        // Preserve legacy nullable projections without lazy-loading missing relations.
        $booking = $review->relationLoaded('booking') ? $review->getRelation('booking') : null;
        $property = $review->relationLoaded('property') ? $review->getRelation('property') : null;
        $room = $booking && $booking->relationLoaded('room') ? $booking->getRelation('room') : null;
        $customer = $review->relationLoaded('customer') ? $review->getRelation('customer') : null;

        return $this->mapResponse($review, HostReviewResponse::class, ['id', 'booking_id', 'property_id', 'rating', 'comment'], [
            'booking_code' => $booking?->booking_code ?? null,
            'property_name' => $property?->name ?? null,
            'room_name' => $room?->name ?? null,
            'customer_name' => $customer?->name ?? 'Khách lưu trú',
        ], ['created_at']);
    }

    /** Preserve permissive Customer pagination and its distinct input/query sources. */
    public function toCustomerFilterRequest(array $input, array $query): CustomerReviewFilterRequest
    {
        $queryPerPage = $query['per_page'] ?? null;
        $perPage = array_key_exists('per_page', $input) && is_numeric($queryPerPage)
            ? min(max(1, (int) $queryPerPage), 50)
            : 10;
        $rawPage = $input['page'] ?? null;
        // Match Laravel's current-page resolver, without introducing HTTP validation.
        $page = filter_var($rawPage, FILTER_VALIDATE_INT) !== false && (int) $rawPage >= 1
            ? (int) $rawPage : 1;

        return new CustomerReviewFilterRequest($page, $perPage);
    }

    public function toCustomerResponse(Review $review): CustomerReviewResponse
    {
        $booking = $review->relationLoaded('booking') ? $review->getRelation('booking') : null;
        $property = $review->relationLoaded('property') ? $review->getRelation('property') : null;
        $room = $booking && $booking->relationLoaded('room') ? $booking->getRelation('room') : null;

        return $this->mapResponse($review, CustomerReviewResponse::class, ['id', 'booking_id', 'property_id', 'rating', 'comment'], [
            'booking_code' => $booking?->booking_code ?? null,
            'property_name' => $property?->name ?? null,
            'room_name' => $room?->name ?? null,
        ], ['created_at', 'updated_at']);
    }

    /**
     * @template T of object
     * @param class-string<T> $target
     * @param list<string> $fields
     * @param array<string, mixed> $prepared
     * @param list<string> $timestamps
     * @return T
     */
    private function mapResponse(Review $review, string $target, array $fields, array $prepared, array $timestamps): object
    {
        $attributes = $review->getAttributes();
        foreach ($timestamps as $timestamp) {
            if (array_key_exists($timestamp, $attributes)) {
                $fields[] = $timestamp;
            } else {
                $prepared[$timestamp] = null;
            }
        }

        return $this->objectMapper->map($review, $target, $fields, $prepared);
    }

    /** @param array{rating: int|float|string|bool, comment: string} $input Validated HTTP input. */
    public function toCreateRequest(int $bookingId, array $input): CreateReviewRequest
    {
        $rating = $input['rating'];

        return new CreateReviewRequest($bookingId, is_bool($rating) ? $rating : (int) $rating, $input['comment']);
    }

    /** Project server-owned identities and domain-validated values; do not trust client owner fields. */
    public function toCreationAttributes(Booking $booking, User $actor, int $propertyId, array $data): array
    {
        return [
            'booking_id' => $booking->id,
            'customer_id' => $actor->id,
            'property_id' => $propertyId,
            'rating' => (int) $data['rating'],
            'comment' => trim((string) $data['comment']),
        ];
    }

    public function anonymizeName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return 'Khách lưu trú';
        }

        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return 'Khách lưu trú';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        $first = $parts[0];
        $last = end($parts);
        $lastInitial = mb_strtoupper(mb_substr($last, 0, 1, 'UTF-8'), 'UTF-8');

        return "{$first} {$lastInitial}.";
    }
}
