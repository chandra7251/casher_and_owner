<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['menu_item_id', 'name_snapshot', 'size', 'unit_price', 'quantity', 'line_total'];
    protected $casts = ['unit_price' => 'integer', 'quantity' => 'integer', 'line_total' => 'integer'];
}
