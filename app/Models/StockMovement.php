<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['menu_item_size_id', 'user_id', 'type', 'quantity', 'before_on_hand', 'after_on_hand', 'before_reserved', 'after_reserved', 'reference_type', 'reference_id'];

    protected $casts = ['quantity' => 'integer', 'before_on_hand' => 'integer', 'after_on_hand' => 'integer', 'before_reserved' => 'integer', 'after_reserved' => 'integer'];

    public function size()
    {
        return $this->belongsTo(MenuItemSize::class, 'menu_item_size_id');
    }
}
