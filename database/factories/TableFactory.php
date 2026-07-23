<?php

namespace Database\Factories;

use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    protected $model = Table::class;
    public function definition(): array { return ['name' => 'Meja '.fake()->unique()->numberBetween(1, 99), 'status' => 'available']; }
}
