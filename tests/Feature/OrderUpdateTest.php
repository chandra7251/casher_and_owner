<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_order_releases_previous_reservation_and_records_stock_movement(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 10000, 'on_hand' => 10, 'reserved' => 2, 'low_stock_threshold' => 1]);

        $order = Order::factory()->create(['user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'awaiting_payment', 'total' => 20000, 'payment_expires_at' => now()->addMinutes(1)]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 10000, 'quantity' => 2, 'line_total' => 20000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => 2, 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);

        $response = $this->actingAs($cashier)->patchJson('/cashier/orders/'.$order->id, [
            'table_id' => $table->id,
            'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 3]],
        ]);

        $response->assertOk();

        // Check release movement
        $releaseMovement = StockMovement::where('menu_item_size_id', $size->id)->where('type', 'release')->first();
        $this->assertNotNull($releaseMovement);
        $this->assertSame(-2, $releaseMovement->quantity);
        $this->assertSame(2, $releaseMovement->before_reserved);
        $this->assertSame(0, $releaseMovement->after_reserved);
        $this->assertSame($order->id, $releaseMovement->reference_id);
    }

    public function test_edit_order_creates_new_reservation_and_records_stock_movement(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 10000, 'on_hand' => 10, 'reserved' => 2, 'low_stock_threshold' => 1]);

        $order = Order::factory()->create(['user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'awaiting_payment', 'total' => 20000, 'payment_expires_at' => now()->addMinutes(1)]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 10000, 'quantity' => 2, 'line_total' => 20000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => 2, 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);

        $response = $this->actingAs($cashier)->patchJson('/cashier/orders/'.$order->id, [
            'table_id' => $table->id,
            'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 4]],
        ]);

        $response->assertOk();

        $size->refresh();
        $this->assertSame(4, $size->reserved);

        $reserveMovement = StockMovement::where('menu_item_size_id', $size->id)->where('type', 'reserve')->first();
        $this->assertNotNull($reserveMovement);
        $this->assertSame(4, $reserveMovement->quantity);
        $this->assertSame(0, $reserveMovement->before_reserved);
        $this->assertSame(4, $reserveMovement->after_reserved);
    }

    public function test_ledger_snapshots_accurate_on_order_edit(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 15000, 'on_hand' => 8, 'reserved' => 3, 'low_stock_threshold' => 1]);

        $order = Order::factory()->create(['user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'awaiting_payment', 'total' => 45000, 'payment_expires_at' => now()->addMinutes(1)]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 15000, 'quantity' => 3, 'line_total' => 45000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => 3, 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);

        $this->actingAs($cashier)->patchJson('/cashier/orders/'.$order->id, [
            'table_id' => $table->id,
            'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 5]],
        ])->assertOk();

        $movements = StockMovement::where('menu_item_size_id', $size->id)->orderBy('id')->get();
        $this->assertCount(2, $movements);

        // 1st: release old 3 units
        $this->assertSame('release', $movements[0]->type);
        $this->assertSame(-3, $movements[0]->quantity);
        $this->assertSame(8, $movements[0]->before_on_hand);
        $this->assertSame(8, $movements[0]->after_on_hand);
        $this->assertSame(3, $movements[0]->before_reserved);
        $this->assertSame(0, $movements[0]->after_reserved);

        // 2nd: reserve new 5 units
        $this->assertSame('reserve', $movements[1]->type);
        $this->assertSame(5, $movements[1]->quantity);
        $this->assertSame(8, $movements[1]->before_on_hand);
        $this->assertSame(8, $movements[1]->after_on_hand);
        $this->assertSame(0, $movements[1]->before_reserved);
        $this->assertSame(5, $movements[1]->after_reserved);
    }

    public function test_expired_order_cannot_be_edited_and_releases_reservation(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 10000, 'on_hand' => 10, 'reserved' => 2, 'low_stock_threshold' => 1]);

        $order = Order::factory()->create([
            'user_id' => $cashier->id,
            'table_id' => $table->id,
            'status' => 'awaiting_payment',
            'total' => 20000,
            'payment_expires_at' => now()->subSecond(),
        ]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 10000, 'quantity' => 2, 'line_total' => 20000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => 2, 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);

        $response = $this->actingAs($cashier)->patchJson('/cashier/orders/'.$order->id, [
            'table_id' => $table->id,
            'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 3]],
        ]);

        $response->assertStatus(422);

        $order->refresh();
        $this->assertSame('expired', $order->status);

        $size->refresh();
        $this->assertSame(0, $size->reserved);

        $reservation = StockReservation::where('order_id', $order->id)->where('menu_item_size_id', $size->id)->first();
        $this->assertSame('released', $reservation->status);

        $releaseMovement = StockMovement::where('menu_item_size_id', $size->id)->where('type', 'release')->first();
        $this->assertNotNull($releaseMovement);
        $this->assertSame(-2, $releaseMovement->quantity);
    }

    public function test_insufficient_stock_edit_rolls_back_atomically(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 10000, 'on_hand' => 5, 'reserved' => 2, 'low_stock_threshold' => 1]);

        $order = Order::factory()->create([
            'user_id' => $cashier->id,
            'table_id' => $table->id,
            'status' => 'awaiting_payment',
            'total' => 20000,
            'payment_expires_at' => now()->addMinutes(1),
        ]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 10000, 'quantity' => 2, 'line_total' => 20000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => 2, 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);

        // Attempt to request 10 units when on_hand is only 5
        $response = $this->actingAs($cashier)->patchJson('/cashier/orders/'.$order->id, [
            'table_id' => $table->id,
            'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 10]],
        ]);

        $response->assertStatus(422);

        // Entire transaction must rollback: old reservation intact, zero movements created
        $size->refresh();
        $this->assertSame(2, $size->reserved);

        $order->refresh();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame(20000, $order->total);
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items->first()->quantity);

        $reservation = StockReservation::where('order_id', $order->id)->where('menu_item_size_id', $size->id)->first();
        $this->assertSame('reserved', $reservation->status);
        $this->assertSame(2, $reservation->quantity);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_cross_item_edit_maintains_consistent_ledger_for_both_items(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create();
        $itemA = MenuItem::factory()->create(['name' => 'Kopi A']);
        $sizeA = MenuItemSize::create(['menu_item_id' => $itemA->id, 'size' => 'Regular', 'price' => 12000, 'on_hand' => 10, 'reserved' => 2, 'low_stock_threshold' => 1]);

        $itemB = MenuItem::factory()->create(['name' => 'Kopi B']);
        $sizeB = MenuItemSize::create(['menu_item_id' => $itemB->id, 'size' => 'Regular', 'price' => 15000, 'on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 1]);

        $order = Order::factory()->create([
            'user_id' => $cashier->id,
            'table_id' => $table->id,
            'status' => 'awaiting_payment',
            'total' => 24000,
            'payment_expires_at' => now()->addMinutes(1),
        ]);
        $order->items()->create(['menu_item_id' => $itemA->id, 'name_snapshot' => $itemA->name, 'size' => 'Regular', 'unit_price' => 12000, 'quantity' => 2, 'line_total' => 24000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $sizeA->id, 'quantity' => 2, 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);

        // Edit order: replace Kopi A with Kopi B qty 3
        $response = $this->actingAs($cashier)->patchJson('/cashier/orders/'.$order->id, [
            'table_id' => $table->id,
            'items' => [['product_id' => $itemB->id, 'size' => 'Regular', 'quantity' => 3]],
        ]);

        $response->assertOk()->assertJsonPath('data.total', 45000);

        $sizeA->refresh();
        $sizeB->refresh();
        $this->assertSame(0, $sizeA->reserved);
        $this->assertSame(3, $sizeB->reserved);

        // Movement for size A: release
        $movementA = StockMovement::where('menu_item_size_id', $sizeA->id)->first();
        $this->assertNotNull($movementA);
        $this->assertSame('release', $movementA->type);
        $this->assertSame(-2, $movementA->quantity);
        $this->assertSame(2, $movementA->before_reserved);
        $this->assertSame(0, $movementA->after_reserved);

        // Movement for size B: reserve
        $movementB = StockMovement::where('menu_item_size_id', $sizeB->id)->first();
        $this->assertNotNull($movementB);
        $this->assertSame('reserve', $movementB->type);
        $this->assertSame(3, $movementB->quantity);
        $this->assertSame(0, $movementB->before_reserved);
        $this->assertSame(3, $movementB->after_reserved);

        // Stock reservations
        $resA = StockReservation::where('order_id', $order->id)->where('menu_item_size_id', $sizeA->id)->first();
        $this->assertSame('released', $resA->status);

        $resB = StockReservation::where('order_id', $order->id)->where('menu_item_size_id', $sizeB->id)->first();
        $this->assertSame('reserved', $resB->status);
        $this->assertSame(3, $resB->quantity);
    }
}
