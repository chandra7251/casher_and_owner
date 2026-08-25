<?php

namespace App\Console\Commands;

use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Expire unpaid orders and release reserved stock';

    public function handle(): int
    {
        $count = 0;
        Order::query()->where('status', 'awaiting_payment')->whereNotNull('payment_expires_at')->where('payment_expires_at', '<=', now())->pluck('id')->each(function ($id) use (&$count) {
            DB::transaction(function () use ($id, &$count) {
                $order = Order::query()->with('items')->lockForUpdate()->find($id);
                if (! $order || $order->status !== 'awaiting_payment') {
                    return;
                } foreach ($order->items as $item) {
                    $size = MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->firstOrFail();
                    $before = $size->reserved;
                    abort_if($before < $item->quantity, 422, 'Reservasi stok order tidak valid.');
                    $size->decrement('reserved', $item->quantity);
                    StockMovement::create(['menu_item_size_id' => $size->id, 'type' => 'release', 'quantity' => -$item->quantity, 'before_on_hand' => $size->on_hand, 'after_on_hand' => $size->on_hand, 'before_reserved' => $before, 'after_reserved' => $before - $item->quantity, 'reference_type' => Order::class, 'reference_id' => $order->id]);
                    StockReservation::query()->where('order_id', $order->id)->where('menu_item_size_id', $size->id)->where('status', 'reserved')->lockForUpdate()->first()?->update(['status' => 'released', 'released_at' => now()]);
                } $order->update(['status' => 'expired']);
                $count++;
            });
        });
        $this->info("Expired {$count} order(s).");

        return self::SUCCESS;
    }
}
