<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';
}
