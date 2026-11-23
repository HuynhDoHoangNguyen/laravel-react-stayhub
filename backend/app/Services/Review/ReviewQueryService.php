<?php

namespace App\Services\Review;

use App\DTOs\Review\Requests\PublicReviewFilterRequest;
use App\DTOs\Review\Responses\PublicReviewResponse;
use App\Mappers\Review\ReviewMapper;
use App\Models\Review;
use App\DTOs\Review\Requests\CustomerReviewFilterRequest;
use App\DTOs\Review\Responses\CustomerReviewResponse;
use App\Models\User;
use App\Validators\Review\ReviewValidator;
use App\DTOs\Review\Requests\HostReviewFilterRequest;
use App\DTOs\Review\Responses\HostReviewResponse;
use App\Repositories\Review\ReviewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewQueryService
{
    public function __construct(
        private readonly ReviewRepository $repository,
        private readonly ReviewMapper $mapper,
        private readonly ReviewValidator $validator,
    )
    {
    }

    public function propertyExists(int $propertyId): bool
    {
        return $this->repository->propertyExists($propertyId);
    }

    public function hostOwnsProperty(User $host, int $propertyId): bool
    {
        return $this->repository->hostOwnsProperty($host->id, $propertyId);
    }

    /** @return array{average_rating: ?float, review_count: int, rating_breakdown: array<int, int>} */
    public function getPropertyReviewSummary(int $propertyId): array
    {
        $stats = $this->repository->getPropertyReviewStatistics($propertyId);

        return $this->summary($stats, 'review_count');
    }

    public function getPublicPropertyReviews(int $propertyId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->getPublicPropertyReviews($propertyId, $filters, $perPage);
    }

    /** @return LengthAwarePaginator<int, PublicReviewResponse> */
    public function getPublicReviewPage(PublicReviewFilterRequest $filters): LengthAwarePaginator
    {
        $page = $this->repository->getPublicPropertyReviews(
            $filters->propertyId,
            ['rating' => $filters->rating, 'sort_by' => $filters->sortBy],
            $filters->perPage,
            $filters->page,
        );
        $page->setCollection($page->getCollection()->map(
            fn (Review $review) => $this->mapper->toPublicResponse($review),
        ));

        return $page;
    }

    /** @return array{total_reviews: int, average_rating: ?float, rating_breakdown: array<int, int>} */
    public function getHostReviewSummary(User $host, ?int $propertyId = null): array
    {
        $stats = $this->repository->getHostReviewStatistics($host->id, $propertyId);

        return $this->summary($stats, 'total_reviews');
    }

    public function getHostReviews(User $host, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->getHostReviews($host->id, $filters, $perPage);
    }

    /** @return LengthAwarePaginator<int, HostReviewResponse> */
    public function getHostReviewPage(User $host, HostReviewFilterRequest $filters): LengthAwarePaginator
    {
        $this->validator->authorizeHost($host);
        $page = $this->repository->getHostReviews(
            $host->id,
            ['property_id' => $filters->propertyId, 'rating' => $filters->rating, 'search' => $filters->search],
            $filters->perPage,
            $filters->page,
        );
        $page->setCollection($page->getCollection()->map(
            fn (Review $review) => $this->mapper->toHostResponse($review),
        ));

        return $page;
    }

    public function getCustomerReviews(User $customer, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->getCustomerReviews($customer->id, $perPage);
    }

    /** @return LengthAwarePaginator<int, CustomerReviewResponse> */
    public function getCustomerReviewPage(User $customer, CustomerReviewFilterRequest $filters): LengthAwarePaginator
    {
        $this->validator->authorizeCustomer($customer);
        $page = $this->repository->getCustomerReviews($customer->id, $filters->perPage, $filters->page);
        $page->setCollection($page->getCollection()->map(
            fn (Review $review) => $this->mapper->toCustomerResponse($review),
        ));

        return $page;
    }

    /** Normalize SQL aggregate scalars without changing the existing rating rounding. */
    private function summary(array $stats, string $countKey): array
    {
        $total = (int) ($stats['total_count'] ?? 0);
        // Preserve each endpoint's existing summary key order as well as its values.
        $summary = $countKey === 'total_reviews'
            ? ['total_reviews' => $total, 'average_rating' => $total > 0 ? round((float) $stats['avg_rating'], 1) : null]
            : ['average_rating' => $total > 0 ? round((float) $stats['avg_rating'], 1) : null, 'review_count' => $total];
        $summary['rating_breakdown'] = [
            '5' => (int) ($stats['star_5'] ?? 0),
            '4' => (int) ($stats['star_4'] ?? 0),
            '3' => (int) ($stats['star_3'] ?? 0),
            '2' => (int) ($stats['star_2'] ?? 0),
            '1' => (int) ($stats['star_1'] ?? 0),
        ];

        return $summary;
    }
}
