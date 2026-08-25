<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrderRequest;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\StockReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderUpdateController extends Controller
{
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);
        $data = $request->validated();
        $updated = DB::transaction(function () use ($data, $order) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            abort_if($order->status !== 'awaiting_payment', 422, 'Order tidak dapat diedit.');
            foreach ($order->items as $item) {
                MenuItemSize::where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->decrement('reserved', $item->quantity);
                StockReservation::query()->where('order_id', $order->id)->where('menu_item_size_id', MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->value('id'))->where('status', 'reserved')->update(['status' => 'released', 'released_at' => now()]);
            } $order->items()->delete();
            $total = 0;
            foreach ($data['items'] as $line) {
                $size = MenuItemSize::with('menuItem')->where('menu_item_id', $line['product_id'])->where('size', $line['size'])->lockForUpdate()->firstOrFail();
                abort_if(! $size->menuItem->is_available, 422, 'Menu sedang tidak tersedia.');
                abort_if($size->on_hand - $size->reserved < $line['quantity'], 422, 'Stok tidak cukup.');
                $lineTotal = $size->price * $line['quantity'];
                $order->items()->create(['menu_item_id' => $size->menu_item_id, 'name_snapshot' => $size->menuItem->name, 'size' => $size->size, 'unit_price' => $size->price, 'quantity' => $line['quantity'], 'line_total' => $lineTotal]);
                StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => $line['quantity'], 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);
                $size->increment('reserved', $line['quantity']);
                $total += $lineTotal;
            } $order->update(['table_id' => $data['table_id'], 'total' => $total]);

            return $order->load('items');
        });

        return response()->json(['data' => ['id' => $updated->id, 'total' => $updated->total, 'status' => $updated->status]]);
    }
}
