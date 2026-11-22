<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostAgreementAcceptance extends Model
{
    use HasFactory;

    protected $table = 'host_agreement_acceptances';

    protected $fillable = [
        'host_id',
        'partner_agreement_id',
        'accepted_content_sha256',
        'accepted_at',
        'method',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PartnerAgreement::class, 'partner_agreement_id');
    }
}
