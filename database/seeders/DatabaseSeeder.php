<?php

namespace Database\Seeders;

use App\Models\CafeSetting;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Owner Kedai Senja', 'email' => 'owner@kedaisenja.test', 'role' => 'owner', 'password' => Hash::make('change-this-local-password')]);
        User::factory()->create(['name' => 'Budi', 'email' => 'kasir@kedaisenja.test', 'role' => 'cashier', 'password' => Hash::make('change-this-local-password')]);
        CafeSetting::create(['name' => 'Kedai Senja', 'address' => 'Jl. Melati No. 12', 'thank_you_message' => 'Terima kasih atas kunjungannya!']);

        $categories = [];
        foreach (['Kopi', 'Non-kopi', 'Makanan'] as $sort => $name) {
            $categories[$name] = MenuCategory::create(['name' => $name, 'sort_order' => $sort]);
        }

        foreach ([
            ['Kopi Susu Senja', 'Kopi', 18000, 22000, 42],
            ['Matcha Latte', 'Non-kopi', 20000, 25000, 18],
            ['Croffle Original', 'Makanan', 15000, 19000, 15],
            ['Americano', 'Kopi', 15000, 19000, 24],
        ] as [$name, $category, $regular, $large, $stock]) {
            $item = MenuItem::create(['menu_category_id' => $categories[$category]->id, 'name' => $name]);
            MenuItemSize::insert([
                ['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => $regular, 'on_hand' => $stock, 'reserved' => 0, 'low_stock_threshold' => 5],
                ['menu_item_id' => $item->id, 'size' => 'Large', 'price' => $large, 'on_hand' => $stock, 'reserved' => 0, 'low_stock_threshold' => 5],
            ]);
        }

        for ($number = 1; $number <= 12; $number++) {
            Table::create(['name' => 'Meja '.str_pad((string) $number, 2, '0', STR_PAD_LEFT)]);
        }
    }
}
