<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('template_id')->constrained();
            $table->foreignId('counterparty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status', 20);          // draft | issued | signed | cancelled
            $table->jsonb('payload');              // дані звіту/документа
            $table->decimal('amount', 14, 2)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['counterparty_id', 'created_at']);
            $table->index(['template_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
