<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServiceNotAllowedException extends Exception
{
    public function __construct(string $message = 'Thao tác dịch vụ không được phép.')
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
