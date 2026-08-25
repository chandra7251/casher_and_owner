<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CafeSetting extends Model
{
    protected $fillable = ['name', 'currency', 'address', 'phone', 'logo_path', 'thank_you_message'];
}
