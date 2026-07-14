<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
            ->constrained('branches');
            $table->foreignId('product_id')
            ->constrained('products')
            ->restrictOnDelete();
            $table->foreignId('unit_id')
            ->constrained('units');
            $table->foreignId('level_id')
            ->constrained('levels');
            $table->morphs('level_specific');
            $table->decimal('previous_qty', 10, 2);
            $table->decimal('current_qty', 10, 2);
            $table->decimal('stock_qty', 10, 2);
            $table->json('attributes')->nullable(true);
            $table->string('remarks', length: 500)->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
