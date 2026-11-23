<?php

namespace App\Services\Invoice;

use App\Validators\Invoice\InvoiceCalculationValidator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class InvoiceCalculationService
{
    /**
     * Tham chiếu giới hạn tối đa DECIMAL(12,2) từ Validator.
     */
    public const MAX_MONEY = InvoiceCalculationValidator::MAX_MONEY;

    /**
     * Tham chiếu giới hạn tối đa DECIMAL(10,2) cho quantity từ Validator.
     */
    public const MAX_QUANTITY = InvoiceCalculationValidator::MAX_QUANTITY;

    /**
     * Validator phụ trách kiểm tra và chuẩn hóa dữ liệu tính toán hóa đơn.
     */
    protected InvoiceCalculationValidator $validator;

    /**
     * Logger (tùy chọn) phục vụ ghi log thực thi.
     */
    protected ?LoggerInterface $logger;

    public function __construct(
        ?InvoiceCalculationValidator $validator = null,
        ?LoggerInterface $logger = null
    ) {
        $this->validator = $validator ?? new InvoiceCalculationValidator();
        $this->logger = $logger;
    }

    /**
     * Lấy đối tượng Validator đang sử dụng.
     */
    public function getValidator(): InvoiceCalculationValidator
    {
        return $this->validator;
    }

    /**
     * Lấy đối tượng Logger đang sử dụng (nếu có).
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Ghi log thực thi an toàn (hỗ trợ cả môi trường Laravel và Unit Test độc lập).
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        } elseif (class_exists(Log::class) && Facade::getFacadeApplication() !== null) {
            Log::info($message, $context);
        }
    }

    /**
     * Tính tiền phòng = number_of_nights × price_per_night
     *
     * @param int $numberOfNights Số đêm lưu trú (> 0)
     * @param string|int $pricePerNight Snapshot giá phòng mỗi đêm
     * @return string Kết quả tiền phòng định dạng 2 chữ số thập phân
     */
    public function calculateRoomTotal(int $numberOfNights, string|int $pricePerNight): string
    {
        $this->validator->validateNights($numberOfNights);
        $cleanPrice = $this->validator->validateMoney($pricePerNight, 'price_per_night');

        // Tính toán với scale 4 để giữ độ chính xác trước khi làm tròn
        $rawProduct = bcmul((string) $numberOfNights, $cleanPrice, 4);
        $result = $this->roundHalfUp($rawProduct, 2);

        $this->validator->assertWithinMoneyLimit($result, 'room_total');

        return $result;
    }

    /**
     * Tính tiền một dòng dịch vụ = quantity × unit_price
     *
     * @param string|int $quantity Số lượng dịch vụ (> 0, tối đa 2 chữ số thập phân)
     * @param string|int $unitPrice Snapshot đơn giá dịch vụ (>= 0, tối đa 2 chữ số thập phân)
     * @return string Thành tiền dịch vụ định dạng 2 chữ số thập phân
     */
    public function calculateServiceAmount(string|int $quantity, string|int $unitPrice): string
    {
        $cleanQty = $this->validator->validateQuantity($quantity);
        $cleanUnitPrice = $this->validator->validateMoney($unitPrice, 'unit_price');

        // Nhân số lượng và đơn giá với scale 4 để giữ chữ số có nghĩa
        $rawProduct = bcmul($cleanQty, $cleanUnitPrice, 4);
        $result = $this->roundHalfUp($rawProduct, 2);

        $this->validator->assertWithinMoneyLimit($result, 'service_amount');

        return $result;
    }

    /**
     * Tính tổng tiền danh sách dịch vụ.
     * Hỗ trợ mảng các dòng dịch vụ (có quantity & unit_price, hoặc amount) hoặc mảng chuỗi số tiền.
     *
     * @param array $services Danh sách dịch vụ
     * @return string Tổng tiền dịch vụ
     */
    public function calculateServiceTotal(array $services): string
    {
        if (empty($services)) {
            return '0.00';
        }

        $total = '0.00';
        foreach ($services as $index => $item) {
            $amount = '0.00';

            if (is_array($item)) {
                if (isset($item['quantity'], $item['unit_price'])) {
                    $amount = $this->calculateServiceAmount($item['quantity'], $item['unit_price']);
                } elseif (isset($item['amount'])) {
                    $amount = $this->validator->validateMoney($item['amount'], "service.{$index}.amount");
                } else {
                    throw new InvalidArgumentException("Dòng dịch vụ tại vị trí {$index} phải chứa 'quantity' và 'unit_price' hoặc 'amount'.");
                }
            } elseif (is_string($item) || is_int($item)) {
                $amount = $this->validator->validateMoney($item, "service.{$index}");
            } else {
                throw new InvalidArgumentException("Dữ liệu dịch vụ tại vị trí {$index} không hợp lệ.");
            }

            $total = bcadd($total, $amount, 2);
            $this->validator->assertWithinMoneyLimit($total, 'service_total');
        }

        return $total;
    }

    /**
     * Tính tạm tính (subtotal) = room_total + service_total
     *
     * @param string|int $roomTotal Tổng tiền phòng
     * @param string|int $serviceTotal Tổng tiền dịch vụ
     * @return string Tạm tính
     */
    public function calculateSubtotal(string|int $roomTotal, string|int $serviceTotal): string
    {
        $cleanRoom = $this->validator->validateMoney($roomTotal, 'room_total');
        $cleanService = $this->validator->validateMoney($serviceTotal, 'service_total');

        $subtotal = bcadd($cleanRoom, $cleanService, 2);
        $this->validator->assertWithinMoneyLimit($subtotal, 'subtotal');

        return $subtotal;
    }

    /**
     * Tính tổng tiền cuối cùng = subtotal - discount_amount + extra_fee
     *
     * @param string|int $subtotal Tạm tính
     * @param string|int $discountAmount Số tiền giảm giá (mặc định '0.00', không vượt quá subtotal)
     * @param string|int $extraFee Phụ phí khác (mặc định '0.00')
     * @return string Tổng thanh toán cuối cùng
     */
    public function calculateTotalAmount(
        string|int $subtotal,
        string|int $discountAmount = '0.00',
        string|int $extraFee = '0.00'
    ): string {
        $cleanSubtotal = $this->validator->validateMoney($subtotal, 'subtotal');
        $cleanDiscount = $this->validator->validateMoney($discountAmount, 'discount_amount');
        $cleanExtraFee = $this->validator->validateMoney($extraFee, 'extra_fee');

        // Kiểm tra discount không được lớn hơn subtotal
        $this->validator->assertDiscountWithinSubtotal($cleanDiscount, $cleanSubtotal);

        $afterDiscount = bcsub($cleanSubtotal, $cleanDiscount, 2);
        $total = bcadd($afterDiscount, $cleanExtraFee, 2);

        $this->validator->assertWithinMoneyLimit($total, 'total_amount');

        return $total;
    }

    /**
     * Tính toán toàn diện hóa đơn từ dữ liệu snapshot thuần túy.
     *
     * @param int $numberOfNights Số đêm lưu trú (> 0)
     * @param string|int $pricePerNight Giá phòng snapshot mỗi đêm
     * @param array $services Danh sách dịch vụ snapshot: mảng các ['service_id' => ..., 'name' => ..., 'quantity' => ..., 'unit_price' => ...]
     * @param string|int $discountAmount Số tiền giảm giá
     * @param string|int $extraFee Phụ phí
     * @return array Kết quả chi tiết các phép tính
     */
    public function calculate(
        int $numberOfNights,
        string|int $pricePerNight,
        array $services = [],
        string|int $discountAmount = '0.00',
        string|int $extraFee = '0.00'
    ): array {
        $this->logInfo('InvoiceCalculationService: Bắt đầu tính toán hóa đơn', [
            'nights' => $numberOfNights,
            'price_per_night' => (string) $pricePerNight,
            'services_count' => count($services),
            'discount_amount' => (string) $discountAmount,
            'extra_fee' => (string) $extraFee,
        ]);

        $roomTotal = $this->calculateRoomTotal($numberOfNights, $pricePerNight);

        $calculatedServices = [];
        $serviceAmounts = [];

        foreach ($services as $index => $svc) {
            if (!is_array($svc) || !isset($svc['quantity'], $svc['unit_price'])) {
                throw new InvalidArgumentException("Dòng dịch vụ tại vị trí {$index} phải có 'quantity' và 'unit_price'.");
            }

            $amount = $this->calculateServiceAmount($svc['quantity'], $svc['unit_price']);
            $serviceAmounts[] = $amount;

            $item = $svc;
            $item['quantity'] = $this->validator->validateQuantity($svc['quantity']);
            $item['unit_price'] = $this->validator->validateMoney($svc['unit_price'], "services.{$index}.unit_price");
            $item['amount'] = $amount;
            $calculatedServices[] = $item;
        }

        $serviceTotal = $this->calculateServiceTotal($serviceAmounts);
        $subtotal = $this->calculateSubtotal($roomTotal, $serviceTotal);

        $cleanDiscount = $this->validator->validateMoney($discountAmount, 'discount_amount');
        $cleanExtraFee = $this->validator->validateMoney($extraFee, 'extra_fee');
        $totalAmount = $this->calculateTotalAmount($subtotal, $cleanDiscount, $cleanExtraFee);

        $this->logInfo('InvoiceCalculationService: Hoàn tất tính toán hóa đơn', [
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
        ]);

        return [
            'room_total' => $roomTotal,
            'service_items' => $calculatedServices,
            'service_total' => $serviceTotal,
            'subtotal' => $subtotal,
            'discount_amount' => $cleanDiscount,
            'extra_fee' => $cleanExtraFee,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Làm tròn nửa lên (ROUND_HALF_UP) bằng chuỗi và BCMath thông qua Validator.
     */
    public function roundHalfUp(string $val, int $scale = 2): string
    {
        return $this->validator->roundHalfUp($val, $scale);
    }
}
