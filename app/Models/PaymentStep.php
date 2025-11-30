<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStep extends Model
{
    protected $fillable = ['invoice_id','step_number','amount','payment_date','bank_name'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
