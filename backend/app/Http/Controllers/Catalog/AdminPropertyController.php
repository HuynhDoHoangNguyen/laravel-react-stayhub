<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Catalog\AdminPropertyQueryService;
use App\Validators\Catalog\AdminPropertyQueryValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminPropertyController extends Controller
{
    public function __construct(
        private readonly AdminPropertyQueryService $reader,
        private readonly AdminPropertyQueryValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::page($this->reader->paginate($this->validator->validate($request->query())));
    }
}
