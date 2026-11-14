<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoices';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_code',
        'booking_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'property_name',
        'room_name',
        'subtotal',
        'discount_amount',
        'extra_fee',
        'total_amount',
        'payment_status',
        'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'extra_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the booking associated with the invoice.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * Get the line items for the invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    /** Explicit pilot/create groups; ordinary Eloquent saves do not trigger validation. */
    #[Assert\NotBlank(message: 'Dữ liệu hóa đơn không hợp lệ.', normalizer: 'trim', groups: ['invoice-pilot', 'invoice-create'])]
    public function getInvoiceCodeValidationValue(): string
    {
        return (string) $this->getAttribute('invoice_code');
    }
}
