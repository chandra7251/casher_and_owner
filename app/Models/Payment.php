<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'status', 'method', 'amount', 'received_amount', 'change_amount', 'idempotency_key', 'paid_at'];

    protected $casts = ['amount' => 'integer', 'received_amount' => 'integer', 'change_amount' => 'integer', 'paid_at' => 'datetime'];
}
