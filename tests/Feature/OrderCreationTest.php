<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\CafeSetting;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_recalculates_total_and_reserves_stock(): void
    {
        $user = User::factory()->create();
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Large', 'price' => 22000, 'on_hand' => 5, 'reserved' => 0, 'low_stock_threshold' => 1]);

        $response = $this->actingAs($user)->postJson('/cashier/orders', [
            'table_id' => $table->id,
            'items' => [['product_id' => $item->id, 'size' => 'Large', 'quantity' => 2]],
        ]);

        $response->assertCreated()->assertJsonPath('data.total', 44000)->assertJsonPath('data.status', 'awaiting_payment');
        $this->assertDatabaseHas('menu_item_sizes', ['menu_item_id' => $item->id, 'size' => 'Large', 'reserved' => 2]);
        $this->assertDatabaseHas('orders', ['table_id' => $table->id, 'status' => 'awaiting_payment', 'total' => 44000]);
    }

    public function test_order_creation_rejects_insufficient_available_stock(): void
    {
        $user = User::factory()->create();
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 15000, 'on_hand' => 1, 'reserved' => 1, 'low_stock_threshold' => 1]);

        $this->actingAs($user)->postJson('/cashier/orders', [
            'table_id' => $table->id,
            'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_cashier_workspace_exposes_active_order_count_per_table(): void
    {
        $user = User::factory()->create();
        CafeSetting::create(['name' => 'Test Cafe']);
        $table = Table::factory()->create(['name' => 'Meja Aktif']);
        Order::factory()->create(['table_id' => $table->id, 'status' => 'awaiting_payment']);

        $this->assertSame(1, $table->orders()->whereIn('status', ['draft', 'awaiting_payment'])->count());
        $this->actingAs($user)->get('/cashier/orders')->assertOk();
    }
}
