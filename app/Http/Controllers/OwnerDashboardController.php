<?php

namespace App\Http\Controllers;

use App\Models\MenuItemSize;
use App\Models\Order;
use App\Http\Requests\UpdateTableRequest;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OwnerDashboardController extends Controller
{
    public function __invoke(): Response|JsonResponse
    {
        $data = [
            'paid_revenue' => (int) Order::query()->where('status', 'paid')->sum('total'),
            'paid_orders' => Order::query()->where('status', 'paid')->count(),
            'expired_orders' => Order::query()->where('status', 'expired')->count(),
            'low_stock' => MenuItemSize::query()->with('menuItem:id,name')->whereColumn('on_hand', '<=', 'low_stock_threshold')->orderBy('on_hand')->get(['id', 'menu_item_id', 'size', 'on_hand', 'reserved', 'low_stock_threshold']),
        ];

        if (request()->expectsJson()) return response()->json(['data' => $data]);
        return Inertia::render('Owner/Dashboard', ['data' => $data]);
    }
}
