<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\PublicRoomFilterQuery;
use App\DTOs\Catalog\Requests\PublicRoomDetailQuery;
use App\DTOs\Catalog\Responses\PublicRoomDetailResponse;
use App\DTOs\Catalog\Responses\PublicRoomFilterOptionsResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Repositories\Catalog\CatalogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PublicRoomQueryService
{
    public function __construct(private readonly CatalogRepository $repository, private readonly CatalogMapper $mapper) {}

    public function listing(PublicRoomFilterQuery $filters): LengthAwarePaginator
    {
        $page = $this->repository->paginatePublicRooms($filters);
        $page->setCollection($page->getCollection()->map($this->mapper->toPublicRoomListItem(...)));

        return $page->withQueryString();
    }

    public function detail(PublicRoomDetailQuery $input): ?PublicRoomDetailResponse
    {
        $room = $this->repository->findPublicRoom($input->roomId);

        return $room === null ? null : $this->mapper->toPublicRoomDetail($room);
    }

    public function filterOptions(): PublicRoomFilterOptionsResponse
    {
        return $this->mapper->toPublicRoomFilterOptions($this->repository->getPublicRoomTypes(), $this->repository->getPublicRoomAmenities());
    }
}
