<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\DTOs\Invoice\Requests\CreateInvoiceRequest;
use App\Mappers\Invoice\InvoiceMapper;
use App\Repositories\Invoice\InvoiceRepository;
use App\Models\User;
use App\Exceptions\Invoice\DuplicateInvoiceException;
use App\Validators\Invoice\InvoiceValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class InvoiceService
{
    public function __construct(
        protected readonly InvoiceRepository $repository,
        protected readonly InvoiceValidator $validator,
        protected readonly InvoiceCalculationService $calculationService,
        protected readonly InvoiceMapper $mapper,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * Lấy đối tượng Validator đang sử dụng.
     */
    public function getValidator(): InvoiceValidator
    {
        return $this->validator;
    }

    /**
     * Lấy đối tượng CalculationService đang sử dụng.
     */
    public function getCalculationService(): InvoiceCalculationService
    {
        return $this->calculationService;
    }

    /**
     * Tạo hóa đơn và các dòng InvoiceItems atomically cho một Booking đã hoàn tất (COMPLETED).
     *
     * @param int $bookingId ID của Booking cần lập hóa đơn
     * @param User $hostActor Người dùng thực hiện (bắt buộc role HOST sở hữu cơ sở lưu trú)
     * @return Invoice Hóa đơn đã được tạo kèm quan hệ items
     *
     * @throws ModelNotFoundException Nếu không tìm thấy Booking
     * @throws \Illuminate\Auth\Access\AuthorizationException Nếu Host không đủ quyền
     * @throws DuplicateInvoiceException Nếu Booking đã có hóa đơn
     * @throws \InvalidArgumentException Nếu Booking chưa hoàn tất hoặc dữ liệu snapshot không hợp lệ
     * @throws QueryException Nếu phát sinh lỗi cơ sở dữ liệu khác
     */
    public function createInvoice(int $bookingId, User $hostActor): Invoice
    {
        return $this->createInvoiceFromRequest($this->mapper->toCreateRequest($bookingId), $hostActor);
    }

    /** Typed application entry point; HTTP normalization remains at the existing boundary. */
    public function createInvoiceFromRequest(CreateInvoiceRequest $request, User $hostActor): Invoice
    {
        return DB::transaction(function () use ($request, $hostActor) {
            $bookingId = $request->bookingId;
            // 1. Khóa bản ghi Booking bằng lockForUpdate() chống concurrent requests
            $booking = $this->repository->findBookingForInvoiceWithLock($bookingId);

            if (!$booking) {
                throw new ModelNotFoundException("Không tìm thấy đơn đặt phòng với ID: {$bookingId}");
            }

            // 2. Xác thực toàn bộ điều kiện nghiệp vụ và phân quyền
            $this->validator->validateCreationEligibility($booking, $hostActor);
            $this->validator->validateInvoiceNotExists(
                $bookingId,
                $this->repository->invoiceExistsForBooking($bookingId),
            );
            $this->validator->validateCreationSnapshots($booking);

            // 3. Kiểm tra lại sự tồn tại của Invoice ngay trong phạm vi transaction được khóa
            if ($this->repository->invoiceExistsForBooking($bookingId)) {
                throw new DuplicateInvoiceException("Đơn đặt phòng #{$bookingId} đã có hóa đơn.");
            }

            // 4. Chuẩn bị danh sách dịch vụ snapshot
            $servicesPayload = $this->mapper->toServiceSnapshots($booking);
            $discountAmount = '0.00';
            $extraFee = '0.00';

            // 5. Tính toán tài chính bằng InvoiceCalculationService thuần BCMath
            $calcResult = $this->calculationService->calculate(
                (int) $booking->number_of_nights,
                (string) $booking->price_per_night,
                $servicesPayload,
                $discountAmount,
                $extraFee
            );

            // 6. Sinh mã hóa đơn duy nhất (tối đa 50 ký tự)
            $invoiceCode = $this->generateUniqueInvoiceCode();

            // 7. Tạo bản ghi Invoice và InvoiceItems atomically
            try {
                $invoiceAttributes = $this->mapper->toInvoiceAttributes(
                    $booking, $invoiceCode, $calcResult, $discountAmount, $extraFee, 'UNPAID', null,
                );
                $this->validator->validateInvoiceModel(new Invoice($invoiceAttributes));
                $invoice = $this->repository->createInvoice($invoiceAttributes);

                $roomAttributes = $this->mapper->toRoomItemAttributes($booking, $calcResult);
                $this->validator->validateRoomItemModel(new InvoiceItem($roomAttributes));
                $this->repository->createInvoiceItem($invoice, $roomAttributes);
                foreach ($calcResult['service_items'] as $svcItem) {
                    $this->repository->createInvoiceItem($invoice, $this->mapper->toServiceItemAttributes($svcItem));
                }
            } catch (QueryException $e) {
                $this->handleQueryException($e, $booking->id);
            }

            // 8. Đăng ký log thực thi an toàn SAU KHI transaction đã commit thành công
            DB::afterCommit(function () use ($invoice) {
                $this->logInfo('Tạo hóa đơn thành công', [
                    'invoice_id' => $invoice->id,
                    'invoice_code' => $invoice->invoice_code,
                    'booking_id' => $invoice->booking_id,
                    'total_amount' => $invoice->total_amount,
                ]);
            });

            return $this->repository->loadInvoiceItems($invoice);
        });
    }

    /**
     * Sinh mã hóa đơn duy nhất có định dạng: INV-YYYYMMDD-XXXXXXXX (21 ký tự <= 50 ký tự)
     */
    public function generateUniqueInvoiceCode(): string
    {
        $maxAttempts = 5;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $randomPart = strtoupper(bin2hex(random_bytes(4)));
            $code = 'INV-' . date('Ymd') . '-' . $randomPart;

            if (!$this->repository->invoiceCodeExists($code)) {
                return $code;
            }
        }

        // Dự phòng với microtime nếu có va chạm liên tiếp
        return 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Phân biệt chính xác vi phạm UNIQUE constraint giữa booking_id và invoice_code.
     * Không chuyển mọi QueryException thành lỗi duplicate booking.
     *
     * @throws DuplicateInvoiceException
     * @throws QueryException
     * @throws \RuntimeException
     */
    protected function handleQueryException(QueryException $e, int $bookingId): void
    {
        // Lấy thông điệp lỗi thuần từ driver (không bao gồm câu lệnh SQL đính kèm của Laravel)
        $driverMessage = $e->errorInfo[2] ?? $e->getMessage();
        $pureMessage = explode('(Connection:', $driverMessage)[0];
        $pureMessage = explode('(SQL:', $pureMessage)[0];

        // Kiểm tra dấu hiệu vi phạm UNIQUE constraint (SQLSTATE 23000 hoặc mã lỗi 1062 MySQL / 19 SQLite)
        $isUniqueViolation = str_contains($driverMessage, 'Duplicate entry')
            || str_contains($driverMessage, 'UNIQUE constraint failed')
            || $e->getCode() === '23000'
            || (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [1062, 19], true));

        if ($isUniqueViolation) {
            // Trùng lặp mã hóa đơn (invoice_code)
            if (str_contains($pureMessage, 'invoice_code') || str_contains($pureMessage, 'invoices_invoice_code_unique')) {
                throw new \RuntimeException("Trùng mã hóa đơn khi lưu vào cơ sở dữ liệu. Vui lòng thử lại.", 0, $e);
            }

            // Trùng lặp đơn đặt phòng (booking_id)
            if (str_contains($pureMessage, 'booking_id') || str_contains($pureMessage, 'invoices_booking_id_unique')) {
                throw new DuplicateInvoiceException("Đơn đặt phòng #{$bookingId} đã có hóa đơn trong hệ thống (vi phạm ràng buộc duy nhất).", 0, $e);
            }
        }

        // Ném lại các lỗi database khác mà không nuốt hay chuyển đổi sai lệch
        throw $e;
    }

    /**
     * Ghi log thực thi an toàn, không chứa thông tin nhạy cảm.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
}
