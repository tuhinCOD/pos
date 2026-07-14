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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', length: 100);
            $table->foreignId('parent_id')
            ->nullable()
            ->constrained('categories')
            ->cascadeOnDelete();
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users');
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
