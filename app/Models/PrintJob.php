<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = ['order_id', 'status', 'receipt_snapshot', 'failure_reason', 'printed_at'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    protected $casts = ['receipt_snapshot' => 'array', 'printed_at' => 'datetime'];
}
