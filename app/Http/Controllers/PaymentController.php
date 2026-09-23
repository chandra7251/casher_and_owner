<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\AuditLog;
use App\Models\CafeSetting;
use App\Models\MenuItemSize;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Services\ReceiptRenderer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function receipt(Request $request, Order $order): Response
    {
        $this->authorize('viewReceipt', $order);
        abort_unless($order->status === 'paid', 422, 'Struk hanya tersedia untuk order paid.');
        $order->load(['items', 'table', 'payment', 'user']);
        $cafe = CafeSetting::query()->first() ?? new CafeSetting(['name' => 'Cafe', 'thank_you_message' => 'Terima kasih.']);
        $snapshot = app(ReceiptRenderer::class)->snapshot($order, $cafe, $order->user?->name ?? '-', $order->payment->method, $order->payment->received_amount, $order->payment->change_amount);

        return response(app(ReceiptRenderer::class)->render($snapshot), 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'inline; filename="'.$order->number.'.escpos"']);
    }

    public function store(PaymentRequest $request, Order $order): JsonResponse
    {
        $this->authorize('pay', $order);
        $data = $request->validated();
        $expired = false;
        $userId = $request->user()->id;
        $payment = DB::transaction(function () use ($data, $order, &$expired, $userId): ?Payment {
            $order = Order::query()->with(['items', 'table'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'paid' && $order->payment) {
                abort_if($order->payment->idempotency_key !== $data['idempotency_key'], 409, 'Pesanan sudah dibayar.');

                return $order->payment;
            }
            if ($order->status !== 'awaiting_payment' || ($order->payment_expires_at && now()->greaterThanOrEqualTo($order->payment_expires_at))) {
                if ($order->status === 'awaiting_payment') {
                    foreach ($order->items as $item) {
                        $size = MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->first();
                        if ($size) {
                            $beforeReserved = $size->reserved;
                            abort_if($beforeReserved < $item->quantity, 422, 'Reservasi stok order tidak valid.');
                            $size->decrement('reserved', $item->quantity);
                            StockMovement::create(['menu_item_size_id' => $size->id, 'user_id' => $userId, 'type' => 'release', 'quantity' => -$item->quantity, 'before_on_hand' => $size->on_hand, 'after_on_hand' => $size->on_hand, 'before_reserved' => $beforeReserved, 'after_reserved' => $beforeReserved - $item->quantity, 'reference_type' => Order::class, 'reference_id' => $order->id]);
                            StockReservation::query()->where('order_id', $order->id)->where('menu_item_size_id', $size->id)->where('status', 'reserved')->lockForUpdate()->first()?->update(['status' => 'released', 'released_at' => now()]);
                        }
                    }
                    $order->update(['status' => 'expired']);
                }
                $expired = true;

                return null;
            }
            if ($data['method'] === 'qris_manual' && ! ($data['validated'] ?? false)) {
                throw new HttpResponseException(response()->json(['message' => 'Validasi QRIS manual wajib dikonfirmasi kasir.', 'errors' => ['validated' => ['Validasi QRIS manual wajib dikonfirmasi kasir.']]], 422));
            }
            $total = (int) $order->items->sum('line_total');
            if ($data['received_amount'] < $total) {
                throw new HttpResponseException(response()->json(['message' => 'Jumlah diterima kurang dari total pesanan.', 'errors' => ['received_amount' => ['Jumlah diterima kurang dari total pesanan.']]], 422));
            }
            $payment = Payment::query()->where('order_id', $order->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($payment) {
                return $payment;
            }
            abort_if(Payment::query()->where('order_id', $order->id)->exists(), 409, 'Pesanan sudah memiliki pembayaran.');
            $payment = Payment::create([
                'order_id' => $order->id, 'status' => 'paid', 'method' => $data['method'], 'amount' => $total, 'idempotency_key' => $data['idempotency_key'],
                'received_amount' => $data['received_amount'], 'change_amount' => $data['method'] === 'cash' ? $data['received_amount'] - $total : 0, 'paid_at' => now(),
            ]);
            foreach ($order->items as $item) {
                $size = MenuItemSize::query()->where('menu_item_id', $item->menu_item_id)->where('size', $item->size)->lockForUpdate()->first();
                if ($size) {
                    $beforeOnHand = $size->on_hand;
                    $beforeReserved = $size->reserved;
                    $size->update(['on_hand' => $beforeOnHand - $item->quantity, 'reserved' => $beforeReserved - $item->quantity]);
                    StockMovement::create(['menu_item_size_id' => $size->id, 'user_id' => $userId, 'type' => 'consume', 'quantity' => -$item->quantity, 'before_on_hand' => $beforeOnHand, 'after_on_hand' => $size->on_hand, 'before_reserved' => $beforeReserved, 'after_reserved' => $size->reserved, 'reference_type' => Order::class, 'reference_id' => $order->id]);
                    StockReservation::query()->where('order_id', $order->id)->where('menu_item_size_id', $size->id)->where('status', 'reserved')->lockForUpdate()->first()?->update(['status' => 'consumed', 'consumed_at' => now()]);
                }
            }
            $receipt = app(ReceiptRenderer::class)->snapshot($order->load('table'), (CafeSetting::query()->first() ?? new CafeSetting(['name' => 'Cafe', 'thank_you_message' => 'Terima kasih.'])), auth()->user()->name, $data['method'], $data['received_amount'], $data['method'] === 'cash' ? $data['received_amount'] - $total : 0);
            PrintJob::create(['order_id' => $order->id, 'status' => 'queued', 'receipt_snapshot' => $receipt]);
            $order->update(['status' => 'paid', 'total' => $total]);
            AuditLog::create(['user_id' => $userId, 'action' => 'payment_confirmed', 'auditable_type' => Order::class, 'auditable_id' => $order->id, 'before' => ['status' => 'awaiting_payment'], 'after' => ['status' => 'paid', 'payment_id' => $payment->id, 'amount' => $total]]);

            return $payment;
        });
        if ($expired) {
            throw new HttpResponseException(response()->json(['message' => 'Pesanan sudah kedaluwarsa atau tidak dapat dibayar.', 'errors' => ['order' => ['Pesanan sudah kedaluwarsa atau tidak dapat dibayar.']]], 422));
        }

        return response()->json(['data' => ['total' => $payment->amount, 'change' => $payment->change_amount, 'method' => $payment->method, 'status' => 'paid']]);
    }
}
