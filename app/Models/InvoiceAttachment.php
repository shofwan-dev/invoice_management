<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAttachment extends Model
{
    protected $fillable = ['invoice_id','filename','path','mime','size'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
