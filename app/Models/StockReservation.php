<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $fillable = ['order_id', 'menu_item_size_id', 'quantity', 'status', 'expires_at', 'consumed_at', 'released_at'];

    protected $casts = ['quantity' => 'integer', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'released_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItemSize()
    {
        return $this->belongsTo(MenuItemSize::class);
    }
}
