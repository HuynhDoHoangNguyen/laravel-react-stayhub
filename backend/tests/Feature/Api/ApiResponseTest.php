<?php

namespace Tests\Feature\Api;

use App\Common\ApiResponse;
use App\Common\Constants;
use App\Common\PageResponse;
use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ApiResponseTest extends TestCase
{
    public function test_detail_and_created_keep_decimal_strings_and_null_without_double_wrap(): void
    {
        $data = ['id' => 7, 'total_amount' => '1234567.89', 'paid_at' => null];
        foreach ([200, 201] as $status) {
            $response = ApiResponse::success(new JsonResource($data), $status);
            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame(['data' => $data], $response->getData(true));
        }
        $this->assertSame(['data' => $data], ApiResponse::success($data)->getData(true));
    }

    public function test_pagination_and_review_summary_are_preserved(): void
    {
        $paginator = new LengthAwarePaginator([['id' => 7]], 21, 10, 2, ['path' => '/api/host/reviews']);
        $resource = JsonResource::collection($paginator)->additional(['summary' => ['total_reviews' => 21]]);
        $body = ApiResponse::success($resource)->getData(true);
        $this->assertSame([['id' => 7]], $body['data']);
        $this->assertSame(21, $body['meta']['total']);
        $this->assertSame(2, $body['meta']['current_page']);
        $this->assertSame(3, $body['meta']['last_page']);
        $this->assertStringContainsString('page=3', $body['links']['next']);
        $this->assertSame(['total_reviews' => 21], $body['summary']);
    }

    public function test_errors_only_include_fields_when_supplied_and_204_has_no_body(): void
    {
        $body = ApiResponse::error('Conflict', 'CONFLICT', 409)->getData(true);
        $this->assertSame(['message' => 'Conflict', 'code' => 'CONFLICT'], $body);
        $fields = ['rating' => ['Invalid rating']];
        $this->assertSame($fields, ApiResponse::error('Invalid', 'INVALID_REVIEW', 422, $fields)->getData(true)['errors']);
        $response = ApiResponse::noContent();
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_page_adapter_matches_legacy_success_and_preserves_links_summary_and_hooks(): void
    {
        $paginator = new LengthAwarePaginator([['id' => 7, 'total_amount' => '123.45', 'paid_at' => null]], 21, 10, 2, ['path' => '/api/host/invoices']);
        $paginator->appends(['status' => 'PAID']);
        $resource = new class($paginator) extends ResourceCollection {
            public $collects = JsonResource::class;

            public function withResponse(Request $request, JsonResponse $response): void
            {
                $response->headers->set('X-Resource-Fixture', 'preserved');
            }
        };
        $resource->additional(['summary' => ['total_reviews' => 21]]);
        $legacy = ApiResponse::success($resource);
        $page = ApiResponse::page($resource);
        $this->assertSame($legacy->getData(true), $page->getData(true));
        $this->assertSame(200, $page->getStatusCode());
        $this->assertSame('preserved', $page->headers->get('X-Resource-Fixture'));
        $body = $page->getData(true);
        $this->assertSame([['id' => 7, 'total_amount' => '123.45', 'paid_at' => null]], $body['data']);
        $this->assertSame(21, $body['meta']['total']);
        $this->assertSame(2, $body['meta']['current_page']);
        $this->assertSame(3, $body['meta']['last_page']);
        $this->assertStringContainsString('status=PAID', $body['links']['next']);
        $this->assertStringContainsString('page=3', $body['links']['next']);
        $this->assertSame(['total_reviews' => 21], $body['summary']);
        $this->assertSame(201, ApiResponse::page($resource, 201)->getStatusCode());
    }

    public function test_empty_page_has_real_pagination_metadata(): void
    {
        $resource = JsonResource::collection(new LengthAwarePaginator([], 0, 10, 1, ['path' => '/api/customer/invoices']));
        $body = PageResponse::toResponse($resource)->getData(true);
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['total']);
        $this->assertSame(1, $body['meta']['current_page']);
        $this->assertSame(1, $body['meta']['last_page']);
        $this->assertNull($body['links']['next']);
        $this->assertNull($body['links']['prev']);
    }

    public function test_non_paginated_collection_is_a_programming_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiResponse::page(JsonResource::collection(collect([['id' => 1]])));
    }

    public function test_shared_pagination_constants_keep_existing_defaults_and_clamping(): void
    {
        $this->assertSame(10, Constants::DEFAULT_PAGE_SIZE);
        $this->assertSame(1, Constants::MIN_PAGE_SIZE);
        $this->assertSame(50, Constants::MAX_PAGE_SIZE);
        $this->assertSame(10, (new InvoiceFilterRequest)->perPage);
        $this->assertSame(1, (new InvoiceFilterRequest(perPage: -2))->perPage);
        $this->assertSame(50, (new InvoiceFilterRequest(perPage: 1000))->perPage);
    }

    public function test_dto_success_uses_explicit_serialization_and_one_data_envelope(): void
    {
        $dto = new \App\DTOs\Invoice\Responses\InvoiceItemResponse(8, 7, 'SERVICE', 'Breakfast', null, '1.25', '12.34', '15.43');
        $expected = ['data' => ['id' => 8, 'invoice_id' => 7, 'item_type' => 'SERVICE', 'item_name' => 'Breakfast',
            'description' => null, 'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43']];
        foreach ([200, 201] as $status) {
            $response = ApiResponse::success($dto, $status);
            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame($expected, $response->getData(true));
        }
    }

    public function test_dto_pagination_matches_legacy_envelope_at_boundaries_and_preserves_query_links(): void
    {
        $row = ['id' => 8, 'invoice_id' => 7, 'item_type' => 'SERVICE', 'item_name' => 'Breakfast',
            'description' => null, 'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43'];
        $dto = new \App\DTOs\Invoice\Responses\InvoiceItemResponse(8, 7, 'SERVICE', 'Breakfast', null, '1.25', '12.34', '15.43');
        foreach ([[0, 1, 10, false], [21, 2, 10, true], [21, 9, 10, false], [21, 1, 1, true]] as [$total, $page, $size, $hasData]) {
            $options = ['path' => '/api/host/invoices', 'pageName' => 'page', 'fragment' => 'invoices'];
            $actualPage = new LengthAwarePaginator($hasData ? [$dto] : [], $total, $size, $page, $options);
            $legacyPage = new LengthAwarePaginator($hasData ? [$row] : [], $total, $size, $page, $options);
            $actualPage->appends(['status' => 'PAID', 'search' => 'a b']);
            $legacyPage->appends(['status' => 'PAID', 'search' => 'a b']);
            $before = $actualPage->items();
            $response = ApiResponse::page($actualPage, 201);
            $body = $response->getData(true);
            $expected = ApiResponse::page(JsonResource::collection($legacyPage), 201)->getData(true);
            $this->assertSame($expected, $body);
            $this->assertSame(201, $response->getStatusCode());
            $this->assertSame($hasData ? [$row] : [], $body['data']);
            $this->assertSame($total, $body['meta']['total']);
            $this->assertSame($page, $body['meta']['current_page']);
            $this->assertSame($size, $body['meta']['per_page']);
            $this->assertStringContainsString('status=PAID', $body['links']['first']);
            $this->assertStringContainsString('search=a%20b', $body['links']['first']);
            $this->assertStringEndsWith('#invoices', $body['links']['first']);
            $this->assertSame($before, $actualPage->items());
            $this->assertSame($body, PageResponse::toResponse($actualPage, 201)->getData(true));
        }
    }

}
