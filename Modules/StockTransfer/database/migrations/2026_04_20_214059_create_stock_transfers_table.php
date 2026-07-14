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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_from')
            ->constrained('branches');
            $table->foreignId('branch_to')
            ->constrained('branches');
            $table->foreignId('status_id')
            ->constrained('statuses');
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('sale_id')
            ->constrained('sales')
            ->cascadeOnDelete();
            $table->foreignId('product_id')
            ->constrained('products')
            ->restrictOnDelete();
            $table->decimal('qty', '10', '3');
            $table->decimal('shipping_cost', '10', '3');
            $table->string('remarks', length: 500)->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
