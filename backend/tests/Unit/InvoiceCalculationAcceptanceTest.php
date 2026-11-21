<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Invoice\InvoiceCalculationService;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class InvoiceCalculationAcceptanceTest extends TestCase
{
    private function calculator(): InvoiceCalculationService
    {
        return new InvoiceCalculationService(logger: new NullLogger());
    }

    public function test_invoice_calculation_acceptance_literal_full_oracle(): void
    {
        $services = [['service_id' => 11, 'service_name' => 'A', 'quantity' => '1.00', 'unit_price' => '12.75', 'amount' => '12.75'],
            ['service_id' => 12, 'service_name' => 'B', 'quantity' => '1.25', 'unit_price' => '10.02', 'amount' => '12.53']];
        $original = $services;
        $result = $this->calculator()->calculate(2, '100.25', $services, '10.00', '5.25');
        $this->assertSame(['room_total' => '200.50', 'service_items' => $services,
            'service_total' => '25.28', 'subtotal' => '225.78', 'discount_amount' => '10.00',
            'extra_fee' => '5.25', 'total_amount' => '221.03'], $result);
        $this->assertSame($original, $services);
    }

    public static function totals(): array
    {
        return [['225.78', '0.00', '0.00', '225.78'], ['225.78', '10.00', '5.25', '221.03'],
            ['225.78', '225.78', '0.00', '0.00'], ['225.78', '225.78', '5.25', '5.25'],
            ['9999999999.99', '0.00', '0.00', '9999999999.99'], ['0.00', '0.00', '0.00', '0.00']];
    }

    #[DataProvider('totals')]
    public function test_final_total_literal_oracles(string $subtotal, string $discount, string $fee, string $expected): void
    {
        $this->assertSame($expected, $this->calculator()->calculateTotalAmount($subtotal, $discount, $fee));
    }

    public static function products(): array
    {
        return [['1.25', '10.02', '12.53'], ['1.55', '10.55', '16.35'], ['0.01', '0.50', '0.01'],
            ['0.01', '0.49', '0.00'], ['99999999.99', '0.01', '1000000.00']];
    }

    #[DataProvider('products')]
    public function test_service_product_rounding_oracles(string $quantity, string $price, string $expected): void
    {
        $this->assertSame($expected, $this->calculator()->calculateServiceAmount($quantity, $price));
    }

    public function test_room_only_and_subtotal_oracles(): void
    {
        $service = $this->calculator();
        $this->assertSame('200.50', $service->calculateRoomTotal(2, '100.25'));
        $this->assertSame('0.00', $service->calculateServiceTotal([]));
        $this->assertSame('25.28', $service->calculateServiceTotal(['12.75', '12.53']));
        $this->assertSame('225.78', $service->calculateSubtotal('200.50', '25.28'));
    }

    public static function invalidCalculations(): array
    {
        return [['room', [0, '1.00'], InvalidArgumentException::class],
            ['room', [1, '-1.00'], InvalidArgumentException::class],
            ['room', [1, '1.001'], InvalidArgumentException::class],
            ['room', [1, '1e2'], InvalidArgumentException::class],
            ['room', [2, '5000000000.00'], OverflowException::class],
            ['service', ['0.00', '1.00'], InvalidArgumentException::class],
            ['service', ['1.001', '1.00'], InvalidArgumentException::class],
            ['service', ['100000000.00', '0.01'], OverflowException::class],
            ['service', ['99999999.99', '9999999999.99'], OverflowException::class],
            ['subtotal', ['9999999999.99', '0.01'], OverflowException::class],
            ['total', ['1.00', '1.01', '0.00'], InvalidArgumentException::class],
            ['total', ['1.00', '-0.01', '0.00'], InvalidArgumentException::class],
            ['total', ['1.00', '0.00', '-0.01'], InvalidArgumentException::class],
            ['total', ['9999999999.99', '0.00', '0.01'], OverflowException::class]];
    }

    #[DataProvider('invalidCalculations')]
    public function test_invalid_or_overflowing_calculation_is_rejected(string $operation, array $args, string $exception): void
    {
        $this->expectException($exception);
        $method = ['room' => 'calculateRoomTotal', 'service' => 'calculateServiceAmount',
            'subtotal' => 'calculateSubtotal', 'total' => 'calculateTotalAmount'][$operation];
        $this->calculator()->$method(...$args);
    }
}
