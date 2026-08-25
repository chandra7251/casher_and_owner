<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2d211b; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #d9cbb8; padding: 6px; text-align: left; }
        th { background: #f1d4bd; }
    </style>
</head>
<body>
    <h1>Laporan Transaksi</h1>
    <div>{{ ucfirst($status) }} · {{ $from->toDateString() }} sampai {{ $to->toDateString() }}</div>
    <table>
        <thead>
            <tr>
                <th>Order</th><th>Waktu</th><th>Kasir</th><th>Meja</th><th>Status</th>
                <th>Item</th><th>Ukuran</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Metode</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                @forelse ($order->items as $item)
                    <tr>
                        <td>{{ $order->number }}</td>
                        <td>{{ $order->payment?->paid_at?->format('Y-m-d H:i') ?? $order->payment_expires_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>{{ $order->user?->name ?? '-' }}</td>
                        <td>{{ $order->table?->name ?? '-' }}</td>
                        <td>{{ $order->status }}</td>
                        <td>{{ $item->name_snapshot }}</td>
                        <td>{{ $item->size }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                        <td>{{ $order->payment?->method ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>{{ $order->number }}</td><td>{{ $order->payment?->paid_at?->format('Y-m-d H:i') ?? $order->payment_expires_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>{{ $order->user?->name ?? '-' }}</td><td>{{ $order->table?->name ?? '-' }}</td><td>{{ $order->status }}</td>
                        <td colspan="5">-</td><td>{{ $order->payment?->method ?? '-' }}</td>
                    </tr>
                @endforelse
            @endforeach
        </tbody>
    </table>
</body>
</html>