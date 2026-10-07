<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\Carbon;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'customer_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'guest_count',
        'number_of_nights',
        'price_per_night',
        'room_total',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date:Y-m-d',
            'check_out_date' => 'date:Y-m-d',
            'guest_count' => 'integer',
            'number_of_nights' => 'integer',
            'price_per_night' => 'decimal:2',
            'room_total' => 'decimal:2',
            'status' => BookingStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    // TODO: Member 3 - invoice(), review()

    public function scopeHoldingRoom(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::holdingValues());
    }

    public function scopeOverlapping(Builder $query, mixed $checkInDate, mixed $checkOutDate): Builder
    {
        $in = $checkInDate instanceof \DateTimeInterface
            ? $checkInDate->format('Y-m-d')
            : Carbon::parse($checkInDate)->format('Y-m-d');

        $out = $checkOutDate instanceof \DateTimeInterface
            ? $checkOutDate->format('Y-m-d')
            : Carbon::parse($checkOutDate)->format('Y-m-d');

        return $query->where('check_in_date', '<', $out)
            ->where('check_out_date', '>', $in);
    }

    public function scopeForCustomer(Builder $query, mixed $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForHost(Builder $query, mixed $hostId): Builder
    {
        return $query->whereHas('room.property', function (Builder $q) use ($hostId) {
            $q->where('host_id', $hostId);
        });
    }
}
