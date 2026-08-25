<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request): Order {
            $order = Order::create(['number' => 'ORD-'.str()->upper(str()->random(8)), 'user_id' => $request->user()->id, 'table_id' => $request->integer('table_id'), 'status' => 'awaiting_payment', 'payment_expires_at' => now()->addSeconds(60)]);
            $total = 0;
            foreach ($request->validated('items') as $line) {
                $size = MenuItemSize::query()->with('menuItem')->where('menu_item_id', $line['product_id'])->where('size', $line['size'])->lockForUpdate()->firstOrFail();
                abort_if(! $size->menuItem->is_available, 422, 'Menu sedang tidak tersedia.');
                $available = $size->on_hand - $size->reserved;
                abort_if($available < $line['quantity'], 422, 'Stok tidak cukup.');
                $lineTotal = $size->price * $line['quantity'];
                $order->items()->create(['menu_item_id' => $size->menu_item_id, 'name_snapshot' => $size->menuItem->name, 'size' => $size->size, 'unit_price' => $size->price, 'quantity' => $line['quantity'], 'line_total' => $lineTotal]);
                StockReservation::create(['order_id' => $order->id, 'menu_item_size_id' => $size->id, 'quantity' => $line['quantity'], 'status' => 'reserved', 'expires_at' => $order->payment_expires_at]);
                $beforeReserved = $size->reserved;
                $size->increment('reserved', $line['quantity']);
                StockMovement::create(['menu_item_size_id' => $size->id, 'user_id' => $request->user()->id, 'type' => 'reserve', 'quantity' => $line['quantity'], 'before_on_hand' => $size->on_hand, 'after_on_hand' => $size->on_hand, 'before_reserved' => $beforeReserved, 'after_reserved' => $beforeReserved + $line['quantity'], 'reference_type' => Order::class, 'reference_id' => $order->id]);
                $total += $lineTotal;
            }
            $order->update(['total' => $total]);

            return $order->load('items');
        });

        return response()->json(['data' => ['id' => $order->id, 'number' => $order->number, 'total' => $order->total, 'status' => $order->status]], 201);
    }
}
