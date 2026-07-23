<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cafe_settings', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('address')->nullable(); $table->string('phone')->nullable(); $table->string('logo_path')->nullable(); $table->string('thank_you_message')->nullable(); $table->timestamps();
        });
        Schema::create('tables', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->enum('status', ['available', 'occupied'])->default('available'); $table->timestamps();
        });
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('menu_category_id')->constrained()->restrictOnDelete(); $table->string('name'); $table->boolean('is_available')->default(true); $table->timestamps();
        });
        Schema::create('menu_item_sizes', function (Blueprint $table) {
            $table->id(); $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete(); $table->enum('size', ['Regular', 'Large']); $table->unsignedInteger('price'); $table->unsignedInteger('on_hand')->default(0); $table->unsignedInteger('reserved')->default(0); $table->unsignedInteger('low_stock_threshold')->default(0); $table->unique(['menu_item_id', 'size']);
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->string('number')->unique(); $table->foreignId('user_id')->constrained(); $table->foreignId('table_id')->constrained('tables'); $table->enum('status', ['draft', 'awaiting_payment', 'paid', 'expired'])->default('draft'); $table->unsignedInteger('total')->default(0); $table->timestamp('payment_expires_at')->nullable(); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->foreignId('menu_item_id')->constrained(); $table->string('name_snapshot'); $table->enum('size', ['Regular', 'Large']); $table->unsignedInteger('unit_price'); $table->unsignedInteger('quantity'); $table->unsignedInteger('line_total'); $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete(); $table->enum('method', ['cash', 'qris_manual']); $table->unsignedInteger('amount'); $table->unsignedInteger('received_amount'); $table->unsignedInteger('change_amount')->default(0); $table->string('idempotency_key'); $table->timestamp('paid_at')->nullable(); $table->timestamps(); $table->unique(['order_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments'); Schema::dropIfExists('order_items'); Schema::dropIfExists('orders'); Schema::dropIfExists('menu_item_sizes'); Schema::dropIfExists('menu_items'); Schema::dropIfExists('menu_categories'); Schema::dropIfExists('tables'); Schema::dropIfExists('cafe_settings');
    }
};
