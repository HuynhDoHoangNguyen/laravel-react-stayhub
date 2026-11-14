<?php

namespace App\Common;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use JsonSerializable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/** HTTP serialization helper; does not query models or implement business rules. */
final class ApiResponse
{
    public static function page(ResourceCollection|LengthAwarePaginator $data, int $status = 200): JsonResponse
    {
        return PageResponse::toResponse($data, $status);
    }

    /** Accept an explicit DTO, legacy resource or normalized data, never an envelope. */
    public static function success(array|JsonResource|JsonSerializable $data, int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            // Let Laravel preserve pagination, additional fields and resource hooks.
            return $data->response()->setStatusCode($status);
        }

        return response()->json(['data' => $data], $status);
    }

    /** @param array<string, list<string>> $errors */
    public static function error(string $message, string $code, int $status, array $errors = []): JsonResponse
    {
        $body = ['message' => $message, 'code' => $code];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    public static function noContent(): Response
    {
        return response()->noContent();
    }
}
