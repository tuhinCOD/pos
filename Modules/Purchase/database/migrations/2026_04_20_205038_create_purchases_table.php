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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', length: 16);
            $table->foreignId('status_id')
            ->constrained('statuses');
            $table->foreignId('supplier_id')
            ->nullable(true)
            ->constrained('suppliers');
            $table->foreignId('branch_id')
            ->constrained('branches');
            $table->foreignId('product_unit_id')
            ->constrained('units')
            ->restrictOnDelete();
            $table->decimal('product_unit_qty', 8, 2)->nullable();
            $table->foreignId('product_id')
            ->constrained('products')
            ->restrictOnDelete();
            $table->foreignId('unit_id')
            ->constrained('units')
            ->nullOnDelete();
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable(true)
            ->constrained('users');
            $table->decimal('qty', 8, 2);
            $table->decimal('price', 10, 2);
            $table->decimal('vat', 10, 2);
            $table->decimal('discount', 9, 2)->nullable(true);
            $table->json('attributes')->nullable(true);
            $table->string('remarks', length: 500)->nullable(true);
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('invoice_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
