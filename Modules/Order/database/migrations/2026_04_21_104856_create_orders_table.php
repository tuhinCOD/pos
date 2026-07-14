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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', length: 32);
            $table->foreignId('status_id')
            ->constrained('statuses');
            $table->foreignId('client_id')
            ->constrained('users');
            $table->foreignId('product_id')
            ->constrained('products')
            ->restrictOnDelete();
            $table->foreignId('product_price_id')
            ->constrained('product_prices');
            $table->foreignId('unit_id')
            ->constrained('units');
            $table->foreignId('user_id')
            ->nullable(true)
            ->constrained('users');
            $table->decimal('shipping_fee', '8', '3');
            $table->decimal('qty', 10, 2);
            $table->decimal('price', 10, 2);
            $table->decimal('vat', 10, 2);
            $table->decimal('discount', 9, 2);
            $table->string('attributes', length:500)->nullable(true);
            $table->string('shipping_name', 150);
            $table->string('shipping_contact', 14);
            $table->foreignId('shipping_city_id')->constrained('cities');
            $table->string('shipping_address', 500);
            $table->string('note', 500)->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
