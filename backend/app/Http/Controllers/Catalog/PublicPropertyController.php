<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PublicPropertyListRequest;
use App\Http\Requests\Catalog\PublicPropertyDetailRequest;
use App\Http\Resources\Catalog\PublicPropertyResource;
use App\Services\Catalog\PublicPropertyQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicPropertyController extends Controller
{
    public function index(PublicPropertyListRequest $request, PublicPropertyQuery $query): AnonymousResourceCollection
    {
        return PublicPropertyResource::collection($query->listing((int) $request->input('per_page', 12), (int) $request->input('page', 1)));
    }

    public function show(PublicPropertyDetailRequest $request, string $property, PublicPropertyQuery $query): PublicPropertyResource|JsonResponse
    {
        $id = (int) $property;
        $model = $id > 0 && (string) $id === $property ? $query->detail($id) : null;
        return $model ? new PublicPropertyResource($model)
            : response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404);
    }
}
