<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'payment_id', 'invoice_number', 'amount', 'tax_amount',
        'total', 'status', 'issued_at', 'paid_at', 'pdf_path', 'line_items',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total'      => 'decimal:2',
        'issued_at'  => 'datetime',
        'paid_at'    => 'datetime',
        'line_items' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public static function generateNumber(): string
    {
        $year = date('Y');
        $lastInvoice = static::where('invoice_number', 'like', "INV-{$year}-%")
            ->orderByDesc('id')
            ->value('invoice_number');

        if ($lastInvoice) {
            $lastNum = (int) substr($lastInvoice, strrpos($lastInvoice, '-') + 1);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('INV-%s-%04d', $year, $nextNum);
    }
}
