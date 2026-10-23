<?php
// TEMP-M1-STUB

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id', 'name', 'type', 'address', 'description', 'phone',
        'check_in_time', 'check_out_time', 'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'status' => PropertyStatus::class,
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'property_id');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class, 'property_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class, 'property_id');
    }

    public function propertyImages(): HasMany
    {
        return $this->images();
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'property_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'property_id');
    }
}
