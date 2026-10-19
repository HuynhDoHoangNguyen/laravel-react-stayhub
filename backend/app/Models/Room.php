<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rooms';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'room_type_id',
        'room_number',
        'name',
        'description',
        'capacity',
        'price_per_night',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price_per_night' => 'decimal:2',
            'status' => RoomStatus::class,
        ];
    }

    /**
     * Get the property that owns the room.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the room type that owns the room.
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    /**
     * Get the images for the room.
     */
    public function roomImages(): HasMany
    {
        return $this->hasMany(RoomImage::class, 'room_id');
    }

    /**
     * Alias for room images.
     */
    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class, 'room_id');
    }

    /**
     * Get the bookings for the room.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'room_id');
    }

    /**
     * Get the maintenances scheduled for the room.
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'room_id');
    }

    /**
     * The amenities that belong to the room.
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'room_amenities', 'room_id', 'amenity_id');
    }
}
