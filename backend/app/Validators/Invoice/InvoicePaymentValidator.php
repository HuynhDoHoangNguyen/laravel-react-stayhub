<?php

namespace App\Validators\Invoice;

use App\DTOs\Invoice\Requests\InvoicePaymentInputRequest;
use App\DTOs\Invoice\Requests\UpdateInvoicePaymentStatusRequest;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Auth\AccountStatusException;
use App\Validators\AttributeValidator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class InvoicePaymentValidator
{
    public function __construct(private readonly AttributeValidator $attributes) {}

    public function authorize(?User $actor): void
    {
        if ($actor === null) {
            throw new AuthenticationException;
        }
        if ($actor->status !== UserStatus::ACTIVE) {
            throw new AccountStatusException($actor->status);
        }
        if ($actor->role !== UserRole::HOST) {
            throw new AppException(ErrorCode::INVOICE_PAYMENT_FORBIDDEN,
                'Bạn không có quyền cập nhật trạng thái thanh toán.', 403);
        }
    }

    /** Raw body preserves JSON object/type/whitespace before Laravel input normalization. */
    public function command(int $invoiceId, string $body): UpdateInvoicePaymentStatusRequest
    {
        try {
            $payload = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON.', $exception);
        }
        if (!$payload instanceof stdClass) {
            $this->invalid(['payload' => ['Dữ liệu gửi lên phải là một đối tượng JSON.']]);
        }
        $fields = get_object_vars($payload);
        $errors = [];
        foreach (array_keys($fields) as $field) {
            if ($field !== 'payment_status') {
                $errors[$field] = ['Trường này không được phép.'];
            }
        }
        if (!array_key_exists('payment_status', $fields)) {
            $errors['payment_status'] = ['Trạng thái thanh toán là trường bắt buộc.'];
        } else {
            $errors += $this->attributes->errors(new InvoicePaymentInputRequest($fields['payment_status']));
        }
        if ($errors !== []) {
            $this->invalid($errors);
        }

        return new UpdateInvoicePaymentStatusRequest($invoiceId, $fields['payment_status']);
    }

    /** Returns true only when persistence is necessary. Facts must be read under the Invoice lock. */
    public function requiresTransition(Invoice $invoice, UpdateInvoicePaymentStatusRequest $command): bool
    {
        if ($command->paymentStatus !== 'PAID' || $command->invoiceId !== (int) $invoice->id) {
            $this->invalid(['payment_status' => ['Trạng thái thanh toán phải là PAID.']]);
        }
        if ($invoice->payment_status === 'REFUNDED') {
            throw new AppException(ErrorCode::INVOICE_PAYMENT_CONFLICT,
                'Không thể xác nhận thanh toán cho hóa đơn đã hoàn tiền.', 409);
        }
        if ($invoice->payment_status === 'UNPAID' && $invoice->paid_at === null) {
            return true;
        }
        if ($invoice->payment_status === 'PAID' && $invoice->paid_at !== null) {
            return false;
        }
        throw new AppException(ErrorCode::INVOICE_PAYMENT_INCONSISTENT,
            'Dữ liệu trạng thái thanh toán không nhất quán. Vui lòng liên hệ quản trị viên.', 409);
    }

    /** @param array<string, list<string>> $errors */
    private function invalid(array $errors): never
    {
        throw new ValidationException(validator([], []), response()->json([
            'message' => 'Dữ liệu không hợp lệ.', 'errors' => $errors,
        ], 422));
    }
}
