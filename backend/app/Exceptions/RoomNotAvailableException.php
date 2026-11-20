<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoomNotAvailableException extends Exception
{
    protected array $reasons;

    public function __construct(string $message = 'Phòng không khả dụng trong thời gian yêu cầu.', array $reasons = [])
    {
        parent::__construct($message);
        $this->reasons = $reasons;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => [
                'reasons' => $this->reasons,
            ],
        ], Response::HTTP_CONFLICT);
    }
}
