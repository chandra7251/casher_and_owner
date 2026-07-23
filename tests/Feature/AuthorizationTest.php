<?php

namespace Tests\Feature;

use App\Models\CafeSetting;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\UserRole;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_cashier_workspace(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        CafeSetting::create(['name' => 'Test Cafe']);

        $this->actingAs($cashier)->get('/cashier/orders')->assertOk();
    }

    public function test_owner_login_redirects_to_owner_menu(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'owner-password']);

        $this->post('/login', ['email' => $owner->email, 'password' => 'owner-password'])
            ->assertRedirect('/owner/menu');
    }

    public function test_cashier_cannot_open_owner_menu_route(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get('/owner/menu')->assertForbidden();
    }

    public function test_owner_dashboard_reports_paid_revenue_orders_expired_and_low_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 2, 'reserved' => 0, 'low_stock_threshold' => 3]);
        $paid = \App\Models\Order::factory()->create(['status' => 'paid', 'total' => 45000]);
        \App\Models\Payment::create(['order_id' => $paid->id, 'method' => 'cash', 'amount' => 45000, 'received_amount' => 50000, 'change_amount' => 5000, 'idempotency_key' => 'dash-paid', 'paid_at' => now()]);
        \App\Models\Order::factory()->create(['status' => 'expired', 'total' => 22000]);

        $this->actingAs($owner)->getJson('/owner/dashboard')->assertOk()
            ->assertJsonPath('data.paid_revenue', 45000)
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.expired_orders', 1)
            ->assertJsonPath('data.low_stock.0.id', $size->id);
    }

    public function test_owner_can_open_owner_menu_route(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get('/owner/menu')->assertOk();
    }

    public function test_user_role_uses_allowed_role_values(): void
    {
        $this->assertSame('owner', UserRole::Owner->value);
        $this->assertSame('cashier', UserRole::Cashier->value);
    }

    public function test_owner_can_update_menu_size_price_and_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = \App\Models\MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 2]);

        $this->actingAs($owner)->patchJson('/owner/menu/sizes/'.$size->id, [
            'price' => 21000, 'on_hand' => 25, 'low_stock_threshold' => 5,
        ])->assertOk()->assertJsonPath('data.price', 21000);

        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'price' => 21000, 'on_hand' => 25, 'low_stock_threshold' => 5]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'menu_size_updated', 'auditable_id' => $size->id]);
    }

    public function test_cashier_cannot_update_menu_size(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = \App\Models\MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 2]);

        $this->actingAs($cashier)->patchJson('/owner/menu/sizes/'.$size->id, ['price' => 1])->assertForbidden();
    }

    public function test_owner_can_toggle_menu_availability(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = \App\Models\MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'is_available' => true]);

        $this->actingAs($owner)->patchJson('/owner/menu/items/'.$item->id.'/availability', ['is_available' => false])
            ->assertOk()->assertJsonPath('data.is_available', false);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'is_available' => false]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'menu_availability_updated', 'auditable_id' => $item->id]);
    }

    public function test_cashier_cannot_toggle_menu_availability(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = \App\Models\MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'is_available' => true]);

        $this->actingAs($cashier)->patchJson('/owner/menu/items/'.$item->id.'/availability', ['is_available' => false])->assertForbidden();
    }

    public function test_owner_cannot_lower_physical_stock_below_reserved_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = \App\Models\MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 10, 'reserved' => 4, 'low_stock_threshold' => 2]);

        $this->actingAs($owner)->patchJson('/owner/menu/sizes/'.$size->id, ['price' => 18000, 'on_hand' => 3, 'low_stock_threshold' => 2])->assertStatus(422);
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'on_hand' => 10, 'reserved' => 4]);
    }

    public function test_owner_can_update_table_name_and_availability(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $table = \App\Models\Table::factory()->create(['name' => 'Meja Lama', 'status' => 'available']);

        $this->actingAs($owner)->patchJson('/owner/tables/'.$table->id, ['name' => 'Meja VIP', 'status' => 'available'])
            ->assertOk()->assertJsonPath('data.name', 'Meja VIP');
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'name' => 'Meja VIP', 'status' => 'available']);
    }

    public function test_cashier_cannot_update_table(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = \App\Models\Table::factory()->create();

        $this->actingAs($cashier)->patchJson('/owner/tables/'.$table->id, ['name' => 'Breach'])->assertForbidden();
    }

    public function test_owner_can_view_table_management_data(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        \App\Models\Table::factory()->create(['name' => 'Meja A', 'status' => 'available']);

        $this->actingAs($owner)->getJson('/owner/tables')->assertOk()->assertJsonPath('data.0.name', 'Meja A');
    }

    public function test_owner_can_update_cafe_settings(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $settings = CafeSetting::create(['name' => 'Kedai Lama']);

        $this->actingAs($owner)->patchJson('/owner/settings', [
            'name' => 'Kedai Senja Baru', 'address' => 'Jl. Baru 1', 'phone' => '08123456789', 'thank_you_message' => 'Sampai jumpa.',
        ])->assertOk()->assertJsonPath('data.name', 'Kedai Senja Baru');

        $this->assertDatabaseHas('cafe_settings', ['id' => $settings->id, 'name' => 'Kedai Senja Baru', 'phone' => '08123456789']);
    }

    public function test_cashier_cannot_update_cafe_settings(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        CafeSetting::create(['name' => 'Kedai Aman']);

        $this->actingAs($cashier)->patchJson('/owner/settings', ['name' => 'Hacked'])->assertForbidden();
        $this->assertDatabaseMissing('cafe_settings', ['name' => 'Hacked']);
    }

    public function test_cashier_workspace_exposes_cafe_name_from_db(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        CafeSetting::create(['name' => 'Kedai Kopi Baru']);

        $response = $this->actingAs($cashier)->get('/cashier/orders');
        $response->assertOk();
        $this->assertStringContainsString('Kedai Kopi Baru', $response->content());
    }

    public function test_cashier_workspace_exposes_logged_in_user_name(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Siti Rahayu']);
        CafeSetting::create(['name' => 'Kedai Test']);

        $response = $this->actingAs($cashier)->get('/cashier/orders');
        $response->assertOk();
        $this->assertStringContainsString('Siti Rahayu', $response->content());
    }

    public function test_auth_rate_limiter_is_registered(): void
    {
        // Assert the 'auth' rate limiter is registered (not null).
        $this->assertNotNull(RateLimiter::limiter('auth'));
    }
}