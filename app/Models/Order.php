<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = ['number', 'user_id', 'table_id', 'status', 'total', 'payment_expires_at'];
    protected $casts = ['total' => 'integer', 'payment_expires_at' => 'datetime'];
    public function items() { return $this->hasMany(OrderItem::class); }
    public function payment() { return $this->hasOne(Payment::class); }
    public function table() { return $this->belongsTo(Table::class); }
}
