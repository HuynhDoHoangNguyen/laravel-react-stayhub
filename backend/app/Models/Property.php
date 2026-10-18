<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'properties';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'host_id',
        'name',
        'type',
        'address',
        'description',
        'phone',
        'check_in_time',
        'check_out_time',
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
            'type' => PropertyType::class,
            'status' => PropertyStatus::class,
        ];
    }

    /**
     * Get the host that owns the property.
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * Get the property images.
     */
    public function propertyImages(): HasMany
    {
        return $this->hasMany(PropertyImage::class, 'property_id');
    }

    /**
     * Alias for property images.
     */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class, 'property_id');
    }

    /**
     * Get the room types for the property.
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class, 'property_id');
    }

    /**
     * Get the rooms for the property.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'property_id');
    }

    /**
     * Get the services provided by the property.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'property_id');
    }

    /**
     * Get the reviews for the property.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'property_id');
    }
}
