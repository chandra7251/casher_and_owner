<?php

namespace Tests\Feature;

use App\Models\CafeSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User { return User::factory()->create(['role' => 'owner']); }
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
}
