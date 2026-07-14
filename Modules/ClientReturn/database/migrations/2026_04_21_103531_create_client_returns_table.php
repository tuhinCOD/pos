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
        Schema::create('client_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
            ->constrained('products')
            ->restrictOnDelete();
            $table->foreignId('unit_id')
            ->constrained('units');
            $table->foreignId('branch_id')
            ->constrained('branches');
            $table->foreignId('sale_id')
            ->constrained('sales')
            ->cascadeOnDelete();
            $table->foreignId('status_id')
            ->constrained('statuses');
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable(true)
            ->constrained('users');
            $table->foreignId('product_unit_id')
            ->constrained('units');
            $table->decimal('product_unit_qty', '10', '2')
            ->nullable();
            $table->decimal('qty', '10', '2');
            $table->json('attributes')->nullable(true);
            $table->text('remarks')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_returns');
    }
};
