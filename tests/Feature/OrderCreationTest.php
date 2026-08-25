<?php

namespace Tests\Feature;

use App\Models\CafeSetting;
use App\Models\MenuItem;
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
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $response->json('data.id'), 'menu_item_size_id' => MenuItemSize::query()->where('menu_item_id', $item->id)->value('id'), 'quantity' => 2, 'status' => 'reserved']);
    }

    public function test_two_stock_reservations_never_exceed_on_hand(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 10000, 'on_hand' => 1, 'reserved' => 0, 'low_stock_threshold' => 0]);
        $payload = ['table_id' => $table->id, 'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 1]]];
        $this->actingAs($first)->postJson('/cashier/orders', $payload)->assertCreated();
        $this->actingAs($second)->postJson('/cashier/orders', $payload)->assertStatus(422);
        $size->refresh();
        $this->assertLessThanOrEqual($size->on_hand, $size->reserved);
        $this->assertSame(1, $size->reserved);
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

    public function test_cashier_can_edit_awaiting_order_and_recalculate_stock(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $oldTable = Table::factory()->create();
        $newTable = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 12000, 'on_hand' => 10, 'reserved' => 2, 'low_stock_threshold' => 1]);
        $order = Order::factory()->create(['user_id' => $cashier->id, 'table_id' => $oldTable->id, 'status' => 'awaiting_payment', 'total' => 24000]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 12000, 'quantity' => 2, 'line_total' => 24000]);

        $response = $this->actingAs($cashier)->patchJson('/cashier/orders/'.$order->id, [
            'table_id' => $newTable->id,
            'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 3]],
        ]);

        $response->assertOk()->assertJsonPath('data.total', 36000)->assertJsonPath('data.status', 'awaiting_payment');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'table_id' => $newTable->id, 'total' => 36000]);
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'reserved' => 3]);
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

    public function test_cashier_can_mark_empty_table_available(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create(['status' => 'occupied']);
        $this->actingAs($cashier)->patchJson('/cashier/tables/'.$table->id.'/status', ['status' => 'available'])->assertOk()->assertJsonPath('data.status', 'available');
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'status' => 'available']);
    }

    public function test_cashier_cannot_mark_table_available_with_active_order(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create(['status' => 'occupied']);
        Order::factory()->create(['table_id' => $table->id, 'status' => 'awaiting_payment']);
        $this->actingAs($cashier)->patchJson('/cashier/tables/'.$table->id.'/status', ['status' => 'available'])->assertStatus(422);
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'status' => 'occupied']);
    }
}
