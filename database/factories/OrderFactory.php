<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return ['number' => 'ORD-'.fake()->unique()->numerify('#####'), 'user_id' => User::factory(), 'table_id' => Table::factory(), 'status' => 'awaiting_payment', 'total' => 0, 'payment_expires_at' => now()->addSeconds(60)];
    }
}
