<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Table;
use App\Models\MenuItemSize;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

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
        $order->update(['total' => 40000]);

        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 40000, 'idempotency_key' => 'consume-1'])->assertOk();
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'on_hand' => 3, 'reserved' => 0]);
    }

    public function test_expired_payment_releases_reserved_stock(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'payment_expires_at' => now()->subSecond()]);
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 20000, 'on_hand' => 5, 'reserved' => 2, 'low_stock_threshold' => 1]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'size' => 'Regular', 'unit_price' => 20000, 'quantity' => 2, 'line_total' => 40000]);

        $this->actingAs($user)->postJson('/cashier/orders/'.$order->id.'/payment', ['method' => 'cash', 'received_amount' => 40000, 'idempotency_key' => 'release-1'])->assertStatus(422);
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'reserved' => 0, 'on_hand' => 5]);
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

    public function test_print_bridge_can_claim_queued_job_and_mark_failure(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'paid']);
        $job = PrintJob::create(['order_id' => $order->id, 'status' => 'queued', 'receipt_snapshot' => ['total' => 10000]]);

        $this->actingAs($user)->getJson('/api/print-jobs/next')->assertOk()->assertJsonPath('data.id', $job->id);
        $this->actingAs($user)->patchJson('/api/print-jobs/'.$job->id, ['status' => 'failed', 'failure_reason' => 'Printer tidak terhubung.'])->assertOk();
        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'failed', 'failure_reason' => 'Printer tidak terhubung.']);
    }

    public function test_failed_print_job_can_retry_but_printed_job_cannot(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'paid']);
        $failed = PrintJob::create(['order_id' => $order->id, 'status' => 'failed', 'receipt_snapshot' => ['total' => 10000]]);

        $this->actingAs($user)->postJson('/api/print-jobs/'.$failed->id.'/retry')->assertOk();
        $this->assertDatabaseHas('print_jobs', ['id' => $failed->id, 'status' => 'queued']);

        $failed->update(['status' => 'printed']);
        $this->actingAs($user)->postJson('/api/print-jobs/'.$failed->id.'/retry')->assertStatus(422);
    }
}
