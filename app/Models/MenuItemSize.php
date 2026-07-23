<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItemSize extends Model
{
    public $timestamps = false;
    protected $fillable = ['menu_item_id', 'size', 'price', 'on_hand', 'reserved', 'low_stock_threshold'];
    protected $casts = ['price' => 'integer', 'on_hand' => 'integer', 'reserved' => 'integer', 'low_stock_threshold' => 'integer'];
    public function menuItem() { return $this->belongsTo(MenuItem::class); }
}