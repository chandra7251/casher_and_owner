<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CafeSetting;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

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
        $paid = Order::factory()->create(['status' => 'paid', 'total' => 45000]);
        Payment::create(['order_id' => $paid->id, 'method' => 'cash', 'amount' => 45000, 'received_amount' => 50000, 'change_amount' => 5000, 'idempotency_key' => 'dash-paid', 'paid_at' => now()]);
        Order::factory()->create(['status' => 'expired', 'total' => 22000]);

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
        $category = MenuCategory::factory()->create();
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
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 2]);

        $this->actingAs($cashier)->patchJson('/owner/menu/sizes/'.$size->id, ['price' => 1])->assertForbidden();
    }

    public function test_owner_can_toggle_menu_availability(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'is_available' => true]);

        $this->actingAs($owner)->patchJson('/owner/menu/items/'.$item->id.'/availability', ['is_available' => false])
            ->assertOk()->assertJsonPath('data.is_available', false);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'is_available' => false]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'menu_availability_updated', 'auditable_id' => $item->id]);
    }

    public function test_cashier_cannot_toggle_menu_availability(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'is_available' => true]);

        $this->actingAs($cashier)->patchJson('/owner/menu/items/'.$item->id.'/availability', ['is_available' => false])->assertForbidden();
    }

    public function test_owner_cannot_lower_physical_stock_below_reserved_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 10, 'reserved' => 4, 'low_stock_threshold' => 2]);

        $this->actingAs($owner)->patchJson('/owner/menu/sizes/'.$size->id, ['price' => 18000, 'on_hand' => 3, 'low_stock_threshold' => 2])->assertStatus(422);
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'on_hand' => 10, 'reserved' => 4]);
    }

    public function test_owner_can_update_table_name_and_availability(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $table = Table::factory()->create(['name' => 'Meja Lama', 'status' => 'available']);

        $this->actingAs($owner)->patchJson('/owner/tables/'.$table->id, ['name' => 'Meja VIP', 'status' => 'available'])
            ->assertOk()->assertJsonPath('data.name', 'Meja VIP');
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'name' => 'Meja VIP', 'status' => 'available']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'table_updated', 'auditable_type' => Table::class, 'auditable_id' => $table->id]);
    }

    public function test_cashier_cannot_update_table(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $table = Table::factory()->create();

        $this->actingAs($cashier)->patchJson('/owner/tables/'.$table->id, ['name' => 'Breach'])->assertForbidden();
    }

    public function test_owner_can_view_table_management_data(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Table::factory()->create(['name' => 'Meja A', 'status' => 'available']);

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

    public function test_cashier_workspace_hides_sizes_without_available_stock(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        CafeSetting::create(['name' => 'Kedai Test']);
        $item = MenuItem::factory()->create(['name' => 'Kopi Susu', 'is_available' => true]);
        MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 18000, 'on_hand' => 2, 'reserved' => 0, 'low_stock_threshold' => 1]);
        MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Large', 'price' => 22000, 'on_hand' => 2, 'reserved' => 2, 'low_stock_threshold' => 1]);

        $response = $this->actingAs($cashier)->get('/cashier/orders');
        $response->assertOk();
        $page = $response->viewData('page');
        $products = collect($page['props']['products']);

        $this->assertSame(['Regular' => 18000], $products->firstWhere('id', $item->id)['sizes']);
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

    public function test_owner_can_create_update_and_delete_menu_item(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = MenuCategory::factory()->create();
        $created = $this->actingAs($owner)->postJson('/owner/menu/items', [
            'menu_category_id' => $category->id, 'name' => 'Kopi Gula Aren', 'is_available' => true,
            'sizes' => [
                ['size' => 'Regular', 'price' => 18000, 'on_hand' => 10, 'low_stock_threshold' => 2],
                ['size' => 'Large', 'price' => 22000, 'on_hand' => 8, 'low_stock_threshold' => 2],
            ],
        ])->assertCreated()->assertJsonPath('data.name', 'Kopi Gula Aren');
        $itemId = $created->json('data.id');
        $this->assertDatabaseHas('menu_items', ['id' => $itemId, 'name' => 'Kopi Gula Aren']);
        $this->actingAs($owner)->patchJson('/owner/menu/items/'.$itemId, ['menu_category_id' => $category->id, 'name' => 'Kopi Gula Aren Large', 'is_available' => false])->assertOk()->assertJsonPath('data.name', 'Kopi Gula Aren Large');
        $this->actingAs($owner)->deleteJson('/owner/menu/items/'.$itemId)->assertOk();
        $this->assertDatabaseMissing('menu_items', ['id' => $itemId]);
    }

    public function test_owner_cannot_create_menu_item_without_both_sizes(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = MenuCategory::factory()->create();

        $response = $this->actingAs($owner)->withHeader('Accept', 'application/json')->postJson('/owner/menu/items', [
            'menu_category_id' => $category->id,
            'name' => 'Menu Tanpa Large',
            'sizes' => [
                ['size' => 'Regular', 'price' => 15000, 'on_hand' => 5, 'low_stock_threshold' => 1],
            ],
        ]);

        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('sizes', session('errors.default.messages'));
    }

    public function test_cashier_cannot_create_menu_item(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = MenuCategory::factory()->create();
        $this->actingAs($cashier)->postJson('/owner/menu/items', ['menu_category_id' => $category->id, 'name' => 'Tidak Boleh', 'sizes' => [['size' => 'Regular', 'price' => 10000, 'on_hand' => 1, 'low_stock_threshold' => 0]]])->assertForbidden();
    }

    public function test_cashier_can_upload_menu_photo(): void
    {
        Storage::fake('public');
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);

        $response = $this->actingAs($cashier)->post('/cashier/menu/items/'.$item->id.'/photo', [
            'photo' => UploadedFile::fake()->image('menu-cashier.jpg', 400, 400),
        ]);

        $response->assertOk()->assertJsonPath('data.photo_path', fn ($value) => is_string($value) && $value !== '');
        Storage::disk('public')->assertExists($response->json('data.photo_path'));
    }

    public function test_owner_can_upload_menu_photo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);

        $response = $this->actingAs($owner)->post('/owner/menu/items/'.$item->id.'/photo', [
            'photo' => UploadedFile::fake()->image('menu.jpg', 400, 400),
        ]);

        $response->assertOk()->assertJsonPath('data.photo_path', fn ($value) => is_string($value) && $value !== '');
        Storage::disk('public')->assertExists($response->json('data.photo_path'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'menu_photo_updated', 'auditable_type' => MenuItem::class, 'auditable_id' => $item->id]);
    }

    public function test_owner_can_upload_cafe_logo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        CafeSetting::create(['name' => 'Kedai Logo']);
        $response = $this->actingAs($owner)->post('/owner/settings', [
            'name' => 'Kedai Logo', 'address' => '', 'phone' => '', 'thank_you_message' => '',
            'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
        ]);
        $response->assertOk();
        $path = $response->json('data.logo_path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_owner_can_update_cafe_currency(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        CafeSetting::create(['name' => 'Kedai Currency', 'currency' => 'IDR']);

        $this->actingAs($owner)->post('/owner/settings', [
            'name' => 'Kedai Currency',
            'currency' => 'USD',
            'address' => '',
            'phone' => '',
            'thank_you_message' => '',
        ])->assertOk()->assertJsonPath('data.currency', 'USD');

        $this->assertDatabaseHas('cafe_settings', ['name' => 'Kedai Currency', 'currency' => 'USD']);
    }

    public function test_cashier_cannot_update_cafe_currency(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        CafeSetting::create(['name' => 'Kedai Currency', 'currency' => 'IDR']);

        $this->actingAs($cashier)->postJson('/owner/settings', [
            'name' => 'Kedai Currency',
            'currency' => 'USD',
        ])->assertForbidden();
    }
}
