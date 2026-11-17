<?php

namespace App\Common;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use InvalidArgumentException;
use Illuminate\Support\Arr;

/** DTO pagination envelope with a compatible legacy ResourceCollection branch. */
final class PageResponse
{
    public static function toResponse(ResourceCollection|LengthAwarePaginator $resource, int $status = 200): JsonResponse
    {
        if ($resource instanceof LengthAwarePaginator) {
            // Match Laravel PaginatedResourceResponse without wrapping DTOs in Resources.
            $page = $resource->toArray();
            return response()->json([
                'data' => $page['data'],
                'links' => [
                    'first' => $page['first_page_url'] ?? null,
                    'last' => $page['last_page_url'] ?? null,
                    'prev' => $page['prev_page_url'] ?? null,
                    'next' => $page['next_page_url'] ?? null,
                ],
                'meta' => Arr::except($page, ['data', 'first_page_url', 'last_page_url', 'prev_page_url', 'next_page_url']),
            ], $status);
        }

        if (!$resource->resource instanceof LengthAwarePaginator) {
            throw new InvalidArgumentException('PageResponse requires a length-aware paginated resource.');
        }

        return $resource->response()->setStatusCode($status);
    }
}
