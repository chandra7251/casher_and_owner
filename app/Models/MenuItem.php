<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;
    protected $fillable = ['menu_category_id', 'name', 'is_available'];
    protected $casts = ['is_available' => 'boolean'];
    public function sizes() { return $this->hasMany(MenuItemSize::class); }
}
