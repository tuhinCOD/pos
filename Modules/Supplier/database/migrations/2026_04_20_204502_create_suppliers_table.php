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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', length: 100);
            $table->string('contact', length: 14)->unique();
            $table->string('email', length: 100)->unique()->nullable(true);
            $table->string('address', length: 500)->nullable(true);
            $table->foreignId('city_id')
            ->nullable(true)
            ->constrained('cities');
            $table->foreignId('user_id')
            ->constrained('users');
            $table->foreignId('updated_by')
            ->nullable(true)
            ->constrained('users');
            $table->string('remarks', length: 500)->nullable(true);
            $table->timestamps();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('name');
            $table->index('contact');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
