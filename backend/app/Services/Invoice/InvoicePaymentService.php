<?php

namespace App\Services\Invoice;

use App\DTOs\Invoice\Requests\UpdateInvoicePaymentStatusRequest;
use App\DTOs\Invoice\Responses\InvoiceResponse;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepository;
use App\Validators\Invoice\InvoicePaymentValidator;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

final class InvoicePaymentService
{
    public function __construct(
        private readonly InvoiceRepository $repository,
        private readonly InvoicePaymentValidator $validator,
        private readonly InvoiceMapper $mapper,
        private readonly LoggerInterface $logger,
    ) {}

    public function update(UpdateInvoicePaymentStatusRequest $command, User $actor): InvoiceResponse
    {
        $this->validator->authorize($actor);

        return DB::transaction(function () use ($command, $actor) {
            $invoice = $this->repository->findForHostWithPaymentLock($command->invoiceId, (int) $actor->id);
            if ($invoice === null) {
                throw new AppException(ErrorCode::INVOICE_NOT_FOUND, 'Không tìm thấy hóa đơn.', 404);
            }
            $changed = $this->validator->requiresTransition($invoice, $command);
            if ($changed) {
                $this->repository->markPaid($invoice);
            }
            $response = $this->mapper->toResponse($this->repository->loadInvoiceItems($invoice), true);
            // Check serialization while rollback is still possible, including nested items.
            json_encode($response, JSON_THROW_ON_ERROR);
            if ($changed) {
                DB::afterCommit(function () use ($invoice): void {
                    $this->logger->info('Xác nhận thanh toán hóa đơn thành công', [
                        'invoice_id' => $invoice->id, 'payment_status' => 'PAID',
                    ]);
                });
            }

            return $response;
        });
    }
}
