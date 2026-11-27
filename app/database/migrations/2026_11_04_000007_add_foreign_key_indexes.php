<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL не створює індекси для зовнішніх ключів автоматично.
 * Без індексу order_items.order_id завантаження позицій замовлення (eager loading)
 * перетворюється на послідовне сканування ~500 тис. рядків. Виявлено першим прогоном k6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
