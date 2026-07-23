<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMenuAvailabilityRequest;
use App\Http\Requests\UpdateMenuSizeRequest;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OwnerMenuController extends Controller
{
    public function index(): Response
    {
        $items = MenuItem::query()->with(['sizes' => fn ($query) => $query->orderBy('size')])->orderBy('name')->get();
        return Inertia::render('Owner/Menu', ['items' => $items->map(fn ($item) => [
            'id' => $item->id, 'name' => $item->name, 'is_available' => $item->is_available,
            'sizes' => $item->sizes->map(fn ($size) => ['id' => $size->id, 'size' => $size->size, 'price' => $size->price, 'on_hand' => $size->on_hand, 'reserved' => $size->reserved, 'low_stock_threshold' => $size->low_stock_threshold])->values(),
        ])->values()]);
    }

    public function updateSize(UpdateMenuSizeRequest $request, MenuItemSize $menuItemSize): JsonResponse
    {
        $size = DB::transaction(function () use ($request, $menuItemSize): MenuItemSize {
            $size = MenuItemSize::query()->lockForUpdate()->findOrFail($menuItemSize->id);
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

    public function updateAvailability(UpdateMenuAvailabilityRequest $request, MenuItem $menuItem): JsonResponse
    {
        $before = $menuItem->only(['is_available']);
        $menuItem->update($request->validated());
        $fresh = $menuItem->fresh();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'menu_availability_updated', 'auditable_type' => MenuItem::class, 'auditable_id' => $fresh->id, 'before' => $before, 'after' => $fresh->only(['is_available'])]);
        return response()->json(['data' => $fresh]);
    }
}
