<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerAgreement extends Model
{
    use HasFactory;

    protected $table = 'partner_agreements';

    protected $fillable = [
        'version',
        'title',
        'terms_body',
        'content_sha256',
        'commission_rate_bps',
        'effective_at',
        'retired_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate_bps' => 'integer',
            'effective_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(HostAgreementAcceptance::class, 'partner_agreement_id');
    }
}
