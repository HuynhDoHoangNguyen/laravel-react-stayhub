<?php

namespace App\Models;

use App\Enums\HostReportReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostReport extends Model
{
    public const SUBMITTED = 'SUBMITTED';
    public const SUBSTANTIATED = 'SUBSTANTIATED';
    public const UNSUBSTANTIATED = 'UNSUBSTANTIATED';
    public const DECISIONS = [self::SUBSTANTIATED, self::UNSUBSTANTIATED];
    public const EVIDENCE_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    public const EVIDENCE_MAX_BYTES = 2 * 1024 * 1024;

    protected $fillable = [
        'booking_id', 'customer_id', 'property_id', 'host_id', 'reason', 'description', 'status',
        'evidence_path', 'evidence_mime', 'evidence_size',
    ];

    protected function casts(): array
    {
        return ['reason' => HostReportReason::class, 'reviewed_at' => 'datetime', 'evidence_size' => 'integer'];
    }

    /** @return array{filename: string, mime: string, size: int}|null */
    public function evidenceMetadata(): ?array
    {
        $extension = self::EVIDENCE_EXTENSIONS[$this->evidence_mime] ?? null;
        if ($extension === null || !$this->evidence_path || $this->evidence_size < 1 || $this->evidence_size > self::EVIDENCE_MAX_BYTES) {
            return null;
        }

        return ['filename' => 'chung-cu-bao-cao-'.$this->id.'.'.$extension, 'mime' => $this->evidence_mime, 'size' => $this->evidence_size];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
