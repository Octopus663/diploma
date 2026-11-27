<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('counterparty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);          // new | processing | completed | cancelled
            $table->date('ordered_at');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('currency', 3)->default('UAH');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['counterparty_id', 'ordered_at']);
            $table->index(['status', 'ordered_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('total', 14, 2);

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
