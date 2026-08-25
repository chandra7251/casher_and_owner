<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class OwnerReportController extends Controller
{
    public function index(Request $request): \Inertia\Response|JsonResponse
    {
        $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date'], 'status' => ['nullable', 'in:paid,expired']]);

        $status = $request->input('status', 'paid');
        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();

        $query = Order::query()
            ->with(['payment', 'table:id,name', 'user:id,name', 'items:order_id,name_snapshot,size,unit_price,quantity,line_total'])
            ->where('orders.status', $status);

        if ($status === 'paid') {
            $query->whereHas('payment', fn ($q) => $q->where('status', 'paid')->whereBetween('paid_at', [$from, $to]));
        } else {
            $query->whereBetween('orders.payment_expires_at', [$from, $to]);
        }

        $totalOrders = (clone $query)->count();
        $summary = (clone $query)->leftJoin('payments', 'payments.order_id', '=', 'orders.id')->selectRaw("COALESCE(SUM(orders.total), 0) as revenue, COALESCE(SUM(CASE WHEN payments.method = 'cash' THEN orders.total ELSE 0 END), 0) as cash_total, COALESCE(SUM(CASE WHEN payments.method = 'qris_manual' THEN orders.total ELSE 0 END), 0) as qris_total")->first();
        $orders = $query->orderByDesc('id')->forPage(max(1, (int) $request->input('page', 1)), 50)->get();

        $meta = ['total_orders' => $totalOrders, 'total_revenue' => (int) $summary->revenue, 'cash_total' => (int) $summary->cash_total, 'qris_total' => (int) $summary->qris_total, 'current_page' => max(1, (int) $request->input('page', 1)), 'per_page' => 50, 'last_page' => max(1, (int) ceil($totalOrders / 50)), 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'status' => $status];

        if ($request->expectsJson()) {
            return response()->json(['data' => $orders, 'meta' => $meta]);
        }

        return Inertia::render('Owner/Reports', ['orders' => $orders, 'meta' => $meta, 'filters' => ['from' => $request->input('from'), 'to' => $request->input('to'), 'status' => $status]]);
    }

    public function pdf(Request $request): Response
    {
        $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date'], 'status' => ['nullable', 'in:paid,expired']]);
        $status = $request->input('status', 'paid');
        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();
        $orders = Order::query()->with(['payment', 'table:id,name', 'user:id,name', 'items:order_id,name_snapshot,size,quantity,unit_price,line_total'])->where('orders.status', $status)->when($status === 'paid', fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'paid')->whereBetween('paid_at', [$from, $to])))->when($status === 'expired', fn ($q) => $q->whereBetween('orders.payment_expires_at', [$from, $to]))->orderByDesc('id')->get();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'report_exported', 'auditable_type' => 'report', 'auditable_id' => 0, 'before' => null, 'after' => ['format' => 'pdf', 'status' => $status, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'count' => $orders->count()]]);
        $pdf = Pdf::loadView('reports.pdf', compact('orders', 'status', 'from', 'to'))->setPaper('a4', 'portrait');

        return $pdf->download('laporan-'.$status.'-'.$from->toDateString().'-'.$to->toDateString().'.pdf');
    }

    public function csv(Request $request): Response
    {
        $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date'], 'status' => ['nullable', 'in:paid,expired']]);

        $status = $request->input('status', 'paid');
        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();

        $query = Order::query()
            ->with(['payment', 'table:id,name', 'user:id,name', 'items:order_id,name_snapshot,size,quantity,unit_price,line_total'])
            ->where('orders.status', $status);

        if ($status === 'paid') {
            $query->whereHas('payment', fn ($q) => $q->where('status', 'paid')->whereBetween('paid_at', [$from, $to]));
        } else {
            $query->whereBetween('orders.payment_expires_at', [$from, $to]);
        }

        $orders = $query->orderByDesc('id')->get();

        $rows = collect([['Nomor Order', 'Waktu', 'Kasir', 'Meja', 'Status', 'Total', 'Metode', 'Item', 'Ukuran', 'Qty', 'Harga Satuan', 'Subtotal']])
            ->concat($orders->flatMap(function ($order) {
                $common = [
                    $order->number,
                    $order->payment?->paid_at?->format('Y-m-d H:i') ?? $order->payment_expires_at?->format('Y-m-d H:i') ?? '-',
                    $order->user?->name ?? '-',
                    $order->table?->name ?? '-',
                    $order->status,
                    $order->total,
                    $order->payment?->method ?? '-',
                ];

                if ($order->items->isEmpty()) {
                    return [array_merge($common, ['-', '-', '-', '-', '-'])];
                }

                return $order->items->map(fn ($item) => array_merge($common, [
                    $item->name_snapshot,
                    $item->size,
                    $item->quantity,
                    $item->unit_price,
                    $item->line_total,
                ]));
            }));
        $csv = $rows->map(fn ($r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $r)))->implode("\n");

        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'report_exported', 'auditable_type' => 'report', 'auditable_id' => 0, 'before' => null, 'after' => ['format' => 'csv', 'status' => $status, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'count' => $orders->count()]]);
        $filename = "laporan-{$status}-{$from->toDateString()}-{$to->toDateString()}.csv";

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }
}
