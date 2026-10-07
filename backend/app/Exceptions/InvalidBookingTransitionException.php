<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidBookingTransitionException extends Exception
{
    protected ?BookingStatus $from;
    protected ?BookingStatus $to;

    public function __construct(mixed $from = null, mixed $to = null, ?string $customMessage = null)
    {
        $fromEnum = $from instanceof BookingStatus ? $from : ($from ? BookingStatus::tryFrom((string) $from) : null);
        $toEnum = $to instanceof BookingStatus ? $to : ($to ? BookingStatus::tryFrom((string) $to) : null);

        $this->from = $fromEnum;
        $this->to = $toEnum;

        $msg = $customMessage ?? sprintf(
            'Không thể chuyển trạng thái đặt phòng từ %s sang %s.',
            $fromEnum ? $fromEnum->label() : ($from ?? 'không xác định'),
            $toEnum ? $toEnum->label() : ($to ?? 'không xác định')
        );

        parent::__construct($msg);
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
