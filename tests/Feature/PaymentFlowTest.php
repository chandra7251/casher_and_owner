<?php

namespace Tests\Feature;

use App\Models\CafeSetting;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\StockReservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.print_bridge.token' => 'bridge-secret']);
    }

    public function test_cash_payment_uses_server_total_and_returns_change(): void
    {
        $user = User::factory()->create();
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => 'awaiting_payment', 'total' => 44000]);
        $order->items()->create([
            'menu_item_id' => $item->id,
            'name_snapshot' => 'Kopi Susu Senja',
            'size' => 'Large',
            'unit_price' => 22000,
            'quantity' => 2,
            'line_total' => 44000,
        ]);

        $response = $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', [
            'method' => 'cash',
            'received_amount' => 50000,
            'client_total' => 1,
            'idempotency_key' => 'pay-001',
        ]);

        $response->assertOk()->assertJsonPath('data.total', 44000)->assertJsonPath('data.change', 6000);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid']);
    }

    public function test_qris_requires_manual_validation_and_rejects_unsupported_method(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'total' => 10000]);

        $unsupported = $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', [
            'method' => 'card', 'received_amount' => 10000, 'idempotency_key' => 'pay-002',
        ]);
        $this->assertSame(422, $unsupported->status());

        $missingValidation = $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', [
            'method' => 'qris_manual', 'received_amount' => 10000, 'idempotency_key' => 'pay-003',
        ]);
        $this->assertSame(422, $missingValidation->status());

        $response = $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', [
            'method' => 'qris_manual', 'received_amount' => 10000,
            'validated' => true, 'idempotency_key' => 'pay-004',
        ]);

        $response->assertOk();
    }

    public function test_duplicate_payment_request_returns_same_payment_without_duplicate_record(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'total' => 10000]);
        $payload = ['method' => 'cash', 'received_amount' => 10000, 'idempotency_key' => 'same-key'];

        $first = $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', $payload);
        $second = $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', $payload);

        $first->assertOk();
        $second->assertOk()->assertExactJson($first->json());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_paid_payment_consumes_reserved_stock_once(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'total' => 20000]);
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 20000, 'on_hand' => 5, 'reserved' => 2, 'low_stock_threshold' => 1]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 20000, 'quantity' => 2, 'line_total' => 40000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => 2, 'status' => 'reserved']);
        $order->update(['total' => 40000]);

        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 40000, 'idempotency_key' => 'consume-1'])->assertOk();
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'on_hand' => 3, 'reserved' => 0]);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'status' => 'consumed']);
    }

    public function test_expired_payment_releases_reserved_stock(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'payment_expires_at' => now()->subSecond()]);
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 20000, 'on_hand' => 5, 'reserved' => 2, 'low_stock_threshold' => 1]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 20000, 'quantity' => 2, 'line_total' => 40000]);
        StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => 2, 'status' => 'reserved']);

        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 40000, 'idempotency_key' => 'release-1'])->assertStatus(422);
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'reserved' => 0, 'on_hand' => 5]);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'status' => 'released']);
    }

    public function test_paid_payment_queues_one_receipt_print_job(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'awaiting_payment']);
        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 10000, 'idempotency_key' => 'print-1'])->assertOk();
        $this->assertDatabaseHas('print_jobs', ['order_id' => $order->id, 'status' => 'queued']);
    }

    public function test_print_job_contains_immutable_receipt_snapshot(): void
    {
        $user = User::factory()->create();
        $table = Table::factory()->create(['name' => 'Meja 07']);
        $item = MenuItem::factory()->create(['name' => 'Kopi Snapshot']);
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => 'awaiting_payment']);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => 'Kopi Snapshot', 'size' => 'Regular', 'unit_price' => 18000, 'quantity' => 1, 'line_total' => 18000]);

        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 20000, 'idempotency_key' => 'snapshot-1'])->assertOk();

        $job = PrintJob::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('Meja 07', $job->receipt_snapshot['table']);
        $this->assertSame('Kopi Snapshot', $job->receipt_snapshot['items'][0]['name']);
        $this->assertSame(18000, $job->receipt_snapshot['total']);
    }

    public function test_receipt_snapshot_contains_logo_and_escpos_payload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cafe/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $user = User::factory()->create(['name' => 'Kasir Logo']);
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'awaiting_payment']);
        $item = MenuItem::factory()->create();
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
        CafeSetting::create(['name' => 'Kedai Logo', 'logo_path' => 'cafe/logo.png']);
        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 10000, 'idempotency_key' => 'logo-1'])->assertOk();
        $snapshot = PrintJob::query()->where('order_id', $order->id)->firstOrFail()->receipt_snapshot;
        $this->assertSame('cafe/logo.png', $snapshot['cafe']['logo_path']);
        $this->assertStringContainsString("\x1D\x76\x30\x00", base64_decode($snapshot['escpos_base64']));
    }

    public function test_print_bridge_can_claim_queued_job_and_mark_failure(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'paid']);
        $job = PrintJob::create(['order_id' => $order->id, 'status' => 'queued', 'receipt_snapshot' => ['total' => 10000]]);

        $this->withHeader('Authorization', 'Bearer bridge-secret')->getJson('/api/print-jobs/next')->assertOk()->assertJsonPath('data.id', $job->id);
        $this->withHeader('Authorization', 'Bearer bridge-secret')->patchJson('/api/print-jobs/'.$job->id, ['status' => 'failed', 'failure_reason' => 'Printer tidak terhubung.'])->assertOk();
        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'failed', 'failure_reason' => 'Printer tidak terhubung.']);
    }

    public function test_failed_print_job_can_retry_but_printed_job_cannot(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'paid']);
        $failed = PrintJob::create(['order_id' => $order->id, 'status' => 'failed', 'receipt_snapshot' => ['total' => 10000]]);

        $this->actingAs($user)->postJson('/cashier/print-jobs/'.$failed->id.'/retry')->assertOk();
        $this->assertDatabaseHas('print_jobs', ['id' => $failed->id, 'status' => 'queued']);

        $failed->update(['status' => 'printed']);
        $this->actingAs($user)->postJson('/cashier/print-jobs/'.$failed->id.'/retry')->assertStatus(422);
    }

    public function test_print_bridge_rejects_invalid_state_transition(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'paid']);
        $job = PrintJob::create(['order_id' => $order->id, 'status' => 'queued', 'receipt_snapshot' => ['total' => 10000]]);
        $this->withHeader('Authorization', 'Bearer bridge-secret')->patchJson('/api/print-jobs/'.$job->id, ['status' => 'printed'])->assertStatus(409);
        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'queued']);
    }

    public function test_stock_movement_audit_records_lifecycle(): void
    {
        $user = User::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 10000, 'on_hand' => 5, 'reserved' => 2, 'low_stock_threshold' => 1]);
        $table = Table::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'table_id' => $table->id, 'status' => 'awaiting_payment']);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 10000, 'quantity' => 2, 'line_total' => 20000]);
        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 20000, 'idempotency_key' => 'audit-consume'])->assertOk();
        $this->assertDatabaseHas('stock_movements', ['menu_item_size_id' => $size->id, 'type' => 'consume', 'quantity' => -2]);

        $expired = Order::factory()->create(['user_id' => $user->id, 'table_id' => $table->id, 'status' => 'awaiting_payment', 'payment_expires_at' => now()->subMinute()]);
        $expired->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
        $size->update(['reserved' => 1]);
        $this->artisan('orders:expire')->assertExitCode(0);
        $this->assertDatabaseHas('stock_movements', ['menu_item_size_id' => $size->id, 'type' => 'release', 'quantity' => -1]);
    }

    public function test_print_bridge_requires_valid_token(): void
    {
        config(['services.print_bridge.token' => 'bridge-secret']);
        $this->getJson('/api/print-jobs/next')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong')->getJson('/api/print-jobs/next')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer bridge-secret')->getJson('/api/print-jobs/next')->assertOk();
    }
}
