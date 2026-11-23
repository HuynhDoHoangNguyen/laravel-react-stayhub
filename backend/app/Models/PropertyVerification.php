<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PropertyVerification extends Model
{
    public const SUBMITTED = 'SUBMITTED';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    public const INVALIDATED = 'INVALIDATED';

    public $timestamps = false;

    protected $fillable = [
        'property_id',
        'submitted_by',
        'address_snapshot',
        'status',
        'document_path',
        'document_mime',
        'document_size',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'document_size' => 'integer'];
    }
}
