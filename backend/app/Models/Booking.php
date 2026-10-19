<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bookings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'guest_count' => 'integer',
            'number_of_nights' => 'integer',
            'price_per_night' => 'decimal:2',
            'room_total' => 'decimal:2',
            'status' => BookingStatus::class,
        ];
    }

    /**
     * Get the customer that placed the booking.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Alias for customer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the room booked.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /**
     * Get the services associated with the booking.
     */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class, 'booking_id');
    }

    /**
     * Get the invoice for the booking.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'booking_id');
    }

    /**
     * Get the review for the booking.
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'booking_id');
    }
}
