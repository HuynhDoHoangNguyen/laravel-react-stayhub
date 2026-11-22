<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingCommission extends Model
{
    use HasFactory;

    protected $table = 'booking_commissions';

    protected $fillable = [
        'invoice_id',
        'booking_id',
        'host_id',
        'host_agreement_acceptance_id',
        'reference_code',
        'commission_base',
        'commission_rate_bps',
        'commission_amount',
        'currency',
        'status',
        'void_reason',
        'due_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_base' => 'decimal:2',
            'commission_rate_bps' => 'integer',
            'commission_amount' => 'decimal:2',
            'status' => CommissionStatus::class,
            'due_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function hostAgreementAcceptance(): BelongsTo
    {
        return $this->belongsTo(HostAgreementAcceptance::class, 'host_agreement_acceptance_id');
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(CommissionRemittance::class, 'booking_commission_id');
    }
}
