<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\MenuItemSize;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();
        $expired = false;
        $payment = DB::transaction(function () use ($data, $order, &$expired): ?Payment {
            $order = Order::query()->with(['items', 'table'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'paid' && $order->payment) return $order->payment;
            if ($order->status !== 'awaiting_payment' || ($order->payment_expires_at && now()->greaterThanOrEqualTo($order->payment_expires_at))) {
                if ($order->status === 'awaiting_payment') {
                    foreach ($order->items as $item) {
                        MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->decrement('reserved', $item->quantity);
                    }
                    $order->update(['status' => 'expired']);
                }
                $expired = true;
                return null;
            }
            if ($data['method'] === 'qris_manual' && !($data['validated'] ?? false)) throw new HttpResponseException(response()->json(['message' => 'Validasi QRIS manual wajib dikonfirmasi kasir.', 'errors' => ['validated' => ['Validasi QRIS manual wajib dikonfirmasi kasir.']]], 422));
            $total = (int) $order->items->sum('line_total');
            if ($data['received_amount'] < $total) throw new HttpResponseException(response()->json(['message' => 'Jumlah diterima kurang dari total pesanan.', 'errors' => ['received_amount' => ['Jumlah diterima kurang dari total pesanan.']]], 422));
            $payment = Payment::query()->where('order_id', $order->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($payment) return $payment;
            $payment = Payment::create([
                'order_id' => $order->id, 'method' => $data['method'], 'amount' => $total, 'idempotency_key' => $data['idempotency_key'],
                'received_amount' => $data['received_amount'], 'change_amount' => $data['method'] === 'cash' ? $data['received_amount'] - $total : 0, 'paid_at' => now(),
            ]);
            foreach ($order->items as $item) {
                MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->update([
                    'on_hand' => DB::raw('on_hand - '.$item->quantity),
                    'reserved' => DB::raw('reserved - '.$item->quantity),
                ]);
            }
            PrintJob::create(['order_id' => $order->id, 'status' => 'queued', 'receipt_snapshot' => [
                'order_number' => $order->number,
                'table' => optional($order->table)->name,
                'items' => $order->items->map(fn ($item) => ['name' => $item->name_snapshot, 'size' => $item->size, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'line_total' => $item->line_total])->values()->all(),
                'total' => $total,
                'payment_method' => $data['method'],
            ]]);
            $order->update(['status' => 'paid', 'total' => $total]);
            return $payment;
        });
        if ($expired) throw new HttpResponseException(response()->json(['message' => 'Pesanan sudah kedaluwarsa atau tidak dapat dibayar.', 'errors' => ['order' => ['Pesanan sudah kedaluwarsa atau tidak dapat dibayar.']]], 422));
        return response()->json(['data' => ['total' => $payment->amount, 'change' => $payment->change_amount, 'method' => $payment->method, 'status' => 'paid']]);
    }
}
