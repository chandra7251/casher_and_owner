<?php

namespace App\Http\Controllers;

use App\Models\CafeSetting;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Table;
use Inertia\Inertia;
use Inertia\Response;

class CashierWorkspaceController extends Controller
{
    public function __invoke(): Response
    {
        $settings = CafeSetting::query()->firstOrFail();
        $categories = MenuCategory::query()->orderBy('sort_order')->get(['id', 'name']);
        $products = MenuItem::query()->with(['sizes' => fn ($query) => $query->orderByRaw("FIELD(size, 'Regular', 'Large')")])->where('is_available', true)->orderBy('name')->get();
        $tables = Table::query()->withCount(['orders as active_orders_count' => fn ($query) => $query->whereIn('status', ['draft', 'awaiting_payment'])])->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Cashier/Workspace', [
            'cafe' => $settings->only(['name', 'address']),
            'user' => ['name' => auth()->user()->name],
            'categories' => $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values(),
            'tables' => $tables->map(fn ($table) => ['id' => $table->id, 'name' => $table->name, 'status' => $table->active_orders_count ? 'occupied' : 'available', 'activeOrders' => $table->active_orders_count])->values(),
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'categoryId' => $product->menu_category_id,
                'stock' => $product->sizes->min(fn ($size) => max(0, $size->on_hand - $size->reserved)),
                'sizes' => $product->sizes->mapWithKeys(fn ($size) => [$size->size => $size->price]),
            ])->values(),
        ]);
    }
}
