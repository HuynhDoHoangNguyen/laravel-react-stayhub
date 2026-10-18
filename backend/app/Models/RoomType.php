<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'room_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'name',
        'description',
    ];

    /**
     * Get the property that owns the room type.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the rooms for this room type.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }
}
