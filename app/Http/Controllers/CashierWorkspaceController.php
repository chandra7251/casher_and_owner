<?php

namespace App\Http\Controllers;

use App\Models\CafeSetting;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PrintJob;
use App\Models\Table;
use Inertia\Inertia;
use Inertia\Response;

class CashierWorkspaceController extends Controller
{
    public function __invoke(): Response
    {
        $settings = CafeSetting::query()->firstOrFail();
        $categories = MenuCategory::query()->orderBy('sort_order')->get(['id', 'name']);
        $products = MenuItem::query()
            ->with(['sizes' => fn ($query) => $query->whereColumn('on_hand', '>', 'reserved')->orderByRaw("FIELD(size, 'Regular', 'Large')")])
            ->where('is_available', true)
            ->whereHas('sizes', fn ($query) => $query->whereColumn('on_hand', '>', 'reserved'))
            ->orderBy('name')
            ->get();
        $tables = Table::query()->withCount(['orders as active_orders_count' => fn ($query) => $query->whereIn('status', ['draft', 'awaiting_payment'])])->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Cashier/Workspace', [
            'cafe' => ['name' => $settings->name, 'currency' => $settings->currency ?? 'IDR', 'address' => $settings->address, 'logoUrl' => $settings->logo_path ? asset('storage/'.$settings->logo_path) : null],
            'user' => ['name' => auth()->user()->name],
            'categories' => $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values(),
            'tables' => $tables->map(fn ($table) => ['id' => $table->id, 'name' => $table->name, 'status' => $table->active_orders_count ? 'occupied' : $table->status, 'activeOrders' => $table->active_orders_count])->values(),
            'failedPrintJobs' => PrintJob::query()->where('status', 'failed')->with('order:id,number')->latest()->limit(5)->get(['id', 'order_id', 'failure_reason']),
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'categoryId' => $product->menu_category_id,
                'photoUrl' => $product->photo_path ? asset('storage/'.$product->photo_path) : null,
                'stock' => $product->sizes->min(fn ($size) => max(0, $size->on_hand - $size->reserved)),
                'sizes' => $product->sizes->mapWithKeys(fn ($size) => [$size->size => $size->price]),
            ])->values(),
        ]);
    }
}
