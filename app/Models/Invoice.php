<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'payment_deadline',
        'client_name',
        'total_amount',
        'total_received',
        'remaining_amount',
        'status',
        'template',
        'created_by'
    ];


    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = self::generateInvoiceNumber($invoice->invoice_date ?? now()->toDateString());
            }
        });
    }

    public static function generateInvoiceNumber($date)
    {
        $d = \Carbon\Carbon::parse($date)->format('Ymd');
        $prefix = "INV-{$d}-";
        $last = self::where('invoice_number', 'like', $prefix.'%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if (!$last) {
            $num = 1;
        } else {
            $parts = explode('-', $last->invoice_number);
            $num = intval(end($parts)) + 1;
        }

        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function paymentSteps(): HasMany
    {
        return $this->hasMany(PaymentStep::class)->orderBy('step_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InvoiceAttachment::class);
    }

    public function getTotalReceivedAttribute()
    {
        return $this->paymentSteps->sum('amount');
    }

    public function getStatusAttribute()
    {
        $totalReceived = $this->total_received;
        $totalAmount = $this->total_amount;
        
        if ($totalReceived >= $totalAmount) {
            return 'Lunas';
        }
        
        return 'Belum Lunas';
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->total_amount - $this->total_received);
    }
}