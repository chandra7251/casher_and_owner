<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentStockTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_orders_cannot_reserve_same_stock(): void
    {
        $users = [
            User::factory()->create(['role' => 'cashier']),
            User::factory()->create(['role' => 'cashier']),
        ];
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create();
        $size = MenuItemSize::create(['menu_item_id' => $item->id, 'size' => 'Regular', 'price' => 10000, 'on_hand' => 1, 'reserved' => 0, 'low_stock_threshold' => 0]);
        $directory = storage_path('framework/testing/concurrent-'.str()->random(12));
        mkdir($directory, 0777, true);
        $startPath = $directory.'/start';
        $payload = ['table_id' => $table->id, 'items' => [['product_id' => $item->id, 'size' => 'Regular', 'quantity' => 1]]];
        $workers = [];

        foreach ($users as $index => $user) {
            $readyPath = $directory.'/worker-'.$index;
            $workers[] = new Process([
                'php', base_path('tests/Support/concurrent_order_worker.php'), (string) $user->id,
                json_encode($payload, JSON_THROW_ON_ERROR), $readyPath, $startPath,
            ], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'cafe_pos_test']);
            $workers[$index]->start();
        }

        foreach ([0, 1] as $index) {
            $this->waitForFile($directory.'/worker-'.$index);
        }
        file_put_contents($startPath, 'go');
        foreach ($workers as $worker) {
            $worker->wait();
        }

        $results = collect([0, 1])->map(fn ($index) => json_decode(file_get_contents($directory.'/worker-'.$index.'.result'), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(1, $results->where('status', 201)->count());
        $this->assertSame(1, $results->where('status', 422)->count());
        $this->assertDatabaseHas('menu_item_sizes', ['id' => $size->id, 'on_hand' => 1, 'reserved' => 1]);

        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 15;
        while (! file_exists($path) && microtime(true) < $deadline) {
            usleep(100000);
        }
        $this->assertFileExists($path);
    }
}
