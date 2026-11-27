<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique(); // ЄДРПОУ / ІПН
            $table->string('type', 20)->index();  // company | individual
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('city', 100)->index();
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparties');
    }
};
