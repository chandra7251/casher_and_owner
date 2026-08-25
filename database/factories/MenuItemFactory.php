<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return ['menu_category_id' => MenuCategory::factory(), 'name' => 'Menu Test', 'is_available' => true];
    }
}
