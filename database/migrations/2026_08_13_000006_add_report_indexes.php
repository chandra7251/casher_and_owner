<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'payment_expires_at'], 'orders_status_expires_index');
            $table->index(['status', 'created_at'], 'orders_status_created_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->index('paid_at', 'payments_paid_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_status_expires_index'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_status_created_index'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_paid_index'));
    }
};
