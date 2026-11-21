<?php

namespace App\Http\Controllers\Statistics;

use App\Common\ApiResponse;
use App\DTOs\Statistics\Requests\AdminStatisticsQueryInputRequest;
use App\Http\Controllers\Controller;
use App\Services\Statistics\AdminStatisticsQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminStatisticsController extends Controller
{
    public function __construct(private readonly AdminStatisticsQueryService $queryService) {}

    public function show(Request $request): JsonResponse
    {
        // Read the original URL, before TrimStrings/ConvertEmptyStringsToNull.
        // Do not accept a GET body or PHP's key normalization (from.month, etc.).
        $query = [];
        foreach (explode('&', (string) $request->server->get('QUERY_STRING', '')) as $pair) {
            if ($pair === '') { continue; }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            $value = urldecode($value);
            if (preg_match('/^(from_month|to_month)\[/', $key, $match)) {
                $query[$match[1]] = []; // Preserve invalid array type for field validation.
            } elseif (array_key_exists($key, $query)) {
                $query[$key] = []; // Ambiguous duplicate scalar is invalid too.
            } else {
                $query[$key] = $value;
            }
        }

        return ApiResponse::success($this->queryService->getStatistics(
            $request->user(), new AdminStatisticsQueryInputRequest($query),
        ));
    }
}
