<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'amenities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'icon',
        'description',
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
            'status' => 'boolean',
        ];
    }

    /**
     * The rooms that belong to the amenity.
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_amenities', 'amenity_id', 'room_id');
    }
}
