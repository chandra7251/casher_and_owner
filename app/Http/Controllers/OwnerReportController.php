<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
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
        $from   = Carbon::parse($request->input('from'))->startOfDay();
        $to     = Carbon::parse($request->input('to'))->endOfDay();

        $query = Order::query()
            ->with(['payment', 'table:id,name', 'items:order_id,name_snapshot,size,unit_price,quantity,line_total'])
            ->where('status', $status);

        if ($status === 'paid') {
            $query->whereHas('payment', fn ($q) => $q->whereBetween('paid_at', [$from, $to]));
        } else {
            $query->whereBetween('payment_expires_at', [$from, $to]);
        }

        $orders = $query->orderByDesc('id')->get();

        $meta = ['total_orders' => $orders->count(), 'total_revenue' => (int) $orders->sum('total'), 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'status' => $status];

        if ($request->expectsJson()) return response()->json(['data' => $orders, 'meta' => $meta]);
        return Inertia::render('Owner/Reports', ['orders' => $orders, 'meta' => $meta, 'filters' => ['from' => $request->input('from'), 'to' => $request->input('to'), 'status' => $status]]);
    }

    public function csv(Request $request): Response
    {
        $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date'], 'status' => ['nullable', 'in:paid,expired']]);

        $status = $request->input('status', 'paid');
        $from   = Carbon::parse($request->input('from'))->startOfDay();
        $to     = Carbon::parse($request->input('to'))->endOfDay();

        $query = Order::query()
            ->with(['payment', 'table:id,name'])
            ->where('status', $status);

        if ($status === 'paid') {
            $query->whereHas('payment', fn ($q) => $q->whereBetween('paid_at', [$from, $to]));
        } else {
            $query->whereBetween('payment_expires_at', [$from, $to]);
        }

        $orders = $query->orderByDesc('id')->get();

        $rows = collect([['Nomor Order', 'Meja', 'Status', 'Total', 'Metode', 'Waktu']])
            ->concat($orders->map(fn ($o) => [
                $o->number,
                $o->table?->name ?? '-',
                $o->status,
                $o->total,
                $o->payment?->method ?? '-',
                $o->payment?->paid_at?->format('Y-m-d H:i') ?? $o->payment_expires_at?->format('Y-m-d H:i') ?? '-',
            ]));

        $csv = $rows->map(fn ($r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $r)))->implode("\n");

        $filename = "laporan-{$status}-{$from->toDateString()}-{$to->toDateString()}.csv";

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);;
    }
}
