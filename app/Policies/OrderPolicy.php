<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function pay(User $user, Order $order): bool
    {
        return in_array($user->role?->value, ['owner', 'cashier'], true);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->pay($user, $order) && $order->status === 'awaiting_payment';
    }
}
