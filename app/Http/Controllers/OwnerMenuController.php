<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuCategoryRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuAvailabilityRequest;
use App\Http\Requests\UpdateMenuCategoryRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Requests\UpdateMenuPhotoRequest;
use App\Http\Requests\UpdateMenuSizeRequest;
use App\Models\AuditLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OwnerMenuController
{
    public function index(): Response
    {
        $items = MenuItem::with(['category', 'sizes'])->orderBy('name')->get();
        $categories = MenuCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'sort_order']);

        return Inertia::render('Owner/Menu', ['items' => $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'menu_category_id' => $i->menu_category_id, 'category' => $i->category?->name, 'photo_url' => $i->photo_path ? asset('storage/'.$i->photo_path) : null, 'is_available' => $i->is_available, 'sizes' => $i->sizes->map(fn ($s) => ['id' => $s->id, 'size' => $s->size, 'price' => $s->price, 'on_hand' => $s->on_hand, 'reserved' => $s->reserved, 'low_stock_threshold' => $s->low_stock_threshold])->values()])->values(), 'categories' => $categories]);
    }

    public function storeCategory(StoreMenuCategoryRequest $request): JsonResponse
    {
        $category = MenuCategory::create($request->validated());

        return response()->json(['data' => $category], 201);
    }

    public function updateCategory(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): JsonResponse
    {
        $menuCategory->update($request->validated());

        return response()->json(['data' => $menuCategory->fresh()]);
    }

    public function destroyCategory(MenuCategory $menuCategory): JsonResponse
    {
        abort_if($menuCategory->items()->exists(), 422, 'Kategori masih memiliki menu.');
        $menuCategory->delete();

        return response()->json(['ok' => true]);
    }

    public function storeItem(StoreMenuItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $item = DB::transaction(function () use ($data) {
            $item = MenuItem::create(['menu_category_id' => $data['menu_category_id'], 'name' => $data['name'], 'is_available' => $data['is_available'] ?? true]);
            foreach ($data['sizes'] as $size) {
                $item->sizes()->create($size);
            }

            return $item->load('sizes');
        });

        return response()->json(['data' => $item], 201);
    }

    public function updateItem(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        $menuItem->update($request->validated());

        return response()->json(['data' => $menuItem->fresh()]);
    }

    public function destroyItem(MenuItem $menuItem): JsonResponse
    {
        abort_if($menuItem->orderItems()->exists(), 422, 'Menu sudah dipakai transaksi. Nonaktifkan menu.');
        $menuItem->delete();

        return response()->json(['ok' => true]);
    }

    public function updateSize(UpdateMenuSizeRequest $request, MenuItemSize $menuItemSize): JsonResponse
    {
        $size = DB::transaction(function () use ($request, $menuItemSize) {
            $size = MenuItemSize::lockForUpdate()->findOrFail($menuItemSize->id);
            $data = $request->validated();
            abort_if($data['on_hand'] < $size->reserved, 422, 'Stok fisik tidak boleh di bawah stok reserved.');
            $before = $size->only(['price', 'on_hand', 'reserved', 'low_stock_threshold']);
            $size->update($data);
            $fresh = $size->fresh();
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'menu_size_updated', 'auditable_type' => MenuItemSize::class, 'auditable_id' => $fresh->id, 'before' => $before, 'after' => $fresh->only(['price', 'on_hand', 'reserved', 'low_stock_threshold'])]);

            return $fresh;
        });

        return response()->json(['data' => $size]);
    }

    public function updatePhoto(UpdateMenuPhotoRequest $request, MenuItem $menuItem): JsonResponse
    {
        $oldPath = $menuItem->photo_path;
        $newPath = $request->file('photo')->store('menu', 'public');
        $menuItem->update(['photo_path' => $newPath]);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'menu_photo_updated',
            'auditable_type' => MenuItem::class,
            'auditable_id' => $menuItem->id,
            'before' => ['photo_path' => $oldPath],
            'after' => ['photo_path' => $newPath],
        ]);
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['data' => ['id' => $menuItem->id, 'photo_path' => $newPath, 'photo_url' => asset('storage/'.$newPath)]]);
    }

    public function updateAvailability(UpdateMenuAvailabilityRequest $request, MenuItem $menuItem): JsonResponse
    {
        $before = $menuItem->only(['is_available']);
        $menuItem->update($request->validated());
        $fresh = $menuItem->fresh();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'menu_availability_updated', 'auditable_type' => MenuItem::class, 'auditable_id' => $fresh->id, 'before' => $before, 'after' => $fresh->only(['is_available'])]);

        return response()->json(['data' => $fresh]);
    }
}
