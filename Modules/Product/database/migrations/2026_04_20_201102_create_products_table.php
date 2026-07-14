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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', length: 36)->unique();
            $table->string('barcode_type', 20)->default('single')->after('barcode');
            $table->string('name', length:150);
            $table->foreignId('category_id')
            ->constrained('categories');
            $table->foreignId('unit_id')
            ->constrained('units')
            ->restrictOnDelete();
            $table->foreignId('status_id')
            ->constrained('statuses');
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable(true)
            ->constrained('users');
            $table->json('attributes')->nullable(true);
            $table->string('description', length: 500)->nullable(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('barcode');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
