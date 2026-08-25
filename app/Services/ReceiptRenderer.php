<?php

namespace App\Services;

use App\Models\CafeSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class ReceiptRenderer
{
    public function snapshot(Order $order, CafeSetting $cafe, string $cashier, string $method, int $received, int $change): array
    {
        $snapshot = [
            'cafe' => ['name' => $cafe->name, 'currency' => $cafe->currency ?? 'IDR', 'address' => $cafe->address, 'phone' => $cafe->phone, 'logo_path' => $cafe->logo_path, 'thank_you_message' => $cafe->thank_you_message],
            'order_number' => $order->number,
            'created_at' => $order->created_at?->toIso8601String(),
            'cashier' => $cashier,
            'table' => $order->table?->name,
            'items' => $order->items->map(fn ($item) => ['name' => $item->name_snapshot, 'size' => $item->size, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'line_total' => $item->line_total])->values()->all(),
            'total' => (int) $order->items->sum('line_total'),
            'payment_method' => $method,
            'received_amount' => $received,
            'change_amount' => $change,
        ];
        $snapshot['escpos_base64'] = base64_encode($this->render($snapshot));

        return $snapshot;
    }

    public function render(array $receipt): string
    {
        $data = "\x1B@\x1Ba\x01\x1B!\x10".$this->line($receipt['cafe']['name'] ?? 'Cafe')."\x1B!\x00";
        foreach ([$receipt['cafe']['address'] ?? null, $receipt['cafe']['phone'] ?? null] as $line) {
            if ($line) {
                $data .= $this->line($line);
            }
        }
        if (! empty($receipt['cafe']['logo_path'])) {
            $data .= $this->logo($receipt['cafe']['logo_path']);
        }
        $data .= $this->line(str_repeat('-', 32));
        $data .= $this->line('Order: '.$receipt['order_number']);
        $data .= $this->line('Waktu: '.($receipt['created_at'] ?? '-'));
        $data .= $this->line('Kasir: '.($receipt['cashier'] ?? '-'));
        $data .= $this->line('Meja: '.($receipt['table'] ?? '-'));
        $data .= $this->line(str_repeat('-', 32));
        foreach ($receipt['items'] as $item) {
            $data .= $this->line($item['name'].' ('.$item['size'].')');
            $data .= $this->line($item['quantity'].' x '.$this->money($item['unit_price'], $receipt['cafe']['currency'] ?? 'IDR').' = '.$this->money($item['line_total'], $receipt['cafe']['currency'] ?? 'IDR'));
        }
        $data .= $this->line(str_repeat('-', 32));
        $data .= "\x1B!\x10".$this->line('TOTAL '.$this->money($receipt['total'], $receipt['cafe']['currency'] ?? 'IDR'))."\x1B!\x00";
        $data .= $this->line('BAYAR '.$this->method($receipt['payment_method']));
        $data .= $this->line('DITERIMA '.$this->money($receipt['received_amount'], $receipt['cafe']['currency'] ?? 'IDR'));
        $data .= $this->line('KEMBALIAN '.$this->money($receipt['change_amount'], $receipt['cafe']['currency'] ?? 'IDR'));
        $data .= "\x1Ba\x01".$this->line($receipt['cafe']['thank_you_message'] ?? 'Terima kasih.');

        return $data."\x1Bd\x03\x1Bm";
    }

    private function line(string $value): string
    {
        return (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value)."\n";
    }

    private function money(int $value, string $currency): string
    {
        return match ($currency) {
            'USD' => '$'.number_format($value, 0, '.', ','),
            'SGD' => 'S$'.number_format($value, 0, '.', ','),
            'MYR' => 'RM '.number_format($value, 0, '.', ','),
            default => 'Rp '.number_format($value, 0, ',', '.'),
        };
    }

    private function method(string $method): string
    {
        return $method === 'qris_manual' ? 'QRIS MANUAL' : 'TUNAI';
    }

    private function logo(string $path): string
    {
        $contents = Storage::disk('public')->get($path);
        $image = $contents ? @imagecreatefromstring($contents) : false;
        if (! $image) {
            return '';
        }
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $width = min(384, $sourceWidth);
        $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
        $resized = imagecreatetruecolor($width, $height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        $bytesPerRow = (int) ceil($width / 8);
        $raster = '';
        for ($y = 0; $y < $height; $y++) {
            for ($byte = 0; $byte < $bytesPerRow; $byte++) {
                $value = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = ($byte * 8) + $bit;
                    if ($x >= $width) {
                        continue;
                    }
                    $rgb = imagecolorat($resized, $x, $y);
                    $gray = (($rgb >> 16 & 255) * 299 + (($rgb >> 8) & 255) * 587 + ($rgb & 255) * 114) / 1000;
                    if ($gray < 160) {
                        $value |= 1 << (7 - $bit);
                    }
                }
                $raster .= chr($value);
            }
        }
        imagedestroy($image);
        imagedestroy($resized);

        return "\x1D\x76\x30\x00".chr($bytesPerRow & 255).chr(($bytesPerRow >> 8) & 255).chr($height & 255).chr(($height >> 8) & 255).$raster;
    }
}
