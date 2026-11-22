<?php

namespace App\Models;

use App\Enums\RemittanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRemittance extends Model
{
    use HasFactory;

    protected $table = 'commission_remittances';

    protected $fillable = [
        'booking_commission_id',
        'host_id',
        'amount',
        'bank_transaction_reference',
        'idempotency_key',
        'status',
        'submitted_at',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_note',
        'verified_bank_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RemittanceStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(BookingCommission::class, 'booking_commission_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}
