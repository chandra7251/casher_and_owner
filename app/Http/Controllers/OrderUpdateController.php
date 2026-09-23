<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrderRequest;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderUpdateController extends Controller
{
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);
        $data = $request->validated();
        $expired = false;
        $userId = $request->user()->id;

        $updated = DB::transaction(function () use ($data, $order, $userId, &$expired): ?Order {
            $order = Order::with('items')->lockForUpdate()->findOrFail($order->id);

            // ponytail: inline expiration release here and in PaymentController. Extract to OrderExpirationService when third consumer appears.
            if ($order->status !== 'awaiting_payment' || ($order->payment_expires_at && now()->greaterThanOrEqualTo($order->payment_expires_at))) {
                if ($order->status === 'awaiting_payment') {
                    foreach ($order->items as $item) {
                        $size = MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->first();
                        if ($size) {
                            $beforeReserved = $size->reserved;
                            abort_if($beforeReserved < $item->quantity, 422, 'Reservasi stok order tidak valid.');
                            $size->decrement('reserved', $item->quantity);
                            StockMovement::create([
                                'menu_item_size_id' => $size->id,
                                'user_id' => $userId,
                                'type' => 'release',
                                'quantity' => -$item->quantity,
                                'before_on_hand' => $size->on_hand,
                                'after_on_hand' => $size->on_hand,
                                'before_reserved' => $beforeReserved,
                                'after_reserved' => $beforeReserved - $item->quantity,
                                'reference_type' => Order::class,
                                'reference_id' => $order->id,
                            ]);
                            StockReservation::query()->where('order_id', $order->id)->where('menu_item_size_id', $size->id)->where('status', 'reserved')->lockForUpdate()->first()?->update(['status' => 'released', 'released_at' => now()]);
                        }
                    }
                    $order->update(['status' => 'expired']);
                }
                $expired = true;

                return null;
            }

            foreach ($order->items as $item) {
                $size = MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->first();
                if ($size) {
                    $beforeReserved = $size->reserved;
                    abort_if($beforeReserved < $item->quantity, 422, 'Reservasi stok order tidak valid.');
                    $size->decrement('reserved', $item->quantity);
                    StockMovement::create([
                        'menu_item_size_id' => $size->id,
                        'user_id' => $userId,
                        'type' => 'release',
                        'quantity' => -$item->quantity,
                        'before_on_hand' => $size->on_hand,
                        'after_on_hand' => $size->on_hand,
                        'before_reserved' => $beforeReserved,
                        'after_reserved' => $beforeReserved - $item->quantity,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                    ]);
                    StockReservation::query()->where('order_id', $order->id)->where('menu_item_size_id', $size->id)->where('status', 'reserved')->lockForUpdate()->first()?->update(['status' => 'released', 'released_at' => now()]);
                }
            }
            $order->items()->delete();

            $total = 0;
            foreach ($data['items'] as $line) {
                $size = MenuItemSize::with('menuItem')->where('menu_item_id', $line['product_id'])->where('size', $line['size'])->lockForUpdate()->firstOrFail();
                abort_if(! $size->menuItem->is_available, 422, 'Menu sedang tidak tersedia.');
                abort_if($size->on_hand - $size->reserved < $line['quantity'], 422, 'Stok tidak cukup.');

                $lineTotal = $size->price * $line['quantity'];
                $order->items()->create([
                    'menu_item_id' => $size->menu_item_id,
                    'name_snapshot' => $size->menuItem->name,
                    'size' => $size->size,
                    'unit_price' => $size->price,
                    'quantity' => $line['quantity'],
                    'line_total' => $lineTotal,
                ]);

                StockReservation::updateOrCreate(
                    ['order_id' => $order->id, 'menu_item_size_id' => $size->id],
                    [
                        'quantity' => $line['quantity'],
                        'status' => 'reserved',
                        'expires_at' => $order->payment_expires_at,
                        'released_at' => null,
                        'consumed_at' => null,
                    ]
                );

                $beforeReserved = $size->reserved;
                $size->increment('reserved', $line['quantity']);
                StockMovement::create([
                    'menu_item_size_id' => $size->id,
                    'user_id' => $userId,
                    'type' => 'reserve',
                    'quantity' => $line['quantity'],
                    'before_on_hand' => $size->on_hand,
                    'after_on_hand' => $size->on_hand,
                    'before_reserved' => $beforeReserved,
                    'after_reserved' => $beforeReserved + $line['quantity'],
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                ]);

                $total += $lineTotal;
            }

            $order->update(['table_id' => $data['table_id'], 'total' => $total]);

            return $order->load('items');
        });

        if ($expired) {
            throw new HttpResponseException(response()->json(['message' => 'Pesanan sudah kedaluwarsa atau tidak dapat diedit.', 'errors' => ['order' => ['Pesanan sudah kedaluwarsa atau tidak dapat diedit.']]], 422));
        }

        return response()->json(['data' => ['id' => $updated->id, 'total' => $updated->total, 'status' => $updated->status]]);
    }
}
