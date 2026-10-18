<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Services\Invoice\DuplicateInvoiceException;
use App\Services\Invoice\InvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class HostInvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
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
        // 1. Kiểm tra tính hợp lệ của Booking ID
        if (!is_numeric($booking) || (int) $booking <= 0 || (string) (int) $booking !== (string) $booking) {
            return response()->json([
                'message' => 'Mã đơn đặt phòng không hợp lệ.',
            ], 422);
        }

        $bookingId = (int) $booking;

        // 2. Lấy Host actor hiện tại
        $hostActor = $request->user();
        if (!$hostActor) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 3. Gọi InvoiceService và ánh xạ các ngoại lệ nghiệp vụ cụ thể
        try {
            $invoice = $this->invoiceService->createInvoice($bookingId, $hostActor);

            return (new InvoiceResource($invoice))
                ->response()
                ->setStatusCode(201);
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
}
