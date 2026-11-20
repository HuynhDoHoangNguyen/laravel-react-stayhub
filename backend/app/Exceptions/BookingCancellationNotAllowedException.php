<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BookingCancellationNotAllowedException extends Exception
{
    public function __construct(string $message = 'Không thể hủy đặt phòng này.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => [],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
