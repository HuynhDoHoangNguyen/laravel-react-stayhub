<?php

namespace App\Http\Controllers\Invoice;

use App\Common\ApiResponse;
use App\Enums\UserRole;
use App\Validators\Invoice\InvoiceValidator;
use App\Mappers\Invoice\InvoiceMapper;
use App\Http\Controllers\Controller;
use App\Exceptions\AppException;
use App\Exceptions\Invoice\DuplicateInvoiceException;
use App\Services\Invoice\InvoiceQueryService;
use App\Services\Invoice\InvoiceService;
use App\Services\Invoice\InvoicePaymentService;
use App\Validators\Invoice\InvoicePaymentValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class HostInvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected InvoiceQueryService $queryService,
        protected InvoiceMapper $mapper,
        protected InvoiceValidator $validator,
    ) {
    }

    /**
     * Tạo hóa đơn cho một đơn đặt phòng (Booking) đã hoàn thành của Host.
     *
     * @param Request $request
     * @param mixed $booking Mã ID của đơn đặt phòng từ route parameter
     * @return JsonResponse
     */
    public function store(Request $request, mixed $booking): JsonResponse
    {
        try {
            $bookingId = $this->validator->validateBookingId($booking);
            $hostActor = $request->user();
            if (!$hostActor) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $command = $this->mapper->toCreateRequest($bookingId);
            $invoice = $this->invoiceService->createInvoiceFromRequest($command, $hostActor);

            return ApiResponse::success($this->mapper->toResponse($invoice, true), 201);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (DuplicateInvoiceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->validator->authorize($request->user(), UserRole::HOST);
        $query = $this->validator->validateQuery($request->query());
        $filters = $this->mapper->toFilterRequest($query);
        $invoices = $this->queryService->getHostInvoices($request->user(), $filters);

        return ApiResponse::page($invoices);
    }

    public function bookingContext(Request $request, mixed $booking): JsonResponse
    {
        try {
            $host = $request->user();
            $this->validator->authorize($host, UserRole::HOST);
            $bookingId = $this->validator->validateBookingContextId($booking);

            return ApiResponse::success($this->queryService->getHostBookingContext($bookingId, $host));
        } catch (AppException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errorCode, $exception->status, $exception->errors);
        }
    }

    public function eligibleBookings(Request $request): JsonResponse
    {
        $query = $this->validator->validateEligibleBookingQuery($request->query());

        return ApiResponse::page($this->queryService->getHostEligibleBookings($request->user(), $query));
    }

    public function show(Request $request, mixed $invoice): JsonResponse
    {
        $this->validator->authorize($request->user(), UserRole::HOST);
        $id = $this->validator->validateInvoiceId($invoice);
        $data = $this->queryService->getHostInvoiceDetail($id, $request->user());

        return ApiResponse::success($data);
    }

    public function updatePaymentStatus(
        Request $request,
        mixed $invoice,
        InvoicePaymentValidator $paymentValidator,
        InvoicePaymentService $paymentService,
    ): JsonResponse {
        $paymentValidator->authorize($request->user());
        $id = $this->validator->validateInvoiceId($invoice);
        $command = $paymentValidator->command($id, $request->getContent());

        return ApiResponse::success($paymentService->update($command, $request->user()));
    }
}
