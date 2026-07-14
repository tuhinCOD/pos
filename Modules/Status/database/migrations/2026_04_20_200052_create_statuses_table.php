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
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', length: 100);
            $table->foreignId('parent_id')
            ->nullable()
            ->constrained('statuses')
            ->cascadeOnDelete();
            $table->string('description', length: 500)->nullable(true);
            $table->timestamps();
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
