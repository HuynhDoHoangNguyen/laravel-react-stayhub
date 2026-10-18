<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'services';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'name',
        'description',
        'unit',
        'price',
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
            'price' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the property that offers this service.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the booking service records for this service.
     */
    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class, 'service_id');
    }
}
