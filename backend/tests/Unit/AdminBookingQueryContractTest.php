<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Booking\Requests\AdminBookingQueryRequest;
use App\Mappers\Booking\AdminBookingMapper;
use App\Mappers\EloquentObjectMapper;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class AdminBookingQueryContractTest extends TestCase
{
    private function mapper(): AdminBookingMapper
    {
        return new AdminBookingMapper(new EloquentObjectMapper(new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor())));
    }

    private function booking(): Booking
    {
        $booking = new Booking();
        $booking->setDateFormat('Y-m-d H:i:s');
        $booking->setRawAttributes([
            'id' => '101', 'booking_code' => 'BK-UC44-A', 'customer_id' => '31', 'room_id' => '71',
            'check_in_date' => '2026-11-21', 'check_out_date' => '2026-11-23', 'guest_count' => '2',
            'number_of_nights' => '2', 'price_per_night' => '0.10', 'room_total' => '777.77',
            'status' => 'PENDING', 'created_at' => '2026-11-20 17:30:00', 'note' => 'private',
        ]);
        $customer = (new User())->setRawAttributes(['id' => 31, 'name' => '0', 'email' => 'private@example.test']);
        $room = (new Room())->setRawAttributes(['id' => 71, 'property_id' => '51', 'name' => '', 'room_number' => '0']);
        $property = (new Property())->setRawAttributes(['id' => 51, 'host_id' => '21', 'name' => '   ']);
        $host = (new User())->setRawAttributes(['id' => 21, 'name' => 'Host A']);
        $property->setRelation('host', $host);
        $room->setRelation('property', $property);

        return $booking->setRelation('customer', $customer)->setRelation('room', $room);
    }

    public function test_defaults_limits_normalized_filters_and_date_boundaries(): void
    {
        $this->assertSame(['page' => 1, 'per_page' => 10], (new AdminBookingQueryRequest())->toQuery());
        $query = new AdminBookingQueryRequest(hostId: AdminBookingQueryRequest::MAX_ID, propertyId: 51,
            customerId: 31, status: 'COMPLETED', checkInFrom: '2024-02-29', checkInTo: '2024-02-29',
            search: '0', page: AdminBookingQueryRequest::MAX_PAGE, perPage: 50);
        $this->assertSame(['host_id' => 9007199254740991, 'property_id' => 51, 'customer_id' => 31,
            'status' => 'COMPLETED', 'check_in_from' => '2024-02-29', 'check_in_to' => '2024-02-29',
            'search' => '0', 'page' => 2147483647, 'per_page' => 50], $query->toQuery());
        $this->assertSame('1000-01-01', (new AdminBookingQueryRequest(checkInFrom: '1000-01-01'))->checkInFrom);
        $this->assertSame('9999-12-31', (new AdminBookingQueryRequest(checkInTo: '9999-12-31'))->checkInTo);
        $this->assertSame(255, mb_strlen((new AdminBookingQueryRequest(search: str_repeat('ế', 255)))->search));
    }

    public static function invalidQueries(): array
    {
        return [
            'host zero' => [['hostId' => 0]], 'host overflow' => [['hostId' => 9007199254740992]],
            'property negative' => [['propertyId' => -1]], 'customer zero' => [['customerId' => 0]],
            'page zero' => [['page' => 0]], 'page overflow' => [['page' => 2147483648]],
            'size zero' => [['perPage' => 0]], 'size overflow' => [['perPage' => 51]],
            'ALL' => [['status' => 'ALL']], 'lowercase' => [['status' => 'pending']],
            'blank status' => [['status' => '']], 'empty search' => [['search' => '']],
            'untrimmed' => [['search' => ' a ']], 'long unicode' => [['search' => str_repeat('ế', 256)]],
            'invalid calendar' => [['checkInFrom' => '2026-02-30']],
            'nonleap' => [['checkInTo' => '2026-02-29']], 'early year' => [['checkInFrom' => '0999-12-31']],
            'short date' => [['checkInTo' => '2026-1-01']], 'empty date' => [['checkInFrom' => '']],
            'timestamp date' => [['checkInTo' => '2026-11-21T00:00:00Z']],
            'reverse' => [['checkInFrom' => '2026-11-22', 'checkInTo' => '2026-11-21']],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_typed_query_rejects_invalid_invariants(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AdminBookingQueryRequest(...$arguments);
    }

    public function test_exact_projection_preserves_money_null_text_and_dates_without_queries(): void
    {
        $previous = Model::getConnectionResolver();
        $resolver = $this->createMock(ConnectionResolverInterface::class);
        $resolver->expects($this->never())->method('connection');
        Model::setConnectionResolver($resolver);
        try {
            $data = $this->mapper()->toListItem($this->booking())->jsonSerialize();
        } finally {
            $previous === null ? Model::unsetConnectionResolver() : Model::setConnectionResolver($previous);
        }
        $this->assertSame([
            'id' => 101, 'booking_code' => 'BK-UC44-A', 'customer_id' => 31, 'customer_name' => '0',
            'room_id' => 71, 'room_name' => '', 'room_number' => '0', 'property_id' => 51,
            'property_name' => '   ', 'host_id' => 21, 'host_name' => 'Host A',
            'check_in_date' => '2026-11-21', 'check_out_date' => '2026-11-23',
            'guest_count' => 2, 'number_of_nights' => 2, 'price_per_night' => '0.10', 'room_total' => '777.77',
            'status' => 'PENDING', 'created_at' => '2026-11-20T17:30:00.000000Z',
        ], $data);
        $this->assertCount(19, $data);
    }

    public function test_loaded_null_relations_keep_known_foreign_keys_and_all_fields(): void
    {
        $booking = $this->booking();
        $booking->setRelation('customer', null);
        $booking->room->property->setRelation('host', null);
        $data = $this->mapper()->toListItem($booking)->jsonSerialize();
        $this->assertNull($data['customer_name']);
        $this->assertSame(31, $data['customer_id']);
        $this->assertNull($data['host_name']);
        $this->assertSame(21, $data['host_id']);
        $booking->room->setRelation('property', null);
        $data = $this->mapper()->toListItem($booking)->jsonSerialize();
        $this->assertSame(51, $data['property_id']);
        $this->assertNull($data['property_name']);
        $this->assertNull($data['host_id']);
        $booking->setRelation('room', null);
        $booking->setRawAttributes(array_replace($booking->getAttributes(), ['created_at' => null]));
        $data = $this->mapper()->toListItem($booking)->jsonSerialize();
        $this->assertSame(71, $data['room_id']);
        foreach (['room_name', 'room_number', 'property_id', 'property_name', 'host_id', 'host_name', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $data);
            $this->assertNull($data[$field]);
        }
    }

    public static function malformedFields(): array
    {
        return [
            ['id', '9007199254740992'], ['id', 0], ['customer_id', 1.5], ['room_id', 'abc'],
            ['booking_code', null], ['guest_count', '1.5'], ['number_of_nights', 'x'],
            ['price_per_night', 0.1], ['price_per_night', '-0.10'], ['price_per_night', '0.001'],
            ['room_total', '10000000000.00'], ['room_total', null], ['status', 'UNKNOWN'],
            ['check_in_date', '2026-02-30'], ['check_out_date', '2026-01-01T00:00:00Z'],
            ['created_at', 'nonsense'], ['created_at', '2026-02-30 00:00:00'],
            ['created_at', '2026-11-21 24:00:00'],
        ];
    }

    #[DataProvider('malformedFields')]
    public function test_malformed_projection_is_rejected_before_cast_or_fake_success(string $field, mixed $value): void
    {
        $booking = $this->booking();
        $booking->setRawAttributes(array_replace($booking->getAttributes(), [$field => $value]));
        $this->expectException(LogicException::class);
        $this->mapper()->toListItem($booking);
    }

    public static function unloadedRelations(): array
    {
        return [['customer'], ['room'], ['property'], ['host']];
    }

    #[DataProvider('unloadedRelations')]
    public function test_unloaded_relations_are_rejected_instead_of_lazy_loading(string $relation): void
    {
        $booking = $this->booking();
        match ($relation) {
            'property' => $booking->room->unsetRelation('property'),
            'host' => $booking->room->property->unsetRelation('host'),
            default => $booking->unsetRelation($relation),
        };
        $this->expectException(LogicException::class);
        $this->mapper()->toListItem($booking);
    }

    public function test_maximum_identifiers_and_money_are_preserved(): void
    {
        $booking = $this->booking();
        $booking->setRawAttributes(array_replace($booking->getAttributes(), [
            'id' => '9007199254740991', 'price_per_night' => '9999999999.99', 'room_total' => '0.00',
        ]));
        $data = $this->mapper()->toListItem($booking)->jsonSerialize();
        $this->assertSame(9007199254740991, $data['id']);
        $this->assertSame('9999999999.99', $data['price_per_night']);
        $this->assertSame('0.00', $data['room_total']);
    }
}
