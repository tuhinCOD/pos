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
        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_id')
            ->constrained('damages')
            ->cascadeOnDelete();
            $table->foreignId('status_id')
            ->constrained('statuses');
            $table->foreignId('branch_id')
            ->constrained('branches');
            $table->foreignId('product_id')
            ->constrained('products')
            ->restrictOnDelete();
            $table->foreignId('unit_id')
            ->constrained('units');
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable(true)
            ->constrained('users');
            $table->string('repair_shop', length: 100)->nullable(true);
            $table->decimal('qty', '8', '2');
            $table->decimal('repair_cost', '10', '2')->nullable(true);
            $table->string('remarks', length:500)->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
