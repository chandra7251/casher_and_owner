<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function paidOrder(string $paidAt, int $total = 30000): Order
    {
        $table = Table::factory()->create();
        $cashier = User::factory()->create(['role' => 'cashier']);
        $order = Order::create(['number' => uniqid('ORD-'), 'user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'paid', 'total' => $total]);
        Payment::create(['order_id' => $order->id, 'method' => 'cash', 'amount' => $total, 'received_amount' => $total, 'change_amount' => 0, 'idempotency_key' => uniqid(), 'paid_at' => $paidAt]);

        return $order;
    }

    public function test_report_returns_paid_orders_filtered_by_date(): void
    {
        $owner = $this->owner();
        $this->paidOrder('2026-07-01 10:00:00', 25000);
        $this->paidOrder('2026-07-22 12:00:00', 40000);
        $this->paidOrder('2026-08-01 09:00:00', 15000);

        $response = $this->actingAs($owner)->getJson('/owner/reports?from=2026-07-01&to=2026-07-31');

        $response->assertOk()
            ->assertJsonPath('meta.total_revenue', 65000)
            ->assertJsonPath('meta.total_orders', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_report_csv_download_contains_header_and_rows(): void
    {
        $owner = $this->owner();
        $this->paidOrder('2026-07-10 11:00:00', 18000);

        $response = $this->actingAs($owner)->get('/owner/reports/csv?from=2026-07-01&to=2026-07-31');

        $response->assertOk();
        $this->assertStringContainsString('Nomor Order', $response->content());
        $this->assertStringContainsString('18000', $response->content());
    }

    public function test_csv_export_contains_cashier_and_item_details(): void
    {
        $owner = $this->owner();
        $table = Table::factory()->create(['name' => 'Meja Detail']);
        $cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Sari Kasir']);
        $item = MenuItem::factory()->create(['name' => 'Kopi Detail']);
        $order = Order::create(['number' => 'DET-001', 'user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'paid', 'total' => 36000]);
        $order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => 'Kopi Detail', 'size' => 'Large', 'unit_price' => 18000, 'quantity' => 2, 'line_total' => 36000]);
        Payment::create(['order_id' => $order->id, 'method' => 'cash', 'amount' => 36000, 'received_amount' => 36000, 'change_amount' => 0, 'idempotency_key' => 'detail-001', 'paid_at' => '2026-07-10 11:00:00']);

        $response = $this->actingAs($owner)->get('/owner/reports/csv?from=2026-07-01&to=2026-07-31');

        $response->assertOk();
        $this->assertStringContainsString('Sari Kasir', $response->content());
        $this->assertStringContainsString('Kopi Detail', $response->content());
        $this->assertStringContainsString('Large', $response->content());
        $this->assertStringContainsString('2', $response->content());
    }

    public function test_report_exports_create_audit_logs(): void
    {
        $owner = $this->owner();
        $this->paidOrder('2026-08-01 10:00:00', 18000);
        $this->actingAs($owner)->get('/owner/reports/csv?from=2026-08-01&to=2026-08-31')->assertOk();
        $this->actingAs($owner)->get('/owner/reports/pdf?from=2026-08-01&to=2026-08-31')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'report_exported', 'auditable_type' => 'report', 'auditable_id' => 0]);
        $this->assertSame(2, AuditLog::query()->where('user_id', $owner->id)->where('action', 'report_exported')->count());
    }

    public function test_cashier_cannot_access_reports(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->getJson('/owner/reports?from=2026-07-01&to=2026-07-31')->assertForbidden();
    }

    public function test_report_returns_expired_orders_in_same_range(): void
    {
        $owner = $this->owner();
        $table = Table::factory()->create();
        $cashier = User::factory()->create(['role' => 'cashier']);
        Order::create(['number' => 'EXP-001', 'user_id' => $cashier->id, 'table_id' => $table->id, 'status' => 'expired', 'total' => 12000, 'payment_expires_at' => '2026-07-15 10:00:00']);

        $response = $this->actingAs($owner)->getJson('/owner/reports?from=2026-07-01&to=2026-07-31&status=expired');

        $response->assertOk()->assertJsonPath('meta.total_orders', 1);
    }

    public function test_owner_can_download_pdf_report(): void
    {
        $owner = $this->owner();
        $this->paidOrder('2026-08-01 10:00:00', 18000);
        $response = $this->actingAs($owner)->get('/owner/reports/pdf?from=2026-08-01&to=2026-08-31');
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_report_pagination_metadata_returned_correctly(): void
    {
        $owner = $this->owner();
        $this->paidOrder('2026-07-15 10:00:00', 10000);

        $response = $this->actingAs($owner)->getJson('/owner/reports?from=2026-07-01&to=2026-07-31&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.total_orders', 1);
    }

    public function test_report_page_2_returns_empty_when_only_one_page(): void
    {
        $owner = $this->owner();
        $this->paidOrder('2026-07-15 10:00:00', 10000);

        $response = $this->actingAs($owner)->getJson('/owner/reports?from=2026-07-01&to=2026-07-31&page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total_orders', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonCount(0, 'data');
    }

    public function test_report_aggregation_includes_all_pages_not_just_current(): void
    {
        $owner = $this->owner();
        for ($i = 1; $i <= 3; $i++) {
            $this->paidOrder('2026-07-0'.$i.' 10:00:00', 20000);
        }

        $response = $this->actingAs($owner)->getJson('/owner/reports?from=2026-07-01&to=2026-07-31&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total_orders', 3)
            ->assertJsonPath('meta.total_revenue', 60000);
    }

    public function test_report_invalid_page_below_one_clamped_to_page_1(): void
    {
        $owner = $this->owner();
        $this->paidOrder('2026-07-15 10:00:00', 10000);

        $response = $this->actingAs($owner)->getJson('/owner/reports?from=2026-07-01&to=2026-07-31&page=0');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_report_zero_results_returns_first_page_metadata(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->getJson('/owner/reports?from=2020-01-01&to=2020-01-31');

        $response->assertOk()
            ->assertJsonPath('meta.total_orders', 0)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonCount(0, 'data');
    }
}
