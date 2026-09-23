<?php

namespace Tests\Feature;

use App\Models\CafeSetting;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptAccessTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrderWithItems(): Order
    {
        CafeSetting::firstOrCreate(['id' => 1], ['name' => 'Kedai Test', 'thank_you_message' => 'Terima kasih.']);
        $table = Table::factory()->create();
        $cashier = User::factory()->create(['role' => 'cashier']);
        $item = MenuItem::factory()->create(['name' => 'Kopi Struk']);
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 2]);
        $order = Order::create(['number' => 'RCP-001', 'user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'paid', 'total' => 18000]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => 'Kopi Struk', 'size' => 'Regular', 'unit_price' => 18000, 'quantity' => 1, 'line_total' => 18000]);
        Payment::create(['order_id' => $order->id, 'status' => 'paid', 'method' => 'cash', 'amount' => 18000, 'received_amount' => 20000, 'change_amount' => 2000, 'idempotency_key' => 'rcp-001', 'paid_at' => now()]);

        return $order;
    }

    public function test_guest_cannot_access_receipt(): void
    {
        $order = $this->paidOrderWithItems();

        $this->get('/cashier/orders/'.$order->id.'/receipt')
            ->assertRedirectToRoute('login');
    }

    public function test_cashier_a_can_access_own_paid_receipt(): void
    {
        $order = $this->paidOrderWithItems();
        $cashierA = $order->user;

        $this->actingAs($cashierA)
            ->get('/cashier/orders/'.$order->id.'/receipt')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_cashier_b_can_access_cashier_a_paid_receipt(): void
    {
        $order = $this->paidOrderWithItems();
        $cashierB = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashierB)
            ->get('/cashier/orders/'.$order->id.'/receipt')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_owner_can_access_cashier_receipt(): void
    {
        $order = $this->paidOrderWithItems();
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get('/cashier/orders/'.$order->id.'/receipt')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_unpaid_order_cannot_expose_receipt(): void
    {
        CafeSetting::firstOrCreate(['id' => 1], ['name' => 'Kedai Test', 'thank_you_message' => 'Terima kasih.']);
        $table = Table::factory()->create();
        $cashier = User::factory()->create(['role' => 'cashier']);
        $order = Order::create(['number' => 'RCP-002', 'user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'awaiting_payment', 'total' => 18000, 'payment_expires_at' => now()->addMinute()]);

        $this->actingAs($cashier)
            ->get('/cashier/orders/'.$order->id.'/receipt')
            ->assertStatus(422);
    }

    public function test_expired_order_cannot_expose_receipt(): void
    {
        CafeSetting::firstOrCreate(['id' => 1], ['name' => 'Kedai Test', 'thank_you_message' => 'Terima kasih.']);
        $table = Table::factory()->create();
        $cashier = User::factory()->create(['role' => 'cashier']);
        $order = Order::create(['number' => 'RCP-003', 'user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'expired', 'total' => 18000]);

        $this->actingAs($cashier)
            ->get('/cashier/orders/'.$order->id.'/receipt')
            ->assertStatus(422);
    }

    public function test_cashier_cannot_access_owner_routes_after_receipt_change(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get('/owner/dashboard')->assertForbidden();
        $this->actingAs($cashier)->get('/owner/menu')->assertForbidden();
        $this->actingAs($cashier)->getJson('/owner/reports?from=2026-01-01&to=2026-12-31')->assertForbidden();
        $this->actingAs($cashier)->get('/owner/settings')->assertForbidden();
        $this->actingAs($cashier)->get('/owner/tables')->assertForbidden();
    }

    public function test_receipt_payload_content_is_correct(): void
    {
        $order = $this->paidOrderWithItems();
        $cashier = $order->user;

        $response = $this->actingAs($cashier)
            ->get('/cashier/orders/'.$order->id.'/receipt');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        // ESC/POS starts with ESC @ (reset) command
        $this->assertStringContainsString("\x1B@", $content);
        // Cafe name in receipt
        $this->assertStringContainsString('Kedai Test', $content);
    }
}
