<?php

namespace App\Validators\Invoice;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use App\Models\User;
use App\Exceptions\Invoice\DuplicateInvoiceException;
use App\Services\Invoice\InvoiceCalculationService;
use Illuminate\Auth\Access\AuthorizationException;
use App\DTOs\Invoice\Requests\InvoiceCreateInputRequest;
use App\DTOs\Invoice\Requests\InvoiceIdInputRequest;
use App\DTOs\Invoice\Requests\InvoiceQueryInputRequest;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Validators\AttributeValidator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InvoiceValidator
{
    public function __construct(
        private readonly AttributeValidator $attributes,
        private readonly InvoiceCalculationService $calculationService,
    ) {}

    public function authorize(?User $actor, UserRole $expectedRole): void
    {
        if ($actor?->role !== $expectedRole) {
            throw new AppException(ErrorCode::INVOICE_READ_FORBIDDEN, 'Bạn không có quyền truy cập chức năng này.', 403);
        }
    }

    /** Preserve the existing array boundary, presence of keys and Laravel-translated messages. */
    public function validateQuery(array $query): array
    {
        $data = array_intersect_key($query, array_flip(['status', 'search', 'page', 'per_page']));
        $input = new InvoiceQueryInputRequest(...$data);
        $errors = $this->attributes->errors($input);
        foreach ($errors as $field => &$messages) {
            $messages = array_map(static fn (string $message): string => $message === 'validation.string'
                ? __('validation.string', ['attribute' => $field]) : $message, $messages);
        }
        unset($messages);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        return $data;
    }

    public function validateInvoiceId(mixed $invoiceId): int
    {
        if ($this->attributes->errors(new InvoiceIdInputRequest($invoiceId)) !== []) {
            throw new AppException(ErrorCode::INVOICE_NOT_FOUND, 'Không tìm thấy hóa đơn.', 404);
        }
        return (int) $invoiceId;
    }

    /** Available for the next create boundary migration; store() is unchanged in B.2A. */
    public function validateBookingId(mixed $bookingId): int
    {
        $this->attributes->validateDomain(new InvoiceCreateInputRequest($bookingId), 'Mã đơn đặt phòng không hợp lệ.');
        return (int) $bookingId;
    }

    /**
     * Kiểm tra quyền và trạng thái trước khi Service truy vấn hóa đơn trùng.
     *
     * @param Booking $booking Bản ghi Booking cần lập hóa đơn (kèm quan hệ customer, room.property, bookingServices)
     * @param User $hostActor Người dùng thực hiện thao tác (bắt buộc role HOST và là chủ sở hữu Property)
     *
     * @throws AuthorizationException Nếu người thực hiện không phải HOST sở hữu Property
     * @throws InvalidArgumentException Nếu trạng thái hoặc liên kết Property không hợp lệ
     */
    public function validateCreationEligibility(Booking $booking, User $hostActor): void
    {
        $this->validateHostAuthorization($booking, $hostActor);
        $this->validateBookingStatus($booking);
    }

    public function validateCreationSnapshots(Booking $booking): void
    {
        $this->validateSnapshots($booking);
        $this->validateFinancialIntegrity($booking);
    }

    /**
     * Kiểm tra quyền của Host:
     * - Chỉ người dùng có role HOST mới được tạo hóa đơn.
     * - ADMIN không được tự tạo hóa đơn trong workflow của Host.
     * - host_id của Property phải trùng khớp với id của Host actor.
     *
     * @throws AuthorizationException
     */
    public function validateHostAuthorization(Booking $booking, User $hostActor): void
    {
        $actorRole = $hostActor->role instanceof UserRole ? $hostActor->role->value : (string) $hostActor->role;

        if ($actorRole !== UserRole::HOST->value) {
            throw new AuthorizationException('Chỉ tài khoản đối tác (HOST) sở hữu cơ sở lưu trú mới có quyền tạo hóa đơn.');
        }

        $room = $this->loadedRelation($booking, 'room');
        $property = $room ? $this->loadedRelation($room, 'property') : null;
        if (!$property) {
            throw new InvalidArgumentException('Đơn đặt phòng không liên kết với cơ sở lưu trú hợp lệ.');
        }

        if ((int) $property->host_id !== (int) $hostActor->id) {
            throw new AuthorizationException('Bạn không có quyền tạo hóa đơn cho cơ sở lưu trú của đối tác khác.');
        }
    }

    /**
     * Kiểm tra trạng thái Booking: chỉ cho phép tạo hóa đơn khi Booking đã COMPLETED.
     *
     * @throws InvalidArgumentException
     */
    public function validateBookingStatus(Booking $booking): void
    {
        $statusValue = $booking->status instanceof BookingStatus ? $booking->status->value : (string) $booking->status;

        if ($statusValue !== BookingStatus::COMPLETED->value) {
            throw new InvalidArgumentException("Chỉ có thể tạo hóa đơn cho đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: '{$statusValue}'.");
        }
    }

    /**
     * Kiểm tra Booking chưa từng được lập hóa đơn.
     *
     * @throws DuplicateInvoiceException
     */
    public function validateInvoiceNotExists(int $bookingId, bool $exists): void
    {
        if ($exists) {
            throw new DuplicateInvoiceException("Đơn đặt phòng #{$bookingId} đã có hóa đơn. Không thể tạo hóa đơn thứ hai.");
        }
    }

    /**
     * Kiểm tra tính đầy đủ của các trường snapshot bắt buộc theo schema hóa đơn.
     * Đặc biệt customer_phone là NOT NULL trong schema bảng invoices.
     *
     * @throws InvalidArgumentException
     */
    public function validateSnapshots(Booking $booking): void
    {
        $customer = $this->loadedRelation($booking, 'customer');
        if (!$customer) {
            throw new InvalidArgumentException('Đơn đặt phòng không tìm thấy thông tin khách hàng (customer) liên kết.');
        }

        if (trim((string) $customer->name) === '') {
            throw new InvalidArgumentException('Thông tin khách hàng thiếu họ tên snapshot bắt buộc.');
        }

        if (trim((string) $customer->email) === '') {
            throw new InvalidArgumentException('Thông tin khách hàng thiếu email snapshot bắt buộc.');
        }

        if (trim((string) $customer->phone) === '') {
            throw new InvalidArgumentException('Thông tin khách hàng thiếu số điện thoại snapshot bắt buộc (customer_phone không thể để trống).');
        }

        $room = $this->loadedRelation($booking, 'room');
        if (!$room) {
            throw new InvalidArgumentException('Đơn đặt phòng không tìm thấy thông tin phòng (room) liên kết.');
        }

        if (trim((string) $room->name) === '') {
            throw new InvalidArgumentException('Thông tin phòng thiếu tên phòng snapshot bắt buộc.');
        }

        $property = $this->loadedRelation($room, 'property');
        if (!$property || trim((string) $property->name) === '') {
            throw new InvalidArgumentException('Thông tin cơ sở lưu trú thiếu tên cơ sở snapshot bắt buộc.');
        }
    }

    /**
     * Kiểm tra tính toàn vẹn tài chính giữa snapshot đã lưu trong Booking và phép tính chính xác từ CalculationService.
     *
     * @throws InvalidArgumentException
     */
    public function validateFinancialIntegrity(Booking $booking): void
    {
        $nights = (int) $booking->number_of_nights;
        if ($nights <= 0) {
            throw new InvalidArgumentException("Số đêm lưu trú của Booking phải lớn hơn 0 (nhận được: {$nights}).");
        }

        $expectedRoomTotal = $this->calculationService->calculateRoomTotal($nights, (string) $booking->price_per_night);
        if (bccomp($expectedRoomTotal, (string) $booking->room_total, 2) !== 0) {
            throw new InvalidArgumentException("Dữ liệu room_total của Booking ({$booking->room_total}) không khớp với phép tính snapshot ({$nights} đêm × {$booking->price_per_night} = {$expectedRoomTotal}).");
        }

        foreach ($this->loadedRelation($booking, 'bookingServices') as $index => $bs) {
            if (bccomp((string) $bs->quantity, '0.00', 2) <= 0) {
                throw new InvalidArgumentException("Dịch vụ tại dòng #{$index} ({$bs->service_name}) có số lượng không hợp lệ.");
            }

            $expectedServiceAmount = $this->calculationService->calculateServiceAmount((string) $bs->quantity, (string) $bs->unit_price);
            if (bccomp($expectedServiceAmount, (string) $bs->amount, 2) !== 0) {
                throw new InvalidArgumentException("Thành tiền của dịch vụ '{$bs->service_name}' ({$bs->amount}) không khớp với phép tính snapshot ({$bs->quantity} × {$bs->unit_price} = {$expectedServiceAmount}).");
            }
        }
    }

    /** Validate the candidate without mutating attributes or triggering persistence. */
    public function validateInvoiceModel(Invoice $invoice): void
    {
        $this->attributes->validateDomain($invoice, 'Dữ liệu hóa đơn không hợp lệ.', ['invoice-create']);
    }

    /** ROOM only: SERVICE names retain the existing snapshot contract. */
    public function validateRoomItemModel(InvoiceItem $item): void
    {
        $this->attributes->validateDomain($item, 'Dữ liệu dòng hóa đơn không hợp lệ.', ['invoice-create-room']);
    }

    private function loadedRelation(Model $model, string $relation): mixed
    {
        if (!$model->relationLoaded($relation)) {
            throw new LogicException("Invoice validation requires eager-loaded relation: {$relation}.");
        }
        return $model->getRelation($relation);
    }
}
