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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
            ->constrained('users');
            $table->enum('currency', ['BDT', 'USD']);
            $table->foreignId('payment_method_id')
            ->constrained('payment_methods');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_type', ['sale', 'purchase', 'order']);
            $table->foreignId('status_id')
            ->constrained('statuses');
            $table->string('payment_invoice_no', length: 16);
            $table->string('trx_id')->unique()->nullable(true);
            $table->date('payment_date');
            $table->string('payer_no', length: 14)->nullable(true);
            $table->json('attributes')->nullable(true);
            $table->string('note', length:500)->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
